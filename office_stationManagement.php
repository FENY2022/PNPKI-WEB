<?php
/**
 * SINGLE PAGE APPLICATION: Office & Station Management
 * Feature: Cascading Dropdowns (Office -> Station)
 */

require_once 'db_international.php';

// --- BACKEND API LOGIC ---
if (isset($_GET['api'])) {
    ob_clean(); 
    header('Content-Type: application/json');
    
    try {
        $conn = get_db_connection();
        $action = $_GET['api'];
        $input = json_decode(file_get_contents('php://input'), true);

        switch ($action) {
            // API: Get Valid Office-Station Pairs
            // We fetch distinct pairs so we know which station belongs to which office
            case 'get_dropdowns':
                $data = [];
                $sql = "SELECT DISTINCT Office_StationMain as office, Office_Station as station 
                        FROM cofigurationdata_tbl 
                        WHERE Office_StationMain != '' AND Office_Station != '' 
                        ORDER BY Office_StationMain ASC, Office_Station ASC";
                
                $result = $conn->query($sql);
                while ($r = $result->fetch_assoc()) {
                    $data[] = $r;
                }
                echo json_encode($data);
                break;

            case 'get_records':
                $sql = "SELECT id, Office_StationMain as office, Office_Station as station FROM cofigurationdata_tbl ORDER BY id DESC";
                $result = $conn->query($sql);
                $records = [];
                while ($row = $result->fetch_assoc()) {
                    $records[] = $row;
                }
                echo json_encode($records);
                break;

            case 'save_record':
                if (!$input) throw new Exception("No data received");
                $id = isset($input['id']) ? intval($input['id']) : null;
                $office = $conn->real_escape_string($input['office']);
                $station = $conn->real_escape_string($input['station']);

                if ($id) {
                    $sql = "UPDATE cofigurationdata_tbl SET Office_StationMain='$office', Office_Station='$station' WHERE id=$id";
                } else {
                    $sql = "INSERT INTO cofigurationdata_tbl (Office_StationMain, Office_Station, File_Name, File_Size, N_Verifier, N_RApproval, Approval, TO_Suffixrestibute) 
                            VALUES ('$office', '$station', '', '', 0, 0, 0, '')";
                }
                if ($conn->query($sql)) {
                    echo json_encode(['success' => true, 'message' => 'Record saved successfully']);
                } else throw new Exception($conn->error);
                break;

            case 'delete_record':
                $id = isset($input['id']) ? intval($input['id']) : null;
                if (!$id) throw new Exception("No ID provided");
                $sql = "DELETE FROM cofigurationdata_tbl WHERE id=$id";
                if ($conn->query($sql)) {
                    echo json_encode(['success' => true, 'message' => 'Record deleted successfully']);
                } else throw new Exception($conn->error);
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
    <title>Office & Station Manager</title>
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
                <i data-lucide="building-2" class="w-8 h-8 mr-3 text-blue-500"></i>
                Office & Station Records
            </h1>
            <button id="add-new-btn" class="mt-4 sm:mt-0 px-6 py-2 bg-blue-600 text-white font-semibold rounded-lg shadow-md hover:bg-blue-700 transition-colors flex items-center">
                <i data-lucide="plus" class="w-5 h-5 mr-2"></i> Add New Record
            </button>
        </header>

        <div id="loading-state" class="text-center p-12 bg-white rounded-xl shadow-md hidden">
            <div class="animate-spin inline-block w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full"></div>
            <p class="mt-4 text-gray-600">Loading data...</p>
        </div>

        <div id="empty-state" class="text-center p-12 bg-white rounded-xl shadow-md hidden">
            <i data-lucide="database" class="w-12 h-12 mx-auto text-gray-400 mb-4"></i>
            <h2 class="text-xl font-semibold text-gray-700">No Records Found</h2>
            <p class="text-gray-500 mt-2">Click "Add New Record" to get started.</p>
        </div>

        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Office Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Station Name</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="data-table-body" class="bg-white divide-y divide-gray-200">
                        </tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="crud-modal" class="fixed inset-0 bg-gray-900 bg-opacity-75 hidden flex items-center justify-center p-4 z-50 transition-opacity duration-300 ease-out">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6 transform scale-95 transition-transform duration-300">
            <div class="flex justify-between items-center border-b pb-4 mb-4">
                <h3 id="modal-title" class="text-2xl font-bold text-gray-800">Add New Record</h3>
                <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="w-6 h-6"></i></button>
            </div>
            <form id="record-form">
                <input type="hidden" id="record-id">
                <div class="space-y-4">
                    <div>
                        <label for="office" class="block text-sm font-medium text-gray-700 mb-1">Office Name</label>
                        <select id="office" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500">
                            <option value="" disabled selected>Loading...</option>
                        </select>
                    </div>
                    <div>
                        <label for="station" class="block text-sm font-medium text-gray-700 mb-1">Station Name</label>
                        <select id="station" required disabled class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 bg-gray-50 cursor-not-allowed">
                            <option value="" disabled selected>Select an Office first</option>
                        </select>
                    </div>
                </div>
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 flex items-center">
                        <i data-lucide="save" class="w-5 h-5 mr-2"></i> Save Record
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="delete-modal" class="fixed inset-0 bg-gray-900 bg-opacity-75 hidden flex items-center justify-center p-4 z-50 transition-opacity duration-300 ease-out">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm p-6 transform scale-95 transition-transform duration-300">
            <h3 class="text-xl font-bold text-red-600 flex items-center mb-4"><i data-lucide="alert-triangle" class="w-6 h-6 mr-2"></i> Confirm Deletion</h3>
            <p class="text-gray-700 mb-6">Are you sure you want to delete this record?</p>
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancel</button>
                <button type="button" id="confirm-delete-btn" class="px-4 py-2 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700">Delete</button>
            </div>
        </div>
    </div>

    <div id="toast-container" class="fixed bottom-4 right-4 z-50 space-y-3 pointer-events-none"></div>

    <script>
        // --- GLOBAL DATA ---
        let masterData = []; // Holds all Office-Station pairs
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

        // --- DROPDOWN LOGIC ---

        // 1. Load all pairs on startup
        const loadDropdownData = async () => {
            try {
                masterData = await apiCall('get_dropdowns'); // returns [{office: 'A', station: '1'}, ...]
                populateOfficeDropdown();
            } catch (error) {
                console.error(error);
                showToast('Failed to load configuration data', 'error');
            }
        };

        // 2. Populate just the Office dropdown (Unique Values)
        const populateOfficeDropdown = (selectedOffice = null) => {
            const officeSelect = document.getElementById('office');
            // Extract unique offices
            const uniqueOffices = [...new Set(masterData.map(item => item.office))];
            
            officeSelect.innerHTML = `<option value="" disabled selected>Select an Office</option>`;
            uniqueOffices.forEach(office => {
                const option = document.createElement('option');
                option.value = office;
                option.textContent = office;
                if (office === selectedOffice) option.selected = true;
                officeSelect.appendChild(option);
            });
        };

        // 3. Filter Stations based on Selected Office
        const filterStations = (selectedStation = null) => {
            const officeSelect = document.getElementById('office');
            const stationSelect = document.getElementById('station');
            const currentOffice = officeSelect.value;

            // Reset Station Dropdown
            stationSelect.innerHTML = '<option value="" disabled selected>Select a Station</option>';
            stationSelect.disabled = true;
            stationSelect.classList.add('bg-gray-50', 'cursor-not-allowed');

            if (!currentOffice) return;

            // Filter: Find all items where item.office matches selected office
            const validStations = masterData
                .filter(item => item.office === currentOffice)
                .map(item => item.station);

            // Populate Station Dropdown
            validStations.forEach(station => {
                const option = document.createElement('option');
                option.value = station;
                option.textContent = station;
                if (station === selectedStation) option.selected = true;
                stationSelect.appendChild(option);
            });

            // Enable Station Dropdown
            stationSelect.disabled = false;
            stationSelect.classList.remove('bg-gray-50', 'cursor-not-allowed');
        };

        // --- CRUD OPERATIONS ---

        const fetchRecords = async () => {
            document.getElementById('loading-state').classList.remove('hidden');
            try {
                const records = await apiCall('get_records');
                document.getElementById('loading-state').classList.add('hidden');
                
                if (records.success === false) throw new Error(records.message);

                if (records.length === 0) {
                    document.getElementById('empty-state').classList.remove('hidden');
                    document.getElementById('data-table-body').innerHTML = '';
                } else {
                    document.getElementById('empty-state').classList.add('hidden');
                    renderTable(records);
                }
            } catch (error) {
                document.getElementById('loading-state').classList.add('hidden');
                showToast(error.message, 'error');
            }
        };

        const renderTable = (records) => {
            const tbody = document.getElementById('data-table-body');
            tbody.innerHTML = records.map(r => `
                <tr class="hover:bg-gray-50 transition-colors border-b border-gray-100">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">${r.id}</td>
                    <td class="px-6 py-4 text-sm text-gray-900 font-semibold">${r.office}</td>
                    <td class="px-6 py-4 text-sm text-gray-900">${r.station}</td>
                    <td class="px-6 py-4 text-right text-sm">
                        <button onclick="openModal({id: ${r.id}, office: '${r.office}', station: '${r.station}'})" class="text-blue-600 hover:text-blue-900 mx-1 p-2"><i data-lucide="square-pen" class="w-5 h-5"></i></button>
                        <button onclick="openDeleteModal(${r.id})" class="text-red-600 hover:text-red-900 mx-1 p-2"><i data-lucide="trash-2" class="w-5 h-5"></i></button>
                    </td>
                </tr>
            `).join('');
            lucide.createIcons();
        };

        const saveRecord = async (data) => {
            try {
                const res = await apiCall('save_record', 'POST', data);
                if (res.success) {
                    showToast(res.message, 'success');
                    closeModal();
                    fetchRecords();
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
                    fetchRecords();
                } else throw new Error(res.message);
            } catch (error) {
                showToast(error.message, 'error');
            }
        };

        // --- UI HANDLERS ---

        window.openModal = (record = null) => {
            const modal = document.getElementById('crud-modal');
            const form = document.getElementById('record-form');
            form.reset();
            document.getElementById('record-id').value = '';

            if (record) {
                document.getElementById('modal-title').textContent = 'Edit Record';
                document.getElementById('record-id').value = record.id;
                
                // 1. Set Office
                populateOfficeDropdown(record.office);
                // 2. Trigger filter to get correct stations for this office
                filterStations(record.station);
            } else {
                document.getElementById('modal-title').textContent = 'Add New Record';
                populateOfficeDropdown(); // Reset to empty
                filterStations(); // Reset to empty/disabled
            }

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
            await loadDropdownData();
            await fetchRecords();
            
            // Event Listener: When Office Changes -> Filter Stations
            document.getElementById('office').addEventListener('change', () => {
                filterStations(); 
            });

            document.getElementById('add-new-btn').addEventListener('click', () => openModal());
            
            document.getElementById('record-form').addEventListener('submit', (e) => {
                e.preventDefault();
                saveRecord({
                    id: document.getElementById('record-id').value,
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