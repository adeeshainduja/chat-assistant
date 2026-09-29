<?php
declare(strict_types=1);

final class AttendanceVerification
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const BLOCK_DURATION_SECONDS = 600; // 10 minutes
    private const SESSION_LIFETIME_SECONDS = 1800; // 30 minutes

    /**
     * Verify student public index number and parent mobile number against the database.
     *
     * @param string $studentIndexNumber Public Student ID / Index Number
     * @param string $parentMobileNumber Parent or guardian mobile number
     * @param int $instituteId Current institute ID
     * @return array ['verified' => bool]
     */
    public static function verify(
        string $studentIndexNumber,
        string $parentMobileNumber,
        int $instituteId
    ): array {
        self::ensureSession();

        // Check if temporarily blocked due to rate limit
        if (self::isBlocked()) {
            return ['verified' => false];
        }

        $rawIndex = trim($studentIndexNumber);
        $normIndex = mb_strtolower($rawIndex, 'UTF-8');
        $cleanIndex = preg_replace('/[^a-zA-Z0-9]/', '', $normIndex) ?? '';

        $normParentMobile = self::normalizeSriLankanMobile($parentMobileNumber);

        if ($cleanIndex === '' || $normParentMobile === '') {
            self::recordFailure();
            return ['verified' => false];
        }

        try {
            $pdo = GetmoreDatabase::connection();

            // Check if students table has institute_id, is_active, and student_reg_id columns
            $hasInstituteId = self::hasColumn($pdo, 'students', 'institute_id');
            $hasIsActive = self::hasColumn($pdo, 'students', 'is_active');
            $hasStudentRegId = self::hasColumn($pdo, 'students', 'student_reg_id');
            $hasGuardianHome = self::hasColumn($pdo, 'guardians', 'home_number');

            $sql = "
                SELECT
                    s.id AS student_id,
                    s.index_number,
                    " . ($hasStudentRegId ? "s.student_reg_id," : "NULL AS student_reg_id,") . "
                    g.phone AS guardian_phone,
                    " . ($hasGuardianHome ? "g.home_number AS guardian_home" : "NULL AS guardian_home") . "
                FROM students s
                INNER JOIN guardians g
                    ON g.student_id = s.id
                WHERE COALESCE(s.is_deleted, 0) = 0
            ";

            $params = [];

            if ($hasInstituteId) {
                $sql .= " AND s.institute_id = :institute_id";
                $params['institute_id'] = $instituteId;
            }

            if ($hasIsActive) {
                $sql .= " AND COALESCE(s.is_active, 1) = 1";
            }

            // Public Student ID / Index Number matching using prepared statements
            if ($hasStudentRegId) {
                $sql .= " AND (
                    LOWER(TRIM(s.index_number)) = :exact_index
                    OR LOWER(TRIM(COALESCE(s.student_reg_id, ''))) = :exact_index
                    OR REPLACE(REPLACE(LOWER(s.index_number), '-', ''), ' ', '') = :clean_index
                    OR REPLACE(REPLACE(LOWER(COALESCE(s.student_reg_id, '')), '-', ''), ' ', '') = :clean_index
                )";
            } else {
                $sql .= " AND (
                    LOWER(TRIM(s.index_number)) = :exact_index
                    OR REPLACE(REPLACE(LOWER(s.index_number), '-', ''), ' ', '') = :clean_index
                )";
            }

            $params['exact_index'] = $normIndex;
            $params['clean_index'] = $cleanIndex;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $matchedStudentId = null;

            foreach ($candidates as $row) {
                $dbIndex = mb_strtolower(trim((string) ($row['index_number'] ?? '')), 'UTF-8');
                $dbRegId = mb_strtolower(trim((string) ($row['student_reg_id'] ?? '')), 'UTF-8');
                $cleanDbIndex = preg_replace('/[^a-zA-Z0-9]/', '', $dbIndex) ?? '';
                $cleanDbRegId = preg_replace('/[^a-zA-Z0-9]/', '', $dbRegId) ?? '';

                $indexMatches = (
                    $normIndex === $dbIndex ||
                    ($dbRegId !== '' && $normIndex === $dbRegId) ||
                    $cleanIndex === $cleanDbIndex ||
                    ($cleanDbRegId !== '' && $cleanIndex === $cleanDbRegId)
                );

                if (!$indexMatches) {
                    continue;
                }

                // Verify parent mobile against guardian phone numbers in DB
                $dbGuardianPhone = self::normalizeSriLankanMobile((string) ($row['guardian_phone'] ?? ''));
                $dbGuardianHome = self::normalizeSriLankanMobile((string) ($row['guardian_home'] ?? ''));

                $mobileMatches = (
                    ($dbGuardianPhone !== '' && $normParentMobile === $dbGuardianPhone) ||
                    ($dbGuardianHome !== '' && $normParentMobile === $dbGuardianHome)
                );

                if ($mobileMatches) {
                    $matchedStudentId = (int) $row['student_id'];
                    break;
                }
            }

            if ($matchedStudentId !== null && $matchedStudentId > 0) {
                // Successful verification: save internal IDs SERVER-SIDE in session only
                $_SESSION['attendance_verified'] = true;
                $_SESSION['verified_student_id'] = $matchedStudentId;
                $_SESSION['verified_institute_id'] = $instituteId;
                $_SESSION['attendance_verified_at'] = time();

                // Clear failure counter
                unset($_SESSION['verification_failures']);

                return ['verified' => true];
            }

            // Verification failed
            self::recordFailure();
            return ['verified' => false];

        } catch (Throwable) {
            self::recordFailure();
            return ['verified' => false];
        }
    }

    /**
     * Check if the visitor's attendance session is verified for the given institute.
     */
    public static function isSessionVerified(int $instituteId): bool
    {
        self::ensureSession();

        if (
            empty($_SESSION['attendance_verified']) ||
            empty($_SESSION['verified_student_id']) ||
            empty($_SESSION['verified_institute_id']) ||
            (int) $_SESSION['verified_institute_id'] !== $instituteId ||
            empty($_SESSION['attendance_verified_at'])
        ) {
            return false;
        }

        $ttl = (int) (Env::get('ATTENDANCE_VERIFICATION_TTL', '1800') ?: self::SESSION_LIFETIME_SECONDS);
        $verifiedAt = (int) $_SESSION['attendance_verified_at'];
        if ((time() - $verifiedAt) > $ttl) {
            self::clearVerification();
            return false;
        }

        return true;
    }

    /**
     * Retrieve the verified student ID for the current institute.
     * Returns null if unverified or expired.
     */
    public static function getVerifiedStudentId(int $instituteId): ?int
    {
        if (!self::isSessionVerified($instituteId)) {
            return null;
        }

        return (int) $_SESSION['verified_student_id'];
    }

    /**
     * Clear all verified attendance session data.
     */
    public static function clearVerification(): void
    {
        self::ensureSession();

        unset(
            $_SESSION['attendance_verified'],
            $_SESSION['verified_student_id'],
            $_SESSION['verified_institute_id'],
            $_SESSION['attendance_verified_at']
        );
    }

    /**
     * Safely normalize Sri Lankan mobile numbers into a canonical 10-digit format (07XXXXXXXX).
     *
     * Handles formats:
     * - 0771234567
     * - +94771234567
     * - 0094771234567
     * - 94771234567
     * - 771234567
     * - 077-123-4567 / 077 123 4567
     */
    public static function normalizeSriLankanMobile(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0094')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '94')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        // Standard Sri Lankan numbers have 9 significant digits (e.g. 7XXXXXXXX)
        if (strlen($digits) === 9) {
            return '0' . $digits;
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            return $digits;
        }

        return $digits;
    }

    /**
     * Safely normalize text for name comparison.
     */
    public static function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name), 'UTF-8');
        // Remove punctuation and special characters
        $name = preg_replace('/[^\p{L}\p{N}\s]/u', '', $name) ?? '';
        // Collapse whitespace
        $name = preg_replace('/\s+/', ' ', $name) ?? '';

        return trim($name);
    }

    private static function isBlocked(): bool
    {
        if (isset($_SESSION['verification_failures']['blocked_until'])) {
            $blockedUntil = (int) $_SESSION['verification_failures']['blocked_until'];
            if (time() < $blockedUntil) {
                return true;
            }
            // Block expired, reset
            unset($_SESSION['verification_failures']);
        }

        return false;
    }

    private static function recordFailure(): void
    {
        if (!isset($_SESSION['verification_failures'])) {
            $_SESSION['verification_failures'] = [
                'count' => 0,
                'blocked_until' => 0,
            ];
        }

        $_SESSION['verification_failures']['count']++;

        if ($_SESSION['verification_failures']['count'] >= self::MAX_FAILED_ATTEMPTS) {
            $_SESSION['verification_failures']['blocked_until'] = time() + self::BLOCK_DURATION_SECONDS;
        }
    }

    private static function ensureSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            @session_start();
        }
    }

    private static function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        static $cache = [];
        $key = $table . '.' . $column;
        if (isset($cache[$key])) {
            return $cache[$key];
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([$table, $column]);
            $exists = ((int) $stmt->fetchColumn()) > 0;
            $cache[$key] = $exists;

            return $exists;
        } catch (Throwable) {
            return false;
        }
    }
}

