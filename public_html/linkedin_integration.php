<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: al_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch LinkedIn integration status
$sql = "SELECT * FROM linkedin_integration WHERE alumni_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$linkedin_data = $result->fetch_assoc();

// Handle LinkedIn connection
if (isset($_POST['connect_linkedin'])) {
    // TODO: Implement LinkedIn OAuth flow
    // For now, we'll just show a message
    $message = "LinkedIn integration coming soon!";
}

// Handle sync settings update
if (isset($_POST['update_sync_settings'])) {
    $sync_career = isset($_POST['sync_career']) ? 1 : 0;
    $sync_skills = isset($_POST['sync_skills']) ? 1 : 0;
    $sync_certifications = isset($_POST['sync_certifications']) ? 1 : 0;
    $auto_sync = isset($_POST['auto_sync']) ? 1 : 0;

    if ($linkedin_data) {
        $sql = "UPDATE linkedin_integration SET 
                sync_career = ?, 
                sync_skills = ?, 
                sync_certifications = ?, 
                auto_sync = ? 
                WHERE alumni_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiii", $sync_career, $sync_skills, $sync_certifications, $auto_sync, $user_id);
    } else {
        $sql = "INSERT INTO linkedin_integration (alumni_id, sync_career, sync_skills, sync_certifications, auto_sync) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiii", $user_id, $sync_career, $sync_skills, $sync_certifications, $auto_sync);
    }

    if ($stmt->execute()) {
        $success = "Sync settings updated successfully!";
    } else {
        $error = "Error updating sync settings: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LinkedIn Integration - OLFU Alumni</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-md fixed top-0 left-0 right-0 z-50 border-t-4 border-green-900">
        <!-- ... (Copy header from al_events.php) ... -->
    </header>

    <!-- Main Content -->
    <main class="max-w-2xl mx-auto mt-24 px-4 py-8">
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-900">LinkedIn Integration</h1>
                <a href="al_mycareer.php" class="text-gray-600 hover:text-gray-900">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Career Timeline
                </a>
            </div>

            <?php if (isset($message)): ?>
                <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!$linkedin_data): ?>
                <div class="text-center py-8">
                    <i class="fab fa-linkedin text-4xl text-blue-600 mb-4"></i>
                    <h2 class="text-xl font-semibold mb-2">Connect Your LinkedIn Account</h2>
                    <p class="text-gray-600 mb-6">Sync your career information with LinkedIn to keep your profile up to date.</p>
                    <form method="POST">
                        <button type="submit" name="connect_linkedin" 
                                class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            Connect LinkedIn
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="space-y-6">
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                        <div class="flex items-center">
                            <i class="fab fa-linkedin text-2xl text-blue-600 mr-3"></i>
                            <div>
                                <h3 class="font-medium">Connected to LinkedIn</h3>
                                <p class="text-sm text-gray-600">Last synced: <?php echo date('M d, Y', strtotime($linkedin_data['last_sync'])); ?></p>
                            </div>
                        </div>
                        <button class="text-red-600 hover:text-red-800">
                            <i class="fas fa-unlink"></i> Disconnect
                        </button>
                    </div>

                    <form method="POST" class="space-y-6">
                        <div>
                            <h3 class="text-lg font-medium mb-4">Sync Settings</h3>
                            <div class="space-y-4">
                                <div class="flex items-center">
                                    <input type="checkbox" name="sync_career" id="sync_career" 
                                           <?php echo $linkedin_data['sync_career'] ? 'checked' : ''; ?>
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500">
                                    <label for="sync_career" class="ml-3 text-sm text-gray-700">
                                        Sync Career History
                                    </label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" name="sync_skills" id="sync_skills"
                                           <?php echo $linkedin_data['sync_skills'] ? 'checked' : ''; ?>
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500">
                                    <label for="sync_skills" class="ml-3 text-sm text-gray-700">
                                        Sync Skills
                                    </label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" name="sync_certifications" id="sync_certifications"
                                           <?php echo $linkedin_data['sync_certifications'] ? 'checked' : ''; ?>
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500">
                                    <label for="sync_certifications" class="ml-3 text-sm text-gray-700">
                                        Sync Certifications
                                    </label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" name="auto_sync" id="auto_sync"
                                           <?php echo $linkedin_data['auto_sync'] ? 'checked' : ''; ?>
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500">
                                    <label for="auto_sync" class="ml-3 text-sm text-gray-700">
                                        Auto-sync weekly
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-4">
                            <a href="al_mycareer.php" class="px-4 py-2 text-gray-700 hover:text-gray-900">
                                <i class="fas fa-arrow-left mr-2"></i> Back to Career Timeline
                            </a>
                            <button type="submit" name="update_sync_settings" 
                                    class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html> 