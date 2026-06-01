<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: al_login.php');
    exit();
}

require_once 'db_config.php';
$conn = getDBConnection();

// Get filter parameters
$report_type = $_GET['report'] ?? 'masterlist';
$batch_filter = $_GET['batch'] ?? '';
$course_filter = $_GET['course'] ?? '';
$status_filter = $_GET['status'] ?? '';
$employment_filter = $_GET['employment'] ?? '';

// Set default filters based on report type if no filters are set
if (empty($batch_filter) && empty($course_filter) && empty($status_filter) && empty($employment_filter)) {
    switch ($report_type) {
        case 'employment':
            // Show all employment statuses by default, but can be filtered
            break;
        case 'batch':
            // Show all batches by default
            break;
        case 'status':
            // Show all statuses by default
            break;
        case 'masterlist':
        default:
            // Show all records
            break;
    }
}

// Get report title and description based on type
$report_titles = [
    'masterlist' => ['Alumni Masterlist', 'Complete list of all alumni records'],
    'employment' => ['Employment Report', 'Alumni employment status and company information'],
    'batch' => ['Batch & Year Report', 'Alumni organized by graduation year/batch'],
    'status' => ['Account Status Report', 'Alumni account status and verification information']
];
$report_title = $report_titles[$report_type] ?? $report_titles['masterlist'];

// Build query based on filters
$where_conditions = [];
$params = [];
$param_types = '';

if ($batch_filter) {
    $where_conditions[] = "year_graduated = ?";
    $params[] = $batch_filter;
    $param_types .= 's';
}

if ($course_filter) {
    $where_conditions[] = "program LIKE ?";
    $params[] = "%$course_filter%";
    $param_types .= 's';
}

if ($status_filter) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if ($employment_filter) {
    $where_conditions[] = "employment_status = ?";
    $params[] = $employment_filter;
    $param_types .= 's';
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get unique values for filters
$batches_query = "SELECT DISTINCT year_graduated FROM itcp WHERE year_graduated IS NOT NULL AND year_graduated != '' ORDER BY year_graduated DESC";
$batches_result = $conn->query($batches_query);
$batches = [];
if ($batches_result) {
    while ($row = $batches_result->fetch_assoc()) {
        $batches[] = $row['year_graduated'];
    }
}

$courses_query = "SELECT DISTINCT program FROM itcp WHERE program IS NOT NULL AND program != '' ORDER BY program ASC";
$courses_result = $conn->query($courses_query);
$courses = [];
if ($courses_result) {
    while ($row = $courses_result->fetch_assoc()) {
        $courses[] = $row['program'];
    }
}

// Get report data
$sql = "SELECT id, student_number, firstname, lastname, middlename, email, program, year_graduated, 
        employment_status, company, position, status, date_joined 
        FROM itcp $where_clause ORDER BY lastname, firstname ASC";
$stmt = $conn->prepare($sql);
if ($stmt && !empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $report_data = [];
    while ($row = $result->fetch_assoc()) {
        $report_data[] = $row;
    }
    $stmt->close();
} else {
    $result = $conn->query($sql);
    $report_data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $report_data[] = $row;
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Reports - Admin Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="admin_page_patches.css" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet" />
    <style>
        body.admin-skin {
            min-height: 100vh;
        }
        .glassmorphism {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: 1rem;
            border: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.1);
        }
        @media print {
            @page {
                size: A4 landscape;
                margin: 10mm;
            }
            
            /* CRITICAL: Hide all scripts and their content FIRST - must be at the top */
            script {
                display: none !important;
                visibility: hidden !important;
                height: 0 !important;
                width: 0 !important;
                overflow: hidden !important;
                position: absolute !important;
                left: -9999px !important;
                font-size: 0 !important;
                line-height: 0 !important;
                opacity: 0 !important;
                content: none !important;
                text-indent: -9999px !important;
            }
            
            script * {
                display: none !important;
                visibility: hidden !important;
            }
            
            style:not([data-print]),
            link[rel="stylesheet"],
            noscript {
                display: none !important;
                visibility: hidden !important;
            }
            
            /* Force print colors */
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            html, body {
                width: 100% !important;
                height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            body {
                background: white !important;
                font-size: 10pt;
                margin: 0 !important;
                padding: 0 !important;
            }
            
            .no-print { 
                display: none !important; 
            }
            
            /* CRITICAL: Ensure main content area is visible */
            main {
                display: block !important;
                visibility: visible !important;
                position: static !important;
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
                left: 0 !important;
                top: 0 !important;
            }
            
            /* Ensure all containers are visible */
            .page,
            .max-w-7xl,
            .max-w-full {
                max-width: 100% !important;
                width: 100% !important;
                margin: 0 auto !important;
                padding: 0 !important;
                display: block !important;
                visibility: visible !important;
            }
            
            .glassmorphism { 
                box-shadow: none !important; 
                border: 1px solid #ddd !important;
                background: white !important;
                page-break-inside: avoid;
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                position: static !important;
            }
            
            /* CRITICAL: Ensure report content div is visible */
            div.glassmorphism:has(table),
            div.glassmorphism:has(#reportTable),
            .print-report-content {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
            }
            
            /* Remove scrollbars and force table to fit */
            .overflow-x-auto,
            .no-print-scroll,
            .print-table-container {
                overflow: visible !important;
                display: block !important;
                width: 100% !important;
                position: static !important;
            }
            
            .print-table,
            table {
                width: 100% !important;
                table-layout: fixed !important;
                border-collapse: collapse !important;
                font-size: 9pt !important;
                margin: 0 !important;
                display: table !important;
            }
            
            thead {
                display: table-header-group !important;
            }
            
            tbody {
                display: table-row-group !important;
            }
            
            tr {
                display: table-row !important;
                page-break-inside: avoid;
            }
            
            th, td {
                display: table-cell !important;
                font-size: 9pt !important;
                padding: 4px 6px !important;
                word-wrap: break-word !important;
                white-space: normal !important;
                border: 1px solid #ddd !important;
                overflow: hidden !important;
            }
            
            .print-cell {
                white-space: normal !important;
                word-break: break-word !important;
            }
            
            th {
                background-color: #f5f5f5 !important;
                font-weight: bold !important;
            }
            
            /* Column width constraints for print */
            .print-col-id { width: 8% !important; }
            .print-col-name { width: 15% !important; }
            .print-col-email { width: 18% !important; }
            .print-col-program { width: 15% !important; }
            .print-col-batch { width: 8% !important; }
            .print-col-employment { width: 12% !important; }
            .print-col-company { width: 14% !important; }
            .print-col-status { width: 10% !important; }
            
            /* Ensure table rows don't break across pages */
            tr {
                page-break-inside: avoid;
            }
            
            /* Hide header and sidebar - but be specific */
            header,
            aside,
            .ad-header-universal,
            .ad-sidebar-universal,
            #sidebar,
            body > header,
            body > aside {
                display: none !important;
                visibility: hidden !important;
                height: 0 !important;
                width: 0 !important;
                overflow: hidden !important;
                position: absolute !important;
                left: -9999px !important;
                font-size: 0 !important;
                line-height: 0 !important;
                opacity: 0 !important;
            }
            
            /* Hide all content inside header and sidebar */
            header *,
            aside *,
            .ad-header-universal *,
            .ad-sidebar-universal *,
            #sidebar * {
                display: none !important;
                visibility: hidden !important;
            }
            
            /* Hide all HTML comments and script tags - CRITICAL */
            script,
            script *,
            style:not([data-print]),
            link[rel="stylesheet"],
            noscript {
                display: none !important;
                visibility: hidden !important;
                height: 0 !important;
                width: 0 !important;
                overflow: hidden !important;
                position: absolute !important;
                left: -9999px !important;
                font-size: 0 !important;
                line-height: 0 !important;
                opacity: 0 !important;
            }
            
            /* Hide any text content that might be from script tags */
            body::before,
            body::after {
                display: none !important;
                content: none !important;
            }
            
            /* Hide any elements that might contain JavaScript code as text */
            [onclick],
            [onload],
            [onsubmit],
            [onchange] {
                display: none !important;
            }
            
            /* Ensure only report content is visible - hide everything else */
            body > script,
            body > style,
            head,
            head * {
                display: none !important;
                visibility: hidden !important;
            }
            
            /* Hide all non-print elements more aggressively */
            .no-print,
            .filter-section,
            .report-tabs,
            .report-actions,
            button,
            a[href*="export"],
            a[href*="pdf"],
            a[href*="excel"],
            a[href*="csv"],
            form,
            select,
            input,
            label {
                display: none !important;
                visibility: hidden !important;
            }
            
            /* Hide page header and description */
            h1,
            .mb-6:has(h1),
            .text-3xl {
                display: none !important;
            }
            
            /* Hide everything except main */
            body > header,
            body > aside {
                display: none !important;
            }
            
            /* Ensure main and container are visible */
            main {
                display: block !important;
                visibility: visible !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            main > div.page,
            main > div.max-w-7xl {
                display: block !important;
                visibility: visible !important;
            }
            
            /* Hide specific non-report sections */
            main > div.page > div.mb-6.no-print,
            main > div.max-w-7xl > div.mb-6.no-print,
            main > div.page > div.filter-section,
            main > div.max-w-7xl > div.filter-section,
            main > div.page > div.report-tabs,
            main > div.max-w-7xl > div.report-tabs {
                display: none !important;
            }
            
            /* CRITICAL: Ensure report content is visible */
            .print-report-content,
            div.glassmorphism.print-report-content {
                display: block !important;
                visibility: visible !important;
                page-break-inside: avoid;
            }
            
            /* Hide the preview title and action buttons inside report content */
            .print-report-content > div.flex.items-center.justify-between,
            .print-report-content > .rpt-ph.no-print,
            .print-report-content > div.no-print {
                display: none !important;
            }
            
            /* CRITICAL: Ensure print header is visible */
            .print-header {
                display: block !important;
                visibility: visible !important;
            }
            
            /* CRITICAL: Ensure table container is visible */
            .print-table-container {
                display: block !important;
                visibility: visible !important;
                width: 100% !important;
                overflow: visible !important;
            }
            
            /* CRITICAL: Ensure table is visible */
            .print-table-container table,
            table#reportTable,
            .print-report-content table {
                display: table !important;
                visibility: visible !important;
                width: 100% !important;
            }
            
            /* CRITICAL: Ensure footer is visible */
            .print-footer {
                display: block !important;
                visibility: visible !important;
            }
            
            /* Main content must be visible and properly positioned */
            main {
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
                position: static !important;
                display: block !important;
                visibility: visible !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
            }
            
            /* Remove padding/margin from main element classes */
            main.admin-main,
            main.pt-24 {
                padding-top: 0 !important;
            }
            
            main.admin-main,
            main.ml-16 {
                margin-left: 0 !important;
            }
            
            main.p-4 {
                padding: 0 !important;
            }
            
            main.max-w-full {
                max-width: 100% !important;
            }
            
            /* Ensure report container is visible */
            .page,
            .max-w-7xl {
                max-width: 100% !important;
                margin: 0 auto !important;
                padding: 0 !important;
                display: block !important;
            }
            
            /* Ensure report content and all its children are visible */
            .print-report-content,
            div.glassmorphism.print-report-content {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
            }
            
            .print-report-content *,
            div.glassmorphism.print-report-content * {
                visibility: visible !important;
            }
            
            .print-report-content .no-print {
                display: none !important;
            }
            
            /* Report header styling */
            .text-2xl {
                font-size: 14pt !important;
            }
            
            .text-lg {
                font-size: 11pt !important;
            }
            
            .text-sm {
                font-size: 9pt !important;
            }
            
            .text-xs {
                font-size: 8pt !important;
            }
            
            /* ABSOLUTE FALLBACK: If nothing else works, show everything */
            body > * {
                display: block !important;
            }
            
            body main {
                display: block !important;
                visibility: visible !important;
            }
            
            body main > div {
                display: block !important;
                visibility: visible !important;
            }
            
            body main div.glassmorphism {
                display: block !important;
                visibility: visible !important;
            }
            
            body main div.glassmorphism table {
                display: table !important;
                visibility: visible !important;
            }
            
            /* ULTIMATE FALLBACK: Show everything that's not explicitly hidden */
            body * {
                visibility: visible !important;
            }
            
            body .no-print,
            body header,
            body aside {
                display: none !important;
                visibility: hidden !important;
            }
            
            /* Ensure report section is always visible */
            body main,
            body main div,
            body main div.glassmorphism.print-report-content,
            body main div.glassmorphism.print-report-content div,
            body main div.glassmorphism.print-report-content table,
            .print-header {
                display: block !important;
                visibility: visible !important;
            }
            
            body main div.glassmorphism.print-report-content table,
            body main table,
            table#reportTable {
                display: table !important;
                width: 100% !important;
            }
            
            /* Ensure table container and all children are visible */
            .print-table-container,
            .print-table-container * {
                visibility: visible !important;
            }
            
            .print-table-container table {
                display: table !important;
            }
        }
    </style>
</head>
<body class="admin-skin">
    <!-- Include Admin Universal Header -->
    <?php include 'ad_header_universal.php'; ?>
    <?php include 'ad_sidebar_universal.php'; ?>

    <main class="admin-main">
        <div class="page">
            <div class="no-print fade-in" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:22px;">
                <div>
                    <h1 style="font-size:1.5rem;font-weight:700;color:var(--ink);">Reports</h1>
                    <p style="font-size:.82rem;color:var(--muted);margin-top:2px;">Generate, preview, and export alumni reports</p>
                </div>
            </div>

            <section class="panel no-print report-tabs fade-in">
                <div class="panel-head" style="border-bottom:none;padding-bottom:8px">
                    <h2 class="panel-title"><i class="fas fa-file-alt"></i> Select Report Type</h2>
                </div>
                <div style="padding:0 22px 16px">
                <div class="report-type-tabs">
                    <a href="ad_reports.php?report=masterlist<?php echo $batch_filter ? '&batch=' . urlencode($batch_filter) : ''; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $employment_filter ? '&employment=' . urlencode($employment_filter) : ''; ?>" 
                       class="rtt<?php echo $report_type === 'masterlist' ? ' active' : ''; ?>">
                        <i class="fas fa-list"></i> Alumni Masterlist
                    </a>
                    <a href="ad_reports.php?report=employment<?php echo $batch_filter ? '&batch=' . urlencode($batch_filter) : ''; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $employment_filter ? '&employment=' . urlencode($employment_filter) : ''; ?>" 
                       class="rtt<?php echo $report_type === 'employment' ? ' active' : ''; ?>">
                        <i class="fas fa-briefcase"></i> Employment Report
                    </a>
                    <a href="ad_reports.php?report=batch<?php echo $batch_filter ? '&batch=' . urlencode($batch_filter) : ''; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $employment_filter ? '&employment=' . urlencode($employment_filter) : ''; ?>" 
                       class="rtt<?php echo $report_type === 'batch' ? ' active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i> Batch &amp; Year Report
                    </a>
                    <a href="ad_reports.php?report=status<?php echo $batch_filter ? '&batch=' . urlencode($batch_filter) : ''; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?><?php echo $employment_filter ? '&employment=' . urlencode($employment_filter) : ''; ?>" 
                       class="rtt<?php echo $report_type === 'status' ? ' active' : ''; ?>">
                        <i class="fas fa-user-check"></i> Account Status Report
                    </a>
                </div>
                <div class="rpt-meta">
                    <h3><?php echo htmlspecialchars($report_title[0]); ?></h3>
                    <p><?php echo htmlspecialchars($report_title[1]); ?></p>
                </div>
                </div>
            </section>

            <section class="rpt-filter no-print filter-section fade-in">
                <h2><i class="fas fa-filter"></i> Filter Options</h2>
                <form method="GET" action="ad_reports.php">
                    <input type="hidden" name="report" value="<?php echo htmlspecialchars($report_type); ?>">
                    <div class="rpt-grid">
                    <div class="fi">
                        <label for="rpt_batch">Batch / Year</label>
                        <select id="rpt_batch" name="batch">
                            <option value="">All Batches</option>
                            <?php foreach ($batches as $batch): ?>
                                <option value="<?php echo htmlspecialchars($batch); ?>" <?php echo $batch_filter === $batch ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($batch); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="fi">
                        <label for="rpt_course">Course / Program</label>
                        <select id="rpt_course" name="course">
                            <option value="">All Courses</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?php echo htmlspecialchars($course); ?>" <?php echo $course_filter === $course ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($course); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="fi">
                        <label for="rpt_status">Account Status</label>
                        <select id="rpt_status" name="status">
                            <option value="">All Statuses</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                            <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archived</option>
                        </select>
                    </div>

                    <div class="fi">
                        <label for="rpt_employment">Employment Status</label>
                        <select id="rpt_employment" name="employment">
                            <option value="">All Employment</option>
                            <option value="Employed" <?php echo $employment_filter === 'Employed' ? 'selected' : ''; ?>>Employed</option>
                            <option value="Unemployed" <?php echo $employment_filter === 'Unemployed' ? 'selected' : ''; ?>>Unemployed</option>
                            <option value="Self-Employed" <?php echo $employment_filter === 'Self-Employed' ? 'selected' : ''; ?>>Self-Employed</option>
                            <option value="Student" <?php echo $employment_filter === 'Student' ? 'selected' : ''; ?>>Student</option>
                        </select>
                    </div>

                    <div class="rpt-actions-row">
                        <button type="submit" class="btn-cr">
                            <i class="fas fa-search"></i> Generate Report
                        </button>
                        <a href="ad_reports.php" class="btn-ghost">
                            <i class="fas fa-redo"></i> Reset Filters
                        </a>
                    </div>
                    </div>
                </form>
            </section>

            <!-- Report Preview Section -->
            <?php if (!empty($report_data)): ?>
            <div class="glassmorphism print-report-content rpt-preview fade-in">
                <div class="rpt-ph no-print">
                    <div class="rpt-title">
                        <i class="fas fa-table"></i> Report Preview
                        <span style="font-weight:500;color:var(--muted);font-size:0.8rem">(<?php echo count($report_data); ?> records)</span>
                    </div>
                    <div class="rpt-actions report-actions">
                        <button type="button" onclick="window.print()" class="btn-rpt btn-print">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <button type="button" onclick="downloadPDFReport()" class="btn-rpt btn-pdf">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                        <a href="ad_export_report.php?<?php echo http_build_query($_GET); ?>&format=excel" class="btn-rpt btn-excel">
                            <i class="fas fa-file-excel"></i> Excel
                        </a>
                        <a href="ad_export_report.php?<?php echo http_build_query($_GET); ?>&format=csv" class="btn-rpt btn-csv">
                            <i class="fas fa-file-csv"></i> CSV
                        </a>
                        <a href="export_alumni_tracer.php" class="btn-rpt btn-tracer no-print" title="Employment + course alignment (ISO tracer)">
                            <i class="fas fa-chart-line"></i> Tracer
                        </a>
                    </div>
                </div>

                <div class="print-header rpt-print-hd">
                        <h3>Our Lady of Fatima University</h3>
                        <p>College of Computer Studies — Alumni Portal</p>
                        <p style="margin-top:8px;opacity:.95"><?php echo htmlspecialchars($report_title[0]); ?></p>
                        <p>Generated: <?php echo date('F d, Y h:i A'); ?></p>
                        <?php if ($batch_filter || $course_filter || $status_filter || $employment_filter): ?>
                        <p style="font-size:0.72rem;margin-top:6px;opacity:.85">
                            Filters: 
                            <?php 
                            $filters = [];
                            if ($batch_filter) $filters[] = "Batch: $batch_filter";
                            if ($course_filter) $filters[] = "Course: $course_filter";
                            if ($status_filter) $filters[] = "Status: $status_filter";
                            if ($employment_filter) $filters[] = "Employment: $employment_filter";
                            echo implode(', ', $filters);
                            ?>
                        </p>
                        <?php endif; ?>
                </div>

                <div class="overflow-x-auto no-print-scroll print-table-container rpt-tbl-wrap">
                    <table class="print-table rpt-tbl" id="reportTable">
                        <thead>
                            <tr>
                                <th class="print-col-id">ID</th>
                                <th class="print-col-name">Full Name</th>
                                <th class="print-col-email">Email</th>
                                <th class="print-col-program">Program</th>
                                <th class="print-col-batch">Batch</th>
                                <th class="print-col-employment">Employment</th>
                                <th class="print-col-company">Company</th>
                                <th class="print-col-status">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data as $row): ?>
                            <?php
                                $st = strtolower((string)($row['status'] ?? ''));
                                $badgeClass = 'badge-inactive';
                                if ($st === 'active') { $badgeClass = 'badge-active'; }
                                elseif ($st === 'pending') { $badgeClass = 'badge-pending'; }
                                elseif ($st === 'rejected') { $badgeClass = 'badge-rejected'; }
                            ?>
                            <tr>
                                <td class="print-cell"><?php echo htmlspecialchars($row['student_number'] ?? 'N/A'); ?></td>
                                <td class="print-cell"><?php
                                    $fullname = trim(($row['firstname'] ?? '') . ' ' . ($row['middlename'] ?? '') . ' ' . ($row['lastname'] ?? ''));
                                    echo htmlspecialchars($fullname);
                                    ?></td>
                                <td class="print-cell"><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></td>
                                <td class="print-cell"><?php echo htmlspecialchars($row['program'] ?? 'N/A'); ?></td>
                                <td class="print-cell"><?php echo htmlspecialchars($row['year_graduated'] ?? 'N/A'); ?></td>
                                <td class="print-cell"><?php echo htmlspecialchars($row['employment_status'] ?? 'N/A'); ?></td>
                                <td class="print-cell"><?php echo htmlspecialchars($row['company'] ?? 'N/A'); ?></td>
                                <td class="print-cell">
                                    <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars(ucfirst($row['status'] ?? 'N/A')); ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

                <!-- Report Footer (for print) -->
                <div class="mt-6 pt-4 border-t border-gray-200 text-center text-xs text-gray-500 print-footer">
                    <p>Confidential - OLFU Alumni Office | Generated by: <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'System Administrator'); ?></p>
                    <p class="mt-1">This report contains <?php echo count($report_data); ?> alumni record(s)</p>
                </div>
            </div>
            <?php else: ?>
            <div class="panel empty fade-in">
                <i class="fas fa-inbox"></i>
                <h3>No Data Found</h3>
                <p>Try adjusting your filter criteria to generate a report.</p>
            </div>
            <?php endif; ?>
        </div>
    </main>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
    // Print handler - ensure content is visible before printing
    function handlePrint() {
        // Force visibility of all report content
        const reportContent = document.querySelector('.print-report-content');
        const reportTable = document.getElementById('reportTable');
        
        if (reportContent) {
            reportContent.style.display = 'block';
            reportContent.style.visibility = 'visible';
        }
        
        if (reportTable) {
            reportTable.style.display = 'table';
            reportTable.style.visibility = 'visible';
        }
        
        // Small delay to ensure styles are applied
        setTimeout(function() {
            window.print();
        }, 100);
    }
    
    // PDF Download function - uses server export for clearer output
    function downloadPDFReport() {
        const reportTable = document.getElementById('reportTable');
        if (!reportTable) {
            alert('No report data available to download.');
            return;
        }
        const q = new URLSearchParams(window.location.search);
        q.set('format', 'pdf');
        window.open('ad_export_report.php?' + q.toString(), '_blank');
    }
    
    // Override print button
    document.addEventListener('DOMContentLoaded', function() {
        const printBtn = document.querySelector('button[onclick="window.print()"]');
        if (printBtn) {
            printBtn.setAttribute('onclick', 'handlePrint(); return false;');
        }
    });
    </script>
</body>
</html>
