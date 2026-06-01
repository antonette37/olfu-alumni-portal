<?php
session_start();
require_once 'db_config.php';
alumni_otp_gate_after_session();
$conn = getDBConnection();

if (!isset($_SESSION['user_id'])) {
	header('Location: al_homepage.php');
	exit;
}

$user_id = (int) $_SESSION['user_id'];

$sql = 'SELECT * FROM itcp WHERE id = ?';
$stmt = $conn->prepare($sql);
if ($stmt) {
	$stmt->bind_param('i', $user_id);
	$stmt->execute();
	$user = $stmt->get_result()->fetch_assoc();
	$stmt->close();
} else {
	$user = [];
}

$sql = 'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC';
$stmt = $conn->prepare($sql);
if ($stmt) {
	$stmt->bind_param('i', $user_id);
	$stmt->execute();
	$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
	$stmt->close();
} else {
	$notifications = [];
}
$notification_count = count(array_filter($notifications, function ($n) {
	return empty($n['is_read']);
}));

$displayName = trim(
	(string) ($user['firstname'] ?? '') . ' ' . (string) ($user['lastname'] ?? '')
);
if ($displayName === '') {
	$displayName = 'Your Name';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>Alumni Card – OLFU Alumni Portal</title>
	<link rel="icon" href="olfulogo.png" type="image/png" />

	<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />

	<style>
		/* ─── Design tokens (aligned with Career Center: cream, forest, gold) ─── */
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
			--card-radius:  16px;
			--shadow-card:  0 2px 20px rgba(26,61,43,0.08);
			--shadow-lift:  0 8px 40px rgba(26,61,43,0.14);
		}

		*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
		html { scroll-behavior: smooth; }

		body {
			font-family: 'DM Sans', sans-serif;
			background-color: var(--cream);
			color: var(--ink);
			line-height: 1.6;
			min-height: 100vh;
		}

		body::before {
			content: '';
			position: fixed;
			inset: 0;
			background-image: radial-gradient(circle, rgba(26,61,43,0.045) 1px, transparent 1px);
			background-size: 28px 28px;
			pointer-events: none;
			z-index: 0;
		}

		.page-wrapper {
			position: relative;
			z-index: 1;
			max-width: 1200px;
			margin: 0 auto;
			padding: 4rem 1.5rem 5rem;
		}

		.display {
			font-family: 'Cormorant Garamond', serif;
			line-height: 1.15;
			letter-spacing: -0.02em;
		}

		.rule {
			height: 3px;
			width: 52px;
			background: linear-gradient(90deg, var(--forest-mid), var(--gold));
			border-radius: 99px;
			margin-top: 10px;
		}

		.page-header {
			padding: 1rem 0 1.25rem;
			display: flex;
			align-items: flex-end;
			justify-content: space-between;
			flex-wrap: wrap;
			gap: 1rem;
		}
		.page-header h1 {
			font-size: clamp(2rem, 4vw, 3rem);
			color: var(--forest);
			font-weight: 700;
		}
		.page-header h1 em {
			font-style: italic;
			color: var(--forest-mid);
		}
		.page-header p {
			color: var(--ink-soft);
			font-size: 1rem;
			margin-top: 6px;
		}

		.tab-bar {
			display: flex;
			gap: 8px;
			background: var(--white);
			border: 1.5px solid var(--cream-dark);
			border-radius: 12px;
			padding: 6px;
			margin-bottom: 2.5rem;
			box-shadow: var(--shadow-card);
		}
		.tab-btn {
			flex: 1;
			padding: 10px 16px;
			border: none;
			background: transparent;
			border-radius: 8px;
			font-family: 'DM Sans', sans-serif;
			font-size: 0.875rem;
			font-weight: 500;
			color: var(--ink-soft);
			cursor: pointer;
			transition: all 0.2s ease;
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 8px;
			white-space: nowrap;
		}
		.tab-btn.active {
			background: var(--forest);
			color: var(--white);
			box-shadow: 0 2px 8px rgba(26,61,43,0.25);
		}
		.tab-btn:not(.active):hover { background: var(--cream); }

		.tab-panel { display: none; }
		.tab-panel.active { display: block; animation: fadeUp 0.3s ease; }

		@keyframes fadeUp {
			from { opacity: 0; transform: translateY(12px); }
			to   { opacity: 1; transform: translateY(0); }
		}

		.hero-card {
			background: var(--forest);
			border-radius: var(--card-radius);
			overflow: hidden;
			display: grid;
			grid-template-columns: 1fr 1fr;
			gap: 0;
			min-height: 320px;
			box-shadow: var(--shadow-lift);
			margin-bottom: 2rem;
			position: relative;
		}
		.hero-card::after {
			content: '';
			position: absolute;
			bottom: -60px; right: -60px;
			width: 280px; height: 280px;
			background: radial-gradient(circle, rgba(201,168,76,0.15) 0%, transparent 70%);
			pointer-events: none;
		}
		.hero-left {
			padding: 3rem 2.5rem;
			display: flex;
			flex-direction: column;
			justify-content: center;
			z-index: 1;
		}
		.hero-eyebrow {
			font-size: 0.7rem;
			font-weight: 600;
			letter-spacing: 0.15em;
			text-transform: uppercase;
			color: var(--gold);
			margin-bottom: 12px;
		}
		.hero-left h2 {
			font-size: clamp(1.6rem, 3vw, 2.4rem);
			color: var(--white);
			font-weight: 700;
			line-height: 1.2;
		}
		.hero-left h2 em {
			font-style: italic;
			color: var(--gold-light);
			font-family: 'Cormorant Garamond', serif;
		}
		.hero-left p {
			color: rgba(255,255,255,0.72);
			font-size: 0.95rem;
			margin-top: 14px;
			line-height: 1.65;
			max-width: 380px;
		}
		.hero-badges {
			display: flex;
			flex-wrap: wrap;
			gap: 8px;
			margin-top: 22px;
		}
		.hero-badge {
			background: rgba(255,255,255,0.1);
			border: 1px solid rgba(255,255,255,0.2);
			color: var(--white);
			font-size: 0.78rem;
			padding: 5px 12px;
			border-radius: 999px;
			display: flex;
			align-items: center;
			gap: 6px;
		}
		.hero-badge i { color: var(--gold); }
		.hero-right {
			position: relative;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 2rem;
			overflow: hidden;
		}
		.hero-right::before {
			content: '';
			position: absolute;
			inset: 0;
			background: linear-gradient(135deg, rgba(45,106,79,0.4) 0%, transparent 60%);
		}
		.hero-right img {
			width: 100%;
			max-width: 360px;
			border-radius: 12px;
			box-shadow: 0 12px 40px rgba(0,0,0,0.35);
			position: relative;
			z-index: 1;
			object-fit: cover;
		}
		.card-mockup {
			width: 100%;
			max-width: 360px;
			aspect-ratio: 1.586;
			border-radius: 12px;
			background: linear-gradient(135deg, #2D6A4F 0%, #1A3D2B 40%, #0f2a1c 100%);
			border: 1px solid rgba(255,255,255,0.15);
			display: flex;
			flex-direction: column;
			justify-content: space-between;
			padding: 1.4rem 1.6rem;
			box-shadow: 0 12px 40px rgba(0,0,0,0.35);
			position: relative;
			overflow: hidden;
			z-index: 1;
		}
		.card-mockup::before {
			content: '';
			position: absolute;
			top: -40px; right: -40px;
			width: 160px; height: 160px;
			border-radius: 50%;
			background: radial-gradient(circle, rgba(201,168,76,0.3), transparent 70%);
		}
		.card-mockup-logo {
			font-family: 'Cormorant Garamond', serif;
			font-size: 1.1rem;
			font-weight: 700;
			color: var(--gold-light);
			letter-spacing: 0.05em;
		}
		.card-mockup-logo span { font-weight: 300; }
		.card-mockup-middle {
			text-align: center;
		}
		.card-mockup-middle .label {
			font-size: 0.55rem;
			letter-spacing: 0.2em;
			text-transform: uppercase;
			color: rgba(255,255,255,0.5);
		}
		.card-mockup-middle .name {
			font-family: 'Cormorant Garamond', serif;
			font-size: 1.3rem;
			font-weight: 600;
			color: var(--white);
			margin-top: 2px;
		}
		.card-mockup-bottom {
			display: flex;
			justify-content: space-between;
			align-items: flex-end;
		}
		.card-mockup-bottom .info {
			font-size: 0.6rem;
			letter-spacing: 0.08em;
			color: rgba(255,255,255,0.55);
			text-transform: uppercase;
		}
		.card-mockup-bottom .valid {
			color: var(--gold-light);
			font-size: 0.65rem;
			font-weight: 600;
		}

		.stats-row {
			display: grid;
			grid-template-columns: repeat(4, 1fr);
			gap: 1rem;
			margin-bottom: 2rem;
		}
		.stat-card {
			background: var(--white);
			border: 1.5px solid var(--cream-dark);
			border-radius: var(--card-radius);
			padding: 1.25rem 1.25rem 1.1rem;
			display: flex;
			align-items: center;
			gap: 14px;
			box-shadow: var(--shadow-card);
			transition: transform 0.2s, box-shadow 0.2s;
		}
		.stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lift); }
		.stat-icon {
			width: 44px; height: 44px;
			border-radius: 10px;
			background: var(--cream);
			display: flex; align-items: center; justify-content: center;
			flex-shrink: 0;
			font-size: 1.1rem;
			color: var(--forest-mid);
		}
		.stat-val {
			font-size: 1.35rem;
			font-weight: 700;
			color: var(--forest);
			line-height: 1;
		}
		.stat-lbl {
			font-size: 0.75rem;
			color: var(--ink-muted);
			margin-top: 2px;
		}

		.card {
			background: var(--white);
			border: 1.5px solid var(--cream-dark);
			border-radius: var(--card-radius);
			box-shadow: var(--shadow-card);
			padding: 1.75rem;
		}
		.card-title {
			font-family: 'Cormorant Garamond', serif;
			font-size: 1.3rem;
			font-weight: 700;
			color: var(--forest);
			margin-bottom: 4px;
		}

		.section-head {
			margin-bottom: 1.5rem;
		}
		.section-head h2 {
			font-family: 'Cormorant Garamond', serif;
			font-size: clamp(1.5rem, 2.5vw, 2rem);
			font-weight: 700;
			color: var(--forest);
		}
		.section-head p {
			color: var(--ink-soft);
			font-size: 0.9rem;
			margin-top: 4px;
		}

		.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
		.grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; }

		.pill-list { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
		.pill {
			background: var(--cream);
			border: 1.5px solid var(--cream-dark);
			color: var(--forest);
			font-size: 0.82rem;
			font-weight: 500;
			padding: 6px 14px;
			border-radius: 999px;
			display: flex;
			align-items: center;
			gap: 6px;
		}
		.pill i { color: var(--forest-mid); font-size: 0.75rem; }

		.benefit-group { margin-bottom: 1.5rem; }
		.benefit-group-label {
			font-size: 0.65rem;
			font-weight: 600;
			letter-spacing: 0.14em;
			text-transform: uppercase;
			color: var(--ink-muted);
			margin-bottom: 10px;
			padding-bottom: 6px;
			border-bottom: 1px solid var(--cream-dark);
		}
		.benefit-item {
			display: flex;
			align-items: flex-start;
			gap: 12px;
			padding: 10px 0;
			border-bottom: 1px solid var(--cream-dark);
		}
		.benefit-item:last-child { border-bottom: none; }
		.benefit-icon {
			width: 34px; height: 34px;
			background: var(--cream);
			border-radius: 8px;
			display: flex; align-items: center; justify-content: center;
			color: var(--forest-mid);
			font-size: 0.85rem;
			flex-shrink: 0;
		}
		.benefit-text strong {
			display: block;
			font-size: 0.9rem;
			font-weight: 600;
			color: var(--ink);
		}
		.benefit-text span {
			font-size: 0.82rem;
			color: var(--ink-soft);
		}
		.benefit-tag {
			margin-left: auto;
			font-size: 0.78rem;
			font-weight: 600;
			color: var(--forest-mid);
			background: rgba(45,106,79,0.08);
			padding: 3px 10px;
			border-radius: 999px;
			white-space: nowrap;
			flex-shrink: 0;
		}

		.notice {
			background: #FFFBEB;
			border: 1.5px solid #FDE68A;
			border-left: 4px solid var(--gold);
			border-radius: 8px;
			padding: 12px 14px;
			font-size: 0.83rem;
			color: #78350f;
			display: flex;
			gap: 10px;
			align-items: flex-start;
		}
		.notice i { margin-top: 2px; color: var(--gold); flex-shrink: 0; }

		.partners-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
		.partner-card-new {
			background: var(--white);
			border: 1.5px solid var(--cream-dark);
			border-radius: var(--card-radius);
			overflow: hidden;
			cursor: pointer;
			transition: transform 0.2s, box-shadow 0.2s;
			box-shadow: var(--shadow-card);
		}
		.partner-card-new:hover {
			transform: translateY(-4px);
			box-shadow: var(--shadow-lift);
		}
		.partner-img-wrap {
			position: relative;
			height: 180px;
			overflow: hidden;
		}
		.partner-img-wrap img {
			width: 100%; height: 100%;
			object-fit: cover;
			transition: transform 0.35s ease;
		}
		.partner-card-new:hover .partner-img-wrap img { transform: scale(1.06); }
		.partner-img-overlay {
			position: absolute;
			inset: 0;
			background: linear-gradient(to top, rgba(26,61,43,0.55) 0%, transparent 55%);
		}
		.partner-img-badge {
			position: absolute;
			top: 10px; right: 10px;
			background: var(--forest);
			color: var(--gold-light);
			font-size: 0.65rem;
			font-weight: 600;
			letter-spacing: 0.08em;
			text-transform: uppercase;
			padding: 4px 10px;
			border-radius: 999px;
		}
		.partner-card-body { padding: 14px 16px 16px; }
		.partner-card-body h4 {
			font-family: 'Cormorant Garamond', serif;
			font-size: 1.05rem;
			font-weight: 700;
			color: var(--forest);
			margin-bottom: 4px;
			line-height: 1.3;
		}
		.partner-card-body p {
			font-size: 0.78rem;
			color: var(--ink-soft);
			line-height: 1.5;
		}
		.partner-card-footer {
			display: flex;
			align-items: center;
			justify-content: space-between;
			margin-top: 10px;
			padding-top: 10px;
			border-top: 1px solid var(--cream-dark);
		}
		.partner-valid { font-size: 0.72rem; color: var(--ink-muted); }
		.partner-arrow {
			width: 28px; height: 28px;
			background: var(--cream);
			border-radius: 50%;
			display: flex; align-items: center; justify-content: center;
			color: var(--forest-mid);
			font-size: 0.75rem;
		}

		.faq-item {
			border-bottom: 1px solid var(--cream-dark);
		}
		.faq-question {
			width: 100%;
			background: none;
			border: none;
			padding: 16px 0;
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 12px;
			cursor: pointer;
			text-align: left;
			font-family: 'DM Sans', sans-serif;
			font-size: 0.95rem;
			font-weight: 600;
			color: var(--ink);
		}
		.faq-question i { color: var(--forest-mid); transition: transform 0.25s; flex-shrink: 0; }
		.faq-item.open .faq-question i { transform: rotate(45deg); }
		.faq-answer {
			display: none;
			font-size: 0.875rem;
			color: var(--ink-soft);
			line-height: 1.7;
			padding: 0 0 16px;
		}
		.faq-item.open .faq-answer { display: block; }

		.cta-strip {
			background: var(--forest);
			border-radius: var(--card-radius);
			padding: 2.5rem;
			display: flex;
			align-items: center;
			justify-content: space-between;
			gap: 1.5rem;
			flex-wrap: wrap;
			margin-top: 2rem;
			position: relative;
			overflow: hidden;
		}
		.cta-strip::before {
			content: '';
			position: absolute;
			right: -80px; top: -80px;
			width: 260px; height: 260px;
			border-radius: 50%;
			background: radial-gradient(circle, rgba(201,168,76,0.18), transparent 70%);
		}
		.cta-strip h3 {
			font-family: 'Cormorant Garamond', serif;
			font-size: 1.6rem;
			font-weight: 700;
			color: var(--white);
			line-height: 1.25;
			z-index: 1;
		}
		.cta-strip h3 em { color: var(--gold-light); font-style: italic; }
		.cta-strip p { color: rgba(255,255,255,0.65); font-size: 0.9rem; margin-top: 6px; z-index: 1; }
		.cta-strip-left { z-index: 1; }
		.cta-strip-right { display: flex; gap: 10px; flex-wrap: wrap; z-index: 1; }

		.btn {
			display: inline-flex;
			align-items: center;
			gap: 8px;
			padding: 11px 22px;
			border-radius: 10px;
			font-family: 'DM Sans', sans-serif;
			font-size: 0.875rem;
			font-weight: 600;
			text-decoration: none;
			cursor: pointer;
			border: none;
			transition: all 0.2s;
		}
		.btn-primary {
			background: var(--gold);
			color: var(--forest);
		}
		.btn-primary:hover { background: var(--gold-light); }
		.btn-outline-white {
			background: transparent;
			border: 1.5px solid rgba(255,255,255,0.4);
			color: var(--white);
		}
		.btn-outline-white:hover { background: rgba(255,255,255,0.1); }
		.btn-outline-forest {
			background: transparent;
			border: 1.5px solid var(--forest-mid);
			color: var(--forest);
		}
		.btn-outline-forest:hover { background: var(--cream); }
		.btn-forest {
			background: var(--forest);
			color: var(--white);
		}
		.btn-forest:hover { background: var(--forest-mid); }

		.modal-overlay {
			position: fixed;
			inset: 0;
			background: rgba(26,61,43,0.55);
			backdrop-filter: blur(4px);
			z-index: 100;
			display: none;
			align-items: center;
			justify-content: center;
			padding: 1.5rem;
		}
		.modal-overlay.open { display: flex; }
		.modal-box {
			background: var(--white);
			border-radius: 20px;
			max-width: 780px;
			width: 100%;
			max-height: 90vh;
			overflow: hidden;
			display: flex;
			flex-direction: column;
			box-shadow: 0 24px 80px rgba(0,0,0,0.25);
		}
		.modal-img {
			height: 260px;
			overflow: hidden;
			flex-shrink: 0;
			position: relative;
		}
		.modal-img img {
			width: 100%; height: 100%;
			object-fit: cover;
		}
		.modal-img-overlay {
			position: absolute; inset: 0;
			background: linear-gradient(to top, rgba(26,61,43,0.7) 0%, transparent 50%);
		}
		.modal-img-title {
			position: absolute;
			bottom: 1.25rem; left: 1.5rem;
			font-family: 'Cormorant Garamond', serif;
			font-size: 1.6rem;
			font-weight: 700;
			color: var(--white);
		}
		.modal-body { padding: 1.5rem; overflow-y: auto; }
		.modal-close {
			position: absolute;
			top: 12px; right: 12px;
			width: 36px; height: 36px;
			background: rgba(255,255,255,0.9);
			border: none;
			border-radius: 50%;
			cursor: pointer;
			display: flex; align-items: center; justify-content: center;
			color: var(--ink);
			font-size: 0.85rem;
			transition: background 0.2s;
		}
		.modal-close:hover { background: var(--white); }

		.section-header-row {
			display: flex;
			align-items: flex-end;
			justify-content: space-between;
			margin-bottom: 1.5rem;
		}
		.see-all {
			font-size: 0.85rem;
			font-weight: 600;
			color: var(--forest-mid);
			text-decoration: underline;
			text-underline-offset: 2px;
		}

		@media (max-width: 900px) {
			.hero-card { grid-template-columns: 1fr; }
			.hero-right { display: none; }
			.stats-row { grid-template-columns: repeat(2, 1fr); }
			.partners-grid { grid-template-columns: 1fr 1fr; }
			.grid-2 { grid-template-columns: 1fr; }
			.grid-3 { grid-template-columns: 1fr; }
		}
		@media (max-width: 600px) {
			.stats-row { grid-template-columns: 1fr 1fr; }
			.partners-grid { grid-template-columns: 1fr; }
			.tab-btn span.tab-label { display: none; }
			.cta-strip { flex-direction: column; text-align: center; }
			.cta-strip-right { justify-content: center; }
		}
	</style>
</head>
<body>
	<?php include __DIR__ . '/al_header_universal.php'; ?>

	<div class="page-wrapper">

		<header class="page-header">
			<div>
				<h1 class="display">Alumni <em>Card</em></h1>
				<div class="rule"></div>
				<p>Eligibility, application, renewal, and exclusive benefits</p>
			</div>
			<a href="alumni_id_card.php" class="btn btn-forest">
				<i class="fas fa-id-card"></i> Check your alumni ID here!
			</a>
		</header>

		<div class="stats-row">
			<div class="stat-card">
				<div class="stat-icon"><i class="fas fa-users"></i></div>
				<div>
					<div class="stat-val">Free</div>
					<div class="stat-lbl">First Application</div>
				</div>
			</div>
			<div class="stat-card">
				<div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
				<div>
					<div class="stat-val">3 Years</div>
					<div class="stat-lbl">Card Validity</div>
				</div>
			</div>
			<div class="stat-card">
				<div class="stat-icon"><i class="fas fa-sync-alt"></i></div>
				<div>
					<div class="stat-val">₱300</div>
					<div class="stat-lbl">Renewal Fee</div>
				</div>
			</div>
			<div class="stat-card">
				<div class="stat-icon"><i class="fas fa-handshake"></i></div>
				<div>
					<div class="stat-val">10+</div>
					<div class="stat-lbl">Partner Establishments</div>
				</div>
			</div>
		</div>

		<div class="hero-card">
			<div class="hero-left">
				<div class="hero-eyebrow">Official Alumni Identification</div>
				<h2>Your Key to Exclusive <em>Fatimanian Privileges</em></h2>
				<p>The OLFU Alumni Card connects you to a network of benefits—academic discounts, medical privileges at FUMC, and partner establishment perks across the country.</p>
				<div class="hero-badges">
					<span class="hero-badge"><i class="fas fa-graduation-cap"></i> Academic Discounts</span>
					<span class="hero-badge"><i class="fas fa-hospital"></i> FUMC Benefits</span>
					<span class="hero-badge"><i class="fas fa-tag"></i> Partner Deals</span>
					<span class="hero-badge"><i class="fas fa-globe"></i> National Partners</span>
				</div>
			</div>
			<div class="hero-right">
				<img src="alumnicard.jpg" alt="OLFU Alumni Card"
					onerror="this.style.display='none';document.getElementById('cardMockup').style.display='flex';" />
				<div class="card-mockup" id="cardMockup" style="display:none;">
					<div class="card-mockup-logo">OLFU <span>Alumni</span></div>
					<div class="card-mockup-middle">
						<div class="label">Official Alumni Member</div>
						<div class="name"><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></div>
					</div>
					<div class="card-mockup-bottom">
						<div class="info">Our Lady of Fatima University</div>
						<div class="valid">Valid 3 Years</div>
					</div>
				</div>
			</div>
		</div>

		<div class="tab-bar" role="tablist">
			<button type="button" class="tab-btn active" role="tab" aria-selected="true" onclick="switchTab('overview', this)">
				<i class="fas fa-info-circle"></i><span class="tab-label"> Overview</span>
			</button>
			<button type="button" class="tab-btn" role="tab" aria-selected="false" onclick="switchTab('benefits', this)">
				<i class="fas fa-star"></i><span class="tab-label"> Benefits</span>
			</button>
			<button type="button" class="tab-btn" role="tab" aria-selected="false" onclick="switchTab('partners', this)">
				<i class="fas fa-handshake"></i><span class="tab-label"> Partners</span>
			</button>
			<button type="button" class="tab-btn" role="tab" aria-selected="false" onclick="switchTab('faq', this)">
				<i class="fas fa-question-circle"></i><span class="tab-label"> FAQ</span>
			</button>
		</div>

		<div id="tab-overview" class="tab-panel active">

			<div class="grid-2" style="margin-bottom:1.25rem;">

				<div class="card">
					<div class="section-head">
						<div class="card-title"><i class="fas fa-user-check" style="color:var(--forest-mid);margin-right:8px;font-size:1rem;"></i>Who is Eligible?</div>
						<div class="rule" style="width:36px;"></div>
					</div>
					<p style="font-size:0.875rem;color:var(--ink-soft);margin-bottom:12px;">The Alumni Card is available to graduates of the following programs:</p>
					<div class="pill-list">
						<span class="pill"><i class="fas fa-check"></i> Grade 10 Graduates</span>
						<span class="pill"><i class="fas fa-check"></i> Grade 12 Graduates</span>
						<span class="pill"><i class="fas fa-check"></i> Undergraduate Programs</span>
						<span class="pill"><i class="fas fa-check"></i> Dentistry</span>
						<span class="pill"><i class="fas fa-check"></i> Medicine</span>
						<span class="pill"><i class="fas fa-check"></i> Graduate School</span>
					</div>
					<div class="notice" style="margin-top:16px;">
						<i class="fas fa-lightbulb"></i>
						<span><strong>Pro tip:</strong> Prepare a digital photo and a valid government-issued ID before filling out the form to speed up processing.</span>
					</div>
				</div>

				<div class="card">
					<div class="section-head">
						<div class="card-title"><i class="fas fa-tag" style="color:var(--forest-mid);margin-right:8px;font-size:1rem;"></i>Cost &amp; Validity</div>
						<div class="rule" style="width:36px;"></div>
					</div>
					<div class="benefit-group" style="margin-bottom:1rem;">
						<div class="benefit-item">
							<div class="benefit-icon"><i class="fas fa-id-card"></i></div>
							<div class="benefit-text">
								<strong>First Application</strong>
								<span>Completely free of charge for new alumni applicants.</span>
							</div>
							<span class="benefit-tag">FREE</span>
						</div>
						<div class="benefit-item">
							<div class="benefit-icon"><i class="fas fa-calendar-check"></i></div>
							<div class="benefit-text">
								<strong>Card Validity</strong>
								<span>Valid for three (3) years from date of issuance.</span>
							</div>
							<span class="benefit-tag">3 Years</span>
						</div>
						<div class="benefit-item">
							<div class="benefit-icon"><i class="fas fa-sync-alt"></i></div>
							<div class="benefit-text">
								<strong>Renewal / Replacement</strong>
								<span>Pay ₱300 at the Student's Accounting Office. Keep your official receipt for verification.</span>
							</div>
							<span class="benefit-tag">₱300</span>
						</div>
					</div>
					<div class="notice">
						<i class="fas fa-info-circle"></i>
						<span>Renewal fees also apply to lost or damaged cards that require replacement.</span>
					</div>
				</div>
			</div>

			<div class="cta-strip">
				<div class="cta-strip-left">
					<h3>Ready to claim your <em>alumni privileges?</em></h3>
					<p>Application is free. Start your journey as an official Fatimanian alumni member today.</p>
				</div>
				<div class="cta-strip-right">
					<a href="https://bit.ly/olfualumni" target="_blank" rel="noopener noreferrer" class="btn btn-primary">
						<i class="fas fa-id-card"></i> Apply for Alumni Card
					</a>
					<a href="#" class="btn btn-outline-white" title="Coming soon">
						<i class="fas fa-download"></i> Download Info Sheet
					</a>
				</div>
			</div>

		</div>

		<div id="tab-benefits" class="tab-panel">

			<div class="grid-2" style="margin-bottom:1.25rem;">

				<div class="card">
					<div class="section-head">
						<div class="card-title"><i class="fas fa-university" style="color:var(--forest-mid);margin-right:8px;font-size:1rem;"></i>University Discounts</div>
						<div class="rule" style="width:36px;margin-bottom:8px;"></div>
						<p style="font-size:0.875rem;color:var(--ink-soft);">Educational discounts for alumni pursuing continuing education at OLFU.</p>
					</div>

					<div class="benefit-group">
						<div class="benefit-group-label">Tuition Discount — Medicine</div>
						<div class="benefit-item">
							<div class="benefit-icon"><i class="fas fa-stethoscope"></i></div>
							<div class="benefit-text">
								<strong>Medicine Program</strong>
								<span>For alumni returning to study Medicine at OLFU.</span>
							</div>
							<span class="benefit-tag">₱3,000</span>
						</div>
					</div>

					<div class="benefit-group">
						<div class="benefit-group-label">Tuition Discount — Other Programs</div>
						<div class="benefit-item">
							<div class="benefit-icon"><i class="fas fa-graduation-cap"></i></div>
							<div class="benefit-text">
								<strong>Graduate School &amp; Other Courses</strong>
								<span>Applicable when enrolling in a second degree or continuing education.</span>
							</div>
							<span class="benefit-tag">₱2,000</span>
						</div>
					</div>

					<div class="benefit-group">
						<div class="benefit-group-label">Sibling / Child Benefit</div>
						<div class="benefit-item">
							<div class="benefit-icon"><i class="fas fa-users"></i></div>
							<div class="benefit-text">
								<strong>Sibling or Child of Alumni Discount</strong>
								<span>Alumni cardholders may sponsor the enrollment discount of their sibling or child at OLFU.</span>
							</div>
						</div>
					</div>

					<div class="notice">
						<i class="fas fa-exclamation-triangle"></i>
						<div>
							<strong>Important:</strong> Alumni and Sibling/Child discounts <strong>cannot be stacked</strong> with FEMAC or other non-academic scholarships. When multiple non-academic discounts apply, only the <strong>higher discount</strong> is used. <em>All academic discounts may only be availed once.</em>
						</div>
					</div>
				</div>

				<div class="card">
					<div class="section-head">
						<div class="card-title"><i class="fas fa-hospital" style="color:var(--forest-mid);margin-right:8px;font-size:1rem;"></i>Fatima University Medical Center</div>
						<div class="rule" style="width:36px;margin-bottom:8px;"></div>
						<p style="font-size:0.875rem;color:var(--ink-soft);">Present your Alumni Card at FUMC to enjoy medical service discounts applied on the total hospital fee.</p>
					</div>

					<div class="benefit-item" style="padding:16px 0;">
						<div class="benefit-icon" style="width:44px;height:44px;font-size:1rem;"><i class="fas fa-user-md"></i></div>
						<div class="benefit-text">
							<strong>For OLFU Alumni Cardholders</strong>
							<span>Discount is applied on the total hospital bill at Fatima University Medical Center.</span>
						</div>
						<span class="benefit-tag" style="font-size:1rem;padding:6px 16px;">15%</span>
					</div>
					<div class="benefit-item" style="padding:16px 0;">
						<div class="benefit-icon" style="width:44px;height:44px;font-size:1rem;"><i class="fas fa-user-friends"></i></div>
						<div class="benefit-text">
							<strong>For Immediate Family Members</strong>
							<span>Spouse, children, and parents of the OLFU alumni cardholder are covered.</span>
						</div>
						<span class="benefit-tag" style="font-size:1rem;padding:6px 16px;">10%</span>
					</div>

					<div class="notice" style="margin-top:16px;">
						<i class="fas fa-info-circle"></i>
						<span>Discount is applied on the <strong>total hospital fee</strong>. Present a valid Alumni Card at the billing section. Must be accompanied by proof of relationship for immediate family claims.</span>
					</div>

					<div style="margin-top:20px;background:var(--cream);border-radius:10px;padding:14px;">
						<div style="font-size:0.72rem;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:var(--ink-muted);margin-bottom:10px;">Discount Comparison</div>
						<div style="margin-bottom:8px;">
							<div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:4px;">
								<span style="font-weight:600;">Alumni Member</span><span style="color:var(--forest-mid);font-weight:700;">15%</span>
							</div>
							<div style="height:8px;background:var(--cream-dark);border-radius:999px;overflow:hidden;">
								<div style="height:100%;width:15%;background:var(--forest-mid);border-radius:999px;"></div>
							</div>
						</div>
						<div>
							<div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:4px;">
								<span style="font-weight:600;">Immediate Family</span><span style="color:var(--forest-light);font-weight:700;">10%</span>
							</div>
							<div style="height:8px;background:var(--cream-dark);border-radius:999px;overflow:hidden;">
								<div style="height:100%;width:10%;background:var(--forest-light);border-radius:999px;"></div>
							</div>
						</div>
					</div>
				</div>
			</div>

		</div>

		<div id="tab-partners" class="tab-panel">
			<div class="section-header-row">
				<div class="section-head" style="margin-bottom:0;">
					<h2>Partner Establishments</h2>
					<p>Present your Alumni Card to enjoy exclusive discounts at these partner establishments.</p>
				</div>
				<a href="alumni_card_partners.php" class="see-all">See all partners →</a>
			</div>

			<div class="partners-grid" id="partnersGrid">

				<div class="partner-card-new"
					data-name="Azalea Hotels and Residences"
					data-location="Baguio and Boracay"
					data-desc="Discount offer extended to all Our Lady of Fatima University Alumni Members, Employees, and Students and Fatima University Medical Center Employees."
					data-image="azalea.jpg"
					data-link-text="View special room rates"
					data-link-url="https://bit.ly/Azalea-Room-Rates"
					data-valid="Valid until December 20, 2024">
					<div class="partner-img-wrap">
						<img src="azalea.jpg" alt="Azalea Hotels and Residences" />
						<div class="partner-img-overlay"></div>
						<span class="partner-img-badge">Hotel</span>
					</div>
					<div class="partner-card-body">
						<h4>Azalea Hotels and Residences</h4>
						<p>Baguio &amp; Boracay — Special room rates for OLFU alumni, employees, and students.</p>
						<div class="partner-card-footer">
							<span class="partner-valid"><i class="fas fa-clock" style="margin-right:4px;"></i>Until Dec 20, 2024</span>
							<span class="partner-arrow"><i class="fas fa-chevron-right"></i></span>
						</div>
					</div>
				</div>

				<div class="partner-card-new"
					data-name="Richmonde Hotel"
					data-location="Ortigas"
					data-desc="Discount offer extended to all Our Lady of Fatima University Alumni Members, Employees, and Students and Fatima University Medical Center Employees."
					data-image="rich.jpg"
					data-link-text="View special room rates"
					data-link-url="https://bit.ly/Richmonde-Ortigas"
					data-valid="Valid until December 30, 2024">
					<div class="partner-img-wrap">
						<img src="rich.jpg" alt="Richmonde Hotel Ortigas" />
						<div class="partner-img-overlay"></div>
						<span class="partner-img-badge">Hotel</span>
					</div>
					<div class="partner-card-body">
						<h4>Richmonde Hotel</h4>
						<p>Ortigas — Exclusive rates for OLFU community members.</p>
						<div class="partner-card-footer">
							<span class="partner-valid"><i class="fas fa-clock" style="margin-right:4px;"></i>Until Dec 30, 2024</span>
							<span class="partner-arrow"><i class="fas fa-chevron-right"></i></span>
						</div>
					</div>
				</div>

				<div class="partner-card-new"
					data-name="Microtel by Wyndham"
					data-location="Pampanga"
					data-desc="Discount offer extended to all Our Lady of Fatima University Alumni Members, Employees, and Students and Fatima University Medical Center Employees."
					data-image="micro.jpg"
					data-extras='["20% discount from published rates","10% discount for in-room massage and for in-house guests"]'
					data-valid="Valid until July 31, 2024">
					<div class="partner-img-wrap">
						<img src="micro.jpg" alt="Microtel by Wyndham Pampanga" />
						<div class="partner-img-overlay"></div>
						<span class="partner-img-badge">Hotel</span>
					</div>
					<div class="partner-card-body">
						<h4>Microtel by Wyndham</h4>
						<p>Pampanga — 20% off published rates + 10% on in-room massage.</p>
						<div class="partner-card-footer">
							<span class="partner-valid"><i class="fas fa-clock" style="margin-right:4px;"></i>Until Jul 31, 2024</span>
							<span class="partner-arrow"><i class="fas fa-chevron-right"></i></span>
						</div>
					</div>
				</div>

			</div>

			<div style="text-align:center;margin-top:2rem;">
				<a href="alumni_card_partners.php" class="btn btn-forest">
					<i class="fas fa-th-large"></i> View All Partners
				</a>
			</div>
		</div>

		<div id="tab-faq" class="tab-panel">
			<div class="card">
				<div class="section-head" style="margin-bottom:1.25rem;">
					<div class="card-title">Frequently Asked Questions</div>
					<div class="rule" style="width:36px;"></div>
				</div>

				<div class="faq-item open">
					<button type="button" class="faq-question" onclick="toggleFaq(this)">
						What is the OLFU Alumni Card?
						<i class="fas fa-plus"></i>
					</button>
					<div class="faq-answer">The OLFU Alumni Card is the official identification card issued to graduates of Our Lady of Fatima University. It certifies your membership in the Fatimanian alumni community and serves as your key to exclusive academic discounts, medical privileges at FUMC, and partner establishment deals.</div>
				</div>

				<div class="faq-item">
					<button type="button" class="faq-question" onclick="toggleFaq(this)">
						How long does processing take?
						<i class="fas fa-plus"></i>
					</button>
					<div class="faq-answer">Processing time varies. After submitting your registration form online, the Alumni Affairs Office will send a confirmation email with the next steps. It is recommended to follow up via email if you have not received a response within 5–7 business days.</div>
				</div>

				<div class="faq-item">
					<button type="button" class="faq-question" onclick="toggleFaq(this)">
						Can I use the card discount more than once?
						<i class="fas fa-plus"></i>
					</button>
					<div class="faq-answer">No. All academic discounts (tuition discounts for continuing education) can only be availed <strong>once</strong> per alumni cardholder. However, the FUMC medical discount and partner establishment discounts may be used as often as needed during the card's validity period.</div>
				</div>

				<div class="faq-item">
					<button type="button" class="faq-question" onclick="toggleFaq(this)">
						What if I lose my Alumni Card?
						<i class="fas fa-plus"></i>
					</button>
					<div class="faq-answer">If you lose your Alumni Card, you may apply for a replacement by paying ₱300 at the Student's Accounting Office. Keep your official receipt for verification. Contact the Alumni Affairs Office for the replacement request process.</div>
				</div>

				<div class="faq-item">
					<button type="button" class="faq-question" onclick="toggleFaq(this)">
						Can my family members use my Alumni Card?
						<i class="fas fa-plus"></i>
					</button>
					<div class="faq-answer">At partner establishments, benefits are generally extended to the cardholder. At Fatima University Medical Center, <strong>immediate family members</strong> (spouse, children, parents) of the cardholder are entitled to a 10% discount on total hospital fees. The cardholder receives a 15% discount. Proof of relationship may be required.</div>
				</div>

				<div class="faq-item">
					<button type="button" class="faq-question" onclick="toggleFaq(this)">
						How do I renew my Alumni Card?
						<i class="fas fa-plus"></i>
					</button>
					<div class="faq-answer">Your Alumni Card is valid for three (3) years. Upon expiration, go to the Student's Accounting Office and pay the ₱300 renewal fee. Secure your official receipt and present it to the Alumni Affairs Office to process your renewed card.</div>
				</div>

				<div class="faq-item">
					<button type="button" class="faq-question" onclick="toggleFaq(this)">
						Who can I contact for more questions?
						<i class="fas fa-plus"></i>
					</button>
					<div class="faq-answer">You may reach out to the OLFU Alumni Affairs Office through the official alumni portal or by visiting the office at any OLFU campus. For online inquiries, submit a message through the Contact Us section of this portal.</div>
				</div>
			</div>
		</div>

	</div>

	<div class="modal-overlay" id="partnerModal" role="dialog" aria-modal="true" aria-labelledby="modalName">
		<div class="modal-box">
			<div class="modal-img">
				<img id="modalImg" src="" alt="" />
				<div class="modal-img-overlay"></div>
				<h2 id="modalName" class="modal-img-title display"></h2>
				<button type="button" class="modal-close" onclick="closeModal()" aria-label="Close"><i class="fas fa-times"></i></button>
			</div>
			<div class="modal-body">
				<p style="font-size:0.7rem;font-weight:600;letter-spacing:0.14em;text-transform:uppercase;color:var(--ink-muted);margin-bottom:8px;">Alumni Benefits</p>
				<p id="modalDesc" style="color:var(--ink);font-size:0.925rem;line-height:1.7;"></p>
				<ul id="modalExtras" style="margin-top:12px;padding-left:1.25rem;color:var(--ink-soft);font-size:0.875rem;line-height:1.8;"></ul>
				<p id="modalValid" style="font-size:0.78rem;color:var(--ink-muted);margin-top:14px;"></p>
				<div id="modalLink" style="margin-top:16px;"></div>
				<div style="display:flex;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--cream-dark);">
					<button type="button" onclick="closeModal()" class="btn btn-outline-forest">Close</button>
				</div>
			</div>
		</div>
	</div>

	<script>
		function switchTab(id, btn) {
			document.querySelectorAll('.tab-panel').forEach(function (p) { p.classList.remove('active'); });
			document.querySelectorAll('.tab-btn').forEach(function (b) {
				b.classList.remove('active');
				b.setAttribute('aria-selected', 'false');
			});
			var panel = document.getElementById('tab-' + id);
			if (panel) panel.classList.add('active');
			btn.classList.add('active');
			btn.setAttribute('aria-selected', 'true');
		}

		function toggleFaq(btn) {
			btn.parentElement.classList.toggle('open');
		}

		function parseExtras(raw) {
			if (!raw) return [];
			try {
				return JSON.parse(raw);
			} catch (e) {
				return [];
			}
		}

		document.querySelectorAll('.partner-card-new').forEach(function (card) {
			card.addEventListener('click', function () {
				var name = (card.dataset.name || '') + (card.dataset.location ? ' (' + card.dataset.location + ')' : '');
				var desc = card.dataset.desc || '';
				var image = card.dataset.image || '';
				var extras = parseExtras(card.dataset.extras);
				var valid = card.dataset.valid || '';
				var linkText = card.dataset.linkText || '';
				var linkUrl = card.dataset.linkUrl || '';

				document.getElementById('modalName').textContent = name;
				document.getElementById('modalDesc').textContent = desc;
				document.getElementById('modalValid').textContent = valid;
				document.getElementById('modalImg').src = image;
				document.getElementById('modalImg').alt = name;

				var extrasEl = document.getElementById('modalExtras');
				extrasEl.innerHTML = '';
				extras.forEach(function (e) {
					var li = document.createElement('li');
					li.textContent = e;
					extrasEl.appendChild(li);
				});

				var linkEl = document.getElementById('modalLink');
				if (linkUrl) {
					var a = document.createElement('a');
					a.href = linkUrl;
					a.target = '_blank';
					a.rel = 'noopener noreferrer';
					a.className = 'btn btn-forest';
					a.style.fontSize = '0.85rem';
					var i = document.createElement('i');
					i.className = 'fas fa-external-link-alt';
					a.appendChild(i);
					a.appendChild(document.createTextNode(' ' + (linkText || 'View Details')));
					linkEl.innerHTML = '';
					linkEl.appendChild(a);
				} else {
					linkEl.innerHTML = '';
				}

				document.getElementById('partnerModal').classList.add('open');
			});
		});

		function closeModal() {
			document.getElementById('partnerModal').classList.remove('open');
		}
		document.getElementById('partnerModal').addEventListener('click', function (e) {
			if (e.target === this) closeModal();
		});
	</script>
</body>
</html>
