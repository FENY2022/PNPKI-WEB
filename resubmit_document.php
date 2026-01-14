<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$doc_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// 1. Fetch document details
$query = "SELECT * FROM documents WHERE doc_id = ? AND initiator_id = ? AND status = 'Returned'";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $doc_id, $user_id);
$stmt->execute();
$document = $stmt->get_result()->fetch_assoc();

if (!$document) {
    die("Document not found or not eligible for resubmission.");
}

// 2. Fetch the latest message
$message_query = "SELECT message FROM document_actions WHERE doc_id = ? ORDER BY created_at DESC LIMIT 1";
$m_stmt = $conn->prepare($message_query);
$m_stmt->bind_param("i", $doc_id);
$m_stmt->execute();
$last_action = $m_stmt->get_result()->fetch_assoc();

// 3. Handle Resubmission Logic
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $doc_type = $_POST['doc_type'];
    
    $update_query = "UPDATE documents SET title = ?, doc_type = ?, status = 'Review', updated_at = NOW() WHERE doc_id = ?";
    $u_stmt = $conn->prepare($update_query);
    $u_stmt->bind_param("ssi", $title, $doc_type, $doc_id);
    
    if ($u_stmt->execute()) {
        // Log action
        $log_query = "INSERT INTO document_actions (doc_id, user_id, action_type, message) VALUES (?, ?, 'Resubmitted', 'User addressed feedback and uploaded new files.')";
        $l_stmt = $conn->prepare($log_query);
        $l_stmt->bind_param("ii", $doc_id, $user_id);
        $l_stmt->execute();

        // --- MULTIPLE FILE UPLOAD LOGIC ---
        if (!empty($_FILES['new_files']['name'][0])) {
            $target_dir = "uploads/";
            
            // Loop through each file
            foreach ($_FILES['new_files']['name'] as $key => $val) {
                $original_name = basename($_FILES["new_files"]["name"][$key]);
                $file_name = "resub_" . time() . "_" . $original_name;
                $target_file = $target_dir . $file_name;
                
                if (move_uploaded_file($_FILES["new_files"]["tmp_name"][$key], $target_file)) {
                    $f_stmt = $conn->prepare("INSERT INTO document_files (doc_id, file_name, file_path, uploader_id) VALUES (?, ?, ?, ?)");
                    $f_stmt->bind_param("issi", $doc_id, $file_name, $target_file, $user_id);
                    $f_stmt->execute();
                }
            }
        }

        header("Location: my_submitted_documents.php?msg=Resubmitted Successfully");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resubmit Document | PNPKI</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .resubmit-card { border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .message-box { background: #fff3cd; border-left: 5px solid #ffc107; padding: 15px; margin-bottom: 20px; }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card resubmit-card">
                    <div class="card-header bg-warning text-dark py-3">
                        <h4 class="mb-0"><i class="fas fa-file-export me-2"></i> Resubmit Document</h4>
                    </div>
                    <div class="card-body p-4">
                        
                        <div class="message-box">
                            <strong><i class="fas fa-comment-dots"></i> Message from Reviewer:</strong>
                            <p class="mb-0 mt-2 fst-italic text-muted">
                                "<?php echo htmlspecialchars($last_action['message'] ?? 'No message provided.'); ?>"
                            </p>
                        </div>

                        <form action="" method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Document Title</label>
                                <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($document['title']); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Document Type</label>
                                <select name="doc_type" class="form-select">
                                    <option value="Memorandum" <?php if($document['doc_type'] == 'Memorandum') echo 'selected'; ?>>Memorandum</option>
                                    <option value="Letter" <?php if($document['doc_type'] == 'Letter') echo 'selected'; ?>>Letter</option>
                                    <option value="Report" <?php if($document['doc_type'] == 'Report') echo 'selected'; ?>>Report</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Upload Updated Files (Multiple Allowed)</label>
                                <div class="input-group">
                                    <input type="file" name="new_files[]" class="form-control" id="fileInput" multiple>
                                    <label class="input-group-text" for="fileInput"><i class="fas fa-upload"></i></label>
                                </div>
                                <small class="text-muted">You can select multiple files at once. Leave empty to keep existing files.</small>
                            </div>

                            <div class="d-flex justify-content-between pt-3">
                                <a href="my_submitted_documents.php" class="btn btn-outline-secondary px-4">Cancel</a>
                                <button type="submit" class="btn btn-primary px-5">
                                    <i class="fas fa-paper-plane me-2"></i> Submit Corrections
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>