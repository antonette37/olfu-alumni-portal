<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['ok' => false, 'error' => 'auth']);
    exit();
}

$kind = isset($_GET['kind']) && $_GET['kind'] === 'story' ? 'story' : 'job';
$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'invalid']);
    exit();
}

require_once __DIR__ . '/db_config.php';
$conn = getDBConnection();

$jobsHasUserId = false;
if ($kind === 'job') {
    $chk = $conn->query("SHOW COLUMNS FROM `jobs` LIKE 'user_id'");
    if ($chk && $chk->num_rows > 0) {
        $jobsHasUserId = true;
    }
}

$row = null;
if ($kind === 'job') {
    if ($jobsHasUserId) {
        $st = $conn->prepare(
            'SELECT j.*, i.firstname AS req_fn, i.lastname AS req_ln, i.email AS req_email, i.photo AS req_photo
             FROM jobs j
             LEFT JOIN itcp i ON i.id = j.user_id
             WHERE j.id = ? LIMIT 1'
        );
    } else {
        $st = $conn->prepare('SELECT * FROM jobs WHERE id = ? LIMIT 1');
    }
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
    echo json_encode(['ok' => false, 'error' => 'notfound']);
    exit();
}

$pending = ($kind === 'job' && (($row['status'] ?? '') === 'pending'))
    || ($kind === 'story' && (($row['status'] ?? '') === 'draft'));

if ($kind === 'job') {
    if ($jobsHasUserId) {
        $byName = trim((string) ($row['req_fn'] ?? '') . ' ' . (string) ($row['req_ln'] ?? ''));
        $byEmail = trim((string) ($row['req_email'] ?? ''));
        $uid = (int) ($row['user_id'] ?? 0);
        if ($byName !== '') {
            $postedBy = trim(preg_replace('/\s+/', ' ', $byName));
        } elseif ($byEmail !== '') {
            $postedBy = $byEmail;
        } elseif ($uid > 0) {
            $postedBy = 'Alumni #' . $uid;
        } else {
            $postedBy = 'Unknown';
        }
    } else {
        $postedBy = '—';
    }
    $rp = trim((string) ($row['req_photo'] ?? ''));
    $postedByPhoto = '';
    if ($jobsHasUserId && $rp !== '') {
        $postedByPhoto = stripos($rp, 'http') === 0
            ? $rp
            : ('serve_profile_image.php?img=' . rawurlencode(basename($rp)));
    }
    $payload = [
        'ok' => true,
        'kind' => 'job',
        'pending' => $pending,
        'title' => (string) ($row['title'] ?? ''),
        'posted_by' => $postedBy,
        'posted_by_photo' => $postedByPhoto,
        'company' => (string) ($row['company'] ?? ''),
        'location' => (string) ($row['location'] ?? ''),
        'job_type' => (string) ($row['job_type'] ?? ''),
        'salary_range' => (string) ($row['salary_range'] ?? ''),
        'description' => (string) ($row['description'] ?? ''),
        'requirements' => (string) ($row['requirements'] ?? ''),
        'status' => (string) ($row['status'] ?? ''),
    ];
} else {
    $payload = [
        'ok' => true,
        'kind' => 'story',
        'pending' => $pending,
        'title' => (string) ($row['title'] ?? ''),
        'author_name' => (string) ($row['author_name'] ?? ''),
        'author_program' => (string) ($row['author_program'] ?? ''),
        'author_year' => (int) ($row['author_year'] ?? 0),
        'content' => (string) ($row['content'] ?? ''),
        'status' => (string) ($row['status'] ?? ''),
    ];
}

echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
exit();
