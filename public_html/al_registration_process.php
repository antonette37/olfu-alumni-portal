<?php
require_once 'db_config.php';
require_once 'mail.php';
require_once 'admin_email_notifications.php';

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Upload photo
    $photo = $_FILES['photo']['name'] ?? '';
    $photo_tmp = $_FILES['photo']['tmp_name'] ?? '';
    $photo_path = 'photos/' . basename($photo);
    if (!empty($photo)) {
        if (!move_uploaded_file($photo_tmp, $photo_path)) {
            die("Failed to upload photo.");
        }
    }

    // Resume Parsing with Affinda
    $resume_data = [];
    if (!empty($_FILES['resume']['name'])) {
        $resume_tmp = $_FILES['resume']['tmp_name'];
        $resume_file = new CURLFile($resume_tmp, mime_content_type($resume_tmp), $_FILES['resume']['name']);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api.affinda.com/v2/resumes");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => $resume_file]);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer aff_06f41013821cbecdba640e5eae07f836bf591e28",
            "Content-Type: multipart/form-data"
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $result = json_decode($response, true);
            if (isset($result['data'])) {
                $resume_data = $result['data'];
            }
        }
    }

    // Personal Info (autofill from resume if available)
    $lastname = $_POST['lastname'] ?? ($resume_data['name']['family'] ?? '');
    $firstname = $_POST['firstname'] ?? ($resume_data['name']['given'] ?? '');
    $middlename = $_POST['middlename'] ?? '';
    $name_ext = $_POST['name_ext'] ?? '';
    $birthday = $_POST['birthday'] ?? '';
    $age = $_POST['age'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $civil_status = $_POST['civil_status'] ?? '';
    $religion = $_POST['religion'] ?? '';
    $nationality = $_POST['nationality'] ?? '';
    $email = $_POST['email'] ?? ($resume_data['emails'][0] ?? '');
    $address = $_POST['address'] ?? ($resume_data['location']['formatted'] ?? '');
    $personal_contact = $_POST['personal_contact'] ?? ($resume_data['phoneNumbers'][0] ?? '');
    $emergency_contact = $_POST['emergency_contact'] ?? '';

    // Academic Info
    $student_number = $_POST['student_number'] ?? '';
    $program = $_POST['program'] ?? '';
    $campus = $_POST['campus'] ?? '';
    $month_graduated = $_POST['month_graduated'] ?? '';
    $year_graduated = $_POST['year_graduated'] ?? '';
    $post_grad = $_POST['post_grad'] ?? '';
    $licensure_exam = $_POST['licensure_exam'] ?? '';
    $club_involvement = $_POST['club_involvement'] ?? '';

    // Employment Info (autofill from resume if available)
    $employment_status = $_POST['employment_status'] ?? '';
    $company = $_POST['company'] ?? ($resume_data['employment'][0]['organization'] ?? '');
    $industry = $_POST['industry'] ?? '';
    $position = $_POST['position'] ?? ($resume_data['employment'][0]['jobTitle'] ?? '');
    $employment_history = $_POST['employment_history'] ?? '';
    $previous_role = $_POST['previous_role'] ?? '';
    $length_of_service = $_POST['length_of_service'] ?? '';

    // Password & Consent
    $consent = isset($_POST['consent']) ? 1 : 0;
    $password = $_POST['password'] ?? '';
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Prepare SQL
    $sql = "INSERT INTO itcp (
        photo, lastname, firstname, middlename, name_ext, birthday, age, gender,
        civil_status, religion, nationality, email, address, personal_contact, emergency_contact,
        student_number, program, campus, month_graduated, year_graduated, post_grad,
        licensure_exam, club_involvement, employment_status, company, industry, position,
        employment_history, previous_role, length_of_service, consent, password, status
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending'
    )";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param(
        "ssssssisssssssssssisssssssssssis",
        $photo, $lastname, $firstname, $middlename, $name_ext, $birthday, $age, $gender,
        $civil_status, $religion, $nationality, $email, $address, $personal_contact, $emergency_contact,
        $student_number, $program, $campus, $month_graduated, $year_graduated, $post_grad,
        $licensure_exam, $club_involvement, $employment_status, $company, $industry, $position,
        $employment_history, $previous_role, $length_of_service, $consent, $hashed_password
    );

    try {
        if ($stmt->execute()) {
            // Create notification for coordinator about new registration
            $notification_sql = "INSERT INTO notifications (user_id, type, message, created_at) 
                                SELECT id, 'new_registration', CONCAT('New registration from ', firstname, ' ', lastname), NOW() 
                                FROM itcp 
                                WHERE email = ?";
            $notification_stmt = $conn->prepare($notification_sql);
            $notification_stmt->bind_param("s", $email);
            $notification_stmt->execute();
            $notification_stmt->close();

            // Send confirmation email to the user
            $subject = "Registration Confirmation - OLFU Alumni Portal";
            $body = "
                <h2>Registration Received</h2>
                <p>Dear {$firstname} {$lastname},</p>
                <p>Thank you for registering with the OLFU Alumni Portal. Your registration has been received and is pending approval by the Alumni Affairs office.</p>
                <p>You will receive another email once your account has been approved.</p>
                <p>Registration Details:</p>
                <ul>
                    <li>Name: {$firstname} {$middlename} {$lastname}</li>
                    <li>Email: {$email}</li>
                    <li>Program: {$program}</li>
                    <li>Campus: {$campus}</li>
                </ul>
                <p>Best regards,<br>OLFU Alumni Affairs</p>
            ";

            if (sendEmail($email, $subject, $body)) {
                error_log("Registration confirmation email sent to: {$email}");
            }
            else {
                error_log("Failed to send registration confirmation email to: {$email}");
            }

            // Send admin notification email
            $userData = [
                'firstname' => $firstname,
                'middlename' => $middlename,
                'lastname' => $lastname,
                'email' => $email,
                'student_number' => $student_number,
                'program' => $program,
                'date_joined' => date('Y-m-d H:i:s')
            ];
            sendAdminNewUserNotification($userData);

            session_start();
            $_SESSION['registration_success'] = true;
            echo "<script>
                alert('Registration submitted successfully! Your account is pending coordinator approval. You will receive an email notification once your account is approved.');
                window.location.href='al_homepage.php';
            </script>";
            $stmt->close();
            $conn->close();
            exit;
        }
        else {
            throw new Exception("Execute failed: " . $stmt->error);
        }
    }
    catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        echo "<script>
            alert('Registration failed: " . addslashes($e->getMessage()) . "');
            window.location.href='al_registration.php';
        </script>";
    }
}
?>