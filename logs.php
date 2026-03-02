<?php
include 'db.php';
include 'auth.php';

$pageTitle = "Activity Logs - Timeline";

/* ================= FETCH LOGS ================= */
$sql = "
    SELECT h.*, e.full_name AS employee_name, u.username AS user_name
    FROM history h
    LEFT JOIN employees e ON h.employee_id = e.employee_id
    LEFT JOIN users u ON h.user_id = u.id
    ORDER BY h.timestamp DESC
";
$result = $conn->query($sql);

$logs = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
}

/* ================= FETCH UNIQUE ACTIONS ================= */
$uniqueActions = [];
$actionQuery = "SELECT DISTINCT action FROM history ORDER BY action ASC";
$actionResult = $conn->query($actionQuery);

if ($actionResult) {
    while ($row = $actionResult->fetch_assoc()) {
        if (!empty($row['action'])) {
            $uniqueActions[] = $row['action'];
        }
    }
}

/* ================= FETCH UNIQUE ADMINS ================= */
$uniqueAdmins = [];
$adminQuery = "SELECT DISTINCT u.username FROM history h LEFT JOIN users u ON h.user_id = u.id WHERE u.username IS NOT NULL ORDER BY u.username ASC";
$adminResult = $conn->query($adminQuery);

if ($adminResult) {
    while ($row = $adminResult->fetch_assoc()) {
        $uniqueAdmins[] = $row['username'];
    }
}

/* ================= STATUS ICON FUNCTION ================= */
function getActionIcon($action) {
    $map = [
        'added' => 'fa-plus',
        'assigned' => 'fa-handshake',
        'damaged' => 'fa-triangle-exclamation',
        'under repair' => 'fa-screwdriver-wrench',
        'repaired' => 'fa-check'
    ];
    $lower = strtolower($action);
    return isset($map[$lower]) ? $map[$lower] : 'fa-info-circle';
}

/* ================= UNIFORM STYLE ================= */
$uniformStyle = [
    'bg' => 'bg-gray-100', 
    'text' => 'text-gray-700', 
    'border' => 'border-gray-200'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?></title>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
.timeline-line {
    position: absolute;
    left: 21px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e5e7eb;
}
</style>
</head>

<body class="bg-slate-50 min-h-screen">

<div class="max-w-5xl mx-auto px-6 py-10">

    <!-- HEADER -->
    <header class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-3xl font-extrabold text-gray-900">
                  <i class="fas fa-clipboard-list text-gray-700"></i> Activity Logs</h1>
            <p class="mt-2 text-gray-500">Track all asset history and user actions</p>
        </div>
    </header>

    <!-- FILTER -->
    <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-200 mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex items-center gap-3">

            <!-- Action Filter -->
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400">
                    Filter History
                </label>
                <select id="actionFilter" class="block w-full bg-transparent border-none text-sm font-semibold text-gray-700 focus:ring-0 cursor-pointer">
                    <option value="all">All Activities</option>
                    <?php foreach($uniqueActions as $act): ?>
                        <option value="<?= strtolower($act) ?>"><?= ucfirst($act) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Admin Filter -->
            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-gray-400">
                    Filter by Admin
                </label>
                <select id="adminFilter" class="block w-full bg-transparent border-none text-sm font-semibold text-gray-700 focus:ring-0 cursor-pointer">
                    <option value="all">All Admins</option>
                    <?php foreach($uniqueAdmins as $admin): ?>
                        <option value="<?= strtolower($admin) ?>"><?= htmlspecialchars($admin) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

        </div>

        <div class="text-right">
            <span id="logCount" class="text-xs font-medium bg-slate-100 px-2 py-1 rounded-md text-slate-600">
                <?= count($logs) ?> entries
            </span>
        </div>
    </div>

    <!-- TIMELINE -->
    <div class="relative">
        <?php if(!empty($logs)): ?>
            <div class="timeline-line"></div>
            <?php 
            $currentDate = "";
            foreach($logs as $log):
                $dateDisplay = date('F d, Y', strtotime($log['timestamp']));
                $isNewDay = ($dateDisplay !== $currentDate);
                $currentDate = $dateDisplay;
                $icon = getActionIcon($log['action']);
            ?>
                <?php if($isNewDay): ?>
                    <div class="date-header relative z-10 my-6">
                        <span class="bg-slate-50 pr-4 text-sm font-bold text-gray-400 uppercase tracking-widest"><?= $dateDisplay ?></span>
                    </div>
                <?php endif; ?>

                <div class="log-item relative pl-12 mb-6" 
                     data-action="<?= strtolower($log['action']) ?>" 
                     data-admin="<?= strtolower($log['user_name']) ?>">

                    <!-- ICON -->
                    <div class="absolute left-0 top-1 w-11 h-11 rounded-full border-4 border-grey-900 
                                <?= $uniformStyle['bg'] ?> <?= $uniformStyle['text'] ?> 
                                flex items-center justify-center shadow-sm">
                        <i class="fas <?= $icon ?> text-sm"></i>
                    </div>

                    <!-- CARD -->
                    <div class="bg-white border <?= $uniformStyle['border'] ?> 
                                rounded-xl p-5 shadow-sm hover:shadow-md transition-shadow">

                        <div class="flex justify-between items-center mb-3">
                            <span class="px-2.5 py-0.5 rounded text-xs font-bold uppercase 
                                         <?= $uniformStyle['bg'] ?> <?= $uniformStyle['text'] ?>">
                                <?= htmlspecialchars($log['action']) ?>
                            </span>

                            <time class="text-xs text-gray-400">
                                <i class="far fa-clock mr-1"></i>
                                <?= date('h:i A', strtotime($log['timestamp'])) ?>
                            </time>
                        </div>

<?php if($log['description']): ?>
<div class="mb-2">
    <span class="text-xs font-bold uppercase text-gray-400 tracking-wider">Description</span>
</div>
<p class="text-gray-600 text-sm mb-3">
    <?php
        $desc = $log['description'];
        if (!empty($log['employee_id']) && !empty($log['employee_name'])) {
            $desc = str_replace($log['employee_id'], $log['employee_name'], $desc);
        }
        echo nl2br(htmlspecialchars($desc));
    ?>
</p>
<?php endif; ?>


                        <?php if($log['user_name']): ?>
                            <span class="text-xs text-gray-500"><i class="fas fa-user-shield mr-1"></i> Admin: <?= htmlspecialchars($log['user_name']) ?></span>
                        <?php endif; ?>

                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-center text-gray-500 py-10">No history found.</p>
        <?php endif; ?>
    </div>
</div>

<!-- FILTER SCRIPT -->
<script>
function filterLogs() {
    const actionVal = document.getElementById('actionFilter').value;
    const adminVal = document.getElementById('adminFilter').value;
    const items = document.querySelectorAll('.log-item');
    let visibleCount = 0;

    items.forEach(item => {
        const actionMatch = (actionVal === 'all' || item.dataset.action === actionVal);
        const adminMatch = (adminVal === 'all' || item.dataset.admin === adminVal);

        if (actionMatch && adminMatch) {
            item.style.display = 'block';
            visibleCount++;
        } else {
            item.style.display = 'none';
        }
    });

    document.getElementById('logCount').textContent = visibleCount + " entries";

    // Hide empty date headers
    document.querySelectorAll('.date-header').forEach(header => {
        let next = header.nextElementSibling;
        let hasVisible = false;
        while(next && next.classList.contains('log-item')) {
            if(next.style.display !== 'none') { hasVisible = true; break; }
            next = next.nextElementSibling;
        }
        header.style.display = hasVisible ? 'block' : 'none';
    });
}

document.getElementById('actionFilter').addEventListener('change', filterLogs);
document.getElementById('adminFilter').addEventListener('change', filterLogs);
</script>

</body>
</html>