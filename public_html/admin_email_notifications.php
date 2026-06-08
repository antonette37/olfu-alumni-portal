<?php
/**
 * Admin Email Notifications Helper
 * Sends email notifications to admin when new users, inquiries, or content requests are submitted
 */

// Admin email address - use environment variable or define in email_config.php
if (!defined('ADMIN_EMAIL')) {
    define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'admin@alumni.local');
}

/**
 * Send admin notification email for new user registration
 */
function sendAdminNewUserNotification($userData) {
    if (!function_exists('sendEmail')) {
        require_once __DIR__ . '/mail.php';
    }
    
    $subject = 'New User Registration - OLFU Alumni Portal';
    $body = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
        <h2 style="color: #047857; border-bottom: 2px solid #047857; padding-bottom: 10px;">New User Registration</h2>
        <p>A new user has registered and is pending approval.</p>
        <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <p><strong>Name:</strong> ' . htmlspecialchars($userData['firstname'] . ' ' . ($userData['middlename'] ?? '') . ' ' . $userData['lastname']) . '</p>
            <p><strong>Email:</strong> ' . htmlspecialchars($userData['email']) . '</p>
            <p><strong>Student Number:</strong> ' . htmlspecialchars($userData['student_number'] ?? 'N/A') . '</p>
            <p><strong>Program:</strong> ' . htmlspecialchars($userData['program'] ?? 'N/A') . '</p>
            <p><strong>Registration Date:</strong> ' . date('M d, Y H:i', strtotime($userData['date_joined'] ?? 'now')) . '</p>
        </div>
        <div style="background: #ecfdf5; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <p style="margin: 0;"><strong>Action Required:</strong></p>
            <p style="margin: 5px 0 0 0;">Log in to the Admin panel → User Management to review and approve this registration.</p>
        </div>
        <p style="color: #6b7280; font-size: 12px;">This is an automated notification from the OLFU Alumni System.</p>
    </div>';
    
    try {
        return @sendEmail(ADMIN_EMAIL, $subject, $body);
    } catch (Exception $e) {
        error_log("Failed to send admin new user notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Send admin notification email for new inquiry
 */
function sendAdminNewInquiryNotification($inquiryData) {
    if (!function_exists('sendEmail')) {
        require_once __DIR__ . '/mail.php';
    }
    
    $subject = 'New Alumni Inquiry - OLFU Alumni Portal';
    $body = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
        <h2 style="color: #047857; border-bottom: 2px solid #047857; padding-bottom: 10px;">New Alumni Inquiry</h2>
        <p>You have received a new inquiry from the Alumni Contact Us form.</p>
        <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <p><strong>Name:</strong> ' . htmlspecialchars($inquiryData['name']) . '</p>
            <p><strong>Email:</strong> ' . htmlspecialchars($inquiryData['email']) . '</p>
            ' . (!empty($inquiryData['subject']) ? '<p><strong>Subject:</strong> ' . htmlspecialchars($inquiryData['subject']) . '</p>' : '') . '
            <p><strong>Message:</strong></p>
            <div style="background: white; padding: 15px; border-radius: 5px; border-left: 4px solid #047857;">
                ' . nl2br(htmlspecialchars($inquiryData['message'])) . '
            </div>
        </div>
        <div style="background: #ecfdf5; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <p style="margin: 0;"><strong>Action Required:</strong></p>
            <p style="margin: 5px 0 0 0;">Log in to the Admin panel → Communication & Support → Manage Inquiries to respond to this inquiry.</p>
        </div>
        <p style="color: #6b7280; font-size: 12px;">This is an automated notification from the OLFU Alumni System.</p>
    </div>';
    
    try {
        return @sendEmail(ADMIN_EMAIL, $subject, $body);
    } catch (Exception $e) {
        error_log("Failed to send admin new inquiry notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Send admin notification email for new job post (content request)
 */
function sendAdminNewJobPostNotification($jobData) {
    if (!function_exists('sendEmail')) {
        require_once __DIR__ . '/mail.php';
    }
    
    $subject = 'New Job Post Pending Approval - OLFU Alumni Portal';
    $body = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
        <h2 style="color: #047857; border-bottom: 2px solid #047857; padding-bottom: 10px;">New Job Post Pending Approval</h2>
        <p>A new job post has been submitted and is pending admin approval.</p>
        <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <p><strong>Job Title:</strong> ' . htmlspecialchars($jobData['title']) . '</p>
            <p><strong>Company:</strong> ' . htmlspecialchars($jobData['company']) . '</p>
            <p><strong>Location:</strong> ' . htmlspecialchars($jobData['location']) . '</p>
            <p><strong>Job Type:</strong> ' . htmlspecialchars($jobData['job_type']) . '</p>
            <p><strong>Salary Range:</strong> ' . htmlspecialchars($jobData['salary_range']) . '</p>
            <p><strong>Posted By:</strong> ' . htmlspecialchars($jobData['author_name']) . '</p>
            <p><strong>Posted Date:</strong> ' . date('M d, Y H:i', strtotime($jobData['posted_date'] ?? 'now')) . '</p>
        </div>
        <div style="background: #ecfdf5; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <p style="margin: 0;"><strong>Action Required:</strong></p>
            <p style="margin: 5px 0 0 0;">Log in to the Admin panel → Content Management to review and approve this job post.</p>
        </div>
        <p style="color: #6b7280; font-size: 12px;">This is an automated notification from the OLFU Alumni System.</p>
    </div>';
    
    try {
        return @sendEmail(ADMIN_EMAIL, $subject, $body);
    } catch (Exception $e) {
        error_log("Failed to send admin new job post notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Send admin notification email for new success story (content request)
 */
function sendAdminNewStoryNotification($storyData) {
    if (!function_exists('sendEmail')) {
        require_once __DIR__ . '/mail.php';
    }
    
    $subject = 'New Success Story Pending Approval - OLFU Alumni Portal';
    $body = '
    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
        <h2 style="color: #047857; border-bottom: 2px solid #047857; padding-bottom: 10px;">New Success Story Pending Approval</h2>
        <p>A new success story has been submitted and is pending admin approval.</p>
        <div style="background: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <p><strong>Title:</strong> ' . htmlspecialchars($storyData['title']) . '</p>
            <p><strong>Author:</strong> ' . htmlspecialchars($storyData['author_name']) . '</p>
            <p><strong>Program:</strong> ' . htmlspecialchars($storyData['author_program'] ?? 'N/A') . '</p>
            <p><strong>Year Graduated:</strong> ' . htmlspecialchars($storyData['author_year'] ?? 'N/A') . '</p>
            <p><strong>Submitted Date:</strong> ' . date('M d, Y H:i', strtotime($storyData['created_at'] ?? 'now')) . '</p>
        </div>
        <div style="background: #ecfdf5; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <p style="margin: 0;"><strong>Action Required:</strong></p>
            <p style="margin: 5px 0 0 0;">Log in to the Admin panel → Content Management to review and approve this success story.</p>
        </div>
        <p style="color: #6b7280; font-size: 12px;">This is an automated notification from the OLFU Alumni System.</p>
    </div>';
    
    try {
        return @sendEmail(ADMIN_EMAIL, $subject, $body);
    } catch (Exception $e) {
        error_log("Failed to send admin new story notification: " . $e->getMessage());
        return false;
    }
}
