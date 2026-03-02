<?php
include 'auth.php';
if ($_SESSION['role'] !== 'Manager') die("Access denied.");
include 'db.php';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ================= INITIALIZE ERROR ARRAYS ================= */
$addErrors = ['username'=>'','password'=>'','confirm'=>'','role'=>''];
$editErrors = ['username'=>'','password'=>'','confirm'=>'','role'=>''];
$openAddModal = false;
$openEditModal = false;
$notif = '';
$notifType = '';

/* ================= POST ACTIONS ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF Validation
    $user_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $user_token)) {
        die("Invalid request token.");
    }

    /* ================= ADD USER ================= */
    if (isset($_POST['add_user'])) {
        $u = trim($_POST['new_username']);
        $p = $_POST['new_password'];
        $c = $_POST['new_confirm'];
        $r = $_POST['new_role'];

        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $u)) $addErrors['username']="3-20 chars, letters/numbers/_ only";
        else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username=?");
            $stmt->bind_param("s", $u);
            $stmt->execute();
            if($stmt->get_result()->num_rows>0) $addErrors['username']="Username already exists";
        }

        if(!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $p)) $addErrors['password']="8+ chars, uppercase, lowercase, number";
        if($p !== $c) $addErrors['confirm']="Passwords do not match";
        if(!$r) $addErrors['role']="Role must be selected";

        if(array_filter($addErrors)) $openAddModal = true;

        if(!array_filter($addErrors)){
            $hp = password_hash($p, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $u, $hp, $r);
            $stmt->execute();
            header("Location: index.php?page=users&success=1");
            exit;
        }
    }

    /* ================= EDIT USER ================= */
    if (isset($_POST['edit_user'])) {
        $uid = (int)$_POST['edit_id'];
        $un  = trim($_POST['edit_username']);
        $ur  = $_POST['edit_role'];
        $np  = $_POST['edit_password'] ?? '';
        $cp  = $_POST['edit_confirm'] ?? '';

        if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $un)) $editErrors['username']="3-20 chars, letters/numbers/_ only";
        else {
            $stmt = $conn->prepare("SELECT id FROM users WHERE username=? AND id<>?");
            $stmt->bind_param("si",$un,$uid);
            $stmt->execute();
            if($stmt->get_result()->num_rows>0) $editErrors['username']="Username already exists";
        }

        if($np && !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $np)) $editErrors['password']="8+ chars, uppercase, lowercase, number";
        if($np && $np !== $cp) $editErrors['confirm']="Passwords do not match";
        if(!$ur) $editErrors['role']="Role must be selected";

        if(array_filter($editErrors)) $openEditModal = true;

        if(!array_filter($editErrors)){
            if($np){
                $hp=password_hash($np,PASSWORD_DEFAULT);
                $stmt=$conn->prepare("UPDATE users SET username=?, role=?, password=? WHERE id=?");
                $stmt->bind_param("sssi",$un,$ur,$hp,$uid);
            } else {
                $stmt=$conn->prepare("UPDATE users SET username=?, role=? WHERE id=?");
                $stmt->bind_param("ssi",$un,$ur,$uid);
            }
            $stmt->execute();
            header("Location: index.php?page=users&success=1");
            exit;
        }
    }

    /* ================= DELETE USER ================= */
    if (isset($_POST['delete_user'])) {
        $user_id=(int)$_POST['user_id'];
        if($user_id==$_SESSION['user_id']){
            $notif="You cannot delete your own account.";
            $notifType='warning';
        } else {
            $stmt=$conn->prepare("DELETE FROM users WHERE id=?");
            $stmt->bind_param("i",$user_id);
            $stmt->execute();
            header("Location: index.php?page=users&success=1");
            exit;
        }
    }
}

/* ================= FETCH USERS ================= */
$stmt = $conn->prepare("SELECT id, username, role FROM users ORDER BY username ASC");
$stmt->execute();
$users = $stmt->get_result();
?>
<div class="max-w-6xl mx-auto px-4 py-6 md:px-5 md:py-8">

    <div class="header-section mb-6">
        <h1 class="text-2xl md:text-3xl font-extrabold uppercase tracking-tight text-gray-700 flex items-center gap-2">
            <i class="fas fa-user-shield text-emerald-900"></i> User Management
        </h1>
        <p class="text-gray-500 text-xs md:text-sm mt-1">Add, edit, or remove system users.</p>
    </div>

    <div class="flex flex-col md:flex-row md:justify-between items-stretch md:items-center gap-4 mb-6">
        <div class="relative w-full md:w-80">
            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
            <input type="text" id="liveSearch" autocomplete="off" placeholder="Search users..." 
                class="w-full pl-9 pr-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-emerald-900/20 focus:border-emerald-900 outline-none text-sm transition-all shadow-sm">
        </div>
        <button onclick="openAddUser()" class="bg-emerald-900 text-white px-5 py-3 md:py-2.5 rounded-lg font-bold uppercase text-xs flex items-center justify-center gap-2 hover:bg-emerald-950 transition-colors shadow-md active:scale-95">
            <i class="fas fa-plus text-[10px]"></i> Add User
        </button>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <table class="w-full text-left text-sm border-collapse" id="userTable">
            <thead class="hidden md:table-header-group bg-gray-50 border-b border-gray-200 uppercase text-slate-500 font-semibold">
                <tr>
                    <th class="px-4 py-3">Username</th>
                    <th class="px-4 py-3">Role</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody id="userTableBody" class="divide-y divide-gray-100 block md:table-row-group">
                <?php if($users->num_rows === 0): ?>
                    <tr id="initialNoData" class="block md:table-row"><td colspan="3" class="text-center py-10 text-gray-400 block md:table-cell">No users found.</td></tr>
                <?php else: ?>
                    <?php while($u = $users->fetch_assoc()): ?>
                    <tr class="hover:bg-emerald-50 transition-colors block md:table-row p-4 md:p-0 relative">
                        
                        <td class="block md:table-cell py-1 md:px-4 md:py-4">
                            <span class="md:hidden text-[10px] font-bold text-gray-400 uppercase block">Username</span>
                            <span class="font-medium text-gray-700 text-base md:text-sm"><?= htmlspecialchars($u['username']) ?></span>
                        </td>
                        
                        <td class="block md:table-cell py-1 md:px-4 md:py-4">
                            <span class="md:hidden text-[10px] font-bold text-gray-400 uppercase block">Role</span>
                            <span class="uppercase text-[10px] md:text-xs font-bold px-2 py-0.5 rounded bg-gray-100 text-gray-600 md:bg-transparent md:p-0"><?= htmlspecialchars($u['role']) ?></span>
                        </td>

                        <td class="block md:table-cell py-3 md:px-4 md:py-4 text-center">
                            <div class="flex md:justify-center gap-4 md:gap-3 mt-2 md:mt-0">
                                <?php if($u['id'] != $_SESSION['user_id']): ?>
                                    <button onclick="openEditUser(<?= $u['id'] ?>,'<?= addslashes($u['username']) ?>','<?= $u['role'] ?>')"
                                        class="text-emerald-900 font-bold text-xs hover:underline flex items-center gap-1">
                                        <i class="   md:hidden"></i> EDIT
                                    </button>
                                    <button onclick="openDeleteUser('<?= addslashes($u['username']) ?>', <?= $u['id'] ?>)"
                                        class="text-red-600 font-bold text-xs hover:underline flex items-center gap-1">
                                        <i class=" md:hidden"></i> DELETE
                                    </button>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs italic">Current User</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
                
                <tr id="noResultsRow" style="display: none;" class="block md:table-row">
                    <td colspan="3" class="text-center py-10 text-gray-400 block md:table-cell">No matching users found.</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<div id="addUserModal" class="fixed inset-0 bg-black/40 <?= $openAddModal ? 'flex' : 'hidden' ?> items-center justify-center z-50 backdrop-blur-sm px-4">
    <div class="bg-white w-full max-w-md p-6 rounded-2xl shadow-lg">
        <h2 class="text-emerald-900 text-lg font-bold mb-4">Add User</h2>
        <form method="POST" class="space-y-2">
            <input type="hidden" name="add_user" value="1">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <label class="text-[10px] font-bold text-gray-400 uppercase">Username</label>
            <input type="text" name="new_username" placeholder="Username" value="<?= htmlspecialchars($_POST['new_username'] ?? '') ?>" class="w-full px-3 py-2 border rounded text-sm outline-none focus:border-emerald-900">
            <p class="text-red-600 text-[11px] mb-2"><?= $addErrors['username'] ?></p>
            
            <label class="text-[10px] font-bold text-gray-400 uppercase">Password</label>
            <input type="password" name="new_password" placeholder="••••••••" class="w-full px-3 py-2 border rounded text-sm outline-none focus:border-emerald-900">
            <p class="text-red-600 text-[11px] mb-2"><?= $addErrors['password'] ?></p>
            
            <input type="password" name="new_confirm" placeholder="Confirm Password" class="w-full px-3 py-2 border rounded text-sm outline-none focus:border-emerald-900">
            <p class="text-red-600 text-[11px] mb-2"><?= $addErrors['confirm'] ?></p>
            
            <label class="text-[10px] font-bold text-gray-400 uppercase">Role</label>
            <select name="new_role" required class="w-full px-3 py-2 border rounded text-sm bg-white outline-none">
                <option value="" disabled <?= !isset($_POST['new_role']) ? 'selected' : '' ?>>-- Select Role --</option>
                <option value="Manager" <?= (isset($_POST['new_role']) && $_POST['new_role']=='Manager')?'selected':'' ?>>Manager</option>
                <option value="Authorized Personnel" <?= (isset($_POST['new_role']) && $_POST['new_role']=='Authorized Personnel')?'selected':'' ?>>Authorized Personnel</option>
            </select>
            <p class="text-red-600 text-[11px]"><?= $addErrors['role'] ?></p>
            
            <div class="flex justify-end gap-3 pt-4">
                <button type="button" onclick="closeAddUser()" class="px-4 py-2 border rounded text-xs font-semibold hover:bg-gray-50 transition-colors">CANCEL</button>
                <button type="submit" class="bg-emerald-900 text-white px-5 py-2 rounded text-xs font-bold hover:bg-emerald-950 shadow-md">SAVE USER</button>
            </div>
        </form>
    </div>
</div>

<div id="editUserModal" class="fixed inset-0 bg-black/40 <?= $openEditModal ? 'flex' : 'hidden' ?> items-center justify-center z-50 backdrop-blur-sm px-4">
    <div class="bg-white w-full max-w-md p-6 rounded-2xl shadow-lg">
        <h2 class="text-emerald-900 text-lg font-bold mb-4">Edit User</h2>
        <form method="POST" class="space-y-2">
            <input type="hidden" name="edit_user" value="1">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="edit_id" id="edit_id" value="<?= $_POST['edit_id'] ?? '' ?>">
            
            <label class="text-[10px] font-bold text-gray-400 uppercase">Username</label>
            <input type="text" name="edit_username" id="edit_username" placeholder="Username" value="<?= htmlspecialchars($_POST['edit_username'] ?? '') ?>" class="w-full px-3 py-2 border rounded text-sm outline-none focus:border-emerald-900">
            <p class="text-red-600 text-[11px]"><?= $editErrors['username'] ?></p>
            
            <label class="text-[10px] font-bold text-gray-400 uppercase">Change Password (Optional)</label>
            <input type="password" name="edit_password" id="edit_password" placeholder="Leave blank to keep current" class="w-full px-3 py-2 border rounded text-sm outline-none">
            <p class="text-red-600 text-[11px]"><?= $editErrors['password'] ?></p>
            <input type="password" name="edit_confirm" id="edit_confirm" placeholder="Confirm New Password" class="w-full px-3 py-2 border rounded text-sm outline-none">
            <p class="text-red-600 text-[11px]"><?= $editErrors['confirm'] ?></p>
            
            <label class="text-[10px] font-bold text-gray-400 uppercase">Role</label>
            <select name="edit_role" id="edit_role" required class="w-full px-3 py-2 border rounded text-sm bg-white outline-none">
                <option value="Manager">Manager</option>
                <option value="Authorized Personnel">Authorized Personnel</option>
            </select>
            <p class="text-red-600 text-[11px]"><?= $editErrors['role'] ?></p>
            
            <div class="flex justify-end gap-3 pt-4">
                <button type="button" onclick="closeEditUser()" class="px-4 py-2 border rounded text-xs font-semibold hover:bg-gray-50">CANCEL</button>
                <button type="submit" class="bg-emerald-900 text-white px-5 py-2 rounded text-xs font-bold hover:bg-emerald-950 shadow-md">UPDATE USER</button>
            </div>
        </form>
    </div>
</div>

<div id="deleteUserModal" class="fixed inset-0 flex items-center justify-center bg-black/40 z-50 opacity-0 pointer-events-none transition-opacity px-4">
    <div class="modal-content bg-white rounded-2xl p-6 w-full max-w-sm text-center shadow-xl transform scale-90 transition-all">
        <i class="fa-solid fa-triangle-exclamation text-red-600 text-4xl mb-4"></i>
        <h2 class="text-lg font-bold mb-2">Delete User</h2>
        <p class="text-sm text-gray-600 mb-6">Are you sure you want to delete <span id="deleteUserName" class="font-semibold text-gray-900"></span>?</p>
        <form method="POST">
            <input type="hidden" name="delete_user" value="1">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" name="user_id" id="deleteUserId">
            <div class="flex justify-center gap-3">
                <button type="button" onclick="closeDeleteUser()" class="px-5 py-2 border rounded text-xs font-semibold hover:bg-gray-50 transition-colors">CANCEL</button>
                <button type="submit" class="px-6 py-2 bg-red-600 text-white rounded text-xs font-bold hover:bg-red-700 shadow-md">DELETE</button>
            </div>
        </form>
    </div>
</div>

<script>
/* ================= INSTANT CLIENT-SIDE SEARCH ================= */
document.getElementById('liveSearch').addEventListener('input', function() {
    const query = this.value.toLowerCase();
    const rows = document.querySelectorAll('#userTableBody tr:not(#noResultsRow):not(#initialNoData)');
    let hasResults = false;

    rows.forEach(row => {
        const username = row.cells[0].textContent.toLowerCase();
        if (username.includes(query)) {
            row.style.display = '';
            hasResults = true;
        } else {
            row.style.display = 'none';
        }
    });

    document.getElementById('noResultsRow').style.display = hasResults ? 'none' : 'table-row';
});

/* ================= MODAL CONTROLS ================= */
const addUserModal = document.getElementById('addUserModal');
const editUserModal = document.getElementById('editUserModal');
const deleteUserModal = document.getElementById('deleteUserModal');

function openAddUser(){ addUserModal.classList.replace('hidden', 'flex'); }
function closeAddUser(){ addUserModal.classList.replace('flex', 'hidden'); }

function openEditUser(id, username, role){
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_role').value = role;
    editUserModal.classList.replace('hidden', 'flex');
}
function closeEditUser(){ editUserModal.classList.replace('flex', 'hidden'); }

function openDeleteUser(name, id){
    document.getElementById('deleteUserName').textContent = name;
    document.getElementById('deleteUserId').value = id;
    deleteUserModal.classList.remove('opacity-0','pointer-events-none');
    deleteUserModal.classList.add('opacity-100');
    deleteUserModal.querySelector('.modal-content').classList.add('scale-100');
}
function closeDeleteUser(){
    deleteUserModal.classList.add('opacity-0','pointer-events-none');
    deleteUserModal.classList.remove('opacity-100');
    deleteUserModal.querySelector('.modal-content').classList.remove('scale-100');
}

// Background Clicks to close
window.onclick = (e) => {
    if(e.target == addUserModal) closeAddUser();
    if(e.target == editUserModal) closeEditUser();
    if(e.target == deleteUserModal) closeDeleteUser();
};
</script>