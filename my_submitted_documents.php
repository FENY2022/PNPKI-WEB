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
        $view_files = $f_stmt->get_result();
    }
}

// --- START: TRACK DOCUMENT LOGIC ---
$track_data = null;
$track_history = [];
if (isset($_GET['track_id'])) {
    $t_id = intval($_GET['track_id']);
    
    // Check ownership/access
    $t_doc_query = "SELECT title, status FROM documents WHERE doc_id = ? AND initiator_id = ?";
    $t_doc_stmt = $conn->prepare($t_doc_query);
    $t_doc_stmt->bind_param("ii", $t_id, $user_id);
    $t_doc_stmt->execute();
    $track_data = $t_doc_stmt->get_result()->fetch_assoc();

    if ($track_data) {
        // Fetch action history joined with user names
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

// 2. Fetch All Documents for the Table
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
        .modal-bg { background: rgba(0, 0, 0, 0.5); }
    </style>
</head>
<body class="bg-gray-100">

    <div class="flex h-screen overflow-hidden">
        <div class="relative flex flex-col flex-1 overflow-y-auto overflow-x-hidden">
            
            <header class="bg-white shadow-sm sticky top-0 z-30">
                <div class="px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
                    <h1 class="text-2xl font-bold text-gray-800">My Submitted Documents</h1>
                </div>
            </header>

            <main class="w-full flex-grow p-6">
                <div class="bg-white shadow rounded-lg overflow-hidden">
                    <?php if ($result->num_rows > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase w-10">#</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Title</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date Submitted</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php 
                                    $count = 1;
                                    while ($row = $result->fetch_assoc()): 
                                        // LOGIC FOR COLOR CODING
                                        $status = strtolower($row['status']);
                                        $statusClasses = '';
                                        $icon = 'fa-circle';

                                        switch ($status) {
                                            case 'pending':
                                                $statusClasses = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                                                break;
                                            case 'approved':
                                            case 'completed':
                                            case 'signed':
                                                $statusClasses = 'bg-green-100 text-green-800 border-green-200';
                                                $icon = 'fa-check-circle';
                                                break;
                                            case 'rejected':
                                            case 'denied':
                                            case 'returned':
                                                $statusClasses = 'bg-red-100 text-red-800 border-red-200';
                                                $icon = 'fa-times-circle';
                                                break;
                                            case 'forwarded':
                                            case 'received':
                                            case 'in transit':
                                                $statusClasses = 'bg-blue-100 text-blue-800 border-blue-200';
                                                $icon = 'fa-arrow-right';
                                                break;
                                            default:
                                                $statusClasses = 'bg-gray-100 text-gray-800 border-gray-200';
                                                break;
                                        }
                                    ?>
                                        <tr>
                                            <td class="px-6 py-4 text-sm text-gray-500"><?php echo $count++; ?></td>
                                            <td class="px-6 py-4 font-medium text-gray-900"><?php echo htmlspecialchars($row['title']); ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($row['doc_type']); ?></td>
                                            <td class="px-6 py-4">
                                                <span class="px-3 py-1 inline-flex text-[10px] leading-5 font-bold rounded-full border shadow-sm <?php echo $statusClasses; ?>">
                                                    <i class="fas <?php echo $icon; ?> mr-1.5 self-center"></i>
                                                    <?php echo strtoupper(htmlspecialchars($row['status'])); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-500"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                            <td class="px-6 py-4 text-sm font-medium">
                                                <a href="?view_id=<?php echo $row['doc_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <a href="?track_id=<?php echo $row['doc_id']; ?>" class="text-indigo-600 hover:text-indigo-900">
                                                    <i class="fas fa-history"></i> Track
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-6 text-center text-gray-500">No documents found.</div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <?php if ($view_data): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 modal-bg">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl">
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-800">Document Details</h3>
                <a href="my_submitted_documents.php" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </a>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs text-gray-500 uppercase font-bold">Title</p>
                        <p class="text-gray-900"><?php echo htmlspecialchars($view_data['title']); ?></p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 uppercase font-bold">Type</p>
                        <p class="text-gray-900"><?php echo htmlspecialchars($view_data['doc_type']); ?></p>
                    </div>
                </div>
                <div class="border-t pt-4">
                    <p class="text-xs text-gray-500 uppercase font-bold mb-3">Attached Files</p>
                    <div class="space-y-2">
                        <?php while ($f = $view_files->fetch_assoc()): ?>
                            <div class="flex justify-between items-center p-2 bg-gray-50 rounded border border-gray-100">
                                <span class="text-sm text-gray-700 font-medium">
                                    <i class="far fa-file-alt mr-2 text-indigo-500"></i>
                                    <?php echo htmlspecialchars($f['filename']); ?>
                                </span>
                                <a href="<?php echo $f['filepath']; ?>" target="_blank" class="text-indigo-600 hover:text-indigo-800 text-xs font-bold bg-white px-3 py-1 rounded shadow-sm border border-gray-200 transition">
                                    <i class="fas fa-download mr-1"></i> Download
                                </a>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
            <div class="p-4 bg-gray-50 text-right rounded-b-lg">
                <a href="my_submitted_documents.php" class="px-6 py-2 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded text-sm font-bold transition">
                    Close
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($track_data): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 modal-bg">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg">
            <div class="px-6 py-4 border-b flex justify-between items-center bg-indigo-600 text-white rounded-t-lg">
                <h3 class="text-lg font-bold">Tracking: <?php echo htmlspecialchars($track_data['title']); ?></h3>
                <a href="my_submitted_documents.php" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-xl"></i>
                </a>
            </div>
            <div class="p-6 max-h-[70vh] overflow-y-auto bg-gray-50">
                <?php if ($track_history->num_rows > 0): ?>
                    <div class="relative border-l-2 border-indigo-200 ml-3 space-y-8">
                        <?php while ($h = $track_history->fetch_assoc()): ?>
                            <div class="relative pl-8">
                                <div class="absolute -left-[9px] top-1 w-4 h-4 rounded-full bg-indigo-500 border-2 border-white shadow-sm"></div>
                                
                                <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200">
                                    <div class="flex justify-between items-start mb-2">
                                        <span class="text-[10px] font-black uppercase tracking-widest text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded">
                                            <?php echo htmlspecialchars($h['action']); ?>
                                        </span>
                                        <span class="text-[10px] text-gray-400 font-medium">
                                            <?php echo date('M d, Y h:i A', strtotime($h['created_at'])); ?>
                                        </span>
                                    </div>
                                    <p class="text-sm font-bold text-gray-800 mb-1">
                                        <i class="fas fa-user-circle text-gray-400 mr-1"></i>
                                        <?php echo htmlspecialchars($h['full_name']); ?>
                                    </p>
                                    <?php if (!empty($h['remarks'])): ?>
                                        <div class="mt-2 p-2 bg-amber-50 border-l-4 border-amber-200 rounded text-[11px] text-amber-900 italic">
                                            "<?php echo htmlspecialchars($h['remarks']); ?>"
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="py-10 text-center">
                        <i class="fas fa-search-location text-gray-300 text-4xl mb-3"></i>
                        <p class="text-gray-500 italic">No tracking history found for this document.</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="p-4 bg-gray-50 border-t text-right rounded-b-lg">
                <a href="my_submitted_documents.php" class="inline-block px-6 py-2 bg-indigo-600 text-white rounded text-sm font-bold hover:bg-indigo-700 transition shadow-md">
                    Done
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

</body>
</html>