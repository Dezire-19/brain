<?php
include 'db.php';
include 'auth.php';

// Fetch unique departments and employees
$employees_list = $conn->query("SELECT employee_id, full_name FROM employees ORDER BY full_name ASC")->fetch_all(MYSQLI_ASSOC);
$departments_list = $conn->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department ASC")->fetch_all(MYSQLI_ASSOC);

$report_results = [];
$headers = [];
$report_title = "Asset Report";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_report'])) {
    $report_title = trim($_POST['report_title']) ?: 'Asset Report';
    $filter_type = $_POST['filter_field'] ?? 'date_acquired';
    $selected_fields = $_POST['fields'] ?? [];

    if (!empty($selected_fields)) {
        $db_fields_map = [
            "asset_name" => "Asset Name", "asset_id" => "Asset ID", "category" => "Category",
            "serial_number" => "Serial #", "date_acquired" => "Date Acquired",
            "date_issued" => "Date Issued", "status" => "Status", "item_condition" => "Condition", 
            "full_name" => "Employee Name", "department" => "Department", "authorized_by" => "Authorized By"
        ];
        foreach ($selected_fields as $field) {
            $headers[$field] = $db_fields_map[$field] ?? $field;
        }

        $cols = implode(", ", $selected_fields);
        $sql = "SELECT $cols FROM assets a 
                LEFT JOIN employees e ON a.employee_id = e.employee_id 
                WHERE a.deleted = 0";

        if ($filter_type === 'employee_id') {
            $emp_id = intval($_POST['employee_id']);
            $sql .= " AND a.employee_id = $emp_id";
        } elseif ($filter_type === 'department') {
            $dept = $conn->real_escape_string($_POST['department_name']);
            $sql .= " AND e.department = '$dept'";
        } else {
            $timeframe = $_POST['timeframe'] ?? 'last_90_days';
            $days = ($timeframe === 'last_30_days') ? 30 : 90;
            $sql .= " AND a.$filter_type >= DATE_SUB(NOW(), INTERVAL $days DAY)";
        }

        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) $report_results[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Report Builder | Asset Management</title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
    body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #334155; }

    /* Panels */
    .panel { background: white; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    .panel-header { padding: 14px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; font-weight: 600; }
    .panel-body { padding: 24px; }

    /* Form */
    .form-label { width: 140px; font-weight: 500; color: #475569; }
    .input-text { border: 1px solid #cbd5e1; border-radius: 4px; padding: 8px 12px; font-size: 14px; transition: all 0.2s; background: #fff; width: 100%; }
    .input-text:focus { border-color: #004D2D; outline: none; box-shadow: 0 0 0 3px rgba(0,77,45,0.1); }

    .btn-run { background-color: #004D2D; color: white; padding: 10px 20px; border-radius: 4px; font-weight: 600; transition: all 0.2s; display: flex; align-items: center; gap: 6px; }
    .btn-run:hover { background-color: #003a22; transform: translateY(-1px); box-shadow: 0 4px 6px rgba(0,0,0,0.1); }

    .field-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 12px; }
    .checkbox-item { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #334155; cursor: pointer; padding: 4px; border-radius: 4px; transition: background 0.2s; }
    .checkbox-item:hover { background: #f1f5f9; }
    .accent-green { accent-color: #004D2D; width: 16px; height: 16px; cursor: pointer; }

    /* Table */
    .report-table th { background-color: #f8fafc; color: #64748b; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; }
</style>
</head>
<body class="p-8 md:p-12">

<div class="max-w-6xl mx-auto">

<div class="flex items-center border-b border-gray-200 pb-4 mb-2">
    <i class="fas fa-chart-pie text-emerald-900 text-3xl mr-3"></i>
    <h1 class="text-3xl font-extrabold uppercase tracking-tight text-gray-700">Report Builder</h1>
</div>
<p class="text-gray-500 text-sm mb-4">
    Generate and preview customized reports for company assets, employees, and departments.
</p>

    <form id="reportForm" method="POST">

        <!-- Configuration Panel -->
        <div class="panel">
            <div class="panel-header">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-sliders text-slate-400"></i>
                    Configuration
                </div>
            </div>
            <div class="panel-body space-y-6">

                <!-- Report Metadata -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Report Title</label>
                    <input type="text" name="report_title" value="<?= htmlspecialchars($report_title) ?>" 
                        class="input-text" placeholder="e.g., Q1 Hardware Audit 2026">
                </div>

                <!-- Field Selection -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Select Columns</label>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        <?php
                        $fields = [
                            "asset_name" => "Asset Name", "asset_id" => "Asset ID", "category" => "Category",
                            "serial_number" => "Serial #", "date_acquired" => "Acquired", 
                            "date_issued" => "Issued", "status" => "Status", "item_condition" => "Condition",
                            "full_name" => "Employee", "department" => "Dept.", "authorized_by" => "Authorized By"
                        ];
                        foreach ($fields as $val => $label): ?>
                            <label class="checkbox-item">
                                <input type="checkbox" name="fields[]" value="<?= $val ?>" checked class="accent-green field-checkbox">
                                <span><?= $label ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Filter Selection -->
                <div class="form-row mt-6 mb-0 flex flex-col md:flex-row items-start gap-4">
                    <label class="form-label">Filter By:</label>
                    <div class="flex gap-3 flex-1 max-w-[600px]">

                        <select name="filter_field" id="filterSelector" class="input-text w-1/2 cursor-pointer">
                            <option value="date_acquired">Date Acquired</option>
                            <option value="employee_id">Specific Employee</option>
                            <option value="department">Department</option>
                        </select>

                        <div class="w-1/2 flex flex-col gap-2">
                            <select name="timeframe" id="dateContainer" class="input-text cursor-pointer">
                                <option value="last_90_days">Last 90 Days</option>
                                <option value="last_30_days">Last 30 Days</option>
                            </select>

                            <select name="employee_id" id="employeeContainer" class="input-text hidden cursor-pointer">
                                <?php foreach ($employees_list as $emp): ?>
                                    <option value="<?= $emp['employee_id'] ?>"><?= htmlspecialchars($emp['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>

                            <select name="department_name" id="deptContainer" class="input-text hidden cursor-pointer">
                                <?php foreach ($departments_list as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept['department']) ?>"><?= htmlspecialchars($dept['department']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Preview Panel -->
        <div class="panel">
            <div class="panel-header flex justify-between items-center">
                <div class="flex items-center gap-2"><i class="fa-solid fa-list-check text-slate-400"></i> Preview Results</div>
                <button type="submit" name="run_report" class="btn-run">
                    <i class="fa-solid fa-play text-[10px]"></i> Run Report
                </button>
            </div>
            <div class="panel-body">
                <?php if (!empty($report_results)): ?>
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="w-full text-left text-sm divide-y divide-gray-100 report-table">
                            <thead>
                                <tr>
                                    <?php foreach ($headers as $title): ?>
                                        <th class="px-4 py-2"><?= $title ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php foreach ($report_results as $row): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <?php foreach ($headers as $key => $title): ?>
                                            <td class="px-4 py-2"><?= htmlspecialchars($row[$key] ?? '—') ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-16 text-slate-500">
                        <i class="fa-solid fa-magnifying-glass-chart text-3xl mb-4"></i>
                        <p class="text-sm">Configure your report parameters above and click "Run Report" to see data.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </form>
</div>

<script>
const filterSelector = document.getElementById('filterSelector');
const dateContainer = document.getElementById('dateContainer');
const employeeContainer = document.getElementById('employeeContainer');
const deptContainer = document.getElementById('deptContainer');

filterSelector.addEventListener('change', function() {
    [dateContainer, employeeContainer, deptContainer].forEach(el => el.classList.add('hidden'));
    if (this.value === 'employee_id') employeeContainer.classList.remove('hidden');
    else if (this.value === 'department') deptContainer.classList.remove('hidden');
    else dateContainer.classList.remove('hidden');
});

document.getElementById('selectAll')?.addEventListener('change', function(e) {
    document.querySelectorAll('.field-checkbox').forEach(cb => cb.checked = e.target.checked);
});
</script>

</body>
</html>