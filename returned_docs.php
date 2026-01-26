<?php
session_start();

// --- 1. Security Check ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// --- 2. DB Connection ---
$conn = null;
try {
    if (file_exists(__DIR__ . '/config.php')) {
        require_once __DIR__ . '/config.php';
    } elseif (file_exists(__DIR__ . '/db.php')) {
        require_once __DIR__ . '/db.php';
    } else {
        $conn = new mysqli('127.0.0.1', 'root', '', 'ddts_pnpki');
    }
} catch (Exception $e) {
    die("System Error: Database Connection Failed. " . $e->getMessage());
}

// --- 3. Handle Search ---
$search = $_GET['search'] ?? '';
$search_param = "%" . $search . "%";

// --- 4. Fetch Returned Documents ---
$documents = [];

// This query fetches documents AND looks up the latest "Returned" action to get the reason/person
$sql = "SELECT 
            d.doc_id, 
            d.title, 
            d.doc_type, 
            d.updated_at, 
            -- Subquery to get the specific return message from history
            (SELECT message 
             FROM document_actions da 
             WHERE da.doc_id = d.doc_id AND da.action LIKE '%Returned%' 
             ORDER BY da.created_at DESC LIMIT 1) as return_reason,
             -- Subquery to get the name of the person who returned it
            (SELECT CONCAT(u.first_name, ' ', u.last_name) 
             FROM document_actions da 
             JOIN users u ON da.user_id = u.user_id 
             WHERE da.doc_id = d.doc_id AND da.action LIKE '%Returned%' 
             ORDER BY da.created_at DESC LIMIT 1) as returned_by_name
        FROM documents d
        WHERE d.status = 'Returned' 
        AND (d.initiator_id = ? OR d.current_owner_id = ?)
        AND (d.doc_id LIKE ? OR d.title LIKE ?)
        ORDER BY d.updated_at DESC";

if ($conn) {
    try {
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("iiss", $user_id, $user_id, $search_param, $search_param);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                // If the subquery didn't find a message (e.g. manual status change), fallback to 'remarks' or generic text
                if (empty($row['return_reason'])) {
                    // You could check d.remarks here if you selected it, but usually history is best
                    $row['return_reason'] = "No specific remarks provided.";
                }
                $documents[] = $row;
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        // Keep silent or log error
    }
}

// Helper for relative time
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Returned Documents</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f9fafb; }
        .custom-scrollbar::-webkit-scrollbar { height: 8px; width: 8px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: #f1f1f1; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
</head>
<body class="p-6">

    <div class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-3">
                <span class="p-2 bg-red-100 text-red-600 rounded-lg">
                    <i class="fas fa-undo-alt"></i>
                </span>
                Returned Documents
            </h1>
            <p class="text-sm text-gray-500 mt-1 ml-12">
                These documents require your attention for revision.
            </p>
        </div>

        <form action="" method="GET" class="relative w-full md:w-72">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                   placeholder="Search ID or Title..." 
                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-200 focus:border-red-400 outline-none transition-all shadow-sm text-sm">
            <i class="fas fa-search absolute left-3 top-2.5 text-gray-400"></i>
        </form>
    </div>

    <?php if (empty($documents)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-12 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-green-50 text-green-500 rounded-full mb-4">
                <i class="fas fa-check-circle text-2xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900">All caught up!</h3>
            <p class="text-gray-500 mt-1">You have no returned documents pending revision.</p>
            <?php if (!empty($search)): ?>
                <a href="returned_docs.php" class="inline-block mt-4 text-sm text-indigo-600 hover:text-indigo-800 font-medium">Clear Search</a>
            <?php endif; ?>
        </div>
    <?php else: ?>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto custom-scrollbar">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Document ID / Title</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Type</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Returned By</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Reason / Remarks</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        <?php foreach ($documents as $doc): ?>
                            <tr class="hover:bg-gray-50 transition-colors duration-150">
                                <td class="px-6 py-4">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-bold text-gray-500 uppercase">
                                            ID: <?php echo htmlspecialchars($doc['doc_id']); ?>
                                        </span>
                                        <span class="text-sm font-semibold text-indigo-700 mt-0.5">
                                            <?php echo htmlspecialchars($doc['title']); ?>
                                        </span>
                                    </div>
                                </td>
                                
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 border border-gray-200">
                                        <?php echo htmlspecialchars($doc['doc_type']); ?>
                                    </span>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo htmlspecialchars($doc['returned_by_name'] ?? 'System / Admin'); ?>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-0.5">
                                            <i class="far fa-clock mr-1"></i>
                                            <?php echo time_elapsed_string($doc['updated_at']); ?>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <div class="max-w-xs text-sm text-red-700 bg-red-50 border border-red-100 rounded-md p-2.5">
                                        <i class="fas fa-exclamation-circle text-red-400 mr-1 text-xs"></i>
                                        <span class="italic">"<?php echo htmlspecialchars(mb_strimwidth($doc['return_reason'], 0, 100, "...")); ?>"</span>
                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end gap-2">
                                        <a href="edit_document.php?id=<?php echo $doc['doc_id']; ?>" 
                                           class="group flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700 transition-all shadow-sm text-xs">
                                            <i class="fas fa-edit"></i>
                                            <span>Edit / Resubmit</span>
                                        </a>
                                        <a href="track_document.php?id=<?php echo $doc['doc_id']; ?>" 
                                           class="text-gray-400 hover:text-gray-600 p-2 rounded-md hover:bg-gray-100 border border-transparent hover:border-gray-200 transition-all" 
                                           title="View History">
                                            <i class="fas fa-history"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="bg-gray-50 px-6 py-3 border-t border-gray-200 flex items-center justify-between">
                <div class="text-xs text-gray-500">
                    Showing <?php echo count($documents); ?> returned documents
                </div>
            </div>
        </div>

    <?php endif; ?>

</body>
</html>
<?php
if ($conn) {
    $conn->close();
}
?>