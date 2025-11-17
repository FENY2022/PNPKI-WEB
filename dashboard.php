<?php
session_start();

// --- 1. Security Check ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// --- 2. Get User Data from Session ---
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

        /* --- Sidebar Styles --- */
        /* Active link style */
        .sidebar-link.active {
            background-color: #eef2ff; /* bg-indigo-100 */
            color: #4338ca; /* text-indigo-700 */
            font-weight: 600;
        }
    </style>
</head>
<body class="bg-gray-100">

    <div id="preloader">
        <div class="spinner"></div>
    </div>

    <header class="bg-white shadow-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            
            <a href="dashboard_home.php" target="content_frame" class="flex items-center space-x-2">
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

    <div class="flex" style="height: calc(100vh - 68px);">
        
        <aside class="w-64 bg-white shadow-lg p-4 overflow-y-auto">
            <nav class="space-y-2">
                
                <a href="dashboard_home.php" target="content_frame" class="sidebar-link active flex items-center space-x-3 py-2 px-3 rounded-md text-sm transition-colors duration-150">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6-4l.01.01M12 16l.01.01M12 12l.01.01M12 8l.01.01M15 16l.01.01M15 12l.01.01M15 8l.01.01M9 16l.01.01M9 12l.01.01M9 8l.01.01"></path></svg>
                    <span>Dashboard</span>
                </a>

                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider pt-4 pb-1 px-3">Workflow</h3>

                <?php if ($role == 'Initiator'): ?>
                    <a href="new_document.php" target="content_frame" class="sidebar-link flex items-center space-x-3 py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-150">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 0V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2z"></path></svg>
                        <span>New Document</span>
                    </a>
                <?php endif; ?>

                <?php if (in_array($role, ['Section Chief', 'Division Chief', 'ARD', 'RED'])): ?>
                    <a href="my_queue.php" target="content_frame" class="sidebar-link flex items-center space-x-3 py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-150">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
                        <span>My Action Queue</span>
                    </a>
                <?php endif; ?>

                <?php if (in_array($role, ['Initiator', 'Section Chief'])): ?>
                    <a href="my_drafts.php" target="content_frame" class="sidebar-link flex items-center space-x-3 py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-150">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        <span>My Drafts</span>
                    </a>
                <?php endif; ?>

                <?php if (in_array($role, ['Section Chief', 'Division Chief'])): ?>
                    <a href="returned_docs.php" target="content_frame" class="sidebar-link flex items-center space-x-3 py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-150">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg>
                        <span>Returned Documents</span>
                    </a>
                <?php endif; ?>

                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider pt-4 pb-1 px-3">Archive</h3>

                <?php if ($role == 'Records Office'): ?>
                    <a href="records_management.php" target="content_frame" class="sidebar-link flex items-center space-x-3 py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-150">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h2a2 2 0 012 2v2m-4 6h4"></path></svg>
                        <span>Records Management</span>
                    </a>
                <?php endif; ?>
                
                <a href="completed_docs.php" target="content_frame" class="sidebar-link flex items-center space-x-3 py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-150">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <span>Completed</span>
                </a>

                <?php if ($role == 'Admin'): ?>
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider pt-4 pb-1 px-3">Admin</h3>
                    <a href="admin_users.php" target="content_frame" class="sidebar-link flex items-center space-x-3 py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-150">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 016-6h6m6 6v-1a6 6 0 00-6-6h-1.5m-3 0A3.988 3.988 0 0012 6.354v-2.006a4 4 0 100 5.292"></path></svg>
                        <span>User Management</span>
                    </a>
                    <a href="admin_settings.php" target="content_frame" class="sidebar-link flex items-center space-x-3 py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-150">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        <span>Settings</span>
                    </a>
                <?php endif; ?>

            </nav>
        </aside>

        <main class="flex-1 h-full">
            <iframe 
                name="content_frame" 
                src="dashboard_home.php" 
                frameborder="0" 
                class="w-full h-full"
            >
                Your browser does not support iframes.
            </iframe>
        </main>

    </div> <script>
        window.onload = function() {
            const preloader = document.getElementById('preloader');
            if (preloader) {
                preloader.classList.add('hidden');
            }
        };

        // --- MODIFIED: Active Sidebar Link Handler ---
        // This script now works by listening for clicks, which is
        // required for an iframe-based layout.
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarLinks = document.querySelectorAll('.sidebar-link');
            
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function() {
                    // Remove 'active' from all links
                    sidebarLinks.forEach(l => l.classList.remove('active'));
                    
                    // Add 'active' to the clicked link
                    this.classList.add('active');
                });
            });
        });

    </script>

</body>
</html>