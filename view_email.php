<?php
require_once __DIR__ . '/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$db   = get_db();
$stmt = $db->prepare("SELECT * FROM emails WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$email = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$email) { header('Location: index.php'); exit; }

// Mark as read
if (!$email['is_read']) {
    $db->query("UPDATE emails SET is_read=1 WHERE id={$id}");
}

// Prev / Next
$prev = $db->query("SELECT id FROM emails WHERE received_at > " . ($email['received_at'] ? "'{$email['received_at']}'" : "NOW()") . " ORDER BY received_at ASC  LIMIT 1")->fetch_assoc();
$next = $db->query("SELECT id FROM emails WHERE received_at < " . ($email['received_at'] ? "'{$email['received_at']}'" : "NOW()") . " ORDER BY received_at DESC LIMIT 1")->fetch_assoc();

$from_display = $email['from_name'] ? $email['from_name'] . ' <' . $email['from_email'] . '>' : $email['from_email'];
$date_display = $email['received_at'] ? date('l, F j, Y — g:i a', strtotime($email['received_at'])) : '—';
$attachments  = $email['attachments'] ? (json_decode($email['attachments'], true) ?: []) : [];
$show_html    = !empty($email['body_html']);
$body_plain   = nl2br(htmlspecialchars($email['body_plain'] ?? '(No message body)'));

// Avatar
$initial    = strtoupper(mb_substr($email['from_name'] ?: $email['from_email'] ?: '?', 0, 1));
$av_palette = ['#8B5CF6','#06B6D4','#22c55e','#f59e0b','#ef4444','#ec4899','#3b82f6','#14b8a6'];
$av_color   = $av_palette[abs(crc32($email['from_email'])) % 8];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($email['subject'] ?? '(No Subject)') ?> — MailX</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
  /* ── Surfaces ── */
  --bg:           #111a1f;
  --surface:      #1c2730;
  --surface2:     #253240;
  --surface3:     #3B4953;
  --border:       rgba(144,171,139,.12);
  --border-strong:rgba(144,171,139,.25);

  /* ── Primary accent: dark sage green ── */
  --violet:       #5A7863;
  --violet-dim:   rgba(90,120,99,.2);
  --violet-glow:  rgba(90,120,99,.5);

  /* ── Secondary accent: medium sage green ── */
  --cyan:         #90AB8B;
  --cyan-dim:     rgba(144,171,139,.15);
  --cyan-glow:    rgba(144,171,139,.4);

  /* ── Text ── */
  --text:         #EBF4DD;
  --text-2:       #90AB8B;
  --text-3:       #5A7863;

  /* ── Semantic ── */
  --amber:        #c9a84c;
  --red:          #d95f5f;
  --green:        #90AB8B;
  --glass-bg:     rgba(28,39,48,.75);
  --glass-border: rgba(144,171,139,.1);
  --nb-shadow:    3px 3px 0px;
}
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
html { scroll-behavior:smooth; }
body {
  font-family:'Inter',sans-serif;
  background:var(--bg);
  color:var(--text);
  min-height:100vh;
  display:flex;
  flex-direction:column;
  overflow-x:hidden;
}
/* Ambient orbs */
body::before,body::after {
  content:''; position:fixed; border-radius:50%; pointer-events:none; z-index:0; filter:blur(130px); opacity:.15;
}
body::before { width:500px;height:500px; background:var(--violet); top:-150px;left:-150px; }
body::after  { width:400px;height:400px; background:var(--cyan);   bottom:-150px;right:-150px; }

/* ── TOPBAR ── */
.topbar {
  position:sticky; top:0; z-index:200;
  backdrop-filter:blur(24px) saturate(180%);
  -webkit-backdrop-filter:blur(24px) saturate(180%);
  background:rgba(17,26,31,.92);
  border-bottom:1px solid var(--glass-border);
  padding:0 28px;
  height:62px;
  display:flex; align-items:center; gap:12px;
  box-shadow:0 1px 0 var(--border), 0 8px 32px rgba(0,0,0,.5);
}
.logo {
  display:flex; align-items:center; gap:9px; text-decoration:none;
}
.logo-icon {
  width:34px; height:34px;
  background:linear-gradient(135deg,var(--violet),var(--cyan));
  border-radius:9px;
  display:flex; align-items:center; justify-content:center;
  font-size:.95rem;
  box-shadow:0 0 16px var(--violet-glow);
  flex-shrink:0;
}
.logo-text {
  font-family:'Montserrat',sans-serif;
  font-weight:800; font-size:1rem;
  color:var(--text); letter-spacing:-.02em;
}
.logo-text span { color:var(--violet); }
.topbar-subject {
  flex:1;
  font-size:.85rem;
  color:var(--text-2);
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
  padding:0 16px;
}

/* Buttons — Neu-Brutalism */
.btn {
  display:inline-flex; align-items:center; gap:7px;
  padding:8px 16px; border-radius:10px;
  font-size:.82rem; font-weight:700;
  cursor:pointer; text-decoration:none;
  font-family:'Inter',sans-serif; letter-spacing:.01em;
  transition:all .14s cubic-bezier(.4,0,.2,1);
  border:none; white-space:nowrap;
}
.btn-ghost {
  background:transparent;
  color:var(--text-2);
  border:1px solid var(--border-strong);
}
.btn-ghost:hover { background:var(--surface3); color:var(--text); }
.btn-violet {
  background:var(--violet-dim);
  color:var(--text);
  border:2px solid var(--violet);
  box-shadow:var(--nb-shadow) var(--violet);
}
.btn-violet:hover { transform:translate(-2px,-2px); box-shadow:5px 5px 0 var(--violet); }
.btn-violet:active { transform:translate(2px,2px);  box-shadow:1px 1px 0 var(--violet); }
.btn-amber {
  background:rgba(201,168,76,.12);
  color:var(--amber);
  border:2px solid var(--amber);
  box-shadow:var(--nb-shadow) var(--amber);
}
.btn-amber:hover { transform:translate(-2px,-2px); box-shadow:5px 5px 0 var(--amber); }
.btn-amber:active { transform:translate(2px,2px);  box-shadow:1px 1px 0 var(--amber); }
.btn-red {
  background:rgba(217,95,95,.12);
  color:var(--red);
  border:2px solid var(--red);
  box-shadow:var(--nb-shadow) var(--red);
}
.btn-red:hover { transform:translate(-2px,-2px); box-shadow:5px 5px 0 var(--red); }
.btn-red:active { transform:translate(2px,2px);  box-shadow:1px 1px 0 var(--red); }
.btn-cyan {
  background:var(--cyan-dim);
  color:var(--cyan);
  border:2px solid var(--cyan);
  box-shadow:var(--nb-shadow) var(--cyan);
}
.btn-cyan:hover { transform:translate(-2px,-2px); box-shadow:5px 5px 0 var(--cyan); }

/* ── CONTENT ── */
.page-wrap {
  position:relative; z-index:1;
  max-width:860px; margin:0 auto;
  padding:32px 24px 60px;
  width:100%; flex:1;
}

/* ── SENDER HERO CARD — Glassmorphism ── */
.hero-card {
  background:var(--glass-bg);
  backdrop-filter:blur(24px) saturate(180%);
  -webkit-backdrop-filter:blur(24px) saturate(180%);
  border:1px solid var(--glass-border);
  border-radius:22px;
  padding:30px 32px 24px;
  margin-bottom:18px;
  position:relative;
  overflow:hidden;
}
/* Gradient line at top */
.hero-card::before {
  content:'';
  position:absolute; top:0; left:0; right:0; height:2px;
  background:linear-gradient(90deg, var(--violet), var(--cyan), var(--violet));
  background-size:200% 100%;
  animation:shimmer 3s linear infinite;
}
@keyframes shimmer { to { background-position: -200% 0; } }
/* Glow blob */
.hero-card::after {
  content:'';
  position:absolute; top:-60px; right:-60px;
  width:220px; height:220px; border-radius:50%;
  background:radial-gradient(circle, rgba(139,92,246,.18), transparent 70%);
  pointer-events:none;
}
.hero-subject {
  font-family:'Montserrat',sans-serif;
  font-weight:800; font-size:1.6rem; line-height:1.3;
  color:var(--text);
  margin-bottom:22px;
  position:relative; z-index:1;
}
.sender-row {
  display:flex; align-items:center; gap:16px;
  position:relative; z-index:1;
}
.sender-avatar {
  width:46px; height:46px; border-radius:14px; flex-shrink:0;
  display:flex; align-items:center; justify-content:center;
  font-family:'Montserrat',sans-serif; font-weight:800; font-size:1rem;
  box-shadow:0 0 20px rgba(0,0,0,.4);
}
.sender-details { flex:1; min-width:0; display:grid; gap:5px; }
.sender-detail-row {
  display:flex; align-items:center; gap:8px; font-size:.82rem;
}
.detail-label {
  color:var(--text-3); font-weight:600;
  font-size:.7rem; text-transform:uppercase; letter-spacing:.08em;
  flex-shrink:0; width:32px;
}
.detail-val { color:var(--text-2); word-break:break-all; }
.hero-date {
  text-align:right; flex-shrink:0;
  font-size:.8rem; color:var(--text-3); line-height:1.5;
}

/* ── ACTION BAR ── */
.action-bar {
  display:flex; gap:10px; align-items:center;
  margin-bottom:20px; flex-wrap:wrap;
}
.action-bar-right { margin-left:auto; display:flex; gap:8px; }

/* ── ATTACHMENTS ── */
.attachments {
  display:flex; flex-wrap:wrap; gap:8px; margin-bottom:18px;
}
.att-chip {
  display:inline-flex; align-items:center; gap:8px;
  background:var(--surface2);
  border:1px solid var(--border-strong);
  border-radius:10px; padding:8px 14px;
  font-size:.78rem; color:var(--text-2);
  transition:border-color .15s;
}
.att-chip:hover { border-color:var(--violet); color:var(--text); }
.att-chip-icon { font-size:1rem; }

/* ── BODY TOGGLE TABS ── */
.body-tabs {
  display:flex; gap:0; margin-bottom:-1px; position:relative; z-index:1;
}
.tab-btn {
  padding:9px 20px;
  border-radius:12px 12px 0 0;
  font-size:.8rem; font-weight:700;
  border:1px solid var(--border-strong); border-bottom:none;
  background:var(--surface2); color:var(--text-3);
  cursor:pointer; font-family:'Inter',sans-serif;
  transition:all .15s;
  letter-spacing:.02em;
}
.tab-btn.active {
  background:var(--glass-bg);
  color:var(--violet);
  border-color:var(--glass-border);
  backdrop-filter:blur(12px);
}

/* ── EMAIL BODY CARD — Glassmorphism ── */
.body-card {
  background:var(--glass-bg);
  backdrop-filter:blur(20px) saturate(160%);
  -webkit-backdrop-filter:blur(20px) saturate(160%);
  border:1px solid var(--glass-border);
  border-radius:0 16px 16px 16px;
  overflow:hidden;
}
.body-plain-content {
  padding:30px 34px;
  font-size:.9rem;
  line-height:1.82;
  color:#cbd5e1;
  white-space:pre-wrap;
  word-break:break-word;
  font-variant-numeric:tabular-nums;
  font-feature-settings:"kern";
}
#html-iframe {
  width:100%; border:none; min-height:420px;
  background:#fff;
  display:block;
}

/* ── NAV ARROWS ── */
.nav-arrows {
  display:flex; gap:8px; margin-left:auto;
}

/* Scrollbar */
*::-webkit-scrollbar { width:5px; height:5px; }
*::-webkit-scrollbar-track { background:transparent; }
*::-webkit-scrollbar-thumb { background:var(--surface3); border-radius:3px; }
</style>
</head>
<body>

<!-- TOPBAR -->
<header class="topbar">
  <a href="index.php" class="logo">
    <div class="logo-icon">📬</div>
    <span class="logo-text">Mail<span>X</span></span>
  </a>
  <div class="topbar-subject"><?= htmlspecialchars($email['subject'] ?? '(No Subject)') ?></div>
  <div class="nav-arrows">
    <?php if ($prev): ?>
    <a href="view_email.php?id=<?= $prev['id'] ?>" class="btn btn-ghost" title="Newer">↑</a>
    <?php endif; ?>
    <?php if ($next): ?>
    <a href="view_email.php?id=<?= $next['id'] ?>" class="btn btn-ghost" title="Older">↓</a>
    <?php endif; ?>
    <a href="index.php" class="btn btn-ghost">← Inbox</a>
  </div>
</header>

<div class="page-wrap">

  <!-- HERO CARD -->
  <div class="hero-card">
    <div class="hero-subject"><?= htmlspecialchars($email['subject'] ?? '(No Subject)') ?></div>
    <div class="sender-row">
      <div class="sender-avatar" style="background:<?= $av_color ?>;color:#fff"><?= htmlspecialchars($initial) ?></div>
      <div class="sender-details">
        <div class="sender-detail-row">
          <span class="detail-label">From</span>
          <span class="detail-val"><?= htmlspecialchars($from_display) ?></span>
        </div>
        <div class="sender-detail-row">
          <span class="detail-label">To</span>
          <span class="detail-val"><?= htmlspecialchars($email['to_email'] ?? '—') ?></span>
        </div>
      </div>
      <div class="hero-date">
        <?php if ($email['received_at']): ?>
          <?= date('M j, Y', strtotime($email['received_at'])) ?><br>
          <span style="color:var(--text-2)"><?= date('g:i a', strtotime($email['received_at'])) ?></span>
        <?php else: ?>—<?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ACTION BAR -->
  <div class="action-bar">
    <?php if ($email['is_starred']): ?>
    <span class="btn btn-amber" style="cursor:default" title="Starred in your Gmail account">⭐ Starred in Gmail</span>
    <?php endif; ?>

    <?php if ($email['is_read']): ?>
    <form method="POST" action="index.php" style="display:inline">
      <input type="hidden" name="mark_unread" value="<?= $id ?>">
      <button type="submit" class="btn btn-violet">○ Mark Unread</button>
    </form>
    <?php else: ?>
    <form method="POST" action="index.php" style="display:inline">
      <input type="hidden" name="mark_read" value="<?= $id ?>">
      <button type="submit" class="btn btn-violet">✓ Mark Read</button>
    </form>
    <?php endif; ?>

    <div class="action-bar-right">
      <form method="POST" action="index.php" style="display:inline" onsubmit="return confirm('Delete this email?')">
        <input type="hidden" name="delete" value="<?= $id ?>">
        <button type="submit" class="btn btn-red">🗑 Delete</button>
      </form>
    </div>
  </div>

  <!-- ATTACHMENTS -->
  <?php if (!empty($attachments)): ?>
  <div class="attachments">
    <?php foreach ($attachments as $att): ?>
    <div class="att-chip">
      <span class="att-chip-icon">📎</span>
      <?= htmlspecialchars($att['filename'] ?? 'Attachment') ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- BODY TOGGLE TABS -->
  <?php if ($show_html): ?>
  <div class="body-tabs">
    <button class="tab-btn active" id="tab-html"  onclick="switchView('html')">⟨/⟩ HTML</button>
    <button class="tab-btn"        id="tab-plain" onclick="switchView('plain')">≡ Plain Text</button>
  </div>
  <?php endif; ?>

  <!-- BODY CARD -->
  <div class="body-card">
    <?php if ($show_html): ?>
      <iframe id="html-iframe" sandbox="allow-same-origin" title="Email HTML"></iframe>
      <div class="body-plain-content" id="plain-body" style="display:none"><?= $body_plain ?></div>
    <?php else: ?>
      <div class="body-plain-content"><?= $body_plain ?></div>
    <?php endif; ?>
  </div>

</div><!-- /.page-wrap -->

<?php if ($show_html): ?>
<script>
(function() {
  const frame   = document.getElementById('html-iframe');
  const htmlDoc = <?= json_encode($email['body_html']) ?>;
  frame.onload = function() {
    try {
      const h = frame.contentDocument.body.scrollHeight;
      frame.style.height = Math.max(h + 40, 320) + 'px';
    } catch(e) {}
  };
  const blob = new Blob([htmlDoc], {type:'text/html'});
  frame.src  = URL.createObjectURL(blob);
})();

function switchView(mode) {
  const iframe = document.getElementById('html-iframe');
  const plain  = document.getElementById('plain-body');
  const tH     = document.getElementById('tab-html');
  const tP     = document.getElementById('tab-plain');
  if (mode === 'html') {
    iframe.style.display=''; plain.style.display='none';
    tH.classList.add('active'); tP.classList.remove('active');
  } else {
    iframe.style.display='none'; plain.style.display='';
    tP.classList.add('active'); tH.classList.remove('active');
  }
}
</script>
<?php endif; ?>
</body>
</html>
