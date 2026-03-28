<?php
/**
 * API Endpoint: Send SMS Reminder
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

// Send SMS
$result = sendMedicineReminder($userId, $medicineId, $medicineName, $dosage, $time);

echo json_encode($result);
