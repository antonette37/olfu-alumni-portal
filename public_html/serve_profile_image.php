<?php
/**
 * Image proxy for profile images under /uploads (handles spaces, underscores, subfolders).
 */

error_reporting(0);
ini_set('display_errors', 0);

$raw = isset($_GET['img']) ? (string)$_GET['img'] : '';
if ($raw === '') {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Image not found: No filename provided');
}

$uploads_dir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';

$allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];

function sp_allowed_extension($name) {
    global $allowed_ext;
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return in_array($ext, $allowed_ext, true);
}

function sp_safe_basename($name) {
    $name = basename(str_replace('\\', '/', $name));
    $name = trim($name, " \t\n\r\0\x0B\"'");
    if ($name === '' || $name === '.' || $name === '..') {
        return '';
    }
    if (preg_match('/[\x00-\x1f<>:"|?*\\\\]/', $name)) {
        return '';
    }
    if (strlen($name) > 240) {
        return '';
    }
    if (!sp_allowed_extension($name)) {
        return '';
    }
    return $name;
}

/**
 * Build alternate spellings for the same logical upload (DB vs disk).
 */
function sp_filename_candidates($decoded) {
    $decoded = trim($decoded);
    $decoded = basename(str_replace('\\', '/', $decoded));
    $pool = [];
    $add = function ($s) use (&$pool) {
        $s = sp_safe_basename($s);
        if ($s !== '') {
            $pool[$s] = true;
        }
    };
    $add($decoded);
    $add(urldecode($decoded));
    $add(rawurldecode($decoded));
    if (strpos($decoded, '%') !== false) {
        $add(rawurldecode(str_replace('+', ' ', $decoded)));
    }
    $add(str_replace('%20', ' ', $decoded));
    $add(str_replace('+', ' ', $decoded));
    $add(str_replace(' ', '_', $decoded));
    $add(str_replace(' ', '-', $decoded));
    $ext = pathinfo($decoded, PATHINFO_EXTENSION);
    $base = pathinfo($decoded, PATHINFO_FILENAME);
    if ($base !== '' && $ext !== '') {
        $norm = preg_replace('/\s+/', ' ', trim($base)) . '.' . $ext;
        $add($norm);
        $add(str_replace(' ', '_', $norm));
        $add(str_replace(' ', '-', $norm));
        $add($base . '.' . strtolower($ext));
    }
    return array_keys($pool);
}

function sp_same_stem_extension($a, $b) {
    $ae = strtolower(pathinfo($a, PATHINFO_EXTENSION));
    $be = strtolower(pathinfo($b, PATHINFO_EXTENSION));
    if ($ae === '' || $ae !== $be) {
        return false;
    }
    $an = strtolower(str_replace([' ', '_', '-'], '', pathinfo($a, PATHINFO_FILENAME)));
    $bn = strtolower(str_replace([' ', '_', '-'], '', pathinfo($b, PATHINFO_FILENAME)));
    return $an !== '' && $an === $bn;
}

/** Match DB names like 6941686b15939_Althea Zarragoza.png when only the prefix exists on disk. */
function sp_find_by_hex_prefix($dir, $decoded) {
    $decoded = trim(basename(str_replace('\\', '/', $decoded)));
    if (!preg_match('/^([a-f0-9]{10,})_/i', $decoded, $m)) {
        return false;
    }
    $prefix = $m[1];
    $hits = @glob($dir . DIRECTORY_SEPARATOR . $prefix . '*');
    if (empty($hits)) {
        return false;
    }
    foreach ($hits as $path) {
        if (is_file($path) && sp_allowed_extension($path)) {
            return $path;
        }
    }
    return false;
}

function sp_find_in_directory($dir, array $candidates) {
    foreach ($candidates as $name) {
        $name = sp_safe_basename($name);
        if ($name === '') {
            continue;
        }
        $path = $dir . DIRECTORY_SEPARATOR . $name;
        if (is_file($path)) {
            return $path;
        }
    }
    foreach ($candidates as $name) {
        $name = sp_safe_basename($name);
        if ($name === '') {
            continue;
        }
        $hits = @glob($dir . DIRECTORY_SEPARATOR . $name);
        if (!empty($hits) && is_file($hits[0])) {
            return $hits[0];
        }
    }
    if (!is_dir($dir)) {
        return false;
    }
    $entries = @scandir($dir);
    if ($entries === false) {
        return false;
    }
    foreach ($entries as $ent) {
        if ($ent === '.' || $ent === '..') {
            continue;
        }
        $full = $dir . DIRECTORY_SEPARATOR . $ent;
        if (!is_file($full)) {
            continue;
        }
        foreach ($candidates as $want) {
            $want = sp_safe_basename($want);
            if ($want === '') {
                continue;
            }
            if (strcasecmp($ent, $want) === 0) {
                return $full;
            }
            if (sp_same_stem_extension($ent, $want)) {
                return $full;
            }
        }
    }
    return false;
}

function sp_find_recursive($uploads_dir, array $candidates) {
    if (!is_dir($uploads_dir)) {
        return false;
    }
    try {
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploads_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($it as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            $candidate = $fileInfo->getFilename();
            foreach ($candidates as $want) {
                $want = sp_safe_basename($want);
                if ($want === '') {
                    continue;
                }
                if (strcasecmp($candidate, $want) === 0) {
                    return $fileInfo->getPathname();
                }
                if (sp_same_stem_extension($candidate, $want)) {
                    return $fileInfo->getPathname();
                }
            }
        }
    } catch (Exception $e) {
        return false;
    }
    return false;
}

function sp_default_fallback_path() {
    $candidates = [
        __DIR__ . DIRECTORY_SEPARATOR . 'default-avatar.png',
        __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'default-avatar.png',
        __DIR__ . DIRECTORY_SEPARATOR . 'olfulogo.png',
    ];
    foreach ($candidates as $p) {
        if (is_file($p)) {
            return $p;
        }
    }
    return false;
}

// --- resolve requested image ---
$primary = sp_safe_basename(urldecode($raw));
if ($primary === '' && strpos($raw, '%') !== false) {
    $primary = sp_safe_basename(rawurldecode($raw));
}
if ($primary === '') {
    $primary = sp_safe_basename(basename(str_replace('\\', '/', $raw)));
}

$candidates = array_values(array_unique(array_merge(
    $primary !== '' ? sp_filename_candidates($primary) : [],
    sp_filename_candidates(urldecode($raw)),
    sp_filename_candidates($raw)
)));

$file_path = false;
$search_dirs = [];
if (is_dir($uploads_dir)) {
    $search_dirs[] = $uploads_dir;
}
$photos_dir = __DIR__ . DIRECTORY_SEPARATOR . 'photos';
if (is_dir($photos_dir)) {
    $search_dirs[] = $photos_dir;
}
$ids_dir = $uploads_dir . DIRECTORY_SEPARATOR . 'ids';
if (is_dir($ids_dir)) {
    $search_dirs[] = $ids_dir;
}
$id_cards_dir = $uploads_dir . DIRECTORY_SEPARATOR . 'id_cards';
if (is_dir($id_cards_dir)) {
    $search_dirs[] = $id_cards_dir;
}
foreach ($search_dirs as $dir) {
    $file_path = sp_find_in_directory($dir, $candidates);
    if ($file_path === false && $primary !== '') {
        $file_path = sp_find_by_hex_prefix($dir, $primary);
    }
    if ($file_path !== false) {
        break;
    }
}
if ($file_path === false && is_dir($uploads_dir)) {
    $file_path = sp_find_recursive($uploads_dir, $candidates);
}

// default.jpg / default.png etc. → site placeholder if present
if ($file_path === false && preg_match('/^default\.(jpe?g|png|gif|webp)$/i', $primary !== '' ? $primary : basename($raw))) {
    $file_path = sp_default_fallback_path();
}

if ($file_path === false || !is_file($file_path)) {
    error_log('serve_profile_image: not found for img=' . $raw);
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    exit('Not found');
}

$file_info = pathinfo($file_path);
$mime_type = 'image/jpeg';

switch (strtolower($file_info['extension'] ?? '')) {
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

header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: public, max-age=31536000');
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');

readfile($file_path);
exit;
