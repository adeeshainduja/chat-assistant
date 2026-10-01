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
             FROM ai_assistants a
             LEFT JOIN ai_assistant_institutes i ON i.id = a.institute_id
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
             FROM ai_assistants a
             INNER JOIN ai_assistant_institutes i ON i.id = a.institute_id
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
             FROM ai_assistants a
             INNER JOIN ai_assistant_institutes i ON i.id = a.institute_id
             WHERE a.institute_id = ?
             LIMIT 1'
        );
        $stmt->execute([$instituteId]);

        $row = $stmt->fetch();

        if (is_array($row)) {
            return $row;
        }

        // If no assistant exists yet for this institute, create a default one
        $instStmt = $this->pdo->prepare('SELECT id, name FROM ai_assistant_institutes WHERE id = ? LIMIT 1');
        $instStmt->execute([$instituteId]);
        $inst = $instStmt->fetch();
        if (!$inst) {
            return null;
        }

        $assistantId = $this->createDefaultAssistant($instituteId, 'AI Assistant');
        return $this->getAssistant($assistantId);
    }

    public function createDefaultAssistant(int $instituteId, string $assistantName = 'AI Assistant'): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ai_assistants (
                institute_id, name, header_subtitle, welcome_message,
                description, purpose, enabled, theme_primary_color
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $instituteId,
            $assistantName,
            'AI Assistant',
            'Hello! How can I help you today?',
            'Official AI assistant.',
            'Help visitors learn about classes, schedules, and institute information.',
            1,
            '#00B957'
        ]);

        $assistantId = (int) $this->pdo->lastInsertId();

        $defaultPermissions = [
            'institute_details' => 'Institute Details',
            'class_details' => 'Classes / Courses',
            'teacher_details' => 'Teacher Details',
            'new_courses' => 'New / Upcoming Courses',
            'attendance_details' => 'Attendance',
        ];

        $permStmt = $this->pdo->prepare(
            'INSERT INTO ai_assistant_permissions (assistant_id, permission_key, permission_name, enabled)
             VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE enabled = 1'
        );

        foreach ($defaultPermissions as $key => $label) {
            $permStmt->execute([$assistantId, $key, $label]);
        }

        return $assistantId;
    }

    public function getPermissions(int $assistantId = 1): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT permission_key, permission_name, enabled
             FROM ai_assistant_permissions
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
            'institute_details',
            'class_details',
            'teacher_details',
            'new_courses',
            'attendance_details',
        ];

        $enabledPermissionKeys = array_values(array_intersect(
            $allowedPermissions,
            $enabledPermissionKeys
        ));

        $this->ensurePreChatColumns();

        $this->pdo->beginTransaction();

        try {
            // 1. Update Institute
            if (!empty($instituteData)) {
                $instRepo = new InstituteRepository($this->pdo);
                $instRepo->update($instituteId, $instituteData);
            }

            // 2. Update Assistant
            $stmt = $this->pdo->prepare(
                'UPDATE ai_assistants
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
                     header_subtitle = ?,
                     pre_chat_enabled = ?,
                     pre_chat_message = ?,
                     pre_chat_delay = ?,
                     pre_chat_display_mode = ?
                 WHERE id = ?'
            );

            $preChatDisplayMode = (string) ($assistantSettings['pre_chat_display_mode'] ?? 'always');
            if (!in_array($preChatDisplayMode, ['always', 'once_session', 'once_visitor'], true)) {
                $preChatDisplayMode = 'always';
            }

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
                !empty($assistantSettings['pre_chat_enabled']) ? 1 : 0,
                isset($assistantSettings['pre_chat_message']) && trim((string) $assistantSettings['pre_chat_message']) !== ''
                    ? trim((string) $assistantSettings['pre_chat_message'])
                    : null,
                isset($assistantSettings['pre_chat_delay']) ? max(0, (int) $assistantSettings['pre_chat_delay']) : 3,
                $preChatDisplayMode,
                $assistantId,
            ]);

            // 3. Update Permissions
            $disable = $this->pdo->prepare(
                'UPDATE ai_assistant_permissions
                 SET enabled = 0
                 WHERE assistant_id = ?'
            );
            $disable->execute([$assistantId]);

            $enable = $this->pdo->prepare(
                'INSERT INTO ai_assistant_permissions (assistant_id, permission_key, permission_name, enabled)
                 VALUES (?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE enabled = 1'
            );

            $permissionLabels = [
                'institute_details' => 'Institute Details',
                'class_details' => 'Classes / Courses',
                'teacher_details' => 'Teacher Details',
                'new_courses' => 'New / Upcoming Courses',
                'attendance_details' => 'Attendance',
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
        $instituteFields = [
            'name',
            'allowed_domains',
            'short_description',
            'about_institute',
            'public_address',
            'public_phone',
            'public_email',
            'website',
            'opening_hours',
            'registration_info',
            'facilities_services',
            'public_notes',
        ];

        foreach ($instituteFields as $field) {
            if (isset($settings['institute_' . $field])) {
                $instituteData[$field] = $settings['institute_' . $field];
            } elseif (isset($settings[$field])) {
                $instituteData[$field] = $settings[$field];
            }
        }

        $this->saveAssistantAndInstitute(
            $assistantId,
            $instituteId,
            $instituteData,
            $settings,
            $enabledPermissionKeys
        );
    }

    private function ensurePreChatColumns(): void
    {
        try {
            $cols = $this->pdo->query("SHOW COLUMNS FROM ai_assistants LIKE 'pre_chat_enabled'")->fetchAll();
            if (empty($cols)) {
                $this->pdo->exec("ALTER TABLE ai_assistants ADD COLUMN pre_chat_enabled TINYINT(1) NOT NULL DEFAULT 0");
                $this->pdo->exec("ALTER TABLE ai_assistants ADD COLUMN pre_chat_message TEXT NULL");
                $this->pdo->exec("ALTER TABLE ai_assistants ADD COLUMN pre_chat_delay INT UNSIGNED NOT NULL DEFAULT 3");
                $this->pdo->exec("ALTER TABLE ai_assistants ADD COLUMN pre_chat_display_mode VARCHAR(20) NOT NULL DEFAULT 'always'");
            }
        } catch (Throwable $e) {
            // Ignore if columns already exist or restricted DB user
        }
    }
}
