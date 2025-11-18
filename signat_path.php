<?php
session_start();

// --- 1. Database Connection (Using user-provided credentials) ---
$servername = "153.92.15.60";
$username = "u645536029_otos_root";
$password = "6yI3PF3OZ";
$dbname = "u645536029_otos";

// Establishing connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    // In a real application, you might log the error and display a generic message
    die("Connection failed: " . $conn->connect_error);
}

// Set timezone
date_default_timezone_set('Asia/Manila');

$message = '';

// --- 2. Handle Form Submission (Save Signatory Path) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['doc_id']) && isset($_POST['signatory_path'])) {
    
    // Sanitize and validate input
    $doc_id_to_save = (int)$_POST['doc_id'];
    $signatory_path_str = trim($_POST['signatory_path']);
    // Filter out empty strings before mapping to int
    $signatory_ids = array_filter(array_map('intval', explode(',', $signatory_path_str)));

    if ($doc_id_to_save <= 0) {
        $message = '<div class="alert error">❌ Error: Invalid Document ID provided.</div>';
    } elseif (empty($signatory_ids)) {
        $message = '<div class="alert error">❌ Error: No signatories were selected for the path.</div>';
    } else {
        
        // Start a transaction for atomicity
        $conn->begin_transaction();
        try {
            // A. Delete any existing path for this document (Assumes 'document_signatories' table)
            // NOTE: Using doc_id and simple user_id insert as per user's provided code structure.
            $stmt_delete = $conn->prepare("DELETE FROM document_signatories WHERE doc_id = ?");
            $stmt_delete->bind_param("i", $doc_id_to_save);
            $stmt_delete->execute();
            $stmt_delete->close();

            // B. Insert the new path sequence
            $order = 1;
            // The table structure in your previous request included office_assigned and station_assigned.
            // If you are using the simpler table from the code you just provided (doc_id, user_id, signing_order), 
            // you must adjust the query below. I am using the simplest query to match your provided code.
            $stmt_insert = $conn->prepare("INSERT INTO document_signatories (doc_id, user_id, signing_order) VALUES (?, ?, ?)");

            foreach ($signatory_ids as $user_id) {
                if ($user_id > 0) {
                    $stmt_insert->bind_param("iii", $doc_id_to_save, $user_id, $order);
                    $stmt_insert->execute();
                    $order++;
                }
            }

            $stmt_insert->close();
            $conn->commit();
            $message = '<div class="alert success">✅ Signatory path successfully set for Document ID: <strong>' . $doc_id_to_save . '</strong></div>';

        } catch (Exception $e) {
            $conn->rollback();
            $message = '<div class="alert error">❌ Failed to set signatory path. Database Error.</div>';
            error_log("Signatory Path Save Error: " . $e->getMessage());
        }
    }
}


// --- 3. Fetch Distinct Offices for Filter ---
$offices = [];
$sql_offices = "SELECT DISTINCT Office FROM useremployee WHERE Office IS NOT NULL AND Office != '' ORDER BY Office ASC";
$result_offices = $conn->query($sql_offices);
if ($result_offices) {
    while ($row = $result_offices->fetch_assoc()) {
        $offices[] = $row['Office'];
    }
}

// --- 4. Get Current Filters and Search Term ---
$selected_office = isset($_GET['office']) ? trim($_GET['office']) : '';
$selected_station = isset($_GET['station']) ? trim($_GET['station']) : '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$employees = [];
$stations = [];

// --- 5. Fetch Stations based on Selected Office ---
if (!empty($selected_office)) {
    $sql_stations = $conn->prepare("SELECT DISTINCT Station FROM useremployee WHERE Office = ? AND Station IS NOT NULL AND Station != '' ORDER BY Station ASC");
    $sql_stations->bind_param("s", $selected_office);
    $sql_stations->execute();
    $result_stations = $sql_stations->get_result();
    while ($row = $result_stations->fetch_assoc()) {
        $stations[] = $row['Station'];
    }
    $sql_stations->close();
}


// --- 6. Fetch Employees based on Filters and Search ---
// Note: We include Station in the SELECT for display/data purposes
$sql_employees = "SELECT id, Full_Name, Office, Station, Designation FROM useremployee WHERE 1=1";
$params = [];
$types = '';

if (!empty($selected_office)) {
    $sql_employees .= " AND Office = ?";
    $params[] = $selected_office;
    $types .= 's';
}

if (!empty($selected_station)) {
    $sql_employees .= " AND Station = ?";
    $params[] = $selected_station;
    $types .= 's';
}

if (!empty($search_term)) {
    // Search by Full_Name, Office, or Designation for refinement
    $sql_employees .= " AND (Full_Name LIKE ? OR Office LIKE ? OR Designation LIKE ?)";
    $search_pattern = '%' . $search_term . '%';
    $params[] = $search_pattern;
    $params[] = $search_pattern;
    $params[] = $search_pattern;
    $types .= 'sss';
}

$sql_employees .= " ORDER BY Full_Name ASC LIMIT 100"; 

$stmt_employees = $conn->prepare($sql_employees);

if (!empty($types)) {
    $stmt_employees->bind_param($types, ...$params);
}

if ($stmt_employees->execute()) {
    $result_employees = $stmt_employees->get_result();
    while ($row = $result_employees->fetch_assoc()) {
        $employees[] = $row;
    }
}
$stmt_employees->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Document Signatory Path</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #4895ef;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --success-dark: #3a86ff;
            --danger: #f72585;
            --warning: #f8961e;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --gray-light: #adb5bd;
            --border: #dee2e6;
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --hover-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
            color: var(--dark);
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            padding: 30px 40px;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(30%, -30%);
        }

        .header h1 {
            font-weight: 700;
            font-size: 2.2rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header p {
            font-weight: 400;
            opacity: 0.9;
            max-width: 600px;
        }

        .content {
            padding: 30px 40px;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            border-left: 4px solid transparent;
        }

        .alert.success {
            background-color: rgba(76, 201, 240, 0.1);
            color: var(--success-dark);
            border-left-color: var(--success-dark);
        }

        .alert.error {
            background-color: rgba(247, 37, 133, 0.1);
            color: var(--danger);
            border-left-color: var(--danger);
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 24px;
            margin-bottom: 24px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            box-shadow: var(--hover-shadow);
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title i {
            font-size: 1.2rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.15);
        }

        .form-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .form-col {
            flex: 1;
            min-width: 200px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 500;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--success-dark);
            color: white;
        }

        .btn-success:hover {
            background: #2a75f0;
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            color: var(--gray);
            border: 1.5px solid var(--border);
        }

        .btn-outline:hover {
            background: var(--light);
            color: var(--dark);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #e11573;
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 0.9rem;
        }

        .btn-icon {
            width: 40px;
            height: 40px;
            padding: 0;
            border-radius: 8px;
        }

        .filter-actions {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-top: 16px;
        }

        .dual-list-container {
            display: flex;
            gap: 24px;
            margin-top: 20px;
        }

        .list-panel {
            flex: 1;
            border-radius: 12px;
            background: white;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease;
        }

        .list-panel:hover {
            transform: translateY(-5px);
        }

        .list-header {
            padding: 20px;
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .list-body {
            flex: 1;
            min-height: 400px;
            max-height: 500px;
            overflow-y: auto;
            padding: 0;
        }

        .list-item {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            transition: background-color 0.2s ease;
        }

        .list-item:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }

        .list-item:last-child {
            border-bottom: none;
        }

        .item-info {
            flex: 1;
        }

        .item-name {
            font-weight: 500;
            margin-bottom: 4px;
        }

        .item-details {
            font-size: 0.85rem;
            color: var(--gray);
        }

        .path-list .list-item {
            position: relative;
            padding-left: 60px;
        }

        .path-order {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            width: 32px;
            height: 32px;
            background: var(--primary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .path-controls {
            display: flex;
            gap: 6px;
            margin-right: 10px;
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            color: var(--gray);
            text-align: center;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .submit-section {
            margin-top: 30px;
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        .step-indicator {
            display: flex;
            margin-bottom: 30px;
            position: relative;
        }

        .step {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .step-number {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--light);
            color: var(--gray);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: 600;
            border: 2px solid var(--border);
            transition: all 0.3s ease;
        }

        .step.active .step-number {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .step.completed .step-number {
            background: var(--success-dark);
            color: white;
            border-color: var(--success-dark);
        }

        .step-title {
            font-weight: 500;
            font-size: 0.9rem;
        }

        .step-indicator::before {
            content: "";
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--border);
            z-index: 1;
        }

        .step.active ~ .step .step-number {
            background: var(--light);
            color: var(--gray);
            border-color: var(--border);
        }

        @media (max-width: 992px) {
            .dual-list-container {
                flex-direction: column;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .form-col {
                width: 100%;
            }
            
            .content {
                padding: 20px;
            }
            
            .header {
                padding: 20px;
            }
        }

        @media (max-width: 576px) {
            body {
                padding: 10px;
            }
            
            .header h1 {
                font-size: 1.8rem;
            }
            
            .filter-actions {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-sitemap"></i> Document Signatory Path</h1>
            <p>Define the approval sequence for your documents by setting up a signatory path</p>
        </div>
        
        <div class="content">
            <?php echo $message; ?>
            
            <div class="step-indicator">
                <div class="step active">
                    <div class="step-number">1</div>
                    <div class="step-title">Document ID</div>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-title">Filter Employees</div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-title">Set Path</div>
                </div>
                <div class="step">
                    <div class="step-number">4</div>
                    <div class="step-title">Save</div>
                </div>
            </div>
            
            <form method="POST" action="" onsubmit="return prepareFormSubmission(event);" id="main-path-form">
                <div class="card">
                    <h2 class="card-title"><i class="fas fa-file-alt"></i> Document Information</h2>
                    <div class="form-group">
                        <label class="form-label" for="doc_id">Document ID</label>
                        <input type="number" id="doc_id" name="doc_id" class="form-control" placeholder="Enter the document ID" required value="<?php echo isset($_POST['doc_id']) ? htmlspecialchars($_POST['doc_id']) : ''; ?>">
                        <small style="color: var(--gray); margin-top: 6px; display: block;">Enter the Document ID to proceed with setting up the signatory path.</small>
                    </div>
                    <input type="hidden" name="signatory_path" id="signatory_path_input">
                </div>
            </form>
            
            <form id="filter-form" method="GET" action="">
                <div class="card">
                    <h2 class="card-title"><i class="fas fa-filter"></i> Filter Employees</h2>
                    <div class="form-row">
                        <div class="form-col">
                            <label class="form-label" for="office">Office</label>
                            <select name="office" id="office" class="form-control" onchange="this.form.submit()">
                                <option value="">-- Select Office --</option>
                                <?php foreach ($offices as $office): ?>
                                    <option value="<?php echo htmlspecialchars($office); ?>" 
                                            <?php echo ($selected_office == $office) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($office); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-col">
                            <label class="form-label" for="station">Station</label>
                            <select name="station" id="station" class="form-control" onchange="this.form.submit()" <?php echo empty($selected_office) ? 'disabled' : ''; ?>>
                                <option value="">-- Select Station --</option>
                                <?php foreach ($stations as $station): ?>
                                    <option value="<?php echo htmlspecialchars($station); ?>" 
                                            <?php echo ($selected_station == $station) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($station); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="search">Search</label>
                        <input type="text" id="search" name="search" class="form-control" placeholder="Search by name, office, or designation" value="<?php echo htmlspecialchars($search_term); ?>">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                        <?php if (!empty($selected_office) || !empty($selected_station) || !empty($search_term)): ?>
                            <a href="signat_path.php" class="btn btn-outline">
                                <i class="fas fa-times"></i> Clear Filters
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
            
            <div class="card">
                <h2 class="card-title"><i class="fas fa-project-diagram"></i> Set Signatory Path</h2>
                <p style="margin-bottom: 20px; color: var(--gray);">Add employees to the signatory path and arrange them in the desired approval sequence.</p>
                
                <div class="dual-list-container">
                    <div class="list-panel">
                        <div class="list-header">
                            <i class="fas fa-users"></i> Available Employees
                        </div>
                        <div class="list-body" id="employee-list">
                            <?php if (empty($selected_office)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-users"></i>
                                    <p>Please select an office to view employees</p>
                                </div>
                            <?php elseif (empty($employees)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-search"></i>
                                    <p>No employees found for the selected criteria</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($employees as $emp): ?>
                                    <div class="list-item" 
                                        data-id="<?php echo htmlspecialchars($emp['id']); ?>" 
                                        data-name="<?php echo htmlspecialchars($emp['Full_Name']); ?>"
                                        data-office="<?php echo htmlspecialchars($emp['Office']); ?>"
                                        data-station="<?php echo htmlspecialchars($emp['Station']); ?>"
                                        data-designation="<?php echo htmlspecialchars($emp['Designation']); ?>">
                                        <div class="item-info">
                                            <div class="item-name"><?php echo htmlspecialchars($emp['Full_Name']); ?></div>
                                            <div class="item-details"><?php echo htmlspecialchars($emp['Designation'] . ' • ' . $emp['Office'] . (empty($emp['Station']) ? '' : ' • ' . $emp['Station'])); ?></div>
                                        </div>
                                        <button type="button" class="btn btn-success btn-sm" onclick="addSignatory(this.parentNode)">
                                            <i class="fas fa-plus"></i> Add
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="list-panel path-list">
                        <div class="list-header">
                            <i class="fas fa-list-ol"></i> Signatory Path
                        </div>
                        <div class="list-body" id="path-list">
                            <div class="empty-state">
                                <i class="fas fa-arrow-left"></i>
                                <p>Add employees to create a signatory path</p>
                                <small>The order will determine the approval sequence</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="submit-section">
                <button type="submit" form="main-path-form" class="btn btn-success" style="padding: 14px 40px; font-size: 1.1rem;">
                    <i class="fas fa-save"></i> Save Signatory Path
                </button>
            </div>
        </div>
    </div>

    <script>
        const pathList = document.getElementById('path-list');
        const pathInput = document.getElementById('signatory_path_input');
        
        // Function to ensure the path list reflects the correct order numbers and button states
        function updatePathOrder() {
            const items = pathList.querySelectorAll('.list-item[data-id]');
            
            if (items.length === 0) {
                pathList.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-arrow-left"></i>
                        <p>Add employees to create a signatory path</p>
                        <small>The order will determine the approval sequence</small>
                    </div>`;
            } else {
                // Remove the initial empty message if it exists
                const emptyMessage = pathList.querySelector('.empty-state');
                if(emptyMessage) emptyMessage.remove();

                items.forEach((item, index) => {
                    let orderSpan = item.querySelector('.path-order');
                    if (!orderSpan) {
                        orderSpan = document.createElement('div');
                        orderSpan.className = 'path-order';
                        item.prepend(orderSpan);
                    }
                    orderSpan.textContent = (index + 1);

                    // Update button states
                    const upButton = item.querySelector('.btn-up');
                    const downButton = item.querySelector('.btn-down');

                    if (upButton) upButton.disabled = (index === 0);
                    if (downButton) downButton.disabled = (index === items.length - 1);
                });
            }
        }
        
        // 1. Add Signatory
        window.addSignatory = function(element) {
            const id = element.dataset.id;
            const name = element.dataset.name;
            const office = element.dataset.office;
            const station = element.dataset.station;
            const designation = element.dataset.designation;

            // Prevent adding the same person multiple times 
            const existingItem = pathList.querySelector(`.list-item[data-id="${id}"]`);
            if (existingItem) {
                alert(name + ' is already in the path.');
                return;
            }

            const itemDiv = document.createElement('div');
            itemDiv.className = 'list-item';
            itemDiv.dataset.id = id;
            
            itemDiv.innerHTML = `
                <div class="path-order"></div>
                <div class="item-info">
                    <div class="item-name">${name}</div>
                    <div class="item-details">${designation} • ${office}${station ? ' • ' + station : ''}</div>
                </div>
                <div class="path-controls">
                    <button type="button" class="btn btn-outline btn-icon btn-up" onclick="moveSignatory(this.parentNode.parentNode, 'up')">
                        <i class="fas fa-arrow-up"></i>
                    </button>
                    <button type="button" class="btn btn-outline btn-icon btn-down" onclick="moveSignatory(this.parentNode.parentNode, 'down')">
                        <i class="fas fa-arrow-down"></i>
                    </button>
                </div>
                <button type="button" class="btn btn-danger btn-icon" onclick="removeSignatory(this.parentNode)">
                    <i class="fas fa-times"></i>
                </button>
            `;

            pathList.appendChild(itemDiv);
            updatePathOrder();
        }
        
        // 2. Remove Signatory
        window.removeSignatory = function(element) {
            element.remove();
            updatePathOrder();
        }

        // 3. Reorder Signatory using buttons
        window.moveSignatory = function(element, direction) {
            const parent = element.parentNode;
            const siblings = Array.from(parent.children).filter(item => item.dataset.id); // Filter out the empty message
            const index = siblings.indexOf(element);
            
            if (direction === 'up' && index > 0) {
                parent.insertBefore(element, siblings[index - 1]);
            } else if (direction === 'down' && index < siblings.length - 1) {
                parent.insertBefore(element, siblings[index + 1].nextSibling);
            }
            
            updatePathOrder();
        }

        // 4. Prepare data for POST submission
        window.prepareFormSubmission = function(event) {
            const docIdInput = document.getElementById('doc_id');
            if (docIdInput.value.trim() === '' || parseInt(docIdInput.value) <= 0) {
                alert('Please enter a valid Document ID before saving the path.');
                docIdInput.focus();
                event.preventDefault();
                return false;
            }

            const items = pathList.querySelectorAll('.list-item[data-id]');
            if (items.length === 0) {
                alert('The signatory path is empty. Please add at least one signatory.');
                event.preventDefault();
                return false;
            }

            // Construct a comma-separated string of User IDs in order (as required by PHP submission logic)
            const signatoryIds = Array.from(items).map(item => item.dataset.id).join(',');
            pathInput.value = signatoryIds;

            return true; // Allow form submission
        }

        // 5. Handle Office dropdown change for filter submission (submits the form)
        document.getElementById('office').addEventListener('change', function() {
            // Clear the Station filter when the Office changes to ensure valid selection
            document.getElementById('station').value = ''; 
        });

        // Initialize the path display when the page loads
        document.addEventListener('DOMContentLoaded', updatePathOrder);
        
        // Update step indicator based on user progress
        document.addEventListener('DOMContentLoaded', function() {
            const docIdInput = document.getElementById('doc_id');
            const steps = document.querySelectorAll('.step');
            
            function updateSteps() {
                if (docIdInput.value && parseInt(docIdInput.value) > 0) {
                    steps[0].classList.add('completed');
                    steps[1].classList.add('active');
                    
                    const employeeList = document.getElementById('employee-list');
                    const hasEmployees = employeeList.querySelector('.list-item') !== null;
                    
                    if (hasEmployees) {
                        steps[1].classList.add('completed');
                        steps[2].classList.add('active');
                        
                        const pathItems = pathList.querySelectorAll('.list-item[data-id]');
                        if (pathItems.length > 0) {
                            steps[2].classList.add('completed');
                            steps[3].classList.add('active');
                        }
                    }
                }
            }
            
            docIdInput.addEventListener('input', updateSteps);
            updateSteps();
        });
    </script>
</body>
</html>