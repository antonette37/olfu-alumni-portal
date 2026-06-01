<?php
/** Copy to db_config.php and set production credentials. */
// Database configuration
// Enhanced configuration with better error handling

$_rb = __DIR__ . '/includes/runtime_bootstrap.php';
if (is_file($_rb)) {
    require_once $_rb;
}
unset($_rb);

// Alumni email OTP + 30-day trusted device cookie (set true only to disable OTP during local testing).
if (!defined('SKIP_ALUMNI_OTP_GATE')) {
    define('SKIP_ALUMNI_OTP_GATE', false);
}
// Force re-login for all currently active alumni sessions (Unix timestamp).
if (!defined('FORCE_ALUMNI_RELOGIN_AT')) {
    define('FORCE_ALUMNI_RELOGIN_AT', 1776013200);
}

// Check if we're running locally or on production (browser sets HTTP_HOST; CLI usually does not).
$httpHost = isset($_SERVER['HTTP_HOST']) ? trim((string) $_SERVER['HTTP_HOST']) : '';
$isLocal = (
    $httpHost === ''
    || strcasecmp($httpHost, 'localhost') === 0
    || strcasecmp($httpHost, '127.0.0.1') === 0
    || stripos($httpHost, 'localhost') !== false
    || stripos($httpHost, 'xampp') !== false
    || stripos($httpHost, '127.0.0.1') !== false
);

if ($isLocal) {
    // Local XAMPP configuration
    $DB_HOST = 'localhost';
    $DB_USER = 'root';
    $DB_PASS = '';  // Empty password for XAMPP
    $DB_NAME = 'itcp_db';
    $DB_PORT = 3306;
    
    // Debug: Show we're using local config
    error_log("Using LOCAL database config: " . $DB_HOST . " / " . $DB_USER);
} else {
    // Production Hostinger configuration
    $DB_HOST = 'localhost'; // Try 'mysql.hostinger.com' if localhost fails
    $DB_USER = 'your_hostinger_db_user'; // Your Hostinger database username
    $DB_PASS = 'your_hostinger_db_password'; // Your Hostinger database password
    $DB_NAME = 'your_hostinger_db_name'; // Your Hostinger database name
    $DB_PORT = 3306;
    
    // Debug: Show we're using production config
    error_log("Using PRODUCTION database config: " . $DB_HOST . " / " . $DB_USER);
}

// Create connection
function getDBConnection() {
    // Use specifically-prefixed variables to avoid collisions with application-level $user arrays
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT;

    if (function_exists('mysqli_report')) {
        @mysqli_report(MYSQLI_REPORT_OFF);
    }

    $hostsToTry = ['127.0.0.1', $DB_HOST];
    $portsToTry = [$DB_PORT ?: 3306, 3306];
    $socketsToTry = [null];
    $lastError = '';

    foreach ($hostsToTry as $h) {
        foreach ($portsToTry as $p) {
            foreach ($socketsToTry as $s) {
                $conn = mysqli_init();
                mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
                mysqli_options($conn, MYSQLI_OPT_READ_TIMEOUT, 5);
                if (@mysqli_real_connect($conn, $h, $DB_USER, $DB_PASS, $DB_NAME, $p, $s)) {
                    @$conn->query("SET SESSION net_read_timeout = 5, net_write_timeout = 5");
                    return $conn;
                } else {
                    $lastError = mysqli_connect_error();
                }
            }
        }
    }

    die("Connection failed: " . $lastError);
}

/**
 * Call after session_start() and require_once db_config — before any HTML output.
 * Redirects logged-in alumni to email OTP when the 30-day device cookie is missing/invalid.
 */
if (!function_exists('alumni_otp_gate_after_session')) {
    function alumni_otp_gate_after_session()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        if (empty($_SESSION['user_id']) || !empty($_SESSION['admin_logged_in'])) {
            return;
        }
        if (defined('SKIP_ALUMNI_OTP_GATE') && constant('SKIP_ALUMNI_OTP_GATE')) {
            return;
        }
        /* Edit Profile AJAX fragment: session already verified on full page load. */
        if (
            !empty($_SESSION['user_id'])
            && isset($_GET['render_form'])
            && (string) $_GET['render_form'] === '1'
            && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) {
            return;
        }
        $authAt = (int)($_SESSION['auth_at'] ?? 0);
        if (defined('FORCE_ALUMNI_RELOGIN_AT') && $authAt < (int)constant('FORCE_ALUMNI_RELOGIN_AT')) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
                session_destroy();
            }
            header('Location: al_login.php');
            exit;
        }
        $conn = null;
        try {
            $script = basename($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
            require_once __DIR__ . '/includes/otp_device_lib.php';
            if (!function_exists('otp_device_should_skip_redirect') || otp_device_should_skip_redirect($script)) {
                return;
            }

            $conn = getDBConnection();
            otp_device_ensure_schema($conn);
            if (!otp_device_needs_verification($conn, (int)$_SESSION['user_id'])) {
                return;
            }
            $uri = $_SERVER['REQUEST_URI'] ?? 'al_homepage.php';
            header('Location: al_otp_verify.php?redirect=' . rawurlencode($uri));
            exit;
        } catch (Throwable $e) {
            // Fail-open to avoid site-wide 500s if OTP subsystem has transient issues.
            error_log('OTP gate bypassed due to error: ' . $e->getMessage());
            return;
        }
        /* Do not close $conn here — pages open their own connection and may reuse mysqli on some hosts. */
    }
}

/*
 * Alumni ID card HTML: load ad_alumni_id_cards_snippet.php or includes/alumni_id_cards_embed.php (case variants).
 * If none are deployed, a stub render_alumni_id_cards() is defined so the site does not fatal.
 */
if (!function_exists('render_alumni_id_cards')) {
    /* Prefer embed first: snippet may exist without embed on some deploys; snippet no longer fatals if embed is missing. */
    $_ol_id_paths = [
        __DIR__ . '/includes/alumni_id_cards_embed.php',
        __DIR__ . '/Includes/alumni_id_cards_embed.php',
        __DIR__ . '/ad_alumni_id_cards_snippet.php',
    ];
    foreach ($_ol_id_paths as $_olp) {
        if (is_file($_olp)) {
            require_once $_olp;
            break;
        }
    }
    unset($_ol_id_paths, $_olp);
}
if (!function_exists('render_alumni_id_cards')) {
    $_ol_embed = __DIR__ . '/includes/alumni_id_cards_embed.php';
    if (!is_file($_ol_embed)) {
        $_ol_embed = __DIR__ . '/Includes/alumni_id_cards_embed.php';
    }
    if (is_file($_ol_embed)) {
        require_once $_ol_embed;
    }
}
if (!function_exists('render_alumni_id_cards')) {
    /**
     * Fallback when embed files are not deployed — avoids fatal require_once on production.
     * Upload includes/alumni_id_cards_embed.php (or ad_alumni_id_cards_snippet.php) for full ID card UI.
     */
    function render_alumni_id_cards($card = [])
    {
        echo '<p class="olfu-id-card-unavailable" style="padding:12px;border:1px solid #ddd;border-radius:8px;color:#555;font-size:14px;">'
            . 'Alumni ID card preview is unavailable. Please contact the administrator if this persists.</p>';
    }
}
if (isset($_ol_embed)) {
    unset($_ol_embed);
}