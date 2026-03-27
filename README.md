# 📬 IMAP Email System
**Fetch emails from `1suhairrizwan@gmail.com` via IMAP → store in MySQL → browse in a web UI.**

---

## ⚡ Quick Setup (5 steps)

### Step 1 — Enable PHP IMAP extension

Open `C:\xampp\php\php.ini`, find and **uncomment** this line (remove the `;`):
```
;extension=imap   →   extension=imap
```
Then **restart Apache** in the XAMPP Control Panel.

---

### Step 2 — Get a Gmail App Password

> ⚠️ Gmail blocks direct password login for 3rd-party apps. You **must** use an App Password.

1. Go to your Google Account → **Security**
2. Enable **2-Step Verification** (if not already on)
3. Visit: https://myaccount.google.com/apppasswords
4. Choose App = **Mail**, Device = **Windows Computer**
5. Copy the generated 16-character password

---

### Step 3 — Set your App Password in `config.php`

Open `c:\xampp\htdocs\suhair\config.php` and replace:
```php
define('IMAP_PASS', 'YOUR_GMAIL_APP_PASSWORD_HERE');
```
with your actual App Password (no spaces):
```php
define('IMAP_PASS', 'abcdabcdabcdabcd');
```

---

### Step 4 — Run the database setup

Make sure XAMPP **MySQL** is running, then visit:
```
http://localhost/suhair/db_setup.php
```
This creates the `email_system` database and the `emails` table automatically.

---

### Step 5 — Fetch your emails!

```
http://localhost/suhair/fetch_emails.php
```
Then browse your inbox at:
```
http://localhost/suhair/index.php
```

---

## 🗂 File Structure

| File | Purpose |
|------|---------|
| `config.php` | IMAP + DB credentials |
| `db.php` | MySQL connection helper |
| `db_setup.php` | One-time DB/table creator |
| `fetch_emails.php` | IMAP fetcher (stores to MySQL) |
| `index.php` | Inbox dashboard |
| `view_email.php` | Email reader |

---

## ✨ Features

- **Fetches** emails from Gmail **Inbox** AND **Sent Mail**
- **Stores** To, From, Subject, HTML body, Plain body, Attachments list, Date, and Folder in MySQL
- **Deduplication** — same email is never stored twice (uses IMAP UID + Folder)
- **Inbox UI** — Bento Grid stats, search, filters (All / Inbox / Sent / Unread / Starred), pagination
- **Dark-mode** premium 2026 UI with Glassmorphism and Neu-Brutalism
- **AJAX fetch** — click "Fetch" to sync everything without refresh
- **Email reader** — HTML rendered in sandboxed iframe, or plain text view
- **Actions** — Star, Mark read/unread, Delete, Delete all

---

## 🔄 Auto-fetch (optional cron job)

To fetch emails automatically every 15 minutes, you can set up a Windows Task Scheduler job:

**Action**: `C:\xampp\php\php.exe C:\xampp\htdocs\suhair\fetch_emails.php`  
**Trigger**: Every 15 minutes
