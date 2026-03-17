# Band Manager

A self-hosted PHP web application for managing one or more music bands.
Handles a shared calendar (gigs, rehearsals, personal blocks), band profiles,
and file uploads (EPK / dossier PDFs).

---

## Requirements

| Requirement | Details |
|-------------|---------|
| **PHP**     | 8.1 or higher |
| **Apache**  | With `mod_rewrite` enabled |
| **Composer**| Available in PATH |
| **SMTP**    | Any SMTP relay, or PHP's `mail()` as fallback |
| **Storage** | Writable `data/` and `uploads/` directories |

No database required — data is stored as JSON flat files with `flock()` writes.

---

## Installation on DreamHost VPS

### 1. Clone the repository

Clone into a directory **above** your domain's web root, **not** inside `public_html`:

```bash
cd /home/username
git clone https://github.com/yourname/band-manager.git band-manager
```

### 2. Set the DocumentRoot

In the DreamHost panel, set your domain's **Web Directory** (DocumentRoot) to:

```
/home/username/band-manager/public
```

This ensures `data/` and `uploads/` are never web-accessible.

### 3. Install dependencies

```bash
cd /home/username/band-manager
composer install --no-dev
```

### 4. Configure the application

```bash
cp config.example.php config.local.php
```

Edit `config.local.php` and fill in your SMTP credentials and app name.

### 5. Set directory permissions

```bash
chmod 750 data/ uploads/
```

### 6. Create the first admin user

```bash
php bin/create-admin.php "Your Name" "email@example.com"
```

You can run this for each admin. Running it for an existing email simply upgrades
that user to admin.

### 7. Enable HTTPS

HTTPS is managed through the DreamHost panel — enable it there. The app
auto-detects HTTP vs HTTPS via `$_SERVER['HTTPS']` when building magic links.

---

## SMTP Setup

Leave `SMTP_HOST` blank in `config.local.php` to use PHP's built-in `mail()`.
For reliable delivery, use a dedicated SMTP service:

### Option A — Brevo (recommended, free tier)

- Free tier: 300 emails/day
- Sign up at [brevo.com](https://www.brevo.com)
- Go to **SMTP & API** → **SMTP** to get credentials

```php
define('SMTP_HOST', 'smtp-relay.brevo.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-brevo-login@email.com');
define('SMTP_PASS', 'your-brevo-smtp-key');
```

### Option B — Gmail App Password

- Enable 2FA on your Google account
- Create an **App Password** at [myaccount.google.com/apppasswords](https://myaccount.google.com/apppasswords)

```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'you@gmail.com');
define('SMTP_PASS', 'xxxx xxxx xxxx xxxx');  // 16-char app password
```

---

## First Steps

1. Log in via the magic link sent to your admin email
2. Go to **Admin** → create your bands and add members by email
3. Members receive a magic link email on next login attempt
4. Use the **Calendar** to add gigs, practices, and personal blocks
5. Visit each **Band** page to upload an EPK or dossier PDF

---

## Backup

The entire application state lives in `data/*.json` and `uploads/`.
Back them up regularly:

```bash
# Weekly cron — add to crontab with: crontab -e
0 3 * * 0 rsync -av /home/username/band-manager/data/ /home/username/backups/band-manager-data/
0 3 * * 0 rsync -av /home/username/band-manager/uploads/ /home/username/backups/band-manager-uploads/
```

---

## Project Structure

```
band-manager/
├── bin/
│   └── create-admin.php       # CLI: create the first admin user
├── data/                      # JSON database (NOT web-accessible)
│   ├── users.json
│   ├── bands.json
│   ├── events.json
│   └── sessions.json
├── public/                    # Apache DocumentRoot
│   ├── .htaccess              # URL rewriting + security
│   ├── assets/
│   │   └── app.css            # All styles
│   └── index.php              # Front controller — all routes
├── src/
│   ├── Auth.php               # Magic links, sessions
│   └── JsonStore.php          # Atomic JSON read/write
├── templates/
│   ├── layout.php             # Shared HTML shell
│   ├── nav.php                # Sidebar nav partial
│   ├── login.php              # Standalone login page
│   ├── calendar.php           # FullCalendar view
│   ├── band.php               # Band profile + uploads
│   └── admin.php              # Admin panel
├── uploads/                   # Auth-gated file storage
├── .gitignore
├── composer.json
├── config.example.php         # Copy to config.local.php
└── README.md
```

---

## Tech Stack

| Layer    | Technology |
|----------|-----------|
| Backend  | PHP 8.1+, no framework |
| Routing  | Single front controller (`public/index.php`) |
| Storage  | JSON flat files with `flock()` |
| Frontend | Alpine.js 3 + FullCalendar 6 (CDN) |
| Email    | PHPMailer 6 via Composer |
| CSS      | Hand-written, single `app.css` |
| Auth     | Passwordless magic links |
