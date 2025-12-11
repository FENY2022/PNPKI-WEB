<?php
session_start();

// 1. If user is already logged in, redirect them to the dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php"); // Create a dashboard.php for logged-in users
    exit;
}

require_once 'db.php';
$errors = [];
$email = ""; // To repopulate the email field on error

// 2. Check if the form has been submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    if (empty($_POST['email'])) {
        $errors[] = "Email address is required.";
    } else {
        $email = $conn->real_escape_string(trim($_POST['email']));
    }

    if (empty($_POST['password'])) {
        $errors[] = "Password is required.";
    }

    // 3. If no basic validation errors, proceed to check database
    if (empty($errors)) {
        try {
            // Prepare statement to find user by email
            $sql = "SELECT user_id, email, password_hash, first_name, last_name, role, status, otos_userlink
                    FROM users WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            // 4. Check if a user was found
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();

                // 5. Verify the password
                if (password_verify($_POST['password'], $user['password_hash'])) {
                    
                    // 6. Check account status
                    if ($user['status'] == 'active') {
                        // --- SUCCESSFUL LOGIN ---
                        session_regenerate_id(true); // Prevent session fixation
                        
                        // Store user data in session
                        $_SESSION['user_id'] = $user['user_id'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['otos_userlink'] = $user['otos_userlink'];

                        
                        // Redirect to the member-only area
                        header("Location: dashboard.php");
                        exit;

                    } elseif ($user['status'] == 'pending') {
                        $errors[] = "Your account is pending verification. Please check your email for the confirmation link.";
                    } elseif ($user['status'] == 'disabled') {
                        $errors[] = "Your account has been disabled. Please contact support.";
                    } else {
                        $errors[] = "Invalid account status. Please contact support.";
                    }

                } else {
                    // Password was incorrect
                    $errors[] = "Invalid email or password.";
                }
            } else {
                // No user found with that email
                $errors[] = "Invalid email or password.";
            }
            $stmt->close();

        } catch (Exception $e) {
            $errors[] = "An error occurred. Please try again later.";
            // You should log the detailed error: error_log($e->getMessage());
        }
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In - DDTMS DENR CARAGA</title>
    
    <link rel="icon" type="image/png" href="logo/icon.png">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        body { font-family: 'Inter', sans-serif; }

        /* --- Preloader Styles --- */
        #preloader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: #f3f4f6; /* bg-gray-100 */
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.5s ease-out;
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #e5e7eb; /* gray-200 */
            border-top-color: #2563eb; /* blue-600 */
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        #preloader.hidden {
            opacity: 0;
            pointer-events: none;
        }
    </style>
</head>
<body class="bg-gray-100">

    <div id="preloader">
        <div class="spinner"></div>
    </div>

    <header class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <a href="index.php" class="flex items-center space-x-2">
                <svg class="w-8 h-8 text-blue-700" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 2a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 5a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path></svg>
                <span class="text-xl font-bold text-blue-800">DDTMS | DENR CARAGA</span>
            </a>
            <a href="register.php" class="text-sm font-semibold text-blue-700 hover:text-blue-800">
                Don't have an account? Register
            </a>
        </div>
    </header>

    <main class="flex justify-center items-center py-12 px-4" style="min-height: calc(100vh - 150px);">
        <div class="max-w-md w-full bg-white p-8 sm:p-10 rounded-xl shadow-lg">
            
            <div class="flex justify-center mb-6">
                <img src="logo/banner.png" alt="DDTMS Logo" class="w-1000 h-30"> </div>

            <h1 class="text-3xl font-bold text-center text-gray-900 mb-6">Log In to Your Account</h1>
            
            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg" role="alert">
                    <p class="font-bold">Login Failed</p>
                    <ul class="list-disc list-inside ml-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="space-y-6" id="login-form">
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email Address</label>
                    <input type="email" id="email" name="email" required 
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                           value="<?php echo htmlspecialchars($email); ?>">
                </div>
                
                <div>
                    <div class="flex justify-between items-center">
                        <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                        <a href="forgot_password.php" class="text-sm font-medium text-blue-600 hover:text-blue-500">
                            Forgot password?
                        </a>
                    </div>
                    <div class="relative">
                        <input type="password" id="password" name="password" required 
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 pr-12">
                        <button type="button" class="absolute inset-y-0 right-0 top-1 pr-3 flex items-center text-sm leading-5 text-blue-600 hover:text-blue-500 font-medium password-toggle" data-target="password">
                            Show
                        </button>
                    </div>
                </div>

                <div>
                    <button type="submit" id="login-button" 
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:bg-gray-400 disabled:cursor-not-allowed">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24" style="display: none;">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="button-text">Log In</span>
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
        window.onload = function() {
            const preloader = document.getElementById('preloader');
            if (preloader) {
                preloader.classList.add('hidden');
            }
        };
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            
            // --- Password Show/Hide Toggle ---
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

            // --- Form Submission Spinner Logic ---
            const loginForm = document.getElementById('login-form');
            const loginButton = document.getElementById('login-button');
            
            if (loginForm && loginButton) {
                loginForm.addEventListener('submit', function() {
                    const spinner = loginButton.querySelector('svg');
                    const buttonText = loginButton.querySelector('.button-text');

                    // Disable button, show spinner, update text
                    loginButton.disabled = true;
                    if (spinner) {
                        spinner.style.display = 'inline-block';
                    }
                    if (buttonText) {
                        buttonText.textContent = 'Logging In...';
                    }
                });
            }
        });
    </script>

</body>
</html>