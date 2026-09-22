<?php
require '../config.php'; // your PDO connection file
require_once '../config_session.php';
checkLogin();

// ADD THIS LINE: Block access if they don't have 'Dashboard' permission
requirePermission('Reports Generator'); 

$user_id = getCurrentUserId();
$message = '';
$error = '';

// 1. Prepare and Execute the SQL query
$sql = "SELECT 
    id,
    cms_application_no,
    vendor,
    contracting_party,
    group_acc,
    lot_no,
    field_no,
    field_section,
    barangay,
    municipality,
    province,
    contracted_arable,
    start_date,
    expiry_date,
    paid_up_date,
    rate,
    crop_type,
    lease_status,
    contract_status,
    contract_class 
FROM tbl_main";

// Execute query
$stmt = $pdo->prepare($sql);
$stmt->execute();
$landData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- DYNAMIC YEAR LOGIC START ---
$years = [];
foreach ($landData as $row) {
    // Check start_date ONLY
    if (!empty($row['start_date']) && $row['start_date'] !== '0000-00-00') {
        $years[] = date('Y', strtotime($row['start_date']));
    }
}

// Get unique years and sort them
$years = array_unique($years);
sort($years);

// If no data, default to current year
if (empty($years)) {
    $years[] = date('Y');
}
// --- DYNAMIC YEAR LOGIC END ---

// --- FETCH USER DATA ---
try {
    $stmt = $pdo->prepare("SELECT * FROM tbl_users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user)
        die("User not found");

    $full_name = trim($user['first_name'] . ' ' . ($user['middle_initial'] ? $user['middle_initial'] . '.' : '') . ' ' . $user['last_name']);
    $full_name = preg_replace('/\s+/', ' ', $full_name);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
$default_cloud_url = "https://i.ibb.co/kVPtjmbK/default-profile-pic.png";
$header_profile_image = !empty($user['profile_image']) ? $user['profile_image'] : $default_cloud_url;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon"
        href="https://upload.wikimedia.org/wikipedia/commons/thumb/3/35/Logo_Del_Monte.svg/2560px-Logo_Del_Monte.svg.png"
        type="image/x-icon" />

    <!-- Boxicons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet' />
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <!-- My CSS -->
    <link rel="stylesheet" href="css/user_report.css" />

    <title>Analytics | Land Asset Management Department</title>
</head>

<body>
    <!-- FIX: PREVENT FLASH OF WRONG THEME -->
    <script>
        (function() {
            try {
                const theme = localStorage.getItem('theme');
                if (theme === 'dark') {
                    document.body.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>
    <!-- END FIX -->
     
	<!-- SIDEBAR -->
	<section id="sidebar">
		<?php include 'components/user_sidebar.php'; ?>
	</section>
	<!-- SIDEBAR -->

    <!-- CONTENT -->
    <section id="content">

		<?php include 'components/user_navbar.php'; ?>

        <!-- MAIN -->
        <main>
            <div class="head-title">
                <div class="left">
                    <h1>Reports Generator</h1>
                    <!-- <ul class="breadcrumb">
                        <li><a href="#">Dashboard</a></li>
                        <li><i class='bx bx-chevron-right'></i></li>
                        <li><a class="active" href="#">Reports Generator</a></li>
                    </ul> -->
                </div>
            </div>

            <!-- CONTROLS SECTION -->
            <div class="report-controls">
                <div class="control-group">
                    <label>Report Type:</label>
                    <select id="reportType" class="modern-select">
                        <option value="" disabled selected>-- Select Report --</option>
                        <option value="contract">Contract Status</option>
                        <option value="crops">Crop Distribution Analysis</option>
                        <option value="expiry">Contract Expiry Monitoring</option>
                    </select>
                </div>

                <div class="control-group">
                    <label>Filter Year:</label>
                    <select id="filterYear" class="modern-select">
                        <option value="all">All Time</option>
                        <?php foreach ($years as $yr): ?>
                            <option value="<?= $yr ?>"><?= $yr ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="control-actions">
                    <button id="generateBtn" class="btn-generate">
                        <i class='bx bx-play-circle'></i> Generate Report
                    </button>
                    <button onclick="printReport()" id="printBtn" class="btn-action btn-print" title="Print Report">
                        <i class='bx bx-printer'></i>
                    </button>
                    <button onclick="downloadReport()" id="downloadBtn" class="btn-action btn-download"
                        title="Download as Image">
                        <i class='bx bx-download'></i>
                    </button>
                </div>
            </div>

            <!-- REPORT DISPLAY AREA -->
            <div id="reportWrapper" class="report-wrapper" style="display: none;">
                <div id="reportContainer" class="report-paper">
                    <!-- Dynamic Header -->
                    <div class="report-header">
                        <h2 id="reportTitle">Report Title</h2>
                        <p id="reportSubtitle">Generated on: <span id="reportDate"></span></p>
                    </div>

                    <!-- Dynamic Chart Area -->
                    <div id="chartContainer" class="chart-container">
                        <canvas id="mainChartCanvas"></canvas>
                    </div>

                    <!-- Dynamic Table Area -->
                    <div id="tableContainer" class="table-container">
                        <table id="reportTable">
                            <thead id="tableHead"></thead>
                            <tbody id="tableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

        </main>
    </section>

    <!-- Pass PHP Data to JS -->
    <script>
        const SERVER_DATA = <?php echo json_encode($landData); ?>;
    </script>

    <!-- Scripts -->
    <script src="js/user_report.js"></script>
	
	<!-- Theme Sync Script -->
    <script>
        // Check localStorage for theme preference and apply it immediately
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') {
            document.body.classList.add('dark');
        } else {
            document.body.classList.remove('dark');
        }
    </script>
</body>

</html>