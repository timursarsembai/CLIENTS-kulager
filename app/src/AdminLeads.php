<?php
declare(strict_types=1);

/**
 * Заявки с сайта и уведомления в телеграм.
 *
 * Заявка сначала сохраняется, и только потом уходит в телеграм: если бот
 * недоступен, обращение не потеряется, а в списке будет пометка.
 */
final class AdminLeads extends AdminSection
{
    public function leadRoutes(array $segments): void
    {
        $leads = new Leads($this->db, new Settings($this->db, (array) $this->config['contacts']),
            (array) ($this->config['trusted_proxies'] ?? []));
        $head = (string) ($segments[0] ?? '');

        if ($head === '') {
            $this->leadList($leads);

            return;
        }

        // Настройка уведомлений — дело администратора
        if (in_array($head, ['telegram', 'detect', 'drop-chat', 'test'], true)) {
            if (!$this->adminOnly()) {
                return;
            }

            match ($head) {
                'telegram'  => $this->leadTelegram(),
                'detect'    => $this->leadDetectChat($leads),
                'drop-chat' => $this->leadDropChat($leads),
                'test'      => $this->leadTestMessage($leads),
            };

            return;
        }

        $id = (int) $head;

        match ($segments[1] ?? '') {
            'status' => $this->leadStatus($leads, $id),
            'delete' => $this->leadDelete($leads, $id),
            'resend' => $this->leadResend($leads, $id),
            default  => $this->notFound(),
        };
    }

    private function leadList(Leads $leads): void
    {
        $status = (string) ($_GET['status'] ?? '');

        $this->render('leads', [
            'leads'    => $leads->recent($status),
            'status'   => $status,
            'counts'   => [
                'new'     => (int) $this->db->value("SELECT COUNT(*) FROM leads WHERE status = 'new'", [], 0),
                'in_work' => (int) $this->db->value("SELECT COUNT(*) FROM leads WHERE status = 'in_work'", [], 0),
                'done'    => (int) $this->db->value("SELECT COUNT(*) FROM leads WHERE status = 'done'", [], 0),
                'spam'    => (int) $this->db->value("SELECT COUNT(*) FROM leads WHERE status = 'spam'", [], 0),
            ],
            'telegram' => [
                'token' => trim((string) $this->settingsValue('telegram_token')) !== '',
                'chat'  => trim((string) $this->settingsValue('telegram_chat')),
                'chats' => $leads->chats(),
            ],
            'isAdmin' => $this->auth->isAdmin(),
        ], 'Заявки');
    }

    private function settingsValue(string $key): string
    {
        return (new Settings($this->db, (array) $this->config['contacts']))->get($key, '');
    }

    private function leadStatus(Leads $leads, int $id): void
    {
        if ($this->isPost()) {
            $leads->setStatus($id, (string) ($_POST['status'] ?? ''));
            $this->auth->log('lead_status', (string) $id, (string) ($_POST['status'] ?? ''));
        }

        $this->redirect('leads');
    }

    private function leadDelete(Leads $leads, int $id): void
    {
        if ($this->isPost()) {
            $leads->delete($id);
            $this->auth->log('lead_delete', (string) $id);
            $this->flash(at('Заявка удалена.'));
        }

        $this->redirect('leads');
    }

    /** Повторная отправка в телеграм — когда бот был не настроен или лежал. */
    private function leadResend(Leads $leads, int $id): void
    {
        if (!$this->isPost()) {
            $this->redirect('leads');

            return;
        }

        $lead = $this->db->first('SELECT * FROM leads WHERE id = :id', ['id' => $id]);

        if ($lead === null) {
            $this->redirect('leads');

            return;
        }

        $text = "<b>Заявка с сайта KULAGER</b>\n\n"
            . 'Имя: ' . htmlspecialchars((string) $lead['name'], ENT_NOQUOTES, 'UTF-8') . "\n"
            . ($lead['phone'] !== '' ? 'Телефон: ' . htmlspecialchars((string) $lead['phone'], ENT_NOQUOTES, 'UTF-8') . "\n" : '')
            . ($lead['email'] !== '' ? 'Почта: ' . htmlspecialchars((string) $lead['email'], ENT_NOQUOTES, 'UTF-8') . "\n" : '')
            . ($lead['message'] !== '' ? "\n" . htmlspecialchars((string) $lead['message'], ENT_NOQUOTES, 'UTF-8') . "\n" : '')
            . "\nСтраница: /" . htmlspecialchars((string) $lead['page'], ENT_NOQUOTES, 'UTF-8');

        $error = $leads->send($text);

        $this->db->update('leads', [
            'notified'     => $error === null ? 1 : 0,
            'notify_error' => mb_substr((string) $error, 0, 255),
        ], 'id = :id', ['id' => $id]);

        $this->flash($error === null ? at('Отправлено в телеграм.') : at('Не отправилось: %s', $error));
        $this->redirect('leads');
    }

    private function leadTelegram(): void
    {
        if (!$this->isPost()) {
            $this->redirect('leads');

            return;
        }

        $settings = new Settings($this->db, (array) $this->config['contacts']);

        $settings->save([
            'telegram_token' => (string) ($_POST['telegram_token'] ?? ''),
            'telegram_chat'  => (string) ($_POST['telegram_chat'] ?? ''),
        ]);

        $this->auth->log('lead_telegram_settings');
        $this->flash(at('Настройки бота сохранены.'));
        $this->redirect('leads');
    }

    /**
     * Определяет чат по последнему сообщению боту: так не приходится искать
     * идентификатор вручную — достаточно написать боту «/start».
     */
    private function leadDetectChat(Leads $leads): void
    {
        if (!$this->isPost()) {
            $this->redirect('leads');

            return;
        }

        $settings = new Settings($this->db, (array) $this->config['contacts']);
        $token = trim($settings->get('telegram_token', ''));

        if ($token === '') {
            $this->flash(at('Сначала сохраните токен бота.'));
            $this->redirect('leads');

            return;
        }

        $leads = new Leads($this->db, $settings, (array) ($this->config['trusted_proxies'] ?? []));
        $response = $leads->ask('https://api.telegram.org/bot' . $token . '/getUpdates');

        $data = is_string($response) ? json_decode($response, true) : null;

        if (!is_array($data) || empty($data['ok'])) {
            $this->flash(at('Телеграм не ответил: %s', (string) ($data['description'] ?? at('нет связи'))));
            $this->redirect('leads');

            return;
        }

        $chat = null;

        foreach (array_reverse((array) $data['result']) as $update) {
            $message = $update['message'] ?? $update['channel_post'] ?? null;

            if (is_array($message) && isset($message['chat']['id'])) {
                $chat = (string) $message['chat']['id'];
                break;
            }
        }

        if ($chat === null) {
            $this->flash(at('Не нашли ни одного сообщения. Напишите боту «/start» и повторите.'));
            $this->redirect('leads');

            return;
        }

        /*
         * Добавляем к списку, а не заменяем: раньше второй администратор,
         * нажав эту кнопку, молча переводил уведомления на себя и лишал
         * их первого.
         */
        $chats = $leads->chats();

        if (in_array($chat, $chats, true)) {
            $this->flash(at('Этот чат уже в списке: %s', $chat));
            $this->redirect('leads');

            return;
        }

        $chats[] = $chat;
        $settings->save(['telegram_chat' => implode(', ', $chats)]);
        $this->auth->log('lead_telegram_chat', $chat);
        $this->flash(at('Чат добавлен: %s', $chat));
        $this->redirect('leads');
    }

    /** Убирает один чат из списка — например, когда человек ушёл из компании. */
    private function leadDropChat(Leads $leads): void
    {
        if (!$this->isPost()) {
            $this->redirect('leads');

            return;
        }

        $drop = trim((string) ($_POST['chat'] ?? ''));
        $settings = new Settings($this->db, (array) $this->config['contacts']);
        $left = array_values(array_filter($leads->chats(), static fn (string $chat): bool => $chat !== $drop));

        $settings->save(['telegram_chat' => implode(', ', $left)]);
        $this->auth->log('lead_telegram_chat_drop', $drop);
        $this->flash($left === []
            ? at('Список пуст — заявки больше никуда не уходят.')
            : at('Чат убран: %s', $drop));
        $this->redirect('leads');
    }

    private function leadTestMessage(Leads $leads): void
    {
        if ($this->isPost()) {
            $error = $leads->send("<b>Проверка связи</b>\nЕсли вы видите это сообщение, заявки будут приходить сюда.");

            $this->flash($error === null ? at('Сообщение ушло — проверьте телеграм.') : at('Не отправилось: %s', $error));
        }

        $this->redirect('leads');
    }

    /* -------------------------------------------------------- пользователи */
}
