<?php
/**
 * Image Proxy for Announcement Images
 * Serves announcement images with proper headers to prevent 403 errors
 */

// Get the image filename from query parameter
$filename = isset($_GET['img']) ? basename($_GET['img']) : '';

// Security: Only allow image files
if (empty($filename) || !preg_match('/^[a-zA-Z0-9_\-\.]+\.(jpg|jpeg|png|gif|bmp|webp)$/i', $filename)) {
    http_response_code(404);
    exit('Image not found');
}

// Construct file path
$file_path = __DIR__ . '/uploads/announcements/' . $filename;

// Check if file exists
if (!file_exists($file_path)) {
    http_response_code(404);
    exit('Image not found');
}

// Get file info
$file_info = pathinfo($file_path);
$mime_type = 'image/jpeg'; // default

switch(strtolower($file_info['extension'])) {
    case 'jpg':
    case 'jpeg':
        $mime_type = 'image/jpeg';
        break;
    case 'png':
        $mime_type = 'image/png';
        break;
    case 'gif':
        $mime_type = 'image/gif';
        break;
    case 'webp':
        $mime_type = 'image/webp';
        break;
    case 'bmp':
        $mime_type = 'image/bmp';
        break;
}

// Set headers
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: public, max-age=31536000'); // Cache for 1 year
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');

// Read and output file
readfile($file_path);
exit;

