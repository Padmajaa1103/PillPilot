/**
 * Smart Medicine Reminder System
 * Checks for medicine reminders every minute
 * Features: Sound, Browser Notification, Voice (TTS), SMS
 */

(function() {
    'use strict';
    
    // Configuration
    const CHECK_INTERVAL = 60000; // Check every minute
    const REMINDER_SOUND = 'https://actions.google.com/sounds/v1/alarms/beep_short.ogg';
    
    // State
    let medicines = [];
    let userPhone = null;
    let userName = null;
    let notifiedMedicines = new Set(); // Track already notified medicines
    let familyNotified = new Set(); // Track family notifications sent
    let audioContext = null;
    let voiceEnabled = true; // Voice reminder toggle
    const FAMILY_NOTIFY_DELAY = 30 * 60 * 1000; // 30 minutes in milliseconds
    
    /**
     * Initialize the reminder system
     */
    function init() {
        console.log('PillPilot Reminder System Initialized');
        console.log('Voice Reminder: Enabled');
        
        // Check immediately
        checkReminders();
        
        // Then check every minute
        setInterval(checkReminders, CHECK_INTERVAL);
        
        // Request notification permission
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
        
        // Check speech synthesis support
        if (!('speechSynthesis' in window)) {
            console.warn('Voice Reminder: Speech synthesis not supported in this browser');
            voiceEnabled = false;
        }
    }
    
    /**
     * Fetch today's medicines from API
     */
    async function fetchMedicines() {
        try {
            const response = await fetch('api/get_medicines.php');
            const data = await response.json();
            
            if (data.success) {
                medicines = data.medicines || [];
                userPhone = data.phone;
                userName = data.user_name || 'there';
                return true;
            }
        } catch (error) {
            console.error('Failed to fetch medicines:', error);
        }
        return false;
    }
    
    /**
     * Check for medicine reminders
     */
    async function checkReminders() {
        const fetched = await fetchMedicines();
        if (!fetched) return;
        
        const now = new Date();
        const currentTime = formatTime(now);
        
        medicines.forEach(medicine => {
            // Skip if already logged (taken or missed)
            if (medicine.log_status) return;
            
            // Skip if already notified
            const notifiedKey = `${medicine.id}_${currentTime}`;
            if (notifiedMedicines.has(notifiedKey)) return;
            
            // Check if it's time for medicine
            const medicineTime = medicine.time.substring(0, 5); // HH:MM format
            
            if (isTimeMatch(currentTime, medicineTime)) {
                triggerReminder(medicine);
                notifiedMedicines.add(notifiedKey);
                
                // Send SMS reminder
                if (userPhone) {
                    sendSMSReminder(medicine);
                }
                
                // Schedule family notification after delay if not responded
                scheduleFamilyNotification(medicine);
            }
            
            // Check if it's time to notify family (30 min after medicine time)
            checkFamilyNotification(medicine, currentTime);
        });
    }
    
    /**
     * Schedule family notification for missed medicine
     */
    function scheduleFamilyNotification(medicine) {
        const familyKey = `family_${medicine.id}_${medicine.time}`;
        
        // Don't schedule if already notified
        if (familyNotified.has(familyKey)) return;
        
        setTimeout(async () => {
            // Check if medicine is still not logged
            const fetched = await fetchMedicines();
            if (!fetched) return;
            
            const updatedMedicine = medicines.find(m => m.id === medicine.id);
            
            // If still no log status (not taken or missed), notify family
            if (updatedMedicine && !updatedMedicine.log_status && !familyNotified.has(familyKey)) {
                notifyFamily(medicine);
                familyNotified.add(familyKey);
            }
        }, FAMILY_NOTIFY_DELAY);
    }
    
    /**
     * Check if it's time to notify family (for page refresh scenarios)
     */
    function checkFamilyNotification(medicine, currentTime) {
        const familyKey = `family_${medicine.id}_${medicine.time}`;
        
        // Skip if already notified or already logged
        if (familyNotified.has(familyKey) || medicine.log_status) return;
        
        // Calculate time difference
        const now = new Date();
        const [medHours, medMinutes] = medicine.time.split(':').map(Number);
        const medTime = new Date(now.getFullYear(), now.getMonth(), now.getDate(), medHours, medMinutes);
        
        const diffMs = now - medTime;
        
        // If 30+ minutes passed and not logged, notify family
        if (diffMs >= FAMILY_NOTIFY_DELAY) {
            notifyFamily(medicine);
            familyNotified.add(familyKey);
        }
    }
    
    /**
     * Send notification to family members
     */
    async function notifyFamily(medicine) {
        try {
            const response = await fetch('api/notify_family.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    medicine_id: medicine.id,
                    medicine_name: medicine.name,
                    dosage: medicine.dosage,
                    time: medicine.time
                })
            });
            
            const result = await response.json();
            console.log('Family notification result:', result);
            
            if (result.success) {
                // Show notification to user
                showFamilyNotificationAlert();
            }
        } catch (error) {
            console.error('Family notification failed:', error);
        }
    }
    
    /**
     * Show alert that family has been notified
     */
    function showFamilyNotificationAlert() {
        const alertHTML = `
            <div class="alert alert-warning alert-dismissible fade show position-fixed" 
                 style="top: 20px; right: 20px; z-index: 9999; max-width: 400px;" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Family Notified!</strong><br>
                Your family members have been notified about the missed medicine.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', alertHTML);
        
        // Auto-remove after 10 seconds
        setTimeout(() => {
            const alert = document.querySelector('.alert-warning.position-fixed');
            if (alert) alert.remove();
        }, 10000);
    }
    
    /**
     * Check if current time matches medicine time (within 1 minute window)
     */
    function isTimeMatch(currentTime, medicineTime) {
        return currentTime === medicineTime;
    }
    
    /**
     * Format time as HH:MM
     */
    function formatTime(date) {
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        return `${hours}:${minutes}`;
    }
    
    /**
     * Trigger reminder notification
     */
    function triggerReminder(medicine) {
        // Play sound
        playAlertSound();
        
        // Speak voice reminder
        speakVoiceReminder(medicine);
        
        // Show browser notification
        showBrowserNotification(medicine);
        
        // Show in-app modal
        showReminderModal(medicine);
    }
    
    /**
     * Speak voice reminder using Web Speech API
     */
    function speakVoiceReminder(medicine) {
        if (!voiceEnabled || !('speechSynthesis' in window)) return;
        
        // Cancel any ongoing speech
        window.speechSynthesis.cancel();
        
        const hour = parseInt(medicine.time.split(':')[0]);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const hour12 = hour % 12 || 12;
        const timeString = `${hour12} ${ampm}`;
        
        // Create voice message
        const message = `Hello ${userName}, it's time to take your medicine. Please take ${medicine.name}, ${medicine.dosage}, at ${timeString}.`;
        
        const utterance = new SpeechSynthesisUtterance(message);
        
        // Configure voice settings
        utterance.rate = 0.9; // Slightly slower for clarity
        utterance.pitch = 1;
        utterance.volume = 1;
        
        // Try to select a good voice
        const voices = window.speechSynthesis.getVoices();
        
        // Prefer a female voice (usually clearer for reminders)
        const preferredVoice = voices.find(voice => 
            voice.name.includes('Female') || 
            voice.name.includes('Samantha') || 
            voice.name.includes('Victoria') ||
            voice.name.includes('Google US English')
        );
        
        if (preferredVoice) {
            utterance.voice = preferredVoice;
        }
        
        // Speak
        window.speechSynthesis.speak(utterance);
        
        console.log('Voice Reminder:', message);
    }
    
    /**
     * Play alert sound
     */
    function playAlertSound() {
        try {
            const audio = new Audio(REMINDER_SOUND);
            audio.play().catch(e => console.log('Audio play failed:', e));
        } catch (e) {
            console.log('Audio error:', e);
        }
    }
    
    /**
     * Show browser notification
     */
    function showBrowserNotification(medicine) {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('PillPilot Reminder', {
                body: `Time to take ${medicine.name} (${medicine.dosage})`,
                icon: 'https://cdn-icons-png.flaticon.com/512/2937/2937192.png',
                requireInteraction: true
            });
        }
    }
    
    /**
     * Show reminder modal
     */
    function showReminderModal(medicine) {
        // Remove existing modal
        const existingModal = document.getElementById('reminderModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        const modalHTML = `
            <div class="modal fade show reminder-modal" id="reminderModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5);">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-bell me-2"></i>Medicine Reminder
                            </h5>
                        </div>
                        <div class="modal-body text-center py-4">
                            <div class="mb-3">
                                <i class="fas fa-pills text-primary" style="font-size: 64px;"></i>
                            </div>
                            <h4 class="mb-2">${medicine.name}</h4>
                            <p class="text-muted mb-3">${medicine.dosage}</p>
                            <div class="alert alert-info">
                                <i class="fas fa-clock me-2"></i>Time: ${formatTime12(medicine.time)}
                            </div>
                        </div>
                        <div class="modal-footer justify-content-center">
                            <form method="POST" action="log_medicine.php" class="d-flex gap-2">
                                <input type="hidden" name="medicine_id" value="${medicine.id}">
                                <button type="submit" name="action" value="taken" class="btn btn-success btn-lg px-4">
                                    <i class="fas fa-check me-2"></i>Taken
                                </button>
                                <button type="submit" name="action" value="missed" class="btn btn-danger btn-lg px-4">
                                    <i class="fas fa-times me-2"></i>Missed
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        // Auto-close after 5 minutes if no action
        setTimeout(() => {
            const modal = document.getElementById('reminderModal');
            if (modal) {
                modal.remove();
            }
        }, 300000);
    }
    
    /**
     * Format time to 12-hour format
     */
    function formatTime12(time24) {
        const [hours, minutes] = time24.split(':');
        const hour = parseInt(hours);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const hour12 = hour % 12 || 12;
        return `${hour12}:${minutes} ${ampm}`;
    }
    
    /**
     * Send SMS reminder via API
     */
    async function sendSMSReminder(medicine) {
        try {
            const response = await fetch('api/send_sms.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    medicine_id: medicine.id,
                    medicine_name: medicine.name,
                    dosage: medicine.dosage,
                    time: medicine.time
                })
            });
            
            const result = await response.json();
            console.log('SMS Result:', result);
        } catch (error) {
            console.error('SMS send failed:', error);
        }
    }
    
    /**
     * Setup voice toggle button
     */
    function setupVoiceToggle() {
        const toggleBtn = document.getElementById('voiceToggle');
        if (!toggleBtn) return;
        
        // Load saved preference
        const savedPreference = localStorage.getItem('voiceEnabled');
        if (savedPreference !== null) {
            voiceEnabled = savedPreference === 'true';
        }
        updateVoiceToggleUI(toggleBtn);
        
        toggleBtn.addEventListener('click', function() {
            voiceEnabled = !voiceEnabled;
            localStorage.setItem('voiceEnabled', voiceEnabled);
            updateVoiceToggleUI(toggleBtn);
            
            // Test voice when enabling
            if (voiceEnabled) {
                const testMsg = new SpeechSynthesisUtterance('Voice reminders are now enabled');
                testMsg.rate = 1;
                window.speechSynthesis.speak(testMsg);
            }
        });
    }
    
    /**
     * Update voice toggle button UI
     */
    function updateVoiceToggleUI(btn) {
        if (voiceEnabled) {
            btn.innerHTML = '<i class="fas fa-volume-up me-2"></i>Voice On';
            btn.classList.remove('btn-outline-light');
            btn.classList.add('btn-light');
        } else {
            btn.innerHTML = '<i class="fas fa-volume-mute me-2"></i>Voice Off';
            btn.classList.remove('btn-light');
            btn.classList.add('btn-outline-light');
        }
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            init();
            setupVoiceToggle();
        });
    } else {
        init();
        setupVoiceToggle();
    }
})();
