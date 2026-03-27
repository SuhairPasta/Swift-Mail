<?php
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = get_db();
    if (isset($_POST['mark_read'])   && is_numeric($_POST['mark_read']))   $db->query("UPDATE emails SET is_read=1          WHERE id=".(int)$_POST['mark_read']);
    if (isset($_POST['mark_unread']) && is_numeric($_POST['mark_unread'])) $db->query("UPDATE emails SET is_read=0          WHERE id=".(int)$_POST['mark_unread']);
    if (isset($_POST['delete'])      && is_numeric($_POST['delete']))      $db->query("DELETE FROM emails                   WHERE id=".(int)$_POST['delete']);
    if (isset($_POST['delete_all']))                                        $db->query("DELETE FROM emails");
    header('Location: index.php' . (isset($_GET['q']) ? '?q='.urlencode($_GET['q']) : ''));
    exit;
}

$db          = get_db();
$search      = trim($_GET['q']      ?? '');
$filter      = $_GET['filter']      ?? 'all';
$folder      = $_GET['folder']      ?? 'all';  // 'all', 'inbox', 'sent'
$page        = max(1,(int)($_GET['page'] ?? 1));
$per_page    = 20;
$offset      = ($page-1)*$per_page;

$where_parts = []; $params = []; $types = '';
if ($search !== '') {
    $where_parts[] = "(subject LIKE ? OR from_name LIKE ? OR from_email LIKE ? OR to_email LIKE ? OR body_plain LIKE ?)";
    $like = '%'.$search.'%';
    $params = [$like,$like,$like,$like,$like]; $types .= 'sssss';
}
if ($filter==='unread')  $where_parts[] = "is_read=0";
if ($filter==='starred') $where_parts[] = "is_starred=1";
if ($folder==='inbox')   $where_parts[] = "folder='inbox'";
elseif ($folder==='sent') $where_parts[] = "folder='sent'";
$where = $where_parts ? 'WHERE '.implode(' AND ',$where_parts) : '';

$count_stmt = $db->prepare("SELECT COUNT(*) FROM emails $where");
if ($params) $count_stmt->bind_param($types,...$params);
$count_stmt->execute(); $count_stmt->bind_result($total_emails); $count_stmt->fetch(); $count_stmt->close();
$total_pages = max(1,ceil($total_emails/$per_page));

$list_stmt = $db->prepare("SELECT id,from_name,from_email,subject,body_plain,is_read,is_starred,received_at FROM emails $where ORDER BY received_at DESC LIMIT ? OFFSET ?");
$list_stmt->bind_param($types.'ii',...array_merge($params,[$per_page,$offset]));
$list_stmt->execute();
$emails = $list_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$list_stmt->close();

$unread_count  = (int)$db->query("SELECT COUNT(*) FROM emails WHERE is_read=0 AND folder='inbox'")->fetch_row()[0];
$starred_count = (int)$db->query("SELECT COUNT(*) FROM emails WHERE is_starred=1")->fetch_row()[0];
$total_count   = (int)$db->query("SELECT COUNT(*) FROM emails")->fetch_row()[0];
$inbox_count   = (int)$db->query("SELECT COUNT(*) FROM emails WHERE folder='inbox'")->fetch_row()[0];
$sent_count    = (int)$db->query("SELECT COUNT(*) FROM emails WHERE folder='sent'")->fetch_row()[0];
$today_count   = (int)$db->query("SELECT COUNT(*) FROM emails WHERE DATE(received_at)=CURDATE()")->fetch_row()[0];


?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Inbox — 1suhairrizwan@gmail.com</title>
<meta name="description" content="IMAP Email Client — Premium inbox for 1suhairrizwan@gmail.com">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════
   DESIGN TOKENS — 2026 Palette
═══════════════════════════════════════════ */
:root {
  --bg:           #0B0B0C;
  --surface:      #161618;
  --surface2:     #1d1d20;
  --surface3:     #252528;
  --border:       rgba(255,255,255,.07);
  --border-strong:rgba(255,255,255,.14);
  --violet:       #8B5CF6;
  --violet-dim:   rgba(139,92,246,.15);
  --violet-glow:  rgba(139,92,246,.35);
  --cyan:         #06B6D4;
  --cyan-dim:     rgba(6,182,212,.12);
  --cyan-glow:    rgba(6,182,212,.3);
  --text:         #F8FAFC;
  --text-2:       #94a3b8;
  --text-3:       #475569;
  --amber:        #f59e0b;
  --red:          #ef4444;
  --green:        #22c55e;
  --glass-bg:     rgba(22,22,24,.7);
  --glass-border: rgba(255,255,255,.08);

  /* Neu-Brutalism shadow offset */
  --nb-shadow:    3px 3px 0px;
}
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }

/* ═══════════════════════════════════════════
   BASE
═══════════════════════════════════════════ */
html { scroll-behavior: smooth; }
body {
  font-family: 'Inter', sans-serif;
  background: var(--bg);
  color: var(--text);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  overflow-x: hidden;
}

/* ambient orbs */
body::before, body::after {
  content: '';
  position: fixed;
  border-radius: 50%;
  pointer-events: none;
  z-index: 0;
  filter: blur(120px);
  opacity: .18;
}
body::before {
  width: 600px; height: 600px;
  background: var(--violet);
  top: -200px; left: -200px;
}
body::after {
  width: 500px; height: 500px;
  background: var(--cyan);
  bottom: -180px; right: -180px;
}

/* ═══════════════════════════════════════════
   TOPBAR — Glassmorphism
═══════════════════════════════════════════ */
.topbar {
  position: sticky;
  top: 0;
  z-index: 200;
  backdrop-filter: blur(24px) saturate(180%);
  -webkit-backdrop-filter: blur(24px) saturate(180%);
  background: rgba(11,11,12,.82);
  border-bottom: 1px solid var(--glass-border);
  padding: 0 28px;
  height: 64px;
  display: flex;
  align-items: center;
  gap: 16px;
  box-shadow: 0 1px 0 var(--border), 0 8px 32px rgba(0,0,0,.5);
}
.logo {
  display: flex;
  align-items: center;
  gap: 10px;
  text-decoration: none;
}
.logo-icon {
  width: 36px; height: 36px;
  background: transparent;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.logo-title {
  font-family: 'Montserrat', sans-serif;
  font-weight: 800;
  font-size: 1.05rem;
  color: var(--text);
  letter-spacing: -.02em;
}
.logo-title span { color: var(--violet); }

.topbar-pill {
  background: var(--violet-dim);
  color: var(--violet);
  border: 1px solid rgba(139,92,246,.3);
  font-size: .7rem;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
  padding: 3px 10px;
  border-radius: 999px;
}

/* Search */
.search-wrap {
  flex: 1;
  max-width: 440px;
  position: relative;
}
.search-wrap svg {
  position: absolute;
  left: 13px; top: 50%;
  transform: translateY(-50%);
  color: var(--text-3);
  pointer-events: none;
}
.search-input {
  width: 100%;
  background: var(--surface2);
  border: 1px solid var(--border-strong);
  border-radius: 12px;
  padding: 10px 16px 10px 42px;
  color: var(--text);
  font-size: .88rem;
  font-family: 'Inter', sans-serif;
  outline: none;
  transition: border-color .2s, box-shadow .2s;
}
.search-input:focus {
  border-color: var(--violet);
  box-shadow: 0 0 0 3px var(--violet-dim), 0 0 20px var(--violet-glow);
}
.search-input::placeholder { color: var(--text-3); }

.topbar-right { margin-left: auto; display:flex; gap:10px; align-items:center; }

/* ═══════════════════════════════════════════
   NEU-BRUTALISM BUTTONS
═══════════════════════════════════════════ */
.btn {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 9px 18px;
  border-radius: 10px;
  font-size: .83rem;
  font-weight: 700;
  cursor: pointer;
  text-decoration: none;
  font-family: 'Inter', sans-serif;
  letter-spacing: .01em;
  transition: all .14s cubic-bezier(.4,0,.2,1);
  border: none;
  position: relative;
}
/* Primary — Neu-Brutalism */
.btn-primary {
  background: var(--violet);
  color: #fff;
  border: 2px solid #fff;
  box-shadow: var(--nb-shadow) #fff;
}
.btn-primary:hover {
  transform: translate(-2px,-2px);
  box-shadow: 5px 5px 0px #fff;
}
.btn-primary:active {
  transform: translate(2px,2px);
  box-shadow: 1px 1px 0px #fff;
}
/* Ghost */
.btn-ghost {
  background: transparent;
  color: var(--text-2);
  border: 1px solid var(--border-strong);
}
.btn-ghost:hover { background: var(--surface3); color: var(--text); border-color: var(--border-strong); }
/* Danger */
.btn-danger {
  background: rgba(239,68,68,.1);
  color: var(--red);
  border: 2px solid var(--red);
  box-shadow: var(--nb-shadow) var(--red);
}
.btn-danger:hover { transform:translate(-2px,-2px); box-shadow:5px 5px 0 var(--red); }
.btn-danger:active { transform:translate(2px,2px); box-shadow:1px 1px 0 var(--red); }

/* Glass Buttons */
.btn-glass {
  background: var(--glass-bg);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  color: var(--text);
  border: 1px solid var(--glass-border);
  box-shadow: 0 4px 12px rgba(0,0,0,.3);
}
.btn-glass:hover {
  background: rgba(255,255,255,.05);
  border-color: rgba(255,255,255,.15);
  box-shadow: 0 8px 24px rgba(0,0,0,.5);
}
.btn-glass-danger {
  background: rgba(239,68,68,.1);
  backdrop-filter: blur(16px);
  -webkit-backdrop-filter: blur(16px);
  color: var(--red);
  border: 1px solid rgba(239,68,68,.3);
  box-shadow: 0 4px 12px rgba(0,0,0,.3);
}
.btn-glass-danger:hover {
  background: rgba(239,68,68,.2);
  border-color: rgba(239,68,68,.5);
  box-shadow: 0 8px 24px rgba(0,0,0,.5);
}
/* Cyan ghost */
.btn-cyan {
  background: var(--cyan-dim);
  color: var(--cyan);
  border: 2px solid var(--cyan);
  box-shadow: var(--nb-shadow) var(--cyan);
}
.btn-cyan:hover { transform:translate(-2px,-2px); box-shadow:5px 5px 0 var(--cyan); }
.btn-cyan:active { transform:translate(2px,2px); box-shadow:1px 1px 0 var(--cyan); }

/* ═══════════════════════════════════════════
   LAYOUT
═══════════════════════════════════════════ */
.layout {
  display: flex;
  flex: 1;
  position: relative;
  z-index: 1;
}

/* ═══════════════════════════════════════════
   SIDEBAR
═══════════════════════════════════════════ */
.sidebar {
  width: 230px;
  flex-shrink: 0;
  padding: 24px 14px;
  display: flex;
  flex-direction: column;
  gap: 2px;
  border-right: 1px solid var(--border);
  background: rgba(22,22,24,.5);
  backdrop-filter: blur(12px);
}
.sidebar-label {
  font-size: .68rem;
  font-weight: 700;
  color: var(--text-3);
  text-transform: uppercase;
  letter-spacing: .1em;
  padding: 16px 10px 8px;
}
.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 10px;
  text-decoration: none;
  color: var(--text-2);
  font-size: .85rem;
  font-weight: 500;
  transition: all .15s;
  position: relative;
}
.nav-item:hover {
  background: var(--surface3);
  color: var(--text);
}
.nav-item.active {
  background: var(--violet-dim);
  color: var(--violet);
  font-weight: 600;
  border: 1px solid rgba(139,92,246,.25);
}
.nav-item.active::before {
  content: '';
  position: absolute;
  left: -14px; top: 50%;
  transform: translateY(-50%);
  width: 3px; height: 60%;
  background: var(--violet);
  border-radius: 0 3px 3px 0;
  box-shadow: 0 0 8px var(--violet-glow);
}
.nav-cnt {
  margin-left: auto;
  background: var(--violet);
  color: #fff;
  font-size: .66rem;
  font-weight: 800;
  padding: 2px 8px;
  border-radius: 999px;
  letter-spacing: .02em;
  min-width: 22px;
  text-align: center;
}
.nav-cnt.cyan { background: var(--cyan); }

/* ═══════════════════════════════════════════
   MAIN
═══════════════════════════════════════════ */
.main {
  flex: 1;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
}

/* ═══════════════════════════════════════════
   BENTO STATS GRID  
═══════════════════════════════════════════ */
.bento-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
  padding: 24px 24px 0;
}
.bento-card {
  background: var(--glass-bg);
  backdrop-filter: blur(20px) saturate(160%);
  -webkit-backdrop-filter: blur(20px) saturate(160%);
  border: 1px solid var(--glass-border);
  border-radius: 16px;
  padding: 20px 22px;
  position: relative;
  overflow: hidden;
  transition: transform .2s, box-shadow .2s;
}
.bento-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 12px 40px rgba(0,0,0,.4);
}
.bento-card::after {
  content: '';
  position: absolute;
  inset: 0;
  border-radius: inherit;
  background: linear-gradient(135deg, rgba(255,255,255,.04), transparent);
  pointer-events: none;
}
.bento-card.violet { border-color: rgba(139,92,246,.3); }
.bento-card.violet::before {
  content: ''; position:absolute; top:-30px; right:-30px;
  width:120px;height:120px; border-radius:50%;
  background:var(--violet); opacity:.1; filter:blur(30px);
}
.bento-card.cyan { border-color: rgba(6,182,212,.25); }
.bento-card.cyan::before {
  content:''; position:absolute; top:-30px; right:-30px;
  width:120px;height:120px; border-radius:50%;
  background:var(--cyan); opacity:.1; filter:blur(30px);
}
.bento-card.amber { border-color: rgba(245,158,11,.2); }
.bento-card.amber::before {
  content:''; position:absolute; top:-30px; right:-30px;
  width:120px;height:120px; border-radius:50%;
  background:var(--amber); opacity:.08; filter:blur(30px);
}
.bento-card.green { border-color: rgba(34,197,94,.2); }
.bento-card.green::before {
  content:''; position:absolute; top:-30px; right:-30px;
  width:120px;height:120px; border-radius:50%;
  background:var(--green); opacity:.08; filter:blur(30px);
}
.stat-icon { font-size:1.5rem; margin-bottom:10px; }
.stat-val {
  font-family: 'Montserrat', sans-serif;
  font-weight: 800;
  font-size: 2rem;
  line-height: 1;
  margin-bottom: 4px;
}
.bento-card.violet .stat-val { color: var(--violet); }
.bento-card.cyan   .stat-val { color: var(--cyan); }
.bento-card.amber  .stat-val { color: var(--amber); }
.bento-card.green  .stat-val { color: var(--green); }
.stat-label {
  font-size: .72rem;
  color: var(--text-3);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: .08em;
}

/* ═══════════════════════════════════════════
   LIST SECTION
═══════════════════════════════════════════ */
.list-section {
  margin: 20px 24px;
  background: var(--glass-bg);
  backdrop-filter: blur(20px) saturate(160%);
  -webkit-backdrop-filter: blur(20px) saturate(160%);
  border: 1px solid var(--glass-border);
  border-radius: 20px;
  overflow: hidden;
  flex: 1;
}
.list-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 22px;
  border-bottom: 1px solid var(--border);
}
.list-header-title {
  font-family: 'Montserrat', sans-serif;
  font-weight: 700;
  font-size: .95rem;
  color: var(--text);
  flex: 1;
}
.list-count {
  font-size: .75rem;
  color: var(--text-3);
  background: var(--surface3);
  padding: 3px 10px;
  border-radius: 999px;
  border: 1px solid var(--border);
}

/* ═══════════════════════════════════════════
   EMAIL ROWS
═══════════════════════════════════════════ */
.email-row {
  display: flex;
  align-items: center;
  border-bottom: 1px solid var(--border);
  transition: background .12s;
  position: relative;
  cursor: pointer;
}
.email-row:last-child { border-bottom: none; }
.email-row:hover { background: rgba(255,255,255,.025); }
.email-row.unread { background: rgba(139,92,246,.05); }
.email-row.unread::before {
  content: '';
  position: absolute;
  left: 0; top: 0; bottom: 0;
  width: 3px;
  background: linear-gradient(to bottom, var(--violet), var(--cyan));
  border-radius: 0 2px 2px 0;
  box-shadow: 0 0 12px var(--violet-glow);
}


.email-link {
  display: flex;
  align-items: center;
  text-decoration: none;
  color: inherit;
  flex: 1;
  padding: 14px 18px 14px 4px;
  gap: 14px;
  min-width: 0;
}
.email-avatar {
  width: 38px; height: 38px;
  border-radius: 12px;
  flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  font-family: 'Montserrat', sans-serif;
  font-weight: 800;
  font-size: .9rem;
  letter-spacing: -.01em;
}
.email-body { flex: 1; min-width: 0; }
.email-from {
  font-size: .86rem;
  font-weight: 600;
  color: var(--text);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-bottom: 3px;
}
.email-subject {
  font-size: .82rem;
  color: var(--text-2);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  margin-bottom: 2px;
}
.email-row.unread .email-subject { color: var(--text); font-weight: 700; }
.email-preview {
  font-size: .74rem;
  color: var(--text-3);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.email-meta {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 8px;
  margin-left: 12px;
}
.email-date {
  font-size: .72rem;
  color: var(--text-3);
  white-space: nowrap;
}
.email-actions {
  display: flex;
  gap: 4px;
  opacity: 0;
  transition: opacity .15s;
}
.email-row:hover .email-actions { opacity: 1; }
.row-action {
  background: var(--surface3);
  border: 1px solid var(--border-strong);
  border-radius: 6px;
  color: var(--text-2);
  cursor: pointer;
  padding: 4px 8px;
  font-size: .72rem;
  font-family: inherit;
  font-weight: 600;
  transition: all .12s;
}
.row-action:hover { background: var(--surface2); color: var(--text); }
.row-action.del { color: var(--red); border-color: rgba(239,68,68,.3); }
.row-action.del:hover { background: rgba(239,68,68,.1); }

/* Unread dot */
.unread-dot {
  width: 7px; height: 7px;
  background: var(--violet);
  border-radius: 50%;
  box-shadow: 0 0 8px var(--violet-glow);
  flex-shrink: 0;
}

/* Avatar palette */
.av0{background:linear-gradient(135deg,#8B5CF6,#a78bfa);color:#fff}
.av1{background:linear-gradient(135deg,#06B6D4,#22d3ee);color:#fff}
.av2{background:linear-gradient(135deg,#22c55e,#4ade80);color:#fff}
.av3{background:linear-gradient(135deg,#f59e0b,#fbbf24);color:#fff}
.av4{background:linear-gradient(135deg,#ef4444,#f87171);color:#fff}
.av5{background:linear-gradient(135deg,#ec4899,#f472b6);color:#fff}
.av6{background:linear-gradient(135deg,#3b82f6,#60a5fa);color:#fff}
.av7{background:linear-gradient(135deg,#14b8a6,#2dd4bf);color:#fff}

/* ═══════════════════════════════════════════
   EMPTY STATE
═══════════════════════════════════════════ */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 20px;
  gap: 18px;
  text-align: center;
}
.empty-icon {
  font-size: 4rem;
  filter: opacity(.3);
  animation: float 3s ease-in-out infinite;
}
@keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }
.empty-state h3 {
  font-family: 'Montserrat', sans-serif;
  font-weight: 800;
  font-size: 1.25rem;
  color: var(--text);
}
.empty-state p { font-size: .87rem; color: var(--text-2); max-width: 300px; line-height: 1.6; }

/* ═══════════════════════════════════════════
   PAGINATION
═══════════════════════════════════════════ */
.pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 20px;
  border-top: 1px solid var(--border);
}
.page-btn {
  padding: 7px 14px;
  border-radius: 8px;
  background: var(--surface2);
  color: var(--text-2);
  text-decoration: none;
  font-size: .8rem;
  font-weight: 600;
  border: 1px solid var(--border-strong);
  transition: all .13s;
  font-family: 'Inter', sans-serif;
}
.page-btn:hover { background: var(--surface3); color: var(--text); }
.page-btn.current {
  background: var(--violet);
  color: #fff;
  border: 2px solid #fff;
  box-shadow: var(--nb-shadow) #fff;
}

/* ═══════════════════════════════════════════
   TOAST
═══════════════════════════════════════════ */
.toast {
  position: fixed;
  bottom: 28px; right: 28px;
  background: var(--surface);
  border: 1px solid var(--glass-border);
  border-left: 3px solid var(--cyan);
  padding: 14px 22px;
  border-radius: 12px;
  font-size: .86rem;
  color: var(--text);
  box-shadow: 0 12px 40px rgba(0,0,0,.6), 0 0 0 1px rgba(6,182,212,.2);
  animation: toastIn .3s cubic-bezier(.34,1.56,.64,1);
  z-index: 999;
  backdrop-filter: blur(16px);
  max-width: 340px;
}
@keyframes toastIn {
  from { opacity:0; transform:translateY(20px) scale(.96); }
  to   { opacity:1; transform:translateY(0) scale(1); }
}

/* Scrollbar */
.main::-webkit-scrollbar { width: 5px; }
.main::-webkit-scrollbar-track { background: transparent; }
.main::-webkit-scrollbar-thumb { background: var(--surface3); border-radius: 3px; }

/* Fetch spinner */
@keyframes spin { to { transform: rotate(360deg); } }
.spinning { animation: spin .8s linear infinite; display:inline-block; }
</style>
</head>
<body>

<!-- ── TOP BAR ── -->
<header class="topbar">
  <a href="index.php" class="logo">
    <div class="logo-icon swift-logo">
      <svg viewBox="0 0 200 240" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;">
        <path d="M100 0 L190 20 C190 100 170 190 100 240 C30 190 10 100 10 20 Z" fill="#06B6D4"/>
        <path d="M100 0 L10 20 C10 100 30 190 100 240 Z" fill="#8B5CF6"/>
        <path d="M100 30 C130 30 150 50 150 80 C150 110 120 130 100 130 C80 130 60 120 60 110" stroke="#FFF" stroke-width="12" stroke-linecap="round"/>
        <path d="M50 50 L140 50 C145 50 150 55 150 60 L150 100 C150 105 145 110 140 110 L50 110 C45 110 40 105 40 100 L40 60 C40 55 45 50 50 50 Z" fill="#ef4444"/>
        <path d="M40 60 L95 85 L150 60" stroke="#dc2626" stroke-width="6"/>
        <path d="M20 150 L70 100 M40 170 L90 120 M60 190 L110 140" stroke="#FFF" stroke-width="8" stroke-linecap="round"/>
      </svg>
    </div>
    <span class="logo-title">SWIFT <span>INBOX</span></span>
  </a>
  <?php if ($unread_count > 0): ?>
  <div class="topbar-pill"><?= $unread_count ?> unread</div>
  <?php endif; ?>

  <form class="search-wrap" method="GET" action="index.php" style="margin-left:24px">
    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
    <input type="text" name="q" id="search-input" class="search-input"
           placeholder="Search emails…  (press /)" value="<?= htmlspecialchars($search) ?>">
    <?php if ($filter!=='all'): ?><input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>"><?php endif; ?>
  </form>

  <div class="topbar-right">
    <label style="display:flex;align-items:center;gap:8px;font-size:0.8rem;color:var(--text-2);cursor:pointer;margin-right:10px;">
      <span style="font-weight:600;">Auto Fetch (10s)</span>
      <div style="position:relative;width:36px;height:20px;">
        <input type="checkbox" id="auto-fetch-toggle" style="opacity:0;width:0;height:0;position:absolute;">
        <div id="toggle-bg" style="position:absolute;inset:0;background:var(--surface3);border-radius:10px;transition:0.3s;border:1px solid var(--border-strong);">
          <div id="toggle-knob" style="position:absolute;left:2px;top:2px;width:14px;height:14px;border-radius:50%;background:var(--text-2);transition:0.3s;"></div>
        </div>
      </div>
    </label>
    <button id="fetch-btn" class="btn btn-glass" title="Fetch inbox &amp; sent mail from Gmail">
      <span id="fetch-icon">↻</span> Fetch Emails
    </button>
    <?php if ($total_count > 0): ?>
    <form method="POST" style="display:inline" onsubmit="return confirm('Delete ALL emails?')">
      <button name="delete_all" value="1" class="btn btn-glass-danger" type="submit">🗑</button>
    </form>
    <?php endif; ?>
  </div>
</header>

<div class="layout">
  <!-- ── SIDEBAR ── -->
  <nav class="sidebar">
    <div class="sidebar-label">Mailbox</div>
    <a href="index.php" class="nav-item <?= $folder==='all'&&$filter==='all'&&!$search?'active':'' ?>">
      <span>📬</span> All Mail
      <?php if ($total_count): ?><span class="nav-cnt" style="background:var(--text-3)"><?= $total_count ?></span><?php endif; ?>
    </a>
    <a href="index.php?folder=inbox" class="nav-item <?= $folder==='inbox'?'active':'' ?>">
      <span>📥</span> Inbox
      <?php if ($unread_count): ?><span class="nav-cnt"><?= $unread_count ?> unread</span><?php endif; ?>
    </a>
    <a href="index.php?folder=sent" class="nav-item <?= $folder==='sent'?'active':'' ?>">
      <span>📤</span> Sent
      <?php if ($sent_count): ?><span class="nav-cnt cyan"><?= $sent_count ?></span><?php endif; ?>
    </a>

    <a href="index.php?filter=unread" class="nav-item <?= $filter==='unread'?'active':'' ?>">
      <span>📩</span> Unread
      <?php if ($unread_count): ?><span class="nav-cnt"><?= $unread_count ?></span><?php endif; ?>
    </a>

    <div class="sidebar-label">Tools</div>
    <a href="db_setup.php"     class="nav-item"><span>⚙️</span> DB Setup</a>
  </nav>

  <!-- ── MAIN ── -->
  <main class="main" id="email-main">

    <!-- BENTO GRID -->
    <div class="bento-grid">
      <div class="bento-card violet">
        <div class="stat-icon">📬</div>
        <div class="stat-val"><?= $total_count ?></div>
        <div class="stat-label">Total Emails</div>
      </div>
      <div class="bento-card cyan">
        <div class="stat-icon">📥</div>
        <div class="stat-val"><?= $inbox_count ?></div>
        <div class="stat-label">Inbox</div>
      </div>
      <div class="bento-card amber">
        <div class="stat-icon">📤</div>
        <div class="stat-val"><?= $sent_count ?></div>
        <div class="stat-label">Sent</div>
      </div>
      <div class="bento-card green">
        <div class="stat-icon">📩</div>
        <div class="stat-val"><?= $unread_count ?></div>
        <div class="stat-label">Unread</div>
      </div>
    </div>



    <!-- EMAIL LIST -->
    <div class="list-section">
      <div class="list-header">
        <div class="list-header-title">
          <?php
            if ($search)            echo '🔍 '.htmlspecialchars($search);
            elseif ($folder==='sent')   echo '📤 Sent Mail';
            elseif ($folder==='inbox')  echo '📥 Inbox';
            elseif ($filter==='starred') echo '⭐ Starred';
            elseif ($filter==='unread')  echo '📩 Unread';
            else echo '📬 All Mail';
          ?>
        </div>
        <div class="list-count"><?= $total_emails ?> email<?= $total_emails!==1?'s':'' ?></div>
      </div>

      <?php if (empty($emails)): ?>
        <div class="empty-state">
          <div class="empty-icon">📭</div>
          <h3><?= $search ? 'No results found' : 'Inbox is empty' ?></h3>
          <p><?= $search ? 'Try different search terms.' : 'Hit <strong>Fetch</strong> to pull your latest Gmail messages.' ?></p>
          <?php if (!$search): ?>
          <a href="fetch_emails.php" class="btn btn-glass" style="margin-top:8px">↻ Fetch Emails</a>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <?php
        $av_classes = ['av0','av1','av2','av3','av4','av5','av6','av7'];
        foreach ($emails as $email):
          $is_sent    = ($email['folder'] ?? 'inbox') === 'sent';
          $is_unread  = !$email['is_read'] && !$is_sent;
          // For sent mail, show the recipient; for inbox show the sender
          $display_name  = $is_sent
            ? ($email['to_email'] ?: '(Unknown Recipient)')
            : ($email['from_name'] ?: $email['from_email']);
          $avatar_seed   = $is_sent ? ($email['to_email'] ?? '') : ($email['from_email'] ?? '');
          $initial       = strtoupper(mb_substr($display_name, 0, 1));
          $av_class      = $av_classes[abs(crc32($avatar_seed)) % 8];
          $preview       = trim(strip_tags($email['body_plain'] ?? ''));
          $preview       = preg_replace('/\s+/', ' ', $preview);
          $preview       = mb_substr($preview, 0, 100);
          $ts            = $email['received_at'] ? strtotime($email['received_at']) : null;
          $date_str      = $ts ? (date('Y-m-d',$ts)===date('Y-m-d') ? date('g:i a',$ts) : date('M j',$ts)) : '—';
        ?>
        <div class="email-row <?= $is_unread?'unread':'' ?>" id="row-<?= $email['id'] ?>">
          <?php if ($email['is_starred']): ?><span style="padding:0 4px 0 16px;font-size:.9rem;color:var(--amber)" title="Starred in Gmail">⭐</span><?php else: ?><span style="padding:0 4px 0 16px;font-size:.9rem;opacity:0">⭐</span><?php endif; ?>

          <?php if ($is_unread): ?><div class="unread-dot" style="margin-left:2px;margin-right:-4px"></div><?php endif; ?>
          <?php if ($is_sent): ?><div style="margin-left:14px;margin-right:-4px;font-size:.75rem;color:var(--cyan);font-weight:700">↗</div><?php endif; ?>

          <a href="view_email.php?id=<?= $email['id'] ?>" class="email-link">
            <div class="email-avatar <?= $av_class ?>"><?= htmlspecialchars($initial) ?></div>
            <div class="email-body">
              <div class="email-from">
                <?php if ($is_sent): ?><span style="font-size:.7rem;color:var(--cyan);font-weight:700;margin-right:4px">To:</span><?php endif; ?>
                <?= htmlspecialchars($display_name) ?>
              </div>
              <div class="email-subject"><?= htmlspecialchars($email['subject']??'(No Subject)') ?></div>
              <?php if ($preview): ?><div class="email-preview"><?= htmlspecialchars($preview) ?></div><?php endif; ?>
            </div>
            <div class="email-meta">
              <span class="email-date"><?= $date_str ?></span>
              <div class="email-actions">
                <?php if ($is_unread): ?>
                <form method="POST" style="display:inline"><input type="hidden" name="mark_read" value="<?= $email['id'] ?>"><button class="row-action" type="submit">✓ Read</button></form>
                <?php else: ?>
                <form method="POST" style="display:inline"><input type="hidden" name="mark_unread" value="<?= $email['id'] ?>"><button class="row-action" type="submit">○ Unread</button></form>
                <?php endif; ?>
                <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')"><input type="hidden" name="delete" value="<?= $email['id'] ?>"><button class="row-action del" type="submit">✕</button></form>
              </div>
            </div>
          </a>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if ($total_pages>1): ?>
      <div class="pagination">
        <?php if ($page>1): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page-1])) ?>" class="page-btn">← Prev</a><?php endif; ?>
        <?php for($p=max(1,$page-2);$p<=min($total_pages,$page+2);$p++): ?>
        <a href="?<?= http_build_query(array_merge($_GET,['page'=>$p])) ?>" class="page-btn <?= $p===$page?'current':'' ?>"><?= $p ?></a>
        <?php endfor; ?>
        <?php if ($page<$total_pages): ?><a href="?<?= http_build_query(array_merge($_GET,['page'=>$page+1])) ?>" class="page-btn">Next →</a><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </main>
</div><!-- /.layout -->

<script>
// Auto Fetch Logic
const autoFetchToggle = document.getElementById('auto-fetch-toggle');
const toggleBg = document.getElementById('toggle-bg');
const toggleKnob = document.getElementById('toggle-knob');
let autoFetchInterval = null;

// Restore state from localStorage
const storedAutoFetch = localStorage.getItem('MailXSettings.autoFetch') === 'true';
autoFetchToggle.checked = storedAutoFetch;
updateToggleUI();

if (storedAutoFetch) {
  startAutoFetch();
}

autoFetchToggle.addEventListener('change', (e) => {
  const isChecked = e.target.checked;
  localStorage.setItem('MailXSettings.autoFetch', isChecked);
  updateToggleUI();
  
  if (isChecked) {
    startAutoFetch();
  } else {
    stopAutoFetch();
  }
});

function updateToggleUI() {
  if (autoFetchToggle.checked) {
    toggleBg.style.background = 'var(--cyan)';
    toggleBg.style.borderColor = 'var(--cyan)';
    toggleKnob.style.transform = 'translateX(16px)';
    toggleKnob.style.background = '#fff';
  } else {
    toggleBg.style.background = 'var(--surface3)';
    toggleBg.style.borderColor = 'var(--border-strong)';
    toggleKnob.style.transform = 'translateX(0)';
    toggleKnob.style.background = 'var(--text-2)';
  }
}

function startAutoFetch() {
  if (autoFetchInterval) clearInterval(autoFetchInterval);
  autoFetchInterval = setInterval(() => {
    doFetchEmails(true);
  }, 10000);
}

function stopAutoFetch() {
  if (autoFetchInterval) {
    clearInterval(autoFetchInterval);
    autoFetchInterval = null;
  }
}

function doFetchEmails(silent = false) {
  const btn  = document.getElementById('fetch-btn');
  const icon = document.getElementById('fetch-icon');
  if(btn.disabled) return;
  
  icon.classList.add('spinning');
  btn.disabled = true;
  btn.style.opacity = '0.7';
  
  fetch('fetch_emails.php?ajax=1')
    .then(r => r.json())
    .then(data => {
      if(!data || !data.log) {
          throw new Error('Invalid response');
      }
      const last = data.log[data.log.length - 1] || {msg: 'Sync complete!'};
      if (!silent) showToast('✅ ' + last.msg);
      // Only reload if something was inserted, or if the user clicked manually
      const syncCompleteMsg = data.log.find(l => l.msg && l.msg.includes('Total — Inserted:'));
      let wasInserted = false;
      if (syncCompleteMsg && syncCompleteMsg.msg) {
         const match = syncCompleteMsg.msg.match(/Inserted:\s*(\d+)/);
         if (match && parseInt(match[1]) > 0) wasInserted = true;
      }
      setTimeout(() => {
        if (!silent || wasInserted) {
            location.reload();
        } else {
            icon.classList.remove('spinning');
            btn.disabled = false;
            btn.style.opacity = '';
        }
      }, silent ? 500 : 1800);
    })
    .catch(() => {
      if (!silent) {
          showToast('⚠️ Could not reach server — redirecting…');
          setTimeout(() => { location.href = 'fetch_emails.php'; }, 1000);
      } else {
          icon.classList.remove('spinning');
          btn.disabled = false;
          btn.style.opacity = '';
      }
    });
}

// Keyboard: '/' focuses search
document.addEventListener('keydown', e => {
  if (e.key === '/' && document.activeElement.tagName !== 'INPUT') {
    e.preventDefault();
    document.getElementById('search-input').focus();
  }
});

// AJAX fetch — fetches both inbox and sent in one click
document.getElementById('fetch-btn').addEventListener('click', function() {
  doFetchEmails(false);
});

function showToast(msg) {
  const el = document.createElement('div');
  el.className = 'toast';
  el.textContent = msg;
  document.body.appendChild(el);
  setTimeout(() => { el.style.opacity='0'; el.style.transition='opacity .4s'; setTimeout(()=>el.remove(),400); }, 3500);
}
</script>
</body>
</html>
