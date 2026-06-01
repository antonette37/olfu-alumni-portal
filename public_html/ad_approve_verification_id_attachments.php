<?php
/**
 * Resolve registration / verification ID images (front + back) for approval emails.
 * Logic aligned with admin_get_user_verification.php (alumni_registration + itcp.id_image + disk id_back_*).
 */
declare(strict_types=1);

/**
 * @param array<string, mixed> $user_data itcp row
 *
 * @return list<array{path: string, name: string}>
 */
function ad_approve_verification_id_attachment_list(mysqli $conn, array $user_data, int $user_id): array
{
    $fullName = trim(implode(' ', array_filter([
        isset($user_data['firstname']) ? (string) $user_data['firstname'] : '',
        isset($user_data['middlename']) ? (string) $user_data['middlename'] : '',
        isset($user_data['lastname']) ? (string) $user_data['lastname'] : '',
        isset($user_data['name_ext']) ? (string) $user_data['name_ext'] : '',
    ])));

    $idr = null;
    $idr_back = null;

    $studentTrim = trim((string) ($user_data['student_number'] ?? ''));
    if ($studentTrim !== '') {
        $has_id_back = false;
        $cr = $conn->query("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='alumni_registration' AND COLUMN_NAME='id_image_back'");
        if ($cr && $cr->num_rows > 0) {
            $fr = $cr->fetch_assoc();
            $has_id_back = isset($fr['c']) && (int) $fr['c'] > 0;
            $cr->close();
        }
        $idCols = $has_id_back ? 'id_image, id_image_back' : 'id_image';
        $norm = str_replace(['-', ' '], '', $studentTrim);
        $esc = $conn->real_escape_string($studentTrim);
        $esc_norm = $conn->real_escape_string($norm);

        $getBack = static function (?array $arow): ?string {
            if (!$arow) {
                return null;
            }
            foreach ($arow as $k => $v) {
                if (strtolower((string) $k) === 'id_image_back' && $v !== null && trim((string) $v) !== '') {
                    return trim((string) $v);
                }
            }

            return null;
        };
        $getFront = static function (?array $arow): ?string {
            if (!$arow) {
                return null;
            }
            foreach ($arow as $k => $v) {
                if (strtolower((string) $k) === 'id_image' && $v !== null && trim((string) $v) !== '') {
                    return trim((string) $v);
                }
            }

            return null;
        };

        $res2 = $conn->query("SELECT {$idCols} FROM alumni_registration WHERE student_number = '{$esc}' OR REPLACE(REPLACE(TRIM(student_number), '-', ''), ' ', '') = '{$esc_norm}' ORDER BY created_at DESC, id DESC LIMIT 20");
        if ($res2 && $res2->num_rows > 0) {
            $rowWithBack = null;
            $fallbackRow = null;
            while ($arow = $res2->fetch_assoc()) {
                $front = $getFront($arow);
                $back = $has_id_back ? $getBack($arow) : null;
                if ($front) {
                    $fallbackRow = $arow;
                }
                if ($front && $back) {
                    $rowWithBack = $arow;
                    break;
                }
            }
            $res2->close();
            $arow = $rowWithBack ?: $fallbackRow;
            if ($arow) {
                $idr = $getFront($arow);
                $idr_back = $has_id_back ? $getBack($arow) : null;
            }
            if ($idr && !$idr_back && $has_id_back) {
                $res2b = $conn->query("SELECT id_image_back FROM alumni_registration WHERE (student_number = '{$esc}' OR REPLACE(REPLACE(TRIM(student_number), '-', ''), ' ', '') = '{$esc_norm}') AND id_image_back IS NOT NULL AND TRIM(COALESCE(id_image_back,'')) != '' ORDER BY created_at DESC LIMIT 1");
                if ($res2b && $res2b->num_rows > 0) {
                    $brow = $res2b->fetch_assoc();
                    $idr_back = $getBack($brow);
                    $res2b->close();
                }
                if (!$idr_back && $fullName !== '') {
                    $likeName = $conn->real_escape_string('%' . $fullName . '%');
                    $res2c = $conn->query("SELECT id_image_back FROM alumni_registration WHERE name LIKE '{$likeName}' AND id_image_back IS NOT NULL AND TRIM(COALESCE(id_image_back,'')) != '' ORDER BY created_at DESC LIMIT 1");
                    if ($res2c && $res2c->num_rows > 0) {
                        $brow = $res2c->fetch_assoc();
                        $idr_back = $getBack($brow);
                        $res2c->close();
                    }
                }
            }
        } elseif ($res2) {
            $res2->close();
        }
    }

    if (!$idr && $fullName !== '') {
        $has_id_back = false;
        $cr = $conn->query("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='alumni_registration' AND COLUMN_NAME='id_image_back'");
        if ($cr && $cr->num_rows > 0) {
            $fr = $cr->fetch_assoc();
            $has_id_back = isset($fr['c']) && (int) $fr['c'] > 0;
            $cr->close();
        }
        $idCols = $has_id_back ? 'id_image, id_image_back' : 'id_image';
        $like = $conn->real_escape_string('%' . $fullName . '%');
        $getBack = static function (?array $arow): ?string {
            if (!$arow) {
                return null;
            }
            foreach ($arow as $k => $v) {
                if (strtolower((string) $k) === 'id_image_back' && $v !== null && trim((string) $v) !== '') {
                    return trim((string) $v);
                }
            }

            return null;
        };
        $getFront = static function (?array $arow): ?string {
            if (!$arow) {
                return null;
            }
            foreach ($arow as $k => $v) {
                if (strtolower((string) $k) === 'id_image' && $v !== null && trim((string) $v) !== '') {
                    return trim((string) $v);
                }
            }

            return null;
        };
        $res3 = $conn->query("SELECT {$idCols} FROM alumni_registration WHERE name LIKE '{$like}' ORDER BY created_at DESC, id DESC LIMIT 20");
        if ($res3 && $res3->num_rows > 0) {
            $rowWithBack = null;
            $fallbackRow = null;
            while ($arow = $res3->fetch_assoc()) {
                $front = $getFront($arow);
                $back = $has_id_back ? $getBack($arow) : null;
                if ($front) {
                    $fallbackRow = $arow;
                }
                if ($front && $back) {
                    $rowWithBack = $arow;
                    break;
                }
            }
            $res3->close();
            $arow = $rowWithBack ?: $fallbackRow;
            if ($arow) {
                $idr = $getFront($arow);
                if ($has_id_back && !$idr_back) {
                    $idr_back = $getBack($arow);
                }
            }
        } elseif ($res3) {
            $res3->close();
        }
    }

    if (!$idr) {
        $chk = $conn->query("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='itcp' AND COLUMN_NAME='id_image'");
        $hasCol = false;
        if ($chk && $chk->num_rows > 0) {
            $fr = $chk->fetch_assoc();
            $hasCol = isset($fr['c']) && (int) $fr['c'] > 0;
            $chk->close();
        }
        if ($hasCol) {
            $uid = (int) $user_id;
            $qr = $conn->query('SELECT id_image FROM itcp WHERE id = ' . $uid . ' LIMIT 1');
            if ($qr && $qr->num_rows > 0) {
                $rowImg = $qr->fetch_assoc();
                $idr = isset($rowImg['id_image']) ? trim((string) $rowImg['id_image']) : null;
                if ($idr === '') {
                    $idr = null;
                }
            }
            if ($qr) {
                $qr->close();
            }
        }
    }

    if ($idr && !$idr_back) {
        $idsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ids';
        if (preg_match('/^id_(\d{8}_\d{6})_/', basename(trim($idr)), $m) && is_dir($idsDir)) {
            $ts = $m[1];
            $backFiles = glob($idsDir . DIRECTORY_SEPARATOR . 'id_back_' . $ts . '_*');
            if (!empty($backFiles) && is_file($backFiles[0])) {
                $idr_back = basename($backFiles[0]);
            }
        }
    }

    $out = [];
    $pathFront = ad_approve_local_path_for_id_image($idr);
    if ($pathFront !== null) {
        $ext = pathinfo($pathFront, PATHINFO_EXTENSION);
        $out[] = ['path' => $pathFront, 'name' => 'Alumni-ID-verification-front.' . ($ext !== '' ? $ext : 'jpg')];
    }
    $pathBack = ad_approve_local_path_for_id_image($idr_back);
    if ($pathBack !== null && $pathBack !== $pathFront) {
        $extB = pathinfo($pathBack, PATHINFO_EXTENSION);
        $out[] = ['path' => $pathBack, 'name' => 'Alumni-ID-verification-back.' . ($extB !== '' ? $extB : 'jpg')];
    }

    return $out;
}

function ad_approve_local_path_for_id_image(?string $raw): ?string
{
    if ($raw === null || trim($raw) === '') {
        return null;
    }
    $raw = trim($raw);
    if (stripos($raw, 'http://') === 0 || stripos($raw, 'https://') === 0) {
        return null;
    }
    $base = dirname(__DIR__);
    $clean = ltrim($raw, '/');
    $cleanPath = str_replace('/', DIRECTORY_SEPARATOR, $clean);
    $bn = basename($clean);

    $paths = [
        $base . DIRECTORY_SEPARATOR . $cleanPath,
        $base . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ids' . DIRECTORY_SEPARATOR . $bn,
        $base . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'id_cards' . DIRECTORY_SEPARATOR . $bn,
        $base . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $bn,
    ];
    foreach ($paths as $p) {
        if (is_file($p) && is_readable($p)) {
            return $p;
        }
    }

    return null;
}
