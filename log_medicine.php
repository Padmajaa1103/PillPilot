<?php
/**
 * Log Medicine Taken/Missed
 */

session_start();
require_once 'config/db.php';
require_once 'sms.php';
require_once 'email_notifications.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = getCurrentUserId();
    $medicineId = intval($_POST['medicine_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($medicineId && in_array($action, ['taken', 'missed'])) {
        try {
            $conn = getDBConnection();
            
            // Check if log already exists for today
            $today = date('Y-m-d');
            $stmt = $conn->prepare("SELECT id FROM logs WHERE medicine_id = ? AND log_date = ?");
            $stmt->execute([$medicineId, $today]);
            
            if ($stmt->rowCount() > 0) {
                // Update existing log
                $stmt = $conn->prepare("UPDATE logs SET status = ?, log_time = NOW() WHERE medicine_id = ? AND log_date = ?");
                $stmt->execute([$action, $medicineId, $today]);
            } else {
                // Insert new log
                $stmt = $conn->prepare("INSERT INTO logs (user_id, medicine_id, status, log_date, log_time) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$userId, $medicineId, $action, $today]);
            }
            
            // If missed, notify family members
            if ($action === 'missed') {
                // Try SMS first (may fail due to country restrictions)
                notifyFamilyMembers($userId, $medicineId, $conn);
                
                // Also send FREE email notification
                notifyFamilyByEmail($userId, $medicineId, $conn);
            }
            
            $_SESSION['alert'] = ['message' => 'Medicine marked as ' . $action . '!', 'type' => 'success'];
        } catch (PDOException $e) {
            $_SESSION['alert'] = ['message' => 'Failed to log medicine. Please try again.', 'type' => 'error'];
        }
    }
}

/**
 * Notify family members when medicine is missed
 */
function notifyFamilyMembers($userId, $medicineId, $conn) {
    // Get user info
    $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    // Get medicine info
    $stmt = $conn->prepare("SELECT name, dosage, time FROM medicines WHERE id = ?");
    $stmt->execute([$medicineId]);
    $medicine = $stmt->fetch();
    
    if (!$user || !$medicine) return;
    
    // Get family members
    $stmt = $conn->prepare("SELECT name, phone FROM family_members WHERE user_id = ? AND notify_on_missed = TRUE");
    $stmt->execute([$userId]);
    $familyMembers = $stmt->fetchAll();
    
    if (empty($familyMembers)) return;
    
    $userName = $user['name'];
    $medName = $medicine['name'];
    $dosage = $medicine['dosage'];
    $time = date('h:i A', strtotime($medicine['time']));
    
    // Send SMS to each family member
    foreach ($familyMembers as $member) {
        $message = "PillPilot Alert: {$userName} missed their medicine!\n\n";
        $message .= "Medicine: {$medName}\n";
        $message .= "Dosage: {$dosage}\n";
        $message .= "Scheduled: {$time}\n\n";
        $message .= "Please check on them.";
        
        $result = sendSMS($member['phone'], $message);
        
        // Log the notification
        logSMS($userId, $medicineId, $member['phone'], $message, 
               $result['success'] ? 'sent' : 'failed', 
               json_encode($result));
    }
}

header("Location: dashboard.php");
exit();
