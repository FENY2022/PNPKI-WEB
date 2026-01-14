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
        $log_query = "INSERT INTO document_actions (doc_id, user_id, action_type, message) VALUES (?, ?, 'Resubmitted', 'User addressed feedback and uploaded new files.')";
        $l_stmt = $conn->prepare($log_query);
        $l_stmt->bind_param("ii", $doc_id, $user_id);
        $l_stmt->execute();

        // Updated Multiple File Upload Logic with Titles
        if (!empty($_FILES['new_files']['name'][0])) {
            $target_dir = "uploads/";
            $titles = $_POST['file_titles'];

            foreach ($_FILES['new_files']['name'] as $key => $val) {
                $original_name = basename($_FILES["new_files"]["name"][$key]);
                $custom_title = !empty($titles[$key]) ? $titles[$key] : $original_name;
                $file_name = "resub_" . time() . "_" . $original_name;
                $target_file = $target_dir . $file_name;
                
                if (move_uploaded_file($_FILES["new_files"]["tmp_name"][$key], $target_file)) {
                    $f_stmt = $conn->prepare("INSERT INTO document_files (doc_id, file_name, file_path, uploader_id) VALUES (?, ?, ?, ?)");
                    $f_stmt->bind_param("issi", $doc_id, $custom_title, $target_file, $user_id);
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { --pnpki-blue: #0d6efd; --pnpki-light: #f8f9fa; }
        body { font-family: 'Inter', sans-serif; background-color: #f0f2f5; color: #333; }
        
        .resubmit-card { border: none; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); overflow: hidden; }
        .card-header { background: linear-gradient(135deg, #ffc107 0%, #ffca2c 100%); border: none; padding: 1.5rem; }
        
        .message-box { background: #fffdf5; border: 1px solid #ffeeba; border-left: 5px solid #ffc107; border-radius: 8px; padding: 20px; margin-bottom: 2rem; }
        .message-box strong { color: #856404; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
        
        .section-label { font-size: 0.85rem; font-weight: 700; text-transform: uppercase; color: #6c757d; margin-bottom: 1rem; display: block; border-bottom: 2px solid #eee; padding-bottom: 5px; }
        
        .file-upload-row { background: white; border: 1px solid #e0e0e0; border-radius: 8px; padding: 15px; margin-bottom: 12px; transition: all 0.2s; position: relative; }
        .file-upload-row:hover { border-color: var(--pnpki-blue); box-shadow: 0 4px 12px rgba(13, 110, 253, 0.05); }
        
        .btn-remove { position: absolute; top: -10px; right: -10px; width: 25px; height: 25px; border-radius: 50%; padding: 0; line-height: 22px; font-size: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        
        .form-control, .form-select { border-radius: 8px; padding: 0.6rem 1rem; border: 1px solid #ced4da; }
        .form-control:focus { box-shadow: 0 0 0 4px rgba(13, 110, 253, 0.1); border-color: var(--pnpki-blue); }
        
        .btn-add-file { border-style: dashed; border-width: 2px; font-weight: 600; color: var(--pnpki-blue); }
        .btn-add-file:hover { background-color: rgba(13, 110, 253, 0.05); }
        
        .footer-actions { background: #fafafa; border-top: 1px solid #eee; padding: 1.5rem 2rem; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-9 col-xl-8">
                <div class="card resubmit-card">
                    <div class="card-header text-dark">
                        <div class="d-flex align-items-center">
                            <div class="bg-white rounded-circle p-2 me-3 shadow-sm">
                                <i class="fas fa-file-signature text-warning fa-lg"></i>
                            </div>
                            <div>
                                <h4 class="mb-0 fw-bold">Correction & Resubmission</h4>
                                <p class="mb-0 opacity-75 small">Document ID: #<?php echo $doc_id; ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-4 p-md-5">
                        <div class="message-box">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-exclamation-circle text-warning mt-1 me-3 fa-lg"></i>
                                <div>
                                    <strong>Instructions from Reviewer</strong>
                                    <p class="mb-0 mt-2 text-dark lead italic">
                                        "<?php echo htmlspecialchars($last_action['message'] ?? 'Please review the details below.'); ?>"
                                    </p>
                                </div>
                            </div>
                        </div>

                        <form action="" method="POST" enctype="multipart/form-data" id="resubmitForm">
                            <span class="section-label">General Information</span>
                            <div class="row g-3 mb-4">
                                <div class="col-md-8">
                                    <label class="form-label small fw-bold">Document Title</label>
                                    <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($document['title']); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Document Type</label>
                                    <select name="doc_type" class="form-select">
                                        <option value="Memorandum" <?php if($document['doc_type'] == 'Memorandum') echo 'selected'; ?>>Memorandum</option>
                                        <option value="Letter" <?php if($document['doc_type'] == 'Letter') echo 'selected'; ?>>Letter</option>
                                        <option value="Report" <?php if($document['doc_type'] == 'Report') echo 'selected'; ?>>Report</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="section-label mb-0 border-0">Attachments & Supporting Files</span>
                                    <button type="button" class="btn btn-sm btn-outline-primary btn-add-file px-3" onclick="addFileRow()">
                                        <i class="fas fa-plus-circle me-1"></i> Add Another File
                                    </button>
                                </div>
                                
                                <div id="fileContainer">
                                    <div class="file-upload-row shadow-sm">
                                        <div class="row g-2 align-items-center">
                                            <div class="col-md-5">
                                                <input type="text" name="file_titles[]" class="form-control form-control-sm" placeholder="File Display Name (e.g. Scanned ID)" required>
                                            </div>
                                            <div class="col-md-7">
                                                <div class="input-group input-group-sm">
                                                    <input type="file" name="new_files[]" class="form-control" required>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="alert alert-info py-2 px-3 mt-3 border-0 small">
                                    <i class="fas fa-info-circle me-2"></i> Multiple files can be uploaded. Titles help reviewers identify documents quickly.
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center mt-5 pt-3 border-top">
                                <a href="my_submitted_documents.php" class="btn btn-link text-decoration-none text-muted fw-bold">
                                    <i class="fas fa-arrow-left me-1"></i> Back to List
                                </a>
                                <button type="submit" class="btn btn-primary px-5 py-2 shadow fw-bold">
                                    <i class="fas fa-paper-plane me-2"></i> Submit Corrections
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function addFileRow() {
            const container = document.getElementById('fileContainer');
            const row = document.createElement('div');
            row.className = 'file-upload-row animate__animated animate__fadeInUp';
            row.innerHTML = `
                <button type="button" class="btn btn-danger btn-remove" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
                <div class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <input type="text" name="file_titles[]" class="form-control form-control-sm" placeholder="File Display Name" required>
                    </div>
                    <div class="col-md-7">
                        <div class="input-group input-group-sm">
                            <input type="file" name="new_files[]" class="form-control" required>
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(row);
        }
    </script>
</body>
</html>