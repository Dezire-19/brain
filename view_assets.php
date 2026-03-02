<!-- filepath: c:\xampp\htdocs\inventory_system\view_assets.php -->
<?php
$conn = new mysqli("localhost", "root", "", "inventory_system");
$result = $conn->query("SELECT * FROM assets");
?>

<h2>Asset Inventory</h2>
<table border="1" cellpadding="10">
    <tr>
        <th>Asset ID</th>
        <th>Serial Number</th>
        <th>Description</th>
        <th>Accountable</th>
        <th>Authorized By</th>
        <th>Date Issued</th>
        <th>PDF</th>
        <th>Actions</th>
    </tr>

    <?php while($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?= $row['asset_id'] ?></td>
        <td><?= $row['serial_number'] ?></td>
        <td><?= $row['description'] ?></td>
        <td><?= $row['accountable_name'] ?></td>
        <td><?= $row['authorized_by'] ?></td>
        <td><?= $row['date_issued'] ?></td>
        <td>
            <?php if (!empty($row['pdf_path'])): ?>
                <a href="<?= $row['pdf_path'] ?>" target="_blank">View PDF</a>
            <?php else: ?>
                No PDF
            <?php endif; ?>
        </td>
        <td>
            <a href="edit_asset.php?id=<?= $row['id'] ?>">Edit</a>
        </td>
    </tr>
    <?php endwhile; ?>
</table>