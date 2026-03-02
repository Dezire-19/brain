<?php
include 'db.php';
include 'auth.php';

/* ---------- DEPARTMENTS ---------- */
$departments = ['BRC', 'Contact Center', 'CSD', 'ESG', 'Finance', 'Marketing', 'MIS', 'Sales', 'HR'];

/* ---------- 1. DATA SUBMISSION (ADD EMPLOYEE) ---------- */
$errors = ['full_name' => '', 'department' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    $csrf_token  = $_POST['csrf_token'] ?? '';
    $full_name   = trim($_POST['full_name'] ?? '');
    $department  = trim($_POST['department'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        die("Invalid request.");
    }

    if ($full_name === '' || strlen($full_name) > 100) {
        $errors['full_name'] = "Full name is required and must be under 100 characters.";
    } else {
        $stmt_check = $conn->prepare("SELECT COUNT(*) FROM employees WHERE full_name = ?");
        $stmt_check->bind_param("s", $full_name);
        $stmt_check->execute();
        $stmt_check->bind_result($count);
        $stmt_check->fetch();
        $stmt_check->close();

        if ($count > 0) {
            $errors['full_name'] = "This name is already in use.";
        }
    }

    if (!in_array($department, $departments)) {
        $errors['department'] = "Please select a valid department.";
    }

    if (empty($errors['full_name']) && empty($errors['department'])) {
        $stmt = $conn->prepare("INSERT INTO employees (full_name, department) VALUES (?, ?)");
        $stmt->bind_param("ss", $full_name, $department);
        $stmt->execute();
        $stmt->close();

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header("Location: index.php?page=employee&success=1");
        exit;
    }
}

/* ---------- 2. SORTING LOGIC ---------- */
$sort_by  = $_GET['sort_by'] ?? 'full_name';
$sort_dir = $_GET['sort_dir'] ?? 'asc';
$valid_columns = ['full_name', 'department', 'device_count'];
$sort_by = in_array($sort_by, $valid_columns) ? $sort_by : 'full_name';
$sort_dir = ($sort_dir === 'desc') ? 'desc' : 'asc';

/* ---------- 3. FETCH EMPLOYEES ---------- */
$query = "
    SELECT 
        e.employee_id, e.full_name, e.department,
        COUNT(a.asset_id) AS device_count
    FROM employees e
    LEFT JOIN assets a ON e.employee_id = a.employee_id
    GROUP BY e.employee_id
    ORDER BY $sort_by $sort_dir
";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
$employees = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ---------- 4. DELETE EMPLOYEE ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_employee'])) {
    $csrf_token = $_POST['csrf_token'] ?? '';
    $employee_id = (int)($_POST['employee_id'] ?? 0);

    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        die("Invalid request.");
    }

    if ($employee_id > 0) {
        $stmt = $conn->prepare("DELETE FROM employees WHERE employee_id = ?");
        $stmt->bind_param("i", $employee_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header("Location: index.php?page=employee&success=1");
        exit;
    }
}
?>

<link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>

<style>
    body, input, select, button, table {
        font-family: 'Public Sans', sans-serif !important;
    }
    
    /* Mobile Table Transformation */
    @media (max-width: 768px) {
        #employeeTable thead { display: none; }
        #employeeTable, #employeeTable tbody, #employeeTable tr, #employeeTable td {
            display: block; width: 100%;
        }
        #employeeTable tr {
            margin-bottom: 1rem; border: 1px solid #e2e8f0;
            border-radius: 0.5rem; padding: 0.5rem; background: white;
        }
        #employeeTable td {
            display: flex; justify-content: space-between; align-items: center;
            padding: 0.75rem 0.5rem; border-bottom: 1px solid #f1f5f9;
        }
        #employeeTable td:last-child {
            border-bottom: none; justify-content: center; gap: 2rem;
            background: #f8fafc; margin-top: 0.5rem; border-radius: 0.25rem;
        }
        #employeeTable td::before {
            content: attr(data-label); font-weight: 700;
            text-transform: uppercase; font-size: 0.75rem; color: #64748b;
        }
    }
</style>

<div class="max-w-6xl mx-auto px-4 py-6 md:px-5 md:py-8">

    <div class="header-section mb-6">
        <h1 class="text-2xl md:text-3xl font-extrabold uppercase tracking-tight text-gray-700 flex items-center gap-2">
            <i class="fas fa-user-shield text-emerald-900"></i> User Management
        </h1>
        <p class="text-gray-500 text-xs md:text-sm mt-1">Add, edit, or remove system users.</p>
    </div>

  <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6">
      <div class="relative w-full md:w-80">
          <input type="text" id="directorySearch" 
                 class="w-full pl-4 pr-4 py-3 md:py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-900/20 focus:border-emerald-900 outline-none text-base md:text-sm transition-all shadow-sm" 
                 placeholder="Search name or dept...">
      </div>
      
      <button type="button" onclick="openEmpModal()" 
              class="relative z-10 w-full md:w-auto bg-emerald-900 hover:bg-emerald-950 text-white px-6 py-3 md:py-2.5 rounded-lg font-bold text-sm flex items-center justify-center transition-colors uppercase tracking-widest shadow-md cursor-pointer">
          <i class="fas fa-plus mr-2"></i> Add Employee
      </button>
  </div>

  <div class="overflow-x-auto">
      <table class="w-full border-collapse text-left text-sm" id="employeeTable">
          <thead class="bg-gray-50 border-b border-gray-300 uppercase text-slate-500 font-bold hidden md:table-header-group">
              <tr>
                  <th class="px-4 py-3">Full Name</th>
                  <th class="px-4 py-3">Department</th>
                  <th class="px-4 py-3">Devices</th>
                  <th class="px-4 py-3 text-center">Action</th>
              </tr>
          </thead>
          <tbody class="divide-y divide-gray-200 md:bg-white md:border md:border-gray-300">
              <?php if (empty($employees)): ?>
                  <tr>
                      <td colspan="4" class="text-center py-10 text-gray-400">No employees found.</td>
                  </tr>
              <?php else: ?>
                  <?php foreach ($employees as $row): ?>
                  <tr class="hover:bg-emerald-50 transition-colors group">
                      <td class="px-4 py-4 font-semibold text-gray-900" data-label="Name">
                          <?= htmlspecialchars($row['full_name']) ?>
                      </td>
                      <td class="px-4 py-4 uppercase text-xs text-gray-600 font-medium" data-label="Dept">
                          <?= htmlspecialchars($row['department']) ?>
                      </td>
                      <td class="px-4 py-4" data-label="Devices">
                          <span class="font-bold <?= $row['device_count'] > 0 ? 'text-emerald-700' : 'text-gray-400 font-normal' ?>">
                              <?= (int)$row['device_count'] ?> UNIT<?= $row['device_count'] != 1 ? 'S' : '' ?>
                          </span>
                      </td>
                      <td class="px-4 py-4 text-center flex justify-center gap-6 md:gap-3 items-center" data-label="Actions">
                          <button class="text-emerald-900 font-bold md:text-xs hover:underline" 
                                  onclick="window.location.href='?page=person_detail&id=<?= (int)$row['employee_id'] ?>'">
                              VIEW
                          </button>

                          <button type="button" 
                                  class="text-red-600 font-bold md:text-xs hover:underline"
                                  onclick="openDeleteModal('<?= htmlspecialchars($row['full_name'], ENT_QUOTES) ?>', <?= (int)$row['employee_id'] ?>)">
                              DELETE
                          </button>
                      </td>
                  </tr>
                  <?php endforeach; ?>
              <?php endif; ?>
          </tbody>
      </table>
  </div>
</div>

<div id="empModal" class="fixed inset-0 bg-black/50 <?= (!empty($errors['full_name']) || !empty($errors['department'])) ? 'flex' : 'hidden' ?> items-center justify-center z-[9999] backdrop-blur-sm px-4">
    <div class="bg-white w-full max-w-md p-6 rounded-xl shadow-2xl animate-in fade-in zoom-in duration-200">
        <h2 class="text-emerald-900 text-lg font-bold mb-4">Add Employee</h2>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="add_employee" value="1">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div>
                <label class="block font-bold text-xs mb-1 uppercase text-gray-500">Full Name</label>
                <input type="text" name="full_name" required maxlength="100"
                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-900/20 focus:border-emerald-900 outline-none">
                <?php if (!empty($errors['full_name'])): ?>
                    <p class="text-red-600 text-xs mt-1"><?= htmlspecialchars($errors['full_name']) ?></p>
                <?php endif; ?>
            </div>

            <div>
                <label class="block font-bold text-xs mb-1 uppercase text-gray-500">Department</label>
                <select name="department" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-emerald-900/20 focus:border-emerald-900 outline-none bg-white">
                    <option value="" disabled <?= empty($_POST['department']) ? 'selected' : '' ?>>-- Select Department --</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= htmlspecialchars($dept) ?>"
                            <?= (($_POST['department'] ?? '') === $dept) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($dept) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!empty($errors['department'])): ?>
                    <p class="text-red-600 text-xs mt-1"><?= htmlspecialchars($errors['department']) ?></p>
                <?php endif; ?>
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <button type="button" onclick="closeEmpModal()" 
                        class="px-5 py-2.5 border border-gray-300 rounded-lg text-xs font-semibold hover:bg-gray-50 transition-colors">
                    CANCEL
                </button>
                <button type="submit" 
                        class="px-6 py-2.5 bg-emerald-900 text-white rounded-lg text-xs font-bold hover:bg-emerald-950 transition-colors shadow-lg">
                    SAVE EMPLOYEE
                </button>
            </div>
        </form>
    </div>
</div>

<div id="delete-modal" class="fixed inset-0 flex items-center justify-center bg-black/50 z-[9999] opacity-0 pointer-events-none transition-opacity px-4">
    <div class="modal-content bg-white rounded-2xl p-6 w-full max-w-sm text-center shadow-xl transform scale-90 transition-all">
        <i class="fa-solid fa-triangle-exclamation text-red-600 text-4xl mb-4"></i>
        <h2 class="text-lg font-bold mb-2">Delete Employee</h2>
        <p class="text-sm text-gray-600 mb-6">Are you sure you want to delete <span id="delete-employee-name" class="font-semibold text-gray-900"></span>?</p>
        
        <form id="delete-form" method="POST">
            <input type="hidden" name="delete_employee" value="1">
            <input type="hidden" name="employee_id" id="delete-employee-id">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="flex justify-center gap-3">
                <button type="button" onclick="closeDeleteModal()" 
                        class="px-5 py-2.5 border border-gray-300 rounded-lg text-xs font-semibold hover:bg-gray-50 transition-colors">
                    CANCEL
                </button>
                <button type="submit" 
                        class="px-6 py-2.5 bg-red-600 text-white rounded-lg text-xs font-bold hover:bg-red-700 transition-colors shadow-lg">
                    DELETE
                </button>
            </div>
        </form>
    </div>
</div>

<?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
<div id="success-modal" class="fixed inset-0 flex items-center justify-center bg-black/50 z-[9999] px-4 transition-opacity">
    <div class="modal-content bg-white rounded-2xl p-8 w-full max-w-xs text-center shadow-xl">
        <i class="fa-solid fa-circle-check text-green-600 text-4xl mb-4"></i>
        <h2 class="text-lg font-bold mb-2">Success!</h2>
        <p class="text-sm text-gray-600 mb-6">Employee has been updated successfully.</p>
        <button id="close-success-modal" class="w-full bg-green-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-green-700 transition-colors">OK</button>
    </div>
</div>
<?php endif; ?>

<script>
// UI Control Functions
function openEmpModal() {
    const modal = document.getElementById('empModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.style.overflow = 'hidden'; // Prevent scroll
}

function closeEmpModal() {
    const modal = document.getElementById('empModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    document.body.style.overflow = 'auto';
}

function openDeleteModal(name, id) {
    const dModal = document.getElementById('delete-modal');
    document.getElementById('delete-employee-name').textContent = name;
    document.getElementById('delete-employee-id').value = id;
    dModal.classList.remove('opacity-0', 'pointer-events-none');
    dModal.classList.add('opacity-100');
    dModal.firstElementChild.classList.add('scale-100');
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    const dModal = document.getElementById('delete-modal');
    dModal.classList.add('opacity-0', 'pointer-events-none');
    dModal.classList.remove('opacity-100');
    dModal.firstElementChild.classList.remove('scale-100');
    document.body.style.overflow = 'auto';
}

// Success Modal Logic
const successModal = document.getElementById('success-modal');
if (successModal) {
    document.getElementById('close-success-modal').addEventListener('click', () => {
        successModal.style.display = 'none';
        history.replaceState(null, '', window.location.pathname + '?page=employee');
    });
}

// Global Click Listener for Modal Backgrounds
window.addEventListener('click', function(e) {
    if (e.target.id === 'empModal') closeEmpModal();
    if (e.target.id === 'delete-modal') closeDeleteModal();
});

// Real-time Search Logic
document.getElementById('directorySearch').addEventListener('input', function() {
    const query = this.value.toLowerCase();
    const rows = document.querySelectorAll('#employeeTable tbody tr');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
});
</script>