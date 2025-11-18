<?php
session_start();
require_once 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); // Redirect to your login page
    exit();
}

// Fetch user data
$user_id = $_SESSION['user_id'];
$stmt = $mysqli->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    die("Error: User not found.");
}

// Use a default profile pic if none is set
$profile_pic_path = $user['profile_picture_path'] ?? 'uploads/profile_pics/default.png';
// Check if default.png exists, otherwise use a placeholder
if (!file_exists($profile_pic_path)) {
     $profile_pic_path = 'https://placehold.co/100x100/EBF8FF/3498DB?text=...';
}

// Check for success or error messages from upload
$message = '';
if (isset($_GET['success'])) {
    $message = '<div class="alert alert-success">Profile picture updated successfully!</div>';
} elseif (isset($_GET['error'])) {
    $error_msg = htmlspecialchars($_GET['error']);
    $message = '<div class="alert alert-error">' . $error_msg . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <!-- Sidebar Navigation -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            PNPKI-WEB
        </div>
        <ul class="sidebar-nav">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="profile.php" class="active">Profile</a></li>
            <!-- Add other nav links here -->
            <li><a href="#">Documents</a></li>
            <li><a href="#">Reports</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>

    <!-- Main Content Area -->
    <div class="main-content" id="main-content">
        <header class="header">
            <h1>User Profile</h1>
            <button class="menu-toggle" id="menu-toggle" aria-label="Open menu">&#9776;</button>
        </header>

        <main>
            <div class="card profile-card">
                <?php echo $message; // Display success/error messages ?>
                
                <div class="profile-header">
                    <img src="<?php echo htmlspecialchars($profile_pic_path); ?>?t=<?php echo time(); // Cache buster ?>" alt="Profile Picture" class="profile-pic">
                    <div class="profile-info">
                        <h2><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                        <p><?php echo htmlspecialchars($user['email']); ?></p>
                        <p>Role: <?php echo htmlspecialchars($user['role']); ?></p>
                    </div>
                </div>

                <h3>Update Profile Picture</h3>
                <form action="upload_profile_pic.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="profilePic">Choose a new picture:</label>
                        <input type="file" name="profilePic" id="profilePic" accept="image/jpeg, image/png, image/gif" required>
                    </div>
                    <p style="font-size: 0.9rem; color: #555;">Max file size: 2MB. Allowed types: JPG, PNG, GIF.</p>
                    <button type="submit" class="btn">Upload Picture</button>
                </form>

                <hr style="margin: 30px 0;">

                <h3>Your Details</h3>
                <p><strong>Position:</strong> <?php echo htmlspecialchars($user['position'] ?? 'N/A'); ?></p>
                <p><strong>Designation:</strong> <?php echo htmlspecialchars($user['designation'] ?? 'N/A'); ?></p>
                <p><strong>Division:</strong> <?php echo htmlspecialchars($user['division'] ?? 'N/A'); ?></p>
                <p><strong>Contact:</strong> <?php echo htmlspecialchars($user['contact_number'] ?? 'N/A'); ?></p>
                
                <!-- Add a link to a separate edit_profile.php page if you have one -->
                <!-- <a href="edit_profile.php" class="btn">Edit Details</a> -->
            </div>
        </main>
    </div>

    <script>
        // JavaScript for mobile menu toggle
        const menuToggle = document.getElementById('menu-toggle');
        const sidebar = document.getElementById('sidebar');

        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
    </script>

</body>
</html>