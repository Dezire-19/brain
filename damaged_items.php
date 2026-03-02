<?php
include 'db.php';
include 'auth.php';

// Handle Condition Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_condition'])) {
    $asset_id = (int)$_POST['asset_db_id'];
    $new_condition = $conn->real_escape_string($_POST['new_condition']);
    
    $update_sql = "UPDATE assets SET item_condition = '$new_condition' WHERE id = $asset_id";
    if ($conn->query($update_sql)) {
        header("Location: damaged_items.php?msg=Status Updated");
        exit();
    }
}

// Fetch only damaged items
$sql = "SELECT * FROM assets WHERE item_condition = 'Damaged' AND deleted = 0 ORDER BY date_added DESC";
$result = $conn->query($sql);
?>