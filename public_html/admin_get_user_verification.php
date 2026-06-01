<?php
header('Content-Type: application/json');
if (function_exists('ob_start')) ob_start();
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

$admin_verification_sent = false;
register_shutdown_function(function() use (&$admin_verification_sent) {
    if ($admin_verification_sent) return;
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (function_exists('ob_get_level')) { while (ob_get_level()) ob_end_clean(); }
        echo json_encode(['error' => 'Server error: ' . (isset($e['message']) ? $e['message'] : 'Unknown')]);
    }
});

try {
    require_once __DIR__ . '/db_config.php';
} catch (Exception $e) {
    if (function_exists('ob_get_level')) { while (ob_get_level()) ob_end_clean(); }
    echo json_encode(['error' => 'Config failed: ' . $e->getMessage()]);
    exit;
}

try {
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        throw new Exception('Invalid id');
    }

    // Use query() instead of get_result() so it works without mysqlnd
    $res = $conn->query("SELECT * FROM itcp WHERE id = " . $id);
    if (!$res || $res->num_rows === 0) {
        throw new Exception('User not found');
    }
    $row = $res->fetch_assoc();
    $res->close();

    $row['full_name'] = trim(implode(' ', array_filter([
        isset($row['firstname']) ? $row['firstname'] : '',
        isset($row['middlename']) ? $row['middlename'] : '',
        isset($row['lastname']) ? $row['lastname'] : '',
        isset($row['name_ext']) ? $row['name_ext'] : ''
    ])));
    $row['id_image'] = null;
    $row['id_image_back'] = null;
    $dbg = array(
        'steps' => array(),
        'debug_info' => array(
            'user_id' => $id,
            'student_number' => isset($row['student_number']) ? $row['student_number'] : 'null',
            'full_name' => isset($row['full_name']) ? $row['full_name'] : 'null'
        )
    );

    if (!empty($row['student_number'])) {
        $idr = null;
        $idr_back = null;
        $studentTrim = trim($row['student_number']);
        $dbg['debug_info']['student_number_trimmed'] = $studentTrim;

        // Check if id_image_back column exists (use query, no get_result)
        $has_id_back = false;
        $cr = $conn->query("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'alumni_registration' AND COLUMN_NAME = 'id_image_back'");
        if ($cr && $cr->num_rows > 0) {
            $fr = $cr->fetch_assoc();
            $has_id_back = isset($fr['c']) && (int)$fr['c'] > 0;
            $cr->close();
        }
        $dbg['debug_info']['has_id_image_back_column'] = $has_id_back;
        $idCols = $has_id_back ? "id_image, id_image_back" : "id_image";
        $norm = str_replace(array('-', ' '), '', $studentTrim);
        $esc = $conn->real_escape_string($studentTrim);
        $esc_norm = $conn->real_escape_string($norm);

        // Helper: get id_image_back from row (column name may be any case from MySQL)
        $getBack = function($arow) {
            if (!$arow) return null;
            foreach ($arow as $k => $v) {
                if (strtolower($k) === 'id_image_back' && $v !== null && trim((string)$v) !== '') return trim($v);
            }
            return null;
        };
        $getFront = function($arow) {
            if (!$arow) return null;
            foreach ($arow as $k => $v) {
                if (strtolower($k) === 'id_image' && $v !== null && trim((string)$v) !== '') return trim($v);
            }
            return null;
        };

        // Fetch all matching rows (exact + normalized student_number), then pick one that has back image
        $res2 = $conn->query("SELECT " . $idCols . " FROM alumni_registration WHERE student_number = '" . $esc . "' OR REPLACE(REPLACE(TRIM(student_number), '-', ''), ' ', '') = '" . $esc_norm . "' ORDER BY created_at DESC, id DESC LIMIT 20");
        if ($res2 && $res2->num_rows > 0) {
            $numRows = $res2->num_rows;
            $rowWithBack = null;
            $fallbackRow = null;
            while ($arow = $res2->fetch_assoc()) {
                $front = $getFront($arow);
                $back = $has_id_back ? $getBack($arow) : null;
                if ($front) $fallbackRow = $arow;
                if ($front && $back) {
                    $rowWithBack = $arow;
                    break;
                }
            }
            $res2->close();
            $arow = $rowWithBack ? $rowWithBack : $fallbackRow;
            if ($arow) {
                $idr = $getFront($arow);
                $idr_back = $has_id_back ? $getBack($arow) : null;
                $dbg['steps'][] = array('by_student' => array('student_number' => $studentTrim, 'found' => (bool)$idr, 'id_image' => $idr, 'id_image_back_raw' => $idr_back, 'rows_checked' => $numRows));
            }
            // If we have front but no back, try: (1) any row for this student_number with back, (2) any row with matching name that has back
            if ($idr && !$idr_back && $has_id_back) {
                $res2b = $conn->query("SELECT id_image_back FROM alumni_registration WHERE (student_number = '" . $esc . "' OR REPLACE(REPLACE(TRIM(student_number), '-', ''), ' ', '') = '" . $esc_norm . "') AND id_image_back IS NOT NULL AND TRIM(COALESCE(id_image_back,'')) != '' ORDER BY created_at DESC LIMIT 1");
                if ($res2b && $res2b->num_rows > 0) {
                    $brow = $res2b->fetch_assoc();
                    $idr_back = $getBack($brow);
                    $dbg['steps'][] = array('fallback_back_only' => array('id_image_back_raw' => $idr_back));
                    $res2b->close();
                }
                if (!$idr_back && !empty($row['full_name'])) {
                    $likeName = $conn->real_escape_string('%' . $row['full_name'] . '%');
                    $res2c = $conn->query("SELECT id_image_back FROM alumni_registration WHERE name LIKE '" . $likeName . "' AND id_image_back IS NOT NULL AND TRIM(COALESCE(id_image_back,'')) != '' ORDER BY created_at DESC LIMIT 1");
                    if ($res2c && $res2c->num_rows > 0) {
                        $brow = $res2c->fetch_assoc();
                        $idr_back = $getBack($brow);
                        $dbg['steps'][] = array('fallback_back_by_name' => array('id_image_back_raw' => $idr_back));
                        $res2c->close();
                    }
                }
            }
        }
        if (!$idr && !empty($row['full_name'])) {
            $like = $conn->real_escape_string('%' . $row['full_name'] . '%');
            $res3 = $conn->query("SELECT " . $idCols . " FROM alumni_registration WHERE name LIKE '" . $like . "' ORDER BY created_at DESC, id DESC LIMIT 20");
            if ($res3 && $res3->num_rows > 0) {
                $rowWithBack = null;
                $fallbackRow = null;
                while ($arow = $res3->fetch_assoc()) {
                    $front = $getFront($arow);
                    $back = $has_id_back ? $getBack($arow) : null;
                    if ($front) $fallbackRow = $arow;
                    if ($front && $back) { $rowWithBack = $arow; break; }
                }
                $res3->close();
                $arow = $rowWithBack ? $rowWithBack : $fallbackRow;
                if ($arow && !$idr) {
                    $idr = $getFront($arow);
                    if ($has_id_back && !$idr_back) $idr_back = $getBack($arow);
                    $dbg['steps'][] = array('by_name_like' => array('found' => (bool)$idr, 'id_image_back_raw' => $idr_back));
                }
            } else {
                if ($res3) $res3->close();
            }
        }

        if (!$idr) {
            $chk = $conn->query("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'itcp' AND COLUMN_NAME = 'id_image'");
            $hasCol = false;
            if ($chk && $chk->num_rows > 0) {
                $fr = $chk->fetch_assoc();
                $hasCol = isset($fr['c']) && (int)$fr['c'] > 0;
                $chk->close();
            }
            if ($hasCol) {
                $qr = $conn->query("SELECT id_image FROM itcp WHERE id = " . $id . " LIMIT 1");
                if ($qr && $qr->num_rows > 0) {
                    $idr = $qr->fetch_assoc();
                    $idr = isset($idr['id_image']) ? $idr['id_image'] : null;
                }
                if ($qr) $qr->close();
                $dbg['steps'][] = array('by_itcp_column' => array('has_col' => $hasCol, 'found' => (bool)$idr));
            }
        }

        $resolvePath = function($raw) {
            if (!$raw || !trim($raw)) return null;
            $clean = ltrim(trim($raw), '/');
            if (strpos($clean, 'http') === 0) return $clean;
            if (strpos($clean, 'uploads/') === 0) return $clean;
            if (file_exists(__DIR__ . '/uploads/ids/' . $clean)) return 'uploads/ids/' . $clean;
            if (file_exists(__DIR__ . '/uploads/id_cards/' . $clean)) return 'uploads/id_cards/' . $clean;
            if (file_exists(__DIR__ . '/uploads/' . $clean)) return 'uploads/' . $clean;
            return 'uploads/ids/' . $clean;
        };
        if ($idr) {
            $row['id_image'] = $resolvePath($idr);
            $dbg['resolved'] = array(
                'raw' => $idr,
                'clean' => ltrim($idr, '/'),
                'final' => $row['id_image'],
                'exists_ids' => file_exists(__DIR__ . '/uploads/ids/' . ltrim($idr, '/')),
                'exists_id_cards' => file_exists(__DIR__ . '/uploads/id_cards/' . ltrim($idr, '/')),
                'exists_uploads' => file_exists(__DIR__ . '/uploads/' . ltrim($idr, '/'))
            );
        }
        if ($idr_back) {
            $row['id_image_back'] = $resolvePath($idr_back);
            if (isset($dbg['resolved'])) $dbg['resolved']['id_image_back'] = $row['id_image_back'];
        } elseif ($idr) {
            // Fallback: DB has no back but user uploaded one – find id_back_* file with same timestamp as front (e.g. id_20260222_170017_xxx.jpg → id_back_20260222_170017_*.jpg)
            $idsDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ids';
            if (preg_match('/^id_(\d{8}_\d{6})_/', basename(trim($idr)), $m) && is_dir($idsDir)) {
                $ts = $m[1];
                $glob = $idsDir . DIRECTORY_SEPARATOR . 'id_back_' . $ts . '_*';
                $backFiles = glob($glob);
                if (!empty($backFiles) && is_file($backFiles[0])) {
                    $idr_back = basename($backFiles[0]);
                    $row['id_image_back'] = $resolvePath($idr_back);
                    if (isset($dbg['resolved'])) $dbg['resolved']['id_image_back'] = $row['id_image_back'];
                    $dbg['steps'][] = array('back_from_disk' => array('timestamp' => $ts, 'filename' => $idr_back));
                }
            }
        }
    }

    $out = $row;
    $out['_debug'] = $dbg;

    if (!empty($row['student_number'])) {
        $studentNum = $conn->real_escape_string(trim($row['student_number']));
        $resCount = $conn->query("SELECT COUNT(*) AS count FROM alumni_registration WHERE student_number = '" . $studentNum . "'");
        $out['_debug']['alumni_registration_count'] = 0;
        if ($resCount && $resCount->num_rows > 0) {
            $checkResult = $resCount->fetch_assoc();
            $out['_debug']['alumni_registration_count'] = isset($checkResult['count']) ? (int)$checkResult['count'] : 0;
        }
        if ($resCount) $resCount->close();
    }

    if (function_exists('ob_get_level')) { while (ob_get_level()) ob_end_clean(); }
    $jsonOptions = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) $jsonOptions |= JSON_INVALID_UTF8_SUBSTITUTE;
    $json = json_encode($out, $jsonOptions);
    if ($json === false) {
        echo json_encode(array('error' => 'Failed to encode response: ' . (function_exists('json_last_error_msg') ? json_last_error_msg() : 'unknown')));
    } else {
        echo $json;
    }
    $admin_verification_sent = true;
} catch (Exception $e) {
    if (function_exists('ob_get_level')) { while (ob_get_level()) ob_end_clean(); }
    echo json_encode(array('error' => $e->getMessage()));
    $admin_verification_sent = true;
}
