<?php
/**
 * Admin: preview alumni ID card using data from registration (itcp).
 * No strict_types: shared hosting often returns mixed column types from MySQL.
 * Sets $hide_admin_inquiries so ad_header_universal skips optional contact/inquiry queries.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$hide_admin_inquiries = true;

require_once __DIR__ . '/db_config.php';

/** Some hosts lack mbstring; avoid fatals on UTF-8 names (best-effort without mbstring). */
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper($string, $encoding = null)
    {
        return strtoupper((string) $string);
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr($str, $start, $length = null, $encoding = null)
    {
        $str = (string) $str;
        return $length === null ? substr($str, (int) $start) : substr($str, (int) $start, (int) $length);
    }
}

if (!function_exists('id_check_h')) {
    function id_check_h($s)
    {
        $flags = ENT_QUOTES;
        if (defined('ENT_SUBSTITUTE')) {
            $flags |= ENT_SUBSTITUTE;
        }

        return htmlspecialchars((string) $s, $flags, 'UTF-8');
    }
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: al_login.php');
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Invalid user ID.';
    header('Location: ad_user_management.php');
    exit();
}

$userId = (int) $_GET['id'];
if ($userId < 1) {
    $_SESSION['error'] = 'Invalid user ID.';
    header('Location: ad_user_management.php');
    exit();
}

/*
 * Card HTML: db_config.php loads ad_alumni_id_cards_snippet.php / includes/alumni_id_cards_embed.php
 * when present, or defines render_alumni_id_cards() inline — upload latest db_config.php if this errors.
 */
if (!function_exists('render_alumni_id_cards')) {
    foreach (
        [
            __DIR__ . '/includes/alumni_id_cards_embed.php',
            __DIR__ . '/Includes/alumni_id_cards_embed.php',
            __DIR__ . '/ad_alumni_id_cards_snippet.php',
        ] as $_olfu_emb
    ) {
        if (is_file($_olfu_emb)) {
            require_once $_olfu_emb;
            break;
        }
    }
    unset($_olfu_emb);
}
if (!function_exists('render_alumni_id_cards')) {
    function render_alumni_id_cards($card = [])
    {
        echo '<p class="idcheck-note" role="alert">ID card preview is unavailable. Upload the ID card embed file to restore this section.</p>';
    }
}

$conn = getDBConnection();

/*
 * Use mysqli_query + int id — works without mysqlnd. mysqli_stmt::get_result() is often
 * missing on shared hosting and causes HTTP 500.
 */
$res = mysqli_query($conn, 'SELECT * FROM itcp WHERE id = ' . $userId);
if ($res === false) {
    $_SESSION['error'] = 'Database error while loading alumni record.';
    header('Location: ad_user_management.php');
    exit();
}
$alumni = ($res instanceof mysqli_result) ? $res->fetch_assoc() : null;
if ($res instanceof mysqli_result) {
    mysqli_free_result($res);
}

if (!$alumni) {
    $_SESSION['error'] = 'Alumni record not found.';
    header('Location: ad_user_management.php');
    exit();
}

$rowBuilder = __DIR__ . '/includes/alumni_id_card_preview_row.php';
if (is_file($rowBuilder)) {
    require_once $rowBuilder;
}
unset($rowBuilder);

if (function_exists('alumni_id_card_row_to_render_array')) {
    $card = alumni_id_card_row_to_render_array($alumni, false);
} else {
    // Fallback mapping so Check ID remains accessible even if helper include is missing.
    $fn = trim((string) ($alumni['firstname'] ?? ''));
    $ln = trim((string) ($alumni['lastname'] ?? ''));
    $photo = trim((string) ($alumni['photo'] ?? ''));
    $photoSrc = '';
    if ($photo !== '') {
        $photoSrc = (strpos($photo, 'http') === 0)
            ? $photo
            : 'serve_profile_image.php?img=' . rawurlencode(basename($photo));
    }
    $card = [
        'photoSrc' => $photoSrc,
        'idInitials' => strtoupper(substr($fn, 0, 1) . substr($ln, 0, 1)) ?: '?',
        'fullName' => strtoupper(trim($fn . ' ' . trim((string) ($alumni['middlename'] ?? '')) . ' ' . $ln . ' ' . trim((string) ($alumni['name_ext'] ?? '')))),
        'cardFormatted' => trim((string) ($alumni['student_number'] ?? '')) !== '' ? trim(chunk_split(str_pad(substr(preg_replace('/\D/', '', (string) ($alumni['student_number'] ?? '')), 0, 16), 16, '0'), 4, ' ')) : 'PENDING — NO STUDENT NO.',
        'program' => trim((string) ($alumni['program'] ?? '')) ?: '—',
        'batchYear' => trim((string) ($alumni['year_graduated'] ?? '')) ?: '—',
        'validUntil' => '—',
        'address' => trim((string) ($alumni['address'] ?? '')) ?: '—',
        'contact' => trim((string) ($alumni['personal_contact'] ?? '')) ?: '—',
        'emergency' => trim((string) ($alumni['emergency_contact'] ?? '')) ?: '—',
        'signatureSrc' => '',
    ];
}
$photoSrc = $card['photoSrc'];
$idInitials = $card['idInitials'];
$fullName = $card['fullName'];
$cardFormatted = $card['cardFormatted'];
$program = $card['program'];
$batchYear = $card['batchYear'];
$validUntil = $card['validUntil'];
$address = $card['address'];
$contact = $card['contact'];
$emergency = $card['emergency'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Alumni ID Check — Admin Portal</title>
  <link rel="icon" href="olfulogo.png" type="image/png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="admin_page_patches.css" />
  <style>
    /* Layout: match other admin pages; sidebar uses Tailwind — load tailwindcss above */
    body.admin-skin {
      font-family: 'Sora', -apple-system, sans-serif;
      background: var(--bg, #faf7f7);
      color: var(--ink, #1a0a0a);
      margin: 0;
      -webkit-font-smoothing: antialiased;
    }

    .idcheck-wrap { max-width: 1100px; margin: 0 auto; padding: 24px 20px 48px; }
    .idcheck-back {
      display: inline-flex; align-items: center; gap: 8px; font-size: 0.82rem; font-weight: 600;
      color: #0d3d22; margin-bottom: 16px; text-decoration: none;
    }
    .idcheck-back:hover { text-decoration: underline; }
    .idcheck-note {
      font-size: 0.78rem; color: #5a6b61; margin-bottom: 20px; padding: 12px 14px;
      background: #f0f7f2; border: 1px solid rgba(13,61,34,.15); border-radius: 10px;
    }

    /* Scoped reset — do not use global * { margin:0 } or it breaks the universal sidebar (Tailwind) */
    .idcheck-page,
    .idcheck-page *,
    .idcheck-page *::before,
    .idcheck-page *::after {
      box-sizing: border-box;
    }
    .idcheck-page {
      font-family: 'Segoe UI', Arial, sans-serif;
      background: #f0f2f5;
      color: #1a1a1a;
      border-radius: 12px;
      overflow: hidden;
      border: 1px solid #e0e0e0;
    }

    .page-header {
      background: #0d3d22;
      color: #fff;
      padding: 18px 32px;
      display: flex;
      align-items: center;
      gap: 14px;
      border-bottom: 3px solid #FFD700;
    }
    .page-header .seal { width: 44px; height: 44px; flex-shrink: 0; }
    .page-header h1 { font-size: 18px; font-weight: 700; letter-spacing: .4px; }
    .page-header p { font-size: 12px; color: rgba(255,255,255,.65); margin-top: 2px; }

    .idcheck-grid {
      max-width: 1100px;
      margin: 0 auto;
      padding: 28px 24px;
      display: grid;
      grid-template-columns: 380px 1fr;
      gap: 28px;
      align-items: start;
    }
    @media (max-width: 960px) {
      .idcheck-grid { grid-template-columns: 1fr; }
    }

    .form-panel {
      background: #fff;
      border-radius: 12px;
      border: 1px solid #e0e0e0;
      padding: 24px;
    }
    .form-panel h2 {
      font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em;
      color: #0d3d22; margin-bottom: 18px; padding-bottom: 10px; border-bottom: 1px solid #e8e8e8;
    }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .form-group { display: flex; flex-direction: column; gap: 5px; }
    .form-group.full { grid-column: 1 / -1; }
    .form-group label {
      font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: #555;
    }
    .idcheck-value {
      font-size: 13px; padding: 8px 10px; border: 1px solid #e0e0e0; border-radius: 8px;
      background: #f4f4f4; color: #1a1a1a; width: 100%; line-height: 1.35;
      user-select: text;
    }

    .idcheck-photo-block { margin-bottom: 8px; }
    .idcheck-photo-circle {
      width: 112px; height: 112px; border-radius: 50%;
      margin: 0 auto 8px;
      overflow: hidden;
      border: 3px solid #c8d8c8;
      background: linear-gradient(145deg, #eef6f0, #fff);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .idcheck-photo-circle img {
      width: 100%; height: 100%; object-fit: cover;
    }
    .idcheck-photo-ph {
      width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
      color: #1a6b45; font-size: 2.25rem;
    }
    .idcheck-photo-hint {
      font-size: 10px; color: #888; text-align: center; margin: 0 0 4px;
    }

    .btn-row { display: flex; gap: 10px; margin-top: 20px; flex-wrap: wrap; }
    .btn {
      flex: 1; min-width: 140px; padding: 10px 16px; border-radius: 7px; font-size: 13px; font-weight: 600;
      cursor: pointer; border: 1.5px solid #0d3d22; background: #0d3d22; color: #FFD700;
      transition: all .2s; letter-spacing: .02em;
    }
    .btn:hover { background: #145530; border-color: #145530; }

    .preview-panel { display: flex; flex-direction: column; gap: 24px; }
    .preview-panel h2 {
      font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em;
      color: #555; margin-bottom: 4px;
    }

    @media print {
      body { background: #fff; }
      #adminHeader, #sidebar, .idcheck-wrap > .idcheck-back, .idcheck-note, .page-header, .form-panel, .card-label, .preview-panel h2 { display: none !important; }
      .admin-main { margin: 0 !important; padding: 0 !important; }
      .idcheck-page .idcheck-grid { display: block; padding: 0; }
      .preview-panel { display: block; }
      .cards-wrapper { display: flex; flex-direction: column; gap: 16px; padding: 20px; }
      .id-card { page-break-inside: avoid; }
    }
  </style>
</head>
<body class="admin-skin">
<?php include __DIR__ . '/ad_header_universal.php'; ?>
<?php include __DIR__ . '/ad_sidebar_universal.php'; ?>

<main class="admin-main">
  <div class="idcheck-wrap">
    <a href="ad_user_management.php" class="idcheck-back"><i class="fas fa-arrow-left"></i> Back to User Management</a>
    <p class="idcheck-note">
      <strong>Check ID</strong> — All details below are <strong>read-only</strong> and reflect what the alumnus submitted at registration. Admins cannot change them here. Use Print to verify the physical ID layout.
    </p>

    <div class="idcheck-page">
      <header class="page-header">
        <svg class="seal" viewBox="0 0 44 44" fill="none" aria-hidden="true">
          <circle cx="22" cy="22" r="20" stroke="#FFD700" stroke-width="1.5" fill="none"/>
          <circle cx="22" cy="22" r="15" stroke="#FFD700" stroke-width=".8" fill="none"/>
          <circle cx="22" cy="22" r="9"  stroke="#FFD700" stroke-width=".8" fill="none"/>
          <text x="22" y="16"  text-anchor="middle" font-size="3.8" fill="#FFD700" font-weight="700" font-family="Arial">OUR LADY</text>
          <text x="22" y="20"  text-anchor="middle" font-size="3.8" fill="#FFD700" font-weight="700" font-family="Arial">OF FATIMA</text>
          <text x="22" y="24"  text-anchor="middle" font-size="3.5" fill="#FFD700" font-family="Arial">UNIVERSITY</text>
          <text x="22" y="31"  text-anchor="middle" font-size="2.8" fill="#FFD700" font-family="Arial">ANTIPOLO CITY</text>
        </svg>
        <div>
          <h1>Alumni ID Generator</h1>
          <p>Admin Portal — Our Lady of Fatima University</p>
        </div>
      </header>

      <div class="idcheck-grid">
        <section class="form-panel">
          <h2>Student Information</h2>
          <div class="form-grid">
            <div class="form-group full idcheck-photo-block">
              <label>Profile Photo</label>
              <div class="idcheck-photo-circle" aria-hidden="true">
                <?php if ($photoSrc !== ''): ?>
                  <img src="<?= id_check_h($photoSrc) ?>" alt="" />
                <?php else: ?>
                  <div class="idcheck-photo-ph"><i class="fas fa-user"></i></div>
                <?php endif; ?>
              </div>
              <p class="idcheck-photo-hint">From registration (2×2 formal)</p>
            </div>
            <div class="form-group full">
              <label>Full Name</label>
              <div class="idcheck-value"><?= id_check_h($fullName) ?></div>
            </div>
            <div class="form-group full">
              <label>Card Number</label>
              <div class="idcheck-value"><?= id_check_h($cardFormatted) ?></div>
            </div>
            <div class="form-group full">
              <label>Program / Degree</label>
              <div class="idcheck-value"><?= id_check_h($program) ?></div>
            </div>
            <div class="form-group">
              <label>Batch Year</label>
              <div class="idcheck-value"><?= id_check_h($batchYear) ?></div>
            </div>
            <div class="form-group">
              <label>Valid Until</label>
              <div class="idcheck-value"><?= id_check_h($validUntil) ?></div>
            </div>
            <div class="form-group full">
              <label>Address</label>
              <div class="idcheck-value"><?= id_check_h($address) ?></div>
            </div>
            <div class="form-group">
              <label>Contact Number</label>
              <div class="idcheck-value"><?= id_check_h($contact) ?></div>
            </div>
            <div class="form-group">
              <label>Emergency Contact</label>
              <div class="idcheck-value"><?= id_check_h($emergency) ?></div>
            </div>
          </div>
          <div class="btn-row">
            <button type="button" class="btn" onclick="printCards()"><i class="fas fa-print" style="margin-right:6px;"></i>Print / Save PDF</button>
          </div>
        </section>

        <section class="preview-panel">
          <h2>ID Preview</h2>
          <?php
          try {
              render_alumni_id_cards($card);
          } catch (Throwable $e) {
              @error_log('[ad_alumni_id_check] card preview: ' . $e->getMessage());
              echo '<p class="idcheck-note" role="alert">ID card preview could not be rendered. Details were logged for the administrator.</p>';
          }
          ?>
        </section>
      </div>
    </div>
  </div>
</main>

<script>
function printCards() {
  window.print();
}
</script>
</body>
</html>
<?php
if (isset($conn) && $conn instanceof mysqli) {
    mysqli_close($conn);
}
?>
