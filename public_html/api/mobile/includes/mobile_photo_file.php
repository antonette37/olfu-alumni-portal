<?php
/**
 * Resolve itcp.photo (DB filename) to files on disk.
 * Also falls back to ID card images (uploads/ids/) when profile file is missing — common on Hostinger.
 */
if (!function_exists('mobile_photo_public_root')) {
    function mobile_photo_public_root()
    {
        return dirname(__DIR__, 3);
    }

    function mobile_photo_uploads_dir()
    {
        return mobile_photo_public_root() . DIRECTORY_SEPARATOR . 'uploads';
    }

    function mobile_photo_allowed_ext($name)
    {
        $ext = strtolower(pathinfo((string) $name, PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'], true);
    }

    function mobile_photo_safe_name($name)
    {
        $name = basename(str_replace('\\', '/', (string) $name));
        $name = trim($name, " \t\n\r\0\x0B\"'");
        if ($name === '' || $name === '.' || $name === '..') {
            return '';
        }
        if (preg_match('/[\x00-\x1f<>:"|?*\\\\]/', $name)) {
            return '';
        }
        if (!mobile_photo_allowed_ext($name)) {
            return '';
        }
        return $name;
    }

    function mobile_photo_candidates($photo)
    {
        $p = trim((string) $photo);
        if ($p === '') {
            return [];
        }
        $pool = [];
        $add = function ($s) use (&$pool) {
            $s = mobile_photo_safe_name($s);
            if ($s !== '') {
                $pool[$s] = true;
            }
        };
        $norm = str_replace('\\', '/', $p);
        $norm = preg_replace('#^(\.\./|\./)*#', '', $norm);
        $norm = preg_replace('#^uploads/#i', '', $norm);
        $norm = preg_replace('#^photos/#i', '', $norm);
        $norm = preg_replace('#^ids/#i', '', $norm);
        $norm = ltrim($norm, '/');
        $add($p);
        $add($norm);
        $add(basename($norm));
        $add(urldecode($p));
        $add(str_replace('%20', ' ', $p));
        $add(str_replace(' ', '_', basename($p)));
        $base = pathinfo(basename($p), PATHINFO_FILENAME);
        $ext = pathinfo(basename($p), PATHINFO_EXTENSION);
        if ($base !== '' && $ext !== '') {
            $add(preg_replace('/\s+/', ' ', trim($base)) . '.' . $ext);
            $add(str_replace(' ', '_', $base) . '.' . $ext);
        }
        return array_keys($pool);
    }

    function mobile_photo_find_in_dir($dir, array $candidates)
    {
        if (!is_dir($dir)) {
            return false;
        }
        foreach ($candidates as $name) {
            $path = $dir . DIRECTORY_SEPARATOR . $name;
            if (is_file($path)) {
                return $path;
            }
            $hits = @glob($dir . DIRECTORY_SEPARATOR . $name);
            if (!empty($hits) && is_file($hits[0])) {
                return $hits[0];
            }
        }
        foreach ($candidates as $want) {
            if (preg_match('/^([a-f0-9]{10,})_/i', $want, $m)) {
                $hits = @glob($dir . DIRECTORY_SEPARATOR . $m[1] . '*');
                if (!empty($hits)) {
                    foreach ($hits as $path) {
                        if (is_file($path) && mobile_photo_allowed_ext($path)) {
                            return $path;
                        }
                    }
                }
            }
        }
        return false;
    }

    function mobile_photo_resolve_path($photo)
    {
        if ($photo === null || !is_string($photo)) {
            return false;
        }
        $p = trim($photo);
        if ($p === '' || strcasecmp($p, 'default-avatar.png') === 0) {
            return false;
        }
        if (preg_match('#^https?://#i', $p) || preg_match('#^//#', $p) || strpos($p, 'data:image/') === 0) {
            return false;
        }

        $uploads = mobile_photo_uploads_dir();
        foreach (mobile_photo_candidates($photo) as $name) {
            foreach ([
                $uploads,
                mobile_photo_public_root() . DIRECTORY_SEPARATOR . 'photos',
                $uploads . DIRECTORY_SEPARATOR . 'ids',
                $uploads . DIRECTORY_SEPARATOR . 'id_cards',
            ] as $dir) {
                $found = mobile_photo_find_in_dir($dir, [$name]);
                if ($found !== false) {
                    return $found;
                }
            }
        }

        $dirs = [$uploads, $uploads . DIRECTORY_SEPARATOR . 'ids'];
        foreach (mobile_photo_candidates($photo) as $c) {
            foreach ($dirs as $dir) {
                $found = mobile_photo_find_in_dir($dir, mobile_photo_candidates($c));
                if ($found !== false) {
                    return $found;
                }
            }
        }
        return false;
    }

    /** Resolve a stored filename that may live under uploads/ids/ */
    function mobile_photo_resolve_storage_name($filename)
    {
        $filename = trim((string) $filename);
        if ($filename === '') {
            return false;
        }
        if (preg_match('#^https?://#i', $filename)) {
            return false;
        }
        $filename = ltrim(str_replace('\\', '/', $filename), '/');
        $filename = preg_replace('#^uploads/#i', '', $filename);

        $uploads = mobile_photo_uploads_dir();
        $try = [
            $uploads . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $filename),
            $uploads . DIRECTORY_SEPARATOR . 'ids' . DIRECTORY_SEPARATOR . basename($filename),
            $uploads . DIRECTORY_SEPARATOR . 'id_cards' . DIRECTORY_SEPARATOR . basename($filename),
        ];
        foreach ($try as $path) {
            if (is_file($path)) {
                return $path;
            }
        }
        return mobile_photo_resolve_path(basename($filename));
    }

    function mobile_itcp_has_column($conn, $column)
    {
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        $sql = "SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'itcp' AND COLUMN_NAME = '" . $column . "'";
        $res = @$conn->query($sql);
        if ($res && ($row = $res->fetch_assoc())) {
            return (int) $row['c'] > 0;
        }
        return false;
    }

    /**
     * Load photo + fallbacks for one alumni row.
     * @return array{photo:?string,student_number:?string,id_image:?string}
     */
    function mobile_photo_load_user_row($conn, $userId)
    {
        $out = ['photo' => null, 'student_number' => null, 'id_image' => null, 'firstname' => null, 'lastname' => null];
        if ($userId <= 0) {
            return $out;
        }
        $cols = 'id, photo, student_number, firstname, lastname';
        if (mobile_itcp_has_column($conn, 'id_image')) {
            $cols .= ', id_image';
        }
        $stmt = $conn->prepare("SELECT {$cols} FROM itcp WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return $out;
        }
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = method_exists($stmt, 'get_result') ? $stmt->get_result() : null;
        if ($res && ($row = $res->fetch_assoc())) {
            $out['photo'] = $row['photo'] ?? null;
            $out['student_number'] = $row['student_number'] ?? null;
            $out['id_image'] = $row['id_image'] ?? null;
            $out['firstname'] = $row['firstname'] ?? null;
            $out['lastname'] = $row['lastname'] ?? null;
        }
        $stmt->close();
        return $out;
    }

    function mobile_photo_id_from_registration($conn, $studentNumber, $fullName = '')
    {
        if (!$conn) {
            return null;
        }
        $sn = trim((string) $studentNumber);
        if ($sn !== '') {
            $stmt = @$conn->prepare(
                'SELECT id_image FROM alumni_registration WHERE student_number = ? OR REPLACE(REPLACE(TRIM(student_number), "-", ""), " ", "") = REPLACE(REPLACE(?, "-", ""), " ", "") ORDER BY created_at DESC LIMIT 1'
            );
            if ($stmt) {
                $stmt->bind_param('ss', $sn, $sn);
                if ($stmt->execute()) {
                    $res = $stmt->get_result();
                    if ($res && ($row = $res->fetch_assoc()) && !empty($row['id_image'])) {
                        $stmt->close();
                        return (string) $row['id_image'];
                    }
                }
                $stmt->close();
            }
        }
        $name = trim((string) $fullName);
        if ($name !== '') {
            $like = '%' . $conn->real_escape_string($name) . '%';
            $res = @$conn->query("SELECT id_image FROM alumni_registration WHERE name LIKE '{$like}' ORDER BY created_at DESC LIMIT 1");
            if ($res && ($row = $res->fetch_assoc()) && !empty($row['id_image'])) {
                return (string) $row['id_image'];
            }
        }
        return null;
    }

    /**
     * Best on-disk image for an alumni (profile photo, then ID card from registration).
     */
    function mobile_photo_resolve_path_for_user($userId, $photo = null, $conn = null)
    {
        $closeConn = false;
        if (!$conn) {
            $conn = getDBConnection();
            $closeConn = true;
        }

        $ctx = null;
        if ($photo === null && $userId > 0) {
            $ctx = mobile_photo_load_user_row($conn, $userId);
            $photo = $ctx['photo'];
        }

        if ($photo !== null && trim((string) $photo) !== '') {
            $path = mobile_photo_resolve_path($photo);
            if ($path !== false) {
                if ($closeConn) {
                    $conn->close();
                }
                return $path;
            }
        }

        if ($userId > 0) {
            if ($ctx === null) {
                $ctx = mobile_photo_load_user_row($conn, $userId);
            }
            if (!empty($ctx['id_image'])) {
                $path = mobile_photo_resolve_storage_name($ctx['id_image']);
                if ($path !== false) {
                    if ($closeConn) {
                        $conn->close();
                    }
                    return $path;
                }
            }
            $regId = mobile_photo_id_from_registration(
                $conn,
                $ctx['student_number'] ?? '',
                trim(($ctx['firstname'] ?? '') . ' ' . ($ctx['lastname'] ?? ''))
            );
            if ($regId) {
                $path = mobile_photo_resolve_storage_name($regId);
                if ($path !== false) {
                    if ($closeConn) {
                        $conn->close();
                    }
                    return $path;
                }
            }
        }

        if ($closeConn) {
            $conn->close();
        }
        return false;
    }

    function mobile_photo_user_has_image($userId, $photo = null, $conn = null)
    {
        return mobile_photo_resolve_path_for_user($userId, $photo, $conn) !== false;
    }

    function mobile_photo_file_exists($photo)
    {
        return mobile_photo_resolve_path($photo) !== false;
    }

    function mobile_photo_serve_url($photo, $siteBase = 'https://ccsolfualumni.sbs', $userId = null)
    {
        $siteBase = rtrim((string) $siteBase, '/');
        $uid = (int) $userId;
        if ($uid > 0 && mobile_photo_user_has_image($uid, $photo)) {
            return $siteBase . '/api/mobile/profile_photo.php?user_id=' . $uid;
        }
        if (!mobile_photo_file_exists($photo)) {
            return null;
        }
        $base = basename(str_replace('\\', '/', trim((string) $photo)));
        $base = preg_replace('#^uploads/#i', '', $base);
        return $siteBase . '/serve_profile_image.php?img=' . rawurlencode($base);
    }

    function mobile_photo_data_uri_for_user($userId, $photo = null, $conn = null)
    {
        $path = mobile_photo_resolve_path_for_user($userId, $photo, $conn);
        if ($path === false || !is_readable($path)) {
            return null;
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = 'image/jpeg';
        if ($ext === 'png') {
            $mime = 'image/png';
        } elseif ($ext === 'gif') {
            $mime = 'image/gif';
        } elseif ($ext === 'webp') {
            $mime = 'image/webp';
        }
        $bytes = @file_get_contents($path);
        if ($bytes === false || $bytes === '' || strlen($bytes) > 900000) {
            return null;
        }
        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    function mobile_photo_data_uri($photo)
    {
        return mobile_photo_data_uri_for_user(0, $photo);
    }
}
