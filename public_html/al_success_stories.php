<?php
session_start();
require_once 'config.php';
alumni_otp_gate_after_session();

if (!isset($_SESSION['user_id'])) {
    header("Location: al_homepage.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$sql = "SELECT * FROM itcp WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Fetch featured alumni
$sql = "SELECT * FROM itcp WHERE featured = 1 AND consent = 1 ORDER BY year_graduated DESC LIMIT 6";
$featured_alumni = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Fetch success stories
$sql = "SELECT * FROM alumni_success_stories ORDER BY created_at DESC";
$success_stories = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Success Stories - OLFU Alumni</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
    <style>
        /* Custom scrollbar */
        body::-webkit-scrollbar {
            width: 8px;
        }
        body::-webkit-scrollbar-thumb {
            background-color: #8b0a1c;
            border-radius: 4px;
        }
        
        /* Timeline styles */
        .timeline-item {
            position: relative;
            padding-left: 2rem;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e5e7eb;
        }
        .timeline-item::after {
            content: '';
            position: absolute;
            left: -4px;
            top: 0;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #10b981;
        }
        
        /* Story card hover effects */
        .story-card {
            transition: all 0.3s ease;
        }
        .story-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        
        /* Profile card animations */
        .profile-card {
            transition: all 0.3s ease;
        }
        .profile-card:hover {
            transform: scale(1.02);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header (Same as other pages) -->
    <header class="bg-white shadow-md fixed top-0 left-0 right-0 z-50 border-t-4 border-green-900">
        <!-- ... (Copy header from al_events.php) ... -->
    </header>

    <!-- Sidebar (Same as other pages) -->
    <aside id="sidebar" class="bg-green-700 text-white w-16 hover:w-64 transition-all duration-300 fixed h-full z-40 overflow-hidden pt-20 group">
        <!-- ... (Copy sidebar from al_events.php) ... -->
    </aside>

    <!-- Main Content -->
    <main class="ml-16 pt-24 pb-12 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
        <!-- Hero Section -->
        <div class="relative bg-gradient-to-r from-green-600 to-green-800 rounded-2xl overflow-hidden mb-12">
            <div class="absolute inset-0 bg-black opacity-50"></div>
            <div class="relative px-8 py-16 text-center">
                <h1 class="text-4xl font-bold text-white mb-4">Alumni Success Stories</h1>
                <p class="text-xl text-green-100 max-w-2xl mx-auto">
                    Discover inspiring journeys of our graduates who are making a difference in their fields
                </p>
            </div>
        </div>

        <!-- Featured Alumni Section -->
        <section class="mb-16">
            <h2 class="text-2xl font-bold text-gray-900 mb-8">Featured Alumni</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($featured_alumni as $alumni): ?>
                    <div class="profile-card bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="relative h-48">
                            <?php if (!empty($alumni['photo']) && file_exists(__DIR__ . '/uploads/' . $alumni['photo'])): ?>
                                <img src="<?php echo $alumni['photo'] ? (strpos($alumni['photo'], 'http') === 0 ? $alumni['photo'] : 'uploads/' . $alumni['photo']) : 'default-avatar.png'; ?>" 
                                     alt="<?php echo htmlspecialchars($alumni['firstname'] . ' ' . $alumni['lastname']); ?>"
                                     class="w-full h-full object-cover">
                            <?php else: ?>
                                <div class="w-full h-full bg-gradient-to-r from-green-400 to-green-600 flex items-center justify-center">
                                    <span class="text-4xl text-white font-bold">
                                        <?php 
                                            $initials = '';
                                            if (!empty($alumni['firstname'])) {
                                                $initials .= strtoupper(substr($alumni['firstname'], 0, 1));
                                            }
                                            if (!empty($alumni['lastname'])) {
                                                $initials .= strtoupper(substr($alumni['lastname'], 0, 1));
                                            }
                                            echo $initials ?: '?';
                                        ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black to-transparent p-4">
                                <h3 class="text-xl font-bold text-white">
                                    <?php echo htmlspecialchars($alumni['firstname'] . ' ' . $alumni['lastname']); ?>
                                </h3>
                                <p class="text-green-200">
                                    <?php echo htmlspecialchars($alumni['program']); ?> • Class of <?php echo htmlspecialchars($alumni['year_graduated']); ?>
                                </p>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="flex items-center text-sm text-gray-500 mb-4">
                                <i class="fas fa-briefcase mr-2"></i>
                                <span><?php echo htmlspecialchars($alumni['position']); ?> at <?php echo htmlspecialchars($alumni['company']); ?></span>
                            </div>
                            <p class="text-gray-600 mb-4">
                                <?php echo htmlspecialchars($alumni['bio'] ?? 'No bio available'); ?>
                            </p>
                            <div class="flex space-x-4">
                                <?php if (!empty($alumni['linkedin'])): ?>
                                    <a href="<?php echo htmlspecialchars($alumni['linkedin']); ?>" target="_blank" 
                                       class="text-blue-600 hover:text-blue-800">
                                        <i class="fab fa-linkedin text-xl"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($alumni['twitter'])): ?>
                                    <a href="<?php echo htmlspecialchars($alumni['twitter']); ?>" target="_blank"
                                       class="text-blue-400 hover:text-blue-600">
                                        <i class="fab fa-twitter text-xl"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Success Stories Timeline -->
        <section>
            <h2 class="text-2xl font-bold text-gray-900 mb-8">Success Stories Timeline</h2>
            <div class="space-y-8">
                <?php foreach ($success_stories as $story): ?>
                    <div class="timeline-item bg-white rounded-xl shadow-sm p-6">
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0">
                                <?php if (!empty($story['author_photo'])): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($story['author_photo']); ?>" 
                                         alt="<?php echo htmlspecialchars($story['author_name']); ?>"
                                         class="w-16 h-16 rounded-full object-cover">
                                <?php else: ?>
                                    <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center">
                                        <span class="text-xl font-bold text-green-700">
                                            <?php echo strtoupper(substr($story['author_name'], 0, 1)); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-2">
                                    <h3 class="text-lg font-semibold text-gray-900">
                                        <?php echo htmlspecialchars($story['title']); ?>
                                    </h3>
                                    <span class="text-sm text-gray-500">
                                        <?php echo date('F d, Y', strtotime($story['created_at'])); ?>
                                    </span>
                                </div>
                                <p class="text-gray-600 mb-4">
                                    <?php echo nl2br(htmlspecialchars($story['content'])); ?>
                                </p>
                                <div class="flex items-center text-sm text-gray-500">
                                    <span class="font-medium text-gray-900"><?php echo htmlspecialchars($story['author_name']); ?></span>
                                    <span class="mx-2">•</span>
                                    <span><?php echo htmlspecialchars($story['author_program']); ?></span>
                                    <span class="mx-2">•</span>
                                    <span>Class of <?php echo htmlspecialchars($story['author_year']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add any JavaScript for interactivity here
        });
    </script>
<?php if (file_exists(__DIR__ . '/al_footer_universal.php')) { include 'al_footer_universal.php'; } ?>
</body>
</html> 