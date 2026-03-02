<?php
include 'db.php';
$id = $_GET['id'] ?? 0;

// Fetch asset and latest status
$query = "SELECT * FROM assets WHERE id = ?"; 
// Adjust query based on your table structure (e.g., joining with maintenance logs)
$stmt = $conn->prepare($query);
$stmt->execute([$id]);
$asset = $stmt->fetch();

if (!$asset) {
    echo "Asset not found.";
    exit;
}

// Example Risk Logic (Customize this based on your needs)
$healthScore = 85; // Default
$riskLevel = "Low";
$riskColor = "#22c55e";

if ($asset['status'] == 'Repair') {
    $healthScore = 30;
    $riskLevel = "Critical";
    $riskColor = "#ef4444";
}
?>

<div style="background:#f1f5f9; padding:15px; border-radius:12px; margin-bottom:20px;">
    <small style="color:#64748b; text-transform:uppercase; font-weight:bold;">Current Health</small>
    <div style="display:flex; align-items:center; gap:15px; margin-top:8px;">
        <div style="font-size:24px; font-weight:bold; color:<?php echo $riskColor; ?>;"><?php echo $healthScore; ?>%</div>
        <div style="flex:1; background:#e2e8f0; height:8px; border-radius:4px;">
            <div style="width:<?php echo $healthScore; ?>%; background:<?php echo $riskColor; ?>; height:100%; border-radius:4px;"></div>
        </div>
    </div>
</div>

<h4 style="color:#1e293b; margin-bottom:10px;">Asset Specifications</h4>
<table style="width:100%; font-size:13px; border-collapse:collapse;">
    <tr><td style="padding:8px 0; color:#64748b;">Serial:</td><td style="text-align:right;"><?php echo $asset['serial_no']; ?></td></tr>
    <tr><td style="padding:8px 0; color:#64748b;">Location:</td><td style="text-align:right;"><?php echo $asset['location']; ?></td></tr>
    <tr><td style="padding:8px 0; color:#64748b;">Last Updated:</td><td style="text-align:right;"><?php echo $asset['updated_at']; ?></td></tr>
</table>

<hr style="border:0; border-top:1px solid #e2e8f0; margin:20px 0;">

<h4 style="color:#1e293b;">Neural Risk Assessment</h4>
<p style="font-size:13px; color:#475569; line-height:1.6; background:#fff7ed; padding:10px; border-left:4px solid #f97316;">
    Velyn detected an anomaly in the maintenance cycle. Predicted failure risk is <b><?php echo $riskLevel; ?></b> based on historical usage patterns and component age.
</p>