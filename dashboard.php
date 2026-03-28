<?php
/**
 * Dashboard Page
 */

session_start();
require_once 'config/db.php';
requireAuth();

$pageTitle = 'Dashboard';
$userId = getCurrentUserId();
$conn = getDBConnection();

// Get user data
$user = getCurrentUser();

// Get total medicines count
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM medicines WHERE user_id = ? AND status = 'active'");
$stmt->execute([$userId]);
$totalMedicines = $stmt->fetch()['total'];

// Get today's medicines
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM medicines WHERE user_id = ? AND status = 'active' AND start_date <= ? AND end_date >= ?");
$stmt->execute([$userId, $today, $today]);
$todayMedicines = $stmt->fetch()['total'];

// Get adherence stats for current week
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));

$stmt = $conn->prepare("SELECT 
    COUNT(*) as total_logs,
    SUM(CASE WHEN status = 'taken' THEN 1 ELSE 0 END) as taken_count
    FROM logs 
    WHERE user_id = ? AND log_date BETWEEN ? AND ?");
$stmt->execute([$userId, $weekStart, $weekEnd]);
$adherenceData = $stmt->fetch();

$totalLogs = $adherenceData['total_logs'] ?? 0;
$takenCount = $adherenceData['taken_count'] ?? 0;
$adherenceRate = $totalLogs > 0 ? round(($takenCount / $totalLogs) * 100, 1) : 0;

// Determine risk level
if ($adherenceRate >= 90) {
    $riskLevel = 'Low Risk';
    $riskClass = 'risk-low';
    $riskProgressClass = 'bg-success';
} elseif ($adherenceRate >= 70) {
    $riskLevel = 'Medium Risk';
    $riskClass = 'risk-medium';
    $riskProgressClass = 'bg-warning';
} else {
    $riskLevel = 'High Risk';
    $riskClass = 'risk-high';
    $riskProgressClass = 'bg-danger';
}

// Get today's schedule
$stmt = $conn->prepare("SELECT m.*, l.status as log_status 
    FROM medicines m 
    LEFT JOIN logs l ON m.id = l.medicine_id AND l.log_date = ?
    WHERE m.user_id = ? 
    AND m.status = 'active' 
    AND m.start_date <= ? 
    AND m.end_date >= ?
    ORDER BY m.time ASC");
$stmt->execute([$today, $userId, $today, $today]);
$todaySchedule = $stmt->fetchAll();

// Get upcoming medicines (next 3)
$currentTime = date('H:i:s');
$stmt = $conn->prepare("SELECT * FROM medicines 
    WHERE user_id = ? 
    AND status = 'active' 
    AND start_date <= ? 
    AND end_date >= ?
    AND time > ?
    ORDER BY time ASC 
    LIMIT 3");
$stmt->execute([$userId, $today, $today, $currentTime]);
$upcomingMedicines = $stmt->fetchAll();

// Get medicines needing refill (use default threshold if not set)
$refillThreshold = 3;
try {
    $stmt = $conn->prepare("SELECT refill_threshold_days FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $thresholdData = $stmt->fetch();
    $refillThreshold = $thresholdData['refill_threshold_days'] ?? 3;
} catch (PDOException $e) {
    $refillThreshold = 3; // Default if column doesn't exist
}
$lowMedicines = getMedicinesNeedingRefill($userId, $refillThreshold);
$lowMedicineCount = count($lowMedicines);

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<!-- Dashboard Content -->
<div class="container-fluid">
    <!-- Refill Alert Banner -->
    <?php if ($lowMedicineCount > 0): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-warning alert-dismissible fade show" role="alert" style="border-left: 4px solid #f59e0b;">
                <div class="d-flex align-items-center">
                    <i class="fas fa-exclamation-triangle me-3" style="font-size: 24px; color: #f59e0b;"></i>
                    <div>
                        <strong>Refill Alert!</strong> You have <?php echo $lowMedicineCount; ?> medicine(s) running low. 
                        <a href="medicine_list.php" class="alert-link">View details</a>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Welcome Banner -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="dashboard-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h3 class="mb-2">Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h3>
                        <p class="mb-0 opacity-75">Stay on track with your medication schedule. You have <?php echo $todayMedicines; ?> medicine(s) scheduled for today.</p>
                    </div>
                    <div class="d-flex align-items-center gap-3">
                        <button id="voiceToggle" class="btn btn-light" title="Toggle Voice Reminders">
                            <i class="fas fa-volume-up me-2"></i>Voice On
                        </button>
                        <i class="fas fa-heartbeat" style="font-size: 60px; opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="dashboard-card">
                <div class="card-icon primary">
                    <i class="fas fa-pills"></i>
                </div>
                <div class="card-value"><?php echo $totalMedicines; ?></div>
                <div class="card-label">Total Medicines</div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-3">
            <div class="dashboard-card">
                <div class="card-icon success">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="card-value"><?php echo $todayMedicines; ?></div>
                <div class="card-label">Today's Schedule</div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-3">
            <div class="dashboard-card">
                <div class="card-icon warning">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="card-value"><?php echo $adherenceRate; ?>%</div>
                <div class="card-label">Weekly Adherence</div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-3">
            <div class="dashboard-card">
                <div class="card-icon danger">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="card-value" style="font-size: 18px; margin-top: 8px;">
                    <span class="risk-badge <?php echo $riskClass; ?>"><?php echo $riskLevel; ?></span>
                </div>
                <div class="card-label">Risk Level</div>
            </div>
        </div>
    </div>

    <!-- Progress Bar Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="dashboard-card">
                <h5 class="mb-3"><i class="fas fa-chart-line me-2 text-primary"></i>Adherence Progress</h5>
                <div class="progress" style="height: 25px;">
                    <div class="progress-bar <?php echo $riskProgressClass; ?> progress-bar-striped progress-bar-animated" 
                         role="progressbar" 
                         style="width: <?php echo $adherenceRate; ?>%">
                        <?php echo $adherenceRate; ?>%
                    </div>
                </div>
                <small class="text-muted mt-2 d-block">
                    Based on your medication adherence from <?php echo formatDate($weekStart); ?> to <?php echo formatDate($weekEnd); ?>
                </small>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Today's Schedule -->
        <div class="col-lg-8">
            <div class="dashboard-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0"><i class="fas fa-clock me-2 text-primary"></i>Today's Schedule</h5>
                    <a href="medicine_list.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                
                <?php if (count($todaySchedule) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Medicine</th>
                                    <th>Dosage</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($todaySchedule as $medicine): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($medicine['name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($medicine['dosage']); ?></td>
                                        <td><?php echo formatTime($medicine['time']); ?></td>
                                        <td>
                                            <?php if ($medicine['log_status'] === 'taken'): ?>
                                                <span class="badge bg-success"><i class="fas fa-check me-1"></i>Taken</span>
                                            <?php elseif ($medicine['log_status'] === 'missed'): ?>
                                                <span class="badge bg-danger"><i class="fas fa-times me-1"></i>Missed</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!$medicine['log_status']): ?>
                                                <form method="POST" action="log_medicine.php" class="d-inline">
                                                    <input type="hidden" name="medicine_id" value="<?php echo $medicine['id']; ?>">
                                                    <button type="submit" name="action" value="taken" class="btn btn-sm btn-success me-1">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button type="submit" name="action" value="missed" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-calendar-check text-muted" style="font-size: 48px;"></i>
                        <p class="mt-3 text-muted">No medicines scheduled for today.</p>
                        <a href="add_medicine.php" class="btn btn-gradient">Add Medicine</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Upcoming Medicines -->
        <div class="col-lg-4">
            <div class="dashboard-card">
                <h5 class="mb-3"><i class="fas fa-hourglass-half me-2 text-warning"></i>Upcoming</h5>
                <?php if (count($upcomingMedicines) > 0): ?>
                    <?php foreach ($upcomingMedicines as $med): ?>
                        <div class="d-flex align-items-center mb-3 p-3 bg-light rounded">
                            <div class="flex-shrink-0">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                    <i class="fas fa-pills"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-1"><?php echo htmlspecialchars($med['name']); ?></h6>
                                <small class="text-muted"><?php echo formatTime($med['time']); ?> - <?php echo htmlspecialchars($med['dosage']); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted text-center py-3">No upcoming medicines today.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
