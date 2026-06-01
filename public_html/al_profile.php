<?php
/**
 * Alumni Profile — displays the logged-in user's profile data.
 * Enhanced UI matching OLFU Alumni Portal design system (Career / Alumni Card).
 */
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/includes/mysqli_compat.php';
require_once __DIR__ . '/includes/alumni_profile_lib.php';
require_once __DIR__ . '/includes/cps_alumni_lib.php';
session_start();
alumni_otp_gate_after_session();
$conn = getDBConnection();
alumni_ensure_itcp_registration_columns($conn);
try {
	cps_ensure_schema($conn);
} catch (Throwable $e) {
	error_log('cps_ensure_schema on profile view: ' . $e->getMessage());
}

if (!isset($_SESSION['user_id'])) {
	header('Location: al_login.php');
	exit();
}

$user_id = (int) $_SESSION['user_id'];

$user = alumni_load_itcp_user($conn, $user_id);
if (!is_array($user) || $user === []) {
	echo 'No user data found.';
	exit();
}

$vaStmt = $conn->prepare('SELECT validity_date, cps_alumni_id FROM verified_alumni WHERE itcp_id = ? LIMIT 1');
if ($vaStmt) {
	$vaStmt->bind_param('i', $user_id);
	$vaStmt->execute();
	$vaRow = $vaStmt->get_result()->fetch_assoc();
	$vaStmt->close();
	if (is_array($vaRow)) {
		$vaRow = array_change_key_case($vaRow, CASE_LOWER);
		if (!empty($vaRow['validity_date'])) {
			$user['validity_date'] = $vaRow['validity_date'];
		}
		if (!empty($vaRow['cps_alumni_id'])) {
			$user['cps_alumni_id'] = $vaRow['cps_alumni_id'];
		}
	}
}

function userVal($user, $key)
{
	if (!is_array($user)) {
		return null;
	}
	$kl = strtolower($key);
	foreach ($user as $k => $v) {
		if (strtolower((string) $k) === $kl) {
			return $v;
		}
	}

	return null;
}

function displayField($field)
{
	if ($field === null || $field === '') {
		return '<span class="na">N/A</span>';
	}
	$s = trim((string) $field);
	if ($s === '') {
		return '<span class="na">N/A</span>';
	}

	return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function displayBirthday($value)
{
	if ($value === null || $value === '' || preg_match('/^0000-00-00/', trim((string) $value))) {
		return '<span class="na">N/A</span>';
	}
	$t = strtotime(trim((string) $value));
	if ($t === false) {
		return htmlspecialchars(trim((string) $value), ENT_QUOTES, 'UTF-8');
	}

	return htmlspecialchars(date('F j, Y', $t), ENT_QUOTES, 'UTF-8');
}

function displayAge($value)
{
	if ($value === null || $value === '' || (int) $value <= 0) {
		return '<span class="na">N/A</span>';
	}

	return (int) $value . ' yrs';
}

function displayMonthGraduated($value)
{
	if ($value === null || $value === '') {
		return '<span class="na">N/A</span>';
	}
	$v = trim((string) $value);
	$months = ['01' => 'January', '02' => 'February', '03' => 'March', '04' => 'April', '05' => 'May', '06' => 'June',
		'07' => 'July', '08' => 'August', '09' => 'September', '10' => 'October', '11' => 'November', '12' => 'December'];

	return isset($months[$v]) ? $months[$v] : htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

$profileFields = alumni_profile_completion_field_names();
$profileCompletion = alumni_profile_completion_percent($user);

$photo = userVal($user, 'photo') ?? '';
$photo_src = '';
if ($photo !== '') {
	if (stripos((string) $photo, 'http') === 0) {
		$photo_src = $photo;
	} else {
		$photo_src = 'serve_profile_image.php?img=' . rawurlencode(basename((string) $photo));
	}
}
$firstname = (string) (userVal($user, 'firstname') ?? '');
$lastname = (string) (userVal($user, 'lastname') ?? '');
$initials = strtoupper(substr($firstname, 0, 1) . substr($lastname, 0, 1));
$fullname = trim($firstname . ' ' . (string) (userVal($user, 'middlename') ?? '') . ' ' . $lastname . ' ' . (string) (userVal($user, 'name_ext') ?? ''));
$program = (string) (userVal($user, 'degree') ?? userVal($user, 'program') ?? '');
$campus = (string) (userVal($user, 'campus') ?? '');
$year_grad = (string) (userVal($user, 'year_graduated') ?? '');
$status = (string) (userVal($user, 'status') ?? 'Active');

$student_no = (string) (userVal($user, 'student_number') ?? '');
$card_no_display = '';
if ($student_no !== '') {
	$sn = preg_replace('/\D/', '', $student_no) ?? '';
	if ($sn !== '') {
		$sn = str_pad(substr($sn, 0, 16), 16, '0');
		$card_no_display = trim(chunk_split($sn, 4, ' '));
	}
}
$validity_db = trim((string) (userVal($user, 'validity_date') ?? ''));
$valid_display = '';
if ($validity_db !== '') {
	$valid_display = $validity_db;
} else {
	$vy = (int) preg_replace('/\D/', '', $year_grad);
	if ($vy >= 1990 && $vy <= 2100) {
		$valid_display = 'DECEMBER ' . (string) ($vy + 3);
	}
}

$sig_preview = '';
$sp0 = userVal($user, 'signature_path');
if ($sp0 !== null && (string) $sp0 !== '') {
	$sp = trim(str_replace(["\0", '\\'], ['', '/'], (string) $sp0));
	$sp = ltrim($sp, '/');
	if (stripos($sp, 'signatures/') === 0) {
		$full = realpath(__DIR__ . '/uploads/' . str_replace('/', DIRECTORY_SEPARATOR, $sp));
		$base = realpath(__DIR__ . '/uploads');
		if ($full && $base && is_file($full) && stripos($full, $base) === 0) {
			$sig_preview = 'uploads/' . $sp;
		}
	}
}

$ringR = 34.0;
$ringCirc = 2 * M_PI * $ringR;
$ringOffset = $ringCirc * (1 - min(100, max(0, $profileCompletion)) / 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>My Profile – OLFU Alumni Portal</title>
	<link rel="icon" href="olfulogo.png" type="image/png" />
	<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet" />
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
	<style>
		:root {
			--cream:        #F5F3EC;
			--cream-dark:   #EDE9DF;
			--forest:       #1A3D2B;
			--forest-mid:   #2D6A4F;
			--forest-light: #4A9470;
			--gold:         #C9A84C;
			--gold-light:   #F0D98C;
			--ink:          #1C1C1A;
			--ink-soft:     #4A4A45;
			--ink-muted:    #8A8A82;
			--white:        #FFFFFF;
			--radius:       16px;
			--shadow:       0 2px 20px rgba(26,61,43,0.08);
			--shadow-lg:    0 8px 40px rgba(26,61,43,0.14);
		}
		*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
		html{scroll-behavior:smooth;}
		body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--ink);line-height:1.6;min-height:100vh;}
		body::before{content:'';position:fixed;inset:0;background-image:radial-gradient(circle,rgba(26,61,43,0.04) 1px,transparent 1px);background-size:28px 28px;pointer-events:none;z-index:0;}
		.page-wrap{position:relative;z-index:1;max-width:1200px;margin:0 auto;padding:4rem 1.5rem 5rem;}
		.page-header{padding:1rem 0 1.25rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;}
		.page-header-left h1{font-family:'Cormorant Garamond',serif;font-size:clamp(2rem,4vw,2.8rem);font-weight:700;color:var(--forest);line-height:1.15;}
		.page-header-left h1 em{font-style:italic;color:var(--forest-mid);}
		.rule{height:3px;width:52px;background:linear-gradient(90deg,var(--forest-mid),var(--gold));border-radius:99px;margin-top:8px;}
		.page-header-left p{color:var(--ink-soft);font-size:.95rem;margin-top:6px;}
		.btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:10px;font-family:'DM Sans',sans-serif;font-size:.875rem;font-weight:600;text-decoration:none;cursor:pointer;border:none;transition:all .2s;}
		.btn-forest{background:var(--forest);color:var(--white);}
		.btn-forest:hover{background:var(--forest-mid);}
		.btn-outline{background:transparent;border:1.5px solid var(--forest-mid);color:var(--forest);}
		.btn-outline:hover{background:var(--cream-dark);}
		.profile-hero{background:var(--forest);border-radius:var(--radius);overflow:hidden;margin-bottom:1.5rem;box-shadow:var(--shadow-lg);position:relative;}
		.profile-hero::before{content:'';position:absolute;right:-80px;top:-80px;width:320px;height:320px;border-radius:50%;background:radial-gradient(circle,rgba(201,168,76,0.15),transparent 70%);pointer-events:none;}
		.profile-hero::after{content:'';position:absolute;left:-40px;bottom:-40px;width:200px;height:200px;border-radius:50%;background:radial-gradient(circle,rgba(74,148,112,0.15),transparent 70%);pointer-events:none;}
		.hero-inner{position:relative;z-index:1;padding:2.5rem;display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:2rem;}
		.hero-left{display:flex;align-items:center;gap:1.75rem;flex-wrap:wrap;}
		.avatar-ring{width:100px;height:100px;border-radius:50%;border:3px solid rgba(201,168,76,0.5);padding:3px;flex-shrink:0;}
		.avatar-inner{width:100%;height:100%;border-radius:50%;overflow:hidden;background:rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;}
		.avatar-inner img{width:100%;height:100%;object-fit:cover;}
		.avatar-initials{font-family:'Cormorant Garamond',serif;font-size:2rem;font-weight:700;color:var(--gold-light);}
		.hero-eyebrow{font-size:.65rem;font-weight:600;letter-spacing:.16em;text-transform:uppercase;color:var(--gold);margin-bottom:6px;}
		.hero-name{font-family:'Cormorant Garamond',serif;font-size:clamp(1.4rem,3vw,2rem);font-weight:700;color:var(--white);line-height:1.2;}
		.hero-program{color:rgba(255,255,255,.65);font-size:.9rem;margin-top:4px;}
		.hero-meta{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px;}
		.hero-chip{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);color:rgba(255,255,255,.85);font-size:.75rem;padding:4px 12px;border-radius:999px;}
		.hero-chip i{color:var(--gold);font-size:.7rem;}
		.status-badge{padding:4px 14px;border-radius:999px;font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;}
		.status-active{background:rgba(74,148,112,.25);color:#6ee7b7;border:1px solid rgba(110,231,183,.3);}
		.hero-right{text-align:right;}
		.completion-label{font-size:.7rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:rgba(255,255,255,.5);margin-bottom:8px;}
		.completion-ring{position:relative;width:80px;height:80px;margin-left:auto;}
		.completion-ring svg{transform:rotate(-90deg);}
		.completion-ring-bg{fill:none;stroke:rgba(255,255,255,.12);stroke-width:6;}
		.completion-ring-fill{fill:none;stroke:var(--gold);stroke-width:6;stroke-linecap:round;transition:stroke-dashoffset .8s ease;}
		.completion-pct{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:700;color:var(--white);}
		.tab-bar{display:flex;gap:6px;background:var(--white);border:1.5px solid var(--cream-dark);border-radius:12px;padding:5px;margin-bottom:2rem;box-shadow:var(--shadow);overflow-x:auto;}
		.tab-btn{flex-shrink:0;padding:9px 16px;border:none;background:transparent;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:.85rem;font-weight:500;color:var(--ink-soft);cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:7px;white-space:nowrap;}
		.tab-btn.active{background:var(--forest);color:var(--white);box-shadow:0 2px 8px rgba(26,61,43,.22);}
		.tab-btn:not(.active):hover{background:var(--cream);}
		.tab-panel{display:none;}
		.tab-panel.active{display:block;animation:fadeUp .28s ease;}
		@keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
		.card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.75rem;transition:box-shadow .2s;}
		.card:hover{box-shadow:var(--shadow-lg);}
		.card-head{display:flex;align-items:center;gap:10px;margin-bottom:1.25rem;}
		.card-icon{width:36px;height:36px;background:var(--cream);border-radius:9px;display:flex;align-items:center;justify-content:center;color:var(--forest-mid);font-size:.9rem;flex-shrink:0;}
		.card-title{font-family:'Cormorant Garamond',serif;font-size:1.2rem;font-weight:700;color:var(--forest);}
		.card-rule{height:2px;background:linear-gradient(90deg,var(--forest-mid),var(--gold));border-radius:99px;width:36px;margin-bottom:1.25rem;}
		.field-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:.85rem 1.5rem;}
		.field-item{padding:.75rem 0;border-bottom:1px solid var(--cream-dark);}
		.field-item:last-child,.field-item:nth-last-child(2):nth-child(odd){border-bottom:none;}
		.field-label{font-size:.68rem;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--ink-muted);margin-bottom:3px;}
		.field-value{font-size:.9rem;color:var(--ink);font-weight:500;}
		.na{color:var(--ink-muted);font-style:italic;font-weight:400;}
		.field-full{grid-column:1/-1;}
		.section-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.25rem;}
		.emp-highlight{background:var(--forest);border-radius:var(--radius);padding:1.75rem;color:var(--white);margin-bottom:1.25rem;position:relative;overflow:hidden;}
		.emp-highlight::before{content:'';position:absolute;right:-30px;top:-30px;width:160px;height:160px;border-radius:50%;background:radial-gradient(circle,rgba(201,168,76,.15),transparent 70%);}
		.emp-title{font-family:'Cormorant Garamond',serif;font-size:1.5rem;font-weight:700;color:var(--white);line-height:1.2;}
		.emp-company{color:rgba(255,255,255,.7);font-size:.9rem;margin-top:4px;}
		.emp-chips{display:flex;flex-wrap:wrap;gap:8px;margin-top:1rem;}
		.emp-chip{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.18);color:rgba(255,255,255,.85);font-size:.75rem;padding:4px 12px;border-radius:999px;display:flex;align-items:center;gap:6px;}
		.emp-chip i{color:var(--gold);}
		.feedback-item{display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--cream-dark);}
		.feedback-item:last-child{border-bottom:none;}
		.feedback-icon{width:32px;height:32px;border-radius:8px;background:var(--cream);display:flex;align-items:center;justify-content:center;color:var(--forest-mid);font-size:.8rem;flex-shrink:0;}
		.feedback-label{font-size:.72rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-muted);margin-bottom:2px;}
		.feedback-val{font-size:.9rem;color:var(--ink);font-weight:500;}
		.id-tab-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;}
		.mini-id-host{width:100%;overflow:hidden;border-radius:8px;box-shadow:0 6px 28px rgba(0,0,0,.2);print-color-adjust:exact;-webkit-print-color-adjust:exact;}
		.mini-id-canvas{position:relative;width:680px;height:214px;transform-origin:top left;font-family:Arial,Helvetica,sans-serif;}
		.mc-front{background:#0d3d22;}
		.mc-swoosh{position:absolute;top:-60px;right:55px;width:200px;height:340px;background:linear-gradient(135deg,transparent 0%,rgba(40,120,70,0) 20%,rgba(45,130,78,.88) 38%,rgba(60,160,92,1) 50%,rgba(45,130,78,.88) 62%,rgba(40,120,70,0) 80%,transparent 100%);transform:rotate(-18deg);pointer-events:none;}
		.mc-swoosh2{position:absolute;top:0;right:28px;width:55px;height:214px;background:linear-gradient(135deg,transparent 0%,rgba(100,200,130,.18) 50%,transparent 100%);transform:rotate(-18deg);pointer-events:none;}
		.mc-gold{position:absolute;bottom:0;left:0;right:0;height:4px;background:linear-gradient(90deg,#9a6e10,#FFD700,#DAA520,#FFD700,#9a6e10);z-index:10;}
		.mc-photo{position:absolute;top:12px;left:14px;width:86px;height:106px;overflow:hidden;background:#1a4a2a;display:flex;align-items:center;justify-content:center;z-index:5;}
		.mc-photo img{width:100%;height:100%;object-fit:cover;}
		.mc-ini{font-size:20px;font-weight:700;color:rgba(255,255,255,.35);font-family:'Cormorant Garamond',serif;}
		.mc-title{position:absolute;top:14px;right:14px;text-align:right;z-index:5;}
		.mc-olfu{font-size:13px;font-weight:700;color:#fff;letter-spacing:2.5px;font-family:Arial,sans-serif;line-height:1.1;}
		.mc-alumni{font-size:36px;font-weight:700;color:#fff;line-height:.88;font-family:Georgia,serif;}
		.mc-card{font-size:30px;font-weight:700;color:#fff;display:block;font-family:Georgia,serif;line-height:1.05;text-align:right;}
		.mc-info{position:absolute;bottom:20px;left:14px;right:128px;z-index:5;}
		.mc-name{font-size:12px;font-weight:700;color:#FFD700;letter-spacing:.3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
		.mc-cardno{font-size:10px;color:rgba(255,255,255,.88);letter-spacing:1.5px;margin-top:4px;}
		.mc-prog{font-size:11px;color:#fff;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
		.mc-batch{font-size:10px;color:#fff;margin-top:4px;}
		.mc-valid{position:absolute;bottom:20px;right:14px;text-align:right;z-index:5;}
		.mc-valid-lbl{font-size:8px;color:rgba(255,255,255,.65);display:block;}
		.mc-valid-val{font-size:9px;color:#fff;font-weight:600;display:block;margin-top:1px;}
		.mc-back{background:#fff;border:1px solid #c8c8c8;}
		.mc-bheader{display:flex;align-items:center;gap:10px;padding:9px 13px 7px;border-bottom:1px solid #ddd;}
		.mc-bseal{width:36px;height:36px;flex-shrink:0;}
		.mc-buniv{font-size:13px;font-weight:700;color:#1a6b35;font-family:Arial,sans-serif;}
		.mc-brow{padding:5px 11px;}
		.mc-bfield{border:1px solid #c8c8c8;padding:5px 9px;}
		.mc-blbl{font-size:9.5px;color:#444;}
		.mc-bval{font-size:12px;font-weight:700;color:#111;margin-left:5px;}
		.mc-b2col{display:grid;grid-template-columns:1fr 1fr;padding:0 11px;margin-bottom:5px;}
		.mc-bbox{border:1px solid #c8c8c8;padding:5px 8px;}
		.mc-bbox:first-child{border-right:none;}
		.mc-bboxlbl{font-size:9px;color:#444;display:block;line-height:1.3;}
		.mc-bboxval{font-size:11.5px;font-weight:700;color:#111;display:block;margin-top:1px;}
		.mc-bsig{padding:0 11px;margin-bottom:4px;}
		.mc-bsigbox{border:1px solid #c8c8c8;height:32px;display:flex;align-items:center;justify-content:center;overflow:hidden;}
		.mc-bsigbox img{max-height:28px;max-width:100%;object-fit:contain;}
		.mc-bsiglbl{font-size:8.5px;color:#555;text-align:center;}
		.mc-blegal{padding:3px 13px;}
		.mc-blegal p{font-size:6.5px;color:#444;line-height:1.55;}
		.mc-breturn{font-size:7px;color:#222;text-align:center;padding:2px 13px;line-height:1.5;}
		.mc-bemail{font-size:6.8px;color:#222;text-align:center;padding:2px 13px 6px;}
		.status-card .feedback-item:last-child{border-bottom:none;}
		.stats-row{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem;}
		.stat-card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:1.25rem;display:flex;align-items:center;gap:12px;box-shadow:var(--shadow);}
		.stat-icon{width:40px;height:40px;border-radius:9px;background:var(--cream);display:flex;align-items:center;justify-content:center;color:var(--forest-mid);font-size:1rem;flex-shrink:0;}
		.stat-val{font-size:1.2rem;font-weight:700;color:var(--forest);line-height:1;}
		.stat-lbl{font-size:.72rem;color:var(--ink-muted);margin-top:2px;}
		@media(max-width:900px){.section-grid{grid-template-columns:1fr;}.stats-row{grid-template-columns:repeat(2,1fr);}.id-tab-grid{grid-template-columns:1fr;}}
		@media(max-width:600px){.hero-inner{padding:1.75rem 1.5rem;}.field-grid{grid-template-columns:1fr;}.tab-btn .tab-label{display:none;}.stats-row{grid-template-columns:1fr 1fr;}.hero-right{display:none;}}
	</style>
</head>
<body>
<?php include __DIR__ . '/al_header_universal.php'; ?>
<div class="page-wrap">

	<header class="page-header">
		<div class="page-header-left">
			<h1>My <em>Profile</em></h1>
			<div class="rule"></div>
			<p>Your official Fatimanian alumni record</p>
		</div>
		<a href="al_profileupdate.php" class="btn btn-forest"><i class="fas fa-edit"></i> Edit Profile</a>
	</header>

	<div class="profile-hero">
		<div class="hero-inner">
			<div class="hero-left">
				<div class="avatar-ring">
					<div class="avatar-inner">
						<?php if ($photo_src !== '') : ?>
							<img src="<?php echo htmlspecialchars($photo_src, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile Photo"
								onerror="this.style.display='none';this.nextElementSibling.style.display='flex';" />
							<span class="avatar-initials" style="display:none;"><?php echo htmlspecialchars($initials !== '' ? $initials : '?', ENT_QUOTES, 'UTF-8'); ?></span>
						<?php else : ?>
							<span class="avatar-initials"><?php echo htmlspecialchars($initials !== '' ? $initials : '?', ENT_QUOTES, 'UTF-8'); ?></span>
						<?php endif; ?>
					</div>
				</div>
				<div class="hero-info">
					<div class="hero-eyebrow">Fatimanian Alumni</div>
					<div class="hero-name"><?php echo htmlspecialchars($fullname !== '' ? $fullname : 'Your Name', ENT_QUOTES, 'UTF-8'); ?></div>
					<div class="hero-program"><?php echo htmlspecialchars($program !== '' ? $program : 'Program not set', ENT_QUOTES, 'UTF-8'); ?></div>
					<div class="hero-meta">
						<?php if ($campus !== '') : ?><span class="hero-chip"><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($campus, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
						<?php if ($year_grad !== '') : ?><span class="hero-chip"><i class="fas fa-graduation-cap"></i>Class of <?php echo htmlspecialchars($year_grad, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
						<span class="hero-chip status-badge status-active"><i class="fas fa-circle" style="font-size:.45rem;"></i><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span>
					</div>
				</div>
			</div>
			<div class="hero-right">
				<div class="completion-label">Profile Complete</div>
				<div class="completion-ring">
					<svg width="80" height="80" viewBox="0 0 80 80" aria-hidden="true">
						<circle class="completion-ring-bg" cx="40" cy="40" r="<?php echo (string) $ringR; ?>" />
						<circle class="completion-ring-fill" cx="40" cy="40" r="<?php echo (string) $ringR; ?>"
							stroke-dasharray="<?php echo htmlspecialchars((string) $ringCirc, ENT_QUOTES, 'UTF-8'); ?>"
							stroke-dashoffset="<?php echo htmlspecialchars((string) $ringOffset, ENT_QUOTES, 'UTF-8'); ?>" />
					</svg>
					<div class="completion-pct"><?php echo (int) $profileCompletion; ?>%</div>
				</div>
				<?php if ($profileCompletion < 100) : ?>
					<div style="margin-top:8px;"><a href="al_profileupdate.php" style="font-size:.72rem;color:rgba(255,255,255,.55);text-decoration:underline;text-underline-offset:2px;">Complete profile →</a></div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div class="stats-row">
		<div class="stat-card">
			<div class="stat-icon"><i class="fas fa-id-badge"></i></div>
			<div>
				<div class="stat-val"><?php echo htmlspecialchars((string) (userVal($user, 'student_number') ?: '—'), ENT_QUOTES, 'UTF-8'); ?></div>
				<div class="stat-lbl">Alumni ID</div>
			</div>
		</div>
		<div class="stat-card">
			<div class="stat-icon"><i class="fas fa-briefcase"></i></div>
			<div>
				<div class="stat-val"><?php echo htmlspecialchars((string) (userVal($user, 'employment_status') ?: '—'), ENT_QUOTES, 'UTF-8'); ?></div>
				<div class="stat-lbl">Employment Status</div>
			</div>
		</div>
		<div class="stat-card">
			<div class="stat-icon"><i class="fas fa-building"></i></div>
			<div>
				<div class="stat-val" style="font-size:.95rem;"><?php echo htmlspecialchars((string) (userVal($user, 'company') ?: '—'), ENT_QUOTES, 'UTF-8'); ?></div>
				<div class="stat-lbl">Current Company</div>
			</div>
		</div>
		<div class="stat-card">
			<div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
			<div>
				<div class="stat-val"><?php echo htmlspecialchars((string) (userVal($user, 'year_graduated') ?: '—'), ENT_QUOTES, 'UTF-8'); ?></div>
				<div class="stat-lbl">Year Graduated</div>
			</div>
		</div>
	</div>

	<div class="tab-bar" role="tablist">
		<button type="button" class="tab-btn active" role="tab" aria-selected="true" onclick="switchTab('personal',this)"><i class="fas fa-user"></i><span class="tab-label"> Personal</span></button>
		<button type="button" class="tab-btn" role="tab" aria-selected="false" onclick="switchTab('academic',this)"><i class="fas fa-graduation-cap"></i><span class="tab-label"> Academic</span></button>
		<button type="button" class="tab-btn" role="tab" aria-selected="false" onclick="switchTab('employment',this)"><i class="fas fa-briefcase"></i><span class="tab-label"> Employment</span></button>
		<button type="button" class="tab-btn" role="tab" aria-selected="false" onclick="switchTab('feedback',this)"><i class="fas fa-clipboard-check"></i><span class="tab-label"> Feedback</span></button>
		<button type="button" class="tab-btn" role="tab" aria-selected="false" onclick="switchTab('idcard',this)"><i class="fas fa-id-card"></i><span class="tab-label"> ID Card</span></button>
	</div>

	<div id="tab-personal" class="tab-panel active">
		<div class="section-grid">
			<div class="card">
				<div class="card-head">
					<div class="card-icon"><i class="fas fa-user"></i></div>
					<div class="card-title">Personal Information</div>
				</div>
				<div class="card-rule"></div>
				<div class="field-grid">
					<div class="field-item"><div class="field-label">First Name</div><div class="field-value"><?php echo displayField(userVal($user, 'firstname')); ?></div></div>
					<div class="field-item"><div class="field-label">Last Name</div><div class="field-value"><?php echo displayField(userVal($user, 'lastname')); ?></div></div>
					<div class="field-item"><div class="field-label">Middle Name</div><div class="field-value"><?php echo displayField(userVal($user, 'middlename')); ?></div></div>
					<div class="field-item"><div class="field-label">Name Extension</div><div class="field-value"><?php echo displayField(userVal($user, 'name_ext')); ?></div></div>
					<div class="field-item"><div class="field-label">Birthday</div><div class="field-value"><?php echo displayBirthday(userVal($user, 'birthday')); ?></div></div>
					<div class="field-item"><div class="field-label">Age</div><div class="field-value"><?php echo displayAge(userVal($user, 'age')); ?></div></div>
					<div class="field-item"><div class="field-label">Gender</div><div class="field-value"><?php echo displayField(userVal($user, 'gender')); ?></div></div>
					<div class="field-item"><div class="field-label">Civil Status</div><div class="field-value"><?php echo displayField(userVal($user, 'civil_status')); ?></div></div>
					<div class="field-item"><div class="field-label">Religion</div><div class="field-value"><?php echo displayField(userVal($user, 'religion')); ?></div></div>
					<div class="field-item"><div class="field-label">Nationality</div><div class="field-value"><?php echo displayField(userVal($user, 'nationality')); ?></div></div>
					<div class="field-item field-full"><div class="field-label">Address</div><div class="field-value"><?php echo displayField(userVal($user, 'address')); ?></div></div>
				</div>
			</div>
			<div class="card">
				<div class="card-head">
					<div class="card-icon"><i class="fas fa-address-card"></i></div>
					<div class="card-title">Contact Information</div>
				</div>
				<div class="card-rule"></div>
				<div class="field-grid">
					<div class="field-item field-full"><div class="field-label">Email Address</div><div class="field-value"><?php echo displayField(userVal($user, 'email')); ?></div></div>
					<div class="field-item"><div class="field-label">Personal Contact</div><div class="field-value"><?php echo displayField(userVal($user, 'personal_contact')); ?></div></div>
					<div class="field-item"><div class="field-label">Emergency Contact</div><div class="field-value"><?php echo displayField(userVal($user, 'emergency_contact')); ?></div></div>
				</div>
				<?php if ($profileCompletion < 100) : ?>
				<div style="margin-top:1.25rem;padding:12px 14px;background:rgba(201,168,76,0.08);border:1.5px dashed rgba(201,168,76,0.4);border-radius:10px;font-size:.82rem;color:var(--ink-soft);display:flex;align-items:center;gap:10px;">
					<i class="fas fa-info-circle" style="color:var(--gold);flex-shrink:0;"></i>
					Your profile is <?php echo (int) $profileCompletion; ?>% complete.
					<a href="al_profileupdate.php" style="color:var(--forest-mid);font-weight:600;text-decoration:underline;text-underline-offset:2px;margin-left:auto;">Fill in →</a>
				</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<div id="tab-academic" class="tab-panel">
		<div class="card">
			<div class="card-head">
				<div class="card-icon"><i class="fas fa-graduation-cap"></i></div>
				<div class="card-title">Academic Information</div>
			</div>
			<div class="card-rule"></div>
			<div class="field-grid">
				<div class="field-item"><div class="field-label">Alumni ID / Student Number</div><div class="field-value"><?php echo displayField(userVal($user, 'student_number')); ?></div></div>
				<div class="field-item"><div class="field-label">College</div><div class="field-value"><?php echo displayField(userVal($user, 'college')); ?></div></div>
				<div class="field-item field-full"><div class="field-label">Degree / Program</div><div class="field-value"><?php echo displayField(userVal($user, 'degree') ?? userVal($user, 'program')); ?></div></div>
				<div class="field-item"><div class="field-label">Campus</div><div class="field-value"><?php echo displayField(userVal($user, 'campus')); ?></div></div>
				<div class="field-item"><div class="field-label">Month Graduated</div><div class="field-value"><?php echo displayMonthGraduated(userVal($user, 'month_graduated')); ?></div></div>
				<div class="field-item"><div class="field-label">Year Graduated</div><div class="field-value"><?php echo displayField(userVal($user, 'year_graduated')); ?></div></div>
				<div class="field-item"><div class="field-label">Post-Graduate / Further Studies</div><div class="field-value"><?php echo displayField(userVal($user, 'post_grad')); ?></div></div>
				<div class="field-item"><div class="field-label">Licensure Exam</div><div class="field-value"><?php echo displayField(userVal($user, 'licensure_exam')); ?></div></div>
				<div class="field-item field-full"><div class="field-label">Club / Organization Involvement</div><div class="field-value"><?php echo displayField(userVal($user, 'club_involvement')); ?></div></div>
			</div>
		</div>
	</div>

	<div id="tab-employment" class="tab-panel">
		<?php
		$pos = userVal($user, 'position');
		$company = userVal($user, 'company');
		$ind = userVal($user, 'industry');
		$empstat = userVal($user, 'employment_status');
		$los = userVal($user, 'length_of_service');
		if ($pos || $company) :
			?>
		<div class="emp-highlight" style="margin-bottom:1.25rem;">
			<div class="emp-title"><?php echo htmlspecialchars((string) ($pos ?: 'Position not set'), ENT_QUOTES, 'UTF-8'); ?></div>
			<div class="emp-company"><?php echo htmlspecialchars((string) ($company ?: 'Company not set'), ENT_QUOTES, 'UTF-8'); ?></div>
			<div class="emp-chips">
				<?php if ($ind) : ?><span class="emp-chip"><i class="fas fa-industry"></i><?php echo htmlspecialchars((string) $ind, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
				<?php if ($empstat) : ?><span class="emp-chip"><i class="fas fa-circle" style="font-size:.45rem;"></i><?php echo htmlspecialchars((string) $empstat, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
				<?php if ($los) : ?><span class="emp-chip"><i class="fas fa-clock"></i><?php echo htmlspecialchars((string) $los, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
			</div>
		</div>
		<?php endif; ?>
		<div class="card">
			<div class="card-head">
				<div class="card-icon"><i class="fas fa-briefcase"></i></div>
				<div class="card-title">Employment Details</div>
			</div>
			<div class="card-rule"></div>
			<div class="field-grid">
				<div class="field-item"><div class="field-label">Employment Status</div><div class="field-value"><?php echo displayField(userVal($user, 'employment_status')); ?></div></div>
				<div class="field-item"><div class="field-label">Industry</div><div class="field-value"><?php echo displayField(userVal($user, 'industry')); ?></div></div>
				<div class="field-item"><div class="field-label">Current Company</div><div class="field-value"><?php echo displayField(userVal($user, 'company')); ?></div></div>
				<div class="field-item"><div class="field-label">Current Position</div><div class="field-value"><?php echo displayField(userVal($user, 'position')); ?></div></div>
				<div class="field-item"><div class="field-label">Length of Service</div><div class="field-value"><?php echo displayField(userVal($user, 'length_of_service')); ?></div></div>
				<div class="field-item"><div class="field-label">Previous Role</div><div class="field-value"><?php echo displayField(userVal($user, 'previous_role')); ?></div></div>
				<div class="field-item field-full"><div class="field-label">Employment History</div><div class="field-value"><?php echo displayField(userVal($user, 'employment_history')); ?></div></div>
			</div>
		</div>
	</div>

	<div id="tab-feedback" class="tab-panel">
		<div class="card">
			<div class="card-head">
				<div class="card-icon"><i class="fas fa-clipboard-check"></i></div>
				<div class="card-title">Career &amp; Alumni Feedback</div>
			</div>
			<div class="card-rule"></div>
			<div class="feedback-item">
				<div class="feedback-icon"><i class="fas fa-hourglass-half"></i></div>
				<div><div class="feedback-label">Months to Get First Job</div><div class="feedback-val"><?php echo displayField(userVal($user, 'months_to_get_job')); ?></div></div>
			</div>
			<div class="feedback-item">
				<div class="feedback-icon"><i class="fas fa-link"></i></div>
				<div><div class="feedback-label">Job Aligned with Degree</div><div class="feedback-val"><?php echo displayField(userVal($user, 'job_aligned')); ?></div></div>
			</div>
			<div class="feedback-item">
				<div class="feedback-icon"><i class="fas fa-university"></i></div>
				<div><div class="feedback-label">College Prepared You for Work</div><div class="feedback-val"><?php echo displayField(userVal($user, 'college_prepared')); ?></div></div>
			</div>
			<div class="feedback-item">
				<div class="feedback-icon"><i class="fas fa-star"></i></div>
				<div><div class="feedback-label">Most Important Soft Skill</div><div class="feedback-val"><?php echo displayField(userVal($user, 'important_soft_skill')); ?></div></div>
			</div>
			<div class="feedback-item">
				<div class="feedback-icon"><i class="fas fa-heart"></i></div>
				<div><div class="feedback-label">Proud to be an OLFU Alumni?</div><div class="feedback-val"><?php echo displayField(userVal($user, 'proud_alumni')); ?></div></div>
			</div>
		</div>
	</div>

	<div id="tab-idcard" class="tab-panel">
		<div class="id-tab-grid">
			<div style="display:flex;flex-direction:column;gap:14px;">
				<div class="card" style="padding:1.25rem;">
					<div class="card-head" style="margin-bottom:.75rem;">
						<div class="card-icon"><i class="fas fa-id-card"></i></div>
						<div class="card-title">Your Alumni ID</div>
					</div>
					<div class="card-rule" style="margin-bottom:1rem;"></div>
					<p style="font-size:.7rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-muted);margin-bottom:6px;">Front</p>
					<div class="mini-id-host" data-mini-host>
						<div class="mini-id-canvas mc-front">
							<div class="mc-swoosh" aria-hidden="true"></div>
							<div class="mc-swoosh2" aria-hidden="true"></div>
							<div class="mc-gold" aria-hidden="true"></div>
							<div class="mc-photo">
								<?php if ($photo_src !== '') : ?>
									<img src="<?php echo htmlspecialchars($photo_src, ENT_QUOTES, 'UTF-8'); ?>" alt=""
										onerror="this.style.display='none';this.nextElementSibling.style.display='block';" />
									<span class="mc-ini" style="display:none"><?php echo htmlspecialchars($initials !== '' ? $initials : '?', ENT_QUOTES, 'UTF-8'); ?></span>
								<?php else : ?>
									<span class="mc-ini"><?php echo htmlspecialchars($initials !== '' ? $initials : '?', ENT_QUOTES, 'UTF-8'); ?></span>
								<?php endif; ?>
							</div>
							<div class="mc-title">
								<div class="mc-olfu">OLFU</div>
								<div class="mc-alumni">Alumni<br><span class="mc-card">Card</span></div>
							</div>
							<div class="mc-info">
								<div class="mc-name"><?php echo htmlspecialchars($fullname !== '' ? $fullname : '—', ENT_QUOTES, 'UTF-8'); ?></div>
								<div class="mc-cardno"><?php echo htmlspecialchars($card_no_display !== '' ? $card_no_display : '0000 0000 0000 0000', ENT_QUOTES, 'UTF-8'); ?></div>
								<div class="mc-prog"><?php echo htmlspecialchars($program !== '' ? $program : '—', ENT_QUOTES, 'UTF-8'); ?></div>
								<div class="mc-batch">Batch <?php echo htmlspecialchars($year_grad !== '' ? $year_grad : '—', ENT_QUOTES, 'UTF-8'); ?></div>
							</div>
							<div class="mc-valid">
								<span class="mc-valid-lbl">Valid until</span>
								<span class="mc-valid-val"><?php echo htmlspecialchars($valid_display !== '' ? $valid_display : '—', ENT_QUOTES, 'UTF-8'); ?></span>
							</div>
						</div>
					</div>
					<p style="font-size:.7rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-muted);margin-top:14px;margin-bottom:6px;">Back</p>
					<div class="mini-id-host" data-mini-host>
						<div class="mini-id-canvas mc-back">
							<div class="mc-bheader">
								<svg class="mc-bseal" viewBox="0 0 36 36" fill="none" aria-hidden="true">
									<circle cx="18" cy="18" r="16.5" stroke="#1a6b35" stroke-width="1.4" fill="none"/>
									<circle cx="18" cy="18" r="12" stroke="#1a6b35" stroke-width=".7" fill="none"/>
									<circle cx="18" cy="18" r="7.5" stroke="#1a6b35" stroke-width=".7" fill="none"/>
									<text x="18" y="13" text-anchor="middle" font-size="3" fill="#1a6b35" font-weight="700" font-family="Arial">OUR LADY</text>
									<text x="18" y="16.5" text-anchor="middle" font-size="3" fill="#1a6b35" font-weight="700" font-family="Arial">OF FATIMA</text>
									<text x="18" y="20" text-anchor="middle" font-size="2.8" fill="#1a6b35" font-family="Arial">UNIVERSITY</text>
									<text x="18" y="27" text-anchor="middle" font-size="2.2" fill="#1a6b35" font-family="Arial">ANTIPOLO CITY</text>
								</svg>
								<div class="mc-buniv">OUR LADY OF FATIMA UNIVERSITY</div>
							</div>
							<div class="mc-brow"><div class="mc-bfield"><span class="mc-blbl">Address:</span><span class="mc-bval"><?php echo htmlspecialchars(trim((string) (userVal($user, 'address') ?? '')) !== '' ? trim((string) userVal($user, 'address')) : '—', ENT_QUOTES, 'UTF-8'); ?></span></div></div>
							<div class="mc-b2col">
								<div class="mc-bbox"><span class="mc-bboxlbl">Contact Number:</span><span class="mc-bboxval"><?php echo htmlspecialchars(trim((string) (userVal($user, 'personal_contact') ?? '')) !== '' ? trim((string) userVal($user, 'personal_contact')) : '—', ENT_QUOTES, 'UTF-8'); ?></span></div>
								<div class="mc-bbox"><span class="mc-bboxlbl">Emergency<br>Contact Number:</span><span class="mc-bboxval"><?php echo htmlspecialchars(trim((string) (userVal($user, 'emergency_contact') ?? '')) !== '' ? trim((string) userVal($user, 'emergency_contact')) : '—', ENT_QUOTES, 'UTF-8'); ?></span></div>
							</div>
							<div class="mc-bsig">
								<div class="mc-bsigbox" id="profile-sig-box">
									<?php if ($sig_preview !== '') : ?>
										<img src="<?php echo htmlspecialchars($sig_preview, ENT_QUOTES, 'UTF-8'); ?>" alt="Signature" id="profile-sig-img">
									<?php else : ?>
										<span class="mc-bsiglbl" id="profile-sig-placeholder">Card Holder's Signature</span>
									<?php endif; ?>
								</div>
							</div>
							<div class="mc-blegal"><p>By using this card, CARDHOLDER signifies that he/she has read the terms and condition of membership and agrees to be bound by them. It is non-transferable and any tampering will invalidate this card.</p></div>
							<div class="mc-breturn">If found, please return to the <strong>ALUMNI AFFAIRS OFFICE</strong><br>Ground flr. Saint John the Baptist Hall, Km 23 Sumulong Highway Sta. Cruz, Antipolo City.</div>
							<div class="mc-bemail">FOR ALUMNI ASSISTANCE PLEASE EMAIL AT alumniaffairs@fatima.edu.ph</div>
						</div>
					</div>
					<div style="display:flex;gap:10px;margin-top:1.25rem;flex-wrap:wrap;">
						<a href="alumni_id_card.php" class="btn btn-forest" style="flex:1;justify-content:center;"><i class="fas fa-print"></i> View &amp; Print ID</a>
						<a href="al_profileupdate.php#sec-signature" class="btn btn-outline" style="flex:1;justify-content:center;"><i class="fas fa-pen-fancy"></i> Update Signature</a>
					</div>
				</div>
			</div>
			<div style="display:flex;flex-direction:column;gap:1.25rem;">
				<div class="card status-card">
					<div class="card-head"><div class="card-icon"><i class="fas fa-shield-alt"></i></div><div class="card-title">Account Status</div></div>
					<div class="card-rule"></div>
					<div class="feedback-item"><div class="feedback-icon"><i class="fas fa-user-check"></i></div><div><div class="feedback-label">Account Status</div><div class="feedback-val"><?php echo displayField(userVal($user, 'status')); ?></div></div></div>
					<div class="feedback-item"><div class="feedback-icon"><i class="fas fa-calendar-plus"></i></div><div><div class="feedback-label">Date Joined</div><div class="feedback-val"><?php echo displayBirthday(userVal($user, 'date_joined')); ?></div></div></div>
					<div class="feedback-item"><div class="feedback-icon"><i class="fas fa-tasks"></i></div><div><div class="feedback-label">Profile Completion</div><div class="feedback-val"><?php echo (int) $profileCompletion; ?>% complete</div></div></div>
					<div class="feedback-item"><div class="feedback-icon"><i class="fas fa-pen-fancy"></i></div><div><div class="feedback-label">Signature</div><div class="feedback-val"><?php echo $sig_preview !== '' ? 'Saved' : '<span class="na">Not set yet</span>'; ?></div></div></div>
				</div>
				<div class="card">
					<div class="card-head"><div class="card-icon"><i class="fas fa-info-circle"></i></div><div class="card-title">ID Card Information</div></div>
					<div class="card-rule"></div>
					<div class="field-grid">
						<div class="field-item field-full"><div class="field-label">Card Number</div><div class="field-value" style="font-family:monospace;letter-spacing:1.5px;"><?php echo htmlspecialchars($card_no_display !== '' ? $card_no_display : '—', ENT_QUOTES, 'UTF-8'); ?></div></div>
						<div class="field-item"><div class="field-label">Batch</div><div class="field-value"><?php echo displayField(userVal($user, 'year_graduated')); ?></div></div>
						<div class="field-item"><div class="field-label">Valid Until</div><div class="field-value"><?php echo htmlspecialchars($valid_display !== '' ? $valid_display : '—', ENT_QUOTES, 'UTF-8'); ?></div></div>
						<div class="field-item field-full"><div class="field-label">Program</div><div class="field-value"><?php echo displayField($program !== '' ? $program : null); ?></div></div>
					</div>
					<a href="al_profileupdate.php" class="btn btn-forest" style="margin-top:1.25rem;width:100%;justify-content:center;"><i class="fas fa-edit"></i> Update Profile</a>
				</div>
			</div>
		</div>
	</div>

</div>
<script>
function switchTab(id, btn) {
	document.querySelectorAll('.tab-panel').forEach(function (p) {
		p.classList.remove('active');
		p.style.display = 'none';
	});
	document.querySelectorAll('.tab-btn').forEach(function (b) {
		b.classList.remove('active');
		b.setAttribute('aria-selected', 'false');
	});
	var panel = document.getElementById('tab-' + id);
	if (panel) {
		panel.style.display = 'block';
		requestAnimationFrame(function () { panel.classList.add('active'); });
	}
	btn.classList.add('active');
	btn.setAttribute('aria-selected', 'true');
	if (id === 'idcard') {
		setTimeout(scaleMiniCards, 30);
	}
}
function scaleMiniCards() {
	document.querySelectorAll('[data-mini-host]').forEach(function (host) {
		var canvas = host.querySelector('.mini-id-canvas');
		if (!canvas) return;
		var w = host.offsetWidth || 340;
		var s = w / 680;
		canvas.style.transform = 'scale(' + s + ')';
		host.style.height = (214 * s) + 'px';
	});
}
window.addEventListener('resize', scaleMiniCards);
(function () {
	document.querySelectorAll('.tab-panel').forEach(function (p) {
		if (!p.classList.contains('active')) p.style.display = 'none';
	});
	scaleMiniCards();
})();
function injectProfileSig(dataUrl) {
	if (!dataUrl) return;
	var box = document.getElementById('profile-sig-box');
	var ph = document.getElementById('profile-sig-placeholder');
	var img = document.getElementById('profile-sig-img');
	if (!box) return;
	if (img) {
		img.src = dataUrl;
	} else {
		img = document.createElement('img');
		img.id = 'profile-sig-img';
		img.alt = 'Signature';
		img.style.cssText = 'max-height:28px;max-width:100%;object-fit:contain;';
		img.src = dataUrl;
		box.innerHTML = '';
		box.appendChild(img);
	}
	if (ph) ph.style.display = 'none';
}
window.addEventListener('alumni_sig_saved', function (e) {
	var d = e && e.detail && e.detail.dataUrl;
	injectProfileSig(d);
});
window.addEventListener('message', function (e) {
	if (e.data && e.data.type === 'alumni_sig_saved' && e.data.dataUrl) {
		window.dispatchEvent(new CustomEvent('alumni_sig_saved', { detail: { dataUrl: e.data.dataUrl } }));
	}
});
try {
	var _sigCh = new BroadcastChannel('alumni_sig');
	_sigCh.addEventListener('message', function (ev) {
		if (ev.data && ev.data.type === 'alumni_sig_saved' && ev.data.dataUrl) {
			injectProfileSig(ev.data.dataUrl);
		}
	});
} catch (_) {}
</script>
<?php if (is_file(__DIR__ . '/al_footer_universal.php')) { include __DIR__ . '/al_footer_universal.php'; } ?>
</body>
</html>
