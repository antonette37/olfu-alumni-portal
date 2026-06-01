<?php
/**
 * One-time backfill: set id_image_back for alumni_registration rows that have
 * id_image (front) but null id_image_back, by finding the matching id_back_* file
 * that was uploaded at the same time (same Ymd_His in filename).
 *
 * Run once in browser after adding the id_image_back column. Remove after use.
 */
session_start();
require_once __DIR__ . '/db_config.php';

header('Content-Type: text/html; charset=utf-8');

$conn = getDBConnection();

// Ensure column exists
$chk = $conn->query("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alumni_registration' AND COLUMN_NAME = 'id_image_back'");
$hasCol = $chk && $chk->num_rows > 0;
$hasCol = $hasCol && ($r = $chk->fetch_assoc()) && isset($r['c']) && (int)$r['c'] > 0;
if ($chk) $chk->close();

if (!$hasCol) {
    echo '<p>Column id_image_back does not exist. Run <a href="fix_alumni_registration_id_image_back.php">fix_alumni_registration_id_image_back.php</a> first.</p>';
    $conn->close();
    exit;
}

$idsDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ids';
if (!is_dir($idsDir)) {
    echo '<p>Uploads directory not found: uploads/ids/</p>';
    $conn->close();
    exit;
}

// Rows that have front but no back
$res = $conn->query("SELECT id, student_number, name, id_image FROM alumni_registration WHERE (id_image_back IS NULL OR TRIM(COALESCE(id_image_back,'')) = '') AND id_image IS NOT NULL AND TRIM(id_image) != ''");
if (!$res || $res->num_rows === 0) {
    echo '<p>No rows to backfill (all rows already have id_image_back or no id_image).</p>';
    $conn->close();
    exit;
}

$updated = 0;
$notFound = [];
while ($row = $res->fetch_assoc()) {
    $front = trim($row['id_image']);
    $frontPath = $idsDir . DIRECTORY_SEPARATOR . $front;
    // Front filename: id_20260222_170017_fb4ef14f62e2.jpg → timestamp part 20260222_170017
    if (!preg_match('/^id_(\d{8}_\d{6})_/', $front, $m)) {
        $notFound[] = ['id' => $row['id'], 'reason' => 'front filename format'];
        continue;
    }
    $ts = $m[1]; // e.g. 20260222_170017
    $backPattern = $idsDir . DIRECTORY_SEPARATOR . 'id_back_' . $ts . '_*.*';
    $backFiles = glob($backPattern);
    if (empty($backFiles)) {
        $notFound[] = ['id' => $row['id'], 'student_number' => $row['student_number'], 'reason' => 'no id_back_' . $ts . '_* file'];
        continue;
    }
    // Use first match (or closest by mtime to front file)
    $backPath = $backFiles[0];
    if (count($backFiles) > 1 && file_exists($frontPath)) {
        $frontMtime = filemtime($frontPath);
        $best = $backFiles[0];
        $bestDiff = abs(filemtime($best) - $frontMtime);
        foreach ($backFiles as $f) {
            $d = abs(filemtime($f) - $frontMtime);
            if ($d < $bestDiff) { $best = $f; $bestDiff = $d; }
        }
        $backPath = $best;
    }
    $backFilename = basename($backPath);
    $escBack = $conn->real_escape_string($backFilename);
    $id = (int)$row['id'];
    if ($conn->query("UPDATE alumni_registration SET id_image_back = '" . $escBack . "' WHERE id = " . $id)) {
        $updated++;
    }
}
$res->close();
$conn->close();

echo '<p><strong>Backfill done.</strong> Updated ' . $updated . ' row(s).</p>';
if (!empty($notFound)) {
    echo '<p>No matching back file for ' . count($notFound) . ' row(s):</p><ul>';
    foreach ($notFound as $n) {
        echo '<li>id=' . (isset($n['id']) ? $n['id'] : '') . ' ' . (isset($n['student_number']) ? $n['student_number'] : '') . ' – ' . htmlspecialchars($n['reason']) . '</li>';
    }
    echo '</ul>';
}
echo '<p><a href="check_alumni_registration_id_back.php">Check database</a> &nbsp; <a href="ad_user_management.php">User Management</a></p>';
