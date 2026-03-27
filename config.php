<?php
// ============================================================
//  IMAP Email System — Configuration
// ============================================================

// --- Database Settings ---
define('DB_HOST',   'localhost');
define('DB_USER',   'root');        // Default XAMPP MySQL user
define('DB_PASS',   '');            // Default XAMPP MySQL password (blank)
define('DB_NAME',   'email_system');

// --- Gmail IMAP Settings ---
// IMPORTANT: Use a Gmail "App Password", NOT your real password.
// Enable 2-Step Verification on your Google account first, then
// generate an App Password at: https://myaccount.google.com/apppasswords
define('IMAP_HOST',      '{imap.gmail.com:993/imap/ssl}INBOX');
define('IMAP_HOST_SENT', '{imap.gmail.com:993/imap/ssl}[Gmail]/Sent Mail');
define('IMAP_USER',      '1suhairrizwan@gmail.com');
define('IMAP_PASS',      'gzypepbavjanicve'); // <-- Replace this

// --- Fetch Settings ---
define('FETCH_LIMIT',    50);   // Max emails to fetch per run (inbox)
define('FETCH_SENT_LIMIT', 50); // Max sent emails to fetch per run
define('MARK_AS_READ',   false); // Whether to mark fetched emails as read on Gmail
