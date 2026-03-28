/**
 * PillPilot Health Assistant Chatbot
 * Powered by Google Gemini API
 */

class HealthChatbot {
    constructor() {
        this.chatContainer = document.getElementById('chatbot-container');
        this.messagesContainer = document.getElementById('chatbot-messages');
        this.inputField = document.getElementById('chatbot-input');
        this.sendButton = document.getElementById('chatbot-send');
        this.toggleButton = document.getElementById('chatbot-toggle');
        this.isOpen = false;
        this.isLoading = false;
        
        // Health context prompt
        this.systemPrompt = `You are a helpful health assistant for PillPilot medicine reminder app. 
Provide general medicine information, drug interaction warnings, and health tips. 
Always remind users to consult healthcare professionals for medical advice. 
Keep responses concise and helpful.`;

        this.init();
    }

    init() {
        // Load conversation history
        this.loadHistory();
        
        // Event listeners
        this.sendButton.addEventListener('click', () => this.sendMessage());
        this.inputField.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') this.sendMessage();
        });
        this.toggleButton.addEventListener('click', () => this.toggleChat());

        // Add welcome message if no history
        if (this.messagesContainer.children.length === 0) {
            this.addMessage('bot', 'Hello! I\'m your Health Assistant. Ask me about medicines, drug interactions, or health tips!');
        }
    }

    toggleChat() {
        this.isOpen = !this.isOpen;
        this.chatContainer.classList.toggle('open', this.isOpen);
        this.toggleButton.querySelector('i').className = this.isOpen ? 'fas fa-chevron-down' : 'fas fa-comment-medical';
        
        if (this.isOpen) {
            this.inputField.focus();
        }
    }

    async sendMessage() {
        const message = this.inputField.value.trim();
        if (!message || this.isLoading) return;

        // Add user message
        this.addMessage('user', message);
        this.inputField.value = '';
        this.setLoading(true);

        try {
            const response = await this.getAIResponse(message);
            this.addMessage('bot', response);
        } catch (error) {
            console.error('Chatbot error:', error);
            this.addMessage('bot', 'Sorry, I\'m having trouble connecting. Please try again later.');
        } finally {
            this.setLoading(false);
            this.saveHistory();
        }
    }

    async getAIResponse(message) {
        // Check rate limiting
        if (!this.checkRateLimit()) {
            return 'You\'ve reached the limit of 5 messages per minute. Please wait a moment.';
        }

        try {
            const response = await fetch('api/chatbot.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    message: message,
                    context: this.systemPrompt
                })
            });

            const data = await response.json();
            
            // If we got a valid AI response, use it
            if (data.response && data.response.length > 10) {
                return data.response;
            }
            
            // If API returns empty or no API key configured, use fallback
            console.log('Using fallback responses');
            return this.getFallbackResponse(message);
        } catch (error) {
            console.log('Error, using fallback:', error);
            // Fallback to local responses if API fails
            return this.getFallbackResponse(message);
        }
    }

    getFallbackResponse(message) {
        // Service unavailable - Gemini API is the primary source
        return 'Sorry, the AI service is temporarily unavailable. Please try again later.';
    }

    checkRateLimit() {
        const now = Date.now();
        const key = 'chatbot_requests';
        let requests = JSON.parse(localStorage.getItem(key) || '[]');
        
        // Filter to last minute
        requests = requests.filter(time => now - time < 60000);
        
        if (requests.length >= 5) {
            return false;
        }
        
        requests.push(now);
        localStorage.setItem(key, JSON.stringify(requests));
        return true;
    }

    addMessage(sender, text) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `chatbot-message ${sender}`;
        messageDiv.innerHTML = `
            <div class="message-content">
                ${sender === 'bot' ? '<i class="fas fa-robot"></i>' : '<i class="fas fa-user"></i>'}
                <span>${this.escapeHtml(text)}</span>
            </div>
        `;
        this.messagesContainer.appendChild(messageDiv);
        this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
    }

    setLoading(loading) {
        this.isLoading = loading;
        this.sendButton.disabled = loading;
        this.sendButton.innerHTML = loading ? '<i class="fas fa-spinner fa-spin"></i>' : '<i class="fas fa-paper-plane"></i>';
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    saveHistory() {
        const messages = Array.from(this.messagesContainer.children).map(msg => ({
            sender: msg.classList.contains('user') ? 'user' : 'bot',
            text: msg.querySelector('span').textContent
        }));
        localStorage.setItem('chatbot_history', JSON.stringify(messages.slice(-20))); // Keep last 20
    }

    loadHistory() {
        const history = JSON.parse(localStorage.getItem('chatbot_history') || '[]');
        history.forEach(msg => this.addMessage(msg.sender, msg.text));
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('chatbot-container')) {
        new HealthChatbot();
    }
});
