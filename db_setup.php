<?php
require_once 'config.php';

$errors = []; $messages = [];

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($conn->connect_error) {
    $errors[] = 'MySQL connection failed: ' . $conn->connect_error;
} else {
    if ($conn->query("CREATE DATABASE IF NOT EXISTS `".DB_NAME."` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
        $messages[] = ['icon'=>'✅','text'=>"Database <strong>".DB_NAME."</strong> ready."];
    } else {
        $errors[] = 'Failed to create database: ' . $conn->error;
    }
    $conn->select_db(DB_NAME);
    $sql = "CREATE TABLE IF NOT EXISTS `emails` (
        `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `uid`          VARCHAR(255) NOT NULL,
        `folder`       VARCHAR(20)  NOT NULL DEFAULT 'inbox',
        `message_id`   VARCHAR(512) DEFAULT NULL,
        `from_name`    VARCHAR(255) DEFAULT NULL,
        `from_email`   VARCHAR(255) DEFAULT NULL,
        `to_email`     VARCHAR(512) DEFAULT NULL,
        `subject`      VARCHAR(1000) DEFAULT NULL,
        `body_plain`   LONGTEXT DEFAULT NULL,
        `body_html`    LONGTEXT DEFAULT NULL,
        `attachments`  TEXT DEFAULT NULL,
        `is_read`      TINYINT(1) NOT NULL DEFAULT 0,
        `is_starred`   TINYINT(1) NOT NULL DEFAULT 0,
        `received_at`  DATETIME DEFAULT NULL,
        `fetched_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_uid_folder` (`uid`, `folder`),
        KEY `idx_received` (`received_at`),
        KEY `idx_folder` (`folder`),
        KEY `idx_from_email` (`from_email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    if ($conn->query($sql)) {
        $messages[] = ['icon'=>'✅','text'=>"Table <strong>emails</strong> created / verified."];
    } else {
        $errors[] = 'Failed to create table: ' . $conn->error;
    }

    // Safely add `folder` column to pre-existing tables (ignore if already exists)
    $col_check = $conn->query("SHOW COLUMNS FROM `emails` LIKE 'folder'");
    if ($col_check && $col_check->num_rows === 0) {
        if ($conn->query("ALTER TABLE `emails` ADD COLUMN `folder` VARCHAR(20) NOT NULL DEFAULT 'inbox' AFTER `uid`")) {
            // Also update the unique key to include folder
            @$conn->query("ALTER TABLE `emails` DROP INDEX `uniq_uid`");
            @$conn->query("ALTER TABLE `emails` ADD UNIQUE KEY `uniq_uid_folder` (`uid`, `folder`)");
            @$conn->query("ALTER TABLE `emails` ADD KEY `idx_folder` (`folder`)");
            $messages[] = ['icon'=>'✅','text'=>"Column <strong>folder</strong> added to existing table."];
        } else {
            $errors[] = 'Failed to add folder column: ' . $conn->error;
        }
    } else {
        $messages[] = ['icon'=>'✅','text'=>"Column <strong>folder</strong> already present."];
    }

    $conn->close();
}
$success = empty($errors);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Database Setup — MailX</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
  --bg:#111a1f; --surface:#1c2730; --surface2:#253240; --surface3:#3B4953;
  --border:rgba(144,171,139,.12); --border-strong:rgba(144,171,139,.25);
  --violet:#5A7863; --violet-dim:rgba(90,120,99,.2); --violet-glow:rgba(90,120,99,.5);
  --cyan:#90AB8B; --cyan-dim:rgba(144,171,139,.15); --cyan-glow:rgba(144,171,139,.4);
  --text:#EBF4DD; --text-2:#90AB8B; --text-3:#5A7863;
  --green:#90AB8B; --red:#d95f5f;
  --glass-bg:rgba(28,39,48,.75); --glass-border:rgba(144,171,139,.1);
  --nb-shadow:3px 3px 0px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body {
  font-family:'Inter',sans-serif;
  background:var(--bg); color:var(--text);
  min-height:100vh; display:flex; align-items:center; justify-content:center;
  padding:24px; overflow-x:hidden;
}
body::before,body::after {
  content:''; position:fixed; border-radius:50%; pointer-events:none; z-index:0; filter:blur(130px);
}
body::before { width:500px;height:500px; background:var(--violet); opacity:.15; top:-180px;left:-180px; }
body::after  { width:400px;height:400px; background:var(--cyan);   opacity:.13; bottom:-150px;right:-150px; }

.card {
  position:relative; z-index:1;
  background:var(--glass-bg);
  backdrop-filter:blur(24px) saturate(180%);
  -webkit-backdrop-filter:blur(24px) saturate(180%);
  border:1px solid var(--glass-border);
  border-radius:24px;
  padding:44px 48px;
  width:100%; max-width:520px;
  box-shadow:0 32px 80px rgba(0,0,0,.6);
  overflow:hidden;
}
.card::before {
  content:'';
  position:absolute; top:0; left:0; right:0; height:2px;
  background:linear-gradient(90deg,var(--violet),var(--cyan),var(--violet));
  background-size:200% 100%;
  animation:shimmer 3s linear infinite;
}
@keyframes shimmer { to{background-position:-200% 0;} }
.card::after {
  content:'';
  position:absolute; top:-60px;right:-60px;
  width:200px;height:200px;border-radius:50%;
  background:radial-gradient(circle,rgba(139,92,246,.15),transparent 70%);
  pointer-events:none;
}

/* Logo */
.logo-row {
  display:flex; align-items:center; gap:12px; margin-bottom:32px;
}
.logo-icon {
  width:46px;height:46px;
  background:linear-gradient(135deg,var(--violet),var(--cyan));
  border-radius:13px;
  display:flex; align-items:center; justify-content:center;
  font-size:1.3rem;
  box-shadow:0 0 24px var(--violet-glow);
}
.logo-text {
  font-family:'Montserrat',sans-serif;
  font-weight:900; font-size:1.5rem; letter-spacing:-.03em;
}
.logo-text span { color:var(--violet); }
.logo-sub { font-size:.78rem; color:var(--text-3); margin-top:1px; font-weight:500; }

/* Step items */
.step-list { display:flex; flex-direction:column; gap:10px; margin-bottom:28px; }
.step-item {
  display:flex; align-items:flex-start; gap:13px;
  background:var(--surface2);
  border:1px solid var(--border-strong);
  border-radius:12px; padding:14px 16px;
  transition:border-color .2s;
}
.step-item.ok   { border-color:rgba(34,197,94,.3); background:rgba(34,197,94,.05); }
.step-item.fail { border-color:rgba(239,68,68,.3); background:rgba(239,68,68,.05); }
.step-icon { font-size:1.15rem; margin-top:1px; flex-shrink:0; }
.step-text { font-size:.87rem; color:var(--text-2); line-height:1.5; }
.step-text strong { color:var(--text); font-weight:600; }

/* Success state */
.success-glow {
  text-align:center; padding:8px 0 24px;
}
.success-ring {
  width:70px; height:70px; border-radius:50%; margin:0 auto 16px;
  background:rgba(34,197,94,.12);
  border:2px solid rgba(34,197,94,.4);
  display:flex; align-items:center; justify-content:center;
  font-size:2rem;
  box-shadow:0 0 40px rgba(34,197,94,.25);
  animation:pulse 2s ease-in-out infinite;
}
@keyframes pulse { 0%,100%{box-shadow:0 0 20px rgba(34,197,94,.2)} 50%{box-shadow:0 0 42px rgba(34,197,94,.45)} }
.success-title {
  font-family:'Montserrat',sans-serif;
  font-weight:800; font-size:1.2rem; color:var(--text);
  margin-bottom:6px;
}
.success-sub { font-size:.84rem; color:var(--text-3); line-height:1.5; }

/* Buttons */
.btn-group { display:flex; flex-direction:column; gap:10px; margin-top:8px; }
.btn {
  display:inline-flex; align-items:center; justify-content:center; gap:8px;
  padding:13px 20px; border-radius:12px;
  font-size:.88rem; font-weight:700;
  cursor:pointer; text-decoration:none;
  font-family:'Inter',sans-serif; border:none;
  transition:all .14s cubic-bezier(.4,0,.2,1);
}
.btn-primary {
  background:var(--violet); color:var(--text);
  border:2px solid #EBF4DD;
  box-shadow:var(--nb-shadow) #EBF4DD;
}
.btn-primary:hover { transform:translate(-2px,-2px); box-shadow:5px 5px 0 #EBF4DD; }
.btn-primary:active { transform:translate(2px,2px); box-shadow:1px 1px 0 #EBF4DD; }
.btn-ghost {
  background:transparent; color:var(--text-2);
  border:1px solid var(--border-strong);
}
.btn-ghost:hover { background:var(--surface3); color:var(--text); }
</style>
</head>
<body>
<div class="card">
  <!-- Logo -->
  <div class="logo-row">
    <div class="logo-icon">📬</div>
    <div>
      <div class="logo-text">Mail<span>X</span></div>
      <div class="logo-sub">Database Setup</div>
    </div>
  </div>

  <?php if ($success): ?>
  <div class="success-glow">
    <div class="success-ring">✓</div>
    <div class="success-title">Setup Complete!</div>
    <div class="success-sub">Database and tables are ready.<br>You can now fetch and browse emails.</div>
  </div>
  <?php endif; ?>

  <div class="step-list">
    <?php foreach ($messages as $m): ?>
    <div class="step-item ok">
      <span class="step-icon"><?= $m['icon'] ?></span>
      <span class="step-text"><?= $m['text'] ?></span>
    </div>
    <?php endforeach; ?>
    <?php foreach ($errors as $e): ?>
    <div class="step-item fail">
      <span class="step-icon">❌</span>
      <span class="step-text"><?= htmlspecialchars($e) ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="btn-group">
    <?php if ($success): ?>
    <a href="fetch_emails.php" class="btn btn-primary">↻ Fetch Emails Now</a>
    <a href="index.php"        class="btn btn-ghost">→ Go to Inbox</a>
    <?php else: ?>
    <a href="db_setup.php" class="btn btn-primary">↻ Retry Setup</a>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
