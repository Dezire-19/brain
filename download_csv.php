<?php
include 'db.php';
require 'vendor/autoload.php'; // Include PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Debugging: Check database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Fetch data from the database
$sql = "SELECT asset_id, accountable_name, serial_number, description, category FROM assets";
$result = $conn->query($sql);

if (!$result) {
    die("Query failed: " . $conn->error);
}

if ($result->num_rows === 0) {
    die("No data found in the database.");
}

// Create a new Spreadsheet object
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Write the column headers
$sheet->setCellValue('A1', 'Asset ID');
$sheet->setCellValue('B1', 'Accountable Name');
$sheet->setCellValue('C1', 'Serial Number');
$sheet->setCellValue('D1', 'Description');
$sheet->setCellValue('E1', 'Category');

// Write data to the spreadsheet
$rowNumber = 2; // Start writing data from the second row
while ($row = $result->fetch_assoc()) {
    $sheet->setCellValue('A' . $rowNumber, $row['asset_id']);
    $sheet->setCellValue('B' . $rowNumber, $row['accountable_name']);
    $sheet->setCellValue('C' . $rowNumber, $row['serial_number']);
    $sheet->setCellValue('D' . $rowNumber, $row['description']);
    $sheet->setCellValue('E' . $rowNumber, $row['category']);
    $rowNumber++;
}

// Set headers to force download of the Excel file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="assets.xlsx"');

// Write the spreadsheet to the output stream
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>