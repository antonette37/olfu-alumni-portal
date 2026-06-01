<?php
session_start();
require_once 'config.php';
alumni_otp_gate_after_session();

if (!isset($_SESSION['user_id'])) {
    header("Location: al_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

@$conn->query("CREATE TABLE IF NOT EXISTS privacy_settings (
  user_id INT PRIMARY KEY,
  salary_visibility VARCHAR(32) DEFAULT 'Private',
  salary_range VARCHAR(32) NULL,
  salary_aggregated_consent TINYINT(1) DEFAULT 0,
  salary_group_visibility VARCHAR(32) DEFAULT 'Private',
  contact_visibility VARCHAR(32) DEFAULT 'Private',
  employment_visibility VARCHAR(32) DEFAULT 'Admin Only',
  photo_visibility VARCHAR(32) DEFAULT 'Admin Only',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $salary_visibility          = $_POST['salary_visibility']          ?? 'Private';
    $salary_range               = $_POST['salary_range']               ?? null;
    $salary_aggregated_consent  = isset($_POST['salary_aggregated_consent']) ? 1 : 0;
    $salary_group_visibility    = $_POST['salary_group_visibility']    ?? 'Private';
    $contact_visibility         = $_POST['contact_visibility']         ?? 'Private';
    $employment_visibility      = $_POST['employment_visibility']      ?? 'Admin Only';
    $photo_visibility           = $_POST['photo_visibility']           ?? 'Admin Only';

    $sql = "UPDATE itcp SET salary_visibility=?, salary_range=? WHERE id=?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ssi", $salary_visibility, $salary_range, $user_id);
        if ($stmt->execute()) { $success_message = "Privacy settings updated successfully!"; }
        else { $error_message = "Error updating privacy settings: " . $stmt->error; }
        $stmt->close();
    } else { $error_message = "Failed to prepare update statement: " . $conn->error; }

    $upsert = "INSERT INTO privacy_settings
        (user_id,salary_visibility,salary_range,salary_aggregated_consent,salary_group_visibility,contact_visibility,employment_visibility,photo_visibility)
        VALUES (?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
          salary_visibility=VALUES(salary_visibility),
          salary_range=VALUES(salary_range),
          salary_aggregated_consent=VALUES(salary_aggregated_consent),
          salary_group_visibility=VALUES(salary_group_visibility),
          contact_visibility=VALUES(contact_visibility),
          employment_visibility=VALUES(employment_visibility),
          photo_visibility=VALUES(photo_visibility)";
    $up = $conn->prepare($upsert);
    if ($up) {
        $sr = $salary_range ?? '';
        $up->bind_param("ississss", $user_id, $salary_visibility, $sr, $salary_aggregated_consent, $salary_group_visibility, $contact_visibility, $employment_visibility, $photo_visibility);
        if (!$up->execute()) {
            $error_message = (isset($error_message) ? $error_message . ' | ' : '') . "Error saving privacy settings: " . $up->error;
        }
        $up->close();
    }

    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['status' => isset($error_message) ? 'error' : 'success', 'message' => isset($error_message) ? $error_message : ($success_message ?? 'Saved')]);
        exit;
    }
}

$sql = "SELECT firstname, salary_visibility, salary_range FROM itcp WHERE id=?";
$stmt = $conn->prepare($sql);
$userData = ['firstname' => 'Alumni', 'salary_visibility' => 'Private', 'salary_range' => null];
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $stmt->bind_result($fn, $sv, $sr);
        if ($stmt->fetch()) $userData = ['firstname' => $fn, 'salary_visibility' => $sv, 'salary_range' => $sr];
    }
    $stmt->close();
}

$ps = ['salary_aggregated_consent' => 0, 'salary_group_visibility' => 'Private', 'contact_visibility' => 'Private', 'employment_visibility' => 'Admin Only', 'photo_visibility' => 'Admin Only'];
$q = $conn->prepare("SELECT salary_aggregated_consent,salary_group_visibility,contact_visibility,employment_visibility,photo_visibility FROM privacy_settings WHERE user_id=?");
if ($q) {
    $q->bind_param("i", $user_id);
    if ($q->execute()) {
        $q->bind_result($agg, $grp, $contact, $employment, $photo);
        if ($q->fetch()) $ps = ['salary_aggregated_consent' => (int)$agg, 'salary_group_visibility' => (string)$grp, 'contact_visibility' => (string)$contact, 'employment_visibility' => (string)$employment, 'photo_visibility' => (string)$photo];
    }
    $q->close();
}

$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$sel = fn($a, $b) => $a === $b ? 'selected' : '';
$chk = fn($v) => $v ? 'checked' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Privacy Settings - Alumni Portal</title>
<link rel="icon" href="olfulogo.png" type="image/png">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400;1,600;1,700&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root { --cream:#F5F3EC; --cream-dark:#EDE9DF; --forest:#1A3D2B; --forest-mid:#2D6A4F; --forest-light:#4A9470; --gold:#C9A84C; --gold-light:#F0D98C; --ink:#1C1C1A; --ink-soft:#4A4A45; --ink-muted:#8A8A82; --white:#FFFFFF; --red:#DC2626; --red-light:#FEE2E2; --green-light:#DCFCE7; --radius-card:16px; --radius-input:10px; --shadow:0 2px 20px rgba(26,61,43,.07); --shadow-lg:0 8px 40px rgba(26,61,43,.13); }
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0} html{scroll-behavior:smooth}
body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--ink);line-height:1.6;min-height:100vh;padding-bottom:5rem}
body::before{content:'';position:fixed;inset:0;background-image:radial-gradient(circle,rgba(26,61,43,.045) 1px,transparent 1px);background-size:28px 28px;pointer-events:none;z-index:0}
.page-wrap{position:relative;z-index:1;max-width:1080px;margin:0 auto;padding:3.5rem 1.5rem 4rem}
.page-header{margin-bottom:2.25rem}.page-header h1{font-family:'Cormorant Garamond',serif;font-size:clamp(2.2rem,5vw,3rem);font-weight:700;color:var(--forest);line-height:1.1;letter-spacing:-.01em}.page-header h1 em{font-style:italic;color:var(--forest-mid)}
.header-rule{height:3px;width:56px;background:linear-gradient(90deg,var(--forest-mid),var(--gold));border-radius:99px;margin:10px 0 14px}
.page-header p{color:var(--ink-soft);font-size:.95rem;max-width:56ch}
.header-policy-link{display:inline-flex;align-items:center;gap:7px;margin-top:10px;font-size:.82rem;color:var(--forest-mid);text-decoration:underline;text-underline-offset:3px;font-weight:500}
.alert{display:flex;align-items:center;gap:10px;padding:13px 16px;border-radius:10px;font-size:.875rem;margin-bottom:1.5rem}
.alert-success{background:var(--green-light);color:#166534;border:1px solid #86efac}.alert-error{background:var(--red-light);color:#991b1b;border:1px solid #fca5a5}
.content-grid{display:grid;grid-template-columns:1fr 300px;gap:1.5rem;align-items:start}
.priv-card{background:var(--white);border-radius:var(--radius-card);border:1.5px solid var(--cream-dark);box-shadow:var(--shadow);overflow:hidden;margin-bottom:1rem}
.priv-card:hover{box-shadow:var(--shadow-lg)} .priv-card-header{width:100%;display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.4rem;background:none;border:none;cursor:pointer;text-align:left;gap:12px}
.priv-card-header-left{display:flex;align-items:center;gap:12px}.priv-card-icon{width:38px;height:38px;border-radius:9px;background:var(--cream);display:flex;align-items:center;justify-content:center;color:var(--forest-mid);font-size:.95rem;flex-shrink:0}
.priv-card-title{font-family:'Cormorant Garamond',serif;font-size:1.15rem;font-weight:700;color:var(--forest)} .priv-card-subtitle{font-size:.73rem;color:var(--ink-muted);margin-top:1px}
.priv-card-chevron{color:var(--ink-muted);font-size:.8rem;transition:transform .25s}.priv-card.open .priv-card-chevron{transform:rotate(180deg)}
.priv-card-body{padding:0 1.4rem 1.4rem;display:none}.priv-card.open .priv-card-body{display:block}.priv-card-divider{height:1px;background:var(--cream-dark);margin-bottom:1.2rem}
.field-label{font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--ink-muted);margin-bottom:7px;display:block}
.field-hint{font-size:.75rem;color:var(--ink-muted);margin-top:5px;line-height:1.45}
select.form-select{width:100%;padding:9px 32px 9px 12px;border:1.5px solid var(--cream-dark);border-radius:var(--radius-input);font-family:'DM Sans',sans-serif;font-size:.875rem;color:var(--ink);background:var(--cream);appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%238A8A82' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center}
.vis-options{display:flex;flex-direction:column;gap:7px}.vis-option{display:flex;align-items:flex-start;gap:12px;padding:10px 13px;border:1.5px solid var(--cream-dark);border-radius:10px;cursor:pointer;background:var(--cream)}
.vis-option.selected{border-color:var(--forest-mid);background:var(--white);box-shadow:0 0 0 2px rgba(45,106,79,.12)} .vis-icon{font-size:.85rem;margin-top:2px;width:16px;flex-shrink:0}
.vis-option-label{font-size:.875rem;font-weight:600;color:var(--ink)} .vis-option-desc{font-size:.73rem;color:var(--ink-muted);margin-top:1px;line-height:1.4}
.toggle-row{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;padding:12px 14px;border:1.5px solid var(--cream-dark);border-radius:10px;background:var(--cream)}
.toggle-switch{position:relative;display:inline-flex;align-items:center}.toggle-switch input{position:absolute;opacity:0;width:0;height:0}
.toggle-track{width:42px;height:24px;border-radius:999px;background:var(--cream-dark);border:1.5px solid #d0cfc6;position:relative;cursor:pointer}
.toggle-switch input:checked ~ .toggle-track{background:var(--forest-mid);border-color:var(--forest-mid)} .toggle-thumb{position:absolute;top:3px;left:3px;width:14px;height:14px;border-radius:50%;background:#fff;transition:transform .2s}
.toggle-switch input:checked ~ .toggle-track .toggle-thumb{transform:translateX(18px)}
.impact-box{padding:12px 14px;border-radius:10px;background:rgba(26,61,43,.04);border:1.5px solid rgba(26,61,43,.1)} .impact-box-label{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--forest-mid);margin-bottom:5px}
.impact-box p{font-size:.8rem;color:var(--ink-soft);line-height:1.5}
.field-row-2{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.sidebar{position:sticky;top:24px;display:flex;flex-direction:column;gap:1rem}
.sidebar-card{background:var(--white);border-radius:var(--radius-card);border:1.5px solid var(--cream-dark);box-shadow:var(--shadow);padding:1.25rem}
.sidebar-card-rule{height:2px;width:32px;background:linear-gradient(90deg,var(--forest-mid),var(--gold));border-radius:99px;margin-bottom:.85rem}
.status-list{display:flex;flex-direction:column;gap:7px}.status-item{display:flex;align-items:center;justify-content:space-between;font-size:.8rem}
.status-badge{font-size:.68rem;font-weight:700;padding:2px 9px;border-radius:999px}.badge-private{background:#FEF3C7;color:#92400e}.badge-admin{background:rgba(26,61,43,.1);color:var(--forest)}.badge-connections{background:#EDE9FE;color:#5b21b6}.badge-public{background:#DCFCE7;color:#166534}
.right-item{display:flex;gap:10px;padding:9px 0;border-bottom:1px solid var(--cream-dark);font-size:.82rem}.right-item:last-child{border-bottom:none}
.dpa-badge{display:inline-flex;align-items:center;gap:8px;background:var(--forest);color:#fff;font-size:.78rem;font-weight:600;padding:8px 14px;border-radius:999px;margin-top:.75rem;width:100%;justify-content:center;text-decoration:none}
.save-bar{position:sticky;bottom:0;left:0;right:0;z-index:40;background:var(--forest);padding:.9rem 1.5rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;box-shadow:0 -4px 24px rgba(26,61,43,.25)}
.save-bar-left{font-size:.85rem;color:rgba(255,255,255,.7)} .save-bar-right{display:flex;gap:10px}
.btn{display:inline-flex;align-items:center;gap:8px;padding:9px 22px;border-radius:999px;font-size:.875rem;font-weight:600;cursor:pointer;border:none;text-decoration:none}
.btn-primary{background:var(--gold);color:var(--forest)} .btn-ghost{background:transparent;border:1.5px solid rgba(255,255,255,.3);color:rgba(255,255,255,.85)}
#toast-container{position:fixed;top:1.25rem;left:50%;transform:translateX(-50%);z-index:9999;display:flex;flex-direction:column;gap:8px}.toast{padding:10px 18px;border-radius:10px;font-size:.875rem;font-weight:500;box-shadow:0 4px 20px rgba(0,0,0,.18)}
.toast-success{background:var(--forest);color:#fff}.toast-error{background:#991b1b;color:#fff}
@media(max-width:860px){.content-grid{grid-template-columns:1fr}.sidebar{position:static}.field-row-2{grid-template-columns:1fr}} @media(max-width:540px){.save-bar{flex-direction:column;align-items:stretch}}
</style>
</head>
<body>
<?php include __DIR__ . '/al_header_universal.php'; ?>
<div id="toast-container"></div>
<div class="page-wrap">
    <header class="page-header">
        <h1>Privacy <em>Settings</em></h1>
        <div class="header-rule"></div>
        <p>Control exactly what data the University and fellow alumni can see, in line with the <strong>Data Privacy Act of 2012</strong>.</p>
        <a href="gen_faqs.php" class="header-policy-link"><i class="fas fa-shield-alt"></i> University Data Privacy Policy</a>
    </header>

    <?php if (isset($success_message)): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i><?php echo $h($success_message); ?></div><?php endif; ?>
    <?php if (isset($error_message)): ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?php echo $h($error_message); ?></div><?php endif; ?>

    <form method="POST" id="privacyForm">
    <div class="content-grid">
        <div>
            <div class="priv-card open" id="card-salary">
                <button type="button" class="priv-card-header" onclick="toggleCard('card-salary')">
                    <div class="priv-card-header-left"><div class="priv-card-icon"><i class="fas fa-wallet"></i></div><div><div class="priv-card-title">Salary &amp; Employment Data</div><div class="priv-card-subtitle">Sensitive - controls who sees your income and work info</div></div></div>
                    <i class="fas fa-chevron-up priv-card-chevron"></i>
                </button>
                <div class="priv-card-body">
                    <div class="priv-card-divider"></div>
                    <div class="field-row-2" style="margin-bottom:1.1rem;">
                        <div>
                            <label class="field-label">Salary Range</label>
                            <select name="salary_range" class="form-select">
                                <option value="">Prefer not to say</option>
                                <?php foreach (['Below 20k'=>'Below PHP 20,000','20k-30k'=>'PHP 20,000 - PHP 30,000','30k-50k'=>'PHP 30,000 - PHP 50,000','50k-100k'=>'PHP 50,000 - PHP 100,000','Above 100k'=>'Above PHP 100,000'] as $val=>$label): ?>
                                <option value="<?php echo $h($val); ?>" <?php echo $sel($userData['salary_range'] ?? '', $val); ?>><?php echo $h($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="field-hint">Used for analytics only. We never disclose your exact salary.</p>
                        </div>
                        <div class="impact-box"><div class="impact-box-label"><i class="fas fa-eye"></i> Current Status</div><p id="salaryImpactText">Select a visibility option below to see who can view your salary range.</p></div>
                    </div>

                    <div style="margin-bottom:1.1rem;">
                        <label class="field-label">Statistical Use</label>
                        <div class="toggle-row">
                            <div><div style="font-size:.875rem;font-weight:600;color:var(--ink)"><i class="fas fa-chart-line" style="color:var(--forest-mid);font-size:.8rem;"></i> Allow anonymous data for university statistics</div><div style="font-size:.73rem;color:var(--ink-muted);margin-top:2px;line-height:1.4">Included in aggregated, non-identifiable reports for accreditation and tracer studies.</div></div>
                            <label class="toggle-switch"><input type="checkbox" name="salary_aggregated_consent" value="1" <?php echo $chk($ps['salary_aggregated_consent']); ?> onchange="refreshSidebar()"><div class="toggle-track"><div class="toggle-thumb"></div></div></label>
                        </div>
                    </div>

                    <div class="field-row-2">
                        <div>
                            <label class="field-label">Individual Visibility</label>
                            <div class="vis-options">
                                <?php $curSalVis = $userData['salary_visibility'] ?? 'Private'; foreach ([['Private','fas fa-lock','color:var(--ink-muted)','Only Me','Only you can see your salary range.'],['Admin Only','fas fa-shield-alt','color:var(--forest-mid)','Admin / Department','Visible to designated Alumni Relations staff.'],['Connections','fas fa-user-friends','color:#7c3aed','My Connections','Visible only to alumni you approved.'],['Public','fas fa-globe','color:#d97706','All Alumni','Visible to every alumni user on this platform.']] as [$val,$icon,$iconStyle,$lbl,$desc]): ?>
                                <label class="vis-option<?php echo $curSalVis===$val?' selected':''; ?>" data-group="salary_visibility">
                                    <input type="radio" name="salary_visibility" value="<?php echo $h($val); ?>" <?php echo $curSalVis===$val?'checked':''; ?> onchange="onVisChange(this,'salary_visibility')">
                                    <i class="<?php echo $icon; ?> vis-icon" style="<?php echo $iconStyle; ?>"></i>
                                    <div><div class="vis-option-label"><?php echo $h($lbl); ?></div><div class="vis-option-desc"><?php echo $h($desc); ?></div></div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div>
                            <label class="field-label">Group / Peer Visibility</label>
                            <select name="salary_group_visibility" class="form-select" onchange="refreshSidebar()">
                                <?php foreach (['Private'=>'Private','Admin Only'=>'Admin Only','Department'=>'Department / College Only','Class Year'=>'Class Year Only','Connections'=>'Connections Only','Public'=>'Public'] as $val=>$lbl): ?>
                                <option value="<?php echo $h($val); ?>" <?php echo $sel($ps['salary_group_visibility'] ?? 'Private', $val); ?>><?php echo $h($lbl); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="field-hint">Choose which peer groups can see your salary range.</p>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            $sections = [
                ['card-contact','fas fa-address-book','Contact & Profile Visibility','Email, phone number, and address','contact_visibility','Private'],
                ['card-employment','fas fa-briefcase','Employment Information','Company, position, industry, and work history','employment_visibility','Admin Only'],
                ['card-photo','fas fa-image','Profile Photo & Stories','Profile picture, uploaded photos, and shared stories','photo_visibility','Admin Only'],
            ];
            foreach ($sections as [$id,$icon,$title,$sub,$field,$default]):
                $cur = $ps[$field] ?? $default;
            ?>
            <div class="priv-card open" id="<?php echo $id; ?>">
                <button type="button" class="priv-card-header" onclick="toggleCard('<?php echo $id; ?>')">
                    <div class="priv-card-header-left"><div class="priv-card-icon"><i class="<?php echo $icon; ?>"></i></div><div><div class="priv-card-title"><?php echo $h($title); ?></div><div class="priv-card-subtitle"><?php echo $h($sub); ?></div></div></div>
                    <i class="fas fa-chevron-up priv-card-chevron"></i>
                </button>
                <div class="priv-card-body">
                    <div class="priv-card-divider"></div>
                    <label class="field-label">Who can see this information?</label>
                    <div class="vis-options">
                        <?php foreach ([['Private','fas fa-lock','color:var(--ink-muted)','Only Me','Only you can see this.'],['Admin Only','fas fa-shield-alt','color:var(--forest-mid)','Admin / Department','Only alumni staff can view for official purposes.'],['Connections','fas fa-user-friends','color:#7c3aed','My Connections','Visible to approved alumni connections only.'],['Public','fas fa-globe','color:#d97706','All Alumni','Visible to all alumni users on this portal.']] as [$val,$i,$style,$lbl,$desc]): ?>
                        <label class="vis-option<?php echo $cur===$val?' selected':''; ?>" data-group="<?php echo $field; ?>">
                            <input type="radio" name="<?php echo $field; ?>" value="<?php echo $h($val); ?>" <?php echo $cur===$val?'checked':''; ?> onchange="onVisChange(this,'<?php echo $field; ?>')">
                            <i class="<?php echo $i; ?> vis-icon" style="<?php echo $style; ?>"></i>
                            <div><div class="vis-option-label"><?php echo $h($lbl); ?></div><div class="vis-option-desc"><?php echo $h($desc); ?></div></div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <aside class="sidebar">
            <div class="sidebar-card">
                <div style="font-family:'Cormorant Garamond',serif;font-size:1.05rem;font-weight:700;color:var(--forest);margin-bottom:.5rem"><i class="fas fa-eye" style="color:var(--forest-mid);margin-right:7px;font-size:.9rem;"></i>Privacy Snapshot</div>
                <div class="sidebar-card-rule"></div>
                <div class="status-list">
                    <?php foreach ([['salary','fas fa-wallet','Salary',$userData['salary_visibility'] ?? 'Private'],['contact','fas fa-address-book','Contact',$ps['contact_visibility'] ?? 'Private'],['employment','fas fa-briefcase','Employment',$ps['employment_visibility'] ?? 'Admin Only'],['photo','fas fa-image','Photo',$ps['photo_visibility'] ?? 'Admin Only']] as [$key,$i,$lbl,$val]): $cls = $val==='Public'?'badge-public':($val==='Connections'?'badge-connections':($val==='Admin Only'?'badge-admin':'badge-private')); ?>
                    <div class="status-item" data-snapshot-key="<?php echo $key; ?>"><span><i class="<?php echo $i; ?>"></i> <?php echo $h($lbl); ?></span><span class="status-badge <?php echo $cls; ?>"><?php echo $h($val); ?></span></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="sidebar-card">
                <div style="font-family:'Cormorant Garamond',serif;font-size:1.05rem;font-weight:700;color:var(--forest);margin-bottom:.5rem"><i class="fas fa-balance-scale" style="color:var(--forest-mid);margin-right:7px;font-size:.9rem;"></i>Your Data Rights</div>
                <div class="sidebar-card-rule"></div>
                <?php foreach ([['fas fa-info-circle','Right to be Informed'],['fas fa-hand-paper','Right to Object'],['fas fa-edit','Right to Rectification'],['fas fa-trash','Right to Erasure'],['fas fa-download','Right to Data Portability']] as [$i,$t]): ?><div class="right-item"><i class="<?php echo $i; ?>"></i><div><?php echo $h($t); ?></div></div><?php endforeach; ?>
                <a href="gen_faqs.php" class="dpa-badge"><i class="fas fa-shield-alt"></i> Full Privacy Policy</a>
            </div>
        </aside>
    </div>

    <div class="save-bar">
        <div class="save-bar-left">Review your settings above, then save to apply.</div>
        <div class="save-bar-right"><a href="al_profile.php" class="btn btn-ghost"><i class="fas fa-arrow-left"></i> Back to Profile</a><button type="submit" id="saveBtn" class="btn btn-primary"><i class="fas fa-save"></i> Save Settings</button></div>
    </div>
    </form>
</div>

<script>
function toggleCard(id){var c=document.getElementById(id);if(!c)return;c.classList.toggle('open');}
function onVisChange(input,group){document.querySelectorAll('.vis-option[data-group="'+group+'"]').forEach(function(el){el.classList.remove('selected')});var p=input.closest('.vis-option');if(p)p.classList.add('selected');refreshSidebar();if(group==='salary_visibility')updateSalaryImpact(input.value);}
var impactMap={'Private':'Only you will see your salary range.','Admin Only':'Your salary range will only be seen by designated University staff.','Connections':'Your salary range will be visible to approved alumni connections only.','Public':'Your salary range is visible to all alumni users on this platform.'};
function updateSalaryImpact(v){var el=document.getElementById('salaryImpactText');if(el)el.textContent=impactMap[v]||'Visibility is applied based on your choice.';}
function refreshSidebar(){var map={salary:'salary_visibility',contact:'contact_visibility',employment:'employment_visibility',photo:'photo_visibility'};Object.keys(map).forEach(function(k){var ch=document.querySelector('input[name="'+map[k]+'"]:checked');var row=document.querySelector('[data-snapshot-key="'+k+'"] .status-badge');if(ch&&row){row.className='status-badge '+(ch.value==='Public'?'badge-public':(ch.value==='Connections'?'badge-connections':(ch.value==='Admin Only'?'badge-admin':'badge-private')));row.textContent=ch.value;}});}
(function(){var checked=document.querySelector('input[name="salary_visibility"]:checked');if(checked)updateSalaryImpact(checked.value);})();
(function(){var form=document.getElementById('privacyForm'),btn=document.getElementById('saveBtn'),tc=document.getElementById('toast-container');if(!form||!btn)return;function toast(msg,t){var e=document.createElement('div');e.className='toast '+(t==='error'?'toast-error':'toast-success');e.textContent=msg;tc.appendChild(e);setTimeout(function(){e.remove()},2200);}form.addEventListener('submit',function(e){e.preventDefault();var original=btn.innerHTML;btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> Saving...';btn.disabled=true;fetch(window.location.href,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:new FormData(form)}).then(function(r){return r.json()}).then(function(d){if(d.status==='success'){toast('Settings saved successfully!','success');btn.innerHTML='<i class="fas fa-check"></i> Saved!';}else{toast(d.message||'Save failed','error');btn.innerHTML='<i class="fas fa-exclamation-triangle"></i> Retry';}}).catch(function(){toast('Network error - please try again.','error');btn.innerHTML='<i class="fas fa-exclamation-triangle"></i> Retry';}).finally(function(){setTimeout(function(){btn.innerHTML=original;btn.disabled=false;},1300);});});})();
</script>
<?php if (file_exists(__DIR__ . '/al_footer_universal.php')) { include 'al_footer_universal.php'; } ?>
</body>
</html>
