<?php
require_once 'db_config.php';
$conn = getDBConnection();

session_start();

// Check if coordinator is logged in
if (!isset($_SESSION['coordinator_logged_in']) || $_SESSION['coordinator_logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get event ID from request
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

if ($event_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid event ID']);
    exit();
}

try {
    // Get registrations for the event with user profile information
    $sql = "SELECT er.*, 
            i.firstname, 
            i.lastname, 
            i.email,
            i.program,
            i.campus,
            i.year_graduated,
            i.photo,
            i.personal_contact
            FROM event_registrations er 
            JOIN itcp i ON er.user_id = i.id 
            WHERE er.event_id = ? 
            ORDER BY er.registration_date DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }

    $stmt->bind_param("i", $event_id);
    if (!$stmt->execute()) {
        throw new Exception("Failed to execute statement: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $registrations = [];

    while ($row = $result->fetch_assoc()) {
        $registrations[] = [
            'firstname' => $row['firstname'],
            'lastname' => $row['lastname'],
            'email' => $row['email'],
            'program' => $row['program'],
            'campus' => $row['campus'],
            'year_graduated' => $row['year_graduated'],
            'photo' => $row['photo'],
            'contact_number' => $row['personal_contact'],
            'registration_date' => $row['registration_date']
        ];
    }

    $stmt->close();
    $conn->close();

    // Return registrations as JSON
    header('Content-Type: application/json');
    echo json_encode($registrations);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
    exit();
}
?> 