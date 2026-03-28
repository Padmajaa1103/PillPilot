<?php
/**
 * Add Medicine Page
 */

session_start();
require_once 'config/db.php';
requireAuth();

$pageTitle = 'Add Medicine';
$userId = getCurrentUserId();
$conn = getDBConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name'] ?? '');
    $dosage = sanitize($_POST['dosage'] ?? '');
    $time = $_POST['time'] ?? '';
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    
    // Validation
    if (empty($name) || empty($dosage) || empty($time) || empty($startDate) || empty($endDate)) {
        $error = 'All fields are required.';
    } elseif (strtotime($endDate) < strtotime($startDate)) {
        $error = 'End date must be after start date.';
    } else {
        try {
            $stmt = $conn->prepare("INSERT INTO medicines (user_id, name, dosage, time, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $name, $dosage, $time, $startDate, $endDate]);
            
            $success = 'Medicine added successfully!';
            
            // Clear form
            $name = $dosage = $time = $startDate = $endDate = '';
        } catch (PDOException $e) {
            $error = 'Failed to add medicine. Please try again.';
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
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <div>
                        <h4 class="mb-0">Add New Medicine</h4>
                        <p class="text-muted mb-0">Schedule your medication</p>
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
                                       value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="dosage" class="form-label fw-medium">Dosage</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-tint"></i></span>
                                <input type="text" class="form-control" id="dosage" name="dosage" 
                                       placeholder="e.g., 500mg, 1 tablet" required
                                       value="<?php echo isset($dosage) ? htmlspecialchars($dosage) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="time" class="form-label fw-medium">Reminder Time</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-clock"></i></span>
                                <input type="time" class="form-control" id="time" name="time" required
                                       value="<?php echo isset($time) ? htmlspecialchars($time) : ''; ?>">
                            </div>
                            <small class="text-muted">You'll receive reminders at this time daily</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="start_date" class="form-label fw-medium">Start Date</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                <input type="date" class="form-control" id="start_date" name="start_date" required
                                       value="<?php echo isset($startDate) ? htmlspecialchars($startDate) : date('Y-m-d'); ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="end_date" class="form-label fw-medium">End Date</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar-check"></i></span>
                                <input type="date" class="form-control" id="end_date" name="end_date" required
                                       value="<?php echo isset($endDate) ? htmlspecialchars($endDate) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" class="btn btn-gradient">
                            <i class="fas fa-save me-2"></i>Save Medicine
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
