<?php
/**
 * Admin single-user approve / reject: transactional approve, registrar masterlist gate, CPS, post-commit email.
 */
declare(strict_types=1);

/**
 * @param array<string, mixed> $user_data
 */
function ad_approve_send_notification_email(
    bool $isApprove,
    array $user_data,
    int $user_id,
    string $reason,
    string $cps_block,
    string $loginUrl,
    string $idCardUrl,
    bool $email_function_available,
    bool $phpmailer_available,
    ?mysqli $conn = null
): bool {
    $email_sent = false;
    $recipient = isset($user_data['email']) ? trim((string) $user_data['email']) : '';
    if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $hLogin = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');
    $hCard = htmlspecialchars($idCardUrl, ENT_QUOTES, 'UTF-8');
    $fnEsc = htmlspecialchars((string) ($user_data['firstname'] ?? ''), ENT_QUOTES, 'UTF-8');
    $lnEsc = htmlspecialchars((string) ($user_data['lastname'] ?? ''), ENT_QUOTES, 'UTF-8');

    $attachments = [];
    $tmpPngPaths = [];
    $idBlurb = '';
    if ($isApprove) {
        require_once __DIR__ . '/ad_approve_email_attachments.php';
        $pack = ad_approve_collect_approval_attachments($conn, $user_data, $user_id);
        $attachments = $pack['attachments'];
        $tmpPngPaths = $pack['tmp_png_paths'];
        $idBlurb = ad_approve_id_attachment_email_blurb($pack['has_verification'], $pack['has_digital']);
    }

    try {
    if ($email_function_available && function_exists('sendEmail')) {
        try {
            if ($isApprove) {
                $email_subject = 'Your OLFU CCS Alumni account is approved';
                $email_body = "
                                <div style='font-family: Arial, Helvetica, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f4f6f4;'>
                                    <div style='background-color: #ffffff; padding: 28px; border-radius: 12px; border: 1px solid #e5e7eb;'>
                                        <div style='text-align:center;margin-bottom:20px;'>
                                            <p style='margin:0;font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:#8b0000;font-weight:700;'>Our Lady of Fatima University</p>
                                            <h1 style='color:#14532d;margin:10px 0 0;font-size:22px;'>College of Computer Studies — Alumni</h1>
                                        </div>
                                        <h2 style='color:#2c5530;margin:0 0 12px;font-size:18px;'>Your registration is approved</h2>
                                        <p style='font-size:15px;color:#333;line-height:1.6;'>Dear <strong>{$fnEsc} {$lnEsc}</strong>,</p>
                                        {$cps_block}
                                        <p style='font-size:15px;color:#333;line-height:1.6;'>Your alumni portal access is now <strong>active</strong>. Use your registered email and the password you created at signup.</p>
                                        <div style='margin:22px 0;padding:18px;border-radius:10px;background:#f0fdf4;border-left:4px solid #2c5530;'>
                                            <p style='margin:0 0 10px;font-size:14px;color:#14532d;font-weight:700;'>Digital alumni ID card</p>
                                            {$idBlurb}
                                            <p style='margin:0 0 14px;font-size:14px;color:#374151;'>Open your official alumni ID card (sign in may be required):</p>
                                            <table style='margin:0 auto;border-collapse:collapse;'><tr><td style='background:#2c5530;border-radius:8px;padding:0;'>
                                                <a href='{$hCard}' style='display:block;padding:14px 28px;color:#fff;text-decoration:none;font-weight:700;font-size:15px;' target='_blank' rel='noopener noreferrer'>View Alumni ID Card</a>
                                            </td></tr></table>
                                            <p style='margin:14px 0 0;font-size:13px;color:#6b7280;'>Link: <a href='{$hCard}' style='color:#2c5530;' target='_blank' rel='noopener noreferrer'>{$hCard}</a></p>
                                        </div>
                                        <div style='margin:22px 0;padding:18px;border-radius:10px;background:#f8fafc;border:1px solid #e2e8f0;'>
                                            <p style='margin:0 0 10px;font-size:14px;color:#0f172a;font-weight:700;'>Alumni portal</p>
                                            <table style='margin:0 auto;border-collapse:collapse;'><tr><td style='background:#8b0000;border-radius:8px;padding:0;'>
                                                <a href='{$hLogin}' style='display:block;padding:14px 28px;color:#fff;text-decoration:none;font-weight:700;font-size:15px;' target='_blank' rel='noopener noreferrer'>Sign in to the portal</a>
                                            </td></tr></table>
                                            <p style='margin:12px 0 0;font-size:13px;color:#6b7280;'>{$hLogin}</p>
                                        </div>
                                        <p style='font-size:13px;color:#6b7280;margin-top:24px;border-top:1px solid #eee;padding-top:16px;'>Best regards,<br><strong>CCS OLFU Alumni Affairs</strong></p>
                                    </div>
                                </div>";
            } else {
                $email_subject = 'Registration status — OLFU CCS Alumni';
                $email_body = "
                                <div style='font-family: Arial, Helvetica, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;'>
                                    <div style='background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);'>
                                        <p style='margin:0 0 8px;font-size:12px;letter-spacing:.1em;text-transform:uppercase;color:#8b0000;font-weight:700;'>Our Lady of Fatima University · CCS Alumni</p>
                                        <h1 style='color: #dc2626; margin-bottom: 10px; font-size:20px;'>Registration update</h1>
                                        <p style='font-size: 16px; color: #333; line-height: 1.6;'>Dear <strong>{$fnEsc} {$lnEsc}</strong>,</p>
                                        <p style='font-size: 16px; color: #333; line-height: 1.6;'>We regret to inform you that your registration has not been approved at this time.</p>
                                        " . ($reason !== '' ? ("
                                        <div style='background-color: #fee2e2; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #dc2626;'>
                                            <p style='margin: 0; font-size: 16px; color: #991b1b;'><strong>Reason:</strong></p>
                                            <p style='margin: 10px 0 0 0; font-size: 15px; color: #7f1d1d;'>" . htmlspecialchars($reason, ENT_QUOTES, 'UTF-8') . "</p>
                                        </div>
                                        ") : '') . "
                                        <p style='font-size: 14px; color: #666; margin-top: 24px;'>Best regards,<br><strong>CCS OLFU Alumni Affairs</strong></p>
                                    </div>
                                </div>";
            }
            if (sendEmail($recipient, $email_subject, $email_body, $attachments)) {
                $email_sent = true;
            }
        } catch (Throwable $e) {
            error_log('ad_approve_workflow: sendEmail error for user ' . $user_id . ': ' . $e->getMessage());
        }
    }

    if (!$email_sent && $phpmailer_available && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'shairamaebuensucesobasigaa@gmail.com';
            $mail->Password = 'iqiakhldmxqdancx';
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            $mail->Timeout = 30;
            $mail->SMTPKeepAlive = false;
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
            $mail->SMTPDebug = 0;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom('shairamaebuensucesobasigaa@gmail.com', 'OLFU ALUMNI AFFAIRS');
            $mail->addReplyTo('shairamaebuensucesobasigaa@gmail.com', 'OLFU ALUMNI AFFAIRS');
            $mail->addAddress($recipient);
            $mail->isHTML(true);
            if ($isApprove) {
                $mail->Subject = 'Account approved — OLFU CCS Alumni';
                $qr_code_path = dirname(__DIR__) . '/qrcode.png';
                $qr_code_html = '';
                if (@file_exists($qr_code_path)) {
                    $mail->addEmbeddedImage($qr_code_path, 'qrcode', 'qrcode.png');
                    $qr_code_html = '<img src="cid:qrcode" alt="QR" style="max-width:200px;height:auto;margin:20px 0;">';
                } else {
                    $qr_code_html = '<p style="text-align:center;color:#2c5530;font-weight:700;">Alumni portal</p>';
                }
                $mail->Body = "
                            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9;'>
                                <div style='background-color: white; padding: 30px; border-radius: 10px;'>
                                    <p style='margin:0 0 8px;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#8b0000;'>OLFU · CCS Alumni</p>
                                    <h1 style='color: #2c5530;'>Congratulations</h1>
                                    <p style='font-size: 16px; color: #333;'>Dear <strong>{$fnEsc} {$lnEsc}</strong>,</p>
                                    {$cps_block}
                                    <p style='font-size: 16px; color: #333;'>Your registration has been approved.</p>
                                    {$idBlurb}
                                    <div style='text-align:center;margin:24px 0;'>{$qr_code_html}</div>
                                    <p style='text-align:center;margin:16px 0;'><a href='{$hCard}' style='color:#2c5530;font-weight:700;'>Open your alumni ID card</a></p>
                                    <p style='text-align:center;'><a href='{$hLogin}' style='display:inline-block;padding:12px 24px;background:#2c5530;color:#fff;border-radius:8px;text-decoration:none;font-weight:700;'>Sign in</a></p>
                                </div>
                            </div>";
                $mail->AltBody = "Approved. ID card: {$idCardUrl}\nPortal: {$loginUrl}\n";
            } else {
                $mail->Subject = 'Registration status update';
                $mail->Body = '<p>Dear ' . htmlspecialchars((string) $user_data['firstname'], ENT_QUOTES, 'UTF-8') . ', your registration was not approved.</p>';
                $mail->AltBody = strip_tags($reason);
            }
            foreach ($attachments as $att) {
                $path = is_array($att) ? (string) ($att['path'] ?? '') : (string) $att;
                if ($path === '' || !is_file($path) || !is_readable($path)) {
                    continue;
                }
                $name = is_array($att) ? (string) ($att['name'] ?? '') : '';
                if ($name === '') {
                    $name = basename($path);
                }
                $mail->addAttachment($path, $name);
            }
            if ($mail->send()) {
                $email_sent = true;
            }
        } catch (Throwable $e) {
            error_log('ad_approve_workflow: PHPMailer error for user ' . $user_id . ': ' . $e->getMessage());
        }
    }

    } finally {
        if ($tmpPngPaths !== []) {
            require_once __DIR__ . '/ad_approve_id_card_png.php';
            ad_approve_id_card_unlink_paths($tmpPngPaths);
        }
    }

    return $email_sent;
}

/**
 * Run approve/reject; always closes connection and exits with Location (or throws on unexpected DB errors before redirect).
 *
 * @throws Exception on unexpected prepare/execute failures when redirect is not possible
 */
function ad_approve_process_user(
    mysqli $conn,
    int $user_id,
    string $action,
    string $reason,
    bool $email_function_available,
    bool $phpmailer_available
): void {
    $user_sql = 'SELECT * FROM itcp WHERE id = ?';
    $user_stmt = $conn->prepare($user_sql);
    if (!$user_stmt) {
        throw new Exception('Failed to prepare user query: ' . $conn->error);
    }
    $user_stmt->bind_param('i', $user_id);
    if (!$user_stmt->execute()) {
        $user_stmt->close();
        throw new Exception('Failed to execute user query: ' . $user_stmt->error);
    }
    $user_result = $user_stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    if (!$user_data) {
        $user_stmt->close();
        $conn->close();
        ob_end_clean();
        header('Location: ad_user_management.php?error=notfound');
        exit();
    }

    $baseUrl = cps_site_origin();
    $loginUrl = $baseUrl . '/al_login.php';
    $idCardUrl = $baseUrl . '/alumni_id_card.php';
    $pendingType = 'new';
    $pendingLegacyId = '';
    $pstmt = $conn->prepare('SELECT registration_type, current_alumni_id FROM pending_registrations WHERE itcp_id = ? ORDER BY id DESC LIMIT 1');
    if ($pstmt) {
        $pstmt->bind_param('i', $user_id);
        $pstmt->execute();
        $prow = $pstmt->get_result()->fetch_assoc();
        $pstmt->close();
        if ($prow) {
            $pendingType = strtolower(trim((string) ($prow['registration_type'] ?? 'new')));
            if ($pendingType !== 'legacy') {
                $pendingType = 'new';
            }
            $pendingLegacyId = trim((string) ($prow['current_alumni_id'] ?? ''));
        }
    }

    if ($action === 'reject') {
        $new_status = 'archived';
        $update_stmt = $conn->prepare('UPDATE itcp SET status = ? WHERE id = ?');
        if (!$update_stmt) {
            $user_stmt->close();
            throw new Exception('Failed to prepare update query: ' . $conn->error);
        }
        $update_stmt->bind_param('si', $new_status, $user_id);
        if (!$update_stmt->execute()) {
            $err = $update_stmt->error;
            $update_stmt->close();
            $user_stmt->close();
            $conn->close();
            ob_end_clean();
            error_log('ad_approve_workflow reject update failed: ' . $err);
            header('Location: ad_user_management.php?error=update');
            exit();
        }
        $update_stmt->close();

        try {
            $log_stmt = $conn->prepare('INSERT INTO user_logs (user_id, activity_type, activity_description, ip_address) VALUES (?, ?, ?, ?)');
            if ($log_stmt) {
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $desc = 'Admin rejected account' . ($reason !== '' ? (': ' . $reason) : '');
                $activity = 'verify_reject';
                $log_stmt->bind_param('isss', $user_id, $activity, $desc, $ip);
                $log_stmt->execute();
                $log_stmt->close();
            }
        } catch (Throwable $e) {
            error_log('ad_approve_workflow: log reject failed: ' . $e->getMessage());
        }

        $email_sent = false;
        try {
            $email_sent = ad_approve_send_notification_email(
                false,
                $user_data,
                $user_id,
                $reason,
                '',
                $loginUrl,
                $idCardUrl,
                $email_function_available,
                $phpmailer_available,
                $conn
            );
        } catch (Throwable $e) {
            error_log('ad_approve_workflow: reject email: ' . $e->getMessage());
        }

        $user_stmt->close();
        $conn->close();
        ob_end_clean();
        $q = 'status=pending&status_msg=rejected';
        if (!$email_sent) {
            $q .= '&email_sent=0';
        } else {
            $q .= '&email_sent=1';
        }
        header('Location: ad_user_management.php?' . $q);
        exit();
    }

    // --- Approve ---
    $email = trim((string) ($user_data['email'] ?? ''));
    $student_number = trim((string) ($user_data['student_number'] ?? ''));
    $normalized_sn = preg_replace('/[\s\-]+/', '', $student_number);

    if ($email !== '') {
        $dup = $conn->prepare("SELECT COUNT(*) AS c FROM itcp WHERE LOWER(TRIM(email)) = LOWER(?) AND id != ? AND status IN ('active','pending')");
        if ($dup) {
            $dup->bind_param('si', $email, $user_id);
            $dup->execute();
            $r = $dup->get_result()->fetch_assoc();
            $dup->close();
            if (isset($r['c']) && (int) $r['c'] > 0) {
                $user_stmt->close();
                $conn->close();
                ob_end_clean();
                header('Location: ad_user_management.php?error=duplicate_email');
                exit();
            }
        }
    }

    if ($normalized_sn !== '') {
        $all = $conn->prepare("SELECT id, student_number FROM itcp WHERE id != ? AND status IN ('active','pending') AND TRIM(student_number) != ''");
        if ($all) {
            $all->bind_param('i', $user_id);
            $all->execute();
            $res = $all->get_result();
            while ($other = $res->fetch_assoc()) {
                $other_sn = preg_replace('/[\s\-]+/', '', trim((string) ($other['student_number'] ?? '')));
                if (strcasecmp($other_sn, $normalized_sn) === 0) {
                    $all->close();
                    $user_stmt->close();
                    $conn->close();
                    ob_end_clean();
                    header('Location: ad_user_management.php?error=duplicate_student_number');
                    exit();
                }
            }
            $all->close();
        }
    }

    if (empty($user_data['password']) || !is_string($user_data['password']) || trim((string) $user_data['password']) === '') {
        error_log("WARNING: User ID {$user_id} has empty password during approval");
        $user_stmt->close();
        $conn->close();
        ob_end_clean();
        header('Location: ad_user_management.php?error=no_password');
        exit();
    }

    $statusNow = strtolower(trim((string) ($user_data['status'] ?? '')));
    if ($statusNow === 'active') {
        $user_stmt->close();
        $conn->close();
        ob_end_clean();
        header('Location: ad_user_management.php?error=already_verified');
        exit();
    }

    $bdRaw = trim((string) ($user_data['birthday'] ?? ''));
    $bdOk = $bdRaw !== '' && $bdRaw !== '0000-00-00' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $bdRaw) === 1;
    if (!$bdOk) {
        $user_stmt->close();
        $conn->close();
        ob_end_clean();
        header('Location: ad_user_management.php?error=dob_required');
        exit();
    }

    $normMaster = cps_normalize_student_id_for_masterlist($student_number);
    if (!cps_registrar_masterlist_match($conn, $normMaster, $bdRaw)) {
        $user_stmt->close();
        $conn->close();
        ob_end_clean();
        header('Location: ad_user_management.php?error=masterlist');
        exit();
    }

    $conn->begin_transaction();
    try {
        $update_stmt = $conn->prepare("UPDATE itcp SET status = 'active' WHERE id = ? AND password IS NOT NULL AND password != ''");
        if (!$update_stmt) {
            throw new Exception('Failed to prepare approve update: ' . $conn->error);
        }
        $update_stmt->bind_param('i', $user_id);
        if (!$update_stmt->execute()) {
            throw new Exception('Approve update failed: ' . $update_stmt->error);
        }

        $verify_stmt = $conn->prepare('SELECT status FROM itcp WHERE id = ?');
        if (!$verify_stmt) {
            throw new Exception('Failed to prepare verify: ' . $conn->error);
        }
        $verify_stmt->bind_param('i', $user_id);
        $verify_stmt->execute();
        $verify_row = $verify_stmt->get_result()->fetch_assoc();
        $verify_stmt->close();
        if (!$verify_row || strtolower(trim((string) ($verify_row['status'] ?? ''))) !== 'active') {
            throw new RuntimeException('verify_status');
        }

        try {
            $log_stmt = $conn->prepare('INSERT INTO user_logs (user_id, activity_type, activity_description, ip_address) VALUES (?, ?, ?, ?)');
            if ($log_stmt) {
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $activity = 'verify_approve';
                $desc = 'Admin approved account';
                $log_stmt->bind_param('isss', $user_id, $activity, $desc, $ip);
                $log_stmt->execute();
                $log_stmt->close();
            }
        } catch (Throwable $e) {
            error_log('ad_approve_workflow: log approve failed: ' . $e->getMessage());
        }

        $override = isset($_POST['cps_override']) ? trim((string) $_POST['cps_override']) : null;
        if ($override === '') { $override = null; }
        if ($pendingType === 'legacy' && $pendingLegacyId !== '') {
            $override = $pendingLegacyId;
        }
        $campusCode = isset($user_data['campus']) ? substr(trim((string) $user_data['campus']), 0, 32) : null;
        if ($pendingType === 'legacy' && $pendingLegacyId === '') {
            throw new RuntimeException('legacy_id_missing');
        }
        $cps_info = cps_assign_to_alumni($conn, $user_id, $override, $campusCode);
        if (!is_array($cps_info) || empty($cps_info['formatted'])) {
            throw new RuntimeException('cps_assign');
        }
        cps_mark_pending_approved($conn, $user_id);
        $program = trim((string) ($user_data['program'] ?? ''));
        $industry = trim((string) ($user_data['industry'] ?? ''));
        $pos = trim((string) ($user_data['position'] ?? ''));
        $comp = trim((string) ($user_data['company'] ?? ''));
        if ($program !== '' && ($industry !== '' || $pos !== '' || $comp !== '')) {
            cps_work_history_sync_from_profile($conn, $user_id, $program, $industry, $pos, $comp, false);
        }

        if (!$conn->commit()) {
            throw new RuntimeException('commit');
        }
        $update_stmt->close();
    } catch (Throwable $e) {
        $conn->rollback();
        if (isset($update_stmt) && $update_stmt instanceof mysqli_stmt) {
            @$update_stmt->close();
        }
        $user_stmt->close();
        $conn->close();
        ob_end_clean();
        $msg = $e->getMessage();
        error_log('ad_approve_workflow approve transaction: ' . $msg . ' @' . $e->getFile() . ':' . $e->getLine());
        if ($msg === 'verify_status') {
            header('Location: ad_user_management.php?error=update');
        } elseif ($msg === 'cps_assign' || $msg === 'legacy_id_missing') {
            header('Location: ad_user_management.php?error=cps');
        } else {
            header('Location: ad_user_management.php?error=update');
        }
        exit();
    }

    $cps_block = '';
    if (!empty($cps_info['formatted'])) {
        $vf = htmlspecialchars((string) ($cps_info['validity_date'] ?? ''), ENT_QUOTES, 'UTF-8');
        $cf = htmlspecialchars((string) $cps_info['formatted'], ENT_QUOTES, 'UTF-8');
        $cps_block = "<div style='background:#f0fdf4;border-left:4px solid #2c5530;padding:16px;margin:16px 0;border-radius:8px;'><p style='margin:0 0 8px 0;font-size:15px;color:#14532d;'><strong>CPS Alumni ID:</strong> {$cf}</p><p style='margin:0;font-size:14px;color:#374151;'><strong>Card validity (3 years):</strong> {$vf}</p></div>";
    }

    $email_sent = false;
    try {
        $email_sent = ad_approve_send_notification_email(
            true,
            $user_data,
            $user_id,
            $reason,
            $cps_block,
            $loginUrl,
            $idCardUrl,
            $email_function_available,
            $phpmailer_available,
            $conn
        );
    } catch (Throwable $e) {
        error_log('ad_approve_workflow: approve email: ' . $e->getMessage());
    }

    $user_stmt->close();
    $conn->close();
    ob_end_clean();

    $cpsId = rawurlencode((string) $cps_info['formatted']);
    if ($email_sent) {
        header('Location: ad_user_management.php?success=1&email_sent=1&cps_id=' . $cpsId . '&status=active');
    } else {
        header('Location: ad_user_management.php?success=1&email_sent=0&warn=email&cps_id=' . $cpsId . '&status=active');
    }
    exit();
}
