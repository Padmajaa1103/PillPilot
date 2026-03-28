<?php
/**
 * Sidebar Component
 */

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-pills"></i>
        <h4>PillPilot</h4>
        <small class="text-white-50">Smart Reminder</small>
    </div>
    
    <nav class="sidebar-menu">
        <a href="dashboard.php" class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="add_medicine.php" class="<?php echo $currentPage === 'add_medicine' ? 'active' : ''; ?>">
            <i class="fas fa-plus-circle"></i>
            <span>Add Medicine</span>
        </a>
        <a href="medicine_list.php" class="<?php echo $currentPage === 'medicine_list' ? 'active' : ''; ?>">
            <i class="fas fa-list"></i>
            <span>Medicine List</span>
        </a>
        <a href="reports.php" class="<?php echo $currentPage === 'reports' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Reports</span>
        </a>
        <a href="family_members.php" class="<?php echo $currentPage === 'family_members' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Family Members</span>
        </a>
        <a href="refill_settings.php" class="<?php echo $currentPage === 'refill_settings' ? 'active' : ''; ?>">
            <i class="fas fa-bell"></i>
            <span>Refill Alerts</span>
        </a>
        <a href="profile.php" class="<?php echo $currentPage === 'profile' ? 'active' : ''; ?>">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
        <a href="auth/logout.php">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </nav>
</aside>

<!-- Main Content Wrapper -->
<div class="main-content">
    <!-- Top Navbar -->
    <div class="top-navbar">
        <button class="mobile-toggle" id="mobileToggle">
            <i class="fas fa-bars"></i>
        </button>
        
        <h1 class="page-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
        
        <div class="user-menu">
            <div class="dropdown">
                <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)); ?>
                    </div>
                    <span><?php echo htmlspecialchars($currentUser['name'] ?? 'User'); ?></span>
                    <i class="fas fa-chevron-down ms-2"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Health Assistant Chatbot (Outside Sidebar for proper positioning) -->
    <div class="chatbot-wrapper">
        <button class="chatbot-toggle" id="chatbot-toggle" title="Health Assistant">
            <i class="fas fa-comment-medical"></i>
            <span>Health Assistant</span>
        </button>
        <div class="chatbot-container" id="chatbot-container">
            <div class="chatbot-header">
                <i class="fas fa-robot"></i>
                <span>Health Assistant</span>
                <small class="text-white-50 ms-auto">AI Powered</small>
            </div>
            <div class="chatbot-messages" id="chatbot-messages"></div>
            <div class="chatbot-input-wrapper">
                <input type="text" 
                       class="chatbot-input" 
                       id="chatbot-input" 
                       placeholder="Ask about medicines, interactions..."
                       maxlength="200">
                <button class="chatbot-send" id="chatbot-send">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
            <div class="chatbot-disclaimer">
                <small>Not medical advice. Consult a doctor.</small>
            </div>
        </div>
    </div>
