<?php
/**
 * Cron Job: Check for medicines needing refill and send notifications
 * Run this daily via Task Scheduler (Windows) or Cron (Linux)
 * 
 * Windows Task Scheduler Command:
 * php "C:\xampp\htdocs\PillPilot\cron\refill_check.php"
 */

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// Include required files
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../sms.php';

// Log file
$logFile = __DIR__ . '/../logs/refill_cron.log';
$logDir = dirname($logFile);

// Create logs directory if it doesn't exist
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}

/**
 * Log message to file
 */
function logMessage($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
    echo "$message\n";
}

logMessage("Starting refill notification check...");

$conn = getDBConnection();
$today = date('Y-m-d');

// Get all users with refill notifications enabled
$stmt = $conn->prepare("
    SELECT id, name, phone, refill_threshold_days 
    FROM users 
    WHERE refill_notifications_enabled = 1
");
$stmt->execute();
$users = $stmt->fetchAll();

$totalNotifications = 0;
$totalErrors = 0;

foreach ($users as $user) {
    $userId = $user['id'];
    $userName = $user['name'];
    $thresholdDays = $user['refill_threshold_days'] ?? 3;
    
    logMessage("Checking user: $userName (ID: $userId, Threshold: $thresholdDays days)");
    
    // Get medicines needing refill
    $medicines = getMedicinesNeedingRefill($userId, $thresholdDays);
    
    if (empty($medicines)) {
        logMessage("  No medicines need refill notification");
        continue;
    }
    
    foreach ($medicines as $medicine) {
        $medicineId = $medicine['id'];
        $medicineName = $medicine['name'];
        $daysLeft = intval($medicine['days_remaining']);
        
        // Skip if already notified for this medicine and days left
        if (wasRefillNotificationSent($userId, $medicineId, $daysLeft)) {
            logMessage("  Already notified: $medicineName ($daysLeft days left)");
            continue;
        }
        
        logMessage("  Sending notification: $medicineName ($daysLeft days left)");
        
        // Send SMS notification
        $result = sendRefillNotification($userId, $medicineId, $medicineName, $daysLeft);
        
        if ($result['success']) {
            // Log the notification
            logRefillNotification($userId, $medicineId, $daysLeft);
            $totalNotifications++;
            logMessage("  ✓ SMS sent successfully");
        } else {
            $totalErrors++;
            logMessage("  ✗ Failed to send SMS: " . $result['message']);
        }
        
        // Small delay to avoid rate limiting
        sleep(1);
    }
}

logMessage("Refill check completed.");
logMessage("Total notifications sent: $totalNotifications");
logMessage("Total errors: $totalErrors");
logMessage("----------------------------------------");

// Output summary for cron job
exit(0);
