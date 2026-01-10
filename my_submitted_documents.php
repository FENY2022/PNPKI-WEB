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
    
    // Fetch document details (Ensuring ownership)
    $v_query = "SELECT * FROM documents WHERE doc_id = ? AND initiator_id = ?";
    $v_stmt = $conn->prepare($v_query);
    $v_stmt->bind_param("ii", $v_id, $user_id);
    $v_stmt->execute();
    $view_data = $v_stmt->get_result()->fetch_assoc();

    if ($view_data) {
        // Fetch associated files
        $f_query = "SELECT * FROM document_files WHERE doc_id = ?";
        $f_stmt = $conn->prepare($f_query);
        $f_stmt->bind_param("i", $v_id);
        $f_stmt->execute();
        $view_files = $f_stmt->get_result();
    }
}
// --- END: VIEW DOCUMENT LOGIC ---

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
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-10">#</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Submitted</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php 
                                    $count = 1; // Initializing row counter
                                    while ($row = $result->fetch_assoc()): 
                                    ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $count++; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($row['title']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($row['doc_type']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php 
                                                $status = $row['status'];
                                                $badgeClass = "bg-gray-100 text-gray-800";
                                                if ($status == 'Completed') $badgeClass = "bg-green-100 text-green-800";
                                                elseif ($status == 'Review' || $status == 'Signing') $badgeClass = "bg-blue-100 text-blue-800";
                                                elseif ($status == 'Returned') $badgeClass = "bg-red-100 text-red-800";
                                                ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $badgeClass; ?>">
                                                    <?php echo $status; ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="?view_id=<?php echo $row['doc_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-4" title="View Details">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <a href="track_document.php?id=<?php echo $row['doc_id']; ?>" class="text-indigo-600 hover:text-indigo-900" title="Track History">
                                                    <i class="fas fa-history"></i> Track
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-6 text-center text-gray-500">
                            <p class="text-xl">No submitted documents found.</p>
                            <a href="new_document.php" class="mt-4 inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
                                <i class="fas fa-plus mr-1"></i> Create New Document
                            </a>
                        </div>
                    <?php endif; ?>

                </div>
            </main>
        </div>
    </div>

    <?php if ($view_data): ?>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 modal-bg">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl overflow-hidden">
            <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-50">
                <h3 class="text-xl font-bold text-gray-800">Document Details</h3>
                <a href="my_submitted_documents.php" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </a>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase">Title</p>
                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($view_data['title']); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase">Document Type</p>
                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($view_data['doc_type']); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase">Current Status</p>
                        <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($view_data['status']); ?></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold text-gray-500 uppercase">Date Submitted</p>
                        <p class="text-sm font-medium text-gray-900"><?php echo date('F d, Y h:i A', strtotime($view_data['created_at'])); ?></p>
                    </div>
                </div>

                <div class="border-t pt-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Attached Files</p>
                    <ul class="divide-y divide-gray-100 border rounded-md">
                        <?php if ($view_files && $view_files->num_rows > 0): ?>
                            <?php while ($file = $view_files->fetch_assoc()): ?>
                                <li class="p-3 flex justify-between items-center">
                                    <span class="text-sm text-gray-700">
                                        <i class="fas fa-file-pdf text-red-500 mr-2"></i> <?php echo htmlspecialchars($file['file_name']); ?>
                                    </span>
                                    <a href="<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank" class="text-blue-600 text-sm font-medium hover:underline">
                                        Download
                                    </a>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li class="p-3 text-sm text-gray-500 italic">No files attached to this document.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <div class="px-6 py-4 bg-gray-50 border-t text-right">
                <a href="my_submitted_documents.php" class="bg-gray-200 text-gray-800 px-4 py-2 rounded hover:bg-gray-300 transition text-sm font-semibold">
                    Close
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

</body>
</html>