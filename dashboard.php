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
            background-color: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.5s ease-out;
        }
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #e5e7eb;
            border-top-color: #2563eb;
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

        .sidebar-link.active {
            background-color: #eef2ff;
            color: #4338ca;
            font-weight: 600;
        }
    </style>
</head>
<body class="bg-gray-100">

    <div id="preloader">
        <div class="spinner"></div>
    </div>

    <!-- Header with profile dropdown and sidebar toggle -->
    <header class="bg-white shadow-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center relative">
            <!-- Sidebar toggle button on mobile -->
            <button id="sidebarToggle" class="lg:hidden flex items-center mr-3 text-blue-800 focus:outline-none" aria-label="Open sidebar">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            
            <a href="dashboard_home.php" target="content_frame" class="flex items-center space-x-2">
                <svg class="w-8 h-8 text-blue-700" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 1v11h8V5H6z" clip-rule="evenodd"/>
                </svg>
                <span class="text-xl font-bold text-blue-800">DDTMS | DENR CARAGA</span>
            </a>

            <div class="flex items-center space-x-4">
                <!-- Hide desktop welcome, move to profile dropdown -->
                <!-- PROFILE DROPDOWN -->
                <div class="relative" id="profileDropdownContainer">
                    <button id="profileDropdownBtn" class="flex items-center focus:outline-none">
                        <img src="logo/profile_default.png" alt="Profile" class="w-9 h-9 rounded-full border-2 border-blue-600 object-cover bg-gray-200">
                        <span class="ml-2 font-medium text-gray-800 hidden sm:inline-block"><?php echo htmlspecialchars($full_name); ?></span>
                        <svg class="w-5 h-5 ml-1 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div id="profileDropdownMenu" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-md overflow-hidden shadow-lg z-50 border border-gray-200">
                        <div class="p-4 border-b border-gray-100 bg-blue-50">
                            <div class="flex items-center space-x-2">
                                <img src="logo/profile_default.png" alt="Profile" class="w-11 h-11 rounded-full border-2 border-blue-600 object-cover bg-gray-200">
                                <div>
                                    <div class="font-bold text-blue-900"><?php echo htmlspecialchars($full_name); ?></div>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars($email); ?></div>
                                    <div class="text-xs text-blue-600 font-medium mt-1"><?php echo htmlspecialchars($role); ?></div>
                                </div>
                            </div>
                        </div>
                        <a href="#" class="block px-4 py-2 text-gray-700 hover:bg-gray-100 text-sm">Profile</a>
                        <div class="border-t border-gray-100"></div>
                        <a href="logout.php" class="block px-4 py-2 text-red-600 font-semibold hover:bg-red-50 text-sm flex items-center space-x-1">
                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1m0-9V3"/>
                            </svg>
                            <span>Log Out</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <div class="flex h-screen">
        <!-- Sidebar -->
        <aside id="sidebar"
            class="fixed inset-y-0 left-0 bg-white shadow-lg p-4 w-64 z-40 overflow-y-auto transform -translate-x-full transition-transform duration-300 lg:relative lg:translate-x-0 lg:w-64"
            style="top:68px;height:calc(100vh - 68px);">
            <nav class="space-y-2">

                <a href="dashboard_home.php" target="content_frame" class="sidebar-link active flex items-center space-x-3 py-2 px-3 rounded-md text-sm transition-colors duration-150">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7m-9 2v10a1 1 0 001 1h6a1 1 0 001-1V10m-9 2h10"/>
                    </svg>
                    <span>Dashboard</span>
                </a>

                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider pt-4 pb-1 px-3">Workflow</h3>

                <?php if ($role == 'Initiator'): ?>
                    <a href="new_document.php" target="content_frame" class="sidebar-link flex items-center space-x-3 py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-150">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        <span>New Document</span>
                    </a>
                <?php endif; ?>

                <?php if (in_array($role, ['Section Chief', 'Division Chief', 'ARD', 'RED'])): ?>
                    <a href="my_queue.php" target="content_frame" class="sidebar-link flex items-center space-x-3 py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-150">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>My Action Queue</span>
                    </a>
                <?php endif; ?>

                <?php if (in_array($role, ['Initiator', 'Section Chief'])): ?>
                    <a href="my_drafts.php" target="content_frame" class="sidebar-link flex items-center space-x-3 py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-150">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h16v16H4z"/>
                        </svg>
                        <span>My Drafts</span>
                    </a>
                <?php endif; ?>

                <?php if (in_array($role, ['Section Chief', 'Division Chief'])): ?>
                    <a href="returned_docs.php" target="content_frame" class="sidebar-link flex items-center space-x-3 py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-150">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6h13"/>
                        </svg>
                        <span>Returned Documents</span>
                    </a>
                <?php endif; ?>

                <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider pt-4 pb-1 px-3">Archive</h3>

                <?php if ($role == 'Records Office'): ?>
                    <a href="records_management.php" target="content_frame" class="sidebar-link flex items-center space-x-3 py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-150">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405M19 13V7a2.003 2.003 0 00-2-2h-4a2 2 0 00-2 2v6"/>
                        </svg>
                        <span>Records Management</span>
                    </a>
                <?php endif; ?>
                
                <a href="completed_docs.php" target="content_frame" class="sidebar-link flex items-center space-x-3 py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-150">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span>Completed</span>
                </a>

                <?php if ($role == 'Admin'): ?>
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider pt-4 pb-1 px-3">Admin</h3>
                    <a href="admin_users.php" target="content_frame" class="sidebar-link flex items-center space-x-3 py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-150">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 21v-2a4 4 0 00-3-3.87M4 6V4a2 2 0 012-2h8a2 2 0 012 2v2"/>
                        </svg>
                        <span>User Management</span>
                    </a>
                    <a href="admin_settings.php" target="content_frame" class="sidebar-link flex items-center space-x-3 py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-150">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3"/>
                        </svg>
                        <span>Settings</span>
                    </a>
                <?php endif; ?>
            </nav>
        </aside>

        <!-- Main content with overlay for mobile sidebar -->
        <div class="flex-1 h-full flex flex-col" style="min-height:0;">
            <div id="sidebarBackdrop" class="fixed inset-0 bg-black bg-opacity-40 z-30 hidden lg:hidden"></div>
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
        </div>
    </div>

    <script>
        // Preloader
        window.onload = function() {
            const preloader = document.getElementById('preloader');
            if (preloader) {
                preloader.classList.add('hidden');
            }
        };

        // Collapsible Sidebar for Mobile
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarBackdrop = document.getElementById('sidebarBackdrop');

        function openSidebar() {
            sidebar.classList.remove('-translate-x-full');
            sidebarBackdrop.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        }

        function closeSidebar() {
            sidebar.classList.add('-translate-x-full');
            sidebarBackdrop.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        }

        if(sidebarToggle){
            sidebarToggle.addEventListener('click', function(){
                openSidebar();
            });
        }
        if(sidebarBackdrop){
            sidebarBackdrop.addEventListener('click', function(){
                closeSidebar();
            });
        }
        // Close sidebar automatically if screen is resized up to desktop
        window.addEventListener('resize', function() {
            if(window.innerWidth >= 1024) {
                sidebar.classList.remove('-translate-x-full');
                sidebarBackdrop.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
            }
        });

        // Active Sidebar Link Handler
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarLinks = document.querySelectorAll('.sidebar-link');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', function() {
                    sidebarLinks.forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                    // On mobile, close sidebar when a link is clicked
                    if(window.innerWidth < 1024){
                        closeSidebar();
                    }
                });
            });
        });

        // Profile Dropdown
        const profileDropdownBtn = document.getElementById('profileDropdownBtn');
        const profileDropdownMenu = document.getElementById('profileDropdownMenu');

        if(profileDropdownBtn && profileDropdownMenu){
            profileDropdownBtn.addEventListener('click', function(e){
                e.stopPropagation();
                profileDropdownMenu.classList.toggle('hidden');
            });
            // Hide on click outside
            document.body.addEventListener('click', function(){
                profileDropdownMenu.classList.add('hidden');
            });
            // Prevent closing when clicking inside the menu
            profileDropdownMenu.addEventListener('click', function(e){
                e.stopPropagation();
            });
        }
    </script>

</body>
</html>