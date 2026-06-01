<?php
/**
 * Shared runtime defaults for production hosting (mysqli noise, fatal logging).
 * Safe to require_once from any entry script.
 */
if (defined('OLFU_RUNTIME_BOOTSTRAP')) {
    return;
}
define('OLFU_RUNTIME_BOOTSTRAP', true);

if (function_exists('mysqli_report')) {
    @mysqli_report(MYSQLI_REPORT_OFF);
}

register_shutdown_function(static function () {
    $e = error_get_last();
    if ($e === null || !isset($e['type'], $e['message'], $e['file'], $e['line'])) {
        return;
    }
    if (!in_array((int) $e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    @error_log('[OLFU fatal] ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line'] . ' uri=' . $uri);
});

$__olfu_mysqli_compat = __DIR__ . '/mysqli_compat.php';
if (is_file($__olfu_mysqli_compat)) {
    require_once $__olfu_mysqli_compat;
}
unset($__olfu_mysqli_compat);
