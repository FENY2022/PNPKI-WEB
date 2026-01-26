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

// --- 3. Get Document ID ---
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Error: Document ID is missing.");
}
$doc_id = (int)$_GET['id'];

// --- 4. Fetch Document Details ---
$doc_info = null;
$history = [];

if ($conn) {
    // A. Get Basic Info
    $sql_doc = "SELECT d.*, 
                CONCAT(u.first_name, ' ', u.last_name) as initiator_name,
                CONCAT(own.first_name, ' ', own.last_name) as current_owner_name
                FROM documents d
                LEFT JOIN users u ON d.initiator_id = u.user_id
                LEFT JOIN users own ON d.current_owner_id = own.user_id
                WHERE d.doc_id = ?";
    
    $stmt = $conn->prepare($sql_doc);
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $result_doc = $stmt->get_result();
    $doc_info = $result_doc->fetch_assoc();
    $stmt->close();

    // B. Get History (Audit Trail)
    // Note: Adjust 'remarks' or 'message' column name based on your exact table structure
    // Assuming 'remarks' exists in document_actions, otherwise remove it from query
    $sql_hist = "SELECT da.*, 
                 CONCAT(u.first_name, ' ', u.last_name) as actor_name,
                 u.role as actor_role
                 FROM document_actions da
                 LEFT JOIN users u ON da.user_id = u.user_id
                 WHERE da.doc_id = ?
                 ORDER BY da.created_at DESC"; // Newest on top

    $stmt = $conn->prepare($sql_hist);
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $result_hist = $stmt->get_result();
    while ($row = $result_hist->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();
}

// Helper: Get Icon and Color based on Action string
function getActionStyle($action) {
    $action = strtolower($action);
    if (strpos($action, 'created') !== false || strpos($action, 'draft') !== false) {
        return ['icon' => 'fa-plus', 'color' => 'bg-blue-100 text-blue-600', 'border' => 'border-blue-200'];
    } elseif (strpos($action, 'returned') !== false || strpos($action, 'reject') !== false) {
        return ['icon' => 'fa-undo', 'color' => 'bg-red-100 text-red-600', 'border' => 'border-red-200'];
    } elseif (strpos($action, 'signed') !== false || strpos($action, 'approved') !== false) {
        return ['icon' => 'fa-file-signature', 'color' => 'bg-green-100 text-green-600', 'border' => 'border-green-200'];
    } elseif (strpos($action, 'completed') !== false || strpos($action, 'final') !== false) {
        return ['icon' => 'fa-check-double', 'color' => 'bg-indigo-100 text-indigo-600', 'border' => 'border-indigo-200'];
    } elseif (strpos($action, 'forwarded') !== false) {
        return ['icon' => 'fa-share', 'color' => 'bg-orange-100 text-orange-600', 'border' => 'border-orange-200'];
    }
    return ['icon' => 'fa-history', 'color' => 'bg-gray-100 text-gray-600', 'border' => 'border-gray-200'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Track Document #<?php echo $doc_id; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #f9fafb; }
    </style>
</head>
<body class="p-6 max-w-5xl mx-auto">

    <div class="mb-6 flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="javascript:history.back()" class="p-2 rounded-lg bg-white border border-gray-300 text-gray-600 hover:bg-gray-50 transition shadow-sm">
                <i class="fas fa-arrow-left"></i>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Track Document</h1>
                <p class="text-sm text-gray-500">History and timeline for ID: <span class="font-mono text-indigo-600">#<?php echo $doc_id; ?></span></p>
            </div>
        </div>
        <div>
            <span class="px-4 py-2 rounded-full text-sm font-semibold 
                <?php 
                $st = $doc_info['status'] ?? 'Unknown';
                if($st == 'Returned') echo 'bg-red-100 text-red-700';
                elseif($st == 'Completed') echo 'bg-green-100 text-green-700';
                elseif($st == 'Signing') echo 'bg-blue-100 text-blue-700';
                else echo 'bg-gray-100 text-gray-700';
                ?>">
                <?php echo htmlspecialchars($st); ?>
            </span>
        </div>
    </div>

    <?php if (!$doc_info): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg">
            <i class="fas fa-exclamation-triangle mr-2"></i> Document not found or you do not have permission to view it.
        </div>
    <?php else: ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <div class="md:col-span-1 space-y-6">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">Details</h2>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="text-xs font-medium text-gray-500 uppercase">Title</label>
                            <p class="text-gray-900 font-medium"><?php echo htmlspecialchars($doc_info['title']); ?></p>
                        </div>
                        
                        <div>
                            <label class="text-xs font-medium text-gray-500 uppercase">Document Type</label>
                            <p class="text-gray-700"><?php echo htmlspecialchars($doc_info['doc_type']); ?></p>
                        </div>

                        <div>
                            <label class="text-xs font-medium text-gray-500 uppercase">Initiated By</label>
                            <div class="flex items-center gap-2 mt-1">
                                <div class="w-6 h-6 rounded-full bg-indigo-100 flex items-center justify-center text-xs text-indigo-600 font-bold">
                                    <?php echo substr($doc_info['initiator_name'], 0, 1); ?>
                                </div>
                                <span class="text-sm text-gray-700"><?php echo htmlspecialchars($doc_info['initiator_name']); ?></span>
                            </div>
                        </div>

                        <?php if($doc_info['current_owner_name']): ?>
                        <div>
                            <label class="text-xs font-medium text-gray-500 uppercase">Current Holder</label>
                            <div class="flex items-center gap-2 mt-1">
                                <div class="w-6 h-6 rounded-full bg-orange-100 flex items-center justify-center text-xs text-orange-600 font-bold">
                                    <i class="fas fa-user"></i>
                                </div>
                                <span class="text-sm text-gray-700"><?php echo htmlspecialchars($doc_info['current_owner_name']); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div>
                            <label class="text-xs font-medium text-gray-500 uppercase">Last Updated</label>
                            <p class="text-sm text-gray-600"><?php echo date("M j, Y h:i A", strtotime($doc_info['updated_at'])); ?></p>
                        </div>
                    </div>
                </div>

                <?php if($doc_info['status'] == 'Returned'): ?>
                <div class="bg-white rounded-xl shadow-sm border border-red-200 p-6 bg-red-50">
                    <h3 class="text-red-800 font-medium mb-2"><i class="fas fa-exclamation-circle"></i> Action Required</h3>
                    <p class="text-sm text-red-600 mb-4">This document was returned for revision.</p>
                    <a href="edit_document.php?id=<?php echo $doc_id; ?>" class="block w-full text-center bg-red-600 hover:bg-red-700 text-white py-2 rounded-lg text-sm font-medium transition">
                        Edit / Revise
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <div class="md:col-span-2">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 min-h-[500px]">
                    <h2 class="text-lg font-semibold text-gray-800 mb-6">Activity History</h2>

                    <?php if (empty($history)): ?>
                        <p class="text-gray-500 text-sm italic">No history records found.</p>
                    <?php else: ?>
                        
                        <div class="relative pl-4">
                            <div class="absolute left-8 top-0 bottom-0 w-0.5 bg-gray-200"></div>

                            <div class="space-y-8">
                                <?php foreach ($history as $index => $event): 
                                    $style = getActionStyle($event['action']);
                                    $date = new DateTime($event['created_at']);
                                ?>
                                    <div class="relative flex gap-4">
                                        <div class="relative z-10 flex-shrink-0 w-8 h-8 rounded-full <?php echo $style['color']; ?> flex items-center justify-center border-2 border-white shadow-sm ring-1 ring-gray-100">
                                            <i class="fas <?php echo $style['icon']; ?> text-xs"></i>
                                        </div>

                                        <div class="flex-1 -mt-1">
                                            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start">
                                                <div>
                                                    <p class="text-sm font-bold text-gray-900">
                                                        <?php echo htmlspecialchars($event['action']); ?>
                                                    </p>
                                                    <p class="text-xs text-gray-500 mt-0.5">
                                                        by <span class="font-medium text-gray-700"><?php echo htmlspecialchars($event['actor_name']); ?></span>
                                                        <span class="text-gray-400">(<?php echo htmlspecialchars($event['actor_role'] ?? 'User'); ?>)</span>
                                                    </p>
                                                </div>
                                                <div class="text-xs text-gray-400 mt-1 sm:mt-0 whitespace-nowrap">
                                                    <?php echo $date->format('M j, Y h:i A'); ?>
                                                </div>
                                            </div>

                                            <?php if (!empty($event['remarks']) || !empty($event['message'])): 
                                                // Handle potential column name difference (remarks vs message)
                                                $msg = !empty($event['remarks']) ? $event['remarks'] : $event['message'];
                                            ?>
                                                <div class="mt-2 p-3 bg-gray-50 rounded-lg border border-gray-100 text-sm text-gray-600 italic">
                                                    "<?php echo nl2br(htmlspecialchars($msg)); ?>"
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    <?php endif; ?>
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