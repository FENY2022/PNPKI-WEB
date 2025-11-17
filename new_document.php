<?php
session_start();
require_once 'db.php'; // For $conn
$errors = [];
$success_message = "";
$section_chiefs = [];

// --- 1. Security Check ---
// Redirect top window to login if not logged in
if (!isset($_SESSION['user_id'])) {
    echo "<script>window.top.location.href = 'login.php';</script>";
    exit;
}

// Ensure the user is an Initiator
if ($_SESSION['role'] != 'Initiator' && $_SESSION['role'] != 'Admin') {
    // We are in an iframe, so just show a simple error
    die("<div style='font-family: Inter, sans-serif; padding: 40px;'>
            <h2 style='font-size: 1.5rem; font-weight: 600; color: #DC2626;'>Access Denied</h2>
            <p style='color: #4B5563;'>Only users with the 'Initiator' role can submit new documents.</p>
         </div>");
}
$initiator_id = $_SESSION['user_id'];

// --- 2. Get Section Chiefs for the dropdown ---
// This is needed for the form to load
try {
    $sql = "SELECT user_id, first_name, last_name FROM users WHERE role = 'Section Chief' AND status = 'active'";
    $result = $conn->query($sql);
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $section_chiefs[] = $row;
        }
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

    if (empty($title)) $errors[] = "Document Title is required.";
    if (empty($doc_type)) $errors[] = "Document Type is required.";
    if (empty($section_chief_id)) $errors[] = "You must select a Section Chief to route to.";
    
    // MODIFICATION: Check for multiple files
    if (!isset($_FILES['document_files']) || empty($_FILES['document_files']['name'][0])) {
        // Check if the 'name' array is empty or the first name is an empty string
        $errors[] = "At least one document file upload is required.";
    } else {
        $file_count = count($_FILES['document_files']['name']);
        if ($file_count > 10) {
            $errors[] = "You can upload a maximum of 10 files at a time.";
        }
    }


    // 3.2: Process File Upload (if no validation errors)
    $uploaded_files = []; // This will store info for the DB
    if (empty($errors)) {
        $allowed_ext = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
        $upload_dir = 'uploads/';

        // Ensure upload directory exists
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                $errors[] = "Error: The 'uploads' directory does not exist and could not be created. Please create it manually.";
            }
        }
        
        // MODIFICATION: Loop through each file
        if (empty($errors)) {
            $file_count = count($_FILES['document_files']['name']);
            for ($i = 0; $i < $file_count; $i++) {
                $file_name_orig = basename($_FILES['document_files']['name'][$i]);
                $file_tmp = $_FILES['document_files']['tmp_name'][$i];
                $file_size = $_FILES['document_files']['size'][$i];
                $file_error = $_FILES['document_files']['error'][$i];

                // Check for individual file upload errors
                if ($file_error !== UPLOAD_ERR_OK) {
                    $errors[] = "Error uploading file: $file_name_orig (Error code: $file_error)";
                    continue; // Skip this file and check the next one
                }
                
                $file_ext_check = strtolower(pathinfo($file_name_orig, PATHINFO_EXTENSION));
                
                if (!in_array($file_ext_check, $allowed_ext)) {
                    $errors[] = "Invalid file type: $file_name_orig. Only PDF, Word, Excel, or PowerPoint files are allowed.";
                }
                if ($file_size > 15000000) { // 15MB
                    $errors[] = "File is too large: $file_name_orig. Maximum size is 15MB.";
                }

                // If any errors were found during this loop, stop processing files
                if (!empty($errors)) {
                    break; 
                }

                // Create a unique file name
                $file_name_new = uniqid('doc_', true) . '.' . $file_ext_check;
                $file_path = $upload_dir . $file_name_new;
                
                if (move_uploaded_file($file_tmp, $file_path)) {
                    // Store info for DB insertion
                    $uploaded_files[] = [
                        'orig_name' => $file_name_orig,
                        'new_path' => $file_path
                    ];
                } else {
                    $errors[] = "Failed to move uploaded file: $file_name_orig. Check server permissions on the 'uploads' directory.";
                    break; // Stop if one file fails to move
                }
            }
        }
    }

    // 3.3: Insert into Database (Transaction)
    if (empty($errors)) {
        $conn->begin_transaction();
        try {
            // Step 1: Insert into `documents` table
            $sql_doc = "INSERT INTO documents (title, doc_type, initiator_id, current_owner_id, status) 
                        VALUES (?, ?, ?, ?, 'Draft')";
            $stmt_doc = $conn->prepare($sql_doc);
            $stmt_doc->bind_param("ssii", $title, $doc_type, $initiator_id, $section_chief_id);
            $stmt_doc->execute();
            $doc_id = $conn->insert_id; // Get the new document ID
            
            // Step 2: MODIFICATION: Insert into `document_files` table (in a loop)
            $version = 1; // All files in this submission are version 1
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
            
            // If all queries succeed, commit the transaction
            $conn->commit();
            $success_message = "Document successfully submitted with " . count($uploaded_files) . " file(s)! It has been routed to the Section Chief for drafting.";
        
        } catch (Exception $e) {
            // If any query fails, roll back all changes
            $conn->rollback();
            $errors[] = "Database transaction failed: " . $e->getMessage();
            
            // MODIFICATION: Rollback: Delete all files that were just uploaded
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
        /* Style for the file list */
        .file-list-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 0.75rem;
            background-color: #f9fafb; /* bg-gray-50 */
            border: 1px solid #e5e7eb; /* border-gray-200 */
            border-radius: 0.375rem; /* rounded-md */
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>

    <h1 class="text-3xl font-bold text-gray-900 mb-6">
        Submit New Document
    </h1>

    <div class="max-w-2xl">

        <?php if (!empty($success_message)): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg" role="alert">
                <p class="font-bold">Success!</p>
                <p><?php echo htmlspecialchars($success_message); ?></p>
            </div>
            <a href="new_document.php" class="text-blue-600 hover:underline">&larr; Submit another document</a>
        
        <?php elseif (!empty($errors)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg" role="alert">
                <p class="font-bold">Errors Found:</p>
                <ul class="list-disc list-inside ml-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (empty($success_message)): ?>
            <form action="new_document.php" method="POST" enctype="multipart/form-data" class="bg-white p-8 rounded-lg shadow-md space-y-6" id="new-doc-form">
                
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
                                <?php echo htmlspecialchars($chief['first_name'] . ' ' . $chief['last_name']); ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if (empty($section_chiefs)): ?>
                            <option value="" disabled>No active Section Chiefs found.</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Upload Document(s) (Up to 10) <span class="text-red-500">*</span></label>
                    
                    <div id="file-list-container" class="mt-2">
                        </div>
                    
                    <label for="document_files_input" class="mt-2 inline-block cursor-pointer px-4 py-2 text-sm font-semibold text-blue-700 bg-blue-50 rounded-md hover:bg-blue-100">
                        + Add File(s)
                    </label>

                    <input type="file" id="document_files_input" name="document_files[]" multiple required 
                           class="hidden">
                    <p class="text-xs text-gray-500 mt-1">Allowed types: PDF, DOC, DOCX, XLS, XLSX. Max size: 15MB per file. Max 10 files.</p>
                    <p id="file-error-msg" class="text-xs text-red-600 mt-1 hidden"></p>
                </div>

                <div>
                    <label for="message" class="block text-sm font-medium text-gray-700">Message / Instructions (Optional)</label>
                    <textarea id="message" name="message" rows="4" 
                              class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                </div>

                <div>
                    <button type="submit" id="submit-button"
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:bg-gray-400 disabled:cursor-not-allowed">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24" style="display: none;">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="button-text">Submit Document</span>
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('document_files_input');
            const fileListContainer = document.getElementById('file-list-container');
            const errorMsg = document.getElementById('file-error-msg');
            const maxFiles = 10;
            
            // This DataTransfer object will hold our "master list"
            const fileStore = new DataTransfer();

            // Handle new files being selected
            fileInput.addEventListener('change', function(e) {
                const newFiles = e.target.files;
                let filesAdded = 0;
                
                // Clear any previous errors
                errorMsg.classList.add('hidden');
                errorMsg.textContent = '';

                // Add new files to the master list
                for (let i = 0; i < newFiles.length; i++) {
                    const file = newFiles[i];
                    
                    // Check file limit
                    if (fileStore.items.length >= maxFiles) {
                        showError(`Cannot add "${file.name}". Maximum of ${maxFiles} files allowed.`);
                        break; // Stop adding files
                    }
                    
                    // Check for duplicates
                    if (!isDuplicate(file)) {
                        fileStore.items.add(file);
                        filesAdded++;
                    }
                }
                
                // Update the input's internal file list
                fileInput.files = fileStore.files;
                
                // Re-render the UI
                renderFileList();

                // Clear the input's value so 'change' fires again
                // even if the same file is re-added
                e.target.value = ''; 
            });

            function isDuplicate(newFile) {
                for (let i = 0; i < fileStore.files.length; i++) {
                    if (fileStore.files[i].name === newFile.name && fileStore.files[i].size === newFile.size) {
                        return true;
                    }
                }
                return false;
            }

            function renderFileList() {
                // Clear the visual list
                fileListContainer.innerHTML = '';
                
                // Update the required attribute
                if (fileStore.files.length > 0) {
                    fileInput.required = false;
                } else {
                    fileInput.required = true;
                }
                
                // Re-draw the visual list
                for (let i = 0; i < fileStore.files.length; i++) {
                    const file = fileStore.files[i];
                    const fileItem = document.createElement('div');
                    fileItem.className = 'file-list-item';
                    
                    // File name and size
                    const fileInfo = document.createElement('span');
                    fileInfo.className = 'text-sm text-gray-700';
                    fileInfo.textContent = `${file.name} (${(file.size / 1024).toFixed(2)} KB)`;
                    
                    // Remove button
                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'ml-4 text-sm font-medium text-red-600 hover:text-red-800';
                    removeBtn.textContent = 'Remove';
                    
                    // Store the index to remove on click
                    removeBtn.dataset.index = i;
                    
                    removeBtn.addEventListener('click', function(e) {
                        removeFile(i);
                    });
                    
                    fileItem.appendChild(fileInfo);
                    fileItem.appendChild(removeBtn);
                    fileListContainer.appendChild(fileItem);
                }
            }
            
            function removeFile(indexToRemove) {
                // Create a new DataTransfer object
                const newFileStore = new DataTransfer();
                
                // Add all files *except* the one at indexToRemove
                for (let i = 0; i < fileStore.files.length; i++) {
                    if (i !== indexToRemove) {
                        newFileStore.items.add(fileStore.files[i]);
                    }
                }
                
                // Replace the old file store with the new one
                fileStore.items.clear();
                for (let i = 0; i < newFileStore.files.length; i++) {
                    fileStore.items.add(newFileStore.files[i]);
                }
                
                // Update the input's internal file list
                fileInput.files = fileStore.files;
                
                // Re-render the UI
                renderFileList();
            }

            function showError(message) {
                errorMsg.textContent = message;
                errorMsg.classList.remove('hidden');
            }

            // --- Form Submission Spinner ---
            const docForm = document.getElementById('new-doc-form');
            const submitButton = document.getElementById('submit-button');
            
            if (docForm && submitButton) {
                docForm.addEventListener('submit', function(e) {
                    // Check our file list for the 'required' logic
                    if (fileStore.files.length === 0) {
                        e.preventDefault(); // Stop submission
                        showError('At least one document file is required.');
                        return;
                    }
                    
                    const spinner = submitButton.querySelector('svg');
                    const buttonText = submitButton.querySelector('.button-text');

                    // Disable button, show spinner, update text
                    submitButton.disabled = true;
                    if (spinner) spinner.style.display = 'inline-block';
                    if (buttonText) buttonText.textContent = 'Submitting...';
                });
            }
        });
    </script>

</body>
</html>