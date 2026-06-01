<?php
session_start();
require_once 'config.php';
alumni_otp_gate_after_session();

if (!isset($_SESSION['user_id'])) {
    header("Location: al_home.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch notifications for the current user
$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
	error_log('Prepare failed for notifications query (mycareer): ' . $conn->error);
	$notifications = [];
} else {
	$stmt->bind_param("i", $user_id);
	$stmt->execute();
	$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
$notification_count = count(array_filter($notifications, function($n) { return !$n['is_read']; }));

// Fetch user data
$sql = "SELECT * FROM itcp WHERE id = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
	error_log('Prepare failed for user query (mycareer): ' . $conn->error);
	$user = [];
} else {
	$stmt->bind_param("i", $user_id);
	$stmt->execute();
	$result = $stmt->get_result();
	$user = $result->fetch_assoc();
}

// Fetch career milestones
$sql = "SELECT * FROM career_milestones WHERE alumni_id = ? ORDER BY date_achieved DESC";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
	error_log('Prepare failed for career_milestones (mycareer): ' . $conn->error);
	$milestones = [];
} else {
	$stmt->bind_param("i", $user_id);
	$stmt->execute();
	$milestones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Fetch visibility settings
$sql = "SELECT section_name, visibility FROM career_visibility_settings WHERE alumni_id = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
	error_log('Prepare failed for career_visibility_settings (mycareer): ' . $conn->error);
	$visibility_settings = [];
} else {
	$stmt->bind_param("i", $user_id);
	$stmt->execute();
	$visibility_settings = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Calculate profile completeness
$required_fields = ['current_position', 'company', 'industry', 'skills', 'education'];
$completed_fields = 0;
foreach ($required_fields as $field) {
    if (!empty($user[$field])) $completed_fields++;
}
$completeness = ($completed_fields / count($required_fields)) * 100;

// Fetch version history (if implemented)
$sql = "SELECT * FROM career_history WHERE alumni_id = ? ORDER BY updated_at DESC LIMIT 5";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
	error_log('Prepare failed for career_history (mycareer): ' . $conn->error);
	$version_history = [];
} else {
	$stmt->bind_param("i", $user_id);
	$stmt->execute();
	$version_history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Fetch suggested milestones (if implemented)
$sql = "SELECT * FROM suggested_milestones WHERE alumni_id = ? AND status = 'pending'";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
	error_log('Prepare failed for suggested_milestones (mycareer): ' . $conn->error);
	$suggested_milestones = [];
} else {
	$stmt->bind_param("i", $user_id);
	$stmt->execute();
	$suggested_milestones = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Career Timeline - OLFU Alumni</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
    <script src="https://unpkg.com/@popperjs/core@2"></script>
    <script src="https://unpkg.com/tippy.js@6"></script>
    <style>
        .timeline-item {
            position: relative;
            padding-left: 2rem;
            padding-bottom: 2rem;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: #e5e7eb;
        }
        .timeline-item::after {
            content: '';
            position: absolute;
            left: -4px;
            top: 0;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: #059669;
        }
        .timeline-item:last-child::before {
            height: 0;
        }
        .tooltip {
            position: relative;
            display: inline-block;
        }
        .progress-bar {
            transition: width 0.5s ease-in-out;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header (Universal Include) -->
    <?php include __DIR__ . '/al_header_universal.php'; ?>

    <!-- Main Content -->
    <main class="pt-20 px-4 sm:px-6 md:px-10 max-w-7xl mx-auto">
        <!-- Profile Completeness Bar -->
        <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-semibold text-gray-900">Profile Completeness</h2>
                <span id="completenessText" class="text-sm font-medium text-gray-600"><?php echo round($completeness); ?>% Complete</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2.5">
                <div id="completenessBar" class="progress-bar bg-green-600 h-2.5 rounded-full" style="width: <?php echo $completeness; ?>%"></div>
            </div>
            <p id="completenessHelp" class="mt-2 text-sm text-gray-600">
                Add more information to complete your profile. 
                <a href="al_profile.php" class="text-green-600 hover:text-green-700">Update Profile</a>
            </p>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Left Column - Career Timeline -->
            <div class="flex-1">
                <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
                    <div class="flex justify-between items-center mb-6">
                        <h1 class="text-2xl font-bold text-gray-900">My Career Timeline</h1>
                        <div class="flex space-x-4">
                            <button onclick="previewPublicProfile()" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                <i class="fas fa-eye mr-2"></i>
                                Preview Public Profile
                            </button>
                            <a href="add_milestone.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                <i class="fas fa-plus mr-2"></i>
                                Add Milestone
                            </a>
                        </div>
                    </div>

                    <!-- Suggested Milestones -->
                    <?php if (!empty($suggested_milestones)): ?>
                        <div class="mb-8 p-4 bg-blue-50 rounded-lg">
                            <h3 class="text-lg font-medium text-blue-900 mb-4">Suggested Updates</h3>
                            <div class="space-y-4">
                                <?php foreach ($suggested_milestones as $suggestion): ?>
                                    <div class="flex items-center justify-between bg-white p-4 rounded-lg shadow-sm">
                                        <div>
                                            <h4 class="font-medium text-gray-900"><?php echo htmlspecialchars($suggestion['title']); ?></h4>
                                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($suggestion['description']); ?></p>
                                        </div>
                                        <div class="flex space-x-2">
                                            <button onclick="acceptSuggestion(<?php echo $suggestion['id']; ?>)" class="px-3 py-1 text-sm text-green-600 hover:text-green-700">
                                                <i class="fas fa-check mr-1"></i> Accept
                                            </button>
                                            <button onclick="rejectSuggestion(<?php echo $suggestion['id']; ?>)" class="px-3 py-1 text-sm text-red-600 hover:text-red-700">
                                                <i class="fas fa-times mr-1"></i> Reject
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Existing Timeline Content -->
                    <?php if (empty($milestones)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-briefcase text-4xl text-gray-400 mb-4"></i>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No Career Milestones Yet</h3>
                            <p class="text-gray-500 mb-6">Start building your career timeline by adding your first milestone.</p>
                            <a href="add_milestone.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                <i class="fas fa-plus mr-2"></i>
                                Add Your First Milestone
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-8">
                            <?php foreach ($milestones as $milestone): ?>
                                <div class="timeline-item">
                                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                                        <div class="flex justify-between items-start mb-2">
                                            <div>
                                                <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($milestone['title']); ?></h3>
                                                <p class="text-sm text-gray-500"><?php echo date('F Y', strtotime($milestone['date_achieved'])); ?></p>
                                            </div>
                                            <div class="flex items-center space-x-2">
                                                <span class="px-2 py-1 text-xs font-medium rounded-full 
                                                    <?php
                                                    switch ($milestone['milestone_type']) {
                                                        case 'Education':
                                                            echo 'bg-blue-100 text-blue-800';
                                                            break;
                                                        case 'Employment':
                                                            echo 'bg-green-100 text-green-800';
                                                            break;
                                                        case 'Certification':
                                                            echo 'bg-purple-100 text-purple-800';
                                                            break;
                                                        case 'Award':
                                                            echo 'bg-yellow-100 text-yellow-800';
                                                            break;
                                                        case 'Project':
                                                            echo 'bg-indigo-100 text-indigo-800';
                                                            break;
                                                        default:
                                                            echo 'bg-gray-100 text-gray-800';
                                                    }
                                                    ?>">
                                                    <?php echo htmlspecialchars($milestone['milestone_type']); ?>
                                                </span>
                                                <button onclick="showVersionHistory(<?php echo $milestone['id']; ?>)" class="text-gray-400 hover:text-gray-600">
                                                    <i class="fas fa-history"></i>
                                                </button>
                                                <button 
                                                    onclick="showEditMilestoneModal(this.dataset.milestone)" 
                                                    data-milestone='<?php echo htmlspecialchars(json_encode($milestone), ENT_QUOTES, 'UTF-8'); ?>'
                                                    class="text-green-600 hover:text-green-700">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <p class="text-gray-600 mb-4"><?php echo nl2br(htmlspecialchars($milestone['description'])); ?></p>
                                        <div class="flex items-center justify-between text-sm text-gray-500">
                                            <div class="flex items-center">
                                                <i class="fas fa-eye mr-1"></i>
                                                <span><?php echo htmlspecialchars($milestone['visibility']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column - Stats and Settings -->
            <div class="lg:w-80">
                <!-- Skills & Expertise -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-8">
                    <div class="flex items-center justify-between mb-3">
                        <h2 class="text-lg font-semibold text-gray-900">Skills & Expertise</h2>
                        <span id="skillsCount" class="text-xs text-gray-500">0 tags</span>
                    </div>
                    <div class="mb-3">
                        <div class="flex items-center gap-2">
                            <input id="skillInput" type="text" placeholder="Add a skill and press Enter" class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-600 focus:border-green-600 text-sm" />
                            <button id="addSkillBtn" class="px-3 py-2 bg-green-600 text-white rounded-md text-sm hover:bg-green-700">Add</button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Examples: Java, Project Management, Figma, SQL</p>
                    </div>
                    <div id="skillsTags" class="flex flex-wrap gap-2"></div>
                </div>
                <!-- Privacy Settings -->
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Privacy Settings</h2>
                    <div class="space-y-4">
                        <?php foreach ($visibility_settings as $setting): ?>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <span class="text-sm text-gray-700"><?php echo ucwords(str_replace('_', ' ', $setting['section_name'])); ?></span>
                                    <i class="fas fa-info-circle ml-2 text-gray-400 cursor-help" 
                                       data-tippy-content="<?php 
                                           switch($setting['section_name']) {
                                               case 'career_timeline':
                                                   echo 'Control who can see your career history and milestones';
                                                   break;
                                               case 'skills':
                                                   echo 'Manage visibility of your professional skills';
                                                   break;
                                               case 'achievements':
                                                   echo 'Set who can view your awards and certifications';
                                                   break;
                                               default:
                                                   echo 'Control visibility of this section';
                                           }
                                       ?>"></i>
                                </div>
                                <select class="text-sm border-gray-300 rounded-md focus:ring-green-500 focus:border-green-500">
                                    <option value="Private" <?php echo $setting['visibility'] === 'Private' ? 'selected' : ''; ?>>Private</option>
                                    <option value="Admin Only" <?php echo $setting['visibility'] === 'Admin Only' ? 'selected' : ''; ?>>Admin Only</option>
                                    <option value="Public" <?php echo $setting['visibility'] === 'Public' ? 'selected' : ''; ?>>Public</option>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Version History Modal -->
    <div id="versionHistoryModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-lg w-full p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Version History</h3>
                    <button onclick="closeVersionHistory()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div id="versionHistoryContent" class="space-y-4">
                    <!-- Version history content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Public Profile Preview Modal -->
    <div id="publicProfileModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-white rounded-lg max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-6">
                <div class="flex justify-between items-start mb-4">
                    <h2 class="text-2xl font-bold text-gray-900">Public Profile Preview</h2>
                    <button onclick="closePublicProfileModal()" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div id="publicProfileContent" class="min-h-[200px]">
                    <!-- Content from preview_profile.php will be loaded here -->
                    <div class="text-center text-gray-500 py-8">
                        <i class="fas fa-spinner fa-spin text-4xl mb-4"></i>
                        <p>Loading preview...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize tooltips
        tippy('[data-tippy-content]', {
            placement: 'right',
            arrow: true
        });


        // Skills & Expertise
        document.addEventListener('DOMContentLoaded', function() {
            const skillsTags = document.getElementById('skillsTags');
            const skillsCount = document.getElementById('skillsCount');
            const input = document.getElementById('skillInput');
            const addBtn = document.getElementById('addSkillBtn');
            if (!skillsTags || !input || !addBtn) return;

            const render = (skills) => {
                skillsTags.innerHTML = '';
                skills.forEach(name => {
                    const tag = document.createElement('button');
                    tag.type = 'button';
                    tag.className = 'group inline-flex items-center gap-2 bg-green-50 text-green-800 border border-green-200 px-2 py-1 rounded-full text-xs hover:bg-green-100';
                    tag.innerHTML = `<span class="truncate max-w-[140px]">${name}</span>` +
                        `<span class="text-gray-400 group-hover:text-red-500" title="Remove" aria-label="Remove">×</span>`;
                    tag.addEventListener('click', (e) => {
                        // If click on the close (second span), remove; otherwise navigate to directory
                        if (e.target !== tag.firstChild) {
                            removeSkill(name);
                        } else {
                            window.location.href = 'al_directory.php?keyword=' + encodeURIComponent(name);
                        }
                    });
                    skillsTags.appendChild(tag);
                });
                skillsCount.textContent = `${skills.length} tag${skills.length === 1 ? '' : 's'}`;
                updateCompletenessPreview();
            };

            const load = async () => {
                const res = await fetch('api_mycareer_skills.php');
                if (!res.ok) return;
                const data = await res.json();
                render(data.skills || []);
            };

            const addSkills = async (names) => {
                if (!names || names.length === 0) return load();
                await fetch('api_mycareer_skills.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ tags: names }) });
                input.value = '';
                await load();
            };

            const removeSkill = async (name) => {
                await fetch('api_mycareer_skills.php', { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ name }) });
                await load();
            };

            const parseInput = (text) => {
                return text.split(',').map(t => t.trim()).filter(Boolean);
            };

            addBtn.addEventListener('click', () => addSkills(parseInput(input.value)));
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addSkills(parseInput(input.value));
                }
            });

            function updateCompletenessPreview() {
                // Optional: could call a lightweight endpoint; here we simply nudge the bar visually if present
                const bar = document.querySelector('.progress-bar');
                // No-op if bar is server-computed; leave as-is to avoid drift
            }

            load();
            // Also update overall completeness from server on page load
            refreshCompleteness();
            async function refreshCompleteness() {
                try {
                    const res = await fetch('api_mycareer_completeness.php');
                    if (!res.ok) return;
                    const data = await res.json();
                    const bar = document.getElementById('completenessBar');
                    const text = document.getElementById('completenessText');
                    const help = document.getElementById('completenessHelp');
                    if (bar) bar.style.width = (data.completeness || 0) + '%';
                    if (text) text.textContent = (data.completeness || 0) + '% Complete';
                    if (help) help.classList.toggle('hidden', (data.completeness || 0) >= 100);
                } catch (e) {
                    // silent fail
                }
            }
        });
        // Handle privacy settings changes
        document.addEventListener('DOMContentLoaded', function() {
            const privacySelects = document.querySelectorAll('select');
            privacySelects.forEach(select => {
                select.addEventListener('change', function() {
                    // Add AJAX call to update privacy settings
                    console.log('Privacy setting changed:', this.value);
                });
            });
        });

        // Preview public profile
        async function previewPublicProfile() {
            const modal = document.getElementById('publicProfileModal');
            const contentDiv = document.getElementById('publicProfileContent');
            
            // Show modal with loading indicator
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
            
            try {
                // Fetch the content of preview_profile.php
                const response = await fetch('preview_profile.php');
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const html = await response.text();
                
                // Load the fetched HTML into the modal content div
                contentDiv.innerHTML = html;
                
            } catch (error) {
                console.error('Error loading public profile preview:', error);
                contentDiv.innerHTML = '<div class="text-center text-red-600 py-8"><i class="fas fa-exclamation-circle text-4xl mb-4"></i><p>Failed to load preview.</p></div>';
            }
        }

        function closePublicProfileModal() {
            const modal = document.getElementById('publicProfileModal');
            modal.classList.add('hidden');
            document.body.style.overflow = ''; // Restore background scrolling
            // Optional: Clear content when closing
            document.getElementById('publicProfileContent').innerHTML = '<div class="text-center text-gray-500 py-8"><i class="fas fa-spinner fa-spin text-4xl mb-4"></i><p>Loading preview...</p></div>';
        }

        // Close modal when clicking outside
        document.getElementById('publicProfileModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePublicProfileModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !document.getElementById('publicProfileModal').classList.contains('hidden')) {
                closePublicProfileModal();
            }
        });

        // Show version history
        function showVersionHistory(milestoneId) {
            const modal = document.getElementById('versionHistoryModal');
            const content = document.getElementById('versionHistoryContent');
            
            // Fetch version history via AJAX
            fetch(`get_version_history.php?milestone_id=${milestoneId}`)
                .then(response => response.json())
                .then(data => {
                    content.innerHTML = data.map(version => `
                        <div class="border-b pb-4">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="font-medium">${version.title}</h4>
                                    <p class="text-sm text-gray-500">${version.date}</p>
                                </div>
                                <span class="text-xs text-gray-500">${version.changes}</span>
                            </div>
                        </div>
                    `).join('');
                    
                    modal.classList.remove('hidden');
                });
        }

        function closeVersionHistory() {
            document.getElementById('versionHistoryModal').classList.add('hidden');
        }

        // Handle suggested milestones
        function acceptSuggestion(suggestionId) {
            // Add AJAX call to accept suggestion
            console.log('Accepting suggestion:', suggestionId);
        }

        function rejectSuggestion(suggestionId) {
            // Add AJAX call to reject suggestion
            console.log('Rejecting suggestion:', suggestionId);
        }

        // Add these functions to your existing script block
        window.showEditMilestoneModal = function(milestoneData) {
            // Parse the milestone data if it's a string
            const milestone = typeof milestoneData === 'string' ? JSON.parse(milestoneData) : milestoneData;
            
            // Log the complete milestone object
            console.log('Complete milestone data:', milestone);
            
            // Get the modal and form elements
            const modal = document.getElementById('editMilestoneModal');
            const form = document.getElementById('editMilestoneForm');
            
            if (!modal || !form) {
                console.error('Modal or form elements not found');
                return;
            }

            try {
                // Format the date for the date input (YYYY-MM-DD)
                let formattedDate = '';
                if (milestone.date_achieved) {
                    const dateAchieved = new Date(milestone.date_achieved);
                    if (!isNaN(dateAchieved.getTime())) {
                        formattedDate = dateAchieved.toISOString().split('T')[0];
                    }
                }

                // Populate the form fields
                document.getElementById('editMilestoneId').value = milestone.id || '';
                document.getElementById('editMilestoneTitle').value = milestone.title || '';
                document.getElementById('editMilestoneDescription').value = milestone.description || '';
                document.getElementById('editMilestoneDate').value = formattedDate;
                document.getElementById('editMilestonePrivacy').value = milestone.visibility || 'Public';

                // Log the values being set
                console.log('Setting form values:', {
                    id: milestone.id,
                    title: milestone.title,
                    description: milestone.description,
                    date: formattedDate,
                    originalDate: milestone.date_achieved,
                    visibility: milestone.visibility
                });

                // Show the modal
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden'; // Prevent background scrolling
            } catch (error) {
                console.error('Error populating milestone data:', error);
                alert('Error loading milestone data. Please try again.');
            }
        };

        window.closeEditMilestoneModal = function() {
            const modal = document.getElementById('editMilestoneModal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = ''; // Restore background scrolling
            }
        };

        // Handle form submission
        document.addEventListener('DOMContentLoaded', function() {
            const editMilestoneForm = document.getElementById('editMilestoneForm');
            if (editMilestoneForm) {
                editMilestoneForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    const milestoneId = document.getElementById('editMilestoneId').value;
                    formData.append('id', milestoneId); // Add the milestone ID to the form data
                    
                    // Debug: Log the form data
                    console.log('Submitting form data:');
                    for (let pair of formData.entries()) {
                        console.log(pair[0] + ': ' + pair[1]);
                    }
                    
                    // Add AJAX call to update milestone
                    fetch('update_milestone.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        console.log('Server response:', data); // Debug: Log server response
                        if (data.success) {
                            // Close modal and refresh page to show updated data
                            closeEditMilestoneModal();
                            window.location.reload();
                        } else {
                            alert('Error updating milestone: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while updating the milestone');
                    });
                });
            }
        });

    </script>

    <!-- Edit Milestone Modal -->
    <div id="editMilestoneModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-lg w-full p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Edit Milestone</h3>
                    <button onclick="closeEditMilestoneModal()" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form id="editMilestoneForm" class="space-y-4">
                    <input type="hidden" id="editMilestoneId" name="id">
                    <div>
                        <label for="editMilestoneTitle" class="block text-sm font-medium text-gray-700">Title</label>
                        <input type="text" id="editMilestoneTitle" name="title" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                    </div>
                    <div>
                        <label for="editMilestoneDescription" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea id="editMilestoneDescription" name="description" rows="3" required
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"></textarea>
                    </div>
                    <div>
                        <label for="editMilestoneDate" class="block text-sm font-medium text-gray-700">Date Achieved</label>
                        <input type="date" id="editMilestoneDate" name="date_achieved" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                    </div>
                    <div>
                        <label for="editMilestonePrivacy" class="block text-sm font-medium text-gray-700">Privacy Setting</label>
                        <select id="editMilestonePrivacy" name="visibility" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500">
                            <option value="Public">Public</option>
                            <option value="Private">Private</option>
                            <option value="Admin Only">Admin Only</option>
                        </select>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeEditMilestoneModal()" 
                                class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php if (file_exists(__DIR__ . '/al_footer_universal.php')) { include 'al_footer_universal.php'; } ?>
</body>
</html> 