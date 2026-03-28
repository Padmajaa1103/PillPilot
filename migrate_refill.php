<?php
/**
 * Database Migration: Add Refill Notification Columns
 * Run this script once to update the database
 */

require_once 'config/db.php';

$conn = getDBConnection();

try {
    // Check if columns already exist
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'refill_threshold_days'");
    $columnExists = $stmt->fetch();

    if (!$columnExists) {
        // Add refill_threshold_days column
        $conn->exec("ALTER TABLE users ADD COLUMN refill_threshold_days INT DEFAULT 3");
        echo "Added refill_threshold_days column<br>";
    } else {
        echo "refill_threshold_days column already exists<br>";
    }

    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'refill_notifications_enabled'");
    $columnExists = $stmt->fetch();

    if (!$columnExists) {
        // Add refill_notifications_enabled column
        $conn->exec("ALTER TABLE users ADD COLUMN refill_notifications_enabled BOOLEAN DEFAULT TRUE");
        echo "Added refill_notifications_enabled column<br>";
    } else {
        echo "refill_notifications_enabled column already exists<br>";
    }

    // Create refill_notifications table
    $conn->exec("CREATE TABLE IF NOT EXISTS refill_notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        medicine_id INT NOT NULL,
        days_left INT NOT NULL,
        notification_sent BOOLEAN DEFAULT FALSE,
        sent_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
        UNIQUE KEY unique_medicine_notification (user_id, medicine_id, days_left),
        INDEX idx_user_id (user_id),
        INDEX idx_medicine_id (medicine_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "Created refill_notifications table<br>";

    echo "<br><strong style='color: green;'>Migration completed successfully!</strong>";
    echo "<br><a href='profile.php'>Go back to Profile</a>";

} catch (PDOException $e) {
    echo "<strong style='color: red;'>Migration failed:</strong> " . $e->getMessage();
}
