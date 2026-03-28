<?php
/**
 * Email Notification Functions
 * Note: This app primarily uses Twilio SMS for notifications
 */

require_once 'config/db.php';

/**
 * Send email notification to family members
 * 
 * @param string $to Email address
 * @param string $subject Email subject
 * @param string $message Email body
 * @return array Response with success status
 */
function sendEmailNotification($to, $subject, $message) {
    // Email headers
    $headers = "From: PillPilot <notifications@pillpilot.local>\r\n";
    $headers .= "Reply-To: noreply@pillpilot.local\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    // HTML email template
    $htmlMessage = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
            .alert-box { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
            .medicine-info { background: white; padding: 15px; border-radius: 8px; margin: 15px 0; }
            .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>PillPilot Alert</h2>
            </div>
            <div class="content">
                ' . $message . '
            </div>
            <div class="footer">
                <p>This is an automated message from PillPilot Smart Medicine Reminder</p>
                <p>Please do not reply to this email</p>
            </div>
        </div>
    </body>
    </html>';
    
    // Try to send email using PHP mail
    $success = mail($to, $subject, $htmlMessage, $headers);
    return [
        'success' => $success,
        'message' => $success ? 'Email sent successfully' : 'Failed to send email. Please check your email configuration.'
    ];
}

/**
 * Notify family members via email when medicine is missed
 * 
 * @param int $userId User ID
 * @param int $medicineId Medicine ID
 * @param object $conn Database connection
 * @return array Results for each family member
 */
function notifyFamilyByEmail($userId, $medicineId, $conn) {
    // Get user info
    $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    // Get medicine info
    $stmt = $conn->prepare("SELECT name, dosage, time FROM medicines WHERE id = ?");
    $stmt->execute([$medicineId]);
    $medicine = $stmt->fetch();
    
    if (!$user || !$medicine) {
        return ['success' => false, 'message' => 'User or medicine not found'];
    }
    
    // Get family members with email
    $stmt = $conn->prepare("SELECT name, email FROM family_members WHERE user_id = ? AND notify_on_missed = TRUE AND email IS NOT NULL AND email != ''");
    $stmt->execute([$userId]);
    $familyMembers = $stmt->fetchAll();
    
    if (empty($familyMembers)) {
        return ['success' => false, 'message' => 'No family members with email found'];
    }
    
    $userName = $user['name'];
    $medName = $medicine['name'];
    $dosage = $medicine['dosage'];
    $time = date('h:i A', strtotime($medicine['time']));
    
    $results = [];
    
    // Send email to each family member
    foreach ($familyMembers as $member) {
        $subject = "🚨 PillPilot Alert: {$userName} missed their medicine!";
        
        $message = '
        <div class="alert-box">
            <strong>Medicine Missed!</strong>
        </div>
        
        <p>Hello ' . htmlspecialchars($member['name']) . ',</p>
        
        <p><strong>' . htmlspecialchars($userName) . '</strong> has missed their scheduled medicine.</p>
        
        <div class="medicine-info">
            <h3>Medicine Details:</h3>
            <p><strong>Name:</strong> ' . htmlspecialchars($medName) . '</p>
            <p><strong>Dosage:</strong> ' . htmlspecialchars($dosage) . '</p>
            <p><strong>Scheduled Time:</strong> ' . htmlspecialchars($time) . '</p>
        </div>
        
        <p>Please check on them to ensure they take their medicine.</p>
        
        <p>Best regards,<br>PillPilot Team</p>';
        
        $result = sendEmailNotification($member['email'], $subject, $message);
        
        // Log the notification
        logNotification($userId, $medicineId, $member['email'], 'email', $result['success'] ? 'sent' : 'failed', $result['message']);
        
        $results[] = [
            'name' => $member['name'],
            'email' => $member['email'],
            'success' => $result['success']
        ];
    }
    
    return [
        'success' => true,
        'notifications' => $results
    ];
}

/**
 * Log notification to database
 */
function logNotification($userId, $medicineId, $recipient, $type, $status, $response) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("INSERT INTO notification_logs (user_id, medicine_id, recipient, type, status, response, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $medicineId, $recipient, $type, $status, $response]);
    } catch (PDOException $e) {
        error_log("Notification logging failed: " . $e->getMessage());
    }
}

/**
 * Send test email
 */
function sendTestEmail($to, $userName) {
    $subject = "🧪 PillPilot Test Email";
    
    $message = '
    <div class="alert-box" style="background: #d4edda; border-left: 4px solid #28a745;">
        <strong>✅ Test Email Successful!</strong>
    </div>
    
    <p>Hello,</p>
    
    <p>This is a test email from <strong>PillPilot</strong> Smart Medicine Reminder.</p>
    
    <p>If you received this email, your notification system is working correctly!</p>
    
    <div class="medicine-info">
        <h3>What happens when medicine is missed?</h3>
        <ul>
            <li>You will receive an email alert immediately</li>
            <li>The email will include medicine name, dosage, and scheduled time</li>
            <li>You can check on the patient to ensure they take their medicine</li>
        </ul>
    </div>
    
    <p>Best regards,<br>PillPilot Team</p>';
    
    return sendEmailNotification($to, $subject, $message);
}
