<?php
session_start();
include 'db.php';

$type = $_GET['type'] ?? 'greeting';
$response = [
    'messages' => [],
    'critical' => false,
    'anomaly'  => null
];

// Initialize recent anomalies array in session
// Format: ['asset_id' => ['lastFlagged' => timestamp, 'lastDCount' => int]]
$_SESSION['recent_anomalies'] = $_SESSION['recent_anomalies'] ?? [];

if ($type === 'standby') {
    // Get all assets with 3+ damage reports in last 2 months
    $checkSql = "SELECT a.id AS db_id, h.asset_id, COUNT(*) AS d_count
                 FROM history h
                 JOIN assets a ON h.asset_id = a.asset_id
                 WHERE h.item_condition = 'Damaged'
                   AND h.timestamp >= DATE_SUB(NOW(), INTERVAL 2 MONTH)
                 GROUP BY h.asset_id
                 HAVING d_count >= 3
                 ORDER BY d_count DESC";
                 
    $res = $conn->query($checkSql);

    // Initialize last anomaly timestamp
    $_SESSION['last_anomaly_time'] = $_SESSION['last_anomaly_time'] ?? 0;

    if ($res) {
        foreach ($res as $row) {
            $asset_id = $row['asset_id'];
            $d_count  = intval($row['d_count']);

            $flagged = $_SESSION['recent_anomalies'][$asset_id] ?? null;
            $shouldShow = false;

            // Only proceed if global cooldown passed
            if (time() - $_SESSION['last_anomaly_time'] >= 1 * 60) {

                if (!$flagged) {
                    // Never flagged before → show
                    $shouldShow = true;
                } else {
                    $lastDCount = $flagged['lastDCount'];
                    // Show if damage count increased
                    if ($d_count > $lastDCount) {
                        $shouldShow = true;
                    }
                }

                if ($shouldShow) {
                    // Call Python AI for "Thoughts"
                    $python_url = "http://127.0.0.1:5000/analyze?asset_id=" . urlencode($asset_id) . "&d_count=" . $d_count;
                    $ai_json = @file_get_contents($python_url);
                    $ai_data = json_decode($ai_json, true);
                    $thoughts = $ai_data['thoughts'] ?? "I am analyzing the data, but the neural bridge is currently unresponsive.";

                    // Mark asset as flagged with current damage count
                    $_SESSION['recent_anomalies'][$asset_id] = [
                        'lastFlagged' => time(),
                        'lastDCount'  => $d_count
                    ];

                    // Update global last anomaly timestamp
                    $_SESSION['last_anomaly_time'] = time();

                    // Return this anomaly
                    $response['critical'] = true;
                    $response['anomaly'] = [
                        'id'       => $row['db_id'],
                        'asset_id' => $asset_id,
                        'summary'  => "Asset has " . $d_count . " damage reports.",
                        'thoughts' => $thoughts
                    ];

                    break; // only one anomaly per scan
                }
            } // end global cooldown check
        }
    }
} else {
    $response['messages'][] = "Velyn system active. Neural links established.";
}

header('Content-Type: application/json');
echo json_encode($response);
