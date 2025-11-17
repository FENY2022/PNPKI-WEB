<?php
require_once 'db.php';

$message = "";
$is_success = false;

if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $conn->real_escape_string($_GET['token']);

    // 1. Find the user with this token
    $sql = "SELECT user_id, token_expiry FROM users WHERE verification_token = ? AND status = 'pending'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        $token_expiry = strtotime($user['token_expiry']);

        // 2. Check if the token is expired
        if ($token_expiry > time()) {
            // 3. Token is valid, activate the user
            $update_sql = "UPDATE users SET status = 'active', verification_token = NULL, token_expiry = NULL WHERE user_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $user['user_id']);
            
            if ($update_stmt->execute()) {
                $message = "Account successfully activated! You can now log in.";
                $is_success = true;
            } else {
                $message = "Error: Could not activate your account. Please try again later.";
            }
            $update_stmt->close();
        } else {
            // Token has expired
            $message = "Error: This verification link has expired. Please register again to receive a new link.";
            // You could also add logic here to delete the expired user record
        }
    } else {
        // No user found with this token, or account is already active
        $message = "Error: This verification link is invalid or has already been used.";
    }
    $stmt->close();
} else {
    $message = "Error: No verification token provided.";
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Verification - DDTMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="max-w-md w-full bg-white p-8 rounded-xl shadow-lg text-center">
        <?php if ($is_success): ?>
            <svg class="w-16 h-16 mx-auto text-green-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.27a11.97 11.97 0 013.298 2.395A11.97 11.97 0 0112 20.25c-2.455 0-4.743-.873-6.505-2.395a12 12 0 013.298-2.395"></path></svg>
            <h1 class="text-2xl font-bold text-gray-900 mb-3">Verification Successful!</h1>
        <?php else: ?>
            <svg class="w-16 h-16 mx-auto text-red-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            <h1 class="text-2xl font-bold text-gray-900 mb-3">Verification Failed</h1>
        <?php endif; ?>
        
        <p class="text-gray-600 mb-6"><?php echo htmlspecialchars($message); ?></p>
        
        <?php if ($is_success): ?>
            <a href="login.php" class="inline-block w-full px-6 py-3 bg-blue-600 text-white font-medium rounded-lg shadow-md hover:bg-blue-700 transition duration-150">
                Proceed to Log In
            </a>
        <?php else: ?>
             <a href="register.php" class="inline-block w-full px-6 py-3 bg-gray-600 text-white font-medium rounded-lg shadow-md hover:bg-gray-700 transition duration-150">
                Back to Registration
            </a>
        <?php endif; ?>
    </div>

</body>
</html>