<?php
declare(strict_types=1);
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: al_login.php');
    exit();
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf = htmlspecialchars((string) ($_SESSION['admin_csrf'] ?? ''), ENT_QUOTES, 'UTF-8');

$_SESSION['admin_last_visit_content_management'] = date('Y-m-d H:i:s');

require_once __DIR__ . '/db_config.php';
$conn = getDBConnection();

$cmItcpPhotoUrl = static function (?string $photo): string {
    $p = trim((string) $photo);
    if ($p === '') {
        return '';
    }
    if (stripos($p, 'http') === 0) {
        return $p;
    }

    return 'serve_profile_image.php?img=' . rawurlencode(basename($p));
};

$jobs = [];
$stories = [];
$jobCount = 0;
$storyCount = 0;

$jobPendingSql = "status = 'pending'";
$jobPendingSqlJ = "j.status = 'pending'";
$archCol = $conn->query("SHOW COLUMNS FROM `jobs` LIKE 'archived'");
if ($archCol && $archCol->num_rows > 0) {
    $jobPendingSql .= ' AND (archived IS NULL OR archived = 0)';
    $jobPendingSqlJ .= ' AND (j.archived IS NULL OR j.archived = 0)';
}

$hasJobUserIdCol = false;
$uidColChk = $conn->query("SHOW COLUMNS FROM `jobs` LIKE 'user_id'");
if ($uidColChk && $uidColChk->num_rows > 0) {
    $hasJobUserIdCol = true;
}

$jobsTable = $conn->query("SHOW TABLES LIKE 'jobs'");
if ($jobsTable && $jobsTable->num_rows > 0) {
    $c = $conn->query("SELECT COUNT(*) c FROM jobs WHERE $jobPendingSql");
    if ($c) {
        $jobCount = (int) ($c->fetch_assoc()['c'] ?? 0);
    }
    if ($hasJobUserIdCol) {
        $q = $conn->query(
            "SELECT j.id, j.title, j.company, j.posted_date, j.user_id,
                TRIM(CONCAT(COALESCE(i.firstname,''),' ',COALESCE(i.lastname,''))) AS requester_name,
                i.email AS requester_email,
                i.photo AS requester_photo
            FROM jobs j
            LEFT JOIN itcp i ON i.id = j.user_id
            WHERE $jobPendingSqlJ
            ORDER BY j.posted_date DESC LIMIT 15"
        );
    } else {
        $q = $conn->query("SELECT id, title, company, posted_date FROM jobs WHERE $jobPendingSql ORDER BY posted_date DESC LIMIT 15");
    }
    if ($q) {
        while ($r = $q->fetch_assoc()) {
            if ($hasJobUserIdCol) {
                $disp = trim((string) ($r['requester_name'] ?? ''));
                if ($disp === '') {
                    $em = trim((string) ($r['requester_email'] ?? ''));
                    $disp = $em !== '' ? $em : (((int) ($r['user_id'] ?? 0)) > 0 ? 'Alumni #' . (int) $r['user_id'] : 'Unknown');
                }
                $r['requester_display'] = $disp;
                $r['requester_photo_url'] = $cmItcpPhotoUrl($r['requester_photo'] ?? null);
            } else {
                $r['requester_display'] = '—';
                $r['requester_photo_url'] = '';
            }
            $jobs[] = $r;
        }
    }
}

$storiesTable = $conn->query("SHOW TABLES LIKE 'alumni_success_stories'");
if ($storiesTable && $storiesTable->num_rows > 0) {
    $c = $conn->query("SELECT COUNT(*) c FROM alumni_success_stories WHERE status = 'draft'");
    if ($c) $storyCount = (int)($c->fetch_assoc()['c'] ?? 0);
    $q = $conn->query("SELECT id, title, author_name, created_at FROM alumni_success_stories WHERE status = 'draft' ORDER BY created_at DESC LIMIT 15");
    if ($q) { while ($r = $q->fetch_assoc()) $stories[] = $r; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Management - Admin Panel</title>
    <link rel="icon" href="olfulogo.png" type="image/png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --cr:#8b0000;--cr-dk:#600000;--cr-lt:#b91c1c;--cr-pale:#fef2f2;--border:#e8dada;--surface:#fff;--bg:#faf7f7;--muted:#7a6a6a;--ink:#1a0a0a;--shadow-sm:0 1px 4px rgba(139,0,0,.07),0 2px 12px rgba(139,0,0,.05);--r:14px; }
        body { background: var(--bg); font-family: 'Sora', sans-serif; color: var(--ink); }
        .page-title h1 { font-size: 1.5rem; font-weight: 700; color: var(--ink); line-height: 1.2; }
        .page-title p  { font-size: .82rem; color: var(--muted); margin-top: 2px; }
        .admin-card { background: var(--surface); border-radius: var(--r); border: 1px solid var(--border); box-shadow: var(--shadow-sm); overflow: hidden; }
        .admin-card-header { padding: .875rem 1.25rem; border-bottom: 1px solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; }
        .pending-table { width: 100%; border-collapse: collapse; }
        .pending-table th { padding: .625rem 1rem; text-align: left; font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em; color: #9ca3af; background: #f9fafb; border-bottom: 1px solid #e5e7eb; }
        .pending-table td { padding: .75rem 1rem; font-size: .8125rem; color: #374151; border-bottom: 1px solid #f9fafb; }
        .pending-table tr:hover td { background: var(--cr-pale); }
        .badge-pending { display: inline-flex; align-items: center; padding: .2rem .6rem; border-radius: 9999px; font-size: .68rem; font-weight: 700; background: #fef9c3; color: #a16207; }
        .cm-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; justify-content: flex-end; }
        .cm-btn { display: inline-flex; align-items: center; gap: 4px; padding: 5px 10px; border-radius: 8px; font-size: .72rem; font-weight: 600; border: none; cursor: pointer; text-decoration: none; transition: background .15s, color .15s; font-family: inherit; }
        .cm-btn-review { background: #f3f4f6; color: #374151; }
        .cm-btn-review:hover { background: #e5e7eb; }
        .cm-btn-approve { background: #059669; color: #fff; }
        .cm-btn-approve:hover { background: #047857; }
        .cm-btn-reject { background: #fff; color: #b91c1c; border: 1px solid #fecaca; }
        .cm-btn-reject:hover { background: #fef2f2; }
        .cm-flash { border-radius: var(--r); padding: 12px 16px; margin-bottom: 16px; font-size: .84rem; display: flex; align-items: flex-start; gap: 10px; }
        .cm-flash.ok { background: #f0fdf4; border: 1px solid #bbf7d0; color: #14532d; }
        .cm-flash.err { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; }
        .cm-modal-overlay {
            position: fixed; inset: 0; z-index: 200;
            background: rgba(26, 10, 10, 0.45);
            backdrop-filter: blur(4px);
            display: none; align-items: center; justify-content: center;
            padding: 16px;
            opacity: 0; transition: opacity 0.2s ease;
        }
        .cm-modal-overlay.is-open { display: flex; opacity: 1; }
        .cm-modal-panel {
            background: var(--surface); border-radius: var(--r);
            border: 1px solid var(--border); box-shadow: 0 24px 80px rgba(0,0,0,.18);
            width: 100%; max-width: 560px; max-height: min(88vh, 720px);
            display: flex; flex-direction: column;
            transform: translateY(8px); transition: transform 0.2s ease;
        }
        .cm-modal-overlay.is-open .cm-modal-panel { transform: translateY(0); }
        .cm-modal-head {
            flex-shrink: 0; padding: 14px 18px;
            border-bottom: 1px solid #f3f4f6;
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
        }
        .cm-modal-title { font-size: 0.95rem; font-weight: 700; color: var(--ink); }
        .cm-modal-close {
            width: 36px; height: 36px; border: none; border-radius: 10px;
            background: #f3f4f6; color: #4b5563; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background .15s;
        }
        .cm-modal-close:hover { background: #e5e7eb; }
        .cm-modal-body {
            padding: 16px 18px; overflow-y: auto; flex: 1;
            font-size: 0.84rem; color: #374151; line-height: 1.55;
        }
        .cm-modal-body h4 { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #9ca3af; margin: 14px 0 6px; }
        .cm-modal-body h4:first-child { margin-top: 0; }
        .cm-modal-loading { text-align: center; padding: 40px 20px; color: var(--muted); }
        .cm-modal-foot {
            flex-shrink: 0; padding: 12px 18px;
            border-top: 1px solid #f3f4f6;
            display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end;
        }
        .cm-modal-foot .cm-btn { padding: 8px 14px; font-size: 0.78rem; }
    </style>
</head>
<body>
<?php include __DIR__ . '/ad_header_universal.php'; ?>
<?php include __DIR__ . '/ad_sidebar_universal.php'; ?>
<main class="pt-20 ml-16 min-h-screen">
    <div class="max-w-7xl mx-auto px-0 mb-5">
        <div class="page-title">
            <h1>Content Management</h1>
            <p>Review pending submissions and navigate to content sections</p>
        </div>
    </div>
    <div class="max-w-7xl mx-auto px-4 pb-10">
        <?php
        $flashOk = isset($_GET['ok']) && (string) $_GET['ok'] === '1';
        $flashErr = isset($_GET['error']) ? (string) $_GET['error'] : '';
        $modType = isset($_GET['type']) ? (string) $_GET['type'] : '';
        $modAction = isset($_GET['action']) ? (string) $_GET['action'] : '';
        ?>
        <?php if ($flashOk): ?>
            <div class="cm-flash ok" role="status"><i class="fas fa-circle-check mt-0.5"></i><span><?php
                $label = $modType === 'story' ? 'Success story' : 'Job post';
                echo $modAction === 'approved' ? ($label . ' approved.') : ($label . ' rejected.');
            ?></span></div>
        <?php elseif ($flashErr !== ''): ?>
            <div class="cm-flash err" role="alert"><i class="fas fa-circle-exclamation mt-0.5"></i><span><?php
                $errMsg = 'Something went wrong.';
                if ($flashErr === 'csrf') {
                    $errMsg = 'Security check failed. Refresh the page and try again.';
                } elseif ($flashErr === 'invalid') {
                    $errMsg = 'Invalid request.';
                } elseif ($flashErr === 'update') {
                    $errMsg = 'Could not update that item. It may have already been processed.';
                } elseif ($flashErr === 'notfound') {
                    $errMsg = 'That item was not found.';
                }
                echo $errMsg;
            ?></span></div>
        <?php endif; ?>

        <h2 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-3">Pending Review</h2>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="admin-card">
                <div class="admin-card-header">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 bg-amber-100 rounded-lg flex items-center justify-center"><i class="fas fa-briefcase text-amber-600 text-xs"></i></div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-800">Pending Job Posts</h3>
                            <p class="text-xs text-gray-500">Awaiting approval</p>
                        </div>
                    </div>
                    <?php if ($jobCount > 0): ?><span class="badge-pending"><i class="fas fa-clock mr-1 text-xs"></i><?= $jobCount ?> pending</span><?php endif; ?>
                </div>
                <table class="pending-table">
                    <thead>
                        <tr>
                            <th>Job Title</th>
                            <th>Company</th>
                            <th>Requested by</th>
                            <th>Posted</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($jobs)): ?>
                            <tr><td colspan="5" class="text-center py-10 text-gray-400">All caught up! No pending job posts.</td></tr>
                        <?php else: foreach ($jobs as $j):
                            $jid = (int) ($j['id'] ?? 0);
                            ?>
                            <tr>
                                <td><div class="font-medium text-gray-800"><?= htmlspecialchars((string) ($j['title'] ?? '')) ?></div></td>
                                <td class="text-gray-600"><?= htmlspecialchars((string) ($j['company'] ?? '—')) ?></td>
                                <td class="text-gray-700">
                                    <div class="flex items-center gap-2">
                                        <?php if (!empty($j['requester_photo_url'])): ?>
                                            <img src="<?= htmlspecialchars((string) $j['requester_photo_url'], ENT_QUOTES, 'UTF-8') ?>" alt="" width="36" height="36" class="rounded-full object-cover border border-gray-200 flex-shrink-0" loading="lazy" />
                                        <?php endif; ?>
                                        <span><?= htmlspecialchars((string) ($j['requester_display'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>
                                </td>
                                <td class="text-gray-500 whitespace-nowrap"><?= !empty($j['posted_date']) ? date('M d, Y', strtotime((string) $j['posted_date'])) : '—' ?></td>
                                <td>
                                    <div class="cm-actions">
                                        <button type="button" class="cm-btn cm-btn-review js-cm-open-review" data-kind="job" data-id="<?= $jid ?>"><i class="fas fa-eye"></i> Review</button>
                                        <form method="post" action="ad_moderate_content.php" class="inline" onsubmit="return confirm('Approve this job post?');">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="kind" value="job">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="id" value="<?= $jid ?>">
                                            <button type="submit" class="cm-btn cm-btn-approve"><i class="fas fa-check"></i> Approve</button>
                                        </form>
                                        <form method="post" action="ad_moderate_content.php" class="inline" onsubmit="return confirm('Reject this job post?');">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="kind" value="job">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="id" value="<?= $jid ?>">
                                            <button type="submit" class="cm-btn cm-btn-reject"><i class="fas fa-times"></i> Reject</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="admin-card">
                <div class="admin-card-header">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 bg-yellow-100 rounded-lg flex items-center justify-center"><i class="fas fa-star text-yellow-600 text-xs"></i></div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-800">Pending Success Stories</h3>
                            <p class="text-xs text-gray-500">Awaiting approval</p>
                        </div>
                    </div>
                    <?php if ($storyCount > 0): ?><span class="badge-pending"><i class="fas fa-clock mr-1 text-xs"></i><?= $storyCount ?> pending</span><?php endif; ?>
                </div>
                <table class="pending-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Submitted</th>
                            <th style="text-align:right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($stories)): ?>
                            <tr><td colspan="4" class="text-center py-10 text-gray-400">All caught up! No pending stories.</td></tr>
                        <?php else: foreach ($stories as $s):
                            $sid = (int) ($s['id'] ?? 0);
                            ?>
                            <tr>
                                <td><div class="font-medium text-gray-800"><?= htmlspecialchars((string) ($s['title'] ?? '')) ?></div></td>
                                <td class="text-gray-600"><?= htmlspecialchars((string) ($s['author_name'] ?? '—')) ?></td>
                                <td class="text-gray-500 whitespace-nowrap"><?= !empty($s['created_at']) ? date('M d, Y', strtotime((string) $s['created_at'])) : '—' ?></td>
                                <td>
                                    <div class="cm-actions">
                                        <button type="button" class="cm-btn cm-btn-review js-cm-open-review" data-kind="story" data-id="<?= $sid ?>"><i class="fas fa-eye"></i> Review</button>
                                        <form method="post" action="ad_moderate_content.php" class="inline" onsubmit="return confirm('Publish this success story?');">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="kind" value="story">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="id" value="<?= $sid ?>">
                                            <button type="submit" class="cm-btn cm-btn-approve"><i class="fas fa-check"></i> Approve</button>
                                        </form>
                                        <form method="post" action="ad_moderate_content.php" class="inline" onsubmit="return confirm('Reject this success story?');">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                            <input type="hidden" name="kind" value="story">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="id" value="<?= $sid ?>">
                                            <button type="submit" class="cm-btn cm-btn-reject"><i class="fas fa-times"></i> Reject</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="cmReviewModal" class="cm-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="cmModalTitle" aria-hidden="true" hidden>
        <div class="cm-modal-panel">
            <div class="cm-modal-head">
                <span id="cmModalTitle" class="cm-modal-title">Review</span>
                <button type="button" class="cm-modal-close" id="cmModalClose" aria-label="Close"><i class="fas fa-times"></i></button>
            </div>
            <div class="cm-modal-body" id="cmModalBody">
                <div class="cm-modal-loading" id="cmModalLoading"><i class="fas fa-spinner fa-spin"></i> Loading…</div>
                <div id="cmModalContent" style="display:none;"></div>
            </div>
            <div class="cm-modal-foot" id="cmModalFoot" style="display:none;">
                <form method="post" action="ad_moderate_content.php" class="inline" id="cmFormApprove" onsubmit="return confirm(document.getElementById('cmApproveMsg').value);">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="kind" id="cmApproveKind" value="">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="id" id="cmApproveId" value="">
                    <input type="hidden" id="cmApproveMsg" value="">
                    <button type="submit" class="cm-btn cm-btn-approve"><i class="fas fa-check"></i> Approve</button>
                </form>
                <form method="post" action="ad_moderate_content.php" class="inline" id="cmFormReject" onsubmit="return confirm(document.getElementById('cmRejectMsg').value);">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="kind" id="cmRejectKind" value="">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="id" id="cmRejectId" value="">
                    <input type="hidden" id="cmRejectMsg" value="">
                    <button type="submit" class="cm-btn cm-btn-reject"><i class="fas fa-times"></i> Reject</button>
                </form>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const overlay = document.getElementById('cmReviewModal');
        const bodyEl = document.getElementById('cmModalBody');
        const loadingEl = document.getElementById('cmModalLoading');
        const contentEl = document.getElementById('cmModalContent');
        const footEl = document.getElementById('cmModalFoot');
        const titleEl = document.getElementById('cmModalTitle');
        const closeBtn = document.getElementById('cmModalClose');

        function esc(s) {
            return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
        function nl2brEsc(s) {
            return esc(s).replace(/\n/g, '<br>');
        }

        function openModal() {
            overlay.hidden = false;
            overlay.classList.add('is-open');
            overlay.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
        }
        function closeModal() {
            overlay.classList.remove('is-open');
            overlay.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            setTimeout(function () { overlay.hidden = true; }, 200);
        }

        function loadPreview(kind, id) {
            loadingEl.style.display = 'block';
            contentEl.style.display = 'none';
            contentEl.innerHTML = '';
            footEl.style.display = 'none';
            titleEl.textContent = 'Review';
            openModal();

            fetch('ad_content_preview_data.php?kind=' + encodeURIComponent(kind) + '&id=' + encodeURIComponent(id), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    loadingEl.style.display = 'none';
                    if (!data || !data.ok) {
                        contentEl.innerHTML = '<p class="text-red-700">' + esc(data && data.error === 'notfound' ? 'Item not found.' : 'Could not load preview.') + '</p>';
                        contentEl.style.display = 'block';
                        return;
                    }

                    let html = '';
                    if (data.kind === 'job') {
                        titleEl.textContent = 'Review job post';
                        var byPhoto = (data.posted_by_photo && String(data.posted_by_photo).trim())
                            ? '<img src="' + esc(String(data.posted_by_photo).trim()) + '" alt="" width="32" height="32" class="inline-block rounded-full object-cover border border-gray-200 align-middle mr-2" loading="lazy" />'
                            : '';
                        html =
                            '<p><span class="text-xs font-bold uppercase tracking-wider text-gray-400">Job post</span></p>' +
                            '<p class="text-lg font-bold text-gray-900 mt-1">' + esc(data.title) + '</p>' +
                            '<p class="mt-2 flex items-center flex-wrap gap-2"><strong>Requested by:</strong> ' + byPhoto + '<span>' + esc(data.posted_by) + '</span></p>' +
                            '<p class="mt-2"><strong>Company:</strong> ' + esc(data.company) + '</p>' +
                            '<p><strong>Location:</strong> ' + esc(data.location) + '</p>' +
                            '<p><strong>Type:</strong> ' + esc(data.job_type) + '</p>' +
                            '<p><strong>Salary:</strong> ' + esc(data.salary_range) + '</p>' +
                            '<p class="text-sm text-gray-500 mt-2">Status: <strong>' + esc(data.status) + '</strong></p>' +
                            '<h4>Description</h4><div>' + nl2brEsc(data.description) + '</div>' +
                            '<h4>Requirements</h4><div>' + nl2brEsc(data.requirements) + '</div>';
                    } else {
                        titleEl.textContent = 'Review success story';
                        html =
                            '<p><span class="text-xs font-bold uppercase tracking-wider text-gray-400">Success story</span></p>' +
                            '<p class="text-lg font-bold text-gray-900 mt-1">' + esc(data.title) + '</p>' +
                            '<p class="text-gray-600 mt-2">' + esc(data.author_name) + ' · ' + esc(data.author_program) + ' · Class of ' + esc(String(data.author_year)) + '</p>' +
                            '<p class="text-sm text-gray-500 mt-2">Status: <strong>' + esc(data.status) + '</strong></p>' +
                            '<h4>Content</h4><div>' + nl2brEsc(data.content) + '</div>';
                    }
                    contentEl.innerHTML = html;
                    contentEl.style.display = 'block';

                    if (data.pending) {
                        document.getElementById('cmApproveKind').value = data.kind;
                        document.getElementById('cmApproveId').value = String(id);
                        document.getElementById('cmRejectKind').value = data.kind;
                        document.getElementById('cmRejectId').value = String(id);
                        document.getElementById('cmApproveMsg').value = data.kind === 'job' ? 'Approve this job post?' : 'Publish this success story?';
                        document.getElementById('cmRejectMsg').value = data.kind === 'job' ? 'Reject this job post?' : 'Reject this success story?';
                        footEl.style.display = 'flex';
                    }
                })
                .catch(function () {
                    loadingEl.style.display = 'none';
                    contentEl.innerHTML = '<p class="text-red-700">Could not load preview.</p>';
                    contentEl.style.display = 'block';
                });
        }

        document.querySelectorAll('.js-cm-open-review').forEach(function (btn) {
            btn.addEventListener('click', function () {
                loadPreview(btn.getAttribute('data-kind'), btn.getAttribute('data-id'));
            });
        });

        closeBtn.addEventListener('click', closeModal);
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('is-open')) closeModal();
        });
    })();
    </script>
</main>
</body>
</html>
