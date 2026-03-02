<?php
include 'db.php';
include 'auth.php';
include 'csrf.php'; // CSRF protection

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    die('Invalid request method.');
}

// Check required GET parameters
if (!isset($_GET['id'], $_GET['asset_id'], $_GET['csrf_token'])) {
    echo "<script>alert('Missing parameters.'); window.history.back();</script>";
    exit;
}

// Validate CSRF token
if (!validate_csrf($_GET['csrf_token'])) {
    die('Invalid CSRF token.');
}

$file_id = intval($_GET['id']);
$asset_row_id = intval($_GET['asset_id']);

// Fetch file info
$file_stmt = $conn->prepare("SELECT * FROM asset_files WHERE id = ?");
$file_stmt->bind_param("i", $file_id);
$file_stmt->execute();
$file_data = $file_stmt->get_result()->fetch_assoc();

if (!$file_data) {
    echo "<script>alert('File not found.'); window.history.back();</script>";
    exit;
}

$file_path = $file_data['file_path'];
$file_name = $file_data['file_name'];

// Fetch asset_id string from assets table
$asset_stmt = $conn->prepare("SELECT asset_id FROM assets WHERE id = ?");
$asset_stmt->bind_param("i", $asset_row_id);
$asset_stmt->execute();
$asset_result = $asset_stmt->get_result()->fetch_assoc();

if (!$asset_result) {
    echo "<script>alert('Asset not found.'); window.history.back();</script>";
    exit;
}

$asset_id = $asset_result['asset_id'];

// Delete file from database
$delete_stmt = $conn->prepare("DELETE FROM asset_files WHERE id = ?");
$delete_stmt->bind_param("i", $file_id);

if ($delete_stmt->execute()) {
    // Delete actual file from server
    if (file_exists($file_path)) {
        unlink($file_path);
    }

    // Log deletion in history table
    $user_id = $_SESSION['user_id'];
    $action = "Deleted an Accountability form";
    $description = "Deleted file '$file_name' for asset '$asset_id'";
    $timestamp = date('Y-m-d H:i:s');

    // Declare variable for bind_param to avoid reference error
    $null_emp = null;
    $history_stmt = $conn->prepare("
        INSERT INTO history (employee_id, user_id, asset_id, action, description, timestamp) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $history_stmt->bind_param("iissss", $null_emp, $user_id, $asset_id, $action, $description, $timestamp);
    $history_stmt->execute();

    echo "<script>alert('File deleted successfully.'); window.location.href='index.php?page=asset_detail&id=$asset_row_id';</script>";
} else {
    echo "<script>alert('Failed to delete file.'); window.history.back();</script>";
}
?>