<?php
session_start();
// --- 0. Load Composer Autoload (REQUIRED for Editing) ---
// Make sure you ran: composer require phpoffice/phpword
require_once 'vendor/autoload.php'; 
require_once 'db.php';

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;

// --- 1. Security Check ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: my_queue.php");
    exit;
}

$doc_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];
$toasts = [];

// --- 2. Handle AJAX Request for Fetching Doc Content (For Editing) ---
if (isset($_GET['action']) && $_GET['action'] == 'fetch_content' && isset($_GET['file_path'])) {
    $file_path = $_GET['file_path'];
    
    if (file_exists($file_path)) {
        try {
            $phpWord = IOFactory::load($file_path);
            $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');
            
            // Capture HTML output to string
            ob_start();
            $htmlWriter->save('php://output');
            $html_content = ob_get_clean();
            
            // Extract body content only to avoid full HTML structure conflict in editor
            preg_match('/<body[^>]*>(.*?)<\/body>/is', $html_content, $matches);
            $body_content = $matches[1] ?? $html_content;

            echo json_encode(['status' => 'success', 'content' => $body_content]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error parsing document: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'File not found.']);
    }
    exit; // Stop execution for AJAX
}

// --- 3. Handle Form Submission (Process / Save Edit) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Verify Owner Logic
    $check_sql = "SELECT current_owner_id FROM documents WHERE doc_id = ?";
    $stmt_check = $conn->prepare($check_sql);
    $stmt_check->bind_param("i", $doc_id);
    $stmt_check->execute();
    $curr_doc = $stmt_check->get_result()->fetch_assoc();

    if ($curr_doc && $curr_doc['current_owner_id'] == $user_id) {
        
        $conn->begin_transaction();
        try {
            // --- SCENARIO A: Saving Online Edit ---
            if (isset($_POST['save_edit_content'])) {
                $html_input = $_POST['save_edit_content'];
                
                // 1. Convert HTML back to DOCX
                $phpWord = new PhpWord();
                $section = $phpWord->addSection();
                // Add Html::addHtml($section, $html_input); is the standard way
                \PhpOffice\PhpWord\Shared\Html::addHtml($section, $html_input, false, false);

                // 2. Save File
                $new_filename = uniqid('edited_', true) . '.docx';
                $dest_path = 'uploads/' . $new_filename;
                if (!is_dir('uploads')) mkdir('uploads', 0755, true);
                
                $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
                $objWriter->save($dest_path);

                // 3. Get Next Version
                $v_sql = "SELECT IFNULL(MAX(version), 0) + 1 FROM document_files WHERE doc_id = ?";
                $stmt_v = $conn->prepare($v_sql);
                $stmt_v->bind_param("i", $doc_id);
                $stmt_v->execute();
                $stmt_v->bind_result($next_ver);
                $stmt_v->fetch();
                $stmt_v->close();

                // 4. Insert DB Record
                $sql_f = "INSERT INTO document_files (doc_id, uploader_id, filename, filepath, version) VALUES (?, ?, ?, ?, ?)";
                $stmt_f = $conn->prepare($sql_f);
                $stmt_f->bind_param("iissi", $doc_id, $user_id, $new_filename, $dest_path, $next_ver);
                $stmt_f->execute();

                // 5. Log Action
                $log_msg = "Edited document online (Version $next_ver)";
                $log_sql = "INSERT INTO document_actions (doc_id, user_id, action, message) VALUES (?, ?, 'Edited', ?)";
                $stmt_log = $conn->prepare($log_sql);
                $stmt_log->bind_param("iis", $doc_id, $user_id, $log_msg);
                $stmt_log->execute();

                $conn->commit();
                $toasts[] = ['type' => 'success', 'message' => "Changes saved successfully as a new version!"];
            } 
            
            // --- SCENARIO B: Processing (Forward/Return) ---
            elseif (isset($_POST['action_type'])) {
                // ... (Existing Process Logic) ...
                $action_type = $_POST['action_type']; 
                $remarks = trim($_POST['remarks']);
                $next_user_id = isset($_POST['next_user_id']) ? intval($_POST['next_user_id']) : null;

                // Handle Upload (Manual)
                if (isset($_FILES['signed_file']) && $_FILES['signed_file']['error'] === UPLOAD_ERR_OK) {
                    // ... (Existing upload logic logic) ...
                    $file_tmp = $_FILES['signed_file']['tmp_name'];
                    $file_name = $_FILES['signed_file']['name'];
                    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $new_filename = uniqid('signed_', true) . '.' . $ext;
                    $dest_path = 'uploads/' . $new_filename;
                    move_uploaded_file($file_tmp, $dest_path);

                    // Version logic
                    $v_sql = "SELECT IFNULL(MAX(version), 0) + 1 FROM document_files WHERE doc_id = ?";
                    $stmt_v = $conn->prepare($v_sql);
                    $stmt_v->bind_param("i", $doc_id);
                    $stmt_v->execute();
                    $stmt_v->bind_result($next_ver);
                    $stmt_v->fetch();
                    $stmt_v->close();

                    $sql_f = "INSERT INTO document_files (doc_id, uploader_id, filename, filepath, version) VALUES (?, ?, ?, ?, ?)";
                    $stmt_f = $conn->prepare($sql_f);
                    $stmt_f->bind_param("iissi", $doc_id, $user_id, $file_name, $dest_path, $next_ver);
                    $stmt_f->execute();
                    $remarks .= " [Uploaded signed file]";
                }

                // Determine Status
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
                }

                $update_sql = "UPDATE documents SET current_owner_id = ?, status = ?, updated_at = NOW() WHERE doc_id = ?";
                $stmt_upd = $conn->prepare($update_sql);
                $stmt_upd->bind_param("isi", $target_owner, $new_status, $doc_id);
                $stmt_upd->execute();

                $log_sql = "INSERT INTO document_actions (doc_id, user_id, action, message) VALUES (?, ?, ?, ?)";
                $stmt_log = $conn->prepare($log_sql);
                $stmt_log->bind_param("iiss", $doc_id, $user_id, $log_action, $remarks);
                $stmt_log->execute();

                $conn->commit();
                $toasts[] = ['type' => 'success', 'message' => "Document processed successfully!"];
            }

        } catch (Exception $e) {
            $conn->rollback();
            $toasts[] = ['type' => 'error', 'message' => "Error: " . $e->getMessage()];
        }
    } else {
        $toasts[] = ['type' => 'error', 'message' => "Permission denied."];
    }
}

// --- 4. Fetch Data (Existing Logic) ---
$sql_doc = "SELECT d.*, u.first_name as init_fname, u.last_name as init_lname, co.first_name as owner_fname, co.last_name as owner_lname FROM documents d JOIN users u ON d.initiator_id = u.user_id LEFT JOIN users co ON d.current_owner_id = co.user_id WHERE d.doc_id = ?";
$stmt = $conn->prepare($sql_doc);
$stmt->bind_param("i", $doc_id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();

$sql_files = "SELECT * FROM document_files WHERE doc_id = ? ORDER BY version DESC"; // Changed to version DESC to get latest first
$stmt_files = $conn->prepare($sql_files);
$stmt_files->bind_param("i", $doc_id);
$stmt_files->execute();
$files = $stmt_files->get_result();
$latest_file = null; // We need this to know what to edit
$file_list = [];
while($f = $files->fetch_assoc()) {
    $file_list[] = $f;
    if (!$latest_file) $latest_file = $f;
}

$sql_hist = "SELECT da.*, u.first_name, u.last_name FROM document_actions da JOIN users u ON da.user_id = u.user_id WHERE da.doc_id = ? ORDER BY da.created_at DESC";
$stmt_hist = $conn->prepare($sql_hist);
$stmt_hist->bind_param("i", $doc_id);
$stmt_hist->execute();
$history = $stmt_hist->get_result();

$next_users = [];
if ($doc['current_owner_id'] == $user_id) {
    $sql_next = "SELECT user_id, full_name FROM document_signatories WHERE user_id != ?";
    $stmt_next = $conn->prepare($sql_next);
    $stmt_next->bind_param("i", $user_id);
    $stmt_next->execute();
    $res_next = $stmt_next->get_result();
    while($row = $res_next->fetch_assoc()) $next_users[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View & Edit Document #<?php echo $doc_id; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="https://unpkg.com/jszip/dist/jszip.min.js"></script>
    <script src="https://unpkg.com/docx-preview/dist/docx-preview.min.js"></script>
    
    <script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap'); 
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 p-6">

<div class="max-w-7xl mx-auto mb-6">
    <a href="queue.php" class="text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-2">
        <i class="fas fa-arrow-left"></i> Back to Queue
    </a>
</div>

<div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <div class="lg:col-span-2 space-y-6">
        
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
            <div class="flex justify-between items-start">
                <div>
                    <span class="inline-block px-2 py-1 text-xs font-semibold rounded-md bg-blue-100 text-blue-800 mb-2">
                        <?php echo htmlspecialchars($doc['doc_type']); ?>
                    </span>
                    <h1 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($doc['title']); ?></h1>
                </div>
                <div class="text-right">
                    <div class="text-sm font-medium text-gray-500">Status</div>
                    <div class="text-lg font-bold text-indigo-600"><?php echo htmlspecialchars($doc['status']); ?></div>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Document Versions</h3>
            <?php if (count($file_list) > 0): ?>
                <ul class="divide-y divide-gray-100">
                    <?php foreach($file_list as $index => $file): ?>
                        <?php 
                            $f_ext = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
                            $is_docx = ($f_ext === 'docx');
                            // Latest file is the first one in array because we sorted DESC
                            $is_latest = ($index === 0);
                        ?>
                        <li class="py-3 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <i class="fas fa-file-word text-blue-600 text-xl"></i>
                                <div>
                                    <span class="block text-sm text-gray-700 font-medium"><?php echo htmlspecialchars($file['filename']); ?></span>
                                    <span class="block text-xs text-gray-400">
                                        Version <?php echo $file['version']; ?> &bull; 
                                        <?php echo date('M d, Y', strtotime($file['created_at'])); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" 
                                        onclick="openViewer('<?php echo htmlspecialchars($file['filepath']); ?>', '<?php echo htmlspecialchars($file['filename']); ?>', '<?php echo $f_ext; ?>')"
                                        class="text-sm text-gray-600 hover:text-gray-900 border px-3 py-1 rounded hover:bg-gray-50">
                                    <i class="fas fa-eye"></i> View
                                </button>

                                <?php if ($is_docx && $is_latest && $doc['current_owner_id'] == $user_id): ?>
                                    <button type="button" 
                                            onclick="openEditor('<?php echo htmlspecialchars($file['filepath']); ?>', '<?php echo htmlspecialchars($file['filename']); ?>')"
                                            class="text-sm text-white bg-indigo-600 hover:bg-indigo-700 px-3 py-1 rounded shadow-sm">
                                        <i class="fas fa-pen"></i> Edit Online
                                    </button>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-gray-500 text-sm">No files attached.</p>
            <?php endif; ?>
        </div>
        
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Audit Trail</h3>
            <div class="space-y-4">
                <?php while($log = $history->fetch_assoc()): ?>
                    <div class="text-sm border-l-2 border-gray-200 pl-4">
                        <span class="font-bold"><?php echo htmlspecialchars($log['action']); ?></span> 
                        by <?php echo htmlspecialchars($log['first_name']); ?>
                        <div class="text-xs text-gray-400"><?php echo date('M d h:i A', strtotime($log['created_at'])); ?></div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <div class="lg:col-span-1">
        <?php if ($doc['current_owner_id'] == $user_id): ?>
            <div class="bg-white p-6 rounded-xl shadow-md border-t-4 border-indigo-500 sticky top-6">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Process Document</h3>
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Action</label>
                        <select name="action_type" id="action_type" onchange="toggleNextUser()" class="w-full border p-2 rounded bg-gray-50">
                            <option value="Forward">Forward / Approve</option>
                            <option value="Return">Return / Disapprove</option>
                            <option value="Finalize">Mark as Final</option>
                        </select>
                    </div>

                    <div class="mb-4" id="next_user_div">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Route To</label>
                        <select name="next_user_id" class="w-full border p-2 rounded bg-gray-50">
                            <option value="">Select Next Signatory...</option>
                            <?php foreach($next_users as $u): ?>
                                <option value="<?php echo $u['user_id']; ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <textarea name="remarks" rows="3" class="w-full border p-2 rounded" placeholder="Comments..."></textarea>
                    </div>

                    <button type="submit" class="w-full bg-indigo-600 text-white font-bold py-2 rounded hover:bg-indigo-700">Submit</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="viewerModal" class="fixed inset-0 z-50 hidden" style="background: rgba(0,0,0,0.5);">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-5xl h-[80vh] flex flex-col">
            <div class="flex justify-between items-center p-4 border-b">
                <h3 class="font-bold">Preview</h3>
                <button onclick="closeViewer()" class="text-gray-500 hover:text-black"><i class="fas fa-times"></i></button>
            </div>
            <div id="viewerContent" class="flex-1 overflow-auto bg-gray-100 p-4"></div>
        </div>
    </div>
</div>

<div id="editorModal" class="fixed inset-0 z-50 hidden" style="background: rgba(0,0,0,0.5);">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-6xl h-[90vh] flex flex-col">
            <div class="flex justify-between items-center p-4 border-b">
                <h3 class="font-bold text-lg">Edit Document Online</h3>
                <button onclick="closeEditor()" class="text-gray-500 hover:text-black"><i class="fas fa-times fa-lg"></i></button>
            </div>
            
            <div class="flex-1 overflow-hidden p-2">
                <form method="POST" id="editForm" class="h-full flex flex-col">
                    <div class="flex-1 relative">
                        <textarea id="tinyEditor" name="save_edit_content" class="h-full"></textarea>
                    </div>
                    <div class="p-4 border-t bg-gray-50 flex justify-end gap-3">
                        <button type="button" onclick="closeEditor()" class="px-4 py-2 border rounded text-gray-700 hover:bg-gray-100">Cancel</button>
                        <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-700 flex items-center gap-2">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    // --- 1. Toggle Process Form ---
    function toggleNextUser() {
        const action = document.getElementById('action_type').value;
        const div = document.getElementById('next_user_div');
        div.style.display = (action === 'Finalize' || action === 'Return') ? 'none' : 'block';
    }

    // --- 2. Viewer Logic ---
    function openViewer(filePath, fileName, ext) {
        document.getElementById('viewerModal').classList.remove('hidden');
        const container = document.getElementById('viewerContent');
        container.innerHTML = 'Loading...';

        if (ext === 'docx') {
            fetch(filePath).then(res => res.blob()).then(blob => {
                container.innerHTML = '';
                docx.renderAsync(blob, container, null, { experimental: true });
            });
        } else {
            container.innerHTML = `<iframe src="${filePath}" class="w-full h-full"></iframe>`;
        }
    }
    function closeViewer() { document.getElementById('viewerModal').classList.add('hidden'); }

    // --- 3. Editor Logic (The New Part) ---
    let editorInitialized = false;

    function openEditor(filePath, fileName) {
        document.getElementById('editorModal').classList.remove('hidden');
        
        // Initialize TinyMCE if not already done
        if (!editorInitialized) {
            tinymce.init({
                selector: '#tinyEditor',
                height: '100%',
                plugins: 'anchor autolink charmap codesample emoticons image link lists media searchreplace table visualblocks wordcount',
                toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | link image media table | align lineheight | numlist bullist indent outdent | removeformat',
            });
            editorInitialized = true;
        }

        // Fetch Content via AJAX
        tinymce.get('tinyEditor').setContent('<p>Loading document content...</p>');
        
        fetch(`?action=fetch_content&id=<?php echo $doc_id; ?>&file_path=${encodeURIComponent(filePath)}`)
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    tinymce.get('tinyEditor').setContent(data.content);
                } else {
                    alert("Error loading file: " + data.message);
                    closeEditor();
                }
            })
            .catch(err => {
                console.error(err);
                alert("Failed to connect to server.");
            });
    }

    function closeEditor() {
        document.getElementById('editorModal').classList.add('hidden');
    }

    // Toasts
    <?php if (!empty($toasts)): foreach($toasts as $t): ?>
        alert("<?php echo $t['message']; ?>");
        <?php if($t['type'] === 'success') echo "window.location.href='queue.php';"; ?>
    <?php endforeach; endif; ?>
    
    toggleNextUser();
</script>

</body>
</html>