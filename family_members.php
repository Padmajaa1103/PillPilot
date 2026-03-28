<?php
/**
 * Family Members Management Page
 */

session_start();
require_once 'config/db.php';
requireAuth();

$pageTitle = 'Family Members';
$userId = getCurrentUserId();
$conn = getDBConnection();

$error = '';
$success = '';

// Handle add family member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'add_family') {
        $familyName = sanitize($_POST['family_name'] ?? '');
        $familyPhone = sanitize($_POST['family_phone'] ?? '');
        $relationship = sanitize($_POST['relationship'] ?? '');
        
        if (empty($familyName) || empty($familyPhone) || empty($relationship)) {
            $error = 'Name, phone and relationship are required.';
        } else {
            try {
                $stmt = $conn->prepare("INSERT INTO family_members (user_id, name, phone, relationship) VALUES (?, ?, ?, ?)");
                $stmt->execute([$userId, $familyName, $familyPhone, $relationship]);
                $success = 'Family member added successfully!';
            } catch (PDOException $e) {
                $error = 'Failed to add family member.';
            }
        }
    } elseif ($action === 'delete_family' && isset($_POST['family_id'])) {
        $familyId = intval($_POST['family_id']);
        try {
            $stmt = $conn->prepare("DELETE FROM family_members WHERE id = ? AND user_id = ?");
            $stmt->execute([$familyId, $userId]);
            $success = 'Family member removed.';
        } catch (PDOException $e) {
            $error = 'Failed to remove family member.';
        }
    } elseif ($action === 'toggle_notify' && isset($_POST['family_id'])) {
        $familyId = intval($_POST['family_id']);
        try {
            $stmt = $conn->prepare("UPDATE family_members SET notify_on_missed = NOT notify_on_missed WHERE id = ? AND user_id = ?");
            $stmt->execute([$familyId, $userId]);
            $success = 'Notification setting updated.';
        } catch (PDOException $e) {
            $error = 'Failed to update notification setting.';
        }
    }
}

// Get family members
$stmt = $conn->prepare("SELECT * FROM family_members WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$userId]);
$familyMembers = $stmt->fetchAll();

// Get user info for SMS testing
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="container-fluid">
    <!-- Alert Messages -->
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Add Family Member Card -->
        <div class="col-lg-4">
            <div class="dashboard-card">
                <div class="d-flex align-items-center mb-4">
                    <div class="card-icon success me-3">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <div>
                        <h5 class="mb-0">Add Family Member</h5>
                        <p class="text-muted mb-0">Add guardians for notifications</p>
                    </div>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_family">
                    
                    <div class="mb-3">
                        <label class="form-label fw-medium">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" name="family_name" placeholder="e.g., Mom, Dad" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-medium">Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="tel" class="form-control" name="family_phone" placeholder="+1234567890" required>
                        </div>
                        <small class="text-muted">Include country code (e.g., +1 for US)</small>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-medium">Relationship</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-heart"></i></span>
                            <select class="form-select" name="relationship" required>
                                <option value="">Select relationship...</option>
                                <option value="Parent">Parent</option>
                                <option value="Spouse">Spouse</option>
                                <option value="Sibling">Sibling</option>
                                <option value="Child">Child</option>
                                <option value="Guardian">Guardian</option>
                                <option value="Friend">Friend</option>
                                <option value="Caregiver">Caregiver</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-gradient w-100">
                        <i class="fas fa-plus me-2"></i>Add Family Member
                    </button>
                </form>
            </div>
            
        </div>

        <!-- Family Members List -->
        <div class="col-lg-8">
            <div class="dashboard-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="d-flex align-items-center">
                        <div class="card-icon primary me-3">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <h5 class="mb-0">My Family Members</h5>
                            <p class="text-muted mb-0">People who get notified about missed medicines</p>
                        </div>
                    </div>
                    <span class="badge bg-primary fs-6"><?php echo count($familyMembers); ?> Members</span>
                </div>
                
                <?php if (count($familyMembers) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Contact</th>
                                    <th>Relationship</th>
                                    <th>Notifications</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($familyMembers as $member): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px; font-size: 14px;">
                                                    <?php echo strtoupper(substr($member['name'], 0, 1)); ?>
                                                </div>
                                                <strong><?php echo htmlspecialchars($member['name']); ?></strong>
                                            </div>
                                        </td>
                                        <td>
                                            <small class="d-block"><?php echo htmlspecialchars($member['phone']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo htmlspecialchars($member['relationship']); ?></span>
                                        </td>
                                        <td>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_notify">
                                                <input type="hidden" name="family_id" value="<?php echo $member['id']; ?>">
                                                <button type="submit" class="btn btn-sm <?php echo $member['notify_on_missed'] ? 'btn-success' : 'btn-secondary'; ?>">
                                                    <i class="fas <?php echo $member['notify_on_missed'] ? 'fa-bell' : 'fa-bell-slash'; ?>"></i>
                                                    <?php echo $member['notify_on_missed'] ? 'On' : 'Off'; ?>
                                                </button>
                                            </form>
                                        </td>
                                        <td>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="action" value="delete_family">
                                                <input type="hidden" name="family_id" value="<?php echo $member['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove <?php echo htmlspecialchars($member['name']); ?>?');">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-users text-muted" style="font-size: 64px;"></i>
                        <h5 class="mt-4 text-muted">No Family Members Added</h5>
                        <p class="text-muted">Add family members to notify them when you miss a medicine.<br>They will receive SMS alerts automatically.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Notification Preview Card -->
            <?php if (count($familyMembers) > 0): ?>
                <div class="dashboard-card mt-4">
                    <h6 class="mb-3"><i class="fas fa-envelope me-2 text-primary"></i>SMS Notification Preview</h6>
                    <div class="bg-light p-3 rounded">
                        <small class="text-muted d-block mb-2">Example message your family will receive:</small>
                        <div class="border-start border-primary ps-3" style="border-width: 3px !important;">
                            <p class="mb-0 small" style="font-family: monospace;">
                                <strong>PillPilot Alert: <?php echo htmlspecialchars($user['name']); ?> missed their medicine!</strong><br><br>
                                Medicine: Paracetamol<br>
                                Dosage: 500mg<br>
                                Scheduled: 09:00 AM<br><br>
                                Please check on them.
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
