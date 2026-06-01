<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: al_login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ad_content_management.php');
    exit();
}

if (
    empty($_POST['csrf_token'])
    || empty($_SESSION['admin_csrf'])
    || !hash_equals((string) $_SESSION['admin_csrf'], (string) $_POST['csrf_token'])
) {
    header('Location: ad_content_management.php?error=csrf');
    exit();
}

$kind = (string) ($_POST['kind'] ?? '');
$action = (string) ($_POST['action'] ?? '');
$id = (int) ($_POST['id'] ?? 0);

if ($id <= 0 || !in_array($action, ['approve', 'reject'], true) || !in_array($kind, ['job', 'story'], true)) {
    header('Location: ad_content_management.php?error=invalid');
    exit();
}

require_once __DIR__ . '/db_config.php';
$conn = getDBConnection();

$ok = false;

if ($kind === 'job') {
    if ($action === 'approve') {
        $st = $conn->prepare("UPDATE jobs SET status = 'active' WHERE id = ? AND status = 'pending'");
        if ($st) {
            $st->bind_param('i', $id);
            $ok = $st->execute() && $st->affected_rows > 0;
            $st->close();
        }
    } else {
        $st = $conn->prepare("UPDATE jobs SET status = 'rejected' WHERE id = ? AND status = 'pending'");
        if ($st) {
            $st->bind_param('i', $id);
            if ($st->execute() && $st->affected_rows > 0) {
                $ok = true;
            }
            $st->close();
        }
        if (!$ok) {
            $st2 = $conn->prepare("UPDATE jobs SET archived = 1 WHERE id = ? AND status = 'pending'");
            if ($st2) {
                $st2->bind_param('i', $id);
                if ($st2->execute() && $st2->affected_rows > 0) {
                    $ok = true;
                }
                $st2->close();
            }
        }
    }
} else {
    if ($action === 'approve') {
        $st = $conn->prepare("UPDATE alumni_success_stories SET status = 'published' WHERE id = ? AND status = 'draft'");
        if ($st) {
            $st->bind_param('i', $id);
            $ok = $st->execute() && $st->affected_rows > 0;
            $st->close();
        }
    } else {
        $st = $conn->prepare("UPDATE alumni_success_stories SET status = 'archived' WHERE id = ? AND status = 'draft'");
        if ($st) {
            $st->bind_param('i', $id);
            $ok = $st->execute() && $st->affected_rows > 0;
            $st->close();
        }
    }
}

$conn->close();

$q = $ok ? 'ok=1' : 'error=update';
$frag = $kind === 'job' ? '&type=job' : '&type=story';
$act = $action === 'approve' ? '&action=approved' : '&action=rejected';

header('Location: ad_content_management.php?' . $q . $frag . $act);
exit();
