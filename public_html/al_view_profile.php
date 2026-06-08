<?php
require_once 'db_config.php';
$conn = getDBConnection();

// Define getPrivacySettings function (privacy_access.php requires config.php, but we use db_config.php)
if (!function_exists('getPrivacySettings')) {
    function getPrivacySettings($conn, $userId)
    {
        $defaults = [
            'salary_visibility' => 'Private',
            'salary_aggregated_consent' => 0,
            'contact_visibility' => 'Private',
            'employment_visibility' => 'Public',
            'photo_visibility' => 'Public',
        ];
        $stmt = $conn->prepare("SELECT salary_visibility, salary_aggregated_consent, contact_visibility, employment_visibility, photo_visibility FROM privacy_settings WHERE user_id = ?");
        if (!$stmt)
            return $defaults;
        $stmt->bind_param('i', $userId);
        if ($stmt->execute()) {
            $sVis = $agg = $cVis = $eVis = $pVis = null;
            $stmt->bind_result($sVis, $agg, $cVis, $eVis, $pVis);
            if ($stmt->fetch()) {
                $defaults['salary_visibility'] = (string)$sVis;
                $defaults['salary_aggregated_consent'] = (int)$agg;
                $defaults['contact_visibility'] = (string)$cVis;
                $defaults['employment_visibility'] = (string)$eVis;
                $defaults['photo_visibility'] = (string)$pVis;
            }
        }
        $stmt->close();
        return $defaults;
    }
}

session_start();
alumni_otp_gate_after_session();

if (!isset($_SESSION['user_id'])) {
    header("Location: al_login.php");
    exit();
}

$viewer_id = (int)$_SESSION['user_id'];

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: al_directory.php");
    exit();
}

$profile_id = (int)$_GET['id'];

// If viewing own profile, redirect to al_profile.php
if ($profile_id == $viewer_id) {
    header("Location: al_profile.php");
    exit();
}

// Fetch alumni information
$sql = "SELECT * FROM itcp WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("SQL error: " . $conn->error);
}

$stmt->bind_param("i", $profile_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $stmt->close();
    $conn->close();
    header("Location: al_directory.php?error=Profile not found");
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Load privacy settings
$privacy = getPrivacySettings($conn, $profile_id);

// Helper function to check if viewer can see a field
function canViewField($privacy_level, $viewer_id, $profile_id)
{
    if ($privacy_level === null)
        return false;
    if ($viewer_id == $profile_id)
        return true; // Always show to owner
    if ($privacy_level === 'Public')
        return true;
    if ($privacy_level === 'Connections') {
        // TODO: Check if viewer is a connection
        // For now, allow if both are alumni
        return true;
    }
    if ($privacy_level === 'Admin Only')
        return false; // Only admins can see
    return false;
}

function displayField($field, $privacy_level, $viewer_id, $profile_id)
{
    if (canViewField($privacy_level, $viewer_id, $profile_id)) {
        return htmlspecialchars($field ?? 'N/A');
    }
    return '<span class="text-gray-400 italic">Not visible</span>';
}

function displayFieldWithLabel($label, $value, $privacy_level, $viewer_id, $profile_id)
{
    if (!canViewField($privacy_level, $viewer_id, $profile_id)) {
        return '';
    }
    return '<div>
        <label class="text-sm text-gray-600">' . htmlspecialchars($label) . '</label>
        <p class="font-medium">' . htmlspecialchars($value ?? 'N/A') . '</p>
    </div>';
}
?>

<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1" name="viewport" />
    <title><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?> - Profile - Fatima Alumni</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
    <style>
        body::-webkit-scrollbar {
            width: 8px;
        }
        body::-webkit-scrollbar-thumb {
            background-color: #8b0a1c;
            border-radius: 4px;
        }
    </style>
</head>
<body class="bg-white text-gray-900 font-sans">
	<!-- Mini Header (Universal Include) -->
	<?php include __DIR__ . '/al_header_universal.php'; ?>

    <!-- MAIN CONTENT -->
    <main class="pt-8 px-4 sm:px-6 md:px-10 max-w-7xl mx-auto">
        <div class="mb-4">
            <a href="al_directory.php" class="inline-flex items-center text-green-600 hover:text-green-700">
                <i class="fas fa-arrow-left mr-2"></i>
                Back to Directory
            </a>
        </div>
        
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <div class="flex flex-col md:flex-row items-start md:items-center gap-6 mb-8">
                <div class="relative">
                    <?php
$photo_visible = canViewField($privacy['photo_visibility'] ?? 'Public', $viewer_id, $profile_id);
$photo_displayed = false;
$photo_path = '';

if ($photo_visible && !empty($user['photo'])) {
    // Check if photo is a full URL
    if (strpos($user['photo'], 'http') === 0 || strpos($user['photo'], 'https') === 0) {
        $photo_path = $user['photo'];
        $photo_displayed = true;
    }
    else {
        // Use image proxy to bypass .htaccess restrictions
        $photo_path = 'serve_profile_image.php?img=' . urlencode($user['photo']);
        $photo_displayed = true;
    }
}

if ($photo_displayed):
?>
                        <img src="<?php echo htmlspecialchars($photo_path); ?>" 
                             alt="Profile Photo" 
                             class="w-32 h-32 rounded-full mx-auto object-cover border-4 border-green-100"
                             onerror="this.onerror=null; this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="w-32 h-32 rounded-full mx-auto bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center text-white text-4xl font-bold border-4 border-green-100" style="display: none;">
                            <?php echo strtoupper(substr($user['firstname'] ?? '', 0, 1) . substr($user['lastname'] ?? '', 0, 1)); ?>
                        </div>
                    <?php
else: ?>
                        <div class="w-32 h-32 rounded-full mx-auto bg-gradient-to-br from-green-500 to-green-600 flex items-center justify-center text-white text-4xl font-bold border-4 border-green-100">
                            <?php echo strtoupper(substr($user['firstname'] ?? '', 0, 1) . substr($user['lastname'] ?? '', 0, 1)); ?>
                        </div>
                    <?php
endif; ?>
                </div>
                <div class="flex-1">
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">
                        <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>
                    </h1>
                    <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($user['program'] ?? ''); ?></p>
                    <?php if (!empty($user['position'])): ?>
                        <p class="text-gray-700 font-medium mb-2"><?php echo htmlspecialchars($user['position']); ?></p>
                    <?php
endif; ?>
                    <?php if (!empty($user['company'])): ?>
                        <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($user['company']); ?></p>
                    <?php
endif; ?>
                    <a href="al_directory_sendmail.php?to=<?php echo $profile_id; ?>" class="inline-flex items-center px-4 py-2 bg-green-700 text-white rounded-md hover:bg-green-800 transition-colors">
                        <i class="fas fa-envelope mr-2"></i>
                        Send Message
                    </a>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Personal Information -->
                <div class="bg-gray-50 rounded-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-user mr-2 text-green-700"></i>
                        Personal Information
                    </h2>
                    <div class="space-y-4">
                        <div>
                            <label class="text-sm text-gray-600">Full Name</label>
                            <p class="font-medium"><?php $fullName = trim(($user['firstname'] ?? '') . ' ' . ($user['middlename'] ?? '') . ' ' . ($user['lastname'] ?? '') . ' ' . ($user['name_ext'] ?? ''));
echo htmlspecialchars(preg_replace('/\s+/', ' ', $fullName)); ?></p>
                        </div>
                        <?php echo displayFieldWithLabel('Birthday', $user['birthday'] ?? '', $privacy['birthday_visibility'] ?? 'Private', $viewer_id, $profile_id); ?>
                        <?php echo displayFieldWithLabel('Age', $user['age'] ?? '', $privacy['age_visibility'] ?? 'Private', $viewer_id, $profile_id); ?>
                        <?php echo displayFieldWithLabel('Gender', $user['gender'] ?? '', $privacy['gender_visibility'] ?? 'Public', $viewer_id, $profile_id); ?>
                        <?php echo displayFieldWithLabel('Civil Status', $user['civil_status'] ?? '', $privacy['civil_status_visibility'] ?? 'Private', $viewer_id, $profile_id); ?>
                        <?php echo displayFieldWithLabel('Religion', $user['religion'] ?? '', $privacy['religion_visibility'] ?? 'Private', $viewer_id, $profile_id); ?>
                        <?php echo displayFieldWithLabel('Nationality', $user['nationality'] ?? '', $privacy['nationality_visibility'] ?? 'Public', $viewer_id, $profile_id); ?>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="bg-gray-50 rounded-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-address-card mr-2 text-green-700"></i>
                        Contact Information
                    </h2>
                    <div class="space-y-4">
                        <?php echo displayFieldWithLabel('Email', $user['email'] ?? '', $privacy['contact_visibility'] ?? 'Private', $viewer_id, $profile_id); ?>
                        <?php echo displayFieldWithLabel('Address', $user['address'] ?? '', $privacy['contact_visibility'] ?? 'Private', $viewer_id, $profile_id); ?>
                        <?php echo displayFieldWithLabel('Personal Contact', $user['personal_contact'] ?? '', $privacy['contact_visibility'] ?? 'Private', $viewer_id, $profile_id); ?>
                    </div>
                </div>

                <!-- Academic Information -->
                <div class="bg-gray-50 rounded-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-graduation-cap mr-2 text-green-700"></i>
                        Academic Information
                    </h2>
                    <div class="space-y-4">
                        <?php echo displayFieldWithLabel('Program', $user['program'] ?? '', 'Public', $viewer_id, $profile_id); ?>
                        <?php echo displayFieldWithLabel('Campus', $user['campus'] ?? '', 'Public', $viewer_id, $profile_id); ?>
                        <?php echo displayFieldWithLabel('Month Graduated', $user['month_graduated'] ?? '', 'Public', $viewer_id, $profile_id); ?>
                        <?php echo displayFieldWithLabel('Year Graduated', $user['year_graduated'] ?? '', 'Public', $viewer_id, $profile_id); ?>
                        <?php echo displayFieldWithLabel('Post Graduate', $user['post_grad'] ?? '', 'Public', $viewer_id, $profile_id); ?>
                        <?php echo displayFieldWithLabel('Licensure Exam', $user['licensure_exam'] ?? '', 'Public', $viewer_id, $profile_id); ?>
                        <?php echo displayFieldWithLabel('Club Involvement', $user['club_involvement'] ?? '', 'Public', $viewer_id, $profile_id); ?>
                    </div>
                </div>

                <!-- Employment Information -->
                <div class="bg-gray-50 rounded-lg p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-briefcase mr-2 text-green-700"></i>
                        Employment Information
                    </h2>
                    <div class="space-y-4">
                        <?php echo displayFieldWithLabel('Employment Status', $user['employment_status'] ?? '', $privacy['employment_visibility'] ?? 'Public', $viewer_id, $profile_id); ?>
                        <?php echo displayFieldWithLabel('Company', $user['company'] ?? '', $privacy['employment_visibility'] ?? 'Public', $viewer_id, $profile_id); ?>
                        <?php echo displayFieldWithLabel('Industry', $user['industry'] ?? '', $privacy['employment_visibility'] ?? 'Public', $viewer_id, $profile_id); ?>
                        <?php echo displayFieldWithLabel('Position', $user['position'] ?? '', $privacy['employment_visibility'] ?? 'Public', $viewer_id, $profile_id); ?>
                        <?php echo displayFieldWithLabel('Employment History', $user['employment_history'] ?? '', $privacy['employment_visibility'] ?? 'Public', $viewer_id, $profile_id); ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
<?php if (file_exists(__DIR__ . '/al_footer_universal.php')) {
    include 'al_footer_universal.php';
}?>
<?php $conn->close(); ?>
</body>
</html>

