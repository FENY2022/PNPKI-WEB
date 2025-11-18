<?php
session_start();

// --- Security: require login ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// --- DB connection (Using MySQLi) ---
$conn = null; // Use $conn for MySQLi object
if (file_exists(__DIR__ . '/config.php')) {
    // Assuming config.php/db.php provides a $conn MySQLi instance.
    require_once __DIR__ . '/config.php';
} elseif (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
} else {
    // fallback MySQLi connection (local defaults)
    $dbHost = '127.0.0.1';
    $dbName = 'ddts_pnpki';
    $dbUser = 'root';
    $dbPass = '';

    // Establishing connection
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    
    // Check connection
    if ($conn->connect_error) {
        die("Database connection error: " . $conn->connect_error);
    }
}

// Ensure the connection variable is a MySQLi object
if (!($conn instanceof mysqli)) {
    die("Database connection error. The MySQLi object (\$conn) is not properly initialized.");
}


// --- CSRF ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}
$csrf_token = $_SESSION['csrf_token'];

// Simple flash messaging
$flash = function($msg = null) {
    if ($msg === null) {
        if (!empty($_SESSION['_flash'])) {
            $m = $_SESSION['_flash'];
            unset($_SESSION['_flash']);
            return $m;
        }
        return '';
    } else {
        $_SESSION['_flash'] = $msg;
    }
};

// --- Helpers ---
function e($v) { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// --- Handle POST actions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $posted_token)) {
        $flash("Invalid request (CSRF token mismatch).");
        header("Location: profile.php");
        exit;
    }

    // Choose action
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $first_name = trim($_POST['first_name'] ?? '');
        $middle_name = trim($_POST['middle_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $suffix = trim($_POST['suffix'] ?? '');
        $position = trim($_POST['position'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $division = trim($_POST['division'] ?? '');
        $sex = $_POST['sex'] ?? null;
        $contact_number = trim($_POST['contact_number'] ?? '');
        $email = trim($_POST['email'] ?? '');

        // Basic validation
        if ($first_name === '' || $last_name === '' || $email === '') {
            $flash("Please fill in required fields: First name, Last name and Email.");
            header("Location: profile.php");
            exit;
        }

        // Email uniqueness check
        $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt_check->bind_param("si", $email, $user_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        
        if ($result_check->num_rows > 0) {
            $flash("The email address is already used by another account.");
            $stmt_check->close();
            header("Location: profile.php");
            exit;
        }
        $stmt_check->close();

        // Update profile
        $update = $conn->prepare("UPDATE users SET email = ?, first_name = ?, middle_name = ?, last_name = ?, suffix = ?, position = ?, designation = ?, division = ?, sex = ?, contact_number = ? WHERE user_id = ?");
        // 's' for string, 'i' for integer. Use 's' for all nullable strings as well.
        $update->bind_param("ssssssssssi", $email, $first_name, $middle_name, $last_name, $suffix, $position, $designation, $division, $sex, $contact_number, $user_id);
        $update->execute();
        $update->close();

        // Update session values 
        $_SESSION['full_name'] = $first_name . ($middle_name ? " {$middle_name}" : "") . " {$last_name}";
        $_SESSION['email'] = $email;

        $flash("Profile updated successfully.");
        header("Location: profile.php");
        exit;
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($new === '' || $confirm === '') {
            $flash("New password fields cannot be blank.");
            header("Location: profile.php");
            exit;
        }
        if ($new !== $confirm) {
            $flash("New password and confirmation do not match.");
            header("Location: profile.php");
            exit;
        }
        // Fetch current hash
        $stmt_fetch = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt_fetch->bind_param("i", $user_id);
        $stmt_fetch->execute();
        $result_fetch = $stmt_fetch->get_result();
        $row = $result_fetch->fetch_assoc();
        $stmt_fetch->close();

        if (!$row) {
            $flash("Account not found.");
            header("Location: profile.php");
            exit;
        }
        $hash = $row['password_hash'];
        if (!password_verify($current, $hash)) {
            $flash("Current password is incorrect.");
            header("Location: profile.php");
            exit;
        }
        
        // Update password
        $new_hash = password_hash($new, PASSWORD_BCRYPT);
        $upd = $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $upd->bind_param("si", $new_hash, $user_id);
        $upd->execute();
        $upd->close();

        $flash("Password changed successfully.");
        header("Location: profile.php");
        exit;
    }

    if ($action === 'upload_picture' && isset($_FILES['profile_picture'])) {
        $file = $_FILES['profile_picture'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $flash("File upload error. Code: " . $file['error']);
            header("Location: profile.php");
            exit;
        }
        // Validation
        $maxSize = 3 * 1024 * 1024; // 3MB
        if ($file['size'] > $maxSize) {
            $flash("File is too large. Max 3MB.");
            header("Location: profile.php");
            exit;
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($allowed[$mime])) {
            $flash("Invalid image type. Allowed: JPG, PNG, WEBP.");
            header("Location: profile.php");
            exit;
        }
        $ext = $allowed[$mime];
        $uploadDir = __DIR__ . '/uploads/profile_pics';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        // create secure filename
        $fname = sprintf('%s_%s.%s', $user_id, bin2hex(random_bytes(6)), $ext);
        $dest = $uploadDir . '/' . $fname;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $flash("Failed to save uploaded file.");
            header("Location: profile.php");
            exit;
        }

        // Update DB, store relative path
        $relPath = 'uploads/profile_pics/' . $fname;

        // Optionally remove previous picture
        $stmt_prev = $conn->prepare("SELECT profile_picture_path FROM users WHERE user_id = ?");
        $stmt_prev->bind_param("i", $user_id);
        $stmt_prev->execute();
        $prev = $stmt_prev->get_result()->fetch_column();
        $stmt_prev->close();
        
        if ($prev) {
            $prevFull = __DIR__ . '/' . $prev;
            // Security check to ensure file is within the project directory before unlinking
            if (is_file($prevFull) && strpos(realpath($prevFull), realpath(__DIR__)) === 0) {
                @unlink($prevFull); // best effort
            }
        }

        $upd = $conn->prepare("UPDATE users SET profile_picture_path = ? WHERE user_id = ?");
        $upd->bind_param("si", $relPath, $user_id);
        $upd->execute();
        $upd->close();
        
        // Update session variable for immediate dashboard display
        $_SESSION['profile_picture_path'] = $relPath;

        $flash("Profile picture updated.");
        header("Location: profile.php");
        exit;
    }
}

// --- Load user record for display ---
$stmt = $conn->prepare("SELECT user_id, email, first_name, middle_name, last_name, suffix, position, designation, division, sex, contact_number, role, status, profile_picture_path FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die("User account not found.");
}

// Build friendly name
$full_name = trim($user['first_name'] . ' ' . ($user['middle_name'] ?: '') . ' ' . $user['last_name']);

// flash message
$message = $flash();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>My Profile - DDTMS DENR CARAGA</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
            min-height: 100vh;
        }
        
        .card-shadow {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        
        .card-shadow:hover {
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }
        
        .profile-pic-container {
            position: relative;
            display: inline-block;
        }
        
        .profile-pic-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            cursor: pointer;
        }
        
        .profile-pic-container:hover .profile-pic-overlay {
            opacity: 1;
        }
        
        .avatar-placeholder { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            font-weight: 600; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            border: 3px solid white;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .form-input {
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
        }
        
        .form-input:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            transition: all 0.3s ease;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.3);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            transition: all 0.3s ease;
        }
        
        .btn-warning:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.3);
        }
        
        .tab-active {
            border-bottom: 3px solid #4f46e5;
            color: #4f46e5;
            font-weight: 600;
        }
        
        .section-title {
            position: relative;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            border-radius: 3px;
        }
        
        .flash-message {
            animation: slideIn 0.5s ease-out;
        }
        
        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-active {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .status-inactive {
            background-color: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body class="min-h-screen text-gray-700">


    <div class="max-w-6xl mx-auto p-6">
   

        <?php if ($message): 
            $is_error = strpos(strtolower($message), 'error') !== false || strpos(strtolower($message), 'incorrect') !== false || strpos(strtolower($message), 'mismatch') !== false || strpos(strtolower($message), 'invalid') !== false;
            $flash_bg = $is_error ? 'bg-red-50 border-red-200 text-red-700' : 'bg-green-50 border-green-200 text-green-700';
            $flash_icon = $is_error ? 'fa-exclamation-triangle text-red-500' : 'fa-check-circle text-green-500';
        ?>
            <div class="mb-6 p-4 <?= $flash_bg ?> border rounded-lg flash-message flex items-center">
                <i class="fas <?= $flash_icon ?> mr-2"></i>
                <span><?= e($message) ?></span>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl card-shadow p-6 sticky top-6">
                    <div class="flex flex-col items-center space-y-4">
                        <div class="profile-pic-container">
                            <?php if (!empty($user['profile_picture_path']) && file_exists(__DIR__ . '/' . $user['profile_picture_path'])): ?>
                                <img src="<?= e($user['profile_picture_path']) ?>" alt="avatar" class="w-32 h-32 rounded-full object-cover border-4 border-white shadow-md">
                            <?php else: 
                                $initials = strtoupper(substr($user['first_name'],0,1) . (isset($user['last_name'][0]) ? $user['last_name'][0] : ''));
                            ?>
                                <div class="w-32 h-32 rounded-full avatar-placeholder text-2xl"><?= e($initials) ?></div>
                            <?php endif; ?>
                            
                            <div class="profile-pic-overlay">
                                <i class="fas fa-camera text-white text-xl"></i>
                            </div>
                        </div>

                        <div class="text-center">
                            <h2 class="text-xl font-bold text-gray-800"><?= e($full_name) ?></h2>
                            <p class="text-sm text-gray-600 mt-1"><?= e($user['position'] ?: 'No position specified') ?></p>
                            <p class="text-xs text-indigo-600 font-medium mt-2"><?= e($user['role']) ?></p>
                        </div>

                        <div class="w-full border-t border-gray-100 pt-4">
                            <div class="space-y-3">
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-envelope text-gray-400 w-5 mr-2"></i>
                                    <span class="text-gray-600"><?= e($user['email']) ?></span>
                                </div>
                                <?php if ($user['contact_number']): ?>
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-phone text-gray-400 w-5 mr-2"></i>
                                    <span class="text-gray-600"><?= e($user['contact_number']) ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if ($user['division']): ?>
                                <div class="flex items-center text-sm">
                                    <i class="fas fa-building text-gray-400 w-5 mr-2"></i>
                                    <span class="text-gray-600"><?= e($user['division']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <form action="profile.php" method="post" enctype="multipart/form-data" class="w-full mt-4">
                            <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                            <input type="hidden" name="action" value="upload_picture">
                            
                            <label class="block text-sm font-medium text-gray-700 mb-2">Update Profile Picture</label>
                            <input type="file" name="profile_picture" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 mb-3" />
                            <button class="w-full btn-primary text-white py-2.5 rounded-lg text-sm font-medium flex items-center justify-center">
                                <i class="fas fa-upload mr-2"></i>
                                Upload Picture
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-3">
                <div class="bg-white rounded-xl card-shadow mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="flex -mb-px">
                            <button id="profile-tab" class="tab-active py-4 px-6 text-center border-transparent font-medium text-sm">
                                <i class="fas fa-user-edit mr-2"></i>Edit Profile
                            </button>
                            <button id="password-tab" class="py-4 px-6 text-center border-transparent font-medium text-sm text-gray-500 hover:text-gray-700">
                                <i class="fas fa-key mr-2"></i>Change Password
                            </button>
                        </nav>
                    </div>
                </div>

                <div id="profile-content" class="bg-white rounded-xl card-shadow p-6">
                    <h3 class="section-title text-xl font-bold text-gray-800">Personal Information</h3>
                    
                    <form action="profile.php" method="post" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">First name <span class="text-red-500">*</span></label>
                                <input name="first_name" value="<?= e($user['first_name']) ?>" class="w-full form-input rounded-lg px-4 py-3 text-sm" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Middle name</label>
                                <input name="middle_name" value="<?= e($user['middle_name']) ?>" class="w-full form-input rounded-lg px-4 py-3 text-sm">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Last name <span class="text-red-500">*</span></label>
                                <input name="last_name" value="<?= e($user['last_name']) ?>" class="w-full form-input rounded-lg px-4 py-3 text-sm" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Suffix</label>
                                <input name="suffix" value="<?= e($user['suffix']) ?>" class="w-full form-input rounded-lg px-4 py-3 text-sm">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Position</label>
                                <input name="position" value="<?= e($user['position']) ?>" class="w-full form-input rounded-lg px-4 py-3 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Designation</label>
                                <input name="designation" value="<?= e($user['designation']) ?>" class="w-full form-input rounded-lg px-4 py-3 text-sm">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Division</label>
                                <input name="division" value="<?= e($user['division']) ?>" class="w-full form-input rounded-lg px-4 py-3 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Sex</label>
                                <select name="sex" class="w-full form-input rounded-lg px-4 py-3 text-sm">
                                    <option value="">-- select --</option>
                                    <option value="Male" <?= $user['sex'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= $user['sex'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                    <option value="Other" <?= $user['sex'] === 'Other' ? 'selected' : '' ?>>Other</option>
                                    <option value="Prefer not to say" <?= $user['sex'] === 'Prefer not to say' ? 'selected' : '' ?>>Prefer not to say</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Contact number</label>
                                <input name="contact_number" value="<?= e($user['contact_number']) ?>" class="w-full form-input rounded-lg px-4 py-3 text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Email <span class="text-red-500">*</span></label>
                                <input name="email" value="<?= e($user['email']) ?>" type="email" class="w-full form-input rounded-lg px-4 py-3 text-sm" required>
                            </div>
                        </div>

                        <div class="flex items-center justify-end pt-4">
                            <button type="submit" class="btn-success text-white px-6 py-3 rounded-lg text-sm font-medium flex items-center">
                                <i class="fas fa-save mr-2"></i>
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <div id="password-content" class="bg-white rounded-xl card-shadow p-6 hidden">
                    <h3 class="section-title text-xl font-bold text-gray-800">Change Password</h3>
                    
                    <form action="profile.php" method="post" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="grid grid-cols-1 md:grid-cols-1 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Current password</label>
                                <input name="current_password" type="password" class="w-full form-input rounded-lg px-4 py-3 text-sm" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">New password</label>
                                <input name="new_password" type="password" class="w-full form-input rounded-lg px-4 py-3 text-sm" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Confirm new password</label>
                                <input name="confirm_password" type="password" class="w-full form-input rounded-lg px-4 py-3 text-sm" required>
                            </div>
                        </div>
                        
                        <div class="flex items-center justify-end pt-4">
                            <button type="submit" class="btn-warning text-white px-6 py-3 rounded-lg text-sm font-medium flex items-center">
                                <i class="fas fa-key mr-2"></i>
                                Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="mt-8 text-center text-xs text-gray-500">
            <p>Note: Changing your email here will update your login email. If your project requires email verification on change, adapt this flow accordingly.</p>
        </div>
    </div>

    <script>
        // Tab functionality
        document.getElementById('profile-tab').addEventListener('click', function() {
            // Update classes for active tab button
            this.classList.add('tab-active');
            document.getElementById('password-tab').classList.remove('tab-active');
            // Toggle content visibility
            document.getElementById('profile-content').classList.remove('hidden');
            document.getElementById('password-content').classList.add('hidden');
        });
        
        document.getElementById('password-tab').addEventListener('click', function() {
            // Update classes for active tab button
            this.classList.add('tab-active');
            document.getElementById('profile-tab').classList.remove('tab-active');
            // Toggle content visibility
            document.getElementById('password-content').classList.remove('hidden');
            document.getElementById('profile-content').classList.add('hidden');
        });
        
        // Profile picture upload trigger (Clicking the picture triggers the file input)
        document.querySelector('.profile-pic-container').addEventListener('click', function() {
            // Check if the click was directly on the overlay or the container itself
            if (event.target.closest('.profile-pic-overlay') || event.target.closest('.profile-pic-container') === this) {
                 document.querySelector('input[name="profile_picture"]').click();
            }
        });
        
        // Show filename when file is selected (for better user feedback)
        document.querySelector('input[name="profile_picture"]').addEventListener('change', function() {
            if (this.files.length > 0) {
                // You could dynamically update a label here to show the selected file name
                // e.g., const fileName = this.files[0].name;
            }
        });

        // Initial check to show the correct tab content after a POST request with a flash message
        document.addEventListener('DOMContentLoaded', function() {
            const flash = document.querySelector('.flash-message');
            if (flash) {
                // If the flash message pertains to password change (e.g., error/success), switch to the password tab.
                // This logic assumes password messages contain 'password'. Adjust as necessary.
                const messageText = flash.textContent || flash.innerText;
                if (messageText.toLowerCase().includes('password')) {
                    document.getElementById('password-tab').click();
                }
            }
        });
    </script>
</body>
</html>