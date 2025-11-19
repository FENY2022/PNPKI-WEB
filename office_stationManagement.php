<?php
/**
 * SINGLE PAGE APPLICATION: Office & Station Management
 * Feature: Cascading Dropdowns (Office -> Station) and Local Data Management
 * * NOTE: This file now includes the logic from both db.php and db_international.php 
 * for simultaneous access to both databases/connections.
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

            // API: Save Record - Now saves to the LOCAL table 'office_station'
            case 'save_record':
                if (!$input) throw new Exception("No data received");
                $office = $input['office'];
                $station = $input['station'];

                // 1. Get LOCAL DB Connection
                $conn = get_local_db_connection();
                
                // 2. Sanitize and Prepare
                $office_clean = $conn->real_escape_string($office);
                $station_clean = $conn->real_escape_string($station);

                // 3. Insert into LOCAL table 'office_station'
                $sql = "INSERT INTO office_station (office, station) VALUES ('$office_clean', '$station_clean')";
                
                if ($conn->query($sql)) {
                    $conn->close();
                    echo json_encode(['success' => true, 'message' => 'Record copied and saved successfully to local DB']);
                } else {
                    $conn->close();
                    throw new Exception($conn->error);
                }
                break;

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
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
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
                    <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 flex items-center">
                        <i data-lucide="copy" class="w-5 h-5 mr-2"></i> Save Copy to Local DB
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="delete-modal" class="fixed inset-0 bg-gray-900 bg-opacity-75 hidden flex items-center justify-center p-4 z-50 transition-opacity duration-300 ease-out">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6 transform scale-95 transition-transform duration-300">
            <h3 class="text-xl font-bold text-red-600 flex items-center mb-4"><i data-lucide="alert-triangle" class="w-6 h-6 mr-2"></i> Confirm Deletion</h3>
            <p class="text-gray-700 mb-6">Are you sure you want to delete this **Local** record?</p>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="button" id="confirm-delete-btn" class="px-4 py-2 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700">Delete</button>
            </div>
        </div>
    </div>

    <div id="toast-container" class="fixed bottom-4 right-4 z-50 space-y-3 pointer-events-none"></div>

    <script>
        // --- GLOBAL DATA ---
        let internationalMasterData = []; // Holds all Office-Station pairs from the INT DB
        let localRecords = []; // Holds all records from the LOCAL DB
        let recordToDeleteId = null;

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

        const apiCall = async (action, method = 'GET', data = null) => {
            const url = `?api=${action}`;
            const options = { method: method };
            if (data) options.body = JSON.stringify(data);
            const response = await fetch(url, options);
            if (!response.ok) throw new Error(`Server Error: ${response.statusText}`);
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
                    populateFilterDropdowns(); // Update filters based on new data
                    renderCards(); // Render all records initially
                }
            } catch (error) {
                document.getElementById('loading-state').classList.add('hidden');
                showToast(error.message, 'error');
            }
        };

        // New function to handle duplication (re-using the save_record API endpoint)
        window.duplicateRecord = async (record) => {
            const dataToSave = { office: record.office, station: record.station };
            try {
                const res = await apiCall('save_record', 'POST', dataToSave);
                if (res.success) {
                    showToast('Record duplicated successfully!', 'success');
                    await fetchLocalRecords(); // Refresh the local list
                } else throw new Error(res.message);
            } catch (error) {
                showToast(`Duplication failed: ${error.message}`, 'error');
            }
        };

        const saveRecord = async (data) => {
            try {
                const res = await apiCall('save_record', 'POST', data);
                if (res.success) {
                    showToast(res.message, 'success');
                    closeModal();
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
                    closeDeleteModal();
                    await fetchLocalRecords();
                } else throw new Error(res.message);
            } catch (error) {
                showToast(error.message, 'error');
            }
        };
        
        // --- FILTER & CARD DISPLAY LOGIC ---

        const populateFilterDropdowns = () => {
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
                officeFilter.appendChild(option);
            });
            
            // Reset Station filter
            updateStationFilter();
        };
        
        const updateStationFilter = () => {
            const officeFilter = document.getElementById('filter-office').value;
            const stationFilter = document.getElementById('filter-station');
            
            stationFilter.innerHTML = '<option value="">All Stations</option>';
            stationFilter.disabled = true;
            stationFilter.classList.add('bg-gray-50', 'cursor-not-allowed');

            if (!officeFilter) {
                 // If no office is selected, show all unique stations from all local records
                const allUniqueStations = [...new Set(localRecords.map(item => item.station))];
                allUniqueStations.forEach(station => {
                    stationFilter.innerHTML += `<option value="${station}">${station}</option>`;
                });
            } else {
                // If an office is selected, filter stations by that office
                const stationsForSelectedOffice = localRecords
                    .filter(record => record.office === officeFilter)
                    .map(record => record.station);
                    
                const uniqueStations = [...new Set(stationsForSelectedOffice)];
                uniqueStations.forEach(station => {
                    stationFilter.innerHTML += `<option value="${station}">${station}</option>`;
                });
            }
            
            stationFilter.disabled = false;
            stationFilter.classList.remove('bg-gray-50', 'cursor-not-allowed');
            renderCards(); // Re-render cards after filter change
        };

        const renderCards = () => {
            const officeFilterValue = document.getElementById('filter-office').value;
            const stationFilterValue = document.getElementById('filter-station').value;
            const container = document.getElementById('data-card-container');

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
                            
                            <a href="signat_path.php?id=${r.id}" class="text-blue-600 hover:text-blue-900 p-1 rounded-full hover:bg-blue-50 transition-colors" title="Assign Signatory">
                                <i data-lucide="square-pen" class="w-5 h-5"></i>
                            </a>

                            <button onclick="duplicateRecord({office: '${r.office}', station: '${r.station}'})" class="text-green-600 hover:text-green-900 p-1 rounded-full hover:bg-green-50 transition-colors" title="Duplicate Record">
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

        // --- UI HANDLERS ---

        window.openModal = (record = null) => {
            const modal = document.getElementById('crud-modal');
            const form = document.getElementById('record-form');
            form.reset();
            
            // Note: openModal is now primarily used for the 'Add New Record' button, 
            // as 'Assign Signatory' links directly to signat_path.php
            
            document.getElementById('modal-title').textContent = 'Add New Record (To Local DB)';
            populateOfficeDropdown(); // Reset to empty
            filterStations(); // Reset to empty/disabled
            
            modal.classList.remove('hidden');
            document.querySelector('#crud-modal > div').classList.replace('scale-95', 'scale-100');
        };

        window.closeModal = () => {
            const modal = document.getElementById('crud-modal');
            document.querySelector('#crud-modal > div').classList.replace('scale-100', 'scale-95');
            setTimeout(() => modal.classList.add('hidden'), 300);
        };

        window.openDeleteModal = (id) => {
            recordToDeleteId = id;
            document.getElementById('delete-modal').classList.remove('hidden');
            document.querySelector('#delete-modal > div').classList.replace('scale-95', 'scale-100');
        };

        window.closeDeleteModal = () => {
            const modal = document.getElementById('delete-modal');
            document.querySelector('#delete-modal > div').classList.replace('scale-100', 'scale-95');
            setTimeout(() => modal.classList.add('hidden'), 300);
            recordToDeleteId = null;
        };

        // --- INITIALIZATION ---

        window.onload = async () => {
            // Load international data for the selection modal
            await loadInternationalDropdownData();
            // Load local records for the main display and filters
            await fetchLocalRecords();
            
            // Event Listener: Modal Office Change -> Filter Stations
            document.getElementById('office').addEventListener('change', () => {
                filterStations(); 
            });

            // Event Listener: Filter Office Change -> Update Station Filter AND Render Cards
            document.getElementById('filter-office').addEventListener('change', updateStationFilter);
            
            // Event Listener: Filter Station Change -> Render Cards
            document.getElementById('filter-station').addEventListener('change', renderCards);

            document.getElementById('add-new-btn').addEventListener('click', () => openModal());
            
            // Handle form submission (Saving a copy to local DB)
            document.getElementById('record-form').addEventListener('submit', (e) => {
                e.preventDefault();
                saveRecord({
                    office: document.getElementById('office').value,
                    station: document.getElementById('station').value
                });
            });

            document.getElementById('confirm-delete-btn').addEventListener('click', deleteRecord);
            lucide.createIcons();
        };
    </script>
</body>
</html>