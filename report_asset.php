<?php
include_once 'auth.php';
include_once 'db.php';
include_once 'csrf.php';

$user_id = $_SESSION['user_id'] ?? 0;

/* =========================================
   1️⃣ GET URL ID (assets.id - INT)
========================================= */
$asset_id_url = (int)($_GET['id'] ?? 0);

if ($asset_id_url <= 0) {
    die("<div class='p-10 text-center'>Invalid Asset ID.</div>");
}

/* =========================================
   2️⃣ FETCH ASSET USING PRIMARY KEY id
========================================= */
$stmt = $conn->prepare("
    SELECT asset_id, category, asset_name 
    FROM assets 
    WHERE id = ?
");
$stmt->bind_param("i", $asset_id_url);
$stmt->execute();
$result = $stmt->get_result();
$asset = $result->fetch_assoc();
$stmt->close();

if (!$asset) {
    die("<div class='p-10 text-center'>Asset not found.</div>");
}

$fk_asset_id    = $asset['asset_id'];
$asset_category = $asset['category'] ?? 'Laptop';

// Fetch components already reported for this asset
$reported_components = [];
$report_stmt = $conn->prepare("SELECT component FROM reported_items WHERE asset_id = ?");
$report_stmt->bind_param("s", $fk_asset_id);
$report_stmt->execute();
$res = $report_stmt->get_result();
while($row = $res->fetch_assoc()) {
    $parts = array_map('trim', explode(',', $row['component']));
    $reported_components = array_merge($reported_components, $parts);
}
$report_stmt->close();
$reported_components = array_unique($reported_components);

/* =========================================
   3️⃣ HANDLE FORM SUBMISSION
========================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        die("Security Validation Failed.");
    }

    $status     = trim($_POST['status'] ?? '');
    $tags_input = trim($_POST['tags_input'] ?? '');
    $remarks    = trim($_POST['remarks'] ?? '');
    
    $components = array_filter(array_map('trim', explode(',', $tags_input)));

    if ($status && !empty($components)) {
        $components_to_insert = [];

        $check_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM reported_items WHERE asset_id = ? AND component = ?");
        foreach ($components as $component) {
            $check_stmt->bind_param("ss", $fk_asset_id, $component);
            $check_stmt->execute();
            $res = $check_stmt->get_result()->fetch_assoc();
            if ($res['cnt'] == 0) { $components_to_insert[] = $component; }
        }
        $check_stmt->close();

     if (!empty($components_to_insert)) {
    $components_str = implode(', ', $components_to_insert);

    // Update asset condition **AND status**
    $update_stmt = $conn->prepare("UPDATE assets SET item_condition = ?, status = ? WHERE asset_id = ?");
    $new_status = 'Unavailable'; // Set status to Unavailable when any component is reported
    $update_stmt->bind_param("sss", $status, $new_status, $fk_asset_id);
    $update_stmt->execute();
    $update_stmt->close();

    // Insert report
    $stmt_report = $conn->prepare("INSERT INTO reported_items (asset_id, user_id, status, component, remarks) VALUES (?, ?, ?, ?, ?)");
    $stmt_report->bind_param("sisss", $fk_asset_id, $user_id, $status, $components_str, $remarks);
    $stmt_report->execute();
    $stmt_report->close();

    // Insert history
    $history_desc = "Status: $status | Components reported: $components_str";
    $action = "Maintenance Report";
    $stmt_history = $conn->prepare("INSERT INTO history (user_id, asset_id, action, description) VALUES (?, ?, ?, ?)");
    $stmt_history->bind_param("isss", $user_id, $fk_asset_id, $action, $history_desc);
    $stmt_history->execute();
    $stmt_history->close();

    header("Location: index.php?page=asset_detail&id=" . $asset_id_url . "&notif=success");
    exit;
}
    }
}   
?>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @keyframes slideIn { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }
        .notification-toast { animation: slideIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; }
        .notification-toast.hiding { animation: fadeOut 0.4s ease-in forwards; }
    </style>

<body class="bg-[#F9FAFB] font-['Inter',_sans-serif]">

<div id="notification-container" class="fixed top-6 right-6 z-[9999] flex flex-col gap-3 pointer-events-none"></div>



<div class="min-h-screen">


<div class="p-5 border-b border-slate-200 flex justify-between items-center">
   <a href="javascript:history.back()" class="text-slate-500 hover:text-[#004D2D] transition-colors flex items-center group">
        <span class="mt-2 text-[11px] font-bold uppercase tracking-widest flex items-center">
            <i class="fa-solid fa-arrow-left mr-2 transform group-hover:-translate-x-1 transition-transform"></i> 
            Back to List
        </span>
    </a>
      <h1 class="text-sm font-bold text-slate-800 uppercase tracking-tighter"><?= htmlspecialchars($asset['asset_name']) ?></h1>
</div>


    <main class="max-w-7xl mx-auto py-10 px-6">
        <form id="maintenanceForm" action="" method="POST" class="grid grid-cols-12 gap-10">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="tags_input" id="tags_final_input">

            <div class="col-span-12 lg:col-span-8 space-y-8">
                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-sm">
                    <div class="p-6 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                        <div>
                            <h2 class="text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-1">Step 01</h2>
                            <h3 class="text-sm font-bold text-slate-800"><?= htmlspecialchars($asset_category) ?> Fault Components</h3>
                        </div>
                        <span id="issueBadge" class="text-[10px] font-bold uppercase bg-slate-100 px-3 py-1.5 rounded text-slate-500 border border-slate-200 transition-all">
                            No Faults Selected
                        </span>
                    </div>

                    <div class="p-6 space-y-6">
                        <div id="selectedContainer" class="flex flex-wrap gap-2 min-h-[40px] p-3 border-2 border-dashed border-slate-100 rounded-xl">
                            <p id="emptyStateText" class="text-xs text-slate-400 italic self-center">Selected components will appear here...</p>
                        </div>

                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-tight mb-3">Suggestions</p>
                            <div id="suggestionBox" class="flex flex-wrap gap-2"></div>
                        </div>

                        <div id="otherInputContainer" class="hidden">
                            <div class="flex gap-2 p-2 bg-slate-50 rounded-lg border border-slate-200">
                                <input type="text" id="otherIssueText" placeholder="Describe other issue..." 
                                       class="flex-1 px-3 py-2 bg-white border border-slate-200 rounded text-sm outline-none focus:border-emerald-600">
                                <button type="button" id="addCustomIssueBtn" 
                                        class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 rounded text-xs font-bold transition-all">
                                    Add
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm">
                    <h2 class="text-[11px] font-bold uppercase tracking-widest text-slate-500 mb-4">Step 02: Technician Remarks</h2>
                    <textarea name="remarks" id="remarksInput" rows="6" placeholder="Describe the findings and troubleshooting steps taken..." 
                              class="w-full p-4 bg-white border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500/20 outline-none transition-all"></textarea>
                </div>
            </div>

            <div class="col-span-12 lg:col-span-4 space-y-6">
                <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm space-y-6">
                    <div class="flex flex-col gap-1.5">
                        <label class="text-[10px] font-bold text-slate-600 uppercase">Maintenance Status</label>
                        <select name="status" class="w-full p-3 bg-white border border-slate-200 rounded-lg text-sm font-semibold outline-none focus:border-emerald-600">
                            <option value="Under Repair">Under Repair</option>
                            <option value="Damaged">Damaged/Broken</option>
                            <option value="Under Maintenance">Pending Maintenance</option>
                        </select>
                    </div>
                    <button type="submit" class="w-full bg-[#004D2D] hover:bg-slate-900 text-white py-4 rounded-lg font-bold text-[11px] uppercase tracking-widest transition-all shadow-md active:scale-95">
                        Submit Maintenance Log
                    </button>
                </div>
            </div>
        </form>
    </main>
</div>

<script>
/* =========================================
   NOTIFICATION SYSTEM
========================================= */
function showNotification(message, type = 'error') {
    const container = document.getElementById('notification-container');
    const toast = document.createElement('div');
    const bgColor = type === 'success' ? 'bg-emerald-600' : 'bg-rose-600';
    const icon = type === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation';

    toast.className = `notification-toast pointer-events-auto flex items-center gap-3 min-w-[320px] ${bgColor} text-white px-4 py-3.5 rounded-xl shadow-2xl border border-white/10`;
    
    toast.innerHTML = `
        <i class="fa-solid ${icon} text-lg"></i>
        <div class="flex-1">
            <p class="text-[12px] font-bold leading-tight">${type === 'success' ? 'Task Successful' : 'Attention Required'}</p>
            <p class="text-[11px] opacity-90 mt-0.5">${message}</p>
        </div>
    `;

    container.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('hiding');
        setTimeout(() => toast.remove(), 500);
    }, 4500);
}

/* =========================================
   COMPONENT TAG LOGIC
========================================= */
const reportedComponents = <?= json_encode($reported_components) ?>;
const categoryMap = {
    'Laptop': ['Cooling fan', 'Mother Board', 'Ports', 'RAM', 'Storage', 'Battery', 'Touchpad', 'Keyboard', 'LCD'],
    'Monitor': ['LCD', 'Power Supply', 'Ports', 'Button/Controls', 'Stand/Mount'],
    'Keyboard': ['Key Switches', 'Cables', 'Chassis/Frame'],
    'Mouse': ['Left/Right Click', 'Scroll Wheel', 'Cable', 'Chassis/Body', 'Battery'],
    'Headset': ['Microphone', 'Speaker Drivers', 'Cushions', 'Cable', 'Headband/Frame', 'Battery'],
    'Charger': ['AC Wall Plug', 'DC Connector', 'Cable/Cord', 'Charger Body']
};

const currentCategory = "<?= $asset_category ?>";
const selectedTags = new Set();
const suggestionBox = document.getElementById('suggestionBox');
const selectedContainer = document.getElementById('selectedContainer');
const otherIssueInput = document.getElementById('otherIssueText');
const otherInputContainer = document.getElementById('otherInputContainer');
const emptyStateText = document.getElementById('emptyStateText');

function renderSuggestions() {
    suggestionBox.innerHTML = '';
    let items = categoryMap[currentCategory] || categoryMap['Laptop'];
    let availableItems = items.filter(item => !selectedTags.has(item));

    availableItems.forEach(item => {
        const btn = createPill(item, false);
        if (reportedComponents.includes(item)) {
            btn.disabled = true;
            btn.classList.add('opacity-40', 'cursor-not-allowed', 'bg-slate-50');
            btn.title = "Already reported and active";
        } else {
            btn.onclick = () => addTag(item);
        }
        suggestionBox.appendChild(btn);
    });

    const otherBtn = createPill('Others +', false);
    otherBtn.classList.add('border-dashed', 'text-emerald-600', 'border-emerald-200');
    otherBtn.onclick = () => otherInputContainer.classList.toggle('hidden');
    suggestionBox.appendChild(otherBtn);
}

function createPill(text, isSelected) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.textContent = text;
    if (isSelected) {
        btn.className = "px-3 py-1.5 bg-emerald-700 border border-emerald-800 rounded-lg text-xs font-bold text-white shadow-sm flex items-center gap-2";
        const icon = document.createElement('i');
        icon.className = "fa-solid fa-xmark text-[10px] opacity-70";
        btn.appendChild(icon);
    } else {
        btn.className = "px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-semibold text-slate-600 hover:border-emerald-500 hover:text-emerald-600 transition-all shadow-sm";
    }
    return btn;
}

function addTag(tag) {
    if(selectedTags.has(tag)) {
        showNotification("This component is already selected.", "error");
        return;
    }
    selectedTags.add(tag);
    refreshUI();
}

function removeTag(tag) {
    selectedTags.delete(tag);
    refreshUI();
}

function refreshUI() {
    document.getElementById('tags_final_input').value = Array.from(selectedTags).join(', ');
    selectedContainer.innerHTML = '';
    if (selectedTags.size === 0) {
        selectedContainer.appendChild(emptyStateText);
    } else {
        selectedTags.forEach(tag => {
            const pill = createPill(tag, true);
            pill.onclick = () => removeTag(tag);
            selectedContainer.appendChild(pill);
        });
    }
    const badge = document.getElementById('issueBadge');
    const count = selectedTags.size;
    badge.textContent = count > 0 ? `${count} Selected` : "No Faults Selected";
    badge.className = count > 0 
        ? "text-[10px] font-bold uppercase bg-emerald-100 text-emerald-700 px-3 py-1.5 rounded border border-emerald-200" 
        : "text-[10px] font-bold uppercase bg-slate-100 px-3 py-1.5 rounded text-slate-500 border border-slate-200";
    renderSuggestions();
}

/* =========================================
   FORM VALIDATION & EVENTS
========================================= */
document.getElementById('maintenanceForm').addEventListener('submit', function(e) {
    if (selectedTags.size === 0) {
        e.preventDefault();
        showNotification("You must select at least one faulty component.", "error");
        return;
    }
    
    const remarks = document.getElementById('remarksInput').value.trim();
    if (remarks.length < 3) {
        e.preventDefault();
        showNotification("Please provide brief technician remarks.", "error");
        return;
    }
});

document.getElementById('addCustomIssueBtn').addEventListener('click', () => {
    const val = otherIssueInput.value.trim();
    if(val) {
        addTag(val);
        otherIssueInput.value = '';
        otherInputContainer.classList.add('hidden');
    }
});

otherIssueInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') { 
        e.preventDefault(); 
        document.getElementById('addCustomIssueBtn').click(); 
    }
});

renderSuggestions();
</script>
</body>
</html>