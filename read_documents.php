<?php
// live_document_viewer.php
// Simple Live Document Viewer + Uploader (using Google Docs Viewer)
// Supports: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, ODT, ODS, ODP

// Configuration
$uploadDir = __DIR__ . '/uploads';
$maxFileSize = 25 * 1024 * 1024; // 25 MB
$allowedExt = ['pdf','doc','docx','xls','xlsx','ppt','pptx','odt','ods','odp'];

// Ensure upload folder exists
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Handle upload
$uploadError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadError = 'File upload error code: ' . $file['error'];
    } else if ($file['size'] > $maxFileSize) {
        $uploadError = 'File too large. Max is ' . ($maxFileSize/1024/1024) . ' MB.';
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt)) {
            $uploadError = 'File type not allowed.';
        } else {
            // Sanitize and move uploaded file
            $base = pathinfo($file['name'], PATHINFO_FILENAME);
            $safeBase = preg_replace('/[^A-Za-z0-9\-_]/', '_', $base);
            $target = $uploadDir . '/' . $safeBase . '_' . time() . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $target)) {
                $uploadError = 'Failed to move uploaded file.';
            } else {
                header('Location: ' . $_SERVER['PHP_SELF']);
                exit;
            }
        }
    }
}

// List uploaded files
$files = array_values(array_filter(scandir($uploadDir), function($f) use($uploadDir){
    return is_file($uploadDir . '/' . $f);
}));

// Helper to create a public URL for the uploaded file
function public_url($path) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $scriptDir = dirname($_SERVER['SCRIPT_NAME']);
    $scriptDir = ($scriptDir == '/') ? '' : $scriptDir;
    return $scheme . '://' . $host . $scriptDir . '/' . ltrim($path, '/');
}

function ext($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Live Document Viewer (Google Docs)</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { padding: 20px; }
    .file-list { max-height: 60vh; overflow:auto; }
    .viewer { height: 80vh; border: 1px solid #ddd; }
    iframe { width:100%; height:100%; border:none; }
  </style>
</head>
<body>
<div class="container">
  <h1 class="mb-3">Live Document Viewer (Google Docs)</h1>
  <div class="row">
    <div class="col-md-4">
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">Upload Document</h5>
          <p class="small text-muted">Allowed: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, ODT, ODS, ODP (Max 25MB)</p>
          <?php if ($uploadError): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($uploadError); ?></div>
          <?php endif; ?>
          <form method="post" enctype="multipart/form-data">
            <div class="mb-2">
              <input class="form-control" type="file" name="file" required>
            </div>
            <button class="btn btn-primary" type="submit">Upload</button>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Uploaded Files</h5>
          <div class="file-list list-group">
            <?php if (empty($files)): ?>
              <div class="text-muted">No files uploaded yet.</div>
            <?php else: ?>
              <?php foreach ($files as $f): $u = public_url('uploads/' . rawurlencode($f)); $e = ext($f); ?>
                <a href="#" class="list-group-item list-group-item-action file-item" data-filename="<?php echo htmlspecialchars($f); ?>" data-url="<?php echo htmlspecialchars($u); ?>" data-ext="<?php echo $e; ?>">
                  <?php echo htmlspecialchars($f); ?>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-md-8">
      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Viewer</h5>
          <div id="viewer" class="viewer d-flex align-items-center justify-content-center text-muted">
            Select a file to preview here.
          </div>
          <div class="mt-2">
            <small class="text-muted">Google Docs Viewer requires the file to be publicly accessible via the Internet. Localhost won't work unless you use a tunneling service (like ngrok).</small>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function(){
    const fileItems = document.querySelectorAll('.file-item');
    const viewer = document.getElementById('viewer');

    function clearViewer(){
      viewer.innerHTML = '';
      viewer.classList.remove('text-muted');
    }

    fileItems.forEach(item => {
      item.addEventListener('click', function(e){
        e.preventDefault();
        const url = this.dataset.url;
        const ext = this.dataset.ext.toLowerCase();
        clearViewer();

        let iframe = document.createElement('iframe');
        if (['pdf','doc','docx','xls','xlsx','ppt','pptx','odt','ods','odp'].includes(ext)) {
          iframe.src = 'https://docs.google.com/viewer?url=' + encodeURIComponent(url) + '&embedded=true';
        } else {
          iframe.src = url; // fallback direct
        }
        viewer.appendChild(iframe);
      });
    });
  });
</script>
</body>
</html>
