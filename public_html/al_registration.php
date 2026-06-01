<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once 'db_config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$isLoggedIn = isset($_SESSION['user_id']);
$registrationType = strtolower(trim((string) ($_GET['type'] ?? $_POST['registration_type'] ?? 'new')));
if ($registrationType !== 'legacy') {
    $registrationType = 'new';
}

$max_retries = 3;
$retry_delay = 2;

function connectWithRetry($max_retries, $retry_delay) {
    $attempt = 0; $last_error = '';
    while ($attempt < $max_retries) {
        try {
            $conn = getDBConnection();
            if (defined('MYSQLI_OPT_CONNECT_TIMEOUT')) $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 300);
            $conn->query("SET SESSION wait_timeout=300");
            $conn->query("SET SESSION interactive_timeout=300");
            return $conn;
        } catch (Exception $e) {
            $last_error = $e->getMessage(); $attempt++;
            if ($attempt < $max_retries) { sleep($retry_delay); continue; }
        }
    }
    die("Database connection failed after $max_retries attempts. Last error: $last_error");
}

try {
    $conn = connectWithRetry($max_retries, $retry_delay);
    if (!$conn) throw new Exception("Connection returned null");
} catch (Exception $e) {
    echo "<!DOCTYPE html><html><head><title>Database Error</title></head><body>";
    echo "<h1 style='color:red;padding:20px;'>Database Connection Error</h1>";
    echo "<p style='padding:20px;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</body></html>"; exit;
}

function checkConnection($conn) {
    if (!$conn->ping()) {
        $conn->close();
        try { $conn = connectWithRetry($GLOBALS['max_retries'], $GLOBALS['retry_delay']); }
        catch (Exception $e) { die("Reconnection failed: " . $e->getMessage()); }
    }
    return $conn;
}

/* ── ID CARD SCAN AJAX ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['id_card']) && isset($_POST['ajax']) && $_POST['ajax'] === '1') {
    header('Content-Type: application/json');
    try {
        if (!isset($_FILES['id_card']) || $_FILES['id_card']['error'] !== UPLOAD_ERR_OK) throw new Exception("Please upload a valid ID card image");
        $file = $_FILES['id_card'];
        if (!in_array($file['type'], ['image/jpeg','image/png','image/jpg'])) throw new Exception("Please upload a JPG or PNG image");
        if ($file['size'] > 5 * 1024 * 1024) throw new Exception("File size must be less than 5MB");
        $upload_dir = 'uploads/id_cards';
        if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
        $filename = uniqid() . '_id_card.' . pathinfo($file['name'], PATHINFO_EXTENSION);
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

/* ── DUPLICATE CHECK AJAX ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === 'check_duplicate') {
    header('Content-Type: application/json');
    try {
        $conn = checkConnection($conn);
        $type = $_POST['type'] ?? ''; $value = trim($_POST['value'] ?? '');
        if (empty($type) || empty($value)) { echo json_encode(['success'=>false,'message'=>'Invalid parameters']); exit; }
        $exists = false; $message = '';
        if ($type === 'email') {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM itcp WHERE email = ?");
            $stmt->bind_param("s", $value); $stmt->execute();
            $row = olfu_stmt_get_result($stmt)->fetch_assoc();
            $exists = $row && $row['count'] > 0;
            $message = $exists ? 'This email address is already registered.' : '';
            $stmt->close();
        } elseif ($type === 'alumni_id') {
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM itcp WHERE student_number = ?");
            $stmt->bind_param("s", $value); $stmt->execute();
            $row = olfu_stmt_get_result($stmt)->fetch_assoc();
            $exists = $row && $row['count'] > 0;
            $message = $exists ? 'This Student ID number is already registered.' : '';
            $stmt->close();
        } elseif ($type === 'name') {
            $data = json_decode($value, true);
            $fname = trim($data['firstname'] ?? ''); $lname = trim($data['lastname'] ?? ''); $mi = trim($data['middleInitial'] ?? '');
            if (!empty($fname) && !empty($lname)) {
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM itcp WHERE firstname = ? AND lastname = ? AND middlename = ?");
                $stmt->bind_param("sss", $fname, $lname, $mi); $stmt->execute();
                $row = olfu_stmt_get_result($stmt)->fetch_assoc();
                $exists = $row && $row['count'] > 0;
                $message = $exists ? 'An account with this name is already registered.' : '';
                $stmt->close();
            }
        }
        echo json_encode(['success'=>true,'exists'=>$exists,'message'=>$message]);
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'message'=>'Error checking for duplicates']);
    }
    exit;
}

function processIdCard($imagePath) {
    return ['firstname'=>'','lastname'=>'','middlename'=>'','email'=>'','student_number'=>'','birthday'=>'','address'=>'','nationality'=>'Filipino'];
}

/**
 * JSON response for AJAX registration (no full page reload on validation/duplicate errors).
 */
function registration_json_fail(string $message): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function registration_json_ok(): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => true]);
    exit;
}

/* ── FULL REGISTRATION SUBMIT ── */
$photo_file = $_FILES['photo'] ?? $_FILES['profile_photo'] ?? null;
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $photo_file && !empty($photo_file['name']) && (isset($_POST['firstname']) || isset($_POST['full_name']))) {
    $ajaxReg = isset($_POST['ajax_registration']) && $_POST['ajax_registration'] === '1';
    try {
        $conn = checkConnection($conn);
        $email = trim($_POST['email'] ?? $_POST['personalEmail'] ?? '');
        $alumniIdNumber = trim($_POST['alumniIdNumber'] ?? '');
        $currentAlumniId = trim((string) ($_POST['current_alumni_id_number'] ?? ''));
        $registrationType = strtolower(trim((string) ($_POST['registration_type'] ?? 'new')));
        if ($registrationType !== 'legacy') {
            $registrationType = 'new';
        }
        $fname = trim($_POST['firstname'] ?? '');
        $lname = trim($_POST['lastname'] ?? '');
        $mi = trim($_POST['middleInitial'] ?? '');
        $duplicate_errors = [];
        if ($email !== '') {
            $lp = strrpos(strtolower($email), '@');
            $dom = ($lp !== false) ? strtolower(substr($email, $lp + 1)) : '';
            if ($dom !== 'gmail.com' && $dom !== 'yahoo.com') {
                $duplicate_errors[] = 'Personal email must use @gmail.com or @yahoo.com only.';
            }
            $esc_email = $conn->real_escape_string($email);
            $qr = @$conn->query("SELECT COUNT(*) AS c FROM itcp WHERE LOWER(TRIM(email)) = LOWER('$esc_email') LIMIT 1");
            if ($qr) { $row = $qr->fetch_assoc(); if ((int)($row['c'] ?? 0) > 0) $duplicate_errors[] = "This email address is already registered."; $qr->close(); }
        }
        if ($alumniIdNumber !== '') {
            if ($registrationType === 'legacy') {
                $legacyDigits = preg_replace('/\D/', '', $alumniIdNumber);
                if ($legacyDigits === '' || strlen($legacyDigits) > 16) {
                    $duplicate_errors[] = 'Alumni ID Number must be numbers only and up to 16 digits.';
                }
            }
            $norm_sn = preg_replace('/[\s\-]+/', '', $alumniIdNumber);
            $qr = @$conn->query("SELECT id, student_number FROM itcp WHERE TRIM(student_number) != ''");
            $found_dup = false;
            if ($qr) { while ($r = $qr->fetch_assoc()) { if (strcasecmp(preg_replace('/[\s\-]+/','',trim($r['student_number']??'')), $norm_sn) === 0) { $found_dup = true; break; } } $qr->close(); }
            if ($found_dup) $duplicate_errors[] = ($registrationType === 'legacy') ? "This Alumni ID number is already registered." : "This Student ID number is already registered.";
        } elseif ($registrationType === 'legacy') {
            $duplicate_errors[] = 'Alumni ID Number is required for legacy registration.';
        }
        if ($fname !== '' && $lname !== '') {
            $esc_f = $conn->real_escape_string($fname); $esc_l = $conn->real_escape_string($lname); $esc_m = $conn->real_escape_string($mi);
            $qr = @$conn->query("SELECT COUNT(*) AS c FROM itcp WHERE TRIM(firstname)='$esc_f' AND TRIM(lastname)='$esc_l' AND TRIM(COALESCE(middlename,''))='$esc_m' LIMIT 1");
            if ($qr) { $row = $qr->fetch_assoc(); if ((int)($row['c'] ?? 0) > 0) $duplicate_errors[] = "An account with this name is already registered."; $qr->close(); }
        }
        $pc_check = preg_replace('/\D/', '', (string)($_POST['personal_contact'] ?? $_POST['contactNumber'] ?? ''));
        if (strlen($pc_check) < 10 || strlen($pc_check) > 11) {
            $duplicate_errors[] = 'Contact number must be 10–11 digits (numbers only).';
        }
        $ec_check = preg_replace('/\D/', '', (string)($_POST['emergency_contact'] ?? ''));
        if ($ec_check !== '' && (strlen($ec_check) < 10 || strlen($ec_check) > 11)) {
            $duplicate_errors[] = 'Emergency contact must be 10–11 digits or left blank (numbers only).';
        }
        if ($registrationType === 'legacy' && $currentAlumniId === '') {
            $currentAlumniId = $alumniIdNumber;
        }
        if ($registrationType === 'legacy') {
            $legacyDigits = preg_replace('/\D/', '', $currentAlumniId);
            if (strlen((string) $legacyDigits) !== 16) {
                $duplicate_errors[] = 'Current Alumni ID Number must be a 16-digit card number.';
            }
        }
        if (!empty($duplicate_errors)) {
            $msg = implode(' ', $duplicate_errors);
            if (!empty($ajaxReg)) {
                registration_json_fail($msg);
            }
            $_SESSION['registration_error'] = $msg;
            header('Location: ' . $_SERVER['PHP_SELF'] . '?error=' . urlencode($_SESSION['registration_error'])); exit;
        }
    } catch (Exception $e) {
        $msg = 'Error validating registration data: ' . $e->getMessage();
        if (!empty($ajaxReg)) {
            registration_json_fail($msg);
        }
        $_SESSION['registration_error'] = $msg;
        header('Location: ' . $_SERVER['PHP_SELF'] . '?error=' . urlencode($_SESSION['registration_error'])); exit;
    }

    $upload_dir = 'uploads';
    if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
    $ht_root = __DIR__ . '/uploads/.htaccess';
    if (!file_exists($ht_root)) @file_put_contents($ht_root, "Require all denied\nOptions -Indexes\n");
    $ids_dir = $upload_dir . '/ids';
    if (!file_exists($ids_dir)) mkdir($ids_dir, 0777, true);
    $ht_ids = __DIR__ . '/uploads/ids/.htaccess';
    if (!file_exists($ht_ids)) @file_put_contents($ht_ids, "Require all denied\nOptions -Indexes\n");

    $allowed_types = ['image/jpeg','image/png','image/gif'];
    $detected_mime = null;
    if (function_exists('finfo_open')) { $finfo = finfo_open(FILEINFO_MIME_TYPE); $detected_mime = finfo_file($finfo, $photo_file['tmp_name']); finfo_close($finfo); }
    elseif (function_exists('mime_content_type')) { $detected_mime = mime_content_type($photo_file['tmp_name']); }
    else { $detected_mime = $photo_file['type'] ?? 'application/octet-stream'; }
    if (!in_array($detected_mime, $allowed_types)) {
        if (!empty($ajaxReg)) {
            registration_json_fail('Only JPG, PNG and GIF files are allowed for your profile photo.');
        }
        die("Error: Only JPG, PNG and GIF files are allowed.");
    }
    if ($photo_file['size'] > 5 * 1024 * 1024) {
        if (!empty($ajaxReg)) {
            registration_json_fail('Profile photo must be less than 5MB.');
        }
        die("Error: File size must be less than 5MB.");
    }
    $ext_map = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif'];
    $safe_ext = $ext_map[$detected_mime] ?? 'jpg';
    $photo_name = bin2hex(random_bytes(16)) . '.' . $safe_ext;
    $photo_path = $upload_dir . '/' . $photo_name;
    if (!move_uploaded_file($photo_file['tmp_name'], $photo_path)) {
        if (!empty($ajaxReg)) {
            registration_json_fail('Failed to upload profile photo. Please try again.');
        }
        die("Failed to upload photo.");
    }

    $id_image_name = null;
    $id_image_back_name = null;
    $idDocLabel = $registrationType === 'legacy' ? 'Alumni Card' : 'Student ID';
    $id_front_ok = isset($_FILES['id_card']) && !empty($_FILES['id_card']['name']) && $_FILES['id_card']['error'] === UPLOAD_ERR_OK;
    if (!$id_front_ok) {
        if (!empty($ajaxReg)) {
            registration_json_fail('Please upload the front of your ' . $idDocLabel . '.');
        }
        die("Error: Please upload the front of your " . $idDocLabel . ".");
    }

    $f = $_FILES['id_card'];
    if (!in_array($f['type'], ['image/jpeg','image/png','image/jpg'])) {
        if (!empty($ajaxReg)) {
            registration_json_fail($idDocLabel . ' front image must be JPG or PNG.');
        }
        die("Error: " . $idDocLabel . " front image must be JPG or PNG.");
    }
    if ($f['size'] > 5 * 1024 * 1024) {
        if (!empty($ajaxReg)) {
            registration_json_fail($idDocLabel . ' front image must be less than 5MB.');
        }
        die("Error: " . $idDocLabel . " front image must be less than 5MB.");
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png'])) $ext = ($f['type']==='image/png'?'png':'jpg');
    $id_image_name = 'id_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $ids_dir . '/' . $id_image_name)) {
        if (!empty($ajaxReg)) {
            registration_json_fail('Failed to upload ' . $idDocLabel . ' front image. Please try again.');
        }
        die("Failed to upload " . $idDocLabel . " front image.");
    }

    if ($registrationType === 'legacy') {
        $id_back_ok = isset($_FILES['id_card_back']) && !empty($_FILES['id_card_back']['name']) && $_FILES['id_card_back']['error'] === UPLOAD_ERR_OK;
        if (!$id_back_ok) {
            if (!empty($ajaxReg)) {
                registration_json_fail('Please upload the back of your ' . $idDocLabel . '.');
            }
            die("Error: Please upload the back of your " . $idDocLabel . ".");
        }
        $b = $_FILES['id_card_back'];
        if (!in_array($b['type'], ['image/jpeg','image/png','image/jpg'])) {
            if (!empty($ajaxReg)) {
                registration_json_fail($idDocLabel . ' back image must be JPG or PNG.');
            }
            die("Error: " . $idDocLabel . " back image must be JPG or PNG.");
        }
        if ($b['size'] > 5 * 1024 * 1024) {
            if (!empty($ajaxReg)) {
                registration_json_fail($idDocLabel . ' back image must be less than 5MB.');
            }
            die("Error: " . $idDocLabel . " back image must be less than 5MB.");
        }
        $bext = strtolower(pathinfo($b['name'], PATHINFO_EXTENSION));
        if (!in_array($bext, ['jpg','jpeg','png'])) $bext = ($b['type']==='image/png'?'png':'jpg');
        $id_image_back_name = 'id_back_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $bext;
        if (!move_uploaded_file($b['tmp_name'], $ids_dir . '/' . $id_image_back_name)) {
            if (!empty($ajaxReg)) {
                registration_json_fail('Failed to upload ' . $idDocLabel . ' back image. Please try again.');
            }
            die("Failed to upload " . $idDocLabel . " back image.");
        }
    }

    $alumniIdNumber=$_POST['alumniIdNumber']??''; $lastname=$_POST['lastname']??''; $firstname=$_POST['firstname']??''; $middleInitial=$_POST['middleInitial']??'';
    $campus='Antipolo City';
    $yearGraduated=$_POST['yearGraduated']??''; $college=$_POST['college']??''; $degree=$_POST['degree']??'';
    $month_graduated=$_POST['month_graduated']??''; $personalEmail=trim($_POST['personalEmail']??'');
    $personal_contact=preg_replace('/\D/','',(string)($_POST['personal_contact']??$_POST['contactNumber']??''));
    $gender=$_POST['gender']??''; $civilStatus=$_POST['civil_status']??$_POST['civilStatus']??''; $name_ext=$_POST['name_ext']??'';
    $birthday=$_POST['birthday']??'';
    $age=(int)($_POST['age']??0);
    if($birthday!==''&&preg_match('/^\d{4}-\d{2}-\d{2}$/',$birthday)&&$birthday!=='0000-00-00'){
      $bd=DateTime::createFromFormat('Y-m-d',$birthday);
      if($bd&&$bd->format('Y-m-d')===$birthday){
        $age=(int)$bd->diff(new DateTime('today'))->y;
      }
    }
    $religion=$_POST['religion']??''; $nationality=$_POST['nationality']??'Filipino';
    $address=$_POST['address']??''; $emergency_contact=preg_replace('/\D/','',(string)($_POST['emergency_contact']??'')); $passedLicensure=$_POST['passedLicensure']??'';
    $enrolledPostGrad=$_POST['enrolledPostGrad']??''; $licensure_exam=$_POST['licensure_exam']??$passedLicensure;
    $club_involvement=$_POST['club_involvement']??''; $employment_status=$_POST['employment_status']??''; $company=$_POST['company']??'';
    $industry=$_POST['industry']??''; $position=$_POST['position']??''; $employment_history=$_POST['employment_history']??'';
    $previous_role=$_POST['previous_role']??''; $length_of_service=$_POST['length_of_service']??''; $monthsToGetJob=$_POST['monthsToGetJob']??'';
    $jobAligned=$_POST['jobAligned']??''; $collegePrepared=$_POST['collegePrepared']??''; $importantSoftSkill=$_POST['importantSoftSkill']??'';
    $proudAlumni=$_POST['proudAlumni']??''; $email=$_POST['email']??$personalEmail??'';
    $password=isset($_POST['password'])?password_hash($_POST['password'],PASSWORD_DEFAULT):''; $consent=isset($_POST['consent'])?1:0;
    if ($registrationType === 'legacy') {
        $alumniIdNumber = substr(preg_replace('/\D/', '', $alumniIdNumber), 0, 16);
    }
    $student_number=$alumniIdNumber; $middlename=$middleInitial; $year_graduated=$yearGraduated; $program=$degree; $post_grad=$enrolledPostGrad;
    if($birthday==='')$birthday='0000-00-00';
    $full_name=trim($_POST['full_name']??'');
    if($full_name==='')$full_name=implode(' ',array_filter([$firstname,$middlename,$lastname]));

    try {
        $conn = checkConnection($conn);
        require_once __DIR__ . '/includes/cps_alumni_lib.php';
        cps_ensure_schema($conn);
        $field_map=['photo'=>['value'=>$photo_name,'type'=>'s'],'lastname'=>['value'=>$lastname,'type'=>'s'],'firstname'=>['value'=>$firstname,'type'=>'s'],'middlename'=>['value'=>$middlename,'type'=>'s'],'name_ext'=>['value'=>$name_ext,'type'=>'s'],'birthday'=>['value'=>$birthday,'type'=>'s'],'age'=>['value'=>$age,'type'=>'i'],'gender'=>['value'=>$gender,'type'=>'s'],'civil_status'=>['value'=>$civilStatus,'type'=>'s'],'religion'=>['value'=>$religion,'type'=>'s'],'nationality'=>['value'=>$nationality,'type'=>'s'],'email'=>['value'=>$email,'type'=>'s'],'address'=>['value'=>$address,'type'=>'s'],'personal_contact'=>['value'=>$personal_contact,'type'=>'s'],'emergency_contact'=>['value'=>$emergency_contact,'type'=>'s'],'student_number'=>['value'=>$student_number,'type'=>'s'],'program'=>['value'=>$program,'type'=>'s'],'campus'=>['value'=>$campus,'type'=>'s'],'month_graduated'=>['value'=>$month_graduated,'type'=>'s'],'year_graduated'=>['value'=>$year_graduated,'type'=>'s'],'post_grad'=>['value'=>$post_grad,'type'=>'s'],'licensure_exam'=>['value'=>$licensure_exam,'type'=>'s'],'club_involvement'=>['value'=>$club_involvement,'type'=>'s'],'employment_status'=>['value'=>$employment_status,'type'=>'s'],'company'=>['value'=>$company,'type'=>'s'],'industry'=>['value'=>$industry,'type'=>'s'],'position'=>['value'=>$position,'type'=>'s'],'employment_history'=>['value'=>$employment_history,'type'=>'s'],'previous_role'=>['value'=>$previous_role,'type'=>'s'],'length_of_service'=>['value'=>$length_of_service,'type'=>'s'],'consent'=>['value'=>$consent,'type'=>'i'],'password'=>['value'=>$password,'type'=>'s'],'status'=>['value'=>'Pending','type'=>'s'],'college'=>['value'=>$college,'type'=>'s'],'months_to_get_job'=>['value'=>$monthsToGetJob,'type'=>'s'],'job_aligned'=>['value'=>$jobAligned,'type'=>'s'],'college_prepared'=>['value'=>$collegePrepared,'type'=>'s'],'important_soft_skill'=>['value'=>$importantSoftSkill,'type'=>'s'],'proud_alumni'=>['value'=>$proudAlumni,'type'=>'s']];
        $existingCols=[];
        $colsRes=@$conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='itcp'");
        if($colsRes){while($row=$colsRes->fetch_assoc()){$d=$row['COLUMN_NAME'];$existingCols[strtolower($d)]=$d;$existingCols[strtolower(str_replace('_','',$d))]=$d;}$colsRes->free();}
        $filtered=[];
        foreach($field_map as $col=>$data){$cl=strtolower($col);$cn=str_replace('_','',$cl);$db=$existingCols[$cl]??$existingCols[$cn]??null;if($db!==null)$filtered[$db]=$data;}
        if(empty($filtered))throw new Exception("No matching columns for itcp table.");
        $columns=array_keys($filtered);$types='';$values=[];
        foreach($filtered as $f){$types.=$f['type'];$values[]=$f['value'];}
        $sql="INSERT INTO itcp (".implode(', ',$columns).") VALUES (".implode(', ',array_fill(0,count($columns),'?')).")";
        $stmt=$conn->prepare($sql);if(!$stmt)throw new Exception("Prepare failed: ".$conn->error);
        $stmt->bind_param($types,...$values);$conn=checkConnection($conn);
        if($stmt->execute()){
            $new_itcp_id = (int)$conn->insert_id;
            $full_name_for_min=$full_name?:implode(' ',array_filter([$firstname,$middlename,$lastname]));
            $grad_year_for_min=is_numeric($year_graduated)?(int)$year_graduated:null;
            $has_back_col=false;
            $colCheck=$conn->query("SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='alumni_registration' AND COLUMN_NAME='id_image_back'");
            if($colCheck){$cr=$colCheck->fetch_assoc();$has_back_col=isset($cr['c'])&&(int)$cr['c']>0;$colCheck->close();}
            if(!$has_back_col&&$id_image_back_name){if($conn->query("ALTER TABLE alumni_registration ADD COLUMN id_image_back VARCHAR(255) NULL AFTER id_image"))$has_back_col=true;}
            $store_back=($id_image_back_name!==null&&$id_image_back_name!=='')&&$has_back_col;
            $sqlMin=$store_back?"INSERT INTO alumni_registration (student_number, name, course, grad_year, id_image, id_image_back) VALUES (?,?,?,?,?,?)":"INSERT INTO alumni_registration (student_number, name, course, grad_year, id_image) VALUES (?,?,?,?,?)";
            $minStmt=$conn->prepare($sqlMin);if(!$minStmt)throw new Exception("Failed to prepare alumni_registration insert: ".$conn->error);
            $null=null;
            if($store_back){if($grad_year_for_min===null)$minStmt->bind_param("sssiss",$student_number,$full_name_for_min,$program,$null,$id_image_name,$id_image_back_name);else $minStmt->bind_param("sssiss",$student_number,$full_name_for_min,$program,$grad_year_for_min,$id_image_name,$id_image_back_name);}
            else{if($grad_year_for_min===null)$minStmt->bind_param("sssis",$student_number,$full_name_for_min,$program,$null,$id_image_name);else $minStmt->bind_param("sssis",$student_number,$full_name_for_min,$program,$grad_year_for_min,$id_image_name);}
            if(!$minStmt->execute())throw new Exception("Failed to insert into alumni_registration: ".$minStmt->error);
            $minStmt->close();
            if ($new_itcp_id > 0) {
                $dob_ins = ($birthday !== '' && $birthday !== '0000-00-00' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) ? $birthday : null;
                $cc_ins = substr((string)$campus, 0, 32);
                $pr = $conn->prepare("INSERT INTO pending_registrations (student_id, itcp_id, registration_type, current_alumni_id, date_of_birth, campus_code, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
                if ($pr) {
                    $legacyIdIns = $registrationType === 'legacy' ? $currentAlumniId : null;
                    $pr->bind_param('sissss', $student_number, $new_itcp_id, $registrationType, $legacyIdIns, $dob_ins, $cc_ins);
                    if (!$pr->execute()) {
                        error_log('pending_registrations insert skipped: ' . $pr->error);
                    }
                    $pr->close();
                }
            }
            $_SESSION['registration_success']=true;
            if (!empty($ajaxReg)) {
                registration_json_ok();
            }
        }else{throw new Exception("Execute failed: ".$stmt->error);}
    }catch(Exception $e){
        if (!empty($ajaxReg)) {
            registration_json_fail($e->getMessage());
        }
        $_SESSION['registration_error']=$e->getMessage();
        header('Location: '.$_SERVER['PHP_SELF'].'?error='.urlencode($e->getMessage()));exit;
    }finally{if(isset($stmt))$stmt->close();if(isset($conn))$conn->close();}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Alumni Registration — OLFU CCS</title>

<!-- Fonts matching homepage -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700;900&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@4/dist/tesseract.min.js"></script>

<style>
/* ═══════════════════════════════════════
   DESIGN TOKENS — matches homepage
═══════════════════════════════════════ */
:root {
  --forest:   #0a4a1e;
  --emerald:  #1a7a3a;
  --leaf:     #2ea855;
  --mint:     #d4f0dc;
  --cream:    #faf8f3;
  --warm:     #f2ede6;
  --ink:      #111916;
  --muted:    #5a6b61;
  --gold:     #c9a84c;
  --gold-lt:  #f0d98a;
  --white:    #ffffff;
  --red:      #dc2626;
  --red-lt:   #fef2f2;
  --amber:    #d97706;
  --amber-lt: #fffbeb;
  --border:   rgba(10,74,30,.12);
  --shadow-sm:0 2px 8px rgba(10,74,30,.07);
  --shadow-md:0 8px 28px rgba(10,74,30,.11);
  --shadow-lg:0 20px 56px rgba(10,74,30,.16);
  --radius:   12px;
  --radius-lg:18px;
}

/* ═══════════════════════════════════════
   RESET & BASE
═══════════════════════════════════════ */
*,*::before,*::after { box-sizing:border-box; margin:0; padding:0; }
html { font-size:16px; height:100%; overflow:hidden; }
body {
  height:100%;
  margin:0;
  font-family:'DM Sans',sans-serif;
  background: var(--cream);
  color: var(--ink);
  -webkit-font-smoothing: antialiased;
  overflow:hidden;
}

/* Grain texture overlay matching homepage hero */
body::before {
  content:'';
  position:fixed;
  inset:0;
  background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.025'/%3E%3C/svg%3E");
  pointer-events:none;
  z-index:0;
}

/* Soft green radial from top-right */
body::after {
  content:'';
  position:fixed;
  top:-200px; right:-200px;
  width:600px; height:600px;
  border-radius:50%;
  background:radial-gradient(circle, rgba(46,168,85,.07) 0%, transparent 65%);
  pointer-events:none;
  z-index:0;
}

/* ═══════════════════════════════════════
   PAGE LAYOUT
═══════════════════════════════════════ */
.reg-page {
  position:relative;
  z-index:1;
  height:100%;
  min-height:100vh;
  min-height:100dvh;
  display:flex;
  flex-direction:column;
  overflow:hidden;
}

/* ═══════════════════════════════════════
   HEADER BAR
═══════════════════════════════════════ */
.reg-header {
  position:sticky;
  top:0;
  z-index:100;
  background:rgba(250,248,243,.88);
  backdrop-filter:blur(20px);
  -webkit-backdrop-filter:blur(20px);
  border-bottom:1px solid var(--border);
  padding:0 32px;
  height:60px;
  display:flex;
  align-items:center;
  justify-content:space-between;
}
.reg-header::before {
  content:'';
  position:absolute;
  top:0; left:0; right:0;
  height:3px;
  background:linear-gradient(90deg, var(--forest), var(--gold), var(--leaf), var(--gold), var(--forest));
  background-size:300% 100%;
  animation:shimmerBar 6s linear infinite;
}
@keyframes shimmerBar {
  0%   { background-position:0% 50%; }
  100% { background-position:300% 50%; }
}
.hdr-brand {
  display:flex;
  align-items:center;
  gap:10px;
  text-decoration:none;
}
.hdr-logo-box {
  width:32px; height:32px;
  background:var(--forest);
  border-radius:8px;
  display:flex; align-items:center; justify-content:center;
  flex-shrink:0;
}
.hdr-logo-box i { color:var(--gold-lt); font-size:14px; }
.hdr-wordmark-title {
  font-family:'DM Sans',sans-serif;
  font-size:.8rem;
  font-weight:700;
  color:var(--forest);
  line-height:1.2;
}
.hdr-wordmark-sub {
  font-family:'DM Mono',monospace;
  font-size:.58rem;
  letter-spacing:.12em;
  text-transform:uppercase;
  color:var(--muted);
}
.hdr-login-link {
  display:inline-flex;
  align-items:center;
  gap:7px;
  font-size:.78rem;
  font-weight:600;
  color:var(--forest);
  border:1.5px solid var(--border);
  background:white;
  padding:6px 14px;
  border-radius:100px;
  text-decoration:none;
  transition:background .2s, border-color .2s;
}
.hdr-login-link:hover { background:var(--mint); border-color:var(--leaf); }

/* ═══════════════════════════════════════
   MAIN WRAPPER
═══════════════════════════════════════ */
.reg-main {
  flex:1;
  min-height:0;
  display:flex;
  align-items:stretch;
  justify-content:center;
  padding:14px 16px 12px;
  overflow:hidden;
}
.reg-shell {
  width:100%;
  max-width:1120px;
  display:flex;
  flex-direction:column;
  min-height:0;
  flex:1;
  overflow:hidden;
}
/* Form column: only this region scrolls (steps / “registration” content) */
.reg-form-layout {
  display:flex;
  flex-direction:column;
  flex:1;
  min-height:0;
  overflow:hidden;
}
.reg-form-scroll {
  flex:1;
  min-height:0;
  overflow-y:hidden;
  overflow-x:hidden;
  overscroll-behavior:contain;
  -webkit-overflow-scrolling:touch;
  padding-right:0;
  margin-right:0;
  scrollbar-width:thin;
  scrollbar-color:rgba(10,74,30,.35) transparent;
}
.reg-form-scroll.needs-scroll {
  overflow-y:auto;
  padding-right:6px;
  margin-right:-2px;
}
.reg-form-scroll::-webkit-scrollbar { width:8px; }
.reg-form-scroll::-webkit-scrollbar-thumb {
  background:rgba(10,74,30,.28);
  border-radius:8px;
}
.reg-form-scroll::-webkit-scrollbar-track { background:transparent; }
.reg-nav-outer {
  flex-shrink:0;
  padding-top:12px;
  padding-bottom:4px;
  background:linear-gradient(to top, var(--cream) 70%, transparent);
}

/* ═══════════════════════════════════════
   PAGE TITLE
═══════════════════════════════════════ */
.reg-title-block {
  text-align:center;
  margin-bottom:10px;
  flex-shrink:0;
  animation: fadeUp .6s .05s both;
}
.reg-eyebrow {
  display:inline-flex;
  align-items:center;
  gap:6px;
  font-family:'DM Mono',monospace;
  font-size:.55rem;
  letter-spacing:.16em;
  text-transform:uppercase;
  color:var(--emerald);
  margin-bottom:6px;
}
.reg-eyebrow::before {
  content:'';
  display:block;
  width:24px; height:1.5px;
  background:var(--gold);
  border-radius:2px;
}
.reg-eyebrow::after {
  content:'';
  display:block;
  width:24px; height:1.5px;
  background:var(--gold);
  border-radius:2px;
}
.reg-h1 {
  font-family:'Playfair Display',serif;
  font-size:clamp(1.15rem,2.4vw,1.55rem);
  font-weight:800;
  color:var(--forest);
  letter-spacing:-.02em;
  line-height:1.15;
  margin-bottom:4px;
}
.reg-subtitle {
  font-size:.78rem;
  color:var(--muted);
  line-height:1.5;
  max-width:52em;
  margin-left:auto;
  margin-right:auto;
}

/* ═══════════════════════════════════════
   PROGRESS STEPPER
═══════════════════════════════════════ */
.stepper {
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  position:relative;
  margin-bottom:8px;
  padding:0 4px;
  flex-shrink:0;
  animation:fadeUp .6s .1s both;
}
.stepper-line-bg {
  position:absolute;
  top:17px; left:8%; right:8%;
  height:2px;
  background:var(--border);
  z-index:0;
}
.stepper-line-fill {
  position:absolute;
  top:17px; left:8%;
  height:2px;
  background:linear-gradient(90deg, var(--forest), var(--leaf));
  z-index:1;
  transition:width .5s cubic-bezier(.4,0,.2,1);
  border-radius:2px;
}
.step-node {
  display:flex;
  flex-direction:column;
  align-items:center;
  gap:6px;
  flex:1;
  position:relative;
  z-index:2;
  cursor:pointer;
}
.step-dot-wrap { position:relative; display:inline-flex; }
.step-dot {
  width:34px; height:34px;
  border-radius:50%;
  border:2px solid rgba(10,74,30,.2);
  background:white;
  color:var(--muted);
  font-family:'DM Mono',monospace;
  font-size:.75rem;
  font-weight:700;
  display:flex;
  align-items:center;
  justify-content:center;
  transition:all .3s cubic-bezier(.34,1.56,.64,1);
  box-shadow:var(--shadow-sm);
  user-select:none;
}
.step-dot.active {
  background:var(--forest);
  border-color:var(--forest);
  color:var(--gold-lt);
  box-shadow:0 0 0 4px rgba(10,74,30,.15), var(--shadow-sm);
  transform:scale(1.08);
}
.step-dot.done {
  background:var(--leaf);
  border-color:var(--leaf);
  color:white;
}
.step-dot.has-err {
  background:var(--amber);
  border-color:var(--amber);
  color:white;
}
/* error bubble */
.step-err-dot {
  position:absolute;
  top:-3px; right:-3px;
  width:14px; height:14px;
  border-radius:50%;
  background:var(--red);
  border:2px solid var(--cream);
  display:none;
  align-items:center;
  justify-content:center;
}
.step-err-dot i { color:white; font-size:7px; }
.step-err-dot.show { display:flex; }

.step-label {
  font-size:.68rem;
  font-weight:500;
  color:var(--muted);
  text-align:center;
  line-height:1.3;
  transition:color .2s;
}
.step-label.active { color:var(--forest); font-weight:700; }
.step-label.done   { color:var(--leaf); }
.step-label.has-err{ color:var(--amber); font-weight:600; }

/* step tooltip */
.step-tooltip {
  position:absolute;
  bottom:calc(100% + 6px);
  left:50%;
  transform:translateX(-50%);
  background:var(--ink);
  color:white;
  font-size:.68rem;
  border-radius:6px;
  padding:5px 10px;
  white-space:nowrap;
  pointer-events:none;
  opacity:0;
  transition:opacity .15s;
  z-index:300;
}
.step-tooltip::after {
  content:'';
  position:absolute;
  top:100%; left:50%;
  transform:translateX(-50%);
  border:5px solid transparent;
  border-top-color:var(--ink);
}
.step-node:hover .step-tooltip { opacity:1; }

/* step counter */
.step-counter {
  text-align:center;
  font-family:'DM Mono',monospace;
  font-size:.62rem;
  color:var(--muted);
  margin-top:-2px;
  margin-bottom:8px;
  letter-spacing:.06em;
  flex-shrink:0;
}

/* ═══════════════════════════════════════
   CARD — STEP CONTAINER
═══════════════════════════════════════ */
.step-card {
  background:white;
  border:1px solid var(--border);
  border-radius:var(--radius-lg);
  box-shadow:var(--shadow-md);
  overflow:hidden;
  animation:fadeUp .5s both;
  margin-bottom:24px;
}
@keyframes fadeUp {
  from { opacity:0; transform:translateY(20px); }
  to   { opacity:1; transform:translateY(0); }
}

/* Card header */
.card-header {
  display:flex;
  align-items:center;
  gap:14px;
  padding:20px 28px;
  background:linear-gradient(135deg, var(--forest) 0%, var(--emerald) 100%);
  position:relative;
  overflow:hidden;
}
.card-header::after {
  content:'';
  position:absolute;
  right:-30px; top:-30px;
  width:120px; height:120px;
  border-radius:50%;
  background:rgba(255,255,255,.05);
}
.card-header-icon {
  width:40px; height:40px;
  border-radius:10px;
  background:rgba(255,255,255,.15);
  display:flex; align-items:center; justify-content:center;
  flex-shrink:0;
  backdrop-filter:blur(8px);
}
.card-header-icon i { color:var(--gold-lt); font-size:16px; }
.card-header-title {
  font-family:'Playfair Display',serif;
  font-size:1.1rem;
  font-weight:700;
  color:white;
  margin-bottom:2px;
}
.card-header-sub {
  font-size:.75rem;
  color:rgba(255,255,255,.65);
  font-family:'DM Mono',monospace;
  letter-spacing:.04em;
}

/* Card body */
.card-body { padding:28px; }

/* ═══════════════════════════════════════
   FORM PRIMITIVES
═══════════════════════════════════════ */
.fg { display:flex; flex-direction:column; gap:5px; }
.fg label {
  font-size:.75rem;
  font-weight:600;
  color:var(--muted);
  letter-spacing:.02em;
  text-transform:uppercase;
  font-family:'DM Mono',monospace;
}
.req { color:var(--red); }
.opt-tag {
  display:inline-block;
  font-size:.6rem;
  font-weight:400;
  color:var(--muted);
  background:var(--warm);
  border-radius:3px;
  padding:1px 5px;
  text-transform:none;
  letter-spacing:0;
  vertical-align:middle;
}

.fg input,
.fg select,
.fg textarea {
  width:100%;
  border:1.5px solid rgba(10,74,30,.15);
  border-radius:var(--radius);
  padding:10px 14px;
  font-size:.875rem;
  font-family:'DM Sans',sans-serif;
  color:var(--ink);
  background:white;
  outline:none;
  transition:border-color .2s, box-shadow .2s, background .2s;
  line-height:1.4;
}
.fg input:focus,
.fg select:focus,
.fg textarea:focus {
  border-color:var(--leaf);
  box-shadow:0 0 0 3px rgba(46,168,85,.12);
  background:white;
}
.fg input::placeholder,
.fg textarea::placeholder { color:rgba(90,107,97,.45); }
.fg input.err,
.fg select.err,
.fg textarea.err {
  border-color:var(--red) !important;
  background:#fff8f8 !important;
  box-shadow:0 0 0 3px rgba(220,38,38,.08) !important;
}
.fg textarea { resize:vertical; min-height:72px; }

/* Synced readonly */
.synced {
  background:rgba(10,74,30,.04) !important;
  border-color:var(--mint) !important;
  color:var(--emerald) !important;
  cursor:not-allowed;
}
.sync-tag {
  display:inline-flex;
  align-items:center;
  gap:5px;
  font-size:.7rem;
  color:var(--emerald);
  font-family:'DM Mono',monospace;
}
.sync-tag i { font-size:.65rem; }

/* Hint */
.hint {
  font-size:.7rem;
  color:var(--muted);
  opacity:.8;
}

/* Error message */
.field-err {
  display:none;
  align-items:center;
  gap:5px;
  font-size:.7rem;
  color:var(--red);
  font-weight:500;
}
.field-err.show { display:flex; }
.field-err i { font-size:.65rem; flex-shrink:0; }

/* Soft warning (e.g. letters stripped from phone) */
.field-warn {
  display:none;
  align-items:center;
  gap:5px;
  font-size:.7rem;
  color:#b45309;
  font-weight:500;
  margin-top:4px;
}
.field-warn.show { display:flex; }
.field-warn i { font-size:.65rem; flex-shrink:0; }

/* ═══════════════════════════════════════
   GRID HELPERS
═══════════════════════════════════════ */
.row   { display:grid; gap:16px; margin-bottom:16px; }
.row:last-child { margin-bottom:0; }
.r2    { grid-template-columns:1fr 1fr; }
.r3    { grid-template-columns:1fr 1fr 1fr; }
.r4    { grid-template-columns:1fr 1fr 1fr 1fr; }
.r-name{ grid-template-columns:1fr 1fr 80px; }

/* ═══════════════════════════════════════
   SECTION DIVIDER
═══════════════════════════════════════ */
.section-sep {
  display:flex;
  align-items:center;
  gap:10px;
  margin:24px 0 20px;
}
.section-sep::before,
.section-sep::after {
  content:'';
  flex:1;
  height:1px;
  background:var(--border);
}
.section-sep span {
  font-family:'DM Mono',monospace;
  font-size:.62rem;
  font-weight:700;
  text-transform:uppercase;
  letter-spacing:.14em;
  color:var(--muted);
  white-space:nowrap;
}

/* ═══════════════════════════════════════
   SCANNER PANEL
═══════════════════════════════════════ */
.scanner-panel {
  background:linear-gradient(135deg, rgba(10,74,30,.03) 0%, rgba(201,168,76,.04) 100%);
  border:1.5px dashed rgba(10,74,30,.2);
  border-radius:var(--radius-lg);
  padding:20px;
  margin-bottom:20px;
}
.scanner-badge {
  display:inline-flex;
  align-items:center;
  gap:6px;
  background:var(--forest);
  color:var(--gold-lt);
  border-radius:100px;
  padding:4px 12px;
  font-family:'DM Mono',monospace;
  font-size:.62rem;
  font-weight:700;
  letter-spacing:.08em;
  margin-bottom:12px;
}
.scanner-title {
  font-family:'Playfair Display',serif;
  font-size:1rem;
  font-weight:700;
  color:var(--forest);
  margin-bottom:4px;
}
.scanner-desc {
  font-size:.78rem;
  color:var(--muted);
  line-height:1.6;
  margin-bottom:16px;
}
.id-upload-grid {
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:12px;
  margin-bottom:14px;
  max-width:900px;
}
.id-upload-grid--single {
  grid-template-columns:1fr;
  max-width:440px;
}
.id-upload-box {
  border:1.5px solid rgba(10,74,30,.14);
  border-radius:var(--radius);
  padding:14px;
  background:white;
  cursor:pointer;
  transition:border-color .2s, box-shadow .2s;
}
.id-upload-box:hover { border-color:var(--leaf); box-shadow:var(--shadow-sm); }
.id-upload-box.filled { border-style:solid; border-color:var(--leaf); }
.id-upload-label {
  font-size:.7rem;
  font-weight:700;
  color:var(--muted);
  font-family:'DM Mono',monospace;
  letter-spacing:.06em;
  text-transform:uppercase;
  margin-bottom:8px;
  display:flex;
  align-items:center;
  justify-content:space-between;
}
.id-preview {
  height:64px;
  background:var(--warm);
  border-radius:8px;
  display:flex;
  align-items:center;
  justify-content:center;
  overflow:hidden;
  margin-bottom:8px;
}
.id-preview img { width:100%; height:100%; object-fit:cover; border-radius:8px; }
.id-placeholder { display:flex; flex-direction:column; align-items:center; gap:4px; }
.id-placeholder i { font-size:20px; color:rgba(10,74,30,.25); }
.id-placeholder span { font-size:.68rem; color:var(--muted); }
.id-action-row {
  display:flex;
  gap:6px;
}
.id-btn {
  flex:1;
  display:flex;
  align-items:center;
  justify-content:center;
  gap:5px;
  padding:6px;
  border:1.5px solid var(--border);
  border-radius:8px;
  background:white;
  color:var(--muted);
  font-size:.7rem;
  font-weight:600;
  font-family:'DM Sans',sans-serif;
  cursor:pointer;
  transition:all .15s;
}
.id-btn:hover { background:var(--mint); border-color:var(--leaf); color:var(--forest); }
.id-btn i { font-size:.65rem; }

.ocr-note {
  display:flex;
  align-items:center;
  gap:8px;
  background:white;
  border:1px solid var(--mint);
  border-radius:8px;
  padding:8px 12px;
  font-size:.75rem;
  color:var(--emerald);
}
.ocr-note i { font-size:.78rem; flex-shrink:0; }
.ocr-spinner {
  display:none;
  align-items:center;
  gap:8px;
  font-size:.75rem;
  color:var(--emerald);
  margin-top:8px;
  font-family:'DM Mono',monospace;
}
.ocr-spinner.show { display:flex; }

/* ═══════════════════════════════════════
   ALUMNI ID INFO CARD
═══════════════════════════════════════ */
.alumni-id-card {
  background:linear-gradient(135deg, var(--forest) 0%, #16391f 100%);
  border-radius:var(--radius);
  padding:16px 20px;
  margin-bottom:20px;
  position:relative;
  overflow:hidden;
}
.alumni-id-card::before {
  content:'';
  position:absolute;
  right:-20px; bottom:-20px;
  width:100px; height:100px;
  border-radius:50%;
  background:rgba(201,168,76,.1);
}
.alumni-id-card::after {
  content:'';
  position:absolute;
  left:50%; top:-40px;
  width:200px; height:80px;
  border-radius:50%;
  background:rgba(255,255,255,.03);
}
.id-card-eyebrow {
  font-family:'DM Mono',monospace;
  font-size:.6rem;
  letter-spacing:.16em;
  text-transform:uppercase;
  color:var(--gold);
  margin-bottom:8px;
  display:flex;
  align-items:center;
  gap:8px;
}
.id-card-eyebrow::before {
  content:'';
  width:20px; height:1px;
  background:var(--gold);
}
.id-card-number {
  font-family:'Playfair Display',serif;
  font-size:1.4rem;
  font-weight:700;
  color:white;
  letter-spacing:.04em;
  margin-bottom:4px;
}
.id-card-note {
  font-size:.72rem;
  color:rgba(255,255,255,.55);
  line-height:1.5;
}
.id-card-badge {
  display:inline-flex;
  align-items:center;
  gap:5px;
  background:rgba(201,168,76,.18);
  border:1px solid rgba(201,168,76,.3);
  border-radius:100px;
  padding:3px 10px;
  font-size:.65rem;
  color:var(--gold-lt);
  margin-top:10px;
  font-family:'DM Mono',monospace;
}
.id-card-badge i { font-size:.62rem; }

/* ═══════════════════════════════════════
   PROFILE PHOTO ZONE
═══════════════════════════════════════ */
.photo-zone {
  border:1.5px dashed rgba(10,74,30,.2);
  border-radius:var(--radius);
  padding:16px;
  display:flex;
  flex-direction:column;
  align-items:center;
  gap:8px;
  cursor:pointer;
  transition:border-color .2s, background .2s;
  background:rgba(10,74,30,.02);
  min-height:120px;
  justify-content:center;
}
.photo-zone:hover { border-color:var(--leaf); background:rgba(46,168,85,.04); }
.avatar-circle {
  width:52px; height:52px;
  border-radius:50%;
  border:2px solid var(--mint);
  background:var(--mint);
  display:flex; align-items:center; justify-content:center;
  overflow:hidden;
  flex-shrink:0;
}
.avatar-circle img { width:100%; height:100%; object-fit:cover; border-radius:50%; }
.avatar-circle i { font-size:22px; color:var(--leaf); }
.photo-label { font-size:.8rem; font-weight:600; color:var(--forest); }
.photo-sub   { font-size:.7rem; color:var(--muted); }
.photo-btns  { display:flex; gap:6px; flex-wrap:wrap; justify-content:center; }

/* ═══════════════════════════════════════
   RADIO OPTIONS
═══════════════════════════════════════ */
.radio-group { display:flex; flex-direction:column; gap:8px; margin-top:4px; }
.radio-group.inline { flex-direction:row; flex-wrap:wrap; }
.r-opt {
  display:flex;
  align-items:center;
  gap:10px;
  padding:10px 14px;
  border:1.5px solid rgba(10,74,30,.12);
  border-radius:var(--radius);
  cursor:pointer;
  font-size:.85rem;
  color:var(--ink);
  background:white;
  transition:all .15s;
  user-select:none;
}
.r-opt:hover { border-color:var(--leaf); background:rgba(46,168,85,.04); }
.r-opt input[type=radio] { accent-color:var(--forest); width:14px; height:14px; flex-shrink:0; cursor:pointer; }
.r-opt:has(input:checked) {
  border-color:var(--leaf);
  background:rgba(46,168,85,.06);
  color:var(--forest);
  font-weight:600;
}
.radio-group.inline .r-opt { flex:1; min-width:110px; justify-content:center; }

/* ═══════════════════════════════════════
   LIKERT SCALE
═══════════════════════════════════════ */
.likert { display:flex; flex-wrap:wrap; gap:6px; margin-top:4px; }
.l-opt {
  display:flex;
  align-items:center;
  gap:6px;
  padding:7px 12px;
  border:1.5px solid rgba(10,74,30,.12);
  border-radius:100px;
  cursor:pointer;
  font-size:.78rem;
  color:var(--muted);
  background:white;
  transition:all .15s;
}
.l-opt:hover { border-color:var(--leaf); color:var(--forest); }
.l-opt input { accent-color:var(--forest); width:12px; height:12px; cursor:pointer; }
.l-opt:has(input:checked) {
  background:var(--forest);
  border-color:var(--forest);
  color:white;
  font-weight:600;
}

/* Compact layout for Step 2 and Step 3 so all fields fit without internal scrolling */
#s1 .step-card,
#s2 .step-card { margin-bottom:12px; }
#s1 .card-header,
#s2 .card-header { padding:14px 20px; }
#s1 .card-header-title,
#s2 .card-header-title { font-size:1rem; }
#s1 .card-header-sub,
#s2 .card-header-sub { font-size:.7rem; }
#s1 .card-body,
#s2 .card-body { padding:18px 20px; }
#s1 .row,
#s2 .row { gap:12px; margin-bottom:12px; }
#s1 .fg label,
#s2 .fg label { font-size:.72rem; margin-bottom:4px; }
#s1 .fg input,
#s1 .fg select,
#s1 .fg textarea,
#s2 .fg input,
#s2 .fg select,
#s2 .fg textarea { padding:8px 11px; font-size:.82rem; }
#s1 .hint,
#s2 .hint,
#s1 .field-err,
#s2 .field-err,
#s1 .field-warn,
#s2 .field-warn { font-size:.66rem; }
#s1 .fg textarea { min-height:62px; }
#s2 .radio-group { gap:6px; }
#s2 .r-opt { padding:8px 11px; font-size:.78rem; line-height:1.25; }
#s2 .fg[style] { margin-bottom:12px !important; }

/* ═══════════════════════════════════════
   EMPLOYMENT CONDITIONAL
═══════════════════════════════════════ */
.emp-details { display:none; }
.emp-details.visible { display:block; animation:fadeUp .3s both; }
.emp-notice {
  display:flex;
  align-items:center;
  gap:10px;
  background:rgba(46,168,85,.07);
  border:1px solid rgba(10,74,30,.12);
  border-radius:var(--radius);
  padding:10px 14px;
  font-size:.78rem;
  color:var(--forest);
  font-weight:500;
  margin-bottom:16px;
}
.emp-notice i { font-size:.85rem; color:var(--leaf); }

/* ═══════════════════════════════════════
   PASSWORD
═══════════════════════════════════════ */
.pw-wrap { position:relative; }
.pw-wrap input { padding-right:40px; }
.pw-toggle {
  position:absolute;
  right:12px; top:50%;
  transform:translateY(-50%);
  background:none; border:none;
  color:var(--muted);
  cursor:pointer;
  display:flex; align-items:center;
  padding:2px;
  transition:color .15s;
}
.pw-toggle:hover { color:var(--forest); }
.pw-toggle i { font-size:.85rem; }
.pw-strength {
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:5px 12px;
  margin-top:8px;
  background:var(--warm);
  border:1px solid var(--border);
  border-radius:var(--radius);
  padding:10px 14px;
}
.pw-req {
  display:flex;
  align-items:center;
  gap:6px;
  font-size:.72rem;
  color:var(--muted);
  transition:color .2s;
}
.pw-req.met { color:var(--leaf); }
.pw-req i { font-size:.65rem; width:10px; }
.match-msg { font-size:.72rem; min-height:16px; margin-top:3px; }

/* ═══════════════════════════════════════
   CONSENT
═══════════════════════════════════════ */
.consent-scroll {
  background:rgba(10,74,30,.03);
  border:1px solid var(--mint);
  border-radius:var(--radius);
  padding:14px 16px;
  font-size:.8rem;
  color:var(--muted);
  line-height:1.75;
  max-height:100px;
  overflow-y:hidden;
  margin-bottom:12px;
  scrollbar-width:thin;
  scrollbar-color:var(--mint) transparent;
}
.consent-scroll.needs-scroll {
  overflow-y:auto;
}
.consent-chk {
  display:flex;
  align-items:flex-start;
  gap:10px;
  padding:12px 14px;
  border:1.5px solid var(--border);
  border-radius:var(--radius);
  cursor:pointer;
  transition:border-color .15s, background .15s;
}
.consent-chk:hover { border-color:var(--leaf); background:rgba(46,168,85,.03); }
.consent-chk input[type=checkbox] {
  width:16px; height:16px;
  accent-color:var(--forest);
  margin-top:2px;
  flex-shrink:0;
  cursor:pointer;
}
.consent-chk label {
  font-size:.82rem;
  color:var(--ink);
  cursor:pointer;
  line-height:1.5;
  text-transform:none;
  letter-spacing:0;
  font-family:'DM Sans',sans-serif;
  font-weight:400;
}

/* ═══════════════════════════════════════
   WARNING / INFO BANNERS
═══════════════════════════════════════ */
.info-banner {
  display:flex;
  align-items:flex-start;
  gap:10px;
  background:var(--amber-lt);
  border:1px solid rgba(217,119,6,.2);
  border-radius:var(--radius);
  padding:12px 14px;
  font-size:.8rem;
  color:#92400e;
  line-height:1.6;
  margin-bottom:16px;
}
.info-banner i { font-size:.9rem; color:var(--amber); flex-shrink:0; margin-top:1px; }

.error-banner {
  display:flex;
  align-items:flex-start;
  gap:10px;
  background:var(--red-lt);
  border:1px solid rgba(220,38,38,.2);
  border-radius:var(--radius);
  padding:12px 16px;
  font-size:.82rem;
  color:#991b1b;
  line-height:1.55;
  margin-bottom:20px;
}
.error-banner i { font-size:.9rem; color:var(--red); flex-shrink:0; margin-top:2px; }

/* ═══════════════════════════════════════
   NAVIGATION BAR
═══════════════════════════════════════ */
.nav-bar {
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
  background:white;
  border:1px solid var(--border);
  border-radius:var(--radius-lg);
  padding:14px 20px;
  box-shadow:var(--shadow-sm);
  flex-shrink:0;
}
.btn-prev {
  display:inline-flex;
  align-items:center;
  gap:7px;
  padding:9px 20px;
  border:1.5px solid var(--border);
  border-radius:100px;
  background:white;
  color:var(--muted);
  font-size:.82rem;
  font-weight:600;
  font-family:'DM Sans',sans-serif;
  cursor:pointer;
  transition:all .15s;
}
.btn-prev:hover { background:var(--warm); border-color:rgba(10,74,30,.2); color:var(--forest); }
.btn-prev[style*="none"] { visibility:hidden; }

.nav-info {
  font-family:'DM Mono',monospace;
  font-size:.68rem;
  color:var(--muted);
  letter-spacing:.06em;
  text-align:center;
}

.btn-next,
.btn-submit {
  display:inline-flex;
  align-items:center;
  gap:7px;
  padding:9px 22px;
  border:none;
  border-radius:100px;
  background:var(--forest);
  color:white;
  font-size:.82rem;
  font-weight:600;
  font-family:'DM Sans',sans-serif;
  cursor:pointer;
  transition:all .2s;
  box-shadow:0 4px 16px rgba(10,74,30,.25);
  position:relative;
  overflow:hidden;
}
.btn-next::before,
.btn-submit::before {
  content:'';
  position:absolute;
  inset:0;
  background:linear-gradient(90deg, transparent 30%, rgba(255,255,255,.15) 50%, transparent 70%);
  transform:translateX(-100%);
  transition:transform .4s ease;
}
.btn-next:hover::before,
.btn-submit:hover::before { transform:translateX(100%); }
.btn-next:hover,
.btn-submit:hover {
  background:var(--emerald);
  transform:translateY(-1px);
  box-shadow:0 8px 24px rgba(10,74,30,.3);
}
.btn-submit { background:linear-gradient(135deg, var(--forest), var(--emerald)); }

/* ═══════════════════════════════════════
   SUCCESS MODAL
═══════════════════════════════════════ */
#successModal {
  display:none;
  position:fixed;
  inset:0;
  background:rgba(10,30,15,.6);
  backdrop-filter:blur(8px);
  z-index:1000;
  align-items:center;
  justify-content:center;
  animation:fadeUp .3s both;
}
.success-card {
  background:white;
  border-radius:var(--radius-lg);
  padding:40px 32px;
  max-width:400px;
  width:90%;
  text-align:center;
  box-shadow:var(--shadow-lg);
  border:1px solid var(--border);
}
.success-icon {
  width:64px; height:64px;
  border-radius:50%;
  background:var(--mint);
  display:flex; align-items:center; justify-content:center;
  margin:0 auto 20px;
}
.success-icon i { font-size:28px; color:var(--leaf); }
.success-title {
  font-family:'Playfair Display',serif;
  font-size:1.4rem;
  font-weight:700;
  color:var(--forest);
  margin-bottom:10px;
}
.success-body {
  font-size:.85rem;
  color:var(--muted);
  line-height:1.7;
  margin-bottom:24px;
}
.success-cta {
  display:block;
  width:100%;
  padding:12px;
  background:var(--forest);
  color:white;
  border:none;
  border-radius:var(--radius);
  font-size:.875rem;
  font-weight:700;
  font-family:'DM Sans',sans-serif;
  cursor:pointer;
  text-decoration:none;
  transition:background .2s;
  box-shadow:0 4px 16px rgba(10,74,30,.25);
}
.success-cta:hover { background:var(--emerald); }

/* ═══════════════════════════════════════
   CAMERA OVERLAY
═══════════════════════════════════════ */
#camOverlay {
  display:none;
  position:fixed;
  inset:0;
  background:rgba(10,20,15,.7);
  backdrop-filter:blur(8px);
  z-index:500;
  align-items:center;
  justify-content:center;
}
#camOverlay.show { display:flex; }
.cam-card {
  background:white;
  border-radius:var(--radius-lg);
  width:min(95vw, 560px);
  padding:20px;
  box-shadow:var(--shadow-lg);
}
.cam-head {
  display:flex;
  align-items:center;
  justify-content:space-between;
  margin-bottom:14px;
}
.cam-head h4 {
  font-family:'Playfair Display',serif;
  font-size:1.05rem;
  color:var(--forest);
}
.cam-close {
  width:30px; height:30px;
  border-radius:50%;
  border:none;
  background:var(--red-lt);
  color:var(--red);
  font-size:14px;
  cursor:pointer;
  display:flex; align-items:center; justify-content:center;
}
.cam-video-wrap {
  position:relative;
  background:#000;
  border-radius:var(--radius);
  overflow:hidden;
  margin-bottom:12px;
}
#idcam_video { width:100%; height:auto; display:block; }
.cam-frame {
  position:absolute;
  width:60%; aspect-ratio:54/85;
  top:50%; left:50%;
  transform:translate(-50%,-50%);
  border:3px solid rgba(46,168,85,.95);
  border-radius:10px;
  box-shadow:0 0 0 9999px rgba(0,0,0,.35) inset;
}
.cam-status {
  text-align:center;
  font-size:.78rem;
  color:var(--muted);
  margin-bottom:12px;
  font-family:'DM Mono',monospace;
}
.cam-actions { display:flex; gap:10px; justify-content:center; }

/* ═══════════════════════════════════════
   STEP VISIBILITY
═══════════════════════════════════════ */
.step { display:none; }
.step.on { display:block; animation:fadeUp .35s both; }

/* ═══════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════ */
@media (max-width:640px) {
  .reg-main { padding:16px 14px 12px; }
  .card-body { padding:20px 16px; }
  .r2,.r3,.r4,.r-name { grid-template-columns:1fr; }
  .id-upload-grid { grid-template-columns:1fr; }
  .likert { flex-direction:column; }
  .radio-group.inline { flex-direction:column; }
  .stepper { padding:0; }
  .step-label { font-size:.6rem; }
  .step-dot { width:28px; height:28px; font-size:.68rem; }
  .nav-bar { flex-wrap:wrap; }
}

/* ═══════════════════════════════════════
   SMALL BTN (reused)
═══════════════════════════════════════ */
.sm-btn {
  display:inline-flex;
  align-items:center;
  gap:5px;
  padding:6px 12px;
  border:1.5px solid var(--border);
  border-radius:100px;
  background:white;
  color:var(--muted);
  font-size:.72rem;
  font-weight:600;
  font-family:'DM Sans',sans-serif;
  cursor:pointer;
  transition:all .15s;
}
.sm-btn:hover { background:var(--mint); border-color:var(--leaf); color:var(--forest); }
.sm-btn i { font-size:.65rem; }
</style>
</head>
<body>
<div class="reg-page">

<!-- ── HEADER ── -->
<header class="reg-header">
  <a href="al_homepage.php" class="hdr-brand">
    <div class="hdr-logo-box"><i class="fas fa-graduation-cap"></i></div>
    <div>
      <div class="hdr-wordmark-title">OLFU Alumni Portal</div>
      <div class="hdr-wordmark-sub">College of Computer Studies</div>
    </div>
  </a>
  <a href="al_login.php" class="hdr-login-link">
    <i class="fas fa-arrow-right-to-bracket"></i> Already registered? Log in
  </a>
</header>

<!-- ── MAIN ── -->
<main class="reg-main">
<div class="reg-shell">

  <!-- Stepper -->
  <div class="stepper" id="stepper">
    <div class="stepper-line-bg"></div>
    <div class="stepper-line-fill" id="pFill" style="width:0%"></div>
    <?php
    $steps = [
      [$registrationType === 'legacy' ? 'Alumni ID' : 'Student ID','fa-id-card'],
      ['Personal','fa-user'],
      ['Academic','fa-graduation-cap'],
      ['Employment','fa-briefcase'],
      ['Account','fa-lock'],
    ];
    foreach ($steps as $i => $s):
    ?>
    <div class="step-node" onclick="jumpTo(<?= $i ?>)">
      <div class="step-dot-wrap">
        <div class="step-dot <?= $i===0?'active':'' ?>" id="sd<?= $i ?>"><?= $i+1 ?></div>
        <div class="step-err-dot" id="se<?= $i ?>"><i class="fas fa-exclamation"></i></div>
      </div>
      <div class="step-label <?= $i===0?'active':'' ?>" id="sl<?= $i ?>"><?= $s[0] ?></div>
      <div class="step-tooltip" id="st<?= $i ?>"><?= $s[0] ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="step-counter" id="stepCounter">Step 1 of 5 — <?php echo $registrationType === 'legacy' ? 'Alumni ID' : 'Student ID'; ?> &amp; Academic Info</div>

  <form id="regForm" class="reg-form-layout" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="registration_type" value="<?php echo htmlspecialchars($registrationType, ENT_QUOTES, 'UTF-8'); ?>">

  <div class="reg-form-scroll" id="regFormScroll">

  <!-- ══════════════════════
       STEP 1 — STUDENT ID
  ══════════════════════ -->
  <div class="step on" id="s0">
    <div class="step-card">
      <div class="card-header">
        <div class="card-header-icon"><i class="fas fa-id-card"></i></div>
        <div>
          <div class="card-header-title"><?php echo $registrationType === 'legacy' ? 'Alumni ID &amp; Academic Info' : 'Student ID &amp; Academic Info'; ?></div>
          <div class="card-header-sub">
            <?php if ($registrationType === 'legacy'): ?>
              Upload the front of your alumni card and enter your existing alumni ID number.
            <?php else: ?>
              Upload the front of your student ID — alumni ID assigned after approval.
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="card-body">

        <?php if (isset($_GET['error']) || isset($_SESSION['registration_error'])):
          $em = $_GET['error'] ?? $_SESSION['registration_error'] ?? '';
          unset($_SESSION['registration_error']); ?>
        <div class="error-banner"><i class="fas fa-circle-exclamation"></i><?= htmlspecialchars($em) ?></div>
        <?php endif; ?>

        <!-- Scanner Panel -->
        <div class="scanner-panel">
          <div class="scanner-badge"><i class="fas fa-qrcode"></i> Smart OCR Scanner</div>
          <div class="scanner-title"><?php echo $registrationType === 'legacy' ? 'Alumni Card Scanner' : 'Student ID Scanner'; ?></div>
          <div class="scanner-desc">
            <?php if ($registrationType === 'legacy'): ?>
              Upload the <strong>front and back</strong> of your Alumni Card for verification.
            <?php else: ?>
              Upload the <strong>front</strong> of your Student ID. OCR auto-fills fields from the front side, and your Alumni ID is system-generated after office verification.
            <?php endif; ?>
          </div>

          <div class="id-upload-grid<?php echo $registrationType === 'legacy' ? '' : ' id-upload-grid--single'; ?>">
            <div class="id-upload-box" id="fBox" onclick="document.getElementById('id_card').click()">
              <div class="id-upload-label">Front of <?php echo $registrationType === 'legacy' ? 'Alumni Card' : 'Student ID'; ?> <span class="req">*</span></div>
              <div class="id-preview" id="fPrev">
                <div class="id-placeholder"><i class="fas fa-credit-card"></i><span>Click to upload</span></div>
              </div>
              <div class="id-action-row" onclick="event.stopPropagation()">
                <button type="button" class="id-btn" onclick="document.getElementById('id_card').click()"><i class="fas fa-upload"></i> Upload</button>
                <button type="button" class="id-btn" onclick="startIdCamera()"><i class="fas fa-camera"></i> Camera</button>
              </div>
            </div>
            <?php if ($registrationType === 'legacy'): ?>
            <div class="id-upload-box" id="bBox" onclick="document.getElementById('id_card_back').click()">
              <div class="id-upload-label">Back of Alumni Card <span class="req">*</span></div>
              <div class="id-preview" id="bPrev">
                <div class="id-placeholder"><i class="fas fa-id-card-clip"></i><span>Click to upload</span></div>
              </div>
              <div class="id-action-row" onclick="event.stopPropagation()">
                <button type="button" class="id-btn" onclick="document.getElementById('id_card_back').click()"><i class="fas fa-upload"></i> Upload</button>
              </div>
            </div>
            <?php endif; ?>
          </div>
          <div class="field-err" id="e-idCard" style="margin-top:-6px;margin-bottom:10px;"><i class="fas fa-circle-exclamation"></i> Front of <?php echo $registrationType === 'legacy' ? 'Alumni Card' : 'Student ID'; ?> is required</div>
          <?php if ($registrationType === 'legacy'): ?>
          <div class="field-err" id="e-idCardBack" style="margin-top:-6px;margin-bottom:10px;"><i class="fas fa-circle-exclamation"></i> Back of Alumni Card is required</div>
          <?php endif; ?>

          <div class="ocr-note"><i class="fas fa-circle-info"></i> OCR auto-fills <?php echo $registrationType === 'legacy' ? 'alumni ID number' : 'student number'; ?>, name &amp; course. Please verify all fields after scanning.</div>
          <div class="ocr-spinner" id="ocrSpinner"><i class="fas fa-spinner fa-spin"></i> Scanning ID — please wait…</div>
          <input type="file" name="id_card" id="id_card" accept="image/*" style="display:none" onchange="handleIdFront(event)">
          <?php if ($registrationType === 'legacy'): ?>
          <input type="file" name="id_card_back" id="id_card_back" accept="image/*" style="display:none" onchange="handleIdBack(event)">
          <?php endif; ?>
        </div>

        <!-- Alumni ID Info Card -->
        <div class="alumni-id-card">
          <div class="id-card-eyebrow"><?php echo $registrationType === 'legacy' ? 'Alumni ID — Legacy' : 'Alumni ID — Auto Generated'; ?></div>
          <div class="id-card-number">[ Pending Approval ]</div>
          <div class="id-card-note">
            <?php if ($registrationType === 'legacy'): ?>
              Your existing Alumni ID will be used after verification.
            <?php else: ?>
              Your Alumni ID will be assigned by the OLFU Alumni Office once your student ID is verified.
            <?php endif; ?>
          </div>
          <div class="id-card-badge"><i class="fas fa-shield-halved"></i> System-generated after admin verification</div>
        </div>

        <!-- Student Number + Photo -->
        <div class="row r2">
          <div class="fg">
            <label><?php echo $registrationType === 'legacy' ? 'Alumni ID Number' : 'Student Number'; ?> <span class="req">*</span></label>
            <input
              type="text"
              name="alumniIdNumber"
              id="sNum"
              placeholder="<?php echo $registrationType === 'legacy' ? 'Numbers only, max 16 digits' : 'e.g. 2021-00123'; ?>"
              <?php echo $registrationType === 'legacy' ? 'inputmode="numeric" maxlength="16" pattern="[0-9]{1,16}"' : ''; ?>
              oninput="<?php echo $registrationType === 'legacy' ? "this.value=this.value.replace(/\\D/g,'').slice(0,16);" : ''; ?>validateStep(0)"
            >
            <div class="field-err" id="e-sNum"><i class="fas fa-circle-exclamation"></i> <?php echo $registrationType === 'legacy' ? 'Alumni ID number is required (max 16 digits)' : 'Student number is required'; ?></div>
          </div>
          <?php if ($registrationType === 'legacy'): ?>
          <input type="hidden" name="current_alumni_id_number" id="legacyAlumniId" value="">
          <?php endif; ?>
          <div class="fg">
            <label>Profile Photo <span class="req">*</span></label>
            <div class="photo-zone" id="pZone" onclick="document.getElementById('profile_photo').click()">
              <div class="avatar-circle" id="avatarEl"><i class="fas fa-user"></i></div>
              <div class="photo-label">Upload a profile photo</div>
              <div class="photo-sub">Use a <strong>2×2 formal picture</strong> (passport-style). It will appear on your alumni ID.</div>
              <div class="photo-btns" onclick="event.stopPropagation()">
                <button type="button" class="sm-btn" onclick="document.getElementById('profile_photo').click()"><i class="fas fa-upload"></i> Upload</button>
                <button type="button" class="sm-btn" onclick="startProfileCamera()"><i class="fas fa-camera"></i> Camera</button>
              </div>
            </div>
            <input type="file" name="photo" id="profile_photo" accept="image/jpeg,image/png,image/gif" style="display:none" onchange="previewAvatar(this)">
            <div class="field-err" id="e-photo"><i class="fas fa-circle-exclamation"></i> Profile photo is required</div>
          </div>
        </div>

        <div class="section-sep"><span>Full Name</span></div>

        <!-- Name -->
        <div class="row r-name">
          <div class="fg">
            <label>Last Name <span class="req">*</span></label>
            <input type="text" name="lastname" id="lname" placeholder="Dela Cruz" oninput="validateStep(0)">
            <div class="field-err" id="e-lname"><i class="fas fa-circle-exclamation"></i> Last name is required</div>
          </div>
          <div class="fg">
            <label>First Name <span class="req">*</span></label>
            <input type="text" name="firstname" id="fname" placeholder="Juan" oninput="validateStep(0)">
            <div class="field-err" id="e-fname"><i class="fas fa-circle-exclamation"></i> First name is required</div>
          </div>
          <div class="fg">
            <label>M.I. <span class="opt-tag">opt</span></label>
            <input type="text" name="middleInitial" id="mi" placeholder="A" maxlength="2">
          </div>
        </div>

        <div class="section-sep"><span>Academic Details</span></div>

        <!-- Campus / Year / Month -->
        <div class="row r3">
          <div class="fg">
            <label>Campus <span class="req">*</span></label>
            <input type="text" name="campus" id="campus" class="synced" value="Antipolo City" readonly autocomplete="off" aria-readonly="true">
            <div class="hint">Registration on this portal is for <strong>Antipolo City</strong> campus only.</div>
            <div class="field-err" id="e-campus"><i class="fas fa-circle-exclamation"></i> Campus is required</div>
          </div>
          <div class="fg">
            <label>Year Graduated <span class="req">*</span></label>
            <select name="yearGraduated" id="yearGrad" oninput="validateStep(0)">
              <option value="">Select year</option>
              <?php for ($y = date('Y'); $y >= 1990; $y--) echo "<option>$y</option>"; ?>
            </select>
            <div class="field-err" id="e-yearGrad"><i class="fas fa-circle-exclamation"></i> Year is required</div>
          </div>
          <div class="fg">
            <label>Month Graduated <span class="opt-tag">opt</span></label>
            <select name="month_graduated">
              <option value="">Select month</option>
              <?php $mos=['January','February','March','April','May','June','July','August','September','October','November','December'];
              foreach ($mos as $i => $m) printf('<option value="%02d">%s</option>', $i+1, $m); ?>
            </select>
          </div>
        </div>

        <!-- College / Degree -->
        <div class="row r2">
          <div class="fg">
            <label>College <span class="req">*</span></label>
            <select name="college" id="colSel" onchange="fillDeg(this.value);validateStep(0)">
              <option value="">Select college</option>
              <option value="College of Computer Studies">College of Computer Studies</option>
              <option value="College of Engineering">College of Engineering</option>
              <option value="College of Business &amp; Accountancy">College of Business &amp; Accountancy</option>
              <option value="College of Arts and Sciences">College of Arts and Sciences</option>
              <option value="College of Education">College of Education</option>
              <option value="College of Nursing">College of Nursing</option>
              <option value="College of Medicine">College of Medicine</option>
            </select>
            <div class="field-err" id="e-colSel"><i class="fas fa-circle-exclamation"></i> College is required</div>
          </div>
          <div class="fg">
            <label>Degree Program <span class="req">*</span></label>
            <select name="degree" id="degSel" oninput="validateStep(0)">
              <option value="">Select college first</option>
            </select>
            <div class="field-err" id="e-degSel"><i class="fas fa-circle-exclamation"></i> Degree is required</div>
          </div>
        </div>

      </div>
    </div>
  </div><!-- /step 1 -->

  <!-- ══════════════════════
       STEP 2 — PERSONAL
  ══════════════════════ -->
  <div class="step" id="s1">
    <div class="step-card">
      <div class="card-header">
        <div class="card-header-icon"><i class="fas fa-user"></i></div>
        <div>
          <div class="card-header-title">Personal Information</div>
          <div class="card-header-sub">Your contact and demographic details</div>
        </div>
      </div>
      <div class="card-body">

        <div class="row r3">
          <div class="fg">
            <label>Birthday</label>
            <input type="date" name="birthday" id="birthday" max="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8') ?>" onchange="updateAgeFromBirthday()" oninput="updateAgeFromBirthday()">
            <div class="hint">Enter your birthday first — age is calculated automatically.</div>
          </div>
          <div class="fg">
            <label>Age</label>
            <input type="number" name="age" id="age" class="synced" min="0" max="120" placeholder="—" readonly autocomplete="off" aria-readonly="true" title="Computed from your birthday">
            <div class="sync-tag"><i class="fas fa-circle-check"></i> From birthday</div>
          </div>
          <div class="fg">
            <label>Gender <span class="req">*</span></label>
            <select name="gender" id="gender" oninput="validateStep(1)">
              <option value="">Select</option><option>Male</option><option>Female</option><option>Prefer not to say</option>
            </select>
            <div class="field-err" id="e-gender"><i class="fas fa-circle-exclamation"></i> Required</div>
          </div>
        </div>

        <div class="row r3">
          <div class="fg">
            <label>Civil Status <span class="req">*</span></label>
            <select name="civil_status" id="civil" oninput="validateStep(1)">
              <option value="">Select</option><option>Single</option><option>Married</option><option>Widowed</option><option>Separated</option><option>Divorced</option>
            </select>
            <div class="field-err" id="e-civil"><i class="fas fa-circle-exclamation"></i> Required</div>
          </div>
          <div class="fg">
            <label>Religion <span class="opt-tag">opt</span></label>
            <input type="text" name="religion" placeholder="Roman Catholic">
          </div>
          <div class="fg">
            <label>Nationality</label>
            <input type="text" name="nationality" value="Filipino">
          </div>
        </div>

        <div class="row r2">
          <div class="fg">
            <label>Name Extension <span class="opt-tag">opt</span></label>
            <input type="text" name="name_ext" placeholder="Jr., III, etc.">
          </div>
          <div class="fg">
            <label>Personal Email <span class="req">*</span></label>
            <input type="text" name="personalEmail" id="personalEmail" placeholder="you@gmail.com" inputmode="email" autocomplete="email" oninput="syncEmail();validateStep(1)">
            <div class="hint">@gmail.com or @yahoo.com only — also used as your login email</div>
            <div class="field-err" id="e-personalEmail"><i class="fas fa-circle-exclamation"></i> Use @gmail.com or @yahoo.com only</div>
          </div>
        </div>

        <div class="row r2">
          <div class="fg">
            <label>Contact Number <span class="req">*</span></label>
            <input type="text" name="personal_contact" id="contact" placeholder="09123456789" inputmode="numeric" pattern="[0-9]*" autocomplete="tel" oninput="sanitizePhoneInput('contact','w-contact');">
            <div class="hint">10–11 digits, numbers only</div>
            <div class="field-warn" id="w-contact"><i class="fas fa-exclamation-triangle"></i> Only numbers are allowed — non-digits were removed</div>
            <div class="field-err" id="e-contact"><i class="fas fa-circle-exclamation"></i> Enter a valid 10–11 digit number</div>
          </div>
          <div class="fg">
            <label>Emergency Contact <span class="opt-tag">opt</span></label>
            <input type="text" name="emergency_contact" id="emergency_contact" placeholder="09123456789" inputmode="numeric" pattern="[0-9]*" autocomplete="tel" oninput="sanitizePhoneInput('emergency_contact','w-emergency');">
            <div class="hint">Optional — 10–11 digits if provided</div>
            <div class="field-warn" id="w-emergency"><i class="fas fa-exclamation-triangle"></i> Only numbers are allowed — non-digits were removed</div>
            <div class="field-err" id="e-emergency"><i class="fas fa-circle-exclamation"></i> Use 10–11 digits or leave blank</div>
          </div>
        </div>

        <div class="row">
          <div class="fg">
            <label>Complete Address <span class="req">*</span></label>
            <textarea name="address" id="address" placeholder="Street, Barangay, City, Province" oninput="validateStep(1)"></textarea>
            <div class="field-err" id="e-address"><i class="fas fa-circle-exclamation"></i> Address is required</div>
          </div>
        </div>

      </div>
    </div>
  </div><!-- /step 2 -->

  <!-- ══════════════════════
       STEP 3 — ACADEMIC / LICENSURE
  ══════════════════════ -->
  <div class="step" id="s2">
    <div class="step-card">
      <div class="card-header">
        <div class="card-header-icon"><i class="fas fa-graduation-cap"></i></div>
        <div>
          <div class="card-header-title">Licensure &amp; Post-Graduate</div>
          <div class="card-header-sub">Academic achievements after graduation</div>
        </div>
      </div>
      <div class="card-body">

        <div class="fg" style="margin-bottom:20px;">
          <label>Did you pass a Licensure Examination? <span class="req">*</span></label>
          <div class="radio-group" style="margin-top:8px;" onchange="validateStep(2)">
            <label class="r-opt"><input type="radio" name="passedLicensure" value="yes"> Yes, I passed</label>
            <label class="r-opt"><input type="radio" name="passedLicensure" value="no"> No, I didn't</label>
            <label class="r-opt"><input type="radio" name="passedLicensure" value="not_applicable"> Not applicable — no licensure exam in my course</label>
            <label class="r-opt"><input type="radio" name="passedLicensure" value="not_yet"> Not yet, but I plan to take it in the future</label>
          </div>
          <div class="field-err" id="e-lic"><i class="fas fa-circle-exclamation"></i> Please select an option</div>
        </div>

        <div class="fg" style="margin-bottom:20px;">
          <label>Enrolled in another degree or Masteral studies? <span class="req">*</span></label>
          <div class="radio-group" style="margin-top:8px;" onchange="validateStep(2)">
            <label class="r-opt"><input type="radio" name="enrolledPostGrad" value="yes"> Yes</label>
            <label class="r-opt"><input type="radio" name="enrolledPostGrad" value="no"> No</label>
            <label class="r-opt"><input type="radio" name="enrolledPostGrad" value="not_applicable"> Not applicable — I'm still a graduating student</label>
          </div>
          <div class="field-err" id="e-pg"><i class="fas fa-circle-exclamation"></i> Please select an option</div>
        </div>

        <div class="row r2">
          <div class="fg">
            <label>Licensure Exam Name <span class="opt-tag">opt</span></label>
            <input type="text" name="licensure_exam" placeholder="e.g. PRC Board Exam, Bar Exam">
          </div>
          <div class="fg">
            <label>Club / Organization Involvement <span class="opt-tag">opt</span></label>
            <input type="text" name="club_involvement" placeholder="e.g. ICpEP, IATD, SSG…">
          </div>
        </div>

      </div>
    </div>
  </div><!-- /step 3 -->

  <!-- ══════════════════════
       STEP 4 — EMPLOYMENT
  ══════════════════════ -->
  <div class="step" id="s3">
    <div class="step-card">
      <div class="card-header">
        <div class="card-header-icon"><i class="fas fa-briefcase"></i></div>
        <div>
          <div class="card-header-title">Employment Information</div>
          <div class="card-header-sub">Career details — required only if employed or self-employed</div>
        </div>
      </div>
      <div class="card-body">

        <div class="row" style="margin-bottom:20px;">
          <div class="fg">
            <label>Employment Status <span class="req">*</span></label>
            <select name="employment_status" id="empStatus" onchange="onEmpChange();validateStep(3)">
              <option value="">Select status</option>
              <option value="Employed">Employed</option>
              <option value="Self-employed">Self-employed</option>
              <option value="Unemployed">Unemployed</option>
              <option value="Student">Student</option>
              <option value="Prefer not to say">Prefer not to say</option>
            </select>
            <div class="field-err" id="e-empStatus"><i class="fas fa-circle-exclamation"></i> Please select employment status</div>
          </div>
        </div>

        <!-- Conditional fields -->
        <div class="emp-details" id="empDetails">
          <div class="emp-notice"><i class="fas fa-circle-check"></i> Employment details are required for your selected status</div>
          <div class="row r2">
            <div class="fg">
              <label>Company / Employer <span class="req">*</span></label>
              <input type="text" name="company" id="company" placeholder="Company name" oninput="validateStep(3)">
              <div class="field-err" id="e-company"><i class="fas fa-circle-exclamation"></i> Required</div>
            </div>
            <div class="fg">
              <label>Industry <span class="req">*</span></label>
              <input type="text" name="industry" id="industry" placeholder="e.g. IT, Healthcare, Finance" oninput="validateStep(3)">
              <div class="field-err" id="e-industry"><i class="fas fa-circle-exclamation"></i> Required</div>
            </div>
          </div>
          <div class="row r3">
            <div class="fg">
              <label>Position / Job Title <span class="req">*</span></label>
              <input type="text" name="position" id="position" placeholder="e.g. Software Engineer" oninput="validateStep(3)">
              <div class="field-err" id="e-position"><i class="fas fa-circle-exclamation"></i> Required</div>
            </div>
            <div class="fg">
              <label>Length of Service <span class="req">*</span></label>
              <input type="text" name="length_of_service" id="los" placeholder="e.g. 2 years" oninput="validateStep(3)">
              <div class="field-err" id="e-los"><i class="fas fa-circle-exclamation"></i> Required</div>
            </div>
            <div class="fg">
              <label>Previous Role <span class="opt-tag">opt</span></label>
              <input type="text" name="previous_role" placeholder="Previous job title">
            </div>
          </div>
          <div class="fg" style="margin-bottom:20px;">
            <label>Employment History <span class="opt-tag">opt</span></label>
            <textarea name="employment_history" placeholder="Brief summary of previous roles or companies…"></textarea>
          </div>
        </div>

        <div class="section-sep"><span>Career Feedback Survey</span></div>

        <div class="fg" style="margin-bottom:16px;">
          <label>Months to get your first job? <span class="opt-tag">opt</span></label>
          <div class="radio-group inline" style="margin-top:8px;">
            <label class="r-opt"><input type="radio" name="monthsToGetJob" value="less_than_1"> &lt; 1 month</label>
            <label class="r-opt"><input type="radio" name="monthsToGetJob" value="1_to_3"> 1–3 months</label>
            <label class="r-opt"><input type="radio" name="monthsToGetJob" value="4_to_6"> 4–6 months</label>
            <label class="r-opt"><input type="radio" name="monthsToGetJob" value="more_than_6"> &gt; 6 months</label>
          </div>
        </div>

        <div class="fg" style="margin-bottom:16px;">
          <label>My job is aligned with my degree <span class="opt-tag">opt</span></label>
          <div class="likert" style="margin-top:8px;">
            <label class="l-opt"><input type="radio" name="jobAligned" value="strongly_agree"> Strongly Agree</label>
            <label class="l-opt"><input type="radio" name="jobAligned" value="agree"> Agree</label>
            <label class="l-opt"><input type="radio" name="jobAligned" value="neutral"> Neutral</label>
            <label class="l-opt"><input type="radio" name="jobAligned" value="disagree"> Disagree</label>
            <label class="l-opt"><input type="radio" name="jobAligned" value="strongly_disagree"> Strongly Disagree</label>
          </div>
        </div>

        <div class="fg" style="margin-bottom:16px;">
          <label>College prepared me well for my career <span class="req">*</span></label>
          <div class="likert" style="margin-top:8px;" onchange="validateStep(3)">
            <label class="l-opt"><input type="radio" name="collegePrepared" value="strongly_agree"> Strongly Agree</label>
            <label class="l-opt"><input type="radio" name="collegePrepared" value="agree"> Agree</label>
            <label class="l-opt"><input type="radio" name="collegePrepared" value="neutral"> Neutral</label>
            <label class="l-opt"><input type="radio" name="collegePrepared" value="disagree"> Disagree</label>
            <label class="l-opt"><input type="radio" name="collegePrepared" value="strongly_disagree"> Strongly Disagree</label>
          </div>
          <div class="field-err" id="e-cp"><i class="fas fa-circle-exclamation"></i> Please select an option</div>
        </div>

        <div class="fg">
          <label>I am proud to be an OLFU alumni <span class="req">*</span></label>
          <div class="likert" style="margin-top:8px;" onchange="validateStep(3)">
            <label class="l-opt"><input type="radio" name="proudAlumni" value="strongly_agree"> Strongly Agree</label>
            <label class="l-opt"><input type="radio" name="proudAlumni" value="agree"> Agree</label>
            <label class="l-opt"><input type="radio" name="proudAlumni" value="neutral"> Neutral</label>
            <label class="l-opt"><input type="radio" name="proudAlumni" value="disagree"> Disagree</label>
            <label class="l-opt"><input type="radio" name="proudAlumni" value="strongly_disagree"> Strongly Disagree</label>
          </div>
          <div class="field-err" id="e-pa"><i class="fas fa-circle-exclamation"></i> Please select an option</div>
        </div>

      </div>
    </div>
  </div><!-- /step 4 -->

  <!-- ══════════════════════
       STEP 5 — ACCOUNT
  ══════════════════════ -->
  <div class="step" id="s4">
    <div class="step-card">
      <div class="card-header">
        <div class="card-header-icon"><i class="fas fa-lock"></i></div>
        <div>
          <div class="card-header-title">Create Your Account</div>
          <div class="card-header-sub">Final step — secure login credentials</div>
        </div>
      </div>
      <div class="card-body">

        <div class="info-banner"><i class="fas fa-triangle-exclamation"></i> Your Alumni ID will be auto-assigned by the Alumni Office after verifying your Student ID. You'll receive it via email upon approval.</div>

        <div class="row r2">
          <div class="fg">
            <label>Login Email <span class="req">*</span></label>
            <input type="email" name="email" id="loginEmail" class="synced" readonly placeholder="Fill your email in Step 2" oninput="validateStep(4)">
            <div class="sync-tag"><i class="fas fa-circle-check"></i> Auto-synced from your personal email in Step 2</div>
            <div class="field-err" id="e-loginEmail"><i class="fas fa-circle-exclamation"></i> Go to Step 2 to add your email</div>
          </div>
          <div class="fg">
            <label>Most Important Soft Skill <span class="opt-tag">opt</span></label>
            <select name="importantSoftSkill">
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
        </div>

        <div class="row r2">
          <div class="fg">
            <label>Password <span class="req">*</span></label>
            <div class="pw-wrap">
              <input type="password" name="password" id="pwIn" placeholder="Create a strong password" oninput="chkPw(this.value);validateStep(4)">
              <button type="button" class="pw-toggle" onclick="togPw('pwIn',this)"><i class="fas fa-eye"></i></button>
            </div>
            <div class="pw-strength">
              <div class="pw-req" id="r-len"><i class="fas fa-circle"></i> At least 8 characters</div>
              <div class="pw-req" id="r-up"><i class="fas fa-circle"></i> One uppercase letter</div>
              <div class="pw-req" id="r-lo"><i class="fas fa-circle"></i> One lowercase letter</div>
              <div class="pw-req" id="r-nu"><i class="fas fa-circle"></i> One number</div>
            </div>
            <div class="field-err" id="e-pw"><i class="fas fa-circle-exclamation"></i> Password must meet all requirements</div>
          </div>
          <div class="fg">
            <label>Confirm Password <span class="req">*</span></label>
            <div class="pw-wrap">
              <input type="password" name="confirmPassword" id="cpwIn" placeholder="Repeat password" oninput="chkMatch();validateStep(4)">
              <button type="button" class="pw-toggle" onclick="togPw('cpwIn',this)"><i class="fas fa-eye"></i></button>
            </div>
            <div class="match-msg" id="matchMsg"></div>
            <div class="field-err" id="e-cpw"><i class="fas fa-circle-exclamation"></i> Passwords must match</div>
          </div>
        </div>

        <div class="section-sep"><span>Data Privacy Consent</span></div>

        <div class="consent-scroll">
          I hereby consent to the release of all information and records indicated herewith in relevance to the processing of my school credentials and employment. By registering, I allow Our Lady of Fatima University and its accredited industry partners to use this information for <strong>EMPLOYMENT and RESEARCH PURPOSES</strong> in accordance with Republic Act No. 10173 (Data Privacy Act of the Philippines). This consent shall remain in effect until I revoke it by submitting a written revocation to Our Lady of Fatima University.
        </div>
        <div class="consent-chk">
          <input type="checkbox" name="consent" id="consentCb" onchange="validateStep(4)">
          <label for="consentCb">I have read and agree to the terms and conditions and data privacy consent statement above <span class="req">*</span></label>
        </div>
        <div class="field-err" id="e-consent"><i class="fas fa-circle-exclamation"></i> You must agree to proceed</div>

      </div>
    </div>
  </div><!-- /step 5 -->

  </div><!-- /reg-form-scroll -->

  <!-- ── NAVIGATION BAR ── -->
  <div class="reg-nav-outer">
  <div class="nav-bar">
    <button type="button" class="btn-prev" id="btnNavPrev" onclick="go(-1)" aria-label="Back to previous step" style="visibility:hidden">
      <i class="fas fa-chevron-left"></i> Back
    </button>
    <div class="nav-info" id="navInfo">Step 1 of 5</div>
    <button type="button" class="btn-next" id="bNext" onclick="go(1)">
      Next <i class="fas fa-chevron-right"></i>
    </button>
    <button type="submit" class="btn-submit" id="bSub" style="display:none">
      <i class="fas fa-check"></i> Create Account
    </button>
  </div>
  </div><!-- /reg-nav-outer -->

  </form>
</div><!-- /reg-shell -->
</main>
</div><!-- /reg-page -->

<!-- ── CAMERA OVERLAY ── -->
<div id="camOverlay">
  <div class="cam-card">
    <div class="cam-head">
      <h4 id="camTitle">Capture front of Student ID</h4>
      <button type="button" class="cam-close" onclick="closeIdCamera()">&times;</button>
    </div>
    <div class="cam-video-wrap">
      <video id="idcam_video" autoplay playsinline muted></video>
      <canvas id="idcam_canvas" style="display:none"></canvas>
      <div class="cam-frame"></div>
    </div>
    <div class="cam-status" id="idcam_status">Align your ID inside the frame…</div>
    <div class="cam-actions">
      <button type="button" class="btn-next" onclick="captureIdFromCamera()"><i class="fas fa-camera"></i> Capture</button>
      <button type="button" class="btn-prev" onclick="closeIdCamera()"><i class="fas fa-times"></i> Cancel</button>
    </div>
  </div>
</div>

<!-- ── SUCCESS MODAL ── -->
<div id="successModal">
  <div class="success-card">
    <div class="success-icon"><i class="fas fa-check"></i></div>
    <div class="success-title">Registration Submitted!</div>
    <p class="success-body">Your application is under review. The OLFU Alumni Office will assign your Alumni ID and notify you by email upon approval.</p>
    <a href="al_login.php" class="success-cta">Go to Login</a>
  </div>
</div>

<!-- ══════════════════════════════════════
     JAVASCRIPT — all logic preserved
══════════════════════════════════════ -->
<script>
/* ── GLOBALS ── */
var registrationType=<?php echo json_encode($registrationType, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
var cur=0, tot=5, visited=[false,false,false,false,false];
var stepLabels=['Student ID & Academic Info','Personal Information','Licensure & Post-Graduate','Employment Information','Create Your Account'];
var degs={
  'College of Computer Studies':['BS Information Technology','BS Computer Science','BS Information Systems'],
  'College of Engineering':['BS Civil Engineering','BS Electrical Engineering','BS Mechanical Engineering','BS Computer Engineering'],
  'College of Business & Accountancy':['BS Business Administration','BS Accountancy','BS Entrepreneurship'],
  'College of Arts and Sciences':['BA Communication','BS Psychology','BS Biology','BS Mathematics'],
  'College of Education':['Bachelor of Elementary Education','Bachelor of Secondary Education','Bachelor of Physical Education'],
  'College of Nursing':['BS Nursing'],
  'College of Medicine':['Doctor of Medicine']
};

function g(id){return document.getElementById(id);}
function refreshScrollbars(){
  var mainScroll=g('regFormScroll');
  if(mainScroll){
    if(cur===1||cur===2){
      mainScroll.classList.remove('needs-scroll');
    }else{
      var needsMain=mainScroll.scrollHeight>(mainScroll.clientHeight+2);
      mainScroll.classList.toggle('needs-scroll',needsMain);
    }
  }
  document.querySelectorAll('.consent-scroll').forEach(function(el){
    var needs=el.scrollHeight>(el.clientHeight+2);
    el.classList.toggle('needs-scroll',needs);
  });
}
function scrollRegToTop(){
  var sc=g('regFormScroll');
  if(sc)sc.scrollTo({top:0,behavior:'smooth'});
  else window.scrollTo({top:0,behavior:'smooth'});
}

/* ── DEGREE POPULATE ── */
function fillDeg(col){
  var d=g('degSel');
  d.innerHTML='<option value="">Select degree</option>';
  (degs[col]||[]).forEach(function(x){var o=document.createElement('option');o.value=x;o.textContent=x;d.appendChild(o);});
}

/* ── EMPLOYMENT TOGGLE ── */
function onEmpChange(){
  var v=g('empStatus').value;
  var show=(v==='Employed'||v==='Self-employed');
  g('empDetails').classList.toggle('visible',show);
}

/* ── EMAIL SYNC ── */
function syncEmail(){
  var v=g('personalEmail').value;
  g('loginEmail').value=v;
  validateStep(4);
}

function updateAgeFromBirthday(){
  var inp=g('birthday'),ageEl=g('age');
  if(!inp||!ageEl)return;
  var v=inp.value;
  if(!v){ageEl.value='';inp.classList.remove('err');validateStep(1);return;}
  var parts=v.split('-');
  if(parts.length!==3){ageEl.value='';return;}
  var y=parseInt(parts[0],10),mo=parseInt(parts[1],10)-1,d=parseInt(parts[2],10);
  if(isNaN(y)||isNaN(mo)||isNaN(d)){ageEl.value='';return;}
  var bd=new Date(y,mo,d);
  if(bd.getFullYear()!==y||bd.getMonth()!==mo||bd.getDate()!==d){ageEl.value='';return;}
  var today=new Date();
  today.setHours(0,0,0,0);
  bd.setHours(0,0,0,0);
  if(bd>today){ageEl.value='';inp.classList.add('err');validateStep(1);return;}
  inp.classList.remove('err');
  var age=today.getFullYear()-bd.getFullYear();
  var mDiff=today.getMonth()-bd.getMonth();
  if(mDiff<0||(mDiff===0&&today.getDate()<bd.getDate()))age--;
  if(age<0)age=0;
  ageEl.value=String(age);
  validateStep(1);
}

/* ══════════════════════
   VALIDATION
══════════════════════ */
function val(id){var e=g(id);return e?e.value.trim():'';}
function chkd(name){return!!document.querySelector('input[name="'+name+'"]:checked');}

function setErr(inputId,errId,ok){
  var inp=g(inputId),fe=g(errId);
  if(inp)inp.classList.toggle('err',!ok);
  if(fe)fe.classList.toggle('show',!ok);
  return ok;
}

function sanitizePhoneInput(inputId,warnId){
  if(typeof sanitizePhoneInput._tm!=='object')sanitizePhoneInput._tm={};
  var el=g(inputId);
  if(!el)return;
  var raw=el.value;
  var digits=raw.replace(/\D/g,'');
  if(raw!==digits){
    el.value=digits;
    var w=g(warnId);
    if(w){
      w.classList.add('show');
      clearTimeout(sanitizePhoneInput._tm[inputId]);
      sanitizePhoneInput._tm[inputId]=setTimeout(function(){w.classList.remove('show');},4000);
    }
  }
  validateStep(1);
}

function validateStep(step){
  var errs=[];
  if(step===0){
    if(!setErr('sNum','e-sNum',val('sNum')!==''))errs.push('sNum');
    if(!setErr('lname','e-lname',val('lname')!==''))errs.push('lname');
    if(!setErr('fname','e-fname',val('fname')!==''))errs.push('fname');
    if(!setErr('campus','e-campus',val('campus')!==''))errs.push('campus');
    if(!setErr('yearGrad','e-yearGrad',val('yearGrad')!==''))errs.push('yearGrad');
    if(!setErr('colSel','e-colSel',val('colSel')!==''))errs.push('colSel');
    if(!setErr('degSel','e-degSel',val('degSel')!==''))errs.push('degSel');
    var hasIdFront=g('id_card')&&g('id_card').files&&g('id_card').files.length>0;
    var ide=g('e-idCard');if(ide)ide.classList.toggle('show',!hasIdFront);
    if(!hasIdFront)errs.push('idCard');
    if(registrationType==='legacy'){
      var hasIdBack=g('id_card_back')&&g('id_card_back').files&&g('id_card_back').files.length>0;
      var idbe=g('e-idCardBack');if(idbe)idbe.classList.toggle('show',!hasIdBack);
      if(!hasIdBack)errs.push('idCardBack');
    }
    var hasPhoto=g('profile_photo')&&g('profile_photo').files&&g('profile_photo').files.length>0;
    var pfe=g('e-photo');if(pfe)pfe.classList.toggle('show',!hasPhoto);
    if(!hasPhoto)errs.push('photo');
  }
  if(step===1){
    if(!setErr('gender','e-gender',val('gender')!==''))errs.push('gender');
    if(!setErr('civil','e-civil',val('civil')!==''))errs.push('civil');
    var em=val('personalEmail');
    var emailOk=/^[^\s@]+@(gmail\.com|yahoo\.com)$/i.test(em);
    if(!setErr('personalEmail','e-personalEmail',emailOk))errs.push('email');
    var cDigits=val('contact').replace(/\D/g,'');
    var contactOk=cDigits.length>=10&&cDigits.length<=11;
    if(!setErr('contact','e-contact',contactOk))errs.push('contact');
    var ec=val('emergency_contact').replace(/\D/g,'');
    var emerOk=ec===''||(ec.length>=10&&ec.length<=11);
    if(!setErr('emergency_contact','e-emergency',emerOk))errs.push('emergency');
    if(!setErr('address','e-address',val('address')!==''))errs.push('address');
  }
  if(step===2){
    var licOk=chkd('passedLicensure'),pgOk=chkd('enrolledPostGrad');
    var fe1=g('e-lic'),fe2=g('e-pg');
    if(fe1)fe1.classList.toggle('show',!licOk);
    if(fe2)fe2.classList.toggle('show',!pgOk);
    if(!licOk)errs.push('lic');
    if(!pgOk)errs.push('pg');
  }
  if(step===3){
    if(!setErr('empStatus','e-empStatus',val('empStatus')!==''))errs.push('empStatus');
    var ev=val('empStatus');
    if(ev==='Employed'||ev==='Self-employed'){
      if(!setErr('company','e-company',val('company')!==''))errs.push('company');
      if(!setErr('industry','e-industry',val('industry')!==''))errs.push('industry');
      if(!setErr('position','e-position',val('position')!==''))errs.push('position');
      if(!setErr('los','e-los',val('los')!==''))errs.push('los');
    }
    var cpOk=chkd('collegePrepared'),paOk=chkd('proudAlumni');
    var fe3=g('e-cp'),fe4=g('e-pa');
    if(fe3)fe3.classList.toggle('show',!cpOk);
    if(fe4)fe4.classList.toggle('show',!paOk);
    if(!cpOk)errs.push('cp');
    if(!paOk)errs.push('pa');
  }
  if(step===4){
    if(!setErr('loginEmail','e-loginEmail',val('loginEmail')!==''))errs.push('loginEmail');
    var pwV=g('pwIn')?g('pwIn').value:'';
    var pwOk=pwV.length>=8&&/[A-Z]/.test(pwV)&&/[a-z]/.test(pwV)&&/[0-9]/.test(pwV);
    if(!setErr('pwIn','e-pw',pwOk))errs.push('pw');
    var cpwV=g('cpwIn')?g('cpwIn').value:'';
    var matchOk=cpwV!==''&&cpwV===pwV;
    if(!setErr('cpwIn','e-cpw',matchOk))errs.push('cpw');
    var consentOk=g('consentCb')&&g('consentCb').checked;
    var cfe=g('e-consent');if(cfe)cfe.classList.toggle('show',!consentOk);
    if(!consentOk)errs.push('consent');
  }
  /* update stepper visual */
  if(visited[step]){
    var se=g('se'+step);
    if(se)se.classList.toggle('show',errs.length>0);
    var sd=g('sd'+step);
    if(sd&&step!==cur){
      sd.classList.toggle('has-err',errs.length>0);
      if(errs.length===0){sd.classList.remove('has-err');}
    }
    var sl=g('sl'+step);
    if(sl&&step!==cur)sl.classList.toggle('has-err',errs.length>0);
    var st=g('st'+step);
    if(st)st.textContent=errs.length>0?(errs.length+' field'+(errs.length>1?'s':'')+' need attention'):stepLabels[step];
  }
  return errs.length===0;
}

/* ══════════════════════
   NAVIGATION
══════════════════════ */
function syncUI(){
  /* progress line */
  g('pFill').style.width=(cur/(tot-1)*80)+'%';
  /* dots */
  for(var i=0;i<tot;i++){
    var sd=g('sd'+i),sl=g('sl'+i);
    var isActive=(i===cur),isDone=(i<cur);
    sd.classList.toggle('active',isActive);
    if(!sd.classList.contains('has-err')){sd.classList.toggle('done',isDone&&!isActive);}
    sl.classList.toggle('active',isActive);
    if(!sl.classList.contains('has-err')){sl.classList.toggle('done',isDone&&!isActive);}
  }
  /* counter */
  g('stepCounter').innerHTML='Step '+(cur+1)+' of '+tot+' \u2014 '+stepLabels[cur];
  g('navInfo').textContent='Step '+(cur+1)+' of '+tot;
  /* buttons */
  g('btnNavPrev').style.visibility=cur===0?'hidden':'visible';
  g('bNext').style.display=cur===tot-1?'none':'inline-flex';
  g('bSub').style.display=cur===tot-1?'inline-flex':'none';
}

function scrollToFirstErrInStep(){
  var stepEl=g('s'+cur);
  if(!stepEl)return;
  var fe=stepEl.querySelector('.field-err.show');
  if(fe)fe.scrollIntoView({behavior:'smooth',block:'center'});
}

function showRequiredFieldsAlert(message){
  var id='reqFieldsModal';
  var old=document.getElementById(id);
  if(old) old.remove();
  var overlay=document.createElement('div');
  overlay.id=id;
  overlay.style.cssText='position:fixed;inset:0;background:rgba(17,25,22,.55);z-index:99999;display:flex;align-items:center;justify-content:center;padding:16px;';
  overlay.innerHTML=
    '<div style="max-width:460px;width:100%;background:#fff;border-radius:14px;border:1px solid #e5e7eb;box-shadow:0 20px 60px rgba(0,0,0,.25);padding:18px 18px 16px;">'
    +'<div style="display:flex;align-items:flex-start;gap:10px;">'
    +'<div style="width:36px;height:36px;border-radius:999px;background:#fff7ed;display:flex;align-items:center;justify-content:center;color:#c2410c;flex-shrink:0;"><i class="fas fa-triangle-exclamation"></i></div>'
    +'<div><div style="font-weight:700;color:#1f2937;margin-bottom:3px;">Incomplete Required Fields</div>'
    +'<div style="font-size:14px;color:#4b5563;line-height:1.45;">'+message+'</div></div></div>'
    +'<div style="display:flex;justify-content:flex-end;margin-top:14px;"><button type="button" id="reqFieldsOkBtn" style="border:0;background:#1a3d2b;color:#fff;padding:8px 14px;border-radius:999px;font-weight:600;cursor:pointer;">OK, I\'ll complete it</button></div>'
    +'</div>';
  document.body.appendChild(overlay);
  function close(){ overlay.remove(); }
  overlay.addEventListener('click', function(e){ if(e.target===overlay) close(); });
  var btn=document.getElementById('reqFieldsOkBtn');
  if(btn){ btn.addEventListener('click', close); btn.focus(); }
}

function go(dir){
  if(dir===1){
    visited[cur]=true;
    if(!validateStep(cur)){
      scrollToFirstErrInStep();
      showRequiredFieldsAlert('Please fill up all required fields in this step before going to the next page.');
      syncUI();
      return;
    }
  }
  visited[cur]=true;
  g('s'+cur).classList.remove('on');
  cur=Math.max(0,Math.min(tot-1,cur+dir));
  g('s'+cur).classList.add('on');
  scrollRegToTop();
  syncUI();
  refreshScrollbars();
  if(visited[cur])validateStep(cur);
}

function jumpTo(idx){
  if(idx===cur)return;
  if(idx>cur){
    for(var s=cur;s<idx;s++){
      visited[s]=true;
      if(!validateStep(s)){
        g('s'+cur).classList.remove('on');
        cur=s;
        g('s'+cur).classList.add('on');
        scrollRegToTop();
        syncUI();
        refreshScrollbars();
        validateStep(cur);
        scrollToFirstErrInStep();
        showRequiredFieldsAlert('Please fill up all required fields in Step '+(s+1)+' before skipping ahead.');
        return;
      }
    }
  }
  visited[cur]=true;
  g('s'+cur).classList.remove('on');
  cur=idx;
  g('s'+cur).classList.add('on');
  scrollRegToTop();
  syncUI();
  refreshScrollbars();
  if(visited[cur])validateStep(cur);
}

/* ══════════════════════
   FILE PREVIEWS
══════════════════════ */
function handleIdFront(e){
  var f=e.target.files[0];if(!f)return;
  var reader=new FileReader();
  reader.onload=function(ev){
    g('fPrev').innerHTML='<img src="'+ev.target.result+'">';
    g('fBox').classList.add('filled');
    runOcr(f,'front');
    validateStep(0);
  };
  reader.readAsDataURL(f);
}
function handleIdBack(e){
  var f=e.target.files[0];if(!f)return;
  var reader=new FileReader();
  reader.onload=function(ev){
    g('bPrev').innerHTML='<img src="'+ev.target.result+'">';
    g('bBox').classList.add('filled');
    runOcr(f,'back');
    validateStep(0);
  };
  reader.readAsDataURL(f);
}
function previewAvatar(inp){
  var f=inp.files[0];if(!f)return;
  var reader=new FileReader();
  reader.onload=function(ev){
    g('avatarEl').innerHTML='<img src="'+ev.target.result+'">';
    validateStep(0);
  };
  reader.readAsDataURL(f);
}

/* ── OCR ── */
function ocrLinesFromData(data){
  var lines=[];
  function pushFrom(arr){
    if(!Array.isArray(arr))return;
    arr.forEach(function(l){
      var t=(l&&typeof l.text==='string')?l.text:'';
      t=t.replace(/\|/g,'I').replace(/\s+/g,' ').trim();
      if(t)lines.push(t);
    });
  }
  if(data){
    pushFrom(data.lines);
    if(!lines.length&&Array.isArray(data.paragraphs)){
      data.paragraphs.forEach(function(p){ pushFrom(p&&p.lines); });
    }
  }
  return lines;
}
/* Reading order from word boxes (often fixes jumbled / merged lines on IDs) */
function ocrLinesFromWords(data){
  if(!data||!Array.isArray(data.words)||data.words.length===0)return[];
  var words=data.words.map(function(w){
    if(!w)return null;
    var tx=(typeof w.text==='string'?w.text:'').replace(/\|/g,'I').trim();
    if(!tx)return null;
    var b=w.bbox||{};
    var x0=b.x0!=null?b.x0:(w.x0!=null?w.x0:0);
    var y0=b.y0!=null?b.y0:(w.y0!=null?w.y0:0);
    return{text:tx,x0:x0,y0:y0};
  }).filter(Boolean);
  if(!words.length)return[];
  words.sort(function(a,b){
    var dy=a.y0-b.y0;
    if(Math.abs(dy)>14)return dy;
    return a.x0-b.x0;
  });
  var out=[],row=[],lastY=-1e9;
  words.forEach(function(w){
    if(lastY>-1e8&&Math.abs(w.y0-lastY)>14){
      if(row.length)out.push(row.join(' '));
      row=[w.text];
    }else row.push(w.text);
    lastY=w.y0;
  });
  if(row.length)out.push(row.join(' '));
  return out;
}
function ocrLineTextsForNameParse(data){
  var lw=ocrLinesFromWords(data);
  var ll=ocrLinesFromData(data);
  var wJoin=lw.join('\n');
  if(lw.length>=2&&/\bNAME\b/i.test(wJoin)&&/\bPROGRAM\b/i.test(wJoin))return lw;
  /* Alumni / legacy cards often have no PROGRAM line — word boxes still read L→R, T→B better than raw lines. */
  if(lw.length>=2&&/\b(NAME|ALUMNI|NOME)\b/i.test(wJoin)&&/\b(ALUMNI|DEGREE|COURSE|COLLEGE|BATCH|YEAR|GRAD|VALID)\b/i.test(wJoin))return lw;
  if(lw.length>=3&&/\b(NAME|ALUMNI)\b/i.test(wJoin))return lw;
  return ll.length?ll:lw;
}
function toTitleCaseName(s){
  if(!s)return'';
  return s.split(/(\s+|-)/).map(function(p){
    if(!p||/^\s+$/.test(p)||p==='-')return p;
    var low=p.toLowerCase();
    if(low==='del'||low==='de'||low==='la'||low==='da'||low==='van'||low==='st.')return low;
    return p.charAt(0).toUpperCase()+p.slice(1).toLowerCase();
  }).join('');
}
/* Lines that are not a person name (header, campus, student no., etc.) */
function isOlfuNameNoiseLine(line){
  if(!line)return true;
  var l=line.trim();
  if(l.length<2||l.length>56)return true;
  if(/^[\d\s\-–.]+$/i.test(l))return true;
  if(/^(STUDENT|STUDY|UNDERGRAD)/i.test(l)&&l.length<24)return true;
  if(/\b(OUR\s+LADY|FATIMA\s+UNIVERSITY|UNIVERSITY|COLLEGE\s+OF)\b/i.test(l))return true;
  if(/\b(ANTIPOLO|VALENZUELA|PAMPANGA|QUEZON\s+CITY)\b/i.test(l))return true;
  if(/\bCAMPUS\b/i.test(l))return true;
  if(/\b(STUDENT\s*NO|STUDENT\s*NUMBER|ALUMNI\s*(?:ID|NO)|I\.?\s*D\.?\s*NO)\b/i.test(l))return true;
  if(/\b(SEM\.|S\.Y\.|SY\s*\d)/i.test(l))return true;
  if(/^BS\b|\bBACHELOR\b|\bINFORMATION\s+TECHNOLOGY\b|\bCOMPUTER\b/i.test(l))return true;
  if(/^(INFORMATION|TECHNOLOGY|SCIENCE|ENGINEERING|NURSING|BUSINESS|ACCOUNTANCY|EDUCATION|MEDICINE|VALIDITY|DESIGNATION)$/i.test(l))return true;
  if(/\b(INFORMATION|TECHNOLOGY)\b/i.test(l)&&l.length<28)return true;
  return false;
}
function olfuGivenNameScore(line){
  var l=(line||'').trim();
  if(!l||isOlfuNameNoiseLine(l))return-999;
  var s=0;
  if(/\bMA\.\b|\bM\.?\s*A\.\b/i.test(l))s+=14;
  if(/\b(MARIA|MARY|JOSE|JOSEPH)\b/i.test(l))s+=5;
  if(/\b(JR\.?|SR\.?|II|III|IV)\b/i.test(l))s+=5;
  var parts=l.replace(/[^A-Za-zÑñ.\s'-]/g,' ').trim().split(/\s+/).filter(Boolean);
  if(parts.length>=2){
    var tok=parts[parts.length-1];
    if(/^([A-Za-z])\.?$/.test(tok)&&tok.replace(/\./g,'').length<=1)s+=10;
  }
  if(parts.length>=3)s+=4;
  if(parts.length>=2)s+=2;
  if(/\b(ANTONETTE|JUAN|MARIA|JOSE|JOSEPH|NICHOLE|NICOLE|MICHAEL|CHRISTIAN|ANGEL|ANGELA|PATRICIA|CARLOS|ANA|MARK|PAUL|JOHN|MARY|CATHERINE|KRISTINE|DANIEL|JAMES|RYAN|KEVIN|STEPHANIE|JENNIFER)\b/i.test(l))s+=8;
  return s;
}
function olfuSurnameLineScore(line){
  var l=(line||'').trim();
  if(!l||isOlfuNameNoiseLine(l))return-999;
  if(/\bMA\.\b|\bM\.?\s*A\.\b/i.test(l))return 0;
  var clean=l.replace(/[^A-Za-zÑñ\s.'-]/g,' ').trim();
  var parts=clean.split(/\s+/).filter(Boolean);
  if(!parts.length)return-999;
  if(parts.length===1){
    var w=parts[0];
    if(/^(INFORMATION|TECHNOLOGY|SCIENCE|ENGINEERING|NURSING|BUSINESS|VALIDITY|DESIGNATION|STUDENT|UNIVERSITY|COLLEGE|PROGRAM|CAMPUS)$/i.test(w))return-999;
    if(w.length>=2&&w.length<=20&&/^[A-Za-zÑñ.'-]+$/i.test(w))return 18;
    if(w.length>20)return 0;
    return 0;
  }
  if(parts.length>=2&&parts.length<=4){
    var allAlpha=parts.every(function(p){return/^[A-Za-zÑñ.'-]+$/i.test(p)&&p.length>=1;});
    if(allAlpha)return 10;
  }
  return 0;
}
/* Fix common OCR joins on OLFU given line — do NOT use M.A.+letter or it eats "A" from "ANTONETTE" after "MA. " */
function normalizeOlfuGivenLine(s){
  if(!s)return'';
  var t=s.replace(/\s+/g,' ').trim();
  t=t.replace(/\b(M[Aa]?)\.([A-Za-zÑñ]{2,})\b/gi,'MA. $2');
  t=t.replace(/\b(M)\s+(A\.?)\b/gi,'MA.');
  t=t.replace(/\bM\s+A\.?\b/gi,'MA.');
  t=t.replace(/\bM\.A\.(?=\s|$)/gi,'MA.');
  t=t.replace(/\b(MA)\s*(\.)\s*(?=[A-Za-zÑñ])/gi,'$1$2 ');
  t=t.replace(/\b(Mu)\s*(\.)\s*(?=[A-Za-zÑñ])/gi,'$1$2 ');
  t=t.replace(/\b(M[Aa]?)(\.)(?=[A-Za-zÑñ])/gi,function(_,p,d){return 'MA'+d+' ';});
  t=t.replace(/([a-zñ])([A-ZÑ])/g,function(_,a,b){return a+' '+b;});
  return t.replace(/\s+/g,' ').trim();
}
/* Pull "Ma. … [C.]" from green band text; avoids token pipeline errors */
function parseOlfuGivenLineByRegex(fm){
  if(!fm)return null;
  var s=normalizeOlfuGivenLine(fm).replace(/\s+/g,' ').trim();
  var pref='(?:MA\\.|M\\.A\\.|Ma\\.|M\\.?\\s*A\\.|MARIA\\b)';
  m=s.match(new RegExp('^'+pref+'\\s+(.+?)\\s+([A-Za-z])(?:\\.|$)\\s*$','i'));
  if(m&&m[1]&&m[2]){
    var body=m[1].replace(/\s+/g,' ').trim();
    if(body.length>=2){
      var prefMaria=/^MARIA\b/i.test(s)?'Maria ':'Ma. ';
      return{first:prefMaria+toTitleCaseName(body),mi:m[2].toUpperCase()};
    }
  }
  m=s.match(new RegExp('^'+pref+'\\s+(.+)$','i'));
  if(m&&m[1]){
    var rest=m[1].replace(/\s+/g,' ').trim();
    var pm=popMiddleInitialFromGivenParts(rest.split(/\s+/).filter(Boolean));
    var joined=pm.parts.join(' ');
    if(joined.length>=1){
      var pfx=/^MARIA\b/i.test(s)?'Maria ':'Ma. ';
      return{first:pfx+toTitleCaseName(joined),mi:pm.mi||''};
    }
  }
  m=s.match(/^([A-Za-zÑñ]+(?:\s+[A-Za-zÑñ]+)*)\s+([A-Za-z])(?:\.|$)\s*$/i);
  if(m&&m[1]&&m[2]&&!/^(CABANG|PROGRAM|STUDENT|NAME)$/i.test(m[1])){
    var p2=m[1].trim().split(/\s+/);
    if(p2.length>=1)return{first:toTitleCaseName(m[1]),mi:m[2].toUpperCase()};
  }
  return null;
}
/* Tokenize given line; merge MA / . split across tokens */
function tokenizeOlfuGivenParts(fm){
  var s=normalizeOlfuGivenLine(fm);
  var raw=s.split(/\s+/).filter(Boolean);
  var parts=[];
  for(var i=0;i<raw.length;i++){
    var a=raw[i],b=raw[i+1];
    if(/^M$/i.test(a)&&b&&/^A\.?$/i.test(b)){
      parts.push('MA.');
      i+=1;
      continue;
    }
    if(/^MA$/i.test(a)&&b==='.'){
      parts.push('MA.');
      i+=1;
      continue;
    }
    if(/^MA$/i.test(a)&&b&&/^[A-Za-zÑñ]{2,}/.test(b)){
      parts.push('MA.');
      continue;
    }
    parts.push(a);
  }
  return parts;
}
/* Last token is middle initial only when it is a single letter (optional .); avoid eating a short name */
function popMiddleInitialFromGivenParts(parts){
  if(!parts||parts.length<2)return{parts:parts||[],mi:''};
  var last=parts[parts.length-1];
  var oneLetter=/^[A-Za-z](?:\.|$)/.test(last)&&last.replace(/\./g,'').length===1;
  if(!oneLetter)return{parts:parts,mi:''};
  if(parts.length>=3)return{parts:parts.slice(0,-1),mi:last.charAt(0).toUpperCase()};
  if(parts.length===2){
    var first=parts[0];
    if(/^(MA\.|M\.A\.|MARIA|MARY|MU\.|MC\.)/i.test(first)||first.length>=4)return{parts:parts.slice(0,-1),mi:last.charAt(0).toUpperCase()};
  }
  return{parts:parts,mi:''};
}
/* Decide which of two OCR lines is surname vs given (Ma. / middle initial vs Cabang). */
function orderedOlfuPair(lineA,lineB){
  var gA=olfuGivenNameScore(lineA),gB=olfuGivenNameScore(lineB);
  var sA=olfuSurnameLineScore(lineA),sB=olfuSurnameLineScore(lineB);
  if(gB>gA+2&&sA>sB+2)return parseOlfuTwoLineName(lineA,lineB);
  if(gA>gB+2&&sB>sA+2)return parseOlfuTwoLineName(lineB,lineA);
  if(sA>=sB&&gB>=gA)return parseOlfuTwoLineName(lineA,lineB);
  if(sB>=sA&&gA>=gB)return parseOlfuTwoLineName(lineB,lineA);
  return parseOlfuTwoLineName(lineA,lineB);
}
/* OLFU-style ID: surname line + "MA. ANTONETTE C." (given + middle initial) */
function parseOlfuTwoLineName(lastLine,firstMidLine){
  if(!lastLine||!firstMidLine)return null;
  var last=lastLine.replace(/\s+/g,' ').replace(/[^A-Za-zÑñ\s.'-]/g,'').trim();
  var fm=firstMidLine.replace(/\s+/g,' ').split(/\bPROGRAM\b/i)[0].trim();
  fm=fm.replace(/[^A-Za-zÑñ.\s'-]/g,' ').replace(/\s+/g,' ').trim();
  if(last.length<2||fm.length<2)return null;
  if(/^[\d\s\-–]+$/.test(last)||/^[\d\s\-–]+$/.test(fm))return null;
  if(isOlfuNameNoiseLine(last)||isOlfuNameNoiseLine(fm))return null;
  if(/\b(STUDENT\s*NO|OUR\s+LADY|UNIVERSITY|CAMPUS|FATIMA)\b/i.test(last))return null;
  var rx=parseOlfuGivenLineByRegex(fm);
  if(rx&&rx.first)return{last:toTitleCaseName(last),first:rx.first,mi:rx.mi||''};
  var parts=tokenizeOlfuGivenParts(fm);
  if(!parts.length)return null;
  var pm=popMiddleInitialFromGivenParts(parts);
  parts=pm.parts;
  if(!parts.length)return null;
  var first=parts.join(' ');
  return{last:toTitleCaseName(last),first:toTitleCaseName(first),mi:pm.mi||''};
}
function parseNameBetweenNameAndProgram(block){
  /* Student ID: NAME … PROGRAM. Alumni card: often NAME … DEGREE / BATCH / ALUMNI ID (no PROGRAM). */
  var m=block.replace(/\r/g,'\n').match(/\bNAME\s*([\s\S]{0,320}?)\b(?:PROGRAM|DEGREE|COURSE|COLLEGE|BATCH|ALUMNI\s*(?:ID|NO\.?|NUMBER|#)|YEAR\s*(?:GRAD|OF)|CLASS\s+OF|VALID)\b/i);
  if(!m||!m[1]){
    m=block.replace(/\r/g,'\n').match(/\bNAME\s*([\s\S]{0,320}?)\bPROGRAM\b/i);
  }
  if(!m||!m[1])return null;
  var raw=m[1].replace(/\r/g,'\n');
  var segs=raw.split(/\n/).map(function(l){
    return l.trim().replace(/\s+/g,' ').replace(/^[\s:;]+/,'').replace(/^(?:Name|NAME)\s*[:\s#\-–—]+/i,'');
  }).filter(function(l){
    return l&&!/^NAME$/i.test(l)&&!isOlfuNameNoiseLine(l);
  });
  if(segs.length>=2){
    var p=orderedOlfuPair(segs[0],segs[1]);
    if(p)return p;
  }
  if(segs.length>=3){
    var q=orderedOlfuPair(segs[1],segs[2]);
    if(q)return q;
    q=orderedOlfuPair(segs[0],segs[2]);
    if(q)return q;
  }
  if(segs.length===1){
    var oneLine=splitNameFromLine(segs[0]);
    if(oneLine)return oneLine;
    var parts=segs[0].split(/\s+/).filter(Boolean);
    if(parts.length>=4&&parts[0].length>=2){
      var last=parts[0];
      var lastTok=parts[parts.length-1];
      var mi2='';
      if(/^([A-Za-z])\.?$/i.test(lastTok)){ mi2=lastTok.charAt(0).toUpperCase(); parts=parts.slice(0,-1); }
      var first=parts.slice(1).join(' ');
      if(first.length>=2)return{last:toTitleCaseName(last),first:toTitleCaseName(first),mi:mi2};
    }
  }
  return null;
}
/** OLFU alumni card (front): one line "MICHAELA M. MARGO" = First, M.I., Last — not Last, First. */
function formatMiddleInitialToken(tok){
  if(!tok)return'';
  var t=String(tok).replace(/\s+/g,'').replace(/\./g,'');
  if(!t.length)return'';
  if(t.length===1)return t.toUpperCase()+'.';
  return String(tok).trim().charAt(0).toUpperCase()+'.';
}
function parseAlumniFirstMiddleLastTokens(parts){
  if(!parts||parts.length<3)return null;
  function isShortInitial(tok){
    if(!tok)return false;
    var u=tok.replace(/\./g,'').trim();
    return u.length===1&&/^[A-Za-zÑñ]$/.test(u);
  }
  if(parts.length===3&&isShortInitial(parts[1])){
    return{
      first:toTitleCaseName(parts[0]),
      last:toTitleCaseName(parts[2]),
      mi:formatMiddleInitialToken(parts[1])
    };
  }
  if(parts.length>=4&&isShortInitial(parts[parts.length-2])){
    return{
      first:toTitleCaseName(parts.slice(0,-2).join(' ')),
      last:toTitleCaseName(parts[parts.length-1]),
      mi:formatMiddleInitialToken(parts[parts.length-2])
    };
  }
  return null;
}
function splitNameFromLine(raw){
  if(!raw)return null;
  var stop=/\b(PROGRAM|PROG\.?|COURSE|COLLEGE|CAMPUS|BIRTH|BIRTHDAY|VALID|THRU|VAILD|SEX|GENDER|BATCH|ALUMNI\s*(?:ID|NO\.?|NUMBER)|STUDENT\s*(NO|Number|ID|I\.?D\.?)|I\.?D\.?\s*NO)/i;
  var line=raw.replace(/\s+/g,' ').trim();
  /* "Name: MICHAELA M. MARGO" (alumni card) */
  line=line.replace(/^\s*(?:Name|NAME)\s*[:\#\-–—\s]+\s*/i,'').trim();
  var cut=line.search(/\d{3,}/);
  if(cut>4)line=line.slice(0,cut).trim();
  line=line.split(stop)[0].replace(/[_#|]+$/g,'').trim();
  if(line.length<3)return null;
  if(/,/.test(line)){
    var a=line.split(',');
    var last=a[0].replace(/[^A-Za-zÑñ\s.'-]/g,'').trim();
    var right=a.slice(1).join(',').replace(/[^A-Za-zÑñ\s.'-]/g,' ').trim().split(/\s+/).filter(Boolean);
    if(last.length<2||right.length<1)return null;
    var first=right[0];
    var mi=right.length>1?right[1].charAt(0).toUpperCase():'';
    if(/^(JR|SR|II|III|IV)\.?$/i.test(first)&&right.length>1){first=right[1];mi=right.length>2?right[2].charAt(0).toUpperCase():mi;}
    return{last:toTitleCaseName(last),first:toTitleCaseName(first),mi:mi};
  }
  var parts=line.replace(/[^A-Za-zÑñ\s.'-]/g,' ').split(/\s+/).filter(Boolean);
  var pAl=parseAlumniFirstMiddleLastTokens(parts);
  if(pAl)return pAl;
  if(parts.length===2){
    var pair=orderedOlfuPair(parts[0],parts[1]);
    if(pair)return pair;
    return null;
  }
  if(parts.length===3){
    var pair3=orderedOlfuPair(parts[0],parts[1]+' '+parts[2]);
    if(pair3)return pair3;
    pair3=orderedOlfuPair(parts[1],parts[0]+' '+parts[2]);
    if(pair3)return pair3;
    return{
      last:toTitleCaseName(parts[2]),
      first:toTitleCaseName(parts[0]),
      mi:formatMiddleInitialToken(parts[1])
    };
  }
  if(parts.length>3){
    var pair4=orderedOlfuPair(parts[0],parts.slice(1).join(' '));
    if(pair4)return pair4;
    return{
      last:toTitleCaseName(parts[parts.length-1]),
      first:toTitleCaseName(parts[0]),
      mi:formatMiddleInitialToken(parts[1])
    };
  }
  return null;
}
function extractNameFromOcr(fullText,lineTexts){
  var joined=lineTexts.length?lineTexts.join('\n'):fullText;
  var lines=joined.split(/\n/).map(function(l){return l.trim().replace(/\s+/g,' ');}).filter(Boolean);
  var i,j,t,rest,p,m;
  var block=joined.replace(/\r/g,'');
  p=parseNameBetweenNameAndProgram(block);
  if(p)return p;
  var labelRe=/^\s*(?:STUDENT\s*)?NAME\s*[.:]?\s*$/i;
  var labelInline=/^(?:STUDENT\s*)?NAME\s*[:\s#|._\-–—]+\s*(.+)$/i;
  var fullNameRe=/^FULL\s*NAME\s*[:\s#|._\-–—]+\s*(.+)$/i;
  var surRe=/^SURNAME\s*[:\s#|._\-–—]+\s*(.+)$/i;
  var givenRe=/^(?:GIVEN|FIRST)\s*NAME\s*[:\s#|._\-–—]+\s*(.+)$/i;
  var lastNameLbl=/^(?:LAST|SUR(?:NAME)?)\s*NAME\s*[:\s#|._\-–—]+\s*(.+)$/i;
  var firstNameLbl=/^FIRST\s*NAME\s*[:\s#|._\-–—]+\s*(.+)$/i;
  for(i=0;i<lines.length;i++){
    t=lines[i];
    if(labelRe.test(t)){
      var buf=[];
      for(j=i+1;j<lines.length&&buf.length<8;j++){
        var u=lines[j];
        if(/^\s*PROGRAM\b/i.test(u))break;
        if(!isOlfuNameNoiseLine(u))buf.push(u);
      }
      if(buf.length>=2){
        p=orderedOlfuPair(buf[0],buf[1]);
        if(p)return p;
      }
      if(buf.length>=3){
        p=orderedOlfuPair(buf[1],buf[2]);
        if(p)return p;
        p=orderedOlfuPair(buf[0],buf[2]);
        if(p)return p;
      }
      if(buf.length===1){p=splitNameFromLine(buf[0]);if(p)return p;}
      if(!buf.length&&i+1<lines.length){
        var nxt=lines[i+1];
        if(!isOlfuNameNoiseLine(nxt)&&!/^\s*PROGRAM\b/i.test(nxt)){p=splitNameFromLine(nxt);if(p)return p;}
      }
    }
    if(labelInline.test(t)){
      rest=t.replace(labelInline,'$1').trim();
      if(rest.length>=3){p=splitNameFromLine(rest);if(p)return p;}
    }
    if(fullNameRe.test(t)){
      rest=t.replace(fullNameRe,'$1').trim();
      p=splitNameFromLine(rest);if(p)return p;
    }
    if(lastNameLbl.test(t)&&i+1<lines.length&&firstNameLbl.test(lines[i+1])){
      var ln=t.replace(lastNameLbl,'$1').replace(/[^A-Za-zÑñ\s.'-]/g,' ').trim().split(/\s+/).filter(Boolean);
      var fn=lines[i+1].replace(firstNameLbl,'$1').trim().split(/\s+/).filter(Boolean);
      if(ln.length&&fn.length){
        return{last:toTitleCaseName(ln.join(' ')),first:toTitleCaseName(fn[0]),mi:fn[1]?fn[1].charAt(0).toUpperCase():''};
      }
    }
    if(surRe.test(t)){
      var sur=t.replace(surRe,'$1').trim().split(/\s+/)[0];
      if(i+1<lines.length&&givenRe.test(lines[i+1])){
        var gn=lines[i+1].replace(givenRe,'$1').trim().split(/\s+/).filter(Boolean);
        if(sur&&gn.length){return{last:toTitleCaseName(sur),first:toTitleCaseName(gn[0]),mi:gn[1]?gn[1].charAt(0).toUpperCase():''};}
      }
    }
  }
  m=block.match(/(?:^|\n)\s*NAME\s*\n\s*([^\n]+)\s*\n\s*([^\n]+)/im);
  if(m&&m[1]&&m[2]){p=orderedOlfuPair(m[1].trim(),m[2].trim());if(p)return p;}
  m=block.match(/(?:^|\n)\s*(?:STUDENT\s+)?NAME\s*[:\s#|._\-–—]+\s*([^\n]+)/im);
  if(m&&m[1]){p=splitNameFromLine(m[1]);if(p)return p;}
  m=block.match(/FULL\s*NAME\s*[:\s#|._\-–—]+\s*([^\n]+)/im);
  if(m&&m[1]){p=splitNameFromLine(m[1]);if(p)return p;}
  m=block.match(/SURNAME\s*[:\s#|._\-–—]+\s*([^\n]+)[\s\S]{0,120}?(?:GIVEN|FIRST)\s*NAME\s*[:\s#|._\-–—]+\s*([^\n]+)/i);
  if(m&&m[1]&&m[2]){
    var s2=m[1].trim().split(/[\s,]+/)[0];
    var g2=m[2].trim().split(/\s+/).filter(Boolean);
    if(s2&&g2.length)return{last:toTitleCaseName(s2),first:toTitleCaseName(g2[0]),mi:g2[1]?g2[1].charAt(0).toUpperCase():''};
  }
  for(i=0;i<lines.length;i++){
    t=lines[i];
    if(/^[A-Za-zÑñ][A-Za-zÑñ\s.'-]{0,42},\s*[A-Za-zÑñ]/.test(t)&&!/\d{4}-\d{2}/.test(t)){
      p=splitNameFromLine(t);if(p)return p;
    }
  }
  return null;
}
/** Extra patterns when alumni cards omit PROGRAM / use LAST & FIRST labels. */
function parseLegacyAlumniNameHeuristics(block, lines){
  var p,a,b,i,t,rest,lastN,firstN,midN,parts,m;
  if(!lines||!lines.length){
    lines=block.split(/\n/).map(function(l){return l.trim().replace(/\s+/g,' ');}).filter(Boolean);
  }
  m=block.match(/\b(?:ALUMNI\s+)?NAME\s*[:\#\-]?\s*\n+\s*([^\n]{2,96})\s*\n+\s*([^\n]{2,96})/im);
  if(m&&m[1]&&m[2]){
    a=m[1].trim(); b=m[2].trim();
    if(!isOlfuNameNoiseLine(a)&&!isOlfuNameNoiseLine(b)){
      p=orderedOlfuPair(a,b); if(p) return p;
      p=orderedOlfuPair(b,a); if(p) return p;
    }
  }
  for(i=0;i<lines.length;i++){
    t=lines[i];
    if(/^(?:ALUMNI\s+)?NAME\s*$/i.test(t)){
      if(lines[i+1]&&lines[i+2]){
        p=orderedOlfuPair(lines[i+1],lines[i+2]); if(p) return p;
        p=orderedOlfuPair(lines[i+2],lines[i+1]); if(p) return p;
      }
      if(lines[i+1]){
        p=splitNameFromLine(lines[i+1]); if(p) return p;
      }
      continue;
    }
    if(/^(?:ALUMNI\s+)?NAME\s*[:\#\-]\s*(.+)$/i.test(t)){
      rest=t.replace(/^(?:ALUMNI\s+)?NAME\s*[:\#\-]\s*/i,'').trim();
      if(rest.length>=3){
        p=splitNameFromLine(rest); if(p) return p;
      }
    }
  }
  lastN=''; firstN=''; midN='';
  for(i=0;i<lines.length;i++){
    t=lines[i];
    var ml=/^(?:LAST|SUR(?:NAME)?)\s*[:\#\-]?\s*(.+)$/i.exec(t);
    if(ml&&ml[1]){ lastN=ml[1].replace(/[^A-Za-zÑñ\s.'-]/g,'').trim(); continue; }
    var mf=/^(?:FIRST|GIVEN)\s*[:\#\-]?\s*(.+)$/i.exec(t);
    if(mf&&mf[1]){ firstN=mf[1].replace(/[^A-Za-zÑñ\s.'-]/g,'').trim(); continue; }
    var mm=/^(?:M\.?I\.?|MIDDLE(?:\s*NAME)?)\s*[:\#\-]?\s*([A-Za-z])(?:\.|\s|$)/i.exec(t);
    if(mm&&mm[1]){ midN=mm[1].toUpperCase(); continue; }
  }
  if(lastN&&firstN){
    parts=firstN.split(/\s+/).filter(Boolean);
    midN=midN||'';
    if(!midN&&parts.length>1){
      var lt=parts[parts.length-1];
      if(/^[A-Za-z]\.?$/.test(lt)&&lt.length<=2){
        midN=lt.charAt(0).toUpperCase();
        parts=parts.slice(0,-1);
      }
    }
    return{last:toTitleCaseName(lastN),first:toTitleCaseName(parts.join(' ')),mi:midN};
  }
  m=block.match(/\bLAST\s+NAME\s*[:\#\-]?\s*([^\n]+)\s*\n\s*FIRST\s+NAME\s*[:\#\-]?\s*([^\n]+)/im);
  if(m&&m[1]&&m[2]){
    var ln=m[1].trim().split(/\s+/).filter(Boolean);
    var fn=m[2].trim().split(/\s+/).filter(Boolean);
    if(ln.length&&fn.length){
      midN='';
      if(fn.length>1&&/^[A-Za-z]\.?$/.test(fn[fn.length-1])){
        midN=fn[fn.length-1].charAt(0).toUpperCase();
        fn=fn.slice(0,-1);
      }
      return{last:toTitleCaseName(ln.join(' ')),first:toTitleCaseName(fn.join(' ')),mi:midN};
    }
  }
  return null;
}
function autofillFromOcr(text,data){
  var normalized=text.normalize('NFKC').replace(/\s+/g,' ').trim();
  var snM=normalized.match(/(03\d{2}[-\s]?\d{3,4}[-\s]?\d{3})/)||normalized.match(/\b(\d{4}[-–]\d{5,8})\b/)||normalized.match(/\b(\d{10,12})\b/)||normalized.match(/\b(0\d{9,10})\b/);
  if(snM&&!g('sNum').value){g('sNum').value=snM[1].replace(/\s/g,'').replace(/–/g,'-');}
  var lineTexts=ocrLineTextsForNameParse(data||{});
  if(!lineTexts.length){lineTexts=text.split(/\n/).map(function(l){return l.trim().replace(/\s+/g,' ');}).filter(Boolean);}
  var nameParts=extractNameFromOcr(text,lineTexts);
  if(nameParts){
    if(!g('lname').value&&nameParts.last)g('lname').value=nameParts.last;
    if(!g('fname').value&&nameParts.first)g('fname').value=nameParts.first;
    if(!g('mi').value&&nameParts.mi)g('mi').value=nameParts.mi;
  }
  validateStep(0);
}
function setFieldIfParsed(id, value){
  var el=g(id);
  if(!el || !value) return;
  el.value = String(value).trim();
}
/** Overwrite field (legacy OCR owns this side’s fields). */
function setFieldOverwrite(id, value){
  var el=g(id);
  if(!el || value==null || value==='') return;
  el.value = String(value).trim();
}
function normalizeOcrText(raw){
  return String(raw||'')
    .normalize('NFKC')
    .replace(/[|]/g,'I')
    .replace(/[^\S\r\n]+/g,' ')
    .replace(/\r/g,'\n');
}
function pickLegacyDegreeAndCollege(block){
  var result={degree:'',college:''};
  var allDegrees=[];
  for(var k in degs){
    if(!Object.prototype.hasOwnProperty.call(degs,k)) continue;
    (degs[k]||[]).forEach(function(d){ allDegrees.push({college:k, degree:d}); });
  }
  var upper=block.toUpperCase().replace(/\s+/g,' ');
  for(var i=0;i<allDegrees.length;i++){
    var d=allDegrees[i];
    var du=d.degree.toUpperCase();
    if(upper.indexOf(du)!==-1){
      result.degree=d.degree;
      result.college=d.college;
      return result;
    }
  }
  var abbrev=[
    {re:/\bBS\s*IT\b|\bB\.?\s*S\.?\s*I\.?\s*T\.?\b|\bINFORMATION\s+TECHNOLOGY\b/i,degree:'BS Information Technology',college:'College of Computer Studies'},
    {re:/\bBS\s*CS\b|\bCOMPUTER\s+SCIENCE\b/i,degree:'BS Computer Science',college:'College of Computer Studies'},
    {re:/\bBS\s*IS\b|\bINFORMATION\s+SYSTEMS\b/i,degree:'BS Information Systems',college:'College of Computer Studies'},
    {re:/\bBS\s*CE\b|\bCOMPUTER\s+ENGINEERING\b/i,degree:'BS Computer Engineering',college:'College of Engineering'},
    {re:/\bBS\s*BA\b|\bBUSINESS\s+ADMINISTRATION\b/i,degree:'BS Business Administration',college:'College of Business & Accountancy'},
    {re:/\bBS\s*ACCT?\b|\bACCOUNTANCY\b/i,degree:'BS Accountancy',college:'College of Business & Accountancy'},
    {re:/\bBS\s+in\s+Nursing\b|\bBS\s+Nursing\b|\bBS\s*NURSING\b|\bNURSING\b/i,degree:'BS Nursing',college:'College of Nursing'}
  ];
  for(var a=0;a<abbrev.length;a++){
    if(abbrev[a].re.test(block)){
      result.degree=abbrev[a].degree;
      result.college=abbrev[a].college;
      return result;
    }
  }
  var mProg=block.match(/\b(?:PROGRAM|COURSE|DEGREE|COLLEGE)\s*[:\-]?\s*([^\n]{6,120})/i);
  if(mProg&&mProg[1]){
    var candidate=mProg[1].replace(/\s+/g,' ').trim();
    var cup=candidate.toUpperCase();
    for(var j=0;j<allDegrees.length;j++){
      if(cup.indexOf(allDegrees[j].degree.toUpperCase())!==-1){
        result.degree=allDegrees[j].degree;
        result.college=allDegrees[j].college;
        return result;
      }
    }
  }
  return result;
}
/** OCR often reads O/l as 0 — normalize digit run. */
function legacyDigitsFromChunk(s){
  if(!s) return '';
  var t=String(s).replace(/[OØo]/g,'0').replace(/[Il|]/g,'1').replace(/\D/g,'');
  return t;
}
/** Pick best alumni ID digit sequence (avoid years, phone fragments). */
function extractLegacyAlumniId(oneLine, block){
  var upperB=(block||'').toUpperCase();
  var labPatterns=[
    /\b(?:ALUMNI|A\.?\s*L\.?\s*U\.?\s*M\.?\s*N\.?I)\s*(?:ID|NO\.?|NUMBER|#)?\s*[:\#\-]?\s*([\d\s\-OlI|]{6,44})/gi,
    /\b(?:MEMBER|CARDHOLDER|CARD)\s*(?:ID|NO\.?|NUMBER)?\s*[:\#\-]?\s*([\d\s\-OlI|]{6,44})/gi,
    /\b(?:ID|I\.?D\.?)\s*(?:NO\.?|NUMBER|#)\s*[:\#\-]?\s*([\d\s\-OlI|]{6,44})/gi,
    /\b(?:CPS|OLF\s*U?\s*ID)\s*[:\#\-]?\s*([\d\s\-OlI|]{6,44})/gi
  ];
  var li,lm;
  for(li=0;li<labPatterns.length;li++){
    labPatterns[li].lastIndex=0;
    while((lm=labPatterns[li].exec(oneLine))!==null){
      var d=legacyDigitsFromChunk(lm[1]);
      if(d.length>=8&&d.length<=16) return d.slice(0,16);
    }
  }
  /* Number on the line after “ALUMNI NO” / “ID” (common on cards). */
  var blk=String(block||'').replace(/\r/g,'\n');
  var ml=blk.match(/\b(?:ALUMNI|MEMBER|CARD)\s*(?:ID|NO\.?|NUMBER)?\s*[:\#\-]?\s*\n+\s*([\d\s\-OlI|]{6,44})/im);
  if(ml&&ml[1]){
    var dM=legacyDigitsFromChunk(ml[1]);
    if(dM.length>=8&&dM.length<=16) return dM.slice(0,16);
  }
  /* Label on previous “line” in block (OCR glued with newline) */
  var near=upperB.match(/\b(?:ALUMNI|MEMBER|CARD)\s*(?:ID|NO\.?|NUMBER)?\s*[:\#\-]?\s*([\d\s\n\r\-OIl|]{8,48})/i);
  if(near&&near[1]){
    var d2=legacyDigitsFromChunk(near[1]);
    if(d2.length>=8&&d2.length<=16) return d2.slice(0,16);
  }
  var candidates=(oneLine.match(/\b\d{8,16}\b/g))||[];
  var best='',bestScore=-1;
  for(var i=0;i<candidates.length;i++){
    var d=candidates[i].replace(/\D/g,'');
    if(d.length<8||d.length>16) continue;
    if(/^20\d{2}$/.test(d.slice(0,4))&&d.length===10) continue;
    var score=d.length;
    var pos=upperB.indexOf(d);
    if(pos>=0){
      var ctx=upperB.slice(Math.max(0,pos-30),pos+30);
      if(/\b(ALUMNI|MEMBER|CARD|ID\s*NO|NUMBER|CPS|OLFUID)\b/.test(ctx)) score+=25;
    }
    if(/^09\d{9}$/.test(d)) score-=50;
    if(/^(20\d{2})(19|20)\d{2}$/.test(d)&&d.length>=8) score-=30;
    if(score>bestScore){bestScore=score;best=d;}
  }
  /* Spaced digits in one line: "12 3456 7890123" */
  var sp=oneLine.match(/(?:^|\s)((?:\d[\s\-]*){8,18}\d)(?=\s|$)/);
  if(sp&&sp[1]){
    var ds=legacyDigitsFromChunk(sp[1]);
    if(ds.length>=8&&ds.length<=16){
      var pos2=upperB.indexOf(sp[1].replace(/\s+/g,''));
      var ok=true;
      if(/^09\d{9}$/.test(ds)) ok=false;
      if(ok) return ds.slice(0,16);
    }
  }
  return best?best.slice(0,16):'';
}
function extractLegacyGradYear(oneLine, block){
  var m=oneLine.match(/\b(?:CLASS\s+OF|BATCH|YEAR\s*(?:OF\s*)?GRAD|GRAD(?:UATED)?\s*(?:IN|YEAR)?|A\.?Y\.?|S\.?Y\.?)\s*[:\-]?\s*((?:19|20)\d{2})\b/i);
  if(m&&m[1]) return m[1];
  m=oneLine.match(/\b(?:19|20)\d{2}\s*[-–]\s*(?:19|20)\d{2}\b/);
  if(m&&m[0]){
    var parts=m[0].split(/[-–]/);
    return parts[parts.length-1].trim().replace(/\D/g,'').slice(0,4);
  }
  m=oneLine.match(/\b(20[0-2]\d|19\d{2})\b/g);
  if(m&&m.length){
    for(var i=0;i<m.length;i++){
      var y=parseInt(m[i],10);
      if(y>=1990&&y<=parseInt(String(new Date().getFullYear()),10)) return m[i];
    }
  }
  return '';
}
function parseLegacyFrontFields(fullText,data){
  var out={};
  var block=normalizeOcrText(fullText);
  var oneLine=block.replace(/\s+/g,' ');

  var lineTexts=ocrLineTextsForNameParse(data||{});
  if(!lineTexts.length){
    lineTexts=block.split(/\n/).map(function(l){return l.trim().replace(/\s+/g,' ');}).filter(Boolean);
  }
  var nameParts=extractNameFromOcr(block, lineTexts);
  if(!nameParts) nameParts=parseLegacyAlumniNameHeuristics(block, lineTexts);
  if(!nameParts){
    lineTexts=block.split(/\n/).map(function(l){return l.trim().replace(/\s+/g,' ');}).filter(Boolean);
    nameParts=parseLegacyAlumniNameHeuristics(block, lineTexts);
  }
  if(nameParts){
    out.lastname=nameParts.last||'';
    out.firstname=nameParts.first||'';
    out.middleInitial=nameParts.mi||'';
  }

  var aid=extractLegacyAlumniId(oneLine, block);
  if(aid) out.alumniId=aid;

  var by=extractLegacyGradYear(oneLine, block);
  if(by) out.batchYear=by;

  var pc=pickLegacyDegreeAndCollege(block);
  if(pc.degree) out.degree=pc.degree;
  if(pc.college) out.college=pc.college;
  return out;
}
function parseLegacyBackFields(fullText){
  var out={};
  var block=normalizeOcrText(fullText);
  var oneLine=block.replace(/\s+/g,' ');

  function normPh(s){
    if(!s) return '';
    var d=String(s).replace(/\D/g,'');
    if(d.length===12&&d.slice(0,2)==='63') d='0'+d.slice(2);
    if(d.length===10&&d.charAt(0)==='9') d='0'+d;
    return d.slice(0,11);
  }
  var em=null, cm=null;
  em=oneLine.match(/\b(?:EMERGENCY|ICE|E-?CONTACT)\s*[:\#\-]?\s*((?:\+?63[\s\-]?|0)?9\d{2}[\s\-]?\d{3}[\s\-]?\d{4}|(?:\+?63[\s\-]?|0)?\d{10,11})\b/i);
  if(em&&em[1]) out.emergencyContact=normPh(em[1]);

  cm=oneLine.match(/\b(?:CONTACT|CP|MOBILE|PHONE|CEL(?:L)?|TEL\.?)\s*(?:NO\.?|NUMBER)?\s*[:\#\-]?\s*((?:\+?63[\s\-]?|0)?9\d{2}[\s\-]?\d{3}[\s\-]?\d{4}|(?:\+?63[\s\-]?|0)?\d{10,11})\b/i);
  if(cm&&cm[1]){
    var c2=normPh(cm[1]);
    if(c2&&c2!==out.emergencyContact) out.contact=c2;
  }
  if(!out.contact||!out.emergencyContact){
    var raw=oneLine.match(/(?:\+?63[\s\-]?|0)?9\d{2}[\s\-]?\d{3}[\s\-]?\d{4}/g)||[];
    var phones=[],seen={};
    for(var pi=0;pi<raw.length;pi++){
      var np=normPh(raw[pi]);
      if(np&&!seen[np]){ seen[np]=1; phones.push(np); }
    }
    if(phones.length>=2){
      if(!out.contact) out.contact=phones[0];
      if(!out.emergencyContact) out.emergencyContact=phones[1];
    }else if(phones.length===1){
      if(!out.contact) out.contact=phones[0];
      else if(!out.emergencyContact) out.emergencyContact=phones[0];
    }
  }

  var addr='';
  var am=block.match(/\b(?:ADDRESS|TIRAHAN|COMPLETE\s+ADDRESS)\b\s*[:\#\-]?\s*([\s\S]+?)(?=\n\s*(?:CONTACT|CP|PHONE|MOBILE|EMERGENCY|E-?MAIL|EMAIL)|$)/i);
  if(am&&am[1]){
    addr=am[1].replace(/\s+/g,' ').trim();
    addr=addr.split(/\b(?:CONTACT|EMERGENCY|PHONE)\b/i)[0].trim();
  }
  if(!addr||addr.length<8){
    var lines=block.split('\n').map(function(l){return l.trim();}).filter(Boolean);
    var buf=[];
    for(var i=0;i<lines.length;i++){
      if(/^(ADDRESS|TIRAHAN)/i.test(lines[i])) continue;
      if(/\b(?:CONTACT|EMERGENCY|PHONE|MOBILE)\b/i.test(lines[i])) break;
      if(/\b(?:BRGY|BARANGAY|CITY|PROVINCE|RIZAL|LAGUNA|METRO|QUEZON|MANILA)\b/i.test(lines[i])||lines[i].length>18){
        buf.push(lines[i]);
        if(buf.join(' ').length>24) break;
      }
    }
    if(buf.length) addr=buf.join(', ').replace(/\s+/g,' ').trim();
  }
  if(addr) out.address=addr;
  return out;
}
function applyLegacyFrontFill(parsed){
  if(!parsed) return;
  if(parsed.alumniId){
    setFieldOverwrite('sNum', parsed.alumniId);
    var hid=g('legacyAlumniId'); if(hid) hid.value=parsed.alumniId;
  }
  if(parsed.lastname) setFieldOverwrite('lname', parsed.lastname);
  if(parsed.firstname) setFieldOverwrite('fname', parsed.firstname);
  if(parsed.middleInitial) setFieldOverwrite('mi', parsed.middleInitial);
  if(parsed.batchYear){
    var yg=g('yearGrad');
    if(yg){
      var exists=Array.from(yg.options||[]).some(function(o){ return o.value===String(parsed.batchYear); });
      if(exists) yg.value=String(parsed.batchYear);
    }
  }
  if(parsed.college){
    var cs=g('colSel');
    if(cs){
      var cexists=Array.from(cs.options||[]).some(function(o){ return o.value===parsed.college; });
      if(cexists){
        cs.value=parsed.college;
        fillDeg(parsed.college);
      }
    }
  }
  if(parsed.degree){
    var ds=g('degSel');
    if(ds){
      if(g('colSel')&&g('colSel').value) fillDeg(g('colSel').value);
      var dexists=Array.from(ds.options||[]).some(function(o){ return o.value===parsed.degree; });
      if(dexists) ds.value=parsed.degree;
    }
  }
}
function applyLegacyBackFill(parsed){
  if(!parsed) return;
  if(parsed.address) setFieldOverwrite('address', parsed.address);
  if(parsed.contact) setFieldOverwrite('contact', parsed.contact);
  if(parsed.emergencyContact) setFieldOverwrite('emergency_contact', parsed.emergencyContact);
}
/** Legacy: profile photo comes from the same file as the front ID scan. */
function copyLegacyFrontToProfilePhoto(file){
  if(!file||!g('profile_photo')||!g('avatarEl')) return;
  try{
    var dt=new DataTransfer();
    dt.items.add(file);
    g('profile_photo').files=dt.files;
    var reader=new FileReader();
    reader.onload=function(ev){ g('avatarEl').innerHTML='<img src="'+ev.target.result+'" alt="">'; };
    reader.readAsDataURL(file);
  }catch(err){ console.warn('profile photo from ID front', err); }
}
function runOcr(file,side){
  var sp=g('ocrSpinner');if(sp)sp.classList.add('show');
  var isLegacy=<?php echo $registrationType === 'legacy' ? 'true' : 'false'; ?>;
  var opts={ logger:function(){ /* quiet */ } };
  if(typeof Tesseract!=='undefined'&&Tesseract.PSM){
    var psmBlock=(Tesseract.PSM.SINGLE_BLOCK!=null)?Tesseract.PSM.SINGLE_BLOCK:6;
    /* Front: AUTO keeps NAME / surname lines separate; back: single block helps address blob. */
    if(isLegacy){
      opts.tessedit_pageseg_mode=(side==='back')?psmBlock:Tesseract.PSM.AUTO;
    }else{
      opts.tessedit_pageseg_mode=Tesseract.PSM.AUTO;
    }
  }
  Tesseract.recognize(file,'eng',opts)
    .then(function(result){
      if(sp)sp.classList.remove('show');
      var d=result&&result.data?result.data:{};
      var tx=d.text||'';
      if(!tx.trim())return;
      if(isLegacy){
        if(side==='front'){
          applyLegacyFrontFill(parseLegacyFrontFields(tx,d));
          copyLegacyFrontToProfilePhoto(file);
        }else if(side==='back'){
          applyLegacyBackFill(parseLegacyBackFields(tx));
        }
      }else{
        if(side==='front') autofillFromOcr(tx,d);
      }
      validateStep(0);
      validateStep(1);
    })
    .catch(function(err){if(sp)sp.classList.remove('show');console.warn('OCR',err);});
}

/* ══════════════════════
   CAMERA
══════════════════════ */
var camStream=null,autoTimer=null,stabilityMs=0,lastAvg=null,autoCapturing=false;

async function startIdCamera(){
  var ov=g('camOverlay'),vid=g('idcam_video');
  var ct=g('camTitle');if(ct)ct.textContent='Capture front of Student ID';
  try{
    ov.classList.add('show');
    camStream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'},audio:false});
    vid.srcObject=camStream;
    stabilityMs=0;lastAvg=null;autoCapturing=false;
    if(autoTimer)clearInterval(autoTimer);
    g('idcam_status').textContent='Hold ID steady inside the frame — auto-capturing…';
    autoTimer=setInterval(autoCapCheck,100);
  }catch(e){ov.classList.remove('show');alert('Camera unavailable: '+(e.message||e));}
}
function startProfileCamera(){
  alert('Profile camera — integrate with camera API in production.');
}
function closeIdCamera(){
  if(camStream){camStream.getTracks().forEach(function(t){t.stop()});camStream=null;}
  if(autoTimer){clearInterval(autoTimer);autoTimer=null;}
  g('idcam_video').srcObject=null;
  g('camOverlay').classList.remove('show');
}
function captureIdFromCamera(){
  var vid=g('idcam_video'),canvas=g('idcam_canvas');
  if(!vid.videoWidth)return;
  canvas.width=Math.min(1600,vid.videoWidth);
  canvas.height=Math.floor(vid.videoHeight*(canvas.width/vid.videoWidth));
  canvas.getContext('2d').drawImage(vid,0,0,canvas.width,canvas.height);
  canvas.toBlob(function(blob){
    if(!blob)return;
    var file=new File([blob],'id_front.jpg',{type:'image/jpeg'});
    var dt=new DataTransfer();dt.items.add(file);
    var inp=g('id_card');
    if(!inp)return;
    inp.files=dt.files;
    closeIdCamera();
    var ev=new Event('change');inp.dispatchEvent(ev);
  },'image/jpeg',0.92);
}
function autoCapCheck(){
  if(!camStream||autoCapturing)return;
  var vid=g('idcam_video'),canvas=g('idcam_canvas'),status=g('idcam_status');
  if(!vid.videoWidth)return;
  var w=320,h=Math.floor((vid.videoHeight/vid.videoWidth)*w);
  canvas.width=w;canvas.height=h;
  canvas.getContext('2d').drawImage(vid,0,0,w,h);
  var img=canvas.getContext('2d').getImageData(0,0,w,h).data;
  var sum=0,count=0;
  for(var i=0;i<img.length;i+=4){sum+=(0.299*img[i]+0.587*img[i+1]+0.114*img[i+2]);count++;}
  var avg=sum/count;
  if(lastAvg===null){lastAvg=avg;stabilityMs=0;return;}
  var delta=Math.abs(avg-lastAvg);lastAvg=avg;
  if(delta<1.5){stabilityMs+=100;status.textContent=stabilityMs<2000?'Hold steady… '+Math.ceil((2000-stabilityMs)/1000)+'s':'Capturing…';}
  else{stabilityMs=0;status.textContent='Hold steady inside the frame…';}
  if(stabilityMs>=2000){autoCapturing=true;captureIdFromCamera();}
}

/* ══════════════════════
   PASSWORD
══════════════════════ */
function chkPw(v){
  var map={'r-len':v.length>=8,'r-up':/[A-Z]/.test(v),'r-lo':/[a-z]/.test(v),'r-nu':/[0-9]/.test(v)};
  for(var k in map){
    var el=g(k);if(!el)continue;
    el.classList.toggle('met',map[k]);
    var ic=el.querySelector('i');
    if(ic)ic.className=map[k]?'fas fa-check':'fas fa-circle';
  }
}
function chkMatch(){
  var pw=g('pwIn')?g('pwIn').value:'';
  var cpw=g('cpwIn')?g('cpwIn').value:'';
  var m=g('matchMsg');if(!m)return;
  if(!cpw){m.textContent='';return;}
  m.style.color=pw===cpw?'#2ea855':'#dc2626';
  m.innerHTML=pw===cpw
    ?'<i class="fas fa-check" style="font-size:10px;margin-right:4px;"></i>Passwords match'
    :'<i class="fas fa-times" style="font-size:10px;margin-right:4px;"></i>Passwords do not match';
}
function togPw(id,btn){
  var el=g(id);if(!el)return;
  var show=el.type==='password';
  el.type=show?'text':'password';
  var ic=btn.querySelector('i');
  if(ic)ic.className=show?'fas fa-eye-slash':'fas fa-eye';
}

/* ══════════════════════
   FORM SUBMIT
══════════════════════ */
document.getElementById('regForm').addEventListener('submit',function(e){
  e.preventDefault();
  visited[4]=true;
  if(!validateStep(4)){
    var fe=document.querySelector('#s4 .field-err.show');
    if(fe)fe.scrollIntoView({behavior:'smooth',block:'center'});
    showRequiredFieldsAlert('Please fill up all required fields and fix any errors before submitting.');
    return;
  }
  var fd=new FormData(this);
  fd.append('ajax_registration','1');
  var btn=g('bSub');
  if(btn){btn.disabled=true;btn.style.opacity='0.7';}
  fetch(window.location.href,{method:'POST',body:fd,credentials:'same-origin'})
    .then(function(r){
      var ct=(r.headers.get('Content-Type')||'');
      if(ct.indexOf('application/json')!==-1)return r.json();
      return r.text().then(function(){throw new Error('bad_response');});
    })
    .then(function(data){
      if(data&&data.success){
        var m=g('successModal');if(m)m.style.display='flex';
        return;
      }
      alert((data&&data.message)?data.message:'Registration could not be completed. Please check your information and try again.');
    })
    .catch(function(){
      alert('Registration could not be completed. Please check your connection and try again.');
    })
    .finally(function(){
      if(btn){btn.disabled=false;btn.style.opacity='';}
    });
});

/* ── SUCCESS MODAL ── */
<?php if (isset($_SESSION['registration_success']) && $_SESSION['registration_success']): unset($_SESSION['registration_success']); ?>
(function(){var m=g('successModal');if(m){m.style.display='flex';}})();
<?php endif; ?>

/* ── DUPLICATE CHECK ── */
function checkDuplicate(type,value,inputEl){
  if(!value||!value.trim())return;
  var fd=new FormData();fd.append('ajax','check_duplicate');fd.append('type',type);fd.append('value',value.trim());
  fetch(window.location.href,{method:'POST',body:fd})
    .then(function(r){return r.json();})
    .then(function(d){
      if(d.success&&d.exists){
        inputEl.classList.add('err');
        var ex=inputEl.parentNode.querySelector('.dup-err');
        if(!ex){var de=document.createElement('div');de.className='field-err dup-err show';de.innerHTML='<i class="fas fa-circle-exclamation"></i> '+d.message;inputEl.parentNode.appendChild(de);}
      }else{
        inputEl.classList.remove('err');
        var ex=inputEl.parentNode.querySelector('.dup-err');if(ex)ex.remove();
      }
    }).catch(function(){});
}
var _dbt={};
function debounced(key,fn,ms){clearTimeout(_dbt[key]);_dbt[key]=setTimeout(fn,ms);}

g('personalEmail').addEventListener('input',function(){debounced('email',function(){checkDuplicate('email',g('personalEmail').value,g('personalEmail'));},600);});
g('sNum').addEventListener('input',function(){debounced('sid',function(){checkDuplicate('alumni_id',g('sNum').value,g('sNum'));},600);});
<?php if ($registrationType === 'legacy'): ?>
g('sNum').addEventListener('input',function(){
  var hid=g('legacyAlumniId');
  if(hid) hid.value=(g('sNum').value||'').replace(/\D/g,'').slice(0,16);
});
<?php endif; ?>

/* ── INIT ── */
syncUI();
updateAgeFromBirthday();
refreshScrollbars();
window.addEventListener('resize',refreshScrollbars);
</script>
</body>
</html>