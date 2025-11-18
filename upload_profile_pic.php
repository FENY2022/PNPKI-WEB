<?php
session_start();
require_once 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Not logged in
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if a file was uploaded
if (isset($_FILES['profilePic']) && $_FILES['profilePic']['error'] == 0) {
    
    $file = $_FILES['profilePic'];
    
    // --- Configuration ---
    $target_dir = "uploads/profile_pics/"; // Make sure this directory exists and is writable!
    $max_file_size = 2 * 1024 * 1024; // 2 MB
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

    // --- File Validation ---
    $file_name = $file['name'];
    $file_size = $file['size'];
    $file_tmp = $file['tmp_name'];
    $file_ext_parts = explode('.', $file_name);
    $file_ext = strtolower(end($file_ext_parts));

    // 1. Check file type
    if (!in_array($file_ext, $allowed_types)) {
        header('Location: profile.php?error=Invalid file type. Only JPG, PNG, GIF allowed.');
        exit();
    }

    // 2. Check file size
    if ($file_size > $max_file_size) {
        header('Location: profile.php?error=File is too large. Max size is 2MB.');
        exit();
    }

    // 3. Check for image properties (optional but recommended)
    $check = getimagesize($file_tmp);
    if ($check === false) {
        header('Location: profile.php?error=File is not a valid image.');
        exit();
    }

    // --- File Upload ---
    
    // Create a new, unique filename to prevent overwrites
    // e.g., user_1_1678886400.jpg
    $new_file_name = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
    $target_path = $target_dir . $new_file_name;

    // --- (Optional) Delete old profile picture ---
    // Fetch old path first
    $stmt_old = $mysqli->prepare("SELECT profile_picture_path FROM users WHERE user_id = ?");
    $stmt_old->bind_param('i', $user_id);
    $stmt_old->execute();
    $result_old = $stmt_old->get_result();
    $user_old = $result_old->fetch_assoc();
    $stmt_old->close();

    if ($user_old && !empty($user_old['profile_picture_path'])) {
        $old_pic_path = $user_old['profile_picture_path'];
        // Make sure we don't delete the default pic or a placeholder
        if (file_exists($old_pic_path) && strpos($old_pic_path, 'default.png') === false) {
            unlink($old_pic_path);
        }
    }
    // --- End optional delete ---

    // Move the uploaded file
    if (move_uploaded_file($file_tmp, $target_path)) {
        // File uploaded successfully, now update the database
        
        $stmt_update = $mysqli->prepare("UPDATE users SET profile_picture_path = ? WHERE user_id = ?");
        $stmt_update->bind_param('si', $target_path, $user_id);
        
        if ($stmt_update->execute()) {
            // Success!
            $stmt_update->close();
            $mysqli->close();
            header('Location: profile.php?success=1');
            exit();
        } else {
            // Database update failed
            $stmt_update->close();
            // (Optional: Delete the file we just uploaded since DB failed)
            // unlink($target_path); 
            header('Location: profile.php?error=Database update failed.');
            exit();
        }

    } else {
        // File move failed (check permissions on 'uploads/profile_pics/')
        header('Location: profile.php?error=Failed to save uploaded file. Check server permissions.');
        exit();
    }

} else {
    // No file uploaded or an error occurred
    $upload_error = $_FILES['profilePic']['error'] ?? 'No file provided.';
    header('Location: profile.php?error=Upload failed: ' . $upload_error);
    exit();
}
?>