<?php
session_start();

// 1. Authentication Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'db.php';
$user_id = $_SESSION['user_id'];

// --- START: VIEW DOCUMENT LOGIC ---
$view_data = null;
$view_files = [];
if (isset($_GET['view_id'])) {
    $v_id = intval($_GET['view_id']);
    // Verify ownership and fetch document details
    $v_query = "SELECT * FROM documents WHERE doc_id = ? AND initiator_id = ?";
    $v_stmt = $conn->prepare($v_query);
    $v_stmt->bind_param("ii", $v_id, $user_id);
    $v_stmt->execute();
    $view_data = $v_stmt->get_result()->fetch_assoc();

    if ($view_data) {
        // Fetch attached files
        $f_query = "SELECT * FROM document_files WHERE doc_id = ?";
        $f_stmt = $conn->prepare($f_query);
        $f_stmt->bind_param("i", $v_id);
        $f_stmt->execute();
        $view_files_result = $f_stmt->get_result();
        while($f = $view_files_result->fetch_assoc()) {
            $view_files[] = $f;
        }
    }
}

// --- START: TRACK DOCUMENT LOGIC ---
$track_data = null;
$track_history = [];
if (isset($_GET['track_id'])) {
    $t_id = intval($_GET['track_id']);
    
    $t_doc_query = "SELECT title, status FROM documents WHERE doc_id = ? AND initiator_id = ?";
    $t_doc_stmt = $conn->prepare($t_doc_query);
    $t_doc_stmt->bind_param("ii", $t_id, $user_id);
    $t_doc_stmt->execute();
    $track_data = $t_doc_stmt->get_result()->fetch_assoc();

    if ($track_data) {
        $h_query = "SELECT da.*, CONCAT(u.first_name, ' ', COALESCE(u.middle_name, ''), ' ', u.last_name) AS full_name 
                    FROM document_actions da 
                    JOIN users u ON da.user_id = u.user_id 
                    WHERE da.doc_id = ? 
                    ORDER BY da.created_at DESC";
        $h_stmt = $conn->prepare($h_query);
        $h_stmt->bind_param("i", $t_id);
        $h_stmt->execute();
        $track_history = $h_stmt->get_result();
    }
}

// 2. Fetch All Documents for the Main Table
$query = "SELECT * FROM documents WHERE initiator_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Submitted Documents - DDTMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .modal-bg { background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(8px); }
        #docPreviewContainer { transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="bg-slate-50 font-sans text-slate-900">

    <div class="flex h-screen overflow-hidden">
        <div class="relative flex flex-col flex-1 overflow-y-auto overflow-x-hidden">
            
            <header class="bg-white/80 backdrop-blur-md shadow-sm sticky top-0 z-30 border-b border-slate-200">
                <div class="px-6 py-5 flex justify-between items-center">
                    <h1 class="text-2xl font-black text-slate-800 tracking-tight">
                        <i class="fas fa-folder-tree text-indigo-600 mr-2"></i>My Submissions
                    </h1>
                </div>
            </header>

            <main class="w-full flex-grow p-8">
                <div class="bg-white shadow-xl shadow-slate-200/50 rounded-2xl overflow-hidden border border-slate-200">
                    <?php if ($result->num_rows > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-widest">Document Title</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-widest">Classification</th>
                                        <th class="px-6 py-4 text-center text-xs font-bold text-slate-400 uppercase tracking-widest">Status</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-400 uppercase tracking-widest">Submitted On</th>
                                        <th class="px-6 py-4 text-right text-xs font-bold text-slate-400 uppercase tracking-widest">Options</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-slate-100">
                                    <?php while ($row = $result->fetch_assoc()): 
                                        $status = strtolower($row['status']);
                                        $badgeClass = 'bg-slate-100 text-slate-600 border-slate-200';
                                        $icon = 'fa-circle';

                                        switch ($status) {
                                            case 'pending':
                                                $badgeClass = 'bg-amber-50 text-amber-700 border-amber-200'; break;
                                            case 'approved': case 'completed': case 'signed':
                                                $badgeClass = 'bg-emerald-50 text-emerald-700 border-emerald-200'; $icon = 'fa-check-double'; break;
                                            case 'rejected': case 'returned':
                                                $badgeClass = 'bg-rose-50 text-rose-700 border-rose-200'; $icon = 'fa-exclamation-triangle'; break;
                                            case 'forwarded': case 'received':
                                                $badgeClass = 'bg-indigo-50 text-indigo-700 border-indigo-200'; $icon = 'fa-share-all'; break;
                                        }
                                    ?>
                                        <tr class="hover:bg-slate-50/80 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap font-bold text-slate-700"><?php echo htmlspecialchars($row['title']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500"><?php echo htmlspecialchars($row['doc_type']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <span class="px-3 py-1.5 rounded-lg border text-[11px] font-bold uppercase tracking-tight <?php echo $badgeClass; ?>">
                                                    <i class="fas <?php echo $icon; ?> mr-1.5 opacity-70"></i><?php echo $row['status']; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <div class="flex justify-end space-x-2">
                                                    <a href="?view_id=<?php echo $row['doc_id']; ?>" class="flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition shadow-sm">
                                                        <i class="fas fa-file-invoice mr-2 text-indigo-200"></i> Details
                                                    </a>
                                                    <a href="?track_id=<?php echo $row['doc_id']; ?>" class="flex items-center px-4 py-2 bg-white border border-slate-200 text-slate-600 rounded-lg hover:bg-slate-50 transition shadow-sm">
                                                        <i class="fas fa-history mr-2 text-slate-400"></i> Track
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-20 text-center">
                            <div class="bg-slate-50 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-inbox text-slate-300 text-3xl"></i>
                            </div>
                            <p class="text-slate-500 text-lg font-semibold italic">No documents found in your history.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <?php if ($view_data): ?>
    <div id="viewModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 md:p-10 modal-bg">
        <div class="bg-white rounded-3xl shadow-2xl w-full w-11/12 max-w-7xl flex flex-col overflow-hidden h-full max-h-[95vh] border border-white/20">
            
            <div class="px-8 py-6 border-b bg-slate-900 text-white flex justify-between items-center">
                <div class="flex items-center space-x-4">
                    <div class="bg-indigo-500 p-3 rounded-2xl">
                        <i class="fas fa-file-alt text-xl text-white"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-black leading-none tracking-tight">Document Explorer</h3>
                        <p class="text-xs text-indigo-300 mt-1 uppercase font-bold tracking-widest opacity-80"><?php echo htmlspecialchars($view_data['title']); ?></p>
                    </div>
                </div>
                <a href="my_submitted_documents.php" class="bg-white/10 p-2 rounded-full hover:bg-rose-500 transition group">
                    <i class="fas fa-times text-xl text-white group-hover:scale-90 transition"></i>
                </a>
            </div>

            <div class="flex flex-col lg:flex-row flex-grow overflow-hidden">
                <div class="w-full lg:w-[350px] p-8 border-r bg-slate-50/50 overflow-y-auto custom-scrollbar space-y-8">
                    <div>
                        <h4 class="text-[11px] font-black text-indigo-600 uppercase tracking-[0.2em] mb-4 flex items-center">
                            <span class="w-2 h-2 bg-indigo-500 rounded-full mr-2"></span> Overview
                        </h4>
                        <div class="space-y-5">
                            <div class="flex flex-col p-4 bg-white rounded-2xl shadow-sm border border-slate-100">
                                <span class="text-[10px] text-slate-400 font-bold uppercase mb-1">Type</span>
                                <span class="text-sm font-black text-slate-700"><?php echo htmlspecialchars($view_data['doc_type']); ?></span>
                            </div>
                            <div class="flex flex-col p-4 bg-white rounded-2xl shadow-sm border border-slate-100">
                                <span class="text-[10px] text-slate-400 font-bold uppercase mb-1">Status</span>
                                <span class="text-sm font-black text-indigo-600"><?php echo strtoupper($view_data['status']); ?></span>
                            </div>
                            <div class="flex flex-col p-4 bg-white rounded-2xl shadow-sm border border-slate-100">
                                <span class="text-[10px] text-slate-400 font-bold uppercase mb-1">Logged Date</span>
                                <span class="text-sm font-black text-slate-700"><?php echo date('F d, Y h:i A', strtotime($view_data['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-[11px] font-black text-indigo-600 uppercase tracking-[0.2em] mb-4 flex items-center">
                            <span class="w-2 h-2 bg-indigo-500 rounded-full mr-2"></span> Files (<?php echo count($view_files); ?>)
                        </h4>
                        <div class="space-y-3">
                            <?php foreach ($view_files as $f): ?>
                                <div class="p-4 bg-white border border-slate-100 rounded-2xl hover:border-indigo-400 transition-all cursor-default group shadow-sm">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center overflow-hidden mr-2">
                                            <i class="fas fa-file-pdf text-rose-500 text-xl mr-3"></i>
                                            <span class="text-xs font-bold text-slate-700 truncate"><?php echo htmlspecialchars($f['filename']); ?></span>
                                        </div>
                                    </div>
                                    <div class="flex mt-3 space-x-2">
                                        <button onclick="previewDocument('<?php echo $f['filepath']; ?>')" class="flex-1 py-2 bg-slate-900 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-600 transition shadow-lg shadow-indigo-200">
                                            <i class="fas fa-eye mr-1"></i> Open Online
                                        </button>
                                        <a href="<?php echo $f['filepath']; ?>" download class="px-3 py-2 bg-slate-100 text-slate-600 rounded-xl hover:bg-slate-200 transition">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="w-full lg:flex-grow bg-slate-200 flex items-center justify-center p-6 relative">
                    <div id="docPreviewContainer" class="w-full h-full bg-slate-300 rounded-2xl shadow-2xl border border-slate-400/30 overflow-hidden flex items-center justify-center">
                        <div id="noPreviewMsg" class="text-center p-12">
                            <div class="w-24 h-24 bg-white/50 rounded-full flex items-center justify-center mx-auto mb-6">
                                <i class="fas fa-mouse-pointer text-slate-400 text-4xl animate-bounce"></i>
                            </div>
                            <h5 class="text-slate-600 font-black text-xl tracking-tight">Ready for Preview</h5>
                            <p class="text-slate-500 text-sm mt-2 max-w-[250px] mx-auto">Click <span class="font-bold text-indigo-600">Open Online</span> on any attachment to view it here.</p>
                        </div>
                        <iframe id="docViewer" class="hidden w-full h-full" frameborder="0"></iframe>
                    </div>
                </div>
            </div>

            <div class="px-8 py-5 bg-slate-50 border-t flex justify-between items-center">
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest italic">Digital Document Tracking & Management System</p>
                <a href="my_submitted_documents.php" class="px-8 py-2.5 bg-slate-900 text-white rounded-xl text-xs font-black uppercase tracking-widest hover:bg-indigo-600 transition shadow-xl shadow-indigo-100">
                    Close Window
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($track_data): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 modal-bg">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden border border-slate-200">
            <div class="px-6 py-5 border-b bg-indigo-600 text-white flex justify-between items-center">
                <h3 class="text-lg font-black tracking-tight">Timeline History</h3>
                <a href="my_submitted_documents.php" class="text-indigo-200 hover:text-white transition">
                    <i class="fas fa-times-circle text-2xl"></i>
                </a>
            </div>
            <div class="p-8 max-h-[60vh] overflow-y-auto custom-scrollbar bg-slate-50/30">
                <div class="relative border-l-4 border-indigo-100 ml-4 space-y-8">
                    <?php while ($h = $track_history->fetch_assoc()): ?>
                        <div class="relative pl-10">
                            <div class="absolute -left-[14px] top-1 w-6 h-6 rounded-full bg-white border-4 border-indigo-500 shadow-sm flex items-center justify-center">
                                <div class="w-1.5 h-1.5 bg-indigo-500 rounded-full"></div>
                            </div>
                            <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 hover:shadow-md transition-shadow">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-[10px] font-black uppercase text-indigo-500 bg-indigo-50 px-2 py-1 rounded-md"><?php echo $h['action']; ?></span>
                                    <span class="text-[10px] text-slate-400 font-bold"><?php echo date('M d, h:i A', strtotime($h['created_at'])); ?></span>
                                </div>
                                <p class="text-sm font-black text-slate-800 flex items-center">
                                    <i class="fas fa-user-circle text-slate-300 mr-2"></i><?php echo htmlspecialchars($h['full_name']); ?>
                                </p>
                                <?php if (!empty($h['remarks'])): ?>
                                    <div class="mt-3 p-3 bg-amber-50 border-l-4 border-amber-300 rounded-r-xl text-xs text-amber-900 italic font-medium leading-relaxed">
                                        "<?php echo htmlspecialchars($h['remarks']); ?>"
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <div class="p-6 bg-slate-100 border-t text-center">
                <a href="my_submitted_documents.php" class="text-xs font-black text-indigo-600 hover:text-indigo-800 uppercase tracking-widest">Done Reading</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function previewDocument(path) {
            const viewer = document.getElementById('docViewer');
            const placeholder = document.getElementById('noPreviewMsg');
            const container = document.getElementById('docPreviewContainer');
            
            const ext = path.split('.').pop().toLowerCase();
            
            placeholder.classList.add('hidden');
            viewer.classList.remove('hidden');
            container.classList.add('bg-white'); // Clear background for document
            
            // Native viewing for common web types
            if (['pdf', 'jpg', 'png', 'jpeg'].includes(ext)) {
                viewer.src = path;
            } else {
                viewer.classList.add('hidden');
                placeholder.classList.remove('hidden');
                container.classList.remove('bg-white');
                placeholder.innerHTML = `
                    <div class="bg-white/80 p-10 rounded-3xl shadow-xl">
                        <i class="fas fa-file-word text-indigo-400 text-6xl mb-6"></i>
                        <h5 class="text-slate-800 font-black text-lg">Format Not Supported Online</h5>
                        <p class="text-slate-500 text-sm mt-2">The <b>.${ext.toUpperCase()}</b> format can't be previewed online yet.</p>
                        <a href="${path}" download class="inline-block mt-6 px-6 py-2 bg-indigo-600 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-200">Download to View</a>
                    </div>
                `;
            }
        }
    </script>

</body>
</html>