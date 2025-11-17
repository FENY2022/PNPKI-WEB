<?php
// You can include session_start() here if you need to access session
// variables, for example, to get the user's name.
session_start();

// Redirect to login if not logged in (as a fallback)
if (!isset($_SESSION['user_id'])) {
    // We are inside an iframe, so redirect the top-level window
    echo "<script>window.top.location.href = 'login.php';</script>";
    exit;
}
$full_name = $_SESSION['full_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Home</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        body { 
            font-family: 'Inter', sans-serif; 
            /* This p-10 matches the padding the main content area used to have */
            padding: 2.5rem; /* 40px */
            background-color: #f3f4f6; /* bg-gray-100 */
        }
    </style>
</head>
<body>
    
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

</body>
</html>