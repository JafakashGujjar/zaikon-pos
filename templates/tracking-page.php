<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#FF8A00">
    <title><?php echo esc_html(get_bloginfo('name')); ?> - Track Your Order</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #FF8A00;
            --primary-dark: #E07D00;
            --primary-light: #FFB347;
            --success: #22C55E;
            --success-light: #DCFCE7;
            --warning: #F59E0B;
            --info: #3B82F6;
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
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 50%, #FED7AA 100%);
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
        
        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: var(--white);
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
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
            animation: pulse 4s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        
        .header-content { position: relative; z-index: 1; }
        
        .header .brand-icon {
            width: 56px; height: 56px;
            background: rgba(255,255,255,0.2);
            border-radius: 16px;
            display: inline-flex;
            align-items: center; justify-content: center;
            margin-bottom: 12px;
            backdrop-filter: blur(10px);
        }
        
        .header .brand-icon svg { width: 32px; height: 32px; fill: var(--white); }
        
        .header h1 { font-size: 22px; font-weight: 700; margin-bottom: 6px; letter-spacing: -0.02em; }
        .header .order-number { font-size: 15px; opacity: 0.95; font-weight: 500; }
        
        .content { padding: 24px; }
        
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
        
        .search-input-group {
            display: flex;
            gap: 8px;
        }
        
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
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(255, 138, 0, 0.1);
        }
        
        .search-btn {
            padding: 12px 20px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: var(--white);
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
            box-shadow: var(--shadow-md);
        }
        
        .search-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .search-hint {
            font-size: 12px;
            color: var(--gray-500);
            text-align: center;
        }
        
        .search-results {
            margin-top: 16px;
        }
        
        .search-result-item {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 12px;
            cursor: pointer;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        
        .search-result-item:hover {
            border-color: var(--primary);
            box-shadow: var(--shadow-md);
        }
        
        .search-result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        
        .search-result-order { font-weight: 600; color: var(--gray-800); }
        
        .search-result-status {
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 500;
        }
        
        .search-result-status.pending { background: #FEF3C7; color: #D97706; }
        .search-result-status.confirmed { background: #DBEAFE; color: #2563EB; }
        .search-result-status.cooking { background: #FEE2E2; color: #DC2626; }
        .search-result-status.ready { background: #D1FAE5; color: #059669; }
        .search-result-status.dispatched { background: #E0E7FF; color: #4338CA; }
        .search-result-status.delivered { background: #D1FAE5; color: #047857; }
        
        .search-result-details {
            font-size: 13px;
            color: var(--gray-500);
            display: flex;
            justify-content: space-between;
        }
        
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
            border-top-color: var(--primary);
            animation: spin 1s linear infinite;
        }
        
        .loading-spinner::after {
            border-bottom-color: var(--primary-light);
            animation: spin 1s linear infinite reverse;
            animation-delay: -0.5s;
        }
        
        @keyframes spin { 100% { transform: rotate(360deg); } }
        
        .loading p { color: var(--gray-500); font-size: 15px; font-weight: 500; }
        
        .current-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: var(--white);
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 24px;
            animation: bounceIn 0.5s ease-out;
        }
        
        @keyframes bounceIn {
            0% { transform: scale(0.8); opacity: 0; }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); opacity: 1; }
        }
        
        .current-status-badge .pulse-dot {
            width: 8px; height: 8px;
            background: var(--white);
            border-radius: 50%;
            animation: pulseDot 1.5s ease-in-out infinite;
        }
        
        @keyframes pulseDot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(1.3); }
        }
        
        .status-timeline { position: relative; padding-left: 32px; margin: 24px 0; }
        
        .status-timeline::before {
            content: '';
            position: absolute;
            left: 15px; top: 8px; bottom: 8px;
            width: 2px;
            background: var(--gray-200);
            border-radius: 2px;
        }
        
        .status-step {
            position: relative;
            padding-bottom: 28px;
            opacity: 0;
            animation: fadeInStep 0.4s ease-out forwards;
        }
        
        .status-step:nth-child(1) { animation-delay: 0.1s; }
        .status-step:nth-child(2) { animation-delay: 0.2s; }
        .status-step:nth-child(3) { animation-delay: 0.3s; }
        .status-step:nth-child(4) { animation-delay: 0.4s; }
        .status-step:nth-child(5) { animation-delay: 0.5s; }
        .status-step:nth-child(6) { animation-delay: 0.6s; }
        
        @keyframes fadeInStep {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .status-step:last-child { padding-bottom: 0; }
        
        .status-step.completed::before {
            content: '';
            position: absolute;
            left: -17px; top: 32px;
            width: 2px;
            height: calc(100% - 24px);
            background: var(--success);
            border-radius: 2px;
        }
        
        .status-step.active::before {
            content: '';
            position: absolute;
            left: -17px; top: 32px;
            width: 2px;
            height: calc(100% - 24px);
            background: linear-gradient(to bottom, var(--primary), var(--gray-200));
            border-radius: 2px;
        }
        
        .status-step:last-child::before { display: none; }
        
        .status-icon {
            position: absolute;
            left: -32px; top: 0;
            width: 32px; height: 32px;
            border-radius: 50%;
            background: var(--gray-100);
            border: 2px solid var(--gray-200);
            display: flex;
            align-items: center; justify-content: center;
            transition: all 0.3s ease;
        }
        
        .status-icon svg { width: 16px; height: 16px; fill: var(--gray-400); transition: fill 0.3s ease; }
        
        .status-step.completed .status-icon {
            background: var(--success);
            border-color: var(--success);
            box-shadow: 0 0 0 4px var(--success-light);
        }
        
        .status-step.completed .status-icon svg { fill: var(--white); }
        
        .status-step.active .status-icon {
            background: var(--primary);
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(255, 138, 0, 0.2);
            animation: pulseIcon 2s ease-in-out infinite;
        }
        
        @keyframes pulseIcon {
            0%, 100% { box-shadow: 0 0 0 4px rgba(255, 138, 0, 0.2); }
            50% { box-shadow: 0 0 0 8px rgba(255, 138, 0, 0.1); }
        }
        
        .status-step.active .status-icon svg { fill: var(--white); }
        
        .status-content { padding-left: 16px; }
        
        .status-title { font-weight: 600; font-size: 15px; color: var(--gray-800); margin-bottom: 2px; }
        .status-step.pending .status-title { color: var(--gray-400); }
        
        .status-description { font-size: 13px; color: var(--gray-500); }
        .status-step.pending .status-description { color: var(--gray-400); }
        
        .status-time {
            font-size: 12px;
            color: var(--gray-400);
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .status-time svg { width: 12px; height: 12px; fill: var(--gray-400); }
        
        .eta-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: var(--white);
            padding: 24px;
            border-radius: 16px;
            margin: 24px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .eta-content { position: relative; z-index: 1; }
        
        .eta-icon {
            width: 48px; height: 48px;
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            display: inline-flex;
            align-items: center; justify-content: center;
            margin-bottom: 12px;
        }
        
        .eta-icon svg { width: 28px; height: 28px; fill: var(--white); }
        
        .eta-timer {
            font-size: 48px;
            font-weight: 800;
            margin: 8px 0;
            letter-spacing: -0.02em;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .eta-label { font-size: 13px; text-transform: uppercase; letter-spacing: 0.1em; opacity: 0.9; font-weight: 600; }
        .eta-message { margin-top: 16px; font-size: 14px; opacity: 0.95; font-weight: 500; }
        
        .rider-card {
            background: linear-gradient(135deg, #EEF2FF 0%, #E0E7FF 100%);
            border: 1px solid #C7D2FE;
            border-radius: 16px;
            padding: 20px;
            margin: 24px 0;
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
        .rider-vehicle { font-size: 12px; color: var(--gray-400); margin-top: 4px; }
        
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
        
        .customer-card {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 16px;
            padding: 20px;
            margin: 24px 0;
        }
        
        .customer-header {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 16px;
            color: var(--gray-700);
        }
        
        .customer-header svg { width: 18px; height: 18px; fill: var(--gray-500); }
        
        .customer-grid { display: grid; gap: 16px; }
        
        .customer-field { display: flex; align-items: flex-start; gap: 12px; }
        
        .customer-field-icon {
            width: 36px; height: 36px;
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 10px;
            display: flex;
            align-items: center; justify-content: center;
            flex-shrink: 0;
        }
        
        .customer-field-icon svg { width: 16px; height: 16px; fill: var(--gray-500); }
        .customer-field-content { flex: 1; }
        .customer-label { font-size: 11px; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600; margin-bottom: 2px; }
        .customer-value { font-size: 14px; color: var(--gray-800); font-weight: 500; }
        
        .order-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: 16px;
            overflow: hidden;
            margin: 24px 0;
        }
        
        .order-header {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            font-size: 14px;
            padding: 16px 20px;
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            color: var(--gray-700);
        }
        
        .order-header svg { width: 18px; height: 18px; fill: var(--gray-500); }
        
        .order-items-list { padding: 12px 20px; }
        
        .order-item {
            padding: 12px 0;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .order-item:last-child { border-bottom: none; }
        
        .item-info { flex: 1; }
        .item-name { font-weight: 500; color: var(--gray-800); font-size: 14px; margin-bottom: 2px; }
        .item-quantity { font-size: 13px; color: var(--gray-500); }
        .item-price { font-weight: 600; color: var(--gray-800); font-size: 14px; }
        
        .order-totals {
            background: var(--gray-50);
            padding: 16px 20px;
            border-top: 1px solid var(--gray-200);
        }
        
        .total-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; color: var(--gray-600); }
        
        .total-row.grand {
            padding-top: 12px;
            margin-top: 8px;
            border-top: 2px solid var(--gray-200);
            font-size: 16px;
            font-weight: 700;
            color: var(--gray-900);
        }
        
        .footer {
            text-align: center;
            padding: 24px;
            background: var(--gray-50);
            border-top: 1px solid var(--gray-100);
        }
        
        .footer-message { font-size: 15px; font-weight: 600; color: var(--gray-700); margin-bottom: 8px; }
        .footer-sub { font-size: 13px; color: var(--gray-500); }
        .powered-by { margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--gray-200); font-size: 11px; color: var(--gray-400); text-transform: uppercase; letter-spacing: 0.1em; }
        
        @media (max-width: 480px) {
            body { padding: 0; background: var(--gray-100); }
            .container { border-radius: 0; min-height: 100vh; }
            .eta-timer { font-size: 40px; }
            .header { padding: 24px 20px; }
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
            <div id="loading-state" class="loading">
                <div class="loading-spinner"></div>
                <p>Loading your order...</p>
            </div>
            
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
                            <input type="text" id="search-input" class="search-input" placeholder="Enter order number or phone number" autocomplete="off" />
                            <button type="button" id="search-btn" class="search-btn">Search</button>
                        </div>
                        <div class="search-hint">Example: ORD-2026001 or 03001234567</div>
                    </div>
                    <div id="search-results" class="search-results" style="display: none;"></div>
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
            
            <div id="order-tracking" style="display: none;">
                <div style="text-align: center;">
                    <div class="current-status-badge" id="current-status-badge">
                        <span class="pulse-dot"></span>
                        <span id="current-status-text">Loading...</span>
                    </div>
                </div>
                
                <div id="eta-card" class="eta-card" style="display: none;">
                    <div class="eta-content">
                        <div class="eta-icon" id="eta-icon"></div>
                        <div class="eta-label" id="eta-label">Estimated Time</div>
                        <div class="eta-timer" id="eta-timer">--:--</div>
                        <div class="eta-message" id="eta-message"></div>
                    </div>
                </div>
                
                <div class="status-timeline" id="status-timeline"></div>
                
                <div id="rider-card" class="rider-card" style="display: none;">
                    <div class="rider-header">
                        <svg viewBox="0 0 24 24"><path d="M19.15 8a2 2 0 0 0-1.72-1H15V5a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v10a2 2 0 0 0 2 2h1a3 3 0 0 0 6 0h2a3 3 0 0 0 6 0h2V11a4 4 0 0 0-.85-3zM9 18a1 1 0 1 1 1-1 1 1 0 0 1-1 1zm8 0a1 1 0 1 1 1-1 1 1 0 0 1-1 1zm-.18-6H15V9h2.43l1.8 3z"/></svg>
                        Your Delivery Rider
                    </div>
                    <div class="rider-details">
                        <div class="rider-avatar" id="rider-avatar"></div>
                        <div class="rider-info">
                            <div class="rider-name" id="rider-name"></div>
                            <div class="rider-phone" id="rider-phone-display">
                                <svg viewBox="0 0 24 24"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
                                <span id="rider-phone-text"></span>
                            </div>
                            <div class="rider-vehicle" id="rider-vehicle"></div>
                        </div>
                        <a href="#" id="call-rider-btn" class="call-rider-btn" style="display: none;">
                            <svg viewBox="0 0 24 24"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
                        </a>
                    </div>
                </div>
                
                <div class="customer-card" id="customer-card">
                    <div class="customer-header">
                        <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        Delivery Details
                    </div>
                    <div class="customer-grid" id="customer-grid"></div>
                </div>
                
                <div class="order-card">
                    <div class="order-header">
                        <svg viewBox="0 0 24 24"><path d="M18 6h-2c0-2.21-1.79-4-4-4S8 3.79 8 6H6c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-6-2c1.1 0 2 .9 2 2h-4c0-1.1.9-2 2-2zm6 16H6V8h12v12z"/></svg>
                        Order Items
                    </div>
                    <div class="order-items-list" id="order-items"></div>
                    <div class="order-totals" id="order-totals"></div>
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
        const rawToken = '<?php echo esc_js(get_query_var("zaikon_tracking_token")); ?>';
        // Token validation: allow hex tokens of 16-64 characters (current tokens are 32 chars)
        const trackingToken = /^[a-f0-9]{16,64}$/.test(rawToken) ? rawToken : null;
        const apiBaseUrl = '<?php echo esc_js(rest_url("zaikon/v1/")); ?>';
        
        let currentOrderData = null;
        let pollInterval = null;
        
        const statusIcons = {
            'pending': '<svg viewBox="0 0 24 24"><path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>',
            'confirmed': '<svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>',
            'cooking': '<svg viewBox="0 0 24 24"><path d="M8.1 13.34l2.83-2.83L3.91 3.5c-1.56 1.56-1.56 4.09 0 5.66l4.19 4.18zm6.78-1.81c1.53.71 3.68.21 5.27-1.38 1.91-1.91 2.28-4.65.81-6.12-1.46-1.46-4.2-1.1-6.12.81-1.59 1.59-2.09 3.74-1.38 5.27L3.7 19.87l1.41 1.41L12 14.41l6.88 6.88 1.41-1.41L13.41 13l1.47-1.47z"/></svg>',
            'ready': '<svg viewBox="0 0 24 24"><path d="M18 7l-1.41-1.41-6.34 6.34 1.41 1.41L18 7zm4.24-1.41L11.66 16.17 7.48 12l-1.41 1.41L11.66 19l12-12-1.42-1.41zM.41 13.41L6 19l1.41-1.41L1.83 12 .41 13.41z"/></svg>',
            'dispatched': '<svg viewBox="0 0 24 24"><path d="M19.15 8a2 2 0 0 0-1.72-1H15V5a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1v10a2 2 0 0 0 2 2h1a3 3 0 0 0 6 0h2a3 3 0 0 0 6 0h2V11a4 4 0 0 0-.85-3zM9 18a1 1 0 1 1 1-1 1 1 0 0 1-1 1zm8 0a1 1 0 1 1 1-1 1 1 0 0 1-1 1zm-.18-6H15V9h2.43l1.8 3z"/></svg>',
            'delivered': '<svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>'
        };
        
        const statusConfig = {
            'pending': { title: 'Order Received', description: 'We have received your order' },
            'confirmed': { title: 'Order Confirmed', description: 'Your order has been confirmed by our kitchen' },
            'cooking': { title: 'Preparing', description: 'Our chefs are preparing your delicious food' },
            'ready': { title: 'Ready for Pickup', description: 'Your order is packed and ready' },
            'dispatched': { title: 'On the Way', description: 'Your rider is on the way with your order' },
            'delivered': { title: 'Delivered', description: 'Your order has been delivered. Enjoy!' }
        };
        
        const statusOrder = ['pending', 'confirmed', 'cooking', 'ready', 'dispatched', 'delivered'];
        
        async function fetchOrderData() {
            console.log('ZAIKON TRACKING: Fetching order with token:', trackingToken ? trackingToken.substring(0, 8) + '...' : 'NULL');
            console.log('ZAIKON TRACKING: API URL:', apiBaseUrl + 'track/' + trackingToken);
            
            try {
                const response = await fetch(`${apiBaseUrl}track/${trackingToken}`);
                console.log('ZAIKON TRACKING: Response status:', response.status);
                
                const data = await response.json();
                console.log('ZAIKON TRACKING: Response data:', data);
                
                if (!response.ok || !data.success) {
                    // Provide specific error messages based on response status
                    console.error('ZAIKON TRACKING: Order lookup failed. Status:', response.status, 'Data:', data);
                    if (response.status === 404) {
                        throw new Error('Order not found. The tracking link may have expired or the order number is incorrect.');
                    } else if (response.status === 400) {
                        throw new Error('Invalid tracking link. Please check your URL and try again.');
                    } else {
                        throw new Error(data.message || 'Unable to load order details. Please try again later.');
                    }
                }
                
                console.log('ZAIKON TRACKING: Order found successfully:', data.order?.order_number);
                currentOrderData = data;
                renderOrderTracking(data);
                
            } catch (error) {
                console.error('ZAIKON TRACKING: Error fetching order:', error);
                // Check if it's a network error vs API error
                if (error.name === 'TypeError' && error.message.includes('fetch')) {
                    showError('Unable to connect to the server. Please check your internet connection.');
                } else {
                    showError(error.message || 'Unable to load order. Please check your tracking link.');
                }
            }
        }
        
        function renderOrderTracking(data) {
            const order = data.order;
            const eta = data.eta;
            
            document.getElementById('loading-state').style.display = 'none';
            document.getElementById('order-tracking').style.display = 'block';
            
            document.getElementById('order-number-header').textContent = `Order #${order.order_number}`;
            
            const currentStatus = order.order_status;
            const statusText = statusConfig[currentStatus]?.title || 'Processing';
            document.getElementById('current-status-text').textContent = statusText;
            
            renderStatusTimeline(order);
            renderETA(order, eta);
            
            if ((order.order_status === 'dispatched' || order.order_status === 'ready') && order.rider_name) {
                renderRiderInfo(order);
            } else {
                document.getElementById('rider-card').style.display = 'none';
            }
            
            renderCustomerInfo(order);
            renderOrderItems(order);
            
            const finalStates = ['delivered', 'cancelled'];
            if (finalStates.includes(order.order_status) && pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
                const badge = document.getElementById('current-status-badge');
                const pulseDot = badge.querySelector('.pulse-dot');
                if (pulseDot) pulseDot.style.display = 'none';
            }
        }
        
        function renderStatusTimeline(order) {
            const timeline = document.getElementById('status-timeline');
            const currentStatus = order.order_status;
            const currentStatusIndex = statusOrder.indexOf(currentStatus);
            
            timeline.innerHTML = statusOrder.map((status, index) => {
                const config = statusConfig[status];
                const icon = statusIcons[status];
                const isCompleted = index < currentStatusIndex;
                const isActive = index === currentStatusIndex;
                
                let statusClass = '';
                if (isCompleted) statusClass = 'completed';
                else if (isActive) statusClass = 'active';
                else statusClass = 'pending';
                
                let timestamp = '';
                if (status === 'pending' && order.created_at) {
                    timestamp = formatTime(order.created_at);
                } else if (status === 'confirmed' && order.confirmed_at) {
                    timestamp = formatTime(order.confirmed_at);
                } else if (status === 'cooking' && order.cooking_started_at) {
                    timestamp = formatTime(order.cooking_started_at);
                } else if (status === 'ready' && order.ready_at) {
                    timestamp = formatTime(order.ready_at);
                } else if (status === 'dispatched' && order.dispatched_at) {
                    timestamp = formatTime(order.dispatched_at);
                } else if (status === 'delivered' && order.delivered_at) {
                    timestamp = formatTime(order.delivered_at);
                }
                
                return `
                    <div class="status-step ${statusClass}">
                        <div class="status-icon">${icon}</div>
                        <div class="status-content">
                            <div class="status-title">${config.title}</div>
                            <div class="status-description">${config.description}</div>
                            ${timestamp ? `
                                <div class="status-time">
                                    <svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zM12 20c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/></svg>
                                    ${timestamp}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            }).join('');
        }
        
        function renderETA(order, eta) {
            const etaCard = document.getElementById('eta-card');
            const status = order.order_status;
            
            if (status === 'cooking' && eta && eta.cooking_eta_remaining !== null) {
                etaCard.style.display = 'block';
                document.getElementById('eta-icon').innerHTML = statusIcons.cooking;
                document.getElementById('eta-label').textContent = 'Cooking Time Remaining';
                updateETATimer(eta.cooking_eta_remaining);
                document.getElementById('eta-message').textContent = 'Your delicious food is being prepared with care!';
            } else if (status === 'dispatched' && eta && eta.delivery_eta_remaining !== null) {
                etaCard.style.display = 'block';
                document.getElementById('eta-icon').innerHTML = statusIcons.dispatched;
                document.getElementById('eta-label').textContent = 'Delivery Time Remaining';
                updateETATimer(eta.delivery_eta_remaining);
                
                if (eta.delivery_eta_remaining <= 5) {
                    document.getElementById('eta-message').textContent = 'Almost there! Your order will arrive very soon!';
                } else {
                    document.getElementById('eta-message').textContent = 'Your rider is on the way with your order!';
                }
            } else {
                etaCard.style.display = 'none';
            }
        }
        
        function updateETATimer(minutes) {
            if (minutes === null || minutes === undefined) {
                document.getElementById('eta-timer').textContent = '--:--';
                return;
            }
            const mins = Math.floor(minutes);
            const secs = Math.floor((minutes - mins) * 60);
            document.getElementById('eta-timer').textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
        }
        
        function renderRiderInfo(order) {
            const riderCard = document.getElementById('rider-card');
            riderCard.style.display = 'block';
            
            // Safely extract initials with null check
            const riderName = order.rider_name || 'Unknown';
            const initials = riderName.split(' ').map(n => n[0] || '').join('').toUpperCase().slice(0, 2) || 'R';
            document.getElementById('rider-avatar').textContent = initials;
            document.getElementById('rider-name').textContent = riderName;
            document.getElementById('rider-phone-text').textContent = order.rider_phone || 'Not available';
            
            if (order.rider_vehicle) {
                document.getElementById('rider-vehicle').textContent = 'Vehicle: ' + order.rider_vehicle;
                document.getElementById('rider-vehicle').style.display = 'block';
            } else {
                document.getElementById('rider-vehicle').style.display = 'none';
            }
            
            if (order.rider_phone) {
                const callBtn = document.getElementById('call-rider-btn');
                callBtn.style.display = 'flex';
                callBtn.href = 'tel:' + order.rider_phone;
            }
        }
        
        function renderCustomerInfo(order) {
            const customerGrid = document.getElementById('customer-grid');
            
            let html = '';
            
            if (order.customer_name) {
                html += `
                    <div class="customer-field">
                        <div class="customer-field-icon">
                            <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        </div>
                        <div class="customer-field-content">
                            <div class="customer-label">Customer</div>
                            <div class="customer-value">${escapeHtml(order.customer_name)}</div>
                        </div>
                    </div>
                `;
            }
            
            if (order.customer_phone) {
                html += `
                    <div class="customer-field">
                        <div class="customer-field-icon">
                            <svg viewBox="0 0 24 24"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
                        </div>
                        <div class="customer-field-content">
                            <div class="customer-label">Phone</div>
                            <div class="customer-value">${escapeHtml(order.customer_phone)}</div>
                        </div>
                    </div>
                `;
            }
            
            if (order.location_name) {
                html += `
                    <div class="customer-field">
                        <div class="customer-field-icon">
                            <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
                        </div>
                        <div class="customer-field-content">
                            <div class="customer-label">Delivery Location</div>
                            <div class="customer-value">${escapeHtml(order.location_name)}</div>
                        </div>
                    </div>
                `;
            }
            
            if (order.special_instruction) {
                html += `
                    <div class="customer-field">
                        <div class="customer-field-icon">
                            <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-1.99.9-1.99 2L2 22l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-7 12h-2v-2h2v2zm0-4h-2V6h2v4z"/></svg>
                        </div>
                        <div class="customer-field-content">
                            <div class="customer-label">Special Instructions</div>
                            <div class="customer-value">${escapeHtml(order.special_instruction)}</div>
                        </div>
                    </div>
                `;
            }
            
            if (!html) {
                document.getElementById('customer-card').style.display = 'none';
            } else {
                document.getElementById('customer-card').style.display = 'block';
                customerGrid.innerHTML = html;
            }
        }
        
        function renderOrderItems(order) {
            const itemsContainer = document.getElementById('order-items');
            const totalsContainer = document.getElementById('order-totals');
            
            let itemsHTML = '';
            if (order.items && order.items.length > 0) {
                itemsHTML = order.items.map(item => `
                    <div class="order-item">
                        <div class="item-info">
                            <div class="item-name">${escapeHtml(item.product_name)}</div>
                            <div class="item-quantity">Qty: ${item.qty}</div>
                        </div>
                        <div class="item-price">Rs ${Math.round(parseFloat(item.line_total_rs) || 0)}</div>
                    </div>
                `).join('');
            } else {
                itemsHTML = '<div class="order-item"><div class="item-info"><div class="item-name" style="color: var(--gray-400);">No items found</div></div></div>';
            }
            
            itemsContainer.innerHTML = itemsHTML;
            
            let totalsHTML = `
                <div class="total-row">
                    <span>Subtotal</span>
                    <span>Rs ${Math.round(parseFloat(order.items_subtotal_rs) || 0)}</span>
                </div>
            `;
            
            const deliveryCharge = parseFloat(order.delivery_charges_rs || order.order_delivery_charges_rs || 0);
            if (deliveryCharge > 0) {
                totalsHTML += `
                    <div class="total-row">
                        <span>Delivery Fee</span>
                        <span>Rs ${Math.round(deliveryCharge)}</span>
                    </div>
                `;
            }
            
            const discount = parseFloat(order.discounts_rs || 0);
            if (discount > 0) {
                totalsHTML += `
                    <div class="total-row" style="color: var(--success);">
                        <span>Discount</span>
                        <span>-Rs ${Math.round(discount)}</span>
                    </div>
                `;
            }
            
            totalsHTML += `
                <div class="total-row grand">
                    <span>Total</span>
                    <span>Rs ${Math.round(parseFloat(order.grand_total_rs) || 0)}</span>
                </div>
            `;
            
            totalsContainer.innerHTML = totalsHTML;
        }
        
        function showError(message) {
            document.getElementById('loading-state').style.display = 'none';
            document.getElementById('error-state').style.display = 'block';
            document.getElementById('error-text').textContent = message;
        }
        
        // Search functionality
        async function searchOrder(query) {
            const searchBtn = document.getElementById('search-btn');
            const searchResults = document.getElementById('search-results');
            const searchError = document.getElementById('search-error');
            const originalText = searchBtn.textContent;
            
            // Reset states
            searchResults.style.display = 'none';
            searchError.style.display = 'none';
            searchBtn.textContent = 'Searching...';
            searchBtn.disabled = true;
            
            try {
                // Determine if input is phone number or order number
                // Phone number regex requires at least one digit
                const isPhone = /^[\+]?[\d\s\-]*\d[\d\s\-]*$/.test(query.trim()) && query.replace(/[\s\-\+]/g, '').length >= 7;
                const apiUrl = isPhone 
                    ? `${apiBaseUrl}track/phone/${encodeURIComponent(query.trim())}`
                    : `${apiBaseUrl}track/order/${encodeURIComponent(query.trim())}`;
                
                const response = await fetch(apiUrl);
                const data = await response.json();
                
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'No orders found');
                }
                
                if (isPhone && data.orders) {
                    // Multiple orders from phone search
                    renderSearchResults(data.orders);
                } else if (data.order) {
                    // Single order from order number search - redirect to tracking
                    if (data.tracking_url) {
                        window.location.href = data.tracking_url;
                    } else {
                        // Render the order directly
                        currentOrderData = data;
                        document.getElementById('error-state').style.display = 'none';
                        renderOrderTracking(data);
                        startPolling();
                    }
                }
            } catch (error) {
                console.error('Search error:', error);
                searchError.style.display = 'block';
                document.getElementById('search-error-text').textContent = error.message || 'No orders found. Please check your search and try again.';
            } finally {
                searchBtn.textContent = originalText;
                searchBtn.disabled = false;
            }
        }
        
        function renderSearchResults(orders) {
            const container = document.getElementById('search-results');
            
            if (!orders || orders.length === 0) {
                container.style.display = 'none';
                return;
            }
            
            const statusLabels = {
                'pending': 'Pending',
                'confirmed': 'Confirmed',
                'cooking': 'Preparing',
                'ready': 'Ready',
                'dispatched': 'On the Way',
                'delivered': 'Delivered'
            };
            
            container.innerHTML = orders.map(order => `
                <div class="search-result-item" onclick="window.location.href='${escapeHtml(order.tracking_url)}'">
                    <div class="search-result-header">
                        <span class="search-result-order">${escapeHtml(order.order_number)}</span>
                        <span class="search-result-status ${order.order_status}">${statusLabels[order.order_status] || order.order_status}</span>
                    </div>
                    <div class="search-result-details">
                        <span>${order.items_count || 0} items • Rs ${Math.round(parseFloat(order.grand_total_rs) || 0)}</span>
                        <span>${formatDate(order.created_at)}</span>
                    </div>
                </div>
            `).join('');
            
            container.style.display = 'block';
        }
        
        function formatDate(timestamp) {
            if (!timestamp) return '';
            const date = new Date(timestamp);
            if (isNaN(date.getTime())) return '';
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
        }
        
        function formatTime(timestamp) {
            if (!timestamp) return '';
            const date = new Date(timestamp);
            if (isNaN(date.getTime())) return '';
            return date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function startPolling() {
            pollInterval = setInterval(async () => {
                try {
                    await fetchOrderData();
                } catch (error) {
                    console.error('Polling error:', error);
                }
            }, 15000);
        }
        
        if (trackingToken) {
            fetchOrderData();
            startPolling();
        } else {
            showError('Enter your order number or phone number below to track your order.');
        }
        
        // Search event listeners
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
        
        window.addEventListener('beforeunload', () => {
            if (pollInterval) {
                clearInterval(pollInterval);
            }
        });
        
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                if (pollInterval) {
                    clearInterval(pollInterval);
                    pollInterval = null;
                }
            } else {
                if (!pollInterval && trackingToken) {
                    fetchOrderData();
                    startPolling();
                }
            }
        });
    </script>
</body>
</html>