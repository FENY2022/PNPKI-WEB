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

// --- 3. Handle Search/Filter ---
$search = $_GET['search'] ?? '';
$search_sql = "";
$params = [$user_id];
$types = "i";

if (!empty($search)) {
    $search_sql = " AND (d.title LIKE ? OR d.doc_type LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $search_term = "%" . $search . "%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ssss";
}

// --- 4. Fetch Documents in Queue ---
// Logic: Select documents where current_owner_id is the logged-in user
// AND the status is NOT 'Draft' (Drafts go to my_drafts.php)
// We join with 'users' to get the initiator's name.
$sql = "SELECT 
            d.doc_id, 
            d.title, 
            d.doc_type, 
            d.status, 
            d.created_at, 
            d.updated_at,
            u.first_name as init_fname, 
            u.last_name as init_lname
        FROM documents d
        JOIN users u ON d.initiator_id = u.user_id
        WHERE d.current_owner_id = ? 
        AND d.status != 'Draft' 
        AND d.status != 'Completed'
        AND d.status != 'Archived'
        $search_sql
        ORDER BY d.updated_at DESC";

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
    <title>My Action Queue</title>
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
                <h1 class="text-2xl font-bold text-gray-800">My Action Queue</h1>
                <p class="text-sm text-gray-500 mt-1">Documents awaiting your review or signature.</p>
            </div>
            
            <form method="GET" class="w-full md:w-auto">
                <div class="relative">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search documents..." 
                           class="w-full md:w-64 pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none transition text-sm">
                    <i class="fas fa-search absolute left-3 top-2.5 text-gray-400 text-sm"></i>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            
            <?php if ($result->num_rows > 0): ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Document Details</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Initiator</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Updated</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0 h-10 w-10 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600">
                                                <i class="fas fa-file-alt"></i>
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
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">
                                            <?php echo htmlspecialchars($row['doc_type']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($row['init_fname'] . ' ' . $row['init_lname']); ?></div>
                                        <div class="text-xs text-gray-500">Original Sender</div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php 
                                            $statusClass = 'bg-gray-100 text-gray-800';
                                            if($row['status'] == 'Signing') $statusClass = 'bg-purple-100 text-purple-800 border border-purple-200';
                                            elseif($row['status'] == 'Review') $statusClass = 'bg-yellow-100 text-yellow-800 border border-yellow-200';
                                            elseif($row['status'] == 'Returned') $statusClass = 'bg-red-100 text-red-800 border border-red-200';
                                        ?>
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars($row['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M d, Y h:i A', strtotime($row['updated_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="view_document.php?id=<?php echo $row['doc_id']; ?>" class="text-white bg-indigo-600 hover:bg-indigo-700 px-3 py-1.5 rounded-md text-xs font-semibold shadow-sm transition-all flex items-center inline-flex gap-1">
                                            <i class="fas fa-eye"></i> Process
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-16">
                    <div class="bg-gray-50 rounded-full h-20 w-20 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-check-circle text-gray-300 text-4xl"></i>
                    </div>
                    <h3 class="text-lg font-medium text-gray-900">All caught up!</h3>
                    <p class="mt-1 text-sm text-gray-500">You have no pending documents in your queue.</p>
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