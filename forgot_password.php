<?php
session_start();
require_once 'db.php';
require_once 'mail_helper.php'; 

$toasts = []; // For toast notifications
$email = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // We always show a generic success message to prevent
    // attackers from guessing which emails are registered.
    $success_message = "If an account with that email address exists, a password reset link has been sent.";

    if (empty($_POST['email'])) {
        $errors[] = "Email address is required.";
        $success_message = ""; // Don't show success if field is blank
    } else {
        $email = $conn->real_escape_string(trim($_POST['email']));
    }

    if (empty($errors)) {
        try {
            // Find the user by email
            $sql = "SELECT user_id, first_name, status FROM users WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();
                
                // Only send if the account is 'active'
                if ($user['status'] == 'active') {
                    // 1. Generate a secure token
                    $token = bin2hex(random_bytes(32));
                    $token_expiry = date('Y-m-d H:i:s', time() + 3600); // 1 hour
                    
                    // 2. Store the token and expiry in the database
                    $sql_update = "UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE user_id = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param("ssi", $token, $token_expiry, $user['user_id']);
                    $stmt_update->execute();
                    
                    // 3. Send the email
                    $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;
                    
                    // This new function will be in mail_helper.php (Step 4)
                    send_password_reset_email($email, $user['first_name'], $reset_link);
                }
            }
            // If user not found, or status isn't active, we do nothing.
            
            // MODIFICATION: Add the message to the toast array
            $toasts[] = ['type' => 'success', 'message' => $success_message];
            
        } catch (Exception $e) {
            // Log the error, but still show generic success message
            error_log("Password reset error: " . $e->getMessage());
            $toasts[] = ['type' => 'success', 'message' => $success_message];
        }
    } else {
        // Handle validation errors (e.g., blank email)
        foreach ($errors as $error) {
            $toasts[] = ['type' => 'error', 'message' => $error];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - DDTMS DENR CARAGA</title>
    <link rel="icon" type="image/png" href="logo/icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        body { font-family: 'Inter', sans-serif; }
        
        /* Preloader styles */
        #preloader { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: #f3f4f6; display: flex; align-items: center; justify-content: center; z-index: 9999; transition: opacity 0.5s ease-out; }
        .spinner { width: 50px; height: 50px; border: 5px solid #e5e7eb; border-top-color: #2563eb; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        #preloader.hidden { opacity: 0; pointer-events: none; }

        /* --- MODIFICATION: Toast Notification Styles --- */
        #toast-container {
            position: fixed;
            top: 1.5rem; /* top-6 */
            right: 1.5rem; /* right-6 */
            z-index: 100;
            display: flex;
            flex-direction: column;
            gap: 0.75rem; /* gap-3 */
        }
        .toast {
            max-width: 320px;
            padding: 1rem;
            border-radius: 0.5rem; /* rounded-lg */
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            opacity: 0;
            transform: translateX(100%);
            animation: slideIn 0.5s forwards, fadeOut 0.5s 4.5s forwards;
        }
        .toast-success {
            background-color: #ecfdf5; /* bg-green-50 */
            border: 1px solid #d1fae5; /* border-green-100 */
        }
        .toast-error {
            background-color: #fff1f2; /* bg-red-50 */
            border: 1px solid #ffe4e6; /* border-red-100 */
        }
        .toast .icon { margin-right: 0.75rem; }
        .toast .message { 
            font-size: 0.875rem; /* text-sm */
            font-weight: 500; 
        }
        .toast-success .message { color: #059669; } /* text-green-700 */
        .toast-error .message { color: #e11d48; } /* text-red-700 */
        .toast .close {
            margin-left: auto;
            padding: 0.25rem;
            border-radius: 0.25rem;
            cursor: pointer;
            background-color: transparent;
            border: none;
        }
        .toast-success .close:hover { background-color: #d1fae5; }
        .toast-error .close:hover { background-color: #ffe4e6; }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; transform: translateX(100%); }
        }
    </style>
</head>
<body class="bg-gray-100">

    <div id="preloader"><div class="spinner"></div></div>
    
    <div id="toast-container"></div>

    <header class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <a href="index.php" class="flex items-center space-x-2">
                <svg class="w-8 h-8 text-blue-700" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 2a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 5a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path></svg>
                <span class="text-xl font-bold text-blue-800">DDTMS | DENR CARAGA</span>
            </a>
            <a href="login.php" class="text-sm font-semibold text-blue-700 hover:text-blue-800">
                Back to Log In
            </a>
        </div>
    </header>

    <main class="flex justify-center items-center py-12 px-4" style="min-height: calc(100vh - 150px);">
        <div class="max-w-md w-full bg-white p-8 sm:p-10 rounded-xl shadow-lg">
            
            <div class="flex justify-center mb-6">
                <img src="logo/icon.png" alt="DDTMS Logo" class="w-20 h-20">
            </div>

            <h1 class="text-3xl font-bold text-center text-gray-900 mb-4">Reset Password</h1>
            <p class="text-center text-gray-600 mb-6">Enter your email address and we will send you a link to reset your password.</p>
            
            <form action="forgot_password.php" method="POST" class="space-y-6" id="forgot-form">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                    <input type="email" id="email" name="email" required 
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                           value="<?php echo htmlspecialchars($email); ?>">
                </div>

                <div>
                    <button type="submit" id="submit-button" 
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:bg-gray-400 disabled:cursor-not-allowed">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24" style="display: none;">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="button-text">Send Reset Link</span>
                    </button>
                </div>
            </form>

        </div>
    </main>

    <script>
        window.onload = function() {
            document.getElementById('preloader').classList.add('hidden');
        };
        
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('forgot-form');
            const button = document.getElementById('submit-button');
            
            // --- Form Submission Spinner ---
            if (form) {
                form.addEventListener('submit', function() {
                    const spinner = button.querySelector('svg');
                    const buttonText = button.querySelector('.button-text');
                    
                    // MODIFICATION: Re-enable button after a short delay 
                    // This allows for re-submission.
                    button.disabled = true;
                    if (spinner) spinner.style.display = 'inline-block';
                    if (buttonText) buttonText.textContent = 'Sending...';

                    // Re-enable after 3 seconds to prevent spam but allow re-try
                    setTimeout(() => {
                        button.disabled = false;
                        if (spinner) spinner.style.display = 'none';
                        if (buttonText) buttonText.textContent = 'Send Reset Link';
                    }, 3000);
                });
            }

            // --- Toast Notification ---
            const toastContainer = document.getElementById('toast-container');
            
            function createToast(message, type) {
                const toast = document.createElement('div');
                toast.className = `toast toast-${type}`;
                
                let icon;
                if (type === 'success') {
                    icon = `<svg class="icon w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
                } else {
                    icon = `<svg class="icon w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
                }
                
                toast.innerHTML = `
                    ${icon}
                    <span class="message">${message}</span>
                    <button class="close">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                `;
                
                // Close button
                toast.querySelector('.close').addEventListener('click', () => {
                    toast.style.animation = 'fadeOut 0.5s forwards';
                    setTimeout(() => toast.remove(), 500);
                });
                
                // Auto-dismiss
                setTimeout(() => {
                    if (document.body.contains(toast)) {
                        toast.style.animation = 'fadeOut 0.5s forwards';
                        setTimeout(() => toast.remove(), 500);
                    }
                }, 5000);
                
                toastContainer.appendChild(toast);
            }

            // --- PHP-to-JS Toast Trigger ---
            <?php if (!empty($toasts)): ?>
                const toastsToShow = <?php echo json_encode($toasts); ?>;
                toastsToShow.forEach(t => {
                    createToast(t.message, t.type);
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>