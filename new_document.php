<?php
session_start();
require_once 'db.php'; // For $conn
$toasts = []; // For toast notifications
$errors = []; // For in-page validation errors
$section_chiefs = [];

// --- 1. Security Check ---
if (!isset($_SESSION['user_id'])) {
    echo "<script>window.top.location.href = 'login.php';</script>";
    exit;
}

if ($_SESSION['role'] != 'Initiator' && $_SESSION['role'] != 'Admin') {
    die("<div style='font-family: Inter, sans-serif; padding: 40px;'>
            <h2 style='font-size: 1.5rem; font-weight: 600; color: #DC2626;'>Access Denied</h2>
            <p style='color: #4B5563;'>Only users with the 'Initiator' role can submit new documents.</p>
         </div>");
}
$initiator_id = $_SESSION['user_id'];

// --- 2. Get Section Chiefs for the dropdown (UPDATED JOIN) ---
// Fetches distinct signatories by joining office_station and document_signatories
try {
    if (isset($_SESSION['signatory_station'])) {
        $station_filter = $_SESSION['signatory_station'];
        
        // UPDATED SQL: Inner Join office_station and document_signatories
        // Filter: office_station.station = $station_filter
        // Join: document_signatories.batch_id = office_station.id
        $sql = "SELECT DISTINCT ds.user_id, ds.full_name 
                FROM document_signatories ds
                INNER JOIN office_station os ON ds.batch_id = os.id
                WHERE os.station = ?";
                
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $station_filter);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $section_chiefs[] = $row;
            }
        }
        $stmt->close();

    } else {
        // Optional: Handle case where session variable is missing
        // $errors[] = "Signatory station not set in session. Please log in again.";
    }
} catch (Exception $e) {
    // This is a critical error, stop the page
    die("Error: Could not load Section Chiefs list. " . $e->getMessage());
}

// --- 3. Handle Form Submission ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 3.1: Get & Validate Form Data
    $title = trim($_POST['doc_title']);
    $doc_type = trim($_POST['doc_type']);
    $section_chief_id = trim($_POST['section_chief_id']);
    $message = trim($_POST['message']);

    // Pre-submission validation (will show in the red box)
    if (empty($title)) $errors[] = "Document Title is required.";
    if (empty($doc_type)) $errors[] = "Document Type is required.";
    if (empty($section_chief_id)) $errors[] = "You must select a Section Chief to route to.";
    
    if (!isset($_FILES['document_files']) || empty($_FILES['document_files']['name'][0])) {
        $errors[] = "At least one document file upload is required.";
    } else {
        $file_count = count($_FILES['document_files']['name']);
        // MODIFIED: Increased max file limit from 10 to 20
        if ($file_count > 20) {
            $errors[] = "You can upload a maximum of 20 files at a time.";
        }
    }

    // 3.2: Process File Upload (if no validation errors)
    $uploaded_files = []; 
    if (empty($errors)) {
        // CONFIRMED: Includes PDF, DOCX, XLSX, etc.
        $allowed_ext = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
        $upload_dir = 'uploads/';

        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                $errors[] = "Error: The 'uploads' directory does not exist and could not be created. Please create it manually.";
            }
        }
        
        if (empty($errors)) {
            $file_count = count($_FILES['document_files']['name']);
            for ($i = 0; $i < $file_count; $i++) {
                $file_name_orig = basename($_FILES['document_files']['name'][$i]);
                $file_tmp = $_FILES['document_files']['tmp_name'][$i];
                $file_size = $_FILES['document_files']['size'][$i];
                $file_error = $_FILES['document_files']['error'][$i];

                if ($file_error !== UPLOAD_ERR_OK) {
                    $errors[] = "Error uploading file: $file_name_orig (Error code: $file_error)";
                    continue; 
                }
                
                $file_ext_check = strtolower(pathinfo($file_name_orig, PATHINFO_EXTENSION));
                
                if (!in_array($file_ext_check, $allowed_ext)) {
                    $errors[] = "Invalid file type: $file_name_orig. Only PDF, Word, Excel, or PowerPoint files are allowed.";
                }
                if ($file_size > 15000000) { // 15MB
                    $errors[] = "File is too large: $file_name_orig. Maximum size is 15MB.";
                }
                if (!empty($errors)) {
                    break; 
                }

                $file_name_new = uniqid('doc_', true) . '.' . $file_ext_check;
                $file_path = $upload_dir . $file_name_new;
                
                if (move_uploaded_file($file_tmp, $file_path)) {
                    $uploaded_files[] = [
                        'orig_name' => $file_name_orig,
                        'new_path' => $file_path
                    ];
                } else {
                    $errors[] = "Failed to move uploaded file: $file_name_orig. Check server permissions on the 'uploads' directory.";
                    break; 
                }
            }
        }
    }

    // 3.3: Insert into Database (Transaction)
    // Only run if validation and file moves succeeded
    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // Step 1: Insert into `documents` table
            $sql_doc = "INSERT INTO documents (title, doc_type, initiator_id, current_owner_id, status) 
                        VALUES (?, ?, ?, ?, 'Draft')";
            $stmt_doc = $conn->prepare($sql_doc);
            $stmt_doc->bind_param("ssii", $title, $doc_type, $initiator_id, $section_chief_id);
            $stmt_doc->execute();
            $doc_id = $conn->insert_id; 
            
            // Step 2: Insert into `document_files` table (in a loop)
            $version = 1; 
            $sql_file = "INSERT INTO document_files (doc_id, uploader_id, filename, filepath, version) 
                         VALUES (?, ?, ?, ?, ?)";
            $stmt_file = $conn->prepare($sql_file);
            
            foreach ($uploaded_files as $file) {
                $stmt_file->bind_param("iissi", $doc_id, $initiator_id, $file['orig_name'], $file['new_path'], $version);
                $stmt_file->execute();
            }

            // Step 3: Insert into `document_actions` table (the log)
            $action = "Submitted";
            $sql_action = "INSERT INTO document_actions (doc_id, user_id, action, message) 
                           VALUES (?, ?, ?, ?)";
            $stmt_action = $conn->prepare($sql_action);
            $stmt_action->bind_param("iiss", $doc_id, $initiator_id, $action, $message);
            $stmt_action->execute();
            
            $conn->commit();
            
            // Set success toast
            $toasts[] = ['type' => 'success', 'message' => 'Document successfully submitted with ' . count($uploaded_files) . ' file(s)!'];
        
        } catch (Exception $e) {
            $conn->rollback();
            
            // Set error toast
            $toasts[] = ['type' => 'error', 'message' => 'Database transaction failed: ' . $e->getMessage()];
            
            // Cleanup uploaded files if DB insert failed
            foreach ($uploaded_files as $file) {
                if (file_exists($file['new_path'])) {
                    unlink($file['new_path']);
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Document</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap');
        body { 
            font-family: 'Inter', sans-serif; 
            padding: 2.5rem; /* p-10 */
            background-color: #f3f4f6; /* bg-gray-100 */
        }
        
        /* Drag-and-Drop Zone Styles */
        #drop-zone {
            border: 2px dashed #d1d5db; /* border-gray-300 */
            border-radius: 0.5rem; /* rounded-lg */
            padding: 2.5rem; /* p-10 */
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        #drop-zone.drag-over {
            border-color: #2563eb; /* border-blue-600 */
            background-color: #eff6ff; /* bg-blue-50 */
        }

        /* File List Item Styles */
        .file-list-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem; /* p-3 */
            background-color: #f9fafb; /* bg-gray-50 */
            border: 1px solid #e5e7eb; /* border-gray-200 */
            border-radius: 0.375rem; /* rounded-md */
            margin-top: 0.5rem;
        }

        /* Toast Notification Styles */
        #toast-container {
            position: fixed;
            top: 1.5rem; /* top-6 */
            right: 1.5rem; /* right-6 */
            z-index: 100;
            display: flex;
            flex-direction: column;
            gap: 0.75rem; /* gap-3 */
        }
        .toast {
            max-width: 320px;
            padding: 1rem;
            border-radius: 0.5rem; /* rounded-lg */
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            opacity: 0;
            transform: translateX(100%);
            animation: slideIn 0.5s forwards, fadeOut 0.5s 4.5s forwards;
        }
        .toast-success {
            background-color: #ecfdf5; /* bg-green-50 */
            border: 1px solid #d1fae5; /* border-green-100 */
        }
        .toast-error {
            background-color: #fff1f2; /* bg-red-50 */
            border: 1px solid #ffe4e6; /* border-red-100 */
        }
        .toast .icon { margin-right: 0.75rem; }
        .toast .message { 
            font-size: 0.875rem; /* text-sm */
            font-weight: 500; 
        }
        .toast-success .message { color: #059669; } /* text-green-700 */
        .toast-error .message { color: #e11d48; } /* text-red-700 */
        .toast .close {
            margin-left: auto;
            padding: 0.25rem;
            border-radius: 0.25rem;
            cursor: pointer;
            background-color: transparent;
            border: none;
        }
        .toast-success .close:hover { background-color: #d1fae5; }
        .toast-error .close:hover { background-color: #ffe4e6; }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; transform: translateX(100%); }
        }
    </style>
</head>
<body>

    <div id="toast-container"></div>

    <form action="new_document.php" method="POST" enctype="multipart/form-data" id="new-doc-form">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-3xl font-bold text-gray-900">
                Submit New Document
            </h1>
            <button type="submit" id="submit-button"
                    class="flex justify-center py-2.5 px-6 border border-transparent rounded-lg shadow-sm text-base font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:bg-gray-400 disabled:cursor-not-allowed">
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24" style="display: none;">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="button-text">Submit Document</span>
            </button>
        </div>

        <?php if (!empty($errors)): ?>
            <script>
                <?php foreach ($errors as $e): ?>
                    console.error("Server-side Error:", <?php echo json_encode($e); ?>);
                <?php endforeach; ?>
            </script>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg" role="alert">
                <p class="font-bold">Errors Found:</p>
                <ul class="list-disc list-inside ml-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <div class="bg-white p-8 rounded-lg shadow-md space-y-6">
                <h2 class="text-xl font-semibold text-gray-900 border-b pb-3">1. Document Details</h2>
                
                <div>
                    <label for="doc_title" class="block text-sm font-medium text-gray-700">Document Title / Subject <span class="text-red-500">*</span></label>
                    <input type="text" id="doc_title" name="doc_title" required 
                           class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                           value="<?php echo isset($_POST['doc_title']) ? htmlspecialchars($_POST['doc_title']) : ''; ?>">
                </div>

                <div>
                    <label for="doc_type" class="block text-sm font-medium text-gray-700">Document Type <span class="text-red-500">*</span></label>
                    <select id="doc_type" name="doc_type" required 
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select a type...</option>
                        <option value="Memorandum" <?php echo (isset($_POST['doc_type']) && $_POST['doc_type'] == 'Memorandum') ? 'selected' : ''; ?>>Memorandum</option>
                        <option value="Travel Order" <?php echo (isset($_POST['doc_type']) && $_POST['doc_type'] == 'Travel Order') ? 'selected' : ''; ?>>Travel Order</option>
                        <option value="Regional Order" <?php echo (isset($_POST['doc_type']) && $_POST['doc_type'] == 'Regional Order') ? 'selected' : ''; ?>>Regional Order</option>
                        <option value="Report" <?php echo (isset($_POST['doc_type']) && $_POST['doc_type'] == 'Report') ? 'selected' : ''; ?>>Report</option>
                        <option value="Other" <?php echo (isset($_POST['doc_type']) && $_POST['doc_type'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <div>
                    <label for="section_chief_id" class="block text-sm font-medium text-gray-700">Route to Section Chief <span class="text-red-500">*</span></label>
                    <select id="section_chief_id" name="section_chief_id" required 
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Select a Section Chief...</option>
                        <?php foreach ($section_chiefs as $chief): ?>
                            <option value="<?php echo $chief['user_id']; ?>" <?php echo (isset($_POST['section_chief_id']) && $_POST['section_chief_id'] == $chief['user_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($chief['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if (empty($section_chiefs)): ?>
                            <option value="" disabled>No signatories found for your station.</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div>
                    <label for="message" class="block text-sm font-medium text-gray-700">Message / Instructions (Optional)</label>
                    <textarea id="message" name="message" rows="6" 
                              class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                </div>
            </div>
            
            <div class="bg-white p-8 rounded-lg shadow-md space-y-6">
                <h2 class="text-xl font-semibold text-gray-900 border-b pb-3">2. Attach Files</h2>
                
                <div>
                    <input type="file" id="document_files_input" name="document_files[]" multiple 
                           class="hidden">
                           
                    <div id="drop-zone">
                        <div class="flex flex-col items-center">
                            <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-4-4V7a4 4 0 014-4h.586a1 1 0 01.707.293l.828.828A1 1 0 009.828 4H14.172a1 1 0 00.707-.293l.828-.828A1 1 0 0116.414 3H17a4 4 0 014 4v5a4 4 0 01-4 4H7z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 16v2a2 2 0 01-2 2H8a2 2 0 01-2-2v-2m16-4h-2m-2 0h-2m-2 0H9m-2 0H5"></path></svg>
                            <p class="mt-2 text-sm text-gray-600">
                                <span class="font-semibold text-blue-600">Drag files here</span> or click to select
                            </p>
                            <p class="text-xs text-gray-500 mt-1">Max 20 files. PDF, DOC, XLS, PPT.</p>
                        </div>
                    </div>
                    
                    <div id="file-list-container" class="mt-4 space-y-2">
                        </div>
                    
                    <label for="document_files_input_label" id="add-more-files-label" class="mt-4 inline-block cursor-pointer px-4 py-2 text-sm font-semibold text-blue-700 bg-blue-50 rounded-md hover:bg-blue-100 hidden">
                        + Add More Files
                    </label>

                    <p id="file-error-msg" class="text-xs text-red-600 mt-2 hidden"></p>
                </div>
            </div>

        </div> </form>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            
            // --- Toast Notification ---
            const toastContainer = document.getElementById('toast-container');
            
            function createToast(message, type) {
                const toast = document.createElement('div');
                toast.className = `toast toast-${type}`;
                
                let icon;
                if (type === 'success') {
                    icon = `<svg class="icon w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
                } else {
                    icon = `<svg class="icon w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>`;
                }
                
                toast.innerHTML = `
                    ${icon}
                    <span class="message">${message}</span>
                    <button class="close">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                `;
                
                // Close button
                toast.querySelector('.close').addEventListener('click', () => {
                    toast.style.animation = 'fadeOut 0.5s forwards';
                    setTimeout(() => toast.remove(), 500);
                });
                
                // Auto-dismiss
                setTimeout(() => {
                    if (document.body.contains(toast)) {
                        toast.style.animation = 'fadeOut 0.5s forwards';
                        setTimeout(() => toast.remove(), 500);
                    }
                }, 5000);
                
                toastContainer.appendChild(toast);
            }

            // --- PHP-to-JS Toast Trigger ---
            <?php if (!empty($toasts)): ?>
                const toastsToShow = <?php echo json_encode($toasts); ?>;
                toastsToShow.forEach(t => {
                    createToast(t.message, t.type);
                });
            <?php endif; ?>


            // --- File Uploader ---
            const fileInput = document.getElementById('document_files_input');
            const dropZone = document.getElementById('drop-zone');
            const fileListContainer = document.getElementById('file-list-container');
            const addMoreLabel = document.getElementById('add-more-files-label');
            const errorMsg = document.getElementById('file-error-msg');
            // MODIFIED: Updated max file limit to 20
            const maxFiles = 20; 
            const fileStore = new DataTransfer(); // Master file list

            // Trigger hidden file input when drop zone is clicked
            dropZone.addEventListener('click', () => fileInput.click());
            
            // "Add More Files" label also triggers the input
            addMoreLabel.addEventListener('click', () => fileInput.click());
            
            // --- Drag and Drop Events ---
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('drag-over');
            });
            dropZone.addEventListener('dragleave', (e) => {
                e.preventDefault();
                dropZone.classList.remove('drag-over');
            });
            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('drag-over');
                handleFiles(e.dataTransfer.files);
            });
            
            // --- File Input Change Event ---
            fileInput.addEventListener('change', (e) => {
                handleFiles(e.target.files);
                // e.target.value = ''; // FIX: Commented out to prevent clearing files
            });

            function handleFiles(newFiles) {
                showError(''); // Clear previous errors
                let filesAdded = 0;
                
                for (let i = 0; i < newFiles.length; i++) {
                    const file = newFiles[i];
                    if (fileStore.items.length >= maxFiles) {
                        showError(`Cannot add "${file.name}". Maximum of ${maxFiles} files allowed.`);
                        break;
                    }
                    if (!isDuplicate(file)) {
                        fileStore.items.add(file);
                        filesAdded++;
                    }
                }
                
                // CRITICAL FIX: Ensure the actual input has the files for immediate UI/prop checks
                fileInput.files = fileStore.files; 
                renderFileList();
            }

            function isDuplicate(newFile) {
                for (let i = 0; i < fileStore.files.length; i++) {
                    // Check by name AND size to avoid accidental mismatches
                    if (fileStore.files[i].name === newFile.name && fileStore.files[i].size === newFile.size) {
                        return true;
                    }
                }
                return false;
            }

            function renderFileList() {
                fileListContainer.innerHTML = ''; // Clear visual list
                
                if (fileStore.files.length > 0) {
                    fileInput.required = false; // Programmatically set required to false
                    addMoreLabel.classList.remove('hidden'); // Show "Add More"
                    dropZone.classList.add('hidden'); // Hide drop zone
                } else {
                    fileInput.required = true; // Programmatically set required to true (only effective if HTML attribute is missing)
                    addMoreLabel.classList.add('hidden');
                    dropZone.classList.remove('hidden');
                }
                
                for (let i = 0; i < fileStore.files.length; i++) {
                    const file = fileStore.files[i];
                    const fileExt = file.name.split('.').pop().toLowerCase();
                    const fileItem = document.createElement('div');
                    fileItem.className = 'file-list-item';
                    
                    let fileIcon = `<svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>`;
                    if (['doc', 'docx'].includes(fileExt)) {
                        fileIcon = `<svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>`;
                    } else if (['xls', 'xlsx'].includes(fileExt)) {
                        fileIcon = `<svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>`;
                    } else if (fileExt === 'pdf') {
                        fileIcon = `<svg class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>`;
                    }

                    fileItem.innerHTML = `
                        <div class="flex items-center space-x-3">
                            ${fileIcon}
                            <div class="flex flex-col">
                                <span class="text-sm font-medium text-gray-800">${file.name}</span>
                                <span class="text-xs text-gray-500">${(file.size / 1024).toFixed(2)} KB</span>
                            </div>
                        </div>
                        <button type="button" data-index="${i}" class="remove-file-btn text-sm font-medium text-red-600 hover:text-red-800">&times;</button>
                    `;
                    
                    fileListContainer.appendChild(fileItem);
                }
                
                // Add event listeners to all new "remove" buttons
                document.querySelectorAll('.remove-file-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        removeFile(parseInt(this.dataset.index));
                    });
                });
            }
            
            function removeFile(indexToRemove) {
                const newFileStore = new DataTransfer();
                for (let i = 0; i < fileStore.files.length; i++) {
                    if (i !== indexToRemove) {
                        newFileStore.items.add(fileStore.files[i]);
                    }
                }
                
                // Replace old master list with new one
                fileStore.items.clear();
                for(let i = 0; i < newFileStore.files.length; i++) {
                    fileStore.items.add(newFileStore.files[i]);
                }
                
                fileInput.files = fileStore.files;
                renderFileList();
            }

            function showError(message) {
                errorMsg.textContent = message;
                message ? errorMsg.classList.remove('hidden') : errorMsg.classList.add('hidden');
            }

            // --- Form Submission Spinner & Fix ---
            const docForm = document.getElementById('new-doc-form');
            const submitButton = document.getElementById('submit-button');
            
            if (docForm && submitButton) {
                docForm.addEventListener('submit', function(e) {
                    
                    // [CRITICAL FIX] Re-populate the input with files from the DataTransfer object
                    // This is done right before submission to ensure files are included
                    fileInput.files = fileStore.files; 

                    const spinner = submitButton.querySelector('svg');
                    const buttonText = submitButton.querySelector('.button-text');

                    // Final check for files
                    if (fileInput.files.length === 0) {
                        e.preventDefault(); 
                        const msg = 'At least one document file is required.';
                        showError(msg);
                        createToast(msg, 'error');
                        
                        // Reset button state if submission is prevented
                        submitButton.disabled = false;
                        submitButton.classList.remove('opacity-75', 'cursor-not-allowed');
                        if (spinner) spinner.style.display = 'none';
                        if (buttonText) buttonText.textContent = 'Submit Document';

                        return;
                    }
                    
                    // If validation passes, show loading state and disable
                    submitButton.disabled = true;
                    if (spinner) spinner.style.display = 'inline-block';
                    if (buttonText) buttonText.textContent = 'Submitting...';
                    submitButton.classList.add('opacity-75', 'cursor-not-allowed');
                });
            }
        });
    </script>

</body>
</html>