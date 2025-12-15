<?php
session_start();

// --- 1. Security Check ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// --- 2. DB Connection ---
require_once 'db.php';

// --- 3. Handle Delete Action ---
$msg = "";
if (isset($_POST['delete_id'])) {
    $delete_id = (int)$_POST['delete_id'];
    
    // Verify ownership before deleting (Security)
    $check_sql = "SELECT doc_id FROM documents WHERE doc_id = ? AND initiator_id = ? AND status = 'Draft'";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $delete_id, $user_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        // We must delete child records first due to Foreign Key constraints
        // 1. Delete Files
        $del_files = $conn->prepare("DELETE FROM document_files WHERE doc_id = ?");
        $del_files->bind_param("i", $delete_id);
        $del_files->execute();
        $del_files->close();

        // 2. Delete Actions/History
        $del_actions = $conn->prepare("DELETE FROM document_actions WHERE doc_id = ?");
        $del_actions->bind_param("i", $delete_id);
        $del_actions->execute();
        $del_actions->close();

        // 3. Delete Signatories
        $del_sigs = $conn->prepare("DELETE FROM document_signatories WHERE doc_id = ?");
        $del_sigs->bind_param("i", $delete_id);
        $del_sigs->execute();
        $del_sigs->close();

        // 4. Finally, Delete the Document
        $del_doc = $conn->prepare("DELETE FROM documents WHERE doc_id = ?");
        $del_doc->bind_param("i", $delete_id);
        
        if ($del_doc->execute()) {
            $msg = "Draft deleted successfully.";
        } else {
            $msg = "Error deleting document.";
        }
        $del_doc->close();
    } else {
        $msg = "Invalid delete request.";
    }
    $check_stmt->close();
}

// --- 4. Handle Search/Filter ---
$search = $_GET['search'] ?? '';
$search_sql = "";
$params = [$user_id];
$types = "i";

if (!empty($search)) {
    $search_sql = " AND (title LIKE ? OR doc_type LIKE ?)";
    $search_term = "%" . $search . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

// --- 5. Fetch Drafts ---
// Only fetch documents initiated by the logged-in user with status 'Draft'
$sql = "SELECT 
            doc_id, 
            title, 
            doc_type, 
            created_at, 
            updated_at
        FROM documents 
        WHERE initiator_id = ? 
        AND status = 'Draft' 
        $search_sql
        ORDER BY updated_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Drafts</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
    </style>
</head>
<body class="p-6">

    <div class="max-w-7xl mx-auto">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">My Drafts</h1>
                <p class="text-sm text-gray-500 mt-1">Manage your unfinished documents.</p>
            </div>
            
            <form method="GET" class="w-full md:w-auto">
                <div class="relative">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search drafts..." 
                           class="w-full md:w-64 pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition text-sm">
                    <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-sm"></i>
                </div>
            </form>
        </div>

        <?php if ($msg): ?>
            <div class="bg-green-100 border border-green-200 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo htmlspecialchars($msg); ?></span>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            
            <?php if ($result->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Document Title</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Updated</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-500">
                                                <i class="fas fa-edit"></i>
                                            </div>
                                            <div class="ml-4">
                                                <div class="text-sm font-medium text-gray-900 truncate max-w-xs" title="<?php echo htmlspecialchars($row['title']); ?>">
                                                    <?php echo htmlspecialchars($row['title']); ?>
                                                </div>
                                                <div class="text-xs text-gray-500">ID: #<?php echo $row['doc_id']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 border border-gray-200">
                                            <?php echo htmlspecialchars($row['doc_type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M d, Y h:i A', strtotime($row['updated_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <div class="flex items-center justify-end space-x-2">
                                            <a href="new_document.php?id=<?php echo $row['doc_id']; ?>" class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-md text-xs font-semibold transition-colors">
                                                <i class="fas fa-pen mr-1"></i> Edit
                                            </a>
                                            
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this draft? This cannot be undone.');">
                                                <input type="hidden" name="delete_id" value="<?php echo $row['doc_id']; ?>">
                                                <button type="submit" class="text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded-md text-xs font-semibold transition-colors">
                                                    <i class="fas fa-trash-alt mr-1"></i> Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-16">
                    <div class="bg-gray-50 rounded-full h-20 w-20 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-folder-open text-gray-300 text-4xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900">No drafts found</h3>
                    <p class="mt-1 text-sm text-gray-500">You don't have any unfinished documents.</p>
                    <div class="mt-6">
                        <a href="new_document.php" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <i class="fas fa-plus mr-2"></i> Create New Document
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>

<?php
if(isset($stmt)) $stmt->close();
$conn->close();
?>