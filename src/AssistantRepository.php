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
                     enabled = ?
                 WHERE id = ?'
            );

            $stmt->execute([
                trim((string) ($settings['name'] ?? '')),
                trim((string) ($settings['description'] ?? '')),
                trim((string) ($settings['purpose'] ?? '')),
                trim((string) ($settings['welcome_message'] ?? '')),
                !empty($settings['enabled']) ? 1 : 0,
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
