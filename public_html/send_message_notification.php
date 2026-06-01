<?php
require_once 'config.php';
require_once 'mail.php';

// Function to send message notification email
function sendMessageNotification($receiver_id, $sender_name, $subject, $message_preview) {
    global $conn;
    
    // Get receiver's email
    $stmt = $conn->prepare("SELECT email, firstname, lastname FROM itcp WHERE id = ?");
    $stmt->bind_param("i", $receiver_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $receiver = $result->fetch_assoc();
    
    if (!$receiver) {
        return false;
    }
    
    $receiver_email = $receiver['email'];
    $receiver_name = $receiver['firstname'] . ' ' . $receiver['lastname'];
    
    // Email subject
    $email_subject = "New Message from " . $sender_name . " - Alumni Portal";
    
    // Email body
    $email_body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
            .message-box { background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #8b5cf6; margin: 20px 0; }
            .button { display: inline-block; background: #8b5cf6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 20px 0; }
            .footer { text-align: center; margin-top: 30px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>New Message Received</h2>
                <p>Alumni Portal - Our Lady of Fatima University</p>
            </div>
            <div class='content'>
                <p>Hello <strong>" . htmlspecialchars($receiver_name) . "</strong>,</p>
                
                <p>You have received a new message from <strong>" . htmlspecialchars($sender_name) . "</strong>.</p>
                
                <div class='message-box'>
                    <h3>Subject: " . htmlspecialchars($subject) . "</h3>
                    <p><strong>Preview:</strong></p>
                    <p>" . htmlspecialchars($message_preview) . "</p>
                </div>
                
                <p>To read and reply to this message, please log in to your alumni portal.</p>
                
                <div style='text-align: center;'>
                    <a href='http://localhost/capsing/al_messages.php' class='button'>View Message</a>
                </div>
                
                <p><small>This is an automated notification. Please do not reply to this email.</small></p>
            </div>
            <div class='footer'>
                <p>© 2024 Our Lady of Fatima University - Alumni Portal</p>
                <p>This email was sent because you have an account with our alumni system.</p>
            </div>
        </div>
    </body>
    </html>";
    
    // Send email
    return sendEmail($receiver_email, $email_subject, $email_body);
}

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['receiver_id']) && isset($input['sender_name']) && isset($input['subject']) && isset($input['message_preview'])) {
        $result = sendMessageNotification(
            $input['receiver_id'],
            $input['sender_name'],
            $input['subject'],
            $input['message_preview']
        );
        
        echo json_encode(['success' => $result]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    }
}
?>
