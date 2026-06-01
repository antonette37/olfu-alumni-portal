<?php
/**
 * Image Proxy for Gallery Images
 * Serves gallery images with proper headers to prevent 403 errors
 */

// Enable error reporting temporarily for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Get the image path from query parameter
$raw_image_path = isset($_GET['img']) ? $_GET['img'] : '';

if (empty($raw_image_path)) {
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('Image not found: No path provided');
}

// Decode URL encoding
$image_path = urldecode($raw_image_path);
// Remove any directory traversal attempts
$image_path = str_replace('..', '', $image_path);
$image_path = ltrim($image_path, '/\\');

// Keep the path as-is if it contains separators (might be in subdirectory)
// Only extract filename if we need to search recursively
$filename_only = basename($image_path);

// Construct file path - handle both gallery and other upload directories
// Use realpath to get canonical path, which handles Windows path issues
$uploads_base_raw = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
$uploads_base = realpath($uploads_base_raw);
if ($uploads_base === false) {
    // If realpath fails (directory doesn't exist), use the raw path
    $uploads_base = $uploads_base_raw;
}
$gallery_dir = $uploads_base . DIRECTORY_SEPARATOR . 'gallery';

// Debug: Log the path being processed (remove in production)
error_log("serve_gallery_image.php - Processing: " . $image_path);
error_log("serve_gallery_image.php - Uploads base: " . $uploads_base);

// Check if image_path contains a subdirectory (like 'ids/', 'gallery/', etc.)
$file_path = '';
if (strpos($image_path, '/') !== false || strpos($image_path, '\\') !== false) {
    // Path includes subdirectory (e.g., "ids/filename.jpg" or "gallery/filename.jpg")
    // Try multiple path formats for Windows compatibility
    // First, try with forward slashes (works on both Windows and Linux)
    $file_path = $uploads_base . '/' . str_replace('\\', '/', $image_path);
    error_log("serve_gallery_image.php - Constructed path with subdir (forward slash): " . $file_path);
    
    // Also prepare Windows-style path as backup
    $normalized_image_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $image_path);
    $file_path_win = $uploads_base . DIRECTORY_SEPARATOR . $normalized_image_path;
    error_log("serve_gallery_image.php - Constructed path with subdir (Windows): " . $file_path_win);
} else {
    // Just filename, try gallery directory first
    $file_path = $gallery_dir . DIRECTORY_SEPARATOR . $image_path;
    error_log("serve_gallery_image.php - Constructed path (gallery): " . $file_path);
}

// Check if uploads base directory exists
if (!is_dir($uploads_base)) {
    http_response_code(500);
    header('Content-Type: text/plain');
    exit('Server error: Uploads directory not found');
}

// Check if gallery directory exists, create if it doesn't (only if we're using it)
if (strpos($image_path, '/') === false && strpos($image_path, '\\') === false) {
    if (!is_dir($gallery_dir)) {
        // Try to create the directory
        if (!@mkdir($gallery_dir, 0755, true)) {
            http_response_code(500);
            header('Content-Type: text/plain');
            exit('Server error: Gallery directory not found and could not be created');
        }
    }
}

// Check if file exists - try multiple path variations
$file_exists = false;
$actual_path = '';

// Try the direct path first (with full path from database)
// This handles paths like "ids/filename.jpg" -> "uploads/ids/filename.jpg"
error_log("serve_gallery_image.php - Checking file: " . $file_path . " | exists: " . (file_exists($file_path) ? 'yes' : 'no'));
if (file_exists($file_path) && is_file($file_path)) {
    $file_exists = true;
    $actual_path = realpath($file_path); // Use realpath to get canonical path
    if ($actual_path === false) {
        $actual_path = $file_path; // Fallback if realpath fails
    }
    error_log("serve_gallery_image.php - File found at: " . $actual_path);
} else {
    // If we have a Windows path variant, try that too
    if (isset($file_path_win) && $file_path_win !== $file_path) {
        error_log("serve_gallery_image.php - Trying Windows path: " . $file_path_win . " | exists: " . (file_exists($file_path_win) ? 'yes' : 'no'));
        if (file_exists($file_path_win) && is_file($file_path_win)) {
            $file_exists = true;
            $actual_path = realpath($file_path_win);
            if ($actual_path === false) {
                $actual_path = $file_path_win;
            }
            error_log("serve_gallery_image.php - File found at Windows path: " . $actual_path);
        }
    }
    
    // Try alternative path construction (handle Windows path issues)
    if (!$file_exists) {
        // Try with forward slashes (works on Windows too)
        $alt_path1 = $uploads_base . '/' . str_replace('\\', '/', $image_path);
        error_log("serve_gallery_image.php - Trying alt path (forward slash): " . $alt_path1 . " | exists: " . (file_exists($alt_path1) ? 'yes' : 'no'));
        if (file_exists($alt_path1) && is_file($alt_path1)) {
            $file_exists = true;
            $actual_path = realpath($alt_path1);
            if ($actual_path === false) {
                $actual_path = $alt_path1;
            }
            error_log("serve_gallery_image.php - File found at alt path: " . $actual_path);
        } else {
            // Try with backslashes (Windows native)
            $alt_path2 = $uploads_base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $image_path);
            error_log("serve_gallery_image.php - Trying alt path (backslash): " . $alt_path2 . " | exists: " . (file_exists($alt_path2) ? 'yes' : 'no'));
            if (file_exists($alt_path2) && is_file($alt_path2)) {
                $file_exists = true;
                $actual_path = realpath($alt_path2);
                if ($actual_path === false) {
                    $actual_path = $alt_path2;
                }
                error_log("serve_gallery_image.php - File found at alt path: " . $actual_path);
            }
        }
    }
}

if (!$file_exists) {
    // If path includes subdirectory, also try case-insensitive search in that subdirectory
    if (strpos($image_path, '/') !== false || strpos($image_path, '\\') !== false) {
        $subdir_path = dirname($file_path);
        $subdir_name = basename(dirname($image_path));
        $subdir_full = $uploads_base . DIRECTORY_SEPARATOR . $subdir_name;
        $subdir_full = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $subdir_full);
        
        if (is_dir($subdir_full)) {
            $files = @scandir($subdir_full);
            if ($files) {
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..' && is_file($subdir_full . DIRECTORY_SEPARATOR . $file)) {
                        if (strcasecmp($file, $filename_only) === 0) {
                            $file_exists = true;
                            $actual_path = $subdir_full . DIRECTORY_SEPARATOR . $file;
                            break;
                        }
                    }
                }
            }
        }
    }
    
    if (!$file_exists) {
        // Try with just the filename in gallery directory (most common case)
        // Only if the original path didn't include a subdirectory
        if (strpos($image_path, '/') === false && strpos($image_path, '\\') === false) {
            $root_path = $gallery_dir . DIRECTORY_SEPARATOR . $filename_only;
            if (file_exists($root_path) && is_file($root_path)) {
                $file_exists = true;
                $actual_path = $root_path;
            }
        }
        
        if (!$file_exists) {
            // Try case-insensitive match in gallery directory
            if (is_dir($gallery_dir)) {
                $files = @scandir($gallery_dir);
                if ($files) {
                    foreach ($files as $file) {
                        if ($file !== '.' && $file !== '..' && is_file($gallery_dir . DIRECTORY_SEPARATOR . $file)) {
                            if (strcasecmp($file, $filename_only) === 0) {
                                $file_exists = true;
                                $actual_path = $gallery_dir . DIRECTORY_SEPARATOR . $file;
                                break;
                            }
                        }
                    }
                }
            }
        }
        
        if (!$file_exists) {
            // Try with different path separators
            $alternate_paths = [
                str_replace(DIRECTORY_SEPARATOR, '/', $file_path),
                str_replace(DIRECTORY_SEPARATOR, '\\', $file_path),
                $gallery_dir . '/' . $image_path,
                $gallery_dir . '\\' . $image_path,
                $gallery_dir . '/' . $filename_only,
                $gallery_dir . '\\' . $filename_only,
            ];
            
            foreach ($alternate_paths as $alt_path) {
                if (file_exists($alt_path) && is_file($alt_path)) {
                    $file_exists = true;
                    $actual_path = $alt_path;
                    break;
                }
            }
        }
        
        // If still not found, search recursively in uploads directory
        if (!$file_exists && is_dir($uploads_base)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($uploads_base, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    // Try exact match first
                    if ($file->getFilename() === $filename_only) {
                        $file_exists = true;
                        $actual_path = $file->getPathname();
                        break;
                    }
                    // Try case-insensitive match
                    if (strcasecmp($file->getFilename(), $filename_only) === 0) {
                        $file_exists = true;
                        $actual_path = $file->getPathname();
                        break;
                    }
                }
            }
        }
    }
}

if (!$file_exists) {
    // Try one more time with a simple directory scan in the subdirectory (if path includes one)
    if (strpos($image_path, '/') !== false || strpos($image_path, '\\') !== false) {
        $subdir_name = basename(dirname($image_path));
        $subdir_full = $uploads_base . DIRECTORY_SEPARATOR . $subdir_name;
        $subdir_full = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $subdir_full);
        
        if (is_dir($subdir_full)) {
            $files = @scandir($subdir_full);
            if ($files) {
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..' && is_file($subdir_full . DIRECTORY_SEPARATOR . $file)) {
                        if (strcasecmp($file, $filename_only) === 0) {
                            $file_exists = true;
                            $actual_path = $subdir_full . DIRECTORY_SEPARATOR . $file;
                            break;
                        }
                    }
                }
            }
        }
    }
    
    // Also try gallery directory as fallback
    if (!$file_exists && is_dir($gallery_dir)) {
        $files = @scandir($gallery_dir);
        if ($files) {
            foreach ($files as $file) {
                if ($file !== '.' && $file !== '..' && is_file($gallery_dir . DIRECTORY_SEPARATOR . $file)) {
                    if (strcasecmp($file, $filename_only) === 0) {
                        $file_exists = true;
                        $actual_path = $gallery_dir . DIRECTORY_SEPARATOR . $file;
                        break;
                    }
                }
            }
        }
    }
    
    if (!$file_exists) {
        // Try to list files in directory for debugging
        $debug_info = '';
        $search_dir = (strpos($image_path, '/') !== false || strpos($image_path, '\\') !== false) ? dirname($file_path) : $gallery_dir;
        if (is_dir($search_dir)) {
            $files = @scandir($search_dir);
            if ($files) {
                $image_files = array_filter($files, function($f) {
                    return preg_match('/\.(jpg|jpeg|png|gif|bmp|webp)$/i', $f);
                });
                $debug_info = ' | Files in dir: ' . count($image_files);
                if (count($image_files) > 0 && count($image_files) <= 10) {
                    $debug_info .= ' (' . implode(', ', array_slice($image_files, 0, 5)) . ')';
                }
            }
        }
        
        http_response_code(404);
        header('Content-Type: text/plain');
        exit('Image not found: ' . basename($image_path) . ' | Looking for: ' . $filename_only . ' | In: ' . $search_dir . $debug_info);
    }
}

// Use the actual path that exists
if (empty($actual_path)) {
    http_response_code(500);
    header('Content-Type: text/plain');
    exit('Server error: No file path determined');
}

$file_path = $actual_path;

// Verify file exists and is readable
if (!file_exists($file_path) || !is_file($file_path) || !is_readable($file_path)) {
    http_response_code(500);
    header('Content-Type: text/plain');
    exit('Server error: File not accessible: ' . basename($file_path));
}

// Get file info
$file_info = pathinfo($file_path);
$mime_type = 'image/jpeg'; // default

if (isset($file_info['extension'])) {
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
}

// Set headers
header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: public, max-age=31536000'); // Cache for 1 year
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');

// Read and output file
if (file_exists($file_path) && is_readable($file_path)) {
    readfile($file_path);
    exit;
} else {
    http_response_code(500);
    header('Content-Type: text/plain');
    exit('Server error: File exists but cannot be read');
}

