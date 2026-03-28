<?php
/**
 * Reports & Analytics Page
 */

session_start();
require_once 'config/db.php';
requireAuth();

$pageTitle = 'Reports & Analytics';
$userId = getCurrentUserId();
$conn = getDBConnection();

// Get date range (default: last 7 days)
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-6 days', strtotime($endDate)));

// Get adherence data for the date range
$stmt = $conn->prepare("SELECT 
    log_date,
    COUNT(*) as total,
    SUM(CASE WHEN status = 'taken' THEN 1 ELSE 0 END) as taken,
    SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as missed
    FROM logs 
    WHERE user_id = ? AND log_date BETWEEN ? AND ?
    GROUP BY log_date
    ORDER BY log_date ASC");
$stmt->execute([$userId, $startDate, $endDate]);
$dailyData = $stmt->fetchAll();

// Calculate overall stats
$totalLogs = 0;
$totalTaken = 0;
$totalMissed = 0;

foreach ($dailyData as $day) {
    $totalLogs += $day['total'];
    $totalTaken += $day['taken'];
    $totalMissed += $day['missed'];
}

$adherenceRate = $totalLogs > 0 ? round(($totalTaken / $totalLogs) * 100, 1) : 0;

// Determine risk level
if ($adherenceRate >= 90) {
    $riskLevel = 'Low Risk';
    $riskClass = 'risk-low';
    $riskColor = '#10b981';
} elseif ($adherenceRate >= 70) {
    $riskLevel = 'Medium Risk';
    $riskClass = 'risk-medium';
    $riskColor = '#f59e0b';
} else {
    $riskLevel = 'High Risk';
    $riskClass = 'risk-high';
    $riskColor = '#ef4444';
}

// Get medicine-wise stats
$stmt = $conn->prepare("SELECT 
    m.name,
    COUNT(l.id) as total,
    SUM(CASE WHEN l.status = 'taken' THEN 1 ELSE 0 END) as taken
    FROM medicines m
    LEFT JOIN logs l ON m.id = l.medicine_id AND l.log_date BETWEEN ? AND ?
    WHERE m.user_id = ?
    GROUP BY m.id, m.name
    HAVING total > 0
    ORDER BY total DESC");
$stmt->execute([$startDate, $endDate, $userId]);
$medicineStats = $stmt->fetchAll();

// Get weekly trend (last 4 weeks)
$weeks = [];
for ($i = 3; $i >= 0; $i--) {
    $weekStart = date('Y-m-d', strtotime("monday -$i week"));
    $weekEnd = date('Y-m-d', strtotime("sunday -$i week"));
    $weeks[] = ['start' => $weekStart, 'end' => $weekEnd, 'label' => "Week " . (4 - $i)];
}

$weeklyData = [];
foreach ($weeks as $week) {
    $stmt = $conn->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'taken' THEN 1 ELSE 0 END) as taken
        FROM logs 
        WHERE user_id = ? AND log_date BETWEEN ? AND ?");
    $stmt->execute([$userId, $week['start'], $week['end']]);
    $data = $stmt->fetch();
    
    $weeklyData[] = [
        'label' => $week['label'],
        'rate' => $data['total'] > 0 ? round(($data['taken'] / $data['total']) * 100, 1) : 0
    ];
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="container-fluid">
    <!-- Date Range Filter -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="dashboard-card">
                <form method="GET" action="" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label fw-medium">Start Date</label>
                        <input type="date" class="form-control" name="start_date" value="<?php echo $startDate; ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-medium">End Date</label>
                        <input type="date" class="form-control" name="end_date" value="<?php echo $endDate; ?>">
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-gradient">
                            <i class="fas fa-filter me-2"></i>Apply Filter
                        </button>
                    </div>
                    <div class="col-md-3 text-md-end">
                        <a href="reports.php" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-undo me-2"></i>Reset
                        </a>
                        <button type="button" class="btn btn-danger" onclick="downloadPDF()">
                            <i class="fas fa-file-pdf me-2"></i>Download PDF
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- PDF Content Container -->
    <div id="reportContent">
    <div class="row g-4 mb-4">
        <div class="col-md-6 col-lg-3">
            <div class="dashboard-card text-center">
                <div class="card-icon success mx-auto">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="card-value"><?php echo $totalTaken; ?></div>
                <div class="card-label">Medicines Taken</div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-3">
            <div class="dashboard-card text-center">
                <div class="card-icon danger mx-auto">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="card-value"><?php echo $totalMissed; ?></div>
                <div class="card-label">Medicines Missed</div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-3">
            <div class="dashboard-card text-center">
                <div class="card-icon primary mx-auto">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="card-value"><?php echo $adherenceRate; ?>%</div>
                <div class="card-label">Adherence Rate</div>
            </div>
        </div>
        
        <div class="col-md-6 col-lg-3">
            <div class="dashboard-card text-center">
                <div class="card-icon warning mx-auto">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="card-value" style="font-size: 18px; margin-top: 8px;">
                    <span class="risk-badge <?php echo $riskClass; ?>"><?php echo $riskLevel; ?></span>
                </div>
                <div class="card-label">Risk Assessment</div>
            </div>
        </div>
    </div>

    <!-- Charts Row 1 -->
    <div class="row g-4 mb-4">
        <!-- Adherence Progress Circle -->
        <div class="col-lg-4">
            <div class="dashboard-card">
                <h5 class="mb-4"><i class="fas fa-bullseye me-2 text-primary"></i>Adherence Score</h5>
                <div style="position: relative; height: 250px;">
                    <canvas id="adherenceChart"></canvas>
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
                        <div style="font-size: 36px; font-weight: 700; color: <?php echo $riskColor; ?>"><?php echo $adherenceRate; ?>%</div>
                        <small class="text-muted">Adherence</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Daily Breakdown -->
        <div class="col-lg-8">
            <div class="dashboard-card">
                <h5 class="mb-4"><i class="fas fa-chart-bar me-2 text-primary"></i>Daily Medicine Log</h5>
                <div style="height: 250px;">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="row g-4 mb-4">
        <!-- Weekly Trend -->
        <div class="col-lg-6">
            <div class="dashboard-card">
                <h5 class="mb-4"><i class="fas fa-chart-line me-2 text-primary"></i>Weekly Trend</h5>
                <div style="height: 250px;">
                    <canvas id="weeklyChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Medicine-wise Stats -->
        <div class="col-lg-6">
            <div class="dashboard-card">
                <h5 class="mb-4"><i class="fas fa-pills me-2 text-primary"></i>Medicine-wise Adherence</h5>
                <div style="height: 250px;">
                    <canvas id="medicineChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Table -->
    <div class="row">
        <div class="col-12">
            <div class="dashboard-card">
                <h5 class="mb-4"><i class="fas fa-table me-2 text-primary"></i>Medicine-wise Detailed Stats</h5>
                <?php if (count($medicineStats) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Medicine</th>
                                    <th>Total Doses</th>
                                    <th>Taken</th>
                                    <th>Missed</th>
                                    <th>Adherence</th>
                                    <th>Progress</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($medicineStats as $med): 
                                    $medMissed = $med['total'] - $med['taken'];
                                    $medRate = $med['total'] > 0 ? round(($med['taken'] / $med['total']) * 100, 1) : 0;
                                    $progressClass = $medRate >= 90 ? 'bg-success' : ($medRate >= 70 ? 'bg-warning' : 'bg-danger');
                                ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($med['name']); ?></strong></td>
                                        <td><?php echo $med['total']; ?></td>
                                        <td class="text-success"><?php echo $med['taken']; ?></td>
                                        <td class="text-danger"><?php echo $medMissed; ?></td>
                                        <td><?php echo $medRate; ?>%</td>
                                        <td style="width: 200px;">
                                            <div class="progress">
                                                <div class="progress-bar <?php echo $progressClass; ?>" 
                                                     role="progressbar" 
                                                     style="width: <?php echo $medRate; ?>%">
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-chart-pie text-muted" style="font-size: 48px;"></i>
                        <p class="mt-3 text-muted">No data available for the selected date range.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- End PDF Content Container -->
    </div>
    
    <!-- PDF Footer -->
    <div class="pdf-footer mt-4 text-center text-muted small" style="display: none;">
        <hr>
        <p>Generated by PillPilot - Smart Medicine Reminder on <?php echo date('F d, Y h:i A'); ?></p>
    </div>
</div>

<!-- PDF Generation Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
    // Ensure libraries are loaded
    window.jsPDF = window.jspdf.jsPDF;
</script>

<script>
    // Chart.js Configuration
    Chart.defaults.font.family = 'Poppins';
    Chart.defaults.color = '#666';
    
    // Adherence Circle Chart
    const adherenceCtx = document.getElementById('adherenceChart').getContext('2d');
    new Chart(adherenceCtx, {
        type: 'doughnut',
        data: {
            labels: ['Taken', 'Missed'],
            datasets: [{
                data: [<?php echo $totalTaken; ?>, <?php echo $totalMissed; ?>],
                backgroundColor: ['#10b981', '#ef4444'],
                borderWidth: 0,
                cutout: '75%'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    
    // Daily Bar Chart
    const dailyCtx = document.getElementById('dailyChart').getContext('2d');
    new Chart(dailyCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_map(fn($d) => date('M d', strtotime($d['log_date'])), $dailyData)); ?>,
            datasets: [
                {
                    label: 'Taken',
                    data: <?php echo json_encode(array_map(fn($d) => $d['taken'], $dailyData)); ?>,
                    backgroundColor: '#10b981',
                    borderRadius: 5
                },
                {
                    label: 'Missed',
                    data: <?php echo json_encode(array_map(fn($d) => $d['missed'], $dailyData)); ?>,
                    backgroundColor: '#ef4444',
                    borderRadius: 5
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
    
    // Weekly Trend Line Chart
    const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
    new Chart(weeklyCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode(array_map(fn($w) => $w['label'], $weeklyData)); ?>,
            datasets: [{
                label: 'Adherence Rate (%)',
                data: <?php echo json_encode(array_map(fn($w) => $w['rate'], $weeklyData)); ?>,
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#667eea',
                pointRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    
    // Medicine-wise Horizontal Bar Chart
    const medicineCtx = document.getElementById('medicineChart').getContext('2d');
    new Chart(medicineCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_map(fn($m) => $m['name'], $medicineStats)); ?>,
            datasets: [{
                label: 'Adherence Rate (%)',
                data: <?php echo json_encode(array_map(function($m) {
                    return $m['total'] > 0 ? round(($m['taken'] / $m['total']) * 100, 1) : 0;
                }, $medicineStats)); ?>,
                backgroundColor: <?php echo json_encode(array_map(function($m) {
                    $rate = $m['total'] > 0 ? ($m['taken'] / $m['total']) * 100 : 0;
                    return $rate >= 90 ? '#10b981' : ($rate >= 70 ? '#f59e0b' : '#ef4444');
                }, $medicineStats)); ?>,
                borderRadius: 5
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            }
        }
    });
    
    // PDF Download Function
    async function downloadPDF() {
        const btn = document.querySelector('button[onclick="downloadPDF()"]');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Generating...';
        btn.disabled = true;
        
        try {
            // Check if libraries are loaded
            if (typeof window.jspdf === 'undefined' || typeof html2canvas === 'undefined') {
                throw new Error('PDF libraries not loaded. Please refresh the page.');
            }
            
            const { jsPDF } = window.jspdf;
            const reportContent = document.getElementById('reportContent');
            const pdfFooter = document.querySelector('.pdf-footer');
            
            if (!reportContent) {
                throw new Error('Report content not found');
            }
            
            // Show footer for PDF
            if (pdfFooter) pdfFooter.style.display = 'block';
            
            // Wait for charts to render
            await new Promise(resolve => setTimeout(resolve, 800));
            
            // Capture the report content
            const canvas = await html2canvas(reportContent, {
                scale: 2,
                useCORS: true,
                logging: false,
                backgroundColor: '#ffffff',
                allowTaint: true,
                foreignObjectRendering: false
            });
            
            // Hide footer again
            if (pdfFooter) pdfFooter.style.display = 'none';
            
            // Calculate PDF dimensions
            const imgData = canvas.toDataURL('image/png');
            const imgWidth = 210; // A4 width in mm
            const pageHeight = 297; // A4 height in mm
            const imgHeight = (canvas.height * imgWidth) / canvas.width;
            
            // Create PDF
            const pdf = new jsPDF('p', 'mm', 'a4');
            
            // Add title
            pdf.setFontSize(20);
            pdf.setTextColor(102, 126, 234);
            pdf.text('PillPilot - Medication Report', 105, 15, { align: 'center' });
            
            // Add date range
            pdf.setFontSize(12);
            pdf.setTextColor(100);
            const startDate = "<?php echo formatDate($startDate); ?>";
            const endDate = "<?php echo formatDate($endDate); ?>";
            pdf.text('Report Period: ' + startDate + ' - ' + endDate, 105, 25, { align: 'center' });
            
            // Add user info
            pdf.setFontSize(10);
            pdf.text('Patient: <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?>', 15, 35);
            pdf.text('Generated: <?php echo date('F d, Y h:i A'); ?>', 15, 40);
            
            // Add summary stats
            pdf.setFontSize(11);
            pdf.setTextColor(0);
            pdf.text('Summary:', 15, 50);
            pdf.setFontSize(10);
            pdf.text('• Medicines Taken: <?php echo $totalTaken; ?>', 20, 57);
            pdf.text('• Medicines Missed: <?php echo $totalMissed; ?>', 20, 63);
            pdf.text('• Adherence Rate: <?php echo $adherenceRate; ?>%', 20, 69);
            pdf.text('• Risk Level: <?php echo $riskLevel; ?>', 20, 75);
            
            // Add the chart image
            let heightLeft = imgHeight;
            let position = 85;
            
            pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
            heightLeft -= (pageHeight - position);
            
            // Add new pages if content is longer
            while (heightLeft > 0) {
                position = heightLeft - imgHeight;
                pdf.addPage();
                pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;
            }
            
            // Add footer on each page
            const totalPages = pdf.internal.getNumberOfPages();
            for (let i = 1; i <= totalPages; i++) {
                pdf.setPage(i);
                pdf.setFontSize(8);
                pdf.setTextColor(150);
                pdf.text('Page ' + i + ' of ' + totalPages + ' - PillPilot Smart Medicine Reminder', 105, 290, { align: 'center' });
            }
            
            // Save PDF
            const fileName = 'PillPilot_Report_' + startDate.replace(/\s/g, '_') + '_to_' + endDate.replace(/\s/g, '_') + '.pdf';
            pdf.save(fileName);
            
        } catch (error) {
            console.error('PDF generation failed:', error);
            alert('Failed to generate PDF: ' + error.message);
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    }
</script>

<?php include 'includes/footer.php'; ?>
