<?php
/**
 * One-time: create CPS / tracer tables and itcp columns. Safe to run multiple times.
 * CLI: php bootstrap_cps_schema.php
 * Browser: open this file while logged in locally (optional).
 */
if (PHP_SAPI === 'cli' && empty($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/includes/cps_alumni_lib.php';
require_once __DIR__ . '/includes/otp_device_lib.php';

header('Content-Type: text/plain; charset=utf-8');

$conn = getDBConnection();
if (!$conn) {
    die("No database connection.\n");
}

cps_ensure_schema($conn);
otp_device_ensure_schema($conn);
$conn->close();

echo "CPS / tracer schema is up to date.\n";
