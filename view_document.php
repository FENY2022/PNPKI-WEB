<?php
session_start();
require_once 'db.php';

// --- 1. Security Check ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: my_queue.php");
    exit;
}

$doc_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];
$toasts = [];

// --- 2. Handle Form Submissions ---

// A. Handle "Edit / Update" Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_document'])) {
    
    // Verify Owner
    $check_sql = "SELECT current_owner_id, status FROM documents WHERE doc_id = ?";
    $stmt_check = $conn->prepare($check_sql);
    $stmt_check->bind_param("i", $doc_id);
    $stmt_check->execute();
    $curr_doc = $stmt_check->get_result()->fetch_assoc();

    if ($curr_doc && $curr_doc['current_owner_id'] == $user_id) {
        $new_title = trim($_POST['doc_title']);
        $new_type = trim($_POST['doc_type']);
        $edit_remarks = "Updated document details/content.";

        $conn->begin_transaction();
        try {
            // 1. Update Metadata
            $upd_sql = "UPDATE documents SET title = ?, doc_type = ?, updated_at = NOW() WHERE doc_id = ?";
            $stmt_upd = $conn->prepare($upd_sql);
            $stmt_upd->bind_param("ssi", $new_title, $new_type, $doc_id);
            $stmt_upd->execute();

            // 2. Handle New Version Upload (if provided)
            if (isset($_FILES['new_version_file']) && $_FILES['new_version_file']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['new_version_file']['tmp_name'];
                $file_name = $_FILES['new_version_file']['name'];
                $file_size = $_FILES['new_version_file']['size'];
                
                $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
                $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

                if (in_array($ext, $allowed) && $file_size < 15000000) {
                    $new_filename = uniqid('v_', true) . '.' . $ext;
                    $dest_path = 'uploads/' . $new_filename;

                    if (!is_dir('uploads')) mkdir('uploads', 0755, true);

                    if (move_uploaded_file($file_tmp, $dest_path)) {
                        // Get Next Version Number
                        $v_sql = "SELECT IFNULL(MAX(version), 0) + 1 FROM document_files WHERE doc_id = ?";
                        $stmt_v = $conn->prepare($v_sql);
                        $stmt_v->bind_param("i", $doc_id);
                        $stmt_v->execute();
                        $stmt_v->bind_result($next_ver);
                        $stmt_v->fetch();
                        $stmt_v->close();

                        // Insert new file record
                        $sql_f = "INSERT INTO document_files (doc_id, uploader_id, filename, filepath, version) VALUES (?, ?, ?, ?, ?)";
                        $stmt_f = $conn->prepare($sql_f);
                        $stmt_f->bind_param("iissi", $doc_id, $user_id, $file_name, $dest_path, $next_ver);
                        $stmt_f->execute();
                        
                        $edit_remarks = "Uploaded new version (v$next_ver): $file_name";
                    }
                } else {
                    throw new Exception("Invalid file type or file too large.");
                }
            }

            // 3. Log History
            $log_sql = "INSERT INTO document_actions (doc_id, user_id, action, message) VALUES (?, ?, ?, ?)";
            $stmt_log = $conn->prepare($log_sql);
            $action_log = "Edited";
            $stmt_log->bind_param("iiss", $doc_id, $user_id, $action_log, $edit_remarks);
            $stmt_log->execute();

            $conn->commit();
            $toasts[] = ['type' => 'success', 'message' => "Document updated successfully!"];

        } catch (Exception $e) {
            $conn->rollback();
            $toasts[] = ['type' => 'error', 'message' => "Update failed: " . $e->getMessage()];
        }
    } else {
        $toasts[] = ['type' => 'error', 'message' => "Permission denied: You are not the owner."];
    }
}

// B. Handle Process (Forward/Return/Finalize)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action_type'])) {
    
    // Verify Owner
    $check_sql = "SELECT current_owner_id, status FROM documents WHERE doc_id = ?";
    $stmt_check = $conn->prepare($check_sql);
    $stmt_check->bind_param("i", $doc_id);
    $stmt_check->execute();
    $curr_doc = $stmt_check->get_result()->fetch_assoc();
    
    if ($curr_doc && $curr_doc['current_owner_id'] == $user_id) {
        
        $action_type = $_POST['action_type']; 
        $remarks = trim($_POST['remarks']);
        $next_user_id = isset($_POST['next_user_id']) ? intval($_POST['next_user_id']) : null;
        
        $conn->begin_transaction();
        try {
            // Handle Signed File Upload (If present)
            if (isset($_FILES['signed_file']) && $_FILES['signed_file']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['signed_file']['tmp_name'];
                $file_name = $_FILES['signed_file']['name'];
                $file_size = $_FILES['signed_file']['size'];
                
                $allowed = ['pdf', 'doc', 'docx', 'jpg', 'png', 'jpeg'];
                $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                if (in_array($ext, $allowed) && $file_size < 15000000) {
                    $new_filename = uniqid('signed_', true) . '.' . $ext;
                    $dest_path = 'uploads/' . $new_filename;
                    
                    if (!is_dir('uploads')) mkdir('uploads', 0755, true);

                    if (move_uploaded_file($file_tmp, $dest_path)) {
                        // Get Next Version
                        $v_sql = "SELECT IFNULL(MAX(version), 0) + 1 FROM document_files WHERE doc_id = ?";
                        $stmt_v = $conn->prepare($v_sql);
                        $stmt_v->bind_param("i", $doc_id);
                        $stmt_v->execute();
                        $stmt_v->bind_result($next_ver);
                        $stmt_v->fetch();
                        $stmt_v->close();

                        // Insert
                        $sql_f = "INSERT INTO document_files (doc_id, uploader_id, filename, filepath, version) VALUES (?, ?, ?, ?, ?)";
                        $stmt_f = $conn->prepare($sql_f);
                        $stmt_f->bind_param("iissi", $doc_id, $user_id, $file_name, $dest_path, $next_ver);
                        $stmt_f->execute();
                        
                        $remarks .= " [Uploaded signed version: $file_name]";
                    }
                } else {
                    throw new Exception("Invalid file type or file too large.");
                }
            }

            // Determine Status & Target
            $new_status = "";
            $log_action = "";
            $target_owner = $next_user_id;
            
            if ($action_type === 'Return') {
                $new_status = "Returned";
                $log_action = "Returned Document";
                $target_owner = $user_id; 
            } elseif ($action_type === 'Finalize') {
                $new_status = "Completed";
                $log_action = "Approved & Finalized";
                $target_owner = 0; 
            } else {
                $new_status = "Review"; 
                $log_action = "Forwarded";
                if (!$next_user_id) throw new Exception("Please select a valid user to forward to.");
            }

            // Update Document
            $update_sql = "UPDATE documents SET current_owner_id = ?, status = ?, updated_at = NOW() WHERE doc_id = ?";
            $stmt_upd = $conn->prepare($update_sql);
            $stmt_upd->bind_param("isi", $target_owner, $new_status, $doc_id);
            $stmt_upd->execute();

            // Log History
            $log_sql = "INSERT INTO document_actions (doc_id, user_id, action, message) VALUES (?, ?, ?, ?)";
            $stmt_log = $conn->prepare($log_sql);
            $stmt_log->bind_param("iiss", $doc_id, $user_id, $log_action, $remarks);
            $stmt_log->execute();

            $conn->commit();
            $toasts[] = ['type' => 'success', 'message' => "Document processed successfully!"];
            
        } catch (Exception $e) {
            $conn->rollback();
            $toasts[] = ['type' => 'error', 'message' => "Error: " . $e->getMessage()];
        }
    } else {
        $toasts[] = ['type' => 'error', 'message' => "Permission denied: You are not the current owner."];
    }
}

// --- 3. Fetch Document Details ---
$sql_doc = "SELECT d.*, 
            u.first_name as init_fname, u.last_name as init_lname, u.email as init_email,
            co.first_name as owner_fname, co.last_name as owner_lname
            FROM documents d
            JOIN users u ON d.initiator_id = u.user_id
            LEFT JOIN users co ON d.current_owner_id = co.user_id
            WHERE d.doc_id = ?";
$stmt = $conn->prepare($sql_doc);
$stmt->bind_param("i", $doc_id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();

if (!$doc) {
    header("Location: my_queue.php");
    exit;
}

// --- 4. Fetch Files ---
$sql_files = "SELECT * FROM document_files WHERE doc_id = ? ORDER BY created_at DESC";
$stmt_files = $conn->prepare($sql_files);
$stmt_files->bind_param("i", $doc_id);
$stmt_files->execute();
$files = $stmt_files->get_result();

// --- 5. Fetch History ---
$sql_hist = "SELECT da.*, u.first_name, u.last_name 
             FROM document_actions da
             JOIN users u ON da.user_id = u.user_id
             WHERE da.doc_id = ? 
             ORDER BY da.created_at DESC";
$stmt_hist = $conn->prepare($sql_hist);
$stmt_hist->bind_param("i", $doc_id);
$stmt_hist->execute();
$history = $stmt_hist->get_result();

// --- 6. Fetch Next Signatories ---
$next_users = [];
if ($doc['current_owner_id'] == $user_id) {
    $sql_next = "SELECT user_id, full_name FROM document_signatories WHERE user_id != ?";
    $stmt_next = $conn->prepare($sql_next);
    $stmt_next->bind_param("i", $user_id);
    $stmt_next->execute();
    $res_next = $stmt_next->get_result();
    while($row = $res_next->fetch_assoc()) {
        $next_users[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Document #<?php echo $doc_id; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://unpkg.com/jszip/dist/jszip.min.js"></script>
    <script src="https://unpkg.com/docx-preview/dist/docx-preview.min.js"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap'); 
        body { font-family: 'Inter', sans-serif; }
        
        #viewerModal, #editModal { transition: opacity 0.3s ease; }
        #viewerContent { height: 80vh; overflow: auto; background-color: #f3f4f6; border: 1px solid #e5e7eb; }
    </style>
</head>
<body class="bg-gray-50 p-6">

<div class="max-w-7xl mx-auto mb-6">
    <a href="my_queue.php" class="text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-2">
        <i class="fas fa-arrow-left"></i> Back to Queue
    </a>
</div>

<div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <div class="lg:col-span-2 space-y-6">
        
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 relative">
            <?php if ($doc['current_owner_id'] == $user_id): ?>
                <button onclick="openEditModal()" 
                        class="absolute top-6 right-6 text-sm bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded-lg flex items-center gap-2 transition">
                    <i class="fas fa-edit"></i> Edit Document
                </button>
            <?php endif; ?>

            <div class="flex justify-between items-start pr-32"> <div>
                    <span class="inline-block px-2 py-1 text-xs font-semibold rounded-md bg-blue-100 text-blue-800 mb-2">
                        <?php echo htmlspecialchars($doc['doc_type']); ?>
                    </span>
                    <h1 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($doc['title']); ?></h1>
                    <p class="text-sm text-gray-500 mt-1">Created on <?php echo date('F j, Y', strtotime($doc['created_at'])); ?></p>
                </div>
            </div>
            
            <div class="mt-6 pt-6 border-t border-gray-100 flex gap-8">
                <div>
                    <span class="block text-xs text-gray-400 uppercase tracking-wide">Initiator</span>
                    <span class="block text-sm font-medium text-gray-800">
                        <?php echo htmlspecialchars($doc['init_fname'] . ' ' . $doc['init_lname']); ?>
                    </span>
                </div>
                <div>
                    <span class="block text-xs text-gray-400 uppercase tracking-wide">Current Owner</span>
                    <span class="block text-sm font-medium text-gray-800">
                        <?php echo $doc['owner_fname'] ? htmlspecialchars($doc['owner_fname'] . ' ' . $doc['owner_lname']) : 'None (Completed)'; ?>
                    </span>
                </div>
                <div class="ml-auto">
                    <div class="text-xs text-gray-400 uppercase tracking-wide text-right">Status</div>
                    <div class="text-lg font-bold <?php echo ($doc['status']=='Completed')?'text-green-600':'text-indigo-600'; ?>">
                        <?php echo htmlspecialchars($doc['status']); ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Attachments / Versions</h3>
            <?php if ($files->num_rows > 0): ?>
                <ul class="divide-y divide-gray-100">
                    <?php while($file = $files->fetch_assoc()): ?>
                        <?php 
                            $f_ext = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
                            $can_view = in_array($f_ext, ['pdf', 'docx']);
                        ?>
                        <li class="py-3 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded bg-gray-100 flex items-center justify-center text-gray-500">
                                    <i class="fas fa-file-<?php echo ($f_ext == 'pdf' ? 'pdf' : 'word'); ?>"></i>
                                </div>
                                <div>
                                    <span class="block text-sm text-gray-700 font-medium"><?php echo htmlspecialchars($file['filename']); ?></span>
                                    <span class="block text-xs text-gray-400">
                                        v<?php echo $file['version']; ?> &bull; 
                                        <?php echo date('M d, Y h:i A', strtotime($file['created_at'])); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <?php if ($can_view): ?>
                                    <button type="button" 
                                            onclick="openViewer('<?php echo htmlspecialchars($file['filepath']); ?>', '<?php echo htmlspecialchars($file['filename']); ?>', '<?php echo $f_ext; ?>')"
                                            class="text-sm text-blue-600 hover:text-blue-800 font-medium border border-blue-200 px-3 py-1 rounded bg-blue-50 hover:bg-blue-100 transition-colors">
                                        <i class="fas fa-eye mr-1"></i> View
                                    </button>
                                <?php endif; ?>

                                <a href="<?php echo htmlspecialchars($file['filepath']); ?>" target="_blank" 
                                   class="text-sm text-gray-600 hover:text-gray-800 font-medium border border-gray-300 px-3 py-1 rounded hover:bg-gray-50 transition-colors">
                                    <i class="fas fa-download mr-1"></i> Download
                                </a>
                            </div>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <p class="text-gray-500 text-sm italic">No files attached.</p>
            <?php endif; ?>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Audit Trail</h3>
            <div class="relative border-l-2 border-gray-200 ml-3 space-y-8">
                <?php while($log = $history->fetch_assoc()): ?>
                    <div class="ml-6 relative">
                        <span class="absolute -left-[31px] bg-white border-2 border-gray-200 rounded-full w-4 h-4 mt-1.5"></span>
                        <div>
                            <span class="text-sm font-bold text-gray-900">
                                <?php echo htmlspecialchars($log['action']); ?>
                            </span>
                            <span class="text-xs text-gray-500 ml-2">
                                by <?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?>
                            </span>
                        </div>
                        <div class="text-xs text-gray-400 mt-0.5 mb-2">
                            <?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?>
                        </div>
                        <?php if (!empty($log['message'])): ?>
                            <div class="bg-gray-50 p-3 rounded-md text-sm text-gray-600 italic border border-gray-100">
                                "<?php echo htmlspecialchars($log['message']); ?>"
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <div class="lg:col-span-1">
        <?php if ($doc['current_owner_id'] == $user_id): ?>
            <div class="bg-white p-6 rounded-xl shadow-md border-t-4 border-indigo-500 sticky top-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Process Document</h3>
                
                <form action="" method="POST" id="processForm" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Action</label>
                        <select name="action_type" id="action_type" onchange="toggleNextUser()" 
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 p-2 border bg-gray-50">
                            <option value="Forward">Forward / Approve</option>
                            <option value="Return">Return / Disapprove</option>
                            <option value="Finalize">Mark as Final/Completed</option>
                        </select>
                    </div>

                    <div class="mb-4 p-4 bg-blue-50 border border-blue-100 rounded-lg">
                        <label class="block text-sm font-bold text-gray-800 mb-1">
                            <i class="fas fa-file-signature text-blue-600 mr-1"></i> Upload Signed Copy
                        </label>
                        <p class="text-xs text-gray-500 mb-2">If you signed the document, upload the signed version here.</p>
                        <input type="file" name="signed_file" 
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-100 file:text-blue-700 hover:file:bg-blue-200">
                    </div>

                    <div class="mb-4" id="next_user_div">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Route To</label>
                        <select name="next_user_id" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 p-2 border bg-gray-50">
                            <option value="">Select Next Signatory...</option>
                            <?php foreach($next_users as $u): ?>
                                <option value="<?php echo $u['user_id']; ?>">
                                    <?php echo htmlspecialchars($u['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Remarks / Instructions</label>
                        <textarea name="remarks" rows="4" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 p-2 border bg-gray-50" placeholder="Add optional comments..."></textarea>
                    </div>

                    <button type="submit" onclick="return confirm('Are you sure you want to process this document?');"
                            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 px-4 rounded-lg shadow transition duration-150 ease-in-out flex justify-center items-center gap-2">
                        <i class="fas fa-paper-plane"></i> Submit Decision
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div class="bg-gray-100 p-6 rounded-xl border border-gray-200 text-center text-gray-500">
                <i class="fas fa-lock text-3xl mb-3 text-gray-400"></i>
                <p class="text-sm">You cannot process this document because it is currently with:</p>
                <p class="font-bold text-gray-800 mt-1">
                    <?php echo $doc['owner_fname'] ? htmlspecialchars($doc['owner_fname'] . ' ' . $doc['owner_lname']) : 'No one (Completed)'; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="viewerModal" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeViewer()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-5xl sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4 border-b border-gray-100 flex justify-between items-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">Document Preview</h3>
                <button type="button" onclick="closeViewer()" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div class="bg-gray-50 p-4" id="viewerContainer">
                <div id="viewerContent" class="w-full rounded bg-white shadow-inner flex items-center justify-center text-gray-500"></div>
            </div>
        </div>
    </div>
</div>

<div id="editModal" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeEditModal()"></div>
        
        <div class="inline-block bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full">
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-bold text-gray-900 mb-4 border-b pb-2">
                        <i class="fas fa-pen-to-square text-indigo-500 mr-2"></i>Edit Document
                    </h3>
                    
                    <input type="hidden" name="update_document" value="1">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Document Title</label>
                        <input type="text" name="doc_title" required value="<?php echo htmlspecialchars($doc['title']); ?>" 
                               class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 p-2 border">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Document Type</label>
                        <select name="doc_type" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 p-2 border">
                            <option value="Memorandum" <?php echo $doc['doc_type']=='Memorandum'?'selected':''; ?>>Memorandum</option>
                            <option value="Travel Order" <?php echo $doc['doc_type']=='Travel Order'?'selected':''; ?>>Travel Order</option>
                            <option value="Report" <?php echo $doc['doc_type']=='Report'?'selected':''; ?>>Report</option>
                            <option value="Letter" <?php echo $doc['doc_type']=='Letter'?'selected':''; ?>>Letter</option>
                        </select>
                    </div>

                    <div class="mb-4 bg-yellow-50 p-3 rounded-md border border-yellow-200">
                        <label class="block text-sm font-bold text-gray-800 mb-1">Upload New Version (Save Edits)</label>
                        <p class="text-xs text-gray-600 mb-2">To "save" your edits: Download the file, edit it on your computer, and upload the updated version here.</p>
                        <input type="file" name="new_version_file" 
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-yellow-100 file:text-yellow-700 hover:file:bg-yellow-200">
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none sm:ml-3 sm:w-auto sm:text-sm">
                        Save Changes
                    </button>
                    <button type="button" onclick="closeEditModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // --- Actions Logic ---
    function toggleNextUser() {
        const action = document.getElementById('action_type').value;
        const div = document.getElementById('next_user_div');
        if (action === 'Finalize' || action === 'Return') {
            div.style.display = 'none';
        } else {
            div.style.display = 'block';
        }
    }

    // --- Viewer Logic ---
    function openViewer(filePath, fileName, ext) {
        const modal = document.getElementById('viewerModal');
        const container = document.getElementById('viewerContent');
        modal.classList.remove('hidden');
        container.innerHTML = '<p class="text-center p-10"><i class="fas fa-spinner fa-spin text-3xl"></i><br>Loading...</p>';

        if (ext === 'pdf') {
            container.innerHTML = `<iframe src="${filePath}" class="w-full h-full" frameborder="0"></iframe>`;
        } else if (ext === 'docx') {
            fetch(filePath)
                .then(response => response.blob())
                .then(blob => {
                    container.innerHTML = ''; 
                    docx.renderAsync(blob, container, null, {
                        inWrapper: false, ignoreWidth: false, experimental: true 
                    }).then(x => console.log("docx: finished"));
                })
                .catch(err => {
                    container.innerHTML = '<div class="text-center p-10 text-red-500">Error loading document.</div>';
                });
        }
    }

    function closeViewer() {
        document.getElementById('viewerModal').classList.add('hidden');
        document.getElementById('viewerContent').innerHTML = '';
    }

    // --- Edit Modal Logic ---
    function openEditModal() {
        document.getElementById('editModal').classList.remove('hidden');
    }
    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }

    // --- Initialize ---
    document.addEventListener("DOMContentLoaded", function() {
        toggleNextUser();
        <?php if (!empty($toasts)): ?>
            <?php foreach($toasts as $t): ?>
                alert("<?php echo $t['message']; ?>"); 
                <?php if ($t['type'] === 'success'): ?>
                    // Optional: remove redirect if you want them to stay on page to see changes
                     window.location.href = window.location.href;
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    });
</script>

</body>
</html>