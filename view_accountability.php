<?php
include 'db.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    echo "<script>alert('Invalid asset ID.'); window.location.href='all_assets.php';</script>";
    exit;
}

// Fetch the PDF path for the asset
$assetQuery = $conn->prepare("SELECT pdf_path FROM assets WHERE id = ?");
$assetQuery->bind_param("i", $id);
$assetQuery->execute();
$asset = $assetQuery->get_result()->fetch_assoc();

if (!$asset || empty($asset['pdf_path']) || !file_exists($asset['pdf_path'])) {
    echo "<script>alert('No PDF attached for this asset.'); window.location.href='asset_detail.php?id=$id';</script>";
    exit;
}

$pdfPath = $asset['pdf_path'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Accountability Form</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        .back-button {
            display: inline-block;
            margin: 10px;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }

        .back-button:hover {
            background-color: #0056b3;
        }

        iframe {
            flex: 1;
            width: 100%;
            height: 100%;
            border: none;
        }
    </style>
</head>
<body>
    <a href="asset_detail.php?id=<?= $id ?>" class="back-button">← Back</a>
    <iframe src="<?= $pdfPath ?>"></iframe>
</body>
</html>