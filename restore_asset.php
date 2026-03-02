<?php
include 'db.php';
include 'auth.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'No asset ID provided']);
    exit;
}

$id = (int)$_GET['id'];

// Restore the asset (set deleted = 0)
$update = $conn->query("UPDATE assets SET deleted = 0 WHERE id = $id");

if ($update) {
    // Fetch asset info for logging
    $result = $conn->query("SELECT asset_id, asset_name FROM assets WHERE id = $id");
    $asset = $result->fetch_assoc();
    
    // Log the restore action
    $stmt = $conn->prepare("INSERT INTO logs (user_id, action, description, id_link) VALUES (?, ?, ?, ?)");
    $action = "Restored Asset " . $asset['asset_id'];
    $description = "Restored asset: " . $asset['asset_name'];
    $stmt->bind_param("issi", $_SESSION['user_id'], $action, $description, $id);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Asset successfully restored.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to restore asset.']);
}
