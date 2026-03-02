<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'db.php';
include 'auth.php';
include 'csrf.php'; // Must contain csrf_token() and validate_csrf()

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // -------------------------
    // CSRF Validation
    // -------------------------
    if (!isset($_POST['csrf_token']) || !validate_csrf($_POST['csrf_token'])) {
        die("CSRF token invalid");
    }

    // -------------------------
    // Role check: Only Manager can delete
    // -------------------------
    if ($_SESSION['role'] !== 'Manager') {
        die("Unauthorized");
    }

    // -------------------------
    // Safe asset numeric ID (PK)
    // -------------------------
    $id = intval($_POST['id']); // numeric PK from assets table

    // -------------------------
    // Fetch asset info including string asset_id for FK
    // -------------------------
    $stmt = $conn->prepare("
        SELECT asset_id, asset_name, employee_id 
        FROM assets 
        WHERE id = ? AND deleted = 0
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($asset_id, $asset_name, $employee_id);
    $stmt->fetch();
    $stmt->close();

    if (!empty($asset_name)) {

        // -------------------------
        // Insert into history table using string asset_id
        // -------------------------
        $action = "Deleted Asset";
        $description = "Deleted asset: $asset_name";
        $user_id = $_SESSION['user_id'] ?? null;

        // Handle NULL employee_id safely
        $employee_id_for_history = !empty($employee_id) ? $employee_id : null;

        $stmt = $conn->prepare("
            INSERT INTO history (employee_id, user_id, asset_id, action, description, timestamp)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        // Bind parameters:
        // employee_id = i (nullable)
        // user_id     = i
        // asset_id    = s (string)
        // action      = s
        // description = s
        if ($employee_id_for_history === null) {
            $null_var = null;
            $stmt->bind_param("iisss", $null_var, $user_id, $asset_id, $action, $description);
        } else {
            $stmt->bind_param("iisss", $employee_id_for_history, $user_id, $asset_id, $action, $description);
        }

        $stmt->execute();
        $stmt->close();

        // -------------------------
        // Soft delete the asset using numeric PK
        // -------------------------
   $stmt = $conn->prepare("
    UPDATE assets 
    SET deleted = 1,
        disposed_date = CURDATE()
    WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();
    }

    // -------------------------
    // Redirect back safely
    // -------------------------
    header("Location: index.php?page=assets");
    exit();
}
?>