<?php
session_start();

// --- 1. Security Check ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// --- 2. Get User Data from Session ---
$user_id = (int)$_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$role = $_SESSION['role'];
$email = $_SESSION['email'];

$otos_userlink = $_SESSION['otos_userlink'];


// --- 3. DB Connection (Local / ddts_pnpki) ---
// This connection is used for fetching the profile picture and other local data.
$conn = null;
if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
} elseif (file_exists(__DIR__ . '/db.php')) {
    require_once __DIR__ . '/db.php';
} else {
    // Fallback MySQLi connection
    $dbHost = '127.0.0.1';
    $dbName = 'ddts_pnpki';
    $dbUser = 'root';
    $dbPass = '';
    
    $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);
    
    if ($conn->connect_error) {
        $conn = null; 
    }
}

// --- 3.5 Fetch Signatory_Station (FIXED LOGIC) ---
// We use the external connection (db_international.php) for this specific query.
$signatory_station = ""; 

if (isset($otos_userlink) && file_exists(__DIR__ . '/db_international.php')) {
    
    // 1. Include the external DB configuration
    require_once __DIR__ . '/db_international.php';
    
    // 2. Get the connection to the OTOS database
    // Note: This function handles its own error logging/connection failures.
    $otos_conn = get_db_connection();

    if ($otos_conn instanceof mysqli) {
        // 3. Query the 'useremployee' table in the remote DB
        $sql_sig = "SELECT Signatory_Station FROM useremployee WHERE id = ?";
        
        if ($stmt_sig = $otos_conn->prepare($sql_sig)) {
            $stmt_sig->bind_param("i", $otos_userlink);
            $stmt_sig->execute();
            $stmt_sig->bind_result($signatory_station);
            $stmt_sig->fetch();
            $stmt_sig->close();
            
            // Echo the result as originally requested
             $signatory_station; 
        }
        
        // Close the external connection
        $otos_conn->close();
    }
}


// --- 4. Fetch Profile Picture (Using Local DB) ---
$profile_picture_path = $_SESSION['profile_picture_path'] ?? null;

if ($conn && $user_id && $profile_picture_path === null) {
    
    $stmt = $conn->prepare("SELECT profile_picture_path FROM users WHERE user_id = ?");
    
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $profile_picture_path = $row['profile_picture_path'];
            $_SESSION['profile_picture_path'] = $profile_picture_path;
        }
        
        $stmt->close();
    }
}

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

// Close local connection
if ($conn instanceof mysqli && !empty($conn->thread_id)) {
    $conn->close();
}
?>


<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Dashboard - DDTMS DENR CARAGA</title>
    <link rel="icon" type="image/png" href="logo/ddtms.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">


    <style>
        /* Import Inter for consistency with original. Use Poppins if preferred, but Inter looks good here. */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        html, body { height: 100%; }
        body { 
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; 
            background-color: #f7f9fc; /* Light background for modern feel */
        }

        /* Preloader */
        #preloader {
            position: fixed; inset: 0;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(245,247,255,0.98));
            z-index: 60; transition: opacity .35s ease;
        }
        #preloader.hidden { opacity: 0; pointer-events: none; }

        /* Enhanced Card Shadow */
        .card-shadow { box-shadow: 0 10px 30px rgba(15,23,42,0.08); }

        /* Sidebar active */
        .sidebar-link.active {
            background-color: #e0f2fe; /* Light blue background */
            color: #1d4ed8; /* Darker blue text */
            font-weight: 600;
            border-left: 4px solid #3b82f6; /* Accent border */
            padding-left: 12px !important;
        }
        .sidebar-link {
            transition: all 0.15s ease;
            border-left: 4px solid transparent;
        }
        .sidebar-link:hover {
            background-color: #f3f4f6;
        }

        /* Smooth transition for iframe resize */
        iframe { transition: height .12s ease; }

        /* Avatar initials styling */
        .avatar-initials {
            display: inline-flex; align-items:center; justify-content:center;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); 
            color: white; 
            font-weight: 600;
        }

        /* Header buttons focus ring */
        .header-btn:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.4);
        }
    </style>
</head>
<body class="h-full antialiased text-gray-700">

    <div id="preloader">
        <div class="flex flex-col items-center space-y-3">
            <div class="animate-spin rounded-full h-12 w-12 border-4 border-gray-200 border-t-indigo-600"></div>
            <div class="text-sm font-medium text-gray-600">Loading Dashboard…</div>
        </div>
    </div>

    <header class="bg-white border-b border-gray-100 sticky top-0 z-50 shadow-sm">
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                
                <div class="flex items-center space-x-4">
                    <button id="sidebarToggle" aria-controls="sidebar" aria-expanded="false" class="lg:hidden p-2 rounded-full text-indigo-700 hover:bg-gray-100 focus:ring-indigo-500 header-btn" title="Toggle sidebar">
                        <i class="fas fa-bars h-5 w-5"></i>
                    </button>

                    <a href="dashboard_home.php" target="content_frame" class="main-logo flex items-center space-x-3 group" title="Go to dashboard home">
                        <img src="logo/icon.png" alt="logo" class="w-8 h-8 rounded-full object-cover shadow-sm" />
                        <div class="hidden sm:block">
                            <div class="text-lg font-bold text-indigo-700">DDTMS</div>
                            <div class="text-xs text-gray-500 -mt-1">DENR CARAGA</div>
                        </div>
                    </a>
                </div>

                <div class="flex-1 px-4 hidden md:block">
                    <div class="max-w-2xl mx-auto">
                        <div class="relative">
                            <input id="globalSearch" type="search" placeholder="Search documents, users, actions..." class="w-full border bg-gray-50 border-gray-200 rounded-full py-2.5 pl-12 pr-6 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-400 transition-all duration-200" />
                            <div class="absolute inset-y-0 left-4 flex items-center pointer-events-none">
                                <i class="fas fa-search w-4 h-4 text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center space-x-3">
                    <div class="relative">
                        <button id="notifBtn" class="header-btn p-2 rounded-full text-gray-600 hover:bg-gray-100 focus:ring-blue-500" title="Notifications" aria-expanded="false" aria-controls="notifMenu">
                            <i class="fas fa-bell h-5 w-5"></i>
                            <span id="notifBadge" class="absolute top-0.5 right-0.5 inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-xs font-bold bg-red-600 text-white transform translate-x-1/2 -translate-y-1/2">3</span>
                        </button>
                        <div id="notifMenu" class="hidden absolute right-0 mt-3 w-80 bg-white rounded-xl shadow-xl overflow-hidden border border-gray-100 z-50">
                            <div class="px-4 py-3 border-b bg-gray-50 flex items-center justify-between">
                                <div class="text-sm font-semibold text-gray-800">Notifications (3)</div>
                                <button id="markAllRead" class="text-xs text-indigo-600 hover:underline">Mark all read</button>
                            </div>
                            <div class="max-h-56 overflow-auto">
                                <a href="my_queue.php" target="content_frame" class="block px-4 py-3 hover:bg-gray-50 transition-colors border-b">
                                    <div class="flex items-start space-x-3">
                                        <i class="fas fa-check-double mt-1 text-blue-500 flex-shrink-0"></i>
                                        <div>
                                            <div class="text-sm font-medium text-gray-800">You have 2 new items in your queue</div>
                                            <div class="text-xs text-gray-500 mt-0.5">Section Chief • 2 hours ago</div>
                                        </div>
                                    </div>
                                </a>
                                <a href="completed_docs.php" target="content_frame" class="block px-4 py-3 hover:bg-gray-50 transition-colors border-b">
                                    <div class="flex items-start space-x-3">
                                        <i class="fas fa-file-signature mt-1 text-green-500 flex-shrink-0"></i>
                                        <div>
                                            <div class="text-sm font-medium text-gray-800">Document #123 has been signed</div>
                                            <div class="text-xs text-gray-500 mt-0.5">Records Office • 1 day ago</div>
                                        </div>
                                    </div>
                                </a>
                                <div class="p-4 text-xs text-center text-gray-400">No more notifications</div>
                            </div>
                        </div>
                    </div>

                    <div class="relative" id="profileContainer">
                        <button id="profileBtn" class="header-btn flex items-center space-x-3 rounded-full p-1.5 hover:bg-gray-100 focus:ring-blue-500" aria-haspopup="true" aria-expanded="false" aria-controls="profileMenu">
                            
                            <?php if (!empty($profile_picture_path) && file_exists($profile_picture_path)): ?>
                                <img src="<?php echo htmlspecialchars($profile_picture_path); ?>" alt="avatar" class="w-9 h-9 rounded-full object-cover border-2 border-indigo-200">
                            <?php else: ?>
                                <div class="w-9 h-9 rounded-full avatar-initials text-sm"><?php echo $initials; ?></div>
                            <?php endif; ?>
                            <div class="hidden md:flex md:flex-col md:items-start">
                                <div class="text-sm font-medium text-gray-800 leading-4"><?php echo htmlspecialchars($full_name); ?></div>
                                <div class="text-xs text-gray-500 leading-3"><?php echo htmlspecialchars($role); ?></div>
                            </div>
                            <i class="fas fa-chevron-down w-3 h-3 text-gray-500 hidden md:block"></i>
                        </button>

                        <div id="profileMenu" class="hidden absolute right-0 mt-3 w-64 bg-white rounded-xl shadow-xl border border-gray-100 overflow-hidden z-50">
                            <div class="px-5 py-4 bg-indigo-50 border-b">
                                <div class="flex items-center space-x-3">
                                    
                                    <?php if (!empty($profile_picture_path) && file_exists($profile_picture_path)): ?>
                                        <img src="<?php echo htmlspecialchars($profile_picture_path); ?>" alt="avatar" class="w-12 h-12 rounded-full object-cover border-2 border-indigo-600">
                                    <?php else: ?>
                                        <div class="w-12 h-12 rounded-full avatar-initials text-xl"><?php echo $initials; ?></div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="text-sm font-semibold text-indigo-900"><?php echo htmlspecialchars($full_name); ?></div>
                                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($email); ?></div>
                                        <div class="text-xs text-indigo-600 font-medium mt-1"><?php echo htmlspecialchars($role); ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <a href="profile.php" target="content_frame" class="profile-link block px-5 py-3 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                <i class="fas fa-user-circle w-4 mr-2 text-gray-400"></i> My Profile
                            </a>
                            <a href="logout.php" class="block px-5 py-3 text-sm text-red-600 font-semibold hover:bg-red-50 transition-colors border-t border-gray-100">
                                <i class="fas fa-sign-out-alt w-4 mr-2"></i> Sign out
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </header>

    <div class="flex h-[calc(100vh-64px)]">
        <aside id="sidebar" class="bg-white w-72 border-r border-gray-100 hidden lg:flex flex-col shadow-xl" style="min-height:0;">
            <div class="p-4 pb-2">
                <div class="flex items-center justify-between">
                    <div class="text-xs font-bold text-gray-500 uppercase tracking-wider">NAVIGATION</div>
                    <button id="collapseSidebarBtn" class="p-1 rounded-md text-gray-500 hover:bg-gray-100 focus:outline-none" title="Collapse sidebar">
                        <i class="fas fa-chevron-left w-4 h-4 transition-transform duration-200"></i>
                    </button>
                </div>
            </div>

            <nav class="px-3 pb-6 overflow-auto space-y-1 flex-1">
                <a href="dashboard_home.php" target="content_frame" class="sidebar-link active group flex items-center gap-3 py-2.5 px-3 rounded-lg text-sm text-gray-700">
                    <i class="fas fa-home w-5 h-5 text-blue-600 group-hover:text-blue-700"></i>
                    <span>Dashboard</span>
                </a>

                <div class="mt-4 pt-2 px-3 text-xs font-bold text-gray-500 uppercase tracking-wider border-t border-gray-100">Workflow</div>

                <?php if ($role == 'Initiator'): ?>
                    <a href="new_document.php" target="content_frame" class="sidebar-link group flex items-center gap-3 py-2.5 px-3 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-file-upload w-5 h-5 text-green-500 group-hover:text-green-600"></i>
                        <span>New Document</span>
                    </a>
                <?php endif; ?>

                <?php if (in_array($role, ['Section Chief', 'Division Chief', 'ARD', 'RED'])): ?>
                    <a href="my_queue.php" target="content_frame" class="sidebar-link group flex items-center gap-3 py-2.5 px-3 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-list-alt w-5 h-5 text-yellow-500 group-hover:text-yellow-600"></i>
                        <span>My Action Queue</span>
                    </a>
                <?php endif; ?>

                <?php if (in_array($role, ['Initiator', 'Section Chief'])): ?>
                    <a href="my_drafts.php" target="content_frame" class="sidebar-link group flex items-center gap-3 py-2.5 px-3 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-edit w-5 h-5 text-indigo-500 group-hover:text-indigo-600"></i>
                        <span>My Drafts</span>
                    </a>
                <?php endif; ?>

                <?php if (in_array($role, ['Section Chief', 'Division Chief'])): ?>
                    <a href="returned_docs.php" target="content_frame" class="sidebar-link group flex items-center gap-3 py-2.5 px-3 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-reply w-5 h-5 text-orange-500 group-hover:text-orange-600"></i>
                        <span>Returned Documents</span>
                    </a>
                <?php endif; ?>

                <div class="mt-4 pt-2 px-3 text-xs font-bold text-gray-500 uppercase tracking-wider border-t border-gray-100">Archive & Management</div>

                <?php if ($role == 'Records Office'): ?>
                    <a href="records_management.php" target="content_frame" class="sidebar-link group flex items-center gap-3 py-2.5 px-3 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-archive w-5 h-5 text-pink-500 group-hover:text-pink-600"></i>
                        <span>Records Management</span>
                    </a>
                <?php endif; ?>

                <a href="completed_docs.php" target="content_frame" class="sidebar-link group flex items-center gap-3 py-2.5 px-3 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                    <i class="fas fa-check-circle w-5 h-5 text-teal-500 group-hover:text-teal-600"></i>
                    <span>Completed</span>
                </a>

                <?php if ($role == 'Admin'): ?>
                    <div class="mt-4 pt-2 px-3 text-xs font-bold text-gray-500 uppercase tracking-wider border-t border-gray-100">Admin Tools</div>
                    
                    <a href="admin_users.php" target="content_frame" class="sidebar-link group flex items-center gap-3 py-2.5 px-3 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-users-cog w-5 h-5 text-gray-600 group-hover:text-gray-700"></i>
                        <span>User Management</span>
                    </a>

                    <a href="manageuser.php" target="content_frame" class="sidebar-link group flex items-center gap-3 py-2.5 px-3 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-user-edit w-5 h-5 text-gray-600 group-hover:text-gray-700"></i>
                        <span>Manage User</span>
                    </a>
                    <a href="admin_settings.php" target="content_frame" class="sidebar-link group flex items-center gap-3 py-2.5 px-3 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-cogs w-5 h-5 text-gray-600 group-hover:text-gray-700"></i>
                        <span>Settings</span>
                    </a>

                    <a href="signat_path.php" target="content_frame" class="sidebar-link group flex items-center gap-3 py-2.5 px-3 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-route w-5 h-5 text-gray-600 group-hover:text-gray-700"></i>
                        <span>Signatory Path</span>
                    </a>

                    <a href="office_stationManagement.php" target="content_frame" class="sidebar-link group flex items-center gap-3 py-2.5 px-3 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-building w-5 h-5 text-gray-600 group-hover:text-gray-700"></i>
                        <span>Office Station Management</span>
                    </a>

                <?php endif; ?>

            </nav>

            <div class="px-4 py-3 border-t border-gray-100 text-xs text-gray-500">
                <div class="flex items-center justify-between">
                    <div>User Role:</div>
                    <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($role); ?></div>
                </div>
            </div>
        </aside>

        <div id="mobileSidebar" class="fixed inset-y-0 left-0 z-50 w-72 transform -translate-x-full transition-transform duration-300 lg:hidden">
            <div class="h-full bg-white shadow-xl border-r overflow-auto">
                <div class="p-4 flex items-center justify-between border-b">
                    <div class="text-sm font-semibold text-gray-700">DDTMS Menu</div>
                    <button id="closeMobileSidebar" class="p-1 rounded-md hover:bg-gray-100 text-gray-600 focus:outline-none" title="Close">
                        <i class="fas fa-times w-5 h-5"></i>
                    </button>
                </div>
                <nav class="px-2 pb-6 space-y-1">
                    <a href="dashboard_home.php" target="content_frame" class="mobile-link block py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-50">Dashboard</a>
                    <div class="mt-3 px-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Workflow</div>
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
                    <div class="mt-3 px-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Archive & Management</div>
                    <?php if ($role == 'Records Office'): ?>
                        <a href="records_management.php" target="content_frame" class="mobile-link block py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-50">Records Management</a>
                    <?php endif; ?>
                    <a href="completed_docs.php" target="content_frame" class="mobile-link block py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-50">Completed</a>
                    <?php if ($role == 'Admin'): ?>
                        <div class="mt-3 px-3 text-xs font-bold text-gray-500 uppercase tracking-wider">Admin Tools</div>
                        <a href="admin_users.php" target="content_frame" class="mobile-link block py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-50">User Management</a>
                        
                        <a href="manageuser.php" target="content_frame" class="mobile-link block py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-50">Manage User</a>
                        <a href="admin_settings.php" target="content_frame" class="mobile-link block py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-50">Settings</a>
                        <a href="signat_path.php" target="content_frame" class="mobile-link block py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-50">Signatory Path</a>
                        <a href="office_stationManagement.php" target="content_frame" class="mobile-link block py-2 px-3 rounded-md text-sm text-gray-700 hover:bg-gray-50">Office Station Management</a>

                    <?php endif; ?>
                    <div class="mt-4 px-3 text-sm border-t pt-4">
                        
                        <a href="profile.php" target="content_frame" class="mobile-link block py-2 text-gray-700 hover:bg-gray-50 rounded-md">My Profile</a>
                        <a href="logout.php" class="block py-2 text-red-600 font-semibold hover:bg-red-50 rounded-md">Sign out</a>
                    </div>
                </nav>
            </div>
        </div>

        <div id="mobileBackdrop" class="fixed inset-0 bg-black bg-opacity-40 z-40 hidden lg:hidden"></div>

        <main id="mainContent" class="flex-1 h-full overflow-y-auto relative">
            <div id="iframeLoader" class="hidden absolute inset-0 flex flex-col items-center justify-center bg-white z-40 transition-opacity duration-300">
                <div class="animate-spin rounded-full h-10 w-10 border-4 border-gray-200 border-t-indigo-600"></div>
                <div class="text-sm font-medium text-gray-600 mt-3">Loading Content…</div>
            </div>
            <iframe name="content_frame" src="about:blank" frameborder="0" class="w-full h-full bg-white" aria-label="Content frame"></iframe>
        </main>
    </div>

    <script>
        // Global variables/helpers
        const sidebar = document.getElementById('sidebar');
        let collapsed = false;
        // --- New: Iframe and Loader references ---
        const iframe = document.querySelector('iframe[name="content_frame"]'); 
        const iframeLoader = document.getElementById('iframeLoader');
        // -----------------------------------------
        
        function clearActive() {
            document.querySelectorAll('.sidebar-link').forEach(l => {
                l.classList.remove('active');
                l.style.borderLeft = '4px solid transparent';
            });
            document.querySelectorAll('.mobile-link').forEach(l => l.classList.remove('active'));
        }

        function closeMobile() {
            mobileSidebar.classList.add('-translate-x-full');
            mobileBackdrop.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
            sidebarToggle.setAttribute('aria-expanded', 'false');
        }

        function updateHash(url) {
            const cleanUrl = url.split('?')[0].split('#')[0];
            const hash = `#page=${encodeURIComponent(cleanUrl)}`;
            // Use replaceState to avoid polluting browser history for every single navigation click
            window.history.replaceState(null, '', hash);
        }

        function setActiveLink(url) {
            clearActive();
            const cleanUrl = url.split('?')[0].split('#')[0];

            // Activate desktop link
            const desktopLink = document.querySelector(`.sidebar-link[href="${cleanUrl}"]`);
            if (desktopLink) {
                desktopLink.classList.add('active');
                if (!collapsed) {
                    desktopLink.style.borderLeft = '4px solid #3b82f6';
                }
            }
            // Activate mobile link
            const mobileLink = document.querySelector(`.mobile-link[href="${cleanUrl}"]`);
            if (mobileLink) {
                mobileLink.classList.add('active');
            }
        }
        
        // --- New: Loader functions ---
        function showIframeLoader() {
            if (iframeLoader) iframeLoader.classList.remove('hidden');
        }

        function hideIframeLoader() {
            if (iframeLoader) iframeLoader.classList.add('hidden');
        }
        // -----------------------------

        // --- Core Functions & Event Listeners ---
        
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

        if (sidebarToggle) sidebarToggle.addEventListener('click', openMobileSidebar);
        if (closeMobileSidebar) closeMobileSidebar.addEventListener('click', closeMobile);
        if (mobileBackdrop) mobileBackdrop.addEventListener('click', closeMobile);

        // Desktop collapse sidebar button 
        const collapseBtn = document.getElementById('collapseSidebarBtn');
        if (collapseBtn) {
            const collapseIcon = collapseBtn.querySelector('i');
            collapseBtn.addEventListener('click', () => {
                collapsed = !collapsed;
                if (collapsed) {
                    sidebar.classList.add('w-20');
                    sidebar.classList.remove('w-72');
                    collapseIcon.classList.replace('fa-chevron-left', 'fa-chevron-right');
                    document.querySelectorAll('#sidebar .sidebar-link span').forEach(s => s.classList.add('hidden'));
                    document.querySelectorAll('#sidebar .sidebar-link').forEach(l => {
                        l.classList.add('justify-center', 'px-0');
                        l.classList.remove('justify-start');
                        l.style.borderLeft = 'none'; // Clear border on collapse
                    });
                    document.querySelectorAll('#sidebar .sidebar-link i').forEach(i => i.classList.remove('w-5'));
                    document.querySelectorAll('#sidebar .font-bold.text-gray-500').forEach(t => t.classList.add('hidden'));
                    document.querySelector('#sidebar .border-t.text-xs').classList.add('hidden');
                } else {
                    sidebar.classList.remove('w-20');
                    sidebar.classList.add('w-72');
                    collapseIcon.classList.replace('fa-chevron-right', 'fa-chevron-left');
                    document.querySelectorAll('#sidebar .sidebar-link span').forEach(s => s.classList.remove('hidden'));
                    document.querySelectorAll('#sidebar .sidebar-link').forEach(l => {
                        l.classList.remove('justify-center', 'px-0');
                        l.classList.add('justify-start');
                    });
                    document.querySelectorAll('#sidebar .sidebar-link i').forEach(i => i.classList.add('w-5'));
                    document.querySelectorAll('#sidebar .sidebar-link.active').forEach(l => l.style.borderLeft = '4px solid #3b82f6');
                    document.querySelectorAll('#sidebar .font-bold.text-gray-500').forEach(t => t.classList.remove('hidden'));
                    document.querySelector('#sidebar .border-t.text-xs').classList.remove('hidden');
                }
            });
        }

        // --- Persistent Navigation Logic ---
        
        // Main function to handle link clicks and URL hash updates
        document.querySelectorAll('.sidebar-link, .mobile-link, .main-logo, .profile-link').forEach(link => {
            // Check if the link should be handled by the main iframe navigation
            const targetAttr = link.getAttribute('target');
            if (targetAttr === 'content_frame') {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const targetUrl = this.getAttribute('href');
                    const iframeWindow = window.frames['content_frame']; // Use iframeWindow for clarity
                    
                    if (iframeWindow && targetUrl) {
                        // 1. Show loader
                        showIframeLoader();

                        // 2. Load content into iframe
                        iframeWindow.location.replace(targetUrl);

                        // 3. Update parent URL hash for persistence
                        updateHash(targetUrl);
                        
                        // 4. Set active link
                        setActiveLink(targetUrl);
                    }
                    
                    // 5. Close mobile menu if applicable
                    if (this.classList.contains('mobile-link')) {
                        closeMobile();
                    }
                });
            }
        });
        
        document.addEventListener('DOMContentLoaded', () => {
            // iframe is defined globally
            const defaultPage = 'dashboard_home.php';
            let initialPage = defaultPage;

            // Check for hash value on load
            if (window.location.hash) {
                const hashMatch = window.location.hash.match(/#page=([^&]+)/);
                if (hashMatch) {
                    const decodedUrl = decodeURIComponent(hashMatch[1]);
                    // Only use hash if it points to a non-empty file
                    if (decodedUrl) {
                        initialPage = decodedUrl;
                    }
                }
            }

            // Set initial iframe source and active link
            if (iframe) {
                // Show loader for initial load
                showIframeLoader();
                iframe.src = initialPage;
            }
            setActiveLink(initialPage);
        });

        // --- New: Iframe Load Listener ---
        if (iframe) {
            iframe.addEventListener('load', hideIframeLoader);
        }
        // ---------------------------------


        // Notifications dropdown (existing logic preserved)
        const notifBtn = document.getElementById('notifBtn');
        const notifMenu = document.getElementById('notifMenu');
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

        // Profile dropdown (existing logic preserved)
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

        // Mark all notifications read (existing logic preserved)
        const markAllRead = document.getElementById('markAllRead');
        const notifBadge = document.getElementById('notifBadge');
        if (markAllRead && notifBadge) {
            markAllRead.addEventListener('click', (e) => {
                e.preventDefault();
                notifBadge.remove();
                // TODO: send AJAX to server to mark read
            });
        }

        // Search field: quick open suggestions (existing logic preserved, updated to use iframe.location.replace)
        const searchInput = document.getElementById('globalSearch');
        let searchTimer = null;
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    const q = this.value.trim();
                    if (q.length >= 3) {
                        const lq = q.toLowerCase();
                        let targetUrl = null;
                        if (lq.includes('queue')) {
                            targetUrl = 'my_queue.php';
                        } else if (lq.includes('draft')) {
                            targetUrl = 'my_drafts.php';
                        }
                        
                        if (targetUrl) {
                            showIframeLoader(); // <-- ADDED
                            window.frames['content_frame'].location.replace(targetUrl);
                            updateHash(targetUrl);
                            setActiveLink(targetUrl);
                            this.value = ''; // Clear search field
                        }
                    }
                }, 350);
            });
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const searchUrl = 'dashboard_home.php?search=' + encodeURIComponent(this.value);
                    
                    showIframeLoader(); // <-- ADDED
                    window.frames['content_frame'].location.replace(searchUrl);
                    updateHash(searchUrl);
                    setActiveLink('dashboard_home.php'); // Search results usually belong to the dashboard home context
                    this.value = ''; // Clear search field
                }
            });
        }

        // Keep iframe height synced (defensive, existing logic preserved)
        // iframe is defined globally
        function resizeFrame() {
            if (!iframe) return;
            // Calculate main content height (viewport height - header height)
            const headerHeight = document.querySelector('header').offsetHeight;
            const targetHeight = window.innerHeight - headerHeight;
            iframe.style.height = targetHeight + 'px';
            document.getElementById('mainContent').style.height = targetHeight + 'px';
        }
        window.addEventListener('resize', resizeFrame);
        window.addEventListener('load', resizeFrame);
        resizeFrame();

        // Accessibility: close with Escape (existing logic preserved)
        document.addEventListener('keydown', function(e){
            if (e.key === 'Escape') {
                if (!notifMenu.classList.contains('hidden')) notifMenu.classList.add('hidden');
                if (!profileMenu.classList.contains('hidden')) profileMenu.classList.add('hidden');
                if (!mobileBackdrop.classList.contains('hidden')) closeMobile();
            }
        });
        
        // Handle browser back/forward buttons (re-load content based on hash)
        window.addEventListener('hashchange', function() {
            const hashMatch = window.location.hash.match(/#page=([^&]+)/);
            if (hashMatch) {
                const targetUrl = decodeURIComponent(hashMatch[1]);
                const iframeWindow = window.frames['content_frame'];
                if (iframeWindow && iframeWindow.location.href.split('?')[0].split('#')[0] !== targetUrl.split('?')[0].split('#')[0]) {
                    showIframeLoader(); // <-- ADDED
                    iframeWindow.location.replace(targetUrl);
                    setActiveLink(targetUrl);
                }
            } else {
                // If hash is cleared, go to default page
                showIframeLoader(); // <-- ADDED
                window.frames['content_frame'].location.replace(defaultPage);
                setActiveLink(defaultPage);
            }
        });
        
    </script>

</body>
</html>