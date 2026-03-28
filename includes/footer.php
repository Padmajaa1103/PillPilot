<?php
/**
 * Footer Component
 */
?>
    </div><!-- End Main Content -->
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Reminder System -->
    <script src="assets/js/reminder.js"></script>
    
    <!-- Health Assistant Chatbot -->
    <script src="assets/js/chatbot.js"></script>
    
    <script>
        // Mobile sidebar toggle
        document.getElementById('mobileToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('show');
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert-dismissible').forEach(function(alert) {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 150);
            });
        }, 5000);
    </script>
</body>
</html>
