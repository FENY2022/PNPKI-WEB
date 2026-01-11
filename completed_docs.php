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
    // Verify ownership/participation and fetch document details
    $v_query = "SELECT * FROM documents WHERE doc_id = ? AND (initiator_id = ? OR current_owner_id = ?)";
    $v_stmt = $conn->prepare($v_query);
    $v_stmt->bind_param("iii", $v_id, $user_id, $user_id);
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
    $t_doc_query = "SELECT title, status FROM documents WHERE doc_id = ? AND (initiator_id = ? OR current_owner_id = ?)";
    $t_doc_stmt = $conn->prepare($t_doc_query);
    $t_doc_stmt->bind_param("iii", $t_id, $user_id, $user_id);
    $t_doc_stmt->execute();
    $track_data = $t_doc_stmt->get_result()->fetch_assoc();

    if ($track_data) {
        $h_query = "SELECT da.*, CONCAT(u.first_name, ' ', u.last_name) AS full_name 
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

// 2. Fetch Completed Documents specifically
$query = "SELECT * FROM documents 
          WHERE initiator_id = ? AND status = 'Completed' 
          ORDER BY updated_at DESC";
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
    <title>Completed Archives | DDTMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.3); }
        .modal-overlay { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); }
        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-900 min-h-screen">

    <nav class="sticky top-0 z-40 w-full glass border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center gap-3">
                    <div class="bg-emerald-600 p-2 rounded-lg shadow-emerald-200 shadow-lg">
                        <i class="fas fa-check-double text-white text-lg"></i>
                    </div>
                    <span class="text-xl font-extrabold tracking-tight text-slate-800">DDTMS <span class="text-emerald-600">ARCHIVES</span></span>
                </div>

            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">Completed Documents</h1>
                <p class="text-slate-500 mt-2 font-medium">Viewing your finalized records and legally signed documents.</p>
            </div>
            <div class="flex items-center bg-white px-4 py-2 rounded-2xl border border-slate-200 shadow-sm">
                <i class="fas fa-search text-slate-300 mr-3"></i>
                <input type="text" id="tableSearch" placeholder="Search archive..." class="bg-transparent border-none outline-none text-sm font-medium text-slate-600 w-48">
            </div>
        </div>

        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse" id="completedTable">
                    <thead>
                        <tr class="bg-slate-50/50 border-b border-slate-100">
                            <th class="px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest">Document</th>
                            <th class="px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest">Type</th>
                            <th class="px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest text-center">Status</th>
                            <th class="px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest">Completion Date</th>
                            <th class="px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest text-right">Options</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if ($result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                            <tr class="group hover:bg-emerald-50/30 transition-all">
                                <td class="px-6 py-5">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center text-emerald-600">
                                            <i class="fas fa-file-signature text-lg"></i>
                                        </div>
                                        <div class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($row['title']); ?></div>
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <span class="text-xs font-bold text-slate-500 uppercase tracking-wider"><?php echo htmlspecialchars($row['doc_type']); ?></span>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100 text-[10px] font-black uppercase tracking-tight">
                                        <i class="fas fa-check-circle"></i> Completed
                                    </span>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="text-sm font-semibold text-slate-600"><?php echo date('M d, Y', strtotime($row['updated_at'])); ?></div>
                                </td>
                                <td class="px-6 py-5 text-right">
                                    <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <a href="?view_id=<?php echo $row['doc_id']; ?>" class="h-9 px-4 bg-white border border-slate-200 text-slate-700 rounded-lg text-xs font-bold hover:bg-slate-50 transition flex items-center gap-2">
                                            <i class="fas fa-folder-open text-emerald-500"></i> View
                                        </a>
                                        <a href="?track_id=<?php echo $row['doc_id']; ?>" class="h-9 px-4 bg-white border border-slate-200 text-slate-700 rounded-lg text-xs font-bold hover:bg-slate-50 transition flex items-center gap-2">
                                            <i class="fas fa-history text-slate-400"></i> Log
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="py-24 text-center">
                                    <div class="bg-slate-50 w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-6">
                                        <i class="fas fa-archive text-slate-200 text-4xl"></i>
                                    </div>
                                    <h3 class="text-slate-400 font-bold text-lg italic">The archive is currently empty.</h3>
                                    <p class="text-slate-400 text-sm mt-1">Once documents are fully signed, they will appear here.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <?php if ($view_data): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 md:p-10 modal-overlay overflow-hidden">
        <div class="bg-white rounded-[2.5rem] shadow-2xl w-full max-w-7xl h-full flex flex-col overflow-hidden border border-white/40">
            <div class="px-8 py-6 bg-slate-900 flex justify-between items-center">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 flex items-center justify-center border border-emerald-400/30">
                        <i class="fas fa-shield-check text-emerald-400 text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-white font-black text-xl leading-none"><?php echo htmlspecialchars($view_data['title']); ?></h2>
                        <div class="flex items-center gap-3 mt-1.5">
                            <span class="text-[10px] font-bold text-emerald-300 bg-emerald-500/20 px-2 py-0.5 rounded-md uppercase tracking-widest border border-emerald-500/20">Finalized Archive</span>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest italic"><?php echo $view_data['doc_type']; ?></span>
                        </div>
                    </div>
                </div>
                <a href="completed_docs.php" class="bg-white/10 h-10 w-10 flex items-center justify-center rounded-full text-white hover:bg-rose-500 transition-all">
                    <i class="fas fa-times"></i>
                </a>
            </div>

            <div class="flex flex-col lg:flex-row flex-grow overflow-hidden">
                <div class="w-full lg:w-[400px] border-r border-slate-100 bg-slate-50/50 flex flex-col overflow-hidden">
                    <div class="flex-grow overflow-y-auto p-8 custom-scrollbar">
                        <label class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4 block">Final Attachments</label>
                        <div class="space-y-3">
                            <?php foreach ($view_files as $f): ?>
                                <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm hover:border-emerald-400 transition-all group">
                                    <div class="flex items-center gap-3">
                                        <div class="bg-rose-50 text-rose-500 p-2.5 rounded-xl group-hover:bg-emerald-600 group-hover:text-white transition-colors">
                                            <i class="fas fa-file-pdf"></i>
                                        </div>
                                        <span class="text-xs font-bold text-slate-700 truncate"><?php echo htmlspecialchars($f['filename']); ?></span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 mt-4">
                                        <button onclick="previewDocument('<?php echo $f['filepath']; ?>')" class="py-2.5 bg-slate-900 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-emerald-600 transition shadow-lg shadow-emerald-100">
                                            Preview
                                        </button>
                                        <a href="<?php echo $f['filepath']; ?>" download class="py-2.5 bg-slate-100 text-slate-600 rounded-xl text-[10px] font-black uppercase tracking-widest text-center hover:bg-slate-200 transition">
                                            Download
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="flex-grow bg-[#cbd5e1] flex items-center justify-center p-8 relative">
                    <div id="docPreviewContainer" class="w-full h-full bg-slate-200/50 rounded-[2rem] border-4 border-slate-300/30 flex items-center justify-center overflow-hidden">
                        <div id="noPreviewMsg" class="text-center p-10">
                            <i class="fas fa-eye text-slate-400 text-5xl mb-4"></i>
                            <h3 class="text-slate-700 font-extrabold text-xl">Archive Viewer</h3>
                            <p class="text-slate-500 text-sm mt-3 leading-relaxed">Select a file to view the finalized signed version.</p>
                        </div>
                        <iframe id="docViewer" class="hidden w-full h-full" frameborder="0"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        // Search Functionality
        document.getElementById('tableSearch').addEventListener('keyup', function() {
            let filter = this.value.toUpperCase();
            let rows = document.querySelector("#completedTable tbody").rows;
            for (let i = 0; i < rows.length; i++) {
                let firstCol = rows[i].cells[0].textContent.toUpperCase();
                let secondCol = rows[i].cells[1].textContent.toUpperCase();
                if (firstCol.indexOf(filter) > -1 || secondCol.indexOf(filter) > -1) {
                    rows[i].style.display = "";
                } else {
                    rows[i].style.display = "none";
                }      
            }
        });

        function previewDocument(path) {
            const viewer = document.getElementById('docViewer');
            const placeholder = document.getElementById('noPreviewMsg');
            const container = document.getElementById('docPreviewContainer');
            const ext = path.split('.').pop().toLowerCase();
            
            placeholder.classList.add('hidden');
            viewer.classList.remove('hidden');
            container.classList.add('bg-white'); 
            
            if (['pdf', 'jpg', 'png', 'jpeg'].includes(ext)) {
                viewer.src = path;
            } else {
                viewer.classList.add('hidden');
                placeholder.classList.remove('hidden');
                container.classList.remove('bg-white');
                placeholder.innerHTML = `<div class='p-10 text-center'><i class='fas fa-file-alt text-4xl mb-4'></i><br>Preview not available for this format.</div>`;
            }
        }
    </script>
</body>
</html>