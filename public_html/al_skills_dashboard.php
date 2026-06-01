<?php
session_start();
require_once 'config.php';
alumni_otp_gate_after_session();

if (!isset($_SESSION['user_id'])) {
    header("Location: al_login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$sql = "SELECT * FROM itcp WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Fetch skills distribution
$sql = "SELECT skill_name, COUNT(*) as count 
        FROM alumni_skills 
        GROUP BY skill_name 
        ORDER BY count DESC 
        LIMIT 10";
$top_skills = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Fetch industry distribution
$sql = "SELECT industry, COUNT(*) as count 
        FROM itcp 
        WHERE industry IS NOT NULL 
        GROUP BY industry 
        ORDER BY count DESC 
        LIMIT 10";
$top_industries = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Fetch employment status
$sql = "SELECT employment_status, COUNT(*) as count 
        FROM itcp 
        WHERE employment_status IS NOT NULL 
        GROUP BY employment_status";
$employment_stats = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Fetch recent graduates (last 5 years)
$sql = "SELECT COUNT(*) as count 
        FROM itcp 
        WHERE year_graduated >= YEAR(CURRENT_DATE) - 5";
$recent_grads = $conn->query($sql)->fetch_assoc()['count'];

// Fetch alumni by program
$sql = "SELECT program, COUNT(*) as count 
        FROM itcp 
        WHERE program LIKE '%Computer%' OR program LIKE '%IT%' OR program LIKE '%Information%' 
        GROUP BY program";
$program_stats = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CCS Alumni Skills Dashboard - OLFU Alumni</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Custom scrollbar */
        body::-webkit-scrollbar {
            width: 8px;
        }
        body::-webkit-scrollbar-thumb {
            background-color: #8b0a1c;
            border-radius: 4px;
        }
        
        /* Card hover effects */
        .stat-card {
            transition: all 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
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
                <h1 class="text-4xl font-bold text-white mb-4">CCS Alumni Skills Dashboard</h1>
                <p class="text-xl text-green-100 max-w-2xl mx-auto">
                    Track and analyze the skills, careers, and achievements of our Computer Studies graduates
                </p>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12">
            <div class="stat-card bg-white rounded-xl shadow-sm p-6 border-l-4 border-blue-500">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="fas fa-code text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Total Alumni</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?php echo array_sum(array_column($program_stats, 'count')); ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-white rounded-xl shadow-sm p-6 border-l-4 border-purple-500">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                        <i class="fas fa-graduation-cap text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Recent Graduates</p>
                        <p class="text-2xl font-semibold text-gray-900"><?php echo $recent_grads; ?></p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-white rounded-xl shadow-sm p-6 border-l-4 border-green-500">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="fas fa-briefcase text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Employed Alumni</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?php 
                            $employed = array_filter($employment_stats, function($stat) {
                                return $stat['employment_status'] === 'Employed';
                            });
                            echo $employed ? reset($employed)['count'] : 0;
                            ?>
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-white rounded-xl shadow-sm p-6 border-l-4 border-yellow-500">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <i class="fas fa-laptop-code text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-gray-500">Active Skills</p>
                        <p class="text-2xl font-semibold text-gray-900">
                            <?php echo count($top_skills); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
            <!-- Skills Distribution -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Top Skills Distribution</h2>
                <canvas id="skillsChart" height="300"></canvas>
            </div>

            <!-- Industry Distribution -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Industry Distribution</h2>
                <canvas id="industryChart" height="300"></canvas>
            </div>
        </div>

        <!-- Program Statistics -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-12">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Program Distribution</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($program_stats as $program): ?>
                    <div class="stat-card bg-gray-50 rounded-lg p-4">
                        <h3 class="font-medium text-gray-900 mb-2"><?php echo htmlspecialchars($program['program']); ?></h3>
                        <p class="text-2xl font-semibold text-green-600"><?php echo $program['count']; ?></p>
                        <p class="text-sm text-gray-500">graduates</p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Employment Status -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Employment Status</h2>
            <canvas id="employmentChart" height="200"></canvas>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Skills Chart
            new Chart(document.getElementById('skillsChart'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_column($top_skills, 'skill_name')); ?>,
                    datasets: [{
                        label: 'Number of Alumni',
                        data: <?php echo json_encode(array_column($top_skills, 'count')); ?>,
                        backgroundColor: 'rgba(16, 185, 129, 0.5)',
                        borderColor: 'rgb(16, 185, 129)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Industry Chart
            new Chart(document.getElementById('industryChart'), {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode(array_column($top_industries, 'industry')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($top_industries, 'count')); ?>,
                        backgroundColor: [
                            'rgba(16, 185, 129, 0.5)',
                            'rgba(59, 130, 246, 0.5)',
                            'rgba(139, 92, 246, 0.5)',
                            'rgba(245, 158, 11, 0.5)',
                            'rgba(239, 68, 68, 0.5)'
                        ]
                    }]
                },
                options: {
                    responsive: true
                }
            });

            // Employment Chart
            new Chart(document.getElementById('employmentChart'), {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_column($employment_stats, 'employment_status')); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_column($employment_stats, 'count')); ?>,
                        backgroundColor: [
                            'rgba(16, 185, 129, 0.5)',
                            'rgba(59, 130, 246, 0.5)',
                            'rgba(139, 92, 246, 0.5)'
                        ]
                    }]
                },
                options: {
                    responsive: true
                }
            });
        });
    </script>
<?php if (file_exists(__DIR__ . '/al_footer_universal.php')) { include 'al_footer_universal.php'; } ?>
</body>
</html> 