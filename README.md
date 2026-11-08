# AgriCare - Crop Health and Advisory System

A web-based system where farmers report crop problems (with symptoms and photographs) and
administrators/advisers review the reports and give practical guidance.

Developed for the **Web Technologies** course, Department of Computer Science,
University of the Punjab, Lahore.

| Roll No. | Name |
|---|---|
| BCSF24A020 | Muhammad Rayyan |
| BCSF24A037 | Muhammad Abdullah Amin |

Instructor: Sir Numan Shafi

---

## Features

**Farmer**
- Register, log in, log out, update account information and password
- Add, edit and delete farms (a farm with reports cannot be deleted)
- Record crops grown on each farm with a planting date, and edit that date later (future dates and duplicate records are refused)
- Submit crop problem reports with symptoms and an optional photograph (JPG, PNG, WEBP, max 2 MB)
- Track report status: Pending, Under Review, Resolved
- Read adviser recommendations and the possible disease
- Add follow-up progress updates (history is never overwritten)
- Dashboard with live statistics, recent reports and latest advice

**Admin / Adviser**
- Dashboard with report statistics, pending reports and recent reviewer activity
- Searchable and filterable report list (status, crop, farmer, date range)
- Review page: farmer details, farm and crop details, symptoms, photograph, follow-up notes
- Select a possible disease from the database, write advice and change the report status
- Every response is saved as a new record in the `advice` table
- Add and edit crops and diseases

**Public**
- Home page, About page
- Crop and Disease Guide with search and crop filter (data from MySQL)

> AgriCare does **not** diagnose diseases automatically. Disease identification and advice
> are entered by an authorised reviewer.

## Technology stack

| Technology | Role |
|---|---|
| Linux (Ubuntu) | Operating system |
| Apache | Web server |
| MySQL | Database |
| PHP 8 + PDO | Server-side code and database access (no frameworks) |
| HTML5, CSS3, JavaScript | Pages, styling and small interactions (no frameworks) |

## Security features

- Passwords hashed with `password_hash()` (bcrypt) and checked with `password_verify()`; passwords must be 8-72 bytes (bcrypt ignores everything after 72) with a letter and a number
- PDO prepared statements for all database queries
- Sessions: ID regenerated on login (session fixation), strict mode, cookie-only IDs, `HttpOnly`, `SameSite=Lax`, `Secure` flag switched on automatically over HTTPS (plain HTTP still works for local development), full destruction on logout, 30-minute idle timeout
- Logout is a POST form with a CSRF token, so another website cannot log a user out
- Login limit: 5 failed attempts for the same email from the same IP address within 15 minutes are refused (table `login_attempts`); unknown emails take about the same time as wrong passwords
- Role-based access control on every protected page (`requireRole()`)
- Ownership checks: a farmer can only see and change their own farms, crops and reports
- Output escaping with `htmlspecialchars()` against XSS, plus a Content-Security-Policy that only allows scripts, styles and images from the site itself
- Other headers: `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Strict-Transport-Security` (HTTPS only) and `Cache-Control: no-store` for logged-in pages
- CSRF token on every POST form
- Server-side validation of all inputs
- Image upload checks: size limit, real image type check, random server-side file name, PHP execution disabled in the upload folder
- Database errors are logged, never shown to visitors
- Database credentials are kept in `config/config.php`, which is **not** stored in Git
- Database scripts never drop data during normal setup (see "Create the database")

## Project structure

```text
AgriCare/
├── config/
│   ├── config.example.php     sample settings (copy to config.php)
│   └── database.php           PDO connection (sets MySQL time zone to Pakistan time)
├── database/
│   ├── schema.sql             tables and views only (safe to re-run, never drops data)
│   ├── seed.sql               sample data for testing (run once)
│   └── dev_reset.sql          DESTRUCTIVE: deletes all tables (development only)
├── includes/                  init, auth (sessions), csrf, security headers, login throttle,
│                              helper functions, header/footer/sidebars
├── public/                    Apache DocumentRoot
│   ├── index.php, about.php, guide.php, register.php, login.php, logout.php, account.php
│   ├── farmer/                dashboard, farms, crops, crop_edit, reports
│   ├── admin/                 dashboard, reports, review, crops, diseases
│   ├── assets/                css and js
│   └── uploads/reports/       uploaded photos (not stored in Git)
├── tests/smoke_test.sh        automated curl-based checks (run against a running site)
├── docs/screenshots/          screenshots of the application (checklist inside)
├── TESTING.md                 test record (PASS / FAIL / NOT RUN)
└── README.md
```

## Database design

Nine tables: `users`, `farms`, `crops`, `farm_crops`, `diseases`, `reports`, `advice`, `report_updates`
and `login_attempts` (failed logins only, used by the login limit; it has no foreign keys because attempts
for unknown emails are stored too).

Relationships: users 1-N farms, farms 1-N farm_crops, crops 1-N farm_crops, crops 1-N diseases,
users 1-N reports, farm_crops 1-N reports, reports 1-N advice, users 1-N advice,
reports 1-N report_updates.

Two views are included: `farmer_report_summary` and `reviewer_pending_reports`.

## Installation (Ubuntu / Linux)

### 1. Install the software

```bash
sudo apt update
sudo apt install apache2 mysql-server php libapache2-mod-php php-mysql php-mbstring git curl
```

Check what you have (PHP 8.0 or newer is expected):

```bash
php -v
php -m | grep -E 'pdo_mysql|mbstring'     # both names must be listed
```

### 2. Get the project

```bash
sudo git clone https://github.com/<YOUR-USERNAME>/AgriCare.git /var/www/agricare
cd /var/www/agricare
```

### 3. Create the database

The database files are split so that a normal setup can never destroy existing records:

| File | What it does | Safe to re-run? |
|---|---|---|
| `database/schema.sql` | Creates the `agricare` database, all tables and the two views. Contains **no** `DROP`. | Yes. Existing tables and rows are kept. |
| `database/seed.sql` | Inserts sample users, farms, crops, diseases and reports for testing. | No: run it **once**. A second run stops with a "Duplicate entry" error and changes nothing (it runs as a single transaction). |
| `database/dev_reset.sql` | **Deletes every table and all data.** For development only. | Destructive. Never run it on real data. |

First-time setup:

```bash
sudo mysql < database/schema.sql      # structure
sudo mysql < database/seed.sql        # optional sample data for testing
```

Start again from a clean sample state (this erases everything in the `agricare` database):

```bash
sudo mysql < database/dev_reset.sql
sudo mysql < database/schema.sql
sudo mysql < database/seed.sql
```

Uploaded photographs in `public/uploads/reports/` are not touched by these scripts.
For a real deployment, run only `schema.sql` and create your own admin account instead of using `seed.sql`.

**Upgrading a database made with an older version:** run `sudo mysql < database/schema.sql` again. It is safe:
it keeps all your data and only adds what is missing (for example the `login_attempts` table used by the
login limit). Without that table the login page shows "Something went wrong".

Create a limited MySQL user for the application (choose your own strong password):

```bash
sudo mysql
```

```sql
CREATE USER 'agricare_user'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
GRANT SELECT, INSERT, UPDATE, DELETE ON agricare.* TO 'agricare_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 4. Create your local configuration

```bash
cp config/config.example.php config/config.php
nano config/config.php          # enter the password you chose above
```

`config/config.php` is listed in `.gitignore`, so your password is never uploaded to GitHub.

### 5. Allow photo uploads

```bash
sudo chown -R www-data:www-data public/uploads/reports
sudo chmod 755 public/uploads/reports
```

### 6. Configure Apache

Create `/etc/apache2/sites-available/agricare.conf`:

```apache
<VirtualHost *:80>
    ServerName agricare.local
    DocumentRoot /var/www/agricare/public

    <Directory /var/www/agricare/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

`AllowOverride All` is required so the `.htaccess` file in the upload folder is applied.

```bash
echo "127.0.0.1 agricare.local" | sudo tee -a /etc/hosts
sudo a2ensite agricare.conf
sudo systemctl reload apache2
```

Open **http://agricare.local** in the browser.

### Quick alternative (no Apache) for a fast local test

```bash
php -S localhost:8000 -t public
```

Then open http://localhost:8000. (The `.htaccess` upload protection only works under Apache, so the upload-folder test `N19` of the smoke test will fail with `php -S`.)

The site must be served from the root of a host name (Apache DocumentRoot = `public/`) because the pages use links such as `/login.php`; it will not work from a sub-folder like `http://localhost/agricare/`.

### Sample accounts

| Role | Email | Password |
|---|---|---|
| Admin / Adviser | admin@agricare.local | Admin@123 |
| Farmer | ali@example.com | Farmer@123 |
| Farmer | bilal@example.com | Farmer@123 |

These are public test accounts for development only. Change or delete them before any real use.
Public registration can only create farmer accounts; admin accounts must be created by an administrator
directly in the database.

## Testing

`TESTING.md` is the test record. Each test is **PASS**, **FAIL** or **NOT RUN**; nothing is marked as passed
unless it was really executed. Run the automated checks against your running site:

```bash
./tests/smoke_test.sh | tee smoke_results.txt              # default: http://agricare.local
BASE_URL=http://localhost:8000 ./tests/smoke_test.sh       # another address
```

Browser-only checks (responsive layout, idle timeout, HTTPS cookie flags) are done by hand and listed in `TESTING.md`.

## Screenshots

Screenshots have not been captured yet. The list of pages, file names and a ready-to-paste Markdown block
are in [docs/screenshots/README.md](docs/screenshots/README.md).

## Security notes and known limitations

- **Uploaded photos are public by link.** Apache serves `/uploads/reports/...` directly. Names are random and
  cannot be guessed, but anyone who has the exact link can open the image without logging in. A protected image
  route (PHP checks the owner before sending the file) is a possible future improvement.
- The Secure-cookie check looks at HTTPS / port 443. If the site is ever placed behind a reverse proxy, adapt `isHttpsRequest()` in `includes/auth.php`.
- The login limit counts failures per email **and** IP address. It slows down password guessing; it is not a replacement for strong passwords.
- A user's role is stored in the session at login; a role change in the database takes effect at the next login.
- Lists are not paginated (fine for the size of a course project).

## Scope of version 1

Not included: automatic image recognition or AI diagnosis, live weather data, outbreak maps,
voice recommendations. These are future enhancements.

## Git and GitHub

The repository already has its history. Connect it to your own empty GitHub repository like this
(replace the URL; nothing is uploaded until you run `git push`):

```bash
git remote add origin https://github.com/<YOUR-USERNAME>/AgriCare.git
git push -u origin main
```

Check before pushing: `git status` is clean and `config/config.php` and uploaded photos do not appear in `git ls-files`.
