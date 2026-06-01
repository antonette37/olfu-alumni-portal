<?php

/**

 * Mobile profile update — POST multipart/form-data.

 * Optional: photo (file), firstname, lastname, personal_contact, address,

 * year_graduated, program, employment_status, company, position

 * Authorization: Bearer token_{user_id}

 */

header('Content-Type: application/json');

header('Access-Control-Allow-Origin: *');

header('Access-Control-Allow-Methods: POST, OPTIONS');

header('Access-Control-Allow-Headers: Authorization, Content-Type');



if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

    http_response_code(200);

    exit;

}



if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode(['success' => false, 'message' => 'Method not allowed']);

    exit;

}



require_once __DIR__ . '/../../db_config.php';

require_once __DIR__ . '/includes/resolve_profile_image.php';

require_once __DIR__ . '/includes/mobile_auth.php';



$user_id = mobile_auth_user_id();

if (!$user_id) {

    http_response_code(401);

    echo json_encode(['success' => false, 'message' => 'Unauthorized']);

    exit;

}



try {

    $conn = getDBConnection();

    $new_photo_filename = null;



    if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {

        $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

        $mime = 'application/octet-stream';

        if (class_exists('finfo')) {

            $finfo = new finfo(FILEINFO_MIME_TYPE);

            $mime = $finfo->file($_FILES['photo']['tmp_name']);

        } elseif (function_exists('mime_content_type')) {

            $mime = mime_content_type($_FILES['photo']['tmp_name']);

        }



        if (!in_array($mime, $allowed, true)) {

            http_response_code(400);

            echo json_encode(['success' => false, 'message' => 'Invalid image type. Allowed: jpg, png, gif, webp']);

            exit;

        }



        if ($_FILES['photo']['size'] > 5 * 1024 * 1024) {

            http_response_code(400);

            echo json_encode(['success' => false, 'message' => 'Image too large (max 5 MB)']);

            exit;

        }



        $ext = 'jpg';

        if ($mime === 'image/png') {

            $ext = 'png';

        } elseif ($mime === 'image/gif') {

            $ext = 'gif';

        } elseif ($mime === 'image/webp') {

            $ext = 'webp';

        }



        $uploads_dir = dirname(__DIR__, 2) . '/uploads';

        if (!is_dir($uploads_dir)) {

            @mkdir($uploads_dir, 0755, true);

        }



        $original_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($_FILES['photo']['name'], PATHINFO_FILENAME));

        $new_photo_filename = uniqid() . '_' . $user_id . '_' . $original_name . '.' . $ext;

        $dest = $uploads_dir . '/' . $new_photo_filename;



        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) {

            http_response_code(500);

            echo json_encode(['success' => false, 'message' => 'Failed to save photo']);

            exit;

        }



        $old = $conn->prepare('SELECT photo FROM itcp WHERE id = ? LIMIT 1');

        if ($old) {

            $old->bind_param('i', $user_id);

            $old->execute();

            $res = $old->get_result();

            $prev = $res ? $res->fetch_assoc() : null;

            $old->close();

            if (!empty($prev['photo']) && $prev['photo'] !== 'default-avatar.png') {

                $old_path = $uploads_dir . '/' . basename($prev['photo']);

                if (is_file($old_path)) {

                    @unlink($old_path);

                }

            }

        }

    }



    $fields = [];

    $types = '';

    $values = [];



    foreach (['firstname', 'lastname', 'personal_contact', 'address', 'year_graduated', 'program', 'employment_status', 'company', 'position'] as $f) {

        if (isset($_POST[$f])) {

            $fields[] = "$f = ?";

            $types .= 's';

            $values[] = trim((string) $_POST[$f]);

        }

    }



    if ($new_photo_filename !== null) {

        $fields[] = 'photo = ?';

        $types .= 's';

        $values[] = $new_photo_filename;

    }



    if (empty($fields)) {

        http_response_code(400);

        echo json_encode(['success' => false, 'message' => 'No fields to update']);

        exit;

    }



    $types .= 'i';

    $values[] = $user_id;



    $sql = 'UPDATE itcp SET ' . implode(', ', $fields) . ' WHERE id = ?';

    $stmt = $conn->prepare($sql);

    if (!$stmt) {

        throw new Exception('Prepare failed: ' . $conn->error);

    }

    $stmt->bind_param($types, ...$values);

    if (!$stmt->execute()) {

        throw new Exception('Execute failed: ' . $stmt->error);

    }

    $stmt->close();



    $sel = $conn->prepare('SELECT id, email, firstname, lastname, status, photo, personal_contact, address, year_graduated, program, employment_status, company, position FROM itcp WHERE id = ?');

    $sel->bind_param('i', $user_id);

    $sel->execute();

    $res = $sel->get_result();

    $user = $res->fetch_assoc();

    $sel->close();

    $conn->close();



    $userResponse = [

        'id' => (int) $user['id'],

        'email' => $user['email'],

        'firstname' => $user['firstname'] ?? '',

        'lastname' => $user['lastname'] ?? '',

        'status' => $user['status'] ?? 'active',

        'profile_image' => mobile_resolve_profile_image_url($user['photo'] ?? null, 'https://ccsolfualumni.sbs', $user_id),

        'profile_image_data' => mobile_resolve_profile_image_data($user['photo'] ?? null, $user_id),

        'photo' => $user['photo'] ?? null,

        'phone' => $user['personal_contact'] ?? null,

        'address' => $user['address'] ?? null,

        'batch' => $user['year_graduated'] ?? null,

        'course' => $user['program'] ?? null,

        'employment_status' => $user['employment_status'] ?? null,

        'company' => $user['company'] ?? null,

        'position' => $user['position'] ?? null,

    ];



    echo json_encode([

        'success' => true,

        'message' => 'Profile updated successfully',

        'user' => $userResponse,

    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);



} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);

}


