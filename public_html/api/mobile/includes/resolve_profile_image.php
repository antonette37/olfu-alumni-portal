<?php
/**
 * Profile image URLs for mobile — uses profile_photo.php?user_id= when any image exists on disk.
 */
require_once __DIR__ . '/mobile_photo_file.php';

if (!function_exists('mobile_resolve_profile_image_url')) {
    function mobile_resolve_profile_image_url($photo, $siteBase = 'https://ccsolfualumni.sbs', $userId = null)
    {
        $siteBase = rtrim((string) $siteBase, '/');
        $uid = (int) $userId;

        if ($photo !== null && is_string($photo)) {
            $p = trim($photo);
            if (strpos($p, 'data:image/') === 0) {
                return $p;
            }
            if (preg_match('#^//#', $p)) {
                return 'https:' . $p;
            }
            if (preg_match('#^https?://#i', $p)) {
                return $p;
            }
        }

        return mobile_photo_serve_url($photo, $siteBase, $uid > 0 ? $uid : null);
    }

    function mobile_resolve_profile_image_data($photo, $userId = null)
    {
        $uid = (int) $userId;
        if ($uid > 0) {
            return mobile_photo_data_uri_for_user($uid, $photo);
        }
        if ($photo === null || !is_string($photo)) {
            return null;
        }
        $p = trim($photo);
        if ($p === '' || strcasecmp($p, 'default-avatar.png') === 0) {
            return null;
        }
        if (strpos($p, 'data:image/') === 0) {
            return $p;
        }
        return mobile_photo_data_uri($p);
    }
}
