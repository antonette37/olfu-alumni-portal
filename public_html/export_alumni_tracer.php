<?php
/**
 * ISO / tracer export: alumni with employment and course alignment (admin).
 * Prefers PhpSpreadsheet when vendor/autoload.php exists; otherwise UTF-8 CSV for Excel.
 */
declare(strict_types=1);
session_start();
if (empty($_SESSION['admin_logged_in'])) {
    header('Location: al_login.php');
    exit;
}

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/includes/cps_alumni_lib.php';
require_once __DIR__ . '/includes/course_industry_map.php';

$conn = getDBConnection();
cps_ensure_schema($conn);

$sql = "SELECT i.id, i.student_number, i.firstname, i.lastname, i.email, i.program, i.year_graduated,
        i.employment_status, i.company, i.industry, i.position,
        v.cps_alumni_id, v.validity_date,
        (SELECT is_aligned_with_course FROM work_history w WHERE w.alumni_id = i.id ORDER BY w.id DESC LIMIT 1) AS wh_aligned,
        (SELECT is_private FROM work_history w WHERE w.alumni_id = i.id ORDER BY w.id DESC LIMIT 1) AS wh_private
        FROM itcp i
        LEFT JOIN verified_alumni v ON v.itcp_id = i.id
        WHERE LOWER(i.status) IN ('active','approved')
        ORDER BY i.lastname, i.firstname";

$res = $conn->query($sql);
$rows = [];
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $prog = (string)($r['program'] ?? '');
        $ind = (string)($r['industry'] ?? '');
        $aligned = cps_is_job_aligned_with_course($prog, $ind);
        if (isset($r['wh_aligned'])) {
            $aligned = ((int)$r['wh_aligned']) === 1;
        }
        $r['alignment_status'] = $aligned ? 'Aligned' : 'Not aligned';
        $r['tracer_industry_bucket'] = cps_tracer_industry_bucket($prog, $ind);
        $r['employment_for_report'] = !empty($r['wh_private']) && (int)$r['wh_private'] === 1
            ? '(Private — admin use only)'
            : (string)($r['employment_status'] ?? '');
        $rows[] = $r;
    }
    $res->close();
}
$conn->close();

$headers = ['ID', 'Student No', 'Last name', 'First name', 'Email', 'Program', 'Batch', 'CPS ID', 'Validity', 'Employment status', 'Company', 'Industry', 'Tracer industry category', 'Position', 'Alignment status', 'Work history private'];

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
    if (class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $col = 1;
        foreach ($headers as $h) {
            $sheet->setCellValueByColumnAndRow($col, 1, $h);
            $col++;
        }
        $row = 2;
        foreach ($rows as $r) {
            $sheet->setCellValueByColumnAndRow(1, $row, $r['id']);
            $sheet->setCellValueByColumnAndRow(2, $row, $r['student_number'] ?? '');
            $sheet->setCellValueByColumnAndRow(3, $row, $r['lastname'] ?? '');
            $sheet->setCellValueByColumnAndRow(4, $row, $r['firstname'] ?? '');
            $sheet->setCellValueByColumnAndRow(5, $row, $r['email'] ?? '');
            $sheet->setCellValueByColumnAndRow(6, $row, $r['program'] ?? '');
            $sheet->setCellValueByColumnAndRow(7, $row, $r['year_graduated'] ?? '');
            $sheet->setCellValueByColumnAndRow(8, $row, $r['cps_alumni_id'] ?? '');
            $sheet->setCellValueByColumnAndRow(9, $row, $r['validity_date'] ?? '');
            $sheet->setCellValueByColumnAndRow(10, $row, $r['employment_for_report']);
            $sheet->setCellValueByColumnAndRow(11, $row, $r['company'] ?? '');
            $sheet->setCellValueByColumnAndRow(12, $row, $r['industry'] ?? '');
            $sheet->setCellValueByColumnAndRow(13, $row, $r['tracer_industry_bucket'] ?? '');
            $sheet->setCellValueByColumnAndRow(14, $row, $r['position'] ?? '');
            $sheet->setCellValueByColumnAndRow(15, $row, $r['alignment_status']);
            $sheet->setCellValueByColumnAndRow(16, $row, !empty($r['wh_private']) && (int)$r['wh_private'] === 1 ? 'Yes' : 'No');
            $row++;
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="alumni_tracer_' . date('Y-m-d') . '.xlsx"');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="alumni_tracer_' . date('Y-m-d') . '.csv"');
echo "\xEF\xBB\xBF";
$out = fopen('php://output', 'w');
fputcsv($out, $headers);
foreach ($rows as $r) {
    fputcsv($out, [
        $r['id'],
        $r['student_number'] ?? '',
        $r['lastname'] ?? '',
        $r['firstname'] ?? '',
        $r['email'] ?? '',
        $r['program'] ?? '',
        $r['year_graduated'] ?? '',
        $r['cps_alumni_id'] ?? '',
        $r['validity_date'] ?? '',
        $r['employment_for_report'],
        $r['company'] ?? '',
        $r['industry'] ?? '',
        $r['tracer_industry_bucket'] ?? '',
        $r['position'] ?? '',
        $r['alignment_status'],
        !empty($r['wh_private']) && (int)$r['wh_private'] === 1 ? 'Yes' : 'No',
    ]);
}
fclose($out);
exit;
