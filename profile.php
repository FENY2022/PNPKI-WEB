<?php
session_start();

// --- Security: require login ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// --- DB connection ---
// If your project already has a config/db file that sets $pdo or $conn, the file will be used.
// Otherwise we create a lightweight PDO connection as a fallback.
// Edit the DSN/credentials below to match your environment or replace this block with your project's DB include.
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php'; // should provide $pdo (PDO instance) or adjust accordingly
} elseif (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
} else {
    // fallback PDO (local defaults)
    $dbHost = '127.0.0.1';
    $dbName = 'ddts_pnpki';
    $dbUser = 'root';
    $dbPass = '';
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    try {
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (Exception $ex) {
        die("Database connection error. Please configure your DB connection in config.php.");
    }
}

// Prefer $pdo variable
if (!isset($pdo) && isset($conn) && $conn instanceof mysqli) {
    // convert mysqli to PDO-like wrapper is out of scope; prefer to set $pdo in config
    die("This page expects a PDO instance named \$pdo. Please adapt your project's DB include.");
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
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->execute([$email, $user_id]);
        if ($stmt->fetch()) {
            $flash("The email address is already used by another account.");
            header("Location: profile.php");
            exit;
        }

        $update = $pdo->prepare("UPDATE users SET email = ?, first_name = ?, middle_name = ?, last_name = ?, suffix = ?, position = ?, designation = ?, division = ?, sex = ?, contact_number = ? WHERE user_id = ?");
        $update->execute([$email, $first_name, $middle_name ?: null, $last_name, $suffix ?: null, $position ?: null, $designation ?: null, $division ?: null, $sex ?: null, $contact_number ?: null, $user_id]);

        // Update session values (so dashboard shows updated name/email immediately)
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
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch();
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
        $new_hash = password_hash($new, PASSWORD_BCRYPT);
        $upd = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        $upd->execute([$new_hash, $user_id]);

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
        $stmt = $pdo->prepare("SELECT profile_picture_path FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $prev = $stmt->fetchColumn();
        if ($prev) {
            $prevFull = __DIR__ . '/' . $prev;
            if (is_file($prevFull) && strpos(realpath($prevFull), realpath(__DIR__)) === 0) {
                @unlink($prevFull); // best effort
            }
        }

        $upd = $pdo->prepare("UPDATE users SET profile_picture_path = ? WHERE user_id = ?");
        $upd->execute([$relPath, $user_id]);
        
        // **** THIS IS THE NEW LINE ****
        $_SESSION['profile_picture_path'] = $relPath;
        // **** END NEW LINE ****

        $flash("Profile picture updated.");
        header("Location: profile.php");
        exit;
    }
}

// --- Load user record for display ---
$stmt = $pdo->prepare("SELECT user_id, email, first_name, middle_name, last_name, suffix, position, designation, division, sex, contact_number, role, status, profile_picture_path FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
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
    <style>
        .avatar-placeholder { background: #e0e7ff; color: #312e81; font-weight:700; display:inline-flex; align-items:center; justify-content:center; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen text-gray-700">
    <div class="max-w-4xl mx-auto p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-semibold">My Profile</h1>
            <a href="dashboard.php" class="text-sm text-blue-600 hover:underline">Back to Dashboard</a>
        </div>

        <?php if ($message): ?>
            <div class="mb-4 p-3 bg-green-50 border border-green-100 text-green-800 rounded-md"><?= e($message) ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white p-4 rounded-md card-shadow">
                <div class="flex flex-col items-center space-y-3">
                    <?php if (!empty($user['profile_picture_path']) && file_exists(__DIR__ . '/' . $user['profile_picture_path'])): ?>
                        <img src="<?= e($user['profile_picture_path']) ?>" alt="avatar" class="w-28 h-28 rounded-full object-cover border-2 border-blue-50">
                    <?php else: 
                        $initials = strtoupper(substr($user['first_name'],0,1) . (isset($user['last_name'][0]) ? $user['last_name'][0] : ''));
                    ?>
                        <div class="w-28 h-28 rounded-full avatar-placeholder text-xl"><?= e($initials) ?></div>
                    <?php endif; ?>

                    <div class="text-center">
                        <div class="font-semibold"><?= e($full_name) ?></div>
                        <div class="text-xs text-gray-500"><?= e($user['role']) ?></div>
                    </div>

                    <form action="profile.php" method="post" enctype="multipart/form-data" class="w-full mt-2">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                        <input type="hidden" name="action" value="upload_picture">
                        <label class="block text-xs text-gray-600 mb-1">Change profile picture</label>
                        <input type="file" name="profile_picture" accept="image/*" class="block w-full text-sm mb-2" />
                        <button class="w-full bg-blue-600 text-white py-2 rounded-md text-sm">Upload</button>
                    </form>
                </div>
            </div>

            <div class="md:col-span-2 space-y-4">
                <div class="bg-white p-4 rounded-md card-shadow">
                    <form action="profile.php" method="post" class="space-y-3">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="text-xs text-gray-600">First name *</label>
                                <input name="first_name" value="<?= e($user['first_name']) ?>" class="mt-1 w-full border rounded px-3 py-2 text-sm" required>
                            </div>
                            <div>
                                <label class="text-xs text-gray-600">Middle name</label>
                                <input name="middle_name" value="<?= e($user['middle_name']) ?>" class="mt-1 w-full border rounded px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="text-xs text-gray-600">Last name *</label>
                                <input name="last_name" value="<?= e($user['last_name']) ?>" class="mt-1 w-full border rounded px-3 py-2 text-sm" required>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="text-xs text-gray-600">Suffix</label>
                                <input name="suffix" value="<?= e($user['suffix']) ?>" class="mt-1 w-full border rounded px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="text-xs text-gray-600">Position</label>
                                <input name="position" value="<?= e($user['position']) ?>" class="mt-1 w-full border rounded px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="text-xs text-gray-600">Designation</label>
                                <input name="designation" value="<?= e($user['designation']) ?>" class="mt-1 w-full border rounded px-3 py-2 text-sm">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="text-xs text-gray-600">Division</label>
                                <input name="division" value="<?= e($user['division']) ?>" class="mt-1 w-full border rounded px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label class="text-xs text-gray-600">Sex</label>
                                <select name="sex" class="mt-1 w-full border rounded px-3 py-2 text-sm">
                                    <option value="">-- select --</option>
                                    <option value="Male" <?= $user['sex'] === 'Male' ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= $user['sex'] === 'Female' ? 'selected' : '' ?>>Female</option>
                                    <option value="Other" <?= $user['sex'] === 'Other' ? 'selected' : '' ?>>Other</option>
                                    <option value="Prefer not to say" <?= $user['sex'] === 'Prefer not to say' ? 'selected' : '' ?>>Prefer not to say</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-xs text-gray-600">Contact number</label>
                                <input name="contact_number" value="<?= e($user['contact_number']) ?>" class="mt-1 w-full border rounded px-3 py-2 text-sm">
                            </div>
                        </div>

                        <div>
                            <label class="text-xs text-gray-600">Email *</label>
                            <input name="email" value="<?= e($user['email']) ?>" type="email" class="mt-1 w-full border rounded px-3 py-2 text-sm" required>
                        </div>

                        <div class="flex items-center justify-end">
                            <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded text-sm">Save changes</button>
                        </div>
                    </form>
                </div>

                <div class="bg-white p-4 rounded-md card-shadow">
                    <h3 class="text-sm font-semibold mb-2">Change password</h3>
                    <form action="profile.php" method="post" class="space-y-3">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
                        <input type="hidden" name="action" value="change_password">
                        <div>
                            <label class="text-xs text-gray-600">Current password</label>
                            <input name="current_password" type="password" class="mt-1 w-full border rounded px-3 py-2 text-sm" required>
                        </div>
                        <div>
                            <label class="text-xs text-gray-600">New password</label>
                            <input name="new_password" type="password" class="mt-1 w-full border rounded px-3 py-2 text-sm" required>
                        </div>
                        <div>
                            <label class="text-xs text-gray-600">Confirm new password</label>
                            <input name="confirm_password" type="password" class="mt-1 w-full border rounded px-3 py-2 text-sm" required>
                        </div>
                        <div class="flex items-center justify-end">
                            <button type="submit" class="bg-yellow-600 text-white px-4 py-2 rounded text-sm">Change password</button>
                        </div>
                    </form>
                </div>

            </div>
        </div>

        <div class="mt-6 text-xs text-gray-400">
            Note: changing your email here will update your login email. If your project requires email verification on change, adapt this flow accordingly.
        </div>
    </div>
</body>
</html>