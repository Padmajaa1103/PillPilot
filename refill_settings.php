<?php
/**
 * Refill Notification Settings Page
 */

session_start();
require_once 'config/db.php';
requireAuth();

$pageTitle = 'Refill Alerts';
$userId = getCurrentUserId();
$conn = getDBConnection();

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// Set defaults if columns don't exist
if (!isset($user['refill_threshold_days'])) {
    $user['refill_threshold_days'] = 3;
}
if (!isset($user['refill_notifications_enabled'])) {
    $user['refill_notifications_enabled'] = 1;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $thresholdDays = intval($_POST['refill_threshold_days'] ?? 3);
    $notificationsEnabled = isset($_POST['refill_notifications_enabled']) ? 1 : 0;
    
    if ($thresholdDays < 1 || $thresholdDays > 7) {
        $error = 'Threshold days must be between 1 and 7.';
    } else {
        try {
            $stmt = $conn->prepare("UPDATE users SET refill_threshold_days = ?, refill_notifications_enabled = ? WHERE id = ?");
            $stmt->execute([$thresholdDays, $notificationsEnabled, $userId]);
            $success = 'Refill notification settings updated!';
            
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            $error = 'Database columns not found. Please run <a href="migrate_refill.php">migration script</a> first.';
        }
    }
}

// Get medicines that need refill
$lowMedicines = getMedicinesNeedingRefill($userId, $user['refill_threshold_days'] ?? 3);

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="container-fluid">
    <!-- Alert Messages -->
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-6">
            <!-- Settings Card -->
            <div class="dashboard-card">
                <div class="d-flex align-items-center mb-4">
                    <div class="card-icon primary me-3">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div>
                        <h4 class="mb-0">Refill Alert Settings</h4>
                        <p class="text-muted mb-0">Configure when you receive refill reminders</p>
                    </div>
                </div>
                
                <form method="POST" action="">
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Alert Threshold</label>
                        <select class="form-select" name="refill_threshold_days">
                            <?php for ($i = 1; $i <= 7; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo ($user['refill_threshold_days'] ?? 3) == $i ? 'selected' : ''; ?>>
                                    <?php echo $i; ?> day<?php echo $i > 1 ? 's' : ''; ?> before medicine runs out
                                </option>
                            <?php endfor; ?>
                        </select>
                        <p class="text-muted small mt-2">
                            <i class="fas fa-info-circle me-1"></i>
                            You'll receive an SMS when your medicine has this many days remaining
                        </p>
                    </div>
                    
                    <div class="mb-4">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="refill_notifications_enabled" 
                                   id="refill_notifications_enabled"
                                   <?php echo ($user['refill_notifications_enabled'] ?? 1) ? 'checked' : ''; ?>
                                   style="width: 3rem; height: 1.5rem;">
                            <label class="form-check-label fw-semibold" for="refill_notifications_enabled">
                                Enable SMS Notifications
                            </label>
                        </div>
                        <p class="text-muted small mt-2">
                            <i class="fas fa-sms me-1"></i>
                            Receive refill alerts via SMS on your phone
                        </p>
                    </div>
                    
                    <button type="submit" class="btn btn-gradient">
                        <i class="fas fa-save me-2"></i>Save Settings
                    </button>
                </form>
            </div>
        </div>
        
        <div class="col-lg-6">
            <!-- Medicines Needing Refill -->
            <div class="dashboard-card">
                <div class="d-flex align-items-center mb-4">
                    <div class="card-icon <?php echo count($lowMedicines) > 0 ? 'warning' : 'success'; ?> me-3">
                        <i class="fas fa-<?php echo count($lowMedicines) > 0 ? 'exclamation-triangle' : 'check-circle'; ?>"></i>
                    </div>
                    <div>
                        <h4 class="mb-0">Medicines Status</h4>
                        <p class="text-muted mb-0">
                            <?php echo count($lowMedicines); ?> medicine(s) need attention
                        </p>
                    </div>
                </div>
                
                <?php if (count($lowMedicines) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Medicine</th>
                                    <th>Days Left</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lowMedicines as $medicine): 
                                    $daysLeft = intval($medicine['days_remaining']);
                                    if ($daysLeft <= 1) {
                                        $badgeClass = 'bg-danger';
                                        $status = 'Critical';
                                    } elseif ($daysLeft <= 3) {
                                        $badgeClass = 'bg-warning text-dark';
                                        $status = 'Low';
                                    } else {
                                        $badgeClass = 'bg-info';
                                        $status = 'Monitor';
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($medicine['name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($medicine['dosage']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $badgeClass; ?>">
                                                <?php echo $daysLeft; ?> day<?php echo $daysLeft != 1 ? 's' : ''; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $badgeClass; ?>"><?php echo $status; ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="medicine_list.php" class="btn btn-outline-primary btn-sm mt-3">
                        <i class="fas fa-list me-2"></i>View All Medicines
                    </a>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 48px;"></i>
                        <p class="mt-3 mb-0">All medicines are well stocked!</p>
                        <p class="text-muted small">No refill needed at this time</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- How It Works -->
            <div class="dashboard-card mt-4">
                <h5 class="mb-3"><i class="fas fa-info-circle text-primary me-2"></i>How Refill Alerts Work</h5>
                <ul class="list-unstyled">
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        System checks your medicine end dates daily
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        SMS sent when days remaining match your threshold
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Duplicate notifications are prevented
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success me-2"></i>
                        Dashboard shows alerts for low medicines
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
