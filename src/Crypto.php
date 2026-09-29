<?php
declare(strict_types=1);

final class Crypto
{
    private static function getKey(): string
    {
        $rawKey = (string) (Env::get('APP_ENCRYPTION_KEY') ?: Env::get('GETMORE_SHARED_SECRET') ?: 'getmore_default_32byte_aes_key!');
        return hash('sha256', $rawKey, true); // Always 32 bytes for AES-256
    }

    public static function encrypt(string $plainText): string
    {
        if ($plainText === '') {
            return '';
        }

        $key = self::getKey();
        $iv = random_bytes(16);
        $cipherText = openssl_encrypt($plainText, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipherText === false) {
            throw new RuntimeException('Encryption failed.');
        }

        $hmac = hash_hmac('sha256', $iv . $cipherText, $key, true);

        return base64_encode($hmac . $iv . $cipherText);
    }

    public static function decrypt(?string $encrypted): ?string
    {
        if ($encrypted === null || trim($encrypted) === '') {
            return null;
        }

        $raw = base64_decode($encrypted, true);
        if ($raw === false || strlen($raw) < 49) { // 32 bytes hmac + 16 bytes iv + min 1 byte payload
            return null;
        }

        $key = self::getKey();
        $hmac = substr($raw, 0, 32);
        $iv = substr($raw, 32, 16);
        $cipherText = substr($raw, 48);

        $expectedHmac = hash_hmac('sha256', $iv . $cipherText, $key, true);
        if (!hash_equals($hmac, $expectedHmac)) {
            return null;
        }

        $decrypted = openssl_decrypt($cipherText, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $decrypted !== false ? $decrypted : null;
    }

    public static function mask(?string $secret): string
    {
        $secret = trim((string) $secret);
        if ($secret === '') {
            return '';
        }

        $len = strlen($secret);
        if ($len <= 4) {
            return str_repeat('•', 12);
        }

        return str_repeat('•', 12) . substr($secret, -4);
    }
}
