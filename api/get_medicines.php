<?php
/**
 * API Endpoint: Get Today's Medicines for Reminder System
 */

session_start();
require_once '../config/db.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$userId = getCurrentUserId();
$today = date('Y-m-d');

try {
    $conn = getDBConnection();
    
    // Get today's medicines with their log status
    $stmt = $conn->prepare("SELECT m.id, m.name, m.dosage, m.time, l.status as log_status 
        FROM medicines m 
        LEFT JOIN logs l ON m.id = l.medicine_id AND l.log_date = ?
        WHERE m.user_id = ? 
        AND m.status = 'active' 
        AND m.start_date <= ? 
        AND m.end_date >= ?
        ORDER BY m.time ASC");
    $stmt->execute([$today, $userId, $today, $today]);
    $medicines = $stmt->fetchAll();
    
    // Get user phone and name for SMS and voice
    $stmt = $conn->prepare("SELECT name, phone FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'medicines' => $medicines,
        'phone' => $user['phone'] ?? null,
        'user_name' => $user['name'] ?? null
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error']);
}
