<?php
session_start();

// --- 1. Security Check ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// --- 2. Database Connection ---
require_once 'db.php'; // Ensure db.php is in the same directory

$user_id = $_SESSION['user_id'];

// --- 3. Fetch Submitted Documents ---
// We fetch documents where the user is the initiator and status is NOT 'Draft'
// We also JOIN with the users table to get the name of the 'current_owner'
$sql = "SELECT 
            d.doc_id, 
            d.title, 
            d.doc_type, 
            d.status, 
            d.created_at, 
            d.updated_at,
            u.name AS owner_name
        FROM documents d
        LEFT JOIN users u ON d.current_owner_id = u.user_id
        WHERE d.initiator_id = ? 
          AND d.status != 'Draft'
        ORDER BY d.updated_at DESC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    die("Error preparing query: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Submitted Documents - DDTMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
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
                            <table class="min-w-full leading-normal">
                                <thead>
                                    <tr>
                                        <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Doc ID
                                        </th>
                                        <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Title
                                        </th>
                                        <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Type
                                        </th>
                                        <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Current Status
                                        </th>
                                        <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Current Owner
                                        </th>
                                        <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Last Updated
                                        </th>
                                        <th class="px-5 py-3 border-b-2 border-gray-200 bg-gray-100 text-center text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <p class="text-gray-900 whitespace-no-wrap"><?php echo htmlspecialchars($row['doc_id']); ?></p>
                                            </td>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <p class="text-gray-900 font-semibold whitespace-no-wrap"><?php echo htmlspecialchars($row['title']); ?></p>
                                            </td>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <p class="text-gray-900 whitespace-no-wrap"><?php echo htmlspecialchars($row['doc_type']); ?></p>
                                            </td>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <span class="relative inline-block px-3 py-1 font-semibold leading-tight 
                                                    <?php 
                                                        if($row['status'] == 'Completed') echo 'text-green-900';
                                                        elseif($row['status'] == 'Returned') echo 'text-red-900';
                                                        else echo 'text-orange-900';
                                                    ?>">
                                                    <span aria-hidden="true" class="absolute inset-0 opacity-50 rounded-full 
                                                        <?php 
                                                            if($row['status'] == 'Completed') echo 'bg-green-200';
                                                            elseif($row['status'] == 'Returned') echo 'bg-red-200';
                                                            else echo 'bg-orange-200';
                                                        ?>">
                                                    </span>
                                                    <span class="relative"><?php echo htmlspecialchars($row['status']); ?></span>
                                                </span>
                                            </td>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <p class="text-gray-900 whitespace-no-wrap">
                                                    <?php 
                                                        if (!empty($row['owner_name'])) {
                                                            echo htmlspecialchars($row['owner_name']); 
                                                        } else {
                                                            echo '<span class="text-gray-500 italic">Unassigned</span>';
                                                        }
                                                    ?>
                                                </p>
                                            </td>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm">
                                                <p class="text-gray-900 whitespace-no-wrap">
                                                    <?php echo date("M j, Y g:i A", strtotime($row['updated_at'])); ?>
                                                </p>
                                            </td>
                                            <td class="px-5 py-5 border-b border-gray-200 bg-white text-sm text-center">
                                                <a href="view_document.php?id=<?php echo $row['doc_id']; ?>" class="text-blue-600 hover:text-blue-900 mr-2" title="View Document">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="track_document.php?id=<?php echo $row['doc_id']; ?>" class="text-indigo-600 hover:text-indigo-900" title="Track History">
                                                    <i class="fas fa-history"></i>
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
                            <p class="mt-2 text-sm">Once you initiate and send a document for review, it will appear here.</p>
                            <a href="new_document.php" class="mt-4 inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition">
                                <i class="fas fa-plus mr-1"></i> Create New Document
                            </a>
                        </div>
                    <?php endif; ?>

                </div>
            </main>
        </div>
    </div>
</body>
</html>