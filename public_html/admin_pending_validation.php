<?php
/**
 * Returns validation status for pending users: duplicate email, duplicate alumni ID number,
 * missing password, and alumni ID image presence. Used by admin user management to
 * auto-check before approval and show warnings.
 */
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/db_config.php';

try {
    $conn = getDBConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    $ids = [];
    if (isset($_GET['id']) && is_numeric($_GET['id'])) {
        $ids = [(int)$_GET['id']];
    } elseif (isset($_GET['ids']) && is_string($_GET['ids'])) {
        $ids = array_filter(array_map('intval', explode(',', $_GET['ids'])));
    }

    if (empty($ids)) {
        echo json_encode(['validations' => (object)[], 'error' => 'Missing id or ids']);
        exit;
    }

    $out = ['validations' => []];

    foreach ($ids as $uid) {
        $stmt = $conn->prepare("SELECT id, email, student_number, password FROM itcp WHERE id = ?");
        if (!$stmt) continue;
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            $out['validations'][$uid] = ['not_found' => true, 'no_password' => true, 'duplicate_email' => true, 'duplicate_student_number' => true, 'id_image_ok' => false];
            continue;
        }

        $email = trim($row['email'] ?? '');
        $student_number = trim($row['student_number'] ?? '');
        $normalized_sn = preg_replace('/[\s\-]+/', '', $student_number);

        $no_password = empty($row['password']) || !is_string($row['password']) || trim($row['password']) === '';

        $duplicate_email = false;
        if ($email !== '') {
            $chk = $conn->prepare("SELECT COUNT(*) AS c FROM itcp WHERE LOWER(TRIM(email)) = LOWER(?) AND id != ? AND status IN ('active','pending')");
            if ($chk) {
                $chk->bind_param('si', $email, $uid);
                $chk->execute();
                $r = $chk->get_result()->fetch_assoc();
                $duplicate_email = isset($r['c']) && (int)$r['c'] > 0;
                $chk->close();
            }
        }

        $duplicate_student_number = false;
        if ($normalized_sn !== '') {
            $chk = $conn->prepare("SELECT id, student_number FROM itcp WHERE id != ? AND status IN ('active','pending') AND TRIM(student_number) != ''");
            if ($chk) {
                $chk->bind_param('i', $uid);
                $chk->execute();
                $res2 = $chk->get_result();
                while ($other = $res2->fetch_assoc()) {
                    $other_sn = preg_replace('/[\s\-]+/', '', trim($other['student_number'] ?? ''));
                    if (strcasecmp($other_sn, $normalized_sn) === 0) {
                        $duplicate_student_number = true;
                        break;
                    }
                }
                $chk->close();
            }
        }

        $id_image_ok = false;
        if ($student_number !== '') {
            $ar = $conn->prepare("SELECT id_image FROM alumni_registration WHERE student_number = ? OR REPLACE(REPLACE(student_number, '-', ''), ' ', '') = ? ORDER BY created_at DESC LIMIT 1");
            if ($ar) {
                $ar->bind_param('ss', $student_number, $normalized_sn);
                $ar->execute();
                $rr = $ar->get_result();
                if ($rr && $rr->num_rows > 0) {
                    $img = $rr->fetch_assoc()['id_image'] ?? '';
                    $id_image_ok = $img !== '' && trim($img) !== '';
                }
                $ar->close();
            }
        }

        $out['validations'][$uid] = [
            'no_password' => $no_password,
            'duplicate_email' => $duplicate_email,
            'duplicate_student_number' => $duplicate_student_number,
            'id_image_ok' => $id_image_ok,
            'block_approve' => $no_password || $duplicate_email || $duplicate_student_number
        ];
    }

    echo json_encode($out);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'validations' => []]);
}
