<?php



 $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

session_start();

// --- 1. Database Configuration Settings ---
define('WRITE_DB_HOST', 'localhost');
define('WRITE_DB_USER', 'root');
define('WRITE_DB_PASS', '');
define('WRITE_DB_NAME', 'ddts_pnpki');

define('READ_DB_HOST', '153.92.15.60');
define('READ_DB_USER', 'u645536029_otos_root');
define('READ_DB_PASS', '6yI3PF3OZ');
define('READ_DB_NAME', 'u645536029_otos');

// --- 2. Establish Dual Connections ---
mysqli_report(MYSQLI_REPORT_OFF);

// A. WRITE connection
$write_conn = @new mysqli(WRITE_DB_HOST, WRITE_DB_USER, WRITE_DB_PASS, WRITE_DB_NAME);
if ($write_conn->connect_error) {
    die("Fatal Error: Could not connect to Local Database: " . $write_conn->connect_error);
}
$write_conn->set_charset("utf8mb4");

// B. READ connection
$read_conn = @new mysqli(READ_DB_HOST, READ_DB_USER, READ_DB_PASS, READ_DB_NAME);
if ($read_conn->connect_error) {
    die("Fatal Error: Could not connect to International Database: " . $read_conn->connect_error);
}
$read_conn->set_charset("utf8mb4");

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// --- 3. Table Structure Management (UPDATED) ---
$table_name = 'document_signatories';

// Check if table exists
$sql_check_table = "SHOW TABLES LIKE '{$table_name}'";
$result_check_table = $write_conn->query($sql_check_table);

if ($result_check_table->num_rows == 0) {
    // Create table with NEW batch_id column
    $create_sql = "CREATE TABLE {$table_name} (
        id INT AUTO_INCREMENT PRIMARY KEY,
        batch_id VARCHAR(50) DEFAULT NULL,
        user_id INT NOT NULL,
        signing_order INT NOT NULL,
        office_assigned VARCHAR(255) DEFAULT NULL,
        station_assigned VARCHAR(255) DEFAULT NULL,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    
    if ($write_conn->query($create_sql) !== TRUE) {
        die("Error creating {$table_name} table: " . $write_conn->error);
    }
} else {
    // Check for new columns (Added batch_id here)
    $columns = ['office_assigned', 'station_assigned', 'batch_id'];
    foreach ($columns as $col) {
        $sql_check_column = "SHOW COLUMNS FROM {$table_name} LIKE '{$col}'";
        $result_check_column = $write_conn->query($sql_check_column);
        
        if ($result_check_column->num_rows == 0) {
            // Add column if missing
            $alter_sql = "ALTER TABLE {$table_name} ADD COLUMN {$col} VARCHAR(255) DEFAULT NULL";
            if ($write_conn->query($alter_sql) !== TRUE) {
                error_log("Error altering {$table_name} table to add {$col}: " . $write_conn->error);
            }
        }
    }
}

date_default_timezone_set('Asia/Manila');
$message = '';

// --- 4. Handle Form Submission (UPDATED) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['signatory_path_data'])) {
    
    $signatory_path_json = $_POST['signatory_path_data'];
    $signatory_path = json_decode($signatory_path_json, true);

    if (empty($signatory_path) || !is_array($signatory_path)) {
        $message = '<div class="alert error"><i class="fas fa-exclamation-circle"></i> Error: No valid signatories selected.</div>';
    } else {
        
        $write_conn->begin_transaction();
        try {
            // 1. Clear existing global path
            $write_conn->query("DELETE FROM document_signatories"); 

            // 2. Generate a unique Batch ID for this set of inserts
            // Format: BATCH-YYYYMMDD-RANDOM
            $batch_id = 'BATCH-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));

            // 3. Insert with batch_id
            $stmt_insert = $write_conn->prepare("INSERT INTO document_signatories (batch_id, user_id, signing_order, office_assigned, station_assigned) VALUES (?, ?, ?, ?, ?)");
            $order = 1;

            foreach ($signatory_path as $item) {
                $user_id = (int)($item['id'] ?? 0);
                $office = $item['office'] ?? null;
                $station = $item['station'] ?? null;

                if ($user_id > 0) {
                    // Bind parameters: s (batch_id), i (user_id), i (order), s (office), s (station)
                    $stmt_insert->bind_param("siiss", $batch_id, $user_id, $order, $office, $station); 
                    $stmt_insert->execute();
                    $order++;
                }
            }

            $stmt_insert->close();
            $write_conn->commit();
            $message = '<div class="alert success"><i class="fas fa-check-circle"></i> Global Signatory Path set (Batch ID: ' . $batch_id . ').</div>';

        } catch (Exception $e) {
            $write_conn->rollback();
            $message = '<div class="alert error"><i class="fas fa-exclamation-circle"></i> Failed to set path. ' . $e->getMessage() . '</div>';
            error_log("Signatory Path Save Error: " . $e->getMessage());
        }
    }
}

// --- 5. Fetch Distinct Offices (Unchanged) ---
$offices = [];
$sql_offices = "SELECT DISTINCT Office FROM useremployee WHERE Office IS NOT NULL AND Office != '' ORDER BY Office ASC";
$result_offices = $read_conn->query($sql_offices);
if ($result_offices) {
    while ($row = $result_offices->fetch_assoc()) {
        $offices[] = $row['Office'];
    }
}

// --- 6. Get Filters (Unchanged) ---
$selected_office = isset($_GET['office']) ? trim($_GET['office']) : '';
$selected_station = isset($_GET['station']) ? trim($_GET['station']) : '';
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$employees = [];
$stations = [];

// --- 7. Fetch Stations (Unchanged) ---
if (!empty($selected_office)) {
    $sql_stations = $read_conn->prepare("SELECT DISTINCT Station FROM useremployee WHERE Office = ? AND Station IS NOT NULL AND Station != '' ORDER BY Station ASC");
    $sql_stations->bind_param("s", $selected_office);
    $sql_stations->execute();
    $result_stations = $sql_stations->get_result();
    while ($row = $result_stations->fetch_assoc()) {
        $stations[] = $row['Station'];
    }
    $sql_stations->close();
}

// --- 8. Fetch Employees (Unchanged) ---
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
    $sql_employees .= " AND (Full_Name LIKE ? OR Office LIKE ? OR Designation LIKE ?)";
    $search_pattern = '%' . $search_term . '%';
    $params[] = $search_pattern;
    $params[] = $search_pattern;
    $params[] = $search_pattern;
    $types .= 'sss';
}

$sql_employees .= " ORDER BY Full_Name ASC LIMIT 100"; 
$stmt_employees = $read_conn->prepare($sql_employees);

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

if ($write_conn) $write_conn->close();
if ($read_conn) $read_conn->close();
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

        /* --- Alerts --- */
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

        /* --- Card Styling --- */
        .card {
            background: white;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid var(--border); /* Added subtle border */
        }

        .card-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 10px;
        }

        /* --- Form Elements --- */
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
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        /* Button Colors (Updated to be more vibrant/modern) */
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--secondary); transform: translateY(-1px); }
        .btn-success { background: var(--success-dark); color: white; }
        .btn-success:hover { background: #2a75f0; transform: translateY(-1px); }
        .btn-outline { background: transparent; color: var(--gray); border: 1.5px solid var(--border); }
        .btn-outline:hover { background: var(--light); color: var(--dark); }
        .btn-danger { background: var(--danger); color: white; }
        .btn-danger:hover { background: #e11573; transform: translateY(-1px); }

        .btn-icon { width: 40px; height: 40px; padding: 0; border-radius: 8px; }

        .filter-actions {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-top: 16px;
        }

        /* --- Dual List Container --- */
        .dual-list-container {
            display: flex;
            gap: 24px;
            margin-top: 20px;
        }

        .list-panel {
            flex: 1;
            border-radius: 12px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border: 1px solid var(--border);
        }

        .list-header {
            padding: 15px 20px;
            background: var(--primary);
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 3px solid var(--primary-light);
        }

        .list-body {
            flex: 1;
            min-height: 400px;
            max-height: 500px;
            overflow-y: auto;
            padding: 0;
            list-style: none;
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
        
        .item-info { flex: 1; }
        .item-name { font-weight: 600; margin-bottom: 4px; color: var(--dark); }
        .item-details { font-size: 0.85rem; color: var(--gray); }

        /* --- Signatory Path Specifics --- */
        .path-list .list-item {
            position: relative;
            padding-left: 60px;
        }

        .path-order {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            width: 32px;
            height: 32px;
            background: var(--success-dark);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
            box-shadow: 0 2px 5px rgba(58, 134, 255, 0.3);
        }

        .path-controls {
            display: flex;
            gap: 6px;
            margin-right: 10px;
        }
        
        .path-controls .btn {
            background: var(--light);
            color: var(--primary);
            border: 1px solid var(--border);
        }
        .path-controls .btn:hover:not(:disabled) {
            background: #e9ecef;
            transform: none;
        }
        .path-controls .btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            background: var(--light);
        }

        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            color: var(--gray);
            text-align: center;
            height: 100%;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        .empty-state small {
            margin-top: 10px;
            color: var(--primary);
            font-weight: 500;
        }

        .submit-section {
            margin-top: 30px;
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }

        /* --- Step Indicator --- */
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
            padding: 0 50px;
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

        .step.completed .step-number {
            background: var(--success-dark);
            color: white;
            border-color: var(--success-dark);
        }
        .step.active .step-number {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: scale(1.1);
        }

        .step-title {
            font-weight: 500;
            font-size: 0.9rem;
            color: var(--gray);
        }
        .step.active .step-title, .step.completed .step-title {
            color: var(--dark);
        }

        .step-indicator::before {
            content: "";
            position: absolute;
            top: 20px;
            left: 50px;
            right: 50px;
            height: 2px;
            background: var(--border);
            z-index: 1;
        }

        #loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.8);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .dual-list-container {
                flex-direction: column;
            }
            .step-indicator {
                padding: 0 10px;
            }
            .step-indicator::before {
                left: 10px;
                right: 10px;
            }
            .list-panel {
                min-height: 400px;
            }
        }
        @media (max-width: 576px) {
            .form-row { flex-direction: column; gap: 0; }
            .filter-actions { flex-direction: column; align-items: stretch; }
            .btn { width: 100%; justify-content: center; }
            .header { padding: 20px; }
            .content { padding: 20px; }
            .step-indicator { flex-wrap: wrap; }
            .step { flex: 0 0 50%; margin-bottom: 20px; }
            .step:nth-child(even) { text-align: left; }
            .step:nth-child(odd) { text-align: right; }
            .step-indicator::before { display: none; }
        }
    </style>
</head>
<body>
    <div id="loading-overlay">
        <i class="fas fa-spinner fa-spin fa-3x" style="color: var(--primary);"></i>
    </div>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-sitemap"></i> Global Signatory Path Setup</h1>
            <p>Define the default and overall approval sequence for all documents by filtering employees and setting their signing order.</p>
        </div>
        
        <div class="content">
            <?php echo $message; ?>
            
            <div class="step-indicator">
                <div class="step active" id="step-filter">
                    <div class="step-number">1</div>
                    <div class="step-title">Filter Employees</div>
                </div>
                <div class="step" id="step-path">
                    <div class="step-number">2</div>
                    <div class="step-title">Set Path Sequence</div>
                </div>
                <div class="step" id="step-save">
                    <div class="step-number">3</div>
                    <div class="step-title">Save Path</div>
                </div>
            </div>
            
            <form method="POST" action="" onsubmit="return prepareFormSubmission(event);" id="main-path-form">
                <input type="hidden" name="signatory_path_data" id="signatory_path_input">
            </form>
            
            <form id="filter-form" method="GET" action="">
                <div class="card">
                    <h2 class="card-title"><i class="fas fa-filter"></i> Step 1: Filter Employees</h2>
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
                        <label class="form-label" for="search">Refine Search</label>
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
                <h2 class="card-title"><i class="fas fa-project-diagram"></i> Step 2: Set Path Sequence</h2>
                
                <div class="dual-list-container">
                    <div class="list-panel">
                        <div class="list-header">
                            <i class="fas fa-users"></i> Available Employees
                        </div>
                        <div class="list-body" id="employee-list">
                            <?php if (empty($selected_office)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-filter"></i>
                                    <p>Select an **Office** in Step 1 to view employees.</p>
                                </div>
                            <?php elseif (empty($employees)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-search"></i>
                                    <p>No employees found for the selected criteria.</p>
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
                                <p>Add employees to create a signatory path.</p>
                                <small>Use the Up/Down buttons to manage the order.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="submit-section">
                <button type="submit" form="main-path-form" class="btn btn-success" id="btn-save-path">
                    <i class="fas fa-save"></i> Step 3: Save Signatory Path
                </button>
            </div>
        </div>
    </div>

    <script>
        const pathList = document.getElementById('path-list');
        const pathInput = document.getElementById('signatory_path_input');
        const loadingOverlay = document.getElementById('loading-overlay');
        
        // Function to ensure the path list reflects the correct order numbers and button states
        function updatePathOrder() {
            const items = pathList.querySelectorAll('.list-item[data-id]');
            
            if (items.length === 0) {
                pathList.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-arrow-left"></i>
                        <p>Add employees to create a signatory path.</p>
                        <small>Use the Up/Down buttons to manage the order.</small>
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
            updateSteps(); // Call step update whenever the path changes
        }
        
        function savePathToLocal() {
            // Collect all necessary data including office and station
            const items = pathList.querySelectorAll('.list-item[data-id]');
            const pathData = Array.from(items).map(item => ({
                id: item.dataset.id,
                name: item.dataset.name,
                office: item.dataset.office,
                station: item.dataset.station,
                designation: item.dataset.designation
            }));
            // Save the complete structured data to local storage
            localStorage.setItem('signatoryPath', JSON.stringify(pathData));
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
            itemDiv.dataset.name = name;
            itemDiv.dataset.office = office; // Store office string
            itemDiv.dataset.station = station; // Store station string
            itemDiv.dataset.designation = designation;
            
            itemDiv.innerHTML = `
                <div class="path-order"></div>
                <div class="item-info">
                    <div class="item-name">${name}</div>
                    <div class="item-details">${designation} • ${office}${station ? ' • ' + station : ''}</div>
                </div>
                <div class="path-controls">
                    <button type="button" class="btn btn-outline btn-icon btn-up btn-sm" onclick="moveSignatory(this.parentNode.parentNode, 'up')">
                        <i class="fas fa-arrow-up"></i>
                    </button>
                    <button type="button" class="btn btn-outline btn-icon btn-down btn-sm" onclick="moveSignatory(this.parentNode.parentNode, 'down')">
                        <i class="fas fa-arrow-down"></i>
                    </button>
                </div>
                <button type="button" class="btn btn-danger btn-icon btn-sm" onclick="removeSignatory(this.parentNode)">
                    <i class="fas fa-times"></i>
                </button>
            `;

            pathList.appendChild(itemDiv);
            updatePathOrder();
            savePathToLocal();
        }
        
        // 2. Remove Signatory
        window.removeSignatory = function(element) {
            element.remove();
            updatePathOrder();
            savePathToLocal();
        }

        // 3. Reorder Signatory using buttons
        window.moveSignatory = function(element, direction) {
            const parent = element.parentNode;
            const siblings = Array.from(parent.children).filter(item => item.dataset.id); 
            const index = siblings.indexOf(element);
            
            if (direction === 'up' && index > 0) {
                parent.insertBefore(element, siblings[index - 1]);
            } else if (direction === 'down' && index < siblings.length - 1) {
                parent.insertBefore(element, siblings[index + 1].nextSibling);
            }
            
            updatePathOrder();
            savePathToLocal();
        }

        // 4. Prepare data for POST submission
        window.prepareFormSubmission = function(event) {
            const items = pathList.querySelectorAll('.list-item[data-id]');
            if (items.length === 0) {
                alert('The signatory path is empty. Add at least one signatory.');
                event.preventDefault();
                return false;
            }

            // Construct a structured array of objects with ID, office, and station
            const pathData = Array.from(items).map(item => ({
                id: item.dataset.id,
                office: item.dataset.office,
                station: item.dataset.station
            }));
            
            // JSON-encode the structured data and place it in the hidden input
            pathInput.value = JSON.stringify(pathData);

            loadingOverlay.style.display = 'flex';
            return true; // Allow form submission
        }

        // 5. Handle Office dropdown change for filter submission (submits the form)
        document.getElementById('office').addEventListener('change', function() {
            // Clear the Station filter when the Office changes to ensure valid selection
            document.getElementById('station').value = ''; 
        });


        // --- UI Step Indicator Logic (UPDATED FOR 3 STEPS) ---
        const stepFilter = document.getElementById('step-filter');
        const stepPath = document.getElementById('step-path');
        const stepSave = document.getElementById('step-save');
        const employeeListDiv = document.getElementById('employee-list');
        const steps = [stepFilter, stepPath, stepSave];

        function updateSteps() {
            // Reset all steps to inactive state
            steps.forEach(s => s.classList.remove('active', 'completed'));

            let currentStep = 1;
            const selectedOffice = document.getElementById('office').value;

            // Step 1: Filter Employees Check
            const employeeListChildren = employeeListDiv.children.length;
            const hasEmployees = employeeListChildren > 0 && employeeListDiv.querySelector('.list-item');

            if (selectedOffice || hasEmployees || employeeListChildren > 0) {
                stepFilter.classList.add('completed');
                currentStep = 2;
            } else {
                stepFilter.classList.add('active');
            }

            // Step 2: Set Path Check
            if (currentStep === 2) {
                const pathItems = pathList.querySelectorAll('.list-item[data-id]');
                if (pathItems.length > 0) {
                    stepPath.classList.add('completed');
                    currentStep = 3;
                } else {
                    stepPath.classList.add('active');
                }
            } else if (currentStep > 2) {
                stepPath.classList.add('completed');
            }

            // Step 3: Save
            if (currentStep === 3) {
                stepSave.classList.add('active');
            }
        }
        

        // Initialize the path display and steps when the page loads
        document.addEventListener('DOMContentLoaded', () => {
            const successAlert = document.querySelector('.alert.success');
            if (successAlert) {
                localStorage.removeItem('signatoryPath');
                pathList.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-arrow-left"></i>
                        <p>Add employees to create a signatory path.</p>
                        <small>Use the Up/Down buttons to manage the order.</small>
                    </div>`;
            }

            const savedPath = localStorage.getItem('signatoryPath');
            if (savedPath) {
                const pathData = JSON.parse(savedPath);
                pathData.forEach(data => {
                    const itemDiv = document.createElement('div');
                    itemDiv.className = 'list-item';
                    itemDiv.dataset.id = data.id;
                    itemDiv.dataset.name = data.name;
                    itemDiv.dataset.office = data.office;
                    itemDiv.dataset.station = data.station;
                    itemDiv.dataset.designation = data.designation;
                    itemDiv.innerHTML = `
                        <div class="path-order"></div>
                        <div class="item-info">
                            <div class="item-name">${data.name}</div>
                            <div class="item-details">${data.designation} • ${data.office}${data.station ? ' • ' + data.station : ''}</div>
                        </div>
                        <div class="path-controls">
                            <button type="button" class="btn btn-outline btn-icon btn-up btn-sm" onclick="moveSignatory(this.parentNode.parentNode, 'up')">
                                <i class="fas fa-arrow-up"></i>
                            </button>
                            <button type="button" class="btn btn-outline btn-icon btn-down btn-sm" onclick="moveSignatory(this.parentNode.parentNode, 'down')">
                                <i class="fas fa-arrow-down"></i>
                            </button>
                        </div>
                        <button type="button" class="btn btn-danger btn-icon btn-sm" onclick="removeSignatory(this.parentNode)">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    pathList.appendChild(itemDiv);
                });
            }
            updatePathOrder();
            updateSteps();
        });

        // Show loading on filter form submit
        document.getElementById('filter-form').addEventListener('submit', () => {
            loadingOverlay.style.display = 'flex';
        });
    </script>

    <script>
    (function(){
        const SCROLL_KEY = 'signat_path_scroll';

        // Restore scroll position on load (after layout)
        document.addEventListener('DOMContentLoaded', () => {
            const v = sessionStorage.getItem(SCROLL_KEY);
            if (v !== null) {
                const y = parseInt(v, 10) || 0;
                // Delay slightly to allow layout/images to settle
                setTimeout(() => window.scrollTo({ top: y, left: 0, behavior: 'auto' }), 50);
                // remove stored value so new visits start fresh
                sessionStorage.removeItem(SCROLL_KEY);
            }
        });

        // Throttled save of scroll position
        let ticking = false;
        function saveScroll() {
            sessionStorage.setItem(SCROLL_KEY, String(window.scrollY || window.pageYOffset || 0));
            ticking = false;
        }

        window.addEventListener('scroll', () => {
            if (!ticking) {
                ticking = true;
                requestAnimationFrame(saveScroll);
            }
        }, { passive: true });

        // Also ensure position saved before navigation/unload or form submits
        window.addEventListener('beforeunload', saveScroll);
        document.addEventListener('submit', saveScroll, true);
    })();
    </script>

</body>
</html>