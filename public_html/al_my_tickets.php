<?php
session_start();
require_once 'db_config.php';
require_once __DIR__ . '/includes/mysqli_compat.php';
alumni_otp_gate_after_session();
$conn = getDBConnection();

if (!isset($_SESSION['user_id'])) {
    header('Location: al_homepage.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$user = [];
$notifications = [];
$tickets = [];

$stmt = $conn->prepare("SELECT * FROM itcp WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = mysqli_stmt_fetch_assoc_compat($stmt) ?: [];
    $stmt->close();
}

$stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $notifications = mysqli_stmt_fetch_all_assoc_compat($stmt);
    $stmt->close();
}
$notification_count = count(array_filter($notifications, static function ($n) {
    return empty($n['is_read']);
}));

$user_email = $user['email'] ?? '';
if ($user_email !== '') {
    $stmt = $conn->prepare("SELECT id, name, email, subject, message, status, is_read, submitted_at FROM contact_messages WHERE email = ? ORDER BY submitted_at DESC");
    if ($stmt) {
        $stmt->bind_param("s", $user_email);
        $stmt->execute();
        $tickets = mysqli_stmt_fetch_all_assoc_compat($stmt);
        $stmt->close();
    }
}

$cnt_new = count(array_filter($tickets, static function ($t) { return ($t['status'] ?? '') === 'New'; }));
$cnt_progress = count(array_filter($tickets, static function ($t) { return ($t['status'] ?? '') === 'In Progress'; }));
$cnt_resolved = count(array_filter($tickets, static function ($t) { return ($t['status'] ?? '') === 'Resolved'; }));
$cnt_total = count($tickets);

function getStatusMeta($status)
{
    switch ((string) $status) {
        case 'New':
            return ['label' => 'Open', 'color' => '#DC2626', 'bg' => 'rgba(220,38,38,.1)', 'icon' => 'circle-dot'];
        case 'In Progress':
            return ['label' => 'In Progress', 'color' => '#D97706', 'bg' => 'rgba(217,119,6,.1)', 'icon' => 'clock'];
        case 'Resolved':
            return ['label' => 'Resolved', 'color' => '#2D6A4F', 'bg' => 'rgba(45,106,79,.1)', 'icon' => 'circle-check'];
        case 'Spam':
            return ['label' => 'Spam', 'color' => '#6B7280', 'bg' => 'rgba(107,114,128,.1)', 'icon' => 'ban'];
        default:
            return ['label' => (string) $status, 'color' => '#3B82F6', 'bg' => 'rgba(59,130,246,.1)', 'icon' => 'ticket'];
    }
}

function formatTime($datetime)
{
    if (!$datetime) return '';
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) { $m = floor($diff / 60); return $m . 'm ago'; }
    if ($diff < 86400) { $h = floor($diff / 3600); return $h . 'h ago'; }
    if ($diff < 604800) { $d = floor($diff / 86400); return $d . 'd ago'; }
    return date('M j, Y', strtotime($datetime));
}

function getCategoryIcon($subject)
{
    $s = strtolower((string) $subject);
    if (strpos($s, 'account') !== false || strpos($s, 'login') !== false || strpos($s, 'password') !== false) return 'key';
    if (strpos($s, 'profile') !== false) return 'user-pen';
    if (strpos($s, 'card') !== false || strpos($s, 'alumni card') !== false) return 'id-card';
    if (strpos($s, 'career') !== false || strpos($s, 'job') !== false) return 'briefcase';
    if (strpos($s, 'event') !== false) return 'calendar-days';
    if (strpos($s, 'technical') !== false || strpos($s, 'tech') !== false || strpos($s, 'bug') !== false) return 'screwdriver-wrench';
    if (strpos($s, 'privacy') !== false || strpos($s, 'data') !== false) return 'shield-halved';
    return 'ticket';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>My Support Tickets - OLFU Alumni Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet"/>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet"/>
<style>
:root { --cream:#F5F3EC; --cream-dark:#EDE9DF; --forest:#1A3D2B; --forest-mid:#2D6A4F; --gold:#C9A84C; --gold-light:#F0D98C; --ink:#1C1C1A; --ink-soft:#4A4A45; --ink-muted:#8A8A82; --white:#FFFFFF; --red:#DC2626; --amber:#D97706; --blue:#3B82F6; --radius:14px; --shadow:0 2px 20px rgba(26,61,43,0.08); --shadow-lg:0 8px 40px rgba(26,61,43,0.14); }
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--ink);line-height:1.6;min-height:100vh}
body::before{content:'';position:fixed;inset:0;background-image:radial-gradient(circle, rgba(26,61,43,0.04) 1px, transparent 1px);background-size:28px 28px;pointer-events:none;z-index:0}
.pw{position:relative;z-index:1;max-width:1200px;margin:0 auto;padding:2rem 1.5rem 5rem}
.page-header{padding:2.5rem 0 1.75rem;display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:1rem}
.page-header h1{font-family:'Cormorant Garamond',serif;font-size:clamp(2rem,4vw,2.8rem);font-weight:700;color:var(--forest);line-height:1.15}
.page-header h1 em{font-style:italic;color:var(--forest-mid)}
.rule{height:3px;width:52px;background:linear-gradient(90deg,var(--forest-mid),var(--gold));border-radius:99px;margin-top:8px}
.page-header p{color:var(--ink-soft);font-size:.95rem;margin-top:8px}
.alert{padding:13px 16px;border-radius:10px;font-size:.875rem;display:flex;align-items:center;gap:10px;margin-bottom:1.5rem}
.alert-success{background:#DCFCE7;color:#166534;border:1px solid #86efac}.alert-success i{color:#16a34a}
.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.75rem}
.stat-card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:1.25rem;display:flex;align-items:center;gap:14px;box-shadow:var(--shadow);transition:transform .2s,box-shadow .2s;position:relative;overflow:hidden}
.stat-card::before{content:'';position:absolute;top:0;left:0;width:4px;height:100%;border-radius:4px 0 0 4px}
.stat-card.open::before{background:var(--red)}.stat-card.prog::before{background:var(--amber)}.stat-card.done::before{background:var(--forest-mid)}.stat-card.total::before{background:var(--blue)}
.stat-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-lg)}
.stat-icon{width:44px;height:44px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0}
.stat-icon.red{background:rgba(220,38,38,.1);color:var(--red)}.stat-icon.amber{background:rgba(217,119,6,.1);color:var(--amber)}.stat-icon.green{background:rgba(45,106,79,.1);color:var(--forest-mid)}.stat-icon.blue{background:rgba(59,130,246,.1);color:var(--blue)}
.stat-val{font-size:1.6rem;font-weight:700;color:var(--forest);line-height:1}.stat-lbl{font-size:.72rem;font-weight:600;color:var(--ink-muted);margin-top:2px;text-transform:uppercase;letter-spacing:.06em}
.filter-bar{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:1rem 1.25rem;margin-bottom:1.5rem;box-shadow:var(--shadow);display:flex;flex-wrap:wrap;gap:.85rem;align-items:center}
.search-wrap{position:relative;flex:1;min-width:180px}.search-wrap i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--ink-muted);font-size:.82rem;pointer-events:none}
.search-input{width:100%;padding:9px 12px 9px 33px;border:1.5px solid var(--cream-dark);border-radius:9px;font-family:'DM Sans',sans-serif;font-size:.875rem;color:var(--ink);background:var(--cream);outline:none;transition:border-color .15s,box-shadow .15s}
.search-input:focus{border-color:var(--forest-mid);box-shadow:0 0 0 3px rgba(45,106,79,.1);background:var(--white)}
.chip-row{display:flex;flex-wrap:wrap;gap:5px}.chip{padding:5px 13px;border-radius:999px;font-size:.73rem;font-weight:600;cursor:pointer;border:1.5px solid var(--cream-dark);background:var(--cream);color:var(--ink-soft);transition:all .15s;white-space:nowrap;user-select:none}
.chip:hover{border-color:var(--forest-mid);color:var(--forest-mid)}.chip.active{background:var(--forest);color:var(--white);border-color:var(--forest)}
.sort-select{padding:7px 12px;border:1.5px solid var(--cream-dark);border-radius:9px;font-family:'DM Sans',sans-serif;font-size:.82rem;color:var(--ink-soft);background:var(--cream);outline:none;cursor:pointer}
.main-card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden}
.main-card-head{padding:1.25rem 1.5rem;border-bottom:1px solid var(--cream-dark);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.75rem}
.main-card-head-left h2{font-family:'Cormorant Garamond',serif;font-size:1.3rem;font-weight:700;color:var(--forest)}.main-card-head-left p{font-size:.8rem;color:var(--ink-muted);margin-top:2px}
.ticket-count-badge{font-size:.72rem;font-weight:700;background:var(--cream);color:var(--ink-soft);border:1.5px solid var(--cream-dark);padding:3px 10px;border-radius:999px}
.ticket-row{border-bottom:1px solid var(--cream-dark);transition:background .15s;cursor:pointer}.ticket-row:last-child{border-bottom:none}.ticket-row:hover{background:var(--cream)}.ticket-row.unread .ticket-subject{font-weight:700}
.ticket-inner{padding:1.1rem 1.5rem;display:grid;grid-template-columns:42px 1fr auto auto auto;align-items:center;gap:1rem}
.ticket-icon-wrap{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:.88rem;flex-shrink:0}
.ticket-id{font-size:.65rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-muted)}
.ticket-subject{font-size:.9rem;font-weight:600;color:var(--ink);margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:400px}
.ticket-preview{font-size:.78rem;color:var(--ink-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:400px}
.status-pill{display:inline-flex;align-items:center;gap:5px;padding:4px 11px;border-radius:999px;font-size:.72rem;font-weight:700;white-space:nowrap}.status-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0}
.ticket-time{font-size:.75rem;color:var(--ink-muted);white-space:nowrap;text-align:right}.ticket-action{display:flex;align-items:center;gap:6px}
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:9px;font-family:'DM Sans',sans-serif;font-size:.85rem;font-weight:600;text-decoration:none;cursor:pointer;border:none;transition:all .2s}
.btn-forest{background:var(--forest);color:var(--white)}.btn-forest:hover{background:var(--forest-mid)}.btn-outline{background:transparent;border:1.5px solid var(--cream-dark);color:var(--ink-soft)}.btn-outline:hover{background:var(--cream-dark)}
.btn-sm{padding:6px 13px;font-size:.78rem}.btn-icon{width:32px;height:32px;padding:0;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.8rem;background:var(--cream);border:1.5px solid var(--cream-dark);color:var(--ink-soft)}
.btn-icon:hover{background:var(--forest);color:var(--white);border-color:var(--forest)}
.empty-state{padding:4rem 2rem;text-align:center}.empty-icon{width:72px;height:72px;border-radius:18px;background:var(--cream);display:flex;align-items:center;justify-content:center;font-size:1.8rem;color:var(--cream-dark);margin:0 auto 16px}
.empty-state h3{font-family:'Cormorant Garamond',serif;font-size:1.5rem;font-weight:700;color:var(--forest);margin-bottom:6px}.empty-state p{font-size:.88rem;color:var(--ink-muted);margin-bottom:1.5rem}
.no-results{padding:2.5rem;text-align:center;font-size:.88rem;color:var(--ink-muted);display:none}.no-results i{font-size:1.8rem;color:var(--cream-dark);display:block;margin-bottom:10px}
.drawer-overlay{position:fixed;inset:0;background:rgba(26,61,43,.45);backdrop-filter:blur(3px);z-index:200;display:none;align-items:center;justify-content:center;padding:1.5rem}
.drawer-overlay.open{display:flex}.drawer{background:var(--white);border-radius:18px;max-width:640px;width:100%;max-height:90vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 24px 80px rgba(0,0,0,.22)}
.drawer-header{padding:1.4rem 1.5rem;border-bottom:1px solid var(--cream-dark);display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}
.drawer-header-left h3{font-family:'Cormorant Garamond',serif;font-size:1.3rem;font-weight:700;color:var(--forest)}.drawer-header-left .tid{font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-muted);margin-bottom:4px}
.drawer-close{width:32px;height:32px;background:var(--cream);border:none;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;color:var(--ink-muted);font-size:.8rem}
.drawer-body{padding:1.4rem 1.5rem;overflow-y:auto;flex:1}.drawer-meta{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1.25rem}
.dm-item{background:var(--cream);border-radius:9px;padding:.75rem}.dm-label{font-size:.65rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-muted);margin-bottom:3px}.dm-val{font-size:.85rem;font-weight:600;color:var(--ink)}
.drawer-msg-label{font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-muted);margin-bottom:8px}
.drawer-msg{background:var(--cream);border-radius:10px;padding:1.1rem;font-size:.875rem;color:var(--ink-soft);line-height:1.75;white-space:pre-wrap;word-break:break-word}
.drawer-footer{padding:1rem 1.5rem;border-top:1px solid var(--cream-dark);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem}
.drawer-footer-note{font-size:.76rem;color:var(--ink-muted}.drawer-footer-note i{color:var(--gold);margin-right:4px}
.cta-strip{background:var(--forest);border-radius:var(--radius);padding:1.75rem 2.25rem;display:flex;align-items:center;justify-content:space-between;gap:1.5rem;flex-wrap:wrap;margin-top:1.75rem;position:relative;overflow:hidden;box-shadow:var(--shadow-lg)}
.cta-strip h3{font-family:'Cormorant Garamond',serif;font-size:1.3rem;font-weight:700;color:var(--white)}.cta-strip h3 em{color:var(--gold-light);font-style:italic}
.cta-strip p{font-size:.82rem;color:rgba(255,255,255,.6);margin-top:3px}.cta-r{display:flex;gap:9px;flex-wrap:wrap}.btn-gold{background:var(--gold);color:var(--forest)}.btn-gold:hover{background:var(--gold-light)}.btn-ow{background:transparent;border:1.5px solid rgba(255,255,255,.3);color:var(--white)}.btn-ow:hover{background:rgba(255,255,255,.1)}
@media (max-width:900px){.ticket-inner{grid-template-columns:40px 1fr auto}.ticket-time,.ticket-action{display:none}.stats-row{grid-template-columns:repeat(2,1fr)}}
@media (max-width:500px){.stats-row{grid-template-columns:1fr 1fr}.drawer-meta{grid-template-columns:1fr}.filter-bar{flex-direction:column}}
</style>
</head>
<body>
<?php include __DIR__ . '/al_header_universal.php'; ?>
<div class="pw">
  <?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
    <div class="alert alert-success"><i class="fas fa-circle-check"></i><div><strong>Ticket submitted successfully!</strong> Your support request has been received. We'll get back to you within 24-48 hours.</div></div>
  <?php endif; ?>
  <header class="page-header">
    <div><h1>My Support <em>Tickets</em></h1><div class="rule"></div><p>Track and manage your support requests with the OLFU Alumni team.</p></div>
    <a href="al_contact.php#support-request" class="btn btn-forest"><i class="fas fa-plus"></i> New Ticket</a>
  </header>
  <div class="stats-row">
    <div class="stat-card open"><div class="stat-icon red"><i class="fas fa-circle-dot"></i></div><div><div class="stat-val"><?php echo $cnt_new; ?></div><div class="stat-lbl">Open</div></div></div>
    <div class="stat-card prog"><div class="stat-icon amber"><i class="fas fa-clock"></i></div><div><div class="stat-val"><?php echo $cnt_progress; ?></div><div class="stat-lbl">In Progress</div></div></div>
    <div class="stat-card done"><div class="stat-icon green"><i class="fas fa-circle-check"></i></div><div><div class="stat-val"><?php echo $cnt_resolved; ?></div><div class="stat-lbl">Resolved</div></div></div>
    <div class="stat-card total"><div class="stat-icon blue"><i class="fas fa-ticket"></i></div><div><div class="stat-val"><?php echo $cnt_total; ?></div><div class="stat-lbl">Total</div></div></div>
  </div>
  <?php if (empty($tickets)): ?>
    <div class="main-card"><div class="empty-state"><div class="empty-icon"><i class="fas fa-ticket"></i></div><h3>No Support Tickets Yet</h3><p>You haven't submitted any support requests. If you need help, we're just one message away.</p><a href="al_contact.php#support-request" class="btn btn-forest"><i class="fas fa-plus"></i> Create Your First Ticket</a></div></div>
  <?php else: ?>
    <div class="filter-bar">
      <div class="search-wrap"><i class="fas fa-search"></i><input type="text" id="ticketSearch" class="search-input" placeholder="Search by subject or message..."/></div>
      <div class="chip-row">
        <span class="chip active" data-filter="all" onclick="setFilter(this)">All</span>
        <span class="chip" data-filter="New" onclick="setFilter(this)">Open</span>
        <span class="chip" data-filter="In Progress" onclick="setFilter(this)">In Progress</span>
        <span class="chip" data-filter="Resolved" onclick="setFilter(this)">Resolved</span>
      </div>
      <select class="sort-select" id="sortSelect" onchange="sortTickets()"><option value="newest">Newest first</option><option value="oldest">Oldest first</option><option value="status">By status</option></select>
    </div>
    <div class="main-card">
      <div class="main-card-head"><div class="main-card-head-left"><h2>Support History</h2><p>Click any ticket to view its full details</p></div><span class="ticket-count-badge" id="visibleCount"><?php echo $cnt_total; ?> tickets</span></div>
      <div id="ticketList">
        <?php foreach ($tickets as $ticket): $sm = getStatusMeta($ticket['status'] ?? ''); $icon = getCategoryIcon($ticket['subject'] ?? ''); $isUnread = empty($ticket['is_read']); ?>
          <div class="ticket-row <?php echo $isUnread ? 'unread' : ''; ?>" data-status="<?php echo htmlspecialchars((string) ($ticket['status'] ?? '')); ?>" data-subject="<?php echo htmlspecialchars(strtolower((string) ($ticket['subject'] ?? ''))); ?>" data-message="<?php echo htmlspecialchars(strtolower((string) ($ticket['message'] ?? ''))); ?>" data-time="<?php echo htmlspecialchars((string) ($ticket['submitted_at'] ?? '')); ?>" onclick="openDrawer(<?php echo (int) ($ticket['id'] ?? 0); ?>, <?php echo htmlspecialchars(json_encode(['id' => (int) ($ticket['id'] ?? 0), 'subject' => (string) ($ticket['subject'] ?? 'No Subject'), 'message' => (string) ($ticket['message'] ?? ''), 'status' => (string) ($ticket['status'] ?? ''), 'submitted' => (string) ($ticket['submitted_at'] ?? ''), 'name' => (string) ($ticket['name'] ?? ''), 'email' => (string) ($ticket['email'] ?? '')]), ENT_QUOTES, 'UTF-8'); ?>)">
            <div class="ticket-inner">
              <div class="ticket-icon-wrap" style="background:<?php echo $sm['bg']; ?>;"><i class="fas fa-<?php echo $icon; ?>" style="color:<?php echo $sm['color']; ?>;"></i></div>
              <div style="min-width:0;">
                <div class="ticket-id">#<?php echo str_pad((string) ($ticket['id'] ?? 0), 5, '0', STR_PAD_LEFT); ?></div>
                <div class="ticket-subject"><?php echo htmlspecialchars((string) (($ticket['subject'] ?? '') ?: 'No Subject')); ?></div>
                <div class="ticket-preview"><?php echo htmlspecialchars(function_exists('mb_strimwidth') ? mb_strimwidth((string) ($ticket['message'] ?? ''), 0, 110, '...') : ((strlen((string) ($ticket['message'] ?? '')) > 110) ? substr((string) ($ticket['message'] ?? ''), 0, 110) . '...' : (string) ($ticket['message'] ?? ''))); ?></div>
              </div>
              <span class="status-pill" style="background:<?php echo $sm['bg']; ?>;color:<?php echo $sm['color']; ?>;"><span class="status-dot" style="background:<?php echo $sm['color']; ?>;"></span><?php echo htmlspecialchars($sm['label']); ?></span>
              <div class="ticket-time"><div><?php echo formatTime((string) ($ticket['submitted_at'] ?? '')); ?></div><?php if ($isUnread): ?><div style="font-size:.62rem;font-weight:700;color:var(--forest-mid);margin-top:2px;">UNREAD</div><?php endif; ?></div>
              <div class="ticket-action"><span class="btn-icon" title="View ticket"><i class="fas fa-eye"></i></span></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="no-results" id="noResults"><i class="fas fa-search"></i>No matching tickets found. <a href="al_contact.php#support-request" style="color:var(--forest-mid);text-decoration:underline;">Submit a new ticket -></a></div>
    </div>
  <?php endif; ?>
  <div class="cta-strip"><div><h3>Need <em>more help?</em></h3><p>Our alumni support team responds within 24-48 hours on business days.</p></div><div class="cta-r"><a href="al_contact.php#support-request" class="btn btn-gold"><i class="fas fa-plus"></i> New Ticket</a><a href="gen_faqs.php" class="btn btn-ow"><i class="fas fa-question-circle"></i> FAQs</a></div></div>
</div>
<div class="drawer-overlay" id="drawerOverlay">
  <div class="drawer">
    <div class="drawer-header"><div class="drawer-header-left"><div class="tid" id="dTid"></div><h3 id="dSubject"></h3></div><button class="drawer-close" onclick="closeDrawer()"><i class="fas fa-times"></i></button></div>
    <div class="drawer-body">
      <div class="drawer-meta">
        <div class="dm-item"><div class="dm-label">Status</div><div class="dm-val" id="dStatus"></div></div>
        <div class="dm-item"><div class="dm-label">Submitted</div><div class="dm-val" id="dTime"></div></div>
        <div class="dm-item"><div class="dm-label">Submitted by</div><div class="dm-val" id="dName"></div></div>
        <div class="dm-item"><div class="dm-label">Contact Email</div><div class="dm-val" id="dEmail"></div></div>
      </div>
      <div class="drawer-msg-label">Your Message</div>
      <div class="drawer-msg" id="dMessage"></div>
    </div>
    <div class="drawer-footer"><div class="drawer-footer-note"><i class="fas fa-info-circle"></i> Replies are sent to your registered email address.</div><div style="display:flex;gap:8px;"><button onclick="closeDrawer()" class="btn btn-outline btn-sm">Close</button><a href="al_contact.php#support-request" class="btn btn-forest btn-sm"><i class="fas fa-plus"></i> New Ticket</a></div></div>
  </div>
</div>
<script>
let activeFilter='all';
function setFilter(el){activeFilter=el.dataset.filter;document.querySelectorAll('.chip').forEach(c=>c.classList.toggle('active',c.dataset.filter===activeFilter));applyFilters();}
function applyFilters(){const q=(document.getElementById('ticketSearch')?.value||'').toLowerCase().trim();const rows=document.querySelectorAll('.ticket-row');let visible=0;rows.forEach(row=>{const statusMatch=activeFilter==='all'||row.dataset.status===activeFilter;const textMatch=!q||row.dataset.subject.includes(q)||row.dataset.message.includes(q);const show=statusMatch&&textMatch;row.style.display=show?'':'none';if(show)visible++;});const badge=document.getElementById('visibleCount');if(badge)badge.textContent=visible+' ticket'+(visible!==1?'s':'');const noRes=document.getElementById('noResults');if(noRes)noRes.style.display=visible===0?'block':'none';}
document.getElementById('ticketSearch')?.addEventListener('input',applyFilters);
function sortTickets(){const val=document.getElementById('sortSelect').value;const list=document.getElementById('ticketList');if(!list)return;const rows=Array.from(list.querySelectorAll('.ticket-row'));rows.sort((a,b)=>{if(val==='newest')return new Date(b.dataset.time)-new Date(a.dataset.time);if(val==='oldest')return new Date(a.dataset.time)-new Date(b.dataset.time);if(val==='status')return (a.dataset.status||'').localeCompare(b.dataset.status||'');return 0;});rows.forEach(r=>list.appendChild(r));applyFilters();}
function openDrawer(id,data){const statusMeta={'New':{label:'Open',color:'#DC2626'},'In Progress':{label:'In Progress',color:'#D97706'},'Resolved':{label:'Resolved',color:'#2D6A4F'},'Spam':{label:'Spam',color:'#6B7280'}};const sm=statusMeta[data.status]||{label:data.status,color:'#3B82F6'};document.getElementById('dTid').textContent='Ticket #'+String(data.id).padStart(5,'0');document.getElementById('dSubject').textContent=data.subject||'No Subject';document.getElementById('dStatus').innerHTML='<span style="color:'+sm.color+';font-weight:700;">'+sm.label+'</span>';document.getElementById('dTime').textContent=formatFull(data.submitted);document.getElementById('dName').textContent=data.name||'-';document.getElementById('dEmail').textContent=data.email||'-';document.getElementById('dMessage').textContent=data.message||'(no message content)';document.getElementById('drawerOverlay').classList.add('open');document.body.style.overflow='hidden';}
function closeDrawer(){document.getElementById('drawerOverlay').classList.remove('open');document.body.style.overflow='';}
document.getElementById('drawerOverlay')?.addEventListener('click',function(e){if(e.target===this)closeDrawer();});
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeDrawer();});
function formatFull(dt){if(!dt)return '-';try{return new Date(dt).toLocaleString('en-PH',{month:'long',day:'numeric',year:'numeric',hour:'numeric',minute:'2-digit',hour12:true});}catch(_){return dt;}}
</script>
<?php if (file_exists(__DIR__ . '/al_footer_universal.php')) { include __DIR__ . '/al_footer_universal.php'; } ?>
</body>
</html>
