# cPanel Production Deployment Guide

**Application:** GETMORE AI Assistant  
**Production Domain:** [https://ai.getmore.lk](https://ai.getmore.lk)  
**Deployment Type:** cPanel Subdomain Root (`APP_BASE_PATH=/`)  
**PHP Version:** 8.2+  

---

## 1. Create Subdomain in cPanel
1. Log into your cPanel account.
2. Navigate to **Domains** &rarr; **Domains** (or **Subdomains** in older cPanel themes).
3. Click **Create A New Domain**.
4. Enter domain name:
   ```text
   ai.getmore.lk
   ```
5. Uncheck *"Share document root"* if prompted.
6. Set the **Document Root** (e.g. `public_html/ai` or `ai.getmore.lk`).
   - The application files will sit directly in this directory (there is NO `/ai-assistant` subfolder in production).

---

## 2. Prepare and Upload Application Files
1. You can either upload the pre-assembled clean files from the [`production-package/`](file:///c:/xampp/htdocs/ai-assistant/production-package) directory or create a zip archive of that folder.
2. In cPanel **File Manager**, open the subdomain document root.
3. Upload the files or zip archive and extract directly into the subdomain root:
   ```text
   ai.getmore.lk/
   ├── admin/
   ├── api/
   ├── assets/
   ├── config/
   ├── database/
   │   ├── production_schema.sql
   │   └── production_table_rename.sql
   ├── src/
   ├── storage/
   │   └── ratelimit/
   │       └── .gitkeep
   ├── .htaccess
   ├── .env.production.example
   ├── bootstrap.php
   ├── chat.php
   ├── index.php
   └── widget.js
   ```
4. Do NOT upload local development files listed in [`DEPLOY_EXCLUDE.txt`](file:///c:/xampp/htdocs/ai-assistant/DEPLOY_EXCLUDE.txt) (`.git`, local `.env`, test scripts, admin cookies, log files).

---

## 3. Configure PHP 8.2+ & Extensions
1. In cPanel, navigate to **Software** &rarr; **Select PHP Version** (or **MultiPHP Manager**).
2. Select PHP **8.2** (or PHP 8.3).
3. Under **PHP Extensions** (or **PHP INI Options**), ensure the following required modules are enabled:
   - `pdo_mysql` (Database connectivity)
   - `curl` (Outbound requests to Gemini AI & GETMORE REST API)
   - `mbstring` (Multilingual support for Sinhala, Tamil, English)
   - `openssl` (AES-256 API key encryption & HTTPS verification)
   - `json` (API payloads and responses)
   - `session` (Admin authentication and session rate-limiting fallback)

---

## 4. Create MySQL Database & User
1. In cPanel, open **MySQL® Databases**.
2. **Create New Database**:
   - Example: `cpaneluser_getmore_ai`
3. **Create New MySQL User**:
   - Example: `cpaneluser_aiuser`
   - Generate a strong password and save it securely.
4. **Add User To Database**:
   - Select the user and database.
   - Click **Add**.
   - Check **ALL PRIVILEGES** and click **Make Changes**.

---

## 5. Import Database Schema or Rename Existing Tables

### Case A: Fresh Deployment (New Database)
1. Open **phpMyAdmin** from cPanel.
2. Select your newly created database (`cpaneluser_getmore_ai`).
3. Click the **Import** tab.
4. Choose [`database/production_schema.sql`](file:///c:/xampp/htdocs/ai-assistant/database/production_schema.sql) and click **Import**.
5. This creates the four standardized lowercase tables with utf8mb4 collation:
   - `ai_assistants`
   - `ai_assistant_institutes`
   - `ai_assistant_permissions`
   - `ai_institute_integrations`

### Case B: Upgrading an Existing Database with Data
If your database already contains existing records with mixed-case (`Ai_*`) or legacy names:
1. In phpMyAdmin, select your database.
2. Click the **Import** or **SQL** tab.
3. Run [`database/production_table_rename.sql`](file:///c:/xampp/htdocs/ai-assistant/database/production_table_rename.sql).
4. This safely renames existing tables without losing any data or dropping tables.

---

## 6. Configure Production Environment (`.env`)
1. In the subdomain document root, copy `.env.production.example` to `.env`:
   - Using cPanel File Manager: Right-click `.env.production.example` &rarr; **Copy** &rarr; Name new file `.env`.
2. Edit `.env` and set your production values:
   ```ini
   APP_ENV=production
   APP_DEBUG=false
   DEV_MODE=false
   APP_BASE_PATH=/
   APP_TIMEZONE=Asia/Colombo

   AI_DB_HOST=localhost
   AI_DB_PORT=3306
   AI_DB_NAME=cpaneluser_getmore_ai
   AI_DB_USER=cpaneluser_aiuser
   AI_DB_PASSWORD=YourSecureDatabasePasswordHere

   GETMORE_DATA_MODE=api
   GETMORE_API_BASE_URL=https://demo.getmore.lk
   GETMORE_API_KEY=your_actual_getmore_api_key
   GETMORE_API_AUTH_TYPE=x-api-key

   GETMORE_CLASSES_ENDPOINT=/api/v1/classes
   GETMORE_LECTURERS_ENDPOINT=/api/v1/lecturers
   GETMORE_EXTRA_CLASSES_ENDPOINT=/api/v1/extra-classes
   GETMORE_ATTENDANCE_ENDPOINT=/api/v1/student/attendance/today

   GEMINI_API_KEY=your_actual_gemini_api_key
   GEMINI_MODEL=gemini-3.8-flash

   APP_ENCRYPTION_KEY=your_32_character_random_hex_string

   ADMIN_USERNAME=admin
   ADMIN_PASSWORD_HASH=your_bcrypt_hash_here

   CHAT_RATE_LIMIT=20
   CHAT_RATE_WINDOW=60

   ATTENDANCE_MAX_ATTEMPTS=5
   ATTENDANCE_BLOCK_SECONDS=600
   ATTENDANCE_VERIFICATION_TTL=1800
   ```

> [!NOTE]
> To generate an admin password hash, run `password_hash('your_password', PASSWORD_BCRYPT)` or use PHP CLI:
> `php -r "echo password_hash('YourPassword', PASSWORD_BCRYPT);"`

---

## 7. Storage Permissions
1. In cPanel File Manager, check the directory:
   ```text
   storage/ratelimit/
   ```
2. Recommended permission: **`755`** (or `775` depending on server PHP-FPM / suPHP configuration).
3. Do NOT make it world-writable (`777`).
4. The system contains safe session fallback so that if storage is temporarily unwritable, rate-limiting continues functioning and logs a warning to server logs.

---

## 8. Configure SSL Certificate
1. In cPanel, navigate to **Security** &rarr; **SSL/TLS Status** (or **Let's Encrypt™ SSL**).
2. Select `ai.getmore.lk`.
3. Click **Run AutoSSL** (or Issue Let's Encrypt Certificate).
4. Verify HTTPS is active at:
   ```text
   https://ai.getmore.lk
   ```

---

## 9. Verification & Test URLs

After deployment, test the following URLs in your browser:

1. **Root Redirect (Redirects to Admin Login):**
   ```text
   https://ai.getmore.lk/
   ```
   *Expected result:* Smooth redirect to `https://ai.getmore.lk/admin/login.php`.

2. **Admin Dashboard Login:**
   ```text
   https://ai.getmore.lk/admin/login.php
   ```
   *Expected result:* Secure login screen with no localhost references.

3. **Public Widget Script:**
   ```text
   https://ai.getmore.lk/widget.js
   ```
   *Expected result:* HTTP 200 serving clean JavaScript.

4. **Public Chat Interface:**
   ```text
   https://ai.getmore.lk/chat.php?assistant=YOUR_PUBLIC_WIDGET_KEY
   ```
   *Expected result:* Full responsive chat interface rendered with custom theme colors, starter questions, and Sinhala/Tamil/English language selector.

---

## 10. Customer Website Widget Embed

To embed the AI Assistant widget on any client or institute website (e.g. `https://school.lk`), provide the client with:

```html
<script
    src="https://ai.getmore.lk/widget.js"
    data-assistant="PUBLIC_WIDGET_KEY">
</script>
```

Replace `PUBLIC_WIDGET_KEY` with the institute's specific key generated in the Admin Dashboard (e.g. `pk_achieve_72af8391`).

---

## 11. Production Architecture & GETMORE API Notes

### API Mode Architecture
- `GETMORE_DATA_MODE=api` is enforced for production.
- Outbound requests to `https://demo.getmore.lk` verify SSL certificates (`CURLOPT_SSL_VERIFYPEER => true`).
- Classes, courses, lecturers, and new/extra classes are fetched in real-time from the GETMORE REST API.

### Attendance Verification Notice
- Student attendance verification requires matching student public index numbers against parent/guardian mobile numbers.
- If the external GETMORE REST API instance does not provide an endpoint for student/parent authentication, attendance verification will connect to the GETMORE database directly using `GETMORE_DB_*` credentials in `.env`.
- If attendance verification is disabled in Assistant Permissions, no direct database connection to GETMORE is opened.

---

## 12. Security Checklist
- [x] `.env` is inaccessible from browser (verified via `.htaccess` FilesMatch and rewrite rules).
- [x] `APP_DEBUG=false` hides PHP stack traces, database credentials, and internal paths from visitors.
- [x] `ai_base_path()` resolves to root `/` with no `/ai-assistant` subfolder.
- [x] Allowed domain validation permits `ai.getmore.lk` iframe requests while rejecting unauthorized external domains.
- [x] Outbound cURL validates SSL peer certificates in production.
- [x] Rate limiting is active on `/api/chat.php`.
- [x] Database tables use uniform lowercase names (`ai_assistants`, `ai_assistant_institutes`, `ai_assistant_permissions`, `ai_institute_integrations`).
