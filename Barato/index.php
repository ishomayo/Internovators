<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Hub Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            min-height: 100vh;
        }

        /* Header */
        .header {
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 0 2rem;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .logo {
            background: #4f46e5;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .time-info {
            text-align: right;
            font-size: 12px;
            color: #64748b;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notification-btn {
            width: 32px;
            height: 32px;
            border: 1px solid #e2e8f0;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 14px;
        }

        .user-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 6px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            background: white;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .user-avatar {
            width: 24px;
            height: 24px;
            background: #ef4444;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
        }

        /* Main Layout */
        .main-layout {
            display: flex;
            min-height: calc(100vh - 70px);
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: white;
            border-right: 1px solid #e2e8f0;
            padding: 2rem 1.5rem;
        }

        .nav-section {
            margin-bottom: 1.5rem;
        }

        .nav-title {
            color: #94a3b8;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.75rem;
            padding-left: 0.5rem;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            margin: 2px 0;
            border-radius: 8px;
            color: #475569;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .nav-item:hover {
            background: #f1f5f9;
            color: #334155;
        }

        .nav-item.active {
            background: #fef3c7;
            color: #92400e;
            border-color: #f59e0b;
        }

        .nav-icon {
            width: 18px;
            height: 18px;
            margin-right: 10px;
            background: #e2e8f0;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            color: #64748b;
            flex-shrink: 0;
        }

        .nav-item.active .nav-icon {
            background: #f59e0b;
            color: white;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            background: #f8fafc url('assets/bg.png') center center/cover no-repeat;
            position: relative;
        }

        /* Optional: Add overlay for better text readability */
        .main-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(248, 250, 252, 0.8);
            z-index: 1;
        }

        .main-content > * {
            position: relative;
            z-index: 2;
        }

        .dashboard-welcome {
            max-width: 600px;
            margin-bottom: 3rem;
        }

        .welcome-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
        }

        .welcome-subtitle {
            font-size: 1.125rem;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-primary {
            background: #4f46e5;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background: #4338ca;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: white;
            color: #64748b;
            padding: 12px 24px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-secondary:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }

        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            width: 100%;
            max-width: 800px;
            margin: 2rem 0;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            text-align: left;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 0.25rem;
        }

        .stat-change {
            font-size: 12px;
            color: #10b981;
            font-weight: 500;
        }

        /* Chatbot Styles */
        .chatbot-toggle {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 56px;
            height: 56px;
            background: #ef4444;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 4px 20px rgba(239, 68, 68, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .chatbot-toggle:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 25px rgba(239, 68, 68, 0.4);
        }

        .chatbot-container {
            position: fixed;
            bottom: 100px;
            right: 30px;
            width: 360px;
            height: 520px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            border: 1px solid #e2e8f0;
            transform: translateY(20px) scale(0.95);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            z-index: 999;
            display: flex;
            flex-direction: column;
        }

        .chatbot-container.active {
            transform: translateY(0) scale(1);
            opacity: 1;
            visibility: visible;
        }

        .chatbot-header {
            background: #f8fafc;
            color: #1e293b;
            padding: 20px;
            border-radius: 16px 16px 0 0;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chatbot-title {
            font-weight: 600;
            font-size: 16px;
        }

        .chatbot-close {
            background: none;
            border: none;
            color: #64748b;
            font-size: 18px;
            cursor: pointer;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .chatbot-close:hover {
            background: #e2e8f0;
            color: #1e293b;
        }

        .chatbot-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .message {
            max-width: 85%;
            padding: 12px 16px;
            border-radius: 16px;
            font-size: 14px;
            line-height: 1.5;
            animation: messageSlideIn 0.3s ease;
        }

        .message.bot {
            background: #f1f5f9;
            color: #334155;
            align-self: flex-start;
            border-bottom-left-radius: 4px;
        }

        .message.user {
            background: #4f46e5;
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }

        .chatbot-input-container {
            padding: 20px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }

        .chatbot-input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            outline: none;
            font-size: 14px;
            resize: none;
            min-height: 20px;
            max-height: 80px;
            transition: border-color 0.2s ease;
        }

        .chatbot-input:focus {
            border-color: #4f46e5;
        }

        .chatbot-send {
            background: #4f46e5;
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .chatbot-send:hover:not(:disabled) {
            background: #4338ca;
        }

        .chatbot-send:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .typing-indicator {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 12px 16px;
            background: #f1f5f9;
            border-radius: 16px;
            border-bottom-left-radius: 4px;
            align-self: flex-start;
            max-width: 85%;
        }

        .typing-dot {
            width: 6px;
            height: 6px;
            background: #94a3b8;
            border-radius: 50%;
            animation: typingPulse 1.4s infinite ease-in-out;
        }

        .typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .typing-dot:nth-child(2) { animation-delay: -0.16s; }

        @keyframes typingPulse {
            0%, 80%, 100% {
                transform: scale(0);
                opacity: 0.5;
            }
            40% {
                transform: scale(1);
                opacity: 1;
            }
        }

        @keyframes messageSlideIn {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 1024px) {
            .sidebar {
                width: 240px;
            }
            
            .main-content {
                padding: 2rem;
            }
        }

        @media (max-width: 768px) {
            .header {
                padding: 0 1rem;
            }
            
            .main-layout {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                padding: 1rem;
                border-right: none;
                border-bottom: 1px solid #e2e8f0;
            }
            
            .welcome-title {
                font-size: 2rem;
            }
            
            .chatbot-container {
                width: calc(100vw - 40px);
                right: 20px;
                bottom: 90px;
                height: 480px;
            }
            
            .chatbot-toggle {
                right: 20px;
                bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="logo">B</div>
        <div class="header-right">
            <div class="time-info">
                <div>Philippine Standard Time</div>
                <div>Friday, June 20, 2025, 9:29:45 AM</div>
            </div>
            <button class="notification-btn">🔔</button>
            <button class="user-btn">
                <div class="user-avatar">👤</div>
                <span>User</span>
                <span>▼</span>
            </button>
        </div>
    </div>

    <div class="main-layout">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="nav-section">
                <div class="nav-title">Home</div>
                <a href="#" class="nav-item active" data-page="dashboard">
                    <div class="nav-icon">📊</div>
                    Dashboard
                </a>
            </div>
            
            <div class="nav-section">
                <a href="inventory.html" class="nav-item" data-page="inventory">
                    <div class="nav-icon">📦</div>
                    Inventory Management
                </a>
                <a href="payroll.html" class="nav-item" data-page="payroll">
                    <div class="nav-icon">💰</div>
                    Payroll
                </a>
                <a href="expenses.php" class="nav-item" data-page="expenses">
                    <div class="nav-icon">📈</div>
                    Expense Tracker
                </a>
                <a href="support.html" class="nav-item" data-page="support">
                    <div class="nav-icon">💬</div>
                    Communication & Support
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="dashboard-welcome">
                <h1 class="welcome-title">Welcome to Your Dashboard</h1>
                <p class="welcome-subtitle">
                    Get a comprehensive overview of your business operations. Monitor key metrics, 
                    manage your inventory, track expenses, and streamline your workflow all in one place.
                </p>
                <div class="action-buttons">
                    <button class="btn-primary" onclick="openChatbot()">Get Started</button>
                    <button class="btn-secondary">View Reports</button>
                </div>
            </div>

            <div class="dashboard-stats">
                <div class="stat-card">
                    <div class="stat-value">₱125,400</div>
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-change">+12.5% from last month</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">342</div>
                    <div class="stat-label">Active Orders</div>
                    <div class="stat-change">+8.2% from last week</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">₱45,230</div>
                    <div class="stat-label">Monthly Expenses</div>
                    <div class="stat-change">-3.1% from last month</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">89%</div>
                    <div class="stat-label">Inventory Health</div>
                    <div class="stat-change">Optimal levels maintained</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chatbot -->
    <button class="chatbot-toggle" id="chatbotToggle">💬</button>
    
    <div class="chatbot-container" id="chatbotContainer">
        <div class="chatbot-header">
            <div class="chatbot-title">Business Assistant</div>
            <button class="chatbot-close" id="chatbotClose">×</button>
        </div>
        
        <div class="chatbot-messages" id="chatbotMessages">
            <div class="message bot">
                Hello! I'm here to help you navigate your business dashboard. What would you like to know about?
            </div>
        </div>
        
        <div class="chatbot-input-container">
            <input type="text" class="chatbot-input" id="chatbotInput" placeholder="Ask me anything about your business...">
            <button class="chatbot-send" id="chatbotSend">→</button>
        </div>
    </div>

    <script>
        // Update time every second
        function updateTime() {
            const now = new Date();
            const options = {
                timeZone: 'Asia/Manila',
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            };
            const timeString = now.toLocaleString('en-US', options);
            const timeInfo = document.querySelector('.time-info');
            if (timeInfo) {
                timeInfo.innerHTML = `
                    <div>Philippine Standard Time</div>
                    <div>${timeString}</div>
                `;
            }
        }

        // Update time immediately and then every second
        updateTime();
        setInterval(updateTime, 1000);

        // Chatbot functionality
        const chatbotToggle = document.getElementById('chatbotToggle');
        const chatbotContainer = document.getElementById('chatbotContainer');
        const chatbotClose = document.getElementById('chatbotClose');
        const chatbotMessages = document.getElementById('chatbotMessages');
        const chatbotInput = document.getElementById('chatbotInput');
        const chatbotSend = document.getElementById('chatbotSend');

        let isTyping = false;

        function openChatbot() {
            chatbotContainer.classList.add('active');
            chatbotInput.focus();
        }

        // Toggle chatbot
        chatbotToggle.addEventListener('click', openChatbot);

        chatbotClose.addEventListener('click', () => {
            chatbotContainer.classList.remove('active');
        });

        // Send message function
        function sendMessage() {
            const message = chatbotInput.value.trim();
            if (message && !isTyping) {
                addMessage(message, 'user');
                chatbotInput.value = '';
                chatbotSend.disabled = true;
                
                // Show typing indicator
                showTypingIndicator();
                
                // Simulate bot response
                setTimeout(() => {
                    hideTypingIndicator();
                    const response = getBotResponse(message);
                    addMessage(response, 'bot');
                    chatbotSend.disabled = false;
                }, 800 + Math.random() * 1500);
            }
        }

        // Add message to chat
        function addMessage(text, sender) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message ${sender}`;
            messageDiv.textContent = text;
            chatbotMessages.appendChild(messageDiv);
            chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
        }

        // Show typing indicator
        function showTypingIndicator() {
            isTyping = true;
            const typingDiv = document.createElement('div');
            typingDiv.className = 'typing-indicator';
            typingDiv.id = 'typingIndicator';
            typingDiv.innerHTML = '<div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div>';
            chatbotMessages.appendChild(typingDiv);
            chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
        }

        // Hide typing indicator
        function hideTypingIndicator() {
            isTyping = false;
            const typingIndicator = document.getElementById('typingIndicator');
            if (typingIndicator) {
                typingIndicator.remove();
            }
        }

        // Get bot response
        function getBotResponse(message) {
            const responses = {
                'dashboard': 'Your dashboard shows key metrics like revenue (₱125,400), active orders (342), and expenses (₱45,230). Everything looks healthy! What specific metric would you like to explore?',
                'revenue': 'Great news! Your total revenue is ₱125,400, up 12.5% from last month. This shows strong business growth. Would you like to see a breakdown by product or service?',
                'orders': 'You currently have 342 active orders, which is 8.2% higher than last week. Your order processing seems to be running smoothly!',
                'expenses': 'Your monthly expenses are ₱45,230, down 3.1% from last month. This is excellent cost management! Need help identifying areas for further optimization?',
                'inventory': 'Your inventory health is at 89%, which means optimal stock levels are maintained. I can help you with inventory management, reorder alerts, or stock analysis.',
                'payroll': 'I can help you with payroll processing, employee payments, tax calculations, and compliance reporting. What payroll task do you need assistance with?',
                'help': 'I can assist you with dashboard navigation, business metrics analysis, inventory management, payroll questions, expense tracking, and general business insights. What would you like to explore?',
                'hello': 'Hello! Welcome to your business dashboard. I can see your business is performing well with strong revenue growth. How can I help you today?',
                'hi': 'Hi there! Your dashboard shows some great metrics. What aspect of your business would you like to discuss?'
            };

            const lowerMessage = message.toLowerCase();
            
            // Check for keyword matches
            for (const [keyword, response] of Object.entries(responses)) {
                if (lowerMessage.includes(keyword)) {
                    return response;
                }
            }

            // Default responses
            const defaultResponses = [
                'That\'s a great question! Based on your current dashboard metrics, I can provide more specific insights. What particular area interests you most?',
                'I\'d be happy to help you with that. Your business seems to be performing well with ₱125,400 in revenue this month. Would you like me to dive deeper into any specific metric?',
                'Interesting! Looking at your dashboard, I can see several areas where I might be able to assist. Could you be more specific about what you need help with?',
                'I understand what you\'re looking for. Your current business performance shows positive trends. Let me know which aspect you\'d like to explore further!'
            ];

            return defaultResponses[Math.floor(Math.random() * defaultResponses.length)];
        }

        // Event listeners
        chatbotSend.addEventListener('click', sendMessage);
        
        chatbotInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                sendMessage();
            }
        });

        // Navigation interactions - Simplified to allow normal navigation
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', (e) => {
                // Only handle dashboard clicks (no navigation needed)
                if (item.getAttribute('href') === '#') {
                    e.preventDefault();
                    document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
                    item.classList.add('active');
                }
                // For all other links with actual hrefs, let the browser navigate normally
                // Don't prevent default - this allows normal navigation to work
            });
        });

        // Close chatbot when clicking outside
        document.addEventListener('click', (e) => {
            if (!chatbotContainer.contains(e.target) && !chatbotToggle.contains(e.target)) {
                chatbotContainer.classList.remove('active');
            }
        });
    </script>
</body>
</html>