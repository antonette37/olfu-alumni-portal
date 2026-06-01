<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: al_login.php');
    exit();
}

require_once 'db_config.php';
$conn = getDBConnection();

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: ad_user_management.php');
    exit();
}

// Use prepared statement to prevent SQL injection
$stmt = $conn->prepare("SELECT * FROM itcp WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    header('Location: ad_user_management.php');
    exit();
}
$alumni = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Admin can edit: Name corrections, Academic info, Account status, Email
    // Personal contact info is editable for moderation purposes only
    $fields = [
        'lastname', 'firstname', 'middlename', 'name_ext', // Name corrections
        'email', // Account email
        'student_number', 'program', 'campus', 'month_graduated', 'year_graduated', // Academic info
        'post_grad', 'licensure_exam', 'club_involvement', // Additional academic
        'status', // Account status (if exists in table)
        'personal_contact', 'emergency_contact', // Contact (for moderation)
        'consent' // Consent
    ];

    $values = [];
    foreach ($fields as $field) {
        $values[$field] = $_POST[$field] ?? '';
    }

    $set_clause = implode(', ', array_map(fn($f) => "$f = ?", $fields));
    $types = str_repeat('s', count($fields)) . 'i';
    $params = array_values($values);
    $params[] = $id;

    $stmt = $conn->prepare("UPDATE itcp SET $set_clause WHERE id = ?");
    if (!$stmt) {
        $error = "Prepare failed: " . $conn->error;
    } else {
        $stmt->bind_param($types, ...$params);

        if ($stmt->execute()) {
            // Check if there's a return URL parameter
            $return_url = $_GET['return'] ?? 'ad_alumnirecord.php';
            header("Location: " . htmlspecialchars($return_url));
            exit;
        } else {
            $error = "Error updating record: " . $stmt->error;
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title>Manage Account - Admin Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet" />
    <style>
        body {
            font-family: "Inter", sans-serif;
            background: #f9fafb;
            color: #1e293b;
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
        .form-input {
            @apply w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition-all;
        }
        .form-label {
            @apply block text-sm font-medium text-gray-700 mb-2;
        }
        .section-title {
            @apply text-xl font-bold text-gray-800 mb-4 pb-2 border-b border-gray-200;
        }
    </style>
</head>
<body>
    <!-- Include Admin Universal Header -->
    <?php include 'ad_header_universal.php'; ?>
    <?php include 'ad_sidebar_universal.php'; ?>

    <!-- Main Content -->
    <main class="pt-24 ml-16 p-4 max-w-full">
        <div class="max-w-7xl mx-auto">
            <!-- Page Header -->
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-800">Manage Account</h1>
                        <p class="text-gray-600 mt-1">Edit academic records, account status, and administrative information</p>
                        <p class="text-sm text-amber-600 mt-2">
                            <i class="fas fa-info-circle mr-1"></i>
                            Note: Personal profile information should be edited by the alumni themselves
                        </p>
                    </div>
                    <a href="<?php echo htmlspecialchars($_GET['return'] ?? 'ad_user_management.php'); ?>" 
                       class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Back
                    </a>
                </div>
            </div>

            <?php if (isset($error)): ?>
            <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <!-- Account Status Section (Most Important for Admin) -->
                <div class="glassmorphism p-6 border-l-4 border-green-600">
                    <h2 class="section-title">
                        <i class="fas fa-shield-alt mr-2 text-green-600"></i>Account Management
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="form-label">Account Status <span class="text-red-500">*</span></label>
                            <select name="status" class="form-input" required>
                                <option value="pending" <?php echo ($alumni['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="active" <?php echo ($alumni['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($alumni['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="rejected" <?php echo ($alumni['status'] ?? '') === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="archived" <?php echo ($alumni['status'] ?? '') === 'archived' ? 'selected' : ''; ?>>Archived</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Control account access and verification status</p>
                        </div>
                        <div>
                            <label class="form-label">Email <span class="text-red-500">*</span></label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($alumni['email'] ?? ''); ?>" class="form-input" required>
                            <p class="text-xs text-gray-500 mt-1">Account email for login and notifications</p>
                        </div>
                    </div>
                </div>

                <!-- Academic Information Section (Primary Admin Function) -->
                <div class="glassmorphism p-6 border-l-4 border-blue-600">
                    <h2 class="section-title">
                        <i class="fas fa-graduation-cap mr-2 text-blue-600"></i>Academic Information
                        <span class="text-sm font-normal text-gray-500 ml-2">(Administrative Control)</span>
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="form-label">Student/Alumni ID <span class="text-red-500">*</span></label>
                            <input type="text" name="student_number" value="<?php echo htmlspecialchars($alumni['student_number'] ?? ''); ?>" class="form-input" required>
                            <p class="text-xs text-gray-500 mt-1">Official student or alumni identification number</p>
                        </div>
                        <div>
                            <label class="form-label">Program/Course <span class="text-red-500">*</span></label>
                            <input type="text" name="program" value="<?php echo htmlspecialchars($alumni['program'] ?? ''); ?>" class="form-input" required>
                            <p class="text-xs text-gray-500 mt-1">Degree program or course completed</p>
                        </div>
                        <div>
                            <label class="form-label">Campus</label>
                            <input type="text" name="campus" value="<?php echo htmlspecialchars($alumni['campus'] ?? ''); ?>" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Month Graduated</label>
                            <input type="text" name="month_graduated" value="<?php echo htmlspecialchars($alumni['month_graduated'] ?? ''); ?>" class="form-input" placeholder="e.g., May">
                        </div>
                        <div>
                            <label class="form-label">Year Graduated / Batch <span class="text-red-500">*</span></label>
                            <input type="text" name="year_graduated" value="<?php echo htmlspecialchars($alumni['year_graduated'] ?? ''); ?>" class="form-input" placeholder="e.g., 2020" required>
                            <p class="text-xs text-gray-500 mt-1">Graduation year or batch number</p>
                        </div>
                        <div>
                            <label class="form-label">Post Graduate</label>
                            <input type="text" name="post_grad" value="<?php echo htmlspecialchars($alumni['post_grad'] ?? ''); ?>" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Licensure Exam</label>
                            <input type="text" name="licensure_exam" value="<?php echo htmlspecialchars($alumni['licensure_exam'] ?? ''); ?>" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Club Involvement</label>
                            <input type="text" name="club_involvement" value="<?php echo htmlspecialchars($alumni['club_involvement'] ?? ''); ?>" class="form-input">
                        </div>
                    </div>
                </div>

                <!-- Name Corrections Section (For Typo Fixes) -->
                <div class="glassmorphism p-6 border-l-4 border-amber-500">
                    <h2 class="section-title">
                        <i class="fas fa-user-edit mr-2 text-amber-600"></i>Name Corrections
                        <span class="text-sm font-normal text-gray-500 ml-2">(For administrative corrections only)</span>
                    </h2>
                    <p class="text-sm text-amber-700 bg-amber-50 p-3 rounded-lg mb-4">
                        <i class="fas fa-info-circle mr-2"></i>
                        Use this section only to correct typos or official name changes. Personal profile updates should be done by the alumni.
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="form-label">Last Name <span class="text-red-500">*</span></label>
                            <input type="text" name="lastname" value="<?php echo htmlspecialchars($alumni['lastname'] ?? ''); ?>" class="form-input" required>
                        </div>
                        <div>
                            <label class="form-label">First Name <span class="text-red-500">*</span></label>
                            <input type="text" name="firstname" value="<?php echo htmlspecialchars($alumni['firstname'] ?? ''); ?>" class="form-input" required>
                        </div>
                        <div>
                            <label class="form-label">Middle Name</label>
                            <input type="text" name="middlename" value="<?php echo htmlspecialchars($alumni['middlename'] ?? ''); ?>" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Name Extension</label>
                            <input type="text" name="name_ext" value="<?php echo htmlspecialchars($alumni['name_ext'] ?? ''); ?>" class="form-input" placeholder="Jr., Sr., III, etc.">
                        </div>
                    </div>
                </div>

                <!-- Contact Information (For Moderation Only) -->
                <div class="glassmorphism p-6 border-l-4 border-gray-400">
                    <h2 class="section-title">
                        <i class="fas fa-phone mr-2 text-gray-600"></i>Contact Information
                        <span class="text-sm font-normal text-gray-500 ml-2">(For moderation purposes only)</span>
                    </h2>
                    <p class="text-sm text-gray-600 bg-gray-50 p-3 rounded-lg mb-4">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        Contact information is typically managed by the alumni. Edit only when moderation is required.
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="form-label">Personal Contact</label>
                            <input type="text" name="personal_contact" value="<?php echo htmlspecialchars($alumni['personal_contact'] ?? ''); ?>" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Emergency Contact</label>
                            <input type="text" name="emergency_contact" value="<?php echo htmlspecialchars($alumni['emergency_contact'] ?? ''); ?>" class="form-input">
                        </div>
                    </div>
                </div>

                <!-- Academic Information Section -->
                <div class="glassmorphism p-6">
                    <h2 class="section-title">
                        <i class="fas fa-graduation-cap mr-2 text-green-600"></i>Academic Information
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="form-label">Student Number</label>
                            <input type="text" name="student_number" value="<?php echo htmlspecialchars($alumni['student_number'] ?? ''); ?>" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Program</label>
                            <input type="text" name="program" value="<?php echo htmlspecialchars($alumni['program'] ?? ''); ?>" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Campus</label>
                            <input type="text" name="campus" value="<?php echo htmlspecialchars($alumni['campus'] ?? ''); ?>" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Month Graduated</label>
                            <input type="text" name="month_graduated" value="<?php echo htmlspecialchars($alumni['month_graduated'] ?? ''); ?>" class="form-input" placeholder="e.g., May">
                        </div>
                        <div>
                            <label class="form-label">Year Graduated</label>
                            <input type="text" name="year_graduated" value="<?php echo htmlspecialchars($alumni['year_graduated'] ?? ''); ?>" class="form-input" placeholder="e.g., 2020">
                        </div>
                        <div>
                            <label class="form-label">Post Graduate</label>
                            <input type="text" name="post_grad" value="<?php echo htmlspecialchars($alumni['post_grad'] ?? ''); ?>" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Licensure Exam</label>
                            <input type="text" name="licensure_exam" value="<?php echo htmlspecialchars($alumni['licensure_exam'] ?? ''); ?>" class="form-input">
                        </div>
                        <div>
                            <label class="form-label">Club Involvement</label>
                            <input type="text" name="club_involvement" value="<?php echo htmlspecialchars($alumni['club_involvement'] ?? ''); ?>" class="form-input">
                        </div>
                    </div>
                </div>


                <!-- Consent Section -->
                <div class="glassmorphism p-6">
                    <h2 class="section-title">
                        <i class="fas fa-check-circle mr-2 text-green-600"></i>Consent
                    </h2>
                    <div class="max-w-md">
                        <label class="form-label">Consent</label>
                        <select name="consent" class="form-input" required>
                            <option value="Yes" <?php echo ($alumni['consent'] ?? '') === 'Yes' ? 'selected' : ''; ?>>Yes</option>
                            <option value="No" <?php echo ($alumni['consent'] ?? '') === 'No' ? 'selected' : ''; ?>>No</option>
                        </select>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="flex justify-end space-x-4 pb-6">
                    <a href="<?php echo htmlspecialchars($_GET['return'] ?? 'ad_user_management.php'); ?>" 
                       class="px-6 py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-semibold">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </a>
                    <button type="submit" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-semibold">
                        <i class="fas fa-save mr-2"></i>Update Account
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
