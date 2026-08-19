<?php
declare(strict_types=1);

/**
 * Одноразовые коды для входа (TOTP, RFC 6238) — те самые шестизначные числа
 * из Google Authenticator, Aegis или 1Password.
 *
 * Своя реализация, потому что зависимостей в проекте нет: алгоритм — это
 * HMAC-SHA1 от номера тридцатисекундного интервала, всё нужное есть в PHP.
 */
final class Totp
{
    /** Длина кода и шаг времени — как договорились все приложения-аутентификаторы. */
    private const DIGITS = 6;
    private const PERIOD = 30;

    /** Насколько расходятся часы телефона и сервера: ±1 шаг, то есть ±30 секунд. */
    private const WINDOW = 1;

    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Новый секрет: 160 бит, как рекомендует RFC. */
    public static function secret(): string
    {
        return self::base32encode(random_bytes(20));
    }

    /** Проверяет код, допуская небольшой сдвиг часов. */
    public static function verify(string $secret, string $code): bool
    {
        $code = preg_replace('~\D~', '', $code) ?? '';

        if (strlen($code) !== self::DIGITS) {
            return false;
        }

        $counter = (int) floor(time() / self::PERIOD);

        for ($shift = -self::WINDOW; $shift <= self::WINDOW; $shift++) {
            if (hash_equals(self::code($secret, $counter + $shift), $code)) {
                return true;
            }
        }

        return false;
    }

    /** Ссылка для приложения-аутентификатора. */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer)
            . '&digits=' . self::DIGITS
            . '&period=' . self::PERIOD;
    }

    /** Секрет группами по четыре знака — так его проще ввести руками. */
    public static function readable(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    private static function code(string $secret, int $counter): string
    {
        $key = self::base32decode($secret);

        if ($key === '') {
            return '';
        }

        $hash = hash_hmac('sha1', pack('N*', 0, $counter), $key, true);

        // Смещение берётся из младших четырёх бит последнего байта
        $offset = ord($hash[19]) & 0x0F;

        $part = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($part % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32encode(string $bytes): string
    {
        $bits = '';

        foreach (str_split($bytes) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';

        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
        }

        return $out;
    }

    private static function base32decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('~[^A-Z2-7]~i', '', $secret) ?? '');

        if ($secret === '') {
            return '';
        }

        $bits = '';

        foreach (str_split($secret) as $char) {
            $index = strpos(self::ALPHABET, $char);

            if ($index === false) {
                return '';
            }

            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $out = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $out .= chr(bindec($chunk));
            }
        }

        return $out;
    }
}
