<?php
session_start();
require_once 'db_config.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: al_login.php');
    exit();
}

$conn = getDBConnection();

// Load mail so we can send approval/rejection emails to users' personal email
$notification_email_available = false;
if (@file_exists(__DIR__ . '/mail.php')) {
    @include_once __DIR__ . '/mail.php';
    $notification_email_available = function_exists('sendEmail');
}
@include_once __DIR__ . '/includes/ad_approve_email_attachments.php';

// CSRF: require token (set on ad_user_management.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || empty($_SESSION['admin_csrf']) || !hash_equals((string)$_SESSION['admin_csrf'], (string)$_POST['csrf_token'])) {
        header("Location: ad_user_management.php?error=csrf");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['user_ids'])) {
    $action = $_POST['action'];
    $user_ids = $_POST['user_ids'];
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

    // Validate action
    if (!in_array($action, ['approve', 'reject', 'archive'])) {
        header("Location: ad_user_management.php?error=invalid_action");
        exit();
    }

    // Validate user_ids is an array
    if (!is_array($user_ids)) {
        header("Location: ad_user_management.php?error=invalid_ids");
        exit();
    }

    // Filter and sanitize user IDs
    $user_ids = array_filter(array_map('intval', $user_ids));

    if (empty($user_ids)) {
        header("Location: ad_user_management.php?error=no_users_selected");
        exit();
    }

    // For reject action, require reason
    if ($action === 'reject' && empty($reason)) {
        header("Location: ad_user_management.php?error=reason_required");
        exit();
    }

    $success_count = 0;
    $failed_count = 0;
    $errors = [];

    // Process each user
    foreach ($user_ids as $user_id) {
        try {
            // Get user details
            $user_sql = "SELECT * FROM itcp WHERE id = ?";
            $user_stmt = $conn->prepare($user_sql);
            if (!$user_stmt) {
                $errors[] = "Failed to prepare query for user ID $user_id: " . $conn->error;
                $failed_count++;
                continue;
            }

            $user_stmt->bind_param("i", $user_id);
            if (!$user_stmt->execute()) {
                $errors[] = "Failed to execute query for user ID $user_id: " . $user_stmt->error;
                $failed_count++;
                $user_stmt->close();
                continue;
            }

            $user_result = $user_stmt->get_result();
            $user_data = $user_result->fetch_assoc();
            $user_stmt->close();

            if (!$user_data) {
                $errors[] = "User ID $user_id not found";
                $failed_count++;
                continue;
            }

            // Update status based on action
            if ($action === 'approve') {
                $email = trim($user_data['email'] ?? '');
                $student_number = trim($user_data['student_number'] ?? '');
                $normalized_sn = preg_replace('/[\s\-]+/', '', $student_number);

                // Duplicate email check
                if ($email !== '') {
                    $dup = $conn->prepare("SELECT COUNT(*) AS c FROM itcp WHERE LOWER(TRIM(email)) = LOWER(?) AND id != ? AND status IN ('active','pending')");
                    if ($dup) {
                        $dup->bind_param('si', $email, $user_id);
                        $dup->execute();
                        $r = $dup->get_result()->fetch_assoc();
                        $dup->close();
                        if (isset($r['c']) && (int)$r['c'] > 0) {
                            $errors[] = "User ID $user_id ({$user_data['email']}) has duplicate email. Cannot approve.";
                            $failed_count++;
                            continue;
                        }
                    }
                }

                // Duplicate alumni ID number check
                if ($normalized_sn !== '') {
                    $all = $conn->prepare("SELECT id, student_number FROM itcp WHERE id != ? AND status IN ('active','pending') AND TRIM(student_number) != ''");
                    if ($all) {
                        $all->bind_param('i', $user_id);
                        $all->execute();
                        $res = $all->get_result();
                        $duplicate_sn = false;
                        while ($other = $res->fetch_assoc()) {
                            $other_sn = preg_replace('/[\s\-]+/', '', trim($other['student_number'] ?? ''));
                            if (strcasecmp($other_sn, $normalized_sn) === 0) {
                                $duplicate_sn = true;
                                break;
                            }
                        }
                        $all->close();
                        if ($duplicate_sn) {
                            $errors[] = "User ID $user_id (Alumni # {$student_number}) has duplicate alumni ID number. Cannot approve.";
                            $failed_count++;
                            continue;
                        }
                    }
                }

                // Password required
                if (empty($user_data['password']) || !is_string($user_data['password']) || trim($user_data['password']) === '') {
                    $errors[] = "User ID $user_id ({$user_data['email']}) has no password set. Cannot approve.";
                    error_log("Bulk approval failed - User ID $user_id has empty or invalid password field");
                    $failed_count++;
                    continue;
                }

                // Only update status, explicitly preserve password
                $update_sql = "UPDATE itcp SET status = 'active' WHERE id = ? AND password IS NOT NULL AND password != ''";
                $update_stmt = $conn->prepare($update_sql);
                if (!$update_stmt) {
                    $errors[] = "Failed to prepare update for user ID $user_id: " . $conn->error;
                    $failed_count++;
                    continue;
                }
                $update_stmt->bind_param("i", $user_id);
            }
            elseif ($action === 'reject') {
                // Rejected users go to archives
                $new_status = 'archived';
                $update_sql = "UPDATE itcp SET status = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                if (!$update_stmt) {
                    $errors[] = "Failed to prepare update for user ID $user_id: " . $conn->error;
                    $failed_count++;
                    continue;
                }
                $update_stmt->bind_param("si", $new_status, $user_id);
            }
            else { // archive
                $new_status = 'archived';
                $update_sql = "UPDATE itcp SET status = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                if (!$update_stmt) {
                    $errors[] = "Failed to prepare update for user ID $user_id: " . $conn->error;
                    $failed_count++;
                    continue;
                }
                $update_stmt->bind_param("si", $new_status, $user_id);
            }

            if (!$update_stmt->execute()) {
                $errors[] = "Failed to update user ID $user_id: " . $update_stmt->error;
                $failed_count++;
                $update_stmt->close();
                continue;
            }

            // Log the action
            try {
                $log_stmt = $conn->prepare("INSERT INTO user_logs (user_id, activity_type, activity_description, ip_address) VALUES (?, ?, ?, ?)");
                if ($log_stmt) {
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                    $activity = $action === 'approve' ? 'verify_approve' : ($action === 'reject' ? 'verify_reject' : 'archive_user');
                    $desc = "Admin {$action}d user: {$user_data['firstname']} {$user_data['lastname']} ({$user_data['email']})" . ($reason ? " - Reason: $reason" : "");
                    $log_stmt->bind_param('isss', $user_id, $activity, $desc, $ip);
                    $log_stmt->execute();
                    $log_stmt->close();
                }
            }
            catch (Exception $e) {
                error_log("Failed to log user action: " . $e->getMessage());
            }

            // When approving: send approval email to user's personal email
            if ($action === 'approve') {
                $recipient = isset($user_data['email']) && !empty(trim($user_data['email'] ?? '')) ? trim($user_data['email']) : '';
                if ($recipient !== '' && filter_var($recipient, FILTER_VALIDATE_EMAIL) && $notification_email_available) {
                    $firstname = $user_data['firstname'] ?? '';
                    $lastname = $user_data['lastname'] ?? '';
                    $email_subject = 'Your account is approved – Welcome to CCS OLFU Alumni Community!';
                    $email_body = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;'>
                            <div style='background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                                <div style='text-align: center; margin-bottom: 30px;'>
                                    <h1 style='color: #2c5530; margin-bottom: 10px;'>Congratulations!</h1>
                                    <h2 style='color: #2c5530; margin: 0;'>Your Account is Now Approved</h2>
                                </div>
                                <p style='font-size: 16px; color: #333; line-height: 1.6;'>Dear <strong>" . htmlspecialchars($firstname . ' ' . $lastname) . "</strong>,</p>
                                <p style='font-size: 16px; color: #333; line-height: 1.6;'>We are delighted to inform you that your registration has been approved! <strong>Welcome to the CCS OLFU Alumni Community!</strong></p>
                                <div style='background-color: #e8f5e8; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2c5530;'>
                                    <p style='margin: 0; font-size: 16px; color: #2c5530;'><strong>Your account is now active and ready to use!</strong></p>
                                </div>
                                <div style='background-color: #f0f8ff; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                                    <h3 style='color: #2c5530; margin-top: 0;'>Ready to Get Started?</h3>
                                    <p style='margin-bottom: 15px; color: #333;'>Click the link below to access your alumni portal:</p>
                                    <p style='font-size: 14px; color: #666;'><a href='https://ccsolfualumni.sbs/al_login.php' style='color: #2c5530; text-decoration: underline;' target='_blank'>https://ccsolfualumni.sbs/al_login.php</a></p>
                                </div>
                                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                                    <p style='font-size: 14px; color: #666; margin-bottom: 5px;'><strong>Login:</strong> Use the email and password you created during registration.</p>
                                </div>
                                <div style='text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;'>
                                    <p style='color: #666; font-size: 14px; margin: 10px 0 0 0;'>Best regards,<br>CCS OLFU Alumni Affairs Team</p>
                                </div>
                            </div>
                        </div>
                    ";
                    $attachments = [];
                    $tmpPng = null;
                    if (function_exists('ad_approve_collect_approval_attachments')) {
                        $pack = ad_approve_collect_approval_attachments($conn, $user_data, $user_id);
                        $attachments = $pack['attachments'];
                        $tmpPng = $pack['tmp_png_paths'] !== [] ? $pack['tmp_png_paths'] : null;
                    }
                    try {
                        if (sendEmail($recipient, $email_subject, $email_body, $attachments)) {
                            error_log("Bulk approve: approval email sent to " . $recipient . " (user_id: " . $user_id . ")");
                        } else {
                            error_log("Bulk approve: sendEmail returned false for " . $recipient . " (user_id: " . $user_id . ")");
                        }
                    } catch (Exception $e) {
                        error_log("Bulk approve: failed to send email to " . $recipient . ": " . $e->getMessage());
                    } finally {
                        if ($tmpPng !== null && function_exists('ad_approve_id_card_unlink_paths')) {
                            ad_approve_id_card_unlink_paths($tmpPng);
                        }
                    }
                }
            }

            // When rejecting: send email to user's personal/registered email with the reason
            if ($action === 'reject') {
                $recipient = isset($user_data['email']) && !empty(trim($user_data['email'] ?? '')) ? trim($user_data['email']) : '';
                if ($recipient !== '' && filter_var($recipient, FILTER_VALIDATE_EMAIL) && $notification_email_available) {
                    $firstname = $user_data['firstname'] ?? '';
                    $lastname = $user_data['lastname'] ?? '';
                    $reason_esc = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
                    $email_subject = 'Registration Status Update - Account Not Approved';
                    $email_body = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;'>
                            <div style='background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                                <div style='text-align: center; margin-bottom: 30px;'>
                                    <h1 style='color: #dc2626; margin-bottom: 10px;'>Registration Status Update</h1>
                                </div>
                                <p style='font-size: 16px; color: #333; line-height: 1.6;'>Dear <strong>" . htmlspecialchars($firstname . ' ' . $lastname) . "</strong>,</p>
                                <p style='font-size: 16px; color: #333; line-height: 1.6;'>We regret to inform you that your registration has not been approved at this time.</p>
                                <div style='background-color: #fee2e2; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #dc2626;'>
                                    <p style='margin: 0; font-size: 16px; color: #991b1b;'><strong>Reason for Rejection:</strong></p>
                                    <p style='margin: 10px 0 0 0; font-size: 15px; color: #7f1d1d;'>" . $reason_esc . "</p>
                                </div>
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
                    try {
                        if (sendEmail($recipient, $email_subject, $email_body)) {
                            error_log("Bulk reject: rejection email sent to " . $recipient . " (user_id: " . $user_id . ")");
                        } else {
                            error_log("Bulk reject: sendEmail returned false for " . $recipient . " (user_id: " . $user_id . ")");
                        }
                    } catch (Exception $e) {
                        error_log("Bulk reject: failed to send email to " . $recipient . ": " . $e->getMessage());
                    }
                } else {
                    if ($recipient === '') {
                        error_log("Bulk reject: no email for user_id " . $user_id . ", skipping notification");
                    } elseif (!$notification_email_available) {
                        error_log("Bulk reject: sendEmail not available, skipping notification for user_id " . $user_id);
                    }
                }
            }

            $update_stmt->close();
            $success_count++;

        }
        catch (Exception $e) {
            $errors[] = "Error processing user ID $user_id: " . $e->getMessage();
            $failed_count++;
            error_log("Bulk action error for user ID $user_id: " . $e->getMessage());
        }
    }

    $conn->close();

    // Build redirect URL with results (after reject, show pending so rejected users are out of the list)
    $redirect_params = [];
    if ($action === 'reject') {
        $redirect_params[] = "status=pending";
    }
    if ($success_count > 0) {
        $redirect_params[] = "bulk_success=" . urlencode("$success_count user(s) {$action}d successfully");
    }
    if ($failed_count > 0) {
        $redirect_params[] = "bulk_error=" . urlencode("$failed_count user(s) failed to process");
    }

    $redirect_url = "ad_user_management.php?" . implode("&", $redirect_params);
    header("Location: $redirect_url");
    exit();
}
else {
    $conn->close();
    header("Location: ad_user_management.php?error=invalid_request");
    exit();
}
