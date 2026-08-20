<?php
declare(strict_types=1);

/**
 * Заявки с сайта: приём, хранение, уведомление в телеграм.
 *
 * Порядок важен: сначала запись в базу, потом отправка. Телеграм может быть
 * недоступен, токен — просрочен, хостинг — без внешних запросов; заявка
 * при этом не должна пропадать.
 */
final class Leads
{
    /** Сколько заявок принимаем с одного адреса за час. */
    private const RATE_LIMIT = 5;

    /** Быстрее этого форму заполняет только робот. */
    private const MIN_SECONDS = 3;

    /**
     * Пауза перед второй попыткой достучаться до телеграма, микросекунды.
     * Полсекунды хватает, чтобы пережить моргнувшую сеть, и посетитель
     * такой задержки при отправке формы не замечает.
     */
    private const RETRY_PAUSE = 500000;

    /** @var list<string> адреса прокси, которым верим про адрес посетителя */
    private array $trustedProxies;

    /**
     * @param list<string> $trustedProxies из app/config.php; пусто — прокси нет
     */
    public function __construct(private Db $db, private Settings $settings, array $trustedProxies = [])
    {
        $this->trustedProxies = $trustedProxies;
    }

    public function isReady(): bool
    {
        return $this->db->isAvailable() && $this->db->tableExists('leads');
    }

    /**
     * Принимает данные формы.
     *
     * @return array{ok:bool,error?:string,id?:int} что показать посетителю
     */
    public function accept(array $input, string $page, string $locale): array
    {
        if (!$this->isReady()) {
            return ['ok' => false, 'error' => 'Форма временно не работает. Напишите нам в WhatsApp.'];
        }

        // Ловушка для роботов: поле спрятано от людей, они его не заполняют
        if (trim((string) ($input['company'] ?? '')) !== '') {
            return ['ok' => true];
        }

        /*
         * Время открытия формы проставляет скрипт, а не сервер: страницы
         * кэшируются, и одно и то же серверное время досталось бы всем.
         * Если скрипта нет, проверку пропускаем — остаются ловушка и лимит.
         */
        $started = (int) ($input['started'] ?? 0);

        if ($started > 0 && time() - $started < self::MIN_SECONDS) {
            return ['ok' => true];
        }

        if (!$this->signatureValid((string) ($input['sign'] ?? ''))) {
            return ['ok' => false, 'error' => 'Форма устарела — обновите страницу и попробуйте снова.'];
        }

        $name = $this->clean($input['name'] ?? '', 191);
        $phone = $this->clean($input['phone'] ?? '', 64);
        $email = $this->clean($input['email'] ?? '', 191);
        $message = $this->clean($input['message'] ?? '', 2000);

        if ($name === '') {
            return ['ok' => false, 'error' => 'Как к вам обращаться?'];
        }

        if ($phone === '' && $email === '') {
            return ['ok' => false, 'error' => 'Оставьте телефон или почту — иначе мы не сможем ответить.'];
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Проверьте адрес почты.'];
        }

        if ($this->tooOften()) {
            return ['ok' => false, 'error' => 'Заявка уже отправлена. Мы свяжемся с вами.'];
        }

        $id = $this->db->insert('leads', [
            'name'       => $name,
            'phone'      => $phone,
            'email'      => $email,
            'message'    => $message,
            'page'       => mb_substr($page, 0, 255),
            'locale'     => $locale,
            'ip'         => $this->ip(),
            'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->notify($id, [
            'name'    => $name,
            'phone'   => $phone,
            'email'   => $email,
            'message' => $message,
            'page'    => $page,
        ]);

        return ['ok' => true, 'id' => $id];
    }

    /* ------------------------------------------------------------ телеграм */

    /**
     * Отправляет заявку в телеграм и запоминает результат.
     * Ошибку не показываем посетителю: для него заявка принята.
     */
    private function notify(int $id, array $lead): void
    {
        $text = $this->format($lead);
        $error = $this->send($text);

        $this->db->update('leads', [
            'notified'     => $error === null ? 1 : 0,
            'notify_error' => mb_substr((string) $error, 0, 255),
        ], 'id = :id', ['id' => $id]);
    }

    /** @return string|null текст ошибки, либо null при успехе */
    public function send(string $text): ?string
    {
        $token = trim($this->settings->get('telegram_token', ''));
        $chat = trim($this->settings->get('telegram_chat', ''));

        if ($token === '' || $chat === '') {
            return 'Бот не настроен: нет токена или чата.';
        }

        $payload = http_build_query([
            'chat_id'    => $chat,
            'text'       => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => 'true',
        ]);

        $url = 'https://api.telegram.org/bot' . $token . '/sendMessage';

        /*
         * Связь обрывается и без чьей-либо вины: телеграм притормозил,
         * хостинг моргнул сетью. Заявка при этом остаётся с пометкой
         * «не ушло», и уведомление ждёт, пока кто-нибудь заглянет
         * в админку. Поэтому пробуем ещё раз, с короткой паузой.
         *
         * Повтор только при обрыве связи. Отказ самого телеграма
         * (неверный токен, чужой чат) повторять бессмысленно — ответ
         * будет тот же, а посетитель ждёт отправки формы.
         */
        $response = $this->request($url, $payload);

        if ($response === null) {
            usleep(self::RETRY_PAUSE);
            $response = $this->request($url, $payload);
        }

        if ($response === null) {
            return 'Не удалось связаться с телеграмом.';
        }

        $data = json_decode($response, true);

        if (!is_array($data) || empty($data['ok'])) {
            return mb_substr((string) ($data['description'] ?? 'Телеграм отклонил сообщение.'), 0, 200);
        }

        return null;
    }

    /**
     * Запрос к телеграму без данных — для служебных вызовов вроде getUpdates.
     * Транспорт тот же, что и у отправки: на хостинге бывает закрыто
     * либо одно, либо другое.
     */
    public function ask(string $url): ?string
    {
        return $this->request($url, '');
    }

    /**
     * Запрос к телеграму. cURL есть не везде, поэтому при его отсутствии
     * пробуем обычный поток — на shared-хостинге это обычная ситуация.
     */
    private function request(string $url, string $payload): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);

            curl_setopt_array($ch, [
                CURLOPT_POST           => $payload !== '',
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_CONNECTTIMEOUT => 4,
            ]);

            $response = curl_exec($ch);
            curl_close($ch);

            return is_string($response) ? $response : null;
        }

        $context = stream_context_create([
            'http' => [
                'method'        => $payload === '' ? 'GET' : 'POST',
                'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content'       => $payload,
                'timeout'       => 8,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        return is_string($response) ? $response : null;
    }

    /** Сообщение для телеграма: всё нужное видно без захода в админку. */
    private function format(array $lead): string
    {
        $lines = ['<b>Заявка с сайта KULAGER</b>', ''];
        $lines[] = 'Имя: ' . $this->escape($lead['name']);

        if ($lead['phone'] !== '') {
            $lines[] = 'Телефон: ' . $this->escape($lead['phone']);
        }

        if ($lead['email'] !== '') {
            $lines[] = 'Почта: ' . $this->escape($lead['email']);
        }

        if ($lead['message'] !== '') {
            $lines[] = '';
            $lines[] = $this->escape($lead['message']);
        }

        $lines[] = '';
        $lines[] = 'Страница: ' . $this->escape($lead['page'] === '' ? '/' : $lead['page']);
        $lines[] = 'Время: ' . date('d.m.Y H:i');

        return implode("\n", $lines);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_NOQUOTES, 'UTF-8');
    }

    /* -------------------------------------------------------------- защита */

    /**
     * Подпись формы вместо CSRF-токена: страницы кэшируются и отдаются без
     * сессии, поэтому токен из сессии здесь не годится.
     *
     * Подпись меняется раз в сутки — так она переживает кэш, но отправка
     * по подсмотренной когда-то форме перестаёт работать.
     */
    public static function signatureFor(string $secret, ?string $day = null): string
    {
        return hash_hmac('sha256', 'lead-form|' . ($day ?? date('Y-m-d')), $secret);
    }

    public function signature(): string
    {
        return self::signatureFor($this->secret());
    }

    private function signatureValid(string $sign): bool
    {
        $secret = $this->secret();

        // Вчерашняя подпись тоже подходит: страница могла быть открыта до полуночи
        foreach ([date('Y-m-d'), date('Y-m-d', time() - 86400)] as $day) {
            if (hash_equals(self::signatureFor($secret, $day), $sign)) {
                return true;
            }
        }

        return false;
    }

    private function secret(): string
    {
        $secret = $this->settings->get('form_secret', '');

        if ($secret === '') {
            $secret = bin2hex(random_bytes(16));
            $this->settings->set('form_secret', $secret);
        }

        return $secret;
    }

    private function tooOften(): bool
    {
        $count = (int) $this->db->value(
            'SELECT COUNT(*) FROM leads WHERE ip = :ip AND created_at > :since',
            ['ip' => $this->ip(), 'since' => date('Y-m-d H:i:s', time() - 3600)],
            0
        );

        return $count >= self::RATE_LIMIT;
    }

    private function clean(mixed $value, int $limit): string
    {
        $value = is_string($value) ? $value : '';
        $value = preg_replace('~[\x00-\x08\x0B\x0C\x0E-\x1F]~u', '', $value) ?? '';

        return mb_substr(trim(strip_tags($value)), 0, $limit);
    }

    private function ip(): string
    {
        return client_ip($this->trustedProxies);
    }

    /* -------------------------------------------------------------- админка */

    /** @return list<array> */
    public function recent(string $status = '', int $limit = 100): array
    {
        if (!$this->isReady()) {
            return [];
        }

        $sql = 'SELECT * FROM leads';
        $params = [];

        if ($status !== '') {
            $sql .= ' WHERE status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY created_at DESC LIMIT ' . max(1, min(500, $limit));

        return $this->db->all($sql, $params);
    }

    public function countNew(): int
    {
        return $this->isReady()
            ? (int) $this->db->value("SELECT COUNT(*) FROM leads WHERE status = 'new'", [], 0)
            : 0;
    }

    public function setStatus(int $id, string $status): void
    {
        if (!in_array($status, ['new', 'in_work', 'done', 'spam'], true)) {
            return;
        }

        $this->db->update('leads', ['status' => $status], 'id = :id', ['id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->db->delete('leads', 'id = :id', ['id' => $id]);
    }
}
