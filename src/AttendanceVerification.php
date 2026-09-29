<?php
declare(strict_types=1);

final class AttendanceVerification
{
    private const MAX_FAILED_ATTEMPTS = 5;
    private const BLOCK_DURATION_SECONDS = 600; // 10 minutes
    private const SESSION_LIFETIME_SECONDS = 1800; // 30 minutes

    /**
     * Verify student and parent/guardian full names against the database.
     *
     * @param string $studentName Full name of the student
     * @param string $parentName Full name of the parent/guardian
     * @param int $instituteId Current institute ID
     * @return array ['verified' => bool]
     */
    public static function verify(
        string $studentName,
        string $parentName,
        int $instituteId
    ): array {
        self::ensureSession();

        // Check if temporarily blocked due to rate limit
        if (self::isBlocked()) {
            return ['verified' => false];
        }

        $normStudent = self::normalize($studentName);
        $normParent = self::normalize($parentName);

        if ($normStudent === '' || $normParent === '') {
            self::recordFailure();
            return ['verified' => false];
        }

        try {
            $pdo = GetmoreDatabase::connection();

            // Check if students table has institute_id column
            $hasInstituteId = self::hasColumn($pdo, 'students', 'institute_id');

            $sql = "
                SELECT
                    s.id AS student_id,
                    s.first_name,
                    s.last_name,
                    g.name AS guardian_name
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

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $matchedStudentId = null;

            foreach ($candidates as $row) {
                $dbFirstName = self::normalize((string) ($row['first_name'] ?? ''));
                $dbLastName = self::normalize((string) ($row['last_name'] ?? ''));
                $dbFullName = trim($dbFirstName . ' ' . $dbLastName);
                $dbGuardianName = self::normalize((string) ($row['guardian_name'] ?? ''));

                // Student name match: matches full name, or matches single name if no last name
                $studentMatches = false;
                if ($normStudent === $dbFullName) {
                    $studentMatches = true;
                } elseif ($dbLastName === '' && $normStudent === $dbFirstName) {
                    $studentMatches = true;
                } elseif (str_contains($dbFullName, $normStudent) || str_contains($normStudent, $dbFullName)) {
                    // Check word overlap for full name
                    $inputWords = explode(' ', $normStudent);
                    $dbWords = explode(' ', $dbFullName);
                    $intersection = array_intersect($inputWords, $dbWords);
                    if (count($intersection) >= 2 || (count($dbWords) === 1 && count($intersection) === 1)) {
                        $studentMatches = true;
                    }
                }

                // Guardian name match: matches full name or word overlap
                $guardianMatches = false;
                if ($normParent === $dbGuardianName) {
                    $guardianMatches = true;
                } elseif (str_contains($dbGuardianName, $normParent) || str_contains($normParent, $dbGuardianName)) {
                    $inputWords = explode(' ', $normParent);
                    $dbWords = explode(' ', $dbGuardianName);
                    $intersection = array_intersect($inputWords, $dbWords);
                    if (count($intersection) >= 1 && (count($inputWords) === 1 || count($intersection) >= 2)) {
                        $guardianMatches = true;
                    }
                }

                if ($studentMatches && $guardianMatches) {
                    $matchedStudentId = (int) $row['student_id'];
                    break;
                }
            }

            if ($matchedStudentId !== null && $matchedStudentId > 0) {
                // Successful verification: save to server session
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

        } catch (Throwable $e) {
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

        $verifiedAt = (int) $_SESSION['attendance_verified_at'];
        if ((time() - $verifiedAt) > self::SESSION_LIFETIME_SECONDS) {
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
