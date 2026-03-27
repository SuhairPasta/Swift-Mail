<?php
require_once __DIR__ . '/db.php';
$db       = get_db();
$page_num = 3;
$offset   = $page_num - 1;
$result   = $db->query("SELECT * FROM emails ORDER BY received_at DESC LIMIT 1 OFFSET {$offset}");
$email    = $result ? $result->fetch_assoc() : null;
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Page <?= $page_num ?> — MailX</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
  --bg:#111a1f; --surface:#1c2730; --surface2:#253240; --surface3:#3B4953;
  --border:rgba(144,171,139,.12); --border-strong:rgba(144,171,139,.25);
  --violet:#5A7863; --violet-dim:rgba(90,120,99,.2); --violet-glow:rgba(90,120,99,.5);
  --cyan:#90AB8B; --text:#EBF4DD; --text-2:#90AB8B; --text-3:#5A7863;
  --amber:#c9a84c; --red:#d95f5f;
  --glass-bg:rgba(28,39,48,.75); --glass-border:rgba(144,171,139,.1);
  --nb-shadow:3px 3px 0px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;padding:32px 24px;overflow-x:hidden;}
body::before,body::after{content:'';position:fixed;border-radius:50%;pointer-events:none;z-index:0;filter:blur(130px);}
body::before{width:500px;height:500px;background:var(--violet);opacity:.14;top:-180px;left:-180px;}
body::after{width:400px;height:400px;background:var(--cyan);opacity:.12;bottom:-150px;right:-150px;}
.wrap{position:relative;z-index:1;max-width:720px;margin:0 auto;}
.page-header{display:flex;align-items:center;gap:14px;margin-bottom:28px;}
.page-label{font-size:.72rem;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.1em;}
.page-title{font-family:'Montserrat',sans-serif;font-weight:800;font-size:1.1rem;color:var(--text);}
.page-nav{display:flex;gap:8px;margin-bottom:24px;}
.pnav-btn{display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;border-radius:9px;font-size:.8rem;font-weight:800;text-decoration:none;border:1px solid var(--border-strong);color:var(--text-3);background:var(--surface2);transition:all .14s;}
.pnav-btn.active{background:var(--violet);color:var(--text);border-color:rgba(235,244,221,.3);box-shadow:var(--nb-shadow) #EBF4DD;}
.pnav-btn:hover:not(.active){background:var(--surface3);color:var(--text);}
.back-link{display:inline-flex;align-items:center;gap:6px;font-size:.8rem;font-weight:600;color:var(--text-3);text-decoration:none;margin-left:auto;padding:8px 14px;border-radius:8px;border:1px solid var(--border-strong);transition:all .14s;}
.back-link:hover{background:var(--surface3);color:var(--text);}
.email-card{background:var(--glass-bg);backdrop-filter:blur(24px) saturate(160%);-webkit-backdrop-filter:blur(24px) saturate(160%);border:1px solid var(--glass-border);border-radius:20px;overflow:hidden;position:relative;}
.email-card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--violet),var(--cyan));background-size:200% 100%;animation:shimmer 3s linear infinite;}
@keyframes shimmer{to{background-position:-200% 0;}}
.info-table{width:100%;border-collapse:collapse;}
.info-table tr{border-bottom:1px solid rgba(144,171,139,.1);}
.info-table tr:last-child{border-bottom:none;}
.info-table td{padding:16px 24px;vertical-align:top;}
.info-table td:first-child{width:130px;color:var(--text-3);font-weight:700;font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;white-space:nowrap;padding-top:18px;}
.info-table td:last-child{color:var(--text-2);font-size:.88rem;line-height:1.65;word-break:break-word;}
.info-table td.val-primary{color:var(--text);font-weight:600;}
.empty{text-align:center;padding:60px 20px;color:var(--text-3);font-size:.9rem;}
</style>
</head>
<body>
<div class="wrap">
  <div class="page-header">
    <div>
      <div class="page-label">Directory Slot</div>
      <div class="page-title">Page <span style="color:var(--violet)"><?= $page_num ?></span> — 3rd Most Recent Mail</div>
    </div>
  </div>
  <div class="page-nav">
    <?php foreach(range(1,4) as $pn): ?>
    <a href="page<?= $pn ?>.php" class="pnav-btn <?= $pn === $page_num ? 'active' : '' ?>"><?= $pn ?></a>
    <?php endforeach; ?>
    <a href="index.php" class="back-link">← Back to Inbox</a>
  </div>
  <div class="email-card">
    <?php if ($email): ?>
    <table class="info-table">
      <tr><td>Sender</td><td class="val-primary"><?= htmlspecialchars($email['from_name'] ?: '—') ?><?php if ($email['from_email'] && $email['from_email'] !== $email['from_name']): ?><br><span style="font-size:.78rem;color:var(--text-3);font-weight:400"><?= htmlspecialchars($email['from_email']) ?></span><?php endif; ?></td></tr>
      <tr><td>Receiver</td><td><?= htmlspecialchars($email['to_email'] ?: '—') ?></td></tr>
      <tr><td>Subject</td><td class="val-primary"><?= htmlspecialchars($email['subject'] ?? '(No Subject)') ?></td></tr>
      <tr><td>Directory</td><td><span style="background:var(--violet);color:var(--text);font-size:.72rem;font-weight:700;padding:3px 12px;border-radius:6px;border:1px solid rgba(235,244,221,.25)">Page <?= $page_num ?></span></td></tr>
      <tr><td>Date</td><td><?= $email['received_at'] ? date('D, M j Y  g:i a', strtotime($email['received_at'])) : '—' ?></td></tr>
      <tr><td>Folder</td><td><?= ucfirst(htmlspecialchars($email['folder'] ?? 'inbox')) ?></td></tr>
      <tr><td>Body</td><td style="color:var(--text-2);white-space:pre-wrap;max-height:320px;overflow-y:auto"><?= htmlspecialchars(trim(strip_tags($email['body_plain'] ?? '(No body)'))) ?></td></tr>
    </table>
    <?php else: ?>
      <div class="empty">No email in this slot yet. <a href="index.php" style="color:var(--cyan)">← Fetch emails</a></div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
