<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#FFD700">
    <title><?php echo esc_html(get_bloginfo('name')); ?> - Track Your Order</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            /* Zaikon Yellow Theme */
            --zaikon-yellow: #FFD700;
            --zaikon-yellow-dark: #E0B200;
            --zaikon-yellow-light: #FFE44D;
            --zaikon-yellow-subtle: #FFF9E6;
            
            /* Dark text for contrast */
            --text-on-yellow: #000000;
            
            /* Status colors */
            --success: #22C55E;
            --success-light: #DCFCE7;
            --warning: #F59E0B;
            --warning-light: #FEF3C7;
            --danger: #EF4444;
            --danger-light: #FEE2E2;
            --info: #3B82F6;
            
            /* Neutrals */
            --gray-50: #F9FAFB;
            --gray-100: #F3F4F6;
            --gray-200: #E5E7EB;
            --gray-300: #D1D5DB;
            --gray-400: #9CA3AF;
            --gray-500: #6B7280;
            --gray-600: #4B5563;
            --gray-700: #374151;
            --gray-800: #1F2937;
            --gray-900: #111827;
            --white: #FFFFFF;
            
            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            --shadow-yellow: 0 4px 14px 0 rgba(255, 215, 0, 0.4);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, var(--zaikon-yellow-subtle) 0%, #FFF8E1 50%, #FFFDE7 100%);
            min-height: 100vh;
            padding: 16px;
            line-height: 1.6;
            color: var(--gray-800);
        }
        
        .container {
            max-width: 480px;
            margin: 0 auto;
            background: var(--white);
            border-radius: 24px;
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-20px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.8; }
        }
        
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 0 0 rgba(255, 215, 0, 0.4); }
            50% { box-shadow: 0 0 0 10px rgba(255, 215, 0, 0); }
        }
        
        /* Header */
        .header {
            background: linear-gradient(135deg, var(--zaikon-yellow) 0%, var(--zaikon-yellow-dark) 100%);
            color: var(--text-on-yellow);
            padding: 28px 24px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: -50%; left: -50%;
            width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 60%);
            animation: pulse 4s ease-in-out infinite;
        }
        
        .header-content { position: relative; z-index: 1; }
        
        .header .brand-icon {
            width: 56px; height: 56px;
            background: rgba(0,0,0,0.1);
            border-radius: 16px;
            display: inline-flex;
            align-items: center; justify-content: center;
            margin-bottom: 12px;
        }
        
        .header .brand-icon svg { width: 32px; height: 32px; fill: var(--text-on-yellow); }
        
        .header h1 { font-size: 22px; font-weight: 700; margin-bottom: 6px; letter-spacing: -0.02em; }
        .header .order-number { font-size: 15px; opacity: 0.85; font-weight: 500; }
        
        .content { padding: 24px; }
        
        /* Loading State */
        .loading { text-align: center; padding: 60px 20px; }
        
        .loading-spinner {
            width: 48px; height: 48px;
            margin: 0 auto 20px;
            position: relative;
        }
        
        .loading-spinner::before, .loading-spinner::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: 50%;
            border: 3px solid transparent;
        }
        
        .loading-spinner::before {
            border-top-color: var(--zaikon-yellow);
            animation: spin 1s linear infinite;
        }
        
        .loading-spinner::after {
            border-bottom-color: var(--zaikon-yellow-dark);
            animation: spin 1s linear infinite reverse;
            animation-delay: -0.5s;
        }
        
        @keyframes spin { 100% { transform: rotate(360deg); } }
        
        .loading p { color: var(--gray-500); font-size: 15px; font-weight: 500; }
        
        /* Error State */
        .error-message {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #DC2626;
            padding: 16px 20px;
            border-radius: 12px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .error-message .error-icon { display: flex; align-items: center; gap: 10px; }
        .error-message svg { width: 24px; height: 24px; flex-shrink: 0; }
        
        /* Search Section */
        .search-section {
            margin-top: 24px;
            padding: 20px;
            background: var(--gray-50);
            border-radius: 16px;
            border: 1px solid var(--gray-200);
        }
        
        .search-section-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 12px;
            text-align: center;
        }
        
        .search-form { display: flex; flex-direction: column; gap: 12px; }
        
        .search-input-group { display: flex; gap: 8px; }
        
        .search-input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid var(--gray-300);
            border-radius: 12px;
            font-size: 15px;
            font-family: inherit;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        .search-input:focus {
            border-color: var(--zaikon-yellow);
            box-shadow: 0 0 0 3px rgba(255, 215, 0, 0.2);
        }
        
        .search-btn {
            padding: 12px 20px;
            background: linear-gradient(135deg, var(--zaikon-yellow) 0%, var(--zaikon-yellow-dark) 100%);
            color: var(--text-on-yellow);
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            white-space: nowrap;
        }
        
        .search-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-yellow);
        }
        
        .search-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .search-hint { font-size: 12px; color: var(--gray-500); text-align: center; }
        
        /* Current Status Badge */
        .current-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--zaikon-yellow), var(--zaikon-yellow-dark));
            color: var(--text-on-yellow);
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 24px;
            animation: fadeIn 0.5s ease-out;
        }
        
        .current-status-badge .pulse-dot {
            width: 8px; height: 8px;
            background: var(--text-on-yellow);
            border-radius: 50%;
            animation: pulseDot 1.5s ease-in-out infinite;
        }
        
        @keyframes pulseDot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.3); }
        }
        
        /* ===== 3-STEP TRACKING UI ===== */
        .tracking-steps {
            display: flex;
            flex-direction: column;
            gap: 0;
            margin: 24px 0;
        }
        
        .tracking-step {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 20px 0;
            position: relative;
            opacity: 0;
            animation: slideInLeft 0.4s ease-out forwards;
        }
        
        .tracking-step:nth-child(1) { animation-delay: 0.1s; }
        .tracking-step:nth-child(2) { animation-delay: 0.2s; }
        .tracking-step:nth-child(3) { animation-delay: 0.3s; }
        
        .tracking-step:not(:last-child)::after {
            content: '';
            position: absolute;
            left: 23px;
            top: 68px;
            width: 2px;
            height: calc(100% - 48px);
            background: var(--gray-200);
            transition: background 0.3s ease;
        }
        
        .tracking-step.completed:not(:last-child)::after {
            background: var(--success);
        }
        
        .tracking-step.active:not(:last-child)::after {
            background: linear-gradient(to bottom, var(--zaikon-yellow), var(--gray-200));
        }
        
        .step-indicator {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--gray-100);
            border: 2px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }
        
        .step-indicator svg {
            width: 24px;
            height: 24px;
            fill: var(--gray-400);
            transition: fill 0.3s ease;
        }
        
        .tracking-step.completed .step-indicator {
            background: var(--success);
            border-color: var(--success);
            box-shadow: 0 0 0 4px var(--success-light);
        }
        
        .tracking-step.completed .step-indicator svg {
            fill: var(--white);
        }
        
        .tracking-step.active .step-indicator {
            background: var(--zaikon-yellow);
            border-color: var(--zaikon-yellow);
            box-shadow: 0 0 0 4px rgba(255, 215, 0, 0.3);
            animation: pulseGlow 2s ease-in-out infinite;
        }
        
        .tracking-step.active .step-indicator svg {
            fill: var(--text-on-yellow);
        }
        
        .step-content {
            flex: 1;
            padding-top: 4px;
        }
        
        .step-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 4px;
        }
        
        .tracking-step.pending .step-title {
            color: var(--gray-400);
        }
        
        .step-description {
            font-size: 13px;
            color: var(--gray-500);
        }
        
        .tracking-step.pending .step-description {
            color: var(--gray-400);
        }
        
        .step-time {
            font-size: 12px;
            color: var(--gray-400);
            margin-top: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .step-time svg { width: 12px; height: 12px; fill: var(--gray-400); }
        
        /* ===== COUNTDOWN TIMER ===== */
        .countdown-timer {
            margin-top: 12px;
            padding: 16px;
            background: linear-gradient(135deg, var(--zaikon-yellow-subtle) 0%, #FFF8E1 100%);
            border-radius: 12px;
            border: 1px solid var(--zaikon-yellow);
            text-align: center;
        }
        
        .countdown-timer.overtime {
            background: linear-gradient(135deg, var(--warning-light) 0%, #FEF3C7 100%);
            border-color: var(--warning);
        }
        
        .countdown-label {
            font-size: 11px;
            font-weight: 600;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 4px;
        }
        
        .countdown-time {
            font-size: 36px;
            font-weight: 800;
            color: var(--text-on-yellow);
            letter-spacing: -0.02em;
        }
        
        .countdown-timer.overtime .countdown-time {
            color: var(--warning);
        }
        
        .countdown-message {
            font-size: 12px;
            color: var(--gray-600);
            margin-top: 4px;
        }
        
        /* ===== ANIMATED RIDER (Step 3) ===== */
        .rider-animation-container {
            margin-top: 16px;
            padding: 20px;
            background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%);
            border-radius: 16px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .rider-animation-container::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: repeating-linear-gradient(
                90deg,
                var(--gray-300) 0px,
                var(--gray-300) 10px,
                transparent 10px,
                transparent 20px
            );
            animation: roadMove 1s linear infinite;
        }
        
        @keyframes roadMove {
            from { transform: translateX(0); }
            to { transform: translateX(-20px); }
        }
        
        .animated-rider {
            display: inline-block;
            animation: riderBounce 0.8s ease-in-out infinite;
        }
        
        @keyframes riderBounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }
        
        .rider-animation-container svg {
            width: 120px;
            height: 80px;
        }
        
        /* ===== RIDER INFO CARD ===== */
        .rider-card {
            background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%);
            border: 1px solid #C7D2FE;
            border-radius: 16px;
            padding: 20px;
            margin: 24px 0;
            display: none;
        }
        
        .rider-card.visible {
            display: block;
            animation: slideInLeft 0.4s ease-out;
        }
        
        .rider-header {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 16px;
            color: #4338CA;
        }
        
        .rider-header svg { width: 20px; height: 20px; fill: #4338CA; }
        
        .rider-details { display: flex; align-items: center; gap: 16px; }
        
        .rider-avatar {
            width: 56px; height: 56px;
            border-radius: 16px;
            background: linear-gradient(135deg, #6366F1 0%, #4F46E5 100%);
            color: var(--white);
            display: flex;
            align-items: center; justify-content: center;
            font-size: 20px;
            font-weight: 700;
            box-shadow: var(--shadow-md);
        }
        
        .rider-info { flex: 1; }
        .rider-name { font-weight: 600; font-size: 16px; color: var(--gray-800); margin-bottom: 4px; }
        .rider-phone { font-size: 14px; color: var(--gray-500); display: flex; align-items: center; gap: 6px; }
        .rider-phone svg { width: 14px; height: 14px; fill: var(--gray-400); }
        
        .call-rider-btn {
            background: #4F46E5;
            color: var(--white);
            border: none;
            width: 44px; height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center; justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-md);
            text-decoration: none;
        }
        
        .call-rider-btn:hover { background: #4338CA; transform: translateY(-2px); }
        .call-rider-btn svg { width: 20px; height: 20px; fill: var(--white); }
        
        /* ===== ORDER SUMMARY (Bottom Details) ===== */
        .order-summary {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 16px;
            padding: 20px;
            margin: 24px 0;
        }
        
        .summary-header {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 16px;
            color: var(--gray-700);
        }
        
        .summary-header svg { width: 18px; height: 18px; fill: var(--gray-500); }
        
        .summary-grid { display: grid; gap: 16px; }
        
        .summary-item { display: flex; align-items: flex-start; gap: 12px; }
        
        .summary-icon {
            width: 36px; height: 36px;
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            display: flex;
            align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        
        .summary-icon svg { width: 16px; height: 16px; fill: var(--gray-500); }
        
        .summary-content { flex: 1; }
        .summary-label { 
            font-size: 11px; 
            color: var(--gray-500); 
            text-transform: uppercase; 
            letter-spacing: 0.05em; 
            font-weight: 600; 
            margin-bottom: 2px; 
        }
        .summary-value { font-size: 14px; color: var(--gray-800); font-weight: 500; }
        
        .summary-total {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 2px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .summary-total .summary-label { font-size: 14px; color: var(--gray-700); }
        .summary-total .summary-value { 
            font-size: 20px; 
            font-weight: 700; 
            color: var(--gray-900);
        }
        
        /* ===== FOOTER ===== */
        .footer {
            text-align: center;
            padding: 24px;
            background: var(--gray-50);
            border-top: 1px solid var(--gray-100);
        }
        
        .footer-message { font-size: 15px; font-weight: 600; color: var(--gray-700); margin-bottom: 8px; }
        .footer-sub { font-size: 13px; color: var(--gray-500); }
        .powered-by { 
            margin-top: 16px; 
            padding-top: 16px; 
            border-top: 1px solid var(--gray-200); 
            font-size: 11px; 
            color: var(--gray-400); 
            text-transform: uppercase; 
            letter-spacing: 0.1em; 
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 480px) {
            body { padding: 0; background: var(--gray-100); }
            .container { border-radius: 0; min-height: 100vh; }
            .header { padding: 24px 20px; }
            .countdown-time { font-size: 32px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div class="brand-icon">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                    </svg>
                </div>
                <h1><?php echo esc_html(get_bloginfo('name')); ?></h1>
                <div class="order-number" id="order-number-header">Track Your Order</div>
            </div>
        </div>
        
        <div class="content">
            <!-- Loading State -->
            <div id="loading-state" class="loading">
                <div class="loading-spinner"></div>
                <p>Loading your order...</p>
            </div>
            
            <!-- Error State -->
            <div id="error-state" style="display: none;">
                <div class="error-message">
                    <div class="error-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                        </svg>
                        <span id="error-text">Unable to load order</span>
                    </div>
                </div>
                
                <div class="search-section">
                    <div class="search-section-title">Find Your Order</div>
                    <div class="search-form">
                        <div class="search-input-group">
                            <input type="text" id="search-input" class="search-input" placeholder="Enter order number" autocomplete="off" />
                            <button type="button" id="search-btn" class="search-btn">Search</button>
                        </div>
                        <div class="search-hint">Example: ORD-2026001</div>
                    </div>
                    <div id="search-error" class="error-message" style="display: none; margin-top: 16px;">
                        <div class="error-icon">
                            <svg viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
                            </svg>
                            <span id="search-error-text">No orders found</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Order Tracking (3 Steps) -->
            <div id="order-tracking" style="display: none;">
                <div style="text-align: center;">
                    <div class="current-status-badge" id="current-status-badge">
                        <span class="pulse-dot"></span>
                        <span id="current-status-text">Loading...</span>
                    </div>
                </div>
                
                <!-- 3-Step Tracking -->
                <div class="tracking-steps" id="tracking-steps">
                    <!-- Steps will be rendered by JavaScript -->
                </div>
                
                <!-- Rider Card (Only visible in Step 3) -->
                <div id="rider-card" class="rider-card">
                    <div class="rider-header">
                        <svg viewBox="0 0 24 24"><path d="M19.15 8a2 2 0 0 0-1.72-1H15V5a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v10a2 2 0 0 0 2 2h1a3 3 0 0 0 6 0h2a3 3 0 0 0 6 0h2V11a4 4 0 0 0-.85-3zM9 18a1 1 0 1 1 1-1 1 1 0 0 1-1 1zm8 0a1 1 0 1 1 1-1 1 1 0 0 1-1 1zm-.18-6H15V9h2.43l1.8 3z"/></svg>
                        Your Delivery Rider
                    </div>
                    <div class="rider-details">
                        <div class="rider-avatar" id="rider-avatar">R</div>
                        <div class="rider-info">
                            <div class="rider-name" id="rider-name">Rider Name</div>
                            <div class="rider-phone">
                                <svg viewBox="0 0 24 24"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
                                <span id="rider-phone-text">Phone Number</span>
                            </div>
                        </div>
                        <a href="#" id="call-rider-btn" class="call-rider-btn">
                            <svg viewBox="0 0 24 24"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
                        </a>
                    </div>
                </div>
                
                <!-- Order Summary (Bottom Details) -->
                <div class="order-summary" id="order-summary">
                    <div class="summary-header">
                        <svg viewBox="0 0 24 24"><path d="M18 6h-2c0-2.21-1.79-4-4-4S8 3.79 8 6H6c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-6-2c1.1 0 2 .9 2 2h-4c0-1.1.9-2 2-2zm6 16H6V8h12v12z"/></svg>
                        Order Details
                    </div>
                    <div class="summary-grid" id="summary-grid">
                        <!-- Rendered by JavaScript -->
                    </div>
                    <div class="summary-total">
                        <span class="summary-label">Order Total</span>
                        <span class="summary-value" id="order-total">Rs 0</span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <div class="footer-message">Thank you for your order!</div>
            <div class="footer-sub">We appreciate your business</div>
            <div class="powered-by">Powered by Zaikon POS</div>
        </div>
    </div>

    <script>
        // Configuration
        const rawToken = '<?php echo esc_js(get_query_var("zaikon_tracking_token")); ?>';
        const trackingToken = /^[a-f0-9]{16,64}$/.test(rawToken) ? rawToken : null;
        const apiBaseUrl = '<?php echo esc_js(rest_url("zaikon/v1/")); ?>';
        
        // Server time synchronization for accurate countdown calculations
        // This provides the server's current UTC time in milliseconds at page load
        let serverUtcTimeMs = <?php echo (int)(current_time('timestamp', true) * 1000); ?>;
        // This is the client's time when the sync was last calculated
        let clientSyncTimeMs = Date.now();
        // The offset between server UTC and client time (positive if client is ahead of server)
        let serverClientTimeOffset = clientSyncTimeMs - serverUtcTimeMs;
        
        /**
         * Update server time synchronization from API response.
         * Called on each poll to maintain accurate time sync even if page is open for hours.
         * @param {number} serverTime Server UTC time in milliseconds from API response
         */
        function updateServerTimeSync(serverTime) {
            if (serverTime && !isNaN(serverTime)) {
                serverUtcTimeMs = serverTime;
                clientSyncTimeMs = Date.now();
                serverClientTimeOffset = clientSyncTimeMs - serverUtcTimeMs;
            }
        }
        
        /**
         * Get the current server UTC time in milliseconds.
         * This compensates for any client-server time difference by using the
         * offset calculated from the most recent API poll.
         * @returns {number} Current server UTC time in milliseconds
         */
        function getServerUtcNow() {
            return Date.now() - serverClientTimeOffset;
        }
        
        // State
        let currentOrderData = null;
        let pollInterval = null;
        let countdownInterval = null;
        let deliveryCountdownInterval = null;
        
        // Track previous state for change detection
        let previousOrderStatus = null;
        let previousCookingStartedAt = null;
        let previousReadyAt = null;
        let previousDispatchedAt = null;
        
        // Server timestamps for countdown (from DB timestamps)
        let cookingStartedAt = null;
        let readyAt = null;
        let dispatchedAt = null;
        
        // Timer constants (in minutes)
        const DEFAULT_COOKING_TIME_MINUTES = 20;
        const DEFAULT_DELIVERY_TIME_MINUTES = 10;
        const OVERTIME_EXTENSION_MINUTES = 5;
        
        // Polling interval (5 seconds for real-time sync with KDS)
        const POLL_INTERVAL_MS = 5000;
        
        // Step Icons
        const stepIcons = {
            confirmed: '<svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>',
            preparing: '<svg viewBox="0 0 24 24"><path d="M8.1 13.34l2.83-2.83L3.91 3.5c-1.56 1.56-1.56 4.09 0 5.66l4.19 4.18zm6.78-1.81c1.53.71 3.68.21 5.27-1.38 1.91-1.91 2.28-4.65.81-6.12-1.46-1.46-4.2-1.1-6.12.81-1.59 1.59-2.09 3.74-1.38 5.27L3.7 19.87l1.41 1.41L12 14.41l6.88 6.88 1.41-1.41L13.41 13l1.47-1.47z"/></svg>',
            ontheway: '<svg viewBox="0 0 24 24"><path d="M19.15 8a2 2 0 0 0-1.72-1H15V5a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v10a2 2 0 0 0 2 2h1a3 3 0 0 0 6 0h2a3 3 0 0 0 6 0h2V11a4 4 0 0 0-.85-3zM9 18a1 1 0 1 1 1-1 1 1 0 0 1-1 1zm8 0a1 1 0 1 1 1-1 1 1 0 0 1-1 1zm-.18-6H15V9h2.43l1.8 3z"/></svg>',
            checkmark: '<svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>',
            clock: '<svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>'
        };
        
        // Animated Rider SVG
        const animatedRiderSVG = `
            <svg viewBox="0 0 150 100" xmlns="http://www.w3.org/2000/svg">
                <!-- Motorbike Body -->
                <ellipse cx="45" cy="70" rx="20" ry="20" fill="#374151"/>
                <ellipse cx="105" cy="70" rx="20" ry="20" fill="#374151"/>
                <ellipse cx="45" cy="70" rx="12" ry="12" fill="#6B7280"/>
                <ellipse cx="105" cy="70" rx="12" ry="12" fill="#6B7280"/>
                
                <!-- Bike Frame -->
                <path d="M45 70 L65 45 L95 45 L105 70" stroke="#FFD700" stroke-width="4" fill="none"/>
                <path d="M65 45 L75 30 L85 30" stroke="#FFD700" stroke-width="3" fill="none"/>
                
                <!-- Rider Body -->
                <ellipse cx="75" cy="25" rx="8" ry="8" fill="#4F46E5"/>
                <path d="M67 33 L75 50 L83 33" fill="#4F46E5"/>
                <circle cx="75" cy="18" r="7" fill="#FCD34D"/>
                
                <!-- Helmet -->
                <path d="M68 18 Q75 8 82 18" stroke="#1F2937" stroke-width="3" fill="none"/>
                
                <!-- Package/Bag -->
                <rect x="88" y="35" width="15" height="12" rx="2" fill="#EF4444"/>
                <path d="M90 35 L95 30 L100 35" stroke="#EF4444" stroke-width="2" fill="none"/>
                
                <!-- Motion Lines -->
                <line x1="10" y1="50" x2="25" y2="50" stroke="#9CA3AF" stroke-width="2" opacity="0.6">
                    <animate attributeName="x1" values="10;5;10" dur="0.5s" repeatCount="indefinite"/>
                    <animate attributeName="x2" values="25;20;25" dur="0.5s" repeatCount="indefinite"/>
                </line>
                <line x1="15" y1="60" x2="30" y2="60" stroke="#9CA3AF" stroke-width="2" opacity="0.4">
                    <animate attributeName="x1" values="15;10;15" dur="0.6s" repeatCount="indefinite"/>
                    <animate attributeName="x2" values="30;25;30" dur="0.6s" repeatCount="indefinite"/>
                </line>
                <line x1="12" y1="70" x2="22" y2="70" stroke="#9CA3AF" stroke-width="2" opacity="0.5">
                    <animate attributeName="x1" values="12;7;12" dur="0.4s" repeatCount="indefinite"/>
                    <animate attributeName="x2" values="22;17;22" dur="0.4s" repeatCount="indefinite"/>
                </line>
            </svg>
        `;
        
        // Map backend statuses to 3-step system
        function getTrackingStep(status) {
            // Step 1: Confirmed (pending, confirmed)
            // Step 2: Preparing (cooking)
            // Step 3: On The Way (ready, dispatched, delivered)
            switch(status) {
                case 'pending':
                case 'confirmed':
                    return 1;
                case 'cooking':
                    return 2;
                case 'ready':
                case 'dispatched':
                case 'delivered':
                    return 3;
                default:
                    return 1;
            }
        }
        
        // Show visual notification when KDS updates order
        function showUpdateNotification(newStatus) {
            const statusMessages = {
                'cooking': '🔥 Your order is now being prepared!',
                'ready': '✅ Your order is ready!',
                'dispatched': '🚚 Your order is on the way!',
                'delivered': '🎉 Your order has been delivered!'
            };
            
            const message = statusMessages[newStatus];
            if (!message) return;
            
            // Create notification element
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, var(--zaikon-yellow) 0%, var(--zaikon-yellow-dark) 100%);
                color: var(--text-on-yellow);
                padding: 16px 24px;
                border-radius: 12px;
                box-shadow: var(--shadow-lg);
                font-weight: 600;
                font-size: 16px;
                z-index: 10000;
                animation: slideInRight 0.3s ease-out;
                max-width: 300px;
            `;
            notification.textContent = message;
            
            // Add animation
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideInRight {
                    from { transform: translateX(400px); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                @keyframes slideOutRight {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(400px); opacity: 0; }
                }
            `;
            if (!document.getElementById('tracking-notification-styles')) {
                style.id = 'tracking-notification-styles';
                document.head.appendChild(style);
            }
            
            document.body.appendChild(notification);
            
            // Remove after 4 seconds
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease-in';
                setTimeout(() => notification.remove(), 300);
            }, 4000);
        }
        
        // Get step status text
        function getStepStatusText(step, currentStep, backendStatus) {
            const isCompleted = step < currentStep;
            const isActive = step === currentStep;
            
            switch(step) {
                case 1:
                    return 'Order Confirmed';
                case 2:
                    return isCompleted ? 'Preparation Complete' : 'Preparing Your Order';
                case 3:
                    if (backendStatus === 'delivered') return 'Delivered';
                    return 'Rider On The Way';
                default:
                    return '';
            }
        }
        
        // Fetch order data
        async function fetchOrderData() {
            console.log('ZAIKON TRACKING: Fetching order...');
            
            try {
                const response = await fetch(`${apiBaseUrl}track/${trackingToken}`);
                const data = await response.json();
                
                if (!response.ok || !data.success) {
                    console.error('ZAIKON TRACKING: Order lookup failed', data);
                    if (response.status === 404) {
                        throw new Error('Order not found. The tracking link may have expired.');
                    } else if (response.status === 400) {
                        throw new Error('Invalid tracking link. Please check your URL.');
                    }
                    throw new Error(data.message || 'Unable to load order details.');
                }
                
                // Update server time synchronization on each poll to maintain accuracy
                // This compensates for any drift if the page remains open for extended periods
                if (data.server_utc_ms) {
                    updateServerTimeSync(data.server_utc_ms);
                }
                
                // Log received data for debugging KDS sync issues
                console.log('ZAIKON TRACKING: Order data received', {
                    order_number: data.order.order_number,
                    status: data.order.order_status,
                    cooking_started_at: data.order.cooking_started_at,
                    ready_at: data.order.ready_at,
                    dispatched_at: data.order.dispatched_at
                });
                
                currentOrderData = data;
                renderOrderTracking(data);
                
            } catch (error) {
                console.error('ZAIKON TRACKING: Error:', error);
                showError(error.message || 'Unable to load order.');
            }
        }
        
        // Main render function
        function renderOrderTracking(data) {
            const order = data.order;
            
            document.getElementById('loading-state').style.display = 'none';
            document.getElementById('order-tracking').style.display = 'block';
            document.getElementById('order-number-header').textContent = `Order #${order.order_number}`;
            
            const currentStep = getTrackingStep(order.order_status);
            const statusText = getStepStatusText(currentStep, currentStep, order.order_status);
            document.getElementById('current-status-text').textContent = statusText;
            
            // Parse and store server timestamps for countdown timers
            const newCookingStartedAt = parseServerTimestamp(order.cooking_started_at);
            const newReadyAt = parseServerTimestamp(order.ready_at);
            const newDispatchedAt = parseServerTimestamp(order.dispatched_at);
            
            // ====== KDS SYNC: Detect status changes from KDS updates ======
            const statusChanged = previousOrderStatus !== null && previousOrderStatus !== order.order_status;
            const cookingStarted = previousCookingStartedAt === null && newCookingStartedAt !== null;
            const orderReady = previousReadyAt === null && newReadyAt !== null;
            const orderDispatched = previousDispatchedAt === null && newDispatchedAt !== null;
            
            // Log KDS sync events for debugging
            if (statusChanged || cookingStarted || orderReady || orderDispatched) {
                console.log('🔄 KDS UPDATE DETECTED:', {
                    statusChanged: statusChanged ? `${previousOrderStatus} → ${order.order_status}` : false,
                    cookingStarted: cookingStarted,
                    orderReady: orderReady,
                    orderDispatched: orderDispatched,
                    timestamp: new Date().toISOString()
                });
                
                // Show visual notification to user (optional - can be styled)
                showUpdateNotification(order.order_status);
            }
            
            // Update timestamps
            cookingStartedAt = newCookingStartedAt;
            readyAt = newReadyAt;
            dispatchedAt = newDispatchedAt;
            
            // Update previous state for next comparison
            previousOrderStatus = order.order_status;
            previousCookingStartedAt = newCookingStartedAt;
            previousReadyAt = newReadyAt;
            previousDispatchedAt = newDispatchedAt;
            // ====== End KDS SYNC ======
            
            // Debug logging for timezone validation (can be enabled by adding ?debug=time to URL)
            if (window.location.search.includes('debug=time')) {
                logOrderTimingDebugInfo(order, currentStep);
            }
            
            // Render the 3 steps
            renderTrackingSteps(order, currentStep);
            
            // Render rider card (only visible in step 3)
            renderRiderCard(order, currentStep);
            
            // Render order summary (bottom details)
            renderOrderSummary(order);
            
            // Stop polling for final states
            if (['delivered', 'cancelled'].includes(order.order_status) && pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
                const badge = document.getElementById('current-status-badge');
                const pulseDot = badge.querySelector('.pulse-dot');
                if (pulseDot) pulseDot.style.display = 'none';
            }
        }
        
        // Render 3-step tracking UI
        function renderTrackingSteps(order, currentStep) {
            const stepsContainer = document.getElementById('tracking-steps');
            
            const steps = [
                {
                    num: 1,
                    title: 'Order Confirmed',
                    description: 'Your order has been received and confirmed',
                    icon: stepIcons.confirmed,
                    timestamp: order.confirmed_at || order.created_at
                },
                {
                    num: 2,
                    title: 'Preparing Order',
                    description: 'Our kitchen is preparing your delicious food',
                    icon: stepIcons.preparing,
                    timestamp: order.cooking_started_at,
                    showCookingCountdown: true
                },
                {
                    num: 3,
                    title: 'Rider On The Way',
                    description: 'Your rider is delivering your order',
                    icon: stepIcons.ontheway,
                    timestamp: order.dispatched_at || order.ready_at,
                    showDeliveryCountdown: true,
                    showRiderAnimation: true
                }
            ];
            
            stepsContainer.innerHTML = steps.map(step => {
                const isCompleted = step.num < currentStep;
                const isActive = step.num === currentStep;
                const isPending = step.num > currentStep;
                
                let statusClass = '';
                if (isCompleted) statusClass = 'completed';
                else if (isActive) statusClass = 'active';
                else statusClass = 'pending';
                
                // Use checkmark for completed steps
                const displayIcon = isCompleted ? stepIcons.checkmark : step.icon;
                
                let extraContent = '';
                
                // Step 2: Show cooking countdown timer when active
                if (step.showCookingCountdown && isActive && order.order_status === 'cooking') {
                    extraContent = `
                        <div class="countdown-timer" id="countdown-timer">
                            <div class="countdown-label">Time Remaining</div>
                            <div class="countdown-time" id="countdown-time">20:00</div>
                            <div class="countdown-message">Your food is being prepared with care!</div>
                        </div>
                    `;
                }
                
                // Step 3: Show delivery countdown timer when active (for ready/dispatched)
                if (step.showDeliveryCountdown && isActive && ['ready', 'dispatched'].includes(order.order_status)) {
                    extraContent = `
                        <div class="countdown-timer" id="delivery-countdown-timer">
                            <div class="countdown-label">Estimated Delivery Time</div>
                            <div class="countdown-time" id="delivery-countdown-time">10:00</div>
                            <div class="countdown-message">Your rider is on the way!</div>
                        </div>
                    `;
                    
                    // Add rider animation below the countdown
                    if (step.showRiderAnimation) {
                        extraContent += `
                            <div class="rider-animation-container">
                                <div class="animated-rider">
                                    ${animatedRiderSVG}
                                </div>
                            </div>
                        `;
                    }
                }
                
                let timestampHtml = '';
                if (step.timestamp && (isCompleted || isActive)) {
                    timestampHtml = `
                        <div class="step-time">
                            ${stepIcons.clock}
                            ${formatTime(step.timestamp)}
                        </div>
                    `;
                }
                
                return `
                    <div class="tracking-step ${statusClass}">
                        <div class="step-indicator">
                            ${displayIcon}
                        </div>
                        <div class="step-content">
                            <div class="step-title">${step.title}</div>
                            <div class="step-description">${step.description}</div>
                            ${timestampHtml}
                            ${extraContent}
                        </div>
                    </div>
                `;
            }).join('');
            
            // ====== KDS SYNC: Start/restart timers when status changes ======
            // Always clear and restart timers to ensure they reflect latest KDS data
            // This ensures real-time sync when KDS updates order status
            
            // Start cooking countdown if in cooking state
            if (currentStep === 2 && order.order_status === 'cooking') {
                // Clear any existing countdown before starting
                if (countdownInterval) {
                    clearInterval(countdownInterval);
                    countdownInterval = null;
                }
                startCookingCountdown();
            } else if (countdownInterval) {
                clearInterval(countdownInterval);
                countdownInterval = null;
            }
            
            // Start delivery countdown if in ready/dispatched state
            if (currentStep === 3 && ['ready', 'dispatched'].includes(order.order_status)) {
                // Clear any existing countdown before starting
                if (deliveryCountdownInterval) {
                    clearInterval(deliveryCountdownInterval);
                    deliveryCountdownInterval = null;
                }
                startDeliveryCountdown();
            } else if (deliveryCountdownInterval) {
                clearInterval(deliveryCountdownInterval);
                deliveryCountdownInterval = null;
            }
            // ====== End KDS SYNC ======
        }
        
        // Helper function to parse server timestamps
        function parseServerTimestamp(timestamp) {
            if (!timestamp) return null;
            // If timestamp doesn't contain timezone info, treat as UTC
            const hasTimezone = /[Zz]|[+-]\d{2}:\d{2}$/.test(timestamp);
            const parsed = new Date(hasTimezone ? timestamp : timestamp + 'Z').getTime();
            return isNaN(parsed) ? null : parsed;
        }
        
        // Debug logging function for order timing validation
        // Enable by adding ?debug=time to the tracking URL
        function logOrderTimingDebugInfo(order, currentStep) {
            const serverNow = getServerUtcNow();
            const browserNow = Date.now();
            
            console.group('🕐 ZAIKON Tracking Timer Debug Info');
            console.log('=== Time Synchronization ===');
            console.log('Server UTC (last sync):', new Date(serverUtcTimeMs).toISOString());
            console.log('Client time (last sync):', new Date(clientSyncTimeMs).toISOString());
            console.log('Server-Client offset (ms):', serverClientTimeOffset);
            console.log('Current server UTC (calculated):', new Date(serverNow).toISOString());
            console.log('Current browser time:', new Date(browserNow).toISOString());
            
            console.log('\n=== Stored Timestamps (from DB, UTC) ===');
            console.log('Order status:', order.order_status);
            console.log('cooking_started_at (raw):', order.cooking_started_at || 'NULL');
            console.log('cooking_started_at (parsed ms):', cookingStartedAt);
            console.log('ready_at (raw):', order.ready_at || 'NULL');
            console.log('ready_at (parsed ms):', readyAt);
            console.log('dispatched_at (raw):', order.dispatched_at || 'NULL');
            console.log('dispatched_at (parsed ms):', dispatchedAt);
            
            console.log('\n=== Timer Calculations ===');
            if (currentStep === 2 && cookingStartedAt) {
                const elapsedMs = serverNow - cookingStartedAt;
                const elapsedMinutes = elapsedMs / 60000;
                const remainingMinutes = DEFAULT_COOKING_TIME_MINUTES - elapsedMinutes;
                console.log('Cooking Timer:');
                console.log('  Elapsed (minutes):', elapsedMinutes.toFixed(2));
                console.log('  Remaining (minutes):', remainingMinutes.toFixed(2));
                console.log('  Is Overtime:', elapsedMinutes > DEFAULT_COOKING_TIME_MINUTES);
            }
            
            if (currentStep === 3 && (dispatchedAt || readyAt)) {
                const deliveryStart = dispatchedAt || readyAt;
                const elapsedMs = serverNow - deliveryStart;
                const elapsedMinutes = elapsedMs / 60000;
                const remainingMinutes = DEFAULT_DELIVERY_TIME_MINUTES - elapsedMinutes;
                console.log('Delivery Timer:');
                console.log('  Start timestamp:', deliveryStart ? new Date(deliveryStart).toISOString() : 'NULL');
                console.log('  Elapsed (minutes):', elapsedMinutes.toFixed(2));
                console.log('  Remaining (minutes):', remainingMinutes.toFixed(2));
                console.log('  Is Overtime:', elapsedMinutes > DEFAULT_DELIVERY_TIME_MINUTES);
            }
            
            console.log('\n=== Step Detection ===');
            console.log('Current step:', currentStep);
            console.log('Step 1 (Confirmed): order_status in [pending, confirmed]');
            console.log('Step 2 (Preparing): order_status = cooking');
            console.log('Step 3 (On The Way): order_status in [ready, dispatched, delivered]');
            console.groupEnd();
        }
        
        // Helper function to calculate countdown timer state with overtime extension
        // Uses server-synchronized UTC time for accurate calculations across different timezones
        function calculateCountdownState(startTime, defaultTimeMinutes, extensionMinutes) {
            if (!startTime || isNaN(startTime)) {
                return { isValid: false };
            }
            
            // Use server-synchronized UTC time instead of browser's Date.now()
            // This ensures consistent timer behavior across all browser timezones
            const now = getServerUtcNow();
            const elapsedMs = now - startTime;
            const elapsedMinutes = elapsedMs / 60000;
            
            // Calculate current ETA with automatic overtime extension
            let currentEtaMinutes = defaultTimeMinutes;
            if (elapsedMinutes > defaultTimeMinutes) {
                const overtimeMinutes = elapsedMinutes - defaultTimeMinutes;
                const extensionsNeeded = Math.ceil(overtimeMinutes / extensionMinutes);
                currentEtaMinutes = defaultTimeMinutes + (extensionsNeeded * extensionMinutes);
            }
            
            const endTime = startTime + (currentEtaMinutes * 60 * 1000);
            const remainingMs = endTime - now;
            const isOvertime = elapsedMinutes > defaultTimeMinutes;
            
            let displayTime;
            if (!isOvertime && remainingMs > 0) {
                // Normal countdown
                const mins = Math.floor(remainingMs / 60000);
                const secs = Math.floor((remainingMs % 60000) / 1000);
                displayTime = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
            } else {
                // Overtime display
                const overtimeMs = Math.max(0, now - (startTime + (defaultTimeMinutes * 60 * 1000)));
                const mins = Math.floor(overtimeMs / 60000);
                const secs = Math.floor((overtimeMs % 60000) / 1000);
                displayTime = `+${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
            }
            
            return { isValid: true, displayTime, isOvertime };
        }
        
        // Cooking countdown timer logic (Step 2)
        function startCookingCountdown() {
            if (countdownInterval) {
                clearInterval(countdownInterval);
            }
            
            updateCookingCountdown();
            countdownInterval = setInterval(updateCookingCountdown, 1000);
        }
        
        function updateCookingCountdown() {
            const timerElement = document.getElementById('countdown-time');
            const containerElement = document.getElementById('countdown-timer');
            const messageElement = containerElement?.querySelector('.countdown-message');
            
            if (!timerElement || !containerElement) return;
            
            const state = calculateCountdownState(cookingStartedAt, DEFAULT_COOKING_TIME_MINUTES, OVERTIME_EXTENSION_MINUTES);
            
            if (!state.isValid) {
                timerElement.textContent = '20:00';
                return;
            }
            
            timerElement.textContent = state.displayTime;
            
            if (state.isOvertime) {
                containerElement.classList.add('overtime');
                if (messageElement) messageElement.textContent = 'Taking a bit longer. Almost ready!';
            } else {
                containerElement.classList.remove('overtime');
                if (messageElement) messageElement.textContent = 'Your food is being prepared with care!';
            }
        }
        
        // Delivery countdown timer logic (Step 3)
        function startDeliveryCountdown() {
            if (deliveryCountdownInterval) {
                clearInterval(deliveryCountdownInterval);
            }
            
            updateDeliveryCountdown();
            deliveryCountdownInterval = setInterval(updateDeliveryCountdown, 1000);
        }
        
        function updateDeliveryCountdown() {
            const timerElement = document.getElementById('delivery-countdown-time');
            const containerElement = document.getElementById('delivery-countdown-timer');
            const messageElement = containerElement?.querySelector('.countdown-message');
            
            if (!timerElement || !containerElement) return;
            
            // Use dispatched_at if available, otherwise use ready_at
            const deliveryStartTime = dispatchedAt || readyAt;
            
            const state = calculateCountdownState(deliveryStartTime, DEFAULT_DELIVERY_TIME_MINUTES, OVERTIME_EXTENSION_MINUTES);
            
            if (!state.isValid) {
                timerElement.textContent = '10:00';
                return;
            }
            
            timerElement.textContent = state.displayTime;
            
            if (state.isOvertime) {
                containerElement.classList.add('overtime');
                if (messageElement) messageElement.textContent = 'Rider delayed. Thank you for your patience!';
            } else {
                containerElement.classList.remove('overtime');
                if (messageElement) messageElement.textContent = 'Your rider is on the way!';
            }
        }
        
        // Render rider card (only in Step 3)
        function renderRiderCard(order, currentStep) {
            const riderCard = document.getElementById('rider-card');
            
            // Only show rider info in Step 3
            if (currentStep !== 3 || !order.rider_name) {
                riderCard.classList.remove('visible');
                return;
            }
            
            riderCard.classList.add('visible');
            
            const riderName = order.rider_name || 'Your Rider';
            const initials = riderName.split(' ').map(n => n[0] || '').join('').toUpperCase().slice(0, 2) || 'R';
            
            document.getElementById('rider-avatar').textContent = initials;
            document.getElementById('rider-name').textContent = riderName;
            document.getElementById('rider-phone-text').textContent = order.rider_phone || 'Contact not available';
            
            const callBtn = document.getElementById('call-rider-btn');
            if (order.rider_phone) {
                callBtn.href = 'tel:' + order.rider_phone;
                callBtn.style.display = 'flex';
            } else {
                callBtn.style.display = 'none';
            }
        }
        
        // Render order summary (bottom details) - NO customer phone
        function renderOrderSummary(order) {
            const summaryGrid = document.getElementById('summary-grid');
            
            let html = '';
            
            // 1. Customer Name
            if (order.customer_name) {
                html += `
                    <div class="summary-item">
                        <div class="summary-icon">
                            <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        </div>
                        <div class="summary-content">
                            <div class="summary-label">Customer Name</div>
                            <div class="summary-value">${escapeHtml(order.customer_name)}</div>
                        </div>
                    </div>
                `;
            }
            
            // 2. Delivery Location
            if (order.location_name) {
                html += `
                    <div class="summary-item">
                        <div class="summary-icon">
                            <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                        </div>
                        <div class="summary-content">
                            <div class="summary-label">Delivery Location</div>
                            <div class="summary-value">${escapeHtml(order.location_name)}</div>
                        </div>
                    </div>
                `;
            }
            
            // Note: Customer phone is intentionally NOT shown per requirements
            
            summaryGrid.innerHTML = html;
            
            // 3. Order Total
            const total = Math.round(parseFloat(order.grand_total_rs) || 0);
            document.getElementById('order-total').textContent = `Rs ${total.toLocaleString()}`;
        }
        
        // Show error
        function showError(message) {
            document.getElementById('loading-state').style.display = 'none';
            document.getElementById('error-state').style.display = 'block';
            document.getElementById('error-text').textContent = message;
        }
        
        // Format time
        function formatTime(timestamp) {
            if (!timestamp) return '';
            const date = new Date(timestamp);
            if (isNaN(date.getTime())) return '';
            return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
        }
        
        // Escape HTML
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        // Search functionality
        async function searchOrder(query) {
            const searchBtn = document.getElementById('search-btn');
            const searchError = document.getElementById('search-error');
            const originalText = searchBtn.textContent;
            
            searchError.style.display = 'none';
            searchBtn.textContent = 'Searching...';
            searchBtn.disabled = true;
            
            try {
                const apiUrl = `${apiBaseUrl}track/order/${encodeURIComponent(query.trim())}`;
                const response = await fetch(apiUrl);
                const data = await response.json();
                
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'No orders found');
                }
                
                if (data.tracking_url) {
                    window.location.href = data.tracking_url;
                } else if (data.order) {
                    currentOrderData = data;
                    document.getElementById('error-state').style.display = 'none';
                    renderOrderTracking(data);
                    startPolling();
                }
            } catch (error) {
                searchError.style.display = 'block';
                document.getElementById('search-error-text').textContent = error.message || 'No orders found.';
            } finally {
                searchBtn.textContent = originalText;
                searchBtn.disabled = false;
            }
        }
        
        // Polling - 5 seconds for real-time KDS sync
        function startPolling() {
            if (pollInterval) clearInterval(pollInterval);
            pollInterval = setInterval(fetchOrderData, POLL_INTERVAL_MS);
        }
        
        // Initialize
        if (trackingToken) {
            fetchOrderData();
            startPolling();
        } else {
            showError('Enter your order number below to track your order.');
        }
        
        // Event listeners
        document.getElementById('search-btn').addEventListener('click', function() {
            const query = document.getElementById('search-input').value.trim();
            if (query.length >= 3) {
                searchOrder(query);
            } else {
                document.getElementById('search-error').style.display = 'block';
                document.getElementById('search-error-text').textContent = 'Please enter at least 3 characters';
            }
        });
        
        document.getElementById('search-input').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('search-btn').click();
            }
        });
        
        // Cleanup
        window.addEventListener('beforeunload', () => {
            if (pollInterval) clearInterval(pollInterval);
            if (countdownInterval) clearInterval(countdownInterval);
            if (deliveryCountdownInterval) clearInterval(deliveryCountdownInterval);
        });
        
        // Visibility handling for polling and timers
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                if (pollInterval) {
                    clearInterval(pollInterval);
                    pollInterval = null;
                }
                if (countdownInterval) {
                    clearInterval(countdownInterval);
                    countdownInterval = null;
                }
                if (deliveryCountdownInterval) {
                    clearInterval(deliveryCountdownInterval);
                    deliveryCountdownInterval = null;
                }
            } else {
                if (trackingToken && !pollInterval) {
                    fetchOrderData();
                    startPolling();
                }
                // Restart timers based on current state
                if (currentOrderData) {
                    const currentStep = getTrackingStep(currentOrderData.order.order_status);
                    if (currentStep === 2 && currentOrderData.order.order_status === 'cooking') {
                        startCookingCountdown();
                    }
                    if (currentStep === 3 && ['ready', 'dispatched'].includes(currentOrderData.order.order_status)) {
                        startDeliveryCountdown();
                    }
                }
            }
        });
    </script>
</body>
</html>
