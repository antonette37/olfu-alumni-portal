<?php
/**
 * Admin dashboard — analytics wired to itcp + work_history (CPS) or employment_history fallback.
 */
declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: al_login.php');
    exit();
}

require_once __DIR__ . '/db_config.php';
$conn = getDBConnection();

function ad_db_name(mysqli $conn): string
{
    $r = $conn->query('SELECT DATABASE() AS d');
    if ($r && $row = $r->fetch_assoc()) {
        return (string) ($row['d'] ?? '');
    }

    return '';
}

function ad_table_exists(mysqli $conn, string $table): bool
{
    $db = ad_db_name($conn);
    if ($db === '') {
        return false;
    }
    $t = $conn->real_escape_string($table);
    $q = $conn->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '{$db}' AND TABLE_NAME = '{$t}' LIMIT 1");

    return $q && $q->num_rows > 0;
}

/** @return array<string, true> */
function ad_table_columns(mysqli $conn, string $table): array
{
    $db = ad_db_name($conn);
    $t = $conn->real_escape_string($table);
    $out = [];
    $r = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '{$db}' AND TABLE_NAME = '{$t}'");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $out[strtolower((string) $row['COLUMN_NAME'])] = true;
        }
    }

    return $out;
}

/**
 * Map optional work/employment history table to query column names.
 *
 * @return array{ok:bool, table?:string, alumni?:string, industry?:string, company?:string, match?:string, privacy_sql?:string, extra_where?:string}
 */
function ad_dashboard_history_map(mysqli $conn): array
{
    if (ad_table_exists($conn, 'work_history')) {
        $c = ad_table_columns($conn, 'work_history');
        $priv = isset($c['is_private']) ? ' AND (is_private IS NULL OR is_private = 0) ' : '';

        return [
            'ok' => true,
            'table' => 'work_history',
            'alumni' => 'alumni_id',
            'industry' => 'industry_type',
            'company' => 'company',
            'match' => isset($c['is_aligned_with_course']) ? 'is_aligned_with_course' : null,
            'privacy_sql' => $priv,
            'extra_where' => '',
        ];
    }

    if (ad_table_exists($conn, 'employment_history')) {
        $c = ad_table_columns($conn, 'employment_history');
        $alumni = isset($c['alumni_id']) ? 'alumni_id' : (isset($c['user_id']) ? 'user_id' : null);
        if ($alumni === null) {
            return ['ok' => false];
        }
        $industry = isset($c['industry']) ? 'industry' : (isset($c['industry_type']) ? 'industry_type' : null);
        $company = isset($c['company_name']) ? 'company_name' : (isset($c['company']) ? 'company' : null);
        $match = isset($c['course_industry_match']) ? 'course_industry_match' : (isset($c['is_aligned_with_course']) ? 'is_aligned_with_course' : null);
        $extra = isset($c['is_current']) ? ' AND is_current = 1 ' : '';

        return [
            'ok' => true,
            'table' => 'employment_history',
            'alumni' => $alumni,
            'industry' => $industry,
            'company' => $company,
            'match' => $match,
            'privacy_sql' => '',
            'extra_where' => $extra,
        ];
    }

    return ['ok' => false];
}

function getCount(mysqli $conn, string $table, string $cond = ''): int
{
    $sql = 'SELECT COUNT(*) AS c FROM ' . $table . ($cond !== '' ? ' WHERE ' . $cond : '');
    $r = $conn->query($sql);

    return $r ? (int) $r->fetch_assoc()['c'] : 0;
}

$total_alumni = getCount($conn, 'itcp');
$active_alumni = getCount($conn, 'itcp', "LOWER(TRIM(COALESCE(status,''))) IN ('active','approved')");
$pending_alumni = getCount($conn, 'itcp', "LOWER(TRIM(COALESCE(status,''))) = 'pending'");
$total_events = ad_table_exists($conn, 'events') ? getCount($conn, 'events') : 0;
$upcoming_events_cnt = ad_table_exists($conn, 'events') ? getCount($conn, 'events', 'event_date >= CURDATE()') : 0;
$total_announcements = ad_table_exists($conn, 'announcements') ? getCount($conn, 'announcements') : 0;

$unreadInquiries = 0;
if (ad_table_exists($conn, 'contact_messages')) {
    $uq = $conn->query('SELECT COUNT(*) AS c FROM contact_messages WHERE is_read = 0');
    if ($uq) {
        $unreadInquiries = (int) $uq->fetch_assoc()['c'];
    }
}

$announcements = [];
if (ad_table_exists($conn, 'announcements')) {
    $announcements_result = $conn->query('SELECT * FROM announcements ORDER BY created_at DESC LIMIT 3');
    if ($announcements_result) {
        $announcements = $announcements_result->fetch_all(MYSQLI_ASSOC);
    }
}

$recent_alumni = [];
$raq = $conn->query(
    "SELECT firstname, lastname, program, year_graduated, photo, status, date_joined
     FROM itcp WHERE LOWER(TRIM(COALESCE(status,''))) != 'pending'
     ORDER BY date_joined DESC LIMIT 6"
);
if ($raq) {
    $recent_alumni = $raq->fetch_all(MYSQLI_ASSOC);
}

$upcoming_events = [];
if (ad_table_exists($conn, 'events')) {
    $upcoming_result = $conn->query(
        'SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 4'
    );
    if ($upcoming_result) {
        $upcoming_events = $upcoming_result->fetch_all(MYSQLI_ASSOC);
    }
}

$monthly = [];
$mq = $conn->query(
    "SELECT DATE_FORMAT(date_joined, '%b') AS m, COUNT(*) AS c
     FROM itcp
     WHERE date_joined >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY YEAR(date_joined), MONTH(date_joined)
     ORDER BY MIN(date_joined)"
);
if ($mq) {
    while ($r = $mq->fetch_assoc()) {
        $monthly[] = $r;
    }
}

// Employment status (doughnut) — from itcp profile snapshot (matches admin reports)
$emp_status_data = [];
$esq = $conn->query(
    "SELECT COALESCE(NULLIF(TRIM(employment_status), ''), 'Unknown') AS employment_status, COUNT(*) AS c
     FROM itcp
     WHERE LOWER(TRIM(COALESCE(status,''))) NOT IN ('pending','rejected')
     GROUP BY COALESCE(NULLIF(TRIM(employment_status), ''), 'Unknown')
     ORDER BY c DESC"
);
if ($esq) {
    while ($r = $esq->fetch_assoc()) {
        $emp_status_data[] = $r;
    }
}

$hist = ad_dashboard_history_map($conn);
$industry_data = [];
$companies_data = [];
$field_match_pct = null;

if ($hist['ok']) {
    $t = $hist['table'];
    $priv = $hist['privacy_sql'] ?? '';
    $ex = $hist['extra_where'] ?? '';

    if (!empty($hist['industry'])) {
        $col = $hist['industry'];
        $sql = "SELECT COALESCE(NULLIF(TRIM(`{$col}`), ''), 'Uncategorized') AS industry, COUNT(*) AS c
                FROM `{$t}` WHERE 1=1 {$priv} {$ex}
                GROUP BY COALESCE(NULLIF(TRIM(`{$col}`), ''), 'Uncategorized')
                ORDER BY c DESC LIMIT 6";
        $iq = $conn->query($sql);
        if ($iq) {
            while ($r = $iq->fetch_assoc()) {
                $industry_data[] = $r;
            }
        }
    }

    if (!empty($hist['company']) && !empty($hist['alumni'])) {
        $co = $hist['company'];
        $al = $hist['alumni'];
        $sql = "SELECT `{$co}` AS company_name, COUNT(DISTINCT `{$al}`) AS c
                FROM `{$t}` WHERE 1=1 {$priv} {$ex}
                AND TRIM(COALESCE(`{$co}`,'')) != ''
                GROUP BY `{$co}` ORDER BY c DESC LIMIT 5";
        $cq = $conn->query($sql);
        if ($cq) {
            while ($r = $cq->fetch_assoc()) {
                $companies_data[] = $r;
            }
        }
    }

    if (!empty($hist['match'])) {
        $mc = $hist['match'];
        $fmq = $conn->query(
            "SELECT ROUND(100 * AVG(`{$mc}`)) AS match_pct FROM `{$t}` WHERE 1=1 {$priv} {$ex}"
        );
        if ($fmq) {
            $fmr = $fmq->fetch_assoc();
            if ($fmr && $fmr['match_pct'] !== null) {
                $field_match_pct = (int) $fmr['match_pct'];
            }
        }
    }
}

// Employment status by batch year — itcp (same source as tracer / reports)
$batch_status_raw = [];
$bsq = $conn->query(
    "SELECT year_graduated, employment_status, COUNT(*) AS c
     FROM itcp
     WHERE year_graduated IS NOT NULL AND TRIM(CAST(year_graduated AS CHAR)) != ''
       AND LOWER(TRIM(COALESCE(status,''))) NOT IN ('pending','rejected')
     GROUP BY year_graduated, employment_status
     ORDER BY year_graduated ASC"
);
if ($bsq) {
    while ($r = $bsq->fetch_assoc()) {
        $batch_status_raw[] = $r;
    }
}

$batch_years = [];
$batch_statuses = [];
$batch_map = [];
foreach ($batch_status_raw as $row) {
    $y = (string) $row['year_graduated'];
    $s = (string) ($row['employment_status'] ?? 'Unknown');
    if ($s === '') {
        $s = 'Unknown';
    }
    if (!in_array($y, $batch_years, true)) {
        $batch_years[] = $y;
    }
    if (!in_array($s, $batch_statuses, true)) {
        $batch_statuses[] = $s;
    }
    if (!isset($batch_map[$y])) {
        $batch_map[$y] = [];
    }
    $batch_map[$y][$s] = (int) $row['c'];
}
usort($batch_years, static function ($a, $b) {
    return (int) $a <=> (int) $b;
});

// Recent activity — system_logs (supports mixed schemas)
$audit_rows = [];
if (ad_table_exists($conn, 'system_logs')) {
    $cols = ad_table_columns($conn, 'system_logs');
    $timeCol = isset($cols['created_at']) ? 'created_at' : null;
    if ($timeCol) {
        $parts = [];
        foreach (['description', 'details', 'action_type', 'action'] as $c) {
            if (isset($cols[$c])) {
                $parts[] = "NULLIF(TRIM(`{$c}`), '')";
            }
        }
        $msgExpr = $parts !== [] ? ('COALESCE(' . implode(', ', $parts) . ", '(log entry)')") : "'(log entry)'";
        $sql = "SELECT `{$timeCol}` AS t, {$msgExpr} AS msg FROM system_logs ORDER BY `{$timeCol}` DESC LIMIT 10";
        $aq = $conn->query($sql);
        if ($aq) {
            while ($row = $aq->fetch_assoc()) {
                $audit_rows[] = $row;
            }
        }
    }
}

$conn->close();

$js_emp_labels = json_encode(array_column($emp_status_data, 'employment_status'));
$js_emp_values = json_encode(array_map('intval', array_column($emp_status_data, 'c')));
$js_ind_labels = json_encode(array_column($industry_data, 'industry'));
$js_ind_values = json_encode(array_map('intval', array_column($industry_data, 'c')));
$js_monthly_m = json_encode(array_column($monthly, 'm'));
$js_monthly_c = json_encode(array_map('intval', array_column($monthly, 'c')));
$js_batch_years = json_encode($batch_years);
$js_batch_stats = json_encode($batch_statuses);
$js_batch_map = json_encode($batch_map);
$js_companies = json_encode($companies_data);

$adminFirst = htmlspecialchars(explode(' ', (string) ($_SESSION['admin_name'] ?? 'Admin'), 2)[0], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Dashboard — OLFU CCS Alumni</title>
  <link rel="icon" href="olfulogo.png" type="image/png">
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/countup.js/2.0.7/countUp.umd.min.js"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --cr:      #8b0000;
      --cr-dk:   #600000;
      --cr-lt:   #b91c1c;
      --cr-pale: #fef2f2;
      --gold:    #c9a84c;
      --gold-lt: #f0d98a;
      --gold-pale:#fdf5e0;
      --ink:     #1a0a0a;
      --slate:   #4a4040;
      --muted:   #7a6a6a;
      --border:  #e8dada;
      --surface: #ffffff;
      --bg:      #faf7f7;
      --shadow-sm: 0 1px 4px rgba(139,0,0,.07),0 2px 12px rgba(139,0,0,.05);
      --shadow-md: 0 4px 16px rgba(139,0,0,.10),0 8px 32px rgba(139,0,0,.07);
      --r: 14px;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Sora', -apple-system, sans-serif; background: var(--bg); color: var(--ink); -webkit-font-smoothing: antialiased; }
    h1,h2,h3 { font-family: 'Sora', sans-serif; }
    a { text-decoration: none; color: inherit; }

    .admin-main { margin-left: 64px; padding-top: 64px; transition: margin-left 0.3s cubic-bezier(.4,0,.2,1); min-height: 100vh; }
    #sidebar:hover ~ main.admin-main { margin-left: 256px; }
    @media (max-width: 1023px) {
      .admin-main { margin-left: 0 !important; padding-top: 64px; }
    }
    .page { max-width: 1400px; margin: 0 auto; padding: 32px 28px 60px; }

    .page-hero {
      background: linear-gradient(135deg, var(--cr-dk) 0%, var(--cr) 50%, #a00000 100%);
      border-radius: var(--r);
      padding: 32px 36px;
      margin-bottom: 28px;
      position: relative;
      overflow: hidden;
    }
    .page-hero::before {
      content: '';
      position: absolute; inset: 0;
      background: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.08'/%3E%3C/svg%3E");
      opacity: 0.5;
    }
    .page-hero-ring { position: absolute; border-radius: 50%; border: 1px solid rgba(255,255,255,0.07); }
    .page-hero-inner { position: relative; z-index: 1; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
    .page-hero h1 { font-size: clamp(1.5rem,3vw,2rem); font-weight: 700; color: #fff; line-height: 1.2; }
    .page-hero p  { font-size: 0.85rem; color: rgba(255,255,255,0.65); margin-top: 4px; }
    .hero-date {
      background: rgba(255,255,255,0.12);
      border: 1px solid rgba(255,255,255,0.18);
      border-radius: 10px;
      padding: 10px 18px;
      font-family: 'DM Mono', monospace;
      font-size: 0.78rem;
      color: rgba(255,255,255,0.8);
      letter-spacing: 0.05em;
    }

    .stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px,1fr)); gap: 18px; margin-bottom: 28px; }
    .dash-with-kpi {
      display: grid;
      grid-template-columns: minmax(0, 1fr) minmax(240px, 300px);
      gap: 24px;
      align-items: start;
    }
    .dash-main-col { min-width: 0; }
    .dash-kpi-aside {
      position: sticky;
      top: 76px;
      align-self: start;
    }
    .dash-kpi-aside .section-label { margin-bottom: 10px; }
    .stats-grid--side {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin-bottom: 0;
    }
    .stats-grid--side .stat-card {
      padding: 14px 12px;
      min-height: 0;
    }
    .stats-grid--side .stat-card-head { margin-bottom: 10px; }
    .stats-grid--side .stat-icon { width: 34px; height: 34px; font-size: 15px; border-radius: 10px; }
    .stats-grid--side .stat-badge { font-size: 0.6rem; padding: 2px 6px; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .stats-grid--side .stat-number { font-size: 1.45rem; }
    .stats-grid--side .stat-label { font-size: 0.62rem; margin-top: 4px; line-height: 1.25; }
    @media (max-width: 1180px) {
      .dash-with-kpi { grid-template-columns: 1fr; }
      .dash-kpi-aside { position: static; order: -1; margin-bottom: 8px; }
      .stats-grid--side { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
    }
    .stat-card {
      background: var(--surface);
      border-radius: var(--r);
      border: 1px solid var(--border);
      padding: 24px;
      box-shadow: var(--shadow-sm);
      position: relative; overflow: hidden; cursor: pointer;
      transition: transform 0.25s, box-shadow 0.25s, border-color 0.25s;
      text-decoration: none; display: block;
    }
    .stat-card::before {
      content: ''; position: absolute; top: 0; left: 0; right: 0;
      height: 3px; opacity: 0; transition: opacity 0.3s;
    }
    .stat-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); }
    .stat-card:hover::before { opacity: 1; }
    .stat-card.red::before   { background: linear-gradient(90deg, var(--cr), var(--cr-lt)); }
    .stat-card.gold::before  { background: linear-gradient(90deg, var(--gold), var(--gold-lt)); }
    .stat-card.blue::before  { background: linear-gradient(90deg, #2563eb, #60a5fa); }
    .stat-card.green::before { background: linear-gradient(90deg, #059669, #34d399); }
    .stat-card-head { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 16px; }
    .stat-icon { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 17px; }
    .stat-icon.red   { background: var(--cr-pale);   color: var(--cr); }
    .stat-icon.gold  { background: var(--gold-pale);  color: #a07030; }
    .stat-icon.blue  { background: #eff6ff; color: #2563eb; }
    .stat-icon.green { background: #f0fdf4; color: #059669; }
    .stat-badge { font-size: 0.68rem; font-weight: 600; padding: 3px 8px; border-radius: 100px; }
    .stat-badge.up   { background: #f0fdf4; color: #059669; }
    .stat-badge.warn { background: #fef3c7; color: #d97706; }
    .stat-number { font-size: 2.2rem; font-weight: 700; color: var(--ink); line-height: 1; letter-spacing: -0.02em; }
    .stat-label  { font-size: 0.78rem; font-weight: 600; color: var(--muted); margin-top: 6px; text-transform: uppercase; letter-spacing: 0.06em; }

    .panel { background: var(--surface); border-radius: var(--r); border: 1px solid var(--border); box-shadow: var(--shadow-sm); overflow: hidden; }
    .panel-head { padding: 18px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
    .panel-title { font-size: 0.92rem; font-weight: 700; color: var(--ink); display: flex; align-items: center; gap: 10px; }
    .panel-title i { color: var(--cr); font-size: 14px; }
    .panel-link { font-size: 0.75rem; font-weight: 600; color: var(--cr); transition: color 0.2s; }
    .panel-link:hover { color: var(--cr-dk); }
    .panel-body { padding: 0; }

    .section-label { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); margin: 0 0 14px; }

    .al-table { width: 100%; border-collapse: collapse; }
    .al-table th { padding: 10px 20px; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: var(--muted); background: var(--bg); border-bottom: 1px solid var(--border); text-align: left; }
    .al-table td { padding: 12px 20px; border-bottom: 1px solid rgba(232,218,218,0.5); font-size: 0.82rem; }
    .al-table tr:last-child td { border-bottom: none; }
    .al-table tr:hover td { background: var(--cr-pale); }
    .al-avatar { width: 36px; height: 36px; border-radius: 50%; overflow: hidden; background: linear-gradient(135deg, var(--cr), var(--cr-lt)); color: white; display: flex; align-items: center; justify-content: center; font-size: 0.72rem; font-weight: 700; flex-shrink: 0; }
    .al-avatar img { width: 100%; height: 100%; object-fit: cover; }

    .event-list { display: flex; flex-direction: column; }
    .event-item { display: flex; align-items: flex-start; gap: 14px; padding: 14px 20px; border-bottom: 1px solid rgba(232,218,218,0.5); transition: background 0.15s; }
    .event-item:hover { background: var(--cr-pale); }
    .event-item:last-child { border-bottom: none; }
    .event-date-box { width: 44px; height: 48px; border-radius: 10px; background: linear-gradient(135deg, var(--cr-dk), var(--cr)); color: white; display: flex; flex-direction: column; align-items: center; justify-content: center; flex-shrink: 0; }
    .event-date-day { font-size: 1.1rem; font-weight: 700; line-height: 1; }
    .event-date-mon { font-size: 0.58rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; opacity: 0.8; }
    .event-name { font-size: 0.85rem; font-weight: 600; color: var(--ink); margin-bottom: 2px; }
    .event-loc  { font-size: 0.75rem; color: var(--muted); display: flex; align-items: center; gap: 5px; }
    .event-badge { font-size: 0.65rem; font-weight: 600; padding: 3px 8px; border-radius: 100px; background: var(--gold-pale); color: #a07030; margin-left: auto; flex-shrink: 0; align-self: flex-start; }

    .ann-list { display: flex; flex-direction: column; }
    .ann-item { padding: 14px 20px; border-bottom: 1px solid rgba(232,218,218,0.5); transition: background 0.15s; cursor: pointer; }
    .ann-item:hover { background: var(--cr-pale); }
    .ann-item:last-child { border-bottom: none; }
    .ann-title { font-size: 0.85rem; font-weight: 600; color: var(--ink); margin-bottom: 4px; }
    .ann-body  { font-size: 0.75rem; color: var(--muted); line-height: 1.5; margin-bottom: 6px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .ann-date  { font-size: 0.68rem; color: rgba(122,106,106,0.7); font-family: 'DM Mono', monospace; }

    .analytics-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px; }
    .analytics-grid-3 { display: grid; grid-template-columns: 1.4fr 1fr 1fr; gap: 20px; margin-bottom: 28px; }
    @media (max-width: 1024px) { .analytics-grid, .analytics-grid-3 { grid-template-columns: 1fr; } }

    .chart-wrap { padding: 16px 20px 20px; }
    .chart-legend { display: flex; flex-wrap: wrap; gap: 10px; padding: 0 20px 4px; }
    .legend-item { display: flex; align-items: center; gap: 5px; font-size: 0.72rem; color: var(--muted); }
    .legend-dot  { width: 8px; height: 8px; border-radius: 2px; flex-shrink: 0; }

    .co-list { list-style: none; padding: 8px 20px 12px; }
    .co-item { display: flex; align-items: center; gap: 10px; padding: 7px 0; border-bottom: 1px solid rgba(232,218,218,0.4); font-size: 0.8rem; }
    .co-item:last-child { border-bottom: none; }
    .co-rank { font-size: 0.7rem; font-weight: 700; color: var(--muted); width: 16px; flex-shrink: 0; }
    .co-name { flex: 1; font-weight: 600; }
    .co-bar-wrap { width: 80px; background: var(--bg); border-radius: 4px; height: 5px; overflow: hidden; }
    .co-bar { height: 100%; border-radius: 4px; background: var(--cr); }
    .co-count { font-size: 0.7rem; color: var(--muted); min-width: 20px; text-align: right; }

    .audit-list { list-style: none; padding: 8px 20px 12px; max-height: 280px; overflow-y: auto; }
    .audit-item { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; border-bottom: 1px solid rgba(232,218,218,0.4); font-size: 0.78rem; }
    .audit-item:last-child { border-bottom: none; }
    .audit-col { display: flex; flex-direction: column; align-items: center; padding-top: 3px; }
    .audit-dot  { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
    .audit-line { width: 1px; flex: 1; background: var(--border); margin-top: 4px; min-height: 16px; }
    .audit-text { color: var(--muted); line-height: 1.5; }
    .audit-time { font-size: 0.68rem; color: rgba(122,106,106,0.6); margin-top: 2px; font-family: 'DM Mono', monospace; }

    .insight-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px,1fr)); gap: 16px; margin-bottom: 28px; }
    .insight-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--r); padding: 18px 20px; border-left-width: 3px; box-shadow: var(--shadow-sm); }
    .insight-heading { font-size: 0.82rem; font-weight: 700; margin-bottom: 6px; }
    .insight-text { font-size: 0.75rem; color: var(--muted); line-height: 1.6; }

    .chart-controls { display: flex; align-items: center; gap: 10px; padding: 10px 20px 0; flex-wrap: wrap; }
    .ctrl-btn { font-size: 0.72rem; font-weight: 600; padding: 4px 12px; border-radius: 100px; border: 1px solid var(--border); background: var(--bg); color: var(--muted); cursor: pointer; transition: all 0.15s; }
    .ctrl-btn.active, .ctrl-btn:hover { background: var(--cr); color: white; border-color: var(--cr); }

    .two-col-bottom { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px; }
    @media (max-width: 1024px) { .two-col-bottom { grid-template-columns: 1fr; } }

    @keyframes fadeUp { from { opacity:0; transform: translateY(16px); } to { opacity:1; transform:translateY(0); } }
    .fade-in   { animation: fadeUp 0.5s ease both; }
    .fade-in-1 { animation-delay: 0.05s; }
    .fade-in-2 { animation-delay: 0.10s; }
    .fade-in-3 { animation-delay: 0.15s; }
    .fade-in-4 { animation-delay: 0.20s; }
    .fade-in-5 { animation-delay: 0.25s; }
  </style>
</head>
<body>
<?php include __DIR__ . '/ad_header_universal.php'; ?>
<?php include __DIR__ . '/ad_sidebar_universal.php'; ?>

<main class="admin-main">
<div class="page">

  <div class="page-hero fade-in">
    <div class="page-hero-ring" style="width:400px;height:400px;top:-160px;right:-100px;"></div>
    <div class="page-hero-ring" style="width:220px;height:220px;bottom:-60px;left:-60px;"></div>
    <div class="page-hero-inner">
      <div>
        <h1>Welcome back, <?= $adminFirst ?>.</h1>
        <p>Here's what's happening in the CCS Alumni community today.</p>
      </div>
      <div class="hero-date"><?= htmlspecialchars(date('l, F j Y'), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
  </div>

  <div class="dash-with-kpi">
  <div class="dash-main-col">

  <p class="section-label fade-in">Career analytics</p>

  <div class="analytics-grid fade-in">

    <div class="panel">
      <div class="panel-head">
        <div class="panel-title"><i class="fas fa-chart-pie"></i> Graduate Outcomes</div>
        <a href="ad_reports.php" class="panel-link">Export <i class="fas fa-arrow-right" style="font-size:10px;"></i></a>
      </div>
      <div id="emp-legend" class="chart-legend" style="padding-top:14px;"></div>
      <div class="chart-wrap" style="height:220px;">
        <canvas id="empChart"></canvas>
      </div>
      <?php if ($emp_status_data === []): ?>
        <p style="padding:0 20px 16px;font-size:.78rem;color:var(--muted);">No employment status data yet — alumni fill this on their profile.</p>
      <?php endif; ?>
    </div>

    <div class="panel">
      <div class="panel-head">
        <div class="panel-title"><i class="fas fa-industry"></i> Industry Distribution</div>
      </div>
      <div class="chart-wrap" style="height:260px;">
        <canvas id="industryChart"></canvas>
      </div>
      <?php if (!$hist['ok']): ?>
        <p style="padding:0 20px 16px;font-size:.78rem;color:var(--muted);">Work history table not found. Run <code style="font-size:.7rem;">bootstrap_cps_schema.php</code> or create <code style="font-size:.7rem;">work_history</code>.</p>
      <?php elseif ($industry_data === []): ?>
        <p style="padding:0 20px 16px;font-size:.78rem;color:var(--muted);">No industry rows yet (profile / work history).</p>
      <?php endif; ?>
    </div>

  </div>

  <?php if ($batch_years !== []): ?>
  <div class="panel fade-in" style="margin-bottom:28px;">
    <div class="panel-head">
      <div class="panel-title"><i class="fas fa-layer-group"></i> Employment Status by Batch Year</div>
      <a href="ad_reports.php" class="panel-link">Export <i class="fas fa-arrow-right" style="font-size:10px;"></i></a>
    </div>
    <div class="chart-controls">
      <button type="button" class="ctrl-btn active" onclick="setBatchView('stacked',this)">Stacked</button>
      <button type="button" class="ctrl-btn" onclick="setBatchView('grouped',this)">Grouped</button>
      <button type="button" class="ctrl-btn" onclick="setBatchView('percent',this)">100%</button>
    </div>
    <div id="batch-legend" class="chart-legend" style="padding-top:12px;"></div>
    <div class="chart-wrap" style="height:280px;">
      <canvas id="batchChart"></canvas>
    </div>
  </div>
  <?php endif; ?>

  <div class="analytics-grid-3 fade-in">

    <div class="panel">
      <div class="panel-head">
        <div class="panel-title"><i class="fas fa-chart-line"></i> Registration Trend</div>
        <span style="font-size:0.72rem;color:var(--muted);">Last 6 months</span>
      </div>
      <div class="chart-wrap" style="height:190px;">
        <canvas id="trendChart"></canvas>
      </div>
      <?php if ($monthly === []): ?>
        <p style="padding:0 20px 16px;font-size:.78rem;color:var(--muted);">No registrations in the last 6 months.</p>
      <?php endif; ?>
    </div>

    <div class="panel">
      <div class="panel-head">
        <div class="panel-title"><i class="fas fa-building"></i> Top Employers</div>
      </div>
      <ul class="co-list" id="co-list">
        <?php if ($companies_data === []): ?>
          <li style="padding:20px 0;text-align:center;color:var(--muted);font-size:.8rem;">No employer data yet</li>
        <?php else:
          $maxCo = max(array_map('intval', array_column($companies_data, 'c')));
          foreach ($companies_data as $i => $co): ?>
          <li class="co-item">
            <span class="co-rank"><?= $i + 1 ?></span>
            <span class="co-name"><?= htmlspecialchars((string) ($co['company_name'] ?? '')) ?></span>
            <div class="co-bar-wrap">
              <div class="co-bar" style="width:<?= $maxCo > 0 ? (int) round((int) $co['c'] / $maxCo * 100) : 0 ?>%"></div>
            </div>
            <span class="co-count"><?= (int) $co['c'] ?></span>
          </li>
          <?php endforeach; endif; ?>
      </ul>
    </div>

    <div class="panel">
      <div class="panel-head">
        <div class="panel-title"><i class="fas fa-history"></i> Recent Activity</div>
        <a href="ad_logs.php" class="panel-link">Logs <i class="fas fa-arrow-right" style="font-size:10px;"></i></a>
      </div>
      <ul class="audit-list">
        <?php if ($audit_rows === []): ?>
          <li style="padding:20px;text-align:center;color:var(--muted);font-size:.8rem;">No log entries yet. Actions will appear here when <code style="font-size:.7rem;">system_logs</code> is populated.</li>
        <?php else:
          $colors = ['#059669', '#8b0000', '#2563eb', '#d97706', '#7c3aed'];
          foreach ($audit_rows as $idx => $log):
            $msg = (string) ($log['msg'] ?? '');
            $ts = isset($log['t']) ? strtotime((string) $log['t']) : false;
            $col = $colors[$idx % count($colors)];
            $isLast = $idx === count($audit_rows) - 1;
        ?>
        <li class="audit-item">
          <div class="audit-col">
            <span class="audit-dot" style="background:<?= htmlspecialchars($col) ?>;"></span>
            <?php if (!$isLast): ?><span class="audit-line"></span><?php endif; ?>
          </div>
          <div>
            <div class="audit-text"><?= htmlspecialchars($msg) ?></div>
            <div class="audit-time"><?= $ts ? htmlspecialchars(date('M j, Y g:i A', $ts)) : '—' ?></div>
          </div>
        </li>
        <?php endforeach; endif; ?>
      </ul>
    </div>

  </div>

  <div style="margin-bottom:28px;" class="fade-in">
    <div class="panel">
      <div class="panel-head">
        <div class="panel-title"><i class="fas fa-users"></i> Recently Joined Alumni</div>
        <a href="ad_alumnirecord.php" class="panel-link">View all <i class="fas fa-arrow-right" style="font-size:10px;"></i></a>
      </div>
      <div class="panel-body">
        <table class="al-table">
          <thead>
            <tr>
              <th>Member</th><th>Program</th><th>Batch</th><th>Status</th><th>Joined</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recent_alumni as $al):
              $photo = trim((string) ($al['photo'] ?? ''));
              $photoSrc = $photo !== '' ? ((strpos($photo, 'http') === 0) ? $photo : 'serve_profile_image.php?img=' . rawurlencode(basename($photo))) : '';
              $initials = strtoupper(substr((string) ($al['firstname'] ?? 'A'), 0, 1) . substr((string) ($al['lastname'] ?? ''), 0, 1));
              $st = strtolower((string) ($al['status'] ?? 'active'));
              $stColor = $st === 'active' || $st === 'approved' ? '#059669' : ($st === 'pending' ? '#d97706' : '#6b7280');
              $stBg    = $st === 'active' || $st === 'approved' ? '#f0fdf4' : ($st === 'pending' ? '#fef3c7' : '#f3f4f6');
            ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:10px;">
                  <div class="al-avatar">
                    <?php if ($photoSrc !== ''): ?>
                      <img src="<?= htmlspecialchars($photoSrc, ENT_QUOTES, 'UTF-8') ?>" alt="" onerror="this.style.display='none'; var n=this.nextElementSibling; if(n) n.style.display='flex';">
                      <span style="display:none;width:100%;height:100%;align-items:center;justify-content:center;"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php else: ?><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                  </div>
                  <div style="font-weight:600;font-size:.85rem;"><?= htmlspecialchars(trim(($al['firstname'] ?? '') . ' ' . ($al['lastname'] ?? ''))) ?></div>
                </div>
              </td>
              <td style="color:var(--muted);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars((string) ($al['program'] ?? '—')) ?></td>
              <td style="color:var(--muted);"><?= htmlspecialchars((string) ($al['year_graduated'] ?? '—')) ?></td>
              <td><span style="font-size:.68rem;font-weight:700;padding:3px 9px;border-radius:100px;background:<?= htmlspecialchars($stBg) ?>;color:<?= htmlspecialchars($stColor) ?>;"><?= htmlspecialchars(ucfirst($st)) ?></span></td>
              <td style="color:var(--muted);font-family:'DM Mono',monospace;font-size:.72rem;"><?= !empty($al['date_joined']) ? htmlspecialchars(date('M d, Y', strtotime((string) $al['date_joined']))) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="two-col-bottom fade-in">

    <div class="panel">
      <div class="panel-head">
        <div class="panel-title"><i class="fas fa-calendar-check"></i> Upcoming Events</div>
        <a href="ad_events.php" class="panel-link">Manage <i class="fas fa-arrow-right" style="font-size:10px;"></i></a>
      </div>
      <div class="panel-body event-list">
        <?php if ($upcoming_events === []): ?>
          <div style="padding:32px;text-align:center;color:var(--muted);font-size:.85rem;">
            <i class="fas fa-calendar" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.4;"></i>
            No upcoming events · <a href="ad_events.php" style="color:var(--cr);font-weight:600;">Create one</a>
          </div>
        <?php else: foreach ($upcoming_events as $ev):
          $dt = strtotime((string) ($ev['event_date'] ?? '')); ?>
          <div class="event-item">
            <div class="event-date-box">
              <div class="event-date-day"><?= $dt ? date('d', $dt) : '—' ?></div>
              <div class="event-date-mon"><?= $dt ? date('M', $dt) : '' ?></div>
            </div>
            <div style="flex:1;min-width:0;">
              <div class="event-name"><?= htmlspecialchars((string) ($ev['title'] ?? '')) ?></div>
              <div class="event-loc"><i class="fas fa-map-marker-alt"></i><?= htmlspecialchars((string) ($ev['location'] ?? $ev['venue'] ?? 'TBD')) ?></div>
            </div>
            <span class="event-badge"><?= htmlspecialchars((string) ($ev['type'] ?? $ev['event_type'] ?? 'Event')) ?></span>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>

    <div class="panel">
      <div class="panel-head">
        <div class="panel-title"><i class="fas fa-bullhorn"></i> Recent Announcements</div>
        <a href="ad_announcements.php" class="panel-link">Manage <i class="fas fa-arrow-right" style="font-size:10px;"></i></a>
      </div>
      <div class="panel-body ann-list">
        <?php if ($announcements === []): ?>
          <div style="padding:32px;text-align:center;color:var(--muted);font-size:.85rem;">
            <i class="fas fa-bullhorn" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.4;"></i>No announcements
          </div>
        <?php else: foreach ($announcements as $ann):
          $content = preg_replace('/\s+/', ' ', str_replace(["\r\n","\n","\r"], ' ', (string) ($ann['content'] ?? '')));
          $df = $ann['created_at'] ?? $ann['date_posted'] ?? null; ?>
          <a href="ad_announcements.php" class="ann-item">
            <div class="ann-title"><?= htmlspecialchars((string) ($ann['title'] ?? '')) ?></div>
            <div class="ann-body"><?= htmlspecialchars(function_exists('mb_substr') ? mb_substr($content, 0, 120) : substr($content, 0, 120)) ?><?= (function_exists('mb_strlen') ? mb_strlen($content) : strlen($content)) > 120 ? '…' : '' ?></div>
            <?php if ($df): ?>
              <div class="ann-date"><?= htmlspecialchars(date('M d, Y', strtotime((string) $df))) ?></div>
            <?php endif; ?>
          </a>
        <?php endforeach; endif; ?>
      </div>
    </div>

  </div>

  <p class="section-label fade-in">Smart insights</p>
  <div class="insight-grid fade-in">
    <?php
    $employed = 0;
    $total_with_status = 0;
    foreach ($emp_status_data as $e) {
        $total_with_status += (int) $e['c'];
        $lab = strtolower(preg_replace('/\s+/', ' ', trim((string) ($e['employment_status'] ?? ''))));
        if ($lab === '' || $lab === 'unknown') {
            continue;
        }
        if (str_starts_with($lab, 'unemploy') || $lab === 'unemployed') {
            continue;
        }
        if ($lab === 'employed' || $lab === 'self-employed' || str_contains($lab, 'self-employed')) {
            $employed += (int) $e['c'];
        }
    }
    $emp_rate = $total_with_status > 0 ? (int) round($employed / $total_with_status * 100) : null;
    ?>
    <?php if ($emp_rate !== null): ?>
    <div class="insight-card" style="border-left-color:#059669;">
      <div class="insight-heading" style="color:#059669;">Employment rate: <?= $emp_rate ?>%</div>
      <div class="insight-text">Based on <strong>itcp.employment_status</strong> (<?= (int) $employed ?> / <?= (int) $total_with_status ?> non-pending alumni). <?= $emp_rate >= 80 ? 'Strong workforce participation in the recorded snapshot.' : 'Encourage alumni to update employment on their profile to improve coverage.' ?></div>
    </div>
    <?php endif; ?>
    <?php if ($field_match_pct !== null): ?>
    <div class="insight-card" style="border-left-color:#2563eb;">
      <div class="insight-heading" style="color:#2563eb;">Field alignment: <?= (int) $field_match_pct ?>%</div>
      <div class="insight-text">Average course–industry alignment from <strong><?= htmlspecialchars($hist['ok'] ? $hist['table'] : 'work') ?></strong> records<?= $hist['ok'] && $hist['table'] === 'work_history' ? ' (non-private rows)' : '' ?>. Use tracer export for CHED / AACCUP evidence.</div>
    </div>
    <?php endif; ?>
    <?php if ($pending_alumni > 0): ?>
    <div class="insight-card" style="border-left-color:#d97706;">
      <div class="insight-heading" style="color:#d97706;"><?= (int) $pending_alumni ?> pending registration<?= $pending_alumni > 1 ? 's' : '' ?></div>
      <div class="insight-text"><?= $pending_alumni > 10 ? 'Backlog is building — consider batch review.' : 'Small queue — review when ready.' ?> <a href="ad_user_management.php?status=pending" style="color:var(--cr);font-weight:600;">Review →</a></div>
    </div>
    <?php endif; ?>
  </div>

  </div><!-- .dash-main-col -->

  <aside class="dash-kpi-aside" aria-label="Key metrics">
    <p class="section-label fade-in">Overview</p>
    <div class="stats-grid stats-grid--side">
      <a href="ad_user_management.php" class="stat-card red fade-in fade-in-1">
        <div class="stat-card-head">
          <div class="stat-icon red"><i class="fas fa-users"></i></div>
          <span class="stat-badge up"><i class="fas fa-arrow-up" style="font-size:8px;"></i> Directory</span>
        </div>
        <div class="stat-number" id="cnt-alumni"><?= (int) $total_alumni ?></div>
        <div class="stat-label">Total Alumni</div>
      </a>
      <a href="ad_events.php" class="stat-card blue fade-in fade-in-2">
        <div class="stat-card-head">
          <div class="stat-icon blue"><i class="fas fa-calendar-alt"></i></div>
          <span class="stat-badge up"><?= (int) $upcoming_events_cnt ?> upcoming</span>
        </div>
        <div class="stat-number" id="cnt-events"><?= (int) $total_events ?></div>
        <div class="stat-label">Total Events</div>
      </a>
      <a href="ad_announcements.php" class="stat-card red fade-in fade-in-3">
        <div class="stat-card-head">
          <div class="stat-icon red"><i class="fas fa-bullhorn"></i></div>
        </div>
        <div class="stat-number" id="cnt-ann"><?= (int) $total_announcements ?></div>
        <div class="stat-label">Announcements</div>
      </a>
      <a href="ad_user_management.php?status=active" class="stat-card green fade-in fade-in-4">
        <div class="stat-card-head">
          <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
          <span class="stat-badge up">Verified</span>
        </div>
        <div class="stat-number" id="cnt-active"><?= (int) $active_alumni ?></div>
        <div class="stat-label">Active Members</div>
      </a>
      <a href="ad_user_management.php?status=pending" class="stat-card gold fade-in fade-in-5">
        <div class="stat-card-head">
          <div class="stat-icon gold"><i class="fas fa-clock"></i></div>
          <?php if ($pending_alumni > 0): ?>
            <span class="stat-badge warn"><i class="fas fa-exclamation" style="font-size:8px;"></i> Action</span>
          <?php endif; ?>
        </div>
        <div class="stat-number" id="cnt-pending"><?= (int) $pending_alumni ?></div>
        <div class="stat-label">Pending Review</div>
      </a>
      <?php if ($field_match_pct !== null): ?>
      <a href="ad_reports.php" class="stat-card blue fade-in fade-in-5">
        <div class="stat-card-head">
          <div class="stat-icon blue"><i class="fas fa-graduation-cap"></i></div>
          <span class="stat-badge up">Alignment</span>
        </div>
        <div class="stat-number" id="cnt-fieldmatch"><?= (int) $field_match_pct ?></div>
        <div class="stat-label">Field Match Rate</div>
      </a>
      <?php endif; ?>
      <?php if ($unreadInquiries > 0): ?>
      <a href="ad_contactmessages.php" class="stat-card gold fade-in fade-in-5">
        <div class="stat-card-head">
          <div class="stat-icon gold"><i class="fas fa-envelope-open-text"></i></div>
          <span class="stat-badge warn">Unread</span>
        </div>
        <div class="stat-number" id="cnt-inq"><?= (int) $unreadInquiries ?></div>
        <div class="stat-label">New Inquiries</div>
      </a>
      <?php endif; ?>
    </div>
  </aside>

  </div><!-- .dash-with-kpi -->

</div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {

  [
    ['cnt-alumni', <?= (int) $total_alumni ?>, false],
    ['cnt-active', <?= (int) $active_alumni ?>, false],
    ['cnt-pending', <?= (int) $pending_alumni ?>, false],
    ['cnt-events', <?= (int) $total_events ?>, false],
    ['cnt-ann', <?= (int) $total_announcements ?>, false],
    ['cnt-inq', <?= (int) $unreadInquiries ?>, false],
    ['cnt-fieldmatch', <?= $field_match_pct !== null ? (int) $field_match_pct : 'null' ?>, true],
  ].forEach(([id, val, pct]) => {
    const el = document.getElementById(id);
    if (!el || val === null) return;
    try {
      const n = Number(val);
      const opts = { startVal: 0, duration: 1.6, useEasing: true, decimalPlaces: 0 };
      if (pct) opts.suffix = '%';
      const CU = window.CountUp && (window.CountUp.CountUp || window.CountUp);
      if (typeof CU !== 'function') return;
      const cu = new CU(id, n, opts);
      if (!cu.error) cu.start();
    } catch (e) {}
  });

  const STATUS_COLORS = ['#059669','#2563eb','#7c3aed','#d97706','#dc2626','#0891b2','#64748b'];
  const IND_COLOR = '#8b0000';
  const IND_DIM = 'rgba(139,0,0,0.22)';

  const empLabels = <?= $js_emp_labels ?>;
  const empValues = <?= $js_emp_values ?>;
  const empCtx = document.getElementById('empChart');
  if (empCtx && empLabels.length > 0) {
    const total = empValues.reduce((a, b) => a + b, 0);
    const legendEl = document.getElementById('emp-legend');
    empLabels.forEach((l, i) => {
      const pct = total > 0 ? Math.round(empValues[i] / total * 100) : 0;
      legendEl.innerHTML += '<span class="legend-item"><span class="legend-dot" style="background:' + (STATUS_COLORS[i] || '#999') + ';border-radius:50%;"></span>' + l + ' ' + pct + '%</span>';
    });
    new Chart(empCtx, {
      type: 'doughnut',
      data: {
        labels: empLabels,
        datasets: [{ data: empValues, backgroundColor: STATUS_COLORS.slice(0, empLabels.length), borderWidth: 3, borderColor: '#faf7f7', hoverOffset: 6 }]
      },
      options: {
        responsive: true, maintainAspectRatio: false, cutout: '68%',
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: ctx => ' ' + ctx.label + ': ' + ctx.raw + (total > 0 ? ' (' + Math.round(ctx.raw / total * 100) + '%)' : '') } }
        }
      }
    });
  }

  const indLabels = <?= $js_ind_labels ?>;
  const indValues = <?= $js_ind_values ?>;
  const indCtx = document.getElementById('industryChart');
  if (indCtx && indLabels.length > 0) {
    const maxInd = Math.max.apply(null, indValues);
    new Chart(indCtx, {
      type: 'bar',
      data: {
        labels: indLabels,
        datasets: [{ data: indValues, backgroundColor: indValues.map(v => v === maxInd ? IND_COLOR : IND_DIM), borderRadius: 5, borderSkipped: false }]
      },
      options: {
        indexAxis: 'y', responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 10 }, color: '#7a6a6a' }, beginAtZero: true },
          y: { grid: { display: false }, ticks: { font: { size: 10 }, color: '#7a6a6a' } }
        }
      }
    });
  }

  const batchYears = <?= $js_batch_years ?>;
  const batchStats = <?= $js_batch_stats ?>;
  const batchMap = <?= $js_batch_map ?>;
  let batchChart = null;
  let batchViewMode = 'stacked';

  function buildBatchDatasets(mode) {
    if (mode === 'percent') {
      return batchStats.map((s, i) => ({
        label: s,
        data: batchYears.map(y => {
          let tot = 0;
          batchStats.forEach(st => { tot += (batchMap[y] && batchMap[y][st]) ? batchMap[y][st] : 0; });
          const v = (batchMap[y] && batchMap[y][s]) ? batchMap[y][s] : 0;
          return tot > 0 ? Math.round((v / tot) * 1000) / 10 : 0;
        }),
        backgroundColor: STATUS_COLORS[i] || '#999',
        stack: 'stack'
      }));
    }
    if (mode === 'grouped') {
      return batchStats.map((s, i) => ({
        label: s,
        data: batchYears.map(y => (batchMap[y] && batchMap[y][s]) ? batchMap[y][s] : 0),
        backgroundColor: STATUS_COLORS[i] || '#999'
      }));
    }
    return batchStats.map((s, i) => ({
      label: s,
      data: batchYears.map(y => (batchMap[y] && batchMap[y][s]) ? batchMap[y][s] : 0),
      backgroundColor: STATUS_COLORS[i] || '#999',
      stack: 'stack'
    }));
  }

  const batchCanvas = document.getElementById('batchChart');
  if (batchCanvas && batchYears.length > 0) {
    const bLegend = document.getElementById('batch-legend');
    batchStats.forEach((s, i) => {
      bLegend.innerHTML += '<span class="legend-item"><span class="legend-dot" style="background:' + (STATUS_COLORS[i] || '#999') + ';"></span>' + s + '</span>';
    });
    batchChart = new Chart(batchCanvas, {
      type: 'bar',
      data: { labels: batchYears, datasets: buildBatchDatasets('stacked') },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { stacked: true, grid: { display: false }, ticks: { font: { size: 10 }, color: '#7a6a6a' } },
          y: { stacked: true, beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 10 }, color: '#7a6a6a' } }
        }
      }
    });
  }

  window.setBatchView = function(mode, btn) {
    document.querySelectorAll('.ctrl-btn').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    if (!batchChart) return;
    batchViewMode = mode;
    const percent = mode === 'percent';
    const grouped = mode === 'grouped';
    batchChart.data.datasets = buildBatchDatasets(mode);
    batchChart.options.scales.x.stacked = !grouped;
    batchChart.options.scales.y.stacked = !grouped;
    if (percent) {
      batchChart.options.scales.y.max = 100;
      batchChart.options.scales.y.ticks = { callback: v => v + '%' };
    } else {
      delete batchChart.options.scales.y.max;
      batchChart.options.scales.y.ticks = {};
    }
    batchChart.update();
  };

  <?php if ($monthly !== []): ?>
  const trendCtx = document.getElementById('trendChart');
  if (trendCtx) {
    new Chart(trendCtx, {
      type: 'line',
      data: {
        labels: <?= $js_monthly_m ?>,
        datasets: [{
          label: 'New Registrations',
          data: <?= $js_monthly_c ?>,
          borderColor: '#8b0000',
          backgroundColor: 'rgba(139,0,0,0.08)',
          borderWidth: 2.5,
          pointBackgroundColor: '#8b0000',
          pointRadius: 4,
          tension: 0.4,
          fill: true
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' }, ticks: { font: { size: 11 } } },
          x: { grid: { display: false }, ticks: { font: { size: 11 } } }
        }
      }
    });
  }
  <?php endif; ?>

});
</script>
</body>
</html>
