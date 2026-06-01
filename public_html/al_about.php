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
        $meta = $stmt->result_metadata();
        if (!$meta) return null;
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
        $meta = $stmt->result_metadata();
        if (!$meta) return [];
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
$user = []; $notification_count = 0;
if ($is_logged_in) {
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC");
    if ($stmt) { $stmt->bind_param("i",$user_id); $stmt->execute(); $notifications = _olfu_stmt_fetch_all_local($stmt); $notification_count = count(array_filter($notifications, fn($n)=>empty($n['is_read']))); $stmt->close(); }
    $stmt = $conn->prepare("SELECT * FROM itcp WHERE id=?");
    if ($stmt) { $stmt->bind_param("i",$user_id); $stmt->execute(); $user = _olfu_stmt_fetch_assoc_local($stmt) ?: []; $stmt->close(); }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>About - OLFU Alumni Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>
<style>
:root{--cream:#F5F3EC;--cream-dark:#EDE9DF;--forest:#1A3D2B;--forest-mid:#2D6A4F;--gold:#C9A84C;--ink:#1C1C1A;--ink-soft:#4A4A45;--white:#fff;--radius:14px;--shadow:0 2px 20px rgba(26,61,43,.08);--shadow-lg:0 8px 40px rgba(26,61,43,.14)}
*{box-sizing:border-box}body{margin:0;font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--ink)}.pw{max-width:1200px;margin:0 auto;padding:2rem 1.5rem 4rem}
.ph h1{font-family:'Cormorant Garamond',serif;font-size:clamp(2rem,4vw,2.8rem);margin:0;color:var(--forest)}.ph em{color:var(--forest-mid)}.rule{height:3px;width:52px;background:linear-gradient(90deg,var(--forest-mid),var(--gold));border-radius:99px;margin-top:8px}
.hero{background:var(--forest);border-radius:var(--radius);padding:2rem;color:#fff;box-shadow:var(--shadow-lg);margin:1rem 0}.hero h2{font-family:'Cormorant Garamond',serif;font-size:2rem;margin:.2rem 0}.hero em{color:#f0d98c}
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-top:1rem}.stat{background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.12);border-radius:10px;padding:.9rem}
.card{background:#fff;border:1.5px solid var(--cream-dark);border-radius:var(--radius);box-shadow:var(--shadow);padding:1.4rem;margin-top:1rem}.ct{font-family:'Cormorant Garamond',serif;font-size:1.3rem;color:var(--forest);margin:0 0 .4rem}
.features{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.fc{background:#fff;border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:1rem;box-shadow:var(--shadow)}.fc i{color:var(--forest-mid)}
.cta{background:var(--forest);border-radius:var(--radius);padding:1.4rem;color:#fff;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-top:1rem}
.btn{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:9px;text-decoration:none;font-weight:600}.btn-gold{background:var(--gold);color:var(--forest)}.btn-out{border:1.5px solid rgba(255,255,255,.3);color:#fff}
@media(max-width:900px){.features{grid-template-columns:1fr 1fr}.stats{grid-template-columns:1fr 1fr}}@media(max-width:520px){.features{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php include __DIR__ . '/al_header_universal.php'; ?>
<div class="pw">
    <header class="ph">
        <h1>About the <em>Alumni Portal</em></h1>
        <div class="rule"></div>
        <p>Learn about the OLFU Alumni Management System and its mission.</p>
    </header>

    <section class="hero">
        <div>Our Lady of Fatima University</div>
        <h2>Connecting <em>Fatimanians</em> for Life</h2>
        <p>The official digital home of every Fatimanian graduate.</p>
        <div class="stats">
            <div class="stat"><strong>1967</strong><div>Year Founded</div></div>
            <div class="stat"><strong>8+</strong><div>Campuses</div></div>
            <div class="stat"><strong>Free</strong><div>First Alumni Card</div></div>
            <div class="stat"><strong>CCS</strong><div>College of Computer Studies</div></div>
        </div>
    </section>

    <section class="card">
        <h3 class="ct">Purpose and Objectives</h3>
        <p>Maintain accurate alumni records, strengthen alumni engagement, and support career networking and tracer studies.</p>
    </section>

    <section style="margin-top:1rem">
        <h3 class="ct">Portal Features</h3>
        <div class="features">
            <div class="fc"><i class="fas fa-user-circle"></i> Alumni Profile Management</div>
            <div class="fc"><i class="fas fa-id-card"></i> Alumni Card Application</div>
            <div class="fc"><i class="fas fa-briefcase"></i> Career Center</div>
            <div class="fc"><i class="fas fa-users"></i> Alumni Directory</div>
            <div class="fc"><i class="fas fa-calendar-alt"></i> Events and Engagement</div>
            <div class="fc"><i class="fas fa-shield-alt"></i> Privacy and Security</div>
        </div>
    </section>

    <section class="card">
        <h3 class="ct">About OLFU and CCS</h3>
        <p>OLFU has built generations of professionals grounded in Veritas et Misericordia. CCS develops globally competitive technology professionals and innovators.</p>
    </section>

    <div class="cta">
        <div>
            <strong>Ready to reconnect?</strong>
            <div>Join fellow Fatimanians in the portal.</div>
        </div>
        <div>
            <a href="al_directory.php" class="btn btn-gold"><i class="fas fa-users"></i> Browse Directory</a>
            <a href="al_contact.php" class="btn btn-out"><i class="fas fa-envelope"></i> Contact Us</a>
        </div>
    </div>
</div>
</body>
</html>
