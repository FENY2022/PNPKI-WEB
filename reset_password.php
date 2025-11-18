<?php
session_start();
require_once 'db.php';

$errors = [];
$success_message = "";
$token = $_GET['token'] ?? '';
$user = null; // This will stay null if the token is bad

if (empty($token)) {
    $errors[] = "No reset token provided. Please use the link from your email.";
} else {
    // Check if token is valid and not expired
    try {
        $sql = "SELECT user_id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW()";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
        } else {
            $errors[] = "This password reset link is invalid or has expired. Please request a new one.";
        }
    } catch (Exception $e) {
        $errors[] = "An error occurred. Please try again later.";
    }
}

// Handle the form submission (when new password is set)
if ($_SERVER["REQUEST_METHOD"] == "POST" && $user) {
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $hidden_token = $_POST['token']; // Get token from hidden field

    if ($hidden_token !== $token) {
        $errors[] = "Token mismatch. Please refresh the page and try again.";
    }
    if (empty($password) || strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    if ($password !== $password_confirm) {
        $errors[] = "Passwords do not match.";
    }

    if (empty($errors)) {
        try {
            // Hash the new password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Update the user's password and clear the reset token
            $sql_update = "UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expiry = NULL WHERE user_id = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("si", $password_hash, $user['user_id']);
            
            if ($stmt_update->execute()) {
                $success_message = "Your password has been successfully reset. You can now log in.";
            } else {
                $errors[] = "Failed to update password. Please try again.";
            }
        } catch (Exception $e) {
            $errors[] = "An error occurred: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - DDTMS DENR CARAGA</title>
    <link rel="icon" type="image/png" href="logo/icon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        body { font-family: 'Inter', sans-serif; }
        #preloader { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: #f3f4f6; display: flex; align-items: center; justify-content: center; z-index: 9999; transition: opacity 0.5s ease-out; }
        .spinner { width: 50px; height: 50px; border: 5px solid #e5e7eb; border-top-color: #2563eb; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        #preloader.hidden { opacity: 0; pointer-events: none; }
    </style>
</head>
<body class="bg-gray-100">

    <div id="preloader"><div class="spinner"></div></div>

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

            <h1 class="text-3xl font-bold text-center text-gray-900 mb-6">Set New Password</h1>
            
            <?php if (!empty($success_message)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg" role="alert">
                    <p class="font-bold">Success!</p>
                    <p><?php echo htmlspecialchars($success_message); ?></p>
                </div>
                <a href="login.php" class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-blue-600 hover:bg-blue-700">
                    Proceed to Log In
                </a>
            <?php elseif (!empty($errors)): ?>
                 <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg" role="alert">
                    <p class="font-bold">Error</p>
                    <ul class="list-disc list-inside ml-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <?php if (!$user): // Token was invalid, so $user is null ?>
                        <div class="mt-4 border-t border-red-200 pt-3 text-center">
                            <a href="forgot_password.php" class="text-sm font-medium text-blue-600 hover:text-blue-500">
                                &larr; Request a new reset link
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($success_message) && $user): // Show form only if token was valid and password not yet reset ?>
                <form action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="POST" class="space-y-6" id="reset-form">
                    
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">New Password (min. 8 characters)</label>
                        <div class="relative">
                            <input type="password" id="password" name="password" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 pr-12">
                            <button type="button" class="absolute inset-y-0 right-0 top-1 pr-3 flex items-center text-sm leading-5 text-blue-600 hover:text-blue-500 font-medium password-toggle" data-target="password">Show</button>
                        </div>
                        
                        <div class="mt-2" id="password-strength-container" style="display: none;">
                            <div class="h-2 w-full bg-gray-200 rounded-full">
                                <div id="password-strength-bar" class="h-2 rounded-full transition-all duration-300" style="width: 0%;"></div>
                            </div>
                            <p id="password-strength-text" class="text-xs mt-1 text-gray-500"></p>
                        </div>
                        </div>
                    
                    <div>
                        <label for="password_confirm" class="block text-sm font-medium text-gray-700">Confirm New Password</label>
                        <div class="relative">
                            <input type="password" id="password_confirm" name="password_confirm" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 pr-12">
                            <button type="button" class="absolute inset-y-0 right-0 top-1 pr-3 flex items-center text-sm leading-5 text-blue-600 hover:text-blue-500 font-medium password-toggle" data-target="password_confirm">Show</button>
                        </div>
                    </div>

                    <div>
                        <button type="submit" id="submit-button" 
                                class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:bg-gray-400 disabled:cursor-not-allowed">
                            <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24" style="display: none;">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="button-text">Set New Password</span>
                        </button>
                    </div>
                </form>
            <?php endif; ?>

        </div>
    </main>

    <script>
        window.onload = function() {
            document.getElementById('preloader').classList.add('hidden');
        };
        document.addEventListener('DOMContentLoaded', function() {
            // Form spinner
            const form = document.getElementById('reset-form');
            const button = document.getElementById('submit-button');
            if (form) {
                form.addEventListener('submit', function() {
                    const spinner = button.querySelector('svg');
                    const buttonText = button.querySelector('.button-text');
                    button.disabled = true;
                    if (spinner) spinner.style.display = 'inline-block';
                    if (buttonText) buttonText.textContent = 'Saving...';
                });
            }

            // ==== NEW: PASSWORD STRENGTH VALIDATOR ====
            const passwordInput = document.getElementById('password');
            const strengthContainer = document.getElementById('password-strength-container');
            const strengthBar = document.getElementById('password-strength-bar');
            const strengthText = document.getElementById('password-strength-text');

            const strengthLevels = {
                0: { text: 'Too short (min. 8 characters)', color: 'bg-red-500', width: '10%' },
                1: { text: 'Weak', color: 'bg-red-500', width: '33%' },
                2: { text: 'Medium', color: 'bg-yellow-500', width: '66%' },
                3: { text: 'Strong', color: 'bg-green-500', width: '100%' }
            };

            function checkPasswordStrength(password) {
                if (password.length < 8) return 0; // Too short

                const checks = {
                    lowercase: /[a-z]/.test(password),
                    uppercase: /[A-Z]/.test(password),
                    number: /[0-9]/.test(password),
                    special: /[^A-Za-z0-9]/.test(password) // non-alphanumeric
                };

                let criteriaMet = 0;
                if (checks.lowercase) criteriaMet++;
                if (checks.uppercase) criteriaMet++;
                if (checks.number) criteriaMet++;
                if (checks.special) criteriaMet++;

                if (criteriaMet <= 1) return 1; // Weak
                if (criteriaMet >= 2 && criteriaMet <= 3) return 2; // Medium
                if (criteriaMet === 4) return 3; // Strong
                
                return 1; // Default to weak if logic is missed
            }

            if (passwordInput) {
                passwordInput.addEventListener('input', function() {
                    const password = this.value;

                    if (password.length === 0) {
                        strengthContainer.style.display = 'none';
                        return;
                    }
                    
                    strengthContainer.style.display = 'block';
                    const score = checkPasswordStrength(password);
                    const level = strengthLevels[score];

                    strengthText.textContent = `Strength: ${level.text}`;
                    
                    // Update bar color and width
                    strengthBar.style.width = level.width;
                    strengthBar.classList.remove('bg-red-500', 'bg-yellow-500', 'bg-green-500');
                    strengthBar.classList.add(level.color);
                });
            }
            // ==== END: NEW JAVASCRIPT ====


            // Password toggle
            document.querySelectorAll('.password-toggle').forEach(toggle => {
                toggle.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const targetInput = document.getElementById(targetId);
                    if (targetInput.type === 'password') {
                        targetInput.type = 'text';
                        this.textContent = 'Hide';
                    } else {
                        targetInput.type = 'password';
                        this.textContent = 'Show';
                    }
                });
            });
        });
    </script>
</body>
</html>