<?php
session_start();
require_once 'db.php'; // Ensure db.php is in the same directory

// --- 1. Security & Access Control ---
// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Redirect if not Admin (Strict Role Check)
if ($_SESSION['role'] !== 'Admin') {
    // Log unauthorized access attempt?
    header("Location: dashboard_home.php"); // Send back to home or error page
    exit;
}

$success_msg = '';
$error_msg = '';

// --- 2. Handle Form Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. Update User Role/Status/Division
    if (isset($_POST['action']) && $_POST['action'] === 'update_user') {
        $edit_user_id = (int)$_POST['user_id'];
        $new_role = $_POST['role'];
        $new_status = $_POST['status'];
        $new_division = trim($_POST['division']);
        
        // Basic Validation
        $allowed_roles = ['Initiator','Section Chief','Division Chief','ARD','RED','Records Office','Admin'];
        $allowed_status = ['pending','active','disabled'];

        if (in_array($new_role, $allowed_roles) && in_array($new_status, $allowed_status)) {
            $stmt = $conn->prepare("UPDATE users SET role = ?, status = ?, division = ? WHERE user_id = ?");
            $stmt->bind_param("sssi", $new_role, $new_status, $new_division, $edit_user_id);
            
            if ($stmt->execute()) {
                $success_msg = "User #$edit_user_id updated successfully.";
            } else {
                $error_msg = "Error updating user: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error_msg = "Invalid role or status selected.";
        }
    }

    // B. Admin Password Reset (Optional but useful)
    if (isset($_POST['action']) && $_POST['action'] === 'reset_password') {
        $reset_user_id = (int)$_POST['user_id'];
        $new_pass = trim($_POST['new_password']);
        
        if (strlen($new_pass) >= 8) {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
            $stmt->bind_param("si", $hash, $reset_user_id);
            
            if ($stmt->execute()) {
                $success_msg = "Password for user #$reset_user_id has been reset.";
            } else {
                $error_msg = "Database error: " . $conn->error;
            }
            $stmt->close();
        } else {
            $error_msg = "Password must be at least 8 characters.";
        }
    }
}

// --- 3. Fetch Users for Display ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$users = [];

$sql = "SELECT user_id, first_name, last_name, email, role, status, division, position, created_at, profile_picture_path 
        FROM users WHERE 1=1";

$params = [];
$types = "";

if ($search) {
    $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR division LIKE ?)";
    $search_param = "%$search%";
    $params = [$search_param, $search_param, $search_param, $search_param];
    $types = "ssss";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
if ($search) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();

// Helper for avatars
function get_initials($fname, $lname) {
    return strtoupper(substr($fname, 0, 1) . substr($lname, 0, 1));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f9fafb; }
        
        /* Modal Transitions */
        .modal { transition: opacity 0.25s ease; }
        body.modal-active { overflow-x: hidden; overflow-y: hidden !important; }
        
        .status-active { background-color: #dcfce7; color: #166534; }
        .status-pending { background-color: #fef9c3; color: #854d0e; }
        .status-disabled { background-color: #fee2e2; color: #991b1b; }
    </style>
</head>
<body class="text-gray-800 p-6">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">User Management</h1>
            <p class="text-sm text-gray-500">Manage system users, roles, and account statuses.</p>
        </div>
        <div class="flex items-center gap-2">
            <form action="" method="GET" class="relative">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search users..." 
                       class="pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-indigo-500 focus:border-indigo-500 w-64 shadow-sm">
                <i class="fas fa-search absolute left-3 top-3 text-gray-400 text-xs"></i>
            </form>
        </div>
    </div>

    <?php if ($success_msg): ?>
        <div class="mb-4 p-3 bg-green-100 border border-green-200 text-green-700 rounded-lg flex items-center gap-2 text-sm">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
        </div>
    <?php endif; ?>
    <?php if ($error_msg): ?>
        <div class="mb-4 p-3 bg-red-100 border border-red-200 text-red-700 rounded-lg flex items-center gap-2 text-sm">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-100 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                        <th class="px-6 py-3">User</th>
                        <th class="px-6 py-3">Role & Division</th>
                        <th class="px-6 py-3">Status</th>
                        <th class="px-6 py-3">Joined</th>
                        <th class="px-6 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $u): ?>
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <?php if (!empty($u['profile_picture_path']) && file_exists($u['profile_picture_path'])): ?>
                                            <img class="h-10 w-10 rounded-full object-cover border border-gray-200" src="<?php echo htmlspecialchars($u['profile_picture_path']); ?>" alt="">
                                        <?php else: ?>
                                            <div class="h-10 w-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-sm">
                                                <?php echo get_initials($u['first_name'], $u['last_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($u['email']); ?></div>
                                            <div class="text-xs text-gray-400"><?php echo htmlspecialchars($u['position']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100 mb-1">
                                        <?php echo htmlspecialchars($u['role']); ?>
                                    </span>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($u['division']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php
                                        $statusClass = 'status-' . strtolower($u['status']);
                                        $statusIcon = match($u['status']) {
                                            'active' => 'fa-check',
                                            'pending' => 'fa-clock',
                                            'disabled' => 'fa-ban',
                                            default => 'fa-circle'
                                        };
                                    ?>
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $statusClass; ?>">
                                        <i class="fas <?php echo $statusIcon; ?> text-[10px]"></i>
                                        <?php echo ucfirst(htmlspecialchars($u['status'])); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <?php echo date('M d, Y', strtotime($u['created_at'])); ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <button onclick='openEditModal(<?php echo json_encode($u); ?>)' class="text-indigo-600 hover:text-indigo-900 font-medium text-xs bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded transition-colors">
                                        Manage
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">
                                No users found matching your search.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        </div>

    <div id="editModal" class="modal opacity-0 pointer-events-none fixed w-full h-full top-0 left-0 flex items-center justify-center z-50">
        <div class="modal-overlay absolute w-full h-full bg-gray-900 opacity-50"></div>
        
        <div class="modal-container bg-white w-11/12 md:max-w-md mx-auto rounded shadow-lg z-50 overflow-y-auto transform transition-all scale-95">
            <div class="flex justify-between items-center px-6 py-4 border-b border-gray-100 bg-gray-50 rounded-t">
                <p class="text-lg font-bold text-gray-800">Manage User</p>
                <button class="modal-close cursor-pointer z-50" onclick="closeEditModal()">
                    <i class="fas fa-times text-gray-500 hover:text-gray-700"></i>
                </button>
            </div>

            <div class="px-6 py-4">
                <form action="admin_users.php" method="POST" id="editForm">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-xs font-bold mb-2 uppercase tracking-wider">User</label>
                        <p id="edit_user_name" class="text-sm font-semibold text-gray-900"></p>
                        <p id="edit_user_email" class="text-xs text-gray-500"></p>
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 text-xs font-bold mb-2" for="role">Role</label>
                        <select name="role" id="edit_role" class="block w-full bg-gray-50 border border-gray-300 text-gray-700 py-2 px-3 rounded leading-tight focus:outline-none focus:bg-white focus:border-indigo-500 text-sm">
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
                        <label class="block text-gray-700 text-xs font-bold mb-2" for="status">Account Status</label>
                        <select name="status" id="edit_status" class="block w-full bg-gray-50 border border-gray-300 text-gray-700 py-2 px-3 rounded leading-tight focus:outline-none focus:bg-white focus:border-indigo-500 text-sm">
                            <option value="active">Active (Can Login)</option>
                            <option value="pending">Pending (Needs Approval)</option>
                            <option value="disabled">Disabled (Locked)</option>
                        </select>
                    </div>

                    <div class="mb-6">
                        <label class="block text-gray-700 text-xs font-bold mb-2" for="division">Division/Office</label>
                        <input type="text" name="division" id="edit_division" class="appearance-none block w-full bg-gray-50 text-gray-700 border border-gray-300 rounded py-2 px-3 leading-tight focus:outline-none focus:bg-white focus:border-indigo-500 text-sm">
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" onclick="togglePasswordReset()" class="mr-auto text-xs text-red-600 hover:text-red-800 underline">Reset Password?</button>
                        <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded hover:bg-gray-300">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700 shadow">Save Changes</button>
                    </div>
                </form>

                <form action="admin_users.php" method="POST" id="resetPassForm" class="hidden mt-4 pt-4 border-t border-gray-100">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="user_id" id="reset_user_id">
                    <p class="text-xs font-bold text-red-600 mb-2 uppercase">Danger Zone: Reset Password</p>
                    <div class="flex gap-2">
                        <input type="password" name="new_password" placeholder="New Password" required minlength="8" class="flex-1 appearance-none block bg-white text-gray-700 border border-red-300 rounded py-2 px-3 leading-tight focus:outline-none focus:border-red-500 text-sm">
                        <button type="submit" class="px-3 py-2 bg-red-600 text-white text-xs rounded hover:bg-red-700">Reset</button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <script>
        const modal = document.querySelector('.modal');
        const modalContainer = document.querySelector('.modal-container');

        function openEditModal(user) {
            // Populate fields
            document.getElementById('edit_user_id').value = user.user_id;
            document.getElementById('reset_user_id').value = user.user_id;
            document.getElementById('edit_user_name').textContent = user.first_name + ' ' + user.last_name;
            document.getElementById('edit_user_email').textContent = user.email;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_status').value = user.status;
            document.getElementById('edit_division').value = user.division || '';
            
            // Hide password reset if it was open
            document.getElementById('resetPassForm').classList.add('hidden');

            // Show modal
            modal.classList.remove('opacity-0', 'pointer-events-none');
            modalContainer.classList.remove('scale-95');
            modalContainer.classList.add('scale-100');
            document.body.classList.add('modal-active');
        }

        function closeEditModal() {
            modal.classList.add('opacity-0', 'pointer-events-none');
            modalContainer.classList.add('scale-95');
            modalContainer.classList.remove('scale-100');
            document.body.classList.remove('modal-active');
        }

        function togglePasswordReset() {
            const form = document.getElementById('resetPassForm');
            form.classList.toggle('hidden');
        }

        // Close on overlay click
        document.querySelector('.modal-overlay').addEventListener('click', closeEditModal);
        
        // Close on Escape
        document.onkeydown = function(evt) {
            evt = evt || window.event;
            if (evt.keyCode == 27) {
                closeEditModal();
            }
        };
    </script>
</body>
</html>