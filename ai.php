<?php
// index.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Neural Asset Interface</title>

<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
:root {
    --bg: #050608;
    --panel: #0f111a;
    --accent: #6366f1;
    --text-main: #f8fafc;
    --text-dim: #64748b;
    --border: #1e293b;
}

body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: var(--bg);
    color: var(--text-main);
    margin: 0;
    overflow-x: hidden;
    min-height: 100vh;
}

.container { max-width: 1000px; margin: 0 auto; padding: 40px 20px; }

.view { display: none; animation: fadeIn 0.4s ease-out; }
.view.active { display: block; }

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

header { margin-bottom: 40px; }

.status-tag {
    font-size: 0.7rem;
    color: var(--accent);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.2em;
}

h1 { font-size: 2rem; margin: 8px 0; font-weight: 700; }

.node-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.node-card {
    background: var(--panel);
    border: 1px solid var(--border);
    padding: 24px;
    border-radius: 16px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.node-card:hover {
    border-color: var(--accent);
    transform: scale(1.02);
    box-shadow: 0 0 20px rgba(99, 102, 241, 0.15);
}

.node-id {
    font-family: 'JetBrains Mono', monospace;
    font-size: 1.1rem;
    margin-bottom: 12px;
    display: block;
}

.node-meta {
    font-size: 0.8rem;
    color: var(--text-dim);
}

.back-btn {
    background: none;
    border: 1px solid var(--border);
    color: var(--text-dim);
    padding: 8px 16px;
    border-radius: 8px;
    cursor: pointer;
    margin-bottom: 30px;
}

.back-btn:hover {
    color: var(--text-main);
    border-color: var(--text-main);
}

.detail-header {
    border-bottom: 1px solid var(--border);
    padding-bottom: 30px;
    margin-bottom: 30px;
}

.detail-id {
    font-family: 'JetBrains Mono', monospace;
    font-size: 3rem;
    margin: 0;
}

.thought-container {
    background: linear-gradient(135deg, rgba(99,102,241,0.1) 0%, transparent 100%);
    border-left: 4px solid var(--accent);
    padding: 30px;
    border-radius: 0 16px 16px 0;
    font-size: 1.25rem;
    margin-top: 20px;
}

.stats-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-top: 40px;
}

.stat-card {
    background: rgba(255,255,255,0.02);
    padding: 20px;
    border-radius: 12px;
    border: 1px solid var(--border);
}

.stat-label {
    font-size: 0.75rem;
    color: var(--text-dim);
    text-transform: uppercase;
    margin-bottom: 8px;
}

.stat-value {
    font-family: 'JetBrains Mono', monospace;
    font-size: 1.6rem;
    color: var(--accent);
}

.chart-card {
    background: rgba(255,255,255,0.02);
    padding: 20px;
    border-radius: 12px;
    border: 1px solid var(--border);
    margin-top: 30px;
}
</style>
</head>

<body>
<div class="container">

<!-- GRID VIEW -->
<div id="grid-view" class="view active">
<header>
<span class="status-tag">AI is Active and Monitoring</span>
<h1>Velyn Intelligence</h1>
</header>
<div id="node-container" class="node-grid"></div>
</div>

<!-- DETAIL VIEW -->
<div id="detail-view" class="view">
<button class="back-btn" onclick="showGrid()">← Return to Network</button>

<div class="detail-header">
<span class="status-tag">Neural Diagnostic</span>
<h1 id="detail-id" class="detail-id">--</h1>
</div>

<div class="thought-container">
<span id="detail-thoughts">Analyzing asset telemetry...</span>
</div>

<div class="stats-row">
<div class="stat-card">
<div class="stat-label">Failure Probability</div>
<div id="stat-score" class="stat-value">--</div>
</div>

<div class="stat-card">
<div class="stat-label">Last Maintenance</div>
<div id="stat-date" class="stat-value">--</div>
</div>
</div>

<div class="chart-card"><canvas id="maintenanceChart"></canvas></div>
<div class="chart-card"><canvas id="failureChart"></canvas></div>
<div class="chart-card"><canvas id="repairChart"></canvas></div>

</div>
</div>

<script>
let maintenanceChart, failureChart, repairChart;

// -------------------- FETCH ANOMALIES --------------------
async function fetchAnomalies() {
    const container = document.getElementById('node-container');
    container.innerHTML = "Loading anomalies...";

    try {
        const res = await fetch('http://127.0.0.1:5000/all_anomalies');
        if(!res.ok) throw new Error(`HTTP error ${res.status}`);
        const data = await res.json();
        const anomalies = data.anomalies || [];

        container.innerHTML = "";

        if(anomalies.length === 0){
            container.innerHTML = "No anomalies detected.";
            return;
        }

        anomalies.forEach(a => {
            const card = document.createElement('div');
            card.className = 'node-card';
            card.innerHTML = `
                <span class="node-id">${a.asset_id}</span>
                <div class="node-meta">Damage Reports: ${a.damage_count ?? 0}</div>
            `;
            card.onclick = () => showDetail(a);
            container.appendChild(card);
        });

    } catch (err) {
        console.error("Fetch Anomalies Error:", err);
        container.innerHTML = "Error fetching anomalies.";
    }
}

// -------------------- SHOW DETAIL --------------------
function showDetail(node){
    document.getElementById('grid-view').classList.remove('active');
    document.getElementById('detail-view').classList.add('active');

    document.getElementById('detail-id').innerText = node.asset_id;
    document.getElementById('detail-thoughts').innerText = node.thoughts || "--";

    document.getElementById('stat-score').innerText =
        node.failure_prob !== undefined
        ? (node.failure_prob * 100).toFixed(2) + "%"
        : "--";

    loadLastMaintenance(node.asset_id);
    loadMaintenanceChart(node.asset_id);
    loadFailureChart(node.asset_id);
    loadRepairChart(node.asset_id);
}

// -------------------- LOAD LAST MAINTENANCE --------------------
async function loadLastMaintenance(asset_id){
    try{
        const res = await fetch(`http://127.0.0.1:5000/last_maintenance?asset_id=${asset_id}`);
        if(!res.ok) throw new Error(`HTTP error ${res.status}`);
        const data = await res.json();

        if(data.last_maintenance){
            const date = new Date(data.last_maintenance);
            document.getElementById('stat-date').innerText = date.toLocaleDateString();
        } else {
            document.getElementById('stat-date').innerText = "No Record";
        }
    }catch(err){
        console.error("Load Last Maintenance Error:", err);
        document.getElementById('stat-date').innerText = "--";
    }
}

// -------------------- CHART LOADERS --------------------
async function loadMaintenanceChart(asset_id){
    try {
        const res = await fetch(`http://127.0.0.1:5000/maintenance_by_month?asset_id=${asset_id}`);
        const data = await res.json();
        const trend = data.monthly_maintenance || [];
        const labels = trend.map(t=>`${t.month}/${t.year}`);
        const values = trend.map(t=>t.maintenance_count ?? 0);

        if(maintenanceChart) maintenanceChart.destroy();
        maintenanceChart = new Chart(document.getElementById('maintenanceChart'), {
            type:'line',
            data:{labels,datasets:[{label:'Maintenance Reports',data:values,borderColor:'#6366f1',backgroundColor:'rgba(99,102,241,0.2)',fill:true,tension:0.3}]},
            options:{responsive:true}
        });
    } catch(err) {
        console.error("Maintenance Chart Error:", err);
    }
}

async function loadFailureChart(asset_id){
    try {
        const res = await fetch(`http://127.0.0.1:5000/failure_by_month?asset_id=${asset_id}`);
        const data = await res.json();
        const trend = data.monthly_failure_rates || [];
        const labels = trend.map(t=>`${t.month}/${t.year}`);
        const values = trend.map(t=>(t.avg_failure_prob ?? 0) * 100);

        if(failureChart) failureChart.destroy();
        failureChart = new Chart(document.getElementById('failureChart'), {
            type:'line',
            data:{labels,datasets:[{label:'Failure Rate (%)',data:values,borderColor:'#ef4444',backgroundColor:'rgba(239,68,68,0.2)',fill:true,tension:0.3}]},
            options:{responsive:true,scales:{y:{min:0,max:100}}}
        });
    } catch(err) {
        console.error("Failure Chart Error:", err);
    }
}

async function loadRepairChart(asset_id){
    try {
        const res = await fetch(`http://127.0.0.1:5000/repaired_by_month?asset_id=${asset_id}`);
        const data = await res.json();
        const trend = data.monthly_repairs || [];
        const labels = trend.map(t=>`${t.month}/${t.year}`);
        const values = trend.map(t=>t.repairs_count ?? 0);

        if(repairChart) repairChart.destroy();
        repairChart = new Chart(document.getElementById('repairChart'), {
            type:'bar',
            data:{labels,datasets:[{label:'Repairs per Month',data:values,backgroundColor:'#10b981'}]},
            options:{responsive:true}
        });
    } catch(err) {
        console.error("Repair Chart Error:", err);
    }
}

// -------------------- SHOW GRID --------------------
function showGrid(){
    document.getElementById('detail-view').classList.remove('active');
    document.getElementById('grid-view').classList.add('active');
}

// -------------------- INITIAL LOAD --------------------
window.onload = fetchAnomalies;

</script>

</body>
</html>