<?php
declare(strict_types=1);

final class Token
{
    private static function base64UrlDecode(string $value): string|false
    {
        $value = strtr($value, '-_', '+/');
        $padding = strlen($value) % 4;

        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return base64_decode($value, true);
    }

    public static function verify(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 2) {
            return null;
        }

        [$payloadEncoded, $signatureEncoded] = $parts;

        $secret = Env::get('GETMORE_SHARED_SECRET', '');
        if ($secret === '') {
            throw new RuntimeException('GETMORE_SHARED_SECRET is not configured.');
        }

        $signature = self::base64UrlDecode($signatureEncoded);
        if ($signature === false) {
            return null;
        }

        $expected = hash_hmac('sha256', $payloadEncoded, $secret, true);

        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payloadJson = self::base64UrlDecode($payloadEncoded);
        if ($payloadJson === false) {
            return null;
        }

        $payload = json_decode($payloadJson, true);

        if (!is_array($payload)) {
            return null;
        }

        if (($payload['exp'] ?? 0) < time()) {
            return null;
        }

        if (($payload['aud'] ?? '') !== 'getmore-ai') {
            return null;
        }

        if (($payload['role'] ?? '') !== 'student') {
            return null;
        }

        if (!isset($payload['student_id']) || !is_numeric($payload['student_id'])) {
            return null;
        }

        return $payload;
    }

    public static function bearerFromHeaders(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (!preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }
}
