
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

$dsn = 'mysql:host=localhost;dbname=itcp_db;charset=utf8mb4';
$dbUser = 'root';
$dbPass = '';
$msg = '';

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullname = $_POST['fullname'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $gender = $_POST['gender'];
    $program = $_POST['program'];
    $graduation_year = $_POST['graduation_year'];
    $address = $_POST['address'];
    $company = $_POST['company'] ?? '';
    $designation = $_POST['designation'] ?? '';
    $linkedin = $_POST['linkedin'] ?? '';
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    try {
        $stmt = $pdo->prepare("INSERT INTO itcp (fullname, email, phone, gender, program, year_graduated, address, company, designation, linkedin, password)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $fullname, $email, $phone, $gender, $program, $graduation_year,
            $address, $company, $designation, $linkedin, $password
        ]);
        $msg = "Registration successful!";
    } catch (PDOException $e) {
        $msg = "Registration failed: " . $e->getMessage();
    }
}
?>
