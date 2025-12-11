<?php
session_start();
require_once 'db.php';               // Local Database Connection
require_once 'db_international.php'; // External OTOS Database Connection

// --- 1. Security & Access Control ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: dashboard_home.php");
    exit;
}

$success_msg = '';
$error_msg = '';

// --- 2. Handle Form Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // A. Add New User
    if (isset($_POST['action']) && $_POST['action'] === 'add_user') {
        $email = trim($_POST['email']);
        $password = trim($_POST['password']);
        $fname = trim($_POST['first_name']);
        $lname = trim($_POST['last_name']);
        $role = $_POST['role'];
        $division = trim($_POST['division']);
        $position = trim($_POST['position']);
        $sex = $_POST['sex'];
        // CHANGED: Now getting ID from hidden input
        $otos_link = isset($_POST['otos_userlink']) ? (int)$_POST['otos_userlink'] : 0;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_msg = "Invalid email format.";
        } elseif (strlen($password) < 8) {
            $error_msg = "Password must be at least 8 characters.";
        } else {
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error_msg = "Email already exists.";
            } else {
                $pass_hash = password_hash($password, PASSWORD_DEFAULT);
                $status = 'active';
                $stmt = $conn->prepare("INSERT INTO users (email, password_hash, first_name, last_name, role, division, position, sex, status, otos_userlink) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssssssi", $email, $pass_hash, $fname, $lname, $role, $division, $position, $sex, $status, $otos_link);

                if ($stmt->execute()) {
                    $success_msg = "New user created successfully.";
                } else {
                    $error_msg = "Database Error: " . $conn->error;
                }
            }
            $stmt->close();
        }
    }

    // B. Update Existing User
    if (isset($_POST['action']) && $_POST['action'] === 'update_user') {
        $user_id = (int)$_POST['user_id'];
        $role = $_POST['role'];
        $status = $_POST['status'];
        $division = trim($_POST['division']);
        $position = trim($_POST['position']);
        $fname = trim($_POST['first_name']);
        $lname = trim($_POST['last_name']);
        $otos_link = isset($_POST['otos_userlink']) ? (int)$_POST['otos_userlink'] : 0;

        $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, role=?, status=?, division=?, position=?, otos_userlink=? WHERE user_id=?");
        $stmt->bind_param("ssssssii", $fname, $lname, $role, $status, $division, $position, $otos_link, $user_id);

        if ($stmt->execute()) {
            $success_msg = "User details updated.";
        } else {
            $error_msg = "Error updating user: " . $conn->error;
        }
        $stmt->close();
    }

    // C. Reset Password
    if (isset($_POST['action']) && $_POST['action'] === 'reset_password') {
        $user_id = (int)$_POST['user_id'];
        $new_pass = trim($_POST['new_password']);

        if (strlen($new_pass) >= 8) {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $stmt->bind_param("si", $hash, $user_id);
            if ($stmt->execute()) {
                $success_msg = "Password reset successfully.";
            } else {
                $error_msg = "Error resetting password.";
            }
            $stmt->close();
        } else {
            $error_msg = "Password must be at least 8 characters.";
        }
    }
}

// --- 3. Fetch OTOS Employees (With Console Error Reporting) ---
$otos_employees = [];
$otos_error_console = null; 

try {
    $conn_otos = get_db_connection(); 
    if ($conn_otos) {
        $sql_otos = "SELECT id, first_name, last_name, Full_Name FROM useremployee ORDER BY last_name ASC";
        $result_otos = $conn_otos->query($sql_otos);
        
        if ($result_otos) {
            while ($row = $result_otos->fetch_assoc()) {
                $otos_employees[] = $row;
            }
        }
        $conn_otos->close();
    }
} catch (Exception $e) {
    $otos_error_console = "OTOS DB Error: " . $e->getMessage();
    error_log($otos_error_console);
}

// --- 4. Fetch Users (Search & Pagination) ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$where_sql = "WHERE 1=1";
$params = [];
$types = "";

if ($search) {
    $where_sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR division LIKE ? OR Full_Name LIKE ?)";
    $s_term = "%$search%";
    $params = [$s_term, $s_term, $s_term, $s_term];
    $types = "sssss";
}

$count_sql = "SELECT COUNT(*) as total FROM users $where_sql";
$stmt = $conn->prepare($count_sql);
if ($search) $stmt->bind_param($types, ...$params);
$stmt->execute();
$total_rows = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);
$stmt->close();

$sql = "SELECT * FROM users $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();

function getInitials($fname, $lname) {
    return strtoupper(substr($fname, 0, 1) . substr($lname, 0, 1));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f9fafb; }
        .modal { transition: opacity 0.25s ease; }
        body.modal-active { overflow-x: hidden; overflow-y: hidden !important; }
    </style>
</head>
<body class="text-gray-800 p-4 md:p-8">

    <?php if ($otos_error_console): ?>
    <script>
        console.group("❌ Backend Error Detected");
        console.error(<?php echo json_encode($otos_error_console); ?>);
        console.groupEnd();
    </script>
    <?php endif; ?>

    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Manage Users</h1>
            <p class="text-sm text-gray-500">Create, edit, and manage system access.</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="openAddModal()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium shadow-sm transition-colors flex items-center gap-2">
                <i class="fas fa-plus"></i> Add New User
            </button>
        </div>
    </div>

    <?php if ($success_msg): ?>
        <div class="mb-6 p-4 bg-green-50 border-l-4 border-green-500 text-green-700 rounded-r shadow-sm flex items-center justify-between">
            <div class="flex items-center gap-2"><i class="fas fa-check-circle"></i> <span><?php echo htmlspecialchars($success_msg); ?></span></div>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="mb-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-r shadow-sm flex items-center justify-between">
            <div class="flex items-center gap-2"><i class="fas fa-exclamation-triangle"></i> <span><?php echo htmlspecialchars($error_msg); ?></span></div>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        
        <div class="p-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center">
            <form action="" method="GET" class="relative w-full max-w-sm">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search by name, email, or division..." 
                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500 shadow-sm">
                <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
            </form>
            <div class="text-xs text-gray-500 hidden sm:block">
                Showing <?php echo count($users); ?> of <?php echo $total_rows; ?> users
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-white text-xs font-semibold text-gray-500 uppercase tracking-wider border-b border-gray-200">
                        <th class="px-6 py-4">User Details</th>
                        <th class="px-6 py-4">Role & Access</th>
                        <th class="px-6 py-4">Status</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center">
                                    <?php if (!empty($u['profile_picture_path']) && file_exists($u['profile_picture_path'])): ?>
                                        <img src="<?php echo htmlspecialchars($u['profile_picture_path']); ?>" alt="" class="h-10 w-10 rounded-full object-cover border border-gray-200">
                                    <?php else: ?>
                                        <div class="h-10 w-10 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center font-bold text-xs">
                                            <?php echo getInitials($u['first_name'], $u['last_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="ml-3">
                                        <div class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($u['email']); ?></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($u['role']); ?></div>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($u['division']); ?></div>
                                <div class="text-xs text-gray-400"><?php echo htmlspecialchars($u['position']); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <?php 
                                    $statusStyles = [
                                        'active' => 'bg-green-100 text-green-700',
                                        'pending' => 'bg-yellow-100 text-yellow-700',
                                        'disabled' => 'bg-red-100 text-red-700'
                                    ];
                                    $sClass = $statusStyles[$u['status']] ?? 'bg-gray-100 text-gray-700';
                                ?>
                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $sClass; ?>">
                                    <?php echo ucfirst($u['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button onclick='openEditModal(<?php echo json_encode($u); ?>)' class="text-indigo-600 hover:text-indigo-900 text-sm font-medium">Edit</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="px-6 py-8 text-center text-gray-500 text-sm">No users found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="px-6 py-4 border-t border-gray-200 flex justify-center bg-gray-50">
            <div class="flex space-x-1">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" 
                       class="px-3 py-1 rounded-md text-sm font-medium <?php echo $i === $page ? 'bg-indigo-600 text-white' : 'bg-white border border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div id="addModal" class="modal opacity-0 pointer-events-none fixed inset-0 z-40 flex items-center justify-center">
        <div class="absolute inset-0 bg-gray-900 opacity-50" onclick="closeAddModal()"></div>
        <div class="bg-white w-full max-w-lg mx-4 rounded-xl shadow-2xl z-50 overflow-hidden transform transition-all scale-95" id="addModalContent">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-800">Add New User</h3>
                <button onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            <form action="" method="POST" class="p-6">
                <input type="hidden" name="action" value="add_user">
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">First Name</label>
                        <input type="text" name="first_name" required class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Last Name</label>
                        <input type="text" name="last_name" required class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-indigo-500">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-700 mb-1">Email Address</label>
                    <input type="email" name="email" required class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-indigo-500">
                </div>
                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-700 mb-1">Password</label>
                    <input type="password" name="password" required minlength="8" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-indigo-500">
                </div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Role</label>
                        <select name="role" required class="w-full border rounded-lg px-3 py-2 text-sm bg-white">
                            <option value="Initiator">Initiator</option>
                            <option value="Section Chief">Section Chief</option>
                            <option value="Division Chief">Division Chief</option>
                            <option value="ARD">ARD</option>
                            <option value="RED">RED</option>
                            <option value="Records Office">Records Office</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Sex</label>
                        <select name="sex" class="w-full border rounded-lg px-3 py-2 text-sm bg-white">
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Division</label>
                        <input type="text" name="division" class="w-full border rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">Position</label>
                        <input type="text" name="position" class="w-full border rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-xs font-bold text-gray-700 mb-1">Link OTOS Employee (Optional)</label>
                    <div class="flex gap-2">
                        <input type="hidden" name="otos_userlink" id="add_otos_id" value="0">
                        <input type="text" id="add_otos_name" readonly placeholder="No Employee Selected" 
                               class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-500 cursor-not-allowed">
                        <button type="button" onclick="openOtosModal('add')" 
                                class="px-3 py-2 bg-indigo-600 text-white rounded-lg text-xs font-medium hover:bg-indigo-700 whitespace-nowrap">
                             <i class="fas fa-search"></i> Select
                        </button>
                        <button type="button" onclick="clearOtosSelection('add')" 
                                class="px-3 py-2 bg-gray-200 text-gray-600 rounded-lg text-xs font-medium hover:bg-gray-300">
                             <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <button type="button" onclick="closeAddModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Create User</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal opacity-0 pointer-events-none fixed inset-0 z-40 flex items-center justify-center">
        <div class="absolute inset-0 bg-gray-900 opacity-50" onclick="closeEditModal()"></div>
        <div class="bg-white w-full max-w-lg mx-4 rounded-xl shadow-2xl z-50 overflow-hidden transform transition-all scale-95" id="editModalContent">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-800">Edit User</h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
            </div>
            
            <div class="p-6">
                <div class="flex border-b border-gray-200 mb-4">
                    <button onclick="switchTab('details')" id="tab-details" class="px-4 py-2 text-sm font-medium text-indigo-600 border-b-2 border-indigo-600 focus:outline-none">Details</button>
                    <button onclick="switchTab('security')" id="tab-security" class="px-4 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 focus:outline-none">Security</button>
                </div>

                <form action="" method="POST" id="form-details" class="block">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">First Name</label>
                            <input type="text" name="first_name" id="edit_fname" required class="w-full border rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Last Name</label>
                            <input type="text" name="last_name" id="edit_lname" required class="w-full border rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="block text-xs font-bold text-gray-700 mb-1">Role</label>
                        <select name="role" id="edit_role" class="w-full border rounded-lg px-3 py-2 text-sm bg-white">
                            <option value="Initiator">Initiator</option>
                            <option value="Section Chief">Section Chief</option>
                            <option value="Division Chief">Division Chief</option>
                            <option value="ARD">ARD</option>
                            <option value="RED">RED</option>
                            <option value="Records Office">Records Office</option>
                            <option value="Admin">Admin</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-xs font-bold text-gray-700 mb-1">Status</label>
                        <select name="status" id="edit_status" class="w-full border rounded-lg px-3 py-2 text-sm bg-white">
                            <option value="active">Active</option>
                            <option value="pending">Pending</option>
                            <option value="disabled">Disabled</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Division</label>
                            <input type="text" name="division" id="edit_division" class="w-full border rounded-lg px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">Position</label>
                            <input type="text" name="position" id="edit_position" class="w-full border rounded-lg px-3 py-2 text-sm">
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-xs font-bold text-gray-700 mb-1">Link OTOS Employee (Optional)</label>
                        <div class="flex gap-2">
                            <input type="hidden" name="otos_userlink" id="edit_otos_id" value="0">
                            <input type="text" id="edit_otos_name" readonly placeholder="No Employee Selected" 
                                   class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-500 cursor-not-allowed">
                            <button type="button" onclick="openOtosModal('edit')" 
                                    class="px-3 py-2 bg-indigo-600 text-white rounded-lg text-xs font-medium hover:bg-indigo-700 whitespace-nowrap">
                                <i class="fas fa-search"></i> Select
                            </button>
                            <button type="button" onclick="clearOtosSelection('edit')" 
                                    class="px-3 py-2 bg-gray-200 text-gray-600 rounded-lg text-xs font-medium hover:bg-gray-300">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700">Save Changes</button>
                    </div>
                </form>

                <form action="" method="POST" id="form-security" class="hidden">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" id="reset_user_id">
                    
                    <div class="bg-yellow-50 border border-yellow-200 rounded p-3 mb-4 text-xs text-yellow-800">
                        <i class="fas fa-exclamation-circle mr-1"></i> Admin override. This will immediately change the user's password.
                    </div>

                    <div class="mb-6">
                        <label class="block text-xs font-bold text-gray-700 mb-1">New Password</label>
                        <input type="password" name="new_password" required minlength="8" placeholder="Enter new password" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-red-500 focus:border-red-500">
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="otosModal" class="modal opacity-0 pointer-events-none fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black opacity-60" onclick="closeOtosModal()"></div>
        <div class="bg-white w-full max-w-2xl mx-4 rounded-xl shadow-2xl z-50 overflow-hidden transform transition-all scale-95 flex flex-col max-h-[80vh]" id="otosModalContent">
            
            <div class="bg-indigo-50 px-6 py-4 border-b border-indigo-100 flex justify-between items-center">
                <h3 class="text-lg font-bold text-indigo-900">Select OTOS Employee</h3>
                <button onclick="closeOtosModal()" class="text-indigo-400 hover:text-indigo-700"><i class="fas fa-times"></i></button>
            </div>

            <div class="p-4 bg-white border-b border-gray-100">
                <input type="text" id="otosSearchInput" onkeyup="filterOtosTable()" 
                       placeholder="Search name..." 
                       class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:outline-none">
            </div>

            <div class="overflow-y-auto p-0 flex-grow">
                <table class="w-full text-left border-collapse" id="otosTable">
                    <thead class="bg-gray-50 sticky top-0">
                        <tr>
                            <th class="px-6 py-3 text-xs font-semibold text-gray-600 uppercase">Employee Name</th>
                            <th class="px-6 py-3 text-xs font-semibold text-gray-600 uppercase text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($otos_employees as $emp): ?>
                        <tr class="hover:bg-indigo-50 transition-colors">
                            <td class="px-6 py-3 text-sm text-gray-800 font-medium">
                                <?php echo htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name']); ?>
                            </td>
                            <td class="px-6 py-3 text-right">
                                <button type="button" 
                                        onclick="selectOtosEmployee(<?php echo $emp['id']; ?>, '<?php echo htmlspecialchars($emp['last_name'] . ', ' . $emp['first_name'], ENT_QUOTES); ?>')"
                                        class="px-3 py-1 bg-white border border-indigo-600 text-indigo-600 rounded text-xs hover:bg-indigo-600 hover:text-white transition-colors">
                                    Select
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (empty($otos_employees)): ?>
                    <div class="p-8 text-center text-gray-500 text-sm">No OTOS employees found or database error.</div>
                <?php endif; ?>
            </div>
            
            <div class="p-4 bg-gray-50 border-t border-gray-100 text-right">
                 <button type="button" onclick="closeOtosModal()" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm hover:bg-gray-50">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        // Pass PHP Data to JS for lookup
        const otosData = <?php echo json_encode($otos_employees); ?>;
        
        // Modal Logic
        const addModal = document.getElementById('addModal');
        const editModal = document.getElementById('editModal');
        const otosModal = document.getElementById('otosModal');
        const addContent = document.getElementById('addModalContent');
        const editContent = document.getElementById('editModalContent');
        const otosContent = document.getElementById('otosModalContent');

        // Track which modal opened the OTOS selection ('add' or 'edit')
        let currentOtosMode = null; 

        function openAddModal() {
            addModal.classList.remove('opacity-0', 'pointer-events-none');
            addContent.classList.remove('scale-95');
            addContent.classList.add('scale-100');
            document.body.classList.add('modal-active');
            
            // Clear previous add form selections
            clearOtosSelection('add'); 
        }

        function closeAddModal() {
            addModal.classList.add('opacity-0', 'pointer-events-none');
            addContent.classList.add('scale-95');
            addContent.classList.remove('scale-100');
            document.body.classList.remove('modal-active');
        }

        function openEditModal(user) {
            // Populate fields
            document.getElementById('edit_user_id').value = user.user_id;
            document.getElementById('reset_user_id').value = user.user_id;
            document.getElementById('edit_fname').value = user.first_name;
            document.getElementById('edit_lname').value = user.last_name;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_status').value = user.status;
            document.getElementById('edit_division').value = user.division || '';
            document.getElementById('edit_position').value = user.position || '';

            // Handle OTOS Link Look up
            const otosId = user.otos_userlink || 0;
            const hiddenInput = document.getElementById('edit_otos_id');
            const nameInput = document.getElementById('edit_otos_name');
            
            hiddenInput.value = otosId;
            
            if (otosId > 0 && otosData.length > 0) {
                // Find name in the data dump
                const found = otosData.find(e => e.id == otosId);
                if (found) {
                    nameInput.value = found.last_name + ', ' + found.first_name;
                    nameInput.classList.remove('text-gray-500');
                    nameInput.classList.add('text-gray-900', 'font-medium');
                } else {
                    nameInput.value = "Unknown ID: " + otosId;
                }
            } else {
                nameInput.value = "";
                nameInput.classList.add('text-gray-500');
                nameInput.classList.remove('text-gray-900', 'font-medium');
            }

            switchTab('details');

            editModal.classList.remove('opacity-0', 'pointer-events-none');
            editContent.classList.remove('scale-95');
            editContent.classList.add('scale-100');
            document.body.classList.add('modal-active');
        }

        function closeEditModal() {
            editModal.classList.add('opacity-0', 'pointer-events-none');
            editContent.classList.add('scale-95');
            editContent.classList.remove('scale-100');
            document.body.classList.remove('modal-active');
        }

        // --- OTOS SELECTION LOGIC ---

        function openOtosModal(mode) {
            currentOtosMode = mode; // 'add' or 'edit'
            
            otosModal.classList.remove('opacity-0', 'pointer-events-none');
            otosContent.classList.remove('scale-95');
            otosContent.classList.add('scale-100');
            
            // Reset search
            document.getElementById('otosSearchInput').value = '';
            filterOtosTable();
        }

        function closeOtosModal() {
            otosModal.classList.add('opacity-0', 'pointer-events-none');
            otosContent.classList.add('scale-95');
            otosContent.classList.remove('scale-100');
            currentOtosMode = null;
        }

        function filterOtosTable() {
            var input = document.getElementById("otosSearchInput");
            var filter = input.value.toUpperCase();
            var table = document.getElementById("otosTable");
            var tr = table.getElementsByTagName("tr");

            // Loop through all table rows, and hide those who don't match the search query
            for (var i = 1; i < tr.length; i++) { // Start at 1 to skip header
                var td = tr[i].getElementsByTagName("td")[0];
                if (td) {
                    var txtValue = td.textContent || td.innerText;
                    if (txtValue.toUpperCase().indexOf(filter) > -1) {
                        tr[i].style.display = "";
                    } else {
                        tr[i].style.display = "none";
                    }
                }       
            }
        }

        function selectOtosEmployee(id, name) {
            if (!currentOtosMode) return;

            const hiddenId = document.getElementById(currentOtosMode + '_otos_id');
            const nameInput = document.getElementById(currentOtosMode + '_otos_name');

            if (hiddenId && nameInput) {
                hiddenId.value = id;
                nameInput.value = name;
                nameInput.classList.remove('text-gray-500');
                nameInput.classList.add('text-gray-900', 'font-medium');
            }

            closeOtosModal();
        }

        function clearOtosSelection(mode) {
            const hiddenId = document.getElementById(mode + '_otos_id');
            const nameInput = document.getElementById(mode + '_otos_name');
            
            if (hiddenId && nameInput) {
                hiddenId.value = 0;
                nameInput.value = '';
                nameInput.classList.add('text-gray-500');
                nameInput.classList.remove('text-gray-900', 'font-medium');
            }
        }

        function switchTab(tab) {
            const btnDetails = document.getElementById('tab-details');
            const btnSecurity = document.getElementById('tab-security');
            const formDetails = document.getElementById('form-details');
            const formSecurity = document.getElementById('form-security');

            if (tab === 'details') {
                btnDetails.classList.add('border-indigo-600', 'text-indigo-600');
                btnDetails.classList.remove('text-gray-500', 'border-transparent');
                btnSecurity.classList.remove('border-indigo-600', 'text-indigo-600');
                btnSecurity.classList.add('text-gray-500');
                
                formDetails.classList.remove('hidden');
                formSecurity.classList.add('hidden');
            } else {
                btnSecurity.classList.add('border-indigo-600', 'text-indigo-600');
                btnSecurity.classList.remove('text-gray-500', 'border-transparent');
                btnDetails.classList.remove('border-indigo-600', 'text-indigo-600');
                btnDetails.classList.add('text-gray-500');

                formSecurity.classList.remove('hidden');
                formDetails.classList.add('hidden');
            }
        }
        
        document.addEventListener('keydown', function(event) {
            if (event.key === "Escape") {
                // Close top-most modal first
                if (!otosModal.classList.contains('opacity-0')) {
                    closeOtosModal();
                } else {
                    closeAddModal();
                    closeEditModal();
                }
            }
        });
    </script>
</body>
</html>