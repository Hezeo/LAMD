<?php
require '../../config.php'; 

$lot_number = isset($_GET['lot']) ? $_GET['lot'] : '';

$records = [];
if ($lot_number !== '') {
    $sql = "SELECT * FROM tbl_main WHERE lot_no = :lot ORDER BY expiry_date DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['lot' => $lot_number]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// --- LOGIC TO GROUP DATA INTO ONE SUMMARY CARD ---
$summary = null;
if (!empty($records)) {
    $first = $records[0]; 
    $summary = [
        'cms_application_no'      => $first['cms_application_no'],
        'vendor'           => $first['vendor'],
        'contracting_party'=> $first['contracting_party'],
        'location'         => "{$first['barangay']}, {$first['municipality']}, {$first['province']}",
        'lot_no'           => $first['lot_no'],
        'lease_status'     => $first['lease_status'],
        'contract_status'  => $first['contract_status'],
        'field_no'         => $first['field_no'],
        'field_section'    => $first['field_section'],
        'crop_type'        => $first['crop_type'],
        'contract_class'   => $first['contract_class'],
        'rate'             => $first['rate'],
        'total_arable'     => 0,
        'accounts'         => []
    ];

    foreach ($records as $row) {
        $summary['total_arable'] += (float)$row['contracted_arable'];
        $summary['accounts'][] = [
            'name'   => $row['group_acc'],
            'arable' => $row['contracted_arable']
        ];
    }
}

function formatNum($num) {
    return number_format((float)$num, 4, '.', ',');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href="../../images/web_icon.png" type="image/x-icon" />
    <title>Lot Summary - <?php echo htmlspecialchars($lot_number); ?></title>
    <style>
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

        .container { max-width: 800px; margin: 0 auto; }
        
        /* Structured Card Style */
        .record-group {
            background: #ffffff;
            border: 1px solid var(--dm-border);
            border-top: 5px solid var(--dm-green); /* Corporate Accent */
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        /* Status Tags */
        .status {
            padding: 3px 10px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            border: 1px solid;
            border-radius: 3px;
            margin-right: 5px;
        }
        .status.active { border-color: #2e7d32; color: #2e7d32; background: #e8f5e9; }
        .status.expired { border-color: var(--dm-red); color: var(--dm-red); background: #ffebee; }

        h2 { 
            color: var(--dm-green); 
            margin: 10px 0 20px 0; 
            font-size: 1.6em; 
            border-bottom: 1px solid var(--dm-border);
            padding-bottom: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 20px;
            margin-bottom: 20px;
        }

        p { margin: 8px 0; font-size: 13px; border-bottom: 1px solid #f9f9f9; padding-bottom: 4px; }
        .label { font-weight: bold; color: #666; width: 140px; display: inline-block; }

        /* Group Accounts Section */
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

        .account-box ul { list-style: none; padding: 0; margin: 0; }
        .account-box li { 
            padding: 8px 5px; 
            font-size: 13px; 
            border-bottom: 1px solid #eee; 
            display: flex;
            justify-content: space-between;
        }
        .account-box li:last-child { border-bottom: none; }
        
        .total-highlight {
            font-size: 1.2em;
            color: var(--dm-red);
            font-weight: bold;
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
        <div class="record-group">
            <div style="margin-bottom: 15px;">
                <span class="<?php echo $leaseClass; ?>"><?php echo $summary['lease_status']; ?></span>
                <span class="<?php echo $contractClass; ?>"><?php echo $summary['contract_status']; ?></span>
            </div>

            <p><span class="label">Contracting Party:</span> <span style="font-size: 1.2em; font-weight: bold; color: #000;"><?php echo $summary['contracting_party']; ?></span></p>
            <p><span class="label">Location:</span> <?php echo $summary['location']; ?></p>
            
            <h2>Lot No: <?php echo htmlspecialchars($summary['lot_no']); ?></h2>
            
            <div class="info-grid">
                <div>
                    <p><span class="label">CMS Application No.:</span> <?php echo $summary['cms_application_no']; ?></p>
                    <p><span class="label">Vendor:</span> <?php echo $summary['vendor']; ?></p>
                    <p><span class="label">Field No:</span> <?php echo $summary['field_no']; ?></p>
                </div>
                <div>
                    <p><span class="label">Field Section:</span> <?php echo $summary['field_section']; ?></p>
                    <p><span class="label">Crop Type:</span> <?php echo $summary['crop_type']; ?></p>
                    <p><span class="label">Contract Type:</span> <?php echo $summary['contract_class']; ?></p>
                </div>
            </div>

            <p><span class="label">Current Rate:</span> ₱<?php echo formatNum($summary['rate']); ?></p>
            <p><span class="label">Total Arable Area:</span> <span class="total-highlight"><?php echo formatNum($summary['total_arable']); ?> has</span></p>

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
        </div>
    <?php endif; ?>
</div>

</body>
</html>