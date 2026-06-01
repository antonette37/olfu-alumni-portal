<?php
/**
 * Build PNG attachments (front + back) for approval emails using GD.
 * Uses a TTF font when available (Windows/Linux paths); otherwise skips (no attachment).
 */
declare(strict_types=1);

/**
 * @param array<string, mixed> $u itcp row
 * @return array{0: string, 1: string}|null [frontPath, backPath] or null if GD/font unavailable
 */
function ad_approve_id_card_create_png_pair(array $u, int $userId): ?array
{
    if (!function_exists('imagecreatetruecolor')) {
        return null;
    }
    $font = ad_approve_id_card_ttf_path();
    if ($font === null) {
        error_log('ad_approve_id_card_create_png_pair: no TrueType font found. Add fonts/DejaVuSans.ttf or fonts/arial.ttf under public_html (see ad_approve_id_card_png.php).');
        return null;
    }

    $props = ad_approve_id_card_props($u);
    $tmp = sys_get_temp_dir();
    $front = tempnam($tmp, 'olfuidf_' . $userId . '_');
    $back = tempnam($tmp, 'olfuidb_' . $userId . '_');
    if ($front === false || $back === false) {
        return null;
    }

    $okF = ad_approve_id_card_draw_front($props, $font, $front);
    $okB = ad_approve_id_card_draw_back($props, $font, $back);
    if (!$okF || !$okB) {
        @unlink($front);
        @unlink($back);
        return null;
    }

    return [$front, $back];
}

/**
 * @param list<string> $paths
 */
function ad_approve_id_card_unlink_paths(array $paths): void
{
    foreach ($paths as $p) {
        if ($p !== '' && is_file($p)) {
            @unlink($p);
        }
    }
}

function ad_approve_id_card_ttf_path(): ?string
{
    $root = dirname(__DIR__);
    // Ship fonts under public_html/fonts/ (e.g. arial.ttf or DejaVuSans.ttf) so Linux hosting works without system fonts.
    $bundled = [
        $root . DIRECTORY_SEPARATOR . 'fonts' . DIRECTORY_SEPARATOR . 'DejaVuSans.ttf',
        $root . DIRECTORY_SEPARATOR . 'fonts' . DIRECTORY_SEPARATOR . 'arial.ttf',
        $root . DIRECTORY_SEPARATOR . 'fonts' . DIRECTORY_SEPARATOR . 'LiberationSans-Regular.ttf',
    ];
    foreach ($bundled as $p) {
        if (is_file($p) && is_readable($p)) {
            return $p;
        }
    }

    $windir = getenv('WINDIR');
    $candidates = array_filter([
        $windir ? $windir . DIRECTORY_SEPARATOR . 'Fonts' . DIRECTORY_SEPARATOR . 'arial.ttf' : null,
        $windir ? $windir . DIRECTORY_SEPARATOR . 'Fonts' . DIRECTORY_SEPARATOR . 'calibri.ttf' : null,
        'C:/Windows/Fonts/arial.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/dejavu-ttf/DejaVuSans.ttf',
        '/usr/share/fonts/TTF/DejaVuSans.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf',
        '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf',
    ]);
    foreach ($candidates as $p) {
        if ($p !== null && is_file($p) && is_readable($p)) {
            return $p;
        }
    }

    return null;
}

/**
 * @param array<string, mixed> $u
 * @return array<string, string>
 */
function ad_approve_id_card_props(array $u): array
{
    $fn = trim((string) ($u['firstname'] ?? ''));
    $ln = trim((string) ($u['lastname'] ?? ''));
    $mi = trim((string) ($u['middlename'] ?? ''));
    $ext = trim((string) ($u['name_ext'] ?? ''));
    $mid = '';
    if ($mi !== '') {
        $mid = (strlen($mi) <= 3 && strpos($mi, ' ') === false) ? $mi . '.' : $mi;
    }
    $parts = array_filter([$fn, $mid, $ln, $ext]);
    $fullName = mb_strtoupper(implode(' ', $parts), 'UTF-8');

    $sn = preg_replace('/\D/', '', (string) ($u['student_number'] ?? ''));
    $cardFmt = '';
    if ($sn !== '') {
        $sn = str_pad(substr($sn, 0, 16), 16, '0');
        $cardFmt = trim(chunk_split($sn, 4, ' '));
    }
    if ($cardFmt === '') {
        $cardFmt = 'PENDING — NO STUDENT NO.';
    }

    $program = trim((string) ($u['program'] ?? ''));
    if ($program === '') {
        $program = '—';
    }
    $batch = trim((string) ($u['year_graduated'] ?? ''));
    if ($batch === '') {
        $batch = '—';
    }
    $y = (int) preg_replace('/\D/', '', (string) ($u['year_graduated'] ?? ''));
    $valid = ($y >= 1990 && $y <= 2100) ? ('DECEMBER ' . ($y + 3)) : '—';

    $photoSrc = '';
    $raw = trim((string) ($u['photo'] ?? ''));
    if ($raw !== '') {
        if (stripos($raw, 'http') === 0) {
            $photoSrc = $raw;
        } else {
            $photoSrc = 'serve_profile_image.php?img=' . rawurlencode(basename($raw));
        }
    }

    $photoFile = '';
    if ($raw !== '' && stripos($raw, 'http') !== 0) {
        $root = dirname(__DIR__);
        $bn = basename($raw);
        foreach ([$root . '/photos/' . $bn, $root . '/uploads/' . $bn] as $try) {
            $try = str_replace('/', DIRECTORY_SEPARATOR, $try);
            if (is_readable($try)) {
                $photoFile = $try;
                break;
            }
        }
    }

    $ini = ($fn !== '' || $ln !== '')
        ? mb_strtoupper(mb_substr($fn, 0, 1, 'UTF-8') . mb_substr($ln, 0, 1, 'UTF-8'), 'UTF-8')
        : '?';

    $signatureFile = '';
    $rawSig = trim((string) ($u['signature_path'] ?? ''));
    if ($rawSig !== '' && stripos($rawSig, 'http') !== 0) {
        $norm = str_replace('\\', '/', $rawSig);
        if (strpos($norm, '..') === false && stripos($norm, 'signatures/') === 0) {
            $try = dirname(__DIR__) . '/uploads/' . $norm;
            $try = str_replace('/', DIRECTORY_SEPARATOR, $try);
            if (is_readable($try)) {
                $signatureFile = $try;
            }
        }
    }

    return [
        'fullName' => $fullName !== '' ? $fullName : '—',
        'cardFmt' => $cardFmt,
        'program' => $program,
        'batch' => $batch,
        'valid' => $valid,
        'address' => trim((string) ($u['address'] ?? '')) !== '' ? trim((string) $u['address']) : '—',
        'contact' => trim((string) ($u['personal_contact'] ?? '')) !== '' ? trim((string) $u['personal_contact']) : '—',
        'emergency' => trim((string) ($u['emergency_contact'] ?? '')) !== '' ? trim((string) $u['emergency_contact']) : '—',
        'photoSrc' => $photoSrc,
        'photoFile' => $photoFile,
        'signatureFile' => $signatureFile,
        'ini' => $ini,
    ];
}

/**
 * @param array<string, string> $props
 */
function ad_approve_id_card_draw_front(array $props, string $font, string $outPath): bool
{
    $W = 680;
    $H = 428;
    $im = imagecreatetruecolor($W, $H);
    if ($im === false) {
        return false;
    }
    imagealphablending($im, true);

    $green = imagecolorallocate($im, 13, 61, 34);
    $white = imagecolorallocate($im, 255, 255, 255);
    $gold = imagecolorallocate($im, 201, 168, 76);
    $muted = imagecolorallocate($im, 200, 220, 210);
    imagefilledrectangle($im, 0, 0, $W, $H, $green);
    imagefilledrectangle($im, 0, $H - 12, $W, $H, $gold);

    $box = 170;
    $bx = 36;
    $by = 56;
    $slot = imagecolorallocate($im, 26, 74, 42);
    imagefilledrectangle($im, $bx, $by, $bx + $box, $by + $box, $slot);

    $photoPath = $props['photoFile'];
    if ($photoPath !== '' && is_readable($photoPath)) {
        $ext = strtolower(pathinfo($photoPath, PATHINFO_EXTENSION));
        $src = null;
        if (in_array($ext, ['jpg', 'jpeg'], true) && function_exists('imagecreatefromjpeg')) {
            $src = @imagecreatefromjpeg($photoPath);
        } elseif ($ext === 'png' && function_exists('imagecreatefrompng')) {
            $src = @imagecreatefrompng($photoPath);
        }
        if (is_object($src) || is_resource($src)) {
            imagecopyresampled($im, $src, $bx, $by, 0, 0, $box, $box, imagesx($src), imagesy($src));
            imagedestroy($src);
        }
    } else {
        ad_approve_id_card_ttf($im, $font, 22, $bx + 55, $by + 70, $muted, $props['ini']);
    }

    ad_approve_id_card_ttf($im, $font, 13, 400, 48, $white, 'OLFU');
    ad_approve_id_card_ttf($im, $font, 28, 360, 78, $white, 'Alumni');
    ad_approve_id_card_ttf($im, $font, 24, 400, 118, $white, 'Card');

    $cx = (int) ($bx + $box / 2);
    ad_approve_id_card_ttf_center($im, $font, 15, $cx, 248, $white, $props['fullName'], $W - 80);
    ad_approve_id_card_ttf_center($im, $font, 11, $cx, 278, $muted, $props['cardFmt'], $W - 80);
    ad_approve_id_card_ttf_center($im, $font, 12, $cx, 302, $white, $props['program'], $W - 80);
    ad_approve_id_card_ttf_center($im, $font, 11, $cx, 328, $white, 'Batch ' . $props['batch'], $W - 80);

    ad_approve_id_card_ttf($im, $font, 9, 480, $H - 48, $muted, 'Valid until');
    ad_approve_id_card_ttf($im, $font, 11, 460, $H - 30, $white, $props['valid']);

    $ok = imagepng($im, $outPath, 6);
    imagedestroy($im);

    return $ok;
}

/**
 * @param array<string, string> $props
 */
function ad_approve_id_card_draw_back(array $props, string $font, string $outPath): bool
{
    $W = 680;
    $H = 428;
    $im = imagecreatetruecolor($W, $H);
    if ($im === false) {
        return false;
    }
    $white = imagecolorallocate($im, 255, 255, 255);
    $border = imagecolorallocate($im, 200, 200, 200);
    $green = imagecolorallocate($im, 74, 157, 106);
    $ink = imagecolorallocate($im, 30, 30, 30);
    $muted = imagecolorallocate($im, 80, 80, 80);
    imagefilledrectangle($im, 0, 0, $W, $H, $white);
    imagerectangle($im, 0, 0, $W - 1, $H - 1, $border);

    ad_approve_id_card_ttf($im, $font, 12, 120, 28, $green, 'OUR LADY OF FATIMA UNIVERSITY');
    ad_approve_id_card_ttf($im, $font, 9, 36, 72, $muted, 'Address:');
    ad_approve_id_card_ttf_wrap($im, $font, 10, 36, 88, $ink, $props['address'], $W - 72);
    ad_approve_id_card_ttf($im, $font, 9, 36, 168, $muted, 'Contact No.:');
    ad_approve_id_card_ttf($im, $font, 11, 120, 168, $ink, $props['contact']);
    ad_approve_id_card_ttf($im, $font, 9, 36, 202, $muted, 'Emergency No.:');
    ad_approve_id_card_ttf($im, $font, 11, 140, 202, $ink, $props['emergency']);
    ad_approve_id_card_ttf_wrap($im, $font, 7, 36, 248, $muted, 'Non-transferable. Tampering voids this card. Alumni Affairs — OLFU CCS.', $W - 72);

    $sigX = 36;
    $sigY = 302;
    $sigW = $W - 72;
    $sigH = 108;
    $sigBg = imagecolorallocate($im, 250, 250, 250);
    $sigBr = imagecolorallocate($im, 190, 190, 190);
    imagefilledrectangle($im, $sigX, $sigY, $sigX + $sigW, $sigY + $sigH, $sigBg);
    imagerectangle($im, $sigX, $sigY, $sigX + $sigW, $sigY + $sigH, $sigBr);
    ad_approve_id_card_ttf($im, $font, 7, $sigX + 10, $sigY + 16, $muted, "CARD HOLDER'S SIGNATURE");

    $sigPath = $props['signatureFile'] ?? '';
    if ($sigPath !== '' && is_readable($sigPath)) {
        $ext = strtolower(pathinfo($sigPath, PATHINFO_EXTENSION));
        $src = null;
        if ($ext === 'png' && function_exists('imagecreatefrompng')) {
            $src = @imagecreatefrompng($sigPath);
        } elseif (in_array($ext, ['jpg', 'jpeg'], true) && function_exists('imagecreatefromjpeg')) {
            $src = @imagecreatefromjpeg($sigPath);
        }
        if (is_object($src) || is_resource($src)) {
            $sx = imagesx($src);
            $sy = imagesy($src);
            if ($sx > 0 && $sy > 0) {
                $dstW = $sigW - 24;
                $dstH = $sigH - 40;
                $scale = min($dstW / $sx, $dstH / $sy, 1.0);
                $nw = max(1, (int) ($sx * $scale));
                $nh = max(1, (int) ($sy * $scale));
                $dx = (int) ($sigX + ($sigW - $nw) / 2);
                $dy = (int) ($sigY + $sigH - $nh - 10);
                imagealphablending($im, true);
                imagecopyresampled($im, $src, $dx, $dy, 0, 0, $nw, $nh, $sx, $sy);
            }
            imagedestroy($src);
        }
    }

    $ok = imagepng($im, $outPath, 6);
    imagedestroy($im);

    return $ok;
}

/** @param resource|GdImage $im */
function ad_approve_id_card_ttf($im, string $font, float $size, int $x, int $y, int $color, string $text): void
{
    $text = ad_approve_id_card_sanitize_utf8($text);
    if ($text === '') {
        return;
    }
    imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
}

/** @param resource|GdImage $im */
function ad_approve_id_card_ttf_center($im, string $font, float $size, int $cx, int $y, int $color, string $text, int $maxPx): void
{
    $text = ad_approve_id_card_sanitize_utf8($text);
    if ($text === '') {
        return;
    }
    $bbox = imagettfbbox($size, 0, $font, $text);
    if ($bbox === false) {
        return;
    }
    $tw = (int) abs($bbox[2] - $bbox[0]);
    if ($tw > $maxPx) {
        $text = mb_substr($text, 0, (int) (mb_strlen($text, 'UTF-8') * $maxPx / max(1, $tw)), 'UTF-8') . '…';
        $bbox = imagettfbbox($size, 0, $font, $text);
        if ($bbox === false) {
            return;
        }
        $tw = (int) abs($bbox[2] - $bbox[0]);
    }
    $x = (int) ($cx - $tw / 2);
    imagettftext($im, $size, 0, $x, $y, $color, $font, $text);
}

/** @param resource|GdImage $im */
function ad_approve_id_card_ttf_wrap($im, string $font, float $size, int $x, int $y, int $color, string $text, int $maxW): void
{
    $text = ad_approve_id_card_sanitize_utf8($text);
    $words = preg_split('/\s+/u', $text) ?: [];
    $line = '';
    $yy = $y;
    foreach ($words as $w) {
        $try = $line === '' ? $w : ($line . ' ' . $w);
        $bbox = imagettfbbox($size, 0, $font, $try);
        if ($bbox === false) {
            continue;
        }
        $tw = (int) abs($bbox[2] - $bbox[0]);
        if ($tw > $maxW && $line !== '') {
            imagettftext($im, $size, 0, $x, $yy, $color, $font, $line);
            $yy += (int) ($size * 1.45);
            $line = $w;
        } else {
            $line = $try;
        }
    }
    if ($line !== '') {
        imagettftext($im, $size, 0, $x, $yy, $color, $font, $line);
    }
}

function ad_approve_id_card_sanitize_utf8(string $s): string
{
    if ($s === '') {
        return '';
    }
    if (!mb_check_encoding($s, 'UTF-8')) {
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    }

    return $s;
}
