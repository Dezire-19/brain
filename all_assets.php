<?php
include 'db.php';
include 'auth.php';
include 'csrf.php'; // CSRF protection

// -------------------------
// Get filters safely
// -------------------------
$search   = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$filter   = $_GET['filter'] ?? '';

$limit    = 50;
$page_num = max(1, (int)($_GET['page_num'] ?? 1));
$offset = ($page_num - 1) * $limit;

// -------------------------
// Whitelist filter and category
// -------------------------
$validFilters = ['Available', 'Assigned', 'Unavailable'];
$validCategories = ['Laptop','Monitor','Headset','Mouse','Keyboard','Charger'];

if (!in_array($filter, $validFilters)) $filter = '';
if (!in_array($category, $validCategories)) $category = '';

// -------------------------
// Fetch assets
// -------------------------
$sql = "SELECT a.*, e.full_name,
        CASE 
          WHEN a.item_condition IN ('Damaged','Under Repair') THEN 'Unavailable'
          ELSE a.status
        END AS asset_status
        FROM assets a 
        LEFT JOIN employees e ON a.employee_id = e.employee_id 
        WHERE a.deleted = 0 
          AND (a.asset_id LIKE ? OR a.asset_name LIKE ? OR e.full_name LIKE ?)";
$params = ["%$search%", "%$search%", "%$search%"];

if (!empty($category)) {
    $sql .= " AND a.category = ?";
    $params[] = $category;
}

if (!empty($filter)) {
    if ($filter === 'Unavailable') {
        $sql .= " AND a.item_condition IN ('Damaged','Under Repair')";
    } else {
        $sql .= " AND a.status = ?";
        $params[] = $filter;
    }
}

$sql .= " ORDER BY a.asset_id ASC LIMIT ? OFFSET ?";
$params[] = (int)$limit;
$params[] = (int)$offset;

$types = str_repeat('s', count($params)-2) . 'ii';
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Asset Management</title>
<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
/* Base State */
.delete-btn {
  background: none;
  border: none;
  cursor: pointer;
  padding: 4px;
  color: #64748b; /* Original Muted Grey */
  transition: color 0.2s ease;
  position: relative;
  z-index: 10;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: visible; /* Vital for the lid movement */
}

/* Hover State - Only changes the stroke color */
.delete-btn:hover {
  color: #ef4444; /* Clean Red */
}

/* SVG stroke inheritance */
.trash-icon {
  width: 22px;
  height: 22px;
  overflow: visible;
}

/* This ensures the SVG paths use the color defined in .delete-btn */
.trash-icon path {
  stroke: currentColor; 
}

/* The Animation Logic */
.lid {
  transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  transform-origin: 3px 8px; /* Adjusted pivot point */
}

.delete-btn:hover .lid {
  /* Lifts and rotates the lid */
  transform: rotate(-45deg) translate(-2px, -3px);
}
:root {
    --primary: #004d2d;
    --primary-hover: #00361f;
    --bg-body: #f8fafc;
    --text-main: #1e293b;
    --text-muted: #64748b;
    --border-color: #e2e8f0;
}
body { background-color: var(--bg-body); margin:0; font-family: 'Public Sans', sans-serif; }
.assets-container { padding: 30px 15px; color: var(--text-main); max-width:1200px; margin:0 auto; }
.header-section h1 { font-size:28px; font-weight:700; margin:0 0 8px 0; color:#001d11; }
.header-section p { font-size:15px; color: var(--text-muted); margin:0 0 15px 0; }
.filter-toolbar { display:flex; flex-wrap:wrap; justify-content:space-between; gap:15px; border-bottom:1px solid var(--border-color); padding-bottom:15px; margin-bottom:20px; }
.filter-left, .filter-right { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
.pill-group { display:flex; flex-wrap:wrap; gap:6px; background:#edeff2; padding:4px; border-radius:30px; }
.pill-btn { padding:6px 16px; border-radius:20px; font-size:13px; font-weight:600; text-decoration:none; color:var(--text-muted); transition:all 0.2s; }
.pill-btn.active { background:white; color:var(--primary); box-shadow:0 2px 4px rgba(0,0,0,0.05); }
.pill-btn:not(.active):hover { color:var(--text-main); }
.search-wrapper { position:relative; min-width:150px; flex:1; }
.search-wrapper i { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:14px; }
.search-input-slim { width:100%; padding:10px 15px 10px 35px; border:1px solid var(--border-color); border-radius:10px; font-size:14px; outline:none; transition:border-color 0.2s; }
.search-input-slim:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(0,77,45,0.05); }
.select-minimal { padding:10px 15px; border:1px solid var(--border-color); border-radius:5px; background:white; font-size:14px; cursor:pointer; color:var(--text-main); }
.export-btn { background:white; border:1px solid var(--border-color); padding:10px 15px; border-radius:5px; color:var(--text-main); text-decoration:none; transition:background 0.2s; }
.export-btn:hover { background:#f1f5f9; }
.card-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(220px,1fr)); gap:12px; }
.asset-card { background:white; border:1px solid var(--border-color); border-radius:8px; padding:15px; display:flex; flex-direction:column; text-decoration:none; min-height:170px; cursor:pointer; transition:all 0.3s ease; }
.asset-card:hover { transform:translateY(-4px); border-color:var(--primary); box-shadow:0 10px 15px -3px rgba(0,0,0,0.05); }
.asset-card h3 { font-size:16px; margin:0 0 6px 0; display:flex; align-items:center; gap:8px; }
.asset-card p { font-size:13px; margin:3px 0; color:#475569; }
.asset-card .label { font-size:11px; text-transform:uppercase; color:#94a3b8; margin-top:auto; font-weight:700; letter-spacing:0.5px; }
.asset-card .condition { font-weight:700; font-size:13px; margin-top:2px; }
.add-card-link { border:2px dashed #cbd5e1; justify-content:center; align-items:center; display:flex; }
.add-card-content { text-align:center; color:var(--primary); }
.add-card-content i { font-size:32px; margin-bottom:10px; }
.add-card-content span { font-weight:700; display:block; }
.add-card-link:hover { background:#f0fdf4; border-style:solid; }

#success-modal { position:fixed; inset:0; background:rgba(0,0,0,0.4); display:none; align-items:center; justify-content:center; z-index:100; }
#success-modal.show { display:flex; }
.modal-content { background:white; padding:25px; border-radius:20px; text-align:center; width:90%; max-width:350px; }
.delete-btn img { width:16px; }

@media (max-width:640px){
  .filter-toolbar { flex-direction:column; align-items:stretch; gap:10px; }
  .filter-left, .filter-right { width:100%; justify-content:space-between; }
  .pill-group { justify-content:flex-start; flex-wrap:wrap; }
  .search-wrapper { min-width:100%; }
}
</style>
</head>
<body>

<div class="assets-container">
  <div class="flex items-center mb-2">
    <i class="fas fa-boxes-packing text-emerald-900 text-3xl mr-3"></i>
    <h1 class="text-3xl font-extrabold uppercase tracking-tight text-gray-700">Company Assets</h1>
  </div>
  <p class="text-gray-500 text-sm mb-4">Manage, track, and assign hardware inventory across the organization.</p>

  <div class="filter-toolbar">
    <form method="GET" id="filterForm" class="filter-left">
      <input type="hidden" name="page" value="assets">
      <div class="pill-group">
        <?php
        $filterOptions = ['' => 'All', 'Available' => 'Available', 'Assigned' => 'Assigned', 'Unavailable' => 'Unavailable'];
        foreach ($filterOptions as $val => $label):
          $active = ($filter === $val) ? 'active' : '';
          $url = 'index.php?' . http_build_query(['page'=>'assets','filter'=>$val,'search'=>$search,'category'=>$category]);
        ?>
          <a href="<?= $url ?>" class="pill-btn <?= $active ?>"><?= $label ?></a>
        <?php endforeach; ?>
      </div>

      <div class="search-wrapper">
        <i class="fas fa-search"></i>
        <input type="text" name="search" id="liveSearch" class="search-input-slim" placeholder="Search ID, name, or employee..." value="<?= htmlspecialchars($search, ENT_QUOTES) ?>">
      </div>
    </form>

    <div class="filter-right">
      <select name="category" class="select-minimal" form="filterForm" onchange="this.form.submit()">
        <option value="">All Categories</option>
        <?php foreach($validCategories as $c): ?>
          <option value="<?= $c ?>" <?= $category===$c?'selected':'' ?>><?= $c ?></option>
        <?php endforeach; ?>
      </select>

      <a href="index.php?page=asset_report2" class="export-btn" title="Export CSV">
        <i class="fas fa-file-export"></i>
      </a>
    </div>
  </div>

  <div class="card-grid">
    <?php if (empty($search) && empty($category) && empty($filter)): ?>
      <a href="index.php?page=add_asset" class="asset-card add-card-link">
        <div class="add-card-content">
          <i class="fas fa-plus-circle"></i>
          <span>Add New Asset</span>
        </div>
      </a>
    <?php endif; ?>

    <?php
    $categoryEmoji = ['Laptop'=>'💻','Monitor'=>'🖥️','Keyboard'=>'⌨️','Mouse'=>'🖱️','Charger'=>'⚡','Headset'=>'🎧'];
    while ($row = $result->fetch_assoc()):
      $emoji = $categoryEmoji[$row['category']] ?? '📦';
      $searchData = htmlspecialchars(strtolower($row['asset_id'].' '.$row['asset_name'].' '.($row['full_name']??'')), ENT_QUOTES);
    ?>
      <div class="asset-card" data-search="<?= $searchData ?>" onclick="location.href='index.php?page=asset_detail&id=<?= (int)$row['id'] ?>'">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
          <h3><?= $emoji ?> <?= htmlspecialchars($row['asset_id'], ENT_QUOTES) ?></h3>
          <?php if ($_SESSION['role']==='Manager'): ?>
          <form method="POST" action="index.php?page=delete_asset" onsubmit="return confirm('Dispose this asset?');">
            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="return" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES) ?>">
          <button type="submit" class="delete-btn" title="Dispose Asset">
  <svg class="trash-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
    <g class="lid">
      <path d="M5 7H19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
      <path d="M9 7V4H15V7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </g>
    <path d="M6 7H18V18C18 19.1046 17.1046 20 16 20H8C6.89543 20 6 19.1046 6 18V7Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/>
    <path d="M10 11V16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    <path d="M14 11V16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
  </svg>
</button>
          </form>
          <?php endif; ?>
        </div>
        <div style="flex:1; display:flex; flex-direction:column;">
          <p><strong><?= htmlspecialchars($row['asset_name'], ENT_QUOTES) ?></strong></p>
          <p style="font-size:13px;">
            <i class="fa-solid fa-user" style="margin-right:5px;opacity:0.7;"></i>
            <?= !empty($row['full_name'])?htmlspecialchars($row['full_name'], ENT_QUOTES):'<span style="color:#94a3b8">Unassigned</span>' ?>
          </p>
          <span class="label">Status</span>
          <p class="condition" style="color:
            <?= $row['asset_status']=='Available'?'#16a34a':($row['asset_status']=='Assigned'?'#f59e0b':'#ef4444') ?>;">
            <?= htmlspecialchars($row['asset_status'], ENT_QUOTES) ?>
          </p>
        </div>
      </div>
    <?php endwhile; ?>
  </div>
</div>

<?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
<div id="success-modal" class="show">
    <div class="modal-content">
        <i class="fa-solid fa-circle-check" style="color:#16a34a; font-size: 40px; margin-bottom: 15px;"></i>
        <h2 style="margin:0 0 10px 0;">Success!</h2>
        <p style="color:#64748b; margin-bottom: 20px;">Asset has been saved successfully.</p>
        <button onclick="document.getElementById('success-modal').classList.remove('show')" style="background:#16a34a; color:white; border:none; padding:10px 25px; border-radius:10px; cursor:pointer; font-weight:600;">OK</button>
    </div>
</div>
<?php endif; ?>

<script>
// LIVE SEARCH
document.getElementById("liveSearch").addEventListener("input", function(){
  const query = this.value.toLowerCase().trim();
  document.querySelectorAll(".asset-card:not(.add-card-link)").forEach(card=>{
    card.style.display = card.getAttribute("data-search").includes(query) ? "flex" : "none";
  });
});
</script>

</body>
</html>