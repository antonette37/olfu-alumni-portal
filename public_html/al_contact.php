<?php
session_start();
require_once 'db_config.php';
alumni_otp_gate_after_session();
$conn = getDBConnection();
if (!function_exists('_olfu_stmt_fetch_assoc_local')) {
    function _olfu_stmt_fetch_assoc_local(mysqli_stmt $stmt): ?array {
        if (function_exists('olfu_stmt_get_result')) {
            $gr = olfu_stmt_get_result($stmt);
            return $gr ? ($gr->fetch_assoc() ?: null) : null;
        }
        if (method_exists($stmt, 'get_result')) {
            $res = @$stmt->get_result();
            if ($res instanceof mysqli_result) { $row = $res->fetch_assoc(); $res->free(); return $row ?: null; }
        }
        $meta = $stmt->result_metadata(); if (!$meta) return null;
        $row = []; $refs = [];
        while ($f = $meta->fetch_field()) { $row[$f->name] = null; $refs[] = &$row[$f->name]; }
        $meta->free();
        if (!$refs || !@call_user_func_array([$stmt, 'bind_result'], $refs) || !$stmt->fetch()) return null;
        return $row;
    }
}
if (!function_exists('_olfu_stmt_fetch_all_local')) {
    function _olfu_stmt_fetch_all_local(mysqli_stmt $stmt): array {
        if (function_exists('olfu_stmt_get_result')) {
            $gr = olfu_stmt_get_result($stmt);
            return $gr ? $gr->fetch_all(MYSQLI_ASSOC) : [];
        }
        if (method_exists($stmt, 'get_result')) {
            $res = @$stmt->get_result();
            if ($res instanceof mysqli_result) { $rows = $res->fetch_all(MYSQLI_ASSOC); $res->free(); return $rows ?: []; }
        }
        $meta = $stmt->result_metadata(); if (!$meta) return [];
        $row = []; $refs = [];
        while ($f = $meta->fetch_field()) { $row[$f->name] = null; $refs[] = &$row[$f->name]; }
        $meta->free();
        if (!$refs || !@call_user_func_array([$stmt, 'bind_result'], $refs)) return [];
        $rows = [];
        while ($stmt->fetch()) { $rows[] = $row; }
        return $rows;
    }
}

$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? (int)$_SESSION['user_id'] : 0;
$user = [];
$notifications = [];
$notification_count = 0;
$user_email_display = '';

if ($is_logged_in) {
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $notifications = _olfu_stmt_fetch_all_local($stmt);
        $notification_count = count(array_filter($notifications, function ($n) { return empty($n['is_read']); }));
        $stmt->close();
    }

    $stmt = $conn->prepare("SELECT * FROM itcp WHERE id=?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = _olfu_stmt_fetch_assoc_local($stmt) ?: [];
        $stmt->close();
    }

    foreach (['user_email', 'email', 'mail', 'email_address', 'emailadd'] as $ek) {
        if ($ek === 'user_email' && !empty($_SESSION[$ek])) { $user_email_display = (string)$_SESSION[$ek]; break; }
        if ($ek !== 'user_email' && !empty($user[$ek])) { $user_email_display = (string)$user[$ek]; break; }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>Contact Us – OLFU Alumni Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600;9..40,700&display=swap" rel="stylesheet"/>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>
<style>
:root{--cream:#F5F3EC;--cream-dark:#EDE9DF;--forest:#1A3D2B;--forest-mid:#2D6A4F;--gold:#C9A84C;--ink:#1C1C1A;--ink-soft:#4A4A45;--ink-muted:#8A8A82;--white:#fff;--radius:14px;--shadow:0 2px 20px rgba(26,61,43,.08)}
*{box-sizing:border-box} body{margin:0;font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--ink)} .pw{max-width:1200px;margin:0 auto;padding:2rem 1.5rem 5rem}
.ph h1{font-family:'Cormorant Garamond',serif;font-size:clamp(2rem,4vw,2.8rem);margin:0;color:var(--forest)} .ph em{color:var(--forest-mid)} .rule{height:3px;width:52px;background:linear-gradient(90deg,var(--forest-mid),var(--gold));border-radius:99px;margin-top:8px}
.qc-row{display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin:1.5rem 0}.qc,.card{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);box-shadow:var(--shadow)}
.qc{padding:1.25rem;display:flex;gap:12px}.qc-icon{width:38px;height:38px;border-radius:9px;background:var(--cream);display:flex;align-items:center;justify-content:center;color:var(--forest-mid)}
.main-grid{display:grid;grid-template-columns:1fr 340px;gap:1.5rem}.card{padding:1.75rem}.card-head{display:flex;align-items:center;gap:10px;margin-bottom:1rem}
.fi-input,.fi-select,.fi-textarea{width:100%;padding:9px 12px;border:1.5px solid var(--cream-dark);border-radius:9px;background:var(--cream)} .fi-textarea{min-height:120px;resize:vertical}
.submit-btn{width:100%;padding:11px;border:0;border-radius:10px;background:var(--forest);color:#fff;font-weight:700;cursor:pointer}
.contact-section{background:var(--white);border:1.5px solid var(--cream-dark);border-radius:var(--radius);overflow:hidden;margin-top:8px}.cs-header{padding:1rem 1.25rem;display:flex;align-items:center;justify-content:space-between;cursor:pointer}.cs-body{display:none;padding:1rem 1.25rem}.contact-section.open .cs-body{display:block}
.ctable{width:100%;border-collapse:collapse;font-size:.82rem}.ctable th,.ctable td{border:1px solid var(--cream-dark);padding:8px 12px;vertical-align:top}
@media(max-width:900px){.main-grid{grid-template-columns:1fr}.qc-row{grid-template-columns:repeat(2,1fr)}} @media(max-width:520px){.qc-row{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php include __DIR__ . '/al_header_universal.php'; ?>
<div class="pw">
    <header class="ph">
        <h1>Contact <em>Us</em></h1>
        <div class="rule"></div>
        <p>We're here to help. Reach out via the support form, or use the OLFU contact details below.</p>
    </header>

    <div class="qc-row">
        <div class="qc"><div class="qc-icon"><i class="fas fa-envelope"></i></div><div><strong>Email</strong><div><a href="mailto:alumni.ccs@olfu.edu.ph">alumni.ccs@olfu.edu.ph</a></div></div></div>
        <div class="qc"><div class="qc-icon"><i class="fas fa-map-marker-alt"></i></div><div><strong>Campus</strong><div>Km. 23 Sumulong Highway, Antipolo</div></div></div>
        <div class="qc"><div class="qc-icon"><i class="fas fa-clock"></i></div><div><strong>Office Hours</strong><div>Mon - Fri, 8AM - 5PM</div></div></div>
        <div class="qc"><div class="qc-icon"><i class="fas fa-reply"></i></div><div><strong>Response</strong><div>24 - 48 hours</div></div></div>
    </div>

    <div class="main-grid">
        <div class="card">
            <div class="card-head"><i class="fas fa-paper-plane"></i><h2>Alumni Support Request</h2></div>
            <?php if ($is_logged_in): ?>
            <form method="POST" action="al_contact_process.php" id="contactForm">
                <p><input type="text" name="firstname" class="fi-input" required placeholder="Your full name" value="<?php echo htmlspecialchars(trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''))); ?>"></p>
                <p><input type="email" name="mail" class="fi-input" readonly value="<?php echo htmlspecialchars($user_email_display); ?>"></p>
                <p><select name="category" class="fi-select" required><option value="">Select a category...</option><option>Account / Login Issue</option><option>Profile Update</option><option>Alumni Card Inquiry</option><option>Career Center / Job Posting</option><option>Events &amp; Activities</option><option>Technical Support</option><option>Privacy / Data Request</option><option>General Inquiry</option><option>Other</option></select></p>
                <p><input type="text" name="subject" class="fi-input" required placeholder="Subject"></p>
                <p><textarea name="message" class="fi-textarea" required placeholder="Describe your concern"></textarea></p>
                <button type="submit" class="submit-btn" id="submitBtn"><i class="fas fa-paper-plane"></i> Send Message</button>
            </form>
            <?php else: ?>
            <p>Please log in to access the alumni support form.</p>
            <p><a href="al_login.php">Log in</a></p>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-head"><i class="fas fa-link"></i><h2>Quick Links</h2></div>
            <p><a href="gen_faqs.php">Frequently Asked Questions</a></p>
            <p><a href="al_about.php">About the Portal</a></p>
            <p><a href="alumni_id_card.php">Alumni Card Info</a></p>
            <p><a href="al_events.php">Upcoming Events</a></p>
            <p><a href="al_directory.php">Alumni Directory</a></p>
            <p><a href="al_career.php">Career Center</a></p>
        </div>
    </div>

    <div class="contact-section open" onclick="toggleCS(this)">
        <div class="cs-header"><strong>Online Concierge - Antipolo Campus</strong><i class="fas fa-chevron-down"></i></div>
        <div class="cs-body"><p>Zoom Meeting ID: <strong>926 1060 9860</strong> | Password: <strong>560073</strong></p></div>
    </div>
    <div class="contact-section" onclick="toggleCS(this)">
        <div class="cs-header"><strong>Admissions - Antipolo Campus</strong><i class="fas fa-chevron-down"></i></div>
        <div class="cs-body">
            <table class="ctable"><tr><th>Hours</th><td>Mon-Fri 8AM-4PM</td></tr><tr><th>Mobile</th><td>09171442175</td></tr><tr><th>Email</th><td>admissions-ant@fatima.edu.ph</td></tr></table>
        </div>
    </div>
</div>

<script>
function toggleCS(el){el.classList.toggle('open')}
const form=document.getElementById('contactForm'); const submitBtn=document.getElementById('submitBtn');
if(form&&submitBtn){form.addEventListener('submit',()=>{submitBtn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Sending...';submitBtn.disabled=true;});}
</script>
<?php if (file_exists(__DIR__ . '/al_footer_universal.php')) { include __DIR__ . '/al_footer_universal.php'; } ?>
</body>
</html>
