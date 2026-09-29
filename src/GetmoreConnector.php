<?php
declare(strict_types=1);

final class GetmoreConnector
{
    public function __construct(
        private ?string $authToken = null
    ) {
    }

    /**
     * Return public class schedules and information belonging to the specified institute.
     * Safe fields only: class name, subject, grade, medium, day, start time, end time, hall, teacher name, fee.
     * No student data, no student IDs, no admin IDs.
     */
    public function publicClasses(int $instituteId): array
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

        $keywords = $this->extractSearchKeywords($rawQuery);
        $pdo = GetmoreDatabase::connection();

        $classesResults = $this->searchClassesTable($pdo, $instituteId, $keywords, $rawQuery);
        $coursesResults = $this->searchCoursesTable($pdo, $instituteId, $keywords, $rawQuery);

        $merged = array_merge($classesResults, $coursesResults);

        if ($merged === []) {
            return [
                'found' => false,
                'query' => $rawQuery,
                'classes' => [],
            ];
        }

        return [
            'found' => true,
            'query' => $rawQuery,
            'classes' => $merged,
        ];
    }

    /**
     * Return new or upcoming classes and courses for the current institute.
     */
    public function newClasses(int $instituteId): array
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

            $clsConds = [];
            if ($hasClassIsNew) {
                $clsConds[] = "c.is_new = 1";
            }
            if ($hasClassStartDate) {
                $clsConds[] = "c.start_date >= CURDATE() - INTERVAL 14 DAY";
            }

            if ($clsConds !== []) {
                $clsSql .= " AND (" . implode(" OR ", $clsConds) . ") ORDER BY c.id DESC LIMIT 10";
                try {
                    $stmt = $pdo->prepare($clsSql);
                    $stmt->execute($clsParams);
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                        $startTime = $this->formatTimeString((string) ($row['raw_start_time'] ?? ''));
                        $endTime = $this->formatTimeString((string) ($row['raw_end_time'] ?? ''));
                        $day = !empty($row['day_name']) ? (string) $row['day_name'] : null;

                        $schedule = null;
                        if ($day !== null && $startTime !== null && $endTime !== null) {
                            $schedule = $day . ', ' . $startTime . ' – ' . $endTime;
                        } elseif ($startTime !== null && $endTime !== null) {
                            $schedule = $startTime . ' – ' . $endTime;
                        }

                        $startDate = !empty($row['start_date']) ? $this->formatDateString((string) $row['start_date']) : null;

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

    /**
     * Return approved public teacher names and their assigned public classes.
     * Safe fields only: teacher name, classes.
     * No NIC, no passwords, no bank info, no phone/email.
     */
    public function publicTeachers(int $instituteId, ?string $teacherName = null): array
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

    /**
     * Return attendance records belonging ONLY to a verified student.
     * Requires valid student ID resolved through server-side verification.
     * Safe fields only: date, status, class name, time in, time out.
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
    | INTERNAL SEARCH HELPERS
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

        // Build keyword matching conditions
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

        // Expand common subject abbreviations
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

    /*
    |--------------------------------------------------------------------------
    | FORMATTING & INSPECTION HELPERS
    |--------------------------------------------------------------------------
    */

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