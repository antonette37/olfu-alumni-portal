<?php
session_start();
require_once 'db_config.php';
require_once __DIR__ . '/privacy_access.php';
$conn = getDBConnection();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: al_login.php');
    exit();
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid alumni ID.";
    header('Location: ad_alumnidirectory.php');
    exit();
}

$alumni_id = (int) $_GET['id'];

// Fetch alumni information (mysqli_query: works without mysqlnd; get_result() often missing on shared hosting)
$resAl = mysqli_query($conn, 'SELECT * FROM itcp WHERE id = ' . $alumni_id);
$alumni = ($resAl instanceof mysqli_result) ? $resAl->fetch_assoc() : null;
if ($resAl instanceof mysqli_result) {
    mysqli_free_result($resAl);
}

// Load privacy settings for audit and access control
$privacy = getPrivacySettings($conn, $alumni_id);

// Load raw privacy row to get updated_at for audit display
$privacyUpdatedAt = null;
$psStmt = $conn->prepare("SELECT updated_at FROM privacy_settings WHERE user_id = ?");
if ($psStmt) {
    $psStmt->bind_param('i', $alumni_id);
    if ($psStmt->execute()) {
        $psStmt->bind_result($privacyUpdatedAt);
        $psStmt->fetch();
    }
    $psStmt->close();
}

// Audit: ensure admin view is logged (if admin id available)
@$conn->query("CREATE TABLE IF NOT EXISTS admin_view_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT DEFAULT 0,
  user_id INT NOT NULL,
  context VARCHAR(64) DEFAULT 'profile_view',
  viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$adminId = isset($_SESSION['admin_id']) ? (int) $_SESSION['admin_id'] : 0;
$logStmt = $conn->prepare("INSERT INTO admin_view_logs (admin_id, user_id, context) VALUES (?, ?, 'profile_view')");
if ($logStmt) {
    $logStmt->bind_param('ii', $adminId, $alumni_id);
    $logStmt->execute();
    $logStmt->close();
}

if (!$alumni) {
    $_SESSION['error'] = "Alumni not found.";
    header('Location: ad_alumnidirectory.php');
    exit();
}

/** @param mixed $v */
function vp_disp($v): string
{
    $t = trim((string) ($v ?? ''));
    return $t === '' ? '—' : htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
}

/**
 * Build serve_id_image.php URLs from DB filenames (alumni_registration + optional itcp.id_image).
 * Does not require files to exist at resolve time — serve_id_image.php locates them under uploads/.
 *
 * @return array{front: string, back: string}
 */
function vp_registration_id_urls_from_db($conn, array $alumni, int $alumni_id): array
{
    $empty = ['front' => '', 'back' => ''];
    if (!$conn instanceof mysqli) {
        return $empty;
    }

    $toUrl = static function (string $raw): string {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (stripos($raw, 'http://') === 0 || stripos($raw, 'https://') === 0) {
            return $raw;
        }
        $bn = basename(str_replace('\\', '/', $raw));
        if ($bn === '' || $bn === '.' || $bn === '..') {
            return '';
        }

        return 'serve_id_image.php?img=' . rawurlencode($bn);
    };

    $front = '';
    $back = '';

    try {
        $tbl = @$conn->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'alumni_registration' LIMIT 1");
        $hasAr = ($tbl && $tbl->num_rows > 0);
        if ($tbl instanceof mysqli_result) {
            $tbl->close();
        }
        if (!$hasAr) {
            $tq = @$conn->query('SELECT 1 FROM `alumni_registration` LIMIT 1');
            $hasAr = ($tq !== false);
            if ($tq instanceof mysqli_result) {
                $tq->close();
            }
        }

        $hasBackCol = false;
        if ($hasAr) {
            $cc = @$conn->query("SHOW COLUMNS FROM `alumni_registration` LIKE 'id_image_back'");
            if ($cc && $cc->num_rows > 0) {
                $hasBackCol = true;
            }
            if ($cc instanceof mysqli_result) {
                $cc->close();
            }
        }

        $sn = trim((string) ($alumni['student_number'] ?? $alumni['student_id'] ?? ''));
        $norm = str_replace(['-', ' '], '', $sn);
        $escSn = $conn->real_escape_string($sn);
        $escNorm = $conn->real_escape_string($norm);
        $cols = $hasBackCol ? '`id_image`, `id_image_back`' : '`id_image`';

        if ($hasAr && $sn !== '') {
            $q = "SELECT {$cols} FROM `alumni_registration` WHERE `student_number` = '{$escSn}' OR REPLACE(REPLACE(TRIM(`student_number`), '-', ''), ' ', '') = '{$escNorm}' ORDER BY `id` DESC LIMIT 1";
            $rq = @$conn->query($q);
            if ($rq && $rq->num_rows > 0) {
                $row = $rq->fetch_assoc();
                if ($rq instanceof mysqli_result) {
                    $rq->close();
                }
                foreach ($row as $k => $v) {
                    if ($v === null || trim((string) $v) === '') {
                        continue;
                    }
                    $lk = strtolower((string) $k);
                    if ($lk === 'id_image') {
                        $front = trim((string) $v);
                    }
                    if ($lk === 'id_image_back') {
                        $back = trim((string) $v);
                    }
                }
            } elseif ($rq instanceof mysqli_result) {
                $rq->close();
            }
        }

        if ($hasAr && $front === '' && $back === '') {
            $full = trim(implode(' ', array_filter([
                (string) ($alumni['firstname'] ?? ''),
                (string) ($alumni['middlename'] ?? ''),
                (string) ($alumni['lastname'] ?? ''),
            ])));
            if ($full !== '') {
                $like = $conn->real_escape_string('%' . $full . '%');
                $q = "SELECT {$cols} FROM `alumni_registration` WHERE `name` LIKE '{$like}' ORDER BY `id` DESC LIMIT 1";
                $rq = @$conn->query($q);
                if ($rq && $rq->num_rows > 0) {
                    $row = $rq->fetch_assoc();
                    if ($rq instanceof mysqli_result) {
                        $rq->close();
                    }
                    foreach ($row as $k => $v) {
                        if ($v === null || trim((string) $v) === '') {
                            continue;
                        }
                        $lk = strtolower((string) $k);
                        if ($lk === 'id_image') {
                            $front = trim((string) $v);
                        }
                        if ($lk === 'id_image_back') {
                            $back = trim((string) $v);
                        }
                    }
                } elseif ($rq instanceof mysqli_result) {
                    $rq->close();
                }
            }
        }

        if ($front === '') {
            $ic = @$conn->query("SHOW COLUMNS FROM `itcp` LIKE 'id_image'");
            $hasItcpImg = ($ic && $ic->num_rows > 0);
            if ($ic instanceof mysqli_result) {
                $ic->close();
            }
            if ($hasItcpImg) {
                $uid = (int) $alumni_id;
                $qr = @$conn->query('SELECT `id_image` FROM `itcp` WHERE `id` = ' . $uid . ' LIMIT 1');
                if ($qr && $qr->num_rows > 0) {
                    $r2 = $qr->fetch_assoc();
                    if ($qr instanceof mysqli_result) {
                        $qr->close();
                    }
                    $v = isset($r2['id_image']) ? trim((string) $r2['id_image']) : '';
                    if ($v !== '') {
                        $front = $v;
                    }
                } elseif ($qr instanceof mysqli_result) {
                    $qr->close();
                }
            }
        }
    } catch (\Throwable $e) {
        if (function_exists('error_log')) {
            @error_log('ad_viewprofile vp_registration_id_urls_from_db: ' . $e->getMessage());
        }
    }

    return [
        'front' => $toUrl($front),
        'back' => $toUrl($back),
    ];
}

/** Same rules as alumni ID card preview (ad_alumni_id_check.php) */
function vp_format_id_card_number(string $raw): string
{
    $d = preg_replace('/\D/', '', $raw);
    if ($d === '') {
        return '';
    }
    $d = str_pad(substr($d, 0, 16), 16, '0');

    return trim(chunk_split($d, 4, ' '));
}

function vp_id_valid_until(?string $yearGrad): string
{
    if ($yearGrad === null || $yearGrad === '') {
        return '';
    }
    $y = (int) preg_replace('/\D/', '', (string) $yearGrad);
    if ($y < 1990 || $y > 2100) {
        return '';
    }

    return 'DECEMBER ' . ($y + 3);
}

function vp_id_display_name(array $row): string
{
    $fn = trim((string) ($row['firstname'] ?? ''));
    $mi = trim((string) ($row['middlename'] ?? ''));
    $ln = trim((string) ($row['lastname'] ?? ''));
    $ext = trim((string) ($row['name_ext'] ?? ''));
    $mid = '';
    if ($mi !== '') {
        $mid = (strlen($mi) <= 3 && strpos($mi, ' ') === false) ? $mi . '.' : $mi;
    }
    $parts = array_filter([$fn, $mid, $ln, $ext]);

    return mb_strtoupper(implode(' ', $parts), 'UTF-8');
}

$__vpRegIds = vp_registration_id_urls_from_db($conn, $alumni, $alumni_id);
$vp_front_url = $__vpRegIds['front'];
$vp_back_url = $__vpRegIds['back'];

$fullName = trim(implode(' ', array_filter([
    (string) ($alumni['firstname'] ?? ''),
    (string) ($alumni['middlename'] ?? ''),
    (string) ($alumni['lastname'] ?? ''),
    (string) ($alumni['name_ext'] ?? ''),
])));

$bday = $alumni['birthday'] ?? $alumni['birth_date'] ?? '';
$studentNum = $alumni['student_number'] ?? $alumni['student_id'] ?? '';
$position = $alumni['position'] ?? $alumni['current_position'] ?? '';
$los = $alumni['length_of_service'] ?? $alumni['years_experience'] ?? '';

$has_survey = !empty($alumni['months_to_get_job'] ?? '') || !empty($alumni['job_aligned'] ?? '')
    || !empty($alumni['college_prepared'] ?? '') || !empty($alumni['important_soft_skill'] ?? '')
    || !empty($alumni['proud_alumni'] ?? '');

$idCardNumber = vp_format_id_card_number(trim((string) ($alumni['student_number'] ?? '')));
if ($idCardNumber === '') {
    $idCardNumber = 'PENDING — NO STUDENT NO.';
}
$idValidUntil = vp_id_valid_until($alumni['year_graduated'] ?? null);
if ($idValidUntil === '') {
    $idValidUntil = '—';
}
$nameOnId = vp_id_display_name($alumni);
if (trim($nameOnId) === '') {
    $nameOnId = '—';
}
$idBatchYear = trim((string) ($alumni['year_graduated'] ?? ''));
if ($idBatchYear === '') {
    $idBatchYear = '—';
}

$photo_src = '';
$photoRaw = trim((string) ($alumni['photo'] ?? ''));
if ($photoRaw !== '') {
    if (strpos($photoRaw, 'http') === 0) {
        $photo_src = $photoRaw;
    } else {
        $photo_src = 'serve_profile_image.php?img=' . rawurlencode(basename($photoRaw));
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Alumni Profile — Admin Portal</title>
    <link rel="icon" href="olfulogo.png" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --cr: #8b0000;
            --cr-dk: #600000;
            --cr-lt: #b91c1c;
            --cr-pale: #fef2f2;
            --ink: #1a0a0a;
            --muted: #7a6a6a;
            --border: #e8dada;
            --surface: #ffffff;
            --bg: #faf7f7;
            --shadow-sm: 0 1px 4px rgba(139,0,0,.07), 0 2px 12px rgba(139,0,0,.05);
            --r: 14px;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body { font-family: 'Sora', -apple-system, sans-serif; background: var(--bg); color: var(--ink); -webkit-font-smoothing: antialiased; margin: 0; }
        a { color: var(--cr); text-decoration: none; font-weight: 600; }
        a:hover { color: var(--cr-dk); text-decoration: underline; }

        .admin-main { margin-left: 64px; padding-top: 64px; min-height: 100vh; transition: margin-left 0.3s cubic-bezier(.4,0,.2,1); }
        #sidebar.ad-universal-sidebar:hover ~ main.admin-main,
        #sidebar.ad-universal-sidebar:focus-within ~ main.admin-main { margin-left: 256px; }
        @media (max-width: 1023px) {
            .admin-main { margin-left: 0 !important; padding-top: 64px; }
        }
        .vp-page { max-width: 1200px; margin: 0 auto; padding: 28px 24px 56px; }

        .vp-id-reg { margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--border); }
        .vp-id-reg h4 { margin: 0 0 10px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); }
        .vp-id-reg-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        @media (max-width: 520px) { .vp-id-reg-grid { grid-template-columns: 1fr; } }
        .vp-id-reg figcaption { font-size: 0.7rem; color: var(--muted); margin-top: 4px; font-weight: 600; }
        .vp-id-reg img { width: 100%; max-height: 140px; object-fit: contain; border-radius: 8px; border: 1px solid var(--border); background: var(--bg); }

        .vp-hero {
            background: linear-gradient(135deg, var(--cr-dk) 0%, var(--cr) 50%, #a00000 100%);
            border-radius: var(--r);
            padding: 24px 28px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
        }
        .vp-hero::before {
            content: '';
            position: absolute; inset: 0;
            background: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.08'/%3E%3C/svg%3E");
            opacity: 0.5;
        }
        .vp-hero-inner { position: relative; z-index: 1; display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .vp-back {
            display: inline-flex; align-items: center; gap: 8px;
            font-size: 0.8rem; font-weight: 600; color: rgba(255,255,255,0.85);
            margin-bottom: 10px;
        }
        .vp-back:hover { color: #fff; text-decoration: none; }
        .vp-hero h1 { font-size: clamp(1.35rem, 2.5vw, 1.75rem); font-weight: 700; color: #fff; margin: 0 0 4px; line-height: 1.2; }
        .vp-hero .vp-sub { font-size: 0.85rem; color: rgba(255,255,255,0.65); margin: 0; }

        .vp-layout {
            display: grid;
            grid-template-columns: minmax(0, 280px) minmax(0, 1fr);
            gap: 24px;
            align-items: start;
        }
        .vp-layout > .vp-bookmarks-bar {
            grid-column: 1 / -1;
        }
        @media (max-width: 1024px) { .vp-layout { grid-template-columns: 1fr; } }

        .vp-card {
            background: var(--surface);
            border-radius: var(--r);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 24px;
        }
        .vp-card:last-child { margin-bottom: 0; }
        .vp-card-head {
            min-height: 48px;
            box-sizing: border-box;
            padding: 14px 20px;
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; gap: 10px;
            font-size: 0.92rem; font-weight: 700; color: var(--ink);
        }
        .vp-card-head i { color: var(--cr); width: 18px; text-align: center; }

        .vp-avatar-wrap {
            padding: 24px 20px;
            text-align: center;
            border-bottom: 1px solid var(--border);
        }
        .vp-avatar {
            width: 140px; height: 140px; border-radius: 50%; margin: 0 auto 14px;
            background: linear-gradient(135deg, var(--cr-pale), #fff);
            border: 3px solid var(--border);
            overflow: hidden; display: flex; align-items: center; justify-content: center;
        }
        .vp-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .vp-avatar .vp-ph { font-size: 3rem; color: var(--cr); }
        .vp-name { font-size: 1.1rem; font-weight: 700; color: var(--ink); margin: 0 0 4px; line-height: 1.3; }
        .vp-program { font-size: 0.82rem; color: var(--muted); margin: 0 0 16px; }

        .vp-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.84rem;
            table-layout: fixed;
        }
        /* Same label column width (px) in sidebar + main so tables line up visually */
        .vp-table th {
            text-align: left;
            padding: 10px 18px;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
            background: var(--bg);
            border-bottom: 1px solid var(--border);
            width: 140px;
            vertical-align: top;
            font-family: 'Sora', sans-serif;
            box-sizing: border-box;
            line-height: 1.4;
            word-break: break-word;
        }
        .vp-table td {
            padding: 10px 18px;
            border-bottom: 1px solid rgba(232,218,218,0.55);
            color: var(--ink);
            vertical-align: top;
            word-break: break-word;
            line-height: 1.45;
            width: auto;
            min-width: 0;
            box-sizing: border-box;
        }
        .vp-table tr:last-child th, .vp-table tr:last-child td { border-bottom: none; }

        .vp-badge {
            display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 100px;
            font-size: 0.68rem; font-weight: 700; text-transform: capitalize;
        }
        .vp-badge-active { background: #f0fdf4; color: #059669; }
        .vp-badge-pending { background: #fef3c7; color: #d97706; }
        .vp-badge-other { background: #f3f4f6; color: #4b5563; }

        .vp-callout {
            margin-top: 20px; padding: 14px 18px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: var(--r);
            font-size: 0.82rem;
        }
        .vp-callout-title { font-weight: 700; color: var(--ink); margin-bottom: 8px; font-size: 0.85rem; }
        .vp-mono { font-family: 'DM Mono', monospace; font-size: 0.78rem; }

        .vp-bookmarks-bar {
            display: flex;
            align-items: flex-end;
            justify-content: flex-end;
            gap: 10px;
            margin-bottom: 14px;
            min-height: 36px;
            position: relative;
            z-index: 5;
            pointer-events: auto;
        }
        .vp-bookmarks {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: flex-end;
            max-width: 100%;
            pointer-events: auto;
        }
        .vp-bookmark {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 10px 10px 4px 4px;
            font-size: 0.76rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
            pointer-events: auto;
            position: relative;
            z-index: 1;
            border: 1px solid var(--border);
            background: linear-gradient(180deg, #fff 0%, var(--bg) 100%);
            color: var(--muted);
            box-shadow: 0 1px 0 rgba(139,0,0,.04);
            transition: background .2s, color .2s, border-color .2s, box-shadow .2s, transform .15s;
        }
        .vp-bookmark:hover {
            color: var(--ink);
            border-color: rgba(139,0,0,.35);
            background: #fff;
        }
        .vp-bookmark.is-active {
            color: #fff;
            background: linear-gradient(180deg, var(--cr) 0%, var(--cr-dk) 100%);
            border-color: var(--cr-dk);
            box-shadow: 0 3px 12px rgba(139,0,0,.22);
            transform: translateY(-1px);
        }
        .vp-bookmark i { font-size: 0.7rem; opacity: 0.9; }

        .vp-step { display: none; }
        .vp-step.is-active { display: block; animation: vpFade .22s ease; }
        @keyframes vpFade { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }

        .vp-id-visual {
            padding: 16px 18px 18px;
            border-top: 1px solid var(--border);
            background: linear-gradient(180deg, #fafafa 0%, #fff 100%);
        }
        .vp-id-visual-title {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
            margin: 0 0 12px;
        }
        .vp-id-visual .alumni-id-cards-preview { overflow-x: auto; }
    </style>
</head>
<body>
<?php include __DIR__ . '/ad_header_universal.php'; ?>
<?php include __DIR__ . '/ad_sidebar_universal.php'; ?>

<?php
$st = strtolower(trim((string) ($alumni['status'] ?? '')));
$badgeClass = ($st === 'active' || $st === 'approved') ? 'vp-badge-active' : ($st === 'pending' ? 'vp-badge-pending' : 'vp-badge-other');
?>

<main class="admin-main">
<div class="vp-page">
    <div class="vp-hero">
        <div class="vp-hero-inner">
            <div>
                <a href="ad_alumnidirectory.php" class="vp-back"><i class="fas fa-arrow-left"></i> Back to directory</a>
                <h1>Alumni profile</h1>
                <p class="vp-sub"><?= vp_disp($fullName !== '' ? $fullName : 'Member') ?> · ID #<?= (int) $alumni_id ?></p>
            </div>
        </div>
    </div>

    <div class="vp-layout">
        <div class="vp-bookmarks-bar">
            <div class="vp-bookmarks" id="vpBookmarks" role="tablist" aria-label="Profile sections">
                <button type="button" class="vp-bookmark is-active" role="tab" id="vp-tab-1" aria-selected="true" aria-controls="vp-step-1" tabindex="0" data-vp-tab="1" onclick="if (window.__vpGoTab) { window.__vpGoTab(1); } return false;">
                    <i class="fas fa-id-card" aria-hidden="true"></i> Personal
                </button>
                <button type="button" class="vp-bookmark" role="tab" id="vp-tab-2" aria-selected="false" aria-controls="vp-step-2" tabindex="-1" data-vp-tab="2" onclick="if (window.__vpGoTab) { window.__vpGoTab(2); } return false;">
                    <i class="fas fa-graduation-cap" aria-hidden="true"></i> Academic
                </button>
                <button type="button" class="vp-bookmark" role="tab" id="vp-tab-3" aria-selected="false" aria-controls="vp-step-3" tabindex="-1" data-vp-tab="3" onclick="if (window.__vpGoTab) { window.__vpGoTab(3); } return false;">
                    <i class="fas fa-briefcase" aria-hidden="true"></i> Employment
                </button>
                <button type="button" class="vp-bookmark" role="tab" id="vp-tab-4" aria-selected="false" aria-controls="vp-step-4" tabindex="-1" data-vp-tab="4" onclick="if (window.__vpGoTab) { window.__vpGoTab(4); } return false;">
                    <i class="fas fa-user-lock" aria-hidden="true"></i> Privacy
                </button>
                <button type="button" class="vp-bookmark" role="tab" id="vp-tab-5" aria-selected="false" aria-controls="vp-step-5" tabindex="-1" data-vp-tab="5" onclick="if (window.__vpGoTab) { window.__vpGoTab(5); } return false;">
                    <i class="fas fa-address-card" aria-hidden="true"></i> Alumni ID
                </button>
            </div>
        </div>
        <aside>
            <div class="vp-card">
                <div class="vp-avatar-wrap">
                    <div class="vp-avatar">
                        <?php if ($photo_src !== ''): ?>
                            <img src="<?= htmlspecialchars($photo_src, ENT_QUOTES, 'UTF-8') ?>" alt=""
                                 onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
                            <span class="vp-ph" style="display:none;"><i class="fas fa-user"></i></span>
                        <?php else: ?>
                            <span class="vp-ph"><i class="fas fa-user"></i></span>
                        <?php endif; ?>
                    </div>
                    <p class="vp-name"><?= vp_disp($fullName !== '' ? $fullName : '—') ?></p>
                    <p class="vp-program"><?= vp_disp($alumni['program'] ?? '') ?></p>
                </div>
                <table class="vp-table">
                    <tbody>
                        <tr><th>Email</th><td><?= vp_disp($alumni['email'] ?? '') ?></td></tr>
                        <tr><th>Personal contact</th><td><?= vp_disp($alumni['personal_contact'] ?? '') ?></td></tr>
                        <tr><th>Status</th><td><?= $st !== '' ? '<span class="vp-badge ' . $badgeClass . '">' . vp_disp(ucfirst($st)) . '</span>' : '—' ?></td></tr>
                        <tr><th>Date joined</th><td class="vp-mono"><?= !empty($alumni['date_joined']) ? htmlspecialchars(date('M d, Y · g:i A', strtotime((string) $alumni['date_joined'])), ENT_QUOTES, 'UTF-8') : '—' ?></td></tr>
                    </tbody>
                </table>
                <div class="vp-id-reg">
                    <h4>Registration ID uploads</h4>
                    <?php if ($vp_front_url === '' && $vp_back_url === ''): ?>
                        <p class="vp-muted" style="margin:0;font-size:0.85rem;color:var(--muted);">—</p>
                    <?php else: ?>
                        <div class="vp-id-reg-grid">
                            <?php if ($vp_front_url !== ''): ?>
                                <figure style="margin:0;">
                                    <a href="<?= htmlspecialchars($vp_front_url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" title="Open full size">
                                        <img src="<?= htmlspecialchars($vp_front_url, ENT_QUOTES, 'UTF-8') ?>" alt="ID front from registration">
                                    </a>
                                    <figcaption>Front</figcaption>
                                </figure>
                            <?php endif; ?>
                            <?php if ($vp_back_url !== ''): ?>
                                <figure style="margin:0;">
                                    <a href="<?= htmlspecialchars($vp_back_url, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" title="Open full size">
                                        <img src="<?= htmlspecialchars($vp_back_url, ENT_QUOTES, 'UTF-8') ?>" alt="ID back from registration">
                                    </a>
                                    <figcaption>Back</figcaption>
                                </figure>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </aside>

        <div class="vp-main">
            <div class="vp-step is-active" data-step="1" id="vp-step-1" role="tabpanel" aria-labelledby="vp-tab-1" aria-hidden="false">
            <div class="vp-card" style="margin-bottom:0;">
                <div class="vp-card-head"><i class="fas fa-id-card"></i> Personal information</div>
                <table class="vp-table">
                    <tbody>
                        <tr><th>First name</th><td><?= vp_disp($alumni['firstname'] ?? '') ?></td></tr>
                        <tr><th>Last name</th><td><?= vp_disp($alumni['lastname'] ?? '') ?></td></tr>
                        <tr><th>Middle name</th><td><?= vp_disp($alumni['middlename'] ?? '') ?></td></tr>
                        <tr><th>Name extension</th><td><?= vp_disp($alumni['name_ext'] ?? '') ?></td></tr>
                        <tr><th>Gender</th><td><?= vp_disp($alumni['gender'] ?? '') ?></td></tr>
                        <tr><th>Birthday</th><td><?= vp_disp($bday) ?></td></tr>
                        <tr><th>Age</th><td><?= vp_disp($alumni['age'] ?? '') ?></td></tr>
                        <tr><th>Civil status</th><td><?= vp_disp($alumni['civil_status'] ?? '') ?></td></tr>
                        <tr><th>Religion</th><td><?= vp_disp($alumni['religion'] ?? '') ?></td></tr>
                        <tr><th>Nationality</th><td><?= vp_disp($alumni['nationality'] ?? '') ?></td></tr>
                        <tr><th>Address</th><td><?= vp_disp($alumni['address'] ?? '') ?></td></tr>
                        <tr><th>Emergency contact</th><td><?= vp_disp($alumni['emergency_contact'] ?? '') ?></td></tr>
                    </tbody>
                </table>
            </div>
            </div>

            <div class="vp-step" data-step="2" id="vp-step-2" role="tabpanel" aria-labelledby="vp-tab-2" aria-hidden="true">
            <div class="vp-card" style="margin-bottom:0;">
                <div class="vp-card-head"><i class="fas fa-graduation-cap"></i> Academic information</div>
                <table class="vp-table">
                    <tbody>
                        <tr><th>Student / Alumni number</th><td class="vp-mono"><?= vp_disp($studentNum) ?></td></tr>
                        <tr><th>College</th><td><?= vp_disp($alumni['college'] ?? '') ?></td></tr>
                        <tr><th>Program</th><td><?= vp_disp($alumni['program'] ?? '') ?></td></tr>
                        <tr><th>Campus</th><td><?= vp_disp($alumni['campus'] ?? '') ?></td></tr>
                        <tr><th>Year graduated</th><td><?= vp_disp($alumni['year_graduated'] ?? '') ?></td></tr>
                        <tr><th>Month graduated</th><td><?= vp_disp($alumni['month_graduated'] ?? '') ?></td></tr>
                        <tr><th>Post grad</th><td><?= vp_disp($alumni['post_grad'] ?? '') ?></td></tr>
                        <tr><th>Licensure exam</th><td><?= vp_disp($alumni['licensure_exam'] ?? '') ?></td></tr>
                        <tr><th>Club involvement</th><td><?= vp_disp($alumni['club_involvement'] ?? '') ?></td></tr>
                    </tbody>
                </table>
            </div>
            </div>

            <div class="vp-step" data-step="3" id="vp-step-3" role="tabpanel" aria-labelledby="vp-tab-3" aria-hidden="true">
            <div class="vp-card" style="margin-bottom:0;">
                <div class="vp-card-head"><i class="fas fa-briefcase"></i> Employment information</div>
                <table class="vp-table">
                    <tbody>
                        <tr><th>Employment status</th><td><?= vp_disp($alumni['employment_status'] ?? '') ?></td></tr>
                        <tr><th>Company</th><td><?= vp_disp($alumni['company'] ?? '') ?></td></tr>
                        <tr><th>Industry</th><td><?= vp_disp($alumni['industry'] ?? '') ?></td></tr>
                        <tr><th>Position</th><td><?= vp_disp($position) ?></td></tr>
                        <tr><th>Length of service</th><td><?= vp_disp($los) ?></td></tr>
                        <tr><th>Previous role</th><td><?= vp_disp($alumni['previous_role'] ?? '') ?></td></tr>
                        <tr><th>Employment history</th><td><?= vp_disp($alumni['employment_history'] ?? '') ?></td></tr>
                        <?php if (isset($alumni['consent'])): ?>
                        <tr><th>Registration consent</th><td><?= !empty($alumni['consent']) ? 'Yes' : 'No' ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($has_survey): ?>
                <div class="vp-card-head" style="border-top:1px solid var(--border);"><i class="fas fa-comments"></i> Career &amp; feedback (registration)</div>
                <table class="vp-table">
                    <tbody>
                        <?php if (!empty($alumni['months_to_get_job'])): ?>
                        <tr><th>Months to get job</th><td><?= vp_disp($alumni['months_to_get_job']) ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($alumni['job_aligned'])): ?>
                        <tr><th>Job aligned with degree</th><td><?= vp_disp($alumni['job_aligned']) ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($alumni['college_prepared'])): ?>
                        <tr><th>College prepared you</th><td><?= vp_disp($alumni['college_prepared']) ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($alumni['important_soft_skill'])): ?>
                        <tr><th>Important soft skill</th><td><?= vp_disp($alumni['important_soft_skill']) ?></td></tr>
                        <?php endif; ?>
                        <?php if (!empty($alumni['proud_alumni'])): ?>
                        <tr><th>Proud alumni</th><td><?= vp_disp($alumni['proud_alumni']) ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <div class="vp-callout" style="margin: 0; border-radius: 0; border-left: none; border-right: none; border-bottom: none;">
                    <div class="vp-callout-title"><i class="fas fa-shield-halved" style="color:var(--cr);margin-right:6px;"></i> Sensitive data (admin)</div>
                    <table class="vp-table">
                        <tbody>
                            <tr>
                                <th>Salary range <span style="font-weight:600;font-size:0.6rem;color:var(--muted);">(privacy)</span></th>
                                <td>
                                    <?php if (canAdminSeeSalary($privacy)): ?>
                                        <?= vp_disp($alumni['salary_range'] ?? '') ?>
                                        <span style="display:block;margin-top:6px;font-size:0.72rem;color:#059669;"><i class="fas fa-lock-open"></i> Visibility: <?= vp_disp($privacy['salary_visibility'] ?? '—') ?></span>
                                    <?php else: ?>
                                        <span style="font-size:0.8rem;color:var(--muted);"><i class="fas fa-lock"></i> Redacted by user privacy setting</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p style="margin:12px 0 0;font-size:0.72rem;color:var(--muted);">
                        <a href="ad_reports.php">Compliance dashboard</a>
                        <span style="margin:0 8px;">·</span>
                        Aggregated reporting uses consented data only.
                    </p>
                </div>
            </div>
            </div>

            <div class="vp-step" data-step="4" id="vp-step-4" role="tabpanel" aria-labelledby="vp-tab-4" aria-hidden="true">
            <div class="vp-card" style="margin-bottom:0;">
                <div class="vp-card-head" style="justify-content:space-between;flex-wrap:wrap;gap:8px;">
                    <span style="display:flex;align-items:center;gap:10px;"><i class="fas fa-user-lock"></i> Privacy settings (read-only)</span>
                    <span class="vp-mono" style="font-weight:500;color:var(--muted);">Updated: <?= htmlspecialchars($privacyUpdatedAt ?: '—', ENT_QUOTES, 'UTF-8') ?></span>
                </div>
                <table class="vp-table">
                    <tbody>
                        <tr><th>Salary visibility</th><td><?= vp_disp($privacy['salary_visibility'] ?? 'Private') ?></td></tr>
                        <tr><th>Aggregated statistical consent</th><td><?= !empty($privacy['salary_aggregated_consent']) ? '<span style="color:#059669;font-weight:600;">Yes</span>' : '<span>No</span>' ?></td></tr>
                        <tr><th>Contact visibility</th><td><?= vp_disp($privacy['contact_visibility'] ?? 'Private') ?></td></tr>
                        <tr><th>Employment visibility</th><td><?= vp_disp($privacy['employment_visibility'] ?? 'Admin Only') ?></td></tr>
                        <tr><th>Photo / story visibility</th><td><?= vp_disp($privacy['photo_visibility'] ?? 'Admin Only') ?></td></tr>
                    </tbody>
                </table>
                <p style="margin:0;padding:12px 18px 16px;font-size:0.72rem;color:var(--muted);border-top:1px solid var(--border);">
                    Reflects the alumnus’s choices. Admins cannot change these settings. Access to sensitive fields is governed by these values and is logged for compliance.
                </p>
            </div>
            </div>

            <div class="vp-step" data-step="5" id="vp-step-5" role="tabpanel" aria-labelledby="vp-tab-5" aria-hidden="true">
            <div class="vp-card" style="margin-bottom:0;">
                <div class="vp-card-head"><i class="fas fa-address-card"></i> Alumni ID</div>
                <table class="vp-table">
                    <tbody>
                        <tr><th>Record ID</th><td class="vp-mono"><?= (int) $alumni_id ?></td></tr>
                        <tr><th>Name (on card)</th><td><?= vp_disp($nameOnId) ?></td></tr>
                        <tr><th>Card number</th><td class="vp-mono"><?= vp_disp($idCardNumber) ?></td></tr>
                        <tr><th>Batch year</th><td><?= vp_disp($idBatchYear) ?></td></tr>
                        <tr><th>Valid until</th><td class="vp-mono"><?= vp_disp($idValidUntil) ?></td></tr>
                        <tr>
                            <th>Print / full page</th>
                            <td><a href="ad_alumni_id_check.php?id=<?= (int) $alumni_id ?>" target="_blank" rel="noopener noreferrer">Open alumni ID card preview</a></td>
                        </tr>
                    </tbody>
                </table>
                <div class="vp-id-visual">
                    <p class="vp-id-visual-title">Visual preview</p>
                    <?php
                    $fnAl = trim((string) ($alumni['firstname'] ?? ''));
                    $lnAl = trim((string) ($alumni['lastname'] ?? ''));
                    if ($fnAl !== '' || $lnAl !== '') {
                        $idPreviewIni = strtoupper(mb_substr($fnAl, 0, 1, 'UTF-8') . mb_substr($lnAl, 0, 1, 'UTF-8'));
                    } else {
                        $idPreviewIni = '?';
                    }
                    $idPvProg = trim((string) ($alumni['program'] ?? ''));
                    if ($idPvProg === '') {
                        $idPvProg = '—';
                    }
                    $idPvBatch = trim((string) ($alumni['year_graduated'] ?? ''));
                    if ($idPvBatch === '') {
                        $idPvBatch = '—';
                    }
                    $idPvAddr = trim((string) ($alumni['address'] ?? ''));
                    if ($idPvAddr === '') {
                        $idPvAddr = '—';
                    }
                    $idPvContact = trim((string) ($alumni['personal_contact'] ?? ''));
                    if ($idPvContact === '') {
                        $idPvContact = '—';
                    }
                    $idPvEmerg = trim((string) ($alumni['emergency_contact'] ?? ''));
                    if ($idPvEmerg === '') {
                        $idPvEmerg = '—';
                    }
                    if (function_exists('render_alumni_id_cards')) {
                        render_alumni_id_cards([
                            'photoSrc' => $photo_src,
                            'idInitials' => $idPreviewIni,
                            'fullName' => vp_id_display_name($alumni),
                            'cardFormatted' => $idCardNumber,
                            'program' => $idPvProg,
                            'batchYear' => $idPvBatch,
                            'validUntil' => $idValidUntil,
                            'address' => $idPvAddr,
                            'contact' => $idPvContact,
                            'emergency' => $idPvEmerg,
                        ]);
                    } else {
                        echo '<p class="vp-muted" style="margin:0;padding:12px 18px;">Visual preview unavailable: re-upload the latest <code>db_config.php</code> from your project.</p>';
                    }
                    ?>
                </div>
            </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var TOTAL = 5;
    var steps = [];
    var tabs = [];
    var i;
    for (i = 1; i <= TOTAL; i++) {
        steps.push(document.getElementById('vp-step-' + i));
        tabs.push(document.getElementById('vp-tab-' + i));
    }

    function go(n) {
        if (n < 1 || n > TOTAL) return;
        var idx;
        for (idx = 0; idx < steps.length; idx++) {
            var on = idx === n - 1;
            if (steps[idx]) {
                steps[idx].classList.toggle('is-active', on);
                steps[idx].setAttribute('aria-hidden', on ? 'false' : 'true');
            }
            if (tabs[idx]) {
                tabs[idx].classList.toggle('is-active', on);
                tabs[idx].setAttribute('aria-selected', on ? 'true' : 'false');
                tabs[idx].setAttribute('tabindex', on ? '0' : '-1');
            }
        }
        var active = document.getElementById('vp-step-' + n);
        if (active && typeof active.scrollIntoView === 'function') {
            active.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    function tabNumFromBtn(btn) {
        if (!btn || !btn.id) return -1;
        var m = /^vp-tab-(\d+)$/.exec(btn.id);
        return m ? parseInt(m[1], 10) : -1;
    }

    window.__vpGoTab = function (n) {
        go(n);
    };

    var bar = document.getElementById('vpBookmarks');
    if (bar) {
        bar.addEventListener('keydown', function (ev) {
            var btn = ev.target;
            if (!btn || btn.tagName !== 'BUTTON' || !btn.classList.contains('vp-bookmark')) return;
            var idx = tabNumFromBtn(btn) - 1;
            if (idx < 0) return;
            var k = ev.key;
            var next = idx;
            if (k === 'ArrowRight' || k === 'ArrowDown') {
                next = (idx + 1) % TOTAL;
                ev.preventDefault();
            } else if (k === 'ArrowLeft' || k === 'ArrowUp') {
                next = (idx - 1 + TOTAL) % TOTAL;
                ev.preventDefault();
            } else if (k === 'Home') {
                next = 0;
                ev.preventDefault();
            } else if (k === 'End') {
                next = TOTAL - 1;
                ev.preventDefault();
            } else {
                return;
            }
            go(next + 1);
            if (tabs[next]) tabs[next].focus();
        });
    }
    go(1);
})();
</script>
</main>
</body>
</html>

<?php
if (isset($stmt) && $stmt instanceof mysqli_stmt) {
    $stmt->close();
}
$conn->close();
?>
