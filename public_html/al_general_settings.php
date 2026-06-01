<?php
session_start();
require_once __DIR__ . '/config.php';
alumni_otp_gate_after_session();

if (!isset($_SESSION['user_id'])) {
    header('Location: al_homepage.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$success_message = null;
$error_message   = null;

@$conn->query("CREATE TABLE IF NOT EXISTS notification_prefs (
  user_id INT PRIMARY KEY,
  newsletter TINYINT(1) DEFAULT 0,
  event_invites TINYINT(1) DEFAULT 1,
  fundraising TINYINT(1) DEFAULT 0,
  jobs_updates TINYINT(1) DEFAULT 1,
  system_alerts TINYINT(1) DEFAULT 1,
  platform_notifications TINYINT(1) DEFAULT 1,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

@$conn->query("CREATE TABLE IF NOT EXISTS user_prefs (
  user_id INT PRIMARY KEY,
  timezone VARCHAR(64) DEFAULT 'Asia/Manila',
  language VARCHAR(16) DEFAULT 'en',
  theme VARCHAR(16) DEFAULT 'system',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $old     = $_POST['old_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if ($new === '' || $new !== $confirm) {
            $error_message = 'New passwords do not match.';
        } elseif (strlen($new) < 8) {
            $error_message = 'New password must be at least 8 characters.';
        } else {
            $stmt = $conn->prepare("SELECT password FROM itcp WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $stmt->bind_result($hash);
                if ($stmt->fetch() && $hash && password_verify($old, $hash)) {
                    $stmt->close();
                    $newHash = password_hash($new, PASSWORD_DEFAULT);
                    $up = $conn->prepare("UPDATE itcp SET password = ? WHERE id = ?");
                    if ($up) {
                        $up->bind_param('si', $newHash, $user_id);
                        $up->execute() ? $success_message = 'Password updated successfully.' : $error_message = 'Failed to update password: ' . $up->error;
                        $up->close();
                    }
                } else {
                    $stmt->close();
                    $error_message = 'Old password is incorrect.';
                }
            } else { $error_message = 'Password change not supported.'; }
        }
    } elseif ($action === 'update_email') {
        $newEmail = trim($_POST['new_email'] ?? '');
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) { $error_message = 'Please enter a valid email address.'; }
        else {
            $up = $conn->prepare("UPDATE itcp SET email = ? WHERE id = ?");
            if ($up) { $up->bind_param('si', $newEmail, $user_id); $up->execute() ? $success_message = 'Login email updated. You may need to re-verify.' : $error_message = 'Failed to update email: ' . $up->error; $up->close(); }
        }
    } elseif ($action === 'save_notifications') {
        $newsletter             = isset($_POST['newsletter']) ? 1 : 0;
        $event_invites          = isset($_POST['event_invites']) ? 1 : 0;
        $fundraising            = isset($_POST['fundraising']) ? 1 : 0;
        $jobs_updates           = isset($_POST['jobs_updates']) ? 1 : 0;
        $system_alerts          = isset($_POST['system_alerts']) ? 1 : 0;
        $platform_notifications = isset($_POST['platform_notifications']) ? 1 : 0;
        $up = $conn->prepare("INSERT INTO notification_prefs (user_id,newsletter,event_invites,fundraising,jobs_updates,system_alerts,platform_notifications) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE newsletter=VALUES(newsletter),event_invites=VALUES(event_invites),fundraising=VALUES(fundraising),jobs_updates=VALUES(jobs_updates),system_alerts=VALUES(system_alerts),platform_notifications=VALUES(platform_notifications)");
        if ($up) { $up->bind_param('iiiiiii', $user_id, $newsletter, $event_invites, $fundraising, $jobs_updates, $system_alerts, $platform_notifications); $up->execute() ? $success_message='Notification preferences saved.' : $error_message='Failed: '.$up->error; $up->close(); }
    } elseif ($action === 'save_display') {
        $timezone = $_POST['timezone'] ?? 'Asia/Manila';
        $language = $_POST['language'] ?? 'en';
        $theme    = $_POST['theme'] ?? 'system';
        $up = $conn->prepare("INSERT INTO user_prefs (user_id,timezone,language,theme) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE timezone=VALUES(timezone),language=VALUES(language),theme=VALUES(theme)");
        if ($up) { $up->bind_param('isss', $user_id, $timezone, $language, $theme); $up->execute() ? $success_message='Display preferences saved.' : $error_message='Failed: '.$up->error; $up->close(); }
    } elseif ($action === 'deactivate_account') {
        $up = $conn->prepare("UPDATE itcp SET account_status = 'deactivated' WHERE id = ?");
        if ($up) { $up->bind_param('i', $user_id); $up->execute() ? $success_message='Your account has been marked as deactivated.' : $error_message='Failed to deactivate: '.$up->error; $up->close(); }
        else { $error_message = 'Account deactivation is not supported on this system.'; }
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH'])==='xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['status'=>isset($error_message)?'error':'success','message'=>isset($error_message)?$error_message:($success_message??'Saved')]);
        exit;
    }
}

$fname = $lname = $email = '';
$rs = $conn->prepare("SELECT firstname,lastname,email FROM itcp WHERE id=?");
if ($rs) { $rs->bind_param('i', $user_id); $rs->execute(); $rs->bind_result($fname, $lname, $email); $rs->fetch(); $rs->close(); }

$np = ['newsletter'=>0,'event_invites'=>1,'fundraising'=>0,'jobs_updates'=>1,'system_alerts'=>1,'platform_notifications'=>1];
$rs2 = $conn->prepare("SELECT newsletter,event_invites,fundraising,jobs_updates,system_alerts,platform_notifications FROM notification_prefs WHERE user_id=?");
if ($rs2) { $rs2->bind_param('i', $user_id); $rs2->execute(); $rs2->bind_result($np['newsletter'],$np['event_invites'],$np['fundraising'],$np['jobs_updates'],$np['system_alerts'],$np['platform_notifications']); $rs2->fetch(); $rs2->close(); }

$dp = ['timezone'=>'Asia/Manila','language'=>'en','theme'=>'system'];
$rs3 = $conn->prepare("SELECT timezone,language,theme FROM user_prefs WHERE user_id=?");
if ($rs3) { $rs3->bind_param('i',$user_id); $rs3->execute(); $rs3->bind_result($dp['timezone'],$dp['language'],$dp['theme']); $rs3->fetch(); $rs3->close(); }

$h   = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$sel = fn($a,$b) => $a===$b ? 'selected' : '';
$chk = fn($v) => $v ? 'checked' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>General Settings - OLFU Alumni Portal</title>
<link rel="icon" href="olfulogo.png" type="image/png">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600;1,700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root{--cream:#F5F3EC;--cream-dark:#EDE9DF;--forest:#1A3D2B;--forest-mid:#2D6A4F;--gold:#C9A84C;--gold-light:#F0D98C;--ink:#1C1C1A;--ink-soft:#4A4A45;--ink-muted:#8A8A82;--white:#FFF;--red:#DC2626;--red-light:#FEE2E2;--amber-light:#FEF3C7;--amber-dark:#92400e;--green-light:#DCFCE7;--radius-card:16px;--radius-input:10px;--shadow:0 2px 20px rgba(26,61,43,.07);--shadow-lg:0 8px 40px rgba(26,61,43,.13)}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}html{scroll-behavior:smooth}
body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--ink);line-height:1.6;min-height:100vh;padding-bottom:6rem}
body::before{content:'';position:fixed;inset:0;background-image:radial-gradient(circle,rgba(26,61,43,.045) 1px,transparent 1px);background-size:28px 28px;pointer-events:none;z-index:0}
.page-wrap{position:relative;z-index:1;max-width:1000px;margin:0 auto;padding:3.5rem 1.5rem 5rem}
.page-header{margin-bottom:2.25rem}.page-header h1{font-family:'Cormorant Garamond',serif;font-size:clamp(2.2rem,5vw,3rem);font-weight:700;color:var(--forest);line-height:1.1}.page-header h1 em{font-style:italic;color:var(--forest-mid)}
.header-rule{height:3px;width:56px;background:linear-gradient(90deg,var(--forest-mid),var(--gold));border-radius:99px;margin:10px 0 14px}.page-header p{color:var(--ink-soft);font-size:.95rem;max-width:56ch}
.header-back{display:inline-flex;align-items:center;gap:7px;margin-top:12px;font-size:.82rem;color:var(--forest-mid);text-decoration:none}
.alert{display:flex;align-items:center;gap:10px;padding:13px 16px;border-radius:10px;font-size:.875rem;margin-bottom:1.5rem}.alert-success{background:var(--green-light);color:#166534;border:1px solid #86efac}.alert-error{background:var(--red-light);color:#991b1b;border:1px solid #fca5a5}
.sec-card{background:var(--white);border-radius:var(--radius-card);border:1.5px solid var(--cream-dark);box-shadow:var(--shadow);overflow:hidden;margin-bottom:1rem}.sec-card:hover{box-shadow:var(--shadow-lg)}
.sec-header{width:100%;display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.4rem;background:none;border:none;cursor:pointer;text-align:left;gap:12px}.sec-header:hover{background:var(--cream)}
.sec-header-left{display:flex;align-items:center;gap:12px}.sec-icon{width:38px;height:38px;border-radius:9px;background:var(--cream);display:flex;align-items:center;justify-content:center;color:var(--forest-mid);font-size:.95rem}
.sec-title{font-family:'Cormorant Garamond',serif;font-size:1.15rem;font-weight:700;color:var(--forest)}.sec-subtitle{font-size:.73rem;color:var(--ink-muted)}
.sec-chevron{color:var(--ink-muted);font-size:.8rem;transition:transform .25s}.sec-card.open .sec-chevron{transform:rotate(180deg)}
.sec-body{padding:0 1.4rem 1.4rem;display:none}.sec-card.open .sec-body{display:block}.sec-divider{height:1px;background:var(--cream-dark);margin-bottom:1.25rem}
.sub-block{padding:1rem 1.1rem;border:1.5px solid var(--cream-dark);border-radius:10px;background:var(--cream);margin-bottom:.85rem}.sub-block:last-child{margin-bottom:0}
.sub-block-title{font-size:.875rem;font-weight:700;color:var(--forest);margin-bottom:.2rem;display:flex;align-items:center;gap:8px}.sub-block-desc{font-size:.78rem;color:var(--ink-muted);margin-bottom:.9rem;line-height:1.45}
.field-label{display:block;font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-muted);margin-bottom:7px}.field-hint{font-size:.75rem;color:var(--ink-muted);margin-top:5px}
input.form-input,select.form-select{width:100%;padding:9px 12px;border:1.5px solid var(--cream-dark);border-radius:var(--radius-input);font-size:.875rem;color:var(--ink);background:#fff}
select.form-select{padding-right:32px;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%238A8A82' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center}
.field-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:.85rem 1rem}.field-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:.85rem 1rem}
.toggle-row{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:11px 13px;border:1.5px solid var(--cream-dark);border-radius:10px;background:#fff}
.toggle-row-title{font-size:.875rem;font-weight:600;color:var(--ink);display:flex;align-items:center;gap:7px}.toggle-row-desc{font-size:.73rem;color:var(--ink-muted);margin-top:2px}
.toggle-switch{position:relative;display:inline-flex}.toggle-switch input{position:absolute;opacity:0}.toggle-track{width:42px;height:24px;border-radius:999px;background:var(--cream-dark);border:1.5px solid #d0cfc6;position:relative}
.toggle-switch input:checked ~ .toggle-track{background:var(--forest-mid);border-color:var(--forest-mid)}.toggle-thumb{position:absolute;top:3px;left:3px;width:14px;height:14px;border-radius:50%;background:#fff;transition:transform .2s}
.toggle-switch input:checked ~ .toggle-track .toggle-thumb{transform:translateX(18px)} .toggle-grid{display:grid;grid-template-columns:1fr 1fr;gap:.65rem}
.theme-options{display:flex;gap:.65rem;flex-wrap:wrap}.theme-option{flex:1;min-width:80px;max-width:140px;border:1.5px solid var(--cream-dark);border-radius:10px;padding:.7rem .85rem;cursor:pointer;background:var(--cream);display:flex;flex-direction:column;align-items:center;gap:6px}
.theme-option.selected{border-color:var(--forest-mid);background:#fff;box-shadow:0 0 0 2px rgba(45,106,79,.12)}.theme-option input{display:none}.theme-icon{font-size:1.25rem}.theme-label{font-size:.75rem;font-weight:600;color:var(--ink-soft)}
.banner{padding:11px 14px;border-radius:10px;font-size:.82rem;display:flex;align-items:flex-start;gap:10px;line-height:1.5}.banner-info{background:rgba(26,61,43,.05);border:1.5px solid rgba(26,61,43,.12);color:var(--forest)}.banner-warn{background:var(--amber-light);border:1.5px solid #fcd34d;color:var(--amber-dark)}
.btn{display:inline-flex;align-items:center;gap:8px;padding:9px 22px;border-radius:999px;font-size:.875rem;font-weight:600;cursor:pointer;border:none;text-decoration:none}.btn-primary{background:var(--forest);color:#fff}.btn-gold{background:var(--gold);color:var(--forest)}.btn-danger{background:var(--red);color:#fff}.btn-ghost{background:transparent;border:1.5px solid rgba(255,255,255,.3);color:rgba(255,255,255,.85)}.btn-sm{padding:7px 16px;font-size:.82rem}
.pw-strength{margin-top:6px;height:4px;border-radius:99px;background:var(--cream-dark);overflow:hidden}.pw-strength-fill{height:100%;border-radius:99px;transition:width .3s,background .3s;width:0}.pw-strength-label{font-size:.72rem;margin-top:3px;font-weight:600}
.save-bar{position:fixed;bottom:0;left:0;right:0;z-index:40;background:var(--forest);padding:.9rem 1.5rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;box-shadow:0 -4px 24px rgba(26,61,43,.25)}
.save-bar-left{font-size:.85rem;color:rgba(255,255,255,.7)}.save-bar-left strong{color:#fff}
#toast-container{position:fixed;top:1.25rem;left:50%;transform:translateX(-50%);z-index:9999;display:flex;flex-direction:column;align-items:center;gap:8px}.toast{padding:10px 20px;border-radius:10px;font-size:.875rem;font-weight:500;box-shadow:0 4px 20px rgba(0,0,0,.18)}.toast-success{background:var(--forest);color:#fff}.toast-error{background:#991b1b;color:#fff}
@media(max-width:720px){.field-grid-2,.field-grid-3{grid-template-columns:1fr}.toggle-grid{grid-template-columns:1fr}}@media(max-width:480px){.save-bar{flex-direction:column;align-items:stretch}}
</style>
</head>
<body>
<?php include __DIR__ . '/al_header_universal.php'; ?>
<div id="toast-container"></div>
<div class="page-wrap">
<header class="page-header">
<h1>General <em>Settings</em></h1><div class="header-rule"></div>
<p>Manage your account security, notifications, and display preferences.</p>
<a href="al_dashboard.php" class="header-back"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</header>
<?php if ($success_message): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i><?php echo $h($success_message); ?></div><?php endif; ?>
<?php if ($error_message): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?php echo $h($error_message); ?></div><?php endif; ?>

<div class="sec-card open" id="card-security"><button type="button" class="sec-header" onclick="toggleCard('card-security')"><div class="sec-header-left"><div class="sec-icon"><i class="fas fa-lock"></i></div><div><div class="sec-title">Account Security &amp; Access</div><div class="sec-subtitle">Password, login email, two-factor authentication</div></div></div><i class="fas fa-chevron-up sec-chevron"></i></button><div class="sec-body"><div class="sec-divider"></div>
<div class="sub-block"><div class="sub-block-title"><i class="fas fa-key" style="color:var(--forest-mid);font-size:.85rem;"></i> Change Password</div><div class="sub-block-desc">Use a strong password of at least 8 characters.</div>
<form method="POST" class="ajax-form" data-action="change_password"><input type="hidden" name="action" value="change_password"><div class="field-grid-3"><div><label class="field-label">Old Password</label><input type="password" name="old_password" class="form-input" required></div><div><label class="field-label">New Password</label><input type="password" name="new_password" id="newPwInput" class="form-input" required oninput="checkPwStrength(this.value)"><div class="pw-strength"><div class="pw-strength-fill" id="pwBar"></div></div><div class="pw-strength-label" id="pwLabel"></div></div><div><label class="field-label">Confirm Password</label><input type="password" name="confirm_password" class="form-input" required></div></div><button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-key"></i> Update Password</button></form></div>
<div class="sub-block"><div class="sub-block-title"><i class="fas fa-envelope" style="color:var(--forest-mid);font-size:.85rem;"></i> Update Login Email</div>
<form method="POST" class="ajax-form" data-action="update_email"><input type="hidden" name="action" value="update_email"><div class="field-grid-2"><div><label class="field-label">Current Email</label><input type="email" class="form-input" value="<?php echo $h($email); ?>" readonly></div><div><label class="field-label">New Login Email</label><input type="email" name="new_email" class="form-input" required></div></div><button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-envelope"></i> Update Email</button></form></div>
</div></div>

<div class="sec-card open" id="card-notifications"><button type="button" class="sec-header" onclick="toggleCard('card-notifications')"><div class="sec-header-left"><div class="sec-icon"><i class="fas fa-bell"></i></div><div><div class="sec-title">Notification &amp; Communication Preferences</div><div class="sec-subtitle">Choose which emails and alerts you receive</div></div></div><i class="fas fa-chevron-up sec-chevron"></i></button><div class="sec-body"><div class="sec-divider"></div>
<form method="POST" class="ajax-form" data-action="save_notifications"><input type="hidden" name="action" value="save_notifications">
<div class="toggle-grid"><?php foreach ([['newsletter','fas fa-newspaper','University Newsletter',$np['newsletter']],['event_invites','fas fa-calendar-alt','Alumni Event Invitations',$np['event_invites']],['fundraising','fas fa-hand-holding-heart','Fundraising / Giving Campaigns',$np['fundraising']],['jobs_updates','fas fa-briefcase','Job Board Updates',$np['jobs_updates']],['system_alerts','fas fa-shield-alt','System Alerts',$np['system_alerts']],['platform_notifications','fas fa-bell','Platform Notifications',$np['platform_notifications']]] as [$name,$icon,$title,$val]): ?><div class="toggle-row"><div><div class="toggle-row-title"><i class="<?php echo $icon; ?>" style="color:var(--forest-mid);font-size:.8rem;"></i><?php echo $h($title); ?></div></div><label class="toggle-switch"><input type="checkbox" name="<?php echo $name; ?>" value="1" <?php echo $chk($val); ?>><div class="toggle-track"><div class="toggle-thumb"></div></div></label></div><?php endforeach; ?></div>
<button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save Notification Preferences</button></form>
</div></div>

<div class="sec-card open" id="card-display"><button type="button" class="sec-header" onclick="toggleCard('card-display')"><div class="sec-header-left"><div class="sec-icon"><i class="fas fa-sliders-h"></i></div><div><div class="sec-title">System Display &amp; Preferences</div><div class="sec-subtitle">Time zone, language, and display theme</div></div></div><i class="fas fa-chevron-up sec-chevron"></i></button><div class="sec-body"><div class="sec-divider"></div>
<form method="POST" class="ajax-form" data-action="save_display"><input type="hidden" name="action" value="save_display"><div class="field-grid-2"><div><label class="field-label">Time Zone</label><select name="timezone" class="form-select"><?php foreach (['Asia/Manila','UTC','America/New_York','America/Chicago','America/Los_Angeles','Europe/London','Europe/Paris','Australia/Sydney','Asia/Singapore','Asia/Tokyo'] as $tz): ?><option value="<?php echo $h($tz); ?>" <?php echo $sel($dp['timezone'],$tz); ?>><?php echo $h($tz); ?></option><?php endforeach; ?></select></div><div><label class="field-label">Language</label><select name="language" class="form-select"><option value="en" <?php echo $sel($dp['language'],'en'); ?>>English</option><option value="fil" <?php echo $sel($dp['language'],'fil'); ?>>Filipino</option></select></div></div>
<div style="margin-top:1rem"><label class="field-label">Display Theme</label><div class="theme-options" id="themeOptions"><?php foreach ([['system','fas fa-circle-half-stroke','System'],['light','fas fa-sun','Light'],['dark','fas fa-moon','Dark']] as [$val,$icon,$lbl]): ?><label class="theme-option<?php echo $dp['theme']===$val?' selected':''; ?>"><input type="radio" name="theme" value="<?php echo $val; ?>" <?php echo $dp['theme']===$val?'checked':''; ?>><i class="<?php echo $icon; ?> theme-icon" style="color:var(--forest-mid);"></i><span class="theme-label"><?php echo $lbl; ?></span></label><?php endforeach; ?></div></div>
<button type="submit" class="btn btn-primary btn-sm" style="margin-top:.8rem"><i class="fas fa-save"></i> Save Display Preferences</button></form>
</div></div>

<div class="sec-card" id="card-deactivate"><button type="button" class="sec-header" onclick="toggleCard('card-deactivate')"><div class="sec-header-left"><div class="sec-icon" style="background:var(--red-light);color:var(--red);"><i class="fas fa-user-slash"></i></div><div><div class="sec-title" style="color:var(--red);">Account Deactivation</div><div class="sec-subtitle">Temporarily disable or permanently remove your account</div></div></div><i class="fas fa-chevron-down sec-chevron"></i></button><div class="sec-body"><div class="sec-divider"></div>
<div class="banner banner-warn"><i class="fas fa-exclamation-triangle"></i><span>Deactivating your account will hide your profile and stop notifications.</span></div>
<div class="field-grid-2" style="margin-top:1rem"><div class="sub-block"><div class="sub-block-title"><i class="fas fa-pause-circle" style="color:var(--forest-mid);font-size:.85rem;"></i> Temporary Deactivation</div><form method="POST" class="ajax-form deactivate-form" data-action="deactivate_account" data-confirm="Are you sure you want to deactivate your account?"><input type="hidden" name="action" value="deactivate_account"><button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-user-slash"></i> Deactivate Account</button></form></div>
<div class="sub-block"><div class="sub-block-title"><i class="fas fa-trash-alt" style="color:var(--red);font-size:.85rem;"></i> Permanent Deletion Request</div><a href="mailto:dpo@fatima.edu.ph?subject=Account%20Deletion%20Request" class="btn btn-danger btn-sm" style="background:transparent;color:var(--red);border:1.5px solid var(--red);"><i class="fas fa-envelope"></i> Email DPO</a></div></div>
</div></div>

<div class="save-bar"><div class="save-bar-left">Logged in as <strong><?php echo $h(trim("$fname $lname")); ?></strong></div><div style="display:flex;gap:10px;"><a href="al_dashboard.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Dashboard</a><a href="al_privacy_settings.php" class="btn btn-gold"><i class="fas fa-shield-alt"></i> Privacy Settings</a></div></div>
</div>

<script>
function toggleCard(id){var card=document.getElementById(id);if(!card)return;var open=card.classList.toggle('open');var chev=card.querySelector('.sec-chevron');if(chev){chev.classList.toggle('fa-chevron-up',open);chev.classList.toggle('fa-chevron-down',!open);}}
document.querySelectorAll('#themeOptions .theme-option').forEach(function(opt){opt.addEventListener('click',function(){document.querySelectorAll('#themeOptions .theme-option').forEach(function(o){o.classList.remove('selected');});opt.classList.add('selected');var inp=opt.querySelector('input[type="radio"]');if(inp)inp.checked=true;});});
function checkPwStrength(pw){var bar=document.getElementById('pwBar'),label=document.getElementById('pwLabel');if(!bar||!label)return;var s=0;if(pw.length>=8)s++;if(pw.length>=12)s++;if(/[A-Z]/.test(pw))s++;if(/[0-9]/.test(pw))s++;if(/[^A-Za-z0-9]/.test(pw))s++;var lv=[['0%','transparent',''],['20%','#ef4444','Very Weak'],['40%','#f97316','Weak'],['60%','#eab308','Fair'],['80%','#22c55e','Strong'],['100%','#16a34a','Very Strong']][Math.min(s,5)];bar.style.width=lv[0];bar.style.background=lv[1];label.textContent=lv[2];label.style.color=lv[1];}
function showToast(msg,type){var tc=document.getElementById('toast-container');if(!tc)return;var el=document.createElement('div');el.className='toast '+(type==='error'?'toast-error':'toast-success');el.textContent=msg;tc.appendChild(el);setTimeout(function(){el.remove();},2400);}
document.querySelectorAll('form.ajax-form').forEach(function(form){form.addEventListener('submit',function(e){e.preventDefault();var msg=form.dataset.confirm;if(msg&&!confirm(msg))return;var btn=form.querySelector('button[type="submit"]');var orig=btn?btn.innerHTML:'';if(btn){btn.disabled=true;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Saving...';}fetch(window.location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(form)}).then(function(r){return r.json();}).then(function(data){if(data.status==='success'){showToast(data.message||'Saved successfully!','success');if(form.dataset.action==='change_password'){form.querySelectorAll('input[type="password"]').forEach(function(i){i.value='';});checkPwStrength('');}}else{showToast(data.message||'An error occurred.','error');}}).catch(function(){showToast('Network error - please try again.','error');}).finally(function(){if(btn){btn.disabled=false;btn.innerHTML=orig;}});});});
</script>
<?php if (file_exists(__DIR__ . '/al_footer_universal.php')) { include 'al_footer_universal.php'; } ?>
</body>
</html>
