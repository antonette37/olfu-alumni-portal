<?php
/**
 * Authorization for mobile APIs (Hostinger-safe).
 * Reads Bearer header, X-Auth-Token header, and access_token query (Apache often strips Authorization).
 */
if (!function_exists('mobile_auth_header')) {
    function mobile_auth_header(): string
    {
        if (function_exists('getallheaders')) {
            $h = getallheaders();
            if (is_array($h)) {
                foreach ($h as $key => $value) {
                    if (strtolower((string) $key) === 'authorization') {
                        return (string) $value;
                    }
                }
            }
        }

        return $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';
    }

    function mobile_auth_user_id(): ?int
    {
        $candidates = [
            mobile_auth_header(),
            $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '',
            $_GET['access_token'] ?? '',
        ];

        foreach ($candidates as $auth) {
            $auth = trim((string) $auth);
            if ($auth === '') {
                continue;
            }
            if (preg_match('/Bearer\s+(\d+)/i', $auth, $m)) {
                return (int) $m[1];
            }
            if (preg_match('/token_(\d+)/i', $auth, $m)) {
                return (int) $m[1];
            }
            if (preg_match('/^(\d+)$/', $auth, $m)) {
                return (int) $m[1];
            }
        }

        return null;
    }
}
