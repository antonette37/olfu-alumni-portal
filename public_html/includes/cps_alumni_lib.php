<?php
/**
 * CPS Alumni ID, verified_alumni, pending_registrations, work_history — mysqli helpers.
 */
declare(strict_types=1);

require_once __DIR__ . '/mysqli_compat.php';
require_once __DIR__ . '/course_industry_map.php';

function cps_ensure_schema(mysqli $conn): void
{
    if (function_exists('mysqli_report')) {
        @mysqli_report(MYSQLI_REPORT_OFF);
    }
    @$conn->query("
        CREATE TABLE IF NOT EXISTS verified_alumni (
            itcp_id INT NOT NULL PRIMARY KEY,
            cps_alumni_id CHAR(19) NOT NULL,
            cps_alumni_id_raw CHAR(16) NOT NULL,
            validity_date DATE NOT NULL,
            campus_code VARCHAR(32) NULL,
            verified_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cps_raw (cps_alumni_id_raw),
            UNIQUE KEY uq_cps_fmt (cps_alumni_id),
            CONSTRAINT fk_verified_itcp FOREIGN KEY (itcp_id) REFERENCES itcp(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    @$conn->query("
        CREATE TABLE IF NOT EXISTS pending_registrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id VARCHAR(64) NOT NULL,
            itcp_id INT NOT NULL,
            registration_type VARCHAR(16) NOT NULL DEFAULT 'new',
            current_alumni_id VARCHAR(64) NULL,
            date_of_birth DATE NULL,
            campus_code VARCHAR(32) NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_pending_itcp (itcp_id),
            KEY idx_pending_student (student_id),
            KEY idx_pending_status (status),
            CONSTRAINT fk_pending_itcp FOREIGN KEY (itcp_id) REFERENCES itcp(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    @$conn->query("
        CREATE TABLE IF NOT EXISTS work_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            alumni_id INT NOT NULL,
            industry_type VARCHAR(150) NOT NULL DEFAULT '',
            job_title VARCHAR(200) NOT NULL DEFAULT '',
            company VARCHAR(200) NOT NULL DEFAULT '',
            is_aligned_with_course TINYINT(1) NOT NULL DEFAULT 0,
            is_private TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_wh_alumni (alumni_id),
            CONSTRAINT fk_wh_itcp FOREIGN KEY (alumni_id) REFERENCES itcp(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    @$conn->query("
        CREATE TABLE IF NOT EXISTS registrar_masterlist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id VARCHAR(64) NULL,
            full_name VARCHAR(255) NOT NULL,
            date_of_birth DATE NULL,
            program VARCHAR(255) NULL,
            campus_code VARCHAR(32) NULL,
            import_batch VARCHAR(64) NULL,
            imported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_ml_student (student_id),
            KEY idx_ml_name (full_name(120)),
            KEY idx_ml_dob (date_of_birth)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $cols = ['signature_path' => 'VARCHAR(255) NULL', 'data_privacy_consent_at' => 'DATETIME NULL'];
    $existing = [];
    $r = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'itcp'");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $existing[strtolower($row['COLUMN_NAME'])] = true;
        }
        $r->close();
    }
    foreach ($cols as $col => $def) {
        if (empty($existing[strtolower($col)])) {
            @$conn->query("ALTER TABLE itcp ADD COLUMN `$col` $def");
        }
    }
    // Backfill newer pending_registrations columns for dual-path registrations.
    @$conn->query("ALTER TABLE pending_registrations ADD COLUMN registration_type VARCHAR(16) NOT NULL DEFAULT 'new'");
    @$conn->query("ALTER TABLE pending_registrations ADD COLUMN current_alumni_id VARCHAR(64) NULL");
}

/**
 * Public site origin for links in emails (no trailing slash).
 */
function cps_site_origin(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host;
}

/**
 * Normalize student_id / student_number for comparison with registrar_masterlist.student_id.
 */
function cps_normalize_student_id_for_masterlist(string $studentId): string
{
    $s = strtolower(trim($studentId));

    return preg_replace('/[\s\-_]+/', '', $s) ?? '';
}

/**
 * When registrar_masterlist has at least one row, require a match on normalized student_id and date_of_birth.
 * If the table is empty (no import yet), returns true so local installs are not blocked.
 */
function cps_registrar_masterlist_match(mysqli $conn, string $normalizedStudentId, string $birthdayYmd): bool
{
    $r = $conn->query('SELECT COUNT(*) AS c FROM registrar_masterlist');
    if (!$r) {
        return true;
    }
    $c = (int) ($r->fetch_assoc()['c'] ?? 0);
    $r->close();
    if ($c === 0) {
        error_log('cps_registrar_masterlist_match: registrar_masterlist is empty; masterlist gate skipped');

        return true;
    }
    if ($normalizedStudentId === '' || $birthdayYmd === '' || $birthdayYmd === '0000-00-00') {
        return false;
    }
    $st = $conn->prepare(
        'SELECT 1 FROM registrar_masterlist
         WHERE REPLACE(REPLACE(REPLACE(LOWER(TRIM(COALESCE(student_id, \'\'))), \' \', \'\'), \'-\', \'\'), \'_\', \'\') = ?
           AND date_of_birth IS NOT NULL
           AND DATE(date_of_birth) = DATE(?)
         LIMIT 1'
    );
    if (!$st) {
        return false;
    }
    $st->bind_param('ss', $normalizedStudentId, $birthdayYmd);
    $st->execute();
    $ok = (bool) $st->get_result()->fetch_row();
    $st->close();

    return $ok;
}

function cps_format_id(string $raw16): string
{
    $raw16 = preg_replace('/\D/', '', $raw16) ?? '';
    $raw16 = str_pad(substr($raw16, 0, 16), 16, '0', STR_PAD_LEFT);
    return substr($raw16, 0, 4) . '-' . substr($raw16, 4, 4) . '-' . substr($raw16, 8, 4) . '-' . substr($raw16, 12, 4);
}

/**
 * Next 16-digit CPS id (string of digits). Uses MAX on stored raw values + 1.
 */
function cps_next_raw_id(mysqli $conn): string
{
    $max = '0000000000000000';
    $r = $conn->query("SELECT MAX(cps_alumni_id_raw) AS m FROM verified_alumni");
    if ($r) {
        $row = $r->fetch_assoc();
        if (!empty($row['m'])) {
            $max = $row['m'];
        }
        $r->close();
    }
    $n = (int)$max;
    if ($n < 1) {
        $n = 1;
    } else {
        $n++;
    }
    if ($n > 9999999999999999) {
        $n = 1;
    }
    return str_pad((string)$n, 16, '0', STR_PAD_LEFT);
}

/**
 * @param string|null $overrideRaw 16 digits only, admin correction
 * @return array{formatted: string, raw: string, validity_date: string}|null
 */
function cps_assign_to_alumni(mysqli $conn, int $itcpId, ?string $overrideRaw, ?string $campusCode): ?array
{
    $chk = $conn->prepare('SELECT 1 FROM verified_alumni WHERE itcp_id = ? LIMIT 1');
    if (!$chk) {
        return null;
    }
    $chk->bind_param('i', $itcpId);
    $chk->execute();
    $exists = $chk->get_result()->fetch_row();
    $chk->close();
    if ($exists) {
        $g = $conn->prepare('SELECT cps_alumni_id, cps_alumni_id_raw, validity_date FROM verified_alumni WHERE itcp_id = ?');
        if ($g) {
            $g->bind_param('i', $itcpId);
            $g->execute();
            $row = $g->get_result()->fetch_assoc();
            $g->close();
            if ($row) {
                return [
                    'formatted' => $row['cps_alumni_id'],
                    'raw' => $row['cps_alumni_id_raw'],
                    'validity_date' => $row['validity_date'],
                ];
            }
        }
        return null;
    }

    $raw = cps_next_raw_id($conn);
    if ($overrideRaw !== null && $overrideRaw !== '') {
        $digits = preg_replace('/\D/', '', $overrideRaw) ?? '';
        if (strlen($digits) === 16) {
            $raw = str_pad($digits, 16, '0', STR_PAD_LEFT);
        }
    }
    $formatted = cps_format_id($raw);

    $dup = $conn->prepare('SELECT itcp_id FROM verified_alumni WHERE cps_alumni_id_raw = ? LIMIT 1');
    if ($dup) {
        $dup->bind_param('s', $raw);
        $dup->execute();
        $other = $dup->get_result()->fetch_assoc();
        $dup->close();
        if ($other && (int)$other['itcp_id'] !== $itcpId) {
            $raw = cps_next_raw_id($conn);
            $formatted = cps_format_id($raw);
        }
    }

    $validity = (new DateTimeImmutable('now'))->modify('+3 years')->format('Y-m-d');
    $ins = $conn->prepare('INSERT INTO verified_alumni (itcp_id, cps_alumni_id, cps_alumni_id_raw, validity_date, campus_code, verified_at) VALUES (?, ?, ?, ?, ?, NOW())');
    if (!$ins) {
        return null;
    }
    $ins->bind_param('issss', $itcpId, $formatted, $raw, $validity, $campusCode);
    if (!$ins->execute()) {
        $ins->close();
        return null;
    }
    $ins->close();

    return ['formatted' => $formatted, 'raw' => $raw, 'validity_date' => $validity];
}

function cps_mark_pending_approved(mysqli $conn, int $itcpId): void
{
    $st = $conn->prepare("UPDATE pending_registrations SET status = 'approved' WHERE itcp_id = ?");
    if ($st) {
        $st->bind_param('i', $itcpId);
        $st->execute();
        $st->close();
    }
}

/**
 * Upsert one visible row for current employment line on profile (tracer / alignment).
 */
function cps_work_history_sync_from_profile(mysqli $conn, int $alumniId, string $program, string $industry, string $position, string $company, bool $isPrivate): void
{
    $aligned = cps_is_job_aligned_with_course($program, $industry) ? 1 : 0;
    $industry = substr($industry, 0, 150);
    $position = substr($position, 0, 200);
    $company = substr($company, 0, 200);
    $priv = $isPrivate ? 1 : 0;

    $sel = $conn->prepare('SELECT id FROM work_history WHERE alumni_id = ? ORDER BY id DESC LIMIT 1');
    if (!$sel) {
        return;
    }
    $sel->bind_param('i', $alumniId);
    $sel->execute();
    $row = null;
    $sgr = olfu_stmt_get_result($sel);
    if ($sgr && method_exists($sgr, 'fetch_assoc')) {
        $row = $sgr->fetch_assoc();
    }
    $sel->close();

    if ($row) {
        $up = $conn->prepare('UPDATE work_history SET industry_type = ?, job_title = ?, company = ?, is_aligned_with_course = ?, is_private = ? WHERE id = ?');
        if ($up) {
            $wid = (int)$row['id'];
            $up->bind_param('sssiii', $industry, $position, $company, $aligned, $priv, $wid);
            $up->execute();
            $up->close();
        }
        return;
    }

    $in = $conn->prepare('INSERT INTO work_history (alumni_id, industry_type, job_title, company, is_aligned_with_course, is_private) VALUES (?, ?, ?, ?, ?, ?)');
    if ($in) {
        $in->bind_param('isssii', $alumniId, $industry, $position, $company, $aligned, $priv);
        $in->execute();
        $in->close();
    }
}
