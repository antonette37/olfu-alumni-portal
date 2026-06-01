<?php
// Start output buffering to catch any errors
ob_start();

// Add error reporting for debugging (but don't display errors to prevent header issues)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set a custom error handler to catch fatal errors
// Only register if not already registered to avoid conflicts
if (!isset($GLOBALS['shutdown_registered'])) {
  $GLOBALS['shutdown_registered'] = true;
  register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
      // Only handle if we haven't already sent output
      if (ob_get_level() > 0) {
        ob_end_clean();
      }
      error_log("Fatal error in al_directory.php: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
      if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
      }
      die("An error occurred. Please check the error logs.");
    }
  });
}

require_once 'db_config.php';
$conn = getDBConnection();

if (!$conn) {
  ob_end_clean();
  http_response_code(500);
  die("Database connection failed. Please try again later.");
}

session_start();
alumni_otp_gate_after_session();

if (!isset($_SESSION['user_id'])) {
  header("Location: al_homepage.php");
  exit();
}

$user_id = $_SESSION['user_id'];

$sql = "SELECT * FROM itcp WHERE id = ?";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
  error_log("SQL error in al_directory.php: " . $conn->error);
  ob_end_clean();
  http_response_code(500);
  die("An error occurred. Please try again later.");
}

$stmt->bind_param("i", $user_id);
if (!$stmt->execute()) {
  error_log("Failed to execute user query: " . $stmt->error);
  ob_end_clean();
  http_response_code(500);
  die("An error occurred. Please try again later.");
}
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
  $user = $result->fetch_assoc();
}
else {
  header("Location: al_homepage.php");
  exit();
}

$stmt->close();

// Update the notification count query to only count unread notifications
$notification_count = 0;
$notifications = [];
$sql = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0";
$stmt = $conn->prepare($sql);
if ($stmt) {
  $stmt->bind_param("i", $user_id);
  if ($stmt->execute()) {
    $result = $stmt->get_result();
    if ($result) {
      $row = $result->fetch_assoc();
      $notification_count = isset($row['unread_count']) ? (int)$row['unread_count'] : 0;
    }
  }
  $stmt->close();
}

// Fetch notifications with their read status
$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
if ($stmt) {
  $stmt->bind_param("i", $user_id);
  if ($stmt->execute()) {
    $result = $stmt->get_result();
    if ($result) {
      $notifications = $result->fetch_all(MYSQLI_ASSOC);
    }
  }
  $stmt->close();
}

// Fetch messages count (optional - table might not exist)
$message_count = 0;
$sql = "SELECT COUNT(*) as message_count FROM messages WHERE recipient_id = ? AND is_read = 0";
$stmt = $conn->prepare($sql);
if ($stmt) {
  $stmt->bind_param("i", $user_id);
  if ($stmt->execute()) {
    $result = $stmt->get_result();
    if ($result) {
      $row = $result->fetch_assoc();
      $message_count = isset($row['message_count']) ? (int)$row['message_count'] : 0;
    }
  }
  $stmt->close();
}

// Degrees/courses from registration step 1 (same as al_registration.php collegeDegrees)
$registration_degrees = [
  'College of Computer Studies' => [
    'Bachelor of Science in Information Technology',
    'Bachelor of Science in Computer Science',
    'Bachelor of Science in Information Systems'
  ],
  'College of Engineering' => [
    'Bachelor of Science in Civil Engineering',
    'Bachelor of Science in Electrical Engineering',
    'Bachelor of Science in Mechanical Engineering',
    'Bachelor of Science in Computer Engineering'
  ],
  'College of Business' => [
    'Bachelor of Science in Business Administration',
    'Bachelor of Science in Accountancy',
    'Bachelor of Science in Entrepreneurship'
  ],
  'College of Arts and Sciences' => [
    'Bachelor of Arts in Communication',
    'Bachelor of Science in Psychology',
    'Bachelor of Science in Biology'
  ],
  'College of Education' => [
    'Bachelor of Elementary Education',
    'Bachelor of Secondary Education',
    'Bachelor of Physical Education'
  ],
  'College of Nursing' => [
    'Bachelor of Science in Nursing'
  ],
  'College of Medicine' => [
    'Doctor of Medicine'
  ]
];
// Flat list of all degree options for directory filter
$all_degrees = [];
foreach ($registration_degrees as $college => $degrees) {
  foreach ($degrees as $deg) {
    $all_degrees[] = $deg;
  }
}
sort($all_degrees);

// Fetch filter data
$graduation_years = [];
$majors = [];
$companies = [];
$locations = [];

$sql = "SELECT DISTINCT year_graduated FROM itcp WHERE year_graduated IS NOT NULL ORDER BY year_graduated DESC";
$stmt = $conn->prepare($sql);
if ($stmt) {
  if ($stmt->execute()) {
    $result = $stmt->get_result();
    if ($result) {
      $graduation_years = $result->fetch_all(MYSQLI_ASSOC);
    }
  }
  $stmt->close();
}

$sql = "SELECT DISTINCT program FROM itcp WHERE program IS NOT NULL ORDER BY program";
$stmt = $conn->prepare($sql);
if ($stmt) {
  if ($stmt->execute()) {
    $result = $stmt->get_result();
    if ($result) {
      $majors = $result->fetch_all(MYSQLI_ASSOC);
    }
  }
  $stmt->close();
}

$sql = "SELECT DISTINCT company FROM itcp WHERE company IS NOT NULL ORDER BY company";
$stmt = $conn->prepare($sql);
if ($stmt) {
  if ($stmt->execute()) {
    $result = $stmt->get_result();
    if ($result) {
      $companies = $result->fetch_all(MYSQLI_ASSOC);
    }
  }
  $stmt->close();
}

$sql = "SELECT DISTINCT address FROM itcp WHERE address IS NOT NULL ORDER BY address";
$stmt = $conn->prepare($sql);
if ($stmt) {
  if ($stmt->execute()) {
    $result = $stmt->get_result();
    if ($result) {
      $locations = $result->fetch_all(MYSQLI_ASSOC);
    }
  }
  $stmt->close();
}

$industries = [];
$chk_ind = @$conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'itcp' AND COLUMN_NAME = 'industry'");
if ($chk_ind && $chk_ind->fetch_row()) {
  $chk_ind->close();
  $sql = "SELECT DISTINCT industry FROM itcp WHERE industry IS NOT NULL AND TRIM(industry) != '' ORDER BY industry";
  $stmt = $conn->prepare($sql);
  if ($stmt) {
    if ($stmt->execute()) {
      $result = $stmt->get_result();
      if ($result) {
        $industries = $result->fetch_all(MYSQLI_ASSOC);
      }
    }
    $stmt->close();
  }
} else {
  if ($chk_ind) $chk_ind->close();
}

// College and month/employment options for filters (match registration)
$college_options = array_keys($registration_degrees);
$month_graduated_options = [
  '01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April',
  '05' => 'May', '06' => 'June', '07' => 'July', '08' => 'August',
  '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'
];
$employment_options = ['Employed', 'Unemployed', 'Self-employed', 'Student'];

// Get total alumni count
$total_alumni = 0;
$sql = "SELECT COUNT(*) as total_alumni FROM itcp";
$stmt = $conn->prepare($sql);
if ($stmt) {
  if ($stmt->execute()) {
    $result = $stmt->get_result();
    if ($result) {
      $row = $result->fetch_assoc();
      $total_alumni = isset($row['total_alumni']) ? (int)$row['total_alumni'] : 0;
    }
  }
  $stmt->close();
}

// Get total companies count
$total_companies = 0;
$sql = "SELECT COUNT(DISTINCT current_company) as total_companies FROM itcp WHERE current_company IS NOT NULL";
$stmt = $conn->prepare($sql);
if ($stmt) {
  if ($stmt->execute()) {
    $result = $stmt->get_result();
    if ($result) {
      $row = $result->fetch_assoc();
      $total_companies = isset($row['total_companies']) ? (int)$row['total_companies'] : 0;
    }
  }
  $stmt->close();
}

// Get total countries count
$total_countries = 0;
$sql = "SELECT COUNT(DISTINCT country) as total_countries FROM itcp WHERE country IS NOT NULL";
$stmt = $conn->prepare($sql);
if ($stmt) {
  if ($stmt->execute()) {
    $result = $stmt->get_result();
    if ($result) {
      $row = $result->fetch_assoc();
      $total_countries = isset($row['total_countries']) ? (int)$row['total_countries'] : 0;
    }
  }
  $stmt->close();
}

// Handle search and filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$graduation_year_filter = isset($_GET['graduation_year']) ? trim($_GET['graduation_year']) : '';
$major_filter = isset($_GET['major']) ? trim($_GET['major']) : '';
$industry_filter = isset($_GET['industry']) ? trim($_GET['industry']) : '';
$location_filter = isset($_GET['location']) ? trim($_GET['location']) : '';
$college_filter = isset($_GET['college']) ? trim($_GET['college']) : '';
$month_graduated_filter = isset($_GET['month_graduated']) ? trim($_GET['month_graduated']) : '';
$employment_filter = isset($_GET['employment']) ? trim($_GET['employment']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'name';

// Column existence for optional filters
$has_college = false;
$has_month_graduated = false;
$has_employment_status = false;
$has_industry = false;
$chk = @$conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'itcp' AND COLUMN_NAME = 'college'");
if ($chk && $chk->fetch_row()) { $has_college = true; } if ($chk) $chk->close();
$chk = @$conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'itcp' AND COLUMN_NAME = 'month_graduated'");
if ($chk && $chk->fetch_row()) { $has_month_graduated = true; } if ($chk) $chk->close();
$chk = @$conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'itcp' AND COLUMN_NAME = 'employment_status'");
if ($chk && $chk->fetch_row()) { $has_employment_status = true; } if ($chk) $chk->close();
$chk = @$conn->query("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'itcp' AND COLUMN_NAME = 'industry'");
if ($chk && $chk->fetch_row()) { $has_industry = true; } if ($chk) $chk->close();

// Build query
$where_conditions = [];
$params = [];
$param_types = '';

// Exclude pending users
$where_conditions[] = "LOWER(status) != 'pending'";

if (!empty($search)) {
  $where_conditions[] = "(firstname LIKE ? OR lastname LIKE ? OR middlename LIKE ? OR email LIKE ? OR student_number LIKE ? OR company LIKE ? OR position LIKE ?)";
  $search_param = "%$search%";
  $params = array_merge($params, array_fill(0, 7, $search_param));
  $param_types .= str_repeat('s', 7);
}

if (!empty($graduation_year_filter)) {
  $where_conditions[] = "year_graduated = ?";
  $params[] = $graduation_year_filter;
  $param_types .= 's';
}

if (!empty($major_filter)) {
  $where_conditions[] = "program = ?";
  $params[] = $major_filter;
  $param_types .= 's';
}

if (!empty($location_filter)) {
  $where_conditions[] = "address = ?";
  $params[] = $location_filter;
  $param_types .= 's';
}

if (!empty($industry_filter) && $has_industry) {
  $where_conditions[] = "industry = ?";
  $params[] = $industry_filter;
  $param_types .= 's';
}

if (!empty($college_filter) && $has_college) {
  $where_conditions[] = "college = ?";
  $params[] = $college_filter;
  $param_types .= 's';
}

if (!empty($month_graduated_filter) && $has_month_graduated) {
  $where_conditions[] = "month_graduated = ?";
  $params[] = $month_graduated_filter;
  $param_types .= 's';
}

if (!empty($employment_filter) && $has_employment_status) {
  $where_conditions[] = "employment_status = ?";
  $params[] = $employment_filter;
  $param_types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Sort options
$sort_options = [
  'name' => 'firstname, lastname',
  'graduation_year' => 'year_graduated DESC',
  'location' => 'address',
  'company' => 'company'
];

// Safely get order_by with fallback
$order_by = 'firstname, lastname'; // Default
if (isset($sort_options[$sort_by])) {
  $order_by = $sort_options[$sort_by];
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Get total results count
$total_results = 0;
$total_pages = 0;
$alumni = [];

$count_sql = "SELECT COUNT(*) as total FROM itcp $where_clause";
$count_stmt = $conn->prepare($count_sql);
if ($count_stmt) {
  if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
  }
  if ($count_stmt->execute()) {
    $count_result = $count_stmt->get_result();
    if ($count_result) {
      $count_row = $count_result->fetch_assoc();
      $total_results = isset($count_row['total']) ? (int)$count_row['total'] : 0;
      $total_pages = ceil($total_results / $per_page);
    }
  }
  $count_stmt->close();
}

// Select all available fields for complete profile information
$alumni = []; // Initialize to prevent undefined variable errors
$sql = "SELECT * FROM itcp $where_clause ORDER BY $order_by LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);

if ($stmt) {
  if (!empty($params)) {
    $all_params = array_merge($params, [$per_page, $offset]);
    $stmt->bind_param($param_types . 'ii', ...$all_params);
  }
  else {
    $stmt->bind_param('ii', $per_page, $offset);
  }

  if ($stmt->execute()) {
    $result = $stmt->get_result();
    if ($result) {
      $alumni = $result->fetch_all(MYSQLI_ASSOC);
    }
  }
  else {
    error_log("Query execution failed: " . $stmt->error);
  }
  $stmt->close();
}
else {
  error_log("Failed to prepare query: " . $conn->error);
}

// Don't close connection yet - header needs it
// $conn->close(); // Commented out - header needs the connection

// Don't flush buffer yet - let it accumulate and flush at the end
// The header will output its content into the buffer

/**
 * Map itcp.photo from the database to a usable image URL (full http(s) URL, protocol-relative, or serve_profile_image.php).
 * Returns null when there is no image to show (caller uses initials).
 */
function directory_resolved_profile_image_src($photo) {
  if ($photo === null || !is_string($photo)) {
    return null;
  }
  $p = trim($photo);
  if ($p === '') {
    return null;
  }
  if (strcasecmp($p, 'default-avatar.png') === 0) {
    return null;
  }
  if (preg_match('#^//#', $p)) {
    return $p;
  }
  if (preg_match('#^https?://#i', $p)) {
    return $p;
  }
  $norm = str_replace('\\', '/', $p);
  $norm = preg_replace('#^(\.\./|\./)*#', '', $norm);
  $norm = preg_replace('#^uploads/#i', '', $norm);
  $norm = ltrim($norm, '/');
  $uploads = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
  $candidates = [];
  if ($norm !== '') {
    $candidates[] = $uploads . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $norm);
  }
  $base = basename(str_replace('\\', '/', $p));
  if ($base !== '' && $base !== '.' && $base !== '..') {
    $candidates[] = $uploads . DIRECTORY_SEPARATOR . $base;
  }
  foreach ($candidates as $full) {
    if ($full !== '' && is_file($full)) {
      return 'serve_profile_image.php?img=' . rawurlencode(basename($full));
    }
  }
  if ($base !== '' && preg_match('/\.(jpe?g|png|gif|webp|bmp)$/i', $base)) {
    return 'serve_profile_image.php?img=' . rawurlencode($base);
  }
  return null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta content="width=device-width, initial-scale=1" name="viewport" />
  <title>Alumni Directory — CCS Alumni</title>
  <link rel="icon" href="olfulogo.png" type="image/png">
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
  <style>
:root{
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
.pg-title{font-family:'DM Serif Display',serif;font-size:clamp(1.8rem,3.5vw,2.6rem);color:var(--forest)}
.pg-title em{font-style:italic;color:var(--moss)}
.gold-bar{height:3px;width:60px;background:linear-gradient(90deg,var(--leaf),var(--gold));border-radius:2px;margin:.5rem 0 .75rem}
.pg-sub{font-size:.9rem;color:var(--slate);max-width:42rem;line-height:1.55}
.stat-row{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:1.1rem}
.stat-pill{display:flex;align-items:center;gap:.5rem;background:var(--white);border:1px solid rgba(200,221,210,.55);border-radius:12px;padding:.55rem 1rem;font-size:.8rem;color:var(--slate);box-shadow:var(--shadow)}
.stat-pill strong{font-weight:700;color:var(--forest)}
.career-layout{display:grid;grid-template-columns:260px 1fr;gap:1.5rem;align-items:start;margin-top:1.75rem;isolation:isolate}
@media(max-width:900px){.career-layout{grid-template-columns:1fr}}
/* Main column must stack above the sticky sidebar or the sidebar's layer can steal clicks on the cards (esp. Connect). */
.career-main{position:relative;z-index:2;min-width:0}
.filter-panel{
  background:var(--white);
  border-radius:18px;
  padding:1.5rem;
  box-shadow:var(--shadow);
  border:1px solid rgba(200,221,210,.5);
  position:sticky;
  top:76px;
  z-index:1;
  align-self:start;
  max-height:calc(100vh - 92px);
  overflow-y:auto;
  -webkit-overflow-scrolling:touch
}
@media(max-width:900px){
  .filter-panel{position:relative;top:auto;max-height:none;z-index:1}
}
.filter-label{font-size:.68rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--moss);margin-bottom:.5rem;display:block}
.filter-input{width:100%;height:40px;border:1.5px solid var(--fog);border-radius:9px;padding:0 .875rem;font-family:'Outfit',sans-serif;font-size:.85rem;color:var(--ink);background:var(--snow);outline:none;transition:border-color .2s,box-shadow .2s;margin-bottom:1rem}
.filter-input:focus{border-color:var(--leaf);box-shadow:0 0 0 3px rgba(27,94,53,.09);background:var(--white)}
.filter-actions{display:flex;flex-direction:column;gap:.5rem;margin-top:.25rem}
.btn-clear{display:inline-flex;align-items:center;justify-content:center;gap:.4rem;width:100%;padding:.65rem 1rem;border-radius:10px;border:1.5px solid var(--fog);background:var(--snow);color:var(--charcoal);font-family:'Outfit',sans-serif;font-size:.82rem;font-weight:600;text-decoration:none;cursor:pointer;transition:background .2s}
.btn-clear:hover{background:var(--mist)}
.board-head{display:flex;align-items:center;justify-content:space-between;background:var(--white);border-radius:14px;padding:1rem 1.25rem;box-shadow:var(--shadow);margin-bottom:1rem;border:1px solid rgba(200,221,210,.5)}
.board-title{font-family:'DM Serif Display',serif;font-size:1.1rem;color:var(--forest)}
.board-count{font-size:.82rem;color:var(--silver)}
.dir-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1rem}
.dir-card{background:var(--white);border-radius:16px;border:1.5px solid rgba(200,221,210,.6);padding:1.35rem;box-shadow:var(--shadow);transition:all .25s}
.dir-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-h);border-color:var(--sage)}
.dir-card-top{display:flex;align-items:flex-start;gap:1rem;margin-bottom:1rem}
.dir-avatar{width:56px;height:56px;border-radius:50%;object-fit:cover;border:2px solid var(--mist);flex-shrink:0}
.dir-avatar-ph{width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,var(--leaf),var(--moss));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:1rem;flex-shrink:0;border:2px solid var(--mist)}
.dir-name{font-family:'DM Serif Display',serif;font-size:1.05rem;color:var(--forest);line-height:1.2}
.dir-class{font-size:.78rem;color:var(--silver);margin-top:.2rem}
.dir-meta{display:flex;flex-direction:column;gap:.45rem;margin-bottom:1rem}
.dir-meta-row{display:flex;align-items:flex-start;gap:.5rem;font-size:.8rem;color:var(--slate);line-height:1.4}
.dir-meta-row i{color:var(--moss);width:16px;text-align:center;margin-top:2px;flex-shrink:0;font-size:.72rem}
.dir-meta-row.muted{color:var(--silver)}
.btn-connect{position:relative;z-index:1;width:100%;display:inline-flex;align-items:center;justify-content:center;gap:.45rem;background:var(--forest);color:#fff;border:none;border-radius:10px;padding:.6rem 1rem;font-family:'Outfit',sans-serif;font-size:.82rem;font-weight:600;cursor:pointer;pointer-events:auto;transition:all .2s;box-shadow:0 2px 6px rgba(13,46,24,.18)}
.btn-connect:hover{background:var(--pine);transform:translateY(-1px)}
.empty-dir{text-align:center;padding:3rem 1rem;color:var(--silver);background:var(--white);border-radius:16px;border:1px solid rgba(200,221,210,.5)}
.empty-dir i{font-size:2.5rem;margin-bottom:.75rem;display:block;opacity:.45;color:var(--moss)}
.empty-dir h3{font-family:'DM Serif Display',serif;color:var(--forest);margin-bottom:.35rem}
.pag-row{display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:.75rem;margin-top:2rem}
.pag-link{display:inline-flex;align-items:center;gap:.4rem;padding:.6rem 1.15rem;border-radius:10px;font-size:.85rem;font-weight:600;text-decoration:none;transition:all .2s}
.pag-link.prev{border:1.5px solid var(--fog);background:var(--white);color:var(--charcoal)}
.pag-link.prev:hover{background:var(--mist)}
.pag-link.next{background:var(--forest);color:#fff;box-shadow:0 2px 8px rgba(13,46,24,.2)}
.pag-link.next:hover{background:var(--pine)}
.pag-info{font-size:.82rem;color:var(--silver)}
.dir-cta{margin-top:2.5rem;background:linear-gradient(135deg,var(--pine),var(--leaf));border-radius:18px;padding:2.25rem 1.75rem;text-align:center;color:#fff;box-shadow:var(--shadow-h)}
.dir-cta h2{font-family:'DM Serif Display',serif;font-size:clamp(1.35rem,2.5vw,1.85rem);margin-bottom:.5rem}
.dir-cta p{font-size:.9rem;opacity:.88;max-width:36rem;margin:0 auto 1.5rem;line-height:1.6}
.dir-cta-btns{display:flex;flex-wrap:wrap;gap:.75rem;justify-content:center}
.dir-cta a{display:inline-flex;align-items:center;justify-content:center;padding:.65rem 1.35rem;border-radius:10px;font-size:.86rem;font-weight:600;text-decoration:none;transition:opacity .2s,transform .2s}
.dir-cta a.primary{background:#fff;color:var(--forest)}
.dir-cta a.primary:hover{opacity:.95;transform:translateY(-1px)}
.dir-cta a.ghost{border:2px solid rgba(255,255,255,.85);color:#fff}
.dir-cta a.ghost:hover{background:rgba(255,255,255,.1)}
.modal-dir{position:fixed;inset:0;background:rgba(13,46,24,.5);z-index:500;display:none;align-items:flex-start;justify-content:center;padding:2rem 1rem;overflow-y:auto}
.modal-dir.open{display:flex}
.modal-dir-box{background:var(--white);border-radius:20px;width:100%;max-width:640px;box-shadow:0 24px 80px rgba(13,46,24,.25);margin:auto;max-height:90vh;overflow-y:auto}
.modal-dir-h{display:flex;justify-content:space-between;align-items:center;padding:1.25rem 1.5rem;background:linear-gradient(135deg,var(--pine),var(--leaf));border-radius:20px 20px 0 0}
.modal-dir-h h2{font-family:'DM Serif Display',serif;font-size:1.25rem;color:#fff}
.modal-dir-h button{background:none;border:none;color:rgba(255,255,255,.85);cursor:pointer;font-size:1.2rem}
.modal-dir-h button:hover{color:#fff}
.modal-dir-b{padding:1.5rem}
@keyframes fade-up{from{opacity:0}to{opacity:1}}
.fade-up{animation:fade-up .45s ease both}
.skill-tag{display:inline-block;font-size:.72rem;padding:.28rem .65rem;background:var(--mist);border:1px solid var(--fog);border-radius:999px;color:var(--charcoal);margin:.2rem .25rem 0 0}
.dir-avatar-wrap{position:relative;flex-shrink:0}
.dir-verify{position:absolute;bottom:-2px;right:-2px;width:18px;height:18px;background:var(--leaf);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:8px;border:2px solid var(--white);box-shadow:0 1px 4px rgba(13,46,24,.2)}
.dir-card-headtext{flex:1;min-width:0}
.modal-dir-profile{display:flex;gap:1.25rem;align-items:flex-start;margin-bottom:1.5rem;flex-wrap:wrap}
.modal-dir-avatar-wrap{position:relative;flex-shrink:0}
.modal-dir-avatar{width:96px;height:96px;border-radius:50%;object-fit:cover;border:3px solid var(--mist);display:block}
.modal-dir-avatar-ph{width:96px;height:96px;border-radius:50%;background:linear-gradient(135deg,var(--leaf),var(--moss));display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.35rem;font-weight:700;border:3px solid var(--mist)}
.modal-dir-verify{position:absolute;bottom:2px;right:2px;width:26px;height:26px;background:var(--leaf);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;border:2px solid #fff;font-size:.65rem;box-shadow:0 2px 6px rgba(13,46,24,.2)}
.modal-h-name{font-family:'DM Serif Display',serif;font-size:1.55rem;color:var(--forest);margin-bottom:.2rem;line-height:1.2}
.modal-h-title{font-size:.95rem;color:var(--slate)}
.modal-h-co{font-size:.92rem;font-weight:600;color:var(--moss);margin:.35rem 0 .65rem}
.modal-meta-row{display:flex;flex-wrap:wrap;gap:1rem;font-size:.84rem;color:var(--silver)}
.modal-meta-row i{color:var(--moss);margin-right:.35rem}
.modal-sec{margin-bottom:1.35rem}
.modal-sec-title{font-size:.95rem;font-weight:700;color:var(--forest);margin-bottom:.65rem;display:flex;align-items:center;gap:.5rem}
.modal-sec-title i{color:var(--moss);font-size:.9rem}
.modal-box{background:var(--snow);border-radius:12px;padding:1rem 1.1rem;border:1px solid var(--fog);line-height:1.55;color:var(--charcoal);font-size:.9rem}
.modal-two-col{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
@media(max-width:560px){.modal-two-col{grid-template-columns:1fr}}
.modal-icon-cell{display:flex;align-items:center;gap:.75rem;padding:.85rem;background:var(--snow);border-radius:12px;border:1px solid var(--fog)}
.modal-icon-round{width:40px;height:40px;border-radius:50%;background:var(--mist);display:flex;align-items:center;justify-content:center;color:var(--moss);flex-shrink:0}
.modal-lbl{font-size:.68rem;color:var(--silver);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.15rem}
.modal-val a{color:var(--leaf);font-weight:600;text-decoration:none;font-size:.9rem}
.modal-val a:hover{text-decoration:underline}
.modal-ach-item{display:flex;align-items:flex-start;gap:.65rem;padding:.75rem .85rem;background:var(--snow);border-radius:10px;border:1px solid var(--fog);margin-bottom:.45rem;font-size:.86rem;color:var(--charcoal)}
.modal-ach-item>i:first-child{color:var(--gold);margin-top:3px;flex-shrink:0}
.modal-footer-actions{display:flex;flex-wrap:wrap;gap:.65rem;padding-top:1.1rem;margin-top:.25rem;border-top:1px solid var(--fog)}
.btn-modal-primary{flex:1;min-width:140px;display:inline-flex;align-items:center;justify-content:center;gap:.45rem;background:var(--forest);color:#fff;border:none;border-radius:10px;padding:.72rem 1rem;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif;font-size:.86rem}
.btn-modal-primary:hover{background:var(--pine)}
.btn-modal-ghost{flex:1;min-width:140px;display:inline-flex;align-items:center;justify-content:center;gap:.45rem;border:2px solid var(--leaf);color:var(--leaf);background:transparent;border-radius:10px;padding:.72rem 1rem;font-weight:600;cursor:pointer;font-family:'Outfit',sans-serif;font-size:.86rem}
.btn-modal-ghost:hover{background:var(--mist)}
  </style>
</head>

<body>
  <?php include __DIR__ . '/al_header_universal.php'; ?>

  <div class="page">
    <div class="fade-up">
      <div class="pg-title">Alumni <em>Directory</em></div>
      <div class="gold-bar"></div>
      <p class="pg-sub">Search and connect with graduates across programs, companies, and locations—same look and feel as the Career Center.</p>
      <div class="stat-row">
        <div class="stat-pill"><i class="fas fa-users" style="color:var(--moss)"></i> <strong><?php echo number_format($total_alumni); ?></strong> in network</div>
        <div class="stat-pill"><i class="fas fa-building" style="color:var(--moss)"></i> <strong><?php echo number_format($total_companies); ?></strong> companies</div>
        <div class="stat-pill"><i class="fas fa-globe-asia" style="color:var(--moss)"></i> <strong><?php echo number_format($total_countries); ?></strong> countries</div>
      </div>
    </div>

    <div class="career-layout">
      <aside class="filter-panel">
        <div style="margin-bottom:1.25rem;padding-bottom:1.25rem;border-bottom:1px solid var(--mist)">
          <div style="font-family:'DM Serif Display',serif;font-size:1.05rem;color:var(--forest)">Filters</div>
          <div style="font-size:.78rem;color:var(--silver);margin-top:.25rem">Refine your search</div>
        </div>
        <label class="filter-label" for="searchInput">Search</label>
        <input type="text" id="searchInput" class="filter-input" placeholder="Name, company, program…" value="<?php echo htmlspecialchars($search); ?>" style="margin-bottom:1rem">
        <label class="filter-label" for="graduationYearFilter">Graduation year</label>
        <select id="graduationYearFilter" class="filter-input">
          <option value="">All years</option>
          <?php foreach ($graduation_years as $year): ?>
            <option value="<?php echo htmlspecialchars((string)($year['year_graduated'] ?? '')); ?>" <?php echo $graduation_year_filter == ($year['year_graduated'] ?? '') ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)($year['year_graduated'] ?? '')); ?></option>
          <?php endforeach; ?>
        </select>
        <label class="filter-label" for="degreeFilter">Degree / program</label>
        <select id="degreeFilter" class="filter-input">
          <option value="">All programs</option>
          <?php foreach ($all_degrees as $deg): ?>
            <option value="<?php echo htmlspecialchars($deg); ?>" <?php echo $major_filter === $deg ? 'selected' : ''; ?>><?php echo htmlspecialchars($deg); ?></option>
          <?php endforeach; ?>
        </select>
        <label class="filter-label" for="locationFilter">Location</label>
        <select id="locationFilter" class="filter-input">
          <option value="">All locations</option>
          <?php foreach ($locations as $location): ?>
            <option value="<?php echo htmlspecialchars($location['address'] ?? ''); ?>" <?php echo $location_filter == ($location['address'] ?? '') ? 'selected' : ''; ?>><?php echo htmlspecialchars($location['address'] ?? ''); ?></option>
          <?php endforeach; ?>
        </select>
        <?php if ($has_college): ?>
        <label class="filter-label" for="collegeFilter">College</label>
        <select id="collegeFilter" class="filter-input">
          <option value="">All colleges</option>
          <?php foreach ($college_options as $co): ?>
            <option value="<?php echo htmlspecialchars($co); ?>" <?php echo $college_filter === $co ? 'selected' : ''; ?>><?php echo htmlspecialchars($co); ?></option>
          <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <?php if ($has_month_graduated): ?>
        <label class="filter-label" for="monthGraduatedFilter">Graduation month</label>
        <select id="monthGraduatedFilter" class="filter-input">
          <option value="">All months</option>
          <?php foreach ($month_graduated_options as $num => $label): ?>
            <option value="<?php echo htmlspecialchars($num); ?>" <?php echo $month_graduated_filter === $num ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
          <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <?php if ($has_industry && !empty($industries)): ?>
        <label class="filter-label" for="industryFilter">Industry</label>
        <select id="industryFilter" class="filter-input">
          <option value="">All industries</option>
          <?php foreach ($industries as $ind): ?>
            <option value="<?php echo htmlspecialchars($ind['industry'] ?? ''); ?>" <?php echo $industry_filter === ($ind['industry'] ?? '') ? 'selected' : ''; ?>><?php echo htmlspecialchars($ind['industry'] ?? ''); ?></option>
          <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <?php if ($has_employment_status): ?>
        <label class="filter-label" for="employmentFilter">Employment</label>
        <select id="employmentFilter" class="filter-input">
          <option value="">All</option>
          <?php foreach ($employment_options as $emp): ?>
            <option value="<?php echo htmlspecialchars($emp); ?>" <?php echo $employment_filter === $emp ? 'selected' : ''; ?>><?php echo htmlspecialchars($emp); ?></option>
          <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <label class="filter-label" for="sortFilter">Sort by</label>
        <select id="sortFilter" class="filter-input">
          <option value="name" <?php echo $sort_by == 'name' ? 'selected' : ''; ?>>Name (A–Z)</option>
          <option value="graduation_year" <?php echo $sort_by == 'graduation_year' ? 'selected' : ''; ?>>Graduation year</option>
          <option value="location" <?php echo $sort_by == 'location' ? 'selected' : ''; ?>>Location</option>
          <option value="company" <?php echo $sort_by == 'company' ? 'selected' : ''; ?>>Company</option>
        </select>
        <div class="filter-actions">
          <a href="al_directory.php" id="clearFiltersBtn" class="btn-clear"><i class="fas fa-times"></i> Clear all filters</a>
        </div>
      </aside>

      <div class="career-main">
        <div class="board-head">
          <div>
            <div class="board-title">Alumni profiles</div>
            <div class="board-count">Showing <?php echo number_format($total_results); ?> of <?php echo number_format($total_alumni); ?> profiles<?php if ($total_pages > 1): ?> · Page <?php echo (int)$page; ?> of <?php echo (int)$total_pages; ?><?php endif; ?></div>
          </div>
        </div>

    <?php if (empty($alumni)): ?>
      <div class="empty-dir" style="margin-top:1rem">
        <i class="fas fa-search"></i>
        <h3>No alumni found</h3>
        <p>Try adjusting your search or filters.</p>
      </div>
    <?php else: ?>
      <div class="dir-grid" id="alumniGrid">
        <?php foreach ($alumni as $alumnus): ?>
          <div class="dir-card">
            <div class="dir-card-top">
              <div class="dir-avatar-wrap">
                  <?php
    $firstname = $alumnus['firstname'] ?? '';
    $lastname = $alumnus['lastname'] ?? '';
    $initials = strtoupper(substr($firstname, 0, 1) . substr($lastname, 0, 1));
    $photo_href = directory_resolved_profile_image_src(isset($alumnus['photo']) ? (string)$alumnus['photo'] : null);
    if ($photo_href):
?>
                    <img src="<?php echo htmlspecialchars($photo_href, ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($firstname . ' ' . $lastname); ?>"
                         class="dir-avatar"
                         loading="lazy"
                         decoding="async"
                         onerror="this.onerror=null;this.style.display='none';this.nextElementSibling.style.display='flex';">
                    <div class="dir-avatar-ph" style="display:none;" aria-hidden="true"><?php echo htmlspecialchars($initials); ?></div>
                  <?php else: ?>
                    <div class="dir-avatar-ph"><?php echo htmlspecialchars($initials); ?></div>
                  <?php endif; ?>
                <span class="dir-verify" title="Profile on file"><i class="fas fa-check"></i></span>
              </div>
              <div class="dir-card-headtext">
                <div class="dir-name"><?php echo htmlspecialchars(trim($firstname . ' ' . $lastname)); ?></div>
                <?php $graduation_year = $alumnus['year_graduated'] ?? ''; ?>
                <?php if ($graduation_year): ?><div class="dir-class">Class of <?php echo htmlspecialchars((string)$graduation_year); ?></div><?php endif; ?>
              </div>
            </div>
            <div class="dir-meta">
              <?php $current_position = $alumnus['position'] ?? ''; ?>
              <div class="dir-meta-row<?php echo $current_position ? '' : ' muted'; ?>"><i class="fas fa-briefcase"></i><span><?php echo $current_position ? htmlspecialchars($current_position) : 'Position not specified'; ?></span></div>
              <?php $current_company = $alumnus['company'] ?? ''; ?>
              <div class="dir-meta-row<?php echo $current_company ? '' : ' muted'; ?>"><i class="fas fa-building"></i><span><?php echo $current_company ? htmlspecialchars($current_company) : 'Company not specified'; ?></span></div>
              <?php $major = $alumnus['program'] ?? ''; ?>
              <div class="dir-meta-row<?php echo $major ? '' : ' muted'; ?>"><i class="fas fa-graduation-cap"></i><span><?php echo $major ? htmlspecialchars($major) : 'Program not specified'; ?></span></div>
              <?php $location = $alumnus['address'] ?? ''; ?>
              <div class="dir-meta-row<?php echo $location ? '' : ' muted'; ?>"><i class="fas fa-location-dot"></i><span><?php echo $location ? htmlspecialchars($location) : 'Location not specified'; ?></span></div>
            </div>
            <button type="button" class="btn-connect" onclick="openAlumniModal(<?php echo (int)$alumnus['id']; ?>)"><i class="fas fa-envelope"></i> Connect</button>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($total_pages > 1): ?>
        <div class="pag-row">
          <?php if ($page > 1): ?>
              <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $page - 1])), ENT_QUOTES, 'UTF-8'); ?>" class="pag-link prev"><i class="fas fa-chevron-left"></i> Previous</a>
          <?php endif; ?>
            <span class="pag-info">Page <?php echo (int)$page; ?> of <?php echo (int)$total_pages; ?></span>
          <?php if ($page < $total_pages): ?>
              <a href="?<?php echo htmlspecialchars(http_build_query(array_merge($_GET, ['page' => $page + 1])), ENT_QUOTES, 'UTF-8'); ?>" class="pag-link next">Next <i class="fas fa-chevron-right"></i></a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

      </div>
    </div>

    <div class="dir-cta fade-up" style="animation-delay:.1s">
      <h2>Keep the directory current</h2>
      <p>Share your latest career updates or help us add missing alumni. A stronger network starts with accurate profiles.</p>
      <div class="dir-cta-btns">
        <a class="primary" href="al_profileupdate.php">Update my profile</a>
        <a class="ghost" href="al_contact.php">Submit alumni info</a>
      </div>
    </div>
  </div>

  <div id="alumniModal" class="modal-dir" role="dialog" aria-modal="true" aria-labelledby="alumniModalTitle">
    <div class="modal-dir-box">
      <div class="modal-dir-h">
        <h2 id="alumniModalTitle">Alumni profile</h2>
        <button type="button" onclick="closeAlumniModal()" aria-label="Close"><i class="fas fa-times"></i></button>
      </div>
      <div class="modal-dir-b">
        <div class="modal-dir-profile">
          <div class="modal-dir-avatar-wrap">
            <div id="modalAvatar" class="modal-dir-avatar-ph"></div>
            <div class="modal-dir-verify"><i class="fas fa-check"></i></div>
          </div>
          <div style="flex:1;min-width:0">
            <h3 id="modalName" class="modal-h-name"></h3>
            <p id="modalTitle" class="modal-h-title"></p>
            <p id="modalCompany" class="modal-h-co"></p>
            <div class="modal-meta-row">
              <span id="modalLocation"></span>
              <span id="modalGraduation"></span>
            </div>
          </div>
        </div>

        <div class="modal-sec">
          <div class="modal-sec-title"><i class="fas fa-address-card"></i> Contact</div>
          <div class="modal-two-col">
            <div class="modal-icon-cell">
              <div class="modal-icon-round"><i class="fas fa-envelope"></i></div>
              <div>
                <div class="modal-lbl">Email</div>
                <div class="modal-val"><a id="modalEmail" href="#"></a></div>
              </div>
            </div>
            <div class="modal-icon-cell">
              <div class="modal-icon-round"><i class="fas fa-phone"></i></div>
              <div>
                <div class="modal-lbl">Phone</div>
                <div class="modal-val"><a id="modalPhone" href="#"></a></div>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-sec">
          <div class="modal-sec-title"><i class="fas fa-user-circle"></i> About</div>
          <div class="modal-box"><p id="modalBio"></p></div>
        </div>

        <div class="modal-sec">
          <div class="modal-sec-title"><i class="fas fa-screwdriver-wrench"></i> Skills &amp; expertise</div>
          <div id="modalSkills"></div>
        </div>

        <div class="modal-sec">
          <div class="modal-sec-title"><i class="fas fa-graduation-cap"></i> Education</div>
          <div class="modal-icon-cell" style="align-items:flex-start">
            <div class="modal-icon-round"><i class="fas fa-university"></i></div>
            <div>
              <div style="font-weight:700;color:var(--forest)">Our Lady of Fatima University</div>
              <p id="modalEducation" style="color:var(--moss);font-weight:600;margin:.25rem 0"></p>
              <p id="modalEducationYear" style="font-size:.82rem;color:var(--silver)"></p>
            </div>
          </div>
        </div>

        <div class="modal-sec">
          <div class="modal-sec-title"><i class="fas fa-briefcase"></i> Work experience</div>
          <div class="modal-icon-cell" style="align-items:flex-start">
            <div class="modal-icon-round"><i class="fas fa-building"></i></div>
            <div>
              <h5 id="modalWorkPosition" style="font-weight:700;color:var(--forest);margin-bottom:.2rem"></h5>
              <p id="modalWorkCompany" style="color:var(--moss);font-weight:600"></p>
              <p style="font-size:.8rem;color:var(--silver);margin-top:.25rem">Current role</p>
            </div>
          </div>
        </div>

        <div class="modal-sec">
          <div class="modal-sec-title"><i class="fas fa-trophy"></i> Achievements</div>
          <div id="modalAchievements"></div>
        </div>

        <div class="modal-footer-actions">
          <button type="button" class="btn-modal-primary" id="modalBtnEmail"><i class="fas fa-comments"></i> Send message</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Filter functionality
    function updateFilters() {
      const search = document.getElementById('searchInput').value;
      const graduationYear = document.getElementById('graduationYearFilter').value;
      const degree = document.getElementById('degreeFilter').value;
      const location = document.getElementById('locationFilter').value;
      const sort = document.getElementById('sortFilter').value;
      const params = new URLSearchParams();
      if (search) params.set('search', search);
      if (graduationYear) params.set('graduation_year', graduationYear);
      if (degree) params.set('major', degree);
      if (location) params.set('location', location);
      if (sort !== 'name') params.set('sort', sort);
      const collegeEl = document.getElementById('collegeFilter');
      if (collegeEl && collegeEl.value) params.set('college', collegeEl.value);
      const monthEl = document.getElementById('monthGraduatedFilter');
      if (monthEl && monthEl.value) params.set('month_graduated', monthEl.value);
      const industryEl = document.getElementById('industryFilter');
      if (industryEl && industryEl.value) params.set('industry', industryEl.value);
      const employmentEl = document.getElementById('employmentFilter');
      if (employmentEl && employmentEl.value) params.set('employment', employmentEl.value);
      window.location.href = '?' + params.toString();
    }

    // Add event listeners
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        updateFilters();
      }
    });

    document.getElementById('graduationYearFilter').addEventListener('change', updateFilters);
    document.getElementById('degreeFilter').addEventListener('change', updateFilters);
    document.getElementById('locationFilter').addEventListener('change', updateFilters);
    document.getElementById('sortFilter').addEventListener('change', updateFilters);
    var collegeFilter = document.getElementById('collegeFilter');
    if (collegeFilter) collegeFilter.addEventListener('change', updateFilters);
    var monthFilter = document.getElementById('monthGraduatedFilter');
    if (monthFilter) monthFilter.addEventListener('change', updateFilters);
    var industryFilter = document.getElementById('industryFilter');
    if (industryFilter) industryFilter.addEventListener('change', updateFilters);
    var employmentFilter = document.getElementById('employmentFilter');
    if (employmentFilter) employmentFilter.addEventListener('change', updateFilters);

    // Debounced search
    let searchTimeout;
    document.getElementById('searchInput').addEventListener('input', function() {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(updateFilters, 500);
    });

    function profilePhotoSrc(photo) {
      if (!photo || typeof photo !== 'string') return null;
      const p = photo.trim();
      if (!p || /^default-avatar\.png$/i.test(p)) return null;
      if (/^\/\//.test(p)) return p;
      if (/^https?:\/\//i.test(p)) return p;
      const norm = p.replace(/\\/g, '/').replace(/^(\.\.\/|\.\/)+/, '').replace(/^uploads\//i, '').replace(/^\/+/, '');
      const parts = norm.split('/').filter(Boolean);
      const base = parts.length ? parts[parts.length - 1] : '';
      if (!base || !/\.(jpe?g|png|gif|webp|bmp)$/i.test(base)) return null;
      return 'serve_profile_image.php?img=' + encodeURIComponent(base);
    }

    function escapeHtml(s) {
      return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
    }

    // Alumni Modal Functions
    function openAlumniModal(alumniId) {
      const alumniData = <?php echo json_encode($alumni, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | (defined('JSON_INVALID_UTF8_SUBSTITUTE') ? JSON_INVALID_UTF8_SUBSTITUTE : 0)); ?>;
      const want = Number(alumniId);
      const alumnus = alumniData.find(function (a) { return Number(a.id) === want; });

      if (!alumnus) {
        console.error('Alumni not found for id', alumniId);
        return;
      }

      populateModal(alumnus);

      document.getElementById('alumniModal').classList.add('open');
      document.body.style.overflow = 'hidden';
    }

    function closeAlumniModal() {
      document.getElementById('alumniModal').classList.remove('open');
      document.body.style.overflow = '';
    }

    function populateModal(alumnus) {
      const firstname = alumnus.firstname || '';
      const lastname = alumnus.lastname || '';
      const fullName = `${firstname} ${lastname}`.trim();

      const modalAvatar = document.getElementById('modalAvatar');
      const src = profilePhotoSrc(alumnus.photo || '');
      modalAvatar.className = '';
      modalAvatar.replaceChildren();
      if (src) {
        const img = document.createElement('img');
        img.className = 'modal-dir-avatar';
        img.src = src;
        img.alt = fullName || 'Profile photo';
        img.onerror = function () {
          const initials = ((firstname.charAt(0) || '') + (lastname.charAt(0) || '')).toUpperCase() || '?';
          modalAvatar.replaceChildren();
          modalAvatar.className = 'modal-dir-avatar-ph';
          modalAvatar.textContent = initials;
        };
        modalAvatar.appendChild(img);
      } else {
        modalAvatar.className = 'modal-dir-avatar-ph';
        modalAvatar.textContent = ((firstname.charAt(0) || '') + (lastname.charAt(0) || '')).toUpperCase() || '?';
      }

      document.getElementById('modalName').textContent = fullName || 'Alumni member';
      document.getElementById('modalTitle').textContent = alumnus.position || 'Professional';
      document.getElementById('modalCompany').textContent = alumnus.company || 'Not specified';
      document.getElementById('modalLocation').innerHTML = '<i class="fas fa-map-marker-alt"></i> ' + escapeHtml(alumnus.address || 'Location not specified');
      document.getElementById('modalGraduation').innerHTML = '<i class="fas fa-graduation-cap"></i> Class of ' + escapeHtml(String(alumnus.year_graduated || 'N/A'));

      const email = alumnus.email || '';
      const phone = alumnus.personal_contact || alumnus.emergency_contact || 'Not provided';

      const modalEmail = document.getElementById('modalEmail');
      modalEmail.textContent = email || 'Not provided';
      modalEmail.href = email ? 'mailto:' + email : '#';

      const modalPhone = document.getElementById('modalPhone');
      modalPhone.textContent = phone;
      modalPhone.href = phone && phone !== 'Not provided' ? 'tel:' + String(phone).replace(/\s+/g, '') : '#';

      const bio = alumnus.bio || `Experienced professional with expertise in ${alumnus.program || 'their field'}. Graduated from Our Lady of Fatima University and currently working at ${alumnus.company || 'a leading organization'}.`;
      document.getElementById('modalBio').textContent = bio;

      const skills = generateSkills(alumnus);
      const modalSkills = document.getElementById('modalSkills');
      modalSkills.innerHTML = skills.map(function (skill) {
        return '<span class="skill-tag">' + escapeHtml(skill) + '</span>';
      }).join('');

      document.getElementById('modalEducation').textContent = alumnus.program || 'Not specified';
      document.getElementById('modalEducationYear').textContent = 'Graduated ' + (alumnus.year_graduated || 'N/A');

      document.getElementById('modalWorkPosition').textContent = alumnus.position || 'Position not specified';
      document.getElementById('modalWorkCompany').textContent = alumnus.company || 'Company not specified';

      const achievements = generateAchievements(alumnus);
      const modalAchievements = document.getElementById('modalAchievements');
      modalAchievements.innerHTML = achievements.map(function (achievement) {
        return '<div class="modal-ach-item"><i class="fas fa-award"></i><span>' + escapeHtml(achievement) + '</span></div>';
      }).join('');

      document.getElementById('modalBtnEmail').onclick = function () {
        window.location.href = 'al_messages.php?to=' + encodeURIComponent(String(alumnus.id));
      };
    }

    function generateSkills(alumnus) {
      const skills = [];
      
      // Add skills based on program
      if (alumnus.program) {
        const program = alumnus.program.toLowerCase();
        if (program.includes('computer') || program.includes('it') || program.includes('software')) {
          skills.push('Programming', 'Software Development', 'Database Management', 'System Analysis');
        } else if (program.includes('business') || program.includes('management')) {
          skills.push('Project Management', 'Strategic Planning', 'Leadership', 'Business Analysis');
        } else if (program.includes('engineering')) {
          skills.push('Technical Design', 'Problem Solving', 'Project Management', 'Quality Assurance');
        }
      }

      // Add skills based on position
      if (alumnus.position) {
        const position = alumnus.position.toLowerCase();
        if (position.includes('manager') || position.includes('lead')) {
          skills.push('Team Leadership', 'Strategic Planning', 'Communication');
        } else if (position.includes('developer') || position.includes('engineer')) {
          skills.push('Technical Skills', 'Problem Solving', 'Innovation');
        } else if (position.includes('analyst')) {
          skills.push('Data Analysis', 'Research', 'Critical Thinking');
        }
      }

      // Add general skills
      skills.push('Communication', 'Teamwork', 'Problem Solving', 'Adaptability');

      return [...new Set(skills)].slice(0, 8); // Remove duplicates and limit to 8
    }

    function generateAchievements(alumnus) {
      const achievements = [];
      
      // Add achievements based on available data
      if (alumnus.licensure_exam) {
        achievements.push(`Licensed Professional - ${alumnus.licensure_exam}`);
      }
      
      if (alumnus.post_grad) {
        achievements.push(`Post-Graduate Studies - ${alumnus.post_grad}`);
      }

      if (alumnus.club_involvement) {
        achievements.push(`Active Club Member - ${alumnus.club_involvement}`);
      }

      // Add general achievements
      achievements.push('OLFU Alumni Network Member');
      achievements.push('Professional Development Certified');
      
      if (alumnus.year_graduated) {
        const yearsSinceGrad = new Date().getFullYear() - alumnus.year_graduated;
        if (yearsSinceGrad > 0) {
          achievements.push(`${yearsSinceGrad}+ Years Professional Experience`);
        }
      }

      return achievements.slice(0, 5); // Limit to 5 achievements
    }

    // Close modal when clicking outside
    document.getElementById('alumniModal').addEventListener('click', function(e) {
      if (e.target === this) {
        closeAlumniModal();
      }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        closeAlumniModal();
      }
    });
  </script>
</body>
</html>
<?php
// Close connection at the very end, after all output
if (isset($conn) && $conn) {
  $conn->close();
}

// End output buffering and flush all content at the very end
if (ob_get_level() > 0) {
  ob_end_flush();
}
?>