<?php
declare(strict_types=1);

final class GetmoreConnector
{
    public function __construct(
        private ?string $authToken = null
    ) {
    }

    /**
     * Get classes belonging only to the current student.
     */
    public function myClasses(): array
    {
        if ($this->isDevMode()) {
            return $this->directMyClasses();
        }

        return $this->get('/api/ai/my-classes.php');
    }

    /**
     * Get attendance belonging only to the current student.
     */
    public function myAttendance(?string $date = null): array
    {
        if ($this->isDevMode()) {
            return $this->directMyAttendance($date);
        }

        $query = [];

        if ($date !== null && $date !== '') {
            $query['date'] = $date;
        }

        return $this->get(
            '/api/ai/my-attendance.php',
            $query
        );
    }

    /**
     * Get approved teacher information only.
     */
    public function myTeachers(): array
    {
        if ($this->isDevMode()) {
            return $this->directMyTeachers();
        }

        return $this->get('/api/ai/teachers.php');
    }

    /*
    |--------------------------------------------------------------------------
    | DEVELOPMENT / DIRECT DATABASE MODE
    |--------------------------------------------------------------------------
    */

    private function directMyClasses(): array
    {
        $studentId = $this->studentId();

        $pdo = GetmoreDatabase::connection();

        $sql = "
            SELECT
                c.id AS class_id,
                c.name AS class_name,
                c.lecturer_profile_id,

                ha.day_name,
                ha.start_time,
                ha.end_time

            FROM enrollments e

            INNER JOIN classes c
                ON c.id = e.class_id

            LEFT JOIN hall_allocations ha
                ON ha.class_id = c.id
                AND COALESCE(ha.is_deleted, 0) = 0

            WHERE e.student_id = :student_id
              AND COALESCE(e.is_active, 1) = 1
              AND COALESCE(e.is_deleted, 0) = 0

            ORDER BY c.name ASC
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            'student_id' => $studentId
        ]);

        $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($classes as &$class) {

            $class['next_occurrence'] = null;

            if (
                !empty($class['day_name']) &&
                !empty($class['start_time'])
            ) {
                $class['next_occurrence'] =
                    $this->calculateNextOccurrence(
                        (string) $class['day_name'],
                        (string) $class['start_time']
                    );
            }
        }

        unset($class);

        /*
         * Sort scheduled classes first,
         * according to next occurrence.
         */
        usort(
            $classes,
            static function (array $a, array $b): int {

                $aTime = $a['next_occurrence'] ?? null;
                $bTime = $b['next_occurrence'] ?? null;

                if ($aTime === null && $bTime === null) {
                    return 0;
                }

                if ($aTime === null) {
                    return 1;
                }

                if ($bTime === null) {
                    return -1;
                }

                return strcmp($aTime, $bTime);
            }
        );

        return [
            'ok' => true,
            'student_id' => $studentId,
            'classes' => $classes
        ];
    }

    private function directMyAttendance(?string $date = null): array
    {
        $studentId = $this->studentId();

        /*
         * Validate optional date.
         */
        if (
            $date !== null &&
            $date !== '' &&
            !$this->isValidDate($date)
        ) {
            throw new InvalidArgumentException(
                'Attendance date must use YYYY-MM-DD format.'
            );
        }

        $pdo = GetmoreDatabase::connection();

        $sql = "
            SELECT
                ar.id AS attendance_id,
                ar.date,
                ar.status,
                ar.time_in,
                ar.time_out,

                c.id AS class_id,
                c.name AS class_name

            FROM attendance_records ar

            INNER JOIN enrollments e
                ON e.id = ar.enrollment_id

            INNER JOIN classes c
                ON c.id = e.class_id

            WHERE e.student_id = :student_id
              AND COALESCE(e.is_deleted, 0) = 0
        ";

        $params = [
            'student_id' => $studentId
        ];

        if ($date !== null && $date !== '') {
            $sql .= " AND ar.date = :attendance_date";

            $params['attendance_date'] = $date;
        }

        $sql .= "
            ORDER BY
                ar.date DESC,
                ar.time_in DESC
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute($params);

        return [
            'ok' => true,
            'student_id' => $studentId,
            'date_filter' => $date,
            'attendance' =>
                $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    private function directMyTeachers(): array
    {
        $studentId = $this->studentId();

        $pdo = GetmoreDatabase::connection();

        /*
         * IMPORTANT:
         * Only return approved teacher fields.
         *
         * Do NOT return:
         * NIC
         * password_hash
         * bank details
         * financial information
         */
        $sql = "
            SELECT DISTINCT
                lp.id AS teacher_id,
                lp.name AS teacher_name,
                c.id AS class_id,
                c.name AS class_name

            FROM enrollments e

            INNER JOIN classes c
                ON c.id = e.class_id

            INNER JOIN lecturer_profiles lp
                ON lp.id = c.lecturer_profile_id

            WHERE e.student_id = :student_id
              AND COALESCE(e.is_active, 1) = 1
              AND COALESCE(e.is_deleted, 0) = 0

            ORDER BY lp.name ASC
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            'student_id' => $studentId
        ]);

        return [
            'ok' => true,
            'student_id' => $studentId,
            'teachers' =>
                $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | DEVELOPMENT STUDENT
    |--------------------------------------------------------------------------
    */

    private function studentId(): int
    {
        if (!$this->isDevMode()) {
            throw new RuntimeException(
                'Direct student ID access is only allowed in development mode.'
            );
        }

        $studentId = (int) Env::get(
            'DEV_STUDENT_ID',
            '0'
        );

        if ($studentId < 1) {
            throw new RuntimeException(
                'DEV_STUDENT_ID is not configured.'
            );
        }

        return $studentId;
    }

    private function isDevMode(): bool
    {
        return filter_var(
            (string) Env::get(
                'DEV_MODE',
                'false'
            ),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    /*
    |--------------------------------------------------------------------------
    | NEXT CLASS CALCULATION
    |--------------------------------------------------------------------------
    */

    private function calculateNextOccurrence(
        string $dayName,
        string $startTime
    ): ?string {

        $allowedDays = [
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
            'Sunday'
        ];

        $normalizedDay = ucfirst(
            strtolower(trim($dayName))
        );

        if (
            !in_array(
                $normalizedDay,
                $allowedDays,
                true
            )
        ) {
            return null;
        }

        $timezoneName = (string) Env::get(
            'APP_TIMEZONE',
            'Asia/Colombo'
        );

        try {

            $timezone =
                new DateTimeZone($timezoneName);

            $now =
                new DateTimeImmutable(
                    'now',
                    $timezone
                );

            /*
             * Remove seconds if necessary.
             */
            $timeParts = explode(
                ':',
                $startTime
            );

            $hour =
                isset($timeParts[0])
                ? (int) $timeParts[0]
                : 0;

            $minute =
                isset($timeParts[1])
                ? (int) $timeParts[1]
                : 0;

            /*
             * If the class is today and has
             * not started yet, today is next.
             */
            if (
                strcasecmp(
                    $now->format('l'),
                    $normalizedDay
                ) === 0
            ) {

                $todayClass =
                    $now->setTime(
                        $hour,
                        $minute,
                        0
                    );

                if ($todayClass > $now) {
                    return $todayClass->format(
                        'Y-m-d H:i:s'
                    );
                }
            }

            /*
             * Otherwise find next week's/day's
             * occurrence.
             */
            $next =
                new DateTimeImmutable(
                    'next ' . $normalizedDay,
                    $timezone
                );

            $next =
                $next->setTime(
                    $hour,
                    $minute,
                    0
                );

            return $next->format(
                'Y-m-d H:i:s'
            );

        } catch (Throwable) {
            return null;
        }
    }

    private function isValidDate(string $date): bool
    {
        $parsed =
            DateTimeImmutable::createFromFormat(
                'Y-m-d',
                $date
            );

        return $parsed !== false &&
            $parsed->format('Y-m-d') === $date;
    }

    /*
    |--------------------------------------------------------------------------
    | PRODUCTION GETMORE API MODE
    |--------------------------------------------------------------------------
    */

    private function get(
        string $path,
        array $query = []
    ): array {

        $baseUrl = rtrim(
            (string) Env::get(
                'GETMORE_BASE_URL',
                ''
            ),
            '/'
        );

        if ($baseUrl === '') {
            throw new RuntimeException(
                'GETMORE_BASE_URL is not configured.'
            );
        }

        if (
            $this->authToken === null ||
            $this->authToken === ''
        ) {
            throw new RuntimeException(
                'GETMORE authentication token is missing.'
            );
        }

        $url = $baseUrl . $path;

        if ($query !== []) {
            $url .= '?' .
                http_build_query($query);
        }

        $ch = curl_init($url);

        if ($ch === false) {
            throw new RuntimeException(
                'Could not initialize GETMORE request.'
            );
        }

        curl_setopt_array(
            $ch,
            [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPGET => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 15,

                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Authorization: Bearer ' .
                    $this->authToken
                ],
            ]
        );

        $response = curl_exec($ch);

        $status =
            (int) curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );

        $curlError =
            curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException(
                'GETMORE connection failed: ' .
                $curlError
            );
        }

        $decoded =
            json_decode(
                (string) $response,
                true
            );

        if (!is_array($decoded)) {
            throw new RuntimeException(
                'GETMORE returned invalid JSON.'
            );
        }

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(
                'GETMORE API returned HTTP ' .
                $status
            );
        }

        return $decoded;
    }
}