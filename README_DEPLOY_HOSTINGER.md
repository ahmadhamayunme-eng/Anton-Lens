# Anton Lens — Hostinger Deployment (public_html)

## 1) Upload files
1. In Hostinger File Manager, upload this project into `public_html/markup` (or directly `public_html`).
2. Ensure these folders exist and are writable: `storage/logs`, `storage/screenshots`, `storage/tmp`.

## 2) Create database
1. In hPanel, create a MySQL database + user.
2. Open `config/config.php` and set DB + `APP_BASE_URL` + `APP_SECRET` via env vars or direct values.

## 3) Run SQL migrations
Run each SQL file in order inside phpMyAdmin:
1. `database/migrations/001_create_users.sql`
2. `database/migrations/002_create_sessions.sql`
3. `database/migrations/003_create_clients_projects.sql`
4. `database/migrations/004_create_share_guest.sql`
5. `database/migrations/005_create_feedback_tables.sql`
6. `database/migrations/006_create_jobs_activity.sql`

### Minimal migration runner note
Hostinger shared hosting can use phpMyAdmin import in numeric order; this project intentionally keeps SQL migrations as standalone files for manual execution.

## 4) Create first admin
Insert manually in phpMyAdmin:
```sql
INSERT INTO users (name,email,password_hash,role,is_active,created_at,updated_at)
VALUES ('Admin','admin@example.com','$2y$10$replace_with_password_hash','admin',1,NOW(),NOW());
```
Generate hash locally:
```bash
php -r "echo password_hash('ChangeMeNow!', PASSWORD_DEFAULT), PHP_EOL;"
```

## 5) Apache rewrite + storage protection
- Root `.htaccess` routes everything to `index.php` and blocks `/storage/*`.
- `storage/.htaccess` denies direct access.

## 6) Cron for async screenshot jobs
In Hostinger Cron Jobs, add (every minute):
```bash
php /home/<hostinger_user>/public_html/markup/bin/run-jobs.php
```
Adjust path if deploying to `public_html` root.

## 7) Login
Open `/login`, use admin credentials, then create clients and projects.

## File tree
```text
.
├── .htaccess
├── AGENTS.md
├── README_DEPLOY_HOSTINGER.md
├── index.php
├── app
│   ├── Controllers
│   ├── Middleware
│   ├── Repositories
│   ├── Security
│   │   └── ProxyGuard.php
│   ├── Services
│   │   ├── Auth.php
│   │   ├── Csrf.php
│   │   ├── Database.php
│   │   ├── PlaceholderScreenshotProvider.php
│   │   ├── ScreenshotProviderInterface.php
│   │   └── View.php
│   └── Views
│       ├── auth/login.php
│       ├── layouts/footer.php
│       ├── layouts/header.php
│       └── projects/{index.php,new.php,show.php,viewer.php}
├── bin
│   └── run-jobs.php
├── config
│   └── config.php
├── database
│   └── migrations
│       ├── 001_create_users.sql
│       ├── 002_create_sessions.sql
│       ├── 003_create_clients_projects.sql
│       ├── 004_create_share_guest.sql
│       ├── 005_create_feedback_tables.sql
│       └── 006_create_jobs_activity.sql
├── public
│   └── assets
│       ├── app.js
│       └── styles.css
└── storage
    ├── .htaccess
    ├── logs
    ├── screenshots
    └── tmp
```
