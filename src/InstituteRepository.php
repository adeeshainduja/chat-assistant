<?php
declare(strict_types=1);

final class InstituteRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM Ai_assistant_institutes WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return null;
        }

        // Normalize aliases
        $row['about'] = $row['about'] ?? $row['about_institute'] ?? null;
        $row['about_institute'] = $row['about_institute'] ?? $row['about'] ?? null;
        $row['registration_information'] = $row['registration_information'] ?? $row['registration_info'] ?? null;
        $row['registration_info'] = $row['registration_info'] ?? $row['registration_information'] ?? null;
        $row['facilities'] = $row['facilities'] ?? $row['facilities_services'] ?? null;
        $row['facilities_services'] = $row['facilities_services'] ?? $row['facilities'] ?? null;

        return $row;
    }

    public function getByWidgetKey(string $widgetKey): ?array
    {
        $widgetKey = trim($widgetKey);
        if ($widgetKey === '') {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM Ai_assistant_institutes WHERE public_widget_key = ? LIMIT 1');
        $stmt->execute([$widgetKey]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return null;
        }

        $row['about'] = $row['about'] ?? $row['about_institute'] ?? null;
        $row['about_institute'] = $row['about_institute'] ?? $row['about'] ?? null;
        $row['registration_information'] = $row['registration_information'] ?? $row['registration_info'] ?? null;
        $row['registration_info'] = $row['registration_info'] ?? $row['registration_information'] ?? null;
        $row['facilities'] = $row['facilities'] ?? $row['facilities_services'] ?? null;
        $row['facilities_services'] = $row['facilities_services'] ?? $row['facilities'] ?? null;

        return $row;
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM Ai_assistant_institutes ORDER BY id ASC');
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['about'] = $row['about'] ?? $row['about_institute'] ?? null;
            $row['about_institute'] = $row['about_institute'] ?? $row['about'] ?? null;
            $row['registration_information'] = $row['registration_information'] ?? $row['registration_info'] ?? null;
            $row['registration_info'] = $row['registration_info'] ?? $row['registration_information'] ?? null;
            $row['facilities'] = $row['facilities'] ?? $row['facilities_services'] ?? null;
            $row['facilities_services'] = $row['facilities_services'] ?? $row['facilities'] ?? null;
        }
        unset($row);

        return $rows;
    }

    public function create(array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        $key = trim((string) ($data['public_widget_key'] ?? ''));
        if ($key === '') {
            $key = self::generateWidgetKey();
        }

        $isActive = isset($data['is_active']) ? (!empty($data['is_active']) ? 1 : 0) : 1;
        $allowedDomains = isset($data['allowed_domains']) ? trim((string) $data['allowed_domains']) : null;

        $about = trim((string) ($data['about'] ?? $data['about_institute'] ?? '')) ?: null;
        $regInfo = trim((string) ($data['registration_information'] ?? $data['registration_info'] ?? '')) ?: null;
        $facilities = trim((string) ($data['facilities'] ?? $data['facilities_services'] ?? '')) ?: null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO Ai_assistant_institutes (
                name, public_widget_key, is_active, allowed_domains,
                short_description, about, about_institute, public_address,
                public_phone, public_email, website, opening_hours,
                registration_information, registration_info,
                facilities, facilities_services, public_notes
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $name,
            $key,
            $isActive,
            $allowedDomains !== '' ? $allowedDomains : null,
            trim((string) ($data['short_description'] ?? '')) ?: null,
            $about,
            $about,
            trim((string) ($data['public_address'] ?? '')) ?: null,
            trim((string) ($data['public_phone'] ?? '')) ?: null,
            trim((string) ($data['public_email'] ?? '')) ?: null,
            trim((string) ($data['website'] ?? '')) ?: null,
            trim((string) ($data['opening_hours'] ?? '')) ?: null,
            $regInfo,
            $regInfo,
            $facilities,
            $facilities,
            trim((string) ($data['public_notes'] ?? '')) ?: null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [];
        $params = [];

        // Direct fields
        $directFields = [
            'name', 'allowed_domains', 'short_description',
            'public_address', 'public_phone', 'public_email',
            'website', 'opening_hours', 'public_notes',
        ];

        foreach ($directFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "`$field` = ?";
                $val = trim((string) $data[$field]);
                $params[] = $val !== '' ? $val : null;
            }
        }

        if (array_key_exists('is_active', $data)) {
            $fields[] = "`is_active` = ?";
            $params[] = !empty($data['is_active']) ? 1 : 0;
        }

        // Synced fields: about & about_institute
        if (array_key_exists('about', $data) || array_key_exists('about_institute', $data)) {
            $val = trim((string) ($data['about'] ?? $data['about_institute'] ?? ''));
            $val = $val !== '' ? $val : null;
            $fields[] = "`about` = ?";
            $params[] = $val;
            $fields[] = "`about_institute` = ?";
            $params[] = $val;
        }

        // Synced fields: registration_information & registration_info
        if (array_key_exists('registration_information', $data) || array_key_exists('registration_info', $data)) {
            $val = trim((string) ($data['registration_information'] ?? $data['registration_info'] ?? ''));
            $val = $val !== '' ? $val : null;
            $fields[] = "`registration_information` = ?";
            $params[] = $val;
            $fields[] = "`registration_info` = ?";
            $params[] = $val;
        }

        // Synced fields: facilities & facilities_services
        if (array_key_exists('facilities', $data) || array_key_exists('facilities_services', $data)) {
            $val = trim((string) ($data['facilities'] ?? $data['facilities_services'] ?? ''));
            $val = $val !== '' ? $val : null;
            $fields[] = "`facilities` = ?";
            $params[] = $val;
            $fields[] = "`facilities_services` = ?";
            $params[] = $val;
        }

        if ($fields === []) {
            return;
        }

        $params[] = $id;
        $sql = 'UPDATE Ai_assistant_institutes SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public function setActive(int $id, bool $active): void
    {
        $stmt = $this->pdo->prepare('UPDATE Ai_assistant_institutes SET is_active = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$active ? 1 : 0, $id]);
    }

    public function regenerateWidgetKey(int $id): string
    {
        $newKey = self::generateWidgetKey();
        $stmt = $this->pdo->prepare('UPDATE Ai_assistant_institutes SET public_widget_key = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$newKey, $id]);

        return $newKey;
    }

    /**
     * Return only approved public fields for this institute.
     * Never returns IDs, secrets, billing info, or internal hashes.
     */
    public function getPublicDetails(int $id): array
    {
        $inst = $this->getById($id);
        if (!$inst) {
            return [
                'ok' => false,
                'error' => 'Institute details not found.',
            ];
        }

        $details = [
            'institute_name' => (string) ($inst['name'] ?? ''),
        ];

        $fieldMap = [
            'short_description' => 'short_description',
            'about' => 'about',
            'about_institute' => 'about_institute',
            'public_address' => 'address',
            'public_phone' => 'phone',
            'public_email' => 'email',
            'website' => 'website',
            'opening_hours' => 'opening_hours',
            'registration_information' => 'registration_info',
            'registration_info' => 'registration_info',
            'facilities' => 'facilities_services',
            'facilities_services' => 'facilities_services',
            'public_notes' => 'notes',
        ];

        foreach ($fieldMap as $col => $key) {
            $val = trim((string) ($inst[$col] ?? ''));
            if ($val !== '') {
                $details[$key] = $val;
                if ($key === 'short_description') {
                    $details['description'] = $val;
                }
            }
        }

        return [
            'ok' => true,
            'details' => $details,
        ];
    }

    public static function generateWidgetKey(): string
    {
        // Cryptographically secure format: pk_ + 16 hex chars (e.g. pk_4bd91f83c08e71aa)
        return 'pk_' . bin2hex(random_bytes(8));
    }

    /**
     * Validate whether the incoming request (Origin or Referer) is from an allowed domain.
     */
    public static function validateDomain(
        ?string $allowedDomains,
        ?string $origin,
        ?string $referer,
        bool $isDev = false
    ): bool {
        $testHost = null;
        if ($origin !== null && trim($origin) !== '') {
            $testHost = parse_url($origin, PHP_URL_HOST);
        } elseif ($referer !== null && trim($referer) !== '') {
            $testHost = parse_url($referer, PHP_URL_HOST);
        }

        if ($isDev) {
            if ($testHost === null || in_array(strtolower($testHost), ['localhost', '127.0.0.1', '::1'], true)) {
                return true;
            }
        }

        $allowedDomains = trim((string) $allowedDomains);
        if ($allowedDomains === '') {
            return true;
        }

        if ($testHost === null || $testHost === '') {
            return $isDev;
        }

        $testHost = strtolower($testHost);
        $domains = preg_split('/[\s,]+/', $allowedDomains, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($domains)) {
            return true;
        }

        foreach ($domains as $domain) {
            $d = strtolower(trim($domain));
            $d = preg_replace('/:\d+$/', '', $d);

            if ($d === $testHost) {
                return true;
            }

            if (str_ends_with($testHost, '.' . $d)) {
                return true;
            }
        }

        return false;
    }
}
