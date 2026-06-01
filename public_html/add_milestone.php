<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: al_homepage.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $milestone_type = $_POST['milestone_type'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $date_achieved = $_POST['date_achieved'];
    $visibility = $_POST['visibility'];
    $linkedin_sync = isset($_POST['linkedin_sync']) ? 1 : 0;

    $sql = "INSERT INTO career_milestones (alumni_id, milestone_type, title, description, date_achieved, visibility, linkedin_sync) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssssi", $user_id, $milestone_type, $title, $description, $date_achieved, $visibility, $linkedin_sync);
    
    if ($stmt->execute()) {
        header("Location: al_mycareer.php");
        exit();
    } else {
        $error = "Error adding milestone: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Milestone - OLFU Alumni</title>
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
                <h1 class="text-2xl font-bold text-gray-900">Add Career Milestone</h1>
                <a href="al_mycareer.php" class="text-gray-600 hover:text-gray-900">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Career Timeline
                </a>
            </div>

            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Milestone Type
                    </label>
                    <select name="milestone_type" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="">Select type</option>
                        <option value="Education">Education</option>
                        <option value="Employment">Employment</option>
                        <option value="Certification">Certification</option>
                        <option value="Award">Award</option>
                        <option value="Project">Project</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Title
                    </label>
                    <input type="text" name="title" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                           placeholder="e.g., Senior Software Engineer, AWS Certification, etc.">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Description
                    </label>
                    <textarea name="description" rows="4" required
                              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                              placeholder="Describe your achievement, role, or certification..."></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Date Achieved
                    </label>
                    <input type="date" name="date_achieved" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Visibility
                    </label>
                    <div class="space-y-2">
                        <div class="flex items-center">
                            <input type="radio" name="visibility" value="Private" checked
                                   class="h-4 w-4 text-green-600 focus:ring-green-500">
                            <label class="ml-3 text-sm text-gray-700">
                                Private (Only visible to you)
                            </label>
                        </div>
                        <div class="flex items-center">
                            <input type="radio" name="visibility" value="Admin Only"
                                   class="h-4 w-4 text-green-600 focus:ring-green-500">
                            <label class="ml-3 text-sm text-gray-700">
                                Admin Only (Visible to administrators)
                            </label>
                        </div>
                        <div class="flex items-center">
                            <input type="radio" name="visibility" value="Public"
                                   class="h-4 w-4 text-green-600 focus:ring-green-500">
                            <label class="ml-3 text-sm text-gray-700">
                                Public (Visible to all alumni)
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex items-center">
                    <input type="checkbox" name="linkedin_sync" id="linkedin_sync"
                           class="h-4 w-4 text-green-600 focus:ring-green-500">
                    <label for="linkedin_sync" class="ml-3 text-sm text-gray-700">
                        Sync with LinkedIn (if connected)
                    </label>
                </div>

                <div class="flex justify-end space-x-4">
                    <a href="al_mycareer.php" class="px-4 py-2 text-gray-700 hover:text-gray-900">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Career Timeline
                    </a>
                    <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                        Add Milestone
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html> 