<?php
declare(strict_types=1);

/**
 * Защита форм админки от отправки со стороннего сайта.
 * Токен один на сессию — этого достаточно и не ломает работу
 * в нескольких вкладках.
 */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(self::token()) . '">';
    }

    public static function isValid(?string $token): bool
    {
        $expected = $_SESSION['csrf_token'] ?? '';

        return $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }

    /** Проверяет токен для изменяющих запросов и прерывает выполнение при несовпадении. */
    public static function verify(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }

        if (!self::isValid($_POST['_token'] ?? null)) {
            // 419 отдавать нельзя: код нестандартный, Apache подменяет его на 500
            http_response_code(403);
            exit(at('Форма устарела. Обновите страницу и повторите действие.'));
        }
    }
}
