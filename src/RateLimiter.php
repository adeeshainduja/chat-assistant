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

        /*
         * Occasionally clean old rate-limit files.
         *
         * 1 in every 100 requests will run cleanup.
         */
        if (random_int(1, 100) === 1) {
            self::cleanup($dir);
        }

        $file = $dir . '/' . hash('sha256', $key) . '.json';

        $now = time();

        $state = [
            'window_start' => $now,
            'count' => 0,
        ];

        if (is_file($file)) {

            $existing = json_decode(
                (string) file_get_contents($file),
                true
            );

            if (is_array($existing)) {
                $state = array_merge(
                    $state,
                    $existing
                );
            }
        }

        /*
         * Reset rate-limit window.
         */
        if (
            ($now - (int) $state['window_start'])
            >= $windowSeconds
        ) {

            $state = [
                'window_start' => $now,
                'count' => 0,
            ];
        }

        /*
         * Too many requests.
         */
        if (
            (int) $state['count']
            >= $maxRequests
        ) {
            return false;
        }

        /*
         * Count current request.
         */
        $state['count']++;

        @file_put_contents(
            $file,
            json_encode($state),
            LOCK_EX
        );

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