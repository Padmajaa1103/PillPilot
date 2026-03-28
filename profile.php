<?php
/**
 * User Profile Page - Modern Redesign
 */

session_start();
require_once 'config/db.php';
requireAuth();

$pageTitle = 'Profile Settings';
$userId = getCurrentUserId();
$conn = getDBConnection();

$error = '';
$success = '';

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $name = sanitize($_POST['name'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        
        if (empty($name) || empty($phone)) {
            $error = 'Name and phone are required.';
        } else {
            try {
                $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
                $stmt->execute([$name, $phone, $userId]);
                
                $_SESSION['user_name'] = $name;
                $success = 'Profile updated successfully!';
                
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
            } catch (PDOException $e) {
                $error = 'Failed to update profile. Please try again.';
            }
        }
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'All password fields are required.';
        } elseif (strlen($newPassword) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match.';
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $error = 'Current password is incorrect.';
        } else {
            try {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $userId]);
                $success = 'Password changed successfully!';
            } catch (PDOException $e) {
                $error = 'Failed to change password. Please try again.';
            }
        }
    } elseif ($action === 'update_refill_settings') {
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
                $error = 'Failed to update settings. Please try again.';
            }
        }
    }
}

// Get user statistics
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM medicines WHERE user_id = ?");
$stmt->execute([$userId]);
$totalMedicines = $stmt->fetch()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM logs WHERE user_id = ?");
$stmt->execute([$userId]);
$totalLogs = $stmt->fetch()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM logs WHERE user_id = ? AND status = 'taken'");
$stmt->execute([$userId]);
$takenLogs = $stmt->fetch()['total'];

$adherenceRate = $totalLogs > 0 ? round(($takenLogs / $totalLogs) * 100, 1) : 0;

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<style>
/* Modern Profile Page Styles */
.profile-page {
    max-width: 800px;
    margin: 0 auto;
    padding: 24px 16px;
}

/* Profile Header Card */
.profile-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    padding: 32px;
    color: white;
    margin-bottom: 24px;
    box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
}

.profile-header-content {
    display: flex;
    align-items: center;
    gap: 24px;
}

.profile-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    font-weight: 600;
    flex-shrink: 0;
    border: 3px solid rgba(255, 255, 255, 0.3);
}

.profile-info h1 {
    font-size: 24px;
    font-weight: 600;
    margin-bottom: 4px;
}

.profile-info .email {
    opacity: 0.9;
    font-size: 14px;
    margin-bottom: 12px;
}

.profile-stats {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
}

.stat-badge {
    background: rgba(255, 255, 255, 0.2);
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
}

/* Section Cards */
.profile-section {
    background: white;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    border: 1px solid #f0f0f0;
}

.section-header {
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #f0f0f0;
}

.section-header h2 {
    font-size: 18px;
    font-weight: 600;
    color: #1a1a1a;
    margin-bottom: 4px;
}

.section-header p {
    font-size: 14px;
    color: #666;
    margin: 0;
}

/* Form Styles */
.form-group {
    margin-bottom: 20px;
}

.form-label {
    display: block;
    font-size: 13px;
    font-weight: 500;
    color: #374151;
    margin-bottom: 6px;
}

.form-input {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s;
    background: #fff;
}

.form-input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-input:disabled {
    background: #f9fafb;
    color: #6b7280;
    cursor: not-allowed;
}

.helper-text {
    font-size: 12px;
    color: #6b7280;
    margin-top: 4px;
}

/* Button Styles */
.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: #f3f4f6;
    color: #374151;
    border: 1px solid #e5e7eb;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-secondary:hover {
    background: #e5e7eb;
}

/* Alert Styles */
.alert {
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 16px;
    font-size: 14px;
}

.alert-success {
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #a7f3d0;
}

.alert-danger {
    background: #fee2e2;
    color: #991b1b;
    border: 1px solid #fecaca;
}

/* Grid Layout */
.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

@media (max-width: 640px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .profile-header-content {
        flex-direction: column;
        text-align: center;
    }
    
    .profile-stats {
        justify-content: center;
    }
}
</style>

<div class="profile-page">
    <!-- Profile Header -->
    <div class="profile-header">
        <div class="profile-header-content">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
            </div>
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($user['name']); ?></h1>
                <p class="email"><?php echo htmlspecialchars($user['email']); ?></p>
                <div class="profile-stats">
                    <span class="stat-badge"><?php echo $totalMedicines; ?> Medicines</span>
                    <span class="stat-badge"><?php echo $adherenceRate; ?>% Adherence</span>
                    <span class="stat-badge"><?php echo $totalLogs; ?> Logs</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>

    <!-- Personal Information Section -->
    <div class="profile-section">
        <div class="section-header">
            <h2>Personal Information</h2>
            <p>Update your personal details</p>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_profile">
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-input" name="name" 
                           value="<?php echo htmlspecialchars($user['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-input" 
                           value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                    <p class="helper-text">Email cannot be changed</p>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="tel" class="form-input" name="phone" 
                           value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                    <p class="helper-text">Format: +1234567890 (for SMS reminders)</p>
                </div>
            </div>
            
            <button type="submit" class="btn-primary">Save Changes</button>
        </form>
    </div>

    <!-- Security Section -->
    <div class="profile-section">
        <div class="section-header">
            <h2>Security</h2>
            <p>Update your password</p>
        </div>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="change_password">
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input type="password" class="form-input" name="current_password" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" class="form-input" name="new_password" required minlength="6">
                    <p class="helper-text">Minimum 6 characters</p>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" class="form-input" name="confirm_password" required>
                </div>
            </div>
            
            <button type="submit" class="btn-secondary">Change Password</button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
