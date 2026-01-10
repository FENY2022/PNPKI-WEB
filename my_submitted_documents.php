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
                    <a href="dashboard.php" class="text-blue-600 hover:text-blue-800">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
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
                                    ?>
                                        <tr>
                                            <td class="px-6 py-4 text-sm text-gray-500"><?php echo $count++; ?></td>
                                            <td class="px-6 py-4 font-medium text-gray-900"><?php echo htmlspecialchars($row['title']); ?></td>
                                            <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($row['doc_type']); ?></td>
                                            <td class="px-6 py-4">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                                    <?php echo $row['status']; ?>
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
                <h3 class="text-xl font-bold">Document Details</h3>
                <a href="my_submitted_documents.php"><i class="fas fa-times"></i></a>
            </div>
            <div class="p-6 space-y-4">
                <p><strong>Title:</strong> <?php echo htmlspecialchars($view_data['title']); ?></p>
                <p><strong>Type:</strong> <?php echo htmlspecialchars($view_data['doc_type']); ?></p>
                <div class="border-t pt-4">
                    <p class="font-bold mb-2">Files:</p>
                    <?php while ($f = $view_files->fetch_assoc()): ?>
                        <div class="flex justify-between py-1 text-sm">
                            <span><?php echo htmlspecialchars($f['filename']); ?></span>
                            <a href="<?php echo $f['filepath']; ?>" target="_blank" class="text-blue-600 underline">Download</a>
                        </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <div class="p-4 bg-gray-50 text-right rounded-b-lg">
                <a href="my_submitted_documents.php" class="px-4 py-2 bg-gray-300 rounded text-sm">Close</a>
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
            <div class="p-6 max-h-[70vh] overflow-y-auto">
                <?php if ($track_history->num_rows > 0): ?>
                    <div class="relative border-l-2 border-indigo-200 ml-3 space-y-8">
                        <?php while ($h = $track_history->fetch_assoc()): ?>
                            <div class="relative pl-8">
                                <div class="absolute -left-[9px] top-1 w-4 h-4 rounded-full bg-indigo-500 border-2 border-white"></div>
                                
                                <div class="bg-gray-50 p-3 rounded shadow-sm border border-gray-100">
                                    <div class="flex justify-between items-start mb-1">
                                        <span class="text-xs font-bold uppercase tracking-wider text-indigo-600">
                                            <?php echo htmlspecialchars($h['action']); ?>
                                        </span>
                                        <span class="text-[10px] text-gray-400">
                                            <?php echo date('M d, Y h:i A', strtotime($h['created_at'])); ?>
                                        </span>
                                    </div>
                                    <p class="text-sm font-semibold text-gray-800"><?php echo htmlspecialchars($h['full_name']); ?></p>
                                    <?php if (!empty($h['remarks'])): ?>
                                        <p class="text-xs text-gray-600 mt-2 italic">"<?php echo htmlspecialchars($h['remarks']); ?>"</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="text-center text-gray-500 italic">No tracking history found for this document.</p>
                <?php endif; ?>
            </div>
            <div class="p-4 bg-gray-50 border-t text-right rounded-b-lg">
                <a href="my_submitted_documents.php" class="inline-block px-4 py-2 bg-indigo-600 text-white rounded text-sm font-bold hover:bg-indigo-700 transition">
                    Done
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

</body>
</html>