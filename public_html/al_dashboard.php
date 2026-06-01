<?php
session_start();
require_once 'db_config.php';
alumni_otp_gate_after_session();
$conn = getDBConnection();

if (!function_exists('dash_stmt_result')) {
    function dash_stmt_result($stmt)
    {
        if (function_exists('olfu_stmt_get_result')) {
            return olfu_stmt_get_result($stmt);
        }
        if (method_exists($stmt, 'get_result')) {
            return @$stmt->get_result();
        }
        return false;
    }
}

if (!isset($_SESSION['user_id'])) {
    header('Location: al_homepage.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM itcp WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) { $user = []; } else {
    $stmt->bind_param("i", $user_id); $stmt->execute();
    $result = dash_stmt_result($stmt);
    $user = $result ? ($result->fetch_assoc() ?: []) : [];
    $stmt->close();
}
if (is_array($user)) { $user = array_change_key_case($user, CASE_LOWER); }

function cleanDescription($text) {
    if (empty($text)) return '';
    $text = str_replace(['\\r\\n', '\\n', '\\r'], "\n", $text);
    return stripslashes($text);
}

$sql = "SELECT * FROM announcements WHERE status = 'published' ORDER BY created_at DESC LIMIT 5";
$announcements_result = false;
try { $announcements_result = $conn->query($sql); } catch (Throwable $e) { error_log('Dashboard announcements query failed: ' . $e->getMessage()); }
$announcements = [];
if ($announcements_result) {
    while ($row = $announcements_result->fetch_assoc()) {
        $row['content'] = cleanDescription($row['content']);
        $announcements[] = $row;
    }
}

$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
if ($stmt === false) { $notifications = []; } else {
    $stmt->bind_param("i", $user_id); $stmt->execute();
    $result = dash_stmt_result($stmt);
    $notifications = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}
$notification_count = count(array_filter($notifications, function($n) { return !$n['is_read']; }));

$sql = "SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 5";
$upcoming_events_result = false;
try { $upcoming_events_result = $conn->query($sql); } catch (Throwable $e) { error_log('Dashboard upcoming events query failed: ' . $e->getMessage()); }
$upcoming_events = [];
if ($upcoming_events_result) { $upcoming_events = $upcoming_events_result->fetch_all(MYSQLI_ASSOC); }
$upcoming_events_count = count($upcoming_events);

$sql = "SELECT COUNT(*) as count FROM job_applications WHERE alumni_id = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) { $job_applications_count = 0; } else {
    $stmt->bind_param("i", $user_id); $stmt->execute();
    $result = dash_stmt_result($stmt);
    $row = $result ? $result->fetch_assoc() : null;
    $job_applications_count = $row ? (int)$row['count'] : 0;
    $stmt->close();
}

$sql = "SELECT * FROM alumni_skills WHERE alumni_id = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) { $user_skills = []; } else {
    $stmt->bind_param("i", $user_id); $stmt->execute();
    $result = dash_stmt_result($stmt);
    $user_skills = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
}

$profile_fields = ['photo','firstname','lastname','email','program','year_graduated','position','company','bio','address','personal_contact'];
$total_fields = count($profile_fields);
$filled_fields = 0;
foreach ($profile_fields as $f) {
    $v = isset($user[$f]) ? $user[$f] : null;
    if ($v !== null && trim((string)$v) !== '') $filled_fields++;
}
$profile_completion = $total_fields > 0 ? round(($filled_fields / $total_fields) * 100) : 0;

$recent_jobs = [];
// Prefer the Career Center source table (`jobs`) so dashboard mirrors posted career listings.
try {
    $sql = "SELECT id, title, company, location, posted_date AS created_at FROM jobs WHERE status = 'active' ORDER BY posted_date DESC LIMIT 3";
    $recent_jobs_result = $conn->query($sql);
    if ($recent_jobs_result) {
        while ($row = $recent_jobs_result->fetch_assoc()) {
            $recent_jobs[] = $row;
        }
    }
} catch (Throwable $e) {
    error_log('Dashboard recent jobs query (jobs) failed: ' . $e->getMessage());
}
// Backward-compatible fallback if legacy `job_postings` is still used.
if (empty($recent_jobs)) {
    try {
        $sql = "SELECT id, title, company, location, created_at FROM job_postings WHERE status = 'active' ORDER BY created_at DESC LIMIT 3";
        $recent_jobs_result = $conn->query($sql);
        if ($recent_jobs_result) {
            while ($row = $recent_jobs_result->fetch_assoc()) {
                $recent_jobs[] = $row;
            }
        }
    } catch (Throwable $e) {
        error_log('Dashboard recent jobs query (job_postings) failed: ' . $e->getMessage());
    }
}

$recent_activities = [];
$sql = "SELECT 'event' as type, title as activity_title, event_date as activity_date, 'calendar' as icon, 'green' as color FROM events WHERE event_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY) ORDER BY event_date ASC LIMIT 2";
$events_result = false;
try { $events_result = $conn->query($sql); } catch (Throwable $e) { error_log('Dashboard recent activity events query failed: ' . $e->getMessage()); }
if ($events_result) { while ($row = $events_result->fetch_assoc()) $recent_activities[] = $row; }

$sql = "SELECT 'job_application' as type, CONCAT('Applied to ', jp.title) as activity_title, ja.created_at as activity_date, 'briefcase' as icon, 'blue' as color FROM job_applications ja JOIN job_postings jp ON ja.job_id = jp.id WHERE ja.alumni_id = ? ORDER BY ja.created_at DESC LIMIT 2";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = dash_stmt_result($stmt);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $recent_activities[] = $row;
        }
    }
    $stmt->close();
}

usort($recent_activities, function($a, $b) { return strtotime($b['activity_date']) - strtotime($a['activity_date']); });
$recent_activities = array_slice($recent_activities, 0, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Dashboard — CCS Alumni</title>
<link rel="icon" href="olfulogo.png" type="image/png" />
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
:root {
  --forest:  #0d2e18; --pine:  #133d23; --leaf:  #1b5e35;
  --moss:    #2d7a4f; --fern:  #3d9966; --sage:  #a8c9b0;
  --mist:    #e8f2ec; --snow:  #f5f9f6; --cream: #faf8f3;
  --white:   #ffffff; --gold:  #b8922a; --gold-lt:#e0b84a;
  --ink:     #0c1a10; --charcoal:#2a3d30; --slate: #4a6355;
  --silver:  #8aab96; --fog:   #c8ddd2;
  --card-shadow: 0 1px 3px rgba(13,46,24,.07), 0 4px 16px rgba(13,46,24,.06);
  --card-shadow-hover: 0 4px 8px rgba(13,46,24,.1), 0 12px 32px rgba(13,46,24,.12);
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Outfit', system-ui, sans-serif; background: var(--cream); color: var(--ink); }

/* ── LAYOUT ── */
.page-wrap { max-width: 1320px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
@media(max-width:768px){ .page-wrap { padding: 1.25rem 1rem 3rem; } }

/* ── WELCOME BANNER ── */
.welcome-banner {
  background: var(--forest);
  border-radius: 20px;
  padding: 2.5rem 3rem;
  margin-bottom: 2rem;
  position: relative; overflow: hidden;
}
.welcome-banner::before {
  content: ''; position: absolute; inset: 0;
  background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
}
.welcome-banner::after {
  content: ''; position: absolute; top: -60px; right: -60px;
  width: 280px; height: 280px; border-radius: 50%;
  background: radial-gradient(circle, rgba(184,146,42,.18) 0%, transparent 70%);
}
.welcome-title {
  font-family: 'DM Serif Display', serif;
  font-size: clamp(1.8rem, 4vw, 2.8rem);
  color: #fff; line-height: 1.1; position: relative; z-index: 1;
}
.welcome-title em { font-style: italic; color: var(--gold-lt); }
.welcome-sub {
  font-size: .9rem; font-weight: 300; color: rgba(255,255,255,.6);
  margin-top: .5rem; position: relative; z-index: 1;
}
.welcome-meta {
  display: flex; gap: 1.5rem; margin-top: 1.75rem;
  position: relative; z-index: 1;
}
.welcome-chip {
  display: flex; align-items: center; gap: .5rem;
  font-size: .8rem; font-weight: 500; color: rgba(255,255,255,.7);
  background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12);
  padding: .4rem .875rem; border-radius: 999px;
}
.welcome-chip i { color: var(--gold-lt); font-size: .75rem; }
@media(max-width:600px){
  .welcome-banner { padding: 1.75rem 1.5rem; }
  .welcome-meta { flex-wrap: wrap; gap: .75rem; }
}

/* ── STAT CARDS ── */
.stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem; }
@media(max-width:700px){ .stats-grid { grid-template-columns: 1fr; } }
.stat-card {
  background: var(--white); border-radius: 16px;
  padding: 1.5rem; box-shadow: var(--card-shadow);
  border: 1px solid rgba(200,221,210,.6);
  transition: transform .2s, box-shadow .2s;
  display: flex; align-items: center; gap: 1.25rem;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: var(--card-shadow-hover); }
.stat-icon {
  width: 52px; height: 52px; border-radius: 14px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center; font-size: 1.3rem;
}
.stat-icon.green { background: var(--mist); color: var(--leaf); }
.stat-icon.gold  { background: #fdf5e0; color: var(--gold); }
.stat-icon.blue  { background: #e8f0fb; color: #2563eb; }
.stat-num { font-family: 'DM Serif Display', serif; font-size: 2rem; color: var(--forest); line-height: 1; }
.stat-label { font-size: .78rem; font-weight: 500; color: var(--slate); text-transform: uppercase; letter-spacing: .07em; margin-top: .2rem; }

/* ── MAIN GRID ── */
.main-grid { display: grid; grid-template-columns: 1fr 360px; gap: 1.5rem; align-items: start; }
@media(max-width:1100px){ .main-grid { grid-template-columns: 1fr; } }

/* ── CARDS ── */
.card {
  background: var(--white); border-radius: 18px;
  box-shadow: var(--card-shadow); border: 1px solid rgba(200,221,210,.6);
  overflow: hidden; margin-bottom: 1.5rem;
}
.card-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid var(--mist);
}
.card-title {
  font-family: 'DM Serif Display', serif; font-size: 1.15rem; color: var(--forest);
}
.card-link { font-size: .8rem; font-weight: 600; color: var(--moss); text-decoration: none; transition: color .2s; }
.card-link:hover { color: var(--forest); }
.card-body { padding: 1.5rem; }

/* ── ANNOUNCEMENT CARDS ── */
.announce-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; }
.announce-card {
  background: var(--white); border-radius: 16px; overflow: hidden;
  box-shadow: var(--card-shadow); border: 1px solid rgba(200,221,210,.5);
  transition: transform .2s, box-shadow .2s;
}
.announce-card:hover { transform: translateY(-3px); box-shadow: var(--card-shadow-hover); }
.announce-img { width: 100%; height: 160px; object-fit: cover; }
.announce-placeholder {
  width: 100%; height: 160px; background: linear-gradient(135deg, var(--leaf), var(--moss));
  display: flex; align-items: center; justify-content: center;
}
.announce-placeholder i { font-size: 2.5rem; color: rgba(255,255,255,.4); }
.announce-body { padding: 1.25rem; }
.announce-title { font-weight: 600; color: var(--forest); margin-bottom: .5rem; font-size: .95rem; }
.announce-excerpt { font-size: .82rem; color: var(--slate); line-height: 1.6; margin-bottom: 1rem; }
.announce-footer { display: flex; align-items: center; justify-content: space-between; }
.announce-date { font-size: .75rem; color: var(--silver); }
.announce-btn {
  font-size: .78rem; font-weight: 600; color: var(--moss);
  background: none; border: none; cursor: pointer; transition: color .2s;
}
.announce-btn:hover { color: var(--forest); }

/* ── SECTION LABEL ── */
.section-label {
  display: flex; align-items: center; gap: .75rem;
  font-size: .65rem; font-weight: 700; letter-spacing: .2em; text-transform: uppercase;
  color: var(--moss); margin-bottom: 1.25rem;
}
.section-label::before { content: ''; width: 20px; height: 2px; background: var(--gold); border-radius: 1px; }

/* ── EVENT / JOB ITEMS ── */
.list-item {
  display: flex; align-items: flex-start; gap: 1rem;
  padding: 1rem; border-radius: 12px; border: 1px solid var(--fog);
  margin-bottom: .75rem; transition: background .2s, border-color .2s;
}
.list-item:hover { background: var(--snow); border-color: var(--sage); }
.list-item:last-child { margin-bottom: 0; }
.list-icon {
  width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center; font-size: .95rem;
}
.list-icon.green { background: var(--mist); color: var(--leaf); }
.list-icon.blue  { background: #e8f0fb; color: #2563eb; }
.list-item-title { font-weight: 600; font-size: .88rem; color: var(--forest); margin-bottom: .25rem; }
.list-item-sub   { font-size: .78rem; color: var(--slate); line-height: 1.5; }
.list-item-action { font-size: .78rem; font-weight: 600; color: var(--moss); text-decoration: none; white-space: nowrap; margin-left: auto; transition: color .2s; }
.list-item-action:hover { color: var(--forest); }

/* ── PROFILE SIDEBAR ── */
.profile-avatar {
  width: 64px; height: 64px; border-radius: 50%; object-fit: cover;
  border: 3px solid var(--mist); flex-shrink: 0;
}
.profile-initials {
  width: 64px; height: 64px; border-radius: 50%; flex-shrink: 0;
  background: linear-gradient(135deg, var(--leaf), var(--moss));
  display: flex; align-items: center; justify-content: center;
  font-family: 'DM Serif Display', serif; font-size: 1.4rem; color: #fff;
}
.profile-name { font-family: 'DM Serif Display', serif; font-size: 1.1rem; color: var(--forest); }
.profile-role { font-size: .8rem; color: var(--slate); margin-top: .15rem; }
.profile-company { font-size: .8rem; color: var(--slate); }
.meta-row { display: flex; align-items: center; gap: .625rem; font-size: .8rem; color: var(--charcoal); margin-bottom: .5rem; }
.meta-row i { color: var(--silver); font-size: .75rem; width: 14px; flex-shrink: 0; }

/* ── PROGRESS BAR ── */
.progress-wrap { margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px solid var(--mist); }
.progress-header { display: flex; justify-content: space-between; font-size: .8rem; margin-bottom: .5rem; }
.progress-label { font-weight: 600; color: var(--charcoal); }
.progress-pct { color: var(--slate); }
.progress-track { height: 6px; background: var(--fog); border-radius: 99px; overflow: hidden; }
.progress-fill { height: 100%; border-radius: 99px; background: linear-gradient(90deg, var(--leaf), var(--gold-lt)); transition: width .6s ease; }
.progress-hint { font-size: .72rem; color: var(--silver); margin-top: .4rem; }

/* ── QUICK ACTIONS ── */
.qa-btn {
  display: flex; align-items: center; gap: .875rem;
  padding: .875rem 1rem; border-radius: 12px;
  background: var(--snow); border: 1px solid var(--fog);
  text-decoration: none; color: var(--charcoal);
  font-size: .86rem; font-weight: 500;
  transition: all .2s; margin-bottom: .625rem;
}
.qa-btn:last-child { margin-bottom: 0; }
.qa-btn:hover { background: var(--mist); border-color: var(--sage); color: var(--forest); }
.qa-icon {
  width: 36px; height: 36px; border-radius: 9px;
  display: flex; align-items: center; justify-content: center;
  background: var(--white); color: var(--leaf); font-size: .9rem;
  flex-shrink: 0;
}

/* ── ACTIVITY ITEM ── */
.act-item {
  display: flex; align-items: flex-start; gap: .875rem;
  padding: .875rem; border-radius: 12px; border: 1px solid var(--fog);
  margin-bottom: .625rem; transition: background .15s;
}
.act-item:hover { background: var(--snow); }
.act-dot {
  width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center; font-size: .8rem;
}
.act-dot.green { background: var(--mist); color: var(--leaf); }
.act-dot.blue  { background: #e8f0fb; color: #2563eb; }
.act-dot.purple{ background: #f0e8fb; color: #7c3aed; }
.act-title { font-size: .84rem; font-weight: 600; color: var(--forest); }
.act-time  { font-size: .75rem; color: var(--silver); margin-top: .15rem; }
.act-badge {
  margin-left: auto; font-size: .68rem; font-weight: 600;
  padding: .2rem .625rem; border-radius: 999px; white-space: nowrap;
}
.act-badge.green { background: var(--mist); color: var(--leaf); }
.act-badge.blue  { background: #e8f0fb; color: #1d4ed8; }
.act-badge.purple{ background: #f0e8fb; color: #6d28d9; }

/* ── SKILL BARS ── */
.skill-row { margin-bottom: .875rem; }
.skill-row:last-child { margin-bottom: 0; }
.skill-meta { display: flex; justify-content: space-between; font-size: .8rem; margin-bottom: .35rem; }
.skill-name { font-weight: 600; color: var(--charcoal); }
.skill-pct { color: var(--slate); }
.skill-track { height: 6px; background: var(--fog); border-radius: 99px; overflow: hidden; }
.skill-fill { height: 100%; border-radius: 99px; background: linear-gradient(90deg, var(--leaf), var(--fern)); }

/* ── MODAL ── */
.modal-backdrop {
  position: fixed; inset: 0; background: rgba(13,46,24,.45); z-index: 500;
  display: none; align-items: center; justify-content: center; padding: 1rem;
}
.modal-backdrop.open { display: flex; }
.modal-box {
  background: var(--white); border-radius: 20px; width: 100%; max-width: 680px;
  max-height: 90vh; overflow-y: auto;
  box-shadow: 0 24px 80px rgba(13,46,24,.25);
  animation: modal-in .25s ease;
}
@keyframes modal-in { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }
.modal-head {
  display: flex; align-items: flex-start; justify-content: space-between;
  padding: 1.5rem 1.75rem; border-bottom: 1px solid var(--mist);
}
.modal-title { font-family: 'DM Serif Display', serif; font-size: 1.3rem; color: var(--forest); }
.modal-close { background: none; border: none; cursor: pointer; color: var(--silver); font-size: 1.2rem; transition: color .2s; }
.modal-close:hover { color: var(--forest); }
.modal-body { padding: 1.75rem; }
.modal-img { width: 100%; border-radius: 12px; margin-bottom: 1.25rem; object-fit: cover; }
.modal-text { font-size: .9rem; color: var(--slate); line-height: 1.8; white-space: pre-line; }
.modal-date { font-size: .8rem; color: var(--silver); display: flex; align-items: center; gap: .4rem; margin-top: 1.25rem; }
.modal-foot { padding: 1rem 1.75rem; border-top: 1px solid var(--mist); display: flex; justify-content: flex-end; }
.btn-primary {
  background: var(--forest); color: var(--white); border: none; border-radius: 10px;
  padding: .625rem 1.5rem; font-family: 'Outfit', sans-serif; font-size: .88rem;
  font-weight: 600; cursor: pointer; transition: background .2s;
}
.btn-primary:hover { background: var(--pine); }

/* ── EMPTY STATE ── */
.empty-state { text-align: center; padding: 2.5rem 1rem; }
.empty-icon { font-size: 2rem; color: var(--fog); margin-bottom: .75rem; }
.empty-text { font-size: .88rem; color: var(--silver); }

/* ── ANIMATIONS ── */
@keyframes fade-up { from { opacity:0; transform:translateY(14px); } to { opacity:1; transform:translateY(0); } }
.fade-up { animation: fade-up .45s ease both; }
</style>
</head>
<body>
<?php include __DIR__ . '/al_header_universal.php'; ?>

<div class="page-wrap">

  <!-- Welcome Banner -->
  <div class="welcome-banner fade-up">
    <div class="welcome-title">
      Good <?php echo (date('H') < 12) ? 'morning' : ((date('H') < 18) ? 'afternoon' : 'evening') ?>,
      <em><?php echo htmlspecialchars($user['firstname'] ?? 'Alumni'); ?></em> 👋
    </div>
    <p class="welcome-sub">Here's what's happening in your alumni community today.</p>
    <div class="welcome-meta">
      <span class="welcome-chip"><i class="fas fa-graduation-cap"></i> Class of <?php echo htmlspecialchars($user['year_graduated'] ?? '—'); ?></span>
      <span class="welcome-chip"><i class="fas fa-university"></i> <?php echo htmlspecialchars($user['program'] ?? '—'); ?></span>
      <?php if (!empty($user['company'])): ?>
      <span class="welcome-chip"><i class="fas fa-building"></i> <?php echo htmlspecialchars($user['company']); ?></span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Stat Cards -->
  <div class="stats-grid fade-up" style="animation-delay:.08s">
    <div class="stat-card">
      <div class="stat-icon green"><i class="fas fa-calendar-check"></i></div>
      <div>
        <div class="stat-num"><?php echo $upcoming_events_count; ?></div>
        <div class="stat-label">Upcoming Events</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon blue"><i class="fas fa-briefcase"></i></div>
      <div>
        <div class="stat-num"><?php echo $job_applications_count; ?></div>
        <div class="stat-label">Job Applications</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon gold"><i class="fas fa-bell"></i></div>
      <div>
        <div class="stat-num"><?php echo $notification_count; ?></div>
        <div class="stat-label">Notifications</div>
      </div>
    </div>
  </div>

  <!-- Announcements -->
  <div class="fade-up" style="animation-delay:.12s">
    <div class="section-label">Latest Announcements</div>
    <?php if (empty($announcements)): ?>
      <div class="card"><div class="card-body empty-state"><div class="empty-icon"><i class="fas fa-bullhorn"></i></div><p class="empty-text">No announcements at the moment.</p></div></div>
    <?php else: ?>
    <div class="announce-grid">
      <?php foreach ($announcements as $ann): ?>
      <div class="announce-card">
        <?php if (!empty($ann['image'])): $fn = basename($ann['image']); ?>
          <img src="serve_announcement_image.php?img=<?php echo urlencode($fn); ?>" class="announce-img" onerror="this.style.display='none'">
        <?php else: ?>
          <div class="announce-placeholder"><i class="fas fa-bullhorn"></i></div>
        <?php endif; ?>
        <div class="announce-body">
          <div class="announce-title"><?php echo htmlspecialchars($ann['title']); ?></div>
          <div class="announce-excerpt"><?php echo nl2br(htmlspecialchars(substr($ann['content'], 0, 130))); ?><?php if (strlen($ann['content']) > 130) echo '…'; ?></div>
          <div class="announce-footer">
            <span class="announce-date"><i class="far fa-clock" style="margin-right:.3rem"></i><?php echo date('M d, Y', strtotime($ann['created_at'])); ?></span>
            <button class="announce-btn" onclick="showAnnouncement(<?php echo $ann['id']; ?>)">Read More →</button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Main Grid -->
  <div class="main-grid fade-up" style="animation-delay:.16s">
    <!-- Left Column -->
    <div>
      <!-- Skills -->
      <?php if (!empty($user_skills)): ?>
      <div class="card">
        <div class="card-header">
          <span class="card-title">Skills & Proficiency</span>
          <a href="al_mycareer.php" class="card-link">Manage</a>
        </div>
        <div class="card-body">
          <?php foreach ($user_skills as $sk): ?>
          <div class="skill-row">
            <div class="skill-meta">
              <span class="skill-name"><?php echo htmlspecialchars($sk['skill_name']); ?></span>
              <span class="skill-pct"><?php echo $sk['proficiency']; ?>%</span>
            </div>
            <div class="skill-track"><div class="skill-fill" style="width:<?php echo $sk['proficiency']; ?>%"></div></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Upcoming Events -->
      <div class="card">
        <div class="card-header">
          <span class="card-title">Upcoming Events</span>
          <a href="al_events.php" class="card-link">See All →</a>
        </div>
        <div class="card-body">
          <?php if (empty($upcoming_events)): ?>
            <div class="empty-state"><div class="empty-icon"><i class="fas fa-calendar"></i></div><p class="empty-text">No upcoming events. Check back soon.</p></div>
          <?php else: foreach ($upcoming_events as $ev): ?>
          <div class="list-item">
            <div class="list-icon green"><i class="fas fa-calendar"></i></div>
            <div style="flex:1; min-width:0;">
              <div class="list-item-title"><?php echo htmlspecialchars($ev['title']); ?></div>
              <div class="list-item-sub"><?php echo date('M d, Y', strtotime($ev['event_date'])); ?> &middot; <?php echo htmlspecialchars(substr($ev['description'] ?? '', 0, 80)); ?>…</div>
            </div>
            <a href="al_events.php" class="list-item-action">View</a>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <!-- Recent Jobs -->
      <div class="card">
        <div class="card-header">
          <span class="card-title">Recent Job Opportunities</span>
          <a href="al_career.php" class="card-link">See All →</a>
        </div>
        <div class="card-body">
          <?php if (empty($recent_jobs)): ?>
            <div class="empty-state"><div class="empty-icon"><i class="fas fa-briefcase"></i></div><p class="empty-text">No jobs posted yet.</p></div>
          <?php else: foreach ($recent_jobs as $job): ?>
          <div class="list-item">
            <div class="list-icon blue"><i class="fas fa-briefcase"></i></div>
            <div style="flex:1; min-width:0;">
              <div class="list-item-title"><?php echo htmlspecialchars($job['title']); ?></div>
              <div class="list-item-sub">
                <?php echo htmlspecialchars($job['company'] ?? ''); ?>
                <?php if (!empty($job['location'])): ?> &middot; <i class="fas fa-map-marker-alt" style="font-size:.7rem"></i> <?php echo htmlspecialchars($job['location']); ?><?php endif; ?>
              </div>
            </div>
            <a href="al_career.php" class="list-item-action">Apply</a>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>

    <!-- Right Sidebar -->
    <div>
      <!-- Profile Card -->
      <div class="card">
        <div class="card-header">
          <span class="card-title">Your Profile</span>
          <div style="display:flex;gap:.75rem">
            <a href="al_profile.php" class="card-link">View</a>
            <a href="al_profileupdate.php" class="card-link">Edit</a>
          </div>
        </div>
        <div class="card-body">
          <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem">
            <?php $photo = trim((string)($user['photo'] ?? ''));
            if ($photo !== ''): $psrc = 'serve_profile_image.php?img=' . rawurlencode(basename($photo)); ?>
              <img src="<?php echo htmlspecialchars($psrc); ?>" class="profile-avatar" onerror="this.style.display='none'">
            <?php else:
              $initials = strtoupper(substr($user['firstname'] ?? '?', 0, 1) . substr($user['lastname'] ?? '', 0, 1)); ?>
              <div class="profile-initials"><?php echo $initials; ?></div>
            <?php endif; ?>
            <div>
              <div class="profile-name"><?php echo htmlspecialchars(trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? '')) ?: '—'); ?></div>
              <div class="profile-role"><?php echo htmlspecialchars($user['position'] ?? '—'); ?></div>
              <div class="profile-company"><?php echo htmlspecialchars($user['company'] ?? ''); ?></div>
            </div>
          </div>
          <div class="meta-row"><i class="fas fa-graduation-cap"></i> Class of <?php echo htmlspecialchars($user['year_graduated'] ?? '—'); ?></div>
          <div class="meta-row"><i class="fas fa-university"></i> <?php echo htmlspecialchars($user['program'] ?? '—'); ?></div>
          <div class="meta-row"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email'] ?? '—'); ?></div>
          <div class="progress-wrap">
            <div class="progress-header">
              <span class="progress-label">Profile Completion</span>
              <span class="progress-pct"><?php echo $profile_completion; ?>%</span>
            </div>
            <div class="progress-track"><div class="progress-fill" style="width:<?php echo $profile_completion; ?>%"></div></div>
            <div class="progress-hint">Complete your profile to boost visibility</div>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="card">
        <div class="card-header"><span class="card-title">Quick Actions</span></div>
        <div class="card-body">
          <a href="al_directory.php" class="qa-btn"><div class="qa-icon"><i class="fas fa-users"></i></div> Browse Alumni Directory</a>
          <a href="al_events.php" class="qa-btn"><div class="qa-icon"><i class="fas fa-calendar-alt"></i></div> Find Upcoming Events</a>
          <a href="al_career.php" class="qa-btn"><div class="qa-icon"><i class="fas fa-briefcase"></i></div> Post a Job Opening</a>
          <a href="al_messages.php" class="qa-btn"><div class="qa-icon"><i class="fas fa-paper-plane"></i></div> Send a Message</a>
        </div>
      </div>

      <!-- Recent Activities -->
      <div class="card">
        <div class="card-header"><span class="card-title">Recent Activity</span></div>
        <div class="card-body">
          <?php if (empty($recent_activities)): ?>
            <div class="empty-state"><div class="empty-icon"><i class="fas fa-history"></i></div><p class="empty-text">Your activities will appear here.</p></div>
          <?php else: foreach ($recent_activities as $act):
            $date = new DateTime($act['activity_date']);
            $now = new DateTime(); $diff = $now->diff($date);
            if ($diff->days == 0) $when = 'Today';
            elseif ($diff->days == 1) $when = 'Yesterday';
            elseif ($diff->days < 7) $when = $diff->days . ' days ago';
            else $when = $date->format('M d, Y');
          ?>
          <div class="act-item">
            <div class="act-dot <?php echo $act['color']; ?>"><i class="fas fa-<?php echo htmlspecialchars($act['icon']); ?>"></i></div>
            <div style="flex:1;min-width:0;">
              <div class="act-title"><?php echo htmlspecialchars($act['activity_title']); ?></div>
              <div class="act-time"><?php echo $when; ?></div>
            </div>
            <?php $badge = ['event'=>['label'=>'Event','class'=>'green'],'job_application'=>['label'=>'Applied','class'=>'blue'],'profile_update'=>['label'=>'Profile','class'=>'purple']]; $b = $badge[$act['type']] ?? ['label'=>'Activity','class'=>'green']; ?>
            <span class="act-badge <?php echo $b['class']; ?>"><?php echo $b['label']; ?></span>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Announcement Modal -->
<div class="modal-backdrop" id="annModal">
  <div class="modal-box">
    <div class="modal-head">
      <div class="modal-title" id="annModalTitle">Loading…</div>
      <button class="modal-close" onclick="closeAnn()"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <div id="annModalImgWrap" style="display:none"><img id="annModalImg" class="modal-img" /></div>
      <div class="modal-text" id="annModalContent"></div>
      <div class="modal-date" id="annModalDate"><i class="far fa-clock"></i></div>
    </div>
    <div class="modal-foot"><button class="btn-primary" onclick="closeAnn()">Close</button></div>
  </div>
</div>

<script>
function showAnnouncement(id) {
  const modal = document.getElementById('annModal');
  document.getElementById('annModalTitle').textContent = 'Loading…';
  document.getElementById('annModalContent').textContent = '';
  document.getElementById('annModalDate').innerHTML = '<i class="far fa-clock"></i>';
  document.getElementById('annModalImgWrap').style.display = 'none';
  modal.classList.add('open');
  document.body.style.overflow = 'hidden';
  fetch('get_announcement.php?id=' + id)
    .then(r => r.json()).then(d => {
      document.getElementById('annModalTitle').textContent = d.title || '';
      let c = (d.content || '').replace(/\\r\\n/g,'\n').replace(/\\n/g,'\n');
      document.getElementById('annModalContent').textContent = c;
      document.getElementById('annModalDate').innerHTML = '<i class="far fa-clock"></i> Posted ' + new Date(d.created_at).toLocaleDateString('en-US',{year:'numeric',month:'long',day:'numeric'});
      if (d.image) {
        const fn = d.image.split('/').pop();
        document.getElementById('annModalImg').src = 'serve_announcement_image.php?img=' + encodeURIComponent(fn);
        document.getElementById('annModalImgWrap').style.display = 'block';
      }
    }).catch(() => {
      document.getElementById('annModalTitle').textContent = 'Error';
      document.getElementById('annModalContent').textContent = 'Could not load announcement.';
    });
}
function closeAnn() {
  document.getElementById('annModal').classList.remove('open');
  document.body.style.overflow = '';
}
document.getElementById('annModal').addEventListener('click', function(e) { if(e.target===this) closeAnn(); });
document.addEventListener('keydown', e => { if(e.key==='Escape') closeAnn(); });
</script>
<?php if (file_exists(__DIR__ . '/al_footer_universal.php')) include 'al_footer_universal.php'; ?>
</body>
</html>
