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
            'SELECT * FROM assistants WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);

        $assistant = $stmt->fetch();

        if (!$assistant) {
            throw new RuntimeException('Assistant configuration was not found.');
        }

        return $assistant;
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

    public function save(
        int $assistantId,
        array $settings,
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
                trim((string) ($settings['name'] ?? '')),
                trim((string) ($settings['description'] ?? '')),
                trim((string) ($settings['purpose'] ?? '')),
                trim((string) ($settings['welcome_message'] ?? '')),
                !empty($settings['enabled']) ? 1 : 0,
                trim((string) ($settings['theme_primary_color'] ?? '#00B957')),
                trim((string) ($settings['theme_secondary_color'] ?? '#F3F4F6')),
                trim((string) ($settings['theme_text_color'] ?? '#111827')),
                trim((string) ($settings['theme_header_text_color'] ?? '#FFFFFF')),
                trim((string) ($settings['user_bubble_color'] ?? '#ECFDF3')),
                trim((string) ($settings['assistant_bubble_color'] ?? '#EAEAEA')),
                trim((string) ($settings['chat_background_color'] ?? '#FFFFFF')),
                isset($settings['starter_messages']) && $settings['starter_messages'] !== '' ? (string) $settings['starter_messages'] : null,
                trim((string) ($settings['header_subtitle'] ?? 'AI Assistant')),
                $assistantId,
            ]);

            $disable = $this->pdo->prepare(
                'UPDATE assistant_permissions
                 SET enabled = 0
                 WHERE assistant_id = ?'
            );
            $disable->execute([$assistantId]);

            $enable = $this->pdo->prepare(
                'UPDATE assistant_permissions
                 SET enabled = 1
                 WHERE assistant_id = ?
                   AND permission_key = ?'
            );

            foreach ($enabledPermissionKeys as $key) {
                $enable->execute([$assistantId, $key]);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
