<?php
/**
 * Quick check that /api/mobile/ is reachable. Open in browser:
 * https://ccsolfualumni.sbs/api/mobile/ping.php
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
echo json_encode([
    'ok' => true,
    'message' => 'Mobile API folder is reachable',
    'dashboard' => 'https://ccsolfualumni.sbs/api/mobile/dashboard.php',
    'time' => date('c'),
], JSON_UNESCAPED_SLASHES);
