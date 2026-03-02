<?php
// assets.php
header('Content-Type: application/json');
include 'db.php'; // Your DB connection file

$action = $_GET['action'] ?? '';
$asset_id = $_GET['asset_id'] ?? null;

// Helper function to fetch query results as associative array
function fetch_all_assoc($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

switch ($action) {

    // -----------------------------
    // GET ALL ASSETS
    // -----------------------------
    case 'all':
        $stmt = $conn->prepare("SELECT * FROM assets");
        $assets = fetch_all_assoc($stmt);
        echo json_encode($assets);
        break;

    // -----------------------------
    // GET DAMAGED COMPONENTS
    // -----------------------------
    case 'damaged':
        $stmt = $conn->prepare("SELECT asset_id, component FROM reported_items WHERE status = 'Damaged'");
        $damaged = fetch_all_assoc($stmt);
        echo json_encode($damaged);
        break;

    // -----------------------------
    // GET ASSET FAILURE HISTORY
    // -----------------------------
    case 'history':
        if (!$asset_id) {
            echo json_encode(["error" => "asset_id parameter required"]);
            exit;
        }
        $stmt = $conn->prepare("SELECT * FROM asset_failure_history WHERE asset_id = ? ORDER BY date_recorded ASC");
        $stmt->bind_param('s', $asset_id);
        $history = fetch_all_assoc($stmt);
        echo json_encode($history);
        break;

    // -----------------------------
    // GET REPAIRS / MAINTENANCE LOGS
    // -----------------------------
    case 'history_logs':
        if ($asset_id) {
            $stmt = $conn->prepare("SELECT * FROM history WHERE asset_id = ? ORDER BY timestamp ASC");
            $stmt->bind_param('s', $asset_id);
        } else {
            $stmt = $conn->prepare("SELECT * FROM history ORDER BY timestamp ASC");
        }
        $logs = fetch_all_assoc($stmt);
        echo json_encode($logs);
        break;

    // -----------------------------
    // GET LAST MAINTENANCE DATE
    // -----------------------------
    case 'last_maintenance':
        if (!$asset_id) {
            echo json_encode(["error" => "asset_id parameter required"]);
            exit;
        }
        $stmt = $conn->prepare("
            SELECT MAX(timestamp) AS last_maintenance 
            FROM history 
            WHERE asset_id = ? AND action = 'Maintenance Report'
        ");
        $stmt->bind_param('s', $asset_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        echo json_encode($result);
        break;

    default:
        echo json_encode(["error" => "Invalid action"]);
        break;
}

$conn->close();
?>