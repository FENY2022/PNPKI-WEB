<?php
session_start();

// --- 1. Security Check ---
// Check if the user is not logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect them to the login page
    header("Location: login.php");
    exit; // Stop the script from running
}

// --- 2. Get User Data from Session ---
// We can safely use these variables because we know the user is logged in
$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];
$email = $_SESSION['email'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - DDTMS DENR CARAGA</title>
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
            <a href="dashboard.php" class="flex items-center space-x-2">
                <svg class="w-8 h-8 text-blue-700" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 2a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 5a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path></svg>
                <span class="text-xl font-bold text-blue-800">DDTMS | DENR CARAGA</span>
            </a>
            
            <div class="flex items-center space-x-4">
                <span class="text-sm text-gray-600 hidden sm:block">
                    Welcome, <strong class="font-medium text-gray-800"><?php echo htmlspecialchars($full_name); ?></strong>
                    (<em class="text-blue-600"><?php echo htmlspecialchars($role); ?></em>)
                </span>
                <a href="logout.php" class="text-sm font-semibold text-red-600 hover:text-red-800 transition duration-150 flex items-center space-x-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    <span>Log Out</span>
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8">
        
        <h1 class="text-3xl font-bold text-gray-900 mb-6">
            Dashboard
        </h1>

        <div class="bg-white p-6 rounded-lg shadow-md mb-8">
            <h2 class="text-2xl font-semibold text-gray-800">
                Hello, <?php echo htmlspecialchars($full_name); ?>!
            </h2>
            <p class="text-gray-600 mt-2">
                Welcome to the Digital Document Tracking & Management System. Here's a summary of your current tasks and activities.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            
            <div class="bg-white p-6 rounded-lg shadow-lg border-l-4 border-blue-500 hover:shadow-xl transition-shadow duration-300">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">My Documents</h3>
                <p class="text-4xl font-bold text-blue-600">12</p>
                <p class="text-sm text-gray-500 mt-1">Total documents you've initiated.</p>
                <a href="#" class="inline-block mt-4 text-sm font-medium text-blue-600 hover:text-blue-800">View All &rarr;</a>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-lg border-l-4 border-yellow-500 hover:shadow-xl transition-shadow duration-300">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Pending My Action</h3>
                <p class="text-4xl font-bold text-yellow-600">3</p>
                <p class="text-sm text-gray-500 mt-1">Documents waiting for your review or signature.</p>
                <a href="#" class="inline-block mt-4 text-sm font-medium text-yellow-600 hover:text-yellow-800">View Queue &rarr;</a>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-lg border-l-4 border-green-500 hover:shadow-xl transition-shadow duration-300">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">Completed</h3>
                <p class="text-4xl font-bold text-green-600">8</p>
                <p class="text-sm text-gray-500 mt-1">Documents you've fully processed.</p>
                <a href="#" class="inline-block mt-4 text-sm font-medium text-green-600 hover:text-green-800">View Archive &rarr;</a>
            </div>

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

</body>
</html>