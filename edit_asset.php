<?php
include 'auth.php';
include 'db.php';
include_once 'csrf.php';
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$user_id = $_SESSION['user_id'] ?? 0;

// --- 1. FETCH EXISTING DATA ---
$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: all_assets.php");
    exit;
}

$stmt_fetch = $conn->prepare("SELECT * FROM assets WHERE id = ?");
$stmt_fetch->bind_param("i", $id);
$stmt_fetch->execute();
$asset = $stmt_fetch->get_result()->fetch_assoc();

if (!$asset) {
    die("Asset record not found.");
}

// Initialize field errors
$field_errors = [
    'asset_id'      => '',
    'serial_number' => '',
    'pdf_file'      => '',
    'date_acquired' => '',
];

// Fetch employees for dropdown
$employees_list = [];
$employee_result = $conn->query("SELECT employee_id, full_name FROM employees ORDER BY full_name ASC");
if ($employee_result) {
    while ($row = $employee_result->fetch_assoc()) {
        $employees_list[] = $row;
    }
}

// --- 2. HANDLE UPDATE SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        die("Invalid request.");
    }

    if (isset($_POST['asset_name'], $_POST['asset_id'])) {

        $asset_name    = trim($_POST['asset_name']);
        $asset_id      = trim($_POST['asset_id']);
        $category      = trim($_POST['category']);
        $serial_number = trim($_POST['serial_number']);
        $description   = trim($_POST['description']);
        $authorized_by = trim($_POST['authorized_by']);
        $employee_id   = !empty($_POST['employee_id']) ? (int)$_POST['employee_id'] : null;
        $status        = $employee_id ? "Assigned" : "Available";
        $date_acquired = trim($_POST['date_acquired'] ?? '');
        
        if (empty($date_acquired)) {
            $field_errors['date_acquired'] = "Acquired date is required.";
        }
        $date_issued = empty($_POST['date_issued']) ? null : $_POST['date_issued'];

        // Check for duplicate Asset ID or Serial (excluding current record)
        $check_stmt = $conn->prepare("SELECT asset_id, serial_number FROM assets WHERE (asset_id = ? OR serial_number = ?) AND id != ?");
        $check_stmt->bind_param("ssi", $asset_id, $serial_number, $id);
        $check_stmt->execute();
        $result_check = $check_stmt->get_result();
        while ($row = $result_check->fetch_assoc()) {
            if ($row['asset_id'] === $asset_id) $field_errors['asset_id'] = "Asset ID already exists.";
            if ($row['serial_number'] === $serial_number) $field_errors['serial_number'] = "Serial Number already exists.";
        }
        $check_stmt->close();

        // PDF Validation (Only if a new file is uploaded)
        if (!empty($_FILES['pdf_file']['tmp_name']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
            $file_tmp  = $_FILES['pdf_file']['tmp_name'];
            $file_name = $_FILES['pdf_file']['name'];
            $file_size = $_FILES['pdf_file']['size'];
            $allowed_mime  = 'application/pdf';
            $max_size      = 5 * 1024 * 1024; // 5MB
            $mime = mime_content_type($file_tmp);

            if ($mime !== $allowed_mime || strtolower(pathinfo($file_name, PATHINFO_EXTENSION)) !== 'pdf') {
                $field_errors['pdf_file'] = "Invalid file type. Only PDF allowed.";
            } elseif ($file_size > $max_size) {
                $field_errors['pdf_file'] = "File too large. Maximum size is 5MB.";
            }
        }

        // Update only if no field errors
        if (!array_filter($field_errors)) {

            $stmt = $conn->prepare("
                UPDATE assets SET 
                asset_name=?, asset_id=?, category=?, serial_number=?, description=?, 
                employee_id=?, authorized_by=?, date_acquired=?, date_issued=?, status=? 
                WHERE id=?
            ");
            $stmt->bind_param(
                "sssssissssi",
                $asset_name, $asset_id, $category, $serial_number, $description,
                $employee_id, $authorized_by, $date_acquired, $date_issued, $status, $id
            );

            if ($stmt->execute()) {
                // Handle PDF upload if new file provided
                if (!empty($_FILES['pdf_file']['tmp_name'])) {
                    $upload_dir = __DIR__ . "/uploads/files/";
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                    $unique_name = bin2hex(random_bytes(8)) . "_" . preg_replace("/[^a-zA-Z0-9_.-]/", "_", basename($file_name));
                    $target_file = $upload_dir . $unique_name;

                    if (move_uploaded_file($file_tmp, $target_file)) {
                        // Insert new file record
                        $stmt_file = $conn->prepare("
                            INSERT INTO asset_files (asset_id, file_name, file_path, upload_date)
                            VALUES (?, ?, ?, NOW())
                        ");
                        $stmt_file->bind_param("iss", $id, $file_name, $target_file);
                        $stmt_file->execute();
                        $stmt_file->close();
                    }
                }

                header("Location: index.php?page=assets&updated=1");
                exit;

            } else {
                $field_errors['asset_id'] = "System Error: " . $stmt->error;
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Asset Management | Edit Record</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
body { font-family: 'Plus Jakarta Sans', sans-serif; }
.form-input { transition: all 0.2s ease-in-out; }
.form-input:focus { box-shadow: 0 0 0 4px rgba(0, 77, 45, 0.1); }
</style>
</head>
<body class="min-h-screen bg-slate-50">

<div class="p-5 border-b border-slate-200 flex justify-between items-center">
    <a href="all_assets.php" class="text-slate-500 hover:text-[#004D2D] transition-colors flex items-center group">
        <span class="mt-2 text-[11px] font-bold uppercase tracking-widest flex items-center">
            <i class="fa-solid fa-arrow-left mr-2 transform group-hover:-translate-x-1 transition-transform"></i> 
            Back to Registry
        </span>
    </a>
      <h1 class="text-sm font-bold text-slate-800 uppercase tracking-tighter">Edit Asset Record</h1>
</div>

<form action="" method="POST" enctype="multipart/form-data" class="p-8 space-y-8">
<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
<input type="hidden" name="status" id="statusInput" value="<?= htmlspecialchars($asset['status']) ?>">

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-2 space-y-6">
        <div class="border-b border-slate-100 pb-2 flex items-center gap-2">
            <i class="fa-solid fa-microchip text-slate-400"></i>
            <h2 class="text-xs font-black uppercase tracking-widest text-slate-800">01. Hardware Information</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div class="flex flex-col space-y-1.5">
                <label class="text-[11px] font-bold text-slate-700 uppercase ml-1">Asset Name</label>
                <input type="text" name="asset_name" value="<?= htmlspecialchars($asset['asset_name']) ?>" required class="form-input w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-[#004D2D] focus:bg-white outline-none">
            </div>

            <div class="flex flex-col space-y-1.5">
                <label class="text-[11px] font-bold text-slate-700 uppercase ml-1">Asset Tag / ID</label>
                <input type="text" name="asset_id" value="<?= htmlspecialchars($asset['asset_id']) ?>" required class="form-input w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-[#004D2D] focus:bg-white outline-none">
                <?php if ($field_errors['asset_id']): ?>
                    <span class="text-red-600 text-xs mt-1"><?= htmlspecialchars($field_errors['asset_id']) ?></span>
                <?php endif; ?>
            </div>

            <div class="flex flex-col space-y-1.5">
                <label class="text-[11px] font-bold text-slate-700 uppercase ml-1">Serial Number</label>
                <input type="text" name="serial_number" value="<?= htmlspecialchars($asset['serial_number']) ?>" required class="form-input w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-[#004D2D] focus:bg-white outline-none">
                <?php if ($field_errors['serial_number']): ?>
                    <span class="text-red-600 text-xs mt-1"><?= htmlspecialchars($field_errors['serial_number']) ?></span>
                <?php endif; ?>
            </div>

            <div class="flex flex-col space-y-1.5">
                <label class="text-[11px] font-bold text-slate-700 uppercase ml-1">Category</label>
                <select name="category" required class="form-input w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-[#004D2D] focus:bg-white outline-none cursor-pointer">
                    <?php $categories = ['Laptop', 'Monitor', 'Headset', 'Keyboard', 'Mouse', 'Charger']; ?>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?= $cat ?>" <?= $asset['category'] == $cat ? 'selected' : '' ?>><?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex flex-col space-y-1.5 md:col-span-2">
                <label class="text-[11px] font-bold text-slate-700 uppercase ml-1">Specifications & Description</label>
                <textarea name="description" rows="3" required class="form-input w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:border-[#004D2D] focus:bg-white outline-none resize-none"><?= htmlspecialchars($asset['description']) ?></textarea>
            </div>
        </div>
    </div>

    <div class="bg-slate-150 p-6 rounded-2xl border border-slate-300 space-y-6">
        <div class="border-b border-slate-200 pb-2 flex items-center gap-2">
            <i class="fa-solid fa-user-check text-slate-400"></i>
            <h2 class="text-xs font-black uppercase tracking-widest text-slate-800">02. Assignment</h2>
        </div>

        <div class="space-y-4">
            <div class="flex flex-col space-y-1.5">
                <label class="text-[11px] font-bold text-slate-700 uppercase">Accountable Person</label>
                <select name="employee_id" id="employeeSelect" class="form-input w-full p-3.5 bg-white border border-slate-200 rounded-xl text-sm focus:border-[#004D2D] outline-none">
                    <option value="">Buffer (N/A)</option>
                    <?php foreach ($employees_list as $emp): ?>
                        <option value="<?= $emp['employee_id'] ?>" <?= $asset['employee_id'] == $emp['employee_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($emp['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex flex-col space-y-1.5">
                <label class="text-[11px] font-bold text-slate-500 uppercase">Authorized By</label>
                <select name="authorized_by" required class="form-input w-full p-3.5 bg-white border border-slate-200 rounded-xl text-sm focus:border-[#004D2D] outline-none">
                    <?php $authorizers = ["Rogino Mahinay", "Edwin Lopez", "Anthony Revil", "Leonard Dee"]; ?>
                    <?php foreach($authorizers as $auth): ?>
                        <option value="<?= $auth ?>" <?= $asset['authorized_by'] == $auth ? 'selected' : '' ?>><?= $auth ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="flex flex-col space-y-1.5">
                    <label class="text-[11px] font-bold text-slate-500 uppercase">Acquired</label>
                  <input type="date" name="date_acquired" value="<?= $asset['date_acquired'] ?>" class="w-full p-3 bg-white border border-slate-200 rounded-xl text-[12px]" required>
                </div>
                <div class="flex flex-col space-y-1.5">
                    <label class="text-[11px] font-bold text-slate-500 uppercase">Issued</label>
                    <input type="date" name="date_issued" value="<?= $asset['date_issued'] ?>" class="w-full p-3 bg-white border border-slate-200 rounded-xl text-[12px]">
                </div>
            </div>
        </div>
    </div>
</div>

<div class="pt-8 border-t border-slate-100 flex flex-col md:flex-row items-center justify-between gap-6">
    <div class="w-full md:max-w-md">
        <label class="text-[11px] font-bold text-slate-500 uppercase mb-2 block">Upload New Accountability (PDF)</label>
        <label class="flex items-center justify-center w-full h-14 px-4 transition bg-white border-2 border-slate-300 border-dashed rounded-xl appearance-none cursor-pointer hover:border-[#004D2D] focus:outline-none">
            <span class="flex items-center space-x-2">
                <i class="fa-solid fa-cloud-arrow-up text-slate-400"></i>
                <span class="text-xs font-medium text-slate-600" id="fileName">Click to replace document</span>
            </span>
            <input type="file" name="pdf_file" accept="application/pdf" class="hidden" id="pdfInput">
        </label>
    </div>

    <button type="submit" class="w-full md:w-auto bg-[#004D2D] hover:bg-slate-900 text-white px-12 py-4 rounded-xl font-bold text-sm uppercase tracking-widest transition-all shadow-lg shadow-green-900/20 flex items-center justify-center gap-3">
        <i class="fa-solid fa-plus-circle"></i>
        Save Asset Record
    </button>
</div>
</form>

<script>
const employeeSelect = document.getElementById('employeeSelect');
const statusInput = document.getElementById('statusInput');
employeeSelect.addEventListener('change', function() {
    statusInput.value = this.value !== "" ? "Assigned" : "Available";
});

const pdfInput = document.getElementById('pdfInput');
const fileName = document.getElementById('fileName');
pdfInput.addEventListener('change', function() {
    if (this.files && this.files.length > 0) {
        fileName.textContent = this.files[0].name;
    }
});
</script>

</body>
</html>