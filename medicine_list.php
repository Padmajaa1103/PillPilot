<?php
/**
 * Medicine List Page
 */

session_start();
require_once 'config/db.php';
requireAuth();

$pageTitle = 'Medicine List';
$userId = getCurrentUserId();
$conn = getDBConnection();

// Handle delete action
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $medicineId = intval($_GET['delete']);
    
    try {
        // Verify the medicine belongs to current user
        $stmt = $conn->prepare("DELETE FROM medicines WHERE id = ? AND user_id = ?");
        $stmt->execute([$medicineId, $userId]);
        
        if ($stmt->rowCount() > 0) {
            $_SESSION['alert'] = ['message' => 'Medicine deleted successfully!', 'type' => 'success'];
        } else {
            $_SESSION['alert'] = ['message' => 'Medicine not found.', 'type' => 'error'];
        }
    } catch (PDOException $e) {
        $_SESSION['alert'] = ['message' => 'Failed to delete medicine.', 'type' => 'error'];
    }
    
    header("Location: medicine_list.php");
    exit();
}

// Handle status toggle
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $medicineId = intval($_GET['toggle']);
    
    try {
        $stmt = $conn->prepare("UPDATE medicines SET status = CASE WHEN status = 'active' THEN 'inactive' ELSE 'active' END WHERE id = ? AND user_id = ?");
        $stmt->execute([$medicineId, $userId]);
        
        $_SESSION['alert'] = ['message' => 'Status updated successfully!', 'type' => 'success'];
    } catch (PDOException $e) {
        $_SESSION['alert'] = ['message' => 'Failed to update status.', 'type' => 'error'];
    }
    
    header("Location: medicine_list.php");
    exit();
}

// Get all medicines for current user with days remaining
$today = date('Y-m-d');
$stmt = $conn->prepare("
    SELECT m.*, 
           DATEDIFF(m.end_date, ?) as days_remaining
    FROM medicines m 
    WHERE m.user_id = ? 
    ORDER BY m.status DESC, m.time ASC
");
$stmt->execute([$today, $userId]);
$medicines = $stmt->fetchAll();

// Get user's refill threshold (use default if column doesn't exist)
try {
    $stmt = $conn->prepare("SELECT refill_threshold_days FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch();
    $refillThreshold = $userData['refill_threshold_days'] ?? 3;
} catch (PDOException $e) {
    $refillThreshold = 3; // Default value if column doesn't exist
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="container-fluid">
    <!-- Alert Messages -->
    <?php if (isset($_SESSION['alert'])): ?>
        <div class="alert alert-<?php echo $_SESSION['alert']['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
            <i class="fas fa-<?php echo $_SESSION['alert']['type'] === 'success' ? 'check-circle' : 'exclamation-circle'; ?> me-2"></i>
            <?php echo $_SESSION['alert']['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['alert']); ?>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-12">
            <div class="dashboard-card">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                    <div class="d-flex align-items-center">
                        <div class="card-icon primary me-3">
                            <i class="fas fa-list"></i>
                        </div>
                        <div>
                            <h4 class="mb-0">My Medicines</h4>
                            <p class="text-muted mb-0">Manage your medication schedule</p>
                        </div>
                    </div>
                    <a href="add_medicine.php" class="btn btn-gradient">
                        <i class="fas fa-plus me-2"></i>Add Medicine
                    </a>
                </div>
                
                <?php if (count($medicines) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Medicine Name</th>
                                    <th>Dosage</th>
                                    <th>Time</th>
                                    <th>Duration</th>
                                    <th>Days Left</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($medicines as $medicine): 
                                    $isActive = $medicine['status'] === 'active';
                                    $isExpired = $medicine['end_date'] < $today;
                                    $daysLeft = intval($medicine['days_remaining'] ?? 0);
                                    $isLowStock = $isActive && !$isExpired && $daysLeft >= 0 && $daysLeft <= $refillThreshold;
                                    
                                    // Determine badge color for days left
                                    if ($isExpired || $daysLeft < 0) {
                                        $daysBadgeClass = 'bg-secondary';
                                    } elseif ($daysLeft <= 1) {
                                        $daysBadgeClass = 'bg-danger';
                                    } elseif ($daysLeft <= 3) {
                                        $daysBadgeClass = 'bg-warning text-dark';
                                    } else {
                                        $daysBadgeClass = 'bg-success';
                                    }
                                ?>
                                    <tr class="<?php echo !$isActive ? 'table-secondary' : ''; ?> <?php echo $isLowStock ? 'table-warning' : ''; ?>">
                                        <td>
                                            <strong><?php echo htmlspecialchars($medicine['name']); ?></strong>
                                            <?php if ($isExpired): ?>
                                                <span class="badge bg-secondary ms-2">Expired</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($medicine['dosage']); ?></td>
                                        <td>
                                            <span class="badge bg-info">
                                                <i class="fas fa-clock me-1"></i><?php echo formatTime($medicine['time']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small>
                                                <?php echo formatDate($medicine['start_date']); ?> - <?php echo formatDate($medicine['end_date']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($isExpired): ?>
                                                <span class="badge bg-secondary">Expired</span>
                                            <?php else: ?>
                                                <span class="badge <?php echo $daysBadgeClass; ?>">
                                                    <?php if ($isLowStock): ?>
                                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                                    <?php endif; ?>
                                                    <?php echo $daysLeft; ?> day<?php echo $daysLeft != 1 ? 's' : ''; ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $isActive ? 'success' : 'secondary'; ?>">
                                                <?php echo $isActive ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="edit_medicine.php?id=<?php echo $medicine['id']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="medicine_list.php?toggle=<?php echo $medicine['id']; ?>" 
                                                   class="btn btn-sm btn-outline-<?php echo $isActive ? 'warning' : 'success'; ?>" 
                                                   title="<?php echo $isActive ? 'Deactivate' : 'Activate'; ?>">
                                                    <i class="fas fa-<?php echo $isActive ? 'pause' : 'play'; ?>"></i>
                                                </a>
                                                <a href="#" 
                                                   class="btn btn-sm btn-outline-danger" 
                                                   title="Delete"
                                                   data-bs-toggle="modal"
                                                   data-bs-target="#deleteModal"
                                                   onclick="setDeleteId(<?php echo $medicine['id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-pills text-muted" style="font-size: 64px;"></i>
                        <h5 class="mt-4 text-muted">No medicines found</h5>
                        <p class="text-muted">Start by adding your first medicine</p>
                        <a href="add_medicine.php" class="btn btn-gradient mt-2">
                            <i class="fas fa-plus me-2"></i>Add Medicine
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Summary Cards -->
    <?php if (count($medicines) > 0): ?>
        <div class="row g-4 mt-2">
            <?php
            $activeCount = count(array_filter($medicines, fn($m) => $m['status'] === 'active'));
            $inactiveCount = count($medicines) - $activeCount;
            $expiredCount = count(array_filter($medicines, fn($m) => $m['end_date'] < date('Y-m-d')));
            ?>
            
            <div class="col-md-4">
                <div class="dashboard-card text-center">
                    <div class="card-icon success mx-auto">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="card-value"><?php echo $activeCount; ?></div>
                    <div class="card-label">Active Medicines</div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="dashboard-card text-center">
                    <div class="card-icon warning mx-auto">
                        <i class="fas fa-pause-circle"></i>
                    </div>
                    <div class="card-value"><?php echo $inactiveCount; ?></div>
                    <div class="card-label">Inactive Medicines</div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="dashboard-card text-center">
                    <div class="card-icon danger mx-auto">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                    <div class="card-value"><?php echo $expiredCount; ?></div>
                    <div class="card-label">Expired Medicines</div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 15px; border: none;">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center pt-0">
                <div class="mb-3">
                    <div class="bg-danger bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                        <i class="fas fa-trash-alt text-danger" style="font-size: 36px;"></i>
                    </div>
                </div>
                <h4 class="modal-title mb-2">Delete Medicine?</h4>
                <p class="text-muted mb-0">Are you sure you want to delete this medicine? This action cannot be undone.</p>
            </div>
            <div class="modal-footer border-0 justify-content-center pb-4">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal" style="border-radius: 25px;">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger px-4" style="border-radius: 25px;">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
function setDeleteId(id) {
    document.getElementById('confirmDeleteBtn').href = 'medicine_list.php?delete=' + id;
}
</script>

<?php include 'includes/footer.php'; ?>
