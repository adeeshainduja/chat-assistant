<?php
declare(strict_types=1);

final class InstituteIntegrationRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getByInstituteId(int $instituteId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM institute_integrations WHERE institute_id = ? AND provider = "getmore" LIMIT 1'
        );
        $stmt->execute([$instituteId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }

        $decryptedKey = Crypto::decrypt($row['encrypted_api_key'] ?? null);
        $row['api_key'] = $decryptedKey;
        $row['has_api_key'] = ($decryptedKey !== null && $decryptedKey !== '');
        $row['masked_api_key'] = Crypto::mask($decryptedKey);

        return $row;
    }

    public function save(int $instituteId, array $data): void
    {
        $existing = $this->getByInstituteId($instituteId);

        $baseUrl = trim((string) ($data['api_base_url'] ?? ''));
        $classesEndpoint = trim((string) ($data['classes_endpoint'] ?? '/api/v1/classes'));
        $lecturersEndpoint = trim((string) ($data['lecturers_endpoint'] ?? '/api/v1/lecturers'));
        $extraClassesEndpoint = trim((string) ($data['extra_classes_endpoint'] ?? '/api/v1/extra-classes'));
        $attendanceEndpoint = trim((string) ($data['attendance_endpoint'] ?? '/api/v1/student/attendance/today'));
        $isActive = isset($data['is_active']) ? (!empty($data['is_active']) ? 1 : 0) : 1;

        // If a new API key was provided, encrypt it; otherwise keep existing encrypted key
        $newApiKey = trim((string) ($data['api_key'] ?? ''));
        $encryptedKey = null;

        if ($newApiKey !== '') {
            $encryptedKey = Crypto::encrypt($newApiKey);
        } elseif ($existing !== null) {
            $encryptedKey = $existing['encrypted_api_key'] ?? null;
        }

        if ($existing) {
            $stmt = $this->pdo->prepare(
                'UPDATE institute_integrations
                 SET api_base_url = ?,
                     encrypted_api_key = ?,
                     classes_endpoint = ?,
                     lecturers_endpoint = ?,
                     extra_classes_endpoint = ?,
                     attendance_endpoint = ?,
                     is_active = ?,
                     updated_at = NOW()
                 WHERE institute_id = ? AND provider = "getmore"'
            );
            $stmt->execute([
                $baseUrl !== '' ? $baseUrl : null,
                $encryptedKey,
                $classesEndpoint !== '' ? $classesEndpoint : '/api/v1/classes',
                $lecturersEndpoint !== '' ? $lecturersEndpoint : '/api/v1/lecturers',
                $extraClassesEndpoint !== '' ? $extraClassesEndpoint : '/api/v1/extra-classes',
                $attendanceEndpoint !== '' ? $attendanceEndpoint : '/api/v1/student/attendance/today',
                $isActive,
                $instituteId,
            ]);
        } else {
            $stmt = $this->pdo->prepare(
                'INSERT INTO institute_integrations (
                    institute_id, provider, api_base_url, encrypted_api_key,
                    classes_endpoint, lecturers_endpoint, extra_classes_endpoint,
                    attendance_endpoint, is_active
                 ) VALUES (?, "getmore", ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $instituteId,
                $baseUrl !== '' ? $baseUrl : null,
                $encryptedKey,
                $classesEndpoint !== '' ? $classesEndpoint : '/api/v1/classes',
                $lecturersEndpoint !== '' ? $lecturersEndpoint : '/api/v1/lecturers',
                $extraClassesEndpoint !== '' ? $extraClassesEndpoint : '/api/v1/extra-classes',
                $attendanceEndpoint !== '' ? $attendanceEndpoint : '/api/v1/student/attendance/today',
                $isActive,
            ]);
        }
    }

    /**
     * Resolve effective API configuration for an institute.
     * Falls back cleanly to .env if institute has no custom integration.
     */
    public function getEffectiveConfig(int $instituteId): array
    {
        $custom = $instituteId > 0 ? $this->getByInstituteId($instituteId) : null;

        if ($custom !== null) {
            if (empty($custom['is_active'])) {
                return [
                    'source' => 'institute_disabled',
                    'is_active' => false,
                    'base_url' => '',
                    'api_key' => '',
                    'classes_endpoint' => '/api/v1/classes',
                    'lecturers_endpoint' => '/api/v1/lecturers',
                    'extra_classes_endpoint' => '/api/v1/extra-classes',
                    'attendance_endpoint' => '/api/v1/student/attendance/today',
                ];
            }

            if (!empty($custom['api_base_url']) && !empty($custom['api_key'])) {
                return [
                    'source' => 'institute',
                    'is_active' => true,
                    'base_url' => rtrim((string)$custom['api_base_url'], '/'),
                    'api_key' => (string)$custom['api_key'],
                    'classes_endpoint' => (string)($custom['classes_endpoint'] ?: '/api/v1/classes'),
                    'lecturers_endpoint' => (string)($custom['lecturers_endpoint'] ?: '/api/v1/lecturers'),
                    'extra_classes_endpoint' => (string)($custom['extra_classes_endpoint'] ?: '/api/v1/extra-classes'),
                    'attendance_endpoint' => (string)($custom['attendance_endpoint'] ?: '/api/v1/student/attendance/today'),
                ];
            }
        }

        // Global fallback to .env
        $envBaseUrl = trim((string)(Env::get('GETMORE_API_BASE_URL') ?: Env::get('GETMORE_API_URL') ?: ''));
        $envApiKey = trim((string)Env::get('GETMORE_API_KEY', ''));

        return [
            'source' => 'env',
            'is_active' => ($envBaseUrl !== '' && $envApiKey !== ''),
            'base_url' => rtrim($envBaseUrl, '/'),
            'api_key' => $envApiKey,
            'classes_endpoint' => trim((string)Env::get('GETMORE_CLASSES_ENDPOINT', '/api/v1/classes')),
            'lecturers_endpoint' => trim((string)Env::get('GETMORE_LECTURERS_ENDPOINT', '/api/v1/lecturers')),
            'extra_classes_endpoint' => trim((string)Env::get('GETMORE_EXTRA_CLASSES_ENDPOINT', '/api/v1/extra-classes')),
            'attendance_endpoint' => trim((string)Env::get('GETMORE_ATTENDANCE_ENDPOINT', '/api/v1/student/attendance/today')),
        ];
    }
}
