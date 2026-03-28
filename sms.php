<?php
/**
 * SMS Integration using Twilio API
 * 
 * Configuration - Set in .env file:
 * - TWILIO_SID: Your Account SID from Twilio Console
 * - TWILIO_TOKEN: Your Auth Token from Twilio Console
 * - TWILIO_PHONE: Your Twilio phone number (e.g., +1234567890)
 */

// Load environment configuration
require_once __DIR__ . '/config/env.php';

// Twilio Configuration
define('TWILIO_SID', env('TWILIO_SID', 'YOUR_TWILIO_ACCOUNT_SID'));
define('TWILIO_TOKEN', env('TWILIO_TOKEN', 'YOUR_TWILIO_AUTH_TOKEN'));
define('TWILIO_PHONE', env('TWILIO_PHONE', 'YOUR_TWILIO_PHONE_NUMBER'));

/**
 * Send SMS using Twilio API
 * 
 * @param string $phone Phone number with country code (e.g., +1234567890)
 * @param string $message SMS message
 * @return array Response with success status and message
 */
function sendSMS($phone, $message) {
    // Check if Twilio is configured
    if (TWILIO_SID === 'YOUR_TWILIO_ACCOUNT_SID' || TWILIO_TOKEN === 'YOUR_TWILIO_AUTH_TOKEN') {
        return [
            'success' => false,
            'message' => 'Twilio not configured. Please add your Twilio credentials in sms.php'
        ];
    }
    
    $apiUrl = 'https://api.twilio.com/2010-04-01/Accounts/' . TWILIO_SID . '/Messages.json';
    
    $data = [
        'To' => $phone,
        'From' => TWILIO_PHONE,
        'Body' => $message
    ];
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERPWD, TWILIO_SID . ':' . TWILIO_TOKEN);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 201 && $response) {
        $result = json_decode($response, true);
        return [
            'success' => true,
            'message' => 'SMS sent successfully',
            'sid' => $result['sid'] ?? null,
            'status' => $result['status'] ?? null
        ];
    }
    
    // Parse error response
    $error = json_decode($response, true);
    $errorMessage = $error['message'] ?? 'Failed to send SMS';
    
    return [
        'success' => false,
        'message' => $errorMessage,
        'code' => $error['code'] ?? null
    ];
}

/**
 * Log SMS to database
 * 
 * @param int $userId User ID
 * @param int $medicineId Medicine ID
 * @param string $phone Phone number
 * @param string $message SMS message
 * @param string $status SMS status
 * @param string $response API response
 */
function logSMS($userId, $medicineId, $phone, $message, $status, $response) {
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("INSERT INTO sms_logs (user_id, medicine_id, phone, message, status, response) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $medicineId, $phone, $message, $status, $response]);
    } catch (PDOException $e) {
        // Silent fail for logging
        error_log("SMS Logging failed: " . $e->getMessage());
    }
}

/**
 * Send medicine reminder SMS
 * 
 * @param int $userId User ID
 * @param int $medicineId Medicine ID
 * @param string $medicineName Medicine name
 * @param string $dosage Medicine dosage
 * @param string $time Medicine time
 * @return array Response
 */
function sendMedicineReminder($userId, $medicineId, $medicineName, $dosage, $time) {
    $conn = getDBConnection();
    
    // Get user phone
    $stmt = $conn->prepare("SELECT phone FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user || empty($user['phone'])) {
        return ['success' => false, 'message' => 'No phone number found'];
    }
    
    $phone = $user['phone'];
    $formattedTime = date('h:i A', strtotime($time));
    
    $message = "PillPilot Reminder: Time to take your medicine!\n\n";
    $message .= "Medicine: $medicineName\n";
    $message .= "Dosage: $dosage\n";
    $message .= "Time: $formattedTime\n\n";
    $message .= "Stay healthy! ";
    
    $result = sendSMS($phone, $message);
    
    // Log the SMS
    logSMS($userId, $medicineId, $phone, $message, $result['success'] ? 'sent' : 'failed', json_encode($result));
    
    return $result;
}

/**
 * Send refill notification SMS
 * 
 * @param int $userId User ID
 * @param int $medicineId Medicine ID
 * @param string $medicineName Medicine name
 * @param int $daysLeft Days remaining
 * @return array Response
 */
function sendRefillNotification($userId, $medicineId, $medicineName, $daysLeft) {
    $conn = getDBConnection();
    
    // Get user phone
    $stmt = $conn->prepare("SELECT phone FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user || empty($user['phone'])) {
        return ['success' => false, 'message' => 'No phone number found'];
    }
    
    $phone = $user['phone'];
    $dayText = $daysLeft == 1 ? '1 day' : "$daysLeft days";
    
    $message = "PillPilot Alert: Your medicine is running low!\n\n";
    $message .= "Medicine: $medicineName\n";
    $message .= "Remaining: $dayText\n\n";
    $message .= "Please refill soon to avoid missing doses.\n";
    $message .= "- PillPilot";
    
    $result = sendSMS($phone, $message);
    
    // Log the SMS
    logSMS($userId, $medicineId, $phone, $message, $result['success'] ? 'sent' : 'failed', json_encode($result));
    
    return $result;
}
