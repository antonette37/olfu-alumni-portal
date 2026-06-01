<?php
require_once 'db_config.php';
$conn = getDBConnection();

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: al_homepage.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if user is admin
$sql = "SELECT role FROM itcp WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || $user['role'] !== 'admin') {
    header("Location: al_directory.php");
    exit();
}

$exportFormat = $_GET['export'] ?? 'csv';

// Apply same filtering logic as main directory
$whereClauses = ["consent = 1"];
$params = [];
$types = '';

// Handle multi-select filters
$multiSelectValues = $_GET['multiSelect'] ?? [];
if (!empty($multiSelectValues)) {
    $multiSelectConditions = [];
    foreach ($multiSelectValues as $filterKey => $values) {
        $filterMap = [
            'role' => 'previous_role',
            'year_graduation' => 'year_graduated',
            'house' => 'campus',
            'current_city' => 'address',
            'company' => 'company',
            'designation' => 'position',
            'work_industry' => 'industry',
            'other_institute' => 'club_involvement',
            'other_degree' => 'post_grad'
        ];
        
        if (isset($filterMap[$filterKey]) && !empty($values)) {
            $column = $filterMap[$filterKey];
            $placeholders = str_repeat('?,', count($values) - 1) . '?';
            $multiSelectConditions[] = "$column IN ($placeholders)";
            foreach ($values as $value) {
                $params[] = $value;
                $types .= 's';
            }
        }
    }
    if (!empty($multiSelectConditions)) {
        $whereClauses[] = '(' . implode(' OR ', $multiSelectConditions) . ')';
    }
}

// Handle keyword search
$keyword = trim($_GET['keyword'] ?? '');
if ($keyword !== '') {
    $searchFields = [
        'firstname', 'lastname', 'middlename',
        'program', 'company', 'position', 'address',
        'year_graduated', 'campus', 'industry', 'previous_role'
    ];
    
    $searchConditions = [];
    foreach ($searchFields as $field) {
        $searchConditions[] = "$field LIKE ?";
        $params[] = "%$keyword%";
        $types .= 's';
    }
    
    $whereClauses[] = '(' . implode(' OR ', $searchConditions) . ')';
}

// Handle single filter
$filter = $_GET['filter'] ?? '';
$filterValue = $_GET['filterValue'] ?? '';
if ($filter !== '' && $filterValue !== '') {
    $filterMap = [
        'role' => 'previous_role',
        'year_graduation' => 'year_graduated',
        'house' => 'campus',
        'current_city' => 'address',
        'company' => 'company',
        'designation' => 'position',
        'work_industry' => 'industry',
        'other_institute' => 'club_involvement',
        'other_degree' => 'post_grad'
    ];
    
    if (isset($filterMap[$filter])) {
        $column = $filterMap[$filter];
        if ($filter === 'current_city') {
            $whereClauses[] = "$column LIKE ?";
            $params[] = "$filterValue,%";
            $types .= 's';
        } else {
            $whereClauses[] = "$column = ?";
            $params[] = $filterValue;
            $types .= 's';
        }
    }
}

$whereSQL = implode(' AND ', $whereClauses);
$sql = "SELECT * FROM itcp WHERE $whereSQL ORDER BY lastname, firstname";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $bindParams = array($types);
    foreach ($params as $key => $value) {
        $bindParams[] = &$params[$key];
    }
    call_user_func_array(array($stmt, 'bind_param'), $bindParams);
}

$stmt->execute();
$profiles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

switch ($exportFormat) {
    case 'csv':
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="alumni_directory_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'ID', 'First Name', 'Middle Name', 'Last Name', 'Name Extension',
            'Program', 'Year Graduated', 'Campus', 'Address', 'Email', 'Personal Contact',
            'Company', 'Position', 'Industry', 'Previous Role', 'Post Graduate Studies',
            'Club Involvement', 'LinkedIn', 'Date Joined'
        ]);
        
        // CSV data
        foreach ($profiles as $profile) {
            fputcsv($output, [
                $profile['id'],
                $profile['firstname'],
                $profile['middlename'],
                $profile['lastname'],
                $profile['name_ext'],
                $profile['program'],
                $profile['year_graduated'],
                $profile['campus'],
                $profile['address'],
                $profile['email'],
                $profile['personal_contact'],
                $profile['company'],
                $profile['position'],
                $profile['industry'],
                $profile['previous_role'],
                $profile['post_grad'],
                $profile['club_involvement'],
                $profile['linkedin'],
                $profile['date_joined']
            ]);
        }
        
        fclose($output);
        break;
        
    case 'excel':
        // Simple Excel-like CSV with UTF-8 BOM for Excel compatibility
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="alumni_directory_' . date('Y-m-d') . '.xlsx"');
        
        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, [
            'ID', 'First Name', 'Middle Name', 'Last Name', 'Name Extension',
            'Program', 'Year Graduated', 'Campus', 'Address', 'Email', 'Personal Contact',
            'Company', 'Position', 'Industry', 'Previous Role', 'Post Graduate Studies',
            'Club Involvement', 'LinkedIn', 'Date Joined'
        ]);
        
        // Data
        foreach ($profiles as $profile) {
            fputcsv($output, [
                $profile['id'],
                $profile['firstname'],
                $profile['middlename'],
                $profile['lastname'],
                $profile['name_ext'],
                $profile['program'],
                $profile['year_graduated'],
                $profile['campus'],
                $profile['address'],
                $profile['email'],
                $profile['personal_contact'],
                $profile['company'],
                $profile['position'],
                $profile['industry'],
                $profile['previous_role'],
                $profile['post_grad'],
                $profile['club_involvement'],
                $profile['linkedin'],
                $profile['date_joined']
            ]);
        }
        
        fclose($output);
        break;
        
    case 'pdf':
        // For PDF, we'll create a simple HTML that can be printed to PDF
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Alumni Directory Export</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; font-weight: bold; }
                .header { text-align: center; margin-bottom: 30px; }
                .summary { margin-bottom: 20px; }
                @media print {
                    body { margin: 0; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Our Lady of Fatima University</h1>
                <h2>Alumni Directory Export</h2>
                <p>Generated on: <?= date('F d, Y g:i A') ?></p>
            </div>
            
            <div class="summary">
                <p><strong>Total Alumni:</strong> <?= count($profiles) ?></p>
                <p><strong>Export Format:</strong> PDF</p>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Program</th>
                        <th>Year</th>
                        <th>Campus</th>
                        <th>Company</th>
                        <th>Position</th>
                        <th>Location</th>
                        <th>Industry</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($profiles as $profile): ?>
                    <tr>
                        <td><?= htmlspecialchars($profile['firstname'] . ' ' . $profile['middlename'] . ' ' . $profile['lastname']) ?></td>
                        <td><?= htmlspecialchars($profile['program']) ?></td>
                        <td><?= htmlspecialchars($profile['year_graduated']) ?></td>
                        <td><?= htmlspecialchars($profile['campus']) ?></td>
                        <td><?= htmlspecialchars($profile['company']) ?></td>
                        <td><?= htmlspecialchars($profile['position']) ?></td>
                        <td><?= htmlspecialchars($profile['address']) ?></td>
                        <td><?= htmlspecialchars($profile['industry']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="no-print" style="margin-top: 30px; text-align: center;">
                <button onclick="window.print()">Print PDF</button>
                <button onclick="window.close()">Close</button>
            </div>
        </body>
        </html>
        <?php
        break;
        
    default:
        header("Location: al_directory.php");
        exit();
}
?>
