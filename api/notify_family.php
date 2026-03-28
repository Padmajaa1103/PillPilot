<?php
/**
 * API Endpoint: Notify Family Members (Auto-notification for missed medicine)
 */

session_start();
require_once '../config/db.php';
require_once '../sms.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

$medicineId = intval($input['medicine_id'] ?? 0);
$medicineName = sanitize($input['medicine_name'] ?? '');
$dosage = sanitize($input['dosage'] ?? '');
$time = $input['time'] ?? '';

if (!$medicineId || empty($medicineName)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

$userId = getCurrentUserId();
$conn = getDBConnection();

// Get user info
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit();
}

// Get family members
$stmt = $conn->prepare("SELECT name, phone FROM family_members WHERE user_id = ? AND notify_on_missed = TRUE");
$stmt->execute([$userId]);
$familyMembers = $stmt->fetchAll();

if (empty($familyMembers)) {
    echo json_encode(['success' => false, 'message' => 'No family members to notify']);
    exit();
}

$userName = $user['name'];
$formattedTime = date('h:i A', strtotime($time));

$notificationsSent = 0;
$errors = [];

// Send SMS to each family member
foreach ($familyMembers as $member) {
    $message = "PillPilot Alert: {$userName} has NOT taken their medicine for 30+ minutes!\n\n";
    $message .= "Medicine: {$medicineName}\n";
    $message .= "Dosage: {$dosage}\n";
    $message .= "Scheduled: {$formattedTime}\n\n";
    $message .= "Please check on them immediately.";
    
    $result = sendSMS($member['phone'], $message);
    
    // Log the notification
    logSMS($userId, $medicineId, $member['phone'], $message, 
           $result['success'] ? 'sent' : 'failed', 
           json_encode($result));
    
    if ($result['success']) {
        $notificationsSent++;
    } else {
        $errors[] = "Failed to notify {$member['name']}: {$result['message']}";
    }
}

echo json_encode([
    'success' => $notificationsSent > 0,
    'notifications_sent' => $notificationsSent,
    'total_family_members' => count($familyMembers),
    'errors' => $errors
]);
