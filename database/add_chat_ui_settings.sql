-- Migration to add Chat UI appearance and starter message settings
ALTER TABLE ai_assistants
ADD COLUMN IF NOT EXISTS theme_primary_color VARCHAR(20) DEFAULT '#00B957',
ADD COLUMN IF NOT EXISTS theme_secondary_color VARCHAR(20) DEFAULT '#F3F4F6',
ADD COLUMN IF NOT EXISTS theme_text_color VARCHAR(20) DEFAULT '#111827',
ADD COLUMN IF NOT EXISTS theme_header_text_color VARCHAR(20) DEFAULT '#FFFFFF',
ADD COLUMN IF NOT EXISTS user_bubble_color VARCHAR(20) DEFAULT '#ECFDF3',
ADD COLUMN IF NOT EXISTS assistant_bubble_color VARCHAR(20) DEFAULT '#EAEAEA',
ADD COLUMN IF NOT EXISTS chat_background_color VARCHAR(20) DEFAULT '#FFFFFF',
ADD COLUMN IF NOT EXISTS starter_messages TEXT NULL,
ADD COLUMN IF NOT EXISTS header_subtitle VARCHAR(255) DEFAULT 'AI Assistant';

UPDATE ai_assistants
SET theme_primary_color = COALESCE(theme_primary_color, '#00B957'),
    theme_secondary_color = COALESCE(theme_secondary_color, '#F3F4F6'),
    theme_text_color = COALESCE(theme_text_color, '#111827'),
    theme_header_text_color = COALESCE(theme_header_text_color, '#FFFFFF'),
    user_bubble_color = COALESCE(user_bubble_color, '#ECFDF3'),
    assistant_bubble_color = COALESCE(assistant_bubble_color, '#EAEAEA'),
    chat_background_color = COALESCE(chat_background_color, '#FFFFFF'),
    starter_messages = COALESCE(starter_messages, '["What classes do I have?","Show my attendance","Who are my teachers?","When is my next class?"]'),
    header_subtitle = COALESCE(header_subtitle, 'AI Assistant')
WHERE id = 1;
