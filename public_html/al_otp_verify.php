<?php
/**
 * Email OTP step after password login — sets 30-day device cookie on success.
 */
declare(strict_types=1);
session_start();

require_once __DIR__ . '/db_config.php';
if (is_file(__DIR__ . '/includes/mysqli_compat.php')) {
    require_once __DIR__ . '/includes/mysqli_compat.php';
}
if (function_exists('mysqli_report')) {
    @mysqli_report(MYSQLI_REPORT_OFF);
}
require_once __DIR__ . '/includes/otp_device_lib.php';

function otp_safe_redirect(string $candidate, string $default = 'al_homepage.php'): string
{
    $candidate = trim($candidate);
    if ($candidate === '' || preg_match('/^([a-zA-Z]+:)?\/\//', $candidate) || strpos($candidate, '..') !== false) {
        return $default;
    }
    return $candidate;
}

$error = '';
$info = '';
$maskedRecipient = 'your registered email';
$redirect = otp_safe_redirect($_GET['redirect'] ?? $_POST['redirect'] ?? 'al_homepage.php');
$conn = null;

try {
    $conn = getDBConnection();
    otp_device_ensure_schema($conn);

    if (empty($_SESSION['user_id'])) {
        header('Location: al_login.php?redirect=' . urlencode('al_otp_verify.php?redirect=' . urlencode($redirect)));
        exit;
    }

    $userId = (int)$_SESSION['user_id'];

    $forceOtpAfterLogin = !empty($_SESSION['otp_required_after_login']);
    if (!$forceOtpAfterLogin && !otp_device_needs_verification($conn, $userId)) {
        header('Location: ' . $redirect);
        exit;
    }

    if (function_exists('otp_device_alumni_personal_email')) {
        $__pe = otp_device_alumni_personal_email($conn, $userId);
        if ($__pe !== null && $__pe !== '') {
            $maskedRecipient = function_exists('otp_device_mask_email') ? otp_device_mask_email($__pe) : $__pe;
        }
        unset($__pe);
    }

    if (empty($_SESSION['otp_csrf'])) {
        $_SESSION['otp_csrf'] = bin2hex(random_bytes(32));
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrf = $_POST['csrf'] ?? '';
        if (!hash_equals((string)$_SESSION['otp_csrf'], (string)$csrf)) {
            $error = 'Invalid session. Please refresh the page.';
        } elseif (!empty($_POST['resend'])) {
            $last = (int)($_SESSION['otp_last_sent'] ?? 0);
            if (time() - $last < 60) {
                $error = 'Please wait before requesting another code.';
            } else {
                $r = otp_device_send_challenge_email($conn, $userId);
                $_SESSION['otp_last_sent'] = time();
                if ($r['ok']) {
                    $info = $r['message'];
                } else {
                    $error = $r['message'];
                }
            }
        } else {
            $code = trim((string)($_POST['otp_code'] ?? ''));
            if (otp_device_verify_code($conn, $userId, $code)) {
                unset($_SESSION['otp_auto_sent_once'], $_SESSION['otp_last_sent'], $_SESSION['otp_csrf'], $_SESSION['otp_required_after_login']);
                header('Location: ' . $redirect);
                exit;
            }
            $error = 'Invalid or expired code. Try again.';
        }
    } else {
        if (empty($_SESSION['otp_auto_sent_once'])) {
            $r = otp_device_send_challenge_email($conn, $userId);
            $_SESSION['otp_last_sent'] = time();
            if ($r['ok']) {
                $_SESSION['otp_auto_sent_once'] = true;
                $info = $r['message'];
            } else {
                $error = $r['message'];
            }
        }
    }
} catch (Throwable $e) {
    error_log('OTP verify fatal: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $error = 'Unable to load OTP verification right now. Please try again.';
} finally {
    if ($conn instanceof mysqli) {
        $conn->close();
    }
}
if (empty($_SESSION['otp_csrf']) && session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION['otp_csrf'] = bin2hex(random_bytes(32));
}
$csrfVal = (string)($_SESSION['otp_csrf'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify your email — OLFU Alumni</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; font-family: 'Outfit', system-ui, sans-serif; background: linear-gradient(145deg, #0d2e18 0%, #1b5e35 100%); color: #fff; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.15); border-radius: 16px; padding: 32px; max-width: 420px; width: 100%; backdrop-filter: blur(8px); }
        h1 { font-size: 1.35rem; margin: 0 0 8px; font-weight: 600; }
        p { margin: 0 0 16px; opacity: .9; font-size: .95rem; line-height: 1.5; }
        input[type="text"] { width: 100%; padding: 14px 16px; border-radius: 10px; border: 1px solid rgba(255,255,255,.25); background: rgba(0,0,0,.2); color: #fff; font-size: 1.25rem; letter-spacing: .4em; text-align: center; }
        input::placeholder { color: rgba(255,255,255,.4); letter-spacing: normal; }
        .row { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 20px; }
        button, .linkbtn { flex: 1; min-width: 120px; padding: 12px 16px; border-radius: 10px; border: none; font-weight: 600; cursor: pointer; font-family: inherit; text-align: center; text-decoration: none; display: inline-block; }
        .primary { background: #34d399; color: #052e16; }
        .secondary { background: rgba(255,255,255,.12); color: #fff; border: 1px solid rgba(255,255,255,.25); }
        .err { background: rgba(220,38,38,.25); border: 1px solid rgba(248,113,113,.4); padding: 12px; border-radius: 8px; margin-bottom: 16px; font-size: .9rem; }
        .ok { background: rgba(16,185,129,.2); border: 1px solid rgba(52,211,153,.4); padding: 12px; border-radius: 8px; margin-bottom: 16px; font-size: .9rem; }
        .muted { font-size: .8rem; opacity: .75; margin-top: 16px; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); border: 0; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Check your email</h1>
        <p>We sent a 6-digit code to your personal email on file (<strong><?php echo htmlspecialchars($maskedRecipient, ENT_QUOTES, 'UTF-8'); ?></strong>). Enter it below to verify this device for 30 days.</p>
        <?php if ($error): ?><div class="err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
        <?php if ($info): ?><div class="ok"><?php echo htmlspecialchars($info); ?></div><?php endif; ?>
        <form method="post" action="">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($csrfVal, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
            <label class="sr-only" for="otp_code">Verification code</label>
            <input type="text" name="otp_code" id="otp_code" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" placeholder="000000" autofocus>
            <div class="row">
                <button type="submit" class="primary">Verify</button>
                <button type="submit" name="resend" value="1" class="secondary">Resend code</button>
            </div>
        </form>
        <p class="muted">Code expires in <?php echo (int)(otp_device_otp_ttl_seconds() / 60); ?> minutes. <a href="al_logout.php" style="color:#a7f3d0;">Sign out</a></p>
    </div>
</body>
</html>
