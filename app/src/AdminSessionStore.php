<?php
declare(strict_types=1);

/**
 * Сессия админки: где лежит, сколько живёт и как её не потерять.
 *
 * Ту же сессию читает и публичная часть — по ней сайт узнаёт вошедшего
 * и показывает ему панель администратора. Поэтому настройка одна на всех
 * и вынесена сюда: разойдись они, панель просто пропала бы с сайта.
 */
final class AdminSessionStore
{
    /** Сколько сессия живёт без действий редактора. */
    public const IDLE_LIMIT = 28800;

    /** Сколько живёт вообще — даже если работать не переставая. */
    public const ABSOLUTE_LIMIT = 604800;

    /**
     * Открывает сессию админки. Метод статический: та же сессия читается
     * и на публичной части — по ней сайт узнаёт вошедшего и показывает ему
     * панель администратора.
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off'
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        /*
         * PHP сам убирает файлы сессий, к которым не обращались
         * session.gc_maxlifetime — по умолчанию 24 минуты. Пока это меньше
         * нашего лимита простоя, вошедшего выбрасывает намного раньше срока,
         * который админка обещает. Поэтому срок уборки равен нашему.
         */
        ini_set('session.gc_maxlifetime', (string) self::IDLE_LIMIT);

        // Сессию с придуманным номером не поднимаем — только со своей
        ini_set('session.use_strict_mode', '1');

        /*
         * На общем хостинге сессии всех сайтов лежат в одном каталоге, и чужая
         * уборка с коротким сроком чистит наши файлы заодно. Свой каталог —
         * вне веб-корня, поэтому снаружи он недоступен.
         *
         * Не вышло (нет прав, запрещена функция) — остаёмся на общем: вход
         * будет работать, просто с чужим сроком уборки.
         */
        $store = APP_DIR . '/sessions';

        /*
         * 0700 — чтобы файлы сессий не читались соседями по серверу: на
         * shared-хостинге PHP работает от владельца сайта, и прав хватает.
         * Локально код смонтирован от другого пользователя, каталог тогда
         * создаётся с правами окружения — на боевой машине это не так.
         */
        if (function_exists('session_save_path')
            && (is_dir($store) || @mkdir($store, 0700, true))
            && is_writable($store)
        ) {
            self::protectStore($store);
            session_save_path($store);

            /*
             * Часть хостингов отключает уборку внутри PHP и чистит свой общий
             * каталог по расписанию системы. В своём каталоге такой уборки
             * не будет, и файлы копились бы до упора в квоту, поэтому здесь
             * включаем её сами.
             */
            if ((int) ini_get('session.gc_probability') === 0) {
                ini_set('session.gc_probability', '1');
                ini_set('session.gc_divisor', '100');
            }
        }

        session_name('kulager_admin');
        session_set_cookie_params([
            'lifetime' => self::IDLE_LIMIT,
            'path'     => '/',
            'httponly' => true,
            'secure'   => $https,
            'samesite' => 'Strict',
        ]);
        session_start();

        /*
         * Срок куки браузер отсчитывает от её выдачи, а не от последнего
         * действия. Вошедшему выдаём её заново на каждой странице: пока
         * человек работает, вход не обрывается на ровном месте.
         */
        if (($_SESSION['user_id'] ?? 0) > 0 && !headers_sent()) {
            setcookie(session_name(), session_id(), [
                'expires'  => time() + self::IDLE_LIMIT,
                'path'     => '/',
                'httponly' => true,
                'secure'   => $https,
                'samesite' => 'Strict',
            ]);
        }
    }

    /**
     * Закрывает каталог сессий от веба.
     *
     * Обычно app лежит выше корня сайта и снаружи недоступен, но раскладка
     * бывает и плоской — тогда единственной защитой остаётся этот файл.
     */
    private static function protectStore(string $dir): void
    {
        $guard = $dir . '/.htaccess';

        if (!is_file($guard)) {
            @file_put_contents($guard, "Require all denied\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
        }
    }

    /** Есть ли вообще хоть один пользователь — от этого зависит первичная установка. */
}
