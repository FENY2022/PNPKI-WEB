<?php
session_start();

// --- 0. Load Dependencies ---
// Ensure you have run: composer require phpoffice/phpword
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
// Fetch the station from session for filtering signatories




$station_filter = $_SESSION['signatory_station'] ?? ''; 
$toasts = [];

// --- 2. AJAX: Fetch Word Content for Editor ---
if (isset($_GET['action']) && $_GET['action'] == 'fetch_content' && isset($_GET['file_path'])) {
    $file_path = $_GET['file_path'];
    
    if (file_exists($file_path)) {
        try {
            $phpWord = IOFactory::load($file_path);
            $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');
            
            ob_start();
            $htmlWriter->save('php://output');
            $html_content = ob_get_clean();
            
            preg_match('/<body[^>]*>(.*?)<\/body>/is', $html_content, $matches);
            $body_content = $matches[1] ?? $html_content;

            echo json_encode(['status' => 'success', 'content' => $body_content]);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'Error reading file: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'File not found on server.']);
    }
    exit; 
}

// --- 3. Handle Form Submissions ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $check_sql = "SELECT current_owner_id FROM documents WHERE doc_id = ?";
    $stmt_check = $conn->prepare($check_sql);
    $stmt_check->bind_param("i", $doc_id);
    $stmt_check->execute();
    $curr_doc = $stmt_check->get_result()->fetch_assoc();

    if ($curr_doc && $curr_doc['current_owner_id'] == $user_id) {
        
        $conn->begin_transaction();
        try {
            
            if (isset($_POST['save_edit_content'])) {
                $html_input = $_POST['save_edit_content'];
                $html_input = str_replace('&nbsp;', ' ', $html_input);
                
                $dom = new DOMDocument();
                libxml_use_internal_errors(true);
                $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html_input, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                libxml_clear_errors();

                $body = $dom->getElementsByTagName('body')->item(0);
                $clean_html = '';
                
                if ($body) {
                    foreach ($body->childNodes as $child) {
                        $clean_html .= $dom->saveXML($child);
                    }
                } else {
                    $clean_html = $dom->saveXML($dom->documentElement);
                }

                $phpWord = new PhpWord();
                $section = $phpWord->addSection();
                
                try {
                    \PhpOffice\PhpWord\Shared\Html::addHtml($section, $clean_html, false, false);
                } catch (Exception $e) {
                    $section->addText(strip_tags($html_input));
                }

                $new_filename = uniqid('edited_', true) . '.docx';
                $dest_path = 'uploads/' . $new_filename;
                
                if (!is_dir('uploads')) mkdir('uploads', 0755, true);
                
                $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
                $objWriter->save($dest_path);

                $v_sql = "SELECT IFNULL(MAX(version), 0) + 1 FROM document_files WHERE doc_id = ?";
                $stmt_v = $conn->prepare($v_sql);
                $stmt_v->bind_param("i", $doc_id);
                $stmt_v->execute();
                $stmt_v->bind_result($next_ver);
                $stmt_v->fetch();
                $stmt_v->close();

                $sql_f = "INSERT INTO document_files (doc_id, uploader_id, filename, filepath, version) VALUES (?, ?, ?, ?, ?)";
                $stmt_f = $conn->prepare($sql_f);
                $stmt_f->bind_param("iissi", $doc_id, $user_id, $new_filename, $dest_path, $next_ver);
                $stmt_f->execute();

                $log_msg = "Edited document online (v$next_ver)";
                $log_sql = "INSERT INTO document_actions (doc_id, user_id, action, message) VALUES (?, ?, 'Edited', ?)";
                $stmt_log = $conn->prepare($log_sql);
                $stmt_log->bind_param("iis", $doc_id, $user_id, $log_msg);
                $stmt_log->execute();

                $conn->commit();
                $toasts[] = ['type' => 'success', 'message' => "Changes saved as Version $next_ver!"];
            } 
            
            elseif (isset($_POST['action_type'])) {
                
                $action_type = $_POST['action_type']; 
                $remarks = trim($_POST['remarks']);
                $next_user_id = isset($_POST['next_user_id']) ? intval($_POST['next_user_id']) : null;

                if (isset($_FILES['signed_file']) && $_FILES['signed_file']['error'] === UPLOAD_ERR_OK) {
                    $file_tmp = $_FILES['signed_file']['tmp_name'];
                    $file_name = $_FILES['signed_file']['name'];
                    $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $new_filename = uniqid('signed_', true) . '.' . $ext;
                    $dest_path = 'uploads/' . $new_filename;
                    
                    if (!is_dir('uploads')) mkdir('uploads', 0755, true);
                    move_uploaded_file($file_tmp, $dest_path);

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
        $toasts[] = ['type' => 'error', 'message' => "Permission denied. You are not the owner."];
    }
}

// --- 4. Fetch Data for View ---
$sql_doc = "SELECT d.*, u.first_name as init_fname, u.last_name as init_lname, co.first_name as owner_fname, co.last_name as owner_lname FROM documents d JOIN users u ON d.initiator_id = u.user_id LEFT JOIN users co ON d.current_owner_id = co.user_id WHERE d.doc_id = ?";
$stmt = $conn->prepare($sql_doc);
$stmt->bind_param("i", $doc_id);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();

if (!$doc) die("Document not found.");

$sql_files = "SELECT * FROM document_files WHERE doc_id = ? ORDER BY version DESC";
$stmt_files = $conn->prepare($sql_files);
$stmt_files->bind_param("i", $doc_id);
$stmt_files->execute();
$files = $stmt_files->get_result();

$file_list = [];
$latest_file = null;
while($f = $files->fetch_assoc()) {
    $file_list[] = $f;
    if (!$latest_file) $latest_file = $f;
}

$sql_hist = "SELECT da.*, u.first_name, u.last_name FROM document_actions da JOIN users u ON da.user_id = u.user_id WHERE da.doc_id = ? ORDER BY da.created_at DESC";
$stmt_hist = $conn->prepare($sql_hist);
$stmt_hist->bind_param("i", $doc_id);
$stmt_hist->execute();
$history = $stmt_hist->get_result();

// --- UPDATED SIGNATORY FETCHING LOGIC ---
$next_users = [];
if ($doc['current_owner_id'] == $user_id) {
    // Modified to use the station filter and internal user ID
    $sql_next = "SELECT DISTINCT u.user_id, ds.full_name 
                 FROM document_signatories ds
                 INNER JOIN office_station os ON ds.batch_id = os.id
                 INNER JOIN users u ON ds.user_id = u.otos_userlink
                 WHERE os.station = ? AND u.user_id != ?";
                 
    $stmt_next = $conn->prepare($sql_next);
    $stmt_next->bind_param("si", $station_filter, $user_id);
    $stmt_next->execute();
    $res_next = $stmt_next->get_result();
    while($row = $res_next->fetch_assoc()) {
        $next_users[] = $row;
    }
    $stmt_next->close();
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
    
    <script src="https://cdn.tiny.cloud/1/nhgr79jw7mch6vkui23a189kenqp8v0bc6e9akjt5gmickj9/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap'); 
        body { font-family: 'Inter', sans-serif; }
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
        
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
            <div class="flex justify-between items-start">
                <div>
                    <span class="inline-block px-2 py-1 text-xs font-semibold rounded-md bg-blue-100 text-blue-800 mb-2">
                        <?php echo htmlspecialchars($doc['doc_type']); ?>
                    </span>
                    <h1 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($doc['title']); ?></h1>
                    <p class="text-sm text-gray-500 mt-1">
                        From: <?php echo htmlspecialchars($doc['init_fname'] . ' ' . $doc['init_lname']); ?>
                    </p>
                </div>
                <div class="text-right">
                    <div class="text-sm font-medium text-gray-500">Status</div>
                    <div class="text-lg font-bold text-indigo-600"><?php echo htmlspecialchars($doc['status']); ?></div>
                </div>
            </div>
        </div>

        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-900">Document Versions</h3>
            </div>
            
            <?php if (count($file_list) > 0): ?>
                <ul class="divide-y divide-gray-100">
                    <?php foreach($file_list as $index => $file): ?>
                        <?php 
                            $f_ext = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
                            $is_docx = ($f_ext === 'docx');
                            $is_latest = ($index === 0);
                        ?>
                        <li class="py-3 flex justify-between items-center">
                            <div class="flex items-center gap-3">
                                <div class="bg-blue-50 p-2 rounded text-blue-600">
                                    <i class="fas fa-file-word text-xl"></i>
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
                                <button type="button" 
                                        onclick="openViewer('<?php echo htmlspecialchars($file['filepath']); ?>', '<?php echo htmlspecialchars($file['filename']); ?>', '<?php echo $f_ext; ?>')"
                                        class="text-xs font-semibold text-gray-600 hover:text-gray-900 border px-3 py-1.5 rounded hover:bg-gray-50 transition">
                                    <i class="fas fa-eye"></i> View
                                </button>

                                <?php if ($is_docx && $is_latest && $doc['current_owner_id'] == $user_id): ?>
                                    <button type="button" 
                                            onclick="openEditor('<?php echo htmlspecialchars($file['filepath']); ?>')"
                                            class="text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 px-3 py-1.5 rounded shadow-sm transition flex items-center gap-1">
                                        <i class="fas fa-pen"></i> Edit Online
                                    </button>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-gray-500 text-sm italic">No files attached yet.</p>
            <?php endif; ?>
        </div>
        
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Audit Trail</h3>
            <div class="space-y-6 pl-2">
                <?php while($log = $history->fetch_assoc()): ?>
                    <div class="relative pl-6 border-l-2 border-gray-200">
                        <div class="absolute -left-[5px] top-1 h-2 w-2 rounded-full bg-gray-300"></div>
                        <div class="text-sm">
                            <span class="font-bold text-gray-900"><?php echo htmlspecialchars($log['action']); ?></span> 
                            <span class="text-gray-600">by <?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?></span>
                        </div>
                        <div class="text-xs text-gray-400 mb-1"><?php echo date('M d, Y h:i A', strtotime($log['created_at'])); ?></div>
                        <?php if(!empty($log['message'])): ?>
                            <div class="text-xs bg-gray-50 p-2 rounded text-gray-500 italic border border-gray-100 mt-1">
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
                <h3 class="text-lg font-bold text-gray-900 mb-4">Processing Actions</h3>
                <form action="" method="POST" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Decision</label>
                        <select name="action_type" id="action_type" onchange="toggleNextUser()" 
                                class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 p-2 border bg-gray-50">
                            <option value="Forward">Forward / Approve</option>
                            <option value="Return">Return / Disapprove</option>
                            <option value="Finalize">Mark as Final</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Upload Signed File (Optional)</label>
                        <input type="file" name="signed_file" class="block w-full text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                    </div>

                    <div class="mb-4" id="next_user_div">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Route To</label>
                        <select name="next_user_id" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 p-2 border bg-gray-50">
                            <option value="">Select Next Signatory...</option>
                            <?php foreach($next_users as $u): ?>
                                <option value="<?php echo $u['user_id']; ?>"><?php echo htmlspecialchars($u['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Remarks</label>
                        <textarea name="remarks" rows="3" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 p-2 border bg-gray-50" placeholder="Any comments?"></textarea>
                    </div>

                    <button type="submit" onclick="return confirm('Process this document?')" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2.5 rounded-lg shadow transition">
                        Submit Decision
                    </button>
                </form>
            </div>
        <?php else: ?>
             <div class="bg-gray-100 p-6 rounded-xl border border-gray-200 text-center">
                <i class="fas fa-lock text-gray-400 text-3xl mb-2"></i>
                <p class="text-gray-500 text-sm">Document is currently with: <br>
                <span class="font-bold text-gray-700">
                    <?php echo $doc['owner_fname'] ? htmlspecialchars($doc['owner_fname'] . ' ' . $doc['owner_lname']) : 'Completed/Archived'; ?>
                </span></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="viewerModal" class="fixed inset-0 z-50 hidden" style="background: rgba(0,0,0,0.6);">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-5xl h-[85vh] flex flex-col relative animate-[fadeIn_0.2s_ease-out]">
            <div class="flex justify-between items-center p-4 border-b bg-gray-50 rounded-t-lg">
                <h3 class="font-bold text-gray-700">Document Preview</h3>
                <button onclick="closeViewer()" class="text-gray-400 hover:text-gray-700 transition"><i class="fas fa-times text-xl"></i></button>
            </div>
            <div id="viewerContent" class="flex-1 overflow-auto bg-gray-100 p-4 flex items-center justify-center"></div>
        </div>
    </div>
</div>

<div id="editorModal" class="fixed inset-0 z-50 hidden" style="background: rgba(0,0,0,0.6);">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-6xl h-[90vh] flex flex-col relative animate-[fadeIn_0.2s_ease-out]">
            <div class="flex justify-between items-center p-4 border-b bg-gray-50 rounded-t-lg">
                <h3 class="font-bold text-indigo-700 flex items-center gap-2">
                    <i class="fas fa-pen-nib"></i> Edit Document Online
                </h3>
                <button onclick="closeEditor()" class="text-gray-400 hover:text-gray-700 transition"><i class="fas fa-times text-xl"></i></button>
            </div>
            
            <div class="flex-1 overflow-hidden">
                <form method="POST" id="editForm" class="h-full flex flex-col">
                    <div class="flex-1 relative">
                        <textarea id="tinyEditor" name="save_edit_content" class="h-full w-full"></textarea>
                    </div>
                    <div class="p-4 border-t bg-gray-50 flex justify-end gap-3 rounded-b-lg">
                        <button type="button" onclick="closeEditor()" class="px-5 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-white font-medium transition">Cancel</button>
                        <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium shadow-sm transition flex items-center gap-2">
                            <i class="fas fa-save"></i> Save New Version
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleNextUser() {
        const action = document.getElementById('action_type').value;
        const div = document.getElementById('next_user_div');
        div.style.display = (action === 'Finalize' || action === 'Return') ? 'none' : 'block';
    }

    function openViewer(filePath, fileName, ext) {
        document.getElementById('viewerModal').classList.remove('hidden');
        const container = document.getElementById('viewerContent');
        container.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin text-4xl text-indigo-500 mb-2"></i><br>Loading Preview...</div>';

        if (ext === 'docx') {
            fetch(filePath)
                .then(res => res.blob())
                .then(blob => {
                    container.innerHTML = '';
                    docx.renderAsync(blob, container, null, { experimental: true })
                        .catch(e => container.innerHTML = '<div class="text-red-500">Error rendering DOCX. Please download to view.</div>');
                });
        } else {
            container.innerHTML = `<iframe src="${filePath}" class="w-full h-full border-0"></iframe>`;
        }
    }
    function closeViewer() { document.getElementById('viewerModal').classList.add('hidden'); }

    let editorInitialized = false;
    function openEditor(filePath) {
        document.getElementById('editorModal').classList.remove('hidden');
        
        if (!editorInitialized) {
            tinymce.init({
                selector: '#tinyEditor',
                height: '100%',
                resize: false,
                menubar: false,
                plugins: 'lists link image table code preview',
                toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist outdent indent | table | code',
                branding: false, 
                promotion: false,
                entity_encoding: "raw", 
                verify_html: true,
                valid_children: "+body[style]",
            });
            editorInitialized = true;
        }

        if(tinymce.get('tinyEditor')) {
            tinymce.get('tinyEditor').setContent('<p style="text-align:center; color:#888;">Loading document content from server...</p>');
        }

        fetch(`?action=fetch_content&id=<?php echo $doc_id; ?>&file_path=${encodeURIComponent(filePath)}`)
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    tinymce.get('tinyEditor').setContent(data.content);
                } else {
                    alert("Error: " + data.message);
                    closeEditor();
                }
            })
            .catch(err => {
                console.error(err);
                alert("Server connection failed.");
                closeEditor();
            });
    }

    function closeEditor() {
        document.getElementById('editorModal').classList.add('hidden');
    }

    <?php if (!empty($toasts)): foreach($toasts as $t): ?>
        alert("<?php echo $t['message']; ?>");
        <?php if($t['type'] === 'success') echo "window.location.href='my_queue.php';"; ?>
    <?php endforeach; endif; ?>
    
    toggleNextUser();
</script>

</body>
</html>