<?php
/**
 * SINGLE PAGE APPLICATION: Office & Station Management
 * Feature: Cascading Dropdowns (Office -> Station), Local Data Management, and Signatory Order Swapping
 */

declare(strict_types=1);

// Disable direct error output in production, log instead
ini_set('display_errors', '0');
error_reporting(E_ALL);

// --- LOCAL DATABASE CONFIG (from db.php) ---
define('LOCAL_DB_HOST', 'localhost');
define('LOCAL_DB_NAME', 'ddts_pnpki');     // Your database name
define('LOCAL_DB_USER', 'root');    // Your database user
define('LOCAL_DB_PASS', ''); // Your database password

// --- INTERNATIONAL DATABASE CONFIG (from db_international.php) ---
define('INTL_DB_HOST', '153.92.15.60');
define('INTL_DB_USER', 'u645536029_otos_root');
define('INTL_DB_PASS', '6yI3PF3OZ');
define('INTL_DB_NAME', 'u645536029_otos');
define('DB_CHARSET', 'utf8mb4');


/**
 * Returns a connected mysqli instance for the LOCAL database.
 */
function get_local_db_connection(): mysqli
{
    mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
    try {
        $conn = new mysqli(LOCAL_DB_HOST, LOCAL_DB_USER, LOCAL_DB_PASS, LOCAL_DB_NAME);
        if (! $conn->set_charset(DB_CHARSET)) {
            error_log('Local DB charset set failed: ' . $conn->error);
        }
        return $conn;
    } catch (mysqli_sql_exception $e) {
        error_log('Local Database connection error: ' . $e->getMessage());
        die('Local Database connection failed. Please try again later.');
    }
}

/**
 * Returns a connected mysqli instance for the INTERNATIONAL database.
 */
function get_international_db_connection(): mysqli
{
    mysqli_report(MYSQLI_REPORT_STRICT | MYSQLI_REPORT_ERROR);
    try {
        $conn = new mysqli(INTL_DB_HOST, INTL_DB_USER, INTL_DB_PASS, INTL_DB_NAME);
        if (! $conn->set_charset(DB_CHARSET)) {
            error_log('International DB charset set failed: ' . $conn->error);
        }
        return $conn;
    } catch (mysqli_sql_exception $e) {
        error_log('International Database connection error: ' . $e->getMessage());
        die('International Database connection failed. Please try again later.');
    }
}

// --- BACKEND API LOGIC ---
if (isset($_GET['api'])) {
    ob_clean(); 
    header('Content-Type: application/json');
    
    try {
        $action = $_GET['api'];
        $input = json_decode(file_get_contents('php://input'), true);

        switch ($action) {
            
            // API: Get Valid Office-Station Pairs (from INTERNATIONAL DB)
            case 'get_dropdowns':
                $conn = get_international_db_connection();
                $data = [];
                $sql = "SELECT DISTINCT Office_StationMain as office, Office_Station as station 
                        FROM cofigurationdata_tbl 
                        WHERE Office_StationMain != '' AND Office_Station != '' 
                        ORDER BY Office_StationMain ASC, Office_Station ASC";
                
                $result = $conn->query($sql);
                while ($r = $result->fetch_assoc()) {
                    $data[] = $r;
                }
                $conn->close();
                echo json_encode($data);
                break;

            // API: Get Records from the NEW LOCAL TABLE (for card display)
            case 'get_local_records':
                $conn = get_local_db_connection();
                $sql = "SELECT id, office, station FROM office_station ORDER BY id DESC";
                $result = $conn->query($sql);
                $records = [];
                while ($row = $result->fetch_assoc()) {
                    $records[] = $row;
                }
                $conn->close();
                echo json_encode($records);
                break;
                
            // API: Get Signatories for a given Office_Station ID (batch_id)
            case 'get_signatories':
                $id = isset($_GET['id']) ? intval($_GET['id']) : null;
                if (!$id) throw new Exception("No ID provided for signatories");

                $conn = get_local_db_connection();
                
                // Direct selection from document_signatories using batch_id.
                $sql = $conn->prepare("
                    SELECT doc_id, user_id, signing_order, full_name, 
                           office_assigned, station_assigned
                    FROM document_signatories
                    WHERE batch_id = ?
                    ORDER BY signing_order ASC
                ");
                
                $sql->bind_param("i", $id);
                $sql->execute();
                $result = $sql->get_result();
                $signatories = [];
                while ($row = $result->fetch_assoc()) {
                    $signatories[] = $row;
                }
                $conn->close();
                echo json_encode(['success' => true, 'signatories' => $signatories]);
                break;

            // API: Move Signatory (Swap order with the occupant of the target slot)
            case 'move_signatory':
                if (!$input) throw new Exception("No data received for moving signatory");
                
                $doc_id = isset($input['doc_id']) ? intval($input['doc_id']) : null;
                $current_order = isset($input['current_order']) ? intval($input['current_order']) : null;
                $new_order = isset($input['new_order']) ? intval($input['new_order']) : null;

                if (!$doc_id || !$current_order || !$new_order || $current_order === $new_order) {
                    throw new Exception("Invalid parameters provided for signatory move. Check doc_id, current_order, and new_order.");
                }
                
                $conn = get_local_db_connection();
                $conn->begin_transaction(); // Start transaction for safety

                try {
                    // 1. Get the batch_id and identify the other signatory involved in the swap
                    $sql_get_info = $conn->prepare("
                        SELECT T1.batch_id, T2.doc_id AS other_doc_id
                        FROM document_signatories T1
                        LEFT JOIN document_signatories T2 
                            ON T2.signing_order = ? AND T2.batch_id = T1.batch_id
                        WHERE T1.doc_id = ?
                    ");
                    $sql_get_info->bind_param("ii", $new_order, $doc_id);
                    $sql_get_info->execute();
                    $result_info = $sql_get_info->get_result();
                    
                    if (!$row = $result_info->fetch_assoc()) {
                        $conn->rollback();
                        throw new Exception("Source signatory (Doc ID: $doc_id) not found.");
                    }
                    
                    $batch_id = $row['batch_id'];
                    $other_doc_id = $row['other_doc_id'];
                    
                    if (!$other_doc_id) {
                        $conn->rollback();
                        throw new Exception("Target slot (Order: $new_order) is empty or invalid for this batch.");
                    }

                    // 2. Perform the direct swap using UPDATE and CASE statements
                    $sql_swap = $conn->prepare("
                        UPDATE document_signatories
                        SET signing_order = CASE
                            WHEN doc_id = ? THEN ?    -- Move $doc_id to $new_order
                            WHEN doc_id = ? THEN ?    -- Move the other signatory to $current_order
                            ELSE signing_order
                        END
                        WHERE doc_id IN (?, ?) AND batch_id = ?
                    ");
                    
                    $sql_swap->bind_param(
                        "iiiiiii", 
                        $doc_id, $new_order, 
                        $other_doc_id, $current_order, 
                        $doc_id, $other_doc_id, 
                        $batch_id
                    );

                    if (!$sql_swap->execute()) {
                        $conn->rollback();
                        throw new Exception("Database update failed: " . $conn->error);
                    }
                    
                    $conn->commit(); // Commit the transaction
                    $conn->close();
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => "Signatories swapped successfully. Doc ID $doc_id is now Order $new_order."
                    ]);

                } catch (Exception $e) {
                    if ($conn) $conn->rollback();
                    error_log('Signatory move error: ' . $e->getMessage());
                    http_response_code(500); 
                    echo json_encode(['success' => false, 'message' => "Failed to move signatory: " . $e->getMessage()]);
                    exit;
                }
                break;


            // API: Save Record - Now saves to the LOCAL table 'office_station' with conditional duplication of signatories
            case 'save_record':
                if (!$input) throw new Exception("No data received");
                $office = $input['office'];
                $station = $input['station'];
                // Check for optional source_id for duplication
                // This is NULL if the user chose "Without Signatories" or if it's a new record
                $source_id = isset($input['source_id']) ? intval($input['source_id']) : null; 

                // 1. Get LOCAL DB Connection
                $conn = get_local_db_connection();
                $conn->begin_transaction(); // Start transaction for safety

                try {
                    // 2. Sanitize and Prepare
                    $office_clean = $conn->real_escape_string($office);
                    $station_clean = $conn->real_escape_string($station);

                    // 3. Insert into LOCAL table 'office_station'
                    $sql_office = "INSERT INTO office_station (office, station) VALUES ('$office_clean', '$station_clean')";
                    
                    if (!$conn->query($sql_office)) {
                        throw new Exception("Failed to insert office_station: " . $conn->error);
                    }

                    $new_batch_id = $conn->insert_id;
                    $message = 'Record saved successfully to local DB'; // Default message

                    // 4. CONDITIONAL: Duplicate Signatories if source_id is provided
                    if ($source_id) {
                        // Copy records from document_signatories where batch_id matches the source ID
                        $sql_signatories = $conn->prepare("
                            INSERT INTO document_signatories 
                                (user_id, signing_order, office_assigned, station_assigned, office_id, station_id, batch_id, full_name)
                            SELECT 
                                user_id, signing_order, office_assigned, station_assigned, office_id, station_id, ?, full_name
                            FROM document_signatories
                            WHERE batch_id = ?
                        ");
                        
                        $sql_signatories->bind_param("ii", $new_batch_id, $source_id);
                        
                        if (!$sql_signatories->execute()) {
                            throw new Exception("Failed to duplicate signatories: " . $conn->error);
                        }
                        
                        $message = 'Record and associated signatories duplicated successfully!';
                    }
                    
                    $conn->commit(); // Commit transaction
                    $conn->close();
                    echo json_encode(['success' => true, 'message' => $message]);

                } catch (Exception $e) {
                    if ($conn) $conn->rollback(); // Rollback on failure
                    if ($conn) $conn->close();
                    http_response_code(500); 
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
                break; // End of case 'save_record'

            case 'delete_record':
                $conn = get_local_db_connection(); // Delete from local table
                $id = isset($input['id']) ? intval($input['id']) : null;
                if (!$id) throw new Exception("No ID provided");
                $sql = "DELETE FROM office_station WHERE id=$id";
                if ($conn->query($sql)) {
                    $conn->close();
                    echo json_encode(['success' => true, 'message' => 'Local record deleted successfully']);
                } else {
                    $conn->close();
                    throw new Exception($conn->error);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid API Action']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        error_log("API Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit; 
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Office & Station Local Manager</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
        .toast-enter { opacity: 0; transform: translateY(100%); }
        .toast-enter-active { opacity: 1; transform: translateY(0); transition: opacity 300ms, transform 300ms; }
        .toast-leave { opacity: 1; }
        .toast-leave-active { opacity: 0; transform: translateY(100%); transition: opacity 300ms, transform 300ms; }
        .list-item { transition: background-color 0.2s; }
    </style>
</head>
<body class="min-h-screen p-4 sm:p-8">

    <div id="app" class="max-w-7xl mx-auto">
        <header class="bg-white p-6 rounded-xl shadow-lg mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center">
            <h1 class="text-3xl font-extrabold text-gray-800 flex items-center">
                <i data-lucide="server" class="w-8 h-8 mr-3 text-red-500"></i>
                Local Office & Station Records
            </h1>
            <button id="add-new-btn" class="mt-4 sm:mt-0 px-6 py-2 bg-blue-600 text-white font-semibold rounded-lg shadow-md hover:bg-blue-700 transition-colors flex items-center">
                <i data-lucide="plus" class="w-5 h-5 mr-2"></i> Add New Record (From International)
            </button>
        </header>
        
        <div class="bg-white p-6 rounded-xl shadow-lg mb-6 flex flex-col md:flex-row gap-4">
            <div class="flex-1">
                <label for="filter-office" class="block text-sm font-medium text-gray-700 mb-1">Filter by Office</label>
                <select id="filter-office" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500">
                    <option value="">All Offices</option>
                </select>
            </div>
            <div class="flex-1">
                <label for="filter-station" class="block text-sm font-medium text-gray-700 mb-1">Filter by Station</label>
                <select id="filter-station" disabled class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 bg-gray-50 cursor-not-allowed">
                    <option value="">All Stations</option>
                </select>
            </div>
        </div>

        <div id="loading-state" class="text-center p-12 bg-white rounded-xl shadow-md hidden">
            <div class="animate-spin inline-block w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full"></div>
            <p class="mt-4 text-gray-600">Loading local data...</p>
        </div>

        <div id="empty-state" class="text-center p-12 bg-white rounded-xl shadow-md hidden">
            <i data-lucide="database" class="w-12 h-12 mx-auto text-gray-400 mb-4"></i>
            <h2 class="text-xl font-semibold text-gray-700">No Local Records Found</h2>
            <p class="text-gray-500 mt-2">Click "Add New Record" to copy a record from the International Database.</p>
        </div>

        <div id="data-card-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            </div>
    </div>

    <div id="crud-modal" class="fixed inset-0 bg-gray-900 bg-opacity-75 hidden flex items-center justify-center p-4 z-50 transition-opacity duration-300 ease-out">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6 transform scale-95 transition-transform duration-300">
            <div class="flex justify-between items-center border-b pb-4 mb-4">
                <h3 id="modal-title" class="text-2xl font-bold text-gray-800">Add New Record (To Local DB)</h3>
                <button onclick="closeModal('crud-modal')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            <form id="record-form">
                <input type="hidden" id="record-id">
                <p class="text-sm text-gray-600 mb-4">Select an Office-Station pair from the International Database to save a copy to your Local Database.</p>
                <div class="space-y-4">
                    <div>
                        <label for="office" class="block text-sm font-medium text-gray-700 mb-1">Office Name (International)</label>
                        <select id="office" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500">
                            <option value="" disabled selected>Loading...</option>
                        </select>
                    </div>
                    <div>
                        <label for="station" class="block text-sm font-medium text-gray-700 mb-1">Station Name (International)</label>
                        <select id="station" required disabled class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 bg-gray-50 cursor-not-allowed">
                            <option value="" disabled selected>Select an Office first</option>
                        </select>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal('crud-modal')" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 flex items-center">
                        <i data-lucide="copy" class="w-5 h-5 mr-2"></i> Save Copy to Local DB
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="signatories-modal" class="fixed inset-0 bg-gray-900 bg-opacity-75 hidden flex items-center justify-center p-4 z-50 transition-opacity duration-300 ease-out">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-4xl p-6 transform scale-95 transition-transform duration-300">
            <div class="flex justify-between items-center border-b pb-4 mb-4">
                <h3 id="signatories-modal-title" class="text-2xl font-bold text-gray-800 flex items-center">
                    <i data-lucide="users-round" class="w-6 h-6 mr-2 text-blue-600"></i> Assigned Signatories
                </h3>
                <button onclick="closeModal('signatories-modal')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            <div class="text-sm text-gray-600 mb-4">List of individuals assigned to sign documents for this Office-Station pair (Local ID: <span id="current-batch-id" class="font-semibold"></span>).</div>
            
            <div id="signatories-list-container" class="max-h-96 overflow-y-auto">
                </div>

            <div class="mt-6 flex justify-end">
                <button type="button" onclick="closeModal('signatories-modal')" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Close</button>
            </div>
        </div>
    </div>

    <div id="confirm-modal" class="fixed inset-0 bg-gray-900 bg-opacity-75 hidden flex items-center justify-center p-4 z-50 transition-opacity duration-300 ease-out">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6 transform scale-95 transition-transform duration-300">
            <h3 id="confirm-modal-title" class="text-xl font-bold text-gray-800 flex items-center mb-4"><i data-lucide="help-circle" class="w-6 h-6 mr-2 text-blue-600"></i> Confirm Action</h3>
            <p id="confirm-modal-body" class="text-gray-700 mb-6"></p>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeModal('confirm-modal')" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                <div id="confirm-action-buttons" class="flex space-x-3"></div>
            </div>
        </div>
    </div>

    <div id="toast-container" class="fixed bottom-4 right-4 z-50 space-y-3 pointer-events-none"></div>

    <div id="global-loading-overlay" class="fixed inset-0 bg-gray-900 bg-opacity-70 z-[60] hidden flex flex-col items-center justify-center transition-opacity duration-300 backdrop-blur-sm">
        <div class="animate-spin inline-block w-16 h-16 border-[6px] border-blue-500 border-t-transparent rounded-full mb-4 shadow-lg"></div>
        <h2 class="text-white text-xl font-semibold tracking-wide animate-pulse">Loading Signatory Setup...</h2>
        <p class="text-gray-300 text-sm mt-2">Please wait while we redirect you.</p>
    </div>

    <script>
        // --- GLOBAL DATA ---
        let internationalMasterData = []; // Holds all Office-Station pairs from the INT DB
        let localRecords = []; // Holds all records from the LOCAL DB
        let currentActionRecord = null; // Stores record object for delete/duplicate operations
        let recordToDeleteId = null; // Stays for compatibility with delete flow

        const showToast = (message, type = 'info') => {
            const container = document.getElementById('toast-container');
            const colorMap = { 'success': 'bg-green-500 border-green-700', 'error': 'bg-red-500 border-red-700', 'info': 'bg-blue-500 border-blue-700' };
            const toast = document.createElement('div');
            toast.className = `toast-enter p-4 border-l-4 ${colorMap[type]} text-white shadow-lg rounded-lg max-w-sm w-full pointer-events-auto transition-all duration-300 ease-out transform`;
            toast.innerHTML = `<div class="flex items-center"><p class="font-medium text-sm">${message}</p></div>`;
            container.appendChild(toast);
            requestAnimationFrame(() => toast.classList.replace('toast-enter', 'toast-enter-active'));
            setTimeout(() => {
                toast.classList.replace('toast-enter-active', 'toast-leave-active');
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        };

        const apiCall = async (action, method = 'GET', data = null, id = null) => {
            let url = `?api=${action}`;
            // Use the ID in the URL only for GET requests where it's a query parameter
            if (id !== null && method === 'GET') url += `&id=${id}`; 
            
            const options = { method: method };
            if (data) options.body = JSON.stringify(data);
            
            const response = await fetch(url, options);
            
            if (!response.ok) {
                const errorBody = await response.json().catch(() => ({ message: response.statusText }));
                console.error("API Call Failed Response:", errorBody);
                throw new Error(errorBody.message || `Server Error: ${response.statusText}`);
            }
            return await response.json();
        };

        // --- INTERNATIONAL DROPDOWN LOGIC (For Modal) ---

        const loadInternationalDropdownData = async () => {
            try {
                internationalMasterData = await apiCall('get_dropdowns'); // returns [{office: 'A', station: '1'}, ...]
                populateOfficeDropdown(); // Populates the modal's office dropdown
            } catch (error) {
                console.error(error);
                showToast('Failed to load international configuration data', 'error');
            }
        };

        const populateOfficeDropdown = (selectedOffice = null) => {
            const officeSelect = document.getElementById('office');
            const uniqueOffices = [...new Set(internationalMasterData.map(item => item.office))];
            
            officeSelect.innerHTML = `<option value="" disabled selected>Select an Office</option>`;
            uniqueOffices.forEach(office => {
                const option = document.createElement('option');
                option.value = office;
                option.textContent = office;
                if (office === selectedOffice) option.selected = true;
                officeSelect.appendChild(option);
            });
        };

        const filterStations = (selectedStation = null) => {
            const officeSelect = document.getElementById('office');
            const stationSelect = document.getElementById('station');
            const currentOffice = officeSelect.value;

            stationSelect.innerHTML = '<option value="" disabled selected>Select a Station</option>';
            stationSelect.disabled = true;
            stationSelect.classList.add('bg-gray-50', 'cursor-not-allowed');

            if (!currentOffice) return;

            const validStations = internationalMasterData
                .filter(item => item.office === currentOffice)
                .map(item => item.station);

            validStations.forEach(station => {
                const option = document.createElement('option');
                option.value = station;
                option.textContent = station;
                if (station === selectedStation) option.selected = true;
                stationSelect.appendChild(option);
            });

            stationSelect.disabled = false;
            stationSelect.classList.remove('bg-gray-50', 'cursor-not-allowed');
        };

        // --- LOCAL CRUD OPERATIONS & DISPLAY ---

        const fetchLocalRecords = async () => {
            document.getElementById('loading-state').classList.remove('hidden');
            try {
                const records = await apiCall('get_local_records');
                document.getElementById('loading-state').classList.add('hidden');
                
                if (records.success === false) throw new Error(records.message);
                
                localRecords = records; // Store full list
                
                if (localRecords.length === 0) {
                    document.getElementById('empty-state').classList.remove('hidden');
                    document.getElementById('data-card-container').innerHTML = '';
                } else {
                    document.getElementById('empty-state').classList.add('hidden');
                    // Get filter values from URL on initial load
                    const urlParams = new URLSearchParams(window.location.search);
                    const initialOfficeFilter = urlParams.get('office_filter') || '';
                    const initialStationFilter = urlParams.get('station_filter') || '';
                    
                    // Pass initial filter values to populate dropdowns
                    populateFilterDropdowns(initialOfficeFilter, initialStationFilter); 
                }
            } catch (error) {
                document.getElementById('loading-state').classList.add('hidden');
                showToast(error.message, 'error');
            }
        };

        /**
         * NEW FUNCTION: Executes the duplication API call with the option to include signatories.
         */
        const executeDuplication = async (record, withSignatories) => {
            const dataToSave = { 
                office: record.office, 
                station: record.station,
                // Pass source_id ONLY if signatories are requested
                source_id: withSignatories ? record.id : null 
            };
            
            closeModal('confirm-modal'); // Close the confirmation modal
            
            try {
                const res = await apiCall('save_record', 'POST', dataToSave);
                if (res.success) {
                    showToast(res.message, 'success');
                    await fetchLocalRecords(); // Refresh the local list
                } else throw new Error(res.message);
            } catch (error) {
                showToast(`Duplication failed: ${error.message}`, 'error');
            }
        };

        /**
         * MODIFIED FUNCTION: Opens a confirmation modal for duplication choices.
         */
        window.duplicateRecord = (record) => {
            currentActionRecord = record; // Store the record globally for the modal buttons
            
            const modal = document.getElementById('confirm-modal');
            document.getElementById('confirm-modal-title').innerHTML = '<i data-lucide="copy-check" class="w-6 h-6 mr-2 text-green-600"></i> Confirm Duplication';
            document.getElementById('confirm-modal-body').innerHTML = `
                You are duplicating **${record.office} / ${record.station} (ID: ${record.id})**. <br><br>
                Do you want to copy the **assigned signatories** to the new record?
            `;

            const buttonsContainer = document.getElementById('confirm-action-buttons');
            buttonsContainer.innerHTML = `
                <button type="button" onclick="executeDuplication(currentActionRecord, false)" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-100">Duplicate <b>Without</b> Signatories</button>
                <button type="button" onclick="executeDuplication(currentActionRecord, true)" class="px-4 py-2 bg-green-600 text-white font-semibold rounded-lg hover:bg-green-700">Duplicate <b>With</b> Signatories</button>
            `;
            
            lucide.createIcons();
            openModal('confirm-modal');
        };

        const saveRecord = async (data) => {
            // This function is for saving a NEW record from the modal, 
            // which does not pass source_id, thus no signatory duplication occurs.
            try {
                const res = await apiCall('save_record', 'POST', data);
                if (res.success) {
                    showToast(res.message, 'success');
                    closeModal('crud-modal');
                    await fetchLocalRecords(); // Refresh the local list
                } else throw new Error(res.message);
            } catch (error) {
                showToast(error.message, 'error');
            }
        };

        const deleteRecord = async () => {
            if (!recordToDeleteId) return;
            try {
                const res = await apiCall('delete_record', 'POST', { id: recordToDeleteId });
                if (res.success) {
                    showToast(res.message, 'success');
                    closeModal('confirm-modal'); // Updated to use confirm-modal
                    await fetchLocalRecords();
                } else throw new Error(res.message);
            } catch (error) {
                showToast(error.message, 'error');
            }
        };
        
        // --- FILTER & CARD DISPLAY LOGIC ---

        const updateURLFilters = (office, station) => {
            const url = new URL(window.location);
            if (office) {
                url.searchParams.set('office_filter', office);
            } else {
                url.searchParams.delete('office_filter');
            }
            if (station) {
                url.searchParams.set('station_filter', station);
            } else {
                url.searchParams.delete('station_filter');
            }
            window.history.pushState({}, '', url); 
        };

        const populateFilterDropdowns = (selectedOffice = '', selectedStation = '') => {
            const officeFilter = document.getElementById('filter-office');
            
            // Clear and add 'All' option
            officeFilter.innerHTML = '<option value="">All Offices</option>';
            document.getElementById('filter-station').innerHTML = '<option value="">All Stations</option>';

            // Populate unique offices from local data
            const uniqueOffices = [...new Set(localRecords.map(item => item.office))];
            uniqueOffices.forEach(office => {
                const option = document.createElement('option');
                option.value = office;
                option.textContent = office;
                if (office === selectedOffice) option.selected = true; 
                officeFilter.appendChild(option);
            });
            
            // Reset/Update Station filter based on the (possibly pre-selected) office filter
            updateStationFilter(selectedStation);
        };
        
        const updateStationFilter = (selectedStation = '') => {
            const officeFilter = document.getElementById('filter-office').value;
            const stationFilter = document.getElementById('filter-station');
            
            stationFilter.innerHTML = '<option value="">All Stations</option>';
            stationFilter.disabled = true;
            stationFilter.classList.add('bg-gray-50', 'cursor-not-allowed');

            let uniqueStations = [];

            if (!officeFilter) {
                 // If no office is selected, show all unique stations from all local records
                uniqueStations = [...new Set(localRecords.map(item => item.station))];
            } else {
                // If an office is selected, filter stations by that office
                const stationsForSelectedOffice = localRecords
                    .filter(record => record.office === officeFilter)
                    .map(record => record.station);
                    
                uniqueStations = [...new Set(stationsForSelectedOffice)];
            }
            
            uniqueStations.forEach(station => {
                const option = document.createElement('option');
                option.value = station;
                option.textContent = station;
                if (station === selectedStation) option.selected = true; 
                stationFilter.appendChild(option);
            });
            
            stationFilter.disabled = false;
            stationFilter.classList.remove('bg-gray-50', 'cursor-not-allowed');
            
            // Update URL and render cards after filter change
            updateURLFilters(officeFilter, stationFilter.value);
            renderCards(); 
        };

        const renderCards = () => {
            const officeFilterValue = document.getElementById('filter-office').value;
            const stationFilterValue = document.getElementById('filter-station').value;
            const container = document.getElementById('data-card-container');
            
            // Update URL based on current selections
            updateURLFilters(officeFilterValue, stationFilterValue);

            const filteredRecords = localRecords.filter(record => {
                const officeMatch = !officeFilterValue || record.office === officeFilterValue;
                const stationMatch = !stationFilterValue || record.station === stationFilterValue;
                return officeMatch && stationMatch;
            });

            if (filteredRecords.length === 0 && localRecords.length > 0) {
                container.innerHTML = '<div class="md:col-span-2 lg:col-span-3 text-center p-8 text-gray-500">No records match the current filter criteria.</div>';
                return;
            } else if (filteredRecords.length === 0 && localRecords.length === 0) {
                 container.innerHTML = ''; 
                 return;
            }


            container.innerHTML = filteredRecords.map(r => `
                <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500 hover:shadow-lg transition-shadow">
                    <div class="flex justify-between items-start">
                        <h4 class="text-xl font-bold text-gray-800 mb-2">${r.office}</h4>
                        <div class="flex space-x-1">
                            
                            <a href="#" onclick="navigateToSignatory(event, ${r.id})" class="text-blue-600 hover:text-blue-900 p-1 rounded-full hover:bg-blue-50 transition-colors" title="Assign Signatory">
                                <i data-lucide="square-pen" class="w-5 h-5"></i>
                            </a>
                            
                            <button onclick="openSignatoriesModal(${r.id})" class="text-purple-600 hover:text-purple-900 p-1 rounded-full hover:bg-purple-50 transition-colors" title="View Signatories">
                                <i data-lucide="users" class="w-5 h-5"></i>
                            </button>

                            <button onclick="duplicateRecord({id: ${r.id}, office: '${r.office}', station: '${r.station}'})" class="text-green-600 hover:text-green-900 p-1 rounded-full hover:bg-green-50 transition-colors" title="Duplicate Record">
                                <i data-lucide="copy-check" class="w-5 h-5"></i>
                            </button>

                            <button onclick="openDeleteModal(${r.id})" class="text-red-600 hover:text-red-900 p-1 rounded-full hover:bg-red-50 transition-colors" title="Delete Local Record">
                                <i data-lucide="trash-2" class="w-5 h-5"></i>
                            </button>
                        </div>
                    </div>
                    <p class="text-sm font-semibold text-blue-600 uppercase">Station:</p>
                    <p class="text-lg text-gray-700 font-medium">${r.station}</p>
                    <p class="text-xs text-gray-400 mt-2">Local ID: ${r.id}</p>
                </div>
            `).join('');
            lucide.createIcons();
        };

        // --- SIGNATORIES MODAL LOGIC (FIXED: Table View with Dynamic Empty State) ---

        const renderSignatories = (signatories) => {
            const container = document.getElementById('signatories-list-container');
            
            // Clear previous content completely
            container.innerHTML = '';

            // If no data, inject the empty state message directly
            if (signatories.length === 0) {
                container.innerHTML = '<p class="text-center p-8 text-gray-500">No signatories found for this batch ID.</p>';
                return;
            }

            const totalSignatories = signatories.length;

            // Create Table Structure
            let tableHtml = `
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 border border-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Signatory Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assigned Office/Station</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
            `;

            // Loop through signatories to create rows
            tableHtml += signatories.map((s, index) => {
                const docId = s.doc_id;
                const order = s.signing_order;
                
                const moveUpDisabled = order === 1;
                const moveDownDisabled = order === totalSignatories;

                return `
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-lg font-bold text-blue-600">${order}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm font-semibold text-gray-900">${s.full_name}</div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="text-sm text-gray-600">${s.office_assigned}</div>
                            <div class="text-xs text-gray-500">${s.station_assigned} <span class="text-gray-400 ml-1">(ID: ${docId})</span></div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex justify-end space-x-2">
                                <button onclick="moveSignatory(${docId}, ${order}, ${order - 1})" 
                                    class="p-1.5 rounded-full transition-colors ${moveUpDisabled ? 'text-gray-300 cursor-not-allowed' : 'text-yellow-600 hover:bg-yellow-100'}" 
                                    title="Move Up" ${moveUpDisabled ? 'disabled' : ''}>
                                    <i data-lucide="chevron-up" class="w-5 h-5"></i>
                                </button>
                                <button onclick="moveSignatory(${docId}, ${order}, ${order + 1})" 
                                    class="p-1.5 rounded-full transition-colors ${moveDownDisabled ? 'text-gray-300 cursor-not-allowed' : 'text-yellow-600 hover:bg-yellow-100'}" 
                                    title="Move Down" ${moveDownDisabled ? 'disabled' : ''}>
                                    <i data-lucide="chevron-down" class="w-5 h-5"></i>
                                </button>
                                <button onclick="editSignatory(${docId}, ${order})" 
                                    class="p-1.5 rounded-full text-blue-600 hover:bg-blue-100 transition-colors" 
                                    title="Edit Signatory">
                                    <i data-lucide="pencil" class="w-5 h-5"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');

            tableHtml += `
                        </tbody>
                    </table>
                </div>
            `;
            
            container.innerHTML = tableHtml;
            lucide.createIcons();
        };
        
        window.openSignatoriesModal = async (batchId) => {
            document.getElementById('current-batch-id').textContent = batchId;
            const modal = document.getElementById('signatories-modal');
            modal.classList.remove('hidden');
            document.querySelector('#signatories-modal > div').classList.replace('scale-95', 'scale-100');
            
            try {
                // Fetch signatories using the local office_station ID as batch_id
                const result = await apiCall('get_signatories', 'GET', null, batchId);
                if (result.success) {
                    renderSignatories(result.signatories);
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                renderSignatories([]); // Clear list on error
                showToast(`Failed to load signatories: ${error.message}`, 'error');
            }
        };
        
        window.moveSignatory = async (docId, currentOrder, newOrder) => {
            if (currentOrder === newOrder) return;
            
            const batchId = document.getElementById('current-batch-id').textContent;
            
            try {
                const res = await apiCall('move_signatory', 'POST', {
                    doc_id: docId,
                    current_order: currentOrder,
                    new_order: newOrder
                });
                
                if (res.success) {
                    showToast(res.message, 'success');
                    // Refresh the signatories list in the modal
                    await openSignatoriesModal(parseInt(batchId));
                } else {
                    throw new Error(res.message);
                }
            } catch (error) {
                showToast(`Move failed: ${error.message}`, 'error');
            }
        };
        
        window.editSignatory = (docId, order) => {
            showToast(`Action: Edit Doc ID ${docId} at Order ${order}. (New modal/form needed!)`, 'info');
        };


        // --- UI HANDLERS ---
        
        /**
         * NEW: Handles navigation with a loading overlay
         */
        window.navigateToSignatory = (event, id) => {
            event.preventDefault(); // Stop the default link click immediately
            
            const overlay = document.getElementById('global-loading-overlay');
            overlay.classList.remove('hidden'); // Show the loading screen
            
            // Small timeout to ensure the browser paints the overlay before freezing for navigation
            setTimeout(() => {
                window.location.href = `signat_path.php?id=${id}`;
            }, 100); 
        };

        window.openModal = (id) => {
            const modal = document.getElementById(id);
            const form = document.getElementById('record-form');
            if (id === 'crud-modal') {
                form.reset();
                document.getElementById('modal-title').textContent = 'Add New Record (To Local DB)';
                populateOfficeDropdown(); // Reset to empty
                filterStations(); // Reset to empty/disabled
            }
            modal.classList.remove('hidden');
            document.querySelector(`#${id} > div`).classList.replace('scale-95', 'scale-100');
        };

        window.closeModal = (id) => {
            const modal = document.getElementById(id);
            document.querySelector(`#${id} > div`).classList.replace('scale-100', 'scale-95');
            setTimeout(() => modal.classList.add('hidden'), 300);
        };
        
        /**
         * MODIFIED FUNCTION: Opens the generic confirm modal for deletion.
         */
        window.openDeleteModal = (id) => {
            recordToDeleteId = id;
            const modal = document.getElementById('confirm-modal');
            document.getElementById('confirm-modal-title').innerHTML = '<i data-lucide="alert-triangle" class="w-6 h-6 mr-2 text-red-600"></i> Confirm Deletion';
            document.getElementById('confirm-modal-body').innerHTML = 'Are you sure you want to **delete** this **Local** record?';
            
            const buttonsContainer = document.getElementById('confirm-action-buttons');
            buttonsContainer.innerHTML = `
                <button type="button" id="confirm-delete-btn" class="px-4 py-2 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700">Delete Permanently</button>
            `;
            document.getElementById('confirm-delete-btn').onclick = deleteRecord;
            lucide.createIcons();
            openModal('confirm-modal');
        };
        
        // --- INITIALIZATION ---

        window.onload = async () => {
            // Load international data for the selection modal
            await loadInternationalDropdownData();
            // Load local records for the main display and filters. This now handles
            // reading filter state from the URL and calls updateStationFilter/renderCards.
            await fetchLocalRecords(); 
            
            // Event Listener: Modal Office Change -> Filter Stations
            document.getElementById('office').addEventListener('change', () => {
                filterStations(); 
            });

            // Event Listener: Filter Office Change -> Update Station Filter AND Render Cards
            document.getElementById('filter-office').addEventListener('change', () => updateStationFilter('')); // Reset station filter when office changes
            
            // Event Listener: Filter Station Change -> Render Cards
            document.getElementById('filter-station').addEventListener('change', renderCards);

            document.getElementById('add-new-btn').addEventListener('click', () => openModal('crud-modal'));
            
            // Handle form submission (Saving a copy to local DB)
            document.getElementById('record-form').addEventListener('submit', (e) => {
                e.preventDefault();
                // When saving from the modal, we don't pass source_id, so no duplication occurs.
                saveRecord({
                    office: document.getElementById('office').value,
                    station: document.getElementById('station').value
                });
            });

            // The confirm-delete-btn handler is now dynamically assigned inside openDeleteModal
            // document.getElementById('confirm-delete-btn').addEventListener('click', deleteRecord);
            lucide.createIcons();
        };
    </script>
</body>
</html>