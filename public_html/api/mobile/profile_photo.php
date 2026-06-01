<?php
/**
 * Profile photo for mobile — profile file or ID card fallback from registration.
 * GET ?user_id=N  |  GET ?img=filename
 */
error_reporting(0);
ini_set('display_errors', 0);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Cache-Control: public, max-age=86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$root = dirname(__DIR__, 2);
require_once $root . '/db_config.php';
require_once __DIR__ . '/includes/mobile_photo_file.php';

$compat = $root . '/includes/mysqli_compat.php';
if (is_file($compat)) {
    require_once $compat;
}

$userId = (int) ($_GET['user_id'] ?? 0);
$imgParam = isset($_GET['img']) ? trim((string) $_GET['img']) : '';
$conn = null;
$file_path = false;

try {
    $conn = getDBConnection();

    if ($userId > 0) {
        $file_path = mobile_photo_resolve_path_for_user($userId, null, $conn);
    } elseif ($imgParam !== '' && strcasecmp($imgParam, 'default-avatar.png') !== 0) {
        $file_path = mobile_photo_resolve_path($imgParam);
        if ($file_path === false) {
            $file_path = mobile_photo_resolve_storage_name($imgParam);
        }
    }

    if ($conn) {
        $conn->close();
    }
} catch (Throwable $e) {
    if ($conn) {
        $conn->close();
    }
}

if ($file_path === false || !is_file($file_path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Not found');
}

$ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
$mime_map = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'bmp' => 'image/bmp',
];
$mime_type = $mime_map[$ext] ?? 'image/jpeg';

header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($file_path));
readfile($file_path);
exit;
