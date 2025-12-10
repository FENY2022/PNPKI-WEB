<?php
session_start();
require_once 'db.php'; 
// We will create 'mail_helper.php' next. It simulates email sending.
require_once 'mail_helper.php'; 

$errors = [];
$success_message = "";
$start_step = 1; // Default to step 1

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
    // ALLOW ANY EMAIL: We only check if it is a valid email format.
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "A valid email address is required.";
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

    // If there are any errors, set the form to start at the last step
    if (!empty($errors)) {
        $start_step = 4;
    }

    // --- 3. Check for Existing User ---
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "An account with this email address already exists.";
            $start_step = 4; // Also go to step 4 if user exists
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

            // Insert into database with 'Pending' status
            $sql = "INSERT INTO users (email, password_hash, first_name, middle_name, last_name, suffix, position, designation, division, sex, contact_number, status, verification_token, token_expiry) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?)";
            
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
                    $start_step = 4;
                }
            } else {
                $errors[] = "Database error: Could not register user. " . $stmt->error;
                $start_step = 4;
            }
            $stmt->close();

        } catch (Exception $e) {
            $errors[] = "An error occurred: " . $e->getMessage();
            $start_step = 4;
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

        /* --- STYLES FOR MULTI-STEP FORM --- */
        .form-step {
            display: none;
        }
        .form-step.active {
            display: block;
            animation: fadeIn 0.5s ease-in-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .progress-bar {
            display: flex;
            margin-bottom: 2.5rem; /* Increased margin */
            justify-content: space-between;
            position: relative;
        }
        /* Progress line (background) */
        .progress-bar::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            transform: translateY(-50%);
            height: 4px;
            width: 100%;
            background-color: #e5e7eb; /* gray-200 */
            z-index: 1;
        }
        /* Progress line (active) */
        .progress-bar::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            transform: translateY(-50%);
            height: 4px;
            width: var(--progress-width, 0%);
            background-color: #2563eb; /* blue-600 */
            z-index: 1;
            transition: width 0.5s ease;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #e5e7eb; /* gray-200 */
            z-index: 2;
            font-weight: 600;
            color: #6b7280; /* gray-500 */
            position: relative;
            transition: all 0.3s ease;
        }
        .step.active {
            background-color: #2563eb; /* blue-600 */
            color: white;
            border-color: #2563eb; /* blue-600 */
        }
        .step.completed {
            background-color: #16a34a; /* green-600 */
            color: white;
            border-color: #16a34a; /* green-600 */
        }
        .step-label {
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            margin-top: 8px;
            font-size: 0.75rem; /* 12px */
            font-weight: 500;
            color: #6b7280; /* gray-500 */
            white-space: nowrap;
        }
        .step.active .step-label {
            color: #1d4ed8; /* blue-700 */
            font-weight: 600;
        }
        
        /* Button styles for navigation */
        .btn {
            padding: 0.65rem 1.5rem;
            border-radius: 0.375rem; /* rounded-md */
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem; /* text-sm */
        }
        .btn-primary {
            background-color: #2563eb; /* blue-600 */
            color: white;
            box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        }
        .btn-primary:hover {
            background-color: #1d4ed8; /* blue-700 */
        }
        .btn-primary:disabled {
            background-color: #9ca3af; /* gray-400 */
            cursor: not-allowed;
        }
        .btn-outline {
            background-color: transparent;
            color: #4b5563; /* gray-600 */
            border: 1px solid #d1d5db; /* gray-300 */
        }
        .btn-outline:hover {
            background-color: #f9fafb; /* gray-50 */
        }

        /* Styles for Privacy Policy box */
        .privacy-content {
            background-color: #f9fafb; /* Lighter gray for content area */
            border: 1px solid #e5e7eb; /* gray-200 */
            border-radius: 0.375rem;
            padding: 1rem;
            height: 20rem; /* 320px */
            overflow-y: auto;
        }
        .privacy-content h1 {
            font-size: 1.5rem; /* text-2xl */
            font-weight: 700;
            color: #111827; /* gray-900 */
            margin-bottom: 1rem;
        }
        .privacy-content h2 { 
            margin-top: 1.5rem; 
            margin-bottom: 0.5rem; 
            font-size: 1.25rem; /* text-xl */
            font-weight: 600; 
        }
        .privacy-content p, .privacy-content ul { 
            margin-bottom: 1rem; 
            color: #4b5563; /* gray-600 */
            line-height: 1.6; 
        }
         .privacy-content ul {
            list-style-type: disc;
            list-style-position: inside;
            padding-left: 1rem;
         }

        /* --- MODIFICATION: Preloader Styles --- */
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
        /* Class to hide the preloader */
        #preloader.hidden {
            opacity: 0;
            pointer-events: none; /* Make it unclickable */
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
            <a href="login.php" class="text-sm font-semibold text-blue-700 hover:text-blue-800">
                Already have an account? Log In
            </a>
        </div>
    </header>

    <main class="flex justify-center items-center py-12 px-4">
        <div class="max-w-4xl w-full bg-white p-8 sm:p-10 rounded-xl shadow-lg">
            <h1 class="text-3xl font-bold text-center text-gray-900 mb-6">Create Your Account</h1>
            <p class="text-center text-gray-600 mb-8">
                Fill in the details below to register.
            </p>

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
                <div class="progress-bar">
                    <div class="step active" id="step-1-indicator">
                        <span>1</span>
                        <span class="step-label">Agreement</span>
                    </div>
                    <div class="step" id="step-2-indicator">
                        <span>2</span>
                        <span class="step-label">Personal</span>
                    </div>
                    <div class="step" id="step-3-indicator">
                        <span>3</span>
                        <span class="step-label">Professional</span>
                    </div>
                     <div class="step" id="step-4-indicator">
                        <span>4</span>
                        <span class="step-label">Account</span>
                    </div>
                </div>


                <form action="register.php" method="POST" class="space-y-6" id="registration-form">
                    
                    <div class="form-step active" id="form-step-1">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Data Privacy Agreement</h2>
                        <div class="privacy-content mb-4">
                            <h1>Privacy Policy for DDTMS DENR CARAGA</h1>
                            <p><strong>Last Updated:</strong> <?php echo date("F j, Y"); ?></p>
                            <p>This Privacy Policy describes how the Digital Document Tracking & Management System (DDTMS) for DENR CARAGA ("we," "us," or "our") collects, uses, and discloses your information in connection with your use of our web application (the "Service").</p>
                            <h2>1. Information We Collect</h2>
                            <p>We collect personal information that you provide directly to us when you register for an account. This information includes:</p>
                            <ul>
                                <li>Full Name (First, Middle, Last, Suffix)</li>
                                <li>Contact Information (Email Address, Contact Number)</li>
                                <li>Professional Details (Position, Designation, Division)</li>
                                <li>Demographic Information (Sex)</li>
                                <li>Account Credentials (Hashed Password)</li>
                            </ul>
                            <h2>2. How We Use Your Information</h2>
                            <p>We use the information we collect for the following purposes:</p>
                            <ul>
                                <li>To create, maintain, and secure your account.</li>
                                <li>To verify your identity and send account verification links or password reset links.</li>
                                <li>To enable your participation in the document tracking workflow (e.g., identifying you as an initiator, reviewer, or signatory).</li>
                                <li>To maintain an audit trail of document actions.</li>
                                <li>To communicate with you about service updates or security alerts.</li>
                            </ul>
                            <h2>3. Data Security</h2>
                            <p>We implement appropriate technical and organizational measures to protect your personal information against accidental or unlawful destruction, loss, alteration, unauthorized disclosure, or access. Your password is stored in a hashed format, meaning we cannot see or retrieve your actual password.</p>
                            <h2>4. Data Retention</h2>
                            <p>We will retain your personal information for as long as your account is active or as needed to provide you with the Service and to comply with our legal obligations (e.g., retaining audit logs for official documents).</p>
                            <h2>5. Your Rights</h2>
                            <p>As a user, you have the right to access, update, or correct your personal information through your account profile page. You also have the right to request the deletion of your account, subject to legal and official retention requirements.</p>
                            <h2>6. Agreement</h2>
                            <p>By checking "I agree" on the registration page, you acknowledge that you have read, understood, and agree to the terms of this Privacy Policy and consent to the collection and use of your information as described herein.</p>
                            </div>
                        <div class="flex items-center">
                            <input id="privacy_agreement_step1" name="privacy_agreement_step1" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="privacy_agreement_step1" class="ml-2 block text-sm text-gray-900">
                                I have read and agree to the Privacy Policy.
                            </label>
                        </div>
                        <div class="flex justify-end mt-8">
                            <button type="button" id="next-step-1" class="btn btn-primary next-step" data-next="2" disabled>
                                Next &rarr;
                            </button>
                        </div>
                    </div>


                    <div class="form-step" id="form-step-2">
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
                        <div class="flex justify-between mt-8">
                            <button type="button" class="btn btn-outline prev-step" data-prev="1">
                                &larr; Back
                            </button>
                            <button type="button" id="next-step-2" class="btn btn-primary next-step" data-next="3" disabled>
                                Next &rarr;
                            </button>
                        </div>
                    </div>

                    <div class="form-step" id="form-step-3">
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
                        <div class="flex justify-between mt-8">
                            <button type="button" class="btn btn-outline prev-step" data-prev="2">
                                &larr; Back
                            </button>
                            <button type="button" class="btn btn-primary next-step" data-next="4">
                                Next &rarr;
                            </button>
                        </div>
                    </div>


                    <div class="form-step" id="form-step-4">
                        <fieldset class="border border-gray-300 p-4 rounded-lg">
                            <legend class="text-lg font-semibold text-gray-700 px-2">Account & Contact</legend>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-4">
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700">Email Address <span class="text-red-500">*</span></label>
                                    <input type="email" id="email" name="email" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                </div>
                                <div>
                                    <label for="contact_number" class="block text-sm font-medium text-gray-700">Contact Number</label>
                                    <input type="tel" id="contact_number" name="contact_number" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" value="<?php echo isset($_POST['contact_number']) ? htmlspecialchars($_POST['contact_number']) : ''; ?>">
                                </div>
                                <div>
                                    <label for="password" class="block text-sm font-medium text-gray-700">Password (min. 8 characters) <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <input type="password" id="password" name="password" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 pr-12">
                                        <button type="button" class="absolute inset-y-0 right-0 top-1 pr-3 flex items-center text-sm leading-5 text-blue-600 hover:text-blue-500 font-medium password-toggle" data-target="password">
                                            Show
                                        </button>
                                    </div>
                                    <div class="mt-2">
                                        <div class="h-2 rounded-full bg-gray-200">
                                            <div id="password-strength-bar" class="h-2 rounded-full transition-all duration-300" style="width: 0;"></div>
                                        </div>
                                        <span id="password-strength-text" class="text-xs font-medium text-gray-500 mt-1 block"></span>
                                    </div>
                                </div>
                                <div>
                                    <label for="password_confirm" class="block text-sm font-medium text-gray-700">Confirm Password <span class="text-red-500">*</span></label>
                                    <div class="relative">
                                        <input type="password" id="password_confirm" name="password_confirm" required class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 pr-12">
                                        <button type="button" class="absolute inset-y-0 right-0 top-1 pr-3 flex items-center text-sm leading-5 text-blue-600 hover:text-blue-500 font-medium password-toggle" data-target="password_confirm">
                                            Show
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <div class="space-y-4 mt-6">
                            <div class="flex items-center">
                                <input id="privacy_policy" name="privacy_policy" type="checkbox" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded" <?php echo (isset($_POST['privacy_policy']) && $_POST['privacy_policy']) ? 'checked' : ''; ?>>
                                <label for="privacy_policy" class="ml-2 block text-sm text-gray-900">
                                    I confirm I have read and agree to the <a href="privacy.php" target="_blank" class="font-medium text-blue-600 hover:text-blue-500">Privacy Policy</a>. <span class="text-red-500">*</span>
                                </label>
                            </div>

                            <div class="flex justify-between mt-8">
                                <button type="button" class="btn btn-outline prev-step" data-prev="3">
                                    &larr; Back
                                </button>
                                <button type="submit" id="register-button" disabled class="w-1/2 flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:bg-gray-400 disabled:cursor-not-allowed">
                                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24" style="display: none;">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span class="button-text">Register Account</span>
                                </button>
                            </div>
                        </div>
                    </div>

                </form>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // --- Preloader Logic ---
        // This runs as soon as the window is fully loaded
        window.onload = function() {
            const preloader = document.getElementById('preloader');
            if (preloader) {
                preloader.classList.add('hidden');
            }
        };
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const formSteps = document.querySelectorAll('.form-step');
            const nextButtons = document.querySelectorAll('.next-step');
            const prevButtons = document.querySelectorAll('.prev-step');
            const progressSteps = document.querySelectorAll('.step');
            const progressBar = document.querySelector('.progress-bar');
            
            // --- MODIFICATION: Start on the step PHP determined ---
            let currentStep = <?php echo $start_step; ?>;

            function updateProgress() {
                const progressWidth = ((currentStep - 1) / (progressSteps.length - 1)) * 100;
                progressBar.style.setProperty('--progress-width', `${progressWidth}%`);

                progressSteps.forEach((step, index) => {
                    const indicator = document.getElementById(`step-${index + 1}-indicator`);
                    if (index + 1 < currentStep) {
                        indicator.classList.add('completed');
                        indicator.classList.remove('active');
                    } else if (index + 1 === currentStep) {
                        indicator.classList.add('active');
                        indicator.classList.remove('completed');
                    } else {
                        indicator.classList.remove('active', 'completed');
                    }
                });
            }

            function showStep(stepNumber) {
                formSteps.forEach(step => step.classList.remove('active'));
                document.getElementById(`form-step-${stepNumber}`).classList.add('active');
                currentStep = stepNumber;
                updateProgress();
            }

            nextButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const nextStep = parseInt(button.getAttribute('data-next'));
                    showStep(nextStep);
                });
            });

            prevButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const prevStep = parseInt(button.getAttribute('data-prev'));
                    showStep(prevStep);
                });
            });

            // --- Step 1 Agreement Check ---
            const step1Checkbox = document.getElementById('privacy_agreement_step1');
            const step1NextButton = document.getElementById('next-step-1');
            
            step1Checkbox.addEventListener('change', function() {
                step1NextButton.disabled = !this.checked;
            });

            // --- MODIFICATION: Client-side "Next" button validation ---
            
            // --- Validation for Step 2 (Personal) ---
            const firstNameInput = document.getElementById('first_name');
            const lastNameInput = document.getElementById('last_name');
            const nextStep2Button = document.getElementById('next-step-2');

            function validateStep2() {
                const firstValid = firstNameInput.value.trim() !== '';
                const lastValid = lastNameInput.value.trim() !== '';
                if (nextStep2Button) {
                    nextStep2Button.disabled = !(firstValid && lastValid);
                }
            }
            if (firstNameInput && lastNameInput) {
                firstNameInput.addEventListener('input', validateStep2);
                lastNameInput.addEventListener('input', validateStep2);
            }
            
            // --- Validation for Step 4 (Account) ---
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password'); // Re-using from strength bar
            const passwordConfirmInput = document.getElementById('password_confirm');
            const finalCheckbox = document.getElementById('privacy_policy');
            const registerButton = document.getElementById('register-button');
            
            function validateStep4() {
                if (!emailInput || !passwordInput || !passwordConfirmInput || !finalCheckbox || !registerButton) return;

                const emailValid = emailInput.value.trim() !== '' && emailInput.checkValidity(); // checkValidity handles email format
                const passValid = passwordInput.value.trim().length >= 8; 
                const passConfirmValid = passwordConfirmInput.value.trim() !== '';
                const checkboxValid = finalCheckbox.checked;

                registerButton.disabled = !(emailValid && passValid && passConfirmValid && checkboxValid);
            }
            
            if (emailInput && passwordInput && passwordConfirmInput && finalCheckbox) {
                emailInput.addEventListener('input', validateStep4);
                passwordInput.addEventListener('input', validateStep4);
                passwordConfirmInput.addEventListener('input', validateStep4);
                finalCheckbox.addEventListener('change', validateStep4);
            }
            
            // --- MODIFICATION: Password Show/Hide Toggle ---
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

            // --- MODIFICATION: Password Strength Indicator ---
            // const passwordInput = document.getElementById('password'); // Already defined
            const strengthBar = document.getElementById('password-strength-bar');
            const strengthText = document.getElementById('password-strength-text');

            if (passwordInput) {
                passwordInput.addEventListener('input', function() {
                    const password = this.value;
                    let score = 0;
                    
                    if (!password) {
                        strengthBar.style.width = '0%';
                        strengthText.textContent = '';
                        return;
                    }

                    // Criteria
                    if (password.length >= 8) score++;
                    if (/[A-Z]/.test(password)) score++;
                    if (/[0-9]/.test(password)) score++;
                    if (/[^A-Za-z0-9]/.test(password)) score++; // Special character

                    let width = '25%';
                    let barColor = 'bg-red-500'; // Tailwind bar class
                    let textColor = 'text-red-500'; // Tailwind text class
                    let text = 'Weak';

                    switch (score) {
                        case 2:
                            width = '50%';
                            barColor = 'bg-yellow-500';
                            textColor = 'text-yellow-600';
                            text = 'Medium';
                            break;
                        case 3:
                            width = '75%';
                            barColor = 'bg-blue-500';
                            textColor = 'text-blue-600';
                            text = 'Strong';
                            break;
                        case 4:
                            width = '100%';
                            barColor = 'bg-green-500';
                            textColor = 'text-green-600';
                            text = 'Very Strong';
                            break;
                    }

                    // Reset classes
                    strengthBar.classList.remove('bg-red-500', 'bg-yellow-500', 'bg-blue-500', 'bg-green-500');
                    strengthText.classList.remove('text-red-500', 'text-yellow-600', 'text-blue-600', 'text-green-600');

                    // Add new classes
                    strengthBar.classList.add(barColor);
                    strengthText.classList.add(textColor);
                    
                    strengthBar.style.width = width;
                    strengthText.textContent = text;
                });
            }

            // --- MODIFICATION: Form Submission Spinner Logic ---
            const regForm = document.getElementById('registration-form');
            
            if (regForm && registerButton) {
                regForm.addEventListener('submit', function(event) {
                    // Validate one last time
                    validateStep4();
                    
                    // If validation fails (e.g., user hits Enter), stop submission
                    if (registerButton.disabled) {
                        event.preventDefault(); 
                        return;
                    }

                    // Find the spinner and text span inside the button
                    const spinner = registerButton.querySelector('svg');
                    const buttonText = registerButton.querySelector('.button-text');

                    // Disable button, show spinner, update text
                    registerButton.disabled = true;
                    if (spinner) {
                        spinner.style.display = 'inline-block';
                    }
                    if (buttonText) {
                        buttonText.textContent = 'Registering...';
                    }
                });
            }


            // --- Initialize ---
            // Run validations on load to check for pre-filled/error-state values
            validateStep2();
            validateStep4();
            
            // Show the correct step on page load (e.g., step 4 if errors)
            showStep(currentStep);
        });
    </script>

</body>
</html>