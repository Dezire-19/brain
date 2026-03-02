<?php
include 'db.php';
include 'auth.php';

$asset_id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare("SELECT description FROM asset_reports WHERE asset_id = ? LIMIT 1");
$stmt->bind_param("i", $asset_id);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

echo json_encode($data);
