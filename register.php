<?php
session_start();
require_once 'db.php'; 
// We will create 'mail_helper.php' next. It simulates email sending.
require_once 'mail_helper.php'; 

$errors = [];
$success_message = "";

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // --- 1. Retrieve and Sanitize Form Data ---
    $email = $conn->real_escape_string(trim($_POST['email']));
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $first_name = $conn->real_escape_string(trim($_POST['first_name']));
    $middle_name = $conn->real_escape_string(trim($_POST['middle_name']));
    $last_name = $conn->real_escape_string(trim($_POST['last_name']));
    $suffix = $conn->real_escape_string(trim($_POST['suffix']));
    $position = $conn->real_escape_string(trim($_POST['position']));
    $designation = $conn->real_escape_string(trim($_POST['designation']));
    $division = $conn->real_escape_string(trim($_POST['division']));
    $sex = $conn->real_escape_string(trim($_POST['sex']));
    $contact_number = $conn->real_escape_string(trim($_POST['contact_number']));
    $privacy_policy = isset($_POST['privacy_policy']);

    // --- 2. Server-Side Validation ---
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "A valid email address is required.";
    }
    // Check if email must be a 'gmail.com' address
    if (substr($email, -10) !== "@gmail.com") {
        $errors[] = "Registration is currently limited to @gmail.com addresses only.";
    }
    if (empty($password) || strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    if ($password !== $password_confirm) {
        $errors[] = "Passwords do not match.";
    }
    if (empty($first_name) || empty($last_name)) {
        $errors[] = "First Name and Last Name are required.";
    }
    if (!$privacy_policy) {
        $errors[] = "You must agree to the Privacy Policy to register.";
    }

    // --- 3. Check for Existing User ---
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "An account with this email address already exists.";
        }
        $stmt->close();
    }

    // --- 4. Process Registration if No Errors ---
    if (empty($errors)) {
        try {
            // Hash the password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Generate a unique verification token
            $verification_token = bin2hex(random_bytes(32));
            // Set token expiry (e.g., 1 hour from now)
            $token_expiry = date('Y-m-d H:i:s', time() + 3600); 

            // Insert into database with 'pending' status
            $sql = "INSERT INTO users (email, password_hash, first_name, middle_name, last_name, suffix, position, designation, division, sex, contact_number, status, verification_token, token_expiry) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "sssssssssssss",
                $email, $password_hash, $first_name, $middle_name, $last_name, $suffix,
                $position, $designation, $division, $sex, $contact_number, 
                $verification_token, $token_expiry
            );

            if ($stmt->execute()) {
                // --- 5. Send Confirmation Email (Simulated) ---
                $verification_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/verify.php?token=" . $verification_token;
                
                // This function is in 'mail_helper.php'
                $email_sent = send_verification_email($email, $first_name, $verification_link); 

                if ($email_sent) {
                    $success_message = "Registration successful! A confirmation link has been sent to your email address. Please check your inbox (and spam folder) to activate your account.";
                } else {
                    // In a real app, you might roll back the transaction or log this
                    $errors[] = "Could not send verification email. Please contact support.";
                }
            } else {
                $errors[] = "Database error: Could not register user. " . $stmt->error;
            }
            $stmt->close();

        } catch (Exception $e) {
            $errors[] = "An error occurred: " . $e->getMessage();
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
    <title>Register - DDTMS DENR CARAGA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-100">

    <!-- Header -->
    <header class="bg-white shadow-md">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <a href="index.php" class="flex items-center space-x-2">
                <svg class="w-8 h-8 text-blue-700" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 2a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 5a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path></svg>
                <span class="text-xl font-bold text-blue-800">DDTMS | DENR CARAGA</span>
            </a>
            <a href="login.php" class_alias="login.php" class="text-sm font-semibold text-blue-700 hover:text-blue-800">
                Already have an account? Log In
            </a>
        </div>
    </header>

    <!-- Registration Form -->
    <main class="flex justify-center items-center py-12 px-4">
        <div class="max-w-4xl w-full bg-white p-8 sm:p-10 rounded-xl shadow-lg">
            <h1 class="text-3xl font-bold text-center text-gray-900 mb-6">Create Your Account</h1>
            <p class="text-center text-gray-600 mb-8">
                Fill in the details below to register. Registration is limited to <span class="font-semibold text-blue-600">@gmail.com</span> addresses.
            </p>

            <!-- Display Error/Success Messages -->
            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg" role="alert">
                    <p class="font-bold">Errors Found:</p>
                    <ul class="list-disc list-inside ml-2">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg" role="alert">
                    <p class="font-bold">Success!</p>
                    <p><?php echo htmlspecialchars($success_message); ?></p>
                </div>
            <?php else: ?>
                <!-- Hide form on success -->
                <form action="register.php" method="POST" class="space-y-6" id="registration-form">
                    
                    <!-- Personal Information -->
                    <fieldset class="border border-gray-300 p-4 rounded-lg">
                        <legend class="text-lg font-semibold text-gray-700 px-2">Personal Information</legend>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700">First Name <span class="text-red-500">*</span></label>
                                <input type="text" id="first_name" name="first_name" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                            </div>
                            <div>
                                <label for="middle_name" class="block text-sm font-medium text-gray-700">Middle Name</label>
                                <input type="text" id="middle_name" name="middle_name" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" value="<?php echo isset($_POST['middle_name']) ? htmlspecialchars($_POST['middle_name']) : ''; ?>">
                            </div>
                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name <span class="text-red-500">*</span></label>
                                <input type="text" id="last_name" name="last_name" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                            </div>
                            <div>
                                <label for="suffix" class="block text-sm font-medium text-gray-700">Suffix (e.g., Jr., III)</label>
                                <input type="text" id="suffix" name="suffix" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" value="<?php echo isset($_POST['suffix']) ? htmlspecialchars($_POST['suffix']) : ''; ?>">
                            </div>
                            <div>
                                <label for="sex" class="block text-sm font-medium text-gray-700">Sex</label>
                                <select id="sex" name="sex" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Select...</option>
                                    <option value="Male" <?php echo (isset($_POST['sex']) && $_POST['sex'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo (isset($_POST['sex']) && $_POST['sex'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?php echo (isset($_POST['sex']) && $_POST['sex'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                                    <option value="Prefer not to say" <?php echo (isset($_POST['sex']) && $_POST['sex'] == 'Prefer not to say') ? 'selected' : ''; ?>>Prefer not to say</option>
                                </select>
                            </div>
                        </div>
                    </fieldset>

                    <!-- Professional Information -->
                    <fieldset class="border border-gray-300 p-4 rounded-lg">
                        <legend class="text-lg font-semibold text-gray-700 px-2">Professional Information</legend>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
                            <div>
                                <label for="position" class="block text-sm font-medium text-gray-700">Position</label>
                                <input type="text" id="position" name="position" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" value="<?php echo isset($_POST['position']) ? htmlspecialchars($_POST['position']) : ''; ?>">
                            </div>
                            <div>
                                <label for="designation" class="block text-sm font-medium text-gray-700">Designation</label>
                                <input type="text" id="designation" name="designation" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" value="<?php echo isset($_POST['designation']) ? htmlspecialchars($_POST['designation']) : ''; ?>">
                            </div>
                            <div>
                                <label for="division" class="block text-sm font-medium text-gray-700">Division</label>
                                <input type="text" id="division" name="division" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" value="<?php echo isset($_POST['division']) ? htmlspecialchars($_POST['division']) : ''; ?>">
                            </div>
                        </div>
                    </fieldset>

                    <!-- Account & Contact Information -->
                    <fieldset class="border border-gray-300 p-4 rounded-lg">
                        <legend class="text-lg font-semibold text-gray-700 px-2">Account & Contact</legend>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">Email Address (must be @gmail.com) <span class="text-red-500">*</span></label>
                                <input type="email" id="email" name="email" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                            <div>
                                <label for="contact_number" class="block text-sm font-medium text-gray-700">Contact Number</label>
                                <input type="tel" id="contact_number" name="contact_number" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" value="<?php echo isset($_POST['contact_number']) ? htmlspecialchars($_POST['contact_number']) : ''; ?>">
                            </div>
                            <div>
                                <label for="password" class="block text-sm font-medium text-gray-700">Password (min. 8 characters) <span class="text-red-500">*</span></label>
                                <input type="password" id="password" name="password" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            <div>
                                <label for="password_confirm" class="block text-sm font-medium text-gray-700">Confirm Password <span class="text-red-500">*</span></label>
                                <input type="password" id="password_confirm" name="password_confirm" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                    </fieldset>

                    <!-- Privacy Policy & Submit -->
                    <div class="space-y-4">
                        <div class="flex items-center">
                            <input id="privacy_policy" name="privacy_policy" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" onchange="document.getElementById('register-button').disabled = !this.checked;">
                            <label for="privacy_policy" class="ml-2 block text-sm text-gray-900">
                                I have read and agree to the <a href="privacy.php" target="_blank" class="font-medium text-blue-600 hover:text-blue-500">Privacy Policy</a>. <span class="text-red-500">*</span>
                            </label>
                        </div>

                        <div>
                            <button type="submit" id="register-button" disabled class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:bg-gray-400 disabled:cursor-not-allowed">
                                Register Account
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </main>

</body>
</html>