<?php
declare(strict_types=1);

final class RateLimiter
{
    public static function allow(
        string $key,
        int $maxRequests = 30,
        int $windowSeconds = 60
    ): bool {
        $dir = APP_ROOT . '/storage/ratelimit';

        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }

        $file = $dir . '/' . hash('sha256', $key) . '.json';
        $now = time();

        $state = [
            'window_start' => $now,
            'count' => 0,
        ];

        if (is_file($file)) {
            $existing = json_decode((string) file_get_contents($file), true);
            if (is_array($existing)) {
                $state = array_merge($state, $existing);
            }
        }

        if (($now - (int) $state['window_start']) >= $windowSeconds) {
            $state = [
                'window_start' => $now,
                'count' => 0,
            ];
        }

        if ((int) $state['count'] >= $maxRequests) {
            return false;
        }

        $state['count']++;

        @file_put_contents(
            $file,
            json_encode($state),
            LOCK_EX
        );

        return true;
    }
}
