<?php
declare(strict_types=1);

final class InstituteRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM institutes WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function getByWidgetKey(string $widgetKey): ?array
    {
        $widgetKey = trim($widgetKey);
        if ($widgetKey === '') {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM institutes WHERE public_widget_key = ? LIMIT 1');
        $stmt->execute([$widgetKey]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM institutes ORDER BY id ASC');
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $name = trim((string) ($data['name'] ?? ''));
        $key = trim((string) ($data['public_widget_key'] ?? ''));
        if ($key === '') {
            $key = self::generateWidgetKey($name);
        }
        $isActive = !empty($data['is_active']) ? 1 : 0;
        $allowedDomains = isset($data['allowed_domains']) ? trim((string) $data['allowed_domains']) : null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO institutes (name, public_widget_key, is_active, allowed_domains)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$name, $key, $isActive, $allowedDomains]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $fields = [];
        $params = [];

        if (array_key_exists('name', $data)) {
            $fields[] = 'name = ?';
            $params[] = trim((string) $data['name']);
        }

        if (array_key_exists('is_active', $data)) {
            $fields[] = 'is_active = ?';
            $params[] = !empty($data['is_active']) ? 1 : 0;
        }

        if (array_key_exists('allowed_domains', $data)) {
            $fields[] = 'allowed_domains = ?';
            $val = trim((string) $data['allowed_domains']);
            $params[] = $val !== '' ? $val : null;
        }

        if ($fields === []) {
            return;
        }

        $params[] = $id;
        $sql = 'UPDATE institutes SET ' . implode(', ', $fields) . ', updated_at = NOW() WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }

    public static function generateWidgetKey(string $name): string
    {
        $slug = preg_replace('/[^a-z0-9]/', '', strtolower($name));
        $prefix = substr($slug !== '' ? $slug : 'institute', 0, 8);
        $randomHex = bin2hex(random_bytes(4));

        return sprintf('pk_%s_%s', $prefix, $randomHex);
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
        // In local development mode, always allow localhost / loopback addresses
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

        // If no allowed domains are configured, default to allowing or require explicit match
        $allowedDomains = trim((string) $allowedDomains);
        if ($allowedDomains === '') {
            return true;
        }

        if ($testHost === null || $testHost === '') {
            // Direct request without origin/referer
            return $isDev;
        }

        $testHost = strtolower($testHost);
        $domains = preg_split('/[\s,]+/', $allowedDomains, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($domains)) {
            return true;
        }

        foreach ($domains as $domain) {
            $d = strtolower(trim($domain));
            // Strip port if present in domain string
            $d = preg_replace('/:\d+$/', '', $d);

            if ($d === $testHost) {
                return true;
            }

            // Subdomain match: if allowed is "example.com", allow "www.example.com"
            if (str_ends_with($testHost, '.' . $d)) {
                return true;
            }
        }

        return false;
    }
}
