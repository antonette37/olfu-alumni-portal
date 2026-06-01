<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: al_login.php');
    exit();
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf = htmlspecialchars((string) $_SESSION['admin_csrf'], ENT_QUOTES, 'UTF-8');

$kind = isset($_GET['kind']) && $_GET['kind'] === 'story' ? 'story' : 'job';
$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: ad_content_management.php');
    exit();
}

require_once __DIR__ . '/db_config.php';
$conn = getDBConnection();

$row = null;
if ($kind === 'job') {
    $st = $conn->prepare('SELECT * FROM jobs WHERE id = ? LIMIT 1');
    if ($st) {
        $st->bind_param('i', $id);
        $st->execute();
        $res = $st->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $st->close();
    }
} else {
    $st = $conn->prepare('SELECT * FROM alumni_success_stories WHERE id = ? LIMIT 1');
    if ($st) {
        $st->bind_param('i', $id);
        $st->execute();
        $res = $st->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $st->close();
    }
}

$conn->close();

if ($row === null) {
    header('Location: ad_content_management.php?error=notfound');
    exit();
}

$isPendingJob = $kind === 'job' && (($row['status'] ?? '') === 'pending');
$isDraftStory = $kind === 'story' && (($row['status'] ?? '') === 'draft');
$showActions = $isPendingJob || $isDraftStory;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Preview — Content Management</title>
  <link rel="icon" href="olfulogo.png" type="image/png">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>body{font-family:Sora,sans-serif;background:#faf7f7;color:#1a0a0a}</style>
</head>
<body class="min-h-screen p-6">
  <div class="max-w-3xl mx-auto">
    <a href="ad_content_management.php" class="inline-flex items-center gap-2 text-sm font-semibold text-[#8b0000] hover:underline mb-6">
      <i class="fas fa-arrow-left"></i> Back to Content Management
    </a>

    <?php if ($kind === 'job'): ?>
      <div class="bg-white rounded-xl border border-[#e8dada] shadow-sm p-6">
        <span class="text-xs font-bold uppercase tracking-wider text-gray-400">Job post</span>
        <h1 class="text-2xl font-bold text-gray-900 mt-1"><?= htmlspecialchars((string) ($row['title'] ?? '')) ?></h1>
        <p class="text-gray-600 mt-2"><strong>Company:</strong> <?= htmlspecialchars((string) ($row['company'] ?? '—')) ?></p>
        <p class="text-gray-600"><strong>Location:</strong> <?= htmlspecialchars((string) ($row['location'] ?? '—')) ?></p>
        <p class="text-gray-600"><strong>Type:</strong> <?= htmlspecialchars((string) ($row['job_type'] ?? '—')) ?></p>
        <p class="text-gray-600"><strong>Salary:</strong> <?= htmlspecialchars((string) ($row['salary_range'] ?? '—')) ?></p>
        <p class="text-sm text-gray-500 mt-2">Status: <span class="font-semibold"><?= htmlspecialchars((string) ($row['status'] ?? '')) ?></span></p>
        <hr class="my-4 border-gray-100">
        <h2 class="text-sm font-bold text-gray-500 uppercase mb-2">Description</h2>
        <div class="text-gray-700 whitespace-pre-wrap text-sm leading-relaxed"><?= nl2br(htmlspecialchars((string) ($row['description'] ?? ''))) ?></div>
        <h2 class="text-sm font-bold text-gray-500 uppercase mt-6 mb-2">Requirements</h2>
        <div class="text-gray-700 whitespace-pre-wrap text-sm leading-relaxed"><?= nl2br(htmlspecialchars((string) ($row['requirements'] ?? ''))) ?></div>
      </div>
    <?php else: ?>
      <div class="bg-white rounded-xl border border-[#e8dada] shadow-sm p-6">
        <span class="text-xs font-bold uppercase tracking-wider text-gray-400">Success story</span>
        <h1 class="text-2xl font-bold text-gray-900 mt-1"><?= htmlspecialchars((string) ($row['title'] ?? '')) ?></h1>
        <p class="text-gray-600 mt-2"><?= htmlspecialchars((string) ($row['author_name'] ?? '—')) ?> · <?= htmlspecialchars((string) ($row['author_program'] ?? '')) ?> · Class of <?= (int) ($row['author_year'] ?? 0) ?></p>
        <p class="text-sm text-gray-500 mt-2">Status: <span class="font-semibold"><?= htmlspecialchars((string) ($row['status'] ?? '')) ?></span></p>
        <hr class="my-4 border-gray-100">
        <div class="text-gray-700 whitespace-pre-wrap text-sm leading-relaxed"><?= nl2br(htmlspecialchars((string) ($row['content'] ?? ''))) ?></div>
      </div>
    <?php endif; ?>

    <?php if ($showActions): ?>
      <div class="flex flex-wrap gap-3 mt-6">
        <form method="post" action="ad_moderate_content.php" class="inline" onsubmit="return confirm('Approve this <?= $kind === 'job' ? 'job' : 'story' ?>?');">
          <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
          <input type="hidden" name="kind" value="<?= htmlspecialchars($kind, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="approve">
          <input type="hidden" name="id" value="<?= $id ?>">
          <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-semibold hover:bg-emerald-700">
            <i class="fas fa-check"></i> Approve
          </button>
        </form>
        <form method="post" action="ad_moderate_content.php" class="inline" onsubmit="return confirm('Reject this <?= $kind === 'job' ? 'job' : 'story' ?>?');">
          <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
          <input type="hidden" name="kind" value="<?= htmlspecialchars($kind, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="action" value="reject">
          <input type="hidden" name="id" value="<?= $id ?>">
          <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-red-200 bg-red-50 text-red-800 text-sm font-semibold hover:bg-red-100">
            <i class="fas fa-times"></i> Reject
          </button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
