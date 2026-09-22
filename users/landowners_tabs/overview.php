<?php
require '../../config.php';

// 1. Get ID, Lot, and Name from URL
 $id = isset($_GET['id']) ? $_GET['id'] : '';
 $lot_number = isset($_GET['lot']) ? $_GET['lot'] : '';
 $contracting_party = isset($_GET['name']) ? $_GET['name'] : '';

 $records = [];

// --- SMART QUERY LOGIC (CORRECTED) ---

// Step 1: Determine the Target Lot and Party
 $target_lot = null;
 $target_party = null;

if (!empty($id)) {
    // PRIORITY 1: Use ID to find the correct Lot/Party combination
    try {
        $stmt_ref = $pdo->prepare("SELECT lot_no, contracting_party FROM tbl_main WHERE id = :id LIMIT 1");
        $stmt_ref->execute([':id' => $id]);
        $ref_data = $stmt_ref->fetch(PDO::FETCH_ASSOC);

        if ($ref_data) {
            $target_lot = $ref_data['lot_no'];
            $target_party = $ref_data['contracting_party'];
        }
    } catch (PDOException $e) {
        // Handle error silently or log
    }
} elseif ($lot_number !== '' && $contracting_party !== '') {
    // PRIORITY 2: Fallback to URL parameters
    $target_lot = $lot_number;
    $target_party = $contracting_party;
}

// Step 2: Fetch ALL records matching that Lot and Party
if ($target_lot && $target_party) {
    $sql = "SELECT * FROM tbl_main WHERE lot_no = :lot AND contracting_party = :name ORDER BY expiry_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':lot' => $target_lot,
        ':name' => $target_party
    ]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- HELPER FUNCTIONS ---

// UPDATED: Better handling of precision
function formatNum($num, $precision = null)
{
    // 1. Clean the number
    $cleanNum = str_replace(',', '', (string)$num); // Cast to string to be safe
    $floatVal = (float) $cleanNum;

    // 2. If specific valid numeric precision is requested, use it
    if (is_numeric($precision)) {
        return number_format($floatVal, (int)$precision, '.', ',');
    }

    // 3. Otherwise, detect decimals automatically (Default behavior)
    // This preserves whatever decimals are calculated
    $decimals = 0;
    if (strpos($cleanNum, '.') !== false) {
        $parts = explode('.', $cleanNum);
        if (isset($parts[1])) {
            $decimals = strlen($parts[1]);
        }
    }

    return number_format($floatVal, $decimals, '.', ',');
}

// Format Dates
function formatDate($dateStr)
{
    if (empty($dateStr) || $dateStr === '0000-00-00') {
        return 'N/A';
    }
    return date('F d, Y', strtotime($dateStr));
}

// --- LOGIC TO GROUP DATA INTO ONE SUMMARY CARD ---
 $summary = null;
 $processor_name = null;

if (!empty($records)) {
    $first = $records[0];
    
    // Check if longlat exists in the DB result, default to empty string if null
    $longlatValue = isset($first['longlat']) ? $first['longlat'] : '';

    $summary = [
        'cms_application_no' => $first['cms_application_no'],
        'vendor' => $first['vendor'],
        'contracting_party' => $first['contracting_party'],
        'location' => "{$first['barangay']}, {$first['municipality']}, {$first['province']}",
        'lot_no' => $first['lot_no'],
        'lease_status' => $first['lease_status'],
        'contract_status' => $first['contract_status'],
        'field_no' => $first['field_no'],
        'field_section' => $first['field_section'],
        'crop_type' => $first['crop_type'],
        'contract_class' => $first['contract_class'],
        'rate' => $first['rate'],
        'longlat' => $longlatValue, 
        'total_arable' => 0,
        'accounts' => [],
        'inception_date' => $first['inception_date'] ?? null,
        'start_date' => $first['start_date'] ?? null,
        'expiry_date' => $first['expiry_date'] ?? null,
        'paid_up_date' => $first['paid_up_date'] ?? null
    ];

    foreach ($records as $row) {
        // Use (float) for calculation, but keep raw for display if needed
        $summary['total_arable'] += (float) $row['contracted_arable'];
        $summary['accounts'][] = [
            'name' => $row['group_acc'],
            'arable' => $row['contracted_arable']
        ];
    }

    // --- FETCH PROCESSOR NAME ---
    $main_id = $first['id'];

    try {
        $sql_proc = "SELECT u.first_name, u.middle_initial, u.last_name 
                     FROM tbl_contracts_and_users cu
                     INNER JOIN tbl_users u ON cu.user_id = u.user_id
                     WHERE cu.id = :id 
                     LIMIT 1";
        $stmt_proc = $pdo->prepare($sql_proc);
        $stmt_proc->execute([':id' => $main_id]);
        $processor_data = $stmt_proc->fetch(PDO::FETCH_ASSOC);

        if ($processor_data) {
            $middle = !empty($processor_data['middle_initial']) ? ' ' . $processor_data['middle_initial'] : '';
            $processor_name = $processor_data['first_name'] . $middle . ' ' . $processor_data['last_name'];
        }
    } catch (PDOException $e) {
        // Silently fail
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../../images/web_icon.png" type="image/x-icon" />
    <title>Overview | Lot Summary - <?php echo htmlspecialchars($lot_number); ?></title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Poppins:wght@400;500;600;700&display=swap');

        /* Del Monte Inspired Palette */
        :root {
            --dm-green: #006837;
            --dm-red: #d32f2f;
            --dm-light-gray: #f4f4f4;
            --dm-border: #cccccc;
            --dm-text: #333333;
        }

        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: var(--dm-light-gray);
            padding: 40px;
            color: var(--dm-text);
            margin: 0;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .record-group {
            background: #ffffff;
            border: 1px solid var(--dm-border);
            border-top: 5px solid var(--dm-green);
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .status {
            padding: 3px 10px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            border: 1px solid;
            border-radius: 3px;
            margin-right: 5px;
        }

        /* STATUS COLORS */
        .status.active { background: #2aa146; color: #fff; }
        .status.forreturn { background: #ffc107; color: #ffffff; }
        .status.returned { background: #17a2b8; color: #fff; }
        .status.extension { background: #6f42c1; color: #fff; }
        .status.nonrenewing { background: #fd7e14; color: #fff; }
        .status.newland { background: #20c997; color: #fff; }
        .status.expired { background: #dc3545; color: #fff; }
        .status.unutilized { background: #6610f2; color: #fff; }
        .status.renewed { background: #2778c9; color: #fff; }
        .status.nearexpiration { background: #ff4800d3; color: #fff; }
        .status.existing { background: #56afeb; color: #fff; }

        h2 {
            color: var(--dm-green);
            margin: 10px 0 20px 0;
            font-size: 1.6em;
            border-bottom: 1px solid var(--dm-border);
            padding-bottom: 10px;
        }

        h3 {
            color: var(--dm-green);
            margin: 25px 0 15px 0;
            font-size: 1.2em;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 20px;
            margin-bottom: 20px;
        }

        p {
            margin: 8px 0;
            font-size: 13px;
            border-bottom: 1px solid #f9f9f9;
            padding-bottom: 4px;
        }

        .label {
            font-weight: bold;
            color: #666;
            width: 140px;
            display: inline-block;
        }

        .account-box {
            margin-top: 25px;
            background: #fafafa;
            border: 1px solid var(--dm-border);
            padding: 15px;
        }

        .account-box-header {
            background: var(--dm-green);
            color: white;
            padding: 5px 10px;
            font-size: 12px;
            font-weight: bold;
            margin: -15px -15px 15px -15px;
        }

        .account-box ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .account-box li {
            padding: 8px 5px;
            font-size: 13px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
        }

        .account-box li:last-child {
            border-bottom: none;
        }

        .total-highlight {
            font-size: 1.2em;
            color: var(--dm-red);
            font-weight: bold;
        }

        .location-link-container {
            margin-top: 25px;
            text-align: center;
        }

        .location-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            background-color: #006837;
            color: #ffffff;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: bold;
            font-size: 14px;
            transition: background-color 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .location-btn:hover {
            background-color: #005028;
        }

        .location-btn i {
            font-size: 18px;
        }

        .processor-info {
            text-align: right;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px dashed #ccc;
            font-size: 12px;
            color: #666;
            font-style: italic;
        }
    </style>
</head>

<body>

    <div class="container">
        <?php if (!$summary): ?>
            <div class="record-group" style="text-align:center;">
                <h3>No Records Found</h3>
                <p>The requested lot details are not available in the database.</p>
            </div>
        <?php else:
            $leaseClass = "status " . strtolower(str_replace(' ', '', $summary['lease_status']));
            $contractClass = "status " . strtolower(str_replace(' ', '', $summary['contract_status']));
        ?>
            <div>
                <h3>OVERVIEW</h3>
            </div>
            <div class="record-group">
                <div style="position: relative; margin-bottom: 25px;">
                    <span class="<?php echo $leaseClass; ?>"><?php echo $summary['lease_status']; ?></span>
                    <span class="<?php echo $contractClass; ?>"><?php echo $summary['contract_status']; ?></span>
                    <img src="../../images/web_icon.png" style="position: absolute; height: 70px; right: 5px; top: -20px;">
                </div>

                <p><span class="label">Contracting Party:</span> <span
                        style="font-size: 1.2em; font-weight: bold; color: #000;"><?php echo $summary['contracting_party']; ?></span>
                </p>
                
                <div class="info-grid">
                    <div>
                        <p><span class="label">CMS Application No.:</span> <?php echo $summary['cms_application_no']; ?></p>
                        <p><span class="label">Vendor:</span> <?php echo $summary['vendor']; ?></p>
                    </div>
                </div>

                <h2>Lot No: <?php echo htmlspecialchars($summary['lot_no']); ?></h2>
                <p><span class="label">Location:</span> <?php echo $summary['location']; ?></p>

                <div class="info-grid">
                    <div>
                        <p><span class="label">Field No:</span> <?php echo $summary['field_no']; ?></p>
                        <p><span class="label">Field Section:</span> <?php echo $summary['field_section']; ?></p>
                        <p><span class="label">Longlat:</span> <?php echo empty($summary['longlat']) ? 'NONE' : $summary['longlat']; ?></p>
                    </div>
                    <div>
                        <p><span class="label">Crop Type:</span> <?php echo $summary['crop_type']; ?></p>
                        <p><span class="label">Contract Type:</span> <?php echo $summary['contract_class']; ?></p>
                    </div>
                </div>

                <!-- NEW DATES SECTION -->
                <h3>Contract Timeline</h3>
                <div style="margin-bottom: 20px;">
                    <div style="display: flex; gap: 20%;">
                        <p style="margin: 0;"><span class="label">Start Date:</span>
                            <?php echo formatDate($summary['start_date']); ?></p>
                        <p style="margin: 0;"><span class="label">Expiry Date:</span>
                            <?php echo formatDate($summary['expiry_date']); ?></p>
                        <p style="margin: 0;"><span class="label">Paid Up Date:</span>
                            <?php echo formatDate($summary['paid_up_date']); ?></p>
                    </div>
                </div>
                
                <p><span class="label">Current Rate:</span> ₱<?php echo formatNum($summary['rate']); ?></p>
                
                <!-- FIXED: Removed ", false" so it displays all decimals without rounding -->
                <p><span class="label">Total Arable Area:</span> <span
                        class="total-highlight"><?php echo formatNum($summary['total_arable']); ?> has</span></p>

                <div class="account-box">
                    <div class="account-box-header">GROUP ACCOUNTS BREAKDOWN</div>
                    <ul>
                        <?php foreach ($summary['accounts'] as $acc): ?>
                            <li>
                                <span><?php echo $acc['name']; ?></span>
                                <span><strong><?php echo formatNum($acc['arable']); ?></strong> has</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <?php if ($processor_name): ?>
                    <div class="processor-info">
                        Processed by: <strong><?php echo htmlspecialchars($processor_name); ?></strong>
                    </div>
                <?php endif; ?>

            </div>

            <?php if (!empty($summary['longlat'])): ?>
                <div class="location-link-container">
                    <a href="https://earth.google.com/web/search/<?php echo urlencode($summary['longlat']); ?>" target="_blank"
                        class="location-btn">
                        <img src="../images/GOOGLE_EARTH_LOGO.png" style="height: 20px; width: 20px; border-radius: 50%;"> View Location on Google Earth
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

</body>

</html>