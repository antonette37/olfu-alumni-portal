<?php
/**
 * Build approval email attachments: uploaded verification IDs + digital ID card PNGs (same data as ad_alumni_id_check).
 */
declare(strict_types=1);

/**
 * @param array<string, mixed> $user_data itcp row
 *
 * @return array{
 *   attachments: list<array{path: string, name: string}>,
 *   tmp_png_paths: list<string>,
 *   has_verification: bool,
 *   has_digital: bool
 * }
 */
function ad_approve_collect_approval_attachments(?mysqli $conn, array $user_data, int $user_id): array
{
    require_once __DIR__ . '/ad_approve_verification_id_attachments.php';
    require_once __DIR__ . '/ad_approve_id_card_png.php';

    $verification = [];
    if ($conn instanceof mysqli) {
        $verification = ad_approve_verification_id_attachment_list($conn, $user_data, $user_id);
    }

    $tmpPngPaths = [];
    $digital = [];
    $pair = ad_approve_id_card_create_png_pair($user_data, $user_id);
    if ($pair !== null) {
        $tmpPngPaths = $pair;
        $digital = [
            ['path' => $pair[0], 'name' => 'OLFU-Alumni-ID-Card-Front.png'],
            ['path' => $pair[1], 'name' => 'OLFU-Alumni-ID-Card-Back.png'],
        ];
    }

    $attachments = array_merge($verification, $digital);

    return [
        'attachments' => $attachments,
        'tmp_png_paths' => $tmpPngPaths,
        'has_verification' => $verification !== [],
        'has_digital' => $digital !== [],
    ];
}

function ad_approve_id_attachment_email_blurb(bool $hasVerification, bool $hasDigital): string
{
    if (!$hasVerification && !$hasDigital) {
        return '';
    }
    if ($hasVerification && $hasDigital) {
        return "<p style='font-size:14px;color:#374151;line-height:1.5;margin:0 0 12px 0;'>Attached: your submitted ID verification photos (when on file), and digital alumni ID card images (PNG) matching the admin <strong>Check Alumni ID</strong> preview.</p>";
    }
    if ($hasDigital) {
        return "<p style='font-size:14px;color:#374151;line-height:1.5;margin:0 0 12px 0;'>Your digital alumni ID card (front and back), matching the admin <strong>Check Alumni ID</strong> page, is attached as PNG files.</p>";
    }

    return "<p style='font-size:14px;color:#374151;line-height:1.5;margin:0 0 12px 0;'>The photos you submitted for alumni ID verification (front and/or back) are attached to this email.</p>";
}
