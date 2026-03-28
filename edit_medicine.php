<?php
/**
 * Edit Medicine Page
 */

session_start();
require_once 'config/db.php';
requireAuth();

$pageTitle = 'Edit Medicine';
$userId = getCurrentUserId();
$conn = getDBConnection();

// Get medicine ID
$medicineId = intval($_GET['id'] ?? 0);

if (!$medicineId) {
    header("Location: medicine_list.php");
    exit();
}

// Fetch medicine data
$stmt = $conn->prepare("SELECT * FROM medicines WHERE id = ? AND user_id = ?");
$stmt->execute([$medicineId, $userId]);
$medicine = $stmt->fetch();

if (!$medicine) {
    header("Location: medicine_list.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $dosage = sanitize($_POST['dosage'] ?? '');
    $time = $_POST['time'] ?? '';
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $status = $_POST['status'] ?? 'active';
    
    // Validation
    if (empty($name) || empty($dosage) || empty($time) || empty($startDate) || empty($endDate)) {
        $error = 'All fields are required.';
    } elseif (strtotime($endDate) < strtotime($startDate)) {
        $error = 'End date must be after start date.';
    } else {
        try {
            $stmt = $conn->prepare("UPDATE medicines SET name = ?, dosage = ?, time = ?, start_date = ?, end_date = ?, status = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$name, $dosage, $time, $startDate, $endDate, $status, $medicineId, $userId]);
            
            $success = 'Medicine updated successfully!';
            
            // Refresh medicine data
            $stmt = $conn->prepare("SELECT * FROM medicines WHERE id = ? AND user_id = ?");
            $stmt->execute([$medicineId, $userId]);
            $medicine = $stmt->fetch();
        } catch (PDOException $e) {
            $error = 'Failed to update medicine. Please try again.';
        }
    }
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="dashboard-card">
                <div class="d-flex align-items-center mb-4">
                    <div class="card-icon primary me-3">
                        <i class="fas fa-edit"></i>
                    </div>
                    <div>
                        <h4 class="mb-0">Edit Medicine</h4>
                        <p class="text-muted mb-0">Update medication details</p>
                    </div>
                </div>
                
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
                
                <form method="POST" action="">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <label for="name" class="form-label fw-medium">Medicine Name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-pills"></i></span>
                                <input type="text" class="form-control" id="name" name="name" 
                                       placeholder="e.g., Paracetamol" required
                                       value="<?php echo htmlspecialchars($medicine['name']); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="dosage" class="form-label fw-medium">Dosage</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-tint"></i></span>
                                <input type="text" class="form-control" id="dosage" name="dosage" 
                                       placeholder="e.g., 500mg, 1 tablet" required
                                       value="<?php echo htmlspecialchars($medicine['dosage']); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="time" class="form-label fw-medium">Reminder Time</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-clock"></i></span>
                                <input type="time" class="form-control" id="time" name="time" required
                                       value="<?php echo $medicine['time']; ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="status" class="form-label fw-medium">Status</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-toggle-on"></i></span>
                                <select class="form-select" id="status" name="status">
                                    <option value="active" <?php echo $medicine['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $medicine['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="start_date" class="form-label fw-medium">Start Date</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                <input type="date" class="form-control" id="start_date" name="start_date" required
                                       value="<?php echo $medicine['start_date']; ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="end_date" class="form-label fw-medium">End Date</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar-check"></i></span>
                                <input type="date" class="form-control" id="end_date" name="end_date" required
                                       value="<?php echo $medicine['end_date']; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" class="btn btn-gradient">
                            <i class="fas fa-save me-2"></i>Update Medicine
                        </button>
                        <a href="medicine_list.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i>Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // Set minimum date for end_date based on start_date
    document.getElementById('start_date').addEventListener('change', function() {
        document.getElementById('end_date').min = this.value;
    });
</script>

<?php include 'includes/footer.php'; ?>
