<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../db_config.php';

try {
    $conn = getDBConnection();
    
    // Get active alumni count
    $alumni_count = 0;
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM itcp WHERE status IN ('active', 'approved')");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $alumni_count = $result ? (int)$result['count'] : 0;
        $stmt->close();
    }
    
    // Get events count (this year)
    $events_count = 0;
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM events WHERE YEAR(event_date) = YEAR(CURDATE()) AND status = 'published'");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $events_count = $result ? (int)$result['count'] : 0;
        $stmt->close();
    }
    
    // Get job opportunities count
    $jobs_count = 0;
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_listings WHERE status = 'active'");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $jobs_count = $result ? (int)$result['count'] : 0;
        $stmt->close();
    } else {
        // Try alternative table name
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM job_postings WHERE status = 'active'");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $jobs_count = $result ? (int)$result['count'] : 0;
            $stmt->close();
        }
    }
    
    // Get gallery images (same logic as al_gallery.php)
    $gallery_images = [];
    
    // Check if is_highlight column exists (same as al_gallery.php)
    $highlight_check = $conn->query("SHOW COLUMNS FROM gallery_images LIKE 'is_highlight'");
    $has_highlight_column = $highlight_check && $highlight_check->num_rows > 0;
    
    // If column doesn't exist, add it (same as al_gallery.php)
    if (!$has_highlight_column) {
        $conn->query("ALTER TABLE gallery_images ADD COLUMN is_highlight TINYINT(1) DEFAULT 0 AFTER status");
        $has_highlight_column = true;
    }
    
    // Get gallery images - prioritize highlighted images first (same as al_gallery.php)
    // Use the same query structure as al_gallery.php
    if ($has_highlight_column) {
        $sql = "SELECT gi.file_path 
                FROM gallery_images gi 
                INNER JOIN gallery_albums ga ON gi.album_id = ga.id 
                WHERE (gi.status IS NULL OR gi.status = 'active' OR gi.status = '') 
                AND (ga.status = 'active' OR ga.status IS NULL OR ga.status = '')
                ORDER BY gi.is_highlight DESC, gi.created_at DESC
                LIMIT 12";
    } else {
        $sql = "SELECT gi.file_path 
                FROM gallery_images gi 
                INNER JOIN gallery_albums ga ON gi.album_id = ga.id 
                WHERE (gi.status IS NULL OR gi.status = 'active' OR gi.status = '') 
                AND (ga.status = 'active' OR ga.status IS NULL OR ga.status = '')
                ORDER BY gi.created_at DESC
                LIMIT 12";
    }
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['file_path'])) {
                $file_path = trim($row['file_path']);
                if (!empty($file_path)) {
                    $gallery_images[] = 'serve_gallery_image.php?img=' . urlencode($file_path);
                }
            }
        }
        $stmt->close();
    }
    
    // If still no images, try without any status filters (fallback like al_gallery.php)
    if (empty($gallery_images)) {
        if ($has_highlight_column) {
            $sql2 = "SELECT gi.file_path 
                     FROM gallery_images gi 
                     INNER JOIN gallery_albums ga ON gi.album_id = ga.id 
                     ORDER BY gi.is_highlight DESC, gi.created_at DESC
                     LIMIT 12";
        } else {
            $sql2 = "SELECT gi.file_path 
                     FROM gallery_images gi 
                     INNER JOIN gallery_albums ga ON gi.album_id = ga.id 
                     ORDER BY gi.created_at DESC
                     LIMIT 12";
        }
        
        $stmt2 = $conn->prepare($sql2);
        if ($stmt2) {
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            while ($row = $result2->fetch_assoc()) {
                if (!empty($row['file_path'])) {
                    $file_path = trim($row['file_path']);
                    if (!empty($file_path)) {
                        $gallery_images[] = 'serve_gallery_image.php?img=' . urlencode($file_path);
                    }
                }
            }
            $stmt2->close();
        }
    }
    
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'alumni_count' => $alumni_count,
            'events_count' => $events_count,
            'jobs_count' => $jobs_count
        ],
        'gallery_images' => $gallery_images
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage(),
        'stats' => [
            'alumni_count' => 0,
            'events_count' => 0,
            'jobs_count' => 0
        ],
        'gallery_images' => []
    ]);
}
?>

