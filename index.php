<?php
// Determine main-content margin dynamically
$main_margin_top = !isMobile() ? '40px' : '0';
ob_start(); // <--- 1. THIS MUST BE THE VERY FIRST LINE
session_start();
define('IN_SYSTEM', true);
include 'auth.php';
include 'db.php';
// Simple mobile detection
function isMobile() {
    return preg_match("/(android|iphone|ipad|mobile|windows phone)/i", $_SERVER['HTTP_USER_AGENT']);
}

// Include header only if NOT mobile
if (!isMobile()) {
    include 'header.php';
}



$page = $_GET['page'] ?? 'dashboard';
$username = $_SESSION['username'] ?? 'User';

$allowed_pages = [
    'dashboard' => 'dashboard.php',
    'assets' => 'all_assets.php',
    'add_asset' => 'add_asset.php',
     'edit_asset' => 'edit_asset.php',
    'employee' => 'accountable_persons.php',
    'users' => 'add_user.php',
    'Person_details' => 'person_details.php',
    'logs' => 'logs.php',
    'delete_file' => 'delete_file.php',    
    'asset_detail' => 'asset_detail.php',
    'person_detail' => 'person_detail.php',
    'asset_history' => 'asset_history.php',
    'reports' => 'reports.php',
    'report_asset' => 'report_asset.php',
    'asset_report' => 'asset_report.php',
      'asset_report2' => 'asset_report2.php',
      'maintenance' => 'maintenance_report.php',
      'delete_asset' => 'delete_asset.php',  
     'repair_report' => 'repair_report.php',
     'employee_detail' => 'employee_detail.php'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>AMS - Asset Management System</title>


<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body { 
    font-family:'Inter',sans-serif; 
    margin:0; 
    background:#f8fafc; 
    display:flex; 
    min-height:100vh; 
    height: 100vh; /* Lock the body height to the screen */
    overflow: hidden; /* Prevent the WHOLE page from scrolling */
}

/* Sidebar already fixed width */
#sidebar {
    flex-shrink: 0;
}

/* Main content auto-adjusts */
#main-content {
    flex: 1;     
    overflow-y: auto; /* This allows ONLY the dashboard/assets to scroll */
      margin-top: <?php echo $main_margin_top; ?>;/* Adjusted from 40px to align with sticky sidebar */           /* takes remaining space */
    margin-left: 0;         /* remove manual margin */
    padding: 24px;
    transition: 0.3s ease;
}

/* Remove top margin on mobile */
@media (max-width: 768px) {
    #main-content {
        margin-top: 0 !important;
        padding-top: 12px; /* optional padding so content isn't stuck at top */
    }
}



/* ===== AI Assistant Styling ===== */
.ai-assistant {   pointer-events: none; position:fixed; bottom:24px; right:24px; z-index:9999; display:flex; flex-direction:column; align-items:flex-end; }
.ai-tooltip { 
    background:white; border-radius:16px; padding:18px; font-size:14px; width:320px; 
    box-shadow:0 10px 25px rgba(0,0,0,0.1); opacity:0; visibility:hidden; 
    transition:0.4s cubic-bezier(0.175,0.885,0.32,1.275); 
    border:1px solid #e2e8f0; margin-bottom:7px; pointer-events:none;
    position:relative;
}
.ai-tooltip.show { opacity:1; visibility:visible; transform: translateY(-10px); pointer-events:auto; }
.ai-avatar-head {   pointer-events: auto;width:80px; height:80px; border-radius:50%; cursor:pointer; position:relative; box-shadow:0 4px 10px rgba(0,0,0,0.1); transition: transform 0.2s ease; }
.ai-avatar-head:hover { transform:scale(1.05); }
.ai-avatar-head img { width:100%; height:100%; border-radius:50%; object-fit:cover; border:2px solid white; }
.ai-status { position:absolute; bottom:4px; right:4px; width:14px; height:14px; border-radius:50%; border:2px solid white; background:#22c55e; }
.status-alert { background:#ef4444 !important; animation:pulse-red 2s infinite; }

.btn-close-tooltip { position:absolute; top:6px; right:10px; cursor:pointer; font-weight:bold; color:#ef4444; font-size:16px; }

@keyframes pulse-red {
    0% { box-shadow:0 0 0 0 rgba(239,68,68,0.7); }
    70% { box-shadow:0 0 0 10px rgba(239,68,68,0); }
    100% { box-shadow:0 0 0 0 rgba(239,68,68,0); }
}

/* Updated Menu Container */
.ai-menu-container {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding-top: 10px;
}

/* Matching your "View Asset" button style from the image */
.btn-ai-menu {
    width: 100%;
    padding: 10px 14px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-family: 'Inter', sans-serif;
    font-size: 13px;
    font-weight: 600;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.btn-anomalies {
    background-color: #1e293b; /* Dark navy from your screenshot */
    color: #ffffff;
}

.btn-sleep {
    background-color: #ffffff;
    color: #475569;
    border: 1px solid #e2e8f0;
}

.btn-ai-menu:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}


.audit-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.4);
    backdrop-filter: blur(3px);
    opacity: 0;
    visibility: hidden;
    transition: 0.3s ease;
    z-index: 998;
}

.audit-panel {
    position: fixed;
    top: 0;
    right: -450px;
    width: 450px;
    height: 100vh;
    background: #ffffff;
    box-shadow: -4px 0 25px rgba(0,0,0,0.15);
    transition: 0.4s cubic-bezier(.4,0,.2,1);
    z-index: 999;
    display: flex;
    flex-direction: column;
}

.audit-panel.open {
    right: 0;
}

.audit-overlay.open {
    opacity: 1;
    visibility: visible;
}

.audit-header {
    padding: 15px;
    background: #0f172a;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
}

.audit-header button {
    background: transparent;
    border: none;
    color: white;
    font-size: 18px;
    cursor: pointer;
}

.audit-content {
    flex: 1;
    overflow: hidden;
}

.audit-content iframe {
    width: 100%;
    height: 100%;
}

</style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<main id="main-content">
    <?php
        if(array_key_exists($page, $allowed_pages)) include $allowed_pages[$page];
        else include 'dashboard.php';
    ?>
</main>

<!-- Audit Sliding Panel -->
<div id="audit-overlay" class="audit-overlay"></div>

<div id="audit-panel" class="audit-panel">
    <div class="audit-header">
        <span>Neural Network</span>
        <button onclick="closeAuditPanel()">✕</button>
    </div>

    <div class="audit-content">
        <iframe src="ai.php" frameborder="0"></iframe>
    </div>
</div>

<!-- ===== AI Assistant ===== -->
<div class="ai-assistant">
    <div class="ai-tooltip" id="velyn-bubble">
        <div id="bubble-content"></div>
    </div>
    <div class="ai-avatar-head" id="ai-click-zone">
        <img id="ai-face" src="3.png" alt="AI Face">
        <div class="ai-status" id="ai-dot"></div>
    </div>
</div>

<script src="ai-logic.js"></script>

</body>
</html>
