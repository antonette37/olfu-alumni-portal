<?php
// Email sending function using PHPMailer
// This file loads PHPMailer and provides a sendEmail function

// Start output buffering to catch any accidental output
$mail_ob_started = false;
if (!ob_get_level()) {
    ob_start();
    $mail_ob_started = true;
}

// Suppress warnings during file operations to prevent output
$old_error_reporting = error_reporting(0);
$old_display_errors = ini_get('display_errors');
ini_set('display_errors', 0);

// Try multiple paths to find PHPMailer files
$phpmailer_paths = [
    __DIR__ . '/phpmailer/src/Exception.php',
    __DIR__ . '/phpmailer/src/PHPMailer.php',
    __DIR__ . '/PHPMailer/src/Exception.php',
    __DIR__ . '/PHPMailer/src/PHPMailer.php',
    __DIR__ . DIRECTORY_SEPARATOR . 'phpmailer' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Exception.php',
    __DIR__ . DIRECTORY_SEPARATOR . 'PHPMailer' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Exception.php',
    dirname(__FILE__) . '/phpmailer/src/Exception.php',
    dirname(__FILE__) . '/PHPMailer/src/Exception.php',
    dirname(__FILE__) . DIRECTORY_SEPARATOR . 'phpmailer' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Exception.php',
    dirname(__FILE__) . DIRECTORY_SEPARATOR . 'PHPMailer' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Exception.php',
    'phpmailer/src/Exception.php',
    'PHPMailer/src/Exception.php',
    './phpmailer/src/Exception.php',
    './PHPMailer/src/Exception.php'
];

$phpmailer_loaded = false;
$phpmailer_base = null;
$GLOBALS['phpmailer_debug'] = [];

// First, find where Exception.php is located
foreach ($phpmailer_paths as $path) {
    $exists = @file_exists($path);
    $GLOBALS['phpmailer_debug'][] = "Checking: {$path} - " . ($exists ? "EXISTS" : "NOT FOUND");

    if ($exists) {
        $phpmailer_base = dirname($path);
        // Verify all three files exist in this directory
        $exception_file = $phpmailer_base . '/Exception.php';
        $phpmailer_file = $phpmailer_base . '/PHPMailer.php';
        $smtp_file = $phpmailer_base . '/SMTP.php';

        $GLOBALS['phpmailer_debug'][] = "Found Exception.php at: {$path}";
        $GLOBALS['phpmailer_debug'][] = "Checking Exception: " . ($exception_file) . " - " . (@file_exists($exception_file) ? "EXISTS" : "NOT FOUND");
        $GLOBALS['phpmailer_debug'][] = "Checking PHPMailer: " . ($phpmailer_file) . " - " . (@file_exists($phpmailer_file) ? "EXISTS" : "NOT FOUND");
        $GLOBALS['phpmailer_debug'][] = "Checking SMTP: " . ($smtp_file) . " - " . (@file_exists($smtp_file) ? "EXISTS" : "NOT FOUND");

        if (@file_exists($exception_file) && @file_exists($phpmailer_file) && @file_exists($smtp_file)) {
            try {
                require_once $exception_file;
                require_once $phpmailer_file;
                require_once $smtp_file;
                if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                    $phpmailer_loaded = true;
                    $GLOBALS['phpmailer_debug'][] = "SUCCESS: PHPMailer loaded from: {$phpmailer_base}";
                    break;
                }
                else {
                    $GLOBALS['phpmailer_debug'][] = "Files loaded but class PHPMailer\\PHPMailer\\PHPMailer not found";
                }
            }
            catch (Throwable $e) {
                $error_msg = "Error loading PHPMailer from " . $phpmailer_base . ": " . $e->getMessage();
                error_log($error_msg);
                $GLOBALS['phpmailer_debug'][] = $error_msg;
                $phpmailer_base = null;
                continue;
            }
        }
        else {
            $GLOBALS['phpmailer_debug'][] = "Not all required files found in: {$phpmailer_base}";
        }
    }
}

// If still not loaded, try to find phpmailer directory and check src subdirectory
if (!$phpmailer_loaded) {
    $GLOBALS['phpmailer_debug'][] = "Trying alternative base directory paths...";
    $possible_bases = [
        __DIR__ . '/phpmailer',
        __DIR__ . '/PHPMailer',
        __DIR__ . DIRECTORY_SEPARATOR . 'phpmailer',
        __DIR__ . DIRECTORY_SEPARATOR . 'PHPMailer',
        dirname(__FILE__) . '/phpmailer',
        dirname(__FILE__) . '/PHPMailer',
        dirname(__FILE__) . DIRECTORY_SEPARATOR . 'phpmailer',
        dirname(__FILE__) . DIRECTORY_SEPARATOR . 'PHPMailer',
        'phpmailer',
        'PHPMailer',
        './phpmailer',
        './PHPMailer'
    ];

    foreach ($possible_bases as $base) {
        $exception_file = $base . '/src/Exception.php';
        $phpmailer_file = $base . '/src/PHPMailer.php';
        $smtp_file = $base . '/src/SMTP.php';

        $GLOBALS['phpmailer_debug'][] = "Checking base: {$base}";
        $GLOBALS['phpmailer_debug'][] = "  Exception: " . (@file_exists($exception_file) ? "EXISTS" : "NOT FOUND");
        $GLOBALS['phpmailer_debug'][] = "  PHPMailer: " . (@file_exists($phpmailer_file) ? "EXISTS" : "NOT FOUND");
        $GLOBALS['phpmailer_debug'][] = "  SMTP: " . (@file_exists($smtp_file) ? "EXISTS" : "NOT FOUND");

        if (@file_exists($exception_file) && @file_exists($phpmailer_file) && @file_exists($smtp_file)) {
            try {
                require_once $exception_file;
                require_once $phpmailer_file;
                require_once $smtp_file;
                if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                    $phpmailer_loaded = true;
                    $phpmailer_base = $base . '/src';
                    $GLOBALS['phpmailer_debug'][] = "SUCCESS: PHPMailer loaded from base: {$base}";
                    break;
                }
                else {
                    $GLOBALS['phpmailer_debug'][] = "Files loaded from {$base} but class not found";
                }
            }
            catch (Throwable $e) {
                $error_msg = "Error loading PHPMailer from " . $base . ": " . $e->getMessage();
                error_log($error_msg);
                $GLOBALS['phpmailer_debug'][] = $error_msg;
                continue;
            }
        }
    }
}

// As final fallback, try Composer autoload
if (!$phpmailer_loaded) {
    $GLOBALS['phpmailer_debug'][] = "Trying Composer autoload paths...";
    $autoload_paths = [
        __DIR__ . '/vendor/autoload.php',
        dirname(__FILE__) . '/vendor/autoload.php',
        __DIR__ . '/../vendor/autoload.php'
    ];

    foreach ($autoload_paths as $autoload) {
        $exists = @file_exists($autoload);
        $GLOBALS['phpmailer_debug'][] = "Checking autoload: {$autoload} - " . ($exists ? "EXISTS" : "NOT FOUND");
        if ($exists) {
            try {
                require_once $autoload;
                if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                    $phpmailer_loaded = true;
                    $GLOBALS['phpmailer_debug'][] = "SUCCESS: PHPMailer loaded via Composer autoload: {$autoload}";
                    break;
                }
                else {
                    $GLOBALS['phpmailer_debug'][] = "Autoload loaded but class not found";
                }
            }
            catch (Throwable $e) {
                $error_msg = "Error loading Composer autoload (" . $autoload . "): " . $e->getMessage();
                error_log($error_msg);
                $GLOBALS['phpmailer_debug'][] = $error_msg;
            }
        }
    }
}

if (!$phpmailer_loaded) {
    $GLOBALS['phpmailer_debug'][] = "FAILED: PHPMailer could not be loaded from any path";
}

// Restore error reporting before loading config
error_reporting($old_error_reporting);
ini_set('display_errors', $old_display_errors);

// Try multiple paths for email_config.php
$config_paths = [
    __DIR__ . '/email_config.php',
    __DIR__ . DIRECTORY_SEPARATOR . 'email_config.php',
    'email_config.php',
    './email_config.php',
    dirname(__FILE__) . '/email_config.php'
];

$config_loaded = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        try {
            require_once $path;
            $config_loaded = true;
            break;
        }
        catch (Throwable $e) {
            error_log("Error loading email_config.php from " . $path . ": " . $e->getMessage());
            continue;
        }
    }
}

// Define defaults if config file not found
if (!$config_loaded) {
    if (!defined('SMTP_HOST'))
        define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.gmail.com');
    if (!defined('SMTP_PORT'))
        define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
    if (!defined('SMTP_USERNAME'))
        define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: '');
    if (!defined('SMTP_PASSWORD'))
        define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: '');
    if (!defined('SMTP_FROM_EMAIL'))
        define('SMTP_FROM_EMAIL', getenv('SMTP_FROM_EMAIL') ?: '');
    if (!defined('SMTP_FROM_NAME'))
        define('SMTP_FROM_NAME', getenv('SMTP_FROM_NAME') ?: 'OLFU ALUMNI AFFAIRS');
    if (!defined('ENVIRONMENT'))
        define('ENVIRONMENT', 'production');
    if (!defined('SMTP_AUTO_TLS'))
        define('SMTP_AUTO_TLS', true);
}

/**
 * Send HTML email via PHPMailer.
 *
 * @param array<int, array{path: string, name?: string}|string> $attachments Local file paths; array items may be
 *                                                                               ['path' => '/abs/file', 'name' => 'shown-name.pdf'] or a path string (basename used as name).
 */
function sendEmail($recipient_email, $subject, $body, array $attachments = [])
{
    $logPath = __DIR__ . DIRECTORY_SEPARATOR . 'logs';
    $logFile = $logPath . DIRECTORY_SEPARATOR . 'email_debug.log';

    $logToFile = function ($message) use ($logPath, $logFile) {
        try {
            if (!is_dir($logPath)) {
                @mkdir($logPath, 0755, true);
            }
            $date = date('Y-m-d H:i:s');
            @file_put_contents($logFile, "[{$date}] {$message}\n", FILE_APPEND);
        }
        catch (\Throwable $e) {
            error_log("sendEmail logging failure: " . $e->getMessage());
        }
    };

    $log = function ($message) use ($logToFile, $recipient_email) {
        $formatted = "sendEmail: {$message}";
        error_log($formatted);
        $logToFile($formatted . " (recipient: {$recipient_email})");
    };

    $setLastError = function ($message = null) {
        if ($message === null) {
            unset($GLOBALS['send_email_last_error']);
        }
        else {
            $GLOBALS['send_email_last_error'] = $message;
        }
    };

    $setLastError();

    if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
        $log("PHPMailer class not found. Email skipped.");
        $setLastError('PHPMailer PHP class not found on server.');
        return false;
    }

    $host = defined('SMTP_HOST') ? SMTP_HOST : getenv('SMTP_HOST') ?: 'smtp.gmail.com';
    $port = defined('SMTP_PORT') ? SMTP_PORT : (getenv('SMTP_PORT') ?: 587);
    $username = defined('SMTP_USERNAME') ? SMTP_USERNAME : getenv('SMTP_USERNAME');
    $password = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : getenv('SMTP_PASSWORD');
    $from_email = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : getenv('SMTP_FROM_EMAIL');
    $from_name = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : (getenv('SMTP_FROM_NAME') ?: 'OLFU ALUMNI AFFAIRS');
    $timeout = defined('SMTP_TIMEOUT') ? SMTP_TIMEOUT : 30;
    $env = defined('ENVIRONMENT') ? ENVIRONMENT : 'production';
    $log("Preparing email. Host={$host}, Port={$port}, Username={$username}");

    $entity_flags = ENT_QUOTES;
    if (defined('ENT_HTML5')) {
        $entity_flags |= ENT_HTML5;
    }
    $plain_text = html_entity_decode(strip_tags($body), $entity_flags, 'UTF-8');

    $primary_secure = ($port == 465) ?\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $secondary_secure = ($primary_secure === \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS)
        ?\PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
        : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    $secondary_port = ($primary_secure === \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS) ? 465 : 587;

    $configureMessage = function (\PHPMailer\PHPMailer\PHPMailer $mail) use ($recipient_email, $subject, $body, $plain_text, $from_email, $from_name, $attachments) {
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($from_email, $from_name);
        $mail->addAddress($recipient_email);
        $mail->addReplyTo($from_email, $from_name);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = $plain_text;
        foreach ($attachments as $att) {
            $path = is_array($att) ? (string) ($att['path'] ?? '') : (string) $att;
            if ($path === '' || !is_file($path) || !is_readable($path)) {
                continue;
            }
            $name = is_array($att) ? ($att['name'] ?? '') : '';
            if ($name === '') {
                $name = basename($path);
            }
            $mail->addAttachment($path, (string) $name);
        }
    };

    $configureDebug = function (\PHPMailer\PHPMailer\PHPMailer $mail, $transport) use ($env) {
        if ($env === 'development') {
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = function ($str, $level) use ($transport) {
                        error_log("PHPMailer Debug ({$transport}): {$str}");
                    }
                        ;
                }
                else {
                    $mail->SMTPDebug = 0;
                }
            };

    $attempts = [];

    $attempts[] = function () use ($host, $port, $username, $password, $timeout, $primary_secure) {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->SMTPSecure = $primary_secure;
        $mail->Port = $port;
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        $mail->Timeout = $timeout;
        $mail->SMTPKeepAlive = false;
        if (defined('SMTP_AUTO_TLS')) {
            $mail->SMTPAutoTLS = SMTP_AUTO_TLS ? true : false;
        }
        return $mail;
    };

    if ($secondary_port !== $port) {
        $attempts[] = function () use ($host, $secondary_port, $username, $password, $timeout, $secondary_secure) {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->SMTPSecure = $secondary_secure;
            $mail->Port = $secondary_port;
            $mail->SMTPOptions = array(
                'ssl' => array(
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                )
            );
            $mail->Timeout = $timeout;
            $mail->SMTPKeepAlive = false;
            if (defined('SMTP_AUTO_TLS')) {
                $mail->SMTPAutoTLS = SMTP_AUTO_TLS ? true : false;
            }
            return $mail;
        };
    }

    $attempts[] = function () {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isMail();
        return $mail;
    };

    foreach ($attempts as $index => $factory) {
        $transport = $index === 0
            ? 'smtp_primary'
            : ($index === 1 && count($attempts) === 3 ? 'smtp_fallback' : ($index === count($attempts) - 1 ? 'php_mail' : 'smtp_fallback'));

        try {
            $mail = $factory();
            $configureDebug($mail, $transport);
            $configureMessage($mail);
            $log("Attempting {$transport} transport.");
            $mail->send();
            $log("{$transport} delivery succeeded.");
            return true;
        }
        catch (\PHPMailer\PHPMailer\Exception $e) {
            $error_info = isset($mail) && $mail ? ($mail->ErrorInfo ?: $e->getMessage()) : $e->getMessage();
            $log("{$transport} transport failed. Error: {$error_info}");
            $setLastError($error_info);
        }
        catch (\Exception $e) {
            $log("{$transport} transport threw exception. Error: " . $e->getMessage());
            $setLastError($e->getMessage());
        }
        catch (\Throwable $e) {
            $log("{$transport} transport threw throwable. Error: " . $e->getMessage());
            $setLastError($e->getMessage());
        }
    }

    $log("All transports failed.");
    if (!isset($GLOBALS['send_email_last_error'])) {
        $setLastError('Unknown email transport failure');
    }
    return false;
}

if (!function_exists('getEmailLastError')) {
    function getEmailLastError()
    {
        return isset($GLOBALS['send_email_last_error']) ? $GLOBALS['send_email_last_error'] : null;
    }
}

// Clean any output buffer that might have been created
if ($mail_ob_started && ob_get_level() > 0) {
    ob_end_clean();
}

?>
