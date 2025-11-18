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

// Helper: create initials for avatar fallback
function initials($name) {
    $parts = preg_split("/\s+/", trim($name));
    $initials = "";
    foreach ($parts as $p) {
        if ($p !== "") $initials .= mb_substr($p, 0, 1);
        if (mb_strlen($initials) >= 2) break;
    }
    return strtoupper($initials ?: "U");
}
$initials = initials($full_name);
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Dashboard - DDTMS DENR CARAGA</title>
    <link rel="icon" type="image/png" href="logo/icon.png">
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        html, body { height: 100%; }
        body { font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; }

        /* Preloader */
        #preloader {
            position: fixed; inset: 0;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(180deg, rgba(243,244,246,0.96), rgba(255,255,255,0.96));
            z-index: 60; transition: opacity .35s ease;
        }
        #preloader.hidden { opacity: 0; pointer-events: none; }

        /* Subtle card shadow */
        .card-shadow { box-shadow: 0 8px 22px rgba(15,23,42,0.06); }

        /* Sidebar active */
        .sidebar-link.active {
            background: linear-gradient(90deg, rgba(238,242,255,1), rgba(245,247,255,1));
            color: #312e81; font-weight: 600;
        }

        /* Smooth transition for iframe resize */
        iframe { transition: height .12s ease; }

        /* Avatar initials styling in case no image */
        .avatar-initials {
            display: inline-flex; align-items:center; justify-content:center;
            background: #e0e7ff; color:#312e81; font-weight:700;
        }
    </style>
</head>
<body class="bg-gray-50 h-full antialiased text-gray-700">

    <div id="preloader">
        <div class="flex flex-col items-center space-y-3">
            <div class="animate-spin rounded-full h-12 w-12 border-4 border-gray-200 border-t-blue-600"></div>
            <div class="text-sm text-gray-600">Preparing your workspace…</div>
        </div>
    </div>

    <!-- TOPBAR -->
    <header class="bg-white border-b sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                
                <div class="flex items-center space-x-3">
                    <!-- mobile toggle -->
                    <button id="sidebarToggle" aria-controls="sidebar" aria-expanded="false" class="lg:hidden p-2 rounded-md hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500" title="Toggle sidebar">
                        <svg class="h-6 w-6 text-blue-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/>
                        </svg>
                    </button>

                    <!-- Brand -->
                    <a href="dashboard_home.php" target="content_frame" class="flex items-center space-x-2 group" title="Go to dashboard home">
                        <img src="logo/icon.png" alt="logo" class="w-9 h-9 rounded-md object-cover" />
                        <div class="hidden sm:block">
                            <div class="text-sm font-bold text-blue-800">DDTMS</div>
                            <div class="text-xs text-gray-500 -mt-0.5">DENR CARAGA</div>
                        </div>
                    </a>
                </div>

                <!-- search / actions -->
                <div class="flex-1 px-4">
                    <div class="max-w-2xl mx-auto">
                        <div class="relative">
                            <input id="globalSearch" type="search" placeholder="Search documents, users, actions..." class="w-full border bg-white border-gray-200 rounded-full py-2 pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300 focus:border-transparent" />
                            <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                                <svg class="w-4 h-4 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M10.5 18A7.5 7.5 0 1010.5 3a7.5 7.5 0 000 15z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: notifications + profile -->
                <div class="flex items-center space-x-3">
                    <!-- notifications -->
                    <div class="relative">
                        <button id="notifBtn" class="p-2 rounded-md hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500" title="Notifications" aria-expanded="false" aria-controls="notifMenu">
                            <svg class="h-5 w-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                            </svg>
                            <span id="notifBadge" class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-xs font-semibold bg-red-600 text-white">3</span>
                        </button>
                        <div id="notifMenu" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg card-shadow overflow-hidden border border-gray-100 z-50">
                            <div class="px-4 py-3 border-b bg-gray-50">
                                <div class="flex items-center justify-between">
                                    <div class="text-sm font-semibold">Notifications</div>
                                    <button id="markAllRead" class="text-xs text-blue-600 hover:underline">Mark all read</button>
                                </div>
                            </div>
                            <div class="max-h-56 overflow-auto">
                                <!-- Example notification items -->
                                <a href="my_queue.php" target="content_frame" class="block px-4 py-3 hover:bg-gray-50">
                                    <div class="flex items-start space-x-3">
                                        <div class="w-2.5 h-2.5 mt-1 rounded-full bg-blue-500 flex-shrink-0"></div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-800">You have 2 new items in your queue</div>
                                            <div class="text-xs text-gray-500">Section Chief • 2h</div>
                                        </div>
                                    </div>
                                </a>
                                <a href="completed_docs.php" target="content_frame" class="block px-4 py-3 hover:bg-gray-50">
                                    <div class="flex items-start space-x-3">
                                        <div class="w-2.5 h-2.5 mt-1 rounded-full bg-green-400 flex-shrink-0"></div>
                                        <div>
                                            <div class="text-sm font-medium text-gray-800">Document #123 has been signed</div>
                                            <div class="text-xs text-gray-500">Records Office • 1d</div>
                                        </div>
                                    </div>
                                </a>
                                <div class="p-4 text-xs text-gray-400">No more notifications</div>
                            </div>
                        </div>
                    </div>

                    <!-- profile -->
                    <div class="relative" id="profileContainer">
                        <button id="profileBtn" class="flex items-center space-x-2 rounded-md p-1 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500" aria-haspopup="true" aria-expanded="false" aria-controls="profileMenu">
                            <?php if (file_exists('logo/profile_default.png')): ?>
                                <img src="logo/profile_default.png" alt="avatar" class="w-9 h-9 rounded-full object-cover border-2 border-blue-50">
                            <?php else: ?>
                                <div class="w-9 h-9 rounded-full avatar-initials"><?php echo $initials; ?></div>
                            <?php endif; ?>
                            <div class="hidden sm:flex sm:flex-col sm:items-start">
                                <div class="text-sm font-medium text-gray-800 leading-4"><?php echo htmlspecialchars($full_name); ?></div>
                                <div class="text-xs text-gray-500 leading-3"><?php echo htmlspecialchars($role); ?></div>
                            </div>
                            <svg class="w-4 h-4 text-gray-500 hidden sm:block" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <div id="profileMenu" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-lg card-shadow border border-gray-100 overflow-hidden z-50">
                            <div class="px-4 py-4 bg-blue-50 border-b">
                                <div class="flex items-center space-x-3">
                                    <?php if (file_exists('logo/profile_default.png')): ?>
                                        <img src="logo/profile_default.png" alt="avatar" class="w-12 h-12 rounded-full object-cover border-2 border-blue-600">
                                    <?php else: ?>
                                        <div class="w-12 h-12 rounded-full avatar-initials text-lg"><?php echo $initials; ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="text-sm font-semibold text-blue-900"><?php echo htmlspecialchars($full_name); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($email); ?></div>
                                        <div class="text-xs text-blue-600 font-medium mt-1"><?php echo htmlspecialchars($role); ?></div>
                                    </div>
                                </div>
                            </div>
                            <a href="#" class="block px-4 py-3 text-sm text-gray-700 hover:bg-gray-50">My Profile</a>
                            <a href="logout.php" class="block px-4 py-3 text-sm text-red-600 font-semibold hover:bg-red-50">Sign out</a>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </header>

    <div class="flex h-[calc(100vh-64px)]">
        <!-- Sidebar -->
        <aside id="sidebar" class="bg-white w-72 border-r hidden lg:flex flex-col card-shadow" style="min-height:0;">
            <div class="p-4 pb-2">
                <div class="flex items-center justify-between">
                    <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Navigation</div>
                    <button id="collapseSidebarBtn" class="p-1 rounded-md hover:bg-gray-100 focus:outline-none" title="Collapse sidebar">
                        <svg class="w-5 h-5 text-gray-500" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9l6 6 6-6"/>
                        </svg>
                    </button>
                </div>
            </div>

            <nav class="px-2 pb-6 overflow-auto space-y-1 flex-1">
                <a href="dashboard_home.php" target="content_frame" class="sidebar-link group flex items-center gap-3 py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-50">
                    <svg class="w-5 h-5 text-blue-600 group-hover:text-blue-700" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M13 5v14"/></svg>
                    <span>Dashboard</span>
                </a>

                <div class="mt-3 px-3 text-xs text-gray-500 uppercase tracking-wide">Workflow</div>

                <?php if ($role == 'Initiator'): ?>
                    <a href="new_document.php" target="content_frame" class="sidebar-link group flex items-center gap-3 py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-50">
                        <svg class="w-5 h-5 text-green-500 group-hover:text-green-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>New Document</span>
                    </a>
                <?php endif; ?>

                <?php if (in_array($role, ['Section Chief', 'Division Chief', 'ARD', 'RED'])): ?>
                    <a href="my_queue.php" target="content_frame" class="sidebar-link group flex items-center gap-3 py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-50">
                        <svg class="w-5 h-5 text-yellow-500 group-hover:text-yellow-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>My Action Queue</span>
                    </a>
                <?php endif; ?>

                <?php if (in_array($role, ['Initiator', 'Section Chief'])): ?>
                    <a href="my_drafts.php" target="content_frame" class="sidebar-link group flex items-center gap-3 py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-50">
                        <svg class="w-5 h-5 text-indigo-500 group-hover:text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4h16v16H4z"/></svg>
                        <span>My Drafts</span>
                    </a>
                <?php endif; ?>

                <?php if (in_array($role, ['Section Chief', 'Division Chief'])): ?>
                    <a href="returned_docs.php" target="content_frame" class="sidebar-link group flex items-center gap-3 py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-50">
                        <svg class="w-5 h-5 text-orange-500 group-hover:text-orange-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6h13"/></svg>
                        <span>Returned Documents</span>
                    </a>
                <?php endif; ?>

                <div class="mt-4 px-3 text-xs text-gray-500 uppercase tracking-wide">Archive</div>

                <?php if ($role == 'Records Office'): ?>
                    <a href="records_management.php" target="content_frame" class="sidebar-link group flex items-center gap-3 py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-50">
                        <svg class="w-5 h-5 text-pink-500 group-hover:text-pink-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405M19 13V7a2 2 0 00-2-2h-4a2 2 0 00-2 2v6"/></svg>
                        <span>Records Management</span>
                    </a>
                <?php endif; ?>

                <a href="completed_docs.php" target="content_frame" class="sidebar-link group flex items-center gap-3 py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-50">
                    <svg class="w-5 h-5 text-teal-500 group-hover:text-teal-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>Completed</span>
                </a>

                <?php if ($role == 'Admin'): ?>
                    <div class="mt-4 px-3 text-xs text-gray-500 uppercase tracking-wide">Admin</div>
                    <a href="admin_users.php" target="content_frame" class="sidebar-link group flex items-center gap-3 py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-50">
                        <svg class="w-5 h-5 text-gray-600 group-hover:text-gray-700" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 21v-2a4 4 0 00-3-3.87M4 6V4a2 2 0 012-2h8a2 2 0 012 2v2"/></svg>
                        <span>User Management</span>
                    </a>
                    <a href="admin_settings.php" target="content_frame" class="sidebar-link group flex items-center gap-3 py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-50">
                        <svg class="w-5 h-5 text-gray-600 group-hover:text-gray-700" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3"/></svg>
                        <span>Settings</span>
                    </a>
                <?php endif; ?>

            </nav>

            <div class="px-4 py-3 border-t text-xs text-gray-500">
                <div class="flex items-center justify-between">
                    <div>Signed in as</div>
                    <div class="font-medium text-gray-800"><?php echo htmlspecialchars($full_name); ?></div>
                </div>
            </div>
        </aside>

        <!-- Mobile sidebar (slide-over) -->
        <div id="mobileSidebar" class="fixed inset-y-0 left-0 z-50 w-72 transform -translate-x-full transition-transform duration-300 lg:hidden">
            <div class="h-full bg-white card-shadow border-r overflow-auto">
                <div class="p-4 flex items-center justify-between">
                    <div class="text-sm font-semibold text-gray-700">Menu</div>
                    <button id="closeMobileSidebar" class="p-1 rounded-md hover:bg-gray-100 focus:outline-none" title="Close">
                        <svg class="w-5 h-5 text-gray-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <nav class="px-2 pb-6 space-y-1">
                    <!-- replicate links for mobile -->
                    <a href="dashboard_home.php" target="content_frame" class="mobile-link block py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-50">Dashboard</a>
                    <?php if ($role == 'Initiator'): ?>
                        <a href="new_document.php" target="content_frame" class="mobile-link block py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-50">New Document</a>
                    <?php endif; ?>
                    <?php if (in_array($role, ['Section Chief', 'Division Chief', 'ARD', 'RED'])): ?>
                        <a href="my_queue.php" target="content_frame" class="mobile-link block py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-50">My Action Queue</a>
                    <?php endif; ?>
                    <?php if (in_array($role, ['Initiator', 'Section Chief'])): ?>
                        <a href="my_drafts.php" target="content_frame" class="mobile-link block py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-50">My Drafts</a>
                    <?php endif; ?>
                    <?php if (in_array($role, ['Section Chief', 'Division Chief'])): ?>
                        <a href="returned_docs.php" target="content_frame" class="mobile-link block py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-50">Returned Documents</a>
                    <?php endif; ?>
                    <?php if ($role == 'Records Office'): ?>
                        <a href="records_management.php" target="content_frame" class="mobile-link block py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-50">Records Management</a>
                    <?php endif; ?>
                    <a href="completed_docs.php" target="content_frame" class="mobile-link block py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-50">Completed</a>
                    <?php if ($role == 'Admin'): ?>
                        <a href="admin_users.php" target="content_frame" class="mobile-link block py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-50">User Management</a>
                        <a href="admin_settings.php" target="content_frame" class="mobile-link block py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-50">Settings</a>
                    <?php endif; ?>
                    <div class="mt-4 px-3 text-sm">
                        <a href="#" class="block py-2 text-gray-700 hover:bg-gray-50 rounded-md">My Profile</a>
                        <a href="logout.php" class="block py-2 text-red-600 font-semibold hover:bg-red-50 rounded-md">Sign out</a>
                    </div>
                </nav>
            </div>
        </div>

        <!-- Overlay for mobile sidebar -->
        <div id="mobileBackdrop" class="fixed inset-0 bg-black bg-opacity-40 z-40 hidden lg:hidden"></div>

        <!-- MAIN -->
        <main id="mainContent" class="flex-1 h-full">
            <iframe name="content_frame" src="dashboard_home.php" frameborder="0" class="w-full h-full" aria-label="Content frame"></iframe>
        </main>
    </div>

    <script>
        // Preloader hide
        window.addEventListener('load', function() {
            const pre = document.getElementById('preloader');
            if (pre) pre.classList.add('hidden');
        });

        // Sidebar toggle for mobile
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mobileSidebar = document.getElementById('mobileSidebar');
        const mobileBackdrop = document.getElementById('mobileBackdrop');
        const closeMobileSidebar = document.getElementById('closeMobileSidebar');

        function openMobileSidebar() {
            mobileSidebar.classList.remove('-translate-x-full');
            mobileBackdrop.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
            sidebarToggle.setAttribute('aria-expanded', 'true');
        }
        function closeMobile() {
            mobileSidebar.classList.add('-translate-x-full');
            mobileBackdrop.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            sidebarToggle.setAttribute('aria-expanded', 'false');
        }

        if (sidebarToggle) sidebarToggle.addEventListener('click', openMobileSidebar);
        if (closeMobileSidebar) closeMobileSidebar.addEventListener('click', closeMobile);
        if (mobileBackdrop) mobileBackdrop.addEventListener('click', closeMobile);

        // Close mobile when link clicked
        document.querySelectorAll('.mobile-link').forEach(l => l.addEventListener('click', closeMobile));

        // Desktop collapse sidebar button (collapses to icons-only)
        const collapseBtn = document.getElementById('collapseSidebarBtn');
        const sidebar = document.getElementById('sidebar');
        let collapsed = false;
        if (collapseBtn) {
            collapseBtn.addEventListener('click', () => {
                collapsed = !collapsed;
                if (collapsed) {
                    sidebar.classList.add('w-20');
                    sidebar.classList.remove('w-72');
                    document.querySelectorAll('#sidebar .sidebar-link span').forEach(s => s.classList.add('hidden'));
                    document.querySelectorAll('#sidebar .sidebar-link svg').forEach(svg => svg.classList.add('mx-auto'));
                } else {
                    sidebar.classList.remove('w-20');
                    sidebar.classList.add('w-72');
                    document.querySelectorAll('#sidebar .sidebar-link span').forEach(s => s.classList.remove('hidden'));
                    document.querySelectorAll('#sidebar .sidebar-link svg').forEach(svg => svg.classList.remove('mx-auto'));
                }
            });
        }

        // Active link handling for both desktop and mobile links
        function clearActive() {
            document.querySelectorAll('.sidebar-link').forEach(l => l.classList.remove('active'));
            document.querySelectorAll('.mobile-link').forEach(l => l.classList.remove('active'));
        }
        document.querySelectorAll('.sidebar-link, .mobile-link').forEach(link => {
            link.addEventListener('click', function() {
                clearActive();
                this.classList.add('active');
            });
        });

        // Notifications dropdown
        const notifBtn = document.getElementById('notifBtn');
        const notifMenu = document.getElementById('notifMenu');
        const notifBadge = document.getElementById('notifBadge');
        if (notifBtn && notifMenu) {
            notifBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                notifMenu.classList.toggle('hidden');
                notifBtn.setAttribute('aria-expanded', String(!notifMenu.classList.contains('hidden')));
            });
            document.addEventListener('click', () => {
                notifMenu.classList.add('hidden');
                notifBtn.setAttribute('aria-expanded', 'false');
            });
            notifMenu.addEventListener('click', (e) => e.stopPropagation());
        }

        // Profile dropdown
        const profileBtn = document.getElementById('profileBtn');
        const profileMenu = document.getElementById('profileMenu');
        if (profileBtn && profileMenu) {
            profileBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                profileMenu.classList.toggle('hidden');
                profileBtn.setAttribute('aria-expanded', String(!profileMenu.classList.contains('hidden')));
            });
            document.addEventListener('click', () => {
                profileMenu.classList.add('hidden');
                profileBtn.setAttribute('aria-expanded', 'false');
            });
            profileMenu.addEventListener('click', (e) => e.stopPropagation());
        }

        // Mark all notifications read (example action)
        const markAllRead = document.getElementById('markAllRead');
        if (markAllRead && notifBadge) {
            markAllRead.addEventListener('click', (e) => {
                e.preventDefault();
                notifBadge.remove();
                // TODO: send AJAX to server to mark read
            });
        }

        // Search field: quick open suggestions (local demo)
        const searchInput = document.getElementById('globalSearch');
        let searchTimer = null;
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    const q = this.value.trim();
                    // For now: if user types "queue" navigate to queue, as a helpful shortcut
                    if (q.length >= 3) {
                        const lq = q.toLowerCase();
                        if (lq.includes('queue')) {
                            window.frames['content_frame'].location = 'my_queue.php';
                        } else if (lq.includes('draft')) {
                            window.frames['content_frame'].location = 'my_drafts.php';
                        }
                    }
                }, 350);
            });
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    // fallback: open dashboard home
                    window.frames['content_frame'].location = 'dashboard_home.php?search=' + encodeURIComponent(this.value);
                }
            });
        }

        // Keep iframe height synced (defensive)
        const iframe = document.querySelector('iframe[name="content_frame"]');
        function resizeFrame() {
            if (!iframe) return;
            iframe.style.height = (window.innerHeight - document.querySelector('header').offsetHeight) + 'px';
        }
        window.addEventListener('resize', resizeFrame);
        window.addEventListener('load', resizeFrame);
        resizeFrame();

        // Accessibility: close with Escape
        document.addEventListener('keydown', function(e){
            if (e.key === 'Escape') {
                // close menus and mobile sidebar
                if (!notifMenu.classList.contains('hidden')) notifMenu.classList.add('hidden');
                if (!profileMenu.classList.contains('hidden')) profileMenu.classList.add('hidden');
                if (!mobileBackdrop.classList.contains('hidden')) closeMobile();
            }
        });
    </script>

</body>
</html>