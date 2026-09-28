<?php
declare(strict_types=1);

final class GetmoreConnector
{
    public function __construct(private string $authToken)
    {
    }

    public function myClasses(): array
    {
        return $this->get('/api/ai/my-classes.php');
    }

    public function myAttendance(?string $date): array
    {
        $query = [];

        if ($date !== null && $date !== '') {
            $query['date'] = $date;
        }

        return $this->get('/api/ai/my-attendance.php', $query);
    }

    public function myTeachers(): array
    {
        return $this->get('/api/ai/teachers.php');
    }

    private function get(string $path, array $query = []): array
    {
        $baseUrl = rtrim((string) Env::get('GETMORE_BASE_URL', ''), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('GETMORE_BASE_URL is not configured.');
        }

        $url = $baseUrl . $path;

        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $this->authToken,
            ],
        ]);

        $body = curl_exec($ch);
        $curlError = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException(
                'GETMORE connector error: ' . $curlError
            );
        }

        $decoded = json_decode($body, true);

        if ($status < 200 || $status >= 300) {
            $message = is_array($decoded)
                ? (string) ($decoded['error'] ?? 'GETMORE API request failed.')
                : 'GETMORE API request failed.';

            throw new RuntimeException($message);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('GETMORE API returned invalid JSON.');
        }

        return $decoded;
    }
}
