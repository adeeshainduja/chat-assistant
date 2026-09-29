<?php
declare(strict_types=1);

final class GetmoreConnector
{
    private string $dataMode;

    public function __construct(
        private ?array $apiConfig = null,
        ?string $dataMode = null
    ) {
        $this->dataMode = strtolower(trim((string) ($dataMode ?: Env::get('GETMORE_DATA_MODE', 'api'))));
        if ($this->dataMode !== 'database') {
            $this->dataMode = 'api';
        }
    }

    public function getDataMode(): string
    {
        return $this->dataMode;
    }

    /**
     * Return public class schedules and information belonging to the specified institute.
     * Safe fields only: class name, subject, grade, medium, day, start time, end time, hall, teacher name, fee.
     * No student data, no student IDs, no admin IDs.
     */
    public function publicClasses(int $instituteId): array
    {
        if ($this->dataMode === 'api') {
            return $this->publicClassesFromApi($instituteId);
        }

        return $this->publicClassesFromDatabase($instituteId);
    }

    /**
     * Search public classes and courses belonging strictly to this institute.
     * Returns structured { found: bool, query: string, classes: array }.
     */
    public function searchClasses(int $instituteId, string $query): array
    {
        $rawQuery = trim($query);
        if ($rawQuery === '') {
            return [
                'found' => false,
                'query' => '',
                'classes' => [],
            ];
        }

        if ($this->dataMode === 'api') {
            return $this->searchClassesFromApi($instituteId, $rawQuery);
        }

        return $this->searchClassesFromDatabase($instituteId, $rawQuery);
    }

    /**
     * Return new or upcoming classes and courses for the current institute.
     */
    public function newClasses(int $instituteId): array
    {
        if ($this->dataMode === 'api') {
            return $this->newClassesFromApi($instituteId);
        }

        return $this->newClassesFromDatabase($instituteId);
    }

    /**
     * Return approved public teacher names and their assigned public classes.
     * Safe fields only: teacher name, classes.
     * No NIC, no passwords, no bank info, no phone/email.
     */
    public function publicTeachers(int $instituteId, ?string $teacherName = null): array
    {
        if ($this->dataMode === 'api') {
            return $this->publicTeachersFromApi($instituteId, $teacherName);
        }

        return $this->publicTeachersFromDatabase($instituteId, $teacherName);
    }

    /**
     * Return attendance records belonging ONLY to a verified student.
     */
    public function verifiedStudentAttendance(
        int $studentId,
        int $instituteId,
        ?string $date = null
    ): array {
        if ($date !== null && $date !== '' && !$this->isValidDate($date)) {
            throw new InvalidArgumentException(
                'Attendance date must use YYYY-MM-DD format.'
            );
        }

        // In API mode, check if we can query GETMORE API or fallback to database
        if ($this->dataMode === 'api') {
            return $this->verifiedAttendanceFromApi($studentId, $instituteId, $date);
        }

        return $this->verifiedAttendanceFromDatabase($studentId, $instituteId, $date);
    }

    /*
    |--------------------------------------------------------------------------
    | REST API IMPLEMENTATION (PRODUCTION)
    |--------------------------------------------------------------------------
    */

    /**
     * Secure generic HTTP client for GETMORE REST API calls.
     * Uses PHP cURL, sends Accept: application/json and X-API-KEY: {key}.
     * Throws safe RuntimeException on failure without leaking secret keys.
     */
    public function request(
        string $method,
        string $endpoint,
        array $data = [],
        ?array $overrideConfig = null
    ): array {
        $config = $overrideConfig ?? $this->resolveConfig(0);

        if (isset($config['is_active']) && !$config['is_active']) {
            throw new RuntimeException('GETMORE API integration is currently disabled for this institute.');
        }

        $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
        $apiKey = trim((string) ($config['api_key'] ?? ''));

        if ($baseUrl === '') {
            throw new RuntimeException('GETMORE API base URL is not configured.');
        }

        if ($apiKey === '') {
            throw new RuntimeException('GETMORE API secret key is not configured.');
        }

        $endpoint = '/' . ltrim($endpoint, '/');
        $fullUrl = $baseUrl . $endpoint;

        $method = strtoupper(trim($method));
        if ($method === 'GET' && !empty($data)) {
            $fullUrl .= '?' . http_build_query($data);
        }

        $headers = [
            'Accept: application/json',
            'X-API-KEY: ' . $apiKey,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For local testing / demo SSL certificates

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            $headers[] = 'Content-Type: application/json';
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($body === false || $curlErr !== '') {
            throw new RuntimeException('GETMORE API network error: ' . $curlErr);
        }

        $decoded = json_decode((string) $body, true);

        if ($httpCode < 200 || $httpCode >= 300) {
            $errDetail = '';
            if (is_array($decoded)) {
                $errDetail = (string) ($decoded['error'] ?? $decoded['message'] ?? '');
            }
            $msg = 'GETMORE API returned HTTP ' . $httpCode;
            if ($errDetail !== '') {
                $msg .= ': ' . $errDetail;
            }
            throw new RuntimeException($msg);
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('GETMORE API returned invalid JSON response.');
        }

        return $decoded;
    }

    private function resolveConfig(int $instituteId): array
    {
        if ($this->apiConfig !== null) {
            return $this->apiConfig;
        }

        try {
            $pdo = Database::connection();
            $repo = new InstituteIntegrationRepository($pdo);
            return $repo->getEffectiveConfig($instituteId);
        } catch (Throwable) {
            // Fallback to .env directly if database is unreachable
            return [
                'source' => 'env',
                'is_active' => true,
                'base_url' => rtrim((string) (Env::get('GETMORE_API_BASE_URL') ?: Env::get('GETMORE_API_URL') ?: 'https://demo.getmore.lk'), '/'),
                'api_key' => (string) Env::get('GETMORE_API_KEY', ''),
                'classes_endpoint' => (string) Env::get('GETMORE_CLASSES_ENDPOINT', '/api/v1/classes'),
                'lecturers_endpoint' => (string) Env::get('GETMORE_LECTURERS_ENDPOINT', '/api/v1/lecturers'),
                'extra_classes_endpoint' => (string) Env::get('GETMORE_EXTRA_CLASSES_ENDPOINT', '/api/v1/extra-classes'),
                'attendance_endpoint' => (string) Env::get('GETMORE_ATTENDANCE_ENDPOINT', '/api/v1/student/attendance/today'),
            ];
        }
    }

    private function publicClassesFromApi(int $instituteId): array
    {
        $config = $this->resolveConfig($instituteId);
        $endpoint = (string) ($config['classes_endpoint'] ?: '/api/v1/classes');

        $response = $this->request('GET', $endpoint, [], $config);
        $classes = [];

        // 1. Process standard classes
        $rawClasses = is_array($response['classes'] ?? null) ? $response['classes'] : [];
        foreach ($rawClasses as $c) {
            $startTime = $this->formatTimeString((string) ($c['start_time'] ?? ''));
            $endTime = $this->formatTimeString((string) ($c['end_time'] ?? ''));

            $hallInfo = null;
            if (!empty($c['hall']) && $c['hall'] !== 'TBD') {
                $hallInfo = trim((string) $c['hall']);
            }

            $feeText = null;
            if (!empty($c['fee_amount']) && (float) $c['fee_amount'] > 0) {
                $feeText = 'Rs. ' . number_format((float) $c['fee_amount'], 2);
            }

            $teacherName = !empty($c['lecturer_name']) && $c['lecturer_name'] !== 'Not Assigned'
                ? trim((string) $c['lecturer_name'])
                : null;

            $classes[] = [
                'class_name' => (string) ($c['name'] ?? ''),
                'type' => 'Class',
                'teacher' => $teacherName,
                'subject' => (!empty($c['subject']) && $c['subject'] !== 'N/A') ? (string) $c['subject'] : null,
                'grade' => (!empty($c['grade']) && $c['grade'] !== 'N/A') ? (string) $c['grade'] : null,
                'medium' => (!empty($c['medium']) && $c['medium'] !== 'N/A') ? (string) $c['medium'] : null,
                'day' => !empty($c['day_of_week']) ? trim((string) $c['day_of_week']) : null,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'hall' => $hallInfo,
                'fee' => $feeText,
            ];
        }

        // 2. Process courses
        $rawCourses = is_array($response['courses'] ?? null) ? $response['courses'] : [];
        foreach ($rawCourses as $crs) {
            $startTime = $this->formatTimeString((string) ($crs['start_time'] ?? ''));
            $endTime = $this->formatTimeString((string) ($crs['end_time'] ?? ''));
            $startDate = $this->formatDateString((string) ($crs['start_date'] ?? ''));

            $teacherName = !empty($crs['lecturer_name']) && $crs['lecturer_name'] !== 'Not Assigned'
                ? trim((string) $crs['lecturer_name'])
                : null;

            $classes[] = [
                'class_name' => (string) ($crs['name'] ?? ''),
                'type' => 'Course',
                'teacher' => $teacherName,
                'subject' => (!empty($crs['subject']) && $crs['subject'] !== 'N/A') ? (string) $crs['subject'] : null,
                'grade' => (!empty($crs['grade']) && $crs['grade'] !== 'N/A') ? (string) $crs['grade'] : null,
                'medium' => null,
                'day' => null,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'start_date' => $startDate,
                'description' => !empty($crs['description']) ? trim((string) $crs['description']) : null,
            ];
        }

        return [
            'ok' => true,
            'classes' => $classes,
        ];
    }

    private function searchClassesFromApi(int $instituteId, string $query): array
    {
        $allRes = $this->publicClassesFromApi($instituteId);
        $allClasses = $allRes['classes'] ?? [];

        $keywords = $this->extractSearchKeywords($query);
        $matches = [];

        foreach ($allClasses as $item) {
            $haystack = mb_strtolower(implode(' ', [
                $item['class_name'] ?? '',
                $item['subject'] ?? '',
                $item['grade'] ?? '',
                $item['medium'] ?? '',
                $item['teacher'] ?? '',
                $item['description'] ?? '',
            ]), 'UTF-8');

            $matched = false;
            foreach ($keywords as $kw) {
                if (str_contains($haystack, mb_strtolower($kw, 'UTF-8'))) {
                    $matched = true;
                    break;
                }
            }

            if ($matched) {
                $matches[] = [
                    'class_name' => $item['class_name'],
                    'subject' => $item['subject'],
                    'grade' => $item['grade'],
                    'medium' => $item['medium'],
                    'teacher_name' => $item['teacher'],
                    'day' => $item['day'],
                    'start_time' => $item['start_time'],
                    'end_time' => $item['end_time'],
                    'hall' => $item['hall'] ?? null,
                    'fee' => $item['fee'] ?? null,
                    'start_date' => $item['start_date'] ?? null,
                    'description' => $item['description'] ?? null,
                ];
            }
        }

        if ($matches === []) {
            return [
                'found' => false,
                'query' => $query,
                'classes' => [],
            ];
        }

        return [
            'found' => true,
            'query' => $query,
            'classes' => $matches,
        ];
    }

    private function newClassesFromApi(int $instituteId): array
    {
        $config = $this->resolveConfig($instituteId);
        $results = [];

        // 1. Fetch extra-classes
        try {
            $extraEndpoint = (string) ($config['extra_classes_endpoint'] ?: '/api/v1/extra-classes');
            $response = $this->request('GET', $extraEndpoint, [], $config);
            $rawExtras = is_array($response['extra_classes'] ?? null) ? $response['extra_classes'] : [];

            foreach ($rawExtras as $ex) {
                $startTime = $this->formatTimeString((string) ($ex['start_time'] ?? ''));
                $endTime = $this->formatTimeString((string) ($ex['end_time'] ?? ''));
                $schedule = null;
                if ($startTime && $endTime) {
                    $schedule = (!empty($ex['day_of_week']) ? $ex['day_of_week'] . ', ' : '') . $startTime . ' – ' . $endTime;
                }

                $results[] = [
                    'class_name' => (string) ($ex['title'] ?? ''),
                    'type' => 'Extra Class',
                    'teacher_name' => !empty($ex['lecturer_name']) ? (string) $ex['lecturer_name'] : null,
                    'start_date' => $this->formatDateString((string) ($ex['date'] ?? '')),
                    'schedule' => $schedule,
                    'description' => !empty($ex['description']) ? (string) $ex['description'] : null,
                    'enrollment_status' => 'Open for enrollment',
                ];
            }
        } catch (Throwable) {}

        // 2. Also fetch courses from classes endpoint
        try {
            $classesEndpoint = (string) ($config['classes_endpoint'] ?: '/api/v1/classes');
            $clsResponse = $this->request('GET', $classesEndpoint, [], $config);
            $rawCourses = is_array($clsResponse['courses'] ?? null) ? $clsResponse['courses'] : [];

            foreach ($rawCourses as $crs) {
                $startTime = $this->formatTimeString((string) ($crs['start_time'] ?? ''));
                $endTime = $this->formatTimeString((string) ($crs['end_time'] ?? ''));
                $schedule = ($startTime && $endTime) ? $startTime . ' – ' . $endTime : null;

                $results[] = [
                    'class_name' => (string) ($crs['name'] ?? ''),
                    'type' => 'Course',
                    'subject' => (!empty($crs['subject']) && $crs['subject'] !== 'N/A') ? (string) $crs['subject'] : null,
                    'grade' => (!empty($crs['grade']) && $crs['grade'] !== 'N/A') ? (string) $crs['grade'] : null,
                    'teacher_name' => !empty($crs['lecturer_name']) && $crs['lecturer_name'] !== 'Not Assigned' ? (string) $crs['lecturer_name'] : null,
                    'start_date' => $this->formatDateString((string) ($crs['start_date'] ?? '')),
                    'schedule' => $schedule,
                    'description' => !empty($crs['description']) ? (string) $crs['description'] : null,
                    'enrollment_status' => 'Open for enrollment',
                ];
            }
        } catch (Throwable) {}

        if ($results === []) {
            return [
                'found' => false,
                'classes' => [],
            ];
        }

        return [
            'found' => true,
            'classes' => $results,
        ];
    }

    private function publicTeachersFromApi(int $instituteId, ?string $teacherName = null): array
    {
        $config = $this->resolveConfig($instituteId);
        $endpoint = (string) ($config['lecturers_endpoint'] ?: '/api/v1/lecturers');

        $response = $this->request('GET', $endpoint, [], $config);
        $rawLecturers = is_array($response['lecturers'] ?? null) ? $response['lecturers'] : [];

        // Fetch classes to map lecturer to classes
        $classesRes = $this->publicClassesFromApi($instituteId);
        $classes = $classesRes['classes'] ?? [];

        $teacherClasses = [];
        foreach ($classes as $c) {
            $t = $c['teacher'] ?? null;
            if ($t) {
                $teacherClasses[$t][] = $c['class_name'];
            }
        }

        $teachers = [];
        $filter = ($teacherName !== null && trim($teacherName) !== '')
            ? mb_strtolower(trim($teacherName), 'UTF-8')
            : null;

        foreach ($rawLecturers as $lec) {
            $name = (string) ($lec['name'] ?? '');
            if ($name === '') continue;

            if ($filter !== null && !str_contains(mb_strtolower($name, 'UTF-8'), $filter)) {
                continue;
            }

            $teachers[] = [
                'teacher_name' => $name,
                'classes' => array_values(array_unique($teacherClasses[$name] ?? [])),
            ];
        }

        return [
            'ok' => true,
            'teachers' => $teachers,
        ];
    }

    private function verifiedAttendanceFromApi(int $studentId, int $instituteId, ?string $date = null): array
    {
        // Try local database if available in development mode
        try {
            $pdo = GetmoreDatabase::connection();
            return $this->verifiedAttendanceFromDatabase($studentId, $instituteId, $date);
        } catch (Throwable) {
            // Strictly report API limitation as mandated by Section 13
            return [
                'ok' => false,
                'error' => 'Current GETMORE Attendance API requires student index and parent phone, and does not support the required student-name + parent-name verification flow. Please contact the institute directly.',
            ];
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DIRECT DATABASE IMPLEMENTATION (DEV FALLBACK)
    |--------------------------------------------------------------------------
    */

    private function publicClassesFromDatabase(int $instituteId): array
    {
        $pdo = GetmoreDatabase::connection();
        $hasInstituteId = $this->hasColumn($pdo, 'classes', 'institute_id');

        $sql = "
            SELECT
                c.id AS class_id_internal,
                c.name AS class_name,
                lp.name AS teacher_name,
                s.name AS subject_name,
                g.name AS grade_name,
                m.name AS medium_name,
                COALESCE(ha.day_name, cd.day_name) AS day_name,
                COALESCE(ha.start_time, c.start_time) AS raw_start_time,
                COALESCE(ha.end_time, c.end_time) AS raw_end_time,
                h.name AS hall_name,
                h.location AS hall_location,
                c.fee_amount
            FROM classes c
            LEFT JOIN lecturer_profiles lp
                ON lp.id = c.lecturer_profile_id
                AND COALESCE(lp.is_active, 1) = 1
                AND COALESCE(lp.is_deleted, 0) = 0
            LEFT JOIN subjects s
                ON s.id = c.subject_id
            LEFT JOIN grades g
                ON g.id = c.grade_id
            LEFT JOIN mediums m
                ON m.id = c.medium_id
            LEFT JOIN class_days cd
                ON cd.id = c.day_id
            LEFT JOIN hall_allocations ha
                ON ha.class_id = c.id
                AND COALESCE(ha.is_deleted, 0) = 0
            LEFT JOIN halls h
                ON h.id = COALESCE(ha.hall_id, c.hall_id)
                AND COALESCE(h.is_deleted, 0) = 0
            WHERE COALESCE(c.is_active, 1) = 1
              AND COALESCE(c.is_deleted, 0) = 0
        ";

        $params = [];
        if ($hasInstituteId) {
            $sql .= " AND c.institute_id = :institute_id";
            $params['institute_id'] = $instituteId;
        }

        $sql .= " ORDER BY c.name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $classes = [];
        foreach ($rawRows as $row) {
            $startTime = $this->formatTimeString((string) ($row['raw_start_time'] ?? ''));
            $endTime = $this->formatTimeString((string) ($row['raw_end_time'] ?? ''));
            $dayName = !empty($row['day_name']) ? trim((string) $row['day_name']) : null;

            $hallInfo = null;
            if (!empty($row['hall_name'])) {
                $hallInfo = trim((string) $row['hall_name']);
                if (!empty($row['hall_location'])) {
                    $hallInfo .= ' (' . trim((string) $row['hall_location']) . ')';
                }
            }

            $feeText = null;
            if (!empty($row['fee_amount']) && (float) $row['fee_amount'] > 0) {
                $feeText = 'Rs. ' . number_format((float) $row['fee_amount'], 2);
            }

            $classes[] = [
                'class_name' => (string) $row['class_name'],
                'teacher' => !empty($row['teacher_name']) ? (string) $row['teacher_name'] : null,
                'subject' => !empty($row['subject_name']) ? (string) $row['subject_name'] : null,
                'grade' => !empty($row['grade_name']) ? (string) $row['grade_name'] : null,
                'medium' => !empty($row['medium_name']) ? (string) $row['medium_name'] : null,
                'day' => $dayName,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'hall' => $hallInfo,
                'fee' => $feeText,
            ];
        }

        return [
            'ok' => true,
            'classes' => $classes,
        ];
    }

    private function searchClassesFromDatabase(int $instituteId, string $query): array
    {
        $keywords = $this->extractSearchKeywords($query);
        $pdo = GetmoreDatabase::connection();

        $classesResults = $this->searchClassesTable($pdo, $instituteId, $keywords, $query);
        $coursesResults = $this->searchCoursesTable($pdo, $instituteId, $keywords, $query);

        $merged = array_merge($classesResults, $coursesResults);

        if ($merged === []) {
            return [
                'found' => false,
                'query' => $query,
                'classes' => [],
            ];
        }

        return [
            'found' => true,
            'query' => $query,
            'classes' => $merged,
        ];
    }

    private function newClassesFromDatabase(int $instituteId): array
    {
        $pdo = GetmoreDatabase::connection();
        $results = [];

        // 1. Search courses table for upcoming / new courses
        $hasCrsInst = $this->hasColumn($pdo, 'courses', 'institute_id');
        $hasCrsIsNew = $this->hasColumn($pdo, 'courses', 'is_new');

        $crsSql = "
            SELECT
                crs.id,
                crs.name AS course_name,
                crs.description,
                crs.start_date,
                crs.end_date,
                crs.start_time,
                crs.end_time,
                crs.status,
                lp.name AS teacher_name,
                s.name AS subject_name,
                g.name AS grade_name
            FROM courses crs
            LEFT JOIN lecturer_profiles lp ON lp.id = crs.lecturer_profile_id AND COALESCE(lp.is_active, 1) = 1 AND COALESCE(lp.is_deleted, 0) = 0
            LEFT JOIN subjects s ON s.id = crs.subject_id AND COALESCE(s.is_deleted, 0) = 0
            LEFT JOIN grades g ON g.id = crs.grade_id
            WHERE COALESCE(crs.is_active, 1) = 1
              AND COALESCE(crs.is_deleted, 0) = 0
        ";

        $crsParams = [];
        if ($hasCrsInst) {
            $crsSql .= " AND crs.institute_id = :institute_id";
            $crsParams['institute_id'] = $instituteId;
        }

        $conditions = ["crs.start_date >= CURDATE() - INTERVAL 14 DAY", "crs.status IN ('upcoming', 'new', 'open')"];
        if ($hasCrsIsNew) {
            $conditions[] = "crs.is_new = 1";
        }
        $crsSql .= " AND (" . implode(" OR ", $conditions) . ") ORDER BY crs.start_date ASC, crs.id DESC LIMIT 10";

        try {
            $stmt = $pdo->prepare($crsSql);
            $stmt->execute($crsParams);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $startDate = $this->formatDateString((string) ($row['start_date'] ?? ''));
                $startTime = $this->formatTimeString((string) ($row['start_time'] ?? ''));
                $endTime = $this->formatTimeString((string) ($row['end_time'] ?? ''));

                $schedule = null;
                if ($startTime !== null && $endTime !== null) {
                    $schedule = $startTime . ' – ' . $endTime;
                } elseif ($startTime !== null) {
                    $schedule = $startTime;
                }

                $results[] = [
                    'class_name' => (string) $row['course_name'],
                    'type' => 'Course',
                    'subject' => !empty($row['subject_name']) ? (string) $row['subject_name'] : null,
                    'grade' => !empty($row['grade_name']) ? (string) $row['grade_name'] : null,
                    'teacher_name' => !empty($row['teacher_name']) ? (string) $row['teacher_name'] : null,
                    'start_date' => $startDate,
                    'schedule' => $schedule,
                    'description' => !empty($row['description']) ? (string) $row['description'] : null,
                    'enrollment_status' => 'Open for enrollment',
                ];
            }
        } catch (Throwable) {}

        // 2. Search classes table for classes flagged as new or upcoming
        $hasClassInst = $this->hasColumn($pdo, 'classes', 'institute_id');
        $hasClassIsNew = $this->hasColumn($pdo, 'classes', 'is_new');
        $hasClassStartDate = $this->hasColumn($pdo, 'classes', 'start_date');

        if ($hasClassIsNew || $hasClassStartDate) {
            $clsSql = "
                SELECT
                    c.id,
                    c.name AS class_name,
                    c.start_date,
                    c.fee_amount,
                    lp.name AS teacher_name,
                    s.name AS subject_name,
                    g.name AS grade_name,
                    m.name AS medium_name,
                    COALESCE(ha.day_name, cd.day_name) AS day_name,
                    COALESCE(ha.start_time, c.start_time) AS raw_start_time,
                    COALESCE(ha.end_time, c.end_time) AS raw_end_time
                FROM classes c
                LEFT JOIN lecturer_profiles lp ON lp.id = c.lecturer_profile_id AND COALESCE(lp.is_active, 1) = 1 AND COALESCE(lp.is_deleted, 0) = 0
                LEFT JOIN subjects s ON s.id = c.subject_id AND COALESCE(s.is_deleted, 0) = 0
                LEFT JOIN grades g ON g.id = c.grade_id
                LEFT JOIN mediums m ON m.id = c.medium_id AND COALESCE(m.is_deleted, 0) = 0
                LEFT JOIN class_days cd ON cd.id = c.day_id
                LEFT JOIN hall_allocations ha ON ha.class_id = c.id AND COALESCE(ha.is_deleted, 0) = 0
                WHERE COALESCE(c.is_active, 1) = 1 AND COALESCE(c.is_deleted, 0) = 0
            ";

            $clsParams = [];
            if ($hasClassInst) {
                $clsSql .= " AND c.institute_id = :institute_id";
                $clsParams['institute_id'] = $instituteId;
            }

            $clsConditions = [];
            if ($hasClassIsNew) {
                $clsConditions[] = "c.is_new = 1";
            }
            if ($hasClassStartDate) {
                $clsConditions[] = "c.start_date >= CURDATE() - INTERVAL 14 DAY";
            }

            if ($clsConditions !== []) {
                $clsSql .= " AND (" . implode(" OR ", $clsConditions) . ") ORDER BY c.id DESC LIMIT 10";

                try {
                    $clsStmt = $pdo->prepare($clsSql);
                    $clsStmt->execute($clsParams);
                    foreach ($clsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $startDate = $this->formatDateString((string) ($row['start_date'] ?? ''));
                        $startTime = $this->formatTimeString((string) ($row['raw_start_time'] ?? ''));
                        $endTime = $this->formatTimeString((string) ($row['raw_end_time'] ?? ''));

                        $schedule = null;
                        if ($startTime !== null && $endTime !== null) {
                            $schedule = (!empty($row['day_name']) ? $row['day_name'] . ', ' : '') . $startTime . ' – ' . $endTime;
                        }

                        $results[] = [
                            'class_name' => (string) $row['class_name'],
                            'type' => 'Class',
                            'subject' => !empty($row['subject_name']) ? (string) $row['subject_name'] : null,
                            'grade' => !empty($row['grade_name']) ? (string) $row['grade_name'] : null,
                            'medium' => !empty($row['medium_name']) ? (string) $row['medium_name'] : null,
                            'teacher_name' => !empty($row['teacher_name']) ? (string) $row['teacher_name'] : null,
                            'start_date' => $startDate,
                            'schedule' => $schedule,
                            'enrollment_status' => 'Open for enrollment',
                        ];
                    }
                } catch (Throwable) {}
            }
        }

        if ($results === []) {
            return [
                'found' => false,
                'classes' => [],
            ];
        }

        return [
            'found' => true,
            'classes' => $results,
        ];
    }

    private function publicTeachersFromDatabase(int $instituteId, ?string $teacherName = null): array
    {
        $pdo = GetmoreDatabase::connection();
        $hasInstituteId = $this->hasColumn($pdo, 'classes', 'institute_id');

        $sql = "
            SELECT
                lp.name AS teacher_name,
                c.name AS class_name
            FROM lecturer_profiles lp
            INNER JOIN classes c
                ON c.lecturer_profile_id = lp.id
                AND COALESCE(c.is_active, 1) = 1
                AND COALESCE(c.is_deleted, 0) = 0
            WHERE COALESCE(lp.is_active, 1) = 1
              AND COALESCE(lp.is_deleted, 0) = 0
        ";

        $params = [];
        if ($hasInstituteId) {
            $sql .= " AND c.institute_id = :institute_id";
            $params['institute_id'] = $instituteId;
        }

        if ($teacherName !== null && trim($teacherName) !== '') {
            $sql .= " AND LOWER(lp.name) LIKE :teacher_name";
            $params['teacher_name'] = '%' . mb_strtolower(trim($teacherName)) . '%';
        }

        $sql .= " ORDER BY lp.name ASC, c.name ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $teacherMap = [];
        foreach ($rows as $r) {
            $name = (string) $r['teacher_name'];
            if (!isset($teacherMap[$name])) {
                $teacherMap[$name] = [
                    'teacher_name' => $name,
                    'classes' => [],
                ];
            }
            if (!in_array($r['class_name'], $teacherMap[$name]['classes'], true)) {
                $teacherMap[$name]['classes'][] = (string) $r['class_name'];
            }
        }

        return [
            'ok' => true,
            'teachers' => array_values($teacherMap),
        ];
    }

    private function verifiedAttendanceFromDatabase(
        int $studentId,
        int $instituteId,
        ?string $date = null
    ): array {
        $pdo = GetmoreDatabase::connection();

        $sql = "
            SELECT
                ar.date,
                ar.status,
                ar.time_in,
                ar.time_out,
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
            'student_id' => $studentId,
        ];

        if ($date !== null && $date !== '') {
            $sql .= " AND ar.date = :attendance_date";
            $params['attendance_date'] = $date;
        }

        $sql .= " ORDER BY ar.date DESC, ar.time_in DESC LIMIT 30";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rawRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $records = [];
        foreach ($rawRecords as $rec) {
            $timeIn = $this->formatTimeString((string) ($rec['time_in'] ?? ''));
            $timeOut = $this->formatTimeString((string) ($rec['time_out'] ?? ''));

            $records[] = [
                'date' => (string) $rec['date'],
                'status' => ucfirst((string) ($rec['status'] ?? 'Present')),
                'class_name' => (string) $rec['class_name'],
                'time_in' => $timeIn,
                'time_out' => $timeOut,
            ];
        }

        return [
            'ok' => true,
            'date_filter' => $date,
            'attendance' => $records,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | INTERNAL HELPERS & DATABASE KEYWORD SEARCH
    |--------------------------------------------------------------------------
    */

    private function searchClassesTable(PDO $pdo, int $instituteId, array $keywords, string $rawQuery): array
    {
        $hasInstituteId = $this->hasColumn($pdo, 'classes', 'institute_id');

        $sql = "
            SELECT
                c.name AS class_name,
                lp.name AS teacher_name,
                s.name AS subject_name,
                g.name AS grade_name,
                m.name AS medium_name,
                COALESCE(ha.day_name, cd.day_name) AS day_name,
                COALESCE(ha.start_time, c.start_time) AS raw_start_time,
                COALESCE(ha.end_time, c.end_time) AS raw_end_time,
                h.name AS hall_name,
                h.location AS hall_location,
                c.fee_amount
            FROM classes c
            LEFT JOIN lecturer_profiles lp ON lp.id = c.lecturer_profile_id AND COALESCE(lp.is_active, 1) = 1 AND COALESCE(lp.is_deleted, 0) = 0
            LEFT JOIN subjects s ON s.id = c.subject_id AND COALESCE(s.is_deleted, 0) = 0
            LEFT JOIN grades g ON g.id = c.grade_id
            LEFT JOIN mediums m ON m.id = c.medium_id AND COALESCE(m.is_deleted, 0) = 0
            LEFT JOIN class_days cd ON cd.id = c.day_id
            LEFT JOIN hall_allocations ha ON ha.class_id = c.id AND COALESCE(ha.is_deleted, 0) = 0
            LEFT JOIN halls h ON h.id = COALESCE(ha.hall_id, c.hall_id) AND COALESCE(h.is_deleted, 0) = 0
            WHERE COALESCE(c.is_active, 1) = 1
              AND COALESCE(c.is_deleted, 0) = 0
        ";

        $params = [];
        if ($hasInstituteId) {
            $sql .= " AND c.institute_id = :institute_id";
            $params['institute_id'] = $instituteId;
        }

        $keywordConditions = [];
        $i = 0;
        foreach ($keywords as $kw) {
            $pName = 'kw_name_' . $i;
            $pSub = 'kw_sub_' . $i;
            $pGrd = 'kw_grd_' . $i;
            $pMed = 'kw_med_' . $i;
            $pLec = 'kw_lec_' . $i;

            $keywordConditions[] = "(
                c.name LIKE :$pName
                OR COALESCE(s.name, '') LIKE :$pSub
                OR COALESCE(g.name, '') LIKE :$pGrd
                OR COALESCE(m.name, '') LIKE :$pMed
                OR COALESCE(lp.name, '') LIKE :$pLec
            )";
            $params[$pName] = '%' . $kw . '%';
            $params[$pSub] = '%' . $kw . '%';
            $params[$pGrd] = '%' . $kw . '%';
            $params[$pMed] = '%' . $kw . '%';
            $params[$pLec] = '%' . $kw . '%';
            $i++;
        }

        if ($keywordConditions !== []) {
            $sql .= " AND (" . implode(" OR ", $keywordConditions) . ")";
        }

        $sql .= " ORDER BY c.name ASC LIMIT 10";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $results = [];
            foreach ($rows as $row) {
                $startTime = $this->formatTimeString((string) ($row['raw_start_time'] ?? ''));
                $endTime = $this->formatTimeString((string) ($row['raw_end_time'] ?? ''));

                $hallInfo = null;
                if (!empty($row['hall_name'])) {
                    $hallInfo = trim((string) $row['hall_name']);
                    if (!empty($row['hall_location'])) {
                        $hallInfo .= ' (' . trim((string) $row['hall_location']) . ')';
                    }
                }

                $feeText = null;
                if (!empty($row['fee_amount']) && (float) $row['fee_amount'] > 0) {
                    $feeText = 'Rs. ' . number_format((float) $row['fee_amount'], 2);
                }

                $results[] = [
                    'class_name' => (string) $row['class_name'],
                    'subject' => !empty($row['subject_name']) ? (string) $row['subject_name'] : null,
                    'grade' => !empty($row['grade_name']) ? (string) $row['grade_name'] : null,
                    'medium' => !empty($row['medium_name']) ? (string) $row['medium_name'] : null,
                    'teacher_name' => !empty($row['teacher_name']) ? (string) $row['teacher_name'] : null,
                    'day' => !empty($row['day_name']) ? (string) $row['day_name'] : null,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'hall' => $hallInfo,
                    'fee' => $feeText,
                ];
            }

            return $results;
        } catch (Throwable) {
            return [];
        }
    }

    private function searchCoursesTable(PDO $pdo, int $instituteId, array $keywords, string $rawQuery): array
    {
        $hasCrsInst = $this->hasColumn($pdo, 'courses', 'institute_id');

        $sql = "
            SELECT
                crs.name AS course_name,
                crs.description,
                crs.start_date,
                crs.end_date,
                crs.start_time,
                crs.end_time,
                crs.status,
                lp.name AS teacher_name,
                s.name AS subject_name,
                g.name AS grade_name
            FROM courses crs
            LEFT JOIN lecturer_profiles lp ON lp.id = crs.lecturer_profile_id AND COALESCE(lp.is_active, 1) = 1 AND COALESCE(lp.is_deleted, 0) = 0
            LEFT JOIN subjects s ON s.id = crs.subject_id AND COALESCE(s.is_deleted, 0) = 0
            LEFT JOIN grades g ON g.id = crs.grade_id
            WHERE COALESCE(crs.is_active, 1) = 1
              AND COALESCE(crs.is_deleted, 0) = 0
        ";

        $params = [];
        if ($hasCrsInst) {
            $sql .= " AND crs.institute_id = :institute_id";
            $params['institute_id'] = $instituteId;
        }

        $keywordConditions = [];
        $i = 0;
        foreach ($keywords as $kw) {
            $pName = 'crskw_name_' . $i;
            $pDesc = 'crskw_desc_' . $i;
            $pSub = 'crskw_sub_' . $i;
            $pGrd = 'crskw_grd_' . $i;
            $pLec = 'crskw_lec_' . $i;

            $keywordConditions[] = "(
                crs.name LIKE :$pName
                OR COALESCE(crs.description, '') LIKE :$pDesc
                OR COALESCE(s.name, '') LIKE :$pSub
                OR COALESCE(g.name, '') LIKE :$pGrd
                OR COALESCE(lp.name, '') LIKE :$pLec
            )";
            $params[$pName] = '%' . $kw . '%';
            $params[$pDesc] = '%' . $kw . '%';
            $params[$pSub] = '%' . $kw . '%';
            $params[$pGrd] = '%' . $kw . '%';
            $params[$pLec] = '%' . $kw . '%';
            $i++;
        }

        if ($keywordConditions !== []) {
            $sql .= " AND (" . implode(" OR ", $keywordConditions) . ")";
        }

        $sql .= " ORDER BY crs.name ASC LIMIT 5";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $results = [];
            foreach ($rows as $row) {
                $startTime = $this->formatTimeString((string) ($row['start_time'] ?? ''));
                $endTime = $this->formatTimeString((string) ($row['end_time'] ?? ''));
                $startDate = $this->formatDateString((string) ($row['start_date'] ?? ''));

                $results[] = [
                    'class_name' => (string) $row['course_name'],
                    'type' => 'Course',
                    'subject' => !empty($row['subject_name']) ? (string) $row['subject_name'] : null,
                    'grade' => !empty($row['grade_name']) ? (string) $row['grade_name'] : null,
                    'medium' => null,
                    'teacher_name' => !empty($row['teacher_name']) ? (string) $row['teacher_name'] : null,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'start_date' => $startDate,
                    'description' => !empty($row['description']) ? (string) $row['description'] : null,
                ];
            }

            return $results;
        } catch (Throwable) {
            return [];
        }
    }

    private function extractSearchKeywords(string $query): array
    {
        $clean = mb_strtolower(trim($query), 'UTF-8');
        $clean = preg_replace('/[^\p{L}\p{N}\s]/u', '', $clean) ?? '';
        $tokens = preg_split('/\s+/', $clean, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($tokens) || $tokens === []) {
            return [trim($query)];
        }

        $stopWords = [
            'class', 'classes', 'course', 'courses', 'subject', 'subjects',
            'do', 'you', 'have', 'teach', 'any', 'is', 'there', 'what', 'are',
            'for', 'in', 'the', 'a', 'an', 'at', 'available', 'offered', 'offer',
            'show', 'me', 'tell', 'about', 'find', 'lookup', 'can', 'i', 'take',
            'your', 'our', 'my', 'some', 'please', 'we', 'want', 'like', 'need',
            'know', 'looking', 'interested', 'would', 'could', 'help', 'with',
            'of', 'on', 'to', 'from', 'who', 'which', 'where', 'when', 'how',
        ];

        $filtered = array_values(array_filter($tokens, static function (string $w) use ($stopWords): bool {
            return mb_strlen($w) >= 2 && !in_array($w, $stopWords, true);
        }));

        if ($filtered === []) {
            return [$clean];
        }

        $expanded = [];
        $synonyms = [
            'math' => 'mathematics',
            'maths' => 'mathematics',
            'chem' => 'chemistry',
            'phy' => 'physics',
            'phys' => 'physics',
            'bio' => 'biology',
            'acc' => 'accounting',
        ];

        foreach ($filtered as $kw) {
            $expanded[] = $kw;
            if (isset($synonyms[$kw])) {
                $expanded[] = $synonyms[$kw];
            }
        }

        return array_values(array_unique($expanded));
    }

    private function formatTimeString(string $timeStr): ?string
    {
        $timeStr = trim($timeStr);
        if ($timeStr === '' || str_starts_with($timeStr, '0000-00-00')) {
            return null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $timeStr, $m)) {
            $h = (int) $m[1];
            $min = (int) $m[2];
            $ampm = $h >= 12 ? 'PM' : 'AM';
            $h12 = $h % 12;
            if ($h12 === 0) $h12 = 12;
            return sprintf('%d:%02d %s', $h12, $min, $ampm);
        }

        try {
            $dt = new DateTimeImmutable($timeStr);
            return $dt->format('g:i A');
        } catch (Throwable) {
            return $timeStr;
        }
    }

    private function formatDateString(string $dateStr): ?string
    {
        $dateStr = trim($dateStr);
        if ($dateStr === '' || str_starts_with($dateStr, '0000-00-00')) {
            return null;
        }

        try {
            $dt = new DateTimeImmutable($dateStr);
            return $dt->format('F j, Y');
        } catch (Throwable) {
            return $dateStr;
        }
    }

    private function isValidDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }

    private function hasColumn(PDO $pdo, string $table, string $column): bool
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