<?php
session_start();
require_once 'config.php';
alumni_otp_gate_after_session();
date_default_timezone_set('Asia/Manila');
if (!isset($_SESSION['user_id'])) { header("Location: al_login.php"); exit(); }
$user_id = $_SESSION['user_id'];
if (isset($conn)) $conn->query("SET time_zone = '+08:00'");

$sql = "SELECT * FROM itcp WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt) { $stmt->bind_param("i", $user_id); $stmt->execute(); $user = $stmt->get_result()->fetch_assoc(); } else { $user = []; }

$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$stmt = $conn->prepare($sql);
if ($stmt) { $stmt->bind_param("i", $user_id); $stmt->execute(); $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); } else { $notifications = []; }
$notification_count = count(array_filter($notifications, fn($n) => !$n['is_read']));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_event'])) {
    $event_id = $_POST['event_id'];
    $check_stmt = $conn->prepare("SELECT * FROM event_registrations WHERE event_id = ? AND user_id = ?");
    $check_stmt->bind_param("ii", $event_id, $user_id);
    $check_stmt->execute();
    $existing = $check_stmt->get_result()->fetch_assoc();
    if (!$existing) {
        $conn->begin_transaction();
        try {
            $s = $conn->prepare("INSERT INTO event_registrations (event_id, user_id, status) VALUES (?, ?, 'Registered')");
            $s->bind_param("ii", $event_id, $user_id); $s->execute();
            $conn->commit(); $_SESSION['success'] = "Successfully registered!";
        } catch (Exception $e) { $conn->rollback(); $_SESSION['error'] = "Registration error."; }
    } else { $_SESSION['error'] = "Already registered."; }
    header("Location: al_events.php"); exit();
}

$sql = "SELECT e.*, (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as registered_count, (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id AND user_id = ?) as is_registered FROM events e ORDER BY e.event_date DESC, e.event_time DESC";
$stmt = $conn->prepare($sql);
if ($stmt) { $stmt->bind_param("i", $user_id); $stmt->execute(); $all_events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close(); } else { $all_events = []; }

$now = new DateTime('now', new DateTimeZone('Asia/Manila'));
$upcoming_events = []; $past_events = [];
foreach ($all_events as $e) {
    $event_date = $e['event_date'] ?? ''; if (empty($event_date)) continue;
    $date_only = substr($event_date, 0, 10);
    $event_time = $e['event_time'] ?? '';
    $dt_str = (!empty($event_time) && $event_time != '00:00:00') ? ($date_only.' '.$event_time) : ($date_only.' 00:00:00');
    try {
        $event_dt = new DateTime($dt_str, new DateTimeZone('Asia/Manila'));
        $e['event_datetime'] = $dt_str;
        $e['resolved_location'] = $e['location'] ?? $e['venue'] ?? '';
        $e['normalized_event_type'] = $e['type'] ?? $e['event_type'] ?? 'General';
        if ($event_dt >= $now) $upcoming_events[] = $e; else $past_events[] = $e;
    } catch (Exception $ex) { $e['resolved_location'] = ''; $e['normalized_event_type'] = 'General'; $past_events[] = $e; }
}
usort($upcoming_events, fn($a,$b) => strtotime($a['event_date'].' '.($a['event_time']??'00:00:00')) - strtotime($b['event_date'].' '.($b['event_time']??'00:00:00')));
usort($past_events, fn($a,$b) => strtotime($b['event_date'].' '.($b['event_time']??'00:00:00')) - strtotime($a['event_date'].' '.($a['event_time']??'00:00:00')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Events — CCS Alumni</title>
<link rel="icon" href="olfulogo.png" type="image/png">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root {
  --forest:#0d2e18;--pine:#133d23;--leaf:#1b5e35;--moss:#2d7a4f;--fern:#3d9966;
  --sage:#a8c9b0;--mist:#e8f2ec;--snow:#f5f9f6;--cream:#faf8f3;--white:#ffffff;
  --gold:#b8922a;--gold-lt:#e0b84a;--ink:#0c1a10;--charcoal:#2a3d30;
  --slate:#4a6355;--silver:#8aab96;--fog:#c8ddd2;
  --shadow:0 1px 3px rgba(13,46,24,.07),0 4px 16px rgba(13,46,24,.06);
  --shadow-h:0 4px 8px rgba(13,46,24,.1),0 12px 32px rgba(13,46,24,.12);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Outfit',system-ui,sans-serif;background:var(--cream);color:var(--ink)}
.page{max-width:1320px;margin:0 auto;padding:2rem 1.5rem 4rem}

/* Page header */
.pg-head{margin-bottom:2rem}
.pg-title{font-family:'DM Serif Display',serif;font-size:clamp(1.8rem,3.5vw,2.6rem);color:var(--forest)}
.pg-title em{font-style:italic;color:var(--moss)}
.gold-bar{height:3px;width:60px;background:linear-gradient(90deg,var(--leaf),var(--gold));border-radius:2px;margin:.5rem 0 .75rem}
.pg-sub{font-size:.9rem;color:var(--slate)}

/* Tab nav */
.tab-nav{display:flex;background:var(--white);border-radius:16px;padding:.4rem;box-shadow:var(--shadow);margin-bottom:2rem;gap:.3rem}
.tab-btn{flex:1;padding:.7rem 1rem;border:none;border-radius:12px;font-family:'Outfit',sans-serif;font-size:.86rem;font-weight:500;color:var(--slate);background:none;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:.45rem}
.tab-btn.active{background:var(--forest);color:#fff;box-shadow:0 2px 8px rgba(13,46,24,.2)}
.tab-btn i{font-size:.8rem}

/* Stats strip */
.stats-strip{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:2rem}
@media(max-width:700px){.stats-strip{grid-template-columns:repeat(2,1fr)}}
.stat-pill{background:var(--white);border-radius:14px;padding:1.25rem 1rem;box-shadow:var(--shadow);border-left:4px solid;display:flex;align-items:center;gap:.875rem}
.stat-pill.green{border-color:var(--leaf)}.stat-pill.blue{border-color:#2563eb}.stat-pill.purple{border-color:#7c3aed}.stat-pill.gold{border-color:var(--gold)}
.sp-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:.95rem;flex-shrink:0}
.stat-pill.green .sp-icon{background:var(--mist);color:var(--leaf)}
.stat-pill.blue .sp-icon{background:#e8f0fb;color:#2563eb}
.stat-pill.purple .sp-icon{background:#f0e8fb;color:#7c3aed}
.stat-pill.gold .sp-icon{background:#fdf5e0;color:var(--gold)}
.sp-num{font-family:'DM Serif Display',serif;font-size:1.6rem;color:var(--forest);line-height:1}
.sp-label{font-size:.72rem;font-weight:500;color:var(--slate);text-transform:uppercase;letter-spacing:.07em;margin-top:.15rem}

/* Events grid */
.events-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.5rem}
.ev-card{background:var(--white);border-radius:18px;overflow:hidden;box-shadow:var(--shadow);border:1px solid rgba(200,221,210,.5);transition:transform .25s,box-shadow .25s}
.ev-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-h)}
.ev-card.past{opacity:.78}
.ev-thumb{position:relative;height:180px}
.ev-thumb img{width:100%;height:100%;object-fit:cover}
.ev-placeholder{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:3rem;color:rgba(255,255,255,.4)}
.ev-placeholder.upcoming{background:linear-gradient(135deg,var(--leaf),var(--moss))}
.ev-placeholder.past{background:linear-gradient(135deg,#6b7280,#9ca3af)}
.ev-badge{position:absolute;top:.875rem;right:.875rem;font-size:.7rem;font-weight:600;padding:.3rem .75rem;border-radius:999px}
.ev-badge.Networking{background:#dbeafe;color:#1d4ed8}
.ev-badge.Workshop{background:#ede9fe;color:#6d28d9}
.ev-badge.Webinar{background:var(--mist);color:var(--leaf)}
.ev-badge.Reunion{background:#fef3c7;color:#92400e}
.ev-badge.General{background:#f3f4f6;color:#374151}

.ev-body{padding:1.25rem}
.ev-meta{display:flex;align-items:center;gap:.5rem;font-size:.76rem;color:var(--silver);margin-bottom:.75rem}
.ev-title{font-family:'DM Serif Display',serif;font-size:1.1rem;color:var(--forest);margin-bottom:.5rem;line-height:1.2}
.ev-desc{font-size:.82rem;color:var(--slate);line-height:1.6;margin-bottom:.875rem;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.ev-loc{display:flex;align-items:center;gap:.4rem;font-size:.78rem;color:var(--slate);margin-bottom:1rem}
.ev-foot{display:flex;align-items:center;justify-content:space-between;padding-top:.875rem;border-top:1px solid var(--mist)}
.ev-registered{font-size:.78rem;color:var(--silver)}
.ev-registered strong{color:var(--charcoal)}
.btn-registered{display:flex;align-items:center;gap:.35rem;font-size:.8rem;font-weight:600;color:var(--leaf);cursor:default}
.btn-view{font-size:.8rem;font-weight:600;color:var(--moss);background:none;border:1px solid var(--fog);border-radius:8px;padding:.4rem .875rem;cursor:pointer;transition:all .2s}
.btn-view:hover{background:var(--mist);border-color:var(--sage);color:var(--forest)}

/* Section heading */
.sec-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem}
.sec-title{font-family:'DM Serif Display',serif;font-size:1.4rem;color:var(--forest)}

/* Alerts */
.alert{display:flex;align-items:center;gap:.65rem;padding:.875rem 1.25rem;border-radius:12px;font-size:.85rem;margin-bottom:1.25rem;border-left:3px solid}
.alert-success{background:#f0faf3;color:var(--leaf);border-color:var(--leaf)}
.alert-error{background:#fdf3f2;color:#c0392b;border-color:#c0392b}

/* Empty state */
.empty{text-align:center;padding:4rem 2rem;color:var(--silver)}
.empty i{font-size:2.5rem;margin-bottom:1rem;display:block}
.empty p{font-size:.9rem}

/* Modal */
.modal-backdrop{position:fixed;inset:0;background:rgba(13,46,24,.5);z-index:500;display:none;align-items:flex-start;justify-content:center;padding:2rem 1rem;overflow-y:auto}
.modal-backdrop.open{display:flex}
.modal-box{background:var(--white);border-radius:20px;width:100%;max-width:700px;box-shadow:0 24px 80px rgba(13,46,24,.25);animation:min .25s ease;margin:auto}
@keyframes min{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.mh{display:flex;justify-content:space-between;align-items:flex-start;padding:1.5rem 1.75rem;border-bottom:1px solid var(--mist)}
.mh-title{font-family:'DM Serif Display',serif;font-size:1.3rem;color:var(--forest)}
.mh-close{background:none;border:none;cursor:pointer;color:var(--silver);font-size:1.1rem}
.mh-close:hover{color:var(--forest)}
.mb{padding:1.75rem}
.mb-img{width:100%;height:220px;object-fit:cover;border-radius:12px;margin-bottom:1.25rem}
.mb-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1.25rem}
.mb-row{display:flex;align-items:center;gap:.5rem;font-size:.85rem;color:var(--slate)}
.mb-row i{color:var(--moss);width:14px;flex-shrink:0}
.mb-label{font-weight:700;font-size:.75rem;text-transform:uppercase;letter-spacing:.1em;color:var(--charcoal);margin-bottom:.5rem;margin-top:1rem}
.mb-text{font-size:.88rem;color:var(--slate);line-height:1.75;white-space:pre-line}
.mf{padding:1rem 1.75rem;border-top:1px solid var(--mist);display:flex;justify-content:flex-end;gap:.75rem}
.btn-close{background:var(--snow);border:1px solid var(--fog);color:var(--charcoal);border-radius:10px;padding:.6rem 1.25rem;font-family:'Outfit',sans-serif;font-size:.86rem;cursor:pointer;transition:all .2s}
.btn-close:hover{background:var(--fog)}
.btn-register{background:var(--forest);color:#fff;border:none;border-radius:10px;padding:.6rem 1.5rem;font-family:'Outfit',sans-serif;font-size:.86rem;font-weight:600;cursor:pointer;transition:background .2s}
.btn-register:hover{background:var(--pine)}
.btn-register:disabled{background:#9ca3af;cursor:not-allowed}

/* Tab content */
.tab-content{display:none}.tab-content.active{display:block}
@keyframes fade-up{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.fade-up{animation:fade-up .4s ease both}
</style>
</head>
<body>
<?php include __DIR__ . '/al_header_universal.php'; ?>

<div class="page">
  <div class="pg-head fade-up">
    <div class="pg-title">Alumni <em>Events</em></div>
    <div class="gold-bar"></div>
    <p class="pg-sub">Connect with fellow graduates through events, workshops, and reunions.</p>
  </div>

  <!-- Tab Nav -->
  <div class="tab-nav fade-up" style="animation-delay:.06s">
    <button class="tab-btn active" data-tab="upcoming"><i class="fas fa-calendar-check"></i> Upcoming Events</button>
    <button class="tab-btn" data-tab="past"><i class="fas fa-history"></i> Past Events</button>
  </div>

  <!-- Alerts -->
  <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success fade-up"><i class="fas fa-check-circle"></i><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
  <?php endif; ?>
  <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-error fade-up"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
  <?php endif; ?>

  <!-- UPCOMING TAB -->
  <div class="tab-content active" id="tab-upcoming">
    <div class="stats-strip fade-up" style="animation-delay:.1s">
      <div class="stat-pill green"><div class="sp-icon"><i class="fas fa-calendar-check"></i></div><div><div class="sp-num"><?php echo count($upcoming_events); ?></div><div class="sp-label">Upcoming</div></div></div>
      <div class="stat-pill blue"><div class="sp-icon"><i class="fas fa-users"></i></div><div><div class="sp-num"><?php echo array_sum(array_column($upcoming_events,'registered_count')); ?></div><div class="sp-label">Registrations</div></div></div>
      <div class="stat-pill purple"><div class="sp-icon"><i class="fas fa-check-circle"></i></div><div><div class="sp-num"><?php echo count(array_filter($upcoming_events, fn($e) => $e['is_registered'])); ?></div><div class="sp-label">You Registered</div></div></div>
      <div class="stat-pill gold"><div class="sp-icon"><i class="fas fa-history"></i></div><div><div class="sp-num"><?php echo count($past_events); ?></div><div class="sp-label">Past Events</div></div></div>
    </div>

    <?php if (empty($upcoming_events)): ?>
      <div class="empty fade-up"><i class="fas fa-calendar"></i><p>No upcoming events at the moment. Check back soon!</p></div>
    <?php else: ?>
    <div class="sec-head fade-up" style="animation-delay:.14s"><div class="sec-title">What's Coming Up</div></div>
    <div class="events-grid fade-up" style="animation-delay:.18s">
      <?php foreach ($upcoming_events as $ev):
        try { $dt = new DateTime($ev['event_date'].' '.($ev['event_time']??'00:00:00'), new DateTimeZone('Asia/Manila')); $dFmt = $dt->format('M d, Y'); $tFmt = $dt->format('g:i A'); } catch(Exception $e) { $dFmt = $ev['event_date']; $tFmt = ''; }
        $img = $ev['banner_image'] ?? $ev['image'] ?? '';
        $imgPath = $img ? 'serve_event_image.php?img='.urlencode(basename($img)) : '';
        $evType = $ev['normalized_event_type'];
      ?>
      <div class="ev-card">
        <div class="ev-thumb">
          <?php if ($imgPath): ?><img src="<?php echo htmlspecialchars($imgPath); ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"><?php endif; ?>
          <div class="ev-placeholder upcoming" <?php echo $imgPath ? 'style="display:none"' : ''; ?>><i class="fas fa-calendar-alt"></i></div>
          <span class="ev-badge <?php echo htmlspecialchars($evType); ?>"><?php echo htmlspecialchars($evType); ?></span>
        </div>
        <div class="ev-body">
          <div class="ev-meta"><i class="far fa-calendar-alt"></i> <?php echo $dFmt; ?> <span>&middot;</span> <i class="far fa-clock"></i> <?php echo $tFmt; ?></div>
          <div class="ev-title"><?php echo htmlspecialchars($ev['title']); ?></div>
          <div class="ev-desc"><?php echo htmlspecialchars($ev['description'] ?? ''); ?></div>
          <div class="ev-loc"><i class="fas fa-map-marker-alt" style="color:var(--moss)"></i> <?php echo htmlspecialchars($ev['resolved_location'] ?: 'Location TBD'); ?></div>
          <div class="ev-foot">
            <div class="ev-registered"><strong><?php echo $ev['registered_count']; ?></strong> registered</div>
            <?php if ($ev['is_registered']): ?>
              <span class="btn-registered"><i class="fas fa-check-circle"></i> Registered</span>
            <?php else: ?>
              <button class="btn-view" onclick="openEventModal(<?php echo htmlspecialchars(json_encode($ev, JSON_HEX_APOS|JSON_HEX_QUOT)); ?>)">View Details</button>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- PAST TAB -->
  <div class="tab-content" id="tab-past">
    <div class="stats-strip fade-up">
      <div class="stat-pill green"><div class="sp-icon"><i class="fas fa-history"></i></div><div><div class="sp-num"><?php echo count($past_events); ?></div><div class="sp-label">Past Events</div></div></div>
      <div class="stat-pill blue"><div class="sp-icon"><i class="fas fa-users"></i></div><div><div class="sp-num"><?php echo array_sum(array_column($past_events,'registered_count')); ?></div><div class="sp-label">Total Attendees</div></div></div>
      <div class="stat-pill purple"><div class="sp-icon"><i class="fas fa-check-circle"></i></div><div><div class="sp-num"><?php echo count(array_filter($past_events, fn($e) => $e['is_registered'])); ?></div><div class="sp-label">You Attended</div></div></div>
    </div>

    <?php if (empty($past_events)): ?>
      <div class="empty"><i class="fas fa-history"></i><p>No past events yet.</p></div>
    <?php else: ?>
    <div class="events-grid">
      <?php foreach ($past_events as $ev):
        try { $dt = new DateTime($ev['event_date'].' '.($ev['event_time']??'00:00:00'), new DateTimeZone('Asia/Manila')); $dFmt = $dt->format('M d, Y'); $tFmt = $dt->format('g:i A'); } catch(Exception $e) { $dFmt = $ev['event_date']; $tFmt = ''; }
        $img = $ev['banner_image'] ?? $ev['image'] ?? '';
        $imgPath = $img ? 'serve_event_image.php?img='.urlencode(basename($img)) : '';
        $evType = $ev['normalized_event_type'];
      ?>
      <div class="ev-card past">
        <div class="ev-thumb">
          <?php if ($imgPath): ?><img src="<?php echo htmlspecialchars($imgPath); ?>" style="opacity:.75" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'"><?php endif; ?>
          <div class="ev-placeholder past" <?php echo $imgPath ? 'style="display:none"' : ''; ?>><i class="fas fa-calendar-alt"></i></div>
          <span class="ev-badge <?php echo htmlspecialchars($evType); ?>"><?php echo htmlspecialchars($evType); ?></span>
        </div>
        <div class="ev-body">
          <div class="ev-meta"><i class="far fa-calendar-alt"></i> <?php echo $dFmt; ?> <span>&middot;</span> <i class="far fa-clock"></i> <?php echo $tFmt; ?></div>
          <div class="ev-title"><?php echo htmlspecialchars($ev['title']); ?></div>
          <div class="ev-desc"><?php echo htmlspecialchars($ev['description'] ?? ''); ?></div>
          <div class="ev-loc"><i class="fas fa-map-marker-alt" style="color:var(--moss)"></i> <?php echo htmlspecialchars($ev['resolved_location'] ?: 'Location TBD'); ?></div>
          <div class="ev-foot">
            <?php if ($ev['is_registered']): ?>
              <span class="btn-registered" style="font-size:.78rem;color:var(--leaf)"><i class="fas fa-check-circle"></i> You attended</span>
            <?php else: ?><span></span><?php endif; ?>
            <button class="btn-view" onclick="openEventModal(<?php echo htmlspecialchars(json_encode($ev, JSON_HEX_APOS|JSON_HEX_QUOT)); ?>, true)">View Details</button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Event Modal -->
<div class="modal-backdrop" id="evModal">
  <div class="modal-box">
    <div class="mh">
      <div class="mh-title" id="evTitle"></div>
      <button class="mh-close" onclick="closeEvModal()"><i class="fas fa-times"></i></button>
    </div>
    <div class="mb">
      <div id="evImgWrap" style="display:none"><img id="evImg" class="mb-img"></div>
      <div class="mb-grid">
        <div class="mb-row"><i class="far fa-calendar-alt"></i><span id="evDate"></span></div>
        <div class="mb-row"><i class="far fa-clock"></i><span id="evTime"></span></div>
        <div class="mb-row"><i class="fas fa-map-marker-alt"></i><span id="evLoc"></span></div>
        <div class="mb-row"><i class="fas fa-tag"></i><span id="evType"></span></div>
      </div>
      <div class="mb-label">Description</div>
      <div class="mb-text" id="evDesc"></div>
      <div id="evAddlWrap" style="display:none"><div class="mb-label">Additional Info</div><div class="mb-text" id="evAddl"></div></div>
    </div>
    <div class="mf">
      <button class="btn-close" onclick="closeEvModal()">Close</button>
      <form method="POST" id="evRegForm" style="display:inline">
        <input type="hidden" name="event_id" id="evId">
        <button type="submit" name="register_event" id="evRegBtn" class="btn-register">Register Now</button>
      </form>
    </div>
  </div>
</div>

<script>
// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    this.classList.add('active');
    document.getElementById('tab-' + this.dataset.tab).classList.add('active');
  });
});

function openEventModal(ev, isPast) {
  document.getElementById('evTitle').textContent = ev.title || '';
  const imgWrap = document.getElementById('evImgWrap');
  const img = ev.banner_image || ev.image || '';
  if (img) {
    document.getElementById('evImg').src = 'serve_event_image.php?img=' + encodeURIComponent(img.split('/').pop());
    imgWrap.style.display = 'block';
  } else { imgWrap.style.display = 'none'; }
  try {
    const dt = new Date((ev.event_date + ' ' + (ev.event_time || '00:00:00')).replace(' ', 'T'));
    document.getElementById('evDate').textContent = dt.toLocaleDateString('en-US',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
    document.getElementById('evTime').textContent = dt.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit',hour12:true});
  } catch(e) { document.getElementById('evDate').textContent = ev.event_date || ''; document.getElementById('evTime').textContent = ev.event_time || ''; }
  document.getElementById('evLoc').textContent = ev.resolved_location || ev.location || 'TBD';
  document.getElementById('evType').textContent = ev.normalized_event_type || 'General';
  let desc = (ev.description || '').replace(/\\n/g,'\n').replace(/\\r\\n/g,'\n');
  document.getElementById('evDesc').textContent = desc;
  const addl = ev.additional_info || '';
  if (addl) { document.getElementById('evAddl').textContent = addl.replace(/\\n/g,'\n'); document.getElementById('evAddlWrap').style.display='block'; } else { document.getElementById('evAddlWrap').style.display='none'; }
  document.getElementById('evId').value = ev.id || '';
  const regBtn = document.getElementById('evRegBtn');
  const regForm = document.getElementById('evRegForm');
  if (isPast) { regForm.style.display = 'none'; }
  else {
    regForm.style.display = 'inline';
    if (ev.is_registered && parseInt(ev.is_registered) > 0) { regBtn.textContent = 'Already Registered'; regBtn.disabled = true; }
    else { regBtn.textContent = 'Register Now'; regBtn.disabled = false; }
  }
  document.getElementById('evModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeEvModal() { document.getElementById('evModal').classList.remove('open'); document.body.style.overflow = ''; }
document.getElementById('evModal').addEventListener('click', e => { if (e.target === document.getElementById('evModal')) closeEvModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeEvModal(); });
</script>
<?php if (file_exists(__DIR__ . '/al_footer_universal.php')) include 'al_footer_universal.php'; ?>
</body>
</html>
