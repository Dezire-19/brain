<!-- filepath: c:\xampp\htdocs\inventory_system\upload.php -->
<?php 
include 'db.php';

// Retrieve form data
$asset_name = $_POST['asset_name'];
$asset_id = $_POST['asset_id'];
$category = $_POST['category'];
$date_acquired = $_POST['date_acquired'];
$serial_number = $_POST['serial_number'];
$description = $_POST['description'];
$accountable_name = $_POST['accountable_name'] ?? null; // Optional
$authorized_by = $_POST['authorized_by'];
$date_issued = $_POST['date_issued'];

// Handle file upload
$uploadDir = "uploads/";
$pdfName = null;
$targetFile = null;

if (!empty($_FILES["pdf_file"]["name"])) {
    $pdfName = basename($_FILES["pdf_file"]["name"]);
    $targetFile = $uploadDir . time() . "_" . $pdfName;

    if (!move_uploaded_file($_FILES["pdf_file"]["tmp_name"], $targetFile)) {
        echo "⚠️ PDF upload failed.";
        $targetFile = null;
        $pdfName = null;
    }
}

// Insert data into the database
$stmt = $conn->prepare("INSERT INTO assets (asset_name, asset_id, category, date_acquired, serial_number, description, accountable_name, authorized_by, date_issued, pdf_name, pdf_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("sssssssssss", $asset_name, $asset_id, $category, $date_acquired, $serial_number, $description, $accountable_name, $authorized_by, $date_issued, $pdfName, $targetFile);

if ($stmt->execute()) {
    echo "<script>alert('✅ Asset added successfully!'); window.location.href='add_asset.php';</script>";
} else {
    echo "❌ Failed to add asset: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>