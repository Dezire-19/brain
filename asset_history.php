<?php
include 'db.php';
include 'auth.php';

$id = $_GET['id'] ?? 0;

// 1. FETCH ASSET DETAILS (To show the header)
$asset_stmt = $conn->prepare("SELECT asset_name, asset_id FROM assets WHERE id = ?");
$asset_stmt->bind_param("i", $id);
$asset_stmt->execute();
$asset_res = $asset_stmt->get_result();
$asset = $asset_res->fetch_assoc();

if (!$asset) {
    die("Error: Asset not found.");
}

// 2. FETCH FULL HISTORY
// We join with employees to get the name of the person involved in the transaction
$history_query = "
    SELECT h.*, e.full_name 
    FROM history h 
    LEFT JOIN employees e ON h.employee_id = e.employee_id 
    WHERE h.asset_id = ? 
    ORDER BY h.timestamp DESC
";

$stmt = $conn->prepare($history_query);
$stmt->bind_param("s", $asset['asset_id']);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>History Log - <?= htmlspecialchars($asset['asset_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --radius: 12px;
        }

        body { 
            font-family: 'Inter', sans-serif; 
            background-color: var(--bg); 
            color: var(--text-main); 
            margin: 0; 
            padding: 40px 20px; 
        }

        .container { 
            max-width: 900px; 
            margin: 0 auto; 
        }

        .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 30px; 
        }

        .back-link { 
            text-decoration: none; 
            color: var(--text-muted); 
            font-size: 0.9rem; 
            font-weight: 500; 
        }

        .back-link:hover { color: var(--primary); }

        .card { 
            background: var(--card-bg); 
            border-radius: var(--radius); 
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); 
            border: 1px solid var(--border); 
            overflow: hidden; 
        }

        .card-header { 
            padding: 20px 24px; 
            border-bottom: 1px solid var(--border); 
            background: #fafafa; 
        }

        .card-title { 
            margin: 0; 
            font-size: 1.25rem; 
            font-weight: 600; 
        }

        table { 
            width: 100%; 
            border-collapse: collapse; 
            text-align: left; 
        }

        th { 
            background: #f1f5f9; 
            padding: 12px 24px; 
            font-size: 0.75rem; 
            text-transform: uppercase; 
            letter-spacing: 0.05em; 
            color: var(--text-muted); 
            font-weight: 600; 
        }

        td { 
            padding: 16px 24px; 
            border-bottom: 1px solid var(--border); 
            font-size: 0.9rem; 
        }

        tr:last-child td { border-bottom: none; }

        .timestamp { color: var(--text-muted); white-space: nowrap; }
        .action-badge { 
            display: inline-block; 
            padding: 4px 10px; 
            border-radius: 6px; 
            background: #eff6ff; 
            color: #1e40af; 
            font-weight: 500; 
            font-size: 0.85rem; 
        }
        
        .empty-state { 
            padding: 40px; 
            text-align: center; 
            color: var(--text-muted); 
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <a href="index.php?page=asset_detail&id=<?= $id ?>" class="back-link">← Back to Asset Details</a>
    </div>

    <div class="card">
        <div class="card-header">
            <h1 class="card-title">History Log: <?= htmlspecialchars($asset['asset_name']) ?></h1>
            <small style="color: var(--text-muted);">Asset ID: <?= htmlspecialchars($asset['asset_id']) ?></small>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Action / Status Change</th>
                    <th>Accountable Person</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($log = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="timestamp">
                                <?= date('M d, Y', strtotime($log['timestamp'])) ?><br>
                                <small><?= date('h:i A', strtotime($log['timestamp'])) ?></small>
                            </td>
                            <td>
                                <span class="action-badge"><?= htmlspecialchars($log['action']) ?></span>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($log['full_name'] ?? 'Buffer / Available') ?></strong>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="empty-state">No history records found for this asset.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>