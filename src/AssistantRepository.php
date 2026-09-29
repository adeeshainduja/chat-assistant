<?php
declare(strict_types=1);

final class AssistantRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function getAssistant(int $id = 1): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*,
                    i.id AS institute_id,
                    i.name AS institute_name,
                    i.public_widget_key,
                    i.is_active AS institute_active,
                    i.allowed_domains
             FROM assistants a
             LEFT JOIN institutes i ON i.id = a.institute_id
             WHERE a.id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);

        $assistant = $stmt->fetch();

        if (!$assistant) {
            throw new RuntimeException('Assistant configuration was not found.');
        }

        return $assistant;
    }

    public function getByWidgetKey(string $widgetKey): ?array
    {
        $widgetKey = trim($widgetKey);
        if ($widgetKey === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT a.*,
                    i.id AS institute_id,
                    i.name AS institute_name,
                    i.public_widget_key,
                    i.is_active AS institute_active,
                    i.allowed_domains
             FROM assistants a
             INNER JOIN institutes i ON i.id = a.institute_id
             WHERE i.public_widget_key = ?
             LIMIT 1'
        );
        $stmt->execute([$widgetKey]);

        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function getByInstituteId(int $instituteId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.*,
                    i.id AS institute_id,
                    i.name AS institute_name,
                    i.public_widget_key,
                    i.is_active AS institute_active,
                    i.allowed_domains
             FROM assistants a
             INNER JOIN institutes i ON i.id = a.institute_id
             WHERE a.institute_id = ?
             LIMIT 1'
        );
        $stmt->execute([$instituteId]);

        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function getPermissions(int $assistantId = 1): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT permission_key, permission_name, enabled
             FROM assistant_permissions
             WHERE assistant_id = ?
             ORDER BY id'
        );
        $stmt->execute([$assistantId]);

        $result = [];

        foreach ($stmt->fetchAll() as $row) {
            $result[$row['permission_key']] = [
                'name' => $row['permission_name'],
                'enabled' => (bool) $row['enabled'],
            ];
        }

        return $result;
    }

    public function saveAssistantAndInstitute(
        int $assistantId,
        int $instituteId,
        array $instituteData,
        array $assistantSettings,
        array $enabledPermissionKeys
    ): void {
        $allowedPermissions = [
            'class_details',
            'attendance_details',
            'teacher_details',
        ];

        $enabledPermissionKeys = array_values(array_intersect(
            $allowedPermissions,
            $enabledPermissionKeys
        ));

        $this->pdo->beginTransaction();

        try {
            // 1. Update Institute
            if (!empty($instituteData)) {
                $instStmt = $this->pdo->prepare(
                    'UPDATE institutes
                     SET name = ?,
                         allowed_domains = ?,
                         updated_at = NOW()
                     WHERE id = ?'
                );
                $instStmt->execute([
                    trim((string) ($instituteData['name'] ?? '')),
                    isset($instituteData['allowed_domains']) ? trim((string) $instituteData['allowed_domains']) : null,
                    $instituteId,
                ]);
            }

            // 2. Update Assistant
            $stmt = $this->pdo->prepare(
                'UPDATE assistants
                 SET name = ?,
                     description = ?,
                     purpose = ?,
                     welcome_message = ?,
                     enabled = ?,
                     theme_primary_color = ?,
                     theme_secondary_color = ?,
                     theme_text_color = ?,
                     theme_header_text_color = ?,
                     user_bubble_color = ?,
                     assistant_bubble_color = ?,
                     chat_background_color = ?,
                     starter_messages = ?,
                     header_subtitle = ?
                 WHERE id = ?'
            );

            $stmt->execute([
                trim((string) ($assistantSettings['name'] ?? '')),
                trim((string) ($assistantSettings['description'] ?? '')),
                trim((string) ($assistantSettings['purpose'] ?? '')),
                trim((string) ($assistantSettings['welcome_message'] ?? '')),
                !empty($assistantSettings['enabled']) ? 1 : 0,
                trim((string) ($assistantSettings['theme_primary_color'] ?? '#00B957')),
                trim((string) ($assistantSettings['theme_secondary_color'] ?? '#F3F4F6')),
                trim((string) ($assistantSettings['theme_text_color'] ?? '#111827')),
                trim((string) ($assistantSettings['theme_header_text_color'] ?? '#FFFFFF')),
                trim((string) ($assistantSettings['user_bubble_color'] ?? '#ECFDF3')),
                trim((string) ($assistantSettings['assistant_bubble_color'] ?? '#EAEAEA')),
                trim((string) ($assistantSettings['chat_background_color'] ?? '#FFFFFF')),
                isset($assistantSettings['starter_messages']) && $assistantSettings['starter_messages'] !== ''
                    ? (string) $assistantSettings['starter_messages']
                    : null,
                trim((string) ($assistantSettings['header_subtitle'] ?? 'AI Assistant')),
                $assistantId,
            ]);

            // 3. Update Permissions
            $disable = $this->pdo->prepare(
                'UPDATE assistant_permissions
                 SET enabled = 0
                 WHERE assistant_id = ?'
            );
            $disable->execute([$assistantId]);

            $enable = $this->pdo->prepare(
                'INSERT INTO assistant_permissions (assistant_id, permission_key, permission_name, enabled)
                 VALUES (?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE enabled = 1'
            );

            $permissionLabels = [
                'class_details' => 'Public Class Details',
                'teacher_details' => 'Public Teacher Details',
                'attendance_details' => 'Attendance Access',
            ];

            foreach ($enabledPermissionKeys as $key) {
                $label = $permissionLabels[$key] ?? $key;
                $enable->execute([$assistantId, $key, $label]);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function save(
        int $assistantId,
        array $settings,
        array $enabledPermissionKeys
    ): void {
        $assistant = $this->getAssistant($assistantId);
        $instituteId = (int) ($assistant['institute_id'] ?? 1);

        $instituteData = [];
        if (isset($settings['institute_name'])) {
            $instituteData['name'] = $settings['institute_name'];
        }
        if (isset($settings['allowed_domains'])) {
            $instituteData['allowed_domains'] = $settings['allowed_domains'];
        }

        $this->saveAssistantAndInstitute(
            $assistantId,
            $instituteId,
            $instituteData,
            $settings,
            $enabledPermissionKeys
        );
    }
}
