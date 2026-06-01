<?php
/**
 * Alumni Profile Update — enhanced UI (Career / Alumni Card design system).
 */
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/includes/mysqli_compat.php';
require_once __DIR__ . '/includes/cps_alumni_lib.php';
require_once __DIR__ . '/includes/alumni_profile_lib.php';

/**
 * Same rule as al_registration.php: age is derived from birthday (as of today).
 */
function al_profileupdate_age_from_birthday(string $birthday): string
{
	if ($birthday === '' || preg_match('/^0000-00-00/', $birthday) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) {
		return '';
	}
	$bd = DateTime::createFromFormat('Y-m-d', $birthday);
	if (!$bd || $bd->format('Y-m-d') !== $birthday) {
		return '';
	}
	$today = new DateTime('today');
	if ($bd > $today) {
		return '';
	}

	return (string) (int) $bd->diff($today)->y;
}

/**
 * Resolve edit-profile form include (supports includes/ and Includes/ on Hostinger).
 */
function al_profileupdate_form_inc_path(): ?string
{
	static $resolved = null;
	if ($resolved !== null) {
		return $resolved !== '' ? $resolved : null;
	}
	foreach ([
		__DIR__ . '/al_profile_update_form.inc.php',
		__DIR__ . '/includes/al_profile_update_form.inc.php',
		__DIR__ . '/Includes/al_profile_update_form.inc.php',
	] as $path) {
		if (is_file($path)) {
			$resolved = $path;
			return $path;
		}
	}
	$resolved = '';
	return null;
}

function al_profileupdate_form_missing_html(): string
{
	return '<div class="alert alert-error" data-profile-form-server="1" role="alert">'
		. '<i class="fas fa-exclamation-circle"></i><span class="alert-msg">'
		. '<strong>Missing form file on server.</strong> In Hostinger File Manager, upload '
		. '<code>al_profile_update_form.inc.php</code> into <code>public_html/</code> '
		. '(same folder as <code>al_profileupdate.php</code>), then hard-refresh (Ctrl+F5).'
		. '</span></div>';
}

/** Output edit-profile form markup from include file on disk. */
function al_profileupdate_render_form(): void
{
	$formInc = al_profileupdate_form_inc_path();
	if ($formInc === null) {
		echo al_profileupdate_form_missing_html();
		return;
	}
	require $formInc;
}

alumni_otp_gate_after_session();

$conn = getDBConnection();
alumni_ensure_itcp_registration_columns($conn);

register_shutdown_function(static function (): void {
	$err = error_get_last();
	if (!$err || !in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
		return;
	}
	if (headers_sent()) {
		echo '<div style="margin:1.5rem;padding:1rem;background:#fee2e2;border:1px solid #fca5a5;border-radius:10px;font-family:sans-serif;color:#991b1b;">'
			. '<strong>Profile edit error:</strong> ' . htmlspecialchars($err['message'], ENT_QUOTES, 'UTF-8')
			. '<br><small>' . htmlspecialchars($err['file'] . ':' . $err['line'], ENT_QUOTES, 'UTF-8') . '</small></div>';
	}
});

$profileFlashMsg = '';
$profileFlashType = 'success';
if (!empty($_SESSION['profile_update_flash']) && is_array($_SESSION['profile_update_flash'])) {
	$profileFlashMsg = (string) ($_SESSION['profile_update_flash']['msg'] ?? '');
	$profileFlashType = (string) ($_SESSION['profile_update_flash']['type'] ?? 'success');
	unset($_SESSION['profile_update_flash']);
}

$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? (int) $_SESSION['user_id'] : 0;
$user = null;
$notification_count = 0;

$profileFields = alumni_profile_completion_field_names();
$profileCompletion = 0;

	if ($is_logged_in) {
	$notifications = [];
	$sql = 'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC';
	$stmt = @$conn->prepare($sql);
	if ($stmt) {
		$stmt->bind_param('i', $user_id);
		$stmt->execute();
		$notifications = [];
		$ngr = olfu_stmt_get_result($stmt);
		if ($ngr && method_exists($ngr, 'fetch_all')) {
			$notifications = $ngr->fetch_all(MYSQLI_ASSOC);
		} elseif ($ngr) {
			while ($nr = $ngr->fetch_assoc()) {
				$notifications[] = $nr;
			}
		}
		$stmt->close();
		$notification_count = count(array_filter($notifications, static function ($n) {
			return empty($n['is_read']);
		}));
	}

	try {
		$user = alumni_load_itcp_user($conn, $user_id);
	} catch (Throwable $e) {
		error_log('al_profileupdate load user: ' . $e->getMessage());
		$user = null;
	}
	if (!is_array($user)) {
		$stmt = $conn->prepare('SELECT * FROM itcp WHERE id = ? LIMIT 1');
		if ($stmt) {
			$stmt->bind_param('i', $user_id);
			if ($stmt->execute()) {
				$row = mysqli_stmt_fetch_assoc_compat($stmt);
				if (is_array($row)) {
					$user = array_change_key_case($row, CASE_LOWER);
				}
			}
			$stmt->close();
		}
	}

	if (!is_array($user)) {
		$user = [];
	}

	$profileCompletion = alumni_profile_completion_percent($user);
} else {
	header('Location: al_login.php');
	exit();
}

$profile_load_error = '';
if (!is_array($user) || $user === []) {
	$profile_load_error = 'We could not load your profile from the database. If you just registered, try again in a moment or contact Alumni Affairs.';
	$user = ['id' => $user_id];
}

/** Form value helper */
function pv(string $key, string $default = ''): string
{
	global $user;
	return alumni_profile_form_val($user, $key, $default);
}

/** Radio/select checked helper */
function pr_checked(string $key, string $option): bool
{
	global $user;
	return alumni_profile_radio_checked($user, $key, $option);
}

$itcpCols = [];
$rc = $conn->query('SHOW COLUMNS FROM itcp');
if ($rc) {
	while ($row = $rc->fetch_assoc()) {
		$itcpCols[strtolower((string) $row['Field'])] = true;
	}
	$rc->close();
}

/* AJAX: save signature only (no full profile submit). */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajax_sig_only'])) {
	header('Content-Type: application/json; charset=utf-8');
	if (!$is_logged_in) {
		echo json_encode(['success' => false, 'error' => 'Not logged in']);
		exit;
	}
	$sigData = $_POST['signature_data'] ?? '';
	if (!is_string($sigData) || strpos($sigData, 'data:image/png;base64,') !== 0) {
		echo json_encode(['success' => false, 'error' => 'Invalid signature data']);
		exit;
	}
	$b64 = substr($sigData, strpos($sigData, ',') + 1);
	$raw = base64_decode($b64, true);
	if ($raw === false || strlen($raw) < 50) {
		echo json_encode(['success' => false, 'error' => 'Invalid image']);
		exit;
	}
	$sig_dir = __DIR__ . '/uploads/signatures';
	if (!is_dir($sig_dir)) {
		@mkdir($sig_dir, 0777, true);
	}
	$sig_name = 'sig_' . $user_id . '_' . bin2hex(random_bytes(8)) . '.png';
	if (@file_put_contents($sig_dir . '/' . $sig_name, $raw) === false) {
		echo json_encode(['success' => false, 'error' => 'Could not save file']);
		exit;
	}
	$rel = 'signatures/' . $sig_name;
	$up = $conn->prepare('UPDATE itcp SET signature_path = ? WHERE id = ?');
	if (!$up) {
		echo json_encode(['success' => false, 'error' => $conn->error]);
		exit;
	}
	$up->bind_param('si', $rel, $user_id);
	if (!$up->execute()) {
		echo json_encode(['success' => false, 'error' => $up->error]);
		$up->close();
		exit;
	}
	$up->close();
	echo json_encode(['success' => true, 'dataUrl' => $sigData, 'path' => 'uploads/' . $rel]);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (isset($_POST['resume_parse_data']) && is_string($_POST['resume_parse_data'])) {
		$parsed = json_decode($_POST['resume_parse_data'], true);
		if (is_array($parsed)) {
			$directKeys = ['firstname', 'lastname', 'email', 'personal_contact', 'address', 'program', 'year_graduated', 'company', 'position', 'month_graduated', 'middlename', 'name_ext', 'birthday', 'gender', 'nationality', 'employment_status', 'industry', 'employment_history', 'previous_role', 'length_of_service'];
			foreach ($directKeys as $k) {
				if (!empty($parsed[$k]) && (empty($_POST[$k]) || $_POST[$k] === '')) {
					$_POST[$k] = $parsed[$k];
				}
			}
			$alias = [
				'phone_number' => 'personal_contact',
				'degree' => 'program',
				'graduation_year' => 'year_graduated',
				'current_company' => 'company',
				'current_job_title' => 'position',
			];
			foreach ($alias as $src => $dst) {
				if (!empty($parsed[$src]) && (empty($_POST[$dst]) || $_POST[$dst] === '')) {
					$_POST[$dst] = $parsed[$src];
				}
			}
		}
	}

	if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
		$upload_dir = __DIR__ . '/uploads';
		if (!is_dir($upload_dir)) {
			@mkdir($upload_dir, 0777, true);
		}
		$photo_tmp = $_FILES['photo']['tmp_name'];
		$allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$detected_type = $finfo ? finfo_file($finfo, $photo_tmp) : '';
		if ($finfo) {
			finfo_close($finfo);
		}
		if (!in_array($detected_type, $allowed_types, true)) {
			$profileFlashMsg = 'Only JPG, PNG and GIF files are allowed.';
			$profileFlashType = 'error';
		} elseif ($_FILES['photo']['size'] > 5 * 1024 * 1024) {
			$profileFlashMsg = 'File size must be less than 5MB.';
			$profileFlashType = 'error';
		} else {
			$photo_extension = strtolower(pathinfo((string) $_FILES['photo']['name'], PATHINFO_EXTENSION));
			$photo_name = uniqid('', true) . '.' . $photo_extension;
			$photo_path = $upload_dir . '/' . $photo_name;
			if (move_uploaded_file($photo_tmp, $photo_path)) {
				$old = __DIR__ . '/uploads/' . ($user['photo'] ?? '');
				if (!empty($user['photo']) && $user['photo'] !== 'default-avatar.png' && is_file($old)) {
					@unlink($old);
				}
				$user['photo'] = $photo_name;
			} else {
				$profileFlashMsg = 'Failed to upload photo.';
				$profileFlashType = 'error';
			}
		}
	}

	$signature_rel = $user['signature_path'] ?? '';
	if ($profileFlashMsg === '' && !empty($_POST['signature_data']) && is_string($_POST['signature_data']) && strpos((string) $_POST['signature_data'], 'data:image/png;base64,') === 0) {
		$b64 = substr($_POST['signature_data'], strpos($_POST['signature_data'], ',') + 1);
		$raw = base64_decode($b64, true);
		if ($raw !== false && strlen($raw) > 50) {
			$sig_dir = __DIR__ . '/uploads/signatures';
			if (!is_dir($sig_dir)) {
				@mkdir($sig_dir, 0777, true);
			}
			$sig_name = 'sig_' . $user_id . '_' . bin2hex(random_bytes(8)) . '.png';
			if (@file_put_contents($sig_dir . '/' . $sig_name, $raw) !== false) {
				$signature_rel = 'signatures/' . $sig_name;
			}
		}
	}

	if ($profileFlashMsg === '') {
		$emp_any = trim((string) ($_POST['company'] ?? '')) !== '' || trim((string) ($_POST['industry'] ?? '')) !== '' || trim((string) ($_POST['position'] ?? '')) !== '';
		$privacy_ok = !empty($_POST['data_privacy_consent']);
		if ($emp_any && !$privacy_ok) {
			$profileFlashMsg = 'Please confirm Data Privacy consent to save employment information.';
			$profileFlashType = 'error';
		}
	}

	if ($profileFlashMsg === '') {
		$post_birthday_chk = (string) ($_POST['birthday'] ?? '');
		if ($post_birthday_chk !== '' && !preg_match('/^0000-00-00/', $post_birthday_chk) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $post_birthday_chk)) {
			$bd_future = DateTime::createFromFormat('Y-m-d', $post_birthday_chk);
			if ($bd_future && $bd_future->format('Y-m-d') === $post_birthday_chk && $bd_future > new DateTime('today')) {
				$profileFlashMsg = 'Birthday cannot be in the future.';
				$profileFlashType = 'error';
			}
		}
	}

	if ($profileFlashMsg === '') {
		alumni_ensure_itcp_registration_columns($conn);
		$itcpCols = [];
		$rc = $conn->query('SHOW COLUMNS FROM itcp');
		if ($rc) {
			while ($row = $rc->fetch_assoc()) {
				$itcpCols[strtolower((string) $row['Field'])] = true;
			}
			$rc->close();
		}
		$age_for_db = al_profileupdate_age_from_birthday((string) ($_POST['birthday'] ?? ''));
		if ($age_for_db === '') {
			$age_for_db = '0';
		}

		$save = alumni_profile_save_from_post(
			$conn,
			$user_id,
			$user,
			$_POST,
			$itcpCols,
			(string) ($user['photo'] ?? ''),
			(string) $signature_rel,
			$age_for_db
		);

		if (empty($save['ok'])) {
			$profileFlashMsg = (string) ($save['error'] ?? 'Could not save profile.');
			$profileFlashType = 'error';
		} else {
			try {
				cps_ensure_schema($conn);
			} catch (Throwable $e) {
				error_log('cps_ensure_schema on profile save: ' . $e->getMessage());
			}
			try {
				cps_work_history_sync_from_profile(
					$conn,
					$user_id,
					(string) ($_POST['program'] ?? ''),
					(string) ($_POST['industry'] ?? ''),
					(string) ($_POST['position'] ?? ''),
					(string) ($_POST['company'] ?? ''),
					!empty($_POST['employment_private'])
				);
			} catch (Throwable $e) {
				error_log('cps_work_history_sync on profile save: ' . $e->getMessage());
			}

			$_SESSION['profile_update_flash'] = [
				'msg' => 'Profile updated successfully!',
				'type' => 'success',
			];
			header('Location: al_profileupdate.php');
			exit;
		}
	}
}

$photo = $user['photo'] ?? '';
$photo_src = '';
if ($photo !== '') {
	if (stripos((string) $photo, 'http') === 0) {
		$photo_src = $photo;
	} else {
		$photo_src = 'serve_profile_image.php?img=' . rawurlencode((string) $photo);
	}
}
$initials = strtoupper(substr((string) ($user['firstname'] ?? ''), 0, 1) . substr((string) ($user['lastname'] ?? ''), 0, 1));

$birthday_display = (string) ($user['birthday'] ?? '');
if (preg_match('/^0000-00-00/', $birthday_display)) {
	$birthday_display = '';
}
$lic_raw = strtolower(trim((string) ($user['licensure_exam'] ?? '')));
$lic_radio_vals = ['yes', 'no', 'not_applicable', 'not_yet'];
$lic_is_radio = in_array($lic_raw, $lic_radio_vals, true);
$lic_exam_text = $lic_is_radio ? '' : trim((string) ($user['licensure_exam'] ?? ''));
$age_display = al_profileupdate_age_from_birthday($birthday_display);
if ($age_display === '' && isset($user['age']) && trim((string) $user['age']) !== '') {
	$age_display = (string) (int) $user['age'];
}
$today_max = date('Y-m-d');

$existing_sig = '';
if (!empty($user['signature_path'])) {
	$sp = trim(str_replace(["\0", '\\'], ['', '/'], (string) $user['signature_path']));
	$sp = ltrim($sp, '/');
	if (stripos($sp, 'signatures/') === 0) {
		$full = realpath(__DIR__ . '/uploads/' . str_replace('/', DIRECTORY_SEPARATOR, $sp));
		$base = realpath(__DIR__ . '/uploads');
		if ($full && $base && is_file($full) && stripos($full, $base) === 0) {
			$existing_sig = 'uploads/' . $sp;
		}
	}
}

$firstname_h = (string) ($user['firstname'] ?? '');
$lastname_h = (string) ($user['lastname'] ?? '');
$fullname = trim($firstname_h . ' ' . (string) ($user['middlename'] ?? '') . ' ' . $lastname_h . ' ' . (string) ($user['name_ext'] ?? ''));
$program = (string) ($user['program'] ?? '');
$campus = (string) ($user['campus'] ?? '');
$year_grad = (string) ($user['year_graduated'] ?? '');
$status = (string) ($user['status'] ?? 'Active');
$ringR = 34.0;
$ringCirc = 2 * M_PI * $ringR;
$ringOffset = $ringCirc * (1 - min(100, max(0, $profileCompletion)) / 100);

/* Standalone form HTML for recovery if main page output is truncated on the server. */
if (isset($_GET['render_form']) && (string) $_GET['render_form'] === '1') {
	if (!$is_logged_in) {
		http_response_code(403);
		header('Content-Type: text/plain; charset=utf-8');
		echo 'Forbidden';
		exit;
	}
	header('Content-Type: text/html; charset=utf-8');
	header('X-Profile-Form-Fragment: 1');
	al_profileupdate_render_form();
	exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>Edit Profile – OLFU Alumni Portal</title>
	<link rel="icon" href="olfulogo.png" type="image/png" />
	<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet" />
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
	<style>
		:root{--cream:#F5F3EC;--cream-dark:#EDE9DF;--forest:#1A3D2B;--forest-mid:#2D6A4F;--forest-light:#4A9470;--gold:#C9A84C;--gold-light:#F0D98C;--ink:#1C1C1A;--ink-soft:#4A4A45;--ink-muted:#8A8A82;--white:#FFF;--red:#DC2626;--red-light:#FEE2E2;--green-light:#DCFCE7;--radius:16px;--shadow:0 2px 20px rgba(26,61,43,.08);--shadow-lg:0 8px 40px rgba(26,61,43,.14);}
		*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
		html{scroll-behavior:smooth;}
		body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--ink);line-height:1.6;min-height:100vh;padding-bottom:5.5rem;}
		body::before{content:'';position:fixed;inset:0;background-image:radial-gradient(circle,rgba(26,61,43,.04) 1px,transparent 1px);background-size:28px 28px;pointer-events:none;z-index:0;}
		.page-wrap{position:relative;z-index:1;max-width:1200px;margin:0 auto;padding:4rem 1.5rem 5rem;}
		.page-header{padding:1rem 0 1.25rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;}
		.page-header-left h1{font-family:'Cormorant Garamond',serif;font-size:clamp(2rem,4vw,2.8rem);font-weight:700;color:var(--forest);line-height:1.15;}
		.page-header-left h1 em{font-style:italic;color:var(--forest-mid);}
		.rule{height:3px;width:52px;background:linear-gradient(90deg,var(--forest-mid),var(--gold));border-radius:99px;margin-top:8px;}
		.page-header-left p{color:var(--ink-soft);font-size:.95rem;margin-top:6px;}
		.header-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
		.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:.875rem;font-weight:600;text-decoration:none;cursor:pointer;border:none;transition:all .2s;}
		.btn-forest{background:var(--forest);color:var(--white);}.btn-forest:hover{background:var(--forest-mid);}.btn-forest:disabled{opacity:.55;cursor:not-allowed;}
		.btn-outline{background:transparent;border:1.5px solid var(--forest-mid);color:var(--forest);}.btn-outline:hover{background:var(--cream-dark);}
		.btn-gold{background:var(--gold);color:var(--forest);}.btn-gold:hover{background:var(--gold-light);}
		.btn-sm{padding:7px 14px;font-size:.8rem;}
		.alert{padding:13px 16px;border-radius:10px;font-size:.875rem;display:flex;align-items:center;gap:10px;margin-bottom:1.25rem;}
		.alert .alert-msg{flex:1;min-width:0;color:inherit;font-weight:500;}
		.alert-success{background:var(--green-light);color:#166534;border:1px solid #86efac;}
		.alert-error{background:var(--red-light);color:#991b1b;border:1px solid #fca5a5;}
		.profile-hero{background:var(--forest);border-radius:var(--radius);overflow:hidden;margin-bottom:1.5rem;box-shadow:var(--shadow-lg);position:relative;}
		.profile-hero::before{content:'';position:absolute;right:-80px;top:-80px;width:320px;height:320px;border-radius:50%;background:radial-gradient(circle,rgba(201,168,76,.15),transparent 70%);pointer-events:none;}
		.hero-inner{position:relative;z-index:1;padding:2.5rem;display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:2rem;}
		.hero-left{display:flex;align-items:center;gap:1.75rem;flex-wrap:wrap;}
		.avatar-ring{width:100px;height:100px;border-radius:50%;border:3px solid rgba(201,168,76,.5);padding:3px;flex-shrink:0;cursor:pointer;position:relative;}
		.avatar-inner{width:100%;height:100%;border-radius:50%;overflow:hidden;background:rgba(255,255,255,.12);display:flex;align-items:center;justify-content:center;}
		.avatar-inner img{width:100%;height:100%;object-fit:cover;}
		.avatar-initials{font-family:'Cormorant Garamond',serif;font-size:2rem;font-weight:700;color:var(--gold-light);}
		.hero-cam{position:absolute;bottom:2px;right:2px;width:28px;height:28px;background:var(--gold);border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--forest);font-size:.65rem;border:2px solid var(--forest);}
		.hero-eyebrow{font-size:.65rem;font-weight:600;letter-spacing:.16em;text-transform:uppercase;color:var(--gold);margin-bottom:6px;}
		.hero-name{font-family:'Cormorant Garamond',serif;font-size:clamp(1.4rem,3vw,2rem);font-weight:700;color:var(--white);line-height:1.2;}
		.hero-program{color:rgba(255,255,255,.65);font-size:.9rem;margin-top:4px;}
		.hero-meta{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px;}
		.hero-chip{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);color:rgba(255,255,255,.85);font-size:.75rem;padding:4px 12px;border-radius:999px;}
		.hero-chip i{color:var(--gold);font-size:.7rem;}
		.hero-right{text-align:right;}
		.completion-label{font-size:.7rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.5);margin-bottom:8px;}
		.completion-ring{position:relative;width:80px;height:80px;margin-left:auto;}
		.completion-ring svg{transform:rotate(-90deg);}
		.completion-ring-bg{fill:none;stroke:rgba(255,255,255,.12);stroke-width:6;}
		.completion-ring-fill{fill:none;stroke:var(--gold);stroke-width:6;stroke-linecap:round;transition:stroke-dashoffset .8s ease;}
		.completion-pct{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:700;color:var(--white);}
		.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;}
		.stat-card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:1.25rem;display:flex;align-items:center;gap:12px;box-shadow:var(--shadow);}
		.stat-icon{width:40px;height:40px;border-radius:9px;background:var(--cream);display:flex;align-items:center;justify-content:center;color:var(--forest-mid);font-size:1rem;flex-shrink:0;}
		.stat-val{font-size:1.05rem;font-weight:700;color:var(--forest);line-height:1.2;}
		.stat-lbl{font-size:.72rem;color:var(--ink-muted);margin-top:2px;}
		.resume-card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:1.25rem 1.5rem;margin-bottom:1.5rem;box-shadow:var(--shadow);}
		.resume-card h3{font-family:'Cormorant Garamond',serif;font-size:1.1rem;font-weight:700;color:var(--forest);margin-bottom:4px;}
		.resume-card p{font-size:.82rem;color:var(--ink-muted);margin-bottom:12px;}
		.resume-drop{border:2px dashed var(--cream-dark);border-radius:12px;padding:1.25rem;text-align:center;cursor:pointer;transition:all .2s;background:var(--cream);}
		.resume-drop:hover,.resume-drop.drag-over{border-color:var(--forest-mid);background:var(--white);}
		.resume-drop i{font-size:1.6rem;color:var(--forest-light);margin-bottom:6px;}
		.resume-status{margin-top:10px;font-size:.83rem;border-radius:9px;padding:10px 13px;display:none;align-items:center;gap:8px;}
		.resume-status.visible{display:flex;}
		.resume-status.loading{background:var(--cream-dark);color:var(--ink-soft);}
		.resume-status.success{background:var(--green-light);color:#166534;}
		.resume-status.error{background:var(--red-light);color:#991b1b;}
		.tab-bar{display:flex;gap:6px;background:var(--white);border:1.5px solid var(--cream-dark);border-radius:12px;padding:5px;margin-bottom:2rem;box-shadow:var(--shadow);overflow-x:auto;}
		.tab-btn{flex-shrink:0;padding:9px 16px;border:none;background:transparent;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:.85rem;font-weight:500;color:var(--ink-soft);cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:7px;white-space:nowrap;}
		.tab-btn.active{background:var(--forest);color:var(--white);box-shadow:0 2px 8px rgba(26,61,43,.22);}
		.tab-btn:not(.active):hover{background:var(--cream);}
		.tab-panel{display:none;}
		.tab-panel.active{display:block !important;animation:fadeUp .28s ease;}
		#profileFormMount{min-height:200px;}
		#profileEditPageWrap{position:relative;z-index:2;pointer-events:auto;}
		#profileEditPageWrap .tab-btn,#profileEditPageWrap .avatar-ring,#profileEditPageWrap .resume-drop,#profileEditPageWrap label[for="photo"]{cursor:pointer;pointer-events:auto;}
		@keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
		.section-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.25rem;}
		.card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.75rem;}
		.card-head{display:flex;align-items:center;gap:10px;margin-bottom:1.25rem;}
		.card-icon{width:36px;height:36px;background:var(--cream);border-radius:9px;display:flex;align-items:center;justify-content:center;color:var(--forest-mid);font-size:.9rem;flex-shrink:0;}
		.card-title{font-family:'Cormorant Garamond',serif;font-size:1.2rem;font-weight:700;color:var(--forest);}
		.card-rule{height:2px;background:linear-gradient(90deg,var(--forest-mid),var(--gold));border-radius:99px;width:36px;margin-bottom:1.25rem;}
		.field-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:.85rem 1.25rem;}
		.field-item{display:flex;flex-direction:column;padding:.25rem 0;}
		.field-item.full{grid-column:1/-1;}
		.field-label{font-size:.68rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-muted);margin-bottom:6px;}
		.field-label .req{color:var(--red);}
		.form-input,.form-select,.form-textarea{width:100%;padding:10px 13px;border:1.5px solid var(--cream-dark);border-radius:9px;font-family:'DM Sans',sans-serif;font-size:.9rem;color:var(--ink);background:var(--cream);transition:border-color .15s,box-shadow .15s;outline:none;}
		.form-input:focus,.form-select:focus,.form-textarea:focus{border-color:var(--forest-mid);box-shadow:0 0 0 3px rgba(45,106,79,.1);background:var(--white);}
		.form-textarea{resize:vertical;min-height:80px;}
		.form-hint{font-size:.72rem;color:var(--ink-muted);margin-top:4px;}
		.form-input[readonly]{cursor:default;background:var(--cream-dark);}
		.form-input.birthday-future-err{border-color:var(--red);}
		.age-sync-note{font-size:.72rem;color:var(--forest-mid);margin-top:6px;display:flex;align-items:center;gap:6px;}
		.check-row{display:flex;align-items:flex-start;gap:10px;font-size:.875rem;color:var(--ink-soft);padding:8px 0;}
		.check-row input{flex-shrink:0;margin-top:2px;accent-color:var(--forest-mid);}
		.sig-section{background:var(--cream);border-radius:12px;border:1.5px solid var(--cream-dark);overflow:hidden;}
		.sig-tabs{display:flex;border-bottom:1px solid var(--cream-dark);}
		.sig-tab{flex:1;padding:10px 14px;background:none;border:none;font-family:'DM Sans',sans-serif;font-size:.83rem;font-weight:600;color:var(--ink-muted);cursor:pointer;border-bottom:2px solid transparent;}
		.sig-tab.active{color:var(--forest);border-bottom-color:var(--forest-mid);background:var(--white);}
		.sig-tab-panel{display:none;padding:14px;}.sig-tab-panel.active{display:block;}
		.sig-canvas-wrap{position:relative;background:var(--white);border:1.5px solid var(--cream-dark);border-radius:9px;overflow:hidden;cursor:crosshair;}
		.sig-canvas-wrap canvas{display:block;width:100%;height:130px;touch-action:none;}
		.sig-hint-overlay{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;}
		.sig-footer-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0 0;flex-wrap:wrap;gap:8px;}
		.sig-preview-wrap{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:9px;padding:10px;min-height:60px;display:flex;align-items:center;justify-content:center;}
		.sig-saved-toast{display:none;align-items:center;gap:8px;font-size:.82rem;color:#166534;background:var(--green-light);border:1px solid #86efac;border-radius:8px;padding:8px 12px;margin-top:10px;}
		.sig-saved-toast.show{display:flex;}
		.save-bar{position:fixed;bottom:0;left:0;right:0;z-index:90;display:flex;align-items:center;justify-content:space-between;background:var(--forest);padding:1rem 1.5rem;box-shadow:0 -4px 24px rgba(26,61,43,.25);flex-wrap:wrap;gap:1rem;}
		.save-bar-left{font-size:.85rem;color:rgba(255,255,255,.7);}.save-bar-left strong{color:var(--white);}
		.save-bar-right{display:flex;gap:10px;}
		.save-bar .btn-outline{border-color:rgba(255,255,255,.35);color:rgba(255,255,255,.9);}
		@media(max-width:900px){.section-grid,.field-grid{grid-template-columns:1fr;}.stats-row{grid-template-columns:repeat(2,1fr);}}
		@media(max-width:600px){.hero-inner{padding:1.75rem 1.5rem;}.hero-right{display:none;}.stats-row{grid-template-columns:1fr 1fr;}.tab-btn .tab-label{display:none;}.save-bar{flex-direction:column;align-items:stretch;}}
	</style>
</head>
<body>
<?php
$__profile_edit_ctx = [
	'user' => $user,
	'photo_src' => $photo_src,
	'initials' => $initials,
	'fullname' => $fullname,
	'program' => $program,
	'campus' => $campus,
	'year_grad' => $year_grad,
	'status' => $status,
	'ringR' => $ringR,
	'ringCirc' => $ringCirc,
	'ringOffset' => $ringOffset,
	'profileCompletion' => $profileCompletion,
	'birthday_display' => $birthday_display,
	'today_max' => $today_max,
	'age_display' => $age_display,
	'lic_exam_text' => $lic_exam_text,
	'existing_sig' => $existing_sig,
];
include __DIR__ . '/al_header_universal.php';
foreach ($__profile_edit_ctx as $__k => $__v) {
	$$__k = $__v;
}
unset($__profile_edit_ctx, $__k, $__v);
?>
<!-- profile-edit-build:<?php echo (string) filemtime(__FILE__); ?> -->
<div class="page-wrap" id="profileEditPageWrap">

	<header class="page-header">
		<div class="page-header-left">
			<h1>Edit <em>Profile</em></h1>
			<div class="rule"></div>
			<p>Update your alumni record — same layout as My Profile</p>
		</div>
		<div class="header-actions">
			<a href="al_profile.php" class="btn btn-outline" id="backToProfileBtn"><i class="fas fa-arrow-left"></i> Back to Profile</a>
			<button type="submit" form="profileForm" class="btn btn-forest" id="headerSaveBtn"><i class="fas fa-save"></i> Save Changes</button>
		</div>
	</header>

	<?php if ($profile_load_error !== '') : ?>
	<div class="alert alert-error">
		<i class="fas fa-exclamation-circle"></i>
		<?php echo htmlspecialchars($profile_load_error, ENT_QUOTES, 'UTF-8'); ?>
	</div>
	<?php endif; ?>

	<?php if ($profile_load_error === '' && $profileCompletion < 40) : ?>
	<div class="alert alert-error" style="background:#fffbeb;border-color:#fcd34d;color:#92400e;">
		<i class="fas fa-info-circle"></i>
		Some registration details are missing from your record. Fields below are loaded from your account; review each section and click <strong>Save Changes</strong>.
	</div>
	<?php endif; ?>

	<?php if ($profileFlashMsg !== '') : ?>
	<div class="alert alert-<?php echo $profileFlashType === 'success' ? 'success' : 'error'; ?>" role="status">
		<i class="fas fa-<?php echo $profileFlashType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
		<span class="alert-msg"><?php echo htmlspecialchars($profileFlashMsg, ENT_QUOTES, 'UTF-8'); ?></span>
	</div>
	<?php endif; ?>

	<div id="profileFormMount" data-build="<?php echo (string) filemtime(__FILE__); ?>">
	<?php
	try {
		echo '<div data-profile-form-server="1" style="display:none" aria-hidden="true"></div>';
		al_profileupdate_render_form();
	} catch (Throwable $e) {
		error_log('profile edit form render: ' . $e->getMessage());
		echo '<div class="alert alert-error" data-profile-form-server="1"><i class="fas fa-exclamation-circle"></i><span class="alert-msg">Could not display the form: '
			. htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</span></div>';
	}
	?>
	</div>
</div><!-- .page-wrap -->

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
var RING_CIRC = <?php echo json_encode($ringCirc); ?>;
var PROFILE_COMPLETION_FIELDS = <?php echo json_encode($profileFields, JSON_UNESCAPED_UNICODE); ?>;
var PROFILE_HAS_PHOTO = <?php echo alumni_profile_field_filled($user, 'photo') ? 'true' : 'false'; ?>;

function switchTab(id, btn) {
	var root = document.getElementById('profileEditPageWrap') || document;
	root.querySelectorAll('.tab-panel').forEach(function (p) {
		p.classList.remove('active');
		p.style.display = 'none';
	});
	root.querySelectorAll('.tab-btn').forEach(function (b) {
		b.classList.remove('active');
		b.setAttribute('aria-selected', 'false');
	});
	var panel = document.getElementById('tab-' + id);
	if (panel) {
		panel.style.display = 'block';
		requestAnimationFrame(function () { panel.classList.add('active'); });
	}
	if (btn) {
		btn.classList.add('active');
		btn.setAttribute('aria-selected', 'true');
	}
}
window.switchTab = switchTab;

(function () {
	var root = document.getElementById('profileEditPageWrap') || document;
	root.querySelectorAll('.tab-panel').forEach(function (p) {
		if (!p.classList.contains('active')) p.style.display = 'none';
	});
	var tabBar = document.querySelector('#profileForm .tab-bar');
	if (tabBar) {
		tabBar.addEventListener('click', function (e) {
			var btn = e.target.closest('.tab-btn');
			if (!btn) return;
			var onclick = btn.getAttribute('onclick') || '';
			var m = onclick.match(/switchTab\s*\(\s*['"](\w+)['"]/);
			if (m) switchTab(m[1], btn);
		});
	}
	if (window.location.hash === '#sec-signature') {
		var sigBtn = document.querySelector('#profileForm .tab-btn[onclick*="signature"]');
		switchTab('signature', sigBtn);
	}
})();

function switchSigTab(name, btn) {
	document.querySelectorAll('.sig-tab').forEach(function (t) { t.classList.remove('active'); });
	document.querySelectorAll('.sig-tab-panel').forEach(function (p) { p.classList.remove('active'); });
	if (btn) btn.classList.add('active');
	var panel = document.getElementById('sig-' + name + '-panel');
	if (panel) panel.classList.add('active');
	if (name === 'draw' && window._sigPad) {
		setTimeout(function () { if (typeof resizeSigCanvas === 'function') resizeSigCanvas(); }, 50);
	}
}
window.switchSigTab = switchSigTab;

/** Same logic as al_registration.php updateAgeFromBirthday() */
function updateAgeFromBirthday() {
	var inp = document.getElementById('birthday');
	var ageEl = document.getElementById('age');
	if (!inp || !ageEl) return;
	var v = inp.value;
	if (!v) {
		ageEl.value = '';
		inp.classList.remove('birthday-future-err');
		return;
	}
	var parts = v.split('-');
	if (parts.length !== 3) {
		ageEl.value = '';
		return;
	}
	var y = parseInt(parts[0], 10);
	var mo = parseInt(parts[1], 10) - 1;
	var d = parseInt(parts[2], 10);
	if (isNaN(y) || isNaN(mo) || isNaN(d)) {
		ageEl.value = '';
		return;
	}
	var bd = new Date(y, mo, d);
	if (bd.getFullYear() !== y || bd.getMonth() !== mo || bd.getDate() !== d) {
		ageEl.value = '';
		return;
	}
	var today = new Date();
	today.setHours(0, 0, 0, 0);
	bd.setHours(0, 0, 0, 0);
	if (bd > today) {
		ageEl.value = '';
		inp.classList.add('birthday-future-err');
		return;
	}
	inp.classList.remove('birthday-future-err');
	var age = today.getFullYear() - bd.getFullYear();
	var mDiff = today.getMonth() - bd.getMonth();
	if (mDiff < 0 || (mDiff === 0 && today.getDate() < bd.getDate())) age--;
	if (age < 0) age = 0;
	ageEl.value = String(age);
}

window.initProfilePhotoUpload = function () {
	var photoInput = document.getElementById('photo');
	var photoErr = document.getElementById('photoError');
	var wrap = document.getElementById('photoPreviewWrap');
	var initialsEl = document.getElementById('photoInitials');
	if (!photoInput || photoInput.dataset.bound === '1') return;
	photoInput.dataset.bound = '1';
	photoInput.addEventListener('change', function () {
		var file = this.files[0];
		if (!file) return;
		if (!['image/jpeg', 'image/png', 'image/gif'].includes(file.type)) {
			photoErr.textContent = 'Only JPG, PNG, GIF allowed.';
			return;
		}
		if (file.size > 5 * 1024 * 1024) {
			photoErr.textContent = 'Max file size is 5 MB.';
			return;
		}
		photoErr.textContent = '';
		var reader = new FileReader();
		reader.onload = function (e) {
			var img = document.getElementById('photoPreviewImg');
			if (!img) {
				img = document.createElement('img');
				img.id = 'photoPreviewImg';
				img.alt = 'Photo';
				img.style.cssText = 'width:100%;height:100%;object-fit:cover;';
				wrap.insertBefore(img, wrap.firstChild);
			}
			img.src = e.target.result;
			img.style.display = 'block';
			if (initialsEl) initialsEl.style.display = 'none';
			PROFILE_HAS_PHOTO = true;
			if (typeof calcCompletion === 'function') calcCompletion();
		};
		reader.readAsDataURL(file);
	});
};
window.initProfilePhotoUpload();

function profileEditFieldFilled(name) {
	if (name === 'photo') {
		if (PROFILE_HAS_PHOTO) return true;
		var photoIn = document.getElementById('photo');
		if (photoIn && photoIn.files && photoIn.files.length > 0) return true;
		var img = document.getElementById('photoPreviewImg');
		return !!(img && img.src && img.style.display !== 'none');
	}
	var nodes = document.querySelectorAll('#profileForm [name="' + name + '"]');
	if (!nodes.length) return false;
	var el = nodes[0];
	if (el.type === 'radio') {
		var checked = document.querySelector('#profileForm [name="' + name + '"]:checked');
		return !!(checked && String(checked.value).trim() !== '');
	}
	if (el.type === 'checkbox') {
		return el.checked;
	}
	return el.value && String(el.value).trim() !== '';
}
function calcCompletion() {
	var fields = PROFILE_COMPLETION_FIELDS || [];
	var filled = 0;
	fields.forEach(function (name) {
		if (profileEditFieldFilled(name)) filled++;
	});
	var total = fields.length || 1;
	var pct = Math.round((filled / total) * 100);
	var pctEl = document.getElementById('progressPct');
	var sbar = document.getElementById('saveBarPct');
	var ring = document.getElementById('progressRingFill');
	if (pctEl) pctEl.textContent = pct + '%';
	if (sbar) sbar.textContent = pct + '%';
	if (ring && RING_CIRC) ring.setAttribute('stroke-dashoffset', String(RING_CIRC * (1 - pct / 100)));
}
window.bindProfileFormCompletion = function () {
	var profileForm = document.getElementById('profileForm');
	if (!profileForm || profileForm.dataset.completionBound === '1') return;
	profileForm.dataset.completionBound = '1';
	profileForm.addEventListener('input', calcCompletion);
	profileForm.addEventListener('change', calcCompletion);
	profileForm.addEventListener('submit', function () {
		updateAgeFromBirthday();
	}, true);
	setTimeout(function () {
		updateAgeFromBirthday();
		calcCompletion();
	}, 80);
};
window.bindProfileFormCompletion();

(function () {
	function goProfile(e) {
		if (e) e.preventDefault();
		window.location.assign('al_profile.php');
	}
	['backToProfileBtn', 'cancelToProfileBtn'].forEach(function (id) {
		var el = document.getElementById(id);
		if (el) el.addEventListener('click', goProfile);
	});
})();


window.initProfileResumeUpload = function () {
	var drop = document.getElementById('resumeDrop');
	var input = document.getElementById('resumeInput');
	var loadingEl = document.getElementById('resumeLoading');
	var successEl = document.getElementById('resumeSuccess');
	var errorEl = document.getElementById('resumeError');
	var errorText = document.getElementById('resumeErrorText');
	var successText = document.getElementById('resumeSuccessText');
	var saveBtn = document.getElementById('saveBtn');
	if (!drop || !input || drop.dataset.bound === '1') return;
	drop.dataset.bound = '1';
	function hideAll() {
		[loadingEl, successEl, errorEl].forEach(function (e) { if (e) { e.classList.remove('visible'); } });
	}
	function setVal(key, val) {
		if (val === null || val === undefined || val === '') return;
		var el = document.querySelector('[name="' + key + '"]');
		if (!el) return;
		if (el.tagName === 'SELECT') {
			var opt = Array.from(el.options).find(function (o) { return o.value.toLowerCase() === String(val).toLowerCase(); });
			el.value = opt ? opt.value : String(val);
		} else {
			el.value = String(val);
		}
		try { el.dispatchEvent(new Event('input', { bubbles: true })); } catch (_) {}
		try { el.dispatchEvent(new Event('change', { bubbles: true })); } catch (_) {}
	}
	function handleFiles(files) {
		var file = files && files[0];
		if (!file) return;
		var name = (file.name || '').toLowerCase();
		var ok = file.type === 'application/pdf' || file.type === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' || name.endsWith('.pdf') || name.endsWith('.docx');
		if (!ok) {
			errorText.textContent = 'Only PDF or DOCX files are supported.';
			hideAll();
			errorEl.classList.add('visible');
			return;
		}
		var fd = new FormData();
		fd.append('resume', file);
		hideAll();
		loadingEl.classList.add('visible');
		if (saveBtn) saveBtn.disabled = true;
		fetch('api/alumni/parse_resume.php', { method: 'POST', body: fd })
			.then(function (r) { return r.text().then(function (t) { try { return JSON.parse(t); } catch (e) { throw new Error(t.substring(0, 200) || 'Invalid JSON'); } }); })
			.then(function (data) {
				if (!data || !data.success) throw new Error((data && data.error) ? data.error : 'Failed to parse resume');
				var d = data.data || {};
				var prefer = function () {
					for (var i = 0; i < arguments.length; i++) if (arguments[i] !== undefined && arguments[i] !== null && arguments[i] !== '') return arguments[i];
				};
				setVal('firstname', prefer(d.firstname, d.first_name));
				setVal('lastname', prefer(d.lastname, d.last_name));
				setVal('middlename', prefer(d.middlename, d.middle_name));
				setVal('email', d.email);
				setVal('personal_contact', prefer(d.personal_contact, d.phone_number, d.phone));
				setVal('address', d.address);
				setVal('birthday', prefer(d.birthday, d.date_of_birth));
				if (typeof updateAgeFromBirthday === 'function') updateAgeFromBirthday();
				setVal('gender', d.gender);
				setVal('nationality', d.nationality);
				setVal('program', prefer(d.program, d.degree));
				setVal('year_graduated', prefer(d.year_graduated, d.graduation_year));
				setVal('month_graduated', prefer(d.month_graduated, d.graduation_month));
				setVal('company', prefer(d.company, d.current_company));
				setVal('position', prefer(d.position, d.current_job_title));
				setVal('employment_status', d.employment_status);
				setVal('industry', d.industry);
				setVal('employment_history', prefer(d.employment_history, d.work_experience));
				setVal('previous_role', d.previous_role);
				setVal('length_of_service', d.length_of_service);
				if (Array.isArray(d.skills) && d.skills.length) setVal('skills', d.skills.join(', '));
				var hidden = document.getElementById('resumeParsedJSON');
				if (hidden) hidden.value = JSON.stringify(d);
				switchTab('personal', document.querySelector('.tab-btn[onclick*="personal"]'));
				successText.textContent = 'Profile fields auto-filled from your resume. Please review and click Save Changes.';
				successEl.classList.add('visible');
				calcCompletion();
			})
			.catch(function (err) {
				errorText.textContent = err.message || 'Could not parse resume.';
				errorEl.classList.add('visible');
			})
			.finally(function () {
				loadingEl.classList.remove('visible');
				if (saveBtn) saveBtn.disabled = false;
			});
	}
	drop.addEventListener('click', function () { input.click(); });
	drop.addEventListener('dragover', function (e) { e.preventDefault(); drop.classList.add('drag-over'); });
	drop.addEventListener('dragleave', function () { drop.classList.remove('drag-over'); });
	drop.addEventListener('drop', function (e) {
		e.preventDefault();
		drop.classList.remove('drag-over');
		handleFiles(e.dataTransfer.files);
	});
	input.addEventListener('change', function (e) { handleFiles(e.target.files); });
};
window.initProfileResumeUpload();

window.initProfileEditSignaturePad = function () {
	var canvas = document.getElementById('sigPad');
	var hint = document.getElementById('sigHint');
	var clearBtn = document.getElementById('sigClear');
	var saveBtn = document.getElementById('sigSaveNow');
	var toast = document.getElementById('sigSavedToast');
	var dataInput = document.getElementById('signatureData');
	if (!canvas || typeof SignaturePad === 'undefined') return;
	if (canvas.dataset.sigReady === '1') return;
	canvas.dataset.sigReady = '1';

	var ratio = Math.max(window.devicePixelRatio || 1, 1);
	function resizeSigCanvas() {
		var wrap = document.getElementById('sigCanvasWrap');
		if (!wrap) return;
		var w = wrap.offsetWidth || 600;
		var strokeData = null;
		if (window._sigPad && !window._sigPad.isEmpty()) {
			try { strokeData = window._sigPad.toData(); } catch (_) {}
		}
		canvas.width = w * ratio;
		canvas.height = 130 * ratio;
		canvas.style.width = w + 'px';
		canvas.style.height = '130px';
		var ctx = canvas.getContext('2d');
		ctx.setTransform(1, 0, 0, 1, 0, 0);
		ctx.scale(ratio, ratio);
		if (window._sigPad) {
			window._sigPad.clear();
			if (strokeData && strokeData.length) {
				try { window._sigPad.fromData(strokeData); } catch (_) {}
			}
		}
	}
	window.resizeSigCanvas = resizeSigCanvas;
	resizeSigCanvas();

	var sp = new SignaturePad(canvas, {
		backgroundColor: 'rgba(0,0,0,0)',
		penColor: '#1A3D2B',
		minWidth: 0.8,
		maxWidth: 2.2,
		throttle: 0,
		minDistance: 1
	});
	window._sigPad = sp;

	canvas.addEventListener('pointerdown', function () { if (hint) hint.style.opacity = '0'; });
	sp.addEventListener('beginStroke', function () { if (hint) hint.style.opacity = '0'; });
	sp.addEventListener('afterUpdateStroke', function () { if (hint) hint.style.opacity = '0'; });

	if (clearBtn) {
		clearBtn.addEventListener('click', function () {
			sp.clear();
			if (hint) hint.style.opacity = '1';
			if (toast) toast.classList.remove('show');
		});
	}

	function broadcastSig(dataUrl) {
		window.dispatchEvent(new CustomEvent('alumni_sig_saved', { detail: { dataUrl: dataUrl } }));
		try {
			if (window.opener && !window.opener.closed) {
				window.opener.postMessage({ type: 'alumni_sig_saved', dataUrl: dataUrl }, window.location.origin);
			}
		} catch (_) {}
		try {
			if (window.parent !== window) {
				window.parent.postMessage({ type: 'alumni_sig_saved', dataUrl: dataUrl }, window.location.origin);
			}
		} catch (_) {}
		try {
			var ch = new BroadcastChannel('alumni_sig');
			ch.postMessage({ type: 'alumni_sig_saved', dataUrl: dataUrl });
			ch.close();
		} catch (_) {}
	}

	if (saveBtn) {
		saveBtn.addEventListener('click', function () {
			if (sp.isEmpty()) {
				alert('Please draw your signature first.');
				return;
			}
			var dataUrl = sp.toDataURL('image/png');
			if (dataInput) dataInput.value = dataUrl;
			saveBtn.disabled = true;
			saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
			var fd = new FormData();
			fd.append('ajax_sig_only', '1');
			fd.append('signature_data', dataUrl);
			fetch(window.location.pathname, { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (j) {
					if (!j || !j.success) throw new Error((j && j.error) ? j.error : 'Save failed');
					saveBtn.disabled = false;
					saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Signature';
					if (toast) toast.classList.add('show');
					broadcastSig(dataUrl);
					var exImg = document.getElementById('existingSigImg');
					if (exImg) {
						exImg.src = dataUrl;
					} else {
						var pw = document.querySelector('.sig-preview-wrap');
						if (pw) {
							var ni = document.createElement('img');
							ni.id = 'existingSigImg';
							ni.alt = 'Saved signature';
							ni.style.cssText = 'max-height:60px;max-width:100%;object-fit:contain;';
							ni.src = dataUrl;
							pw.innerHTML = '';
							pw.appendChild(ni);
						}
					}
				})
				.catch(function () {
					saveBtn.disabled = false;
					saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Signature';
					alert('Could not save signature. Please try again or use Save Changes at the bottom.');
				});
		});
	}

	var mainForm = document.getElementById('profileForm');
	if (mainForm) {
		mainForm.addEventListener('submit', function () {
			if (!sp.isEmpty() && dataInput) dataInput.value = sp.toDataURL('image/png');
		});
	}
	window.addEventListener('resize', resizeSigCanvas);
};
window.initProfileEditSignaturePad();

(function () {
	function loadFormFragment() {
		var mount = document.getElementById('profileFormMount');
		if (!mount) return;
		mount.innerHTML = '<div class="alert" style="background:var(--cream-dark);color:var(--ink-soft);"><i class="fas fa-spinner fa-spin"></i> Loading form…</div>';
		fetch('al_profileupdate.php?render_form=1', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
			.then(function (r) { return r.ok ? r.text() : Promise.reject(new Error('HTTP ' + r.status)); })
			.then(function (html) {
				if (!html || html.indexOf('profile-edit-form-start') === -1) {
					throw new Error('Invalid form response');
				}
				mount.innerHTML = html;
				if (typeof window.bindProfileFormCompletion === 'function') window.bindProfileFormCompletion();
				if (typeof window.initProfilePhotoUpload === 'function') window.initProfilePhotoUpload();
				if (typeof window.initProfileResumeUpload === 'function') window.initProfileResumeUpload();
				if (typeof window.initProfileEditSignaturePad === 'function') window.initProfileEditSignaturePad();
				if (typeof updateAgeFromBirthday === 'function') updateAgeFromBirthday();
				if (typeof calcCompletion === 'function') calcCompletion();
			})
			.catch(function () {
				mount.innerHTML = '<div class="alert alert-error" role="alert"><i class="fas fa-exclamation-circle"></i><span class="alert-msg">'
					+ 'The edit form did not load. Re-upload <strong>al_profileupdate.php</strong> and <strong>al_profile_update_form.inc.php</strong> (same <code>public_html</code> folder), then hard-refresh (Ctrl+F5). '
					+ 'In View Source, search for <code>profile-edit-build:</code> — if missing, the server file is outdated.</span></div>';
			});
	}

	document.addEventListener('DOMContentLoaded', function () {
		var mount = document.getElementById('profileFormMount');
		if (document.getElementById('profileForm')) {
			if (typeof updateAgeFromBirthday === 'function') updateAgeFromBirthday();
			if (typeof calcCompletion === 'function') calcCompletion();
			return;
		}
		if (mount && mount.querySelector('[data-profile-form-server]')) {
			return;
		}
		loadFormFragment();
	});
})();
</script>
<?php if (is_file(__DIR__ . '/al_footer_universal.php')) { include __DIR__ . '/al_footer_universal.php'; } ?>
</body>
</html>
