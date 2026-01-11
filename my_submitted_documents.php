<?php
session_start();

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
    $v_query = "SELECT * FROM documents WHERE doc_id = ? AND initiator_id = ?";
    $v_stmt = $conn->prepare($v_query);
    $v_stmt->bind_param("ii", $v_id, $user_id);
    $v_stmt->execute();
    $view_data = $v_stmt->get_result()->fetch_assoc();

    if ($view_data) {
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

// Fetch Main Table
$query = "SELECT d.*, 
          (SELECT message FROM document_actions 
           WHERE doc_id = d.doc_id 
           AND action = 'Returned Document' 
           ORDER BY created_at DESC LIMIT 1) AS return_message
          FROM documents d 
          WHERE d.initiator_id = ? 
          ORDER BY d.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Submissions | DDTMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .glass { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.3); }
        .modal-overlay { background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); }
        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        .document-card:hover { transform: translateY(-2px); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
    </style>
</head>
<body class="bg-[#f8fafc] text-slate-900 min-h-screen">

    <nav class="sticky top-0 z-40 w-full glass border-b border-slate-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <div class="flex items-center gap-3">
                    <div class="bg-indigo-600 p-2 rounded-lg shadow-indigo-200 shadow-lg">
                        <i class="fas fa-layer-group text-white text-lg"></i>
                    </div>
                    <span class="text-xl font-extrabold tracking-tight text-slate-800">DDTMS <span class="text-indigo-600">DENR CARAGA</span></span>
                </div>
                <div class="flex items-center gap-4">
                    <button onclick="location.href='new_document.php'" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded-xl font-bold text-sm transition-all shadow-md flex items-center gap-2">
                        <i class="fas fa-plus"></i> New Document
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="mb-10">
            <h1 class="text-3xl font-extrabold text-slate-900 tracking-tight">My Submissions</h1>
            <p class="text-slate-500 mt-2 font-medium">Track and manage your submitted document workflow and approvals.</p>
        </div>

        <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/50 border-b border-slate-100">
                            <th class="px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest text-center w-16">#</th>
                            <th class="px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest">Document Information</th>
                            <th class="px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest text-center">Current Status</th>
                            <th class="px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest">Submission Date</th>
                            <th class="px-6 py-4 text-[11px] font-bold text-slate-400 uppercase tracking-widest text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php if ($result->num_rows > 0): ?>
                            <?php $row_number = 1; ?>
                            <?php while ($row = $result->fetch_assoc()): 
                                $status = strtolower($row['status']);
                                $config = [
                                    'pending'   => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'border' => 'border-amber-200', 'icon' => 'fa-clock'],
                                    'approved'  => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'border' => 'border-emerald-200', 'icon' => 'fa-check-circle'],
                                    'completed' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'border' => 'border-emerald-200', 'icon' => 'fa-check-double'],
                                    'returned'  => ['bg' => 'bg-rose-50', 'text' => 'text-rose-700', 'border' => 'border-rose-200', 'icon' => 'fa-undo'],
                                    'rejected'  => ['bg' => 'bg-rose-50', 'text' => 'text-rose-700', 'border' => 'border-rose-200', 'icon' => 'fa-times-circle'],
                                    'forwarded' => ['bg' => 'bg-indigo-50', 'text' => 'text-indigo-700', 'border' => 'border-indigo-200', 'icon' => 'fa-paper-plane'],
                                ];
                                $c = $config[$status] ?? ['bg' => 'bg-slate-100', 'text' => 'text-slate-600', 'border' => 'border-slate-200', 'icon' => 'fa-file'];
                            ?>
                            <tr class="group hover:bg-slate-50/50 transition-all cursor-default">
                                <td class="px-6 py-5 text-center">
                                    <span class="text-sm font-bold text-slate-400"><?php echo $row_number; ?></span>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="flex items-center gap-4">
                                        <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-400 group-hover:bg-indigo-50 group-hover:text-indigo-600 transition-colors">
                                            <i class="fas fa-file-contract text-lg"></i>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-800 text-sm mb-0.5"><?php echo htmlspecialchars($row['title']); ?></div>
                                            <div class="text-[11px] font-bold text-slate-400 uppercase"><?php echo htmlspecialchars($row['doc_type']); ?></div>
                                        </div>
                                    </div>

                                    <?php if ($status == 'returned' && !empty($row['return_message'])): ?>
                                        <div class="mt-3 ml-14 bg-rose-50/50 border border-rose-100 rounded-xl p-3 flex gap-3 items-start animate-pulse">
                                            <i class="fas fa-exclamation-circle text-rose-500 mt-1"></i>
                                            <div>
                                                <div class="text-[10px] font-black text-rose-700 uppercase tracking-tighter">Return Action Required</div>
                                                <p class="text-xs text-rose-600 italic mt-0.5">"<?php echo htmlspecialchars($row['return_message']); ?>"</p>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-5 text-center">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full border text-[10px] font-black uppercase tracking-tight <?php echo $c['bg'] . ' ' . $c['text'] . ' ' . $c['border']; ?>">
                                        <i class="fas <?php echo $c['icon']; ?> opacity-70"></i>
                                        <?php echo $row['status']; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="text-sm font-semibold text-slate-600"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></div>
                                    <div class="text-[10px] text-slate-400 font-medium"><?php echo date('h:i A', strtotime($row['created_at'])); ?></div>
                                </td>
                                <td class="px-6 py-5 text-right">
                                    <div class="flex justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <a href="?view_id=<?php echo $row['doc_id']; ?>" class="h-9 px-4 bg-white border border-slate-200 text-slate-700 rounded-lg text-xs font-bold hover:bg-slate-50 transition flex items-center gap-2">
                                            <i class="fas fa-eye text-slate-400"></i> Details
                                        </a>
                                        <a href="?track_id=<?php echo $row['doc_id']; ?>" class="h-9 px-4 bg-white border border-slate-200 text-slate-700 rounded-lg text-xs font-bold hover:bg-slate-50 transition flex items-center gap-2">
                                            <i class="fas fa-route text-slate-400"></i> Track
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php $row_number++; ?>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="py-20 text-center">
                                    <img src="https://illustrations.popsy.co/slate/empty-folder.svg" class="w-48 mx-auto mb-6 opacity-40">
                                    <p class="text-slate-400 font-bold tracking-tight italic">No documents found in your history.</p>
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
                    <div class="w-12 h-12 rounded-2xl bg-indigo-500/20 flex items-center justify-center border border-indigo-400/30">
                        <i class="fas fa-folder-open text-indigo-400 text-xl"></i>
                    </div>
                    <div>
                        <h2 class="text-white font-black text-xl leading-none"><?php echo htmlspecialchars($view_data['title']); ?></h2>
                        <div class="flex items-center gap-3 mt-1.5">
                            <span class="text-[10px] font-bold text-indigo-300 bg-indigo-500/20 px-2 py-0.5 rounded-md uppercase tracking-widest border border-indigo-500/20">Ref: DOC-<?php echo $view_data['doc_id']; ?></span>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-widest italic"><?php echo $view_data['doc_type']; ?></span>
                        </div>
                    </div>
                </div>
                <a href="my_submitted_documents.php" class="bg-white/10 h-10 w-10 flex items-center justify-center rounded-full text-white hover:bg-rose-500 transition-all">
                    <i class="fas fa-times"></i>
                </a>
            </div>

            <div class="flex flex-col lg:flex-row flex-grow overflow-hidden">
                <div class="w-full lg:w-[400px] border-r border-slate-100 bg-slate-50/50 flex flex-col overflow-hidden">
                    <div class="flex-grow overflow-y-auto p-8 custom-scrollbar space-y-8">
                        <div>
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4 block">Attachment Workspace (<?php echo count($view_files); ?>)</label>
                            <div class="space-y-3">
                                <?php foreach ($view_files as $f): ?>
                                    <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm hover:border-indigo-400 transition-all group">
                                        <div class="flex items-center gap-3">
                                            <div class="bg-rose-50 text-rose-500 p-2.5 rounded-xl group-hover:bg-indigo-600 group-hover:text-white transition-colors">
                                                <i class="fas fa-file-pdf"></i>
                                            </div>
                                            <span class="text-xs font-bold text-slate-700 truncate"><?php echo htmlspecialchars($f['filename']); ?></span>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2 mt-4">
                                            <button onclick="previewDocument('<?php echo $f['filepath']; ?>')" class="py-2.5 bg-slate-900 text-white rounded-xl text-[10px] font-black uppercase tracking-widest hover:bg-indigo-600 transition shadow-lg shadow-indigo-100">
                                                <i class="fas fa-eye mr-1.5"></i> Preview
                                            </button>
                                            <a href="<?php echo $f['filepath']; ?>" download class="py-2.5 bg-slate-100 text-slate-600 rounded-xl text-[10px] font-black uppercase tracking-widest text-center hover:bg-slate-200 transition">
                                                <i class="fas fa-download mr-1.5"></i> Get File
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="pt-6 border-t border-slate-200">
                            <label class="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] mb-4 block">Document Details</label>
                            <div class="space-y-3">
                                <div class="flex justify-between items-center py-2 border-b border-slate-100">
                                    <span class="text-xs font-bold text-slate-400">Current Phase</span>
                                    <span class="text-xs font-black text-slate-700 uppercase"><?php echo $view_data['status']; ?></span>
                                </div>
                                <div class="flex justify-between items-center py-2 border-b border-slate-100">
                                    <span class="text-xs font-bold text-slate-400">Creation Date</span>
                                    <span class="text-xs font-black text-slate-700 uppercase"><?php echo date('M d, Y', strtotime($view_data['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex-grow bg-[#cbd5e1] flex items-center justify-center p-8 relative">
                    <div id="docPreviewContainer" class="w-full h-full bg-slate-200/50 rounded-[2rem] border-4 border-slate-300/30 flex items-center justify-center overflow-hidden shadow-inner">
                        <div id="noPreviewMsg" class="text-center p-10 max-w-sm">
                            <div class="w-24 h-24 bg-white/60 rounded-full flex items-center justify-center mx-auto mb-6 shadow-xl">
                                <i class="fas fa-mouse-pointer text-slate-400 text-3xl animate-bounce"></i>
                            </div>
                            <h3 class="text-slate-700 font-extrabold text-xl">Document Workspace</h3>
                            <p class="text-slate-500 text-sm mt-3 leading-relaxed">Select an attachment on the left panel to begin viewing or editing the document.</p>
                        </div>
                        <iframe id="docViewer" class="hidden w-full h-full" frameborder="0"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($track_data): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 modal-overlay">
        <div class="bg-white rounded-[2rem] shadow-2xl w-full max-w-md overflow-hidden border border-slate-200">
            <div class="px-6 py-5 bg-indigo-600 text-white flex justify-between items-center">
                <h3 class="font-black text-lg">Routing History</h3>
                <a href="my_submitted_documents.php" class="text-indigo-200 hover:text-white transition">
                    <i class="fas fa-times-circle text-2xl"></i>
                </a>
            </div>
            <div class="p-8 max-h-[70vh] overflow-y-auto custom-scrollbar bg-slate-50">
                <div class="relative border-l-2 border-indigo-100 ml-4 pl-8 space-y-10">
                    <?php while ($h = $track_history->fetch_assoc()): ?>
                        <div class="relative">
                            <div class="absolute -left-[41px] top-1 w-5 h-5 rounded-full bg-white border-4 border-indigo-600 shadow-md"></div>
                            <div class="text-[10px] font-black text-indigo-500 uppercase tracking-widest mb-1.5"><?php echo $h['action']; ?></div>
                            <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-200">
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fas fa-user-circle text-slate-300"></i>
                                    <span class="text-xs font-bold text-slate-700"><?php echo htmlspecialchars($h['full_name']); ?></span>
                                </div>
                                <?php if (!empty($h['message'])): ?>
                                    <div class="bg-amber-50 p-3 rounded-xl text-xs text-amber-800 italic leading-relaxed border-l-2 border-amber-300">
                                        "<?php echo htmlspecialchars($h['message']); ?>"
                                    </div>
                                <?php endif; ?>
                                <div class="mt-3 text-[10px] text-slate-400 font-bold uppercase tracking-tighter"><?php echo date('M d, Y • h:i A', strtotime($h['created_at'])); ?></div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <div class="p-5 border-t border-slate-100 text-center">
                <button onclick="location.href='my_submitted_documents.php'" class="text-[10px] font-black text-indigo-600 hover:text-indigo-800 uppercase tracking-[0.3em]">Return to Dashboard</button>
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
            container.classList.add('bg-white'); 
            
            if (['pdf', 'jpg', 'png', 'jpeg'].includes(ext)) {
                viewer.src = path;
            } else {
                viewer.classList.add('hidden');
                placeholder.classList.remove('hidden');
                container.classList.remove('bg-white');
                placeholder.innerHTML = `
                    <div class="bg-white p-12 rounded-[2.5rem] shadow-2xl text-center">
                        <div class="w-20 h-20 bg-indigo-50 text-indigo-500 rounded-3xl flex items-center justify-center mx-auto mb-6">
                            <i class="fas fa-file-export text-3xl"></i>
                        </div>
                        <h4 class="text-slate-800 font-black text-lg leading-tight uppercase tracking-tight">Preview Restricted</h4>
                        <p class="text-slate-500 text-sm mt-3 italic mb-8">The <b>.${ext.toUpperCase()}</b> format requires a local editor for proper viewing.</p>
                        <a href="${path}" download class="inline-flex items-center gap-2 px-8 py-3 bg-slate-900 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest shadow-xl hover:bg-indigo-600 transition">
                            <i class="fas fa-download"></i> Download Locally
                        </a>
                    </div>
                `;
            }
        }
    </script>
</body>
</html>