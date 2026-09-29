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
    | HELPER METHODS
    |--------------------------------------------------------------------------
    */

    private function formatTimeString(string $timeStr): ?string
    {
        $timeStr = trim($timeStr);
        if ($timeStr === '' || str_starts_with($timeStr, '0000-00-00')) {
            return null;
        }

        // If in "HH:MM:SS" or "HH:MM"
        if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $timeStr, $m)) {
            $h = (int) $m[1];
            $min = (int) $m[2];
            $ampm = $h >= 12 ? 'PM' : 'AM';
            $h12 = $h % 12;
            if ($h12 === 0) $h12 = 12;
            return sprintf('%d:%02d %s', $h12, $min, $ampm);
        }

        // If in "YYYY-MM-DD HH:MM:SS"
        try {
            $dt = new DateTimeImmutable($timeStr);
            return $dt->format('g:i A');
        } catch (Throwable) {
            return $timeStr;
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