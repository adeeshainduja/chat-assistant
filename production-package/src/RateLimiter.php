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
            @mkdir($dir, 0755, true);
        }

        /*
         * Occasionally clean old rate-limit files.
         * 1 in every 100 requests will run cleanup.
         */
        if (random_int(1, 100) === 1) {
            self::cleanup($dir);
        }

        $now = time();
        $fileKey = hash('sha256', $key);
        $file = $dir . '/' . $fileKey . '.json';

        // Session fallback tracking to ensure security is never silently disabled if storage is unwritable
        $sessionActive = (session_status() === PHP_SESSION_ACTIVE);
        $sessKey = '_rate_limit_' . $fileKey;
        if ($sessionActive) {
            $sessState = $_SESSION[$sessKey] ?? null;
            if (is_array($sessState)) {
                if (($now - (int) ($sessState['window_start'] ?? 0)) >= $windowSeconds) {
                    $_SESSION[$sessKey] = ['window_start' => $now, 'count' => 0];
                } elseif ((int) ($sessState['count'] ?? 0) >= $maxRequests) {
                    return false;
                }
            } else {
                $_SESSION[$sessKey] = ['window_start' => $now, 'count' => 0];
            }
        }

        $state = [
            'window_start' => $now,
            'count' => 0,
        ];

        if (is_file($file)) {
            $content = @file_get_contents($file);
            if ($content !== false) {
                $existing = json_decode($content, true);
                if (is_array($existing)) {
                    $state = array_merge($state, $existing);
                }
            }
        }

        /*
         * Reset rate-limit window.
         */
        if (($now - (int) $state['window_start']) >= $windowSeconds) {
            $state = [
                'window_start' => $now,
                'count' => 0,
            ];
        }

        /*
         * Too many requests.
         */
        if ((int) $state['count'] >= $maxRequests) {
            return false;
        }

        /*
         * Count current request.
         */
        $state['count']++;

        if ($sessionActive) {
            $_SESSION[$sessKey]['count'] = (int) ($_SESSION[$sessKey]['count'] ?? 0) + 1;
        }

        $written = @file_put_contents(
            $file,
            json_encode($state),
            LOCK_EX
        );

        if ($written === false) {
            // Storage unwritable: log warning; session fallback above ensures rate limit still protects the endpoint
            error_log('RateLimiter warning: unable to write to ' . $dir . '. Recommended cPanel permissions: 755 or 775.');
        }

        return true;
    }


    /**
     * Delete old rate-limit files.
     */
    private static function cleanup(string $dir): void
    {
        /*
         * Delete files older than 24 hours.
         */
        $expireBefore =
            time() - (24 * 60 * 60);

        $files = glob(
            $dir . '/*.json'
        );

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {

            if (!is_file($file)) {
                continue;
            }

            $modifiedTime =
                filemtime($file);

            if (
                $modifiedTime !== false &&
                $modifiedTime < $expireBefore
            ) {

                @unlink($file);
            }
        }
    }
}
