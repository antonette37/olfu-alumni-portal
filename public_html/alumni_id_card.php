<?php
/**
 * Alumni printable ID — uses the same render_alumni_id_cards() HTML/CSS as ad_alumni_id_check.php
 * and the same itcp-based field rules (no verified_alumni override for validity).
 */
declare(strict_types=1);

require_once __DIR__ . '/db_config.php';
$cpsLib = __DIR__ . '/includes/cps_alumni_lib.php';
if (is_file($cpsLib)) {
	require_once $cpsLib;
}
$rowBuilder = __DIR__ . '/includes/alumni_id_card_preview_row.php';
if (is_file($rowBuilder)) {
	require_once $rowBuilder;
}
unset($cpsLib, $rowBuilder);

session_start();
alumni_otp_gate_after_session();
if (empty($_SESSION['user_id'])) {
	header('Location: al_login.php');
	exit;
}

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
	function render_alumni_id_cards($card = []) {
		echo '<p class="olfu-id-card-unavailable" style="padding:12px;border:1px solid #ddd;border-radius:8px;color:#555;font-size:14px;">'
			. 'Alumni ID card preview is unavailable. Please contact the administrator if this persists.</p>';
	}
}

$uid = (int) $_SESSION['user_id'];
if ($uid < 1) {
	header('Location: al_login.php');
	exit;
}

$conn = getDBConnection();
if (function_exists('cps_ensure_schema')) {
	cps_ensure_schema($conn);
}

/*
 * Avoid mysqli_stmt::get_result() — it requires mysqlnd and fatals with HTTP 500 on many shared hosts.
 * Same pattern as ad_alumni_id_check.php (int id is safe for SQL).
 */
$res = mysqli_query($conn, 'SELECT * FROM itcp WHERE id = ' . $uid);
if ($res === false) {
	mysqli_close($conn);
	header('Location: al_login.php');
	exit;
}
$user = ($res instanceof mysqli_result) ? mysqli_fetch_assoc($res) : null;
if ($res instanceof mysqli_result) {
	mysqli_free_result($res);
}
mysqli_close($conn);

if (!$user || !is_array($user)) {
	header('Location: al_login.php');
	exit;
}

if (function_exists('alumni_id_card_row_to_render_array')) {
	$card = alumni_id_card_row_to_render_array($user, true);
} else {
	// Fallback so this page does not 500 if preview helper is not deployed.
	$fn = trim((string) ($user['firstname'] ?? ''));
	$ln = trim((string) ($user['lastname'] ?? ''));
	$photo = trim((string) ($user['photo'] ?? ''));
	$photoSrc = '';
	if ($photo !== '') {
		$photoSrc = (strpos($photo, 'http') === 0)
			? $photo
			: 'serve_profile_image.php?img=' . rawurlencode(basename($photo));
	}
	$card = [
		'photoSrc' => $photoSrc,
		'idInitials' => strtoupper(substr($fn, 0, 1) . substr($ln, 0, 1)) ?: '?',
		'fullName' => strtoupper(trim($fn . ' ' . trim((string) ($user['middlename'] ?? '')) . ' ' . $ln . ' ' . trim((string) ($user['name_ext'] ?? '')))),
		'cardFormatted' => trim((string) ($user['student_number'] ?? '')) !== '' ? trim(chunk_split(str_pad(substr(preg_replace('/\D/', '', (string) ($user['student_number'] ?? '')), 0, 16), 16, '0'), 4, ' ')) : 'PENDING — NO STUDENT NO.',
		'program' => trim((string) ($user['program'] ?? '')) ?: '—',
		'batchYear' => trim((string) ($user['year_graduated'] ?? '')) ?: '—',
		'validUntil' => '—',
		'address' => trim((string) ($user['address'] ?? '')) ?: '—',
		'contact' => trim((string) ($user['personal_contact'] ?? '')) ?: '—',
		'emergency' => trim((string) ($user['emergency_contact'] ?? '')) ?: '—',
		'signatureSrc' => '',
	];
}

$missing = [];
if ($card['photoSrc'] === '') {
	$missing[] = ['fa-camera', 'Profile photo — needed for the front of the ID'];
}
if (($card['signatureSrc'] ?? '') === '') {
	$missing[] = ['fa-pen-fancy', 'Signature — needed for the back of the ID'];
}
if (trim((string) ($user['address'] ?? '')) === '') {
	$missing[] = ['fa-map-marker-alt', 'Address'];
}
if (trim((string) ($user['personal_contact'] ?? '')) === '') {
	$missing[] = ['fa-phone', 'Contact number'];
}

$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Alumni ID Card — OLFU</title>
<link rel="icon" href="olfulogo.png" type="image/png">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
<style>
:root {
	--cream: #F5F3EC;
	--cream-dark: #EDE9DF;
	--forest: #1A3D2B;
	--forest-mid: #2D6A4F;
	--gold: #C9A84C;
	--gold-light: #F0D98C;
	--ink: #1C1C1A;
	--ink-soft: #4A4A45;
	--ink-muted: #8A8A82;
	--white: #FFFFFF;
	--radius: 16px;
	--shadow: 0 2px 20px rgba(26,61,43,.08);
}
*,*::before,*::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'DM Sans', sans-serif; background: var(--cream); color: var(--ink); line-height: 1.6; min-height: 100vh; padding-bottom: 4rem; }
body::before {
	content: ''; position: fixed; inset: 0;
	background-image: radial-gradient(circle, rgba(26,61,43,.04) 1px, transparent 1px);
	background-size: 28px 28px; pointer-events: none; z-index: 0;
}
.page-wrap { position: relative; z-index: 1; max-width: 960px; margin: 0 auto; padding: 4rem 1.5rem 5rem; }
.page-header { padding: 1rem 0 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.75rem; }
.page-header-left h1 { font-family: 'Cormorant Garamond', serif; font-size: clamp(1.8rem, 4vw, 2.5rem); font-weight: 700; color: var(--forest); }
.page-header-left h1 em { font-style: italic; color: var(--forest-mid); }
.rule { height: 3px; width: 52px; background: linear-gradient(90deg, var(--forest-mid), var(--gold)); border-radius: 99px; margin-top: 8px; }
.page-header-left p { color: var(--ink-soft); font-size: .9rem; margin-top: 6px; }
.btn {
	display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 10px;
	font-family: 'DM Sans', sans-serif; font-size: .875rem; font-weight: 600; text-decoration: none;
	cursor: pointer; border: none; transition: all .2s;
}
.btn-forest { background: var(--forest); color: var(--white); } .btn-forest:hover { background: var(--forest-mid); }
.btn-outline { background: transparent; border: 1.5px solid var(--forest-mid); color: var(--forest); } .btn-outline:hover { background: var(--cream-dark); }
.btn-gold { background: var(--gold); color: var(--forest); } .btn-gold:hover { background: var(--gold-light); }
.header-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
.info-banner {
	background: var(--forest); border-radius: var(--radius); padding: 1.25rem 1.5rem; margin-bottom: 2rem;
	display: flex; align-items: center; gap: 14px; flex-wrap: wrap; position: relative; overflow: hidden;
}
.info-banner::before {
	content: ''; position: absolute; right: -40px; top: -40px; width: 160px; height: 160px; border-radius: 50%;
	background: radial-gradient(circle, rgba(201,168,76,.15), transparent 70%);
}
.info-banner i { color: var(--gold); font-size: 1.1rem; flex-shrink: 0; position: relative; z-index: 1; }
.info-banner p { color: rgba(255,255,255,.78); font-size: .85rem; position: relative; z-index: 1; }
.info-banner strong { color: var(--white); }
.alumni-id-card-shell {
	background: #f0f2f5; border-radius: 12px; border: 1px solid var(--cream-dark); padding: 1.5rem;
	box-shadow: var(--shadow);
}
.alert-warn {
	padding: 13px 16px; border-radius: 10px; font-size: .875rem; display: flex; align-items: center; gap: 10px;
	margin-bottom: 1.5rem; background: #fef9ec; color: #92400e; border: 1.5px solid #fcd34d;
}
.missing-nudge {
	background: var(--white); border: 1.5px solid var(--cream-dark); border-radius: var(--radius);
	padding: 1.5rem; box-shadow: var(--shadow); margin-top: 1.5rem;
}
.missing-nudge h3 { font-family: 'Cormorant Garamond', serif; font-size: 1.1rem; font-weight: 700; color: var(--forest); margin-bottom: .75rem; }
.missing-list { display: flex; flex-direction: column; gap: 6px; }
.missing-item { display: flex; align-items: center; gap: 8px; font-size: .85rem; color: var(--ink-soft); }
.missing-item i { color: var(--gold); font-size: .75rem; }
@media print {
	body { background: #fff; padding: 0; }
	.no-print { display: none !important; }
	.page-wrap { padding: 10mm; max-width: none; }
	.alumni-id-card-shell { border: none; box-shadow: none; padding: 0; background: #fff; }
	@page { margin: 8mm; size: auto; }
}
</style>
</head>
<body>

<?php include __DIR__ . '/al_header_universal.php'; ?>

<div class="page-wrap">

	<header class="page-header no-print">
		<div class="page-header-left">
			<h1>Alumni <em>ID Card</em></h1>
			<div class="rule"></div>
			<p>Same preview as admin Check ID — your submitted registration data</p>
		</div>
		<div class="header-actions">
			<a href="al_profileupdate.php#sec-signature" class="btn btn-outline"><i class="fas fa-pen-fancy"></i> Update Signature</a>
			<a href="al_profile.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Profile</a>
			<button type="button" class="btn btn-outline" onclick="downloadIdPng()"><i class="fas fa-download"></i> Download PNG</button>
			<button type="button" class="btn btn-gold" onclick="window.print()"><i class="fas fa-print"></i> Print / Save PDF</button>
		</div>
	</header>

	<div class="info-banner no-print">
		<i class="fas fa-info-circle"></i>
		<p>
			This layout matches <strong>Admin → Check ID</strong>. Updates you make in Edit Profile are saved to the same record and will appear here after save.
			<a href="al_profileupdate.php" style="color: var(--gold-light); text-decoration: underline;">Edit Profile</a>
		</p>
	</div>

	<?php if ($missing) : ?>
	<div class="alert-warn no-print">
		<i class="fas fa-exclamation-triangle"></i>
		<span>Some fields are still empty — <a href="al_profileupdate.php" style="color: inherit; font-weight: 600; text-decoration: underline;">complete your profile</a> for a full ID.</span>
	</div>
	<?php endif; ?>

	<div class="alumni-id-card-shell">
		<?php
		try {
			render_alumni_id_cards($card);
		} catch (Throwable $e) {
			@error_log('[alumni_id_card] ' . $e->getMessage());
			echo '<p class="no-print" role="alert" style="padding:1rem;color:#b91c1c;">The ID card preview could not be loaded. Please try again later.</p>';
		}
		?>
	</div>

	<?php if ($missing) : ?>
	<div class="missing-nudge no-print">
		<h3><i class="fas fa-clipboard-list" style="color: var(--gold); margin-right: 8px;"></i>Complete your ID</h3>
		<div class="missing-list">
			<?php foreach ($missing as $m) : ?>
			<div class="missing-item">
				<i class="fas <?php echo $h($m[0]); ?>"></i>
				<span><?php echo $h($m[1]); ?></span>
			</div>
			<?php endforeach; ?>
		</div>
		<a href="al_profileupdate.php" class="btn btn-forest" style="margin-top: 1rem;"><i class="fas fa-edit"></i> Update Profile</a>
	</div>
	<?php endif; ?>

</div>

<script>
function injectSigDataUrl(dataUrl) {
	if (!dataUrl) return;
	var box = document.getElementById('olfu-alumni-sig-box');
	var ph = document.getElementById('olfu-alumni-sig-placeholder');
	var img = document.getElementById('olfu-alumni-sig-img');
	if (!box) return;
	if (img) {
		img.src = dataUrl;
	} else {
		img = document.createElement('img');
		img.id = 'olfu-alumni-sig-img';
		img.className = 'cb-sig-img';
		img.alt = '';
		img.decoding = 'async';
		img.src = dataUrl;
		box.innerHTML = '';
		box.appendChild(img);
		box.classList.add('cb-sig-has-img');
	}
	if (ph) ph.style.display = 'none';
}

window.addEventListener('alumni_sig_saved', function (e) {
	var d = e && e.detail && e.detail.dataUrl;
	injectSigDataUrl(d);
});
window.addEventListener('message', function (e) {
	if (e.data && e.data.type === 'alumni_sig_saved' && e.data.dataUrl) {
		window.dispatchEvent(new CustomEvent('alumni_sig_saved', { detail: { dataUrl: e.data.dataUrl } }));
	}
});
try {
	var ch = new BroadcastChannel('alumni_sig');
	ch.addEventListener('message', function (ev) {
		if (ev.data && ev.data.type === 'alumni_sig_saved' && ev.data.dataUrl) {
			injectSigDataUrl(ev.data.dataUrl);
		}
	});
} catch (_) {}

function downloadIdPng() {
	var target = document.querySelector('.alumni-id-card-shell');
	if (!target || typeof html2canvas !== 'function') return;
	html2canvas(target, {backgroundColor: '#ffffff', scale: 2}).then(function (canvas) {
		var link = document.createElement('a');
		link.download = 'alumni-id-card.png';
		link.href = canvas.toDataURL('image/png');
		link.click();
	});
}
</script>

<?php if (is_file(__DIR__ . '/al_footer_universal.php')) { include __DIR__ . '/al_footer_universal.php'; } ?>
</body>
</html>
