<?php
declare(strict_types=1);

final class GeminiClient
{
    public function generateContent(array $payload): array
    {
        $apiKey = trim((string) Env::get('GEMINI_API_KEY', ''));

        if ($apiKey === '') {
            throw new RuntimeException('GEMINI_API_KEY is not configured.');
        }

        $model = trim((string) Env::get('GEMINI_MODEL', ''));
        if ($model === '') {
            $model = 'gemini-3.8-flash';
        }
        $model = ltrim($model, '/');
        if (str_starts_with($model, 'models/')) {
            $model = substr($model, 7);
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent';

        $jsonPayload = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($jsonPayload === false) {
            throw new RuntimeException('Failed to encode JSON payload for Gemini.');
        }

        $maxAttempts = 3;
        $attempt = 0;
        $body = false;
        $curlError = '';
        $status = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;

            $ch = curl_init($url);

            $curlOptions = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'x-goog-api-key: ' . $apiKey,
                ],
                CURLOPT_POSTFIELDS => $jsonPayload,
            ];

            $caBundle = trim((string) Env::get('CURL_CA_BUNDLE', Env::get('SSL_CERT_FILE', '')));
            if ($caBundle === '') {
                $iniCa = trim((string) (ini_get('curl.cainfo') ?: ini_get('openssl.cafile')));
                if ($iniCa !== '' && file_exists($iniCa)) {
                    $caBundle = $iniCa;
                } else {
                    $candidates = [
                        'C:/xampp/php/extras/ssl/cacert.pem',
                        'C:/xampp/perl/vendor/lib/Mozilla/CA/cacert.pem',
                        'C:/xampp/phpMyAdmin/vendor/composer/ca-bundle/res/cacert.pem',
                    ];
                    foreach ($candidates as $cand) {
                        if (file_exists($cand)) {
                            $caBundle = $cand;
                            break;
                        }
                    }
                }
            }

            if ($caBundle !== '' && file_exists($caBundle)) {
                $curlOptions[CURLOPT_CAINFO] = $caBundle;
            }

            curl_setopt_array($ch, $curlOptions);

            $body = curl_exec($ch);
            $curlError = (string) curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

            curl_close($ch);

            // Retry on temporary server errors / high demand / rate limit
            if (in_array($status, [429, 500, 502, 503, 504], true) && $attempt < $maxAttempts) {
                sleep($status === 429 ? 3 : 1);
                continue;
            }

            break;
        }

        if ($body === false) {
            $safeError = str_replace($apiKey, '[REDACTED]', $curlError);
            throw new RuntimeException('Gemini connection failed: ' . $safeError);
        }

        $decoded = json_decode($body, true);

        if ($status < 200 || $status >= 300) {
            $message = is_array($decoded)
                ? (string) ($decoded['error']['message'] ?? 'Gemini request failed.')
                : 'Gemini request failed.';

            $safeMessage = str_replace($apiKey, '[REDACTED]', $message);

            throw new RuntimeException(
                'Gemini API error (' . $status . '): ' . $safeMessage
            );
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Gemini returned invalid JSON.');
        }

        return $decoded;
    }

    /**
     * Alias for generateContent
     */
    public function createInteraction(array $payload): array
    {
        return $this->generateContent($payload);
    }
}
