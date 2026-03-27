<?php
// ============================================================
//  IMAP Email Fetcher — Inbox + Sent Mail
//  Fetches from both Gmail folders and stores in MySQL.
//  Call via browser or CLI (cron job).
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

set_time_limit(180);
$is_ajax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
         || (isset($_GET['ajax']) && $_GET['ajax'] == 1);

// Optional: fetch only a specific folder via ?folder=sent or ?folder=inbox
$only_folder = isset($_GET['folder']) ? $_GET['folder'] : 'all';

$log = [];

function log_msg(string $msg, string $type = 'info'): void {
    global $log;
    $log[] = ['type' => $type, 'msg' => $msg];
}

// ---------------------------------------------------------------
// Check PHP IMAP extension
// ---------------------------------------------------------------
if (!function_exists('imap_open')) {
    log_msg('PHP IMAP extension is not enabled. Open php.ini and uncomment: extension=imap', 'error');
    output_and_exit();
}

$db = get_db();

// ---------------------------------------------------------------
// Define folders to fetch from
// ---------------------------------------------------------------
$folders = [];
if ($only_folder !== 'sent') {
    $folders[] = ['host' => IMAP_HOST,      'name' => 'inbox', 'label' => 'Inbox',     'limit' => FETCH_LIMIT];
}
if ($only_folder !== 'inbox') {
    $folders[] = ['host' => IMAP_HOST_SENT, 'name' => 'sent',  'label' => 'Sent Mail', 'limit' => FETCH_SENT_LIMIT];
}

$total_inserted = 0;
$total_skipped  = 0;

// ---------------------------------------------------------------
// Process each folder
// ---------------------------------------------------------------
foreach ($folders as $folder_cfg) {
    $folder_label = $folder_cfg['label'];
    $folder_name  = $folder_cfg['name'];

    log_msg("── {$folder_label} ──────────────────────");
    log_msg("Connecting to Gmail [{$folder_label}]…");

    $mailbox = @imap_open(
        $folder_cfg['host'],
        IMAP_USER,
        IMAP_PASS,
        0, 1,
        ['DISABLE_AUTHENTICATOR' => 'GSSAPI']
    );

    if (!$mailbox) {
        $err = imap_last_error();
        log_msg("❌ IMAP connection failed for [{$folder_label}]: {$err}", 'error');
        log_msg('Make sure you are using a Gmail App Password.', 'warn');
        continue;
    }

    log_msg("✅ Connected to [{$folder_label}].");

    // Search all UIDs
    $uids = imap_search($mailbox, 'ALL', SE_UID);
    if (!$uids) {
        log_msg("No emails found in [{$folder_label}].", 'warn');
        imap_close($mailbox);
        continue;
    }

    // Get the UIDs that have the \Flagged flag (= Gmail stars)
    $flagged_uids = imap_search($mailbox, 'FLAGGED', SE_UID) ?: [];
    $flagged_set  = array_flip($flagged_uids); // for O(1) lookup

    arsort($uids);
    $uids  = array_slice($uids, 0, $folder_cfg['limit'], true);
    $count = count($uids);
    log_msg("Found {$count} email(s) in [{$folder_label}] (" . count($flagged_uids) . " starred).");

    $inserted = 0;
    $skipped  = 0;

    foreach ($uids as $uid) {
        $is_starred = isset($flagged_set[$uid]) ? 1 : 0;

        // Dedup by uid + folder — but update is_starred to stay in sync with Gmail
        $stmt = $db->prepare("SELECT id FROM emails WHERE uid = ? AND folder = ?");
        $stmt->bind_param('ss', $uid, $folder_name);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $stmt->bind_result($existing_id);
            $stmt->fetch();
            $stmt->close();
            // Update star status so it always reflects Gmail
            $upd = $db->prepare("UPDATE emails SET is_starred = ? WHERE id = ?");
            $upd->bind_param('ii', $is_starred, $existing_id);
            $upd->execute();
            $upd->close();
            $skipped++;
            continue;
        }
        $stmt->close();

        // Parse headers
        $header    = imap_rfc822_parse_headers(imap_fetchheader($mailbox, $uid, FT_UID));
        $structure = imap_fetchstructure($mailbox, $uid, FT_UID);

        // From
        $from_name  = '';
        $from_email = '';
        if (!empty($header->from)) {
            $f          = $header->from[0];
            $from_email = trim(($f->mailbox ?? '') . '@' . ($f->host ?? ''));
            $from_name  = isset($f->personal) ? decode_mime_str($f->personal) : $from_email;
        }

        // To
        $to_email = '';
        if (!empty($header->to)) {
            $parts = [];
            foreach ($header->to as $t) {
                $parts[] = trim(($t->mailbox ?? '') . '@' . ($t->host ?? ''));
            }
            $to_email = implode(', ', $parts);
        }

        $subject    = isset($header->subject) ? decode_mime_str($header->subject) : '(No Subject)';
        $message_id = $header->message_id ?? null;

        $received_at = null;
        if (!empty($header->date)) {
            $ts = @strtotime($header->date);
            if ($ts) $received_at = date('Y-m-d H:i:s', $ts);
        }

        [$body_plain, $body_html, $attachments] = parse_body($mailbox, $uid, $structure);
        $att_json = !empty($attachments) ? json_encode($attachments) : null;

        $stmt = $db->prepare(
            "INSERT INTO emails (uid, folder, message_id, from_name, from_email, to_email, subject, body_plain, body_html, attachments, received_at, is_starred)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param(
            'sssssssssssi',
            $uid, $folder_name, $message_id,
            $from_name, $from_email, $to_email,
            $subject, $body_plain, $body_html,
            $att_json, $received_at, $is_starred
        );
        if ($stmt->execute()) {
            $inserted++;
        } else {
            log_msg("DB insert error for UID {$uid} [{$folder_label}]: " . $stmt->error, 'error');
        }
        $stmt->close();

        if (MARK_AS_READ && $folder_name === 'inbox') {
            imap_setflag_full($mailbox, (string)$uid, '\\Seen', ST_UID);
        }
    }

    imap_close($mailbox);
    log_msg("✅ [{$folder_label}] — Inserted: {$inserted} | Skipped (duplicates): {$skipped}");
    $total_inserted += $inserted;
    $total_skipped  += $skipped;
}

log_msg("══ Total — Inserted: {$total_inserted} | Skipped: {$total_skipped}");
output_and_exit();

// ================================================================
//  HELPER FUNCTIONS
// ================================================================

function decode_mime_str(string $str): string {
    $decoded = imap_mime_header_decode($str);
    $result  = '';
    foreach ($decoded as $part) {
        $charset = strtolower($part->charset ?? 'utf-8');
        $text    = $part->text;
        if ($charset !== 'default' && $charset !== 'utf-8') {
            $text = mb_convert_encoding($text, 'UTF-8', $charset);
        }
        $result .= $text;
    }
    return trim($result);
}

function parse_body($mailbox, int $uid, object $structure, string $section = ''): array {
    $plain = ''; $html = ''; $attachments = [];

    if (isset($structure->parts) && is_array($structure->parts)) {
        foreach ($structure->parts as $i => $part) {
            $pnum = ($section === '') ? (string)($i + 1) : $section . '.' . ($i + 1);
            [$p, $h, $a] = parse_body($mailbox, $uid, $part, $pnum);
            $plain .= $p; $html .= $h;
            $attachments = array_merge($attachments, $a);
        }
    } else {
        $type    = strtolower($structure->subtype ?? 'plain');
        $section = $section ?: '1';

        $is_attachment = !empty($structure->disposition) && strtolower($structure->disposition) === 'attachment';
        $filename      = '';

        if (!empty($structure->dparameters)) {
            foreach ($structure->dparameters as $dp) {
                if (strtolower($dp->attribute) === 'filename') {
                    $filename = decode_mime_str($dp->value); $is_attachment = true;
                }
            }
        }
        if (!$filename && !empty($structure->parameters)) {
            foreach ($structure->parameters as $p) {
                if (strtolower($p->attribute) === 'name') {
                    $filename = decode_mime_str($p->value); $is_attachment = true;
                }
            }
        }
        if ($is_attachment) {
            $attachments[] = ['filename' => $filename, 'section' => $section];
            return [$plain, $html, $attachments];
        }

        $body     = imap_fetchbody($mailbox, $uid, $section, FT_UID);
        $body     = decode_body($body, $structure->encoding ?? 0);
        $charset  = 'UTF-8';
        if (!empty($structure->parameters)) {
            foreach ($structure->parameters as $p) {
                if (strtolower($p->attribute) === 'charset') $charset = $p->value;
            }
        }
        if (strtolower($charset) !== 'utf-8') {
            $body = mb_convert_encoding($body, 'UTF-8', $charset);
        }
        if ($type === 'plain') $plain = $body;
        elseif ($type === 'html') $html = $body;
    }
    return [$plain, $html, $attachments];
}

function decode_body(string $body, int $encoding): string {
    return match($encoding) {
        1 => imap_8bit($body),
        2 => imap_binary($body),
        3 => base64_decode($body),
        4 => quoted_printable_decode($body),
        default => $body
    };
}

function output_and_exit(): void {
    global $log, $is_ajax;
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['log' => $log]);
        exit;
    }

    $has_error = false; $has_warn = false;
    foreach ($GLOBALS['log'] as $e) {
        if ($e['type'] === 'error') $has_error = true;
        if ($e['type'] === 'warn')  $has_warn  = true;
    }
    $status_color = $has_error ? '#ef4444' : ($has_warn ? '#f59e0b' : '#22c55e');
    $status_icon  = $has_error ? '❌' : ($has_warn ? '⚠️' : '✅');
    $status_label = $has_error ? 'Completed with errors' : ($has_warn ? 'Completed with warnings' : 'Completed successfully');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Fetch Results — MailX</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
  --bg:#0B0B0C; --surface:#161618; --surface2:#1d1d20; --surface3:#252528;
  --border:rgba(255,255,255,.07); --border-strong:rgba(255,255,255,.14);
  --violet:#8B5CF6; --violet-dim:rgba(139,92,246,.15); --violet-glow:rgba(139,92,246,.35);
  --cyan:#06B6D4; --cyan-dim:rgba(6,182,212,.12); --cyan-glow:rgba(6,182,212,.3);
  --text:#F8FAFC; --text-2:#94a3b8; --text-3:#475569;
  --green:#22c55e; --amber:#f59e0b; --red:#ef4444;
  --glass-bg:rgba(22,22,24,.75); --glass-border:rgba(255,255,255,.08);
  --nb-shadow:3px 3px 0px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;overflow-x:hidden;}
body::before,body::after{content:'';position:fixed;border-radius:50%;pointer-events:none;z-index:0;filter:blur(130px);}
body::before{width:500px;height:500px;background:var(--violet);opacity:.14;top:-180px;left:-180px;}
body::after{width:400px;height:400px;background:var(--cyan);opacity:.12;bottom:-150px;right:-150px;}
.card{position:relative;z-index:1;background:var(--glass-bg);backdrop-filter:blur(24px) saturate(180%);-webkit-backdrop-filter:blur(24px) saturate(180%);border:1px solid var(--glass-border);border-radius:24px;padding:40px 44px;width:100%;max-width:580px;box-shadow:0 32px 80px rgba(0,0,0,.6);overflow:hidden;}
.card::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,var(--violet),var(--cyan),var(--violet));background-size:200% 100%;animation:shimmer 3s linear infinite;}
@keyframes shimmer{to{background-position:-200% 0;}}
.card::after{content:'';position:absolute;top:-60px;right:-60px;width:200px;height:200px;border-radius:50%;background:radial-gradient(circle,rgba(139,92,246,.15),transparent 70%);pointer-events:none;}
.header-row{display:flex;align-items:center;gap:14px;margin-bottom:28px;}
.logo-icon{width:48px;height:48px;background:transparent;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.header-title{font-family:'Montserrat',sans-serif;font-weight:800;font-size:1.15rem;color:var(--text);letter-spacing:-.02em;}
.header-title span{color:var(--violet);}
.header-sub{font-size:.75rem;color:var(--text-3);margin-top:2px;}
.status-badge{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:12px;margin-bottom:20px;font-size:.83rem;font-weight:600;width:100%;}
.log-list{display:flex;flex-direction:column;gap:7px;margin-bottom:28px;max-height:400px;overflow-y:auto;}
.log-list::-webkit-scrollbar{width:4px;}
.log-list::-webkit-scrollbar-thumb{background:var(--surface3);border-radius:2px;}
.log-entry{display:flex;align-items:flex-start;gap:11px;padding:11px 14px;border-radius:11px;font-size:.83rem;line-height:1.5;border:1px solid transparent;}
.log-entry.info{background:rgba(139,92,246,.06);border-color:rgba(139,92,246,.15);color:var(--text-2);}
.log-entry.warn{background:rgba(245,158,11,.06);border-color:rgba(245,158,11,.2);color:#fde68a;}
.log-entry.error{background:rgba(239,68,68,.06);border-color:rgba(239,68,68,.2);color:#fca5a5;}
/* separator lines */
.log-entry.sep{background:none;border-color:transparent;color:var(--text-3);font-weight:700;font-size:.72rem;letter-spacing:.08em;text-transform:uppercase;padding:6px 14px 0;}
.log-icon{flex-shrink:0;font-size:.95rem;margin-top:1px;}
.log-msg{flex:1;word-break:break-word;}
.btn-group{display:flex;gap:10px;flex-wrap:wrap;}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:12px 22px;border-radius:11px;font-size:.85rem;font-weight:700;cursor:pointer;text-decoration:none;font-family:'Inter',sans-serif;border:none;transition:all .14s cubic-bezier(.4,0,.2,1);flex:1;min-width:130px;}
.btn-primary{background:var(--violet);color:var(--text);border:2px solid #EBF4DD;box-shadow:var(--nb-shadow) #EBF4DD;}
.btn-primary:hover{transform:translate(-2px,-2px);box-shadow:5px 5px 0 #EBF4DD;}
.btn-ghost{background:transparent;color:var(--text-2);border:1px solid var(--border-strong);}
.btn-ghost:hover{background:var(--surface3);color:var(--text);}
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
.shortcut-row{display:flex;gap:8px;margin-bottom:10px;flex-wrap:wrap;}
.shortcut{display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;font-size:.75rem;font-weight:600;text-decoration:none;border:1px solid var(--border-strong);color:var(--text-3);transition:all .13s;}
.shortcut:hover{background:var(--surface3);color:var(--text);}
</style>
</head>
<body>
<div class="card">
  <div class="header-row">
    <div class="logo-icon">
      <svg viewBox="0 0 200 240" fill="none" xmlns="http://www.w3.org/2000/svg" style="width:100%;height:100%;">
        <path d="M100 0 L190 20 C190 100 170 190 100 240 C30 190 10 100 10 20 Z" fill="#06B6D4"/>
        <path d="M100 0 L10 20 C10 100 30 190 100 240 Z" fill="#8B5CF6"/>
        <path d="M100 30 C130 30 150 50 150 80 C150 110 120 130 100 130 C80 130 60 120 60 110" stroke="#FFF" stroke-width="12" stroke-linecap="round"/>
        <path d="M50 50 L140 50 C145 50 150 55 150 60 L150 100 C150 105 145 110 140 110 L50 110 C45 110 40 105 40 100 L40 60 C40 55 45 50 50 50 Z" fill="#ef4444"/>
        <path d="M40 60 L95 85 L150 60" stroke="#dc2626" stroke-width="6"/>
        <path d="M20 150 L70 100 M40 170 L90 120 M60 190 L110 140" stroke="#FFF" stroke-width="8" stroke-linecap="round"/>
      </svg>
    </div>
    <div>
      <div class="header-title">SWIFT <span>INBOX</span> Sync</div>
      <div class="header-sub">Inbox + Sent Mail sync — <?= htmlspecialchars(IMAP_USER) ?></div>
    </div>
  </div>

  <div class="status-badge" style="background:<?= $status_color ?>18;border:1px solid <?= $status_color ?>33;color:<?= $status_color ?>">
    <span><?= $status_icon ?></span>
    <span><?= htmlspecialchars($status_label) ?></span>
  </div>

  <div class="log-list">
    <?php
    $type_icons = ['info'=>'›', 'warn'=>'⚠', 'error'=>'✕'];
    foreach ($GLOBALS['log'] as $entry):
        $t = htmlspecialchars($entry['type']);
        // Render section separators differently
        if (str_starts_with($entry['msg'], '──')) {
            echo '<div class="log-entry sep"><span class="log-msg">' . htmlspecialchars(trim($entry['msg'], '─ ')) . '</span></div>';
            continue;
        }
        $i = $type_icons[$entry['type']] ?? '›';
    ?>
    <div class="log-entry <?= $t ?>">
      <span class="log-icon"><?= $i ?></span>
      <span class="log-msg"><?= htmlspecialchars($entry['msg']) ?></span>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="btn-group">
    <a href="index.php"        class="btn btn-glass">→ View Inbox</a>
    <a href="fetch_emails.php" class="btn btn-glass">↻ Fetch Again</a>
  </div>
</div>
</body>
</html>
    <?php
    exit;
}
