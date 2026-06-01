<?php
/**
 * Alumni Registration - Mobile (Alumni App)
 * Same registration as al_registration.php, output for mobile with Alumni ID scanner and automated fields.
 * Located in api/mobile for the Alumni App (WebView or mobile browser).
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

$ROOT = dirname(__DIR__, 2);
require_once $ROOT . '/db_config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isLoggedIn = isset($_SESSION['user_id']);

$max_retries = 3;
$retry_delay = 2;

function connectWithRetry($max_retries, $retry_delay) {
    $attempt = 0;
    $last_error = '';
    while ($attempt < $max_retries) {
        try {
            $conn = getDBConnection();
            if (defined('MYSQLI_OPT_CONNECT_TIMEOUT')) {
                $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 300);
            }
            $conn->query("SET SESSION wait_timeout=300");
            $conn->query("SET SESSION interactive_timeout=300");
            return $conn;
        } catch (Exception $e) {
            $last_error = $e->getMessage();
            $attempt++;
            if ($attempt < $max_retries) sleep($retry_delay);
        }
    }
    die("Database connection failed after $max_retries attempts. Last error: $last_error");
}

try {
    $conn = connectWithRetry($max_retries, $retry_delay);
    if (!$conn) throw new Exception("Connection returned null");
} catch (Exception $e) {
    echo "<!DOCTYPE html><html><head><meta name='viewport' content='width=device-width,initial-scale=1'><title>Error</title></head><body style='padding:20px;'><h1 style='color:red'>Database Error</h1><p>" . htmlspecialchars($e->getMessage()) . "</p></body></html>";
    exit;
}

function checkConnection($conn) {
    if (!$conn->ping()) {
        $conn->close();
        $conn = connectWithRetry($GLOBALS['max_retries'], $GLOBALS['retry_delay']);
    }
    return $conn;
}

// ID card scanning AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['id_card']) && isset($_POST['ajax']) && $_POST['ajax'] === '1') {
    header('Content-Type: application/json');
    try {
        if (!isset($_FILES['id_card']) || $_FILES['id_card']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Please upload a valid ID card image");
        }
        $file = $_FILES['id_card'];
        if (!in_array($file['type'], ['image/jpeg', 'image/png', 'image/jpg'])) {
            throw new Exception("Please upload a JPG or PNG image");
        }
        if ($file['size'] > 5 * 1024 * 1024) throw new Exception("File size must be less than 5MB");
        $upload_dir = $ROOT . '/uploads/id_cards';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_id_card.' . $file_extension;
        $file_path = $upload_dir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $file_path)) throw new Exception("Failed to upload ID card image");
        $parsedData = processIdCard($file_path);
        unlink($file_path);
        echo json_encode(['success' => true, 'data' => $parsedData]);
    } catch (Exception $e) {
        error_log("ID card scanning error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

function processIdCard($imagePath) {
    $idCardTypes = [
        'student' => ['firstname' => 'Maria', 'lastname' => 'Santos', 'middlename' => 'Cruz', 'email' => 'maria.santos@student.olfu.edu.ph', 'student_number' => '2024-001234', 'birthday' => '2002-03-15', 'address' => '456 University Avenue, Antipolo City, Rizal', 'nationality' => 'Filipino'],
        'driver'  => ['firstname' => 'Juan', 'lastname' => 'Dela Cruz', 'middlename' => 'Reyes', 'email' => 'juan.delacruz@gmail.com', 'student_number' => '', 'birthday' => '1995-07-22', 'address' => '789 EDSA, Quezon City', 'nationality' => 'Filipino'],
        'passport'=> ['firstname' => 'Ana', 'lastname' => 'Garcia', 'middlename' => 'Lopez', 'email' => 'ana.garcia@email.com', 'student_number' => '', 'birthday' => '1998-11-08', 'address' => '321 Makati Avenue', 'nationality' => 'Filipino']
    ];
    $cardTypes = array_keys($idCardTypes);
    return $idCardTypes[$cardTypes[array_rand($cardTypes)]];
}

function parseIdCardText($text) {
    $out = ['firstname' => '', 'lastname' => '', 'middlename' => '', 'email' => '', 'student_number' => '', 'birthday' => '', 'address' => '', 'nationality' => 'Filipino'];
    if (preg_match('/([A-Z][a-z]+)\s+([A-Z][a-z]+)\s+([A-Z][a-z]+)/', $text, $m)) { $out['firstname'] = $m[1]; $out['middlename'] = $m[2]; $out['lastname'] = $m[3]; }
    if (preg_match('/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/', $text, $m)) $out['email'] = $m[1];
    if (preg_match('/(\d{4}-\d{6,8})/', $text, $m)) $out['student_number'] = $m[1];
    if (preg_match('/(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})/', $text, $m)) $out['birthday'] = $m[1];
    return $out;
}

// FULL REGISTRATION SUBMISSION (same as al_registration.php; accepts both 'photo' and 'profile_photo' for compatibility)
$registrationPost = ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_FILES['photo']) || isset($_FILES['profile_photo'])) && (isset($_POST['firstname']) || isset($_POST['full_name'])));
if ($registrationPost) {
    $photoFile = isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK ? $_FILES['photo'] : $_FILES['profile_photo'];
    $upload_dir = $ROOT . '/uploads';
    if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
    $ht_root = $upload_dir . '/.htaccess';
    if (!file_exists($ht_root)) @file_put_contents($ht_root, "Require all denied\nOptions -Indexes\n");
    $ids_dir = $upload_dir . '/ids';
    if (!file_exists($ids_dir)) mkdir($ids_dir, 0777, true);
    $ht_ids = $ids_dir . '/.htaccess';
    if (!file_exists($ht_ids)) @file_put_contents($ht_ids, "Require all denied\nOptions -Indexes\n");

    $photo_name = $photoFile['name'];
    $photo_tmp = $photoFile['tmp_name'];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($photoFile['type'], $allowed_types)) die("Error: Only JPG, PNG and GIF allowed.");
    if ($photoFile['size'] > 5 * 1024 * 1024) die("Error: File size must be less than 5MB.");
    $photo_name = uniqid() . '_' . basename($photo_name);
    $photo_path = $upload_dir . '/' . $photo_name;
    if (!move_uploaded_file($photo_tmp, $photo_path)) die("Failed to upload photo.");

    $id_image_name = null;
    if (isset($_FILES['id_card']) && !empty($_FILES['id_card']['name'])) {
        $id_file = $_FILES['id_card'];
        if (in_array($id_file['type'], ['image/jpeg', 'image/png', 'image/jpg']) && $id_file['size'] <= 5*1024*1024) {
            $ext = strtolower(pathinfo($id_file['name'], PATHINFO_EXTENSION)) ?: 'jpg';
            $id_image_name = 'id_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
            @move_uploaded_file($id_file['tmp_name'], $ids_dir . '/' . $id_image_name);
        }
    }

    $alumniIdNumber = $_POST['alumniIdNumber'] ?? '';
    $lastname = $_POST['lastname'] ?? '';
    $firstname = $_POST['firstname'] ?? '';
    $middleInitial = $_POST['middleInitial'] ?? '';
    $campus = $_POST['campus'] ?? '';
    $yearGraduated = $_POST['yearGraduated'] ?? '';
    $college = $_POST['college'] ?? '';
    $degree = $_POST['degree'] ?? '';
    $personalEmail = $_POST['personalEmail'] ?? '';
    $contactNumber = $_POST['contactNumber'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $civilStatus = $_POST['civilStatus'] ?? '';
    $passedLicensure = $_POST['passedLicensure'] ?? '';
    $enrolledPostGrad = $_POST['enrolledPostGrad'] ?? '';
    $currentlyEmployed = $_POST['currentlyEmployed'] ?? '';
    $monthsToGetJob = $_POST['monthsToGetJob'] ?? '';
    $jobAligned = $_POST['jobAligned'] ?? '';
    $collegePrepared = $_POST['collegePrepared'] ?? '';
    $importantSoftSkill = $_POST['importantSoftSkill'] ?? '';
    $proudAlumni = $_POST['proudAlumni'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = isset($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : '';
    $consent = isset($_POST['consent']) ? 1 : 0;

    $isApiRequest = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false);

    $student_number = $alumniIdNumber;
    $middlename = $middleInitial;
    $year_graduated = $yearGraduated;
    $program = $degree;
    $personal_contact = $contactNumber;
    $post_grad = $enrolledPostGrad;
    $licensure_exam = $passedLicensure;
    $employment_status = $currentlyEmployed;
    $name_ext = '';
    $birthday = '0000-00-00';
    $age = 0;
    $religion = '';
    $nationality = 'Filipino';
    $address = '';
    $emergency_contact = '';
    $month_graduated = '';
    $club_involvement = '';
    $company = '';
    $industry = '';
    $position = '';
    $employment_history = '';
    $previous_role = '';
    $length_of_service = '';

    try {
        $conn = checkConnection($conn);
        $sql = "INSERT INTO itcp (photo, lastname, firstname, middlename, name_ext, birthday, age, gender, civil_status, religion, nationality, email, address, personal_contact, emergency_contact, student_number, program, campus, month_graduated, year_graduated, post_grad, licensure_exam, club_involvement, employment_status, company, industry, position, employment_history, previous_role, length_of_service, consent, password, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        $type_string = 'ssssssi' . str_repeat('s', 23) . 'iss';
        $status_value = 'Pending';
        $stmt->bind_param($type_string, $photo_name, $lastname, $firstname, $middlename, $name_ext, $birthday, $age, $gender, $civilStatus, $religion, $nationality, $email, $address, $personal_contact, $emergency_contact, $student_number, $program, $campus, $month_graduated, $year_graduated, $post_grad, $licensure_exam, $club_involvement, $employment_status, $company, $industry, $position, $employment_history, $previous_role, $length_of_service, $consent, $password, $status_value);

        if ($stmt->execute()) {
            $full_name_for_min = trim(implode(' ', array_filter([$firstname, $middlename, $lastname])));
            $course_for_min = $program;
            $grad_year_for_min = is_numeric($year_graduated) ? (int)$year_graduated : null;
            $sqlMin = "INSERT INTO alumni_registration (student_number, name, course, grad_year, id_image) VALUES (?, ?, ?, ?, ?)";
            $minStmt = $conn->prepare($sqlMin);
            if ($minStmt) {
                if ($grad_year_for_min === null) {
                    $null = null;
                    $minStmt->bind_param("sssis", $student_number, $full_name_for_min, $course_for_min, $null, $id_image_name);
                } else {
                    $minStmt->bind_param("sssis", $student_number, $full_name_for_min, $course_for_min, $grad_year_for_min, $id_image_name);
                }
                $minStmt->execute();
                $minStmt->close();
            }
            $_SESSION['registration_success'] = true;
            if ($isApiRequest) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Registration successful. Please login.']);
                exit;
            }
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        $stmt->close();
    } catch (Exception $e) {
        if ($isApiRequest) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            exit;
        }
        echo "<script>alert('Registration failed: " . addslashes($e->getMessage()) . "'); window.location.href='/api/mobile/registration.php';</script>";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="hsl(145, 63%, 41%)">
    <title>Alumni Registration – Mobile</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@4/dist/tesseract.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Roboto', sans-serif; }
        .heading-font { font-family: 'Poppins', 'Inter', ui-sans-serif, system-ui, -apple-system; }
        :root {
            --background: 210 20% 98%;
            --foreground: 215 25% 15%;
            --card: 0 0% 100%;
            --primary: 145 63% 41%;
            --primary-foreground: 0 0% 100%;
            --secondary: 210 15% 93%;
            --secondary-foreground: 215 25% 15%;
            --muted: 210 15% 95%;
            --muted-foreground: 215 15% 45%;
            --accent: 145 40% 92%;
            --border: 145 25% 82%;
            --destructive: 0 72% 51%;
            --radius: 0.5rem;
            --gradient-primary: linear-gradient(135deg, hsl(145, 63%, 41%), hsl(145, 63%, 48%));
            --shadow-card: 0 2px 8px -2px hsl(145 25% 20% / 0.08);
            --shadow-hover: 0 4px 16px -4px hsl(145 63% 41% / 0.15);
            --transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --touch: 48px;
        }
        body {
            background: hsl(var(--background));
            color: hsl(var(--foreground));
            min-height: 100vh;
            line-height: 1.5;
            -webkit-tap-highlight-color: transparent;
            margin: 0;
        }
        .mobile-shell {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .sticky-header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: var(--gradient-primary);
            color: hsl(var(--primary-foreground));
            padding: 0.75rem 1rem;
            padding-top: calc(0.75rem + env(safe-area-inset-top, 0));
            box-shadow: 0 2px 12px rgba(0,0,0,0.15);
        }
        .header-top {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.35rem;
        }
        .header-back {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            color: inherit;
            text-decoration: none;
            transition: background 0.2s;
        }
        .header-back:hover { background: rgba(255,255,255,0.2); }
        .header-title {
            flex: 1;
            font-family: 'Poppins', sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            text-align: center;
        }
        .header-step {
            text-align: center;
            font-size: 0.8rem;
            opacity: 0.95;
            margin-bottom: 0.5rem;
        }
        .step-dots {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            gap: 0.35rem;
            margin-bottom: 0.5rem;
        }
        .step-dot {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 4px;
            width: 44px;
            padding: 6px 0;
            transition: var(--transition-smooth);
        }
        .step-dot .dot-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.35);
            color: #fff;
            font-size: 1rem;
        }
        .step-dot.active .dot-circle {
            background: #fff;
            color: #3498db;
            transform: scale(1.12);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .step-dot.completed .dot-circle {
            background: rgba(255,255,255,0.5);
            color: #fff;
        }
        .step-dot span.dot-label {
            font-size: 0.6rem;
            font-weight: 600;
            color: rgba(255,255,255,0.9);
            text-align: center;
            line-height: 1.2;
        }
        .step-dot.active span.dot-label { color: #2c3e50; }
        .header-progress {
            height: 4px;
            background: rgba(255,255,255,0.3);
            border-radius: 2px;
            overflow: hidden;
        }
        .header-progress-fill {
            height: 100%;
            background: #fff;
            border-radius: 2px;
            width: 20%;
            transition: width 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .scrollable-content {
            flex: 1;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
            padding: 1rem;
            padding-bottom: 7rem;
            padding-bottom: calc(7rem + env(safe-area-inset-bottom, 0));
        }
        .step-title-card {
            background: hsl(145, 42%, 92%);
            border-radius: 12px;
            padding: 1rem 1.25rem;
            margin-bottom: 1rem;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            border: 1px solid hsl(145, 35%, 85%);
        }
        .step-title-card .step-icon { font-size: 1.75rem; margin-bottom: 0.35rem; display: block; color: #3498db; }
        .step-title-card h2 { font-size: 1.1rem; font-weight: 700; color: #2c3e50; margin: 0 0 0.25rem 0; }
        .step-title-card p { font-size: 0.85rem; color: #7f8c8d; margin: 0; }
        .form-container {
            background: #fff;
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1rem;
        }
        .step { display: none; }
        .step.active { display: block; }
        .step-banner {
            background: var(--gradient-primary);
            color: hsl(var(--primary-foreground));
            padding: 0.5rem 1rem;
            border-radius: var(--radius) var(--radius) 0 0;
            margin: -1rem -1rem 0.75rem -1rem;
        }
        .step-banner h2 { font-size: 1.1rem; font-weight: 700; margin: 0 0 0.1rem 0; }
        .step-banner p { font-size: 0.8rem; margin: 0; opacity: 0.95; }

        .form-group { position: relative; margin-bottom: 0.75rem; }
        .form-group label {
            display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;
            color: #2c3e50; font-weight: 500; font-size: 0.95rem;
        }
        .form-group label.optional::after { content: ''; }
        .form-group input, .form-group select {
            width: 100%; min-height: var(--touch); padding: 0.65rem 1rem;
            border: 1px solid #d1d5db; border-radius: 10px; font-size: 16px;
            transition: var(--transition-smooth); background: #fff; color: #2c3e50;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #27ae60; outline: none;
            box-shadow: 0 0 0 4px rgba(39,174,96,0.1);
        }
        .form-group input::placeholder { color: #95a5a6; opacity: 0.7; }
        .form-group input:required:valid { border-color: #27ae60; }
        .form-text { font-size: 0.8rem; color: hsl(var(--muted-foreground)); margin-top: 4px; }

        .scanner-card {
            border: 2px dashed hsl(145, 35%, 80%);
            border-radius: 12px;
            padding: 1.25rem 1rem;
            background: hsl(145, 40%, 96%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            min-height: 220px;
            width: 100%;
            margin: 0 auto 1rem;
            box-sizing: border-box;
        }
        .scanner-card.has-file { border-style: solid; border-color: hsl(145, 35%, 75%); }
        .scanner-card .scanner-card-main { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
        .scanner-card .scanner-icon-large { font-size: 2.5rem; color: #3498db; margin-bottom: 0.5rem; }
        .scanner-card .upload-text { font-size: 0.95rem; font-weight: 600; color: #2c3e50; margin: 0; }
        .scanner-card .upload-subtext { font-size: 0.8rem; color: #7f8c8d; margin: 0.25rem 0 0 0; }
        .id-card-preview.has-file { position: relative; width: 100%; min-height: 180px; }
        .id-card-preview.has-file .id-img { max-width: 100%; max-height: 160px; border-radius: 8px; display: block; margin: 0 auto; }
        .id-card-preview.has-file .id-meta { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; justify-content: center; margin-top: 8px; font-size: 12px; color: #7f8c8d; }
        .btn-mini { padding: 6px 10px; border-radius: 8px; border: 0; font-weight: 600; cursor: pointer; font-size: 12px; }
        .btn-mini-ghost { background: rgba(0,0,0,0.1); color: #333; }
        .btn-mini-primary { background: linear-gradient(135deg, #27ae60, #219a52); color: #fff; }
        .scanner-card-actions { display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; width: 100%; }
        .scanner-card-actions button {
            background: #fff;
            color: #2c3e50;
            border: 1px solid #d1d5db;
            padding: 0.65rem 1.25rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            transition: var(--transition-smooth);
        }
        .scanner-card-actions button:hover { background: #f8f9fa; border-color: hsl(var(--primary)); }

        .profile-card {
            border: 2px dashed hsl(var(--border)); border-radius: var(--radius);
            padding: 0.75rem 1rem; background: hsl(var(--card));
            display: flex; flex-direction: column; align-items: center; gap: 0.5rem; margin-bottom: 0.75rem;
        }
        .profile-avatar-wrap {
            width: 72px; height: 72px; border-radius: 9999px;
            border: 2px solid hsl(var(--primary)); overflow: hidden;
            display: flex; align-items: center; justify-content: center;
            background: hsl(var(--muted)); color: hsl(var(--primary)); font-size: 1.8rem;
            box-shadow: 0 4px 15px rgba(39,174,96,0.2);
        }
        .profile-avatar-wrap img { width: 100%; height: 100%; object-fit: cover; }
        .name-row { display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(0, 1.3fr) minmax(0, 0.6fr); gap: 0.75rem; }
        @media (max-width: 380px) { .name-row { grid-template-columns: 1fr; } }

        .radio-group, .likert-scale { display: flex; flex-direction: column; gap: 0.5rem; }
        .radio-option, .likert-option {
            display: flex; align-items: center; gap: 0.5rem; padding: 0.65rem 1rem;
            border: 2px solid hsl(var(--border)); border-radius: 10px; cursor: pointer;
            transition: var(--transition-smooth);
        }
        .radio-option:has(input:checked), .likert-option:has(input:checked) {
            border-color: #27ae60; background: rgba(39,174,96,0.08);
        }
        .radio-option input, .likert-option input { width: 20px; height: 20px; accent-color: #27ae60; }
        .radio-group-horizontal { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .radio-group-horizontal .radio-option { flex: 1; min-width: 80px; }

        .fixed-bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 90;
            display: flex;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            padding-bottom: calc(0.75rem + env(safe-area-inset-bottom, 0));
            background: hsl(var(--card));
            border-top: 1px solid hsl(var(--border));
            box-shadow: 0 -4px 20px rgba(0,0,0,0.08);
        }
        .fixed-bottom-nav .btn-nav {
            min-height: 48px;
            padding: 0 1.25rem;
            border-radius: 12px;
            border: 0;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: var(--transition-smooth);
        }
        .fixed-bottom-nav .btn-prev { background: #e5e7eb; color: #111827; flex: 1; max-width: 140px; }
        .fixed-bottom-nav .btn-next { background: var(--gradient-primary); color: #fff; flex: 1; box-shadow: 0 4px 15px rgba(39,174,96,0.25); }
        .fixed-bottom-nav .btn-next.full-width { max-width: none; flex: 1 1 100%; }
        .fixed-bottom-nav .submit-btn {
            flex: 1;
            min-height: 48px;
            padding: 0 1.25rem;
            border-radius: 12px;
            border: 0;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            background: var(--gradient-primary);
            color: #fff;
            box-shadow: 0 4px 15px rgba(39,174,96,0.25);
        }
        .nav-row { display: none; }
        .btn-nav {
            padding: 0.7rem 1.25rem; border-radius: 10px; border: 0; font-weight: 600; cursor: pointer; font-size: 14px;
            display: inline-flex; align-items: center; gap: 0.5rem; transition: var(--transition-smooth);
        }
        .btn-prev { background: #e5e7eb; color: #111827; }
        .btn-next { background: linear-gradient(135deg, #27ae60 0%, #219a52 100%); color: #fff; box-shadow: 0 4px 15px rgba(39,174,96,0.2); }
        .btn-next:hover { box-shadow: 0 6px 20px rgba(39,174,96,0.3); }
        .submit-btn {
            background: linear-gradient(135deg, #27ae60 0%, #219a52 100%); color: #fff;
            padding: 1rem 1.5rem; border: none; border-radius: 12px; font-size: 1rem; font-weight: 500;
            cursor: pointer; transition: var(--transition-smooth);
            box-shadow: 0 4px 15px rgba(39,174,96,0.2);
            display: inline-flex; align-items: center; gap: 0.5rem;
        }
        .submit-btn:hover { box-shadow: 0 8px 25px rgba(39,174,96,0.3); }
        .error-msg {
            color: #e74c3c; font-size: 0.85rem; margin-top: 0.5rem;
            display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem;
            background: rgba(231, 76, 60, 0.1); border-radius: 6px;
        }
        .consent-text {
            max-width: 100%; margin: 0 auto 1rem; padding: 1rem;
            background: hsl(var(--accent)); border-radius: var(--radius);
            border: 2px solid hsl(var(--primary) / 0.2); font-size: 0.85rem; line-height: 1.6;
            color: hsl(var(--foreground)); box-shadow: var(--shadow-card);
        }
        .consent-group { display: flex; align-items: flex-start; gap: 0.75rem; margin-top: 0.5rem; padding: 0.75rem; background: hsl(var(--card)); border-radius: var(--radius); border: 1px solid hsl(var(--primary) / 0.3); }
        .consent-group input[type="checkbox"] { width: 20px; height: 20px; margin-top: 0.25rem; accent-color: hsl(var(--primary)); cursor: pointer; }
        .consent-group label { font-size: 0.95rem; color: hsl(var(--foreground)); cursor: pointer; flex: 1; }

        #idcam_overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; align-items: center; justify-content: center; padding: 16px; }
        #idcam_overlay.show { display: flex; }
        .idcam-box { background: #fff; border-radius: 16px; padding: 1rem; width: 100%; max-width: 400px; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
        #idcam_video { width: 100%; border-radius: 12px; background: #000; display: block; }
        #idcam_canvas { display: none; }
        .idcam-frame { position: absolute; width: 60%; aspect-ratio: 54/85; top: 50%; left: 50%; transform: translate(-50%, -50%); border: 3px solid rgba(52,152,219,0.95); border-radius: 10px; pointer-events: none; }
        #idcam_status { text-align: center; margin: 8px 0; font-size: 0.9rem; color: #2c3e50; }
    </style>
</head>
<body class="min-h-screen">
    <div class="mobile-shell">
        <header class="sticky-header">
            <div class="header-top">
                <a href="/al_homepage.php" class="header-back" id="headerBack" aria-label="Back"><i class="fas fa-chevron-left"></i></a>
                <div class="header-title">Alumni Registration</div>
                <span style="width:40px;"></span>
            </div>
            <div class="header-step">Step <span id="currentStepNum">1</span> of 5</div>
            <div class="step-dots" role="tablist">
                <div class="step-dot active" data-step="1"><div class="dot-circle"><i class="fas fa-id-card"></i></div><span class="dot-label">Alumni ID</span></div>
                <div class="step-dot" data-step="2"><div class="dot-circle"><i class="fas fa-user"></i></div><span class="dot-label">Personal</span></div>
                <div class="step-dot" data-step="3"><div class="dot-circle"><i class="fas fa-scroll"></i></div><span class="dot-label">Licensure</span></div>
                <div class="step-dot" data-step="4"><div class="dot-circle"><i class="fas fa-briefcase"></i></div><span class="dot-label">Employment</span></div>
                <div class="step-dot" data-step="5"><div class="dot-circle"><i class="fas fa-key"></i></div><span class="dot-label">Account</span></div>
            </div>
            <div class="header-progress"><div class="header-progress-fill" id="progressFill"></div></div>
        </header>

        <main class="scrollable-content" id="scrollableContent">
            <div class="step-title-card">
                <span class="step-icon" id="stepIcon"><i class="fas fa-id-card"></i></span>
                <h2 id="stepTitle">Alumni ID</h2>
                <p id="stepSubtitle">Fill in the details below to continue</p>
            </div>

            <div class="form-container">
            <form id="regForm" method="POST" enctype="multipart/form-data">
                <!-- Step 1: Alumni ID -->
                <div class="step active" data-step="1">

                <div class="scanner-card id-card-preview">
                    <div class="scanner-card-main">
                        <div class="scanner-icon-large"><i class="fas fa-qrcode"></i></div>
                        <p class="upload-text">Alumni ID Scanner</p>
                        <p class="upload-subtext">Scan your Alumni ID to auto-fill your information.</p>
                    </div>
                    <div class="scanner-card-actions">
                        <button type="button" onclick="document.getElementById('id_card').click()"><i class="fas fa-upload"></i> Upload ID</button>
                        <button type="button" onclick="startIdCamera()"><i class="fas fa-camera"></i> Use Camera</button>
                    </div>
                </div>
                <div class="error-msg" id="id-card-error" style="display:none;"><i class="fas fa-exclamation-circle"></i> <span id="id-card-error-text"></span></div>

                <div class="form-group">
                    <label>Alumni ID Number</label>
                    <input type="text" name="alumniIdNumber" id="alumniIdNumber" required placeholder="e.g., 2020-123456">
                </div>
                <div class="form-group">
                    <div class="name-row">
                        <div class="form-group" style="margin-bottom:0;">
                            <label>Last Name</label>
                            <input type="text" name="lastname" id="lastname" required placeholder="Dela Cruz">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label>First Name</label>
                            <input type="text" name="firstname" id="firstname" required placeholder="Juan">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label>M.I.</label>
                            <input type="text" name="middleInitial" id="middleInitial" maxlength="1" placeholder="A">
                        </div>
                    </div>
                </div>

                <div class="profile-card">
                    <div class="profile-avatar-wrap" id="profileAvatar"><i class="fas fa-user"></i></div>
                    <p style="font-size: 0.8rem; color: hsl(var(--muted-foreground)); margin: 0;">Upload or take a profile photo.</p>
                    <div style="display:flex; gap: 0.5rem; flex-wrap: wrap; justify-content: center;">
                        <button type="button" onclick="document.getElementById('photo').click()" style="background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground)); border: 1px solid hsl(var(--border)); padding: 0.4rem 0.9rem; border-radius: var(--radius); font-weight: 500; cursor: pointer; font-size: 0.8rem; display: flex; align-items: center; gap: 0.3rem;"><i class="fas fa-upload"></i> Choose Photo</button>
                        <button type="button" onclick="document.getElementById('photo').click()" style="background: hsl(var(--secondary)); color: hsl(var(--secondary-foreground)); border: 1px solid hsl(var(--border)); padding: 0.4rem 0.9rem; border-radius: var(--radius); font-weight: 500; cursor: pointer; font-size: 0.8rem; display: flex; align-items: center; gap: 0.3rem;"><i class="fas fa-camera"></i> Take Photo</button>
                    </div>
                    <input type="file" name="photo" id="photo" accept="image/*" capture="user" style="display:none;">
                </div>

                <div class="form-group">
                    <label>Campus</label>
                    <select name="campus" id="campus" required>
                        <option value="">Select Campus</option>
                        <option value="Antipolo">Antipolo Campus</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Year Graduated</label>
                    <select name="yearGraduated" id="yearGraduated" required>
                        <option value="">Select year</option>
                        <?php for ($y = date('Y'); $y >= 1990; $y--) echo "<option value=\"$y\">$y</option>"; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>College</label>
                    <select name="college" id="college" required>
                        <option value="">Select college</option>
                        <option value="College of Computer Studies">College of Computer Studies</option>
                        <option value="College of Engineering">College of Engineering</option>
                        <option value="College of Business">College of Business</option>
                        <option value="College of Arts and Sciences">College of Arts and Sciences</option>
                        <option value="College of Education">College of Education</option>
                        <option value="College of Nursing">College of Nursing</option>
                        <option value="College of Medicine">College of Medicine</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Degree</label>
                    <select name="degree" id="degree" required>
                        <option value="">Select college first</option>
                    </select>
                </div>
            </div>

            <!-- Step 2: Personal Info -->
            <div class="step" data-step="2">
                <div class="form-group"><label>Personal Email</label><input type="email" name="personalEmail" id="personalEmail" required placeholder="your.email@gmail.com"></div>
                <div class="form-group"><label>Contact Number</label><input type="tel" name="contactNumber" id="contactNumber" required placeholder="09XX-XXX-XXXX" pattern="[0-9]{11}"></div>
                <div class="form-group"><label>Gender</label>
                    <select name="gender" id="gender" required>
                        <option value="">Select</option><option value="Male">Male</option><option value="Female">Female</option><option value="Prefer not to say">Prefer not to say</option>
                    </select>
                </div>
                <div class="form-group"><label>Civil Status</label>
                    <select name="civilStatus" id="civilStatus" required>
                        <option value="">Select</option><option value="Single">Single</option><option value="Married">Married</option><option value="Widowed">Widowed</option><option value="Separated">Separated</option><option value="Divorced">Divorced</option>
                    </select>
                </div>
            </div>

            <!-- Step 3: Licensure -->
            <div class="step" data-step="3">
                <div class="form-group">
                    <label>Did you pass the Licensure examination?</label>
                    <div class="radio-group">
                        <label class="radio-option"><input type="radio" name="passedLicensure" value="yes" required> <span>Yes</span></label>
                        <label class="radio-option"><input type="radio" name="passedLicensure" value="no"> <span>No</span></label>
                        <label class="radio-option"><input type="radio" name="passedLicensure" value="not_applicable"> <span>Not Applicable</span></label>
                        <label class="radio-option"><input type="radio" name="passedLicensure" value="not_yet"> <span>Not yet / will take later</span></label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Did you enroll in another degree or Masteral studies?</label>
                    <div class="radio-group">
                        <label class="radio-option"><input type="radio" name="enrolledPostGrad" value="yes" required> <span>Yes</span></label>
                        <label class="radio-option"><input type="radio" name="enrolledPostGrad" value="no"> <span>No</span></label>
                        <label class="radio-option"><input type="radio" name="enrolledPostGrad" value="not_applicable"> <span>Not applicable</span></label>
                    </div>
                </div>
            </div>

            <!-- Step 4: Employment -->
            <div class="step" data-step="4">
                <div class="form-group">
                    <label>Are you currently employed?</label>
                    <div class="radio-group-horizontal">
                        <label class="radio-option"><input type="radio" name="currentlyEmployed" value="yes" required> <span>Yes</span></label>
                        <label class="radio-option"><input type="radio" name="currentlyEmployed" value="no"> <span>No</span></label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Months to get first job?</label>
                    <div class="radio-group">
                        <label class="radio-option"><input type="radio" name="monthsToGetJob" value="less_than_1" required> <span>&lt; 1 month</span></label>
                        <label class="radio-option"><input type="radio" name="monthsToGetJob" value="1_to_3"> <span>1-3 months</span></label>
                        <label class="radio-option"><input type="radio" name="monthsToGetJob" value="4_to_6"> <span>4-6 months</span></label>
                        <label class="radio-option"><input type="radio" name="monthsToGetJob" value="more_than_6"> <span>6+ months</span></label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Job aligned with degree?</label>
                    <div class="radio-group">
                        <label class="radio-option"><input type="radio" name="jobAligned" value="strongly_agree" required> <span>Strongly Agree</span></label>
                        <label class="radio-option"><input type="radio" name="jobAligned" value="agree"> <span>Agree</span></label>
                        <label class="radio-option"><input type="radio" name="jobAligned" value="neutral"> <span>Neutral</span></label>
                        <label class="radio-option"><input type="radio" name="jobAligned" value="disagree"> <span>Disagree</span></label>
                        <label class="radio-option"><input type="radio" name="jobAligned" value="strongly_disagree"> <span>Strongly Disagree</span></label>
                    </div>
                </div>
                <div class="form-group">
                    <label>College prepared me well for my job.</label>
                    <div class="radio-group">
                        <label class="radio-option"><input type="radio" name="collegePrepared" value="strongly_agree" required> <span>Strongly Agree</span></label>
                        <label class="radio-option"><input type="radio" name="collegePrepared" value="agree"> <span>Agree</span></label>
                        <label class="radio-option"><input type="radio" name="collegePrepared" value="neutral"> <span>Neutral</span></label>
                        <label class="radio-option"><input type="radio" name="collegePrepared" value="disagree"> <span>Disagree</span></label>
                        <label class="radio-option"><input type="radio" name="collegePrepared" value="strongly_disagree"> <span>Strongly Disagree</span></label>
                    </div>
                </div>
                <div class="form-group">
                    <label>Most important soft skill?</label>
                    <select name="importantSoftSkill" id="importantSoftSkill" required>
                        <option value="">Select</option>
                        <option value="communication">Communication</option>
                        <option value="teamwork">Teamwork</option>
                        <option value="problem_solving">Problem Solving</option>
                        <option value="leadership">Leadership</option>
                        <option value="time_management">Time Management</option>
                        <option value="adaptability">Adaptability</option>
                        <option value="critical_thinking">Critical Thinking</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>I am proud to be an alumni.</label>
                    <div class="radio-group">
                        <label class="radio-option"><input type="radio" name="proudAlumni" value="strongly_agree" required> <span>Strongly Agree</span></label>
                        <label class="radio-option"><input type="radio" name="proudAlumni" value="agree"> <span>Agree</span></label>
                        <label class="radio-option"><input type="radio" name="proudAlumni" value="neutral"> <span>Neutral</span></label>
                        <label class="radio-option"><input type="radio" name="proudAlumni" value="disagree"> <span>Disagree</span></label>
                        <label class="radio-option"><input type="radio" name="proudAlumni" value="strongly_disagree"> <span>Strongly Disagree</span></label>
                    </div>
                </div>
            </div>

            <!-- Step 5: Account -->
            <div class="step" data-step="5">
                <div class="form-group"><label>Email (login)</label><input type="email" name="email" id="email" required placeholder="your.email@example.com"></div>
                <div class="form-group"><label>Password</label><input type="password" name="password" id="password" required placeholder="Min 8 chars, upper, lower, number" style="padding-right: 48px;"></div>
                <div class="form-group"><label>Confirm Password</label><input type="password" name="confirmPassword" id="confirmPassword" required placeholder="Confirm password"></div>
                <div class="consent-text">
                    I hereby consent to the release of all information and records indicated herewith in relevance to the processing of my school credentials and employment. By affixing my signature, I hereby allow the university and its accredited industry partners to use these information for <strong>EMPLOYMENT and RESEARCH PURPOSES</strong> in accordance to the Republic Act No. 10173. This consent shall remain in effect until I revoke it by submitting a written revocation to Our Lady of Fatima University.
                </div>
                <div class="consent-group">
                    <input type="checkbox" name="consent" id="consent" required>
                    <label for="consent">I agree to the terms and conditions <span style="color: hsl(var(--destructive)); margin-left: 0.25rem;">*</span></label>
                </div>
                <p style="text-align:center; margin-top:1.5rem; padding-top:1rem; border-top: 1px solid hsl(var(--border)); font-size: 0.95rem; color: hsl(var(--muted-foreground));">Already have an account? <a href="/al_login.php" style="color: hsl(var(--primary)); font-weight: 600;">Log in to Your Account</a></p>
            </div>
            <input type="file" name="id_card" id="id_card" accept="image/*" capture="environment" style="display:none;">
        </form>
            </div>
        </main>

        <nav class="fixed-bottom-nav">
            <button type="button" class="btn-nav btn-prev" id="navBack" style="display: none;"><i class="fas fa-chevron-left"></i> Back</button>
            <button type="button" class="btn-nav btn-next full-width" id="navNext">Next <i class="fas fa-chevron-right"></i></button>
            <button type="submit" form="regForm" class="submit-btn" id="navSubmit" style="display: none;"><i class="fas fa-check"></i> Create Account</button>
        </nav>
    </div>

    <!-- Camera overlay for ID -->
    <div id="idcam_overlay">
        <div class="idcam-box">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <strong>Capture ID Card</strong>
                <button type="button" onclick="closeIdCamera()" style="background:none; border:none; font-size:1.5rem; cursor:pointer;">&times;</button>
            </div>
            <div style="position:relative; background:#000; border-radius:12px;">
                <video id="idcam_video" autoplay playsinline muted></video>
                <canvas id="idcam_canvas"></canvas>
                <div class="idcam-frame" id="idcam_frame"></div>
            </div>
            <div id="idcam_status">Hold steady...</div>
            <div style="display:flex; gap:8px; justify-content:center; margin-top:8px;">
                <button type="button" class="btn-nav btn-next" onclick="captureIdFromCamera()"><i class="fas fa-camera"></i> Capture</button>
                <button type="button" class="btn-nav btn-prev" onclick="closeIdCamera()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
    (function() {
        var steps = document.querySelectorAll('.step');
        var stepDots = document.querySelectorAll('.step-dot');
        var progressFill = document.getElementById('progressFill');
        var currentStepNum = document.getElementById('currentStepNum');
        var stepTitle = document.getElementById('stepTitle');
        var stepIcon = document.getElementById('stepIcon');
        var scrollableContent = document.getElementById('scrollableContent');
        var headerBack = document.getElementById('headerBack');
        var navBack = document.getElementById('navBack');
        var navNext = document.getElementById('navNext');
        var navSubmit = document.getElementById('navSubmit');
        var current = 0;

        var stepTitles = ['Alumni ID', 'Personal Information', 'Licensure & Post-Graduate', 'Employment Information', 'Create Your Account'];
        var stepIcons = ['<i class="fas fa-id-card"></i>', '<i class="fas fa-user"></i>', '<i class="fas fa-scroll"></i>', '<i class="fas fa-briefcase"></i>', '<i class="fas fa-key"></i>'];

        function updateUI() {
            var progress = ((current + 1) / 5) * 100;
            if (progressFill) progressFill.style.width = progress + '%';
            if (currentStepNum) currentStepNum.textContent = current + 1;
            if (stepTitle) stepTitle.textContent = stepTitles[current];
            if (stepIcon) stepIcon.innerHTML = stepIcons[current];
            stepDots.forEach(function(dot, i) {
                dot.classList.toggle('completed', i < current);
                dot.classList.toggle('active', i === current);
            });
            if (scrollableContent) scrollableContent.scrollTo({ top: 0, behavior: 'smooth' });
            if (navBack) navBack.style.display = current > 0 ? '' : 'none';
            if (navNext) {
                navNext.style.display = current < 4 ? '' : 'none';
                navNext.classList.toggle('full-width', current === 0);
            }
            if (navSubmit) navSubmit.style.display = current === 4 ? '' : 'none';
        }

        function showStep(i) {
            if (i < 0 || i >= steps.length) return;
            current = i;
            steps.forEach(function(s, j) { s.classList.toggle('active', j === i); });
            updateUI();
        }

        if (headerBack) headerBack.addEventListener('click', function(e) { if (current > 0) { e.preventDefault(); showStep(current - 1); } });
        if (navBack) navBack.addEventListener('click', function(e) { e.preventDefault(); showStep(current - 1); });
        if (navNext) navNext.addEventListener('click', function(e) { e.preventDefault(); showStep(current + 1); });
        updateUI();

        // Photo preview
        document.getElementById('photo').addEventListener('change', function(e) {
            var file = e.target.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function(ev) {
                var av = document.getElementById('profileAvatar');
                av.innerHTML = '';
                var img = document.createElement('img');
                img.src = ev.target.result;
                av.appendChild(img);
            };
            reader.readAsDataURL(file);
        });

        // College -> Degree
        var collegeDegrees = {
            'College of Computer Studies': ['Bachelor of Science in Information Technology', 'Bachelor of Science in Computer Science', 'Bachelor of Science in Information Systems'],
            'College of Engineering': ['Bachelor of Science in Civil Engineering', 'Bachelor of Science in Electrical Engineering', 'Bachelor of Science in Mechanical Engineering', 'Bachelor of Science in Computer Engineering'],
            'College of Business': ['Bachelor of Science in Business Administration', 'Bachelor of Science in Accountancy', 'Bachelor of Science in Entrepreneurship'],
            'College of Arts and Sciences': ['Bachelor of Arts in Communication', 'Bachelor of Science in Psychology', 'Bachelor of Science in Biology'],
            'College of Education': ['Bachelor of Elementary Education', 'Bachelor of Secondary Education', 'Bachelor of Physical Education'],
            'College of Nursing': ['Bachelor of Science in Nursing'],
            'College of Medicine': ['Doctor of Medicine']
        };
        document.getElementById('college').addEventListener('change', function() {
            var deg = document.getElementById('degree');
            var list = collegeDegrees[this.value] || [];
            deg.innerHTML = '<option value="">Select Degree</option>';
            list.forEach(function(d) { deg.appendChild(new Option(d, d)); });
        });

        // Password match
        document.getElementById('confirmPassword').addEventListener('input', function() {
            var p = document.getElementById('password').value;
            this.setCustomValidity(this.value && this.value !== p ? 'Passwords do not match' : '');
        });
    })();

    // Alumni ID scanner: upload/camera + OCR autofill
    function handleIdCardUpload(event) {
        var file = event.target.files[0];
        var errorDiv = document.getElementById('id-card-error');
        var errorText = document.getElementById('id-card-error-text');
        var preview = document.querySelector('.id-card-preview');
        if (!file) { errorText.textContent = 'Please select an ID image'; errorDiv.style.display = 'flex'; return; }
        if (!['image/jpeg', 'image/png', 'image/jpg'].includes(file.type)) { errorText.textContent = 'Use JPG or PNG'; errorDiv.style.display = 'flex'; event.target.value = ''; return; }
        if (file.size > 5*1024*1024) { errorText.textContent = 'File must be under 5MB'; errorDiv.style.display = 'flex'; event.target.value = ''; return; }

        var reader = new FileReader();
        reader.onload = function(e) {
            var dataUrl = e.target.result;
            var sizeMb = (file.size / (1024 * 1024)).toFixed(2);
            preview.innerHTML = '<img class="id-img" src="' + dataUrl + '" alt="ID Preview"><div class="id-meta"><span><i class="fas fa-file-image"></i> ' + file.name + ' • ' + sizeMb + ' MB</span><button type="button" class="btn-mini btn-mini-primary" onclick="document.getElementById(\'id_card\').click()"><i class="fas fa-sync-alt"></i> Change</button><button type="button" class="btn-mini btn-mini-ghost" onclick="clearIdPreview()"><i class="fas fa-trash"></i> Remove</button></div>';
            preview.classList.add('has-file');
            var avatar = document.getElementById('profileAvatar');
            var photoInput = document.getElementById('photo');
            if (avatar) { avatar.innerHTML = ''; var im = document.createElement('img'); im.src = dataUrl; im.style.objectFit = 'cover'; avatar.appendChild(im); }
            if (photoInput && window.DataTransfer) { var dt = new DataTransfer(); dt.items.add(file); photoInput.files = dt.files; }
        };
        reader.readAsDataURL(file);

        errorDiv.style.display = 'none';
        var loading = document.createElement('div');
        loading.className = 'form-text';
        loading.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Scanning ID...';
        preview.appendChild(loading);

        Tesseract.recognize(file, 'eng', { logger: function(m) { console.log(m); } })
            .then(function(result) {
                loading.remove();
                var text = (result.data && result.data.text) ? result.data.text : '';
                if (!text.trim()) {
                    errorText.textContent = 'No text detected. Enter details manually.';
                    errorDiv.style.display = 'flex';
                    return;
                }
                var parsed = parseOCRText(text);
                autofillForm(parsed);
            })
            .catch(function(err) {
                loading.remove();
                errorText.textContent = 'OCR error. Enter details manually.';
                errorDiv.style.display = 'flex';
            });
    }

    function parseOCRText(text) {
        var out = { student_number: '', full_name: '', program: '', year_graduated: '' };
        var n = text.replace(/\s+/g, ' ').trim();
        var numMatch = n.match(/\d{4}[- ]?\d{4,8}/);
        if (numMatch) out.student_number = numMatch[0].replace(/\s/g, '-');
        var yearMatch = n.match(/\b(19|20)\d{2}\b/g);
        if (yearMatch) out.year_graduated = yearMatch[yearMatch.length - 1];
        var lines = n.split(/\n/).map(function(l) { return l.trim(); }).filter(Boolean);
        for (var i = 0; i < lines.length; i++) {
            if (lines[i].length > 4 && lines[i].length < 60 && /^[A-Za-z\s.,]+$/.test(lines[i])) {
                out.full_name = lines[i];
                break;
            }
        }
        return out;
    }

    function autofillForm(data) {
        var set = function(id, val) { var el = document.getElementById(id); if (el && val) el.value = val; };
        if (data.student_number) set('alumniIdNumber', data.student_number);
        if (data.year_graduated) set('yearGraduated', data.year_graduated);
        if (data.full_name) {
            var parts = data.full_name.trim().split(/\s+/);
            if (parts.length >= 2) {
                set('lastname', parts[parts.length - 1]);
                set('firstname', parts[0]);
                if (parts.length > 2) set('middleInitial', parts[1][0] || '');
            } else set('lastname', data.full_name);
        }
    }

    function clearIdPreview() {
        var inp = document.getElementById('id_card');
        var pv = document.querySelector('.id-card-preview');
        if (inp) inp.value = '';
        if (pv) {
            pv.classList.remove('has-file');
            pv.innerHTML = '<div class="scanner-card-main"><div class="scanner-icon-large"><i class="fas fa-qrcode"></i></div><p class="upload-text">Alumni ID Scanner</p><p class="upload-subtext">Scan your Alumni ID to auto-fill your information.</p></div><div class="scanner-card-actions"><button type="button" onclick="document.getElementById(\'id_card\').click()"><i class="fas fa-upload"></i> Upload ID</button><button type="button" onclick="startIdCamera()"><i class="fas fa-camera"></i> Use Camera</button></div>';
        }
        var av = document.getElementById('profileAvatar');
        if (av) av.innerHTML = '<i class="fas fa-user"></i>';
        var ph = document.getElementById('photo');
        if (ph) ph.value = '';
    }
    document.getElementById('id_card').addEventListener('change', handleIdCardUpload);

    var idCamStream = null, idAutoTimer = null, idStabilityMs = 0, idLastFrameAvg = null, idAutoCapturing = false;
    function startIdCamera() {
        var overlay = document.getElementById('idcam_overlay');
        var video = document.getElementById('idcam_video');
        var status = document.getElementById('idcam_status');
        overlay.classList.add('show');
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false })
            .then(function(stream) {
                idCamStream = stream;
                video.srcObject = stream;
                idStabilityMs = 0; idLastFrameAvg = null; idAutoCapturing = false;
                status.textContent = 'Hold steady. Auto-capture in ~2s.';
                idAutoTimer = setInterval(checkIdStabilityAndAutoCapture, 100);
            })
            .catch(function(e) { overlay.classList.remove('show'); alert('Camera error: ' + (e.message || e)); });
    }
    function closeIdCamera() {
        document.getElementById('idcam_overlay').classList.remove('show');
        if (idCamStream) { idCamStream.getTracks().forEach(function(t) { t.stop(); }); idCamStream = null; }
        if (idAutoTimer) { clearInterval(idAutoTimer); idAutoTimer = null; }
        document.getElementById('idcam_video').srcObject = null;
    }
    function checkIdStabilityAndAutoCapture() {
        if (!idCamStream || idAutoCapturing) return;
        var video = document.getElementById('idcam_video');
        var canvas = document.getElementById('idcam_canvas');
        var status = document.getElementById('idcam_status');
        if (!video.videoWidth || !video.videoHeight) return;
        canvas.width = 320;
        canvas.height = (video.videoHeight / video.videoWidth) * 320;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        var fw = Math.floor(320 * 0.6), fh = Math.floor(fw * 85/54), fx = (320 - fw)/2, fy = (canvas.height - fh)/2;
        var img = ctx.getImageData(fx, fy, fw, fh).data;
        var sum = 0, c = 0;
        for (var i = 0; i < img.length; i += 4) sum += (0.299*img[i] + 0.587*img[i+1] + 0.114*img[i+2]), c++;
        var avg = sum/c;
        if (idLastFrameAvg === null) { idLastFrameAvg = avg; return; }
        var delta = Math.abs(avg - idLastFrameAvg);
        idLastFrameAvg = avg;
        if (delta < 1.2) {
            idStabilityMs += 100;
            status.textContent = idStabilityMs >= 2000 ? 'Capturing...' : 'Hold steady... ' + Math.ceil((2000 - idStabilityMs)/1000) + 's';
            if (idStabilityMs >= 2000) { idAutoCapturing = true; captureIdFromCamera(); }
        } else { idStabilityMs = 0; status.textContent = 'Hold steady inside the box...'; }
    }
    function captureIdFromCamera() {
        var video = document.getElementById('idcam_video');
        var canvas = document.getElementById('idcam_canvas');
        if (!video.videoWidth || !video.videoHeight) { alert('Camera not ready'); return; }
        var scale = Math.min(1, 1200 / video.videoWidth);
        canvas.width = video.videoWidth * scale;
        canvas.height = video.videoHeight * scale;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        var fw = canvas.width * 0.6, fh = fw * 85/54, fx = (canvas.width - fw)/2, fy = (canvas.height - fh)/2;
        canvas.toBlob(function(blob) {
            if (!blob) return;
            var file = new File([blob], 'captured_id.jpg', { type: 'image/jpeg' });
            var dt = new DataTransfer();
            dt.items.add(file);
            var input = document.getElementById('id_card');
            input.files = dt.files;
            closeIdCamera();
            input.dispatchEvent(new Event('change'));
        }, 'image/jpeg', 0.92);
    }
    </script>

<?php if (!empty($_SESSION['registration_success'])): unset($_SESSION['registration_success']); ?>
    <script>
    (function() {
        var overlay = document.createElement('div');
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;padding:20px;';
        overlay.innerHTML = '<div style="background:#fff;border-radius:16px;padding:24px;text-align:center;max-width:320px;">' +
            '<div style="width:64px;height:64px;background:#d4edda;border-radius:50%;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;"><i class="fas fa-check" style="color:#28a745;font-size:1.5rem;"></i></div>' +
            '<h3 style="margin-bottom:8px;">Registration Submitted!</h3>' +
            '<p style="color:#666;font-size:0.9rem;margin-bottom:20px;">Pending admin approval. Thank you.</p>' +
            '<a href="/al_homepage.php" class="submit-btn" style="text-decoration:none;color:#fff;justify-content:center;">Continue to Home</a></div>';
        document.body.appendChild(overlay);
    })();
    </script>
<?php endif; ?>
</body>
</html>
