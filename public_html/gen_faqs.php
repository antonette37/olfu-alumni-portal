<?php
session_start();
require_once 'config.php';

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
$user = []; $notifications = []; $notification_count = 0;

if ($is_logged_in) {
    if ($stmt = $conn->prepare("SELECT id, firstname, lastname, photo FROM itcp WHERE id = ?")) {
        $stmt->bind_param('i', $user_id); $stmt->execute();
        $user = _olfu_stmt_fetch_assoc_local($stmt) ?: [];
        $stmt->close();
    }
    if ($stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10")) {
        $stmt->bind_param('i', $user_id); $stmt->execute();
        $notifications = _olfu_stmt_fetch_all_local($stmt);
        foreach ($notifications as $n) if (empty($n['is_read'])) $notification_count++;
        $stmt->close();
    }
}

$faq_sections = [
    "General" => [
        ["What is the OLFU Alumni Management System?","The OLFU Alumni Management System (AMS) is an official digital platform connecting Our Lady of Fatima University graduates with each other and with the institution."],
        ["Who can register?","All graduates of OLFU are eligible across all campuses and levels."],
        ["How do I create an account?","Click Register on the homepage and complete the Alumni Registration Form."],
        ["I forgot my password. How do I reset it?","Use Forgot Password on login and follow the reset instructions sent to your email."],
        ["Is there a mobile version?","Yes, the portal is responsive and works on modern mobile browsers."],
    ],
    "Profile & Account" => [
        ["How do I update my personal information?","Go to My Profile then Edit Profile to update details."],
        ["Can I upload my resume to auto-fill my profile?","Yes. Resume upload accepts PDF and DOCX to pre-fill fields."],
        ["How do I change my profile photo?","Use the profile photo uploader in Edit Profile."],
        ["What is the digital signature for?","It is used on your printable Alumni ID card."],
    ],
    "Alumni Card" => [
        ["How do I apply for an Alumni Card?","Submit the official online registration form and wait for verification."],
        ["How much does the Alumni Card cost?","First card is free; renewal/replacement has a fee."],
        ["How long is the card valid?","Three (3) years from issuance."],
    ],
];
$icons = ['General'=>'info-circle','Profile & Account'=>'user-edit','Alumni Card'=>'id-card'];
$total_q = array_sum(array_map('count', $faq_sections));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<title>FAQ - OLFU Alumni Portal</title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600;700&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"/>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet"/>
<style>
:root{--cream:#F5F3EC;--cream-dark:#EDE9DF;--forest:#1A3D2B;--forest-mid:#2D6A4F;--gold:#C9A84C;--ink:#1C1C1A;--ink-soft:#4A4A45;--ink-muted:#8A8A82;--white:#fff;--radius:14px;--shadow:0 2px 20px rgba(26,61,43,.08)}
*{box-sizing:border-box}body{margin:0;font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--ink)}.page-wrap{max-width:1200px;margin:0 auto;padding:2rem 1.5rem 4rem}
.page-header h1{font-family:'Cormorant Garamond',serif;font-size:clamp(2rem,4vw,2.8rem);margin:0;color:var(--forest)}.page-header em{color:var(--forest-mid)}.rule{height:3px;width:52px;background:linear-gradient(90deg,var(--forest-mid),var(--gold));border-radius:99px;margin-top:8px}.page-header p{color:var(--ink-soft)}
.filter-bar{background:#fff;border:1.5px solid var(--cream-dark);border-radius:var(--radius);padding:1rem 1.2rem;margin:1.2rem 0;display:flex;gap:.8rem;flex-wrap:wrap;box-shadow:var(--shadow)}
.search-wrap{position:relative;flex:1;min-width:220px}.search-wrap i{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--ink-muted)}.search-input{width:100%;padding:.55rem .8rem .55rem 2rem;border:1.5px solid var(--cream-dark);border-radius:9px}
.chip{padding:.35rem .8rem;border-radius:999px;border:1.5px solid var(--cream-dark);background:var(--cream);cursor:pointer;font-size:.78rem}.chip.active{background:var(--forest);color:#fff;border-color:var(--forest)}
.faq-section{margin-bottom:1rem}.sec-head{display:flex;align-items:center;gap:8px;margin-bottom:8px}.sec-icon{width:30px;height:30px;border-radius:7px;background:var(--forest);color:#fff;display:flex;align-items:center;justify-content:center}.sec-head h2{margin:0;font-family:'Cormorant Garamond',serif;color:var(--forest)}
.faq-list{background:#fff;border:1.5px solid var(--cream-dark);border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow)}.faq-item{border-bottom:1px solid var(--cream-dark)}.faq-item:last-child{border-bottom:none}
.faq-q{width:100%;border:0;background:none;display:flex;justify-content:space-between;padding:.9rem 1rem;text-align:left;cursor:pointer;font-weight:600;color:var(--ink)}.faq-a{display:none;padding:0 1rem 1rem;color:var(--ink-soft)}.faq-item.open .faq-a{display:block}
.no-results{display:none;text-align:center;padding:2rem;color:var(--ink-muted)}
.chatbot-toggle{position:fixed;right:20px;bottom:20px;z-index:60;border:0;background:var(--forest);color:#fff;width:56px;height:56px;border-radius:50%;box-shadow:0 10px 25px rgba(26,61,43,.25);cursor:pointer}
.chatbot-panel{position:fixed;right:20px;bottom:86px;z-index:60;width:min(420px,calc(100vw - 24px));max-height:min(72vh,620px);background:#fff;border:1.5px solid var(--cream-dark);border-radius:16px;box-shadow:0 16px 45px rgba(26,61,43,.22);display:none;overflow:hidden}
.chatbot-panel.open{display:flex;flex-direction:column}
.cb-head{padding:.8rem 1rem;background:linear-gradient(90deg,var(--forest),var(--forest-mid));color:#fff;display:flex;align-items:center;justify-content:space-between}
.cb-head strong{font-size:.9rem}
.cb-body{padding:.8rem;overflow-y:auto;background:var(--cream);display:flex;flex-direction:column;gap:.5rem}
.msg{max-width:90%;padding:.55rem .7rem;border-radius:12px;font-size:.82rem;line-height:1.45}
.msg.user{align-self:flex-end;background:var(--forest);color:#fff;border-bottom-right-radius:4px}
.msg.bot{align-self:flex-start;background:#fff;color:var(--ink-soft);border:1px solid var(--cream-dark);border-bottom-left-radius:4px}
.cb-foot{padding:.65rem;border-top:1px solid var(--cream-dark);display:flex;gap:.45rem;background:#fff}
.cb-input{flex:1;border:1.5px solid var(--cream-dark);border-radius:10px;padding:.55rem .7rem;font-size:.82rem}
.cb-send{border:0;background:var(--forest);color:#fff;border-radius:10px;padding:.55rem .8rem;cursor:pointer}
.cb-quick{display:flex;gap:.3rem;flex-wrap:wrap;margin-top:.2rem}
.cb-q{font-size:.68rem;border:1px solid var(--cream-dark);padding:.28rem .5rem;border-radius:999px;background:#fff;cursor:pointer}
</style>
</head>
<body>
<?php include __DIR__ . '/al_header_universal.php'; ?>
<div class="page-wrap">
    <header class="page-header">
        <h1>Frequently Asked <em>Questions</em></h1>
        <div class="rule"></div>
        <p>Answers for common concerns about the alumni portal.</p>
    </header>
    <div class="filter-bar">
        <div class="search-wrap"><i class="fas fa-search"></i><input type="text" id="faqSearch" class="search-input" placeholder="Search questions and answers"></div>
        <span class="chip active" data-cat="All" onclick="setCategory('All')">All (<?php echo $total_q; ?>)</span>
        <?php foreach(array_keys($faq_sections) as $s): ?>
        <span class="chip" data-cat="<?php echo htmlspecialchars($s); ?>" onclick="setCategory('<?php echo addslashes($s); ?>')"><?php echo htmlspecialchars($s); ?></span>
        <?php endforeach; ?>
    </div>
    <div id="faqMain">
        <?php foreach($faq_sections as $s=>$qs): $ic=$icons[$s]??'circle'; ?>
        <div class="faq-section" data-section="<?php echo htmlspecialchars($s); ?>">
            <div class="sec-head"><div class="sec-icon"><i class="fas fa-<?php echo $ic; ?>"></i></div><h2><?php echo htmlspecialchars($s); ?></h2></div>
            <div class="faq-list">
                <?php foreach($qs as $q): ?>
                <div class="faq-item" data-q="<?php echo htmlspecialchars(strtolower($q[0])); ?>" data-a="<?php echo htmlspecialchars(strtolower($q[1])); ?>">
                    <button class="faq-q" onclick="toggleFaq(this)"><span><?php echo htmlspecialchars($q[0]); ?></span><i class="fas fa-plus"></i></button>
                    <div class="faq-a"><?php echo htmlspecialchars($q[1]); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="no-results" id="noResults">No matching questions found.</div>
    </div>
</div>
<button class="chatbot-toggle" id="cbToggle" title="Open FAQ Assistant"><i class="fas fa-robot"></i></button>
<div class="chatbot-panel" id="cbPanel" aria-live="polite">
    <div class="cb-head">
        <strong><i class="fas fa-robot"></i> FAQ Assistant</strong>
        <button type="button" id="cbClose" style="border:0;background:none;color:#fff;cursor:pointer;"><i class="fas fa-times"></i></button>
    </div>
    <div class="cb-body" id="cbBody">
        <div class="msg bot">Hi! I can help with registration, CPS ID, OTP, soft copy ID, legacy cardholders, and tracer study concerns.</div>
        <div class="cb-quick">
            <button class="cb-q" type="button" data-q="Which registration should I choose?">New vs Legacy?</button>
            <button class="cb-q" type="button" data-q="I did not get my OTP">OTP issue</button>
            <button class="cb-q" type="button" data-q="How do I get my soft copy ID">Soft copy ID</button>
            <button class="cb-q" type="button" data-q="What is a CPS ID">CPS ID</button>
        </div>
    </div>
    <div class="cb-foot">
        <input type="text" id="cbInput" class="cb-input" placeholder="Type your question...">
        <button type="button" id="cbSend" class="cb-send"><i class="fas fa-paper-plane"></i></button>
    </div>
</div>
<script>
let activeCat='All';
function toggleFaq(btn){const item=btn.closest('.faq-item');item.classList.toggle('open');}
function setCategory(cat){activeCat=cat;document.querySelectorAll('.chip').forEach(c=>c.classList.toggle('active',c.dataset.cat===cat));applyFilters();}
function applyFilters(){const q=(document.getElementById('faqSearch').value||'').toLowerCase().trim();let any=false;document.querySelectorAll('.faq-section').forEach(sec=>{const inCat=activeCat==='All'||sec.dataset.section===activeCat;let visible=false;sec.querySelectorAll('.faq-item').forEach(item=>{const show=inCat&&(!q||item.dataset.q.includes(q)||item.dataset.a.includes(q));item.style.display=show?'':'none';if(show)visible=true;});sec.style.display=visible?'':'none';if(visible)any=true;});document.getElementById('noResults').style.display=any?'none':'block';}
document.getElementById('faqSearch').addEventListener('input',applyFilters);
const cbState={awaitingIdClarification:false};
function cbAdd(text,who){const el=document.createElement('div');el.className='msg '+who;el.innerHTML=text;document.getElementById('cbBody').appendChild(el);document.getElementById('cbBody').scrollTop=document.getElementById('cbBody').scrollHeight;}
function normalize(t){return (t||'').toLowerCase().trim();}
function hasAny(t,arr){return arr.some(k=>t.includes(k));}
function botReply(raw){
  const t=normalize(raw);
  if(!t) return 'Please type your question so I can help.';
  if(cbState.awaitingIdClarification){
    cbState.awaitingIdClarification=false;
    if(hasAny(t,['new','no card','first'])) return 'For <strong>New Applicant</strong>: register without an existing ID number. After admin approval, your <strong>16-digit CPS ID</strong> is generated automatically and valid for <strong>3 years</strong>.';
    if(hasAny(t,['legacy','existing','old card','already'])) return 'For <strong>Legacy Cardholder</strong>: choose legacy path, provide your existing alumni card number, and upload front/back card images. The system preserves your existing ID (no new ID generated).';
    return 'I can help with IDs. Please answer with either <strong>New Applicant</strong> or <strong>Legacy Cardholder</strong>.';
  }
  if((t==='id'||hasAny(t,['alumni id','cps id','id number'])) && !hasAny(t,['soft copy','download','validity'])){cbState.awaitingIdClarification=true;return 'I can help with IDs. Are you asking about <strong>New ID</strong>, <strong>Existing Physical Card</strong>, or <strong>Lost ID</strong>?';}
  if(hasAny(t,['which registration','new vs legacy','legacy','existing cardholder','new applicant','graduated 2010','old graduate'])) return 'If you never had a physical alumni card, choose <strong>New Applicant</strong>. If you already have a physical card (even older batches), choose <strong>Legacy Cardholder</strong> so your existing ID is digitized.';
  if(hasAny(t,['record not found','registration failing','masterlist','not found'])) return 'Registration is validated against the Registrar Masterlist using your <strong>Student Number</strong> and <strong>Birthday</strong>. Please ensure both match official records exactly. If mismatch persists, contact the Registrar/AAO.';
  if(hasAny(t,['otp','code','did not get','resend','security'])) return 'OTP is sent to your registered personal email. Check <strong>Spam/Junk</strong>, wait up to 60 seconds, then tap <strong>Resend</strong>. OTP is valid for about <strong>15 minutes</strong>.';
  if(hasAny(t,['soft copy','digital id','download id','png'])) return 'After admin approval, your digital alumni ID is emailed to you. You can also open <strong>Alumni ID Card</strong> in the portal and use the download option anytime.';
  if(hasAny(t,['validity','expire','expiration','renew'])) return 'CPS Alumni IDs are valid for <strong>3 years</strong>. This helps keep tracer and accreditation records updated.';
  if(hasAny(t,['what is cps id','16-digit','cps'])) return 'The CPS ID is your official <strong>16-digit alumni identifier</strong>. It is assigned upon approval for new applicants, and retained for verified legacy cardholders.';
  if(hasAny(t,['tracer','career','job','why fill'])) return 'Tracer/career updates help OLFU track graduate outcomes for quality improvement and accreditation. Updated employment data improves recommendations and reporting.';
  if(hasAny(t,['change photo','update photo'])) return 'Yes, you can update your photo in profile settings. Some ID-related updates may require admin re-verification before reflecting on the official card.';
  if(hasAny(t,['help','admin','coordinator','contact'])) return 'You can message the Alumni Coordinator directly here: <a href="al_contact.php" target="_self">Contact Support</a>.';
  return 'I could not match that yet. You can ask about <strong>Masterlist</strong>, <strong>New vs Legacy</strong>, <strong>OTP</strong>, <strong>CPS ID</strong>, <strong>Soft Copy</strong>, or open <a href="al_contact.php" target="_self">Contact Support</a>.';
}
function cbSendNow(){
  const input=document.getElementById('cbInput'); const v=input.value.trim(); if(!v) return;
  cbAdd(v,'user'); input.value=''; cbAdd(botReply(v),'bot');
}
document.getElementById('cbToggle').addEventListener('click',()=>document.getElementById('cbPanel').classList.toggle('open'));
document.getElementById('cbClose').addEventListener('click',()=>document.getElementById('cbPanel').classList.remove('open'));
document.getElementById('cbSend').addEventListener('click',cbSendNow);
document.getElementById('cbInput').addEventListener('keydown',e=>{if(e.key==='Enter')cbSendNow();});
document.querySelectorAll('.cb-q').forEach(b=>b.addEventListener('click',()=>{document.getElementById('cbInput').value=b.dataset.q;cbSendNow();}));
</script>
</body>
</html>
