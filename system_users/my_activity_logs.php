<?php
require_once __DIR__ . '/../global/header.php';
require_once __DIR__ . '/../config/db_connection.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$roleId = (int)($_SESSION['role_id'] ?? 0); // Assuming role_id is stored in session

// 1. Fetch Basic User Data
$userStmt = $conn->prepare('SELECT * FROM users WHERE user_id = :id LIMIT 1');
$userStmt->execute([':id' => $userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];

// 2. Define the Role-Based Logic
// If Role is 1 (HR) or 2 (Accounting), filter by their own ID. 
// Otherwise, you can choose to show all (remove WHERE) or keep it the same.
$whereClause = "";
$params = [];

if ($roleId === 1 || $roleId === 2) {
    // Restricted view: only own logs
    $whereClause = "WHERE user_id = :id";
    $params = [
        ':id' => $userId,
        ':id_two' => $userId
    ];
} else {
    // Admin or other view: see all logs
    $whereClause = ""; 
    $params = [];
}

$query = "
    SELECT 
        'Activity' AS log_type,
        action AS action_type,
        description AS details,
        NULL AS ip_address,
        created_at AS timestamp
    FROM activity_logs 
    $whereClause
    
    UNION ALL
    
    SELECT 
        'Login' AS log_type,
        'Access' AS action_type,
        CONCAT('Browser: ', COALESCE(browser, 'N/A'), ' | Platform: ', COALESCE(platform, 'N/A')) AS details,
        ip_address,
        login_time AS timestamp
    FROM login_logs 
    " . str_replace(':id', ':id_two', $whereClause) . "
    
    ORDER BY timestamp DESC
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$logsJson = json_encode($logs, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />

<style>
    body { font-family: 'Poppins', sans-serif; background-color: #f8f9fa; }
    #wrapper { overflow-x: hidden; }
    #page-content-wrapper { 
        flex: 1; /* Automatically takes up remaining space next to sidebar */
        min-width: 0; /* Critical for preventing flexbox overflow */
        width: 100%;
        overflow-x: hidden; 
    }
</style>


<div class="d-flex" id="wrapper">
    <?php include __DIR__ . '/../global/sidebar.php'; ?>

    <div id="page-content-wrapper" class="w-100">
        <div class="container-fluid py-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-600" style="color: #003366;">My Activity Logs</h5>
                        <small class="text-muted" style="font-size: 0.7rem;">
                            (Logged in as: <?php echo ($roleId === 1 ? 'ACCOUNTING' : 'HR'); ?>)
                        </small>
                    <input type="text" id="tabulator-search" class="form-control form-control-sm w-25" placeholder="Search all columns...">
                </div>
                <div class="card-body">
                    <div id="logs-table"></div>
                </div>
            </div>
        </div>
</div>

<!-- Tabulator Dependencies -->
<link href="https://unpkg.com/tabulator-tables@5.5.0/dist/css/tabulator_bootstrap5.min.css" rel="stylesheet">
<script type="text/javascript" src="https://unpkg.com/tabulator-tables@5.5.0/dist/js/tabulator.min.js"></script>

<script nonce="<?php echo htmlspecialchars(csp_nonce(), ENT_QUOTES, 'UTF-8'); ?>">
    // Ensure tableData is correctly parsed from the PHP json_encode
    const tableData = <?php echo $logsJson; ?>;

    // Initialize Tabulator
    const table = new Tabulator("#logs-table", {
        data: tableData, // Load the unified logs
        layout: "fitColumns", // Responsive column fitting
        responsiveLayout: "collapse",
        pagination: "local", // Internal pagination for speed
        paginationSize: 10,
        paginationSizeSelector: [10, 25, 50, 100],
        movableColumns: true,
        placeholder: "No activity or login logs found",
        columns: [
            {
                title: "Category", 
                field: "log_type", 
                width: 120, 
                headerFilter: "list", 
                headerFilterParams: {values: {"Activity": "Activity", "Login": "Login"}},
                formatter: function(cell) {
                    const val = cell.getValue();
                    // Navy/Royal Blue for Login, Green for Activity
                    const badgeClass = val === 'Login' ? 'bg-primary' : 'bg-success';
                    return `<span class="badge ${badgeClass}">${val}</span>`;
                }
            },
            {
                title: "Action", 
                field: "action_type", 
                width: 150, 
                headerFilter: "input"
            },
            {
                title: "Description", 
                field: "details", 
                headerFilter: "input", 
                formatter: "textarea" // Wraps long browser/platform strings
            },
            {
                title: "IP Address", 
                field: "ip_address", 
                width: 150, 
                headerFilter: "input",
                formatter: function(cell) {
                    return cell.getValue() || '<span class="text-muted">N/A</span>';
                }
            },
            {
                title: "Timestamp", 
                field: "timestamp", 
                width: 200, 
                sorter: "datetime", 
                headerFilter: "input",
                formatter: function(cell) {
                    // Formatting the SQL datetime for better readability
                    const val = cell.getValue();
                    return val ? new Date(val).toLocaleString() : '';
                }
            },
        ],
    });

    // Global Search Functionality for the #tabulator-search input
    document.getElementById("tabulator-search").addEventListener("keyup", function(e) {
        const value = e.target.value;
        table.setFilter([
            [
                {field: "log_type", type: "like", value: value},
                {field: "action_type", type: "like", value: value},
                {field: "details", type: "like", value: value},
                {field: "ip_address", type: "like", value: value},
                {field: "timestamp", type: "like", value: value},
            ]
        ]);
    });
</script>