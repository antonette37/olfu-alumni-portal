<?php
session_start();
if (!empty($_SESSION['user_id'])) {
    header('Location: al_dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Alumni Registration - OLFU Alumni Portal</title>
<link rel="icon" href="olfulogo.png" type="image/png">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600;1,700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
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
    --shadow: 0 2px 20px rgba(26,61,43,.07);
    --shadow-lg: 0 10px 48px rgba(26,61,43,.16);
}
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
html { scroll-behavior:smooth; }
body {
    font-family:'DM Sans',sans-serif;
    background:var(--cream);
    color:var(--ink);
    height:100vh;
    display:flex;
    flex-direction:column;
    overflow:hidden;
}
body::before {
    content:'';
    position:fixed; inset:0;
    background-image:radial-gradient(circle, rgba(26,61,43,.045) 1px, transparent 1px);
    background-size:28px 28px;
    pointer-events:none; z-index:0;
}
.top-nav {
    position:relative; z-index:10;
    background:var(--white);
    border-bottom:1.5px solid var(--cream-dark);
    padding:0 2rem;
    display:flex; align-items:center; justify-content:space-between;
    height:60px;
    box-shadow:0 1px 12px rgba(26,61,43,.06);
}
.nav-brand { display:flex; align-items:center; gap:12px; text-decoration:none; }
.nav-brand-logo {
    width:38px; height:38px; border-radius:9px;
    object-fit:cover;
    border:1px solid var(--cream-dark);
    background:var(--white);
    flex-shrink:0;
}
.nav-brand-text {
    font-family:'Cormorant Garamond',serif;
    font-size:1.1rem; font-weight:700; color:var(--forest);
    line-height:1.1;
}
.nav-brand-text span {
    display:block; font-size:.65rem; font-weight:400;
    font-family:'DM Sans',sans-serif; color:var(--ink-muted);
    letter-spacing:.04em; text-transform:uppercase;
}
.nav-actions { display:flex; align-items:center; gap:10px; }
.nav-link {
    font-size:.82rem; font-weight:500; color:var(--ink-soft);
    text-decoration:none; padding:6px 14px; border-radius:999px;
    transition:all .15s;
}
.nav-link:hover { background:var(--cream-dark); color:var(--forest); }
.nav-link-primary {
    background:var(--forest); color:var(--white);
    font-size:.82rem; font-weight:600; padding:8px 18px;
    border-radius:999px; text-decoration:none; transition:background .2s;
}
.nav-link-primary:hover { background:var(--forest-mid); }
.main {
    flex:1; position:relative; z-index:1;
    display:flex; flex-direction:column; align-items:center;
    justify-content:center;
    padding:1.1rem 1.25rem 1rem;
    overflow:hidden;
}
.page-header { text-align:center; max-width:620px; margin:0 auto .9rem; }
.page-header-eyebrow {
    font-size:.7rem; font-weight:700; letter-spacing:.16em;
    text-transform:uppercase; color:var(--gold); margin-bottom:10px;
    display:flex; align-items:center; justify-content:center; gap:8px;
}
.page-header-eyebrow::before,
.page-header-eyebrow::after {
    content:''; flex:1; max-width:40px; height:1px;
    background:linear-gradient(90deg, transparent, var(--gold));
}
.page-header-eyebrow::after { background:linear-gradient(90deg, var(--gold), transparent); }
.page-header h1 {
    font-family:'Cormorant Garamond',serif;
    font-size:clamp(1.85rem,3.1vw,2.45rem); font-weight:700; color:var(--forest);
    line-height:1.1; letter-spacing:-.01em; margin-bottom:10px;
}
.page-header h1 em { font-style:italic; color:var(--forest-mid); }
.header-rule {
    height:3px; width:56px;
    background:linear-gradient(90deg, var(--forest-mid), var(--gold));
    border-radius:99px; margin:8px auto 10px;
}
.page-header p {
    color:var(--ink-soft); font-size:.88rem; line-height:1.45;
    max-width:50ch; margin:0 auto;
}
.cards-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:.9rem;
    width:100%; max-width:760px;
    margin:0 auto .8rem;
}
.choice-card {
    position:relative; display:flex; flex-direction:column;
    background:var(--white); border:1.5px solid var(--cream-dark);
    border-radius:var(--radius); padding:1.15rem 1.05rem 1.05rem;
    text-decoration:none; color:inherit; box-shadow:var(--shadow);
    transition:all .22s; overflow:hidden; cursor:pointer;
}
.choice-card::before {
    content:''; position:absolute; top:0; left:0; right:0; height:3px;
    background:linear-gradient(90deg, var(--forest-mid), var(--gold));
    opacity:0; transition:opacity .22s;
}
.choice-card:hover { border-color:var(--forest-mid); box-shadow:var(--shadow-lg); transform:translateY(-3px); }
.choice-card:hover::before { opacity:1; }
.card-badge {
    position:absolute; top:14px; right:14px;
    font-size:.62rem; font-weight:700; letter-spacing:.08em;
    text-transform:uppercase; padding:3px 10px; border-radius:999px;
    background:rgba(201,168,76,.18); color:var(--forest);
    border:1px solid rgba(201,168,76,.4);
}
.card-icon-wrap {
    width:46px; height:46px; border-radius:12px;
    background:var(--cream); display:flex; align-items:center; justify-content:center;
    font-size:1.15rem; color:var(--forest-mid); margin-bottom:.75rem;
    flex-shrink:0; transition:background .22s, color .22s;
}
.choice-card:hover .card-icon-wrap { background:var(--forest); color:var(--gold-light); }
.card-title {
    font-family:'Cormorant Garamond',serif; font-size:1.18rem;
    font-weight:700; color:var(--forest); margin-bottom:.2rem; line-height:1.2;
}
.card-subtitle {
    font-size:.73rem; color:var(--ink-soft); margin-bottom:.6rem;
    line-height:1.35; flex:1;
}
.card-features { display:flex; flex-direction:column; gap:4px; margin-bottom:.75rem; }
.card-feature { display:flex; align-items:flex-start; gap:7px; font-size:.68rem; color:var(--ink-soft); line-height:1.25; }
.card-feature i { color:var(--forest-mid); font-size:.7rem; margin-top:3px; flex-shrink:0; }
.card-cta {
    display:inline-flex; align-items:center; gap:8px;
    padding:8px 16px; border-radius:999px; font-family:'DM Sans',sans-serif;
    font-size:.78rem; font-weight:600; background:var(--forest); color:var(--white);
    transition:background .2s; align-self:flex-start; margin-top:auto;
}
.choice-card:hover .card-cta { background:var(--forest-mid); }
.card-cta-arrow { font-size:.75rem; transition:transform .2s; }
.choice-card:hover .card-cta-arrow { transform:translateX(3px); }
.info-banner {
    width:100%; max-width:760px; background:var(--white);
    border:1.5px solid var(--cream-dark); border-radius:var(--radius);
    padding:.72rem .9rem; display:flex; align-items:flex-start; gap:10px;
    box-shadow:var(--shadow); margin-bottom:.75rem;
}
.info-banner i { color:var(--gold); font-size:1rem; margin-top:2px; flex-shrink:0; }
.info-banner p { font-size:.72rem; color:var(--ink-soft); line-height:1.38; }
.info-banner strong { color:var(--ink); }
.info-banner a { color:var(--forest-mid); text-decoration:underline; text-underline-offset:2px; font-weight:600; }
.login-nudge {
    text-align:center; font-size:.72rem; color:var(--ink-muted);
    max-width:760px; width:100%;
}
.login-nudge a { color:var(--forest-mid); font-weight:600; text-decoration:underline; text-underline-offset:2px; }
.page-footer {
    position:relative; z-index:1; border-top:1.5px solid var(--cream-dark);
    background:var(--white); padding:1rem 2rem; display:flex;
    align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.5rem;
    font-size:.69rem; color:var(--ink-muted);
    min-height:54px;
}
.page-footer a { color:var(--forest-mid); text-decoration:none; }
.page-footer a:hover { text-decoration:underline; }
@media(max-width:640px){
    .cards-grid { grid-template-columns:1fr 1fr; gap:.6rem; }
    .top-nav { padding:0 1rem; }
    .page-footer { padding:.65rem .8rem; }
    .page-footer span { width:100%; text-align:center; }
}
@media(max-width:400px){
    .top-nav { height:56px; }
    .nav-brand-text span { display:none; }
    .choice-card { padding:.9rem .72rem .8rem; }
}
</style>
</head>
<body>
<nav class="top-nav">
    <a href="al_homepage.php" class="nav-brand">
        <img src="olfulogo.png" alt="OLFU Logo" class="nav-brand-logo">
        <div class="nav-brand-text">
            OLFU Alumni
            <span>Our Lady of Fatima University</span>
        </div>
    </a>
    <div class="nav-actions">
        <a href="al_homepage.php" class="nav-link"><i class="fas fa-home" style="margin-right:5px;"></i> Home</a>
        <a href="gen_faqs.php" class="nav-link"><i class="fas fa-question-circle" style="margin-right:5px;"></i> Help</a>
        <a href="al_login.php" class="nav-link-primary">
            <i class="fas fa-sign-in-alt" style="margin-right:6px;"></i> Sign In
        </a>
    </div>
</nav>

<main class="main">
    <header class="page-header">
        <div class="page-header-eyebrow">Fatimanian Alumni Network</div>
        <h1>Alumni <em>Registration</em></h1>
        <div class="header-rule"></div>
        <p>Welcome back, Fatimanian! Select the registration path that best matches your current status to get started.</p>
    </header>

    <div class="info-banner">
        <i class="fas fa-info-circle"></i>
        <p>
            <strong>Not sure which path to choose?</strong>
            If you graduated from OLFU after 2018 and never received a physical alumni card, select <em>New Graduate</em>.
            If you were issued a physical card by the Alumni Affairs Office at any time, select <em>Existing Card</em>.
            Need help? <a href="gen_faqs.php">Visit our FAQ</a> or <a href="mailto:alumniaffairs@fatima.edu.ph">email Alumni Affairs</a>.
        </p>
    </div>

    <div class="cards-grid">
        <a class="choice-card" href="al_registration.php?type=new">
            <span class="card-badge">Most Common</span>
            <div class="card-icon-wrap">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div class="card-title">New Graduate</div>
            <p class="card-subtitle">
                I recently graduated or have not yet received a physical CPS Alumni ID card.
            </p>
            <ul class="card-features">
                <li class="card-feature"><i class="fas fa-check-circle"></i> Complete online registration form</li>
                <li class="card-feature"><i class="fas fa-check-circle"></i> Upload your profile photo and signature</li>
                <li class="card-feature"><i class="fas fa-check-circle"></i> A new CPS Alumni ID number will be generated upon approval</li>
                <li class="card-feature"><i class="fas fa-check-circle"></i> Download your digital alumni ID card after activation</li>
                <li class="card-feature"><i class="fas fa-clock"></i> Approval typically takes 1-3 business days</li>
            </ul>
            <span class="card-cta">
                <i class="fas fa-arrow-right"></i> Start Registration
                <i class="fas fa-chevron-right card-cta-arrow"></i>
            </span>
        </a>

        <a class="choice-card" href="al_registration.php?type=legacy">
            <div class="card-icon-wrap">
                <i class="fas fa-id-card"></i>
            </div>
            <div class="card-title">I Have an Alumni Card</div>
            <p class="card-subtitle">
                I already hold a physical OLFU Alumni ID card issued by the Alumni Affairs Office.
            </p>
            <ul class="card-features">
                <li class="card-feature"><i class="fas fa-check-circle"></i> Link your existing physical card to the portal</li>
                <li class="card-feature"><i class="fas fa-check-circle"></i> Your legacy alumni ID number is preserved</li>
                <li class="card-feature"><i class="fas fa-check-circle"></i> Access the digital version of your card immediately after verification</li>
                <li class="card-feature"><i class="fas fa-check-circle"></i> Update your contact and employment details online</li>
                <li class="card-feature"><i class="fas fa-shield-alt"></i> Card number verified against Alumni Affairs records</li>
            </ul>
            <span class="card-cta">
                <i class="fas fa-id-card"></i> Digitize My Card
                <i class="fas fa-chevron-right card-cta-arrow"></i>
            </span>
        </a>
    </div>

    <p class="login-nudge">
        Already registered?
        <a href="al_login.php">Sign in to your account <i class="fas fa-arrow-right" style="font-size:.72rem;"></i></a>
        &nbsp;&bull;&nbsp;
        <a href="al_homepage.php">Back to homepage</a>
    </p>
</main>

<footer class="page-footer">
    <span>&copy; <?php echo date('Y'); ?> Our Lady of Fatima University - Alumni Affairs Office</span>
    <span>
        <a href="gen_faqs.php">Help &amp; FAQs</a>
        &nbsp;&bull;&nbsp;
        <a href="al_privacy_settings.php">Privacy Policy</a>
        &nbsp;&bull;&nbsp;
        <a href="mailto:alumniaffairs@fatima.edu.ph">Contact Us</a>
    </span>
</footer>
</body>
</html>
