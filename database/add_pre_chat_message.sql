-- Migration to add pre-chat message settings to ai_assistants
ALTER TABLE ai_assistants
ADD COLUMN IF NOT EXISTS pre_chat_enabled TINYINT(1) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS pre_chat_message TEXT NULL,
ADD COLUMN IF NOT EXISTS pre_chat_delay INT UNSIGNED NOT NULL DEFAULT 3,
ADD COLUMN IF NOT EXISTS pre_chat_display_mode VARCHAR(20) NOT NULL DEFAULT 'always';
