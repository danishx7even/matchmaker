<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matchmaking Member Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+SC:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #CC723F;
            --primary-hover: #b6602f;
            --secondary: #F8F2ED;
            --text-dark: #000000;
            --text-muted: rgba(0, 0, 0, 0.6);
            --text-light: rgba(0, 0, 0, 0.4);
            --sec-accent: #829067;
            --forest-green: #144D34;
            --white: #FFFFFF;
            --border-light: #EFECE6;
            --card-gray: #F4F6F8;
            --danger-bg: #FEE8E8;
            --danger-icon: #D93025;
            --danger-border: #D93025;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--secondary);
            color: var(--text-dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 20px;
        }

        /* Typography */
        .font-cormorant, h1, h2, h3, .nav-tab, .section-heading, .tag-heading {
            font-family: 'Cormorant SC', serif;
            letter-spacing: 0.8px;
            font-weight: 600;
        }

        /* Main Outer Container */
        .portal-canvas {
            width: 100%;
            max-width: 1100px;
            background: var(--white);
            border-radius: 36px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            position: relative;
            min-height: 720px;
            display: flex;
            flex-direction: column;
        }

        /* View Step States */
        .view-state {
            display: none;
            flex: 1;
        }

        .view-state.active {
            display: flex;
            flex-direction: column;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            text-decoration: none;
            font-family: 'Inter', sans-serif;
            padding: 13px 28px;
        }

        .btn-primary {
            background-color: var(--primary);
            color: var(--white);
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
        }

        .btn-outline-dark {
            background: transparent;
            border: 1.5px solid #2B4E3F;
            color: #2B4E3F;
            font-weight: 600;
        }

        .btn-outline-danger {
            background: transparent;
            border: 1.5px solid var(--danger-border);
            color: var(--danger-border);
            font-weight: 600;
        }

        /* ----------------------------------------------------
           1. TOP HEADER NAVIGATION (IMAGE 1)
        ---------------------------------------------------- */
        .portal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 36px 48px 20px;
        }

        .nav-tabs {
            display: flex;
            gap: 32px;
        }

        .nav-tab {
            text-decoration: none;
            color: var(--text-muted);
            font-size: 16px;
            padding-bottom: 6px;
            text-transform: uppercase;
        }

        .nav-tab.active {
            color: var(--text-dark);
            border-bottom: 2px solid var(--text-dark);
            font-weight: 700;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .header-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        /* ----------------------------------------------------
           2. DASHBOARD DISCOVERY (IMAGE 1)
        ---------------------------------------------------- */
        .dashboard-body {
            display: grid;
            grid-template-columns: 220px 1fr;
            padding: 20px 48px 48px;
            gap: 40px;
            flex: 1;
        }

        .sidebar-panel {
            border-right: 1px solid var(--border-light);
            padding-right: 28px;
            display: flex;
            flex-direction: column;
        }

        .user-identity {
            text-align: center;
            margin-bottom: 32px;
        }

        .user-avatar-frame {
            width: 86px;
            height: 86px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 12px;
        }

        .user-avatar-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .user-title {
            font-family: 'Cormorant SC', serif;
            font-size: 16px;
            line-height: 1.3;
            text-transform: uppercase;
        }

        .side-nav {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .side-nav-item {
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
            color: var(--text-dark);
            font-family: 'Cormorant SC', serif;
            font-size: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .side-nav-item svg {
            width: 18px;
            height: 18px;
            stroke: currentColor;
        }

        .main-discovery-content {
            padding-left: 10px;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #FDFBF8;
            border: 1px solid #EFEAE2;
            padding: 6px 14px;
            border-radius: 20px;
            font-family: 'Cormorant SC', serif;
            font-size: 13px;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .discovery-title {
            font-size: 28px;
            font-weight: 500;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .discovery-subtitle {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 24px;
            max-width: 620px;
            line-height: 1.5;
        }

        .new-match-card {
            background: #F8F9FA;
            border-radius: 20px;
            padding: 28px;
            display: grid;
            grid-template-columns: 1fr 280px;
            gap: 32px;
        }

        .candidate-hero-block {
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }

        .candidate-square-thumb {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }

        .candidate-meta h2 {
            font-size: 24px;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .candidate-meta .meta-loc {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 2px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .candidate-meta .meta-lang {
            font-size: 13px;
            color: var(--text-muted);
        }

        .candidate-quote {
            margin-top: 18px;
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        .candidate-tags-row {
            margin-top: 20px;
            display: flex;
            gap: 24px;
            font-family: 'Cormorant SC', serif;
            font-size: 14px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .action-column {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .action-column h3 {
            font-size: 14px;
            text-transform: uppercase;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            margin-bottom: 6px;
        }

        .action-column p {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.5;
            margin-bottom: 14px;
        }

        .timer-card {
            background: var(--white);
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }

        .timer-card .timer-title {
            font-family: 'Cormorant SC', serif;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .timer-card .timer-val {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* ----------------------------------------------------
           3. FULL PROFILE VIEW (IMAGE 2)
        ---------------------------------------------------- */
        .profile-view-body {
            padding: 40px 48px 100px;
            overflow-y: auto;
        }

        .profile-view-title {
            font-size: 34px;
            font-weight: 500;
            text-transform: uppercase;
            margin-bottom: 32px;
        }

        .profile-content-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 48px;
        }

        .photo-sidebar {
            display: flex;
            flex-direction: column;
        }

        .main-photo-frame {
            position: relative;
            width: 100%;
            height: 380px;
            border-radius: 18px;
            overflow: hidden;
            margin-bottom: 18px;
        }

        .main-photo-frame img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-verified-tag {
            position: absolute;
            top: 14px;
            left: 14px;
            background: #D5763D;
            color: var(--white);
            font-size: 11px;
            font-weight: 500;
            padding: 4px 10px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .person-meta h2 {
            font-size: 26px;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .person-meta .loc-line {
            font-size: 14px;
            color: var(--text-muted);
            margin-bottom: 14px;
        }

        .person-details-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 13px;
            color: rgba(0, 0, 0, 0.7);
        }

        .person-details-list div {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .details-stream {
            display: flex;
            flex-direction: column;
            gap: 28px;
        }

        .about-quote-card {
            background: #FAF9F7;
            border: 1px solid var(--border-light);
            border-radius: 14px;
            padding: 24px 28px;
            position: relative;
        }

        .about-quote-card .about-title {
            font-family: 'Cormorant SC', serif;
            font-size: 18px;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .about-quote-card p {
            font-size: 13.5px;
            line-height: 1.7;
            color: var(--text-muted);
        }

        .section-header-title {
            font-size: 17px;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .background-tiles-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
        }

        .bg-tile {
            background: #F4F6F8;
            border-radius: 12px;
            padding: 16px 12px;
            text-align: center;
        }

        .bg-tile .tile-icon {
            color: #1A5336;
            margin-bottom: 6px;
        }

        .bg-tile .tile-label {
            font-size: 11px;
            color: var(--text-muted);
            margin-bottom: 2px;
        }

        .bg-tile .tile-value {
            font-size: 13px;
            font-weight: 600;
        }

        .interests-tags-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .interest-pill {
            background: var(--white);
            border: 1px solid #DDE2E5;
            padding: 7px 18px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .looking-for-card {
            background: #F4F7FB;
            border: 1px solid #E1E8F0;
            border-radius: 14px;
            padding: 24px 28px;
        }

        .looking-for-card .lf-title {
            font-family: 'Cormorant SC', serif;
            font-size: 18px;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #113B29;
        }

        .looking-for-card .lf-item {
            margin-bottom: 10px;
        }

        .looking-for-card .lf-item-title {
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .looking-for-card .lf-item-desc {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.5;
        }

        /* Persistent Action Dock at Bottom */
        .floating-action-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: var(--white);
            border-top: 1px solid #E5E5E5;
            padding: 16px 48px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 100;
        }

        .footer-cta-prompt h4 {
            font-size: 18px;
            font-weight: 600;
            text-transform: uppercase;
            color: #113B29;
        }

        .footer-cta-prompt p {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* ----------------------------------------------------
           4. MODAL / CENTERED CARD STATES (IMAGES 3, 4, 5)
        ---------------------------------------------------- */
        .centered-state-wrapper {
            max-width: 580px;
            margin: 60px auto;
            text-align: center;
            padding: 0 20px;
        }

        .status-avatar-bubble {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
        }

        .status-avatar-bubble.orange {
            background-color: var(--primary);
            color: var(--white);
        }

        .status-avatar-bubble.danger {
            background-color: var(--danger-bg);
            color: var(--danger-icon);
        }

        .state-main-title {
            font-size: 32px;
            font-weight: 500;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .state-main-desc {
            font-size: 14px;
            color: var(--text-muted);
            line-height: 1.6;
            margin-bottom: 28px;
            padding: 0 20px;
        }

        .responses-container-box {
            background: #F4F6F8;
            border-radius: 16px;
            padding: 18px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 28px;
        }

        .response-entry-card {
            background: var(--white);
            padding: 16px 20px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
        }

        .response-entry-card .res-label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Cormorant SC', serif;
            font-size: 15px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .res-tag-accepted {
            color: #144D34;
            font-weight: 600;
            font-size: 13px;
        }

        .res-tag-waiting {
            color: var(--text-muted);
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .state-next-note h3 {
            font-size: 18px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .state-next-note p {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.5;
            margin-bottom: 24px;
        }

        /* Decline Card Specifics (Image 4) */
        .pending-id-pill-box {
            background: #F4F6F8;
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 28px;
        }

        .pending-id-inner {
            background: var(--white);
            padding: 16px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Match Unveiled Specifics (Image 5) */
        .matched-profile-summary-box {
            background: #F4F6F8;
            border-radius: 16px;
            padding: 18px 22px;
            display: flex;
            align-items: center;
            gap: 18px;
            margin-bottom: 28px;
            text-align: left;
        }

        .matched-profile-summary-box img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
        }

        .matched-profile-summary-box h2 {
            font-size: 22px;
            font-weight: 500;
        }

        .matched-profile-summary-box .meta-sub {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .premium-tag-gold {
            background: #FDF3DC;
            color: #9A6E1E;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 12px;
            display: inline-block;
        }

        .contacts-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
            margin-bottom: 20px;
            text-align: left;
        }

        .contact-entry-field {
            background: #F4F7FB;
            border: 1px solid #E5ECF5;
            padding: 14px 16px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .contact-entry-field.full {
            grid-column: span 2;
        }

        .contact-icon-bubble {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #E8EEF5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #2F5779;
        }

        .contact-data-text .label {
            font-size: 11px;
            color: var(--text-muted);
        }

        .contact-data-text .val {
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .notice-gray-box {
            background: #F4F6F8;
            border-radius: 10px;
            padding: 14px 18px;
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.5;
            text-align: left;
            margin-bottom: 28px;
        }
    </style>
</head>
<body>

<div class="portal-canvas">

    <!-- Top Navigation Bar -->
    <header class="portal-header">
        <nav class="nav-tabs">
            <a href="javascript:void(0)" class="nav-tab">Profile</a>
            <a href="javascript:void(0)" class="nav-tab active">Matches</a>
            <a href="javascript:void(0)" class="nav-tab">Forms</a>
            <a href="javascript:void(0)" class="nav-tab">Events</a>
        </nav>
        <div class="header-actions">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
            <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=150&q=80" alt="User" class="header-avatar">
        </div>
    </header>

    <!-- =========================================================================
         VIEW STATE 1: DASHBOARD NEW MATCH (IMAGE 1)
    ========================================================================== -->
    <div id="step-1" class="view-state active">
        <div class="dashboard-body">
            <aside class="sidebar-panel">
                <div class="user-identity">
                    <div class="user-avatar-frame">
                        <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=150&q=80" alt="User">
                    </div>
                    <div class="user-title">Premium Member<br>Active Status</div>
                </div>

                <nav class="side-nav">
                    <a href="javascript:void(0)" class="side-nav-item">
                        <svg viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                        Profile
                    </a>
                    <a href="javascript:void(0)" class="side-nav-item" style="color: var(--primary); font-weight:700;">
                        <svg viewBox="0 0 24 24" fill="currentColor" style="stroke:none;"><path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                        Matches
                    </a>
                    <a href="javascript:void(0)" class="side-nav-item">
                        <svg viewBox="0 0 24 24" fill="none"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                        Forms
                    </a>
                    <a href="javascript:void(0)" class="side-nav-item">
                        <svg viewBox="0 0 24 24" fill="none"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                        Events
                    </a>
                </nav>

                <div style="margin-top: auto; padding-top: 30px;">
                    <button class="btn btn-primary" style="width: 100%; font-family: 'Cormorant SC', serif; font-size: 14px; text-transform: uppercase;">Upgrade to Premium</button>
                </div>
            </aside>

            <main class="main-discovery-content">
                <div class="status-pill">★ Active Member</div>
                <h1 class="discovery-title">You Have a New Match</h1>
                <p class="discovery-subtitle">We've found someone we think could be a meaningful match for you based on your shared values and requirements.</p>

                <div class="new-match-card">
                    <div>
                        <div class="candidate-hero-block">
                            <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=300&q=80" alt="Aisha" class="candidate-square-thumb">
                            <div class="candidate-meta">
                                <h2 class="font-cormorant">Aisha, 28</h2>
                                <div class="meta-loc">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                    Dubai, UAE
                                </div>
                                <div class="meta-lang">Speaks Arabic, English</div>
                            </div>
                        </div>

                        <div class="candidate-quote">
                            "Family-oriented professional seeking a partner who values tradition, mutual respect, and building a supportive home together."
                        </div>

                        <div class="candidate-tags-row">
                            <span>Never Married</span>
                            <span>Master's Degree</span>
                        </div>
                    </div>

                    <div class="action-column">
                        <div>
                            <div style="font-family: 'Cormorant SC', serif; font-size: 15px; text-transform: uppercase; font-weight: 700; margin-bottom: 6px;">Your Response</div>
                            <p>Take your time to review this profile. You have 7 days to accept or decline this match before it expires.</p>
                            <div class="timer-card">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#000" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                <div>
                                    <div class="timer-title">Time Remaining</div>
                                    <div class="timer-val">6 days remaining</div>
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-primary" style="width: 100%; font-family: 'Inter', sans-serif;" onclick="navigateStep(2)">View Match</button>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- =========================================================================
         VIEW STATE 2: FULL PROFILE VIEW (IMAGE 2)
    ========================================================================== -->
    <div id="step-2" class="view-state">
        <div class="profile-view-body">
            <h1 class="profile-view-title font-cormorant">Your Potential Match</h1>

            <div class="profile-content-grid">
                <aside class="photo-sidebar">
                    <div class="main-photo-frame">
                        <span class="photo-verified-tag">✓ Verified Profile</span>
                        <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=600&q=80" alt="Amina M.">
                    </div>

                    <div class="person-meta">
                        <h2 class="font-cormorant">Amina M.</h2>
                        <div class="loc-line">28 • Dubai, UAE</div>

                        <div class="person-details-list">
                            <div>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                                Architect at Design Firm
                            </div>
                            <div>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"></path><path d="M6 12v5c3 3 9 3 12 0v-5"></path></svg>
                                MArch, University of London
                            </div>
                            <div>
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="7 13 12 18 17 13"></polyline><polyline points="7 6 12 11 17 6"></polyline></svg>
                                165 cm (5'5")
                            </div>
                        </div>
                    </div>
                </aside>

                <main class="details-stream">
                    <div class="about-quote-card">
                        <div class="about-title"><span style="color: #CC723F; font-size: 20px;">❞</span> About</div>
                        <p>I am a dedicated professional who values family, growth, and meaningful connections. Raised with a blend of traditional values and a modern outlook, I enjoy balancing my career in architecture with quality time spent with loved ones. I appreciate deep conversations, exploring new cultures through travel, and maintaining a healthy, active lifestyle.</p>
                    </div>

                    <div>
                        <div class="section-header-title font-cormorant">Background</div>
                        <div class="background-tiles-row">
                            <div class="bg-tile">
                                <div class="tile-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="2" y1="12" x2="22" y2="12"></line><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path></svg></div>
                                <div class="tile-label">Nationality</div>
                                <div class="tile-value">Lebanese</div>
                            </div>
                            <div class="bg-tile">
                                <div class="tile-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg></div>
                                <div class="tile-label">Location</div>
                                <div class="tile-value">Dubai, UAE</div>
                            </div>
                            <div class="bg-tile">
                                <div class="tile-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 10a3 3 0 0 1 6 0"></path></svg></div>
                                <div class="tile-label">Religion</div>
                                <div class="tile-value">Muslim (Sunni)</div>
                            </div>
                            <div class="bg-tile">
                                <div class="tile-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg></div>
                                <div class="tile-label">Career</div>
                                <div class="tile-value">Architecture</div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div class="section-header-title font-cormorant">Lifestyle & Interests</div>
                        <div class="interests-tags-list">
                            <div class="interest-pill"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"></path></svg> Travel</div>
                            <div class="interest-pill"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg> Reading</div>
                            <div class="interest-pill"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="m4.93 4.93 4.24 4.24"></path><path d="m14.83 9.17 4.24-4.24"></path><path d="m14.83 14.83 4.24 4.24"></path><path d="m9.17 14.83-4.24 4.24"></path></svg> Art & Design</div>
                            <div class="interest-pill"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m18 15-6-6-6 6"></path></svg> Fitness</div>
                        </div>
                    </div>

                    <div class="looking-for-card">
                        <div class="lf-title"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg> What They're Looking For</div>
                        <div class="lf-item">
                            <div class="lf-item-title">• Relationship Goal</div>
                            <div class="lf-item-desc">Seeking a serious commitment leading to marriage within 1-2 years.</div>
                        </div>
                        <div class="lf-item">
                            <div class="lf-item-title">• Partner Preferences</div>
                            <div class="lf-item-desc">Looking for an educated, emotionally mature partner (28-35) who shares similar cultural values, is career-oriented but family-first.</div>
                        </div>
                    </div>
                </main>
            </div>
        </div>

        <!-- Sticky Footer Action Dock -->
        <footer class="floating-action-footer">
            <div class="footer-cta-prompt">
                <h4 class="font-cormorant">What do you think?</h4>
                <p>You have 7 days to respond.</p>
            </div>
            <div style="display: flex; gap: 14px;">
                <button class="btn btn-outline-dark" onclick="navigateStep(4)">Decline Match</button>
                <button class="btn btn-primary" onclick="navigateStep(3)">Accept Match →</button>
            </div>
        </footer>
    </div>

    <!-- =========================================================================
         VIEW STATE 3: ACCEPTED — WAITING FOR RESPONSE (IMAGE 3)
    ========================================================================== -->
    <div id="step-3" class="view-state">
        <div class="centered-state-wrapper">
            <div class="status-avatar-bubble orange">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
            </div>

            <h1 class="state-main-title font-cormorant">Match Accepted</h1>
            <p class="state-main-desc">You've accepted this match. We're now waiting for the other person to respond.</p>

            <div class="responses-container-box">
                <div class="response-entry-card">
                    <span class="res-label">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1D4ED8" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        Your Response
                    </span>
                    <span class="res-tag-accepted">✓ Accepted</span>
                </div>

                <div class="response-entry-card">
                    <span class="res-label">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1D4ED8" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        Their Response
                    </span>
                    <span class="res-tag-waiting">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="8" y1="12" x2="8" y2="12"></line><line x1="12" y1="12" x2="12" y2="12"></line><line x1="16" y1="12" x2="16" y2="12"></line></svg>
                        Waiting
                    </span>
                </div>
            </div>

            <div class="state-next-note">
                <h3 class="font-cormorant">What's Next?</h3>
                <p>If they also accept, you'll both receive access to each other's approved contact information.</p>
            </div>

            <button class="btn btn-primary" style="width: 100%; margin-bottom: 12px;" onclick="navigateStep(1)">Back to Dashboard →</button>
            <button class="btn btn-outline-dark" style="width: 100%; font-size: 12px; padding: 10px;" onclick="navigateStep(5)">Simulate Mutual Acceptance Screen (Image 5)</button>
        </div>
    </div>

    <!-- =========================================================================
         VIEW STATE 4: DECLINE CONFIRMATION (IMAGE 4)
    ========================================================================== -->
    <div id="step-4" class="view-state">
        <div class="centered-state-wrapper">
            <div class="status-avatar-bubble danger">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
            </div>

            <h1 class="state-main-title font-cormorant">Decline this match?</h1>
            <p class="state-main-desc">Are you sure you want to decline this match? Once declined, this match will no longer be available to you. You can decline up to 5 matches per month.</p>

            <div class="pending-id-pill-box">
                <div class="pending-id-inner">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1D4ED8" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    Pending Match #8492
                </div>
            </div>

            <div style="display: flex; gap: 14px;">
                <button class="btn btn-primary" style="flex: 1;" onclick="navigateStep(2)">Keep Match</button>
                <button class="btn btn-outline-danger" style="flex: 1;" onclick="navigateStep(1)">Decline Match →</button>
            </div>
        </div>
    </div>

    <!-- =========================================================================
         VIEW STATE 5: MUTUAL MATCH — REVEAL CONTACT INFO (IMAGE 5)
    ========================================================================== -->
    <div id="step-5" class="view-state">
        <div class="centered-state-wrapper" style="max-width: 620px;">
            <div class="status-avatar-bubble orange">
                <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M5.8 11.3 2 22l10.7-3.79"></path><path d="M4 3h.01"></path><path d="M22 8h.01"></path><path d="M15 2h.01"></path><path d="M22 20h.01"></path><path d="m22 2-2.24.75a2.9 2.9 0 0 0-1.96 3.12v0c.1.86-.57 1.63-1.45 1.63h-.38c-.86 0-1.6.6-1.76 1.44L14 12"></path></svg>
            </div>

            <h1 class="state-main-title font-cormorant" style="color: #144D34;">It's a Match!</h1>
            <p class="state-main-desc">You both accepted the match. Now you can connect directly outside Arab Zawaj.</p>

            <div class="matched-profile-summary-box">
                <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?auto=format&fit=crop&w=150&q=80" alt="Aisha M.">
                <div>
                    <h2 class="font-cormorant">Aisha M.</h2>
                    <div class="meta-sub">28 • Dubai, UAE</div>
                    <span class="premium-tag-gold">★ Premium Member</span>
                </div>
            </div>

            <div style="text-align: left; font-family: 'Cormorant SC', serif; font-size: 18px; text-transform: uppercase; font-weight: 700; margin-bottom: 14px;">Direct Contact Information</div>

            <div class="contacts-grid">
                <div class="contact-entry-field">
                    <div class="contact-icon-bubble">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
                    </div>
                    <div class="contact-data-text">
                        <div class="label">Phone</div>
                        <div class="val">+971 50 123 4567</div>
                    </div>
                </div>

                <div class="contact-entry-field">
                    <div class="contact-icon-bubble">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"></circle><path d="M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-3.92 7.94"></path></svg>
                    </div>
                    <div class="contact-data-text">
                        <div class="label">Email</div>
                        <div class="val">AISHA.M@EXAMPLE.COM</div>
                    </div>
                </div>

                <div class="contact-entry-field full">
                    <div class="contact-icon-bubble">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line></svg>
                    </div>
                    <div class="contact-data-text">
                        <div class="label">Instagram</div>
                        <div class="val">@AISHA.M_DXB</div>
                    </div>
                </div>
            </div>

            <div class="notice-gray-box">
                <strong>ⓘ Important Note:</strong> Arab Zawaj does not provide chat on the website. You can now contact each other directly using the information above. Please communicate respectfully.
            </div>

            <button class="btn btn-primary" style="width: 100%;" onclick="navigateStep(1)">Back to Dashboard →</button>
        </div>
    </div>

</div>

<script>
    function navigateStep(stepNumber) {
        document.querySelectorAll('.view-state').forEach(function(view) {
            view.classList.remove('active');
        });
        const target = document.getElementById('step-' + stepNumber);
        if (target) {
            target.classList.add('active');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }
</script>

</body>
</html>