<?php
/**
 * Admin: browse alumni (non-pending) with search, program/year filters, grid or table view.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: al_login.php');
    exit();
}

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/includes/mysqli_compat.php';

$conn = getDBConnection();
$current_page = 'ad_alumnidirectory.php';

$limit = 24;
$page = max(1, (int) ($_GET['page'] ?? 1));
$search = trim((string) ($_GET['search'] ?? ''));
$program = trim((string) ($_GET['program'] ?? ''));
$batch = trim((string) ($_GET['batch'] ?? ''));

$programList = [];
$pr = $conn->query("SELECT DISTINCT TRIM(program) AS p FROM itcp WHERE program IS NOT NULL AND TRIM(program) <> '' ORDER BY p ASC LIMIT 400");
if ($pr) {
    while ($r = $pr->fetch_assoc()) {
        $p = (string) ($r['p'] ?? '');
        if ($p !== '') {
            $programList[] = $p;
        }
    }
}

$yearList = [];
$yr = $conn->query("SELECT DISTINCT year_graduated AS y FROM itcp WHERE year_graduated IS NOT NULL ORDER BY y DESC LIMIT 100");
if ($yr) {
    while ($r = $yr->fetch_assoc()) {
        if ($r['y'] !== null && $r['y'] !== '') {
            $yearList[] = (string) $r['y'];
        }
    }
}

$where = ['(LOWER(TRIM(COALESCE(status, \'\'))) <> \'pending\')'];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(firstname LIKE ? OR lastname LIKE ? OR email LIKE ? OR TRIM(COALESCE(program,\'\')) LIKE ? OR CAST(COALESCE(year_graduated,\'\') AS CHAR) LIKE ?)';
    $s = '%' . $search . '%';
    array_push($params, $s, $s, $s, $s, $s);
    $types .= 'sssss';
}
if ($program !== '') {
    $where[] = 'program = ?';
    $params[] = $program;
    $types .= 's';
}
if ($batch !== '') {
    $where[] = 'CAST(year_graduated AS CHAR) = ?';
    $params[] = $batch;
    $types .= 's';
}

$wSql = 'WHERE ' . implode(' AND ', $where);

$total = 0;
$cStmt = $conn->prepare("SELECT COUNT(*) AS t FROM itcp $wSql");
if ($cStmt) {
    if ($types !== '') {
        mysqli_stmt_bind_param_safe($cStmt, $types, $params);
    }
    $cStmt->execute();
    $cr = mysqli_stmt_fetch_assoc_compat($cStmt);
    $total = (int) ($cr['t'] ?? 0);
    $cStmt->close();
}

$totalPages = max(1, (int) ceil($total / $limit));
$page = min($page, $totalPages);
$offset = ($page - 1) * $limit;

$rows = [];
$lStmt = $conn->prepare("SELECT id, firstname, lastname, middlename, email, program, year_graduated, status, photo FROM itcp $wSql ORDER BY lastname ASC, firstname ASC LIMIT ? OFFSET ?");
if ($lStmt) {
    $lt = $types . 'ii';
    $lp = array_merge($params, [$limit, $offset]);
    mysqli_stmt_bind_param_safe($lStmt, $lt, $lp);
    $lStmt->execute();
    $rows = mysqli_stmt_fetch_all_assoc_compat($lStmt);
    $lStmt->close();
}

$conn->close();

function ad_dir_photo_url(?string $photoRaw): string
{
    $photoRaw = trim((string) $photoRaw);
    if ($photoRaw === '') {
        return '';
    }
    if (stripos($photoRaw, 'http') === 0) {
        return $photoRaw;
    }

    return 'serve_profile_image.php?img=' . rawurlencode(basename($photoRaw));
}

function ad_dir_initials(string $first, string $last): string
{
    $a = strtoupper(substr(trim($first), 0, 1));
    $b = strtoupper(substr(trim($last), 0, 1));

    return ($a !== '' || $b !== '') ? ($a . $b) : '?';
}

function ad_dir_status_label(string $st): string
{
    $s = strtolower(trim($st));

    return $s === '' ? '—' : ucfirst($s);
}

function ad_dir_badge_class(string $st): string
{
    $s = strtolower(trim($st));
    if (in_array($s, ['active', 'approved'], true)) {
        return 'ad-dir-tag ad-dir-tag--ok';
    }
    if ($s === 'inactive') {
        return 'ad-dir-tag ad-dir-tag--muted';
    }
    if (in_array($s, ['rejected', 'archived'], true)) {
        return 'ad-dir-tag ad-dir-tag--warn';
    }

    return 'ad-dir-tag';
}

$qsKeep = array_filter(
    [
        'search' => $search,
        'program' => $program,
        'batch' => $batch,
    ],
    static fn ($v) => $v !== '' && $v !== null
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Alumni Directory — Admin Portal</title>
  <link rel="icon" href="olfulogo.png" type="image/png" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet" />
  <style>
    :root {
      --cr: #8b0000; --cr-dk: #600000; --cr-lt: #b91c1c; --cr-pale: #fef2f2;
      --gold: #c9a84c; --ink: #1a0a0a; --muted: #7a6a6a; --border: #e8dada;
      --surface: #fff; --bg: #faf7f7; --shadow-sm: 0 1px 4px rgba(139,0,0,.07), 0 2px 12px rgba(139,0,0,.05);
      --shadow-md: 0 4px 16px rgba(139,0,0,.1), 0 8px 32px rgba(139,0,0,.08);
      --r: 12px; --r-lg: 18px;
    }
    *, *::before, *::after { box-sizing: border-box; }
    body { margin: 0; font-family: Sora, system-ui, sans-serif; background: var(--bg); color: var(--ink); -webkit-font-smoothing: antialiased; }
    a { text-decoration: none; color: inherit; }
    .admin-main { margin-left: 64px; padding-top: 64px; transition: margin-left .3s cubic-bezier(.4,0,.2,1); min-height: 100vh; }
    #sidebar:hover ~ main.admin-main, #sidebar.ad-universal-sidebar:focus-within ~ main.admin-main { margin-left: 256px; }
    @media (max-width: 1023px) {
      .admin-main { margin-left: 0 !important; padding-top: 64px; }
    }
    .ad-dir-page { max-width: 1320px; margin: 0 auto; padding: 28px 22px 56px; }
    .ad-dir-hero {
      display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 18px;
      margin-bottom: 22px;
    }
    .ad-dir-hero h1 { font-size: 1.45rem; font-weight: 700; color: var(--ink); margin: 0 0 4px; letter-spacing: -0.02em; }
    .ad-dir-hero p { margin: 0; font-size: .84rem; color: var(--muted); max-width: 520px; line-height: 1.5; }
    .ad-dir-stat {
      display: flex; gap: 10px; flex-wrap: wrap;
    }
    .ad-dir-stat span {
      font-size: .78rem; font-weight: 600; color: var(--cr-dk); background: var(--cr-pale); border: 1px solid var(--border);
      padding: 8px 14px; border-radius: 999px; font-family: "DM Mono", monospace;
    }
    .ad-dir-toolbar {
      background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); box-shadow: var(--shadow-sm);
      padding: 18px 20px; margin-bottom: 22px; display: grid; gap: 16px;
      grid-template-columns: 1fr; align-items: end;
    }
    @media (min-width: 900px) {
      .ad-dir-toolbar { grid-template-columns: 1.2fr repeat(3, minmax(0, 1fr)) auto; }
    }
    .ad-dir-field label { display: block; font-size: .68rem; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); margin-bottom: 6px; }
    .ad-dir-field input, .ad-dir-field select {
      width: 100%; padding: 10px 12px; border-radius: 10px; border: 1.5px solid var(--border); font-family: inherit; font-size: .84rem;
      background: var(--bg); color: var(--ink); transition: border-color .15s, box-shadow .15s;
    }
    .ad-dir-field input:focus, .ad-dir-field select:focus {
      outline: none; border-color: var(--cr); box-shadow: 0 0 0 3px rgba(139,0,0,.08); background: var(--surface);
    }
    .ad-dir-search-wrap { position: relative; }
    .ad-dir-search-wrap i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: .9rem; pointer-events: none; }
    .ad-dir-search-wrap input { padding-left: 40px; }
    .ad-dir-actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .ad-dir-btn {
      display: inline-flex; align-items: center; gap: 8px; padding: 10px 16px; border-radius: 10px; font-size: .8rem; font-weight: 600;
      border: none; cursor: pointer; font-family: inherit; text-decoration: none; transition: background .15s, color .15s;
    }
    .ad-dir-btn--primary { background: var(--cr); color: #fff; }
    .ad-dir-btn--primary:hover { background: var(--cr-dk); }
    .ad-dir-btn--ghost { background: var(--surface); color: var(--cr); border: 1.5px solid var(--border); }
    .ad-dir-btn--ghost:hover { background: var(--cr-pale); }
    .ad-dir-toggle { background: var(--cr-pale); color: var(--cr-dk); border: 1.5px solid var(--border); }
    .ad-dir-toggle[aria-pressed="true"] { background: var(--cr); color: #fff; border-color: var(--cr); }
    .ad-dir-grid {
      display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 18px;
    }
    .ad-dir-card {
      background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); overflow: hidden;
      box-shadow: var(--shadow-sm); transition: transform .18s ease, box-shadow .18s ease;
    }
    .ad-dir-card:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); }
    .ad-dir-card-top { height: 4px; background: linear-gradient(90deg, var(--cr), var(--gold), var(--cr-lt)); }
    .ad-dir-card-body { padding: 18px 18px 16px; text-align: center; }
    .ad-dir-avatar {
      position: relative;
      width: 88px; height: 88px; border-radius: 50%; margin: 0 auto 14px; overflow: hidden; background: var(--cr-pale);
      border: 3px solid var(--border); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.35rem; color: var(--cr);
    }
    .ad-dir-avatar img {
      position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover;
    }
    .ad-dir-avatar .ad-dir-fallback { display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; }
    .ad-dir-card h2 { font-size: 1rem; font-weight: 700; margin: 0 0 4px; color: var(--ink); line-height: 1.3; }
    .ad-dir-card .meta { font-size: .78rem; color: var(--muted); margin-bottom: 6px; }
    .ad-dir-card .batch { font-size: .72rem; font-weight: 600; color: var(--cr); font-family: "DM Mono", monospace; }
    .ad-dir-card-actions { display: flex; justify-content: center; gap: 8px; padding: 0 14px 16px; flex-wrap: wrap; }
    .ad-dir-iconbtn {
      width: 40px; height: 40px; border-radius: 10px; border: 1px solid var(--border); background: var(--bg); color: var(--slate, #4a4040);
      display: inline-flex; align-items: center; justify-content: center; cursor: pointer; text-decoration: none; transition: background .15s, color .15s, border-color .15s;
    }
    .ad-dir-iconbtn:hover { background: var(--cr-pale); color: var(--cr); border-color: rgba(139,0,0,.25); }
    .ad-dir-iconbtn--danger:hover { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
    .ad-dir-table-wrap {
      background: var(--surface); border: 1px solid var(--border); border-radius: var(--r-lg); box-shadow: var(--shadow-sm); overflow: hidden; display: none;
    }
    .ad-dir-table-wrap.is-visible { display: block; }
    .ad-dir-table-wrap table { width: 100%; border-collapse: collapse; font-size: .82rem; }
    .ad-dir-table-wrap th {
      text-align: left; padding: 12px 16px; background: var(--cr-pale); color: var(--cr-dk); font-weight: 700; font-size: .68rem;
      text-transform: uppercase; letter-spacing: .05em; border-bottom: 1px solid var(--border);
    }
    .ad-dir-table-wrap td { padding: 12px 16px; border-bottom: 1px solid var(--border); vertical-align: middle; }
    .ad-dir-table-wrap tr:last-child td { border-bottom: none; }
    .ad-dir-table-wrap tr:hover td { background: rgba(254,242,242,.45); }
    .ad-dir-row-name { font-weight: 600; color: var(--ink); }
    .ad-dir-row-sub { font-size: .74rem; color: var(--muted); }
    .ad-dir-tag { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: .68rem; font-weight: 600; }
    .ad-dir-tag--ok { background: #ecfdf5; color: #047857; }
    .ad-dir-tag--muted { background: #f3f4f6; color: #4b5563; }
    .ad-dir-tag--warn { background: #fff7ed; color: #c2410c; }
    .ad-dir-empty {
      text-align: center; padding: 48px 20px; color: var(--muted); background: var(--surface); border: 1px dashed var(--border);
      border-radius: var(--r-lg); font-size: .88rem;
    }
    .ad-dir-empty i { font-size: 2rem; display: block; margin-bottom: 12px; opacity: .35; color: var(--cr); }
    .ad-dir-pagination { display: flex; justify-content: center; flex-wrap: wrap; gap: 6px; margin-top: 28px; }
    .ad-dir-pagination a {
      min-width: 40px; padding: 8px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--surface);
      color: var(--ink); font-size: .8rem; font-weight: 600; text-decoration: none; text-align: center;
    }
    .ad-dir-pagination a:hover { background: var(--cr-pale); color: var(--cr); }
    .ad-dir-pagination a.is-current { background: var(--cr); color: #fff; border-color: var(--cr); }
    .ad-dir-pagination span { padding: 8px; color: var(--muted); font-size: .78rem; }
    .ad-dir-modal-overlay {
      position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 500; display: none; align-items: center; justify-content: center; padding: 20px;
    }
    .ad-dir-modal-overlay.is-open { display: flex; }
    .ad-dir-modal {
      background: var(--surface); border-radius: var(--r-lg); max-width: 420px; width: 100%; padding: 22px 24px; box-shadow: var(--shadow-md); border: 1px solid var(--border);
    }
    .ad-dir-modal h3 { margin: 0 0 10px; font-size: 1rem; color: var(--cr-dk); }
    .ad-dir-modal p { margin: 0 0 20px; font-size: .86rem; color: var(--muted); line-height: 1.5; }
    .ad-dir-modal-ft { display: flex; justify-content: flex-end; gap: 10px; }
  </style>
</head>
<body>
<?php include __DIR__ . '/ad_header_universal.php'; ?>
<?php include __DIR__ . '/ad_sidebar_universal.php'; ?>

<main class="admin-main">
  <div class="ad-dir-page">
    <div class="ad-dir-hero">
      <div>
        <h1><i class="fas fa-sitemap" style="color:var(--cr);margin-right:10px;"></i>Alumni Directory</h1>
        <p>Browse registered alumni (excluding pending registrations). Open a profile to review details, ID card, or employment history.</p>
      </div>
      <div class="ad-dir-stat">
        <span><?= number_format($total) ?> <?= $total === 1 ? 'record' : 'records' ?></span>
      </div>
    </div>

    <form method="get" action="ad_alumnidirectory.php" class="ad-dir-toolbar" id="adDirFilterForm">
      <div class="ad-dir-field ad-dir-search-wrap">
        <label for="ad_dir_search">Search</label>
        <i class="fas fa-search" aria-hidden="true"></i>
        <input type="text" name="search" id="ad_dir_search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Name, email, program, year…" autocomplete="off" />
      </div>
      <div class="ad-dir-field">
        <label for="ad_dir_batch">Graduation year</label>
        <select name="batch" id="ad_dir_batch">
          <option value="">All years</option>
          <?php foreach ($yearList as $y): ?>
            <option value="<?= htmlspecialchars($y, ENT_QUOTES, 'UTF-8') ?>" <?= $batch === $y ? 'selected' : '' ?>><?= htmlspecialchars($y, ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="ad-dir-field">
        <label for="ad_dir_program">Program</label>
        <select name="program" id="ad_dir_program">
          <option value="">All programs</option>
          <?php foreach ($programList as $p): ?>
            <option value="<?= htmlspecialchars($p, ENT_QUOTES, 'UTF-8') ?>" <?= $program === $p ? 'selected' : '' ?>><?= htmlspecialchars($p, ENT_QUOTES, 'UTF-8') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="ad-dir-actions">
        <button type="submit" class="ad-dir-btn ad-dir-btn--primary"><i class="fas fa-filter"></i> Apply</button>
        <a href="ad_alumnidirectory.php" class="ad-dir-btn ad-dir-btn--ghost"><i class="fas fa-rotate-left"></i> Reset</a>
        <button type="button" class="ad-dir-btn ad-dir-toggle" id="adDirViewToggle" aria-pressed="false" title="Switch layout">
          <i class="fas fa-table-cells"></i> <span id="adDirViewToggleLabel">Table view</span>
        </button>
      </div>
    </form>

    <?php if ($rows === []): ?>
      <div class="ad-dir-empty">
        <i class="fas fa-user-slash" aria-hidden="true"></i>
        No alumni match your filters. Try clearing search or choosing “All” for program and year.
      </div>
    <?php else: ?>

      <div class="ad-dir-grid" id="adDirGrid">
        <?php foreach ($rows as $row):
            $fn = (string) ($row['firstname'] ?? '');
            $ln = (string) ($row['lastname'] ?? '');
            $dispName = trim($fn . ' ' . $ln);
            $email = (string) ($row['email'] ?? '');
            $prog = (string) ($row['program'] ?? '');
            $yr = (string) ($row['year_graduated'] ?? '');
            $st = (string) ($row['status'] ?? '');
            $id = (int) ($row['id'] ?? 0);
            $photoUrl = ad_dir_photo_url(isset($row['photo']) ? (string) $row['photo'] : '');
            $ini = ad_dir_initials($fn, $ln);
            ?>
          <article class="ad-dir-card">
            <div class="ad-dir-card-top" aria-hidden="true"></div>
            <div class="ad-dir-card-body">
              <div class="ad-dir-avatar">
                <span class="ad-dir-fallback"<?= $photoUrl !== '' ? ' style="display:none"' : '' ?>><?= htmlspecialchars($ini, ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ($photoUrl !== ''): ?>
                  <img src="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" onerror="this.style.display='none';var fb=this.previousElementSibling;if(fb)fb.style.display='flex';" />
                <?php endif; ?>
              </div>
              <h2><?= htmlspecialchars($dispName !== '' ? $dispName : '—', ENT_QUOTES, 'UTF-8') ?></h2>
              <div class="meta"><?= htmlspecialchars($prog !== '' ? $prog : '—', ENT_QUOTES, 'UTF-8') ?></div>
              <div class="batch"><?= $yr !== '' ? 'Batch ' . htmlspecialchars($yr, ENT_QUOTES, 'UTF-8') : '—' ?></div>
              <div style="margin-top:10px;"><span class="<?= htmlspecialchars(ad_dir_badge_class($st), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ad_dir_status_label($st), ENT_QUOTES, 'UTF-8') ?></span></div>
            </div>
            <div class="ad-dir-card-actions">
              <a class="ad-dir-iconbtn" href="ad_viewprofile.php?id=<?= $id ?>" title="View profile"><i class="fas fa-eye"></i></a>
              <a class="ad-dir-iconbtn" href="ad_editprofile.php?id=<?= $id ?>" title="Edit"><i class="fas fa-pen-to-square"></i></a>
              <a class="ad-dir-iconbtn" href="ad_alumni_id_check.php?id=<?= $id ?>" title="ID card preview"><i class="fas fa-id-card"></i></a>
              <button type="button" class="ad-dir-iconbtn ad-dir-iconbtn--danger ad-dir-del" data-del-id="<?= $id ?>" data-del-name="<?= htmlspecialchars($dispName, ENT_QUOTES, 'UTF-8') ?>" title="Delete"><i class="fas fa-trash-can"></i></button>
            </div>
          </article>
        <?php endforeach; ?>
      </div>

      <div class="ad-dir-table-wrap" id="adDirTable">
        <div style="overflow-x:auto;">
          <table>
            <thead>
              <tr>
                <th>Alumni</th>
                <th>Program</th>
                <th>Batch</th>
                <th>Status</th>
                <th style="text-align:right;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $row):
                  $fn = (string) ($row['firstname'] ?? '');
                  $ln = (string) ($row['lastname'] ?? '');
                  $dispName = trim($fn . ' ' . $ln);
                  $email = (string) ($row['email'] ?? '');
                  $prog = (string) ($row['program'] ?? '');
                  $yr = (string) ($row['year_graduated'] ?? '');
                  $st = (string) ($row['status'] ?? '');
                  $id = (int) ($row['id'] ?? 0);
                  $photoUrl = ad_dir_photo_url(isset($row['photo']) ? (string) $row['photo'] : '');
                  $ini = ad_dir_initials($fn, $ln);
                  ?>
                <tr>
                  <td>
                    <div style="display:flex;align-items:center;gap:12px;">
                      <div class="ad-dir-avatar" style="width:44px;height:44px;margin:0;font-size:.85rem;border-width:2px;">
                        <span class="ad-dir-fallback"<?= $photoUrl !== '' ? ' style="display:none"' : '' ?>><?= htmlspecialchars($ini, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if ($photoUrl !== ''): ?>
                          <img src="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy" onerror="this.style.display='none';var fb=this.previousElementSibling;if(fb)fb.style.display='flex';" />
                        <?php endif; ?>
                      </div>
                      <div>
                        <div class="ad-dir-row-name"><?= htmlspecialchars($dispName !== '' ? $dispName : '—', ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="ad-dir-row-sub"><?= htmlspecialchars($email !== '' ? $email : '—', ENT_QUOTES, 'UTF-8') ?></div>
                      </div>
                    </div>
                  </td>
                  <td><?= htmlspecialchars($prog !== '' ? $prog : '—', ENT_QUOTES, 'UTF-8') ?></td>
                  <td style="font-family:'DM Mono',monospace;"><?= htmlspecialchars($yr !== '' ? $yr : '—', ENT_QUOTES, 'UTF-8') ?></td>
                  <td><span class="<?= htmlspecialchars(ad_dir_badge_class($st), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ad_dir_status_label($st), ENT_QUOTES, 'UTF-8') ?></span></td>
                  <td style="text-align:right;">
                    <a class="ad-dir-iconbtn" href="ad_viewprofile.php?id=<?= $id ?>" title="View"><i class="fas fa-eye"></i></a>
                    <a class="ad-dir-iconbtn" href="ad_editprofile.php?id=<?= $id ?>" title="Edit"><i class="fas fa-pen-to-square"></i></a>
                    <a class="ad-dir-iconbtn" href="ad_alumni_id_check.php?id=<?= $id ?>" title="ID card"><i class="fas fa-id-card"></i></a>
                    <button type="button" class="ad-dir-iconbtn ad-dir-iconbtn--danger ad-dir-del" data-del-id="<?= $id ?>" data-del-name="<?= htmlspecialchars($dispName, ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-trash-can"></i></button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    <?php endif; ?>

    <?php
    if ($totalPages > 1):
        $buildPageUrl = static function (int $p) use ($qsKeep): string {
            $q = array_merge($qsKeep, ['page' => $p]);

            return 'ad_alumnidirectory.php?' . http_build_query($q);
        };
        ?>
      <nav class="ad-dir-pagination" aria-label="Pages">
        <?php if ($page > 1): ?>
          <a href="<?= htmlspecialchars($buildPageUrl($page - 1), ENT_QUOTES, 'UTF-8') ?>" aria-label="Previous"><i class="fas fa-chevron-left"></i></a>
        <?php endif; ?>
        <?php
        $from = max(1, $page - 2);
        $to = min($totalPages, $page + 2);
        if ($from > 1) {
            echo '<a href="' . htmlspecialchars($buildPageUrl(1), ENT_QUOTES, 'UTF-8') . '">1</a>';
            if ($from > 2) {
                echo '<span>…</span>';
            }
        }
        for ($i = $from; $i <= $to; $i++) {
            $cls = $i === $page ? ' class="is-current"' : '';
            echo '<a' . $cls . ' href="' . htmlspecialchars($buildPageUrl($i), ENT_QUOTES, 'UTF-8') . '">' . $i . '</a>';
        }
        if ($to < $totalPages) {
            if ($to < $totalPages - 1) {
                echo '<span>…</span>';
            }
            echo '<a href="' . htmlspecialchars($buildPageUrl($totalPages), ENT_QUOTES, 'UTF-8') . '">' . $totalPages . '</a>';
        }
        ?>
        <?php if ($page < $totalPages): ?>
          <a href="<?= htmlspecialchars($buildPageUrl($page + 1), ENT_QUOTES, 'UTF-8') ?>" aria-label="Next"><i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  </div>
</main>

<div class="ad-dir-modal-overlay" id="adDirDeleteModal" role="dialog" aria-modal="true" aria-labelledby="adDirDeleteTitle" aria-hidden="true">
  <div class="ad-dir-modal">
    <h3 id="adDirDeleteTitle">Delete alumni record?</h3>
    <p id="adDirDeleteText">This cannot be undone.</p>
    <div class="ad-dir-modal-ft">
      <button type="button" class="ad-dir-btn ad-dir-btn--ghost" id="adDirDeleteCancel">Cancel</button>
      <button type="button" class="ad-dir-btn ad-dir-btn--primary" id="adDirDeleteConfirm" style="background:#b91c1c;">Delete</button>
    </div>
  </div>
</div>

<script>
(function () {
  var grid = document.getElementById('adDirGrid');
  var table = document.getElementById('adDirTable');
  var btn = document.getElementById('adDirViewToggle');
  var lbl = document.getElementById('adDirViewToggleLabel');
  if (btn && grid && table && lbl) {
    btn.addEventListener('click', function () {
      var showTable = grid.style.display !== 'none';
      if (showTable) {
        grid.style.display = 'none';
        table.classList.add('is-visible');
        lbl.textContent = 'Card view';
        btn.setAttribute('aria-pressed', 'true');
      } else {
        grid.style.display = '';
        table.classList.remove('is-visible');
        lbl.textContent = 'Table view';
        btn.setAttribute('aria-pressed', 'false');
      }
    });
  }

  var modal = document.getElementById('adDirDeleteModal');
  var modalText = document.getElementById('adDirDeleteText');
  var delId = null;
  function openModal(id, name) {
    delId = id;
    if (modalText) modalText.textContent = 'Delete ' + name + '? This removes their registration from the directory and related admin lists.';
    if (modal) { modal.classList.add('is-open'); modal.setAttribute('aria-hidden', 'false'); }
  }
  function closeModal() {
    delId = null;
    if (modal) { modal.classList.remove('is-open'); modal.setAttribute('aria-hidden', 'true'); }
  }
  document.querySelectorAll('.ad-dir-del').forEach(function (b) {
    b.addEventListener('click', function () {
      var id = this.getAttribute('data-del-id');
      var name = this.getAttribute('data-del-name') || 'this member';
      openModal(id, name);
    });
  });
  document.getElementById('adDirDeleteCancel')?.addEventListener('click', closeModal);
  modal?.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
  document.getElementById('adDirDeleteConfirm')?.addEventListener('click', function () {
    if (delId) window.location.href = 'ad_deletealumni.php?id=' + encodeURIComponent(delId);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal?.classList.contains('is-open')) closeModal();
  });
})();
</script>
</body>
</html>
