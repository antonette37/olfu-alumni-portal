<?php
error_reporting(E_ALL); ini_set('display_errors',0); ini_set('log_errors',1);
register_shutdown_function(function(){$e=error_get_last();if($e&&in_array($e['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR])){error_log('Fatal: '.$e['message']);}});
session_start(); require_once 'config.php'; alumni_otp_gate_after_session(); require_once 'admin_email_notifications.php';
if(!isset($_SESSION['user_id'])){header("Location: al_login.php");exit();}
$user_id=$_SESSION['user_id'];

$sql="SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC";
$stmt=$conn->prepare($sql);$stmt->bind_param("i",$user_id);$stmt->execute();
$notifications=$stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$notification_count=count(array_filter($notifications,fn($n)=>!$n['is_read']));

$user=null;$sql="SELECT * FROM itcp WHERE id=?";$stmt=$conn->prepare($sql);
if($stmt){$stmt->bind_param("i",$user_id);$stmt->execute();$result=$stmt->get_result();$user=$result->fetch_assoc();$stmt->close();}
if(!$user){header("Location: al_login.php");exit();}

$user_skills=[];$sql="SELECT skill_name FROM alumni_skills WHERE alumni_id=?";$stmt=$conn->prepare($sql);
if($stmt){$stmt->bind_param("i",$user_id);if($stmt->execute()){$r=$stmt->get_result();if($r)$user_skills=array_column($r->fetch_all(MYSQLI_ASSOC),'skill_name');}$stmt->close();}

@$conn->query("CREATE TABLE IF NOT EXISTS job_applications (id INT AUTO_INCREMENT PRIMARY KEY,job_id INT NOT NULL,applicant_id INT NOT NULL,applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY unique_application (job_id,applicant_id),INDEX (job_id),INDEX (applicant_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if(!empty($user_skills)){
    $sql="SELECT j.*,u.firstname as poster_firstname,u.lastname as poster_lastname,u.photo as poster_photo,(SELECT COUNT(*) FROM job_skills js WHERE js.job_id=j.id AND js.skill_name IN (".implode(',',array_fill(0,count($user_skills),'?')).")) as matching_skills FROM jobs j LEFT JOIN itcp u ON j.user_id=u.id WHERE j.status='active' ORDER BY matching_skills DESC,j.posted_date DESC";
    $stmt=$conn->prepare($sql);if($stmt){$types=str_repeat('s',count($user_skills));$stmt->bind_param($types,...$user_skills);if($stmt->execute()){$r=$stmt->get_result();if($r)$job_listings=$r->fetch_all(MYSQLI_ASSOC);}$stmt->close();}
}else{
    $sql="SELECT j.*,0 as matching_skills,u.firstname as poster_firstname,u.lastname as poster_lastname,u.photo as poster_photo FROM jobs j LEFT JOIN itcp u ON j.user_id=u.id WHERE j.status='active' ORDER BY j.posted_date DESC";
    $stmt=$conn->prepare($sql);if($stmt){if($stmt->execute()){$r=$stmt->get_result();if($r)$job_listings=$r->fetch_all(MYSQLI_ASSOC);}$stmt->close();}
}
if(!isset($job_listings))$job_listings=[];

if(!empty($job_listings)){
    $applied_ids=[];$stmt=$conn->prepare("SELECT job_id FROM job_applications WHERE applicant_id=?");
    if($stmt){$stmt->bind_param("i",$user_id);if($stmt->execute()){$r=$stmt->get_result();if($r)$applied_ids=array_column($r->fetch_all(MYSQLI_ASSOC),'job_id');}$stmt->close();}
    $lk=array_fill_keys(array_map('intval',$applied_ids),true);
    foreach($job_listings as &$j){$j['has_applied']=isset($lk[(int)($j['id']??0)])?1:0;}unset($j);
}

$industry_trends=[];$r=$conn->query("SELECT job_type as industry,COUNT(*) as job_count FROM jobs WHERE status IN ('pending','active') GROUP BY job_type ORDER BY job_count DESC LIMIT 5");
if($r)$industry_trends=$r->fetch_all(MYSQLI_ASSOC);

$top_skills=[];$r=$conn->query("SELECT js.skill_name,COUNT(DISTINCT js.job_id) as demand_count FROM job_skills js INNER JOIN jobs j ON js.job_id=j.id WHERE j.status IN ('pending','active') GROUP BY js.skill_name ORDER BY demand_count DESC LIMIT 10");
if($r)$top_skills=$r->fetch_all(MYSQLI_ASSOC);

$industries=[];$r=$conn->query("SELECT DISTINCT job_type as industry FROM jobs WHERE status='active' ORDER BY job_type");
if($r)$industries=$r->fetch_all(MYSQLI_ASSOC);
$locations=[];$r=$conn->query("SELECT DISTINCT location FROM jobs WHERE status='active' ORDER BY location");
if($r)$locations=$r->fetch_all(MYSQLI_ASSOC);

$job_post_success=false;$job_post_error='';
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['post_job'])){
    $title=trim($_POST['title']??'');$company=trim($_POST['company']??'');$location=trim($_POST['location']??'');
    $job_type=trim($_POST['job_type']??'');$salary_range=trim($_POST['salary_range']??'');
    $description=trim($_POST['description']??'');$requirements=trim($_POST['requirements']??'');
    if($title&&$company&&$location&&$job_type&&$salary_range&&$description){
        $sql="INSERT INTO jobs (title,company,location,job_type,salary_range,description,requirements,status,user_id,posted_date) VALUES (?,?,?,?,?,?,?,'pending',?,NOW())";
        $stmt=$conn->prepare($sql);
        if($stmt){$stmt->bind_param("sssssssi",$title,$company,$location,$job_type,$salary_range,$description,$requirements,$user_id);
            if($stmt->execute()){$job_post_success=true;$_POST=[];header("Location: ".$_SERVER['PHP_SELF']."?posted=1");exit();}
            else{$job_post_error="Failed to post job. Please try again.";}$stmt->close();
        }else{$job_post_error="Database error: ".$conn->error;}
    }else{$job_post_error="Please fill in all required fields.";}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Career Center — CCS Alumni</title>
<link rel="icon" href="olfulogo.png" type="image/png">
<link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
:root{
  --forest:#0d2e18;--pine:#133d23;--leaf:#1b5e35;--moss:#2d7a4f;--fern:#3d9966;
  --sage:#a8c9b0;--mist:#e8f2ec;--snow:#f5f9f6;--cream:#faf8f3;--white:#ffffff;
  --gold:#b8922a;--gold-lt:#e0b84a;--ink:#0c1a10;--charcoal:#2a3d30;
  --slate:#4a6355;--silver:#8aab96;--fog:#c8ddd2;
  --shadow:0 1px 3px rgba(13,46,24,.07),0 4px 16px rgba(13,46,24,.06);
  --shadow-h:0 4px 8px rgba(13,46,24,.1),0 12px 32px rgba(13,46,24,.12);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Outfit',system-ui,sans-serif;background:var(--cream);color:var(--ink)}
.page{max-width:1320px;margin:0 auto;padding:2rem 1.5rem 4rem}
.pg-title{font-family:'DM Serif Display',serif;font-size:clamp(1.8rem,3.5vw,2.6rem);color:var(--forest)}
.pg-title em{font-style:italic;color:var(--moss)}
.gold-bar{height:3px;width:60px;background:linear-gradient(90deg,var(--leaf),var(--gold));border-radius:2px;margin:.5rem 0 .75rem}
.pg-sub{font-size:.9rem;color:var(--slate)}

/* Tab nav */
.tab-nav{display:flex;background:var(--white);border-radius:16px;padding:.4rem;box-shadow:var(--shadow);margin:1.5rem 0;gap:.3rem}
.tab-btn{flex:1;padding:.7rem 1rem;border:none;border-radius:12px;font-family:'Outfit',sans-serif;font-size:.86rem;font-weight:500;color:var(--slate);background:none;cursor:pointer;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:.45rem}
.tab-btn.active{background:var(--forest);color:#fff;box-shadow:0 2px 8px rgba(13,46,24,.2)}
.tab-content{display:none}.tab-content.active{display:block}

/* Layout */
.career-layout{display:grid;grid-template-columns:240px 1fr 300px;gap:1.5rem;align-items:start}
@media(max-width:1200px){.career-layout{grid-template-columns:220px 1fr}}
@media(max-width:860px){.career-layout{grid-template-columns:1fr}}
.charts-col{display:flex;flex-direction:column;gap:1.25rem}
@media(max-width:1200px){.charts-col{display:none}}

/* Sidebar filters */
.filter-panel{background:var(--white);border-radius:18px;padding:1.5rem;box-shadow:var(--shadow);border:1px solid rgba(200,221,210,.5);position:sticky;top:5rem}
.filter-label{font-size:.68rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--moss);margin-bottom:.5rem;display:block}
.filter-input{width:100%;height:40px;border:1.5px solid var(--fog);border-radius:9px;padding:0 .875rem;font-family:'Outfit',sans-serif;font-size:.85rem;color:var(--ink);background:var(--snow);outline:none;transition:border-color .2s,box-shadow .2s;margin-bottom:1rem}
.filter-input:focus{border-color:var(--leaf);box-shadow:0 0 0 3px rgba(27,94,53,.09);background:var(--white)}

/* Post button */
.post-btn{display:flex;align-items:center;gap:.5rem;background:var(--forest);color:#fff;border:none;border-radius:12px;padding:.7rem 1.25rem;font-family:'Outfit',sans-serif;font-size:.86rem;font-weight:600;cursor:pointer;transition:all .2s;box-shadow:0 2px 8px rgba(13,46,24,.2);float:right;margin-bottom:1.25rem}
.post-btn:hover{background:var(--pine);transform:translateY(-1px);box-shadow:0 4px 16px rgba(13,46,24,.25)}

/* Job board header */
.board-head{display:flex;align-items:center;justify-content:space-between;background:var(--white);border-radius:14px;padding:1rem 1.25rem;box-shadow:var(--shadow);margin-bottom:1rem}
.board-title{font-family:'DM Serif Display',serif;font-size:1.1rem;color:var(--forest)}
.board-count{font-size:.82rem;color:var(--silver)}

/* Job card */
.job-card{background:var(--white);border-radius:16px;border:1.5px solid rgba(200,221,210,.6);padding:1.5rem;margin-bottom:1rem;box-shadow:var(--shadow);transition:all .25s}
.job-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-h);border-color:var(--sage)}
.jc-head{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:.875rem}
.jc-title{font-family:'DM Serif Display',serif;font-size:1.1rem;color:var(--forest);line-height:1.2}
.jc-company{font-size:.82rem;color:var(--slate);margin-top:.2rem}
.jc-match{display:flex;align-items:center;gap:.35rem;font-size:.72rem;font-weight:600;color:var(--moss);background:var(--mist);padding:.3rem .75rem;border-radius:999px;white-space:nowrap;flex-shrink:0;margin-left:.75rem}
.jc-meta{display:flex;flex-wrap:wrap;gap:.75rem;font-size:.78rem;color:var(--slate);margin-bottom:.875rem}
.jc-meta-item{display:flex;align-items:center;gap:.35rem}
.jc-meta-item i{color:var(--moss);font-size:.72rem}
.jc-desc{font-size:.84rem;color:var(--slate);line-height:1.65;margin-bottom:.875rem;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.jc-tags{display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:1rem}
.jc-tag{font-size:.72rem;padding:.25rem .65rem;background:var(--snow);border:1px solid var(--fog);border-radius:999px;color:var(--charcoal)}
.jc-foot{display:flex;align-items:center;justify-content:space-between;padding-top:.875rem;border-top:1px solid var(--mist)}
.poster-link{display:flex;align-items:center;gap:.6rem;text-decoration:none;transition:opacity .2s}
.poster-link:hover{opacity:.8}
.poster-avatar{width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid var(--mist);flex-shrink:0}
.poster-avatar-initials{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,var(--leaf),var(--moss));display:flex;align-items:center;justify-content:center;color:#fff;font-size:.75rem;font-weight:700;flex-shrink:0}
.poster-name{font-size:.82rem;font-weight:500;color:var(--charcoal)}
.poster-date{font-size:.72rem;color:var(--silver)}
.btn-apply{background:var(--forest);color:#fff;border:none;border-radius:10px;padding:.55rem 1.25rem;font-family:'Outfit',sans-serif;font-size:.82rem;font-weight:600;cursor:pointer;transition:all .2s;box-shadow:0 2px 6px rgba(13,46,24,.18)}
.btn-apply:hover{background:var(--pine);transform:translateY(-1px)}
.btn-apply.applied{background:#9ca3af;cursor:not-allowed;transform:none;box-shadow:none}
.btn-mine{font-size:.78rem;color:var(--silver)}

/* Chart cards */
.chart-card{background:var(--white);border-radius:16px;padding:1.25rem;box-shadow:var(--shadow);border:1px solid rgba(200,221,210,.5)}
.chart-title{font-family:'DM Serif Display',serif;font-size:1rem;color:var(--forest);margin-bottom:1rem}

/* Pending section */
.pending-section{margin-top:2rem;background:#fefce8;border:1px solid #fde68a;border-radius:16px;padding:1.5rem}
.pending-title{font-weight:600;color:#92400e;display:flex;align-items:center;gap:.5rem;margin-bottom:1rem}
.pending-card{background:var(--white);border-radius:12px;padding:1rem 1.25rem;border:1px solid #fde68a;margin-bottom:.75rem;display:flex;justify-content:space-between;align-items:flex-start}
.pending-card:last-child{margin-bottom:0}
.pending-name{font-weight:600;font-size:.9rem;color:var(--forest)}
.pending-co{font-size:.8rem;color:var(--slate)}
.pending-badge{font-size:.72rem;font-weight:600;padding:.25rem .75rem;background:#fef3c7;color:#92400e;border-radius:999px;white-space:nowrap}
.pending-hint{font-size:.78rem;color:var(--slate);margin-top:.35rem}

/* Stories tab */
.stories-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.5rem;margin-top:1.5rem}
.story-card{background:var(--white);border-radius:16px;padding:1.5rem;box-shadow:var(--shadow);border:1px solid rgba(200,221,210,.5)}
.story-avatar{width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem;margin-bottom:1rem}
.story-quote{font-size:.86rem;color:var(--slate);line-height:1.7;font-style:italic;margin-bottom:.875rem}
.story-meta{display:flex;align-items:center;gap:.5rem;font-size:.78rem;color:var(--silver)}
.story-meta i{color:var(--moss)}

/* Modal */
.modal-backdrop{position:fixed;inset:0;background:rgba(13,46,24,.5);z-index:500;display:none;align-items:flex-start;justify-content:center;padding:2rem 1rem;overflow-y:auto}
.modal-backdrop.open{display:flex}
.modal-box{background:var(--white);border-radius:20px;width:100%;max-width:560px;box-shadow:0 24px 80px rgba(13,46,24,.25);animation:mi .25s ease;margin:auto}
@keyframes mi{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:translateY(0)}}
.mh{display:flex;justify-content:space-between;align-items:center;padding:1.5rem 1.75rem;background:linear-gradient(135deg,var(--pine),var(--leaf));border-radius:20px 20px 0 0}
.mh-title{font-family:'DM Serif Display',serif;font-size:1.3rem;color:#fff}
.mh-sub{font-size:.8rem;color:rgba(255,255,255,.7);margin-top:.2rem}
.mh-close{background:none;border:none;cursor:pointer;color:rgba(255,255,255,.7);font-size:1.1rem}
.mh-close:hover{color:#fff}
.mb{padding:1.5rem 1.75rem}
.form-group{margin-bottom:1.1rem}
.form-label{display:block;font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--charcoal);margin-bottom:.4rem}
.form-label.req::after{content:'*';color:#e74c3c;margin-left:.2rem}
.form-control{width:100%;height:44px;border:1.5px solid var(--fog);border-radius:9px;padding:0 .875rem;font-family:'Outfit',sans-serif;font-size:.88rem;color:var(--ink);background:var(--snow);outline:none;transition:border-color .2s,box-shadow .2s}
.form-control:focus{border-color:var(--leaf);box-shadow:0 0 0 3px rgba(27,94,53,.09);background:var(--white)}
textarea.form-control{height:auto;padding:.75rem .875rem;resize:vertical}
.mf{padding:1rem 1.75rem;border-top:1px solid var(--mist);display:flex;justify-content:flex-end;gap:.75rem}
.btn-cancel{background:var(--snow);border:1px solid var(--fog);color:var(--charcoal);border-radius:10px;padding:.6rem 1.25rem;font-family:'Outfit',sans-serif;font-size:.86rem;cursor:pointer;transition:all .2s}
.btn-cancel:hover{background:var(--fog)}
.btn-submit{background:var(--forest);color:#fff;border:none;border-radius:10px;padding:.6rem 1.5rem;font-family:'Outfit',sans-serif;font-size:.86rem;font-weight:600;cursor:pointer;transition:background .2s}
.btn-submit:hover{background:var(--pine)}

/* Alert */
.alert{display:flex;align-items:center;gap:.65rem;padding:.875rem 1.25rem;border-radius:12px;font-size:.85rem;margin-bottom:1.25rem;border-left:3px solid}
.alert-error{background:#fdf3f2;color:#c0392b;border-color:#c0392b}
.alert-success{background:#f0faf3;color:var(--leaf);border-color:var(--leaf)}

/* Empty */
.empty{text-align:center;padding:3rem 1rem;color:var(--silver)}
.empty i{font-size:2.5rem;margin-bottom:.75rem;display:block}

@keyframes fade-up{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.fade-up{animation:fade-up .4s ease both}
</style>
</head>
<body>
<?php include __DIR__ . '/al_header_universal.php'; ?>

<div class="page">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:1rem" class="fade-up">
    <div>
      <div class="pg-title">Career <em>Center</em></div>
      <div class="gold-bar"></div>
      <p class="pg-sub">Explore opportunities, post job openings, and connect with employers in the alumni network.</p>
    </div>
  </div>

  <?php if (isset($_GET['posted'])): ?>
    <div class="alert alert-success fade-up"><i class="fas fa-check-circle"></i> Job posted! It's pending admin approval and will be visible once approved.</div>
  <?php endif; ?>
  <?php if ($job_post_error): ?>
    <div class="alert alert-error fade-up"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($job_post_error); ?></div>
  <?php endif; ?>

  <div class="tab-nav fade-up" style="animation-delay:.06s">
    <button class="tab-btn active" data-tab="jobs"><i class="fas fa-briefcase"></i> Job Board</button>
    <button class="tab-btn" data-tab="stories"><i class="fas fa-star"></i> Success Stories</button>
  </div>

  <!-- JOB BOARD TAB -->
  <div class="tab-content active fade-up" id="tab-jobs" style="animation-delay:.1s">
    <div class="career-layout">
      <!-- Filters -->
      <aside class="filter-panel">
        <div style="margin-bottom:1.25rem;padding-bottom:1.25rem;border-bottom:1px solid var(--mist)">
          <div style="font-family:'DM Serif Display',serif;font-size:1rem;color:var(--forest);margin-bottom:.5rem">Filters</div>
        </div>
        <label class="filter-label">Search</label>
        <input class="filter-input" id="jobSearch" placeholder="Title, company…" oninput="filterJobs()">
        <label class="filter-label">Job Type</label>
        <select class="filter-input" id="jobTypeFilter" onchange="filterJobs()">
          <option value="">All Types</option>
          <?php foreach($industries as $i): ?><option value="<?php echo htmlspecialchars($i['industry']); ?>"><?php echo htmlspecialchars($i['industry']); ?></option><?php endforeach; ?>
        </select>
        <label class="filter-label">Location</label>
        <select class="filter-input" id="locFilter" onchange="filterJobs()">
          <option value="">All Locations</option>
          <?php foreach($locations as $l): ?><option value="<?php echo htmlspecialchars($l['location']); ?>"><?php echo htmlspecialchars($l['location']); ?></option><?php endforeach; ?>
        </select>
      </aside>

      <!-- Job List -->
      <div>
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem">
          <div class="board-title">Job Opportunities</div>
          <button class="post-btn" onclick="document.getElementById('postModal').classList.add('open')">
            <i class="fas fa-plus"></i> Post a Job
          </button>
        </div>
        <div class="board-head">
          <span class="board-count" id="jobCount"><?php echo count($job_listings); ?> positions available</span>
        </div>

        <div id="jobList">
        <?php if (empty($job_listings)): ?>
          <div class="empty"><i class="fas fa-briefcase"></i><p>No job postings yet. Be the first to post!</p></div>
        <?php else: foreach ($job_listings as $job):
          $pName = trim(($job['poster_firstname']??'').(' '.($job['poster_lastname']??'')));
          $pPhoto = $job['poster_photo'] ?? '';
          $pPhotoUrl = ($pPhoto && file_exists(__DIR__.'/uploads/'.$pPhoto)) ? 'serve_profile_image.php?img='.urlencode($pPhoto) : '';
          $reqs = array_filter(array_map('trim', explode("\n", $job['requirements'] ?? '')));
        ?>
        <div class="job-card"
          data-title="<?php echo htmlspecialchars(strtolower($job['title']??'')); ?>"
          data-company="<?php echo htmlspecialchars(strtolower($job['company']??'')); ?>"
          data-type="<?php echo htmlspecialchars($job['job_type']??''); ?>"
          data-location="<?php echo htmlspecialchars($job['location']??''); ?>">
          <div class="jc-head">
            <div>
              <div class="jc-title"><?php echo htmlspecialchars($job['title']); ?></div>
              <div class="jc-company"><?php echo htmlspecialchars($job['company']); ?></div>
            </div>
            <span class="jc-match"><i class="fas fa-star"></i> <?php echo $job['matching_skills']; ?> match</span>
          </div>
          <div class="jc-meta">
            <span class="jc-meta-item"><i class="fas fa-map-marker-alt"></i><?php echo htmlspecialchars($job['location']); ?></span>
            <span class="jc-meta-item"><i class="fas fa-briefcase"></i><?php echo htmlspecialchars($job['job_type']); ?></span>
            <span class="jc-meta-item"><i class="fas fa-money-bill-wave"></i><?php echo htmlspecialchars($job['salary_range']); ?></span>
          </div>
          <div class="jc-desc"><?php echo htmlspecialchars($job['description']); ?></div>
          <?php if (!empty($reqs)): ?>
          <div class="jc-tags">
            <?php foreach(array_slice($reqs,0,5) as $r): ?><span class="jc-tag"><?php echo htmlspecialchars($r); ?></span><?php endforeach; ?>
          </div>
          <?php endif; ?>
          <div class="jc-foot">
            <a href="al_view_profile.php?id=<?php echo (int)$job['user_id']; ?>" class="poster-link">
              <?php if ($pPhotoUrl): ?>
                <img src="<?php echo htmlspecialchars($pPhotoUrl); ?>" class="poster-avatar">
              <?php else: $pi=strtoupper(substr($job['poster_firstname']??'A',0,1).substr($job['poster_lastname']??'',0,1)); ?>
                <div class="poster-avatar-initials"><?php echo $pi; ?></div>
              <?php endif; ?>
              <div>
                <div class="poster-name"><?php echo htmlspecialchars($pName ?: 'Alumni'); ?></div>
                <div class="poster-date"><?php echo date('M d, Y', strtotime($job['posted_date'])); ?></div>
              </div>
            </a>
            <div style="display:flex;align-items:center;gap:.75rem">
              <?php if ($job['user_id'] == $user_id): ?>
                <span class="btn-mine"><i class="fas fa-info-circle"></i> Your listing</span>
              <?php elseif (!empty($job['has_applied'])): ?>
                <button class="btn-apply applied" disabled><i class="fas fa-check-circle"></i> Applied</button>
              <?php else: ?>
                <!-- Contact / Message poster directly -->
                <a href="al_messages.php?to=<?php echo (int)$job['user_id']; ?>" class="btn-apply" style="text-decoration:none;display:inline-flex;align-items:center;gap:.4rem">
                  <i class="fas fa-paper-plane"></i> Contact
                </a>
                <button class="btn-apply" data-job-id="<?php echo (int)$job['id']; ?>" onclick="applyToJob(<?php echo (int)$job['id']; ?>, this)">Apply</button>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; endif; ?>
        <div id="noJobsMsg" class="empty" style="display:none"><i class="fas fa-search"></i><p>No jobs match your filters.</p></div>
        </div>
      </div>

      <!-- Charts sidebar -->
      <aside class="charts-col">
        <div class="chart-card">
          <div class="chart-title">Industry Breakdown</div>
          <canvas id="industryChart" height="220"></canvas>
        </div>
        <div class="chart-card">
          <div class="chart-title">Skills in Demand</div>
          <canvas id="skillsChart" height="220"></canvas>
        </div>
      </aside>
    </div>

    <!-- Pending jobs -->
    <?php
    $pj_sql="SELECT * FROM jobs WHERE user_id=? AND status='pending' ORDER BY posted_date DESC";
    $pj_stmt=$conn->prepare($pj_sql);if($pj_stmt){$pj_stmt->bind_param("i",$user_id);$pj_stmt->execute();$pending_jobs=$pj_stmt->get_result()->fetch_all(MYSQLI_ASSOC);}else{$pending_jobs=[];}
    if(!empty($pending_jobs)):?>
    <div class="pending-section">
      <div class="pending-title"><i class="fas fa-clock"></i> Your Pending Job Posts</div>
      <?php foreach($pending_jobs as $pj): ?>
      <div class="pending-card">
        <div>
          <div class="pending-name"><?php echo htmlspecialchars($pj['title']); ?></div>
          <div class="pending-co"><?php echo htmlspecialchars($pj['company']); ?></div>
          <div class="pending-hint">Under admin review — you'll be notified when approved.</div>
        </div>
        <span class="pending-badge">Pending Approval</span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- STORIES TAB -->
  <div class="tab-content" id="tab-stories">
    <div style="background:linear-gradient(135deg,var(--pine),var(--leaf));border-radius:20px;padding:2.5rem;margin-bottom:2rem">
      <div style="font-family:'DM Serif Display',serif;font-size:1.8rem;color:#fff;margin-bottom:.5rem">Featured Story</div>
      <div style="background:rgba(255,255,255,.12);border-radius:14px;padding:1.5rem;margin-top:1rem">
        <div style="font-size:1rem;color:rgba(255,255,255,.9);font-style:italic;line-height:1.75;margin-bottom:1rem">"The skills I developed at OLFU and the connections I made through the alumni network were instrumental in my journey to becoming a Senior Software Engineer at a leading tech company."</div>
        <div style="display:flex;align-items:center;gap:.75rem">
          <div style="width:38px;height:38px;background:rgba(255,255,255,.2);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem;color:#fff">JS</div>
          <div><div style="color:#fff;font-weight:600;font-size:.88rem">John Smith</div><div style="color:rgba(255,255,255,.6);font-size:.78rem">Class of 2018 · Computer Science</div></div>
        </div>
      </div>
    </div>
    <div class="stories-grid">
      <?php $stories=[['initials'=>'AM','color'=>'#dbeafe;color:#1d4ed8','name'=>'Alex Martinez','year'=>'2019','role'=>'CEO, Digital Solutions Inc.','quote'=>'"Started my own digital marketing agency right after graduation. The entrepreneurship skills I learned at OLFU gave me the confidence to take the leap."'],['initials'=>'SC','color'=>'#ede9fe;color:#6d28d9','name'=>'Sarah Chen','year'=>'2020','role'=>'Data Scientist, TechCorp','quote'=>'"Transitioned from finance to data science. The analytical thinking from my OLFU education was transferable to any field."'],['initials'=>'MR','color'=>'#dcfce7;color:#166534','name'=>'Michael Rodriguez','year'=>'2017','role'=>'Project Manager, Global Corp','quote'=>'"The networking opportunities through OLFU alumni events helped me land my dream job at a Fortune 500 company."']];
      foreach($stories as $s): ?>
      <div class="story-card">
        <div class="story-avatar" style="background:<?php echo $s['color']; ?>"><?php echo $s['initials']; ?></div>
        <div style="font-weight:600;font-size:.9rem;color:var(--forest);margin-bottom:.15rem"><?php echo $s['name']; ?></div>
        <div style="font-size:.75rem;color:var(--silver);margin-bottom:.875rem">Class of <?php echo $s['year']; ?></div>
        <div class="story-quote"><?php echo $s['quote']; ?></div>
        <div class="story-meta"><i class="fas fa-briefcase"></i> <?php echo $s['role']; ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Post Job Modal -->
<div class="modal-backdrop" id="postModal">
  <div class="modal-box">
    <div class="mh">
      <div>
        <div class="mh-title">Post a Job Opportunity</div>
        <div class="mh-sub">Share career openings with the alumni community</div>
      </div>
      <button class="mh-close" onclick="document.getElementById('postModal').classList.remove('open')"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" id="jobForm">
      <div class="mb">
        <input type="hidden" name="post_job" value="1">
        <div class="form-group"><label class="form-label req">Job Title</label><input class="form-control" name="title" required placeholder="e.g. Senior Software Engineer"></div>
        <div class="form-group"><label class="form-label req">Company</label><input class="form-control" name="company" required placeholder="e.g. Tech Solutions Inc."></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div class="form-group"><label class="form-label req">Location</label><input class="form-control" name="location" required placeholder="e.g. Manila"></div>
          <div class="form-group"><label class="form-label req">Job Type</label>
            <select class="form-control" name="job_type" required>
              <option value="">Select…</option>
              <option>Full-time</option><option>Part-time</option><option>Contract</option><option>Internship</option>
            </select>
          </div>
        </div>
        <div class="form-group"><label class="form-label req">Salary Range</label><input class="form-control" name="salary_range" required placeholder="e.g. ₱30,000 – ₱50,000"></div>
        <div class="form-group"><label class="form-label req">Description</label><textarea class="form-control" name="description" rows="4" required placeholder="Describe the role, responsibilities, and expectations…"></textarea></div>
        <div class="form-group"><label class="form-label">Requirements</label><textarea class="form-control" name="requirements" rows="4" placeholder="One requirement per line…"></textarea></div>
      </div>
      <div class="mf">
        <button type="button" class="btn-cancel" onclick="document.getElementById('postModal').classList.remove('open')">Cancel</button>
        <button type="submit" class="btn-submit">Submit Job Post</button>
      </div>
    </form>
  </div>
</div>

<script>
// Tabs
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    this.classList.add('active');
    document.getElementById('tab-' + this.dataset.tab).classList.add('active');
  });
});

// Close modal on backdrop click
document.getElementById('postModal').addEventListener('click', e => { if(e.target===document.getElementById('postModal')) document.getElementById('postModal').classList.remove('open'); });

// Filter
function filterJobs() {
  const q = (document.getElementById('jobSearch').value || '').toLowerCase();
  const jt = document.getElementById('jobTypeFilter').value;
  const loc = document.getElementById('locFilter').value;
  let visible = 0;
  document.querySelectorAll('.job-card').forEach(card => {
    const t = card.dataset.title || '';
    const co = card.dataset.company || '';
    const cardType = card.dataset.type || '';
    const cardLoc = card.dataset.location || '';
    const show = (!q || t.includes(q) || co.includes(q)) && (!jt || cardType === jt) && (!loc || cardLoc === loc);
    card.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  document.getElementById('jobCount').textContent = visible + ' positions available';
  document.getElementById('noJobsMsg').style.display = visible === 0 ? '' : 'none';
}

// Apply to job via AJAX
function applyToJob(jobId, btn) {
  if (!jobId || !btn || btn.disabled) return;
  btn.disabled = true;
  const orig = btn.innerHTML;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
  fetch('apply_job_message.php?job_id=' + jobId, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => { const ct = r.headers.get('content-type'); return (ct && ct.includes('application/json')) ? r.json() : { success: r.ok }; })
    .then(data => {
      if (data.success) {
        btn.classList.add('applied'); btn.innerHTML = '<i class="fas fa-check-circle"></i> Applied';
        btn.disabled = true;
        const rId = data.receiver_id || '';
        setTimeout(() => { window.location.href = rId ? 'al_messages.php?to=' + rId : 'al_messages.php'; }, 1200);
      } else {
        btn.disabled = false; btn.innerHTML = orig;
        alert(data.message || 'Could not submit application. Please try again.');
      }
    })
    .catch(() => { btn.disabled = false; btn.innerHTML = orig; alert('Network error. Please try again.'); });
}

// Charts
window.addEventListener('DOMContentLoaded', () => {
  const iEl = document.getElementById('industryChart');
  if (iEl) {
    new Chart(iEl, {
      type: 'bar',
      data: {
        labels: <?php echo json_encode(array_column($industry_trends,'industry')); ?>,
        datasets: [{ label: 'Jobs', data: <?php echo json_encode(array_column($industry_trends,'job_count')); ?>,
          backgroundColor: ['rgba(27,94,53,.75)','rgba(45,122,79,.75)','rgba(61,153,102,.75)','rgba(184,146,42,.75)','rgba(37,99,235,.75)'],
          borderRadius: 6, borderWidth: 0 }]
      },
      options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.05)' } }, x: { grid: { display: false } } } }
    });
  }
  const sEl = document.getElementById('skillsChart');
  if (sEl) {
    const sLabels = <?php echo json_encode(array_column($top_skills,'skill_name')); ?>;
    const sData = <?php echo json_encode(array_column($top_skills,'demand_count')); ?>;
    new Chart(sEl, {
      type: 'doughnut',
      data: { labels: sLabels.length ? sLabels : ['No data'], datasets: [{ data: sData.length ? sData : [1], backgroundColor: ['#1b5e35','#2d7a4f','#3d9966','#b8922a','#e0b84a','#2563eb','#7c3aed','#dc2626','#0891b2','#6b7280'], borderWidth: 0 }] },
      options: { responsive: true, plugins: { legend: { position: 'right', labels: { boxWidth: 10, font: { size: 11 } } } } }
    });
  }
});
</script>
<?php if (file_exists(__DIR__ . '/al_footer_universal.php')) include 'al_footer_universal.php'; ?>
</body>
</html>
