<?php
// No strict_types: mysqli + DB row types vary; avoids TypeError fatals (HTTP 500) on shared hosting.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION['admin_last_visit_manage_users'] = date('Y-m-d H:i:s');
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/includes/mysqli_compat.php';
$conn = getDBConnection();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: al_login.php');
    exit();
}

$limit = 12;
$page = max(1, (int) ($_GET['page'] ?? 1));
$start = ($page - 1) * $limit;
$search = trim((string) ($_GET['search'] ?? ''));
$status_filter = (string) ($_GET['status'] ?? '');
$date_registered = (string) ($_GET['date_registered'] ?? '');
$college_filter = trim((string) ($_GET['college'] ?? ''));
$degree_filter = trim((string) ($_GET['degree'] ?? ''));
$year_filter = trim((string) ($_GET['year_graduated'] ?? ''));
$employment_filter = trim((string) ($_GET['employment'] ?? ''));
$reg_type_filter = trim((string) ($_GET['reg_type'] ?? ''));
if (!in_array($reg_type_filter, ['', 'new', 'legacy'], true)) {
    $reg_type_filter = '';
}

$has_college = false;
$has_year = false;
$has_month = false;
$has_emp = false;
foreach (['college' => 'has_college', 'year_graduated' => 'has_year', 'month_graduated' => 'has_month', 'employment_status' => 'has_emp'] as $col => $var) {
    $chk = @$conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'itcp' AND COLUMN_NAME = '" . $conn->real_escape_string($col) . "'");
    if ($chk && $chk->fetch_row()) {
        ${$var} = true;
    }
    if ($chk) {
        $chk->close();
    }
}

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR student_number LIKE ?)';
    $sp = '%' . $search . '%';
    array_push($params, $sp, $sp, $sp, $sp);
    $types .= 'ssss';
}
if ($status_filter !== '') {
    $where[] = 'status = ?';
    $params[] = $status_filter;
    $types .= 's';
} else {
    $where[] = "(status IS NULL OR LOWER(TRIM(status)) != 'archived')";
}
if ($date_registered === 'recently') {
    $where[] = 'date_joined >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
} elseif ($date_registered === 'month') {
    $where[] = 'date_joined >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
} elseif ($date_registered === 'year') {
    $where[] = 'date_joined >= DATE_SUB(NOW(), INTERVAL 1 YEAR)';
}
if ($college_filter !== '' && $has_college) {
    $where[] = 'college = ?';
    $params[] = $college_filter;
    $types .= 's';
}
if ($degree_filter !== '') {
    $where[] = 'program = ?';
    $params[] = $degree_filter;
    $types .= 's';
}
if ($year_filter !== '' && $has_year) {
    $where[] = 'year_graduated = ?';
    $params[] = $year_filter;
    $types .= 's';
}
if ($employment_filter !== '' && $has_emp) {
    $where[] = 'employment_status = ?';
    $params[] = $employment_filter;
    $types .= 's';
}

$wClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';

$pendingJoin = "LEFT JOIN (
    SELECT p1.itcp_id, p1.registration_type, p1.current_alumni_id
    FROM pending_registrations p1
    INNER JOIN (
      SELECT itcp_id, MAX(id) AS mx
      FROM pending_registrations
      GROUP BY itcp_id
    ) p2 ON p2.itcp_id = p1.itcp_id AND p2.mx = p1.id
) pr ON pr.itcp_id = itcp.id";

if ($reg_type_filter !== '') {
    $where[] = "COALESCE(NULLIF(TRIM(LOWER(pr.registration_type)),''),'new') = ?";
    $params[] = $reg_type_filter;
    $types .= 's';
    $wClause = 'WHERE ' . implode(' AND ', $where);
}

$cStmt = $conn->prepare("SELECT COUNT(*) AS t FROM itcp $pendingJoin $wClause");
$total_users = 0;
if ($cStmt) {
    if ($types !== '') {
        mysqli_stmt_bind_param_safe($cStmt, $types, $params);
    }
    $cStmt->execute();
    $cr = mysqli_stmt_fetch_assoc_compat($cStmt);
    $total_users = (int) ($cr['t'] ?? 0);
    $cStmt->close();
}

$total_pages = max(1, (int) ceil($total_users / $limit));

$rows = [];
$fStmt = $conn->prepare("SELECT itcp.id, itcp.firstname, itcp.lastname, itcp.email, itcp.status, itcp.date_joined, itcp.photo, itcp.program, itcp.student_number, COALESCE(NULLIF(TRIM(pr.registration_type),''), 'new') AS registration_type, COALESCE(pr.current_alumni_id, '') AS current_alumni_id FROM itcp $pendingJoin $wClause ORDER BY itcp.date_joined DESC LIMIT ? OFFSET ?");
if ($fStmt) {
    $ft = $types . 'ii';
    $fp = array_merge($params, [$limit, $start]);
    mysqli_stmt_bind_param_safe($fStmt, $ft, $fp);
    $fStmt->execute();
    $rows = mysqli_stmt_fetch_all_assoc_compat($fStmt);
    $fStmt->close();
}

$pendingCount = 0;
$pq = $conn->query("SELECT COUNT(*) AS c FROM itcp WHERE LOWER(TRIM(COALESCE(status,''))) = 'pending'");
if ($pq) {
    $pr = $pq->fetch_assoc();
    $pendingCount = (int) ($pr['c'] ?? 0);
}

$statusCounts = [];
foreach (['active', 'pending', 'inactive', 'rejected', 'archived'] as $s) {
    $st = $conn->prepare('SELECT COUNT(*) AS c FROM itcp WHERE LOWER(TRIM(COALESCE(status, \'\'))) = ?');
    if ($st) {
        mysqli_stmt_bind_param_safe($st, 's', [$s]);
        $st->execute();
        $sr = mysqli_stmt_fetch_assoc_compat($st);
        $statusCounts[$s] = (int) ($sr['c'] ?? 0);
        $st->close();
    } else {
        $statusCounts[$s] = 0;
    }
}

$conn->close();

$collegeOptions = [
    'College of Computer Studies',
    'College of Engineering',
    'College of Business',
    'College of Arts and Sciences',
    'College of Education',
    'College of Nursing',
    'College of Medicine',
];

$degreeOptions = [
    'Bachelor of Science in Information Technology',
    'Bachelor of Science in Computer Science',
    'Bachelor of Science in Information Systems',
    'Bachelor of Science in Civil Engineering',
    'Bachelor of Science in Electrical Engineering',
    'Bachelor of Science in Mechanical Engineering',
    'Bachelor of Science in Computer Engineering',
    'Bachelor of Science in Business Administration',
    'Bachelor of Science in Accountancy',
    'Bachelor of Science in Entrepreneurship',
    'Bachelor of Arts in Communication',
    'Bachelor of Science in Psychology',
    'Bachelor of Science in Biology',
    'Bachelor of Elementary Education',
    'Bachelor of Secondary Education',
    'Bachelor of Physical Education',
    'Bachelor of Science in Nursing',
    'Doctor of Medicine',
];
sort($degreeOptions);

$currentYear = (int) date('Y');
$yearGradOptions = range($currentYear, $currentYear - 40);
$employmentOptions = ['Employed', 'Unemployed', 'Self-employed', 'Student'];

$csrf = htmlspecialchars((string) ($_SESSION['admin_csrf'] ?? ''), ENT_QUOTES, 'UTF-8');

$umSuccess = isset($_GET['success']) && (string) $_GET['success'] === '1';
$umEmailSent = isset($_GET['email_sent']) && (string) $_GET['email_sent'] === '1';
$umEmailFailed = isset($_GET['email_sent']) && (string) $_GET['email_sent'] === '0';
$umWarnEmail = isset($_GET['warn']) && (string) $_GET['warn'] === 'email';
$umCpsId = trim((string) ($_GET['cps_id'] ?? ''));
$umError = trim((string) ($_GET['error'] ?? ''));

/** Flash from redirects (e.g. ad_alumni_id_check.php uses $_SESSION['error'] — was never shown before). */
$umSessionFlash = '';
if (!empty($_SESSION['error'])) {
    $umSessionFlash = trim((string) $_SESSION['error']);
    unset($_SESSION['error']);
}

$umErrorMessages = [
    'masterlist' => 'User not found in Registrar Masterlist. The student number and birthday must match an imported registrar record before approval.',
    'dob_required' => 'Cannot verify against the masterlist: this member has no valid birthday on file. Update their profile or registrar data, then try again.',
    'already_verified' => 'This account is already active. No approval was applied.',
    'duplicate_email' => 'Another active or pending member already uses this email address.',
    'duplicate_student_number' => 'Another active or pending member already uses this student number.',
    'no_password' => 'This registration has no password on file; it cannot be approved until the member completes a valid password.',
    'cps' => 'Database update succeeded but assigning a CPS alumni ID failed. No changes were committed.',
    'update' => 'The membership status could not be updated. Please try again or check the server log.',
    'csrf' => 'Security check failed. Refresh the page and try again.',
    'db' => 'Database connection failed.',
    'notfound' => 'That member record was not found.',
    'exception' => 'An unexpected error occurred. Check the server log for details.',
];

/**
 * Map DB status to CSS badge suffix (approved → active).
 */
function um_badge_class(string $status): string
{
    $s = strtolower(trim($status));
    if (in_array($s, ['active', 'approved'], true)) {
        return 'active';
    }
    if ($s === 'pending') {
        return 'pending';
    }
    if ($s === 'rejected') {
        return 'rejected';
    }
    if ($s === 'archived') {
        return 'archived';
    }
    if ($s === 'inactive') {
        return 'inactive';
    }

    return 'inactive';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>User Management — Admin Portal</title>
  <link rel="icon" href="olfulogo.png" type="image/png">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    :root {
      --cr:#8b0000;--cr-dk:#600000;--cr-lt:#b91c1c;--cr-pale:#fef2f2;
      --gold:#c9a84c;--gold-lt:#f0d98a;--gold-pale:#fdf5e0;
      --ink:#1a0a0a;--slate:#4a4040;--muted:#7a6a6a;
      --border:#e8dada;--surface:#fff;--bg:#faf7f7;
      --shadow-sm:0 1px 4px rgba(139,0,0,.07),0 2px 12px rgba(139,0,0,.05);
      --shadow-md:0 4px 16px rgba(139,0,0,.10),0 8px 32px rgba(139,0,0,.07);
      --r:14px; --r-sm:10px;
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Sora',sans-serif;background:var(--bg);color:var(--ink);-webkit-font-smoothing:antialiased;}
    a{text-decoration:none;color:inherit;}

    .admin-main{margin-left:64px;padding-top:64px;transition:margin-left .3s cubic-bezier(.4,0,.2,1);min-height:100vh;}
    #sidebar:hover ~ main.admin-main{margin-left:256px;}
    @media (max-width:1023px){
      .admin-main{margin-left:0 !important;padding-top:64px;}
    }
    .page{max-width:1400px;margin:0 auto;padding:28px 24px 60px;}

    .status-tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;}
    .status-tab{
      display:inline-flex;align-items:center;gap:6px;
      padding:8px 16px;border-radius:100px;
      font-size:.78rem;font-weight:600;
      border:1.5px solid var(--border);background:var(--surface);color:var(--muted);
      cursor:pointer;transition:all .18s;text-decoration:none;
    }
    .status-tab:hover{border-color:var(--cr);color:var(--cr);}
    .status-tab.active{background:var(--cr);border-color:var(--cr);color:#fff;}
    .status-tab .cnt{
      background:rgba(255,255,255,0.25);
      padding:1px 6px;border-radius:100px;font-size:.68rem;
    }
    .status-tab:not(.active) .cnt{background:var(--cr-pale);color:var(--cr);}

    .filter-bar{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);padding:18px 22px;margin-bottom:20px;box-shadow:var(--shadow-sm);}
    .filter-grid{display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;}
    .fi{display:flex;flex-direction:column;gap:4px;}
    .fi label{font-size:.65rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);}
    .fi select,.fi input{
      height:38px;padding:0 12px;
      border:1.5px solid var(--border);border-radius:100px;
      font-family:'Sora',sans-serif;font-size:.8rem;
      color:var(--ink);background:var(--bg);outline:none;
      transition:border-color .18s,background .18s;
    }
    .fi select:focus,.fi input:focus{border-color:var(--cr);background:var(--surface);}
    .fi.search-fi{position:relative;}
    .fi.search-fi i{position:absolute;left:13px;bottom:11px;color:var(--muted);font-size:12px;pointer-events:none;}
    .fi.search-fi input{padding-left:34px;min-width:220px;}

    .btn{display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:100px;font-family:'Sora',sans-serif;font-size:.8rem;font-weight:600;cursor:pointer;transition:all .18s;border:none;}
    .btn-primary{background:var(--cr);color:#fff;box-shadow:0 3px 12px rgba(139,0,0,.22);}
    .btn-primary:hover{background:var(--cr-dk);transform:translateY(-1px);}
    .btn-ghost{background:var(--surface);color:var(--slate);border:1.5px solid var(--border);}
    .btn-ghost:hover{border-color:var(--cr);color:var(--cr);background:var(--cr-pale);}

    .panel{background:var(--surface);border-radius:var(--r);border:1px solid var(--border);box-shadow:var(--shadow-sm);overflow:hidden;}
    .panel-head{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
    .panel-title{font-size:.9rem;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:8px;}
    .panel-title i{color:var(--cr);}
    table{width:100%;border-collapse:collapse;}
    th{padding:11px 18px;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);background:var(--bg);border-bottom:1px solid var(--border);text-align:left;white-space:nowrap;}
    td{padding:13px 18px;font-size:.82rem;border-bottom:1px solid rgba(232,218,218,0.5);vertical-align:middle;}
    tr:last-child td{border-bottom:none;}
    tr:hover td{background:rgba(254,242,242,0.4);}

    .t-avatar{width:36px;height:36px;border-radius:50%;overflow:hidden;background:linear-gradient(135deg,var(--cr),var(--cr-lt));color:#fff;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;flex-shrink:0;}
    .t-avatar img{width:100%;height:100%;object-fit:cover;}

    .badge{font-size:.65rem;font-weight:700;padding:3px 9px;border-radius:100px;display:inline-block;white-space:nowrap;}
    .badge-active{background:#f0fdf4;color:#059669;}
    .badge-pending{background:#fef3c7;color:#d97706;}
    .badge-rejected{background:#fee2e2;color:#dc2626;}
    .badge-inactive{background:#f3f4f6;color:#6b7280;}
    .badge-archived{background:#fff7ed;color:#ea580c;}

    .act-btn{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:100px;font-size:.72rem;font-weight:600;cursor:pointer;border:1.5px solid transparent;background:transparent;transition:all .15s;}
    .act-view,.act-checkid{color:var(--cr);border-color:rgba(139,0,0,.2);}
    .act-view:hover,.act-checkid:hover{background:var(--cr-pale);}
    .act-approve{color:#059669;border-color:rgba(5,150,105,.2);}
    .act-approve:hover{background:#f0fdf4;}
    .act-reject{color:#d97706;border-color:rgba(217,119,6,.2);}
    .act-reject:hover{background:#fffbeb;}
    .act-archive{color:#ea580c;border-color:rgba(234,88,12,.2);}
    .act-archive:hover{background:#fff7ed;}

    .chk-ok{background:#f0fdf4;color:#059669;font-size:.65rem;font-weight:700;padding:2px 8px;border-radius:100px;}
    .chk-bad{background:#fee2e2;color:#dc2626;font-size:.65rem;font-weight:700;padding:2px 8px;border-radius:100px;}
    .chk-warn{background:#fef3c7;color:#d97706;font-size:.65rem;font-weight:700;padding:2px 8px;border-radius:100px;}

    .pagination{display:flex;align-items:center;justify-content:space-between;padding:16px 22px;border-top:1px solid var(--border);flex-wrap:wrap;gap:12px;}
    .pag-info{font-size:.78rem;color:var(--muted);}
    .pag-btns{display:flex;gap:5px;flex-wrap:wrap;}
    .pag-btn{width:34px;height:34px;border-radius:9px;border:1.5px solid var(--border);background:var(--surface);color:var(--slate);display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:500;cursor:pointer;transition:all .15s;text-decoration:none;}
    .pag-btn:hover{border-color:var(--cr);color:var(--cr);background:var(--cr-pale);}
    .pag-btn.active{background:var(--cr);border-color:var(--cr);color:#fff;}
    .pag-btn.disabled{opacity:.35;pointer-events:none;}

    .bulk-bar{background:var(--gold-pale);border:1.5px solid var(--gold);border-radius:var(--r);padding:12px 18px;margin-bottom:14px;display:none;align-items:center;justify-content:space-between;}
    .bulk-bar.show{display:flex;}

    .modal-overlay{position:fixed;inset:0;background:rgba(26,10,10,.55);backdrop-filter:blur(4px);z-index:500;display:none;align-items:center;justify-content:center;padding:20px;}
    .modal-overlay.open{display:flex;animation:mFade .18s ease;}
    @keyframes mFade{from{opacity:0}to{opacity:1}}
    .modal-box{background:var(--surface);border-radius:20px;width:100%;max-width:480px;box-shadow:0 24px 80px rgba(0,0,0,.2);animation:mSlide .22s ease;}
    @keyframes mSlide{from{opacity:0;transform:translateY(18px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
    .modal-hd{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;}
    .modal-hd h3{font-size:1rem;font-weight:700;color:var(--ink);}
    .modal-x{width:30px;height:30px;border-radius:8px;background:var(--bg);border:none;color:var(--muted);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background .15s,color .15s;}
    .modal-x:hover{background:var(--cr-pale);color:var(--cr);}
    .modal-bd{padding:22px;}
    .modal-ft{padding:14px 22px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:10px;}

    @keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
    .fade-in{animation:fadeUp .4s ease both;}

    .empty{text-align:center;padding:56px 24px;color:var(--muted);}
    .empty i{font-size:2.5rem;display:block;margin-bottom:12px;opacity:.35;}

    .um-flash{border-radius:var(--r);padding:14px 18px;margin-bottom:18px;font-size:.84rem;line-height:1.45;border:1.5px solid transparent;display:flex;align-items:flex-start;gap:12px;}
    .um-flash i{margin-top:2px;flex-shrink:0;}
    .um-flash-success{background:#f0fdf4;border-color:#bbf7d0;color:#14532d;}
    .um-flash-warn{background:#fffbeb;border-color:#fde68a;color:#92400e;}
    .um-flash-error{background:#fef2f2;border-color:#fecaca;color:#991b1b;}
  </style>
</head>
<body>
<?php include __DIR__ . '/ad_header_universal.php'; ?>
<?php include __DIR__ . '/ad_sidebar_universal.php'; ?>

<main class="admin-main">
<div class="page">

  <?php if ($umSuccess): ?>
    <?php if ($umEmailSent && $umCpsId !== ''): ?>
      <div class="um-flash um-flash-success fade-in" role="status">
        <i class="fas fa-circle-check" aria-hidden="true"></i>
        <div>
          <strong>Approved successfully.</strong> Notification email was sent. CPS Alumni ID:
          <strong style="font-family:'DM Mono',monospace;"><?= htmlspecialchars($umCpsId, ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
      </div>
    <?php elseif ($umEmailFailed || $umWarnEmail): ?>
      <div class="um-flash um-flash-warn fade-in" role="alert">
        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
        <div>
          <strong>Approved successfully, but email notification failed.</strong>
          The member is active<?php if ($umCpsId !== ''): ?> with CPS ID
          <strong style="font-family:'DM Mono',monospace;"><?= htmlspecialchars($umCpsId, ENT_QUOTES, 'UTF-8') ?></strong><?php endif; ?>.
          You may fix SMTP and ask them to sign in, or contact them manually.
        </div>
      </div>
    <?php else: ?>
      <div class="um-flash um-flash-success fade-in" role="status"><i class="fas fa-circle-check" aria-hidden="true"></i><div><strong>Action completed.</strong></div></div>
    <?php endif; ?>
  <?php elseif ($umSessionFlash !== ''): ?>
    <div class="um-flash um-flash-error fade-in" role="alert">
      <i class="fas fa-circle-xmark" aria-hidden="true"></i>
      <div><?= htmlspecialchars($umSessionFlash, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
    </div>
  <?php elseif ($umError !== ''): ?>
    <div class="um-flash um-flash-error fade-in" role="alert">
      <i class="fas fa-circle-xmark" aria-hidden="true"></i>
      <div><?= htmlspecialchars($umErrorMessages[$umError] ?? ('Something went wrong (' . $umError . ').'), ENT_QUOTES, 'UTF-8') ?></div>
    </div>
  <?php elseif (isset($_GET['status_msg']) && (string) $_GET['status_msg'] === 'rejected'): ?>
    <div class="um-flash um-flash-success fade-in" role="status"><i class="fas fa-archive" aria-hidden="true"></i><div><strong>Member declined</strong> and moved to archived.</div></div>
  <?php endif; ?>

  <!-- Bulk POST: checkboxes attach via HTML5 form="" (no nested forms inside row approve forms) -->
  <form id="bulkActionForm" method="POST" action="ad_bulk_user_action.php" style="position:absolute;width:0;height:0;overflow:hidden;opacity:0;pointer-events:none;" aria-hidden="true">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
    <input type="hidden" name="action" id="bulkActionInput" value="">
    <input type="hidden" name="reason" id="bulkReasonInput" value="">
  </form>

  <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:22px;" class="fade-in">
    <div>
      <h1 style="font-size:1.5rem;font-weight:700;color:var(--ink);">User Management</h1>
      <p style="font-size:.82rem;color:var(--muted);margin-top:2px;">Review registrations, manage accounts, and approve pending members.</p>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <?php if ($pendingCount > 0): ?>
        <a href="ad_user_management.php?status=pending" class="btn btn-primary">
          <i class="fas fa-clock"></i> <?= (int) $pendingCount ?> Pending Review
        </a>
      <?php endif; ?>
      <a href="ad_registrar_masterlist.php" class="btn btn-ghost"><i class="fas fa-list-check"></i> Masterlist</a>
    </div>
  </div>

  <div class="status-tabs fade-in">
    <?php
    $tabQs = static function (array $extra) use ($search, $status_filter, $date_registered, $college_filter, $degree_filter, $year_filter, $employment_filter, $reg_type_filter): string {
        $base = [
            'search' => $search,
            'date_registered' => $date_registered,
            'college' => $college_filter,
            'degree' => $degree_filter,
            'year_graduated' => $year_filter,
            'employment' => $employment_filter,
            'reg_type' => $reg_type_filter,
        ];
        if (!array_key_exists('status', $extra)) {
            $base['status'] = $status_filter;
        }
        $q = array_merge($base, $extra);
        $q = array_filter($q, static fn ($v) => $v !== '' && $v !== null);

        return $q === [] ? '' : ('?' . http_build_query($q));
    };
    ?>
    <a href="ad_user_management.php<?= htmlspecialchars($tabQs(['reg_type' => '']), ENT_QUOTES, 'UTF-8') ?>" class="status-tab<?= $reg_type_filter === '' ? ' active' : '' ?>">All Paths</a>
    <a href="ad_user_management.php<?= htmlspecialchars($tabQs(['reg_type' => 'new']), ENT_QUOTES, 'UTF-8') ?>" class="status-tab<?= $reg_type_filter === 'new' ? ' active' : '' ?>">New ID Requests</a>
    <a href="ad_user_management.php<?= htmlspecialchars($tabQs(['reg_type' => 'legacy']), ENT_QUOTES, 'UTF-8') ?>" class="status-tab<?= $reg_type_filter === 'legacy' ? ' active' : '' ?>">Legacy Cardholders</a>
    <a href="ad_user_management.php<?= htmlspecialchars($tabQs(['status' => '']), ENT_QUOTES, 'UTF-8') ?>" class="status-tab<?= $status_filter === '' ? ' active' : '' ?>">All <span class="cnt"><?= (int) array_sum($statusCounts) ?></span></a>
    <?php foreach (['active' => 'Active', 'pending' => 'Pending', 'inactive' => 'Inactive', 'rejected' => 'Declined', 'archived' => 'Archived'] as $s => $l): ?>
      <a href="ad_user_management.php<?= htmlspecialchars($tabQs(['status' => $s]), ENT_QUOTES, 'UTF-8') ?>" class="status-tab<?= $status_filter === $s ? ' active' : '' ?>">
        <?= htmlspecialchars($l) ?> <span class="cnt"><?= (int) ($statusCounts[$s] ?? 0) ?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <form method="GET" action="ad_user_management.php" class="filter-bar fade-in">
    <?php if ($status_filter !== ''): ?><input type="hidden" name="status" value="<?= htmlspecialchars($status_filter, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
    <?php if ($reg_type_filter !== ''): ?><input type="hidden" name="reg_type" value="<?= htmlspecialchars($reg_type_filter, ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
    <div class="filter-grid">
      <div class="fi search-fi">
        <label for="um_search">Search</label>
        <i class="fas fa-search" aria-hidden="true"></i>
        <input id="um_search" type="text" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Name, email, ID…">
      </div>
      <div class="fi">
        <label for="um_date">Date Joined</label>
        <select id="um_date" name="date_registered">
          <option value="">Any time</option>
          <option value="recently" <?= $date_registered === 'recently' ? 'selected' : '' ?>>Last 7 days</option>
          <option value="month" <?= $date_registered === 'month' ? 'selected' : '' ?>>Last 30 days</option>
          <option value="year" <?= $date_registered === 'year' ? 'selected' : '' ?>>Last year</option>
        </select>
      </div>
      <?php if ($has_college): ?>
      <div class="fi">
        <label for="um_college">College</label>
        <select id="um_college" name="college">
          <option value="">All colleges</option>
          <?php foreach ($collegeOptions as $co): ?>
            <option value="<?= htmlspecialchars($co, ENT_QUOTES, 'UTF-8') ?>" <?= $college_filter === $co ? 'selected' : '' ?>><?= htmlspecialchars($co) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="fi">
        <label for="um_degree">Program</label>
        <select id="um_degree" name="degree" style="max-width:280px;">
          <option value="">All programs</option>
          <?php foreach ($degreeOptions as $d): ?>
            <option value="<?= htmlspecialchars($d, ENT_QUOTES, 'UTF-8') ?>" <?= $degree_filter === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($has_year): ?>
      <div class="fi">
        <label for="um_year">Year graduated</label>
        <select id="um_year" name="year_graduated">
          <option value="">All years</option>
          <?php foreach ($yearGradOptions as $yr): ?>
            <option value="<?= (int) $yr ?>" <?= $year_filter === (string) (int) $yr ? 'selected' : '' ?>><?= (int) $yr ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <?php if ($has_emp): ?>
      <div class="fi">
        <label for="um_emp">Employment</label>
        <select id="um_emp" name="employment">
          <option value="">All</option>
          <?php foreach ($employmentOptions as $emp): ?>
            <option value="<?= htmlspecialchars($emp, ENT_QUOTES, 'UTF-8') ?>" <?= $employment_filter === $emp ? 'selected' : '' ?>><?= htmlspecialchars($emp) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div style="display:flex;gap:8px;align-items:flex-end;">
        <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
        <a href="ad_user_management.php" class="btn btn-ghost"><i class="fas fa-times"></i> Clear</a>
      </div>
    </div>
  </form>

  <div class="bulk-bar" id="bulkBar">
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
      <span id="bulkCount" style="font-size:.82rem;font-weight:600;color:var(--ink);">0 selected</span>
      <button type="button" onclick="bulkAction('approve')" class="btn btn-primary" style="padding:6px 14px;font-size:.75rem;"><i class="fas fa-check-circle"></i> Approve</button>
      <button type="button" onclick="bulkAction('reject')" class="btn" style="padding:6px 14px;font-size:.75rem;background:#fef3c7;color:#92400e;border:1.5px solid #fde68a;"><i class="fas fa-times-circle"></i> Decline</button>
      <button type="button" onclick="bulkAction('archive')" class="btn" style="padding:6px 14px;font-size:.75rem;background:#fff7ed;color:#ea580c;border:1.5px solid #fed7aa;"><i class="fas fa-archive"></i> Archive</button>
    </div>
    <button type="button" onclick="clearSelection()" style="background:none;border:none;cursor:pointer;color:var(--muted);font-size:.78rem;"><i class="fas fa-times"></i> Clear</button>
  </div>

  <div class="panel fade-in">
      <div class="panel-head">
        <div class="panel-title"><i class="fas fa-users"></i>
          <?= number_format($total_users) ?> <?= $status_filter !== '' ? htmlspecialchars(ucfirst($status_filter)) . ' ' : '' ?>Member<?= $total_users !== 1 ? 's' : '' ?>
        </div>
        <label style="display:flex;align-items:center;gap:6px;font-size:.78rem;color:var(--muted);cursor:pointer;">
          <input type="checkbox" id="selectAll" onchange="toggleAll(this)" style="accent-color:var(--cr);"> Select all
        </label>
      </div>

      <?php if ($rows === []): ?>
        <div class="empty"><i class="fas fa-users"></i><h3>No members found</h3><p>Try adjusting your filters.</p></div>
      <?php else: ?>
      <div style="overflow-x:auto;">
        <table>
          <thead>
            <tr>
              <th style="width:40px;"></th>
              <th>Member</th>
              <th>Program</th>
              <th>Path</th>
              <th>Status</th>
              <th>Checks</th>
              <th>Joined</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row):
              $photo = trim((string) ($row['photo'] ?? ''));
              $photoSrc = $photo !== '' ? ((strpos($photo, 'http') === 0) ? $photo : 'serve_profile_image.php?img=' . rawurlencode(basename($photo))) : '';
              $initials = strtoupper(substr((string) ($row['firstname'] ?? 'A'), 0, 1) . substr((string) ($row['lastname'] ?? ''), 0, 1));
              $st = strtolower((string) ($row['status'] ?? ''));
              $isPending = $st === 'pending';
              $badgeSuffix = um_badge_class((string) ($row['status'] ?? ''));
              $regType = strtolower(trim((string) ($row['registration_type'] ?? 'new')));
              if ($regType !== 'legacy') { $regType = 'new'; }
              $legacyId = trim((string) ($row['current_alumni_id'] ?? ''));
              ?>
            <tr data-id="<?= (int) $row['id'] ?>">
              <td><input type="checkbox" name="user_ids[]" value="<?= (int) $row['id'] ?>" class="user-chk" form="bulkActionForm" onchange="updateSelection()" style="accent-color:var(--cr);width:16px;height:16px;cursor:pointer;"></td>
              <td>
                <div style="display:flex;align-items:center;gap:10px;">
                  <div class="t-avatar">
                    <?php if ($photoSrc !== ''): ?>
                      <img src="<?= htmlspecialchars($photoSrc, ENT_QUOTES, 'UTF-8') ?>" alt="" onerror="this.style.display='none'; var s=this.nextElementSibling; if(s) s.style.display='flex';">
                      <span style="display:none;align-items:center;justify-content:center;width:100%;height:100%;"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span>
                    <?php else: ?><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
                  </div>
                  <div>
                    <div style="font-weight:600;"><?= htmlspecialchars(trim(($row['firstname'] ?? '') . ' ' . ($row['lastname'] ?? ''))) ?></div>
                    <div style="font-size:.72rem;color:var(--muted);"><?= htmlspecialchars((string) ($row['email'] ?? '')) ?></div>
                  </div>
                </div>
              </td>
              <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--muted);"><?= htmlspecialchars((string) ($row['program'] ?? '—')) ?></td>
              <td>
                <?php if ($regType === 'legacy'): ?>
                  <span class="badge badge-inactive" style="background:#ede9fe;color:#5b21b6;">Legacy</span>
                  <?php if ($legacyId !== ''): ?>
                    <div style="font-size:.68rem;color:var(--muted);margin-top:4px;">ID: <?= htmlspecialchars($legacyId, ENT_QUOTES, 'UTF-8') ?></div>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="badge badge-active">New ID</span>
                <?php endif; ?>
              </td>
              <td><span class="badge badge-<?= htmlspecialchars($badgeSuffix, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst((string) ($row['status'] ?? '—'))) ?></span></td>
              <td id="chk-<?= (int) $row['id'] ?>">
                <?php if ($isPending): ?>
                  <span style="color:var(--muted);font-size:.7rem;"><i class="fas fa-spinner fa-spin"></i></span>
                  <?php if ($regType === 'legacy' && $legacyId === ''): ?>
                    <span class="chk-bad">No legacy ID</span>
                  <?php endif; ?>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td style="font-family:'DM Mono',monospace;font-size:.72rem;color:var(--muted);white-space:nowrap;"><?= htmlspecialchars(date('M d, Y', strtotime((string) ($row['date_joined'] ?? 'now')))) ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:4px;flex-wrap:wrap;">
                  <?php if ($isPending): ?>
                    <button type="button" class="act-btn act-view" onclick="openReviewModal(<?= (int) $row['id'] ?>)"><i class="fas fa-id-card"></i> Review</button>
                  <?php else: ?>
                    <a href="ad_viewprofile.php?id=<?= (int) $row['id'] ?>" class="act-btn act-view"><i class="fas fa-id-card"></i> View profile</a>
                  <?php endif; ?>
                  <a href="ad_alumni_id_check.php?id=<?= (int) $row['id'] ?>" class="act-btn act-checkid" title="Preview alumni ID card from registration data"><i class="fas fa-address-card"></i> Check ID</a>
                  <?php if ($isPending): ?>
                    <form method="POST" action="ad_approve_user.php" style="display:inline;" onsubmit="return confirmApprove(event, this)">
                      <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                      <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                      <input type="hidden" name="action" value="approve">
                      <button type="submit" class="act-btn act-approve"><i class="fas fa-check"></i> Approve</button>
                    </form>
                    <button type="button" class="act-btn act-reject" onclick="openRejectModal(<?= (int) $row['id'] ?>)"><i class="fas fa-times"></i> Decline</button>
                  <?php endif; ?>
                  <?php if ($st === 'active' || $st === 'approved'): ?>
                    <button type="button" class="act-btn act-archive" onclick="openArchiveModal(<?= (int) $row['id'] ?>)"><i class="fas fa-archive"></i></button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>

      <?php if ($total_pages > 1): ?>
      <?php
        $qs = http_build_query(array_filter([
            'search' => $search,
            'status' => $status_filter,
            'date_registered' => $date_registered,
            'college' => $college_filter,
            'degree' => $degree_filter,
            'year_graduated' => $year_filter,
            'employment' => $employment_filter,
            'reg_type' => $reg_type_filter,
        ], static fn ($v) => $v !== '' && $v !== null));
      ?>
      <div class="pagination">
        <div class="pag-info">Showing <?= (int) ($start + 1) ?>–<?= (int) min($start + $limit, $total_users) ?> of <?= number_format($total_users) ?></div>
        <div class="pag-btns">
          <a href="?page=1&amp;<?= htmlspecialchars($qs, ENT_QUOTES, 'UTF-8') ?>" class="pag-btn<?= $page <= 1 ? ' disabled' : '' ?>"><i class="fas fa-angle-double-left"></i></a>
          <a href="?page=<?= max(1, $page - 1) ?>&amp;<?= htmlspecialchars($qs, ENT_QUOTES, 'UTF-8') ?>" class="pag-btn<?= $page <= 1 ? ' disabled' : '' ?>"><i class="fas fa-angle-left"></i></a>
          <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="?page=<?= (int) $i ?>&amp;<?= htmlspecialchars($qs, ENT_QUOTES, 'UTF-8') ?>" class="pag-btn<?= $i === $page ? ' active' : '' ?>"><?= (int) $i ?></a>
          <?php endfor; ?>
          <a href="?page=<?= min($total_pages, $page + 1) ?>&amp;<?= htmlspecialchars($qs, ENT_QUOTES, 'UTF-8') ?>" class="pag-btn<?= $page >= $total_pages ? ' disabled' : '' ?>"><i class="fas fa-angle-right"></i></a>
          <a href="?page=<?= (int) $total_pages ?>&amp;<?= htmlspecialchars($qs, ENT_QUOTES, 'UTF-8') ?>" class="pag-btn<?= $page >= $total_pages ? ' disabled' : '' ?>"><i class="fas fa-angle-double-right"></i></a>
        </div>
      </div>
      <?php endif; ?>

  </div>

</div>
</main>

<div class="modal-overlay" id="archiveModal">
  <div class="modal-box">
    <div class="modal-hd">
      <h3><i class="fas fa-archive" style="color:var(--cr);margin-right:8px;"></i> Archive User</h3>
      <button type="button" class="modal-x" onclick="closeModal('archiveModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-bd">
      <p style="font-size:.88rem;color:var(--muted);line-height:1.65;">Are you sure you want to archive this user? They will be moved to archived status and hidden from the active list.</p>
    </div>
    <div class="modal-ft">
      <button type="button" class="btn btn-ghost" onclick="closeModal('archiveModal')">Cancel</button>
      <button type="button" class="btn" id="archiveConfirmBtn" style="background:#ea580c;color:#fff;"><i class="fas fa-archive"></i> Archive</button>
    </div>
  </div>
</div>

<div class="modal-overlay" id="rejectModal">
  <div class="modal-box">
    <div class="modal-hd">
      <h3><i class="fas fa-times-circle" style="color:#d97706;margin-right:8px;"></i> Decline Registration</h3>
      <button type="button" class="modal-x" onclick="closeModal('rejectModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-bd">
      <p style="font-size:.85rem;color:var(--muted);margin-bottom:14px;">Please provide a reason for declining this registration:</p>
      <textarea id="rejectReasonText" rows="4" style="width:100%;border:1.5px solid var(--border);border-radius:var(--r-sm);padding:10px 14px;font-family:'Sora',sans-serif;font-size:.85rem;color:var(--ink);outline:none;resize:vertical;" placeholder="Enter reason for rejection…"></textarea>
    </div>
    <div class="modal-ft">
      <button type="button" class="btn btn-ghost" onclick="closeModal('rejectModal')">Cancel</button>
      <button type="button" class="btn" id="rejectConfirmBtn" style="background:#d97706;color:#fff;"><i class="fas fa-times-circle"></i> Decline</button>
    </div>
  </div>
</div>

<script>
let archiveTarget = null, rejectTarget = null;
const pendingIds = <?= json_encode(array_column(array_filter($rows, static function ($r) {
    return strtolower((string) ($r['status'] ?? '')) === 'pending';
}), 'id'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

document.addEventListener('DOMContentLoaded', () => {
  if (pendingIds.length === 0) return;
  fetch('admin_pending_validation.php?ids=' + pendingIds.join(','))
    .then(r => r.ok ? r.text() : Promise.reject())
    .then(t => { try { return JSON.parse(t.trim()); } catch (e) { return {}; } })
    .then(data => {
      const vals = (data && data.validations) ? data.validations : {};
      pendingIds.forEach(uid => {
        const cell = document.getElementById('chk-' + uid);
        if (!cell) return;
        const v = vals[uid];
        if (!v) { cell.innerHTML = '—'; return; }
        const badges = [];
        if (v.no_password) badges.push('<span class="chk-bad">No pwd</span>');
        if (v.duplicate_email) badges.push('<span class="chk-bad">Dup email</span>');
        if (v.duplicate_student_number) badges.push('<span class="chk-bad">Dup ID</span>');
        if (!v.id_image_ok) badges.push('<span class="chk-warn">No ID</span>');
        if (!badges.length) badges.push('<span class="chk-ok"><i class="fas fa-check"></i> OK</span>');
        cell.innerHTML = badges.join(' ');
      });
    }).catch(() => { pendingIds.forEach(uid => { const c = document.getElementById('chk-'+uid); if (c) c.textContent = '—'; }); });
});

function openModal(id)  { const el = document.getElementById(id); if (el) { el.classList.add('open'); document.body.style.overflow='hidden'; } }
function closeModal(id) { const el = document.getElementById(id); if (el) { el.classList.remove('open'); document.body.style.overflow=''; } }
document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); }));

function openArchiveModal(id) {
  archiveTarget = id;
  openModal('archiveModal');
}
const archiveConfirmBtn = document.getElementById('archiveConfirmBtn');
if (archiveConfirmBtn) {
  archiveConfirmBtn.addEventListener('click', () => {
    if (archiveTarget) window.location.href = 'ad_user_archive.php?id=' + archiveTarget;
  });
}

function openRejectModal(id) {
  rejectTarget = id;
  const ta = document.getElementById('rejectReasonText');
  if (ta) ta.value = '';
  openModal('rejectModal');
}
const rejectConfirmBtn = document.getElementById('rejectConfirmBtn');
if (rejectConfirmBtn) {
  rejectConfirmBtn.addEventListener('click', () => {
    const ta = document.getElementById('rejectReasonText');
    const reason = ta ? ta.value.trim() : '';
    if (!reason) { if (ta) ta.style.borderColor = 'var(--cr)'; return; }
    if (rejectTarget) {
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = 'ad_approve_user.php';
      const csrf = <?= json_encode((string) ($_SESSION['admin_csrf'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      [['csrf_token', csrf], ['user_id', String(rejectTarget)], ['action', 'reject'], ['reason', reason]].forEach(([n, v]) => {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = n;
        inp.value = v;
        form.appendChild(inp);
      });
      document.body.appendChild(form);
      form.submit();
    }
  });
}

function openReviewModal(id) { window.location.href = 'ad_viewprofile.php?id=' + id; }

function confirmApprove(e, form) {
  return true;
}

function updateSelection() {
  const checked = document.querySelectorAll('.user-chk:checked');
  const bar = document.getElementById('bulkBar');
  const bulkCount = document.getElementById('bulkCount');
  if (bulkCount) bulkCount.textContent = checked.length + ' selected';
  if (bar) bar.classList.toggle('show', checked.length > 0);
  const selAll = document.getElementById('selectAll');
  const all = document.querySelectorAll('.user-chk');
  if (selAll) selAll.indeterminate = checked.length > 0 && checked.length < all.length;
}
function toggleAll(cb) {
  document.querySelectorAll('.user-chk').forEach(c => { c.checked = cb.checked; });
  updateSelection();
}
function clearSelection() {
  document.querySelectorAll('.user-chk').forEach(c => { c.checked = false; });
  const selAll = document.getElementById('selectAll');
  if (selAll) selAll.checked = false;
  updateSelection();
}
function bulkAction(action) {
  const checked = document.querySelectorAll('.user-chk:checked');
  if (!checked.length) return;
  if (action === 'reject') {
    const r = prompt('Reason for declining selected users:');
    if (!r) return;
    const br = document.getElementById('bulkReasonInput');
    if (br) br.value = r;
  }
  if (confirm(action.charAt(0).toUpperCase() + action.slice(1) + ' ' + checked.length + ' selected user(s)?')) {
    const bi = document.getElementById('bulkActionInput');
    if (bi) bi.value = action;
    document.getElementById('bulkActionForm').submit();
  }
}

const p = new URLSearchParams(window.location.search);
if (p.get('status_msg') === 'approved' && !p.get('success')) {
  history.replaceState({}, '', 'ad_user_management.php' + (p.get('status') ? '?status=' + encodeURIComponent(p.get('status')) : ''));
}

</script>
</body>
</html>
