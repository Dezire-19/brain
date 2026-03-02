<?php  
include 'db.php';
include 'auth.php';

/* ---------- 2. Fetch ALL Assets ---------- */
$all_assets_query = "SELECT category, status, item_condition, DATE_FORMAT(date_acquired, '%b') as acq_month, DATE_FORMAT(disposed_date, '%b') as disp_month FROM assets WHERE deleted = 0";
$all_assets_result = mysqli_query($conn, $all_assets_query);
$all_assets_raw = [];
while($row = mysqli_fetch_assoc($all_assets_result)) {
    $all_assets_raw[] = $row;
}

/* ---------- 3. Fetch Total Employees ---------- */
$employees_query = "SELECT COUNT(*) as total_employees FROM employees WHERE deleted = 0";
$employees_result = mysqli_query($conn, $employees_query);
$employees = mysqli_fetch_assoc($employees_result);

/* ---------- 4. Asset Growth Trend ---------- */
$trend_query = "
    SELECT 
        months.month,
        IFNULL(acq.acquired, 0) AS acquired,
        IFNULL(dis.disposed, 0) AS disposed,
        months.sort_date
    FROM (
        SELECT DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL seq MONTH), '%Y-%m') AS sort_date,
               DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL seq MONTH), '%b') AS month
        FROM (
            SELECT 0 seq UNION SELECT 1 UNION SELECT 2 
            UNION SELECT 3 UNION SELECT 4 UNION SELECT 5
        ) s
    ) months
    LEFT JOIN (
        SELECT 
            DATE_FORMAT(date_acquired, '%Y-%m') AS sort_date,
            COUNT(*) AS acquired
        FROM assets
        WHERE date_acquired >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY sort_date
    ) acq ON months.sort_date = acq.sort_date
    LEFT JOIN (
        SELECT 
            DATE_FORMAT(disposed_date, '%Y-%m') AS sort_date,
            COUNT(*) AS disposed
        FROM assets
        WHERE disposed_date IS NOT NULL
          AND disposed_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY sort_date
    ) dis ON months.sort_date = dis.sort_date
    ORDER BY months.sort_date ASC
";
$trend_result = mysqli_query($conn, $trend_query);
$trend_data = mysqli_fetch_all($trend_result, MYSQLI_ASSOC);

/* ---------- 7. Initial Availability Counts ---------- */
$status_health_query = "
    SELECT 
        COUNT(*) as total_assets,
        SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) as available,
        SUM(CASE WHEN status = 'Assigned' THEN 1 ELSE 0 END) as assigned,
        SUM(CASE WHEN status NOT IN ('Available','Assigned') THEN 1 ELSE 0 END) as unavailable
    FROM assets WHERE deleted = 0
";
$status_health_result = mysqli_query($conn, $status_health_query);
$status_health_counts = mysqli_fetch_assoc($status_health_result);
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Management Dashboard</title>
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.2); }
    </style>
</head>
<body class="h-full bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 overflow-auto">
<div class="w-full min-h-full p-4 md:p-6 lg:p-8">

<header class="mb-6">
  <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
          <h1 class="text-2xl md:text-3xl font-bold bg-gradient-to-r from-slate-800 via-[#374151] to-[#374151] bg-clip-text text-transparent">Asset Management Dashboard</h1>
          <p class="text-slate-500 mt-1 text-sm">Real-time overview of your organization's assets</p>
      </div>
      <div class="flex items-center gap-3">
          <div class="px-3 py-1.5 bg-white rounded-full shadow-sm border border-slate-200">
              <span class="text-sm text-slate-600" id="current-date"></span>
          </div>
      </div>
  </div>
</header>

<section class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
  <div class="lg:col-span-2 glass-card rounded-xl p-4 shadow-md">
      <div class="flex items-center justify-between mb-3">
          <div>
              <h2 class="text-base font-semibold text-slate-800">Asset Activity Trend</h2>
              <p class="text-xs text-slate-500">Click a point to filter charts by month</p>
          </div>
          <button id="resetTrend" class="hidden px-2 py-1 text-xs bg-indigo-50 text-indigo-600 rounded border border-indigo-100 hover:bg-indigo-100 transition-colors">Reset Filter</button>
      </div>
      <div class="h-48">
          <canvas id="trendChart"></canvas>
      </div>
  </div>

  <div class="glass-card rounded-xl p-4 shadow-md">
      <div class="mb-3">
          <h2 class="text-base font-semibold text-slate-800">Asset Availability</h2>
          <p class="text-xs text-slate-500" id="healthSubtext">Status distribution (All Time)</p>
      </div>
      <div class="h-32 flex items-center justify-center">
          <canvas id="healthChart"></canvas>
      </div>
      <div class="mt-3 space-y-1.5" id="healthLegend">
          <div class="flex items-center justify-between text-xs">
              <div class="flex items-center gap-1.5">
                  <span class="w-2 h-2 bg-emerald-500 rounded-full"></span> 
                  <span class="text-slate-600">Available</span>
              </div>
              <span class="font-semibold text-slate-800" id="val-available">0%</span>
          </div>
          <div class="flex items-center justify-between text-xs">
              <div class="flex items-center gap-1.5">
                  <span class="w-2 h-2 bg-indigo-500 rounded-full"></span> 
                  <span class="text-slate-600">Assigned</span>
              </div>
              <span class="font-semibold text-slate-800" id="val-assigned">0%</span>
          </div>
          <div class="flex items-center justify-between text-xs">
              <div class="flex items-center gap-1.5">
                  <span class="w-2 h-2 bg-rose-500 rounded-full"></span> 
                  <span class="text-slate-600">Unavailable</span>
              </div>
              <span class="font-semibold text-slate-800" id="val-unavailable">0%</span>
          </div>
      </div>
  </div>
</section>

<section class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
  <div class="glass-card rounded-xl p-4 shadow-md">
      <div class="mb-3">
          <h2 class="text-base font-semibold text-slate-800">Assets by Category</h2>
          <p class="text-xs text-slate-500" id="categorySubtext">Department distribution</p>
      </div>
      <div class="h-48">
          <canvas id="categoryChart"></canvas>
      </div>
  </div>
  
  <div class="glass-card rounded-xl p-4 shadow-md">
      <div class="mb-3">
          <h2 class="text-base font-semibold text-slate-800">Status Overview</h2>
          <p class="text-xs text-slate-500" id="statusSubtext">Asset status breakdown</p>
      </div>
      <div class="h-48">
          <canvas id="statusChart"></canvas>
      </div>
  </div>
</section>

<footer class="mt-6 text-center text-xs text-slate-500">
    <p>© 2026 Asset Management System. All rights reserved.</p>
</footer>

<script>
const trendDataRaw = <?php echo json_encode($trend_data); ?>;
const allAssetsRaw = <?php echo json_encode($all_assets_raw); ?>;

let healthChart, categoryChart, statusChart;
let currentTotal = <?php echo $status_health_counts['total_assets']; ?>;

function updateCharts(filterMonth = null) {
    let filtered;
    if (filterMonth) {
        filtered = allAssetsRaw.filter(a => a.acq_month === filterMonth || a.disp_month === filterMonth);
        document.getElementById('healthSubtext').textContent = `Status distribution (${filterMonth})`;
        document.getElementById('categorySubtext').textContent = `Department distribution (${filterMonth})`;
        document.getElementById('statusSubtext').textContent = `Asset status breakdown (${filterMonth})`;
        document.getElementById('resetTrend').classList.remove('hidden');
    } else {
        filtered = allAssetsRaw;
        document.getElementById('healthSubtext').textContent = "Status distribution (All Time)";
        document.getElementById('categorySubtext').textContent = "Department distribution";
        document.getElementById('statusSubtext').textContent = "Asset status breakdown";
        document.getElementById('resetTrend').classList.add('hidden');
    }

    // 1. Update Availability (Doughnut)
    const availabilityCounts = { Available: 0, Assigned: 0, Unavailable: 0 };
    filtered.forEach(a => {
        if (a.status === 'Available') availabilityCounts.Available++;
        else if (a.status === 'Assigned') availabilityCounts.Assigned++;
        else availabilityCounts.Unavailable++;
    });

    const total = filtered.length || 1;
    currentTotal = filtered.length;
    
    document.getElementById('val-available').textContent = Math.round((availabilityCounts.Available / total) * 100) + '%';
    document.getElementById('val-assigned').textContent = Math.round((availabilityCounts.Assigned / total) * 100) + '%';
    document.getElementById('val-unavailable').textContent = Math.round((availabilityCounts.Unavailable / total) * 100) + '%';

    healthChart.data.datasets[0].data = [
        Math.round((availabilityCounts.Available / total) * 100), 
        Math.round((availabilityCounts.Assigned / total) * 100), 
        Math.round((availabilityCounts.Unavailable / total) * 100)
    ];
    healthChart.update();

    // 2. Update Categories (Bar)
    const catCounts = {};
    filtered.forEach(a => { catCounts[a.category] = (catCounts[a.category] || 0) + 1; });
    const updatedCatData = Object.keys(catCounts).map(cat => ({ category: cat, count: catCounts[cat] })).sort((a, b) => b.count - a.count);

    categoryChart.data.labels = updatedCatData.map(d => d.category);
    categoryChart.data.datasets[0].data = updatedCatData.map(d => d.count);
    categoryChart.update();

    // 3. Update Status Overview (Polar Area - With "Spin" Animation)
    const conditionMap = { 'Good': 'Operational', 'Damaged': 'Damaged', 'Under Repair': 'Under Repair', 'Under Maintenance': 'Under Maintenance' };
    const condCounts = { 'Operational': 0, 'Damaged': 0, 'Under Repair': 0, 'Under Maintenance': 0 };
    
    filtered.forEach(a => {
        const label = conditionMap[a.item_condition] || 'Operational';
        if (condCounts.hasOwnProperty(label)) condCounts[label]++;
    });

    statusChart.data.datasets[0].data = [
        condCounts['Operational'], 
        condCounts['Damaged'], 
        condCounts['Under Repair'], 
        condCounts['Under Maintenance']
    ];

    // Triggering 'active' mode forces the rotate/scale animation engine
    statusChart.update(filterMonth ? 'active' : 'default');
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('current-date').textContent = new Date().toLocaleDateString('en-US', { weekday:'short', month:'short', day:'numeric', year:'numeric' });

    // 1. Trend Chart
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    const trendChart = new Chart(trendCtx, {
        type:'line',
        data:{ 
            labels: trendDataRaw.map(d => d.month), 
            datasets:[
                { label:'Acquired', data: trendDataRaw.map(d => parseInt(d.acquired) || 0), borderColor:'#10b981', backgroundColor:'rgba(16,185,129,0.1)', fill:true, tension:0.4, borderWidth:2, pointBackgroundColor:'#10b981', pointRadius:6 },
                { label:'Disposed', data: trendDataRaw.map(d => parseInt(d.disposed) || 0), borderColor:'#ef4444', backgroundColor:'rgba(239,68,68,0.1)', fill:true, tension:0.4, borderWidth:2, pointBackgroundColor:'#ef4444', pointRadius:6 }
            ]
        },
        options:{ 
            responsive:true, maintainAspectRatio:false,
            onClick: (e, elements) => { if (elements.length > 0) updateCharts(trendChart.data.labels[elements[0].index]); },
            plugins: { tooltip: { intersect: false, mode: 'index' } }
        }
    });

    // 2. Health Chart (Doughnut)
    healthChart = new Chart(document.getElementById('healthChart').getContext('2d'), {
        type:'doughnut',
        data:{ labels:['Available','Assigned','Unavailable'], datasets:[{ data:[0,0,0], backgroundColor:['#10b981','#6366f1','#ef4444'], borderWidth:0 }] },
        options:{ responsive:true, maintainAspectRatio:false, cutout:'65%', plugins:{ legend:{ display:false } } },
        plugins:[{
            id:'centerText',
            beforeDraw:(chart)=>{
                const {ctx,width,height}=chart; ctx.save();
                ctx.font='bold 16px "Plus Jakarta Sans"'; ctx.fillStyle='#374151'; ctx.textAlign='center'; ctx.textBaseline='middle';
                ctx.fillText(currentTotal,width/2,height/2-8);
                ctx.font='12px "Plus Jakarta Sans"'; ctx.fillStyle='#6b7280'; ctx.fillText('Assets',width/2,height/2+12);
            }
        }]
    });

    // 3. Category Chart (Bar)
    categoryChart = new Chart(document.getElementById('categoryChart').getContext('2d'), {
        type:'bar',
        data:{ labels: [], datasets:[{ label:'Assets', data: [], backgroundColor:['rgba(99,102,241,0.8)','rgba(139,92,246,0.8)','rgba(236,72,153,0.8)','rgba(14,165,233,0.8)','rgba(16,185,129,0.8)'], borderRadius:6 }] },
        options:{ 
            responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false } },
            animations: {
                y: { duration: 1000, easing: 'easeOutQuart' },
                height: { duration: 1000, easing: 'easeOutQuart' }
            },
            scales: { y: { beginAtZero: true, grid: { display: false } }, x: { grid: { display: false } } }
        }
    });

    // 4. Status Chart (Polar Area - THE RE-BALANCING SPIN ANIMATION)
    statusChart = new Chart(document.getElementById('statusChart').getContext('2d'), {
        type:'polarArea',
        data:{ 
            labels: ['Operational', 'Damaged', 'Under Repair', 'Under Maintenance'], 
            datasets:[{ 
                data: [0,0,0,0], 
                backgroundColor:['rgba(16,185,129,0.8)','rgba(239,68,68,0.8)','rgba(245,158,11,0.8)','rgba(59,130,246,0.8)'], 
                borderWidth:0 
            }] 
        },
        options:{ 
            responsive:true, 
            maintainAspectRatio:false,
            animation: {
                duration: 1200,
                easing: 'easeOutQuart',
                animateRotate: true, // Spins the colors to fill gaps
                animateScale: true   // Shrinks/Grows colors from center
            },
            plugins:{ 
                legend:{ 
                    position:'right', 
                    labels:{ usePointStyle:true, font:{ size:10 } } 
                } 
            } 
        }
    });

    updateCharts();
    document.getElementById('resetTrend').addEventListener('click', () => updateCharts(null));
});
</script>
</body>
</html>