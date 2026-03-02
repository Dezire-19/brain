<?php 
include 'db.php';
include 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['report_id'], $_POST['item_condition'])) {
    $report_id = intval($_POST['report_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['item_condition']);
    $user_id = $_SESSION['user_id'] ?? 0;

    $reportQuery = "SELECT * FROM reported_items WHERE report_id = $report_id";
    $reportResult = mysqli_query($conn, $reportQuery);

    if ($reportResult && mysqli_num_rows($reportResult) > 0) {
        $report = mysqli_fetch_assoc($reportResult);
        $asset_id = $report['asset_id'];
        $component = $report['component'];
        $employee_id = $report['employee_id'] ?? NULL;

        if ($new_status === 'Repaired') {
            $description = "Component [$component] marked as Repaired";
            $insertHistory = $conn->prepare("
                INSERT INTO history (employee_id, user_id, asset_id, action, description, timestamp)
                VALUES (?, ?, ?, 'Repaired', ?, NOW())
            ");
            $insertHistory->bind_param("iiss", $employee_id, $user_id, $asset_id, $description);
            $insertHistory->execute();
            $insertHistory->close();

            $deleteQuery = "DELETE FROM reported_items WHERE report_id = $report_id";
            mysqli_query($conn, $deleteQuery);
        } else {
            $updateQuery = "UPDATE reported_items SET status = '$new_status' WHERE report_id = $report_id";
            mysqli_query($conn, $updateQuery);
        }

        $checkPending = "
            SELECT COUNT(*) AS pending_count
            FROM reported_items
            WHERE asset_id = '$asset_id'
              AND status IN ('Damaged', 'Under Repair', 'Under Maintenance')
        ";
        $res = mysqli_query($conn, $checkPending);
        $rowCheck = mysqli_fetch_assoc($res);

      if ($rowCheck['pending_count'] == 0) {
    // Update both item_condition and status to Good
    $assetUpdateQuery = "UPDATE assets SET item_condition = 'Good', status = 'Available' WHERE asset_id = '$asset_id'";
    mysqli_query($conn, $assetUpdateQuery);
}
        
        header("Location: index.php?page=maintenance&success=1");
        exit();
    }
}

$query = "SELECT r.report_id, r.asset_id, r.status AS item_condition, r.component, r.reported_at, r.remarks, u.username as reported_by, a.asset_name
          FROM reported_items r
          LEFT JOIN users u ON r.user_id = u.id
          LEFT JOIN assets a ON r.asset_id = a.asset_id
          ORDER BY r.reported_at DESC";
$result = mysqli_query($conn, $query);
?>
<head>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f8fafc; margin:0; font-family: 'Public Sans', sans-serif; }
        .description-row { display:none; }
        .description-row.active { display:table-row; }
        .repair-icon { transition: transform 0.3s ease, color 0.3s ease; }
        .active-repair { transform: rotate(160deg); color:#064e3b; }
        .icon-box-active { background:#d1fae5; border-color:#059669; }

        #success-modal {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); display: none; align-items: center;
            justify-content: center; z-index: 9999;
        }
        #success-modal.show { display: flex; }

        @media (max-width: 768px) {
            thead { display: none; }
            table, tbody, tr, td { display: block; width: 100%; }
            tr { margin-bottom: 1rem; border: 1px solid #e2e8f0; border-radius: 12px; background: white; overflow: hidden; }
            td { padding: 12px 16px !important; text-align: left !important; border: none !important; position: relative; }
            td:before {
                content: attr(data-label);
                display: block;
                font-size: 10px;
                text-transform: uppercase;
                color: #94a3b8;
                font-weight: 800;
                margin-bottom: 2px;
            }
            .description-row.active { display: block; border-top: 1px solid #f1f5f9; }
            /* Hide Manage label and center the icon box on mobile */
            td[data-label="Manage"] { display: flex; justify-content: space-between; align-items: center; background: #fdfdfd; }
        }
    </style>
</head>

<body class="bg-[#F9FAFB]">

<?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
<div id="success-modal" class="show">
    <div class="bg-white p-8 rounded-2xl text-center max-w-sm w-[90%] shadow-2xl">
        <i class="fa-solid fa-circle-check text-emerald-600 text-6xl mb-4"></i>
        <h2 class="text-2xl font-bold text-gray-800 mb-2">Success!</h2>
        <p class="text-gray-500 mb-6">Asset update has been processed successfully.</p>
        <button onclick="closeModal()" class="w-full bg-emerald-900 text-white py-3 rounded-xl font-bold hover:bg-emerald-950 transition-colors">GREAT</button>
    </div>
</div>
<?php endif; ?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
    <div class="mb-8">
        <h1 class="text-2xl md:text-3xl font-extrabold uppercase tracking-tight text-gray-700 flex items-center gap-3">
            <i class="fas fa-tools text-emerald-900"></i> Reported Items
        </h1>
        <p class="text-gray-500 text-sm mt-1">Monitor and resolve reported hardware issues.</p>
    </div>

    <div class="flex flex-col md:flex-row md:justify-between items-start md:items-center gap-4 mb-6">
        <div class="relative w-full md:w-96">
            <input 
                type="text" 
                id="search-input" 
                placeholder="Search asset or status..." 
                class="w-full border border-gray-300 rounded-xl py-3 pl-11 pr-4 focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-900 outline-none text-sm transition-all shadow-sm"
            >
            <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
        </div>

    </div>

    <div class="bg-transparent md:bg-white md:border md:border-gray-200 md:rounded-2xl md:shadow-sm overflow-hidden">
        <table class="w-full text-left border-collapse" id="maintenanceTable">
            <thead class="bg-gray-50 border-b border-gray-200 text-[11px] uppercase text-gray-400 font-bold tracking-wider">
                <tr>
                    <th class="px-6 py-4">Asset & Component</th>
                    <th class="px-6 py-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php if($result && mysqli_num_rows($result) > 0): ?>
                <?php while($row = mysqli_fetch_assoc($result)): ?>
                <tr class="hover:bg-emerald-50/30 cursor-pointer transition-colors" onclick="handleRowClick(this, 'desc-<?= $row['report_id']; ?>')">
                  <td class="px-6 py-5" data-label="Asset Information">
    <div class="font-bold text-gray-800 text-sm">
        <?= htmlspecialchars($row['asset_name']); ?> 
        <span class="text-gray-400 font-mono text-[10px] md:ml-1 md:before:content-['#'] md:before:mr-0.5">
            <?= htmlspecialchars($row['asset_id']); ?>
        </span>
    </div>
    <div class="text-[11px] text-red-700 font-medium uppercase tracking-wider mt-1">
        <i class="fas fa-layer-group mr-1 opacity-50"></i>
        <?= htmlspecialchars($row['component']); ?>
    </div>
</td>

                    <td class="px-6 py-5 text-right" data-label="Manage">
                        <div id="box-<?= $row['report_id']; ?>" class="inline-flex items-center justify-center w-10 h-10 rounded-xl border border-gray-200 bg-white text-gray-400 shadow-sm transition-all">
                            <i id="icon-<?= $row['report_id']; ?>" class="fas fa-wrench repair-icon text-sm"></i>
                        </div>
                    </td>
                </tr>

                <tr id="desc-<?= $row['report_id']; ?>" class="description-row bg-gray-50/50">
                    <td colspan="3" class="px-4 md:px-8 py-8">
                        <div class="flex flex-col lg:flex-row gap-8">
                            <div class="flex-1 space-y-4">
                                <h4 class="text-[11px] font-black text-gray-400 uppercase tracking-widest">Report Summary</h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                                        <p class="text-[10px] text-gray-400 font-bold uppercase mb-1">Reported By</p>
                                        <p class="text-sm text-gray-700 font-medium flex items-center gap-2">
                                            <i class="fas fa-user-circle text-grey-900/30 text-lg"></i>
                                            <?= htmlspecialchars($row['reported_by'] ?? 'System'); ?>
                                        </p>
                                    </div>
                                    <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                                        <p class="text-[10px] text-gray-400 font-bold uppercase mb-1">Date Logged</p>
                                        <p class="text-sm text-gray-700 font-medium flex items-center gap-2">
                                            <i class="fas fa-calendar text-grey-900/30 text-lg"></i>
                                            <?= date('M d, Y - h:i A', strtotime($row['reported_at'])); ?>
                                        </p>
                                    </div>
                                    <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm md:col-span-2">
                                        <p class="text-[10px] text-gray-400 font-bold uppercase mb-1">Remarks from user</p>
                                        <p class="text-sm text-gray-600 italic">"<?= htmlspecialchars($row['remarks'] ?: 'No remarks provided.'); ?>"</p>
                                    </div>
                                </div>
                            </div>

                            <div class="w-full lg:w-80 bg-white p-6 rounded-2xl border border-emerald-100 shadow-md">
                                <h4 class="text-sm font-bold text-emerald-900 mb-4 flex items-center gap-2">
                                    <i class="fas fa-clipboard-check"></i> Resolve Issue
                                </h4>
                                <form method="POST" class="space-y-4">
                                    <input type="hidden" name="report_id" value="<?= $row['report_id']; ?>">
                                    <div>
                                  <select name="item_condition" class="w-full text-sm border border-gray-200 rounded-xl py-3 px-4 bg-gray-50 focus:ring-2 focus:ring-emerald-500 outline-none transition-all" required>
    <option value="Repaired" <?= $row['item_condition'] == 'Repaired' ? 'selected' : '' ?>>Fixed / Good Condition</option>
    <option value="Under Repair" <?= $row['item_condition'] == 'Under Repair' ? 'selected' : '' ?>>Move to Under Repair</option>
    <option value="Damaged" <?= $row['item_condition'] == 'Damaged' ? 'selected' : '' ?>>Still Damaged</option>
    <option value="Under Maintenance" <?= $row['item_condition'] == 'Under Maintenance' ? 'selected' : '' ?>>Under Maintenance</option>
    <option value="Disposed" <?= $row['item_condition'] == 'Disposed' ? 'selected' : '' ?>>Mark for Disposal</option>
</select>
                                    </div>
                                    <button type="submit" class="w-full bg-emerald-900 text-white text-xs font-bold py-4 rounded-xl uppercase tracking-widest hover:bg-emerald-950 shadow-lg active:scale-95 transition-all">
                                        Submit Update
                                    </button>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" class="py-20 text-center">
                        <i class="fas fa-clipboard-check text-gray-200 text-6xl mb-4"></i>
                        <p class="text-gray-400 font-bold uppercase text-xs tracking-widest">Everything is operational</p>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<script>
// Search Logic
document.getElementById('search-input').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('#maintenanceTable tbody tr:not(.description-row)');
    
    rows.forEach(row => {
        let text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? "" : "none";
        // Ensure detail row stays hidden if main row is hidden
        let detailRow = row.nextElementSibling;
        if (detailRow && detailRow.classList.contains('description-row')) {
            detailRow.classList.remove('active');
        }
    });
});

function handleRowClick(rowElement, descId) {
    const descRow = document.getElementById(descId);
    const reportId = descId.replace('desc-', '');
    const icon = document.getElementById('icon-' + reportId);
    const box = document.getElementById('box-' + reportId);

    // Toggle logic
    const isActive = descRow.classList.toggle('active');
    
    if(isActive){
        icon.classList.add('active-repair');
        box.classList.add('icon-box-active');
    } else {
        icon.classList.remove('active-repair');
        box.classList.remove('icon-box-active');
    }
}

function closeModal() {
    document.getElementById('success-modal').classList.remove('show');
    window.location.href = 'index.php?page=maintenance';
}
</script>
</body>