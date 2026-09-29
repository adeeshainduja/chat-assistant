# GETMORE AI Assistant — Plain PHP Starter

This is a **separate plain-PHP project** designed to be embedded into the existing GETMORE tuition system.

## V1 scope

The student AI can only use the permissions enabled in the AI admin panel:

- Class Details — only the logged-in student's enrolled classes
- My Attendance Details — only the logged-in student's attendance
- Teacher Details — only approved teacher names associated with the student's enrolled classes

There is **no arbitrary SQL tool** and no generic database access.

## Requirements

- PHP 8.1+
- cURL extension
- PDO MySQL extension
- MySQL/MariaDB
- A Google Gemini API key
- Existing GETMORE student login/session
- The small GETMORE bridge included in `../getmore-integration`

## 1. Create the AI database

Create a new database, for example:

`getmore_ai`

Import:

`database/schema.sql`

## 2. Configure the AI project

Copy:

`.env.example`

to:

`.env`

Fill in the AI database credentials, Gemini API key and model, GETMORE base URL, and a long shared secret.

Generate a shared secret:

`php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`

Use the **same secret** in the GETMORE bridge configuration.

Generate the admin password hash:

`php scripts/generate_admin_hash.php "YourStrongPassword"`

Copy the output into:

`ADMIN_PASSWORD_HASH=...`

## 3. Deploy

Example deployment path:

`public_html/ai-assistant/`

Set:

`APP_BASE_PATH=/ai-assistant`

The AI project remains a separate codebase/repository even though it is served under the existing GETMORE domain.

## 4. Install the GETMORE bridge

See:

`../getmore-integration/README.md`

That bridge adds:

- `ai-auth.php`
- `/api/ai/my-classes.php`
- `/api/ai/my-attendance.php`
- `/api/ai/teachers.php`

The bridge uses the existing GETMORE database and student session.

## 5. Embed the widget

Add this to the common logged-in student layout/footer:

```html
<script
    src="/ai-assistant/widget.js"
    data-auth-endpoint="/ai-auth.php"
    data-label="AI">
</script>
```

The student remains inside GETMORE. The separate AI project renders the floating chat widget.

## 6. Open the admin panel

`/ai-assistant/admin/login.php`

The administrator can edit:

- Assistant name
- Welcome message
- Description
- Purpose
- Enable/disable assistant
- Class Details permission
- My Attendance permission
- Teacher Details permission

Security rules such as "attendance is only the logged-in student" are hard-coded and are not editable from the admin panel.

## 7. Run the environment check

From the AI project directory:

`php scripts/check.php`

## Important security properties

- Student ID is never accepted from the browser or from the AI model.
- GETMORE creates a short-lived signed token from its existing student session.
- The AI backend verifies that token.
- GETMORE API endpoints verify the same token again.
- Attendance and class queries always use the verified student ID.
- Teacher endpoint returns only approved fields.
- Gemini API key remains server-side.
- Function/tool permissions are dynamically built from the admin panel.
- A disabled permission is not exposed as an AI tool.
- PHP re-checks the permission before executing any tool.
"# Getmore-AI-Chatbot" 
