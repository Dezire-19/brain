<?php
include 'db.php';
include 'auth.php';

$employee_id = $_GET['id'] ?? 0;
if (!$employee_id) die("No employee selected.");

/* ---------- FETCH EMPLOYEE INFO ---------- */
$stmt = $conn->prepare("SELECT full_name, department FROM employees WHERE employee_id = ?");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$empResult = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$empResult) die("Employee not found.");

/* ---------- CURRENT ASSETS ---------- */
$currentAssetsQuery = "SELECT id, asset_id, asset_name, date_issued FROM assets WHERE employee_id = ? AND status = 'Assigned' ORDER BY date_issued DESC";
$stmt = $conn->prepare($currentAssetsQuery);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$currentAssets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ---------- RETURNED HISTORY ---------- */
$returnedQuery = "SELECT h.id, h.asset_id, a.asset_name, h.timestamp AS returned_at FROM history h LEFT JOIN assets a ON h.asset_id = a.asset_id WHERE h.employee_id = ? AND h.action = 'Returned' ORDER BY h.timestamp DESC";
$stmt = $conn->prepare($returnedQuery);
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$returnedAssets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Assets | <?= htmlspecialchars($empResult['full_name']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: #f9fafb; }
        .tab-active { color: #115e59; border-bottom: 2px solid #115e59; }
        .tab-inactive { color: #64748b; }
    </style>
</head>
<body>

<div class="max-w-4xl mx-auto px-6 py-10">

    <header class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-10">
        <div>
            <h1 class="text-4xl font-extrabold text-slate-900"><?= htmlspecialchars($empResult['full_name']) ?></h1>
            <div class="flex items-center gap-3 mt-2">
                <span class="text-slate-400 text-sm italic"><?= htmlspecialchars($empResult['department']) ?></span>
            </div>
        </div>
        
        <button onclick="toggleModal(true)" class="bg-white hover:bg-red-50 text-red-600 px-5 py-2.5 rounded-lg text-sm font-bold transition-all border border-red-200 shadow-sm flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
            Delete Employee
        </button>
    </header>

    <div class="flex gap-8 mb-8 border-b border-slate-200">
        <button id="tabCurrent" onclick="switchTab('current')" class="tab-active pb-3 text-sm font-semibold">
            Current Assets <span class="ml-1 px-1.5 py-0.5 rounded-sm bg-slate-100 text-slate-500 text-[10px]"><?= count($currentAssets) ?></span>
        </button>
        <button id="tabReturned" onclick="switchTab('returned')" class="tab-inactive pb-3 text-sm font-medium hover:text-teal-600">
            Returned History <span class="ml-1 px-1.5 py-0.5 rounded-sm bg-slate-100 text-slate-500 text-[10px]"><?= count($returnedAssets) ?></span>
        </button>
    </div>

    <div class="flex flex-col gap-5">
        <div id="currentAssets">
            <?php if(count($currentAssets) > 0): ?>
                <div class="overflow-x-auto rounded-lg border border-slate-200 shadow-sm">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Asset Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Tag</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Date Assigned</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-100">
                            <?php foreach ($currentAssets as $asset): ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-6 py-4 text-slate-800 font-medium"><?= htmlspecialchars($asset['asset_name']) ?></td>
                                    <td class="px-6 py-4 text-slate-500 font-mono text-sm"><?= htmlspecialchars($asset['asset_id']) ?></td>
                                    <td class="px-6 py-4 text-slate-500 text-sm"><?= date('M d, Y', strtotime($asset['date_issued'])) ?></td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="asset_detail.php?id=<?= urlencode($asset['id']) ?>" class="text-teal-600 hover:text-teal-800 text-xs font-semibold">Details →</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-slate-500 italic text-sm">No current assets assigned.</p>
            <?php endif; ?>
        </div>

        <div id="returnedAssets" class="hidden">
            <?php if(count($returnedAssets) > 0): ?>
                <div class="overflow-x-auto rounded-lg border border-slate-200 shadow-sm">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Asset Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Tag</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Returned At</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-100">
                            <?php foreach ($returnedAssets as $asset): ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-6 py-4 text-slate-800 font-medium"><?= htmlspecialchars($asset['asset_name']) ?></td>
                                    <td class="px-6 py-4 text-slate-500 font-mono text-sm"><?= htmlspecialchars($asset['asset_id']) ?></td>
                                    <td class="px-6 py-4 text-slate-500 text-sm"><?= date('M d, Y', strtotime($asset['returned_at'])) ?></td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="asset_detail.php?id=<?= urlencode($asset['id']) ?>" class="text-teal-600 hover:text-teal-800 text-xs font-semibold">Details →</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-slate-500 italic text-sm">No returned history.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="deleteModal" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-8">
        <div class="text-red-600 bg-red-100 w-12 h-12 rounded-full flex items-center justify-center mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </div>
        <h3 class="text-xl font-bold text-slate-900 mb-2">Delete Employee?</h3>
        <p class="text-slate-500 text-sm mb-6">
            You are about to delete <strong><?= htmlspecialchars($empResult['full_name']) ?></strong>. 
            This action cannot be undone and will detach all asset records.
        </p>
        <div class="flex gap-3">
            <button onclick="toggleModal(false)" class="flex-1 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-semibold transition-all">Cancel</button>
            <a href="process_delete_employee.php?id=<?= $employee_id ?>" class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-center rounded-lg font-semibold transition-all shadow-md">Confirm Delete</a>
        </div>
    </div>
</div>

<script>
function switchTab(tab){
    const currentDiv = document.getElementById('currentAssets');
    const returnedDiv = document.getElementById('returnedAssets');
    const currentBtn = document.getElementById('tabCurrent');
    const returnedBtn = document.getElementById('tabReturned');

    if(tab === 'current'){
        currentDiv.classList.remove('hidden');
        returnedDiv.classList.add('hidden');
        currentBtn.className = "tab-active pb-3 text-sm font-semibold";
        returnedBtn.className = "tab-inactive pb-3 text-sm font-medium hover:text-teal-600";
    }else{
        currentDiv.classList.add('hidden');
        returnedDiv.classList.remove('hidden');
        returnedBtn.className = "tab-active pb-3 text-sm font-semibold";
        currentBtn.className = "tab-inactive pb-3 text-sm font-medium hover:text-teal-600";
    }
}

function toggleModal(show) {
    const modal = document.getElementById('deleteModal');
    if(show) {
        modal.classList.remove('hidden');
    } else {
        modal.classList.add('hidden');
    }
}
</script>

</body>
</html>