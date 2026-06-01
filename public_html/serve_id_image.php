<?php
/**
 * Image Proxy for ID Images
 * Serves ID images from uploads/ids/ and uploads/id_cards/ directories with proper headers
 */

// Suppress errors to prevent output before headers
error_reporting(0);
ini_set('display_errors', 0);

// Get the image filename from query parameter
$raw_filename = isset($_GET['img']) ? $_GET['img'] : '';

if (empty($raw_filename)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('Image not found: No filename provided');
}

// Decode URL encoding properly
$filename = urldecode($raw_filename);
// Get just the filename, remove any path
$filename = basename($filename);
// Trim whitespace
$filename = trim($filename);

// Security: Only allow image files
if (empty($filename) || !preg_match('/^[a-zA-Z0-9_\-\.\s]+\.(jpg|jpeg|png|gif|bmp|webp)$/i', $filename)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('Image not found: Invalid filename');
}

// Try multiple possible locations for ID images
$possible_dirs = [
    __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ids',
    __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'id_cards',
    __DIR__ . DIRECTORY_SEPARATOR . 'uploads'
];

$file_path = null;
$file_exists = false;

// Try to find the file in any of the possible directories
foreach ($possible_dirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }
    
    $test_path = $dir . DIRECTORY_SEPARATOR . $filename;
    
    // Method 1: Direct check
    if (file_exists($test_path) && is_file($test_path)) {
        $file_path = $test_path;
        $file_exists = true;
        break;
    }
    
    // Method 2: Use glob to find files (bypasses some .htaccess restrictions)
    $glob_pattern = $dir . DIRECTORY_SEPARATOR . $filename;
    $glob_results = glob($glob_pattern);
    if (!empty($glob_results) && is_file($glob_results[0])) {
        $file_path = $glob_results[0];
        $file_exists = true;
        break;
    }
    
    // Method 3: Try case-insensitive matching
    $files = glob($dir . DIRECTORY_SEPARATOR . '*');
    if ($files) {
        foreach ($files as $file) {
            if (is_dir($file)) {
                continue;
            }
            
            $file_basename = basename($file);
            
            // Exact match (case-insensitive)
            if (strcasecmp($file_basename, $filename) === 0) {
                $file_path = $file;
                $file_exists = true;
                break 2; // Break out of both loops
            }
            
            // Try matching without spaces
            $file_no_spaces = str_replace(' ', '', $file_basename);
            $filename_no_spaces = str_replace(' ', '', $filename);
            if (strcasecmp($file_no_spaces, $filename_no_spaces) === 0) {
                $file_path = $file;
                $file_exists = true;
                break 2;
            }
        }
    }
}

// If still not found, return transparent PNG
if (!$file_exists || !$file_path) {
    // Log for debugging
    error_log("ID image not found: Looking for '" . $filename . "' in " . implode(', ', $possible_dirs));
    // Return a 1x1 transparent PNG so browser's onerror handler can work
    header('Content-Type: image/png');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    // 1x1 transparent PNG
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    exit;
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
