<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: al_login.php');
    exit();
}

require_once 'db_config.php';
$conn = getDBConnection();

// Get filter parameters
$format = $_GET['format'] ?? 'csv';
$report_type = $_GET['report'] ?? 'masterlist';
$batch_filter = $_GET['batch'] ?? '';
$course_filter = $_GET['course'] ?? '';
$status_filter = $_GET['status'] ?? '';
$employment_filter = $_GET['employment'] ?? '';

// Build query
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

$sql = "SELECT student_number, firstname, lastname, middlename, email, program, year_graduated, 
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
}
else {
    $result = $conn->query($sql);
    $report_data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $report_data[] = $row;
        }
    }
}

$conn->close();

// Log export activity
$admin_name = $_SESSION['admin_name'] ?? 'System Administrator';
$export_info = "Admin: $admin_name exported " . count($report_data) . " records in $format format";
error_log("REPORT EXPORT: $export_info");

// Generate filename
$filename = "alumni_report_" . date('Y-m-d_His') . "." . $format;

if ($format === 'csv') {
    // CSV Export
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // Add BOM for UTF-8
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Header row
    fputcsv($output, [
        'Student ID',
        'First Name',
        'Middle Name',
        'Last Name',
        'Email',
        'Program',
        'Year Graduated',
        'Employment Status',
        'Company',
        'Position',
        'Account Status',
        'Date Joined'
    ]);

    // Data rows
    foreach ($report_data as $row) {
        fputcsv($output, [
            $row['student_number'] ?? '',
            $row['firstname'] ?? '',
            $row['middlename'] ?? '',
            $row['lastname'] ?? '',
            $row['email'] ?? '',
            $row['program'] ?? '',
            $row['year_graduated'] ?? '',
            $row['employment_status'] ?? '',
            $row['company'] ?? '',
            $row['position'] ?? '',
            $row['status'] ?? '',
            $row['date_joined'] ?? ''
        ]);
    }

    fclose($output);
    exit;


}
elseif ($format === 'excel') {
    // Excel Export (CSV format that Excel can open)
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // Add BOM for UTF-8
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Header row
    fputcsv($output, [
        'Student ID',
        'First Name',
        'Middle Name',
        'Last Name',
        'Email',
        'Program',
        'Year Graduated',
        'Employment Status',
        'Company',
        'Position',
        'Account Status',
        'Date Joined'
    ]);

    // Data rows
    foreach ($report_data as $row) {
        fputcsv($output, [
            $row['student_number'] ?? '',
            $row['firstname'] ?? '',
            $row['middlename'] ?? '',
            $row['lastname'] ?? '',
            $row['email'] ?? '',
            $row['program'] ?? '',
            $row['year_graduated'] ?? '',
            $row['employment_status'] ?? '',
            $row['company'] ?? '',
            $row['position'] ?? '',
            $row['status'] ?? '',
            $row['date_joined'] ?? ''
        ]);
    }

    fclose($output);
    exit;


}
elseif ($format === 'pdf') {
    // PDF Export - Generate HTML page with PDF download functionality
    // Get report title
    $report_titles = [
        'masterlist' => 'Alumni Masterlist',
        'employment' => 'Employment Report',
        'batch' => 'Batch & Year Report',
        'status' => 'Account Status Report'
    ];
    $report_title = $report_titles[$report_type] ?? 'Alumni Report';

    // Generate filename
    $filename = "alumni_report_" . date('Y-m-d_His') . ".pdf";

    // Output HTML with PDF generation script
    header('Content-Type: text/html; charset=utf-8');
?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($report_title); ?></title>
        <style>
            @page {
                size: A4 landscape;
                margin: 10mm;
            }
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: Arial, sans-serif;
                font-size: 9pt;
                color: #000;
                background: white;
                padding: 0;
                margin: 0;
            }
            
            .report-header {
                text-align: center;
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 2px solid #333;
            }
            
            .report-header h1 {
                font-size: 18pt;
                font-weight: bold;
                margin-bottom: 5px;
                color: #000;
            }
            
            .report-header h2 {
                font-size: 12pt;
                color: #333;
                margin-bottom: 10px;
            }
            
            .report-header p {
                font-size: 9pt;
                color: #666;
                margin: 3px 0;
            }
            
            .report-filters {
                font-size: 8pt;
                color: #555;
                margin-bottom: 15px;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
                font-size: 8pt;
                table-layout: fixed;
            }
            
            th, td {
                border: 1px solid #333;
                padding: 5px;
                text-align: left;
                word-wrap: break-word;
            }
            
            th {
                background-color: #f0f0f0;
                font-weight: bold;
                font-size: 8pt;
            }
            
            tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            
            .report-footer {
                margin-top: 20px;
                padding-top: 10px;
                border-top: 1px solid #333;
                text-align: center;
                font-size: 8pt;
                color: #666;
            }
            
            @media print {
                body {
                    margin: 0;
                    padding: 0;
                }
            }
        </style>
    </head>
    <body>
        <div class="report-header">
            <h1>Our Lady of Fatima University</h1>
            <h2>College of Computer Studies - Alumni Portal</h2>
            <p><strong><?php echo htmlspecialchars($report_title); ?></strong></p>
            <p>Generated: <?php echo date('F d, Y h:i A'); ?></p>
            <?php if ($batch_filter || $course_filter || $status_filter || $employment_filter): ?>
            <div class="report-filters">
                <p>Filters: 
                <?php
        $filters = [];
        if ($batch_filter)
            $filters[] = "Batch: $batch_filter";
        if ($course_filter)
            $filters[] = "Course: $course_filter";
        if ($status_filter)
            $filters[] = "Status: $status_filter";
        if ($employment_filter)
            $filters[] = "Employment: $employment_filter";
        echo implode(', ', $filters);
?>
                </p>
            </div>
            <?php
    endif; ?>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 8%;">ID</th>
                    <th style="width: 15%;">Full Name</th>
                    <th style="width: 18%;">Email</th>
                    <th style="width: 15%;">Program</th>
                    <th style="width: 8%;">Batch</th>
                    <th style="width: 12%;">Employment</th>
                    <th style="width: 14%;">Company</th>
                    <th style="width: 10%;">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($report_data)): ?>
                    <?php foreach ($report_data as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['student_number'] ?? 'N/A'); ?></td>
                        <td><?php
            $fullname = trim(($row['firstname'] ?? '') . ' ' . ($row['middlename'] ?? '') . ' ' . ($row['lastname'] ?? ''));
            echo htmlspecialchars($fullname);
?></td>
                        <td><?php echo htmlspecialchars($row['email'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['program'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['year_graduated'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['employment_status'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($row['company'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars(ucfirst($row['status'] ?? 'N/A')); ?></td>
                    </tr>
                    <?php
        endforeach; ?>
                <?php
    else: ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 20px;">No data found</td>
                    </tr>
                <?php
    endif; ?>
            </tbody>
        </table>
        
        <div class="report-footer">
            <p>Confidential - OLFU Alumni Office | Generated by: <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'System Administrator'); ?></p>
            <p>This report contains <?php echo count($report_data); ?> alumni record(s)</p>
        </div>
        
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
        <script>
            // Generate and download PDF automatically
            window.onload = function() {
                // Wait for page to fully render
                setTimeout(function() {
                    const { jsPDF } = window.jspdf;
                    const element = document.body;
                    const filename = '<?php echo $filename; ?>';
                    
                    // Show loading message AFTER page is rendered, but hide it before capture
                    const loadingDiv = document.createElement('div');
                    loadingDiv.id = 'pdf-loading';
                    loadingDiv.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.9);display:flex;align-items:center;justify-content:center;font-size:18px;z-index:9999;';
                    loadingDiv.textContent = 'Generating PDF... Please wait.';
                    document.body.appendChild(loadingDiv);
                    
                    // Wait a bit more, then hide loading message before capture
                    setTimeout(function() {
                        // Hide loading message before capturing
                        loadingDiv.style.display = 'none';
                        
                        // Small delay to ensure loading message is hidden
                        setTimeout(function() {
                            html2canvas(element, {
                                scale: 2,
                                useCORS: true,
                                logging: false,
                                windowWidth: element.scrollWidth,
                                windowHeight: element.scrollHeight,
                                ignoreElements: function(element) {
                                    return element.id === 'pdf-loading';
                                }
                            }).then(function(canvas) {
                                const imgData = canvas.toDataURL('image/png');
                                const pdf = new jsPDF('landscape', 'mm', 'a4');
                                
                                const imgWidth = 297; // A4 width in mm (landscape)
                                const imgHeight = (canvas.height * imgWidth) / canvas.width;
                                
                                let heightLeft = imgHeight;
                                let position = 0;
                                
                                pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                                heightLeft -= 210; // A4 height in mm
                                
                                while (heightLeft > 0) {
                                    position = heightLeft - imgHeight;
                                    pdf.addPage();
                                    pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                                    heightLeft -= 210;
                                }
                                
                                // Download the PDF
                                pdf.save(filename);
                                
                                // Remove loading message and close window after download
                                if (loadingDiv.parentNode) {
                                    loadingDiv.parentNode.removeChild(loadingDiv);
                                }
                                setTimeout(function() {
                                    window.close();
                                }, 500);
                            }).catch(function(error) {
                                console.error('Error generating PDF:', error);
                                if (loadingDiv.parentNode) {
                                    loadingDiv.parentNode.removeChild(loadingDiv);
                                }
                                alert('Error generating PDF. Please try using the Print button and save as PDF instead.');
                            });
                        }, 100);
                    }, 300);
                }, 500);
            };
        </script>
    </body>
    </html>
    <?php
    exit;
}
else {
    header('Location: ad_reports.php');
    exit;
}
