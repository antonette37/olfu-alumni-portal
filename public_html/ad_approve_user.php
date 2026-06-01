<?php
// Start output buffering to prevent headers already sent errors
if (ob_get_level() == 0) {
    ob_start();
}

// Error reporting - log errors but don't display them
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Suppress any warnings that might cause output
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Log but don't output
    error_log("PHP Error ($errno): $errstr in $errfile on line $errline");
    return true; // Suppress default error handler
}, E_WARNING | E_NOTICE | E_DEPRECATED);

// Set error handler to catch fatal errors
if (!isset($GLOBALS['shutdown_registered_approve_user'])) {
    $GLOBALS['shutdown_registered_approve_user'] = true;
    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            error_log("Fatal error in ad_approve_user.php: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=utf-8');
            }
            // Don't die - let the script continue if possible
        }
    });
}

session_start();

// Simple admin gate (adjust based on your admin session flag)
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    ob_end_clean();
    header('Location: al_login.php');
    exit();
}

// Try to load email sending function or PHPMailer directly
// NOTE: Email loading is optional - if it fails, approval will still work
$email_function_available = false;
$phpmailer_available = false;

// Toggle email loading (keep true in production so notifications are sent)
$try_load_email = true;

// First, try to use the existing mail.php sendEmail function
$mail_php_paths = [
    __DIR__ . '/mail.php',
    __DIR__ . DIRECTORY_SEPARATOR . 'mail.php',
    'mail.php',
    './mail.php',
    dirname(__FILE__) . '/mail.php'
];

$mail_php_found = false;
$mail_php_path = null;
foreach ($mail_php_paths as $path) {
    if (@file_exists($path)) {
        $mail_php_found = true;
        $mail_php_path = $path;
        break;
    }
}

// Only try to load email if flag is set (can be disabled if causing issues)
if ($try_load_email && $mail_php_found && $mail_php_path) {
    // Use a separate output buffer to isolate any output from mail.php
    $mail_ob_started = false;
    if (ob_get_level() == 0) {
        ob_start();
        $mail_ob_started = true;
    }
    
    try {
        // Suppress all warnings/errors during require to prevent output
        $old_error_reporting = error_reporting(0);
        $old_display_errors = ini_get('display_errors');
        ini_set('display_errors', 0);
        
        // Try to include mail.php - use include_once with @ to suppress all errors
        $include_result = @include_once $mail_php_path;
        
        // Immediately clean any output that might have been generated
        if ($mail_ob_started && ob_get_level() > 0) {
            ob_end_clean();
            $mail_ob_started = false;
        } elseif (ob_get_level() > 0) {
            // If there's existing output buffer, just clean it
            $output = ob_get_clean();
            if (!empty(trim($output))) {
                error_log("Warning: mail.php generated output: " . substr($output, 0, 100));
            }
            ob_start(); // Restart buffer
        }
        
        // Restore error reporting
        error_reporting($old_error_reporting);
        ini_set('display_errors', $old_display_errors);
        
        if ($include_result !== false && function_exists('sendEmail')) {
            $email_function_available = true;
            error_log("Email function (sendEmail) loaded successfully from: " . $mail_php_path);
        } else {
            error_log("mail.php loaded but sendEmail function not found or include failed");
        }
    } catch (Throwable $e) {
        // Clean output buffer if it exists
        if ($mail_ob_started && ob_get_level() > 0) {
            ob_end_clean();
        } elseif (ob_get_level() > 0) {
            ob_get_clean();
            ob_start();
        }
        // Restore error reporting even on error
        if (isset($old_error_reporting)) {
            error_reporting($old_error_reporting);
        }
        if (isset($old_display_errors)) {
            ini_set('display_errors', $old_display_errors);
        }
        error_log("Failed to load mail.php from " . $mail_php_path . ": " . $e->getMessage());
        error_log("Error in: " . $e->getFile() . " on line " . $e->getLine());
        // Don't throw - just continue without email
        $try_load_email = false; // Disable further email loading attempts
    }
} else {
    if (!$try_load_email) {
        error_log("Email loading disabled due to previous failure");
    } else {
        error_log("mail.php not found in any of the checked paths");
    }
}

// If mail.php function not available, try to load PHPMailer directly
if (!$email_function_available) {
    $possible_paths = [
        __DIR__ . '/phpmailer/src/Exception.php',
        __DIR__ . DIRECTORY_SEPARATOR . 'phpmailer' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Exception.php',
        'phpmailer/src/Exception.php',
        './phpmailer/src/Exception.php',
        dirname(__FILE__) . '/phpmailer/src/Exception.php'
    ];

    $phpmailer_base = null;
    foreach ($possible_paths as $path) {
        if (@file_exists($path)) {
            $phpmailer_base = dirname($path);
            error_log("Found PHPMailer at: " . $phpmailer_base);
            break;
        }
    }
    
    // If still not found, try to use the mail.php approach which should work
    if (!$phpmailer_base && @file_exists('mail.php')) {
        error_log("PHPMailer not found directly, but mail.php exists - will use sendEmail function");
    }

    if ($phpmailer_base) {
        try {
            require $phpmailer_base . '/Exception.php';
            require $phpmailer_base . '/PHPMailer.php';
            require $phpmailer_base . '/SMTP.php';
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                $phpmailer_available = true;
                error_log("PHPMailer loaded successfully");
            } else {
                error_log("PHPMailer files loaded but class not found");
            }
        } catch (Throwable $e) {
            $phpmailer_available = false;
            error_log("PHPMailer initialization error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        }
    } else {
        error_log("PHPMailer files not found. Checked paths: " . implode(', ', $possible_paths));
    }
}

require_once 'db_config.php';
require_once __DIR__ . '/includes/cps_alumni_lib.php';
$conn = getDBConnection();

if (!$conn) {
    ob_end_clean();
    error_log("Database connection failed in ad_approve_user.php");
    header("Location: ad_user_management.php?error=db");
    exit();
}

cps_ensure_schema($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id']) && isset($_POST['action'])) {
    // CSRF: require token from session (set on ad_user_management.php)
    if (empty($_POST['csrf_token']) || empty($_SESSION['admin_csrf']) || !hash_equals((string)$_SESSION['admin_csrf'], (string)$_POST['csrf_token'])) {
        ob_end_clean();
        header("Location: ad_user_management.php?error=csrf");
        exit();
    }

    try {
        $user_id = (int)$_POST['user_id'];
        $action = $_POST['action'] === 'approve' ? 'approve' : 'reject';
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

        // Get user details
        $user_sql = "SELECT * FROM itcp WHERE id = ?";
        $user_stmt = $conn->prepare($user_sql);
        if (!$user_stmt) {
            throw new Exception("Failed to prepare user query: " . $conn->error);
        }
        $user_stmt->bind_param("i", $user_id);
        if (!$user_stmt->execute()) {
            throw new Exception("Failed to execute user query: " . $user_stmt->error);
        }
        $user_result = $user_stmt->get_result();
        $user_data = $user_result->fetch_assoc();

        if ($user_data) {
            // If approving: run duplicate and security checks first
            if ($action === 'approve') {
                $email = trim($user_data['email'] ?? '');
                $student_number = trim($user_data['student_number'] ?? '');
                $normalized_sn = preg_replace('/[\s\-]+/', '', $student_number);

                // 1. Duplicate email: another active/pending user with same email
                if ($email !== '') {
                    $dup = $conn->prepare("SELECT COUNT(*) AS c FROM itcp WHERE LOWER(TRIM(email)) = LOWER(?) AND id != ? AND status IN ('active','pending')");
                    if ($dup) {
                        $dup->bind_param('si', $email, $user_id);
                        $dup->execute();
                        $r = $dup->get_result()->fetch_assoc();
                        $dup->close();
                        if (isset($r['c']) && (int)$r['c'] > 0) {
                            ob_end_clean();
                            header("Location: ad_user_management.php?error=duplicate_email");
                            exit();
                        }
                    }
                }

                // 2. Duplicate alumni ID number: another active/pending user with same student_number (normalized)
                if ($normalized_sn !== '') {
                    $all = $conn->prepare("SELECT id, student_number FROM itcp WHERE id != ? AND status IN ('active','pending') AND TRIM(student_number) != ''");
                    if ($all) {
                        $all->bind_param('i', $user_id);
                        $all->execute();
                        $res = $all->get_result();
                        while ($other = $res->fetch_assoc()) {
                            $other_sn = preg_replace('/[\s\-]+/', '', trim($other['student_number'] ?? ''));
                            if (strcasecmp($other_sn, $normalized_sn) === 0) {
                                $all->close();
                                ob_end_clean();
                                header("Location: ad_user_management.php?error=duplicate_student_number");
                                exit();
                            }
                        }
                        $all->close();
                    }
                }

                // 3. Password required
                if (empty($user_data['password']) || !is_string($user_data['password']) || trim($user_data['password']) === '') {
                    error_log("WARNING: User ID {$user_id} ({$user_data['email']}) has empty or invalid password field during approval attempt");
                    ob_end_clean();
                    header("Location: ad_user_management.php?error=no_password");
                    exit();
                }
                
                // Only update status, explicitly preserve password
                $update_sql = "UPDATE itcp SET status = 'active' WHERE id = ? AND password IS NOT NULL AND password != ''";
                $update_stmt = $conn->prepare($update_sql);
                if (!$update_stmt) {
                    throw new Exception("Failed to prepare update query: " . $conn->error);
                }
                $update_stmt->bind_param("i", $user_id);
            } else {
                // Rejected users go to archives so they appear in Archived list
                $new_status = 'archived';
                $update_sql = "UPDATE itcp SET status = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                if (!$update_stmt) {
                    throw new Exception("Failed to prepare update query: " . $conn->error);
                }
                $update_stmt->bind_param("si", $new_status, $user_id);
            }

            if (!$update_stmt->execute()) {
                $update_stmt->close();
                $user_stmt->close();
                $conn->close();
                ob_end_clean();
                error_log("Failed to update user status in ad_approve_user.php: " . $update_stmt->error);
                header("Location: ad_user_management.php?error=update");
                exit();
            }
            
            // Verify the update was successful
            $verify_sql = "SELECT status, password FROM itcp WHERE id = ?";
            $verify_stmt = $conn->prepare($verify_sql);
            if ($verify_stmt) {
                $verify_stmt->bind_param("i", $user_id);
                $verify_stmt->execute();
                $verify_result = $verify_stmt->get_result();
                $verify_data = $verify_result->fetch_assoc();
                $verify_stmt->close();
                
                if ($verify_data) {
                    $new_status = strtolower(trim($verify_data['status']));
                    $has_password = !empty($verify_data['password']) && is_string($verify_data['password']);
                    
                    error_log("User approval verification - ID: $user_id, Status: {$verify_data['status']} (normalized: $new_status), Has Password: " . ($has_password ? 'Yes' : 'No'));
                    
                    if ($action === 'approve' && $new_status !== 'active') {
                        error_log("WARNING: User approval failed - Status is '{$verify_data['status']}' instead of 'active'");
                    }
                    
                    if ($action === 'approve' && !$has_password) {
                        error_log("CRITICAL: User approval completed but password field is empty for user ID $user_id");
                    }
                }
            }
            
            // If we get here, the update was successful
            // Log verification action (optional - table might not exist)
            try {
                $log_stmt = $conn->prepare("INSERT INTO user_logs (user_id, activity_type, activity_description, ip_address) VALUES (?, ?, ?, ?)");
                if ($log_stmt) {
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    $activity = $action === 'approve' ? 'verify_approve' : 'verify_reject';
                    $desc = $action === 'approve' ? 'Admin approved account' : ('Admin rejected account' . ($reason ? (': ' . $reason) : ''));
                    $log_stmt->bind_param('isss', $user_id, $activity, $desc, $ip);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
            } catch (Exception $e) {
                error_log("Failed to log user action: " . $e->getMessage());
            }

            $cps_info = null;
            $cps_block = '';
            if ($action === 'approve') {
                $override = isset($_POST['cps_override']) ? trim((string)$_POST['cps_override']) : null;
                if ($override === '') {
                    $override = null;
                }
                $campusCode = isset($user_data['campus']) ? substr(trim((string)$user_data['campus']), 0, 32) : null;
                $cps_info = cps_assign_to_alumni($conn, $user_id, $override, $campusCode);
                cps_mark_pending_approved($conn, $user_id);
                $program = trim($user_data['program'] ?? '');
                $industry = trim($user_data['industry'] ?? '');
                $pos = trim($user_data['position'] ?? '');
                $comp = trim($user_data['company'] ?? '');
                if ($program !== '' && ($industry !== '' || $pos !== '' || $comp !== '')) {
                    cps_work_history_sync_from_profile($conn, $user_id, $program, $industry, $pos, $comp, false);
                }
                if (is_array($cps_info) && !empty($cps_info['formatted'])) {
                    $vf = htmlspecialchars($cps_info['validity_date'] ?? '', ENT_QUOTES, 'UTF-8');
                    $cf = htmlspecialchars($cps_info['formatted'], ENT_QUOTES, 'UTF-8');
                    $cps_block = "<div style='background:#f0fdf4;border-left:4px solid #2c5530;padding:16px;margin:16px 0;border-radius:8px;'><p style='margin:0 0 8px 0;font-size:15px;color:#14532d;'><strong>CPS Alumni ID:</strong> {$cf}</p><p style='margin:0;font-size:14px;color:#374151;'><strong>Card validity (3 years):</strong> {$vf}</p></div>";
                }
            }
            
            // Automatically send email to the user's personal email (approve: account approved; reject: reason)
            $email_sent = false;
            
            // Recipient = user's personal/registered email (itcp.email)
            $recipient = isset($user_data['email']) && !empty($user_data['email']) ? trim($user_data['email']) : '';
            
            if (empty($recipient)) {
                error_log("No email address found for user_id: " . $user_id);
            } elseif (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                error_log("Invalid email address for user_id: " . $user_id . " - Email: " . $recipient);
            } else {
                // Try using the sendEmail function first (from mail.php)
                if ($email_function_available && function_exists('sendEmail')) {
                    error_log("Using sendEmail function to send email for user_id: " . $user_id);
                    try {
                        // Build email content
                        if ($action === 'approve') {
                            $email_subject = 'Your account is approved – Welcome to CCS OLFU Alumni Community!';
                            $email_body = "
                                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;'>
                                    <div style='background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                                        <div style='text-align: center; margin-bottom: 30px;'>
                                            <h1 style='color: #2c5530; margin-bottom: 10px;'>🎉 Congratulations!</h1>
                                            <h2 style='color: #2c5530; margin: 0;'>Your Account is Now Approved</h2>
                                        </div>
                                        
                                        <p style='font-size: 16px; color: #333; line-height: 1.6;'>Dear <strong>{$user_data['firstname']} {$user_data['lastname']}</strong>,</p>
                                        {$cps_block}
                                        <p style='font-size: 16px; color: #333; line-height: 1.6;'>We are delighted to inform you that your registration has been approved! <strong>Welcome to the CCS OLFU Alumni Community!</strong></p>
                                        
                                        <div style='background-color: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2c5530;'>
                                            <p style='margin: 0; font-size: 16px; color: #2c5530;'><strong>Your account is now active and ready to use!</strong></p>
                                        </div>
                                        
                                        <div style='background-color: #f0f8ff; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                                            <h3 style='color: #2c5530; margin-top: 0;'>Ready to Get Started?</h3>
                                            <p style='margin-bottom: 15px; color: #333;'>Click the button below to access your alumni portal:</p>
                                            <div style='text-align: center;'>
                                                <table style='margin: 15px auto; border-collapse: collapse;'>
                                                    <tr>
                                                        <td style='background-color: #2c5530; border-radius: 8px; padding: 0;'>
                                                            <a href='https://ccsolfualumni.sbs/al_login.php' 
                                                               style='display: block; padding: 15px 35px; color: white; text-decoration: none; font-weight: bold; font-size: 16px;'
                                                               target='_blank'
                                                               rel='noopener noreferrer'>🔗 Login to Your Account</a>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                            <p style='font-size: 14px; color: #666; margin-top: 15px; margin-bottom: 0;'>Or copy and paste this link: <a href='https://ccsolfualumni.sbs/al_login.php' style='color: #2c5530; text-decoration: underline;' target='_blank'>https://ccsolfualumni.sbs/al_login.php</a></p>
                                        </div>
                                        
                                        <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                                            <p style='font-size: 14px; color: #666; margin-bottom: 5px;'><strong>Login Credentials:</strong></p>
                                            <p style='font-size: 14px; color: #666; margin: 0;'><strong>Email:</strong> {$user_data['email']}</p>
                                            <p style='font-size: 14px; color: #666; margin: 0;'><strong>Password:</strong> [The password you created during registration]</p>
                                        </div>
                                        
                                        <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                                            <p style='color: #2c5530; font-weight: bold; margin: 0;'>Welcome to the CCS OLFU Alumni Community!</p>
                                            <p style='color: #666; font-size: 14px; margin: 10px 0 0 0;'>Best regards,<br>CCS OLFU Alumni Affairs Team</p>
                                        </div>
                                    </div>
                                </div>
                        ";
                    } else {
                        $email_subject = 'Registration Status Update - Account Not Approved';
                        $email_body = "
                                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;'>
                                    <div style='background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                                        <div style='text-align: center; margin-bottom: 30px;'>
                                            <h1 style='color: #dc2626; margin-bottom: 10px;'>Registration Status Update</h1>
                                        </div>
                                        
                                        <p style='font-size: 16px; color: #333; line-height: 1.6;'>Dear <strong>{$user_data['firstname']} {$user_data['lastname']}</strong>,</p>
                                        
                                        <p style='font-size: 16px; color: #333; line-height: 1.6;'>We regret to inform you that your registration has not been approved at this time.</p>
                                        
                                        " . ($reason ? ("
                                        <div style='background-color: #fee2e2; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #dc2626;'>
                                            <p style='margin: 0; font-size: 16px; color: #991b1b;'><strong>Reason for Rejection:</strong></p>
                                            <p style='margin: 10px 0 0 0; font-size: 15px; color: #7f1d1d;'>" . htmlspecialchars($reason) . "</p>
                                        </div>
                                        ") : "") . "
                                        
                                        <div style='background-color: #f0f8ff; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                                            <h3 style='color: #1e40af; margin-top: 0;'>What's Next?</h3>
                                            <p style='margin-bottom: 15px; color: #333;'>If you believe this is an error or would like to resubmit your registration, please contact the Alumni Affairs office for assistance.</p>
                                        </div>
                                        
                                        <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                                            <p style='color: #666; font-size: 14px; margin: 10px 0 0 0;'>Best regards,<br>CCS OLFU Alumni Affairs Team</p>
                                        </div>
                                    </div>
                                </div>
                        ";
                    }
                    
                    error_log("Attempting to send email using sendEmail function to: " . $recipient . " (Action: " . $action . ", User ID: " . $user_id . ")");
                    if (sendEmail($recipient, $email_subject, $email_body)) {
                        error_log("SUCCESS: Email sent successfully using sendEmail function to: " . $recipient . " (Action: " . $action . ", User ID: " . $user_id . ")");
                        $email_sent = true;
                    } else {
                        error_log("ERROR: sendEmail function returned false for: " . $recipient);
                    }
                    } catch (\Exception $e) {
                        error_log("ERROR: Exception when using sendEmail function: " . $e->getMessage());
                    }
                }
                
                    // Fallback to direct PHPMailer if sendEmail function not available or failed
                if (!$email_sent && $phpmailer_available && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                    error_log("Using PHPMailer directly to send email for user_id: " . $user_id);
                    try {
                        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                        
                        // Server settings (adjust to your SMTP creds)
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'shairamaebuensucesobasigaa@gmail.com';
                        $mail->Password = 'iqiakhldmxqdancx';
                        // Try STARTTLS first (port 587) - more reliable than SMTPS
                        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;
                        
                        // Timeout settings
                        $mail->Timeout = 30;
                        $mail->SMTPKeepAlive = false;
                        
                        // SMTP Options for better compatibility
                        $mail->SMTPOptions = array(
                            'ssl' => array(
                                'verify_peer' => false,
                                'verify_peer_name' => false,
                                'allow_self_signed' => true
                            )
                        );
                        
                        // Enable verbose debug output to log file
                        $mail->SMTPDebug = 2; // Enable verbose debug output
                        $mail->Debugoutput = function($str, $level) {
                            error_log("PHPMailer Debug: $str");
                        };

                        // Recipients
                        $mail->CharSet = 'UTF-8';
                        $mail->setFrom('shairamaebuensucesobasigaa@gmail.com', 'OLFU ALUMNI AFFAIRS');
                        $mail->addReplyTo('shairamaebuensucesobasigaa@gmail.com', 'OLFU ALUMNI AFFAIRS');
                        $mail->addAddress($recipient);
                        error_log("Preparing to send email to: " . $recipient . " for user_id: " . $user_id);

                        // Content
                        $mail->isHTML(true);
                        
                        // Create plain text version for better email client compatibility
                        $plain_text_body = '';
                        
                        if ($action === 'approve') {
                            $mail->Subject = 'Account Approved: Welcome to CCS OLFU Alumni Community!';
                            $plain_text_body = "Congratulations!\n\n";
                            $plain_text_body .= "Dear {$user_data['firstname']} {$user_data['lastname']},\n\n";
                            $plain_text_body .= "We are delighted to inform you that your registration has been approved! Welcome to the CCS OLFU Alumni Community!\n\n";
                            $plain_text_body .= "Your account is now active and ready to use!\n\n";
                            $plain_text_body .= "Login to your account: https://ccsolfualumni.sbs/al_login.php\n\n";
                            $plain_text_body .= "Login Credentials:\n";
                            $plain_text_body .= "Email: {$user_data['email']}\n";
                            $plain_text_body .= "Password: [The password you created during registration]\n\n";
                            $plain_text_body .= "Welcome to the CCS OLFU Alumni Community!\n\n";
                            $plain_text_body .= "Best regards,\nCCS OLFU Alumni Affairs Team";
                            
                            // Add QR code as embedded image
                            $qr_code_path = __DIR__ . '/qrcode.png';
                            if (@file_exists($qr_code_path)) {
                                $mail->addEmbeddedImage($qr_code_path, 'qrcode', 'qrcode.png');
                                $qr_code_html = '<img src="cid:qrcode" alt="QR Code" style="max-width: 200px; height: auto; margin: 20px 0;">';
                            } else {
                                // Fallback: Create a simple QR code placeholder
                                $qr_code_html = '
                                    <div style="width: 200px; height: 200px; margin: 20px auto; background-color: #f0f0f0; border: 2px solid #2c5530; display: flex; align-items: center; justify-content: center; flex-direction: column;">
                                        <div style="font-size: 24px; color: #2c5530; margin-bottom: 10px;">📱</div>
                                        <div style="font-size: 14px; color: #2c5530; text-align: center; font-weight: bold;">QR CODE</div>
                                        <div style="font-size: 12px; color: #666; text-align: center; margin-top: 5px;">Scan to access portal</div>
                                    </div>
                                ';
                            }
                            
                            $mail->Body = "
                            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;'>
                                <div style='background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                                    <div style='text-align: center; margin-bottom: 30px;'>
                                        <h1 style='color: #2c5530; margin-bottom: 10px;'>🎉 Congratulations!</h1>
                                        <h2 style='color: #2c5530; margin: 0;'>Your Account is Now Approved</h2>
                                    </div>
                                    
                                    <p style='font-size: 16px; color: #333; line-height: 1.6;'>Dear <strong>{$user_data['firstname']} {$user_data['lastname']}</strong>,</p>
                                    {$cps_block}
                                    <p style='font-size: 16px; color: #333; line-height: 1.6;'>We are delighted to inform you that your registration has been approved! <strong>Welcome to the CCS OLFU Alumni Community!</strong></p>
                                    
                                    <div style='background-color: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2c5530;'>
                                        <p style='margin: 0; font-size: 16px; color: #2c5530;'><strong>Your account is now active and ready to use!</strong></p>
                                    </div>
                                    
                                    <div style='text-align: center; margin: 30px 0;'>
                                        {$qr_code_html}
                                    </div>
                                    
                                    <div style='background-color: #f0f8ff; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                                        <h3 style='color: #2c5530; margin-top: 0;'>Ready to Get Started?</h3>
                                        <p style='margin-bottom: 15px; color: #333;'>Click the button below to access your alumni portal:</p>
                                        <div style='text-align: center;'>
                                            <!-- Table-based button for better email client compatibility -->
                                            <table style='margin: 15px auto; border-collapse: collapse;'>
                                                <tr>
                                                    <td style='background-color: #2c5530; border-radius: 8px; padding: 0;'>
                                                        <a href='https://ccsolfualumni.sbs/al_login.php' 
                                                           style='display: block; padding: 15px 35px; color: white; text-decoration: none; font-weight: bold; font-size: 16px;'
                                                           target='_blank'
                                                           rel='noopener noreferrer'>🔗 Login to Your Account</a>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>
                                        <p style='font-size: 14px; color: #666; margin-top: 15px; margin-bottom: 0;'>Or copy and paste this link: <a href='https://ccsolfualumni.sbs/al_login.php' style='color: #2c5530; text-decoration: underline;' target='_blank'>https://ccsolfualumni.sbs/al_login.php</a></p>
                                    </div>
                                    
                                    <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                                        <p style='font-size: 14px; color: #666; margin-bottom: 5px;'><strong>Login Credentials:</strong></p>
                                        <p style='font-size: 14px; color: #666; margin: 0;'><strong>Email:</strong> {$user_data['email']}</p>
                                        <p style='font-size: 14px; color: #666; margin: 0;'><strong>Password:</strong> [The password you created during registration]</p>
                                    </div>
                                    
                                    <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                                        <p style='color: #2c5530; font-weight: bold; margin: 0;'>Welcome to the CCS OLFU Alumni Community!</p>
                                        <p style='color: #666; font-size: 14px; margin: 10px 0 0 0;'>Best regards,<br>CCS OLFU Alumni Affairs Team</p>
                                    </div>
                                </div>
                            </div>
                        ";
                        } else {
                        // Rejection email
                        $mail->Subject = 'Registration Status Update - Account Not Approved';
                        $mail->Body = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;'>
                            <div style='background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                                <div style='text-align: center; margin-bottom: 30px;'>
                                    <h1 style='color: #dc2626; margin-bottom: 10px;'>Registration Status Update</h1>
                                </div>
                                
                                <p style='font-size: 16px; color: #333; line-height: 1.6;'>Dear <strong>{$user_data['firstname']} {$user_data['lastname']}</strong>,</p>
                                
                                <p style='font-size: 16px; color: #333; line-height: 1.6;'>We regret to inform you that your registration has not been approved at this time.</p>
                                
                                " . ($reason ? ("
                                <div style='background-color: #fee2e2; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #dc2626;'>
                                    <p style='margin: 0; font-size: 16px; color: #991b1b;'><strong>Reason for Rejection:</strong></p>
                                    <p style='margin: 10px 0 0 0; font-size: 15px; color: #7f1d1d;'>" . htmlspecialchars($reason) . "</p>
                                </div>
                                ") : "") . "
                                
                                <div style='background-color: #f0f8ff; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                                    <h3 style='color: #1e40af; margin-top: 0;'>What's Next?</h3>
                                    <p style='margin-bottom: 15px; color: #333;'>If you believe this is an error or would like to resubmit your registration, please contact the Alumni Affairs office for assistance.</p>
                                    <div style='text-align: center;'>
                                        <table style='margin: 15px auto; border-collapse: collapse;'>
                                            <tr>
                                                <td style='background-color: #1e40af; border-radius: 8px; padding: 0;'>
                                                    <a href='https://ccsolfualumni.sbs/al_login.php' 
                                                       style='display: block; padding: 15px 35px; color: white; text-decoration: none; font-weight: bold; font-size: 16px;'
                                                       target='_blank'
                                                       rel='noopener noreferrer'>📧 Contact Support</a>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                
                                <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                                    <p style='color: #666; font-size: 14px; margin: 10px 0 0 0;'>Best regards,<br>CCS OLFU Alumni Affairs Team</p>
                                </div>
                                </div>
                            </div>
                        ";
                    }
                    
                    // Create plain text version for better email client compatibility
                    if ($action === 'approve') {
                        $plain_text_body = "Congratulations!\n\n";
                        $plain_text_body .= "Dear {$user_data['firstname']} {$user_data['lastname']},\n\n";
                        $plain_text_body .= "We are delighted to inform you that your registration has been approved! Welcome to the CCS OLFU Alumni Community!\n\n";
                        $plain_text_body .= "Your account is now active and ready to use!\n\n";
                        $plain_text_body .= "Login to your account: https://ccsolfualumni.sbs/al_login.php\n\n";
                        $plain_text_body .= "Login Credentials:\n";
                        $plain_text_body .= "Email: {$user_data['email']}\n";
                        $plain_text_body .= "Password: [The password you created during registration]\n\n";
                        $plain_text_body .= "Welcome to the CCS OLFU Alumni Community!\n\n";
                        $plain_text_body .= "Best regards,\nCCS OLFU Alumni Affairs Team";
                    } else {
                        $plain_text_body = "Registration Status Update\n\n";
                        $plain_text_body .= "Dear {$user_data['firstname']} {$user_data['lastname']},\n\n";
                        $plain_text_body .= "We regret to inform you that your registration has not been approved at this time.\n\n";
                        if ($reason) {
                            $plain_text_body .= "Reason for Rejection: " . strip_tags($reason) . "\n\n";
                        }
                        $plain_text_body .= "If you believe this is an error or would like to resubmit your registration, please contact the Alumni Affairs office for assistance.\n\n";
                        $plain_text_body .= "Best regards,\nCCS OLFU Alumni Affairs Team";
                    }
                    
                    // Set AltBody for plain text email clients
                    $mail->AltBody = $plain_text_body;
                    
                    // Send the email
                    error_log("Attempting to send email to: " . $recipient . " (Action: " . $action . ", User ID: " . $user_id . ")");
                    
                    if ($mail->send()) {
                        error_log("SUCCESS: Email sent successfully using PHPMailer to: " . $recipient . " (Action: " . $action . ", User ID: " . $user_id . ")");
                        $email_sent = true;
                    } else {
                        $error_msg = $mail->ErrorInfo;
                        error_log("ERROR: Failed to send email to: " . $recipient . " - Error: " . $error_msg . " (Action: " . $action . ", User ID: " . $user_id . ")");
                        throw new \Exception("Failed to send email: " . $error_msg);
                    }
                    } catch (\Exception $e) {
                        $error_details = isset($mail) && $mail ? $mail->ErrorInfo : $e->getMessage();
                        error_log("CRITICAL: Admin email sending failed for user_id " . $user_id . ": " . $error_details);
                        error_log("Exception details: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
                        // Continue even if email fails - don't block the approval
                    } catch (\Throwable $e) {
                        error_log("CRITICAL: Admin email sending failed (Throwable) for user_id " . $user_id . ": " . $e->getMessage());
                        error_log("Throwable details: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
                        // Continue even if email fails - don't block the approval
                    }
                } else {
                    if (!$email_sent) {
                        error_log("WARNING: Email notification skipped - No email method available. email_function_available=" . ($email_function_available ? 'true' : 'false') . ", phpmailer_available=" . ($phpmailer_available ? 'true' : 'false') . ", class_exists=" . (class_exists('PHPMailer\PHPMailer\PHPMailer') ? 'true' : 'false'));
                    }
                }
            
                if ($email_sent) {
                    error_log("Email notification sent successfully for user_id: " . $user_id . " (Action: " . $action . ")");
                } else {
                    error_log("CRITICAL: Email notification FAILED for user_id: " . $user_id . " (Action: " . $action . ") - User will not receive notification email");
                }
                
                // Close statements
                $update_stmt->close();
                $user_stmt->close();
                $conn->close();
                
                // Clean output buffer and redirect (after reject, show pending list so rejected user is gone)
                ob_end_clean();
                if ($action === 'reject') {
                    header("Location: ad_user_management.php?status=pending&status_msg=rejected");
                } else {
                    header("Location: ad_user_management.php?status_msg=approved");
                }
                exit();
			}
        } else {
            $user_stmt->close();
            $conn->close();
            ob_end_clean();
            error_log("User not found in ad_approve_user.php: user_id=" . $user_id);
            header("Location: ad_user_management.php?error=notfound");
            exit();
        }
    } catch (Throwable $e) {
        // Clean up any open connections
        if (isset($user_stmt) && $user_stmt) {
            $user_stmt->close();
        }
        if (isset($update_stmt) && $update_stmt) {
            $update_stmt->close();
        }
        if (isset($conn) && $conn) {
            $conn->close();
        }
        ob_end_clean();
        error_log("Exception in ad_approve_user.php: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        header("Location: ad_user_management.php?error=exception");
        exit();
    }
} else {
    if (isset($conn) && $conn) {
        $conn->close();
    }
    ob_end_clean();
    header("Location: ad_user_management.php");
    exit();
}
?>


