<?php
/**
 * Email OTP + 30-day device cookie for alumni re-verification (monthly / new device).
 */
declare(strict_types=1);

$_olfu_mc = __DIR__ . '/mysqli_compat.php';
if (is_file($_olfu_mc)) {
    require_once $_olfu_mc;
}
unset($_olfu_mc);

function otp_device_cookie_name(): string
{
    return 'olfu_dev_ver';
}

function otp_device_cookie_lifetime_seconds(): int
{
    return 86400 * 30;
}

function otp_device_otp_ttl_seconds(): int
{
    return 900;
}

function otp_device_ensure_schema(mysqli $conn): void
{
    // No FOREIGN KEY constraints: production DBs may use MyISAM for itcp or reject FK creation,
    // which throws mysqli_sql_exception on PHP 8+ and breaks the OTP page.
    if (function_exists('mysqli_report')) {
        @mysqli_report(MYSQLI_REPORT_OFF);
    }
    @$conn->query("
        CREATE TABLE IF NOT EXISTS email_otp_challenges (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            otp_hash VARCHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            consumed TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_user_exp (user_id, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    @$conn->query("
        CREATE TABLE IF NOT EXISTS device_verification_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_lookup (user_id, token_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function otp_device_parse_cookie(?string $raw): ?array
{
    if ($raw === null || $raw === '') {
        return null;
    }
    $parts = explode(':', $raw, 2);
    if (count($parts) !== 2) {
        return null;
    }
    $id = (int)$parts[0];
    $hex = $parts[1];
    if ($id < 1 || strlen($hex) < 32) {
        return null;
    }
    if (!ctype_xdigit($hex)) {
        return null;
    }
    return ['id' => $id, 'hex' => $hex];
}

function otp_device_cookie_is_valid(mysqli $conn, int $userId): bool
{
    $parsed = otp_device_parse_cookie($_COOKIE[otp_device_cookie_name()] ?? null);
    if ($parsed === null) {
        return false;
    }
    $bin = @hex2bin($parsed['hex']);
    if ($bin === false || strlen($bin) < 16) {
        return false;
    }
    $hash = hash('sha256', $bin);
    $st = $conn->prepare('SELECT id FROM device_verification_tokens WHERE id = ? AND user_id = ? AND token_hash = ? AND expires_at > NOW() LIMIT 1');
    if (!$st) {
        return false;
    }
    $tid = $parsed['id'];
    $st->bind_param('iis', $tid, $userId, $hash);
    $st->execute();
    $gr = olfu_stmt_get_result($st);
    $ok = (bool)($gr && $gr->fetch_assoc());
    $st->close();
    return $ok;
}

/**
 * Alumni emails that skip the 30-day device / email OTP step (e.g. demo or service accounts).
 * Compare case-insensitively to itcp.email.
 */
function otp_device_otp_exempt_emails(): array
{
    return array_map('strtolower', array_map('trim', [
        'olfualumni@gmail.com',
    ]));
}

function otp_device_is_otp_exempt(mysqli $conn, int $userId): bool
{
    if ($userId < 1) {
        return false;
    }
    $allowed = otp_device_otp_exempt_emails();
    if ($allowed === []) {
        return false;
    }
    $st = $conn->prepare('SELECT LOWER(TRIM(email)) AS e FROM itcp WHERE id = ? LIMIT 1');
    if (!$st) {
        return false;
    }
    $st->bind_param('i', $userId);
    $st->execute();
    $gr = olfu_stmt_get_result($st);
    $row = $gr ? $gr->fetch_assoc() : null;
    $st->close();
    if (!$row || empty($row['e'])) {
        return false;
    }
    return in_array((string)$row['e'], $allowed, true);
}

function otp_device_needs_verification(mysqli $conn, int $userId): bool
{
    if (otp_device_is_otp_exempt($conn, $userId)) {
        return false;
    }
    return !otp_device_cookie_is_valid($conn, $userId);
}

function otp_device_set_cookie(int $tokenRowId, string $rawHex): void
{
    $val = $tokenRowId . ':' . $rawHex;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $params = session_get_cookie_params();
    $path = $params['path'] ?: '/';
    $domain = $params['domain'] ?: '';
    setcookie(otp_device_cookie_name(), $val, time() + otp_device_cookie_lifetime_seconds(), $path, $domain, $secure, true);
}

function otp_device_clear_cookie(): void
{
    $params = session_get_cookie_params();
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $path = $params['path'] ?: '/';
    $domain = $params['domain'] ?: '';
    setcookie(otp_device_cookie_name(), '', time() - 3600, $path, $domain, $secure, true);
}

function otp_device_register_new_device(mysqli $conn, int $userId): bool
{
    $raw = random_bytes(32);
    $hex = bin2hex($raw);
    $hash = hash('sha256', $raw);
    $exp = (new DateTimeImmutable('now'))->modify('+' . otp_device_cookie_lifetime_seconds() . ' seconds')->format('Y-m-d H:i:s');
    $ins = $conn->prepare('INSERT INTO device_verification_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
    if (!$ins) {
        return false;
    }
    $ins->bind_param('iss', $userId, $hash, $exp);
    if (!$ins->execute()) {
        $ins->close();
        return false;
    }
    $newId = (int)$conn->insert_id;
    $ins->close();
    otp_device_set_cookie($newId, $hex);
    return true;
}

/**
 * Load project-root mail.php (PHPMailer + sendEmail) the same way admin approval does:
 * buffered require_once so stray output cannot break OTP page headers.
 */
function otp_device_require_send_email(): bool
{
    if (function_exists('sendEmail')) {
        return true;
    }
    $mailPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'mail.php';
    if (!is_readable($mailPath)) {
        error_log('OTP: mail.php not readable at ' . $mailPath);
        return false;
    }

    $prevEr = error_reporting(0);
    $prevDisp = ini_get('display_errors');
    ini_set('display_errors', '0');
    ob_start();
    try {
        require_once $mailPath;
    } catch (Throwable $e) {
        error_log('OTP: mail.php load error: ' . $e->getMessage());
    } finally {
        error_reporting($prevEr);
        ini_set('display_errors', $prevDisp !== false ? $prevDisp : '0');
        if (ob_get_level() > 0) {
            $buf = ob_get_clean();
            if ($buf !== false && trim((string)$buf) !== '') {
                error_log('OTP: mail.php produced output (' . strlen((string)$buf) . ' bytes)');
            }
        }
    }

    if (!function_exists('sendEmail')) {
        error_log('OTP: sendEmail() not defined after loading mail.php');
        return false;
    }
    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        error_log('OTP: PHPMailer class missing after loading mail.php');
        return false;
    }
    return true;
}

/**
 * Alumni login / profile email (itcp.email) — OTP is always sent here.
 */
function otp_device_alumni_personal_email(mysqli $conn, int $userId)
{
    if ($userId < 1) {
        return null;
    }
    $st = $conn->prepare('SELECT TRIM(email) AS e FROM itcp WHERE id = ? LIMIT 1');
    if (!$st) {
        return null;
    }
    $st->bind_param('i', $userId);
    $st->execute();
    $gr = olfu_stmt_get_result($st);
    $row = $gr ? $gr->fetch_assoc() : null;
    $st->close();
    if (!$row || $row['e'] === null || $row['e'] === '') {
        return null;
    }
    $e = trim((string)$row['e']);
    if ($e === '' || !filter_var($e, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    return $e;
}

function otp_device_mask_email($email): string
{
    $email = trim((string)$email);
    if ($email === '' || strpos($email, '@') === false) {
        return 'your registered email';
    }
    $parts = explode('@', $email, 2);
    $local = isset($parts[0]) ? (string)$parts[0] : '';
    $domain = isset($parts[1]) ? (string)$parts[1] : '';
    if ($local === '') {
        return 'your registered email';
    }
    $show = strlen($local) <= 2 ? substr($local, 0, 1) : substr($local, 0, 2);

    return $show . '***@' . $domain;
}

function otp_device_send_challenge_email(mysqli $conn, int $userId): array
{
    $st = $conn->prepare('SELECT TRIM(email) AS email, firstname, lastname FROM itcp WHERE id = ? LIMIT 1');
    if (!$st) {
        return ['ok' => false, 'message' => 'Database error.'];
    }
    $st->bind_param('i', $userId);
    $st->execute();
    $gr = olfu_stmt_get_result($st);
    $u = $gr ? $gr->fetch_assoc() : null;
    $st->close();
    if (!$u) {
        return ['ok' => false, 'message' => 'Account not found.'];
    }
    $to = trim((string)($u['email'] ?? ''));
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'No valid personal email on file. Update your email in Profile settings or contact support.'];
    }

    $code = (string)(function_exists('random_int') ? random_int(100000, 999999) : mt_rand(100000, 999999));
    $hash = hash('sha256', $code . '|' . (string)$userId);
    $exp = (new DateTimeImmutable('now'))->modify('+' . otp_device_otp_ttl_seconds() . ' seconds')->format('Y-m-d H:i:s');
    $name = trim(($u['firstname'] ?? '') . ' ' . ($u['lastname'] ?? ''));
    $subject = 'Your verification code — OLFU Alumni Portal';
    $body = '<p>Hi ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</p>'
        . '<p>Your one-time verification code is:</p>'
        . '<p style="font-size:24px;font-weight:bold;letter-spacing:4px;">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</p>'
        . '<p>This code expires in ' . (otp_device_otp_ttl_seconds() / 60) . ' minutes. If you did not try to sign in, you can ignore this email.</p>'
        . '<p>— OLFU Alumni Affairs</p>';

    if (!otp_device_require_send_email()) {
        return ['ok' => false, 'message' => 'Mail system is not available. Please contact support.'];
    }

    $sent = (bool)sendEmail($to, $subject, $body);
    if (!$sent) {
        $detail = function_exists('getEmailLastError') ? getEmailLastError() : null;
        error_log('OTP email failed for user ' . $userId . ($detail ? (': ' . $detail) : ''));
        return ['ok' => false, 'message' => 'Email could not be sent. Check SMTP settings in email_config.php or logs/email_debug.log.'];
    }

    $inv = $conn->prepare('UPDATE email_otp_challenges SET consumed = 1 WHERE user_id = ? AND consumed = 0');
    if ($inv) {
        $inv->bind_param('i', $userId);
        $inv->execute();
        $inv->close();
    }
    $ins = $conn->prepare('INSERT INTO email_otp_challenges (user_id, otp_hash, expires_at) VALUES (?, ?, ?)');
    if (!$ins) {
        return ['ok' => false, 'message' => 'Email was sent but OTP could not be saved. Request a new code.'];
    }
    $ins->bind_param('iss', $userId, $hash, $exp);
    if (!$ins->execute()) {
        $ins->close();
        return ['ok' => false, 'message' => 'Email was sent but OTP could not be saved. Request a new code.'];
    }
    $ins->close();

    return ['ok' => true, 'message' => 'Verification code sent to your email.'];
}

function otp_device_verify_code(mysqli $conn, int $userId, string $code): bool
{
    $code = preg_replace('/\D/', '', $code) ?? '';
    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }
    $hash = hash('sha256', $code . '|' . (string)$userId);
    $st = $conn->prepare('SELECT id FROM email_otp_challenges WHERE user_id = ? AND otp_hash = ? AND consumed = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
    if (!$st) {
        return false;
    }
    $st->bind_param('is', $userId, $hash);
    $st->execute();
    $gr = olfu_stmt_get_result($st);
    $row = $gr ? $gr->fetch_assoc() : null;
    $st->close();
    if (!$row) {
        return false;
    }
    $cid = (int)$row['id'];
    $up = $conn->prepare('UPDATE email_otp_challenges SET consumed = 1 WHERE id = ?');
    if ($up) {
        $up->bind_param('i', $cid);
        $up->execute();
        $up->close();
    }
    return otp_device_register_new_device($conn, $userId);
}

function otp_device_should_skip_redirect(string $basename): bool
{
    $skip = [
        'al_otp_verify.php',
        'al_login.php',
        'al_logout.php',
        'al_registration.php',
        'al_forgot_password.php',
        'al_reset_password.php',
    ];
    return in_array($basename, $skip, true);
}
