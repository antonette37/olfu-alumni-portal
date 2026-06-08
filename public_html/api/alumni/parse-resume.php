<?php
// Set execution time limit (60 seconds max)
set_time_limit(60);
ini_set('max_execution_time', 60);

// Remove any BOM or whitespace before PHP tag
// Start output buffering immediately
while (ob_get_level() > 0) {
    ob_end_clean();
}
ob_start();

// Suppress any warnings/notices that might cause output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('output_buffering', 4096);
ini_set('zlib.output_compression', 0);

// Log script start for debugging
error_log("parse_resume.php: Script started at " . date('Y-m-d H:i:s'));

if (!function_exists('send_json_response')) {
    function send_json_response(array $payload, $status = 200)
    {
        if (ob_get_length()) {
            ob_clean();
        }

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
        }

        $response = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($response === false) {
            $fallback = json_encode(['success' => false, 'error' => 'Failed to encode response data']);
            if (!headers_sent()) {
                header('Content-Length: ' . strlen($fallback));
            }
            echo $fallback ?: '{"success":false,"error":"Unknown encoding error"}';
        } else {
        if (!headers_sent()) {
            header('Content-Length: ' . strlen($response));
        }
        echo $response;
        
        // Flush output immediately
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        exit;
    }
}

// Set error handler to catch errors without outputting
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error in parse-resume.php ($errno): $errstr in $errfile on line $errline");
    return true; // Suppress default error handler
}, E_WARNING | E_NOTICE | E_DEPRECATED);

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $errorMsg = "Fatal error in parse-resume.php: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line'];
        error_log($errorMsg);
        
        // Try to send JSON response, but if that fails, send plain text
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => false,
            'error' => 'Internal server error. Please check server logs.',
            'debug' => 'Fatal error: ' . $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ]);
        exit;
    }
});

// Clean any existing buffer before processing
if (ob_get_length()) {
    ob_clean();
}

// Allow only POST (but allow GET for testing/debugging with ?test=1)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['test'])) {
    send_json_response(['success' => false, 'error' => 'Method not allowed. Use POST to upload a resume file.'], 405);
}

// Test endpoint - return basic info without processing
if (isset($_GET['test'])) {
    send_json_response([
        'success' => true,
        'message' => 'API endpoint is working',
        'php_version' => phpversion(),
        'extensions' => [
            'curl' => extension_loaded('curl'),
            'zip' => extension_loaded('zip'),
            'json' => extension_loaded('json')
        ],
        'file_path' => __FILE__,
        'file_exists' => file_exists(__FILE__),
        'request_method' => $_SERVER['REQUEST_METHOD']
    ]);
}

// Wrap main processing in try-catch for error handling
try {
    error_log("parse_resume.php: Starting file processing at " . date('Y-m-d H:i:s'));
    
// Basic upload presence check
if (!isset($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
    $errorMsg = 'No file uploaded or upload error.';
    if (isset($_FILES['resume']['error'])) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $errorMsg = $uploadErrors[$_FILES['resume']['error']] ?? $errorMsg;
    }
    send_json_response(['success' => false, 'error' => $errorMsg], 400);
}

// Validate file type and size
$allowedMime = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
$fileTmp = $_FILES['resume']['tmp_name'];

// Verify temp file exists and is readable
if (!file_exists($fileTmp) || !is_readable($fileTmp)) {
    send_json_response([
        'success' => false, 
        'error' => 'Uploaded file is not accessible. Please try uploading again.'
    ], 400);
}

$fileSize = (int)$_FILES['resume']['size'];

// Get MIME type with fallback
$mime = '';
if (function_exists('finfo_open')) {
    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo) {
        $mime = @finfo_file($finfo, $fileTmp);
        finfo_close($finfo);
    }
}
// Fallback to file extension if finfo fails
if (empty($mime)) {
    $ext = strtolower(pathinfo($_FILES['resume']['name'], PATHINFO_EXTENSION));
    $mimeMap = [
        'pdf' => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'doc' => 'application/msword'
    ];
    $mime = $mimeMap[$ext] ?? '';
}

if (!in_array($mime, $allowedMime)) {
    send_json_response(['success' => false, 'error' => 'Unsupported file format. Please upload PDF or DOCX.'], 400);
}

if ($fileSize <= 0 || $fileSize > 8 * 1024 * 1024) { // 8MB limit
    send_json_response(['success' => false, 'error' => 'File must be between 1 byte and 8MB.'], 400);
}

// Persist a copy of the uploaded resume into uploads/resumes
// Base path: project root two levels up from api/alumni
$rootPath = dirname(__DIR__, 2);
$resumeDir = $rootPath . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'resumes';

// Create directory with better error handling
if (!is_dir($resumeDir)) {
    $created = @mkdir($resumeDir, 0755, true);
    if (!$created && !is_dir($resumeDir)) {
        error_log("Failed to create resume directory: $resumeDir");
        send_json_response([
            'success' => false, 
            'error' => 'Failed to create upload directory. Please check server permissions.',
            'debug' => 'Directory: ' . $resumeDir
        ], 500);
    }
}

// Verify directory is writable
if (!is_writable($resumeDir)) {
    error_log("Resume directory is not writable: $resumeDir");
    send_json_response([
        'success' => false, 
        'error' => 'Upload directory is not writable. Please check server permissions.',
        'debug' => 'Directory: ' . $resumeDir
    ], 500);
}
$originalName = $_FILES['resume']['name'];
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$safeExt = in_array($ext, ['pdf','docx']) ? $ext : 'pdf';
$resumeName = 'resume_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $safeExt;
$resumePath = $resumeDir . DIRECTORY_SEPARATOR . $resumeName;
// Copy instead of move so we can still use temp file for parsing
// Copy uploaded file to persistent location
$copySuccess = @copy($fileTmp, $resumePath);
if (!$copySuccess || !file_exists($resumePath)) {
    error_log("Failed to copy uploaded file from $fileTmp to $resumePath");
    send_json_response([
        'success' => false, 
        'error' => 'Failed to save uploaded file. Please check server permissions.',
        'debug' => 'Source: ' . $fileTmp . ', Destination: ' . $resumePath
    ], 500);
}
// Web path relative to project root
$resumeWebPath = 'uploads/resumes/' . $resumeName;

// Hard-enforce the provided Affinda API key (ignore environment)
$apiKey = getenv('AFFINDA_API_KEY') ?: '';
if (empty($apiKey)) {
    send_json_response(['success' => false, 'error' => 'Affinda API key not configured'], 500);
}
$keySuffix = substr($apiKey, -6);

// Workspace (optional for v3). If not set, fall back to v2.
$workspace = getenv('AFFINDA_WORKSPACE');
if (!$workspace) { $workspace = 'chqdYWnF'; }
$useV3 = !empty($workspace);

// Track start time to prevent hanging
$processStartTime = microtime(true);
$maxProcessTime = 25; // Maximum 25 seconds total for entire process

// Call Affinda endpoint synchronously
$ch = curl_init();
if ($useV3) {
    curl_setopt($ch, CURLOPT_URL, 'https://api.affinda.com/v3/documents');
} else {
    curl_setopt($ch, CURLOPT_URL, 'https://api.affinda.com/v2/resumes');
}
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// Use shorter timeout to fail fast and use fallback
curl_setopt($ch, CURLOPT_TIMEOUT, 15); // 15 second timeout - fail fast
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // 5 second connection timeout
curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1); // Force HTTP/1.1 to avoid HTTP/2 issues
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
if ($useV3) {
    $postFields = [
        'file' => new CURLFile($fileTmp, $mime, $_FILES['resume']['name']),
        'wait' => 'true',
        'workspace' => $workspace
    ];
} else {
    $postFields = [
        'file' => new CURLFile($fileTmp, $mime, $_FILES['resume']['name'])
    ];
}
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
$curlErrno = curl_errno($ch);
curl_close($ch);

// If cURL failed or timed out, skip to fallback immediately
if ($response === false || $curlErrno !== 0) {
    error_log("Affinda cURL error ($curlErrno): " . $curlError);
    // Don't fail completely - continue to fallback extraction
    $affinda = [];
    $affindaFailed = true;
    $affindaError = 'Affinda API unavailable. Using local extraction.';
} elseif ($httpCode !== 200 && $httpCode !== 201) {
    error_log("Affinda HTTP error ($httpCode): " . substr($response ?: '', 0, 200));
    // Continue to fallback
    $affinda = [];
    $affindaFailed = true;
    $affindaError = "Affinda API returned HTTP $httpCode. Using local extraction.";
} else {
    $affinda = json_decode($response, true);
    $affindaFailed = false;
    $affindaError = null;
}
// Persist raw response and which key/workspace used for troubleshooting
try {
    $debugPath = $rootPath . DIRECTORY_SEPARATOR . 'affinda_debug.txt';
    $meta = [
        'api_version' => $useV3 ? 'v3' : 'v2',
        'workspace_set' => $useV3 ? $workspace : null,
        'key_suffix' => $keySuffix,
        'timestamp' => date('c')
    ];
    @file_put_contents($debugPath, json_encode($meta) . "\n" . $response);
} catch (\Throwable $e) {}

// Only fail if we got a response but it's invalid JSON (not if we're using fallback)
if (!$affindaFailed && !is_array($affinda)) {
    error_log("Invalid JSON response from Affinda");
    // Continue to fallback instead of failing
    $affinda = [];
    $affindaFailed = true;
    $affindaError = 'Invalid response from Affinda. Using local extraction.';
}

// Helper function defined early for use in error checking
$g = function($array, $path, $default = null) {
    $current = $array;
    foreach (explode('.', $path) as $key) {
        if (is_array($current) && array_key_exists($key, $current)) {
            $current = $current[$key];
        } else {
            return $default;
        }
    }
    return $current;
};

// Check for Affinda API errors (credits expired, etc.)
// Only check top-level error first (actual API errors)
$errorCode = $g($affinda, 'error.errorCode') ?? '';
$errorDetail = $g($affinda, 'error.errorDetail') ?? '';
$isFailed = $g($affinda, 'data.meta.failed', false);

// Save rawText before potentially clearing affinda data
$affindaRawText = $g($affinda, 'data.rawText') ?? $g($affinda, 'rawText') ?? '';

// Log what we received for debugging
if (!empty($affinda)) {
    error_log("Affinda response received - HTTP: $httpCode, has data: " . (isset($affinda['data']) ? 'yes' : 'no'));
    if (isset($affinda['data'])) {
        error_log("Affinda data keys: " . (is_array($affinda['data']) ? implode(', ', array_keys($affinda['data'])) : 'not array'));
    }
}

// Only check for Affinda errors if we haven't already marked it as failed
// Don't mark as failed just because errorDetail exists - it might be in meta for successful responses
if (!$affindaFailed) {
    // Only mark as failed if there's a real error (top-level error, or failed=true, or no_parsing_credits)
    if ($isFailed === true || $errorCode === 'no_parsing_credits' || (!empty($errorCode) && !empty($errorDetail))) {
        // Affinda failed - fall back to local extraction
        $affindaError = $errorDetail ?: ($errorCode === 'no_parsing_credits' ? 'Affinda credits expired. Using local text extraction.' : 'Affinda parsing failed. Using local text extraction.');
        error_log("Affinda API error detected: " . $errorCode . " - " . $errorDetail);
        // Mark as failed but keep data structure for rawText access
        $affindaFailed = true;
    } else {
        // Success - log that we're using Affinda data
        error_log("Affinda API call successful - proceeding with structured data extraction");
    }
}

// Bubble up validation/client errors for clarity (but only if not already handled above)
if (!$isFailed && !$errorCode && isset($affinda['type']) && in_array($affinda['type'], ['validation_error','client_error'])) {
    $detail = 'Error from Affinda.';
    if (!empty($affinda['errors'][0]['detail'])) {
        $detail = $affinda['errors'][0]['detail'];
    }
    send_json_response(['success' => false, 'error' => $detail], 400);
}

// If v3 returned a duplicate error in meta/error, fetch the original document
// BUT only if we have time remaining
$elapsedTime = microtime(true) - $processStartTime;
if ($useV3 && $elapsedTime < ($maxProcessTime - 5)) {
    $dupCode = $g($affinda, 'error.errorCode');
    $dupDetail = $g($affinda, 'meta.errorDetail') ?? $g($affinda, 'error.errorDetail');
    if ($dupCode === 'duplicate_document_error' && is_string($dupDetail)) {
        if (preg_match('/duplicate of\s+([A-Za-z0-9]+)/i', $dupDetail, $m)) {
            $origId = $m[1];
            $remainingTime = $maxProcessTime - $elapsedTime - 2;
            $ch3 = curl_init();
            curl_setopt($ch3, CURLOPT_URL, 'https://api.affinda.com/v3/documents/' . urlencode($origId));
            curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch3, CURLOPT_TIMEOUT, min(10, max(3, $remainingTime))); // Max 10 seconds
            curl_setopt($ch3, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch3, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch3, CURLOPT_HTTPHEADER, [ 'Authorization: Bearer ' . $apiKey ]);
            $resp3 = curl_exec($ch3);
            curl_close($ch3);
            if ($resp3) {
                $doc3 = json_decode($resp3, true);
                $data = $g($doc3, 'data', []);
                $parsed = $g($data, 'parsed', []);
                if (empty($parsed)) { $parsed = $g($data, 'attributes.parsed', []); }
                if (empty($parsed)) { $parsed = $g($data, 'attributes.data', []); }
                // Continue mapping below using $parsed/$data
            }
        }
    }
}

// Extract parsed payload depending on API version
// Skip if Affinda failed - go straight to fallback
if ($affindaFailed) {
    $parsed = [];
    $data = [];
} elseif ($useV3) {
    // Data might be an object or an array (take the first doc)
    $dataNode = $g($affinda, 'data', []);
    if (is_array($dataNode) && array_keys($dataNode) === range(0, count($dataNode) - 1)) {
        $data = isset($dataNode[0]) ? $dataNode[0] : [];
    } else {
        $data = $dataNode;
    }
    
    // Debug: Log the full structure to understand what we're getting
    error_log("Affinda v3 response - top level keys: " . (is_array($affinda) ? implode(', ', array_keys($affinda)) : 'not array'));
    error_log("Affinda v3 data keys: " . (is_array($data) ? implode(', ', array_keys($data)) : 'not array'));
    
    // Try common locations for parsed payload in v3
    $parsed = $g($data, 'parsed', []);
    if (empty($parsed)) { 
        $parsed = $g($data, 'attributes.parsed', []); 
        error_log("Tried attributes.parsed - found: " . (!empty($parsed) ? 'yes' : 'no'));
    }
    if (empty($parsed)) { 
        $parsed = $g($data, 'attributes.data', []); 
        error_log("Tried attributes.data - found: " . (!empty($parsed) ? 'yes' : 'no'));
    }
    if (empty($parsed)) { 
        $parsed = $g($affinda, 'parsed', []); 
        error_log("Tried top-level parsed - found: " . (!empty($parsed) ? 'yes' : 'no'));
    }
    
    // In v3, parsed data might be directly in data.attributes
    if (empty($parsed) && isset($data['attributes']) && is_array($data['attributes'])) {
        $parsed = $data['attributes'];
        error_log("Using data.attributes as parsed data");
    }
    
    // Also try data.attributes directly if it exists and has content
    if (empty($parsed) && isset($data['attributes']) && is_array($data['attributes'])) {
        // Check if attributes has nested structure
        if (isset($data['attributes']['resume'])) {
            $parsed = $data['attributes']['resume'];
            error_log("Using data.attributes.resume as parsed data");
        } elseif (isset($data['attributes']['data'])) {
            $parsed = $data['attributes']['data'];
            error_log("Using data.attributes.data as parsed data");
        }
    }
    
    // Debug: Log what we got from Affinda
    error_log("Final parsed structure keys: " . (is_array($parsed) && !empty($parsed) ? implode(', ', array_keys($parsed)) : 'empty or not array'));
    if (!empty($affindaRawText)) {
        error_log("Affinda rawText length: " . strlen($affindaRawText) . " chars");
    }
    
    // If we still don't have parsed data but have rawText, log a warning
    if (empty($parsed) && !empty($affindaRawText)) {
        error_log("WARNING: Affinda returned rawText but no structured parsed data. Response structure may be different than expected.");
        error_log("Available in data: " . (is_array($data) ? json_encode(array_keys($data)) : 'not array'));
    }
    // If still empty, but we have an identifier and ready=true, fetch the document detail
    // BUT only if we haven't used too much time already
    $elapsedTime = microtime(true) - $processStartTime;
    if (empty($parsed) && $elapsedTime < ($maxProcessTime - 5)) {
        $identifier = $g($affinda, 'meta.identifier');
        $isReady = $g($affinda, 'meta.ready');
        if (!empty($identifier) && $isReady === true) {
            // Use shorter timeout for second request - we're already running low on time
            $remainingTime = $maxProcessTime - $elapsedTime - 2; // Leave 2 seconds buffer
            $ch2 = curl_init();
            curl_setopt($ch2, CURLOPT_URL, 'https://api.affinda.com/v3/documents/' . urlencode($identifier));
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_TIMEOUT, min(10, max(3, $remainingTime))); // Max 10 seconds, min 3
            curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch2, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            curl_setopt($ch2, CURLOPT_HTTPHEADER, [ 'Authorization: Bearer ' . $apiKey ]);
            $resp2 = curl_exec($ch2);
            curl_close($ch2);
            if ($resp2) {
                $doc = json_decode($resp2, true);
                $data = $g($doc, 'data', []);
                $p2 = $g($data, 'parsed', []);
                if (empty($p2)) { $p2 = $g($data, 'attributes.parsed', []); }
                if (empty($p2)) { $p2 = $g($data, 'attributes.data', []); }
                if (!empty($p2)) { $parsed = $p2; }
            }
        }
    }
} else {
    // v2 returns fields directly under 'data'
    $data = $g($affinda, 'data', []);
    $parsed = $data; // treat data as parsed for downstream mapping
}

// Try multiple schema variants for robustness (v2/v3 differences)
// Affinda v3 may use different paths - try both structures
$firstName = $g($parsed, 'name.first_name')
    ?? $g($parsed, 'name.first')
    ?? $g($parsed, 'names.0.first')
    ?? $g($parsed, 'names.0.firstName')
    ?? $g($data, 'name.first')
    ?? $g($data, 'name.firstName')
    ?? '';
$lastName = $g($parsed, 'name.last_name')
    ?? $g($parsed, 'name.last')
    ?? $g($parsed, 'names.0.last')
    ?? $g($parsed, 'names.0.lastName')
    ?? $g($data, 'name.last')
    ?? $g($data, 'name.lastName')
    ?? '';
$middleName = $g($parsed, 'name.middle_name')
    ?? $g($parsed, 'name.middle')
    ?? $g($parsed, 'names.0.middle')
    ?? $g($parsed, 'names.0.middleName')
    ?? '';
$nameSuffix = $g($parsed, 'name.suffix')
    ?? $g($parsed, 'name.suffixes.0')
    ?? '';

// Contact - try multiple paths for v3 compatibility
$email = $g($parsed, 'contact.email')
    ?? $g($parsed, 'contact.emails.0')
    ?? $g($parsed, 'emails.0')
    ?? $g($parsed, 'emails.0.value')
    ?? $g($data, 'emails.0')
    ?? $g($data, 'emails.0.value')
    ?? '';
$phone = $g($parsed, 'contact.phone_number')
    ?? $g($parsed, 'contact.phoneNumbers.0')
    ?? $g($parsed, 'phoneNumbers.0')
    ?? $g($parsed, 'phoneNumbers.0.value')
    ?? $g($data, 'phoneNumbers.0')
    ?? $g($data, 'phoneNumbers.0.value')
    ?? '';
$addressFormatted = $g($parsed, 'contact.address.formatted')
    ?? $g($parsed, 'contact.location.formatted')
    ?? $g($parsed, 'location.formatted')
    ?? $g($data, 'location.formatted')
    ?? '';

// Birthday / gender / nationality
$birthdayRaw = $g($parsed, 'personal_information.date_of_birth')
    ?? $g($data, 'dateOfBirth')
    ?? '';
// Normalize to YYYY-MM-DD if possible
$birthday = '';
if ($birthdayRaw && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $birthdayRaw, $m)) {
    $birthday = $m[1] . '-' . $m[2] . '-' . $m[3];
} elseif ($birthdayRaw && preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $birthdayRaw, $m)) {
    $birthday = $m[3] . '-' . $m[1] . '-' . $m[2];
}
$gender = $g($parsed, 'personal_information.gender')
    ?? $g($data, 'gender')
    ?? '';
$nationality = $g($parsed, 'personal_information.nationality')
    ?? $g($data, 'nationality')
    ?? '';

// Education (most recent)
$edu0 = $g($parsed, 'education.0', []);
$program = $g($edu0, 'organization')
    ?? $g($edu0, 'accreditation.inputStr')
    ?? $g($edu0, 'accreditation.education')
    ?? '';
$gradEnd = $g($edu0, 'end_date') ?? $g($edu0, 'dates.completionDate') ?? '';
$gradYear = '';
if ($gradEnd) {
    if (preg_match('/\b(\d{4})\b/', $gradEnd, $m)) { $gradYear = $m[1]; }
}
// Try to get grad month as integer 1-12 if present
$gradMonth = '';
if ($gradEnd) {
    if (preg_match('/-(\d{2})-/', $gradEnd, $m)) { $gradMonth = ltrim($m[1], '0'); }
}

// Work experience (most recent)
$work0 = $g($parsed, 'workExperience.0', []);
$company = $g($work0, 'organization')
    ?? $g($work0, 'company')
    ?? $g($work0, 'employer')
    ?? $g($work0, 'employer_name')
    ?? '';
$position = $g($work0, 'job_title')
    ?? $g($work0, 'jobTitle')
    ?? $g($work0, 'position')
    ?? '';
// Derive employment status
$employmentStatus = '';
if (is_array($work0)) {
    $isCurrent = $g($work0, 'is_current') ?? $g($work0, 'dates.isCurrent');
    if ($isCurrent === true || $isCurrent === 'true' || $isCurrent === 1) {
        $employmentStatus = 'Employed';
    } elseif (!empty($company)) {
        $employmentStatus = 'Employed';
    }
}
// Industry best-effort
$industry = $g($work0, 'industry') ?? '';

// Previous role (2nd most recent)
$work1 = $g($parsed, 'workExperience.1', []);
$previousRole = $g($work1, 'job_title') ?? '';

// Employment history summary
$employmentHistory = '';
$workArr = $g($parsed, 'workExperience', []);
if (is_array($workArr) && count($workArr) > 0) {
    $chunks = [];
    $lim = min(5, count($workArr));
    for ($i=0; $i<$lim; $i++) {
        $w = $workArr[$i];
        if (is_array($w)) {
            $org = $w['organization'] ?? '';
            $jt = $w['job_title'] ?? '';
            if ($org || $jt) { $chunks[] = trim($org . ' ' . ($jt ? '(' . $jt . ')' : '')); }
        }
    }
    if ($chunks) { $employmentHistory = implode('; ', $chunks); }
}

// Length of service (months) for most recent role
$lengthOfService = '';
$start = $g($work0, 'start_date') ?? $g($work0, 'dates.startDate');
$end = $g($work0, 'end_date') ?? $g($work0, 'dates.completionDate');
if ($start && preg_match('/^(\d{4})-(\d{2})/', $start, $s) ) {
    $sy = (int)$s[1]; $sm = (int)$s[2];
    $ey = (int)date('Y'); $em = (int)date('m');
    if ($end && preg_match('/^(\d{4})-(\d{2})/', $end, $e)) { $ey = (int)$e[1]; $em = (int)$e[2]; }
    $months = ($ey - $sy) * 12 + ($em - $sm);
    if ($months >= 0) { $lengthOfService = $months . ' months'; }
}

// Skills array -> list of strings
$skills = [];
$skillsArr = $g($parsed, 'skills', []);
if (is_array($skillsArr)) {
    foreach ($skillsArr as $s) {
        if (is_array($s)) {
            $name = $s['name'] ?? ($s['skill'] ?? null);
            if ($name) { $skills[] = $name; }
        } elseif (is_string($s)) {
            $skills[] = $s;
        }
    }
}

// Professional summary / objective
$summary = $g($parsed, 'summary')
    ?? $g($parsed, 'sections.summary')
    ?? $g($data, 'summary')
    ?? '';

// LinkedIn / websites
$linkedin = '';
$websites = $g($parsed, 'websites', []);
if (is_array($websites)) {
    foreach ($websites as $w) {
        $url = is_array($w) ? ($w['url'] ?? '') : (is_string($w) ? $w : '');
        if ($url && stripos($url, 'linkedin.com') !== false) { $linkedin = $url; break; }
    }
}
if (!$linkedin) {
    $social = $g($parsed, 'social_links', []);
    if (is_array($social)) {
        foreach ($social as $s) {
            $url = is_array($s) ? ($s['url'] ?? '') : (is_string($s) ? $s : '');
            if ($url && stripos($url, 'linkedin.com') !== false) { $linkedin = $url; break; }
        }
    }
}

// Build education history text
$educationHistory = '';
$eduArr = $g($parsed, 'education', []);
if (is_array($eduArr) && count($eduArr) > 0) {
    $lines = [];
    foreach ($eduArr as $e) {
        if (!is_array($e)) continue;
        $org = $e['organization'] ?? '';
        $acc = $e['accreditation']['inputStr'] ?? ($e['accreditation']['education'] ?? '');
        $dates = $e['dates']['rawText'] ?? ($e['end_date'] ?? '');
        $line = trim($org . ' — ' . $acc . ' — ' . $dates);
        if ($line !== '— —') { $lines[] = $line; }
    }
    if ($lines) $educationHistory = implode("\n", $lines);
}

// Build work experience history text
$workHistory = '';
if (is_array($workArr) && count($workArr) > 0) {
    $lines = [];
    foreach ($workArr as $w) {
        if (!is_array($w)) continue;
        $org = $w['organization'] ?? ($w['company'] ?? '');
        $jt = $w['job_title'] ?? ($w['jobTitle'] ?? '');
        $dates = $w['dates']['rawText'] ?? (($w['start_date'] ?? '') . ' - ' . ($w['end_date'] ?? ''));
        $line = trim($jt . ' — ' . $org . ' — ' . $dates);
        if ($line !== '— —') { $lines[] = $line; }
    }
    if ($lines) $workHistory = implode("\n", $lines);
}

$mapped = [
    'firstname' => $firstName,
    'lastname' => $lastName,
    'middlename' => $middleName,
    'name_ext' => $nameSuffix,
    'email' => $email,
    'personal_contact' => $phone,
    'phone_number' => $phone,
    'address' => $addressFormatted,
    'program' => $program,
    'degree' => $program,
    'year_graduated' => $gradYear,
    'month_graduated' => $gradMonth,
    'company' => $company,
    'position' => $position,
    'current_company' => $company,
    'current_job_title' => $position,
    'skills' => $skills,
    'birthday' => $birthday,
    'gender' => $gender,
    'nationality' => $nationality,
    'employment_status' => $employmentStatus,
    'industry' => $industry,
    'employment_history' => $employmentHistory,
    'previous_role' => $previousRole,
    'length_of_service' => $lengthOfService,
    'professional_summary' => $summary,
    'linkedin_url' => $linkedin,
    'education_history' => $educationHistory,
    'work_experience' => $workHistory,
];

// Lightweight debug info to help diagnose field mapping without exposing entire resume
$debugInfo = [
    'api_version' => $useV3 ? 'v3' : 'v2',
    'has_parsed' => !empty($parsed),
    'parsed_keys' => is_array($parsed) ? array_keys($parsed) : [],
    'data_keys' => is_array($data) ? array_keys($data) : [],
    'affinda_keys' => is_array($affinda) ? array_keys($affinda) : [],
    'key_suffix' => $keySuffix,
    'affinda_failed' => $affindaFailed,
];
if (isset($affindaError)) {
    $debugInfo['affinda_error'] = $affindaError;
    $debugInfo['using_fallback'] = true;
}
// Log sample of extracted fields for debugging
$sampleFields = [];
foreach (['firstname', 'lastname', 'email', 'phone', 'company', 'position'] as $field) {
    $val = $mapped[$field] ?? '';
    if (!empty($val)) {
        $sampleFields[$field] = is_string($val) ? substr($val, 0, 50) : (is_array($val) ? count($val) . ' items' : 'non-empty');
    }
}
if (!empty($sampleFields)) {
    $debugInfo['extracted_fields_sample'] = $sampleFields;
    error_log("Successfully extracted fields: " . implode(', ', array_keys($sampleFields)));
} else {
    error_log("WARNING: No fields extracted from Affinda response. Parsed keys: " . (is_array($parsed) ? implode(', ', array_keys($parsed)) : 'none'));
}

// Local fallback: if nothing parsed, try basic extraction
// Check if we have meaningful data (not just empty strings)
$allEmpty = true;
foreach (['firstname','lastname','email','personal_contact','program','company','position'] as $k) {
    $val = $mapped[$k] ?? '';
    // Check if value is not empty and not just whitespace
    if (!empty($val) && trim($val) !== '' && (!is_array($val) || count($val) > 0)) { 
        $allEmpty = false; 
        break; 
    }
}

// Check elapsed time before starting fallback
$elapsedTime = microtime(true) - $processStartTime;
$timeRemaining = $maxProcessTime - $elapsedTime;

// Always try fallback extraction if we don't have good data, regardless of $allEmpty
// This ensures we extract from raw text even if Affinda returned empty structured data
if ($timeRemaining > 3) { // Only do fallback if we have at least 3 seconds left
    $fallbackText = '';
    if ($safeExt === 'docx') {
        // Extract text from DOCX (zip) without external deps - fast operation
        $zip = new ZipArchive();
        if ($zip->open($resumePath) === true) {
            $xml = $zip->getFromName('word/document.xml');
            $zip->close();
            if ($xml) {
                $txt = preg_replace('/<[^>]+>/', ' ', $xml);
                $fallbackText = html_entity_decode($txt, ENT_QUOTES | ENT_XML1);
            }
        }
    } elseif ($safeExt === 'pdf') {
        // Method 1: ALWAYS prioritize Affinda rawText if available (cleanest text)
        if (!empty($affindaRawText)) {
            $fallbackText = $affindaRawText;
            error_log("Using Affinda rawText for PDF extraction: " . strlen($fallbackText) . " chars");
        } 
        // Only use other methods if Affinda rawText is not available
        elseif ($timeRemaining > 5 && function_exists('shell_exec') && !in_array('shell_exec', explode(',', ini_get('disable_functions')))) {
            // Method 2: Try pdftotext only if shell_exec is available and we have enough time
            $outTxt = $resumePath . '.txt';
            $fallbackStart = microtime(true);
            $result = @shell_exec('pdftotext -layout ' . escapeshellarg($resumePath) . ' ' . escapeshellarg($outTxt) . ' 2>&1');
            // Check if it completed quickly (within 3 seconds)
            if ((microtime(true) - $fallbackStart) < 3 && is_file($outTxt) && filesize($outTxt) > 0) {
                $fallbackText = @file_get_contents($outTxt) ?: '';
                @unlink($outTxt);
                error_log("Extracted PDF text using pdftotext: " . strlen($fallbackText) . " chars");
            }
        }
        
        // Method 3: Basic PDF text extraction (no external tools needed)
        // This is a last resort - extracts readable ASCII text from PDF streams
        if (empty($fallbackText) && $timeRemaining > 2) {
            $pdfContent = @file_get_contents($resumePath);
            if ($pdfContent) {
                // Try multiple extraction methods
                $extractedTexts = [];
                
                // Method 3a: Extract text from stream objects (decompressed)
                if (preg_match_all('/stream\s*(.*?)\s*endstream/is', $pdfContent, $matches)) {
                    foreach ($matches[1] as $stream) {
                        // Try multiple decompression methods
                        $decoded = @gzuncompress($stream);
                        if ($decoded === false) {
                            $decoded = @gzdecode($stream);
                        }
                        if ($decoded === false) {
                            $decoded = $stream;
                        }
                        
                        // Extract text more liberally - include more characters
                        if (preg_match_all('/[A-Za-z0-9\s@.,\-+()\/:;!?#$%&*=\[\]{}|\\"\'<>]{2,}/', $decoded, $words)) {
                            $extractedTexts[] = implode(' ', $words[0]);
                        }
                    }
                }
                
                // Method 3b: Extract text directly from PDF content (between parentheses)
                // PDF text is often stored as (text) or <hex>text</hex>
                if (preg_match_all('/\(([^)]+)\)/', $pdfContent, $matches)) {
                    $textFromParens = [];
                    foreach ($matches[1] as $text) {
                        // Filter out non-readable text
                        if (preg_match('/[A-Za-z]{2,}/', $text)) {
                            $textFromParens[] = $text;
                        }
                    }
                    if (!empty($textFromParens)) {
                        $extractedTexts[] = implode(' ', $textFromParens);
                    }
                }
                
                // Method 3c: Extract from hex-encoded text
                if (preg_match_all('/<([0-9A-Fa-f\s]+)>/i', $pdfContent, $matches)) {
                    $hexTexts = [];
                    foreach ($matches[1] as $hex) {
                        $hex = preg_replace('/\s+/', '', $hex);
                        if (strlen($hex) % 2 === 0) {
                            $decoded = '';
                            for ($i = 0; $i < strlen($hex); $i += 2) {
                                $char = chr(hexdec(substr($hex, $i, 2)));
                                if (ctype_print($char) || $char === "\n" || $char === "\r") {
                                    $decoded .= $char;
                                }
                            }
                            if (preg_match('/[A-Za-z]{3,}/', $decoded)) {
                                $hexTexts[] = $decoded;
                            }
                        }
                    }
                    if (!empty($hexTexts)) {
                        $extractedTexts[] = implode(' ', $hexTexts);
                    }
                }
                
                // Combine all extracted texts and clean up
                if (!empty($extractedTexts)) {
                    $combined = implode(' ', $extractedTexts);
                    // Remove excessive whitespace
                    $combined = preg_replace('/\s+/', ' ', $combined);
                    // Remove non-printable characters except newlines
                    $combined = preg_replace('/[^\x20-\x7E\n\r]/', '', $combined);
                    // Take first 2000 characters (enough for a resume)
                    $fallbackText = substr($combined, 0, 2000);
                    error_log("Extracted PDF text using basic method: " . strlen($fallbackText) . " chars");
                }
            }
        }
        
        if (empty($fallbackText)) {
            error_log("WARNING: Could not extract text from PDF. No fallback text available.");
        }
    }

    if ($fallbackText) {
        // Enhanced regex extraction from raw text
        $isAffindaText = !empty($affindaRawText) && $fallbackText === $affindaRawText;
        error_log("Using " . ($isAffindaText ? "Affinda rawText" : "fallback text") . " extraction. Text length: " . strlen($fallbackText));
        
        // Only extract names from fallback if we don't already have them from Affinda structured data
        // AND if the text looks clean (not garbled)
        $textLooksClean = $isAffindaText || (
            preg_match('/[A-Za-z]{3,}\s+[A-Za-z]{3,}/', $fallbackText) && 
            !preg_match('/[^A-Za-z0-9\s@.,\-+()\/:;!?#$%&*=\[\]{}|\\"\'<>]{5,}/', $fallbackText)
        );
        
        // Email extraction (multiple patterns)
        if (empty($mapped['email'])) {
            if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $fallbackText, $m)) {
                $mapped['email'] = trim($m[0]);
            }
        }
        
        // Phone extraction (multiple patterns)
        if (empty($mapped['personal_contact'])) {
            // Try various phone formats
            $phonePatterns = [
                '/\+?\d{1,3}[\s\-]?\(?\d{3}\)?[\s\-]?\d{3}[\s\-]?\d{4}/',  // Standard US/Philippines
                '/\+63[\s\-]?\d{3}[\s\-]?\d{3}[\s\-]?\d{4}/',  // Philippines format
                '/0\d{2}[\s\-]?\d{3}[\s\-]?\d{4}/',  // Local format
                '/\+?\d[\d\s\-()]{8,}/'  // Generic
            ];
            foreach ($phonePatterns as $pattern) {
                if (preg_match($pattern, $fallbackText, $m)) {
                    $mapped['personal_contact'] = preg_replace('/[\s\-()]/', '', trim($m[0]));
                    break;
                }
            }
        }
        
        // Name extraction from first few lines - ONLY if text looks clean and we don't have names from Affinda
        if ($textLooksClean && (empty($mapped['firstname']) || empty($mapped['lastname']))) {
            $lines = array_filter(array_map('trim', preg_split('/\r?\n/', $fallbackText)), function($l) {
                return strlen($l) > 0 && !preg_match('/^[^a-zA-Z]*$/', $l); // Skip empty and non-text lines
            });
            $lines = array_values($lines); // Re-index
            
            if (count($lines) > 0) {
                // First line often contains name
                $firstLine = $lines[0];
                // Remove common prefixes
                $firstLine = preg_replace('/^(RESUME|CURRICULUM VITAE|CV|Name:?|Full Name:?)\s*/i', '', $firstLine);
                $tokens = preg_split('/\s+/', $firstLine);
                // Only use if tokens look like real names (at least 3 chars, all letters, has vowels, not too many consonants in a row)
                if (count($tokens) >= 2) {
                    $token1 = $tokens[0];
                    $token2 = $tokens[1];
                    // Check if tokens look like real names (not garbled)
                    $token1Valid = strlen($token1) >= 3 && 
                                   preg_match('/^[A-Za-z]+$/', $token1) &&
                                   preg_match('/[aeiou]/i', $token1) && // Must have vowels
                                   !preg_match('/[bcdfghjklmnpqrstvwxyz]{4,}/i', $token1); // Not too many consonants in a row
                    $token2Valid = strlen($token2) >= 3 && 
                                   preg_match('/^[A-Za-z]+$/', $token2) &&
                                   preg_match('/[aeiou]/i', $token2) && // Must have vowels
                                   !preg_match('/[bcdfghjklmnpqrstvwxyz]{4,}/i', $token2); // Not too many consonants in a row
                    
                    if ($token1Valid && $token2Valid) {
                        // Check if existing names look garbled and should be replaced
                        $shouldReplace = empty($mapped['firstname']) || empty($mapped['lastname']);
                        if (!$shouldReplace) {
                            // Check if existing names are garbled
                            $existing1 = strtolower($mapped['firstname']);
                            $existing2 = strtolower($mapped['lastname']);
                            $existingGarbled = !preg_match('/[aeiou]/i', $existing1) || 
                                              preg_match('/[bcdfghjklmnpqrstvwxyz]{4,}/i', $existing1) ||
                                              !preg_match('/[aeiou]/i', $existing2) || 
                                              preg_match('/[bcdfghjklmnpqrstvwxyz]{4,}/i', $existing2);
                            if ($existingGarbled) {
                                $shouldReplace = true;
                                error_log("Replacing garbled names with clean extraction");
                            }
                        }
                        
                        if ($shouldReplace) {
                            $mapped['firstname'] = ucfirst(strtolower($token1));
                            $mapped['lastname'] = ucfirst(strtolower($token2));
                            if (count($tokens) >= 3) {
                                $token3 = $tokens[2];
                                if (strlen($token3) >= 3 && preg_match('/^[A-Za-z]+$/', $token3) && 
                                    preg_match('/[aeiou]/i', $token3) &&
                                    !preg_match('/[bcdfghjklmnpqrstvwxyz]{4,}/i', $token3)) {
                                    $mapped['middlename'] = ucfirst(strtolower($token3));
                                }
                            }
                        }
                    } else {
                        error_log("WARNING: Extracted tokens don't look like valid names. Token1: '$token1' (valid: " . ($token1Valid ? 'yes' : 'no') . "), Token2: '$token2' (valid: " . ($token2Valid ? 'yes' : 'no') . ")");
                    }
                }
            }
        } elseif (!$textLooksClean) {
            error_log("WARNING: Fallback text appears garbled. Skipping name extraction to avoid corrupt data.");
        }
        
        // Address extraction
        if (empty($mapped['address'])) {
            // Look for address patterns
            if (preg_match('/(\d+\s+[A-Za-z\s,]+(?:Street|St|Avenue|Ave|Road|Rd|Boulevard|Blvd|Drive|Dr|Lane|Ln)[\s,]+[A-Za-z\s,]+(?:City|Province|Region)[\s,]*[A-Za-z\s,]*)/i', $fallbackText, $m)) {
                $mapped['address'] = trim($m[1]);
            } elseif (preg_match('/([A-Za-z\s,]+(?:City|Province|Region|Metro)[\s,]*[A-Za-z\s,]*)/i', $fallbackText, $m)) {
                $mapped['address'] = trim($m[1]);
            }
        }
        
        // Education/Program extraction
        if (empty($mapped['program'])) {
            $eduPatterns = [
                '/Bachelor\s+of\s+Science\s+in\s+([A-Za-z\s]+)/i',
                '/BS\s+([A-Za-z\s]+)/i',
                '/Bachelor\s+of\s+([A-Za-z\s]+)/i',
                '/Degree[:\s]+([A-Za-z\s]+)/i',
                '/Education[:\s]+([A-Za-z\s]+)/i'
            ];
            foreach ($eduPatterns as $pattern) {
                if (preg_match($pattern, $fallbackText, $m)) {
                    $mapped['program'] = trim($m[1]);
                    break;
                }
            }
        }
        
        // Graduation year
        if (empty($mapped['year_graduated'])) {
            if (preg_match('/Graduat(?:ed|ion)[:\s]+.*?(\d{4})/i', $fallbackText, $m)) {
                $year = (int)$m[1];
                if ($year >= 1950 && $year <= date('Y')) {
                    $mapped['year_graduated'] = $year;
                }
            } elseif (preg_match('/\b(19|20)\d{2}\b/', $fallbackText, $m)) {
                $year = (int)$m[0];
                if ($year >= 1950 && $year <= date('Y')) {
                    $mapped['year_graduated'] = $year;
                }
            }
        }
        
        // Position/Job Title extraction
        if (empty($mapped['position'])) {
            $positionPatterns = [
                '/Position[:\s]+([A-Za-z\s]+)/i',
                '/Title[:\s]+([A-Za-z\s]+)/i',
                '/Job Title[:\s]+([A-Za-z\s]+)/i',
                '/Role[:\s]+([A-Za-z\s]+)/i',
                '/\b(Software Engineer|Developer|Manager|Analyst|Designer|Engineer|Specialist|Coordinator|Assistant|Director|Supervisor)\b/i'
            ];
            foreach ($positionPatterns as $pattern) {
                if (preg_match($pattern, $fallbackText, $m)) {
                    $mapped['position'] = trim($m[1]);
                    break;
                }
            }
        }
        
        // Company extraction
        if (empty($mapped['company'])) {
            // Look for company names after "Company:", "Employer:", or near position
            if (preg_match('/Company[:\s]+([A-Za-z0-9\s&.,]+)/i', $fallbackText, $m)) {
                $mapped['company'] = trim($m[1]);
            } elseif (preg_match('/Employer[:\s]+([A-Za-z0-9\s&.,]+)/i', $fallbackText, $m)) {
                $mapped['company'] = trim($m[1]);
            } elseif (preg_match('/\b([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*(?:\s+(?:Inc|Corp|LLC|Ltd|Company|Solutions|Systems|Technologies))?)\b/', $fallbackText, $m)) {
                $mapped['company'] = trim($m[1]);
            }
        }
        
        // Skills extraction
        if (empty($mapped['skills']) || (is_array($mapped['skills']) && count($mapped['skills']) === 0)) {
            $skills = [];
            // Look for skills section
            if (preg_match('/Skills?[:\s]+(.*?)(?:\n\n|\n[A-Z]|$)/is', $fallbackText, $m)) {
                $skillsText = $m[1];
                // Extract common skills
                $commonSkills = ['PHP', 'JavaScript', 'Python', 'Java', 'SQL', 'HTML', 'CSS', 'React', 'Vue', 'Angular', 'Node.js', 'MySQL', 'PostgreSQL', 'MongoDB', 'Git', 'Linux', 'Windows', 'AWS', 'Azure', 'Docker', 'Kubernetes'];
                foreach ($commonSkills as $skill) {
                    if (stripos($skillsText, $skill) !== false || stripos($fallbackText, $skill) !== false) {
                        $skills[] = $skill;
                    }
                }
            }
            // Also try comma-separated skills
            if (preg_match('/Skills?[:\s]+([A-Za-z\s,]+)/i', $fallbackText, $m)) {
                $skillList = preg_split('/[,;]/', $m[1]);
                foreach ($skillList as $skill) {
                    $skill = trim($skill);
                    if (strlen($skill) > 2 && !in_array($skill, $skills)) {
                        $skills[] = $skill;
                    }
                }
            }
            if (!empty($skills)) {
                $mapped['skills'] = $skills;
            }
        }
        
        $debugInfo['fallback_used'] = true;
        $debugInfo['fallback_text_length'] = strlen($fallbackText);
    } else {
        $debugInfo['fallback_used'] = false;
    }
}

// Check if we're running out of time - force response if needed
$elapsedTime = microtime(true) - $processStartTime;
if ($elapsedTime > $maxProcessTime) {
    error_log("Process exceeded time limit ({$maxProcessTime}s). Forcing response.");
    // Add warning to debug info
    $debugInfo['timeout_warning'] = 'Process took longer than expected. Results may be incomplete.';
}

// Post-process: Clean up any garbled names that may have been extracted
// Check for garbled patterns in names and clear them if found
foreach (['firstname', 'lastname', 'middlename'] as $nameField) {
    if (!empty($mapped[$nameField]) && is_string($mapped[$nameField])) {
        $name = strtolower(trim($mapped[$nameField]));
        // Check for garbled patterns: no vowels, too many consonants in a row, weird letter combinations
        $isGarbled = (
            strlen($name) > 0 && !preg_match('/[aeiou]/i', $name)) || // No vowels
            preg_match('/[bcdfghjklmnpqrstvwxyz]{4,}/i', $name) || // 4+ consonants in a row
            preg_match('/[qwxz]{2,}/i', $name) || // Multiple rare letters
            (strlen($name) >= 5 && preg_match('/^[bcdfghjklmnpqrstvwxyz]+$/i', $name)); // All consonants and 5+ chars
        
        if ($isGarbled) {
            error_log("Detected garbled name in $nameField: '{$mapped[$nameField]}'. Clearing it.");
            $mapped[$nameField] = '';
            $debugInfo['cleared_garbled_' . $nameField] = true;
        }
    }
}

// Add more debug info to help troubleshoot
$debugInfo['mapped_fields_count'] = count(array_filter($mapped, function($v) {
    return $v !== null && $v !== '' && $v !== [] && (!is_array($v) || count($v) > 0);
}));
$debugInfo['has_fallback_text'] = !empty($fallbackText);
$debugInfo['fallback_text_preview'] = !empty($fallbackText) ? substr($fallbackText, 0, 200) . '...' : '';

send_json_response([
    'success' => true,
    'data' => $mapped,
    'saved_resume' => $resumeWebPath,
    'debug' => $debugInfo,
    'processing_time' => round($elapsedTime, 2),
    '_debug_raw' => [
        'affinda_used' => !$affindaFailed,
        'affinda_has_data' => !empty($affinda),
        'affinda_failed' => $affindaFailed,
        'affinda_error' => $affindaError ?? null,
        'http_code' => $httpCode ?? null,
        'parsed_keys' => is_array($parsed) ? array_keys($parsed) : [],
        'data_keys' => is_array($data) ? array_keys($data) : [],
        'affinda_top_keys' => is_array($affinda) ? array_keys($affinda) : [],
        'has_raw_text' => !empty($affindaRawText),
        'raw_text_length' => strlen($affindaRawText ?? ''),
        'fallback_used' => !empty($fallbackText) && $affindaFailed,
        'fallback_text_length' => strlen($fallbackText ?? ''),
        'fallback_text_preview' => !empty($fallbackText) ? substr($fallbackText, 0, 300) : '',
        'extraction_method' => $affindaFailed ? 'fallback' : 'affinda',
        'sample_extracted' => [
            'firstname' => $mapped['firstname'] ?? '',
            'lastname' => $mapped['lastname'] ?? '',
            'email' => $mapped['email'] ?? '',
            'phone' => $mapped['personal_contact'] ?? ''
        ]
    ]
]);
} catch (\Throwable $e) {
    // Catch any unhandled exceptions
    $errorDetails = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ];
    error_log("Exception in parse-resume.php: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Try to send detailed error (but don't expose sensitive info in production)
    try {
        send_json_response([
            'success' => false,
            'error' => 'An error occurred while processing the resume. Please try again.',
            'debug' => [
                'exception' => $e->getMessage(),
                'file' => basename($e->getFile()),
                'line' => $e->getLine()
            ]
        ], 500);
    } catch (\Throwable $e2) {
        // If sending response fails, output raw JSON
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode([
            'success' => false,
            'error' => 'Fatal error occurred',
            'debug' => 'Could not send proper error response'
        ]);
        exit;
    }
}
