<?php
require_once '../config.php';
require_once '../config_session.php';
checkLogin();

// ADD THIS LINE: Block access if they don't have 'Dashboard' permission
requirePermission('Dashboard'); 

$user_id = getCurrentUserId();
$message = '';
$error = '';

// QUERY FOR CARDS
try {
	// Query 1: Total Expiring - count contracts expiring in current year
	// Determine fiscal year start and end based on today's date
	$current_month_day = date('m-d');

	if ($current_month_day >= '05-01') {
		$start_year = date('Y');
		$end_year = date('Y') + 1;
	} else {
		$start_year = date('Y') - 1;
		$end_year = date('Y');
	}

	$fiscal_year_start = "$start_year-05-01";
	$fiscal_year_end = "$end_year-04-30";

	// CORRECTED LOGIC: Use start_year for the label
    // Example: May1, 2026 - April 30, 2027 -> $start_year is 2026 -> FY26
    $fiscal_year_label = 'FY' . substr($start_year, 2);









	// =========================================================================
// 0 - Query for TOTAL CONTRACTS (Fixed: Added contracting_party to Group By)
// =========================================================================

// MAIN QUERY: Count unique contracts (Landowner + Inception Dates)
 $stmt0 = $pdo->query("
    SELECT COUNT(*) as total_contracts 
    FROM (
        SELECT 
            contracting_party,  -- ADDED THIS
            start_date, 
            expiry_date, 
            paid_up_date 
        FROM tbl_main 
        WHERE start_date IS NOT NULL 
          AND expiry_date IS NOT NULL 
          AND paid_up_date IS NOT NULL
          AND contracting_party IS NOT NULL -- Added safety check
        GROUP BY 
            contracting_party, -- ADDED THIS
            start_date, 
            expiry_date, 
            paid_up_date
    ) as unique_contracts
");
 $total_contracts = $stmt0->fetch(PDO::FETCH_ASSOC)['total_contracts'] ?? 0;

// 0A - Query for NEW contracts within CURRENT fiscal year (Fixed)
 $stmt0A = $pdo->prepare("
    SELECT COUNT(*) as count_new_contracts
    FROM (
        SELECT 
            contracting_party, -- ADDED THIS
            start_date, 
            expiry_date, 
            paid_up_date 
        FROM tbl_main 
        WHERE start_date IS NOT NULL 
          AND expiry_date IS NOT NULL 
          AND paid_up_date IS NOT NULL
          AND contracting_party IS NOT NULL
          AND start_date BETWEEN ? AND ? 
        GROUP BY 
            contracting_party, -- ADDED THIS
            start_date, 
            expiry_date, 
            paid_up_date
    ) as unique_contracts_fy
");
 $stmt0A->execute([$fiscal_year_start, $fiscal_year_end]);
 $new_contracts_this_fy = $stmt0A->fetch(PDO::FETCH_ASSOC)['count_new_contracts'] ?? 0;

// 0B - Previous FY start and end dates
 $prev_fiscal_year_start = ($start_year - 1) . "-05-01";
 $prev_fiscal_year_end = ($end_year - 1) . "-04-30";

// Query for PREVIOUS fiscal year's contracts (Fixed)
 $stmt0B = $pdo->prepare("
    SELECT COUNT(*) as count_prev_contracts
    FROM (
        SELECT 
            contracting_party, -- ADDED THIS
            start_date, 
            expiry_date, 
            paid_up_date 
        FROM tbl_main 
        WHERE start_date IS NOT NULL 
          AND expiry_date IS NOT NULL 
          AND paid_up_date IS NOT NULL
          AND contracting_party IS NOT NULL
          AND start_date BETWEEN ? AND ? 
        GROUP BY 
            contracting_party, -- ADDED THIS
            start_date, 
            expiry_date, 
            paid_up_date
    ) as unique_contracts_prev_fy
");
 $stmt0B->execute([$prev_fiscal_year_start, $prev_fiscal_year_end]);
 $prev_contracts_fy = $stmt0B->fetch(PDO::FETCH_ASSOC)['count_prev_contracts'] ?? 0;

// Calculate difference
 $diff_contracts = $new_contracts_this_fy - $prev_contracts_fy;

// Determine icon and color based on difference
 $icon_contracts = ($diff_contracts > 0) ? '../users/images/arrow_up_golden_green.png' : (($diff_contracts < 0) ? '../users/images/arrow_down_golden_green.png' : '');
 $color_contracts = ($diff_contracts > 0) ? '#4CAF50' : (($diff_contracts < 0) ? '#F44336' : '#999');

// Calculate percentage change
if ($prev_contracts_fy == 0) {
    $contracts_percentage_change = 0; 
} else {
    $contracts_percentage_change = ($diff_contracts / $prev_contracts_fy) * 100;
}
 $formatted_contracts_percentage_change = number_format(abs($contracts_percentage_change), 2);







	// 1 - Query for total lots contracted
	// $stmt1 = $pdo->query("SELECT COUNT(*) as total_lots_contracted FROM tbl_main");
	// $total_lots_contracted = $stmt1->fetch(PDO::FETCH_ASSOC)['total_lots_contracted'] ?? 0;

	$stmt1 = $pdo->query("SELECT COUNT(DISTINCT lot_no, field_no) as total_lots_contracted FROM tbl_main");
	$total_lots_contracted = $stmt1->fetch(PDO::FETCH_ASSOC)['total_lots_contracted'] ?? 0;

			// 1A - Query for new contracts within current fiscal year
			// $stmt1A = $pdo->prepare("SELECT COUNT(*) as count_new_lots_contracted FROM tbl_main WHERE start_date BETWEEN ? AND ?");
			$stmt1A = $pdo->prepare("SELECT COUNT(DISTINCT lot_no, field_no) as count_new_lots_contracted FROM tbl_main WHERE start_date BETWEEN ? AND ?");
			$stmt1A->execute([$fiscal_year_start, $fiscal_year_end]);
			$new_lots_contracted_this_fy = $stmt1A->fetch(PDO::FETCH_ASSOC)['count_new_lots_contracted'] ?? 0;

			// 1B - Previous FY start and end dates
			$prev_fiscal_year_start = ($start_year - 1) . "-05-01";
			$prev_fiscal_year_end = ($end_year - 1) . "-04-30";

			// Query for previous fiscal year's contracts
			$stmt1B = $pdo->prepare("SELECT COUNT(*) as count_prev_lots_contracted FROM tbl_main WHERE start_date BETWEEN ? AND ?");
			$stmt1B->execute([$prev_fiscal_year_start, $prev_fiscal_year_end]);
			$prev_lots_contracted_fy = $stmt1B->fetch(PDO::FETCH_ASSOC)['count_prev_lots_contracted'] ?? 0;

			// Calculate difference
			$diff_contracts = $new_lots_contracted_this_fy - $prev_lots_contracted_fy;

			// Determine icon and color based on difference
			$icon_contracts = ($diff_contracts > 0) ? '../users/images/arrow_up_golden_green.png' : (($diff_contracts < 0) ? '../users/images/arrow_down_golden_green.png' : '');
			$color_contracts = ($diff_contracts > 0) ? '#4CAF50' : (($diff_contracts < 0) ? '#F44336' : '#999');

			// Calculate percentage change
			if ($prev_lots_contracted_fy == 0) {
				$lots_contracted_percentage_change = 0; // or handle as needed
			} else {
				$lots_contracted_percentage_change = ($diff_contracts / $prev_lots_contracted_fy) * 100;
			}
			$formatted_lots_contracted_percentage_change = number_format(abs($lots_contracted_percentage_change), 2);


	// 2 - Query for total land owners
	$stmt2 = $pdo->query("SELECT COUNT(DISTINCT contracting_party) as total_landowners FROM tbl_main");
	$total_landowners = $stmt2->fetch(PDO::FETCH_ASSOC)['total_landowners'] ?? 0;

			// 2A - Query for + Land Owners within Current Fiscal Year
			$stmt2A = $pdo->prepare("SELECT COUNT(DISTINCT contracting_party) as count_new_landowners FROM tbl_main WHERE start_date BETWEEN ? AND ?");
			$stmt2A->execute([$fiscal_year_start, $fiscal_year_end]);
			$new_landowners_this_fy = $stmt2A->fetch(PDO::FETCH_ASSOC)['count_new_landowners'] ?? 0;


			// 2B - Previous FY start and end dates
			$prev_fiscal_year_start = ($start_year - 1) . "-05-01";
			$prev_fiscal_year_end = ($end_year - 1) . "-04-30";

			// Query for previous fiscal year's landowners
			$stmt2B = $pdo->prepare("SELECT COUNT(DISTINCT contracting_party) as count_prev_landowners FROM tbl_main WHERE start_date BETWEEN ? AND ?");
			$stmt2B->execute([$prev_fiscal_year_start, $prev_fiscal_year_end]);
			$prev_landowners_fy = $stmt2B->fetch(PDO::FETCH_ASSOC)['count_prev_landowners'] ?? 0;

			// Calculate difference
			$diff_landowners = $new_landowners_this_fy - $prev_landowners_fy;

			// Set icon and color based on the difference
			$icon_landowners = ($diff_landowners > 0) ? '../users/images/arrow_up_golden_green.png' : (($diff_landowners < 0) ? '../users/images/arrow_down_golden_green.png' : '');
			$color_landowners = ($diff_landowners > 0) ? '#4CAF50' : (($diff_landowners < 0) ? '#F44336' : '#999');

			// Calculate percentage change
			if ($prev_landowners_fy == 0) {
				$landowners_percentage_change = 0; // or handle as needed
			} else {
				$landowners_percentage_change = ($diff_landowners / $prev_landowners_fy) * 100;
			}
			$formatted_landowners_percentage_change = number_format(abs($landowners_percentage_change), 2);


	// 3 - Query for total hectares - sum of contracted_arable
	// $stmt3 = $pdo->query("SELECT COALESCE(SUM(contracted_arable), 0) as total_hectares FROM tbl_main");
	// $total_hectares = $stmt3->fetch(PDO::FETCH_ASSOC)['total_hectares'];

	$stmt3 = $pdo->query("SELECT COALESCE(SUM(hectares), 0) as total_hectares FROM (SELECT AVG(contracted_arable) as hectares FROM tbl_main WHERE lot_no IS NOT NULL AND lot_no != '' GROUP BY lot_no, field_no) as distinct_lots");
	$total_hectares = $stmt3->fetch(PDO::FETCH_ASSOC)['total_hectares'];

			// 3A - Query for + Hectares within Current Fiscal Year
			// $stmt3A = $pdo->prepare("SELECT COALESCE(SUM(contracted_arable), 0) as count_new_hectares FROM tbl_main WHERE start_date BETWEEN ? AND ?");
			$stmt3A = $pdo->prepare("SELECT COALESCE(SUM(hectares), 0) as count_new_hectares FROM (SELECT AVG(contracted_arable) as hectares FROM tbl_main WHERE start_date BETWEEN ? AND ? GROUP BY lot_no, field_no) as distinct_lots");
			$stmt3A->execute([$fiscal_year_start, $fiscal_year_end]);
			$new_hectares_this_fy = $stmt3A->fetch(PDO::FETCH_ASSOC)['count_new_hectares'] ?? 0;

			// 3B - Previous FY start and end dates
			// (Note: You likely already have these variables from Step 2, but repeating them here ensures this block works independently)
			$prev_fiscal_year_start = ($start_year - 1) . "-05-01";
			$prev_fiscal_year_end = ($end_year - 1) . "-04-30";

			// Query for previous fiscal year's hectares
			$stmt3B = $pdo->prepare("SELECT COALESCE(SUM(contracted_arable), 0) as count_prev_hectares FROM tbl_main WHERE start_date BETWEEN ? AND ?");
			$stmt3B->execute([$prev_fiscal_year_start, $prev_fiscal_year_end]);
			$prev_hectares_fy = $stmt3B->fetch(PDO::FETCH_ASSOC)['count_prev_hectares'] ?? 0;

			// Calculate difference
			$diff_hectares = $new_hectares_this_fy - $prev_hectares_fy;

			// Set icon and color based on the difference
			$icon_hectares = ($diff_hectares > 0) ? '../users/images/arrow_up_golden_green.png' : (($diff_hectares < 0) ? '../users/images/arrow_down_golden_green.png' : '');
			$color_hectares = ($diff_hectares > 0) ? '#4CAF50' : (($diff_hectares < 0) ? '#F44336' : '#999');

			// Calculate percentage change
			if ($prev_hectares_fy == 0) {
				$hectares_percentage_change = 0; // Handle division by zero
			} else {
				$hectares_percentage_change = ($diff_hectares / $prev_hectares_fy) * 100;
			}
			$formatted_hectares_percentage_change = number_format(abs($hectares_percentage_change), 2);


	// 4 - Query for new land
	$stmt4 = $pdo->query("SELECT COUNT(contract_status) as total_newlands FROM tbl_main WHERE contract_status = 'NEWLAND'");
	$total_newlands = $stmt4->fetch(PDO::FETCH_ASSOC)['total_newlands'] ?? 0;

			// 4A - Query for + New lands within Current Fiscal Year (Keep original logic for "+ X New Lands this FY")
			$stmt4A = $pdo->prepare("SELECT COUNT(contract_status) as count_newlands FROM tbl_main WHERE contract_status = 'NEWLAND' AND start_date BETWEEN ? AND ?");
			$stmt4A->execute([$fiscal_year_start, $fiscal_year_end]);
			$newlands_this_fy = $stmt4A->fetch(PDO::FETCH_ASSOC)['count_newlands'] ?? 0;

			// --- NEW LOGIC FOR PERCENTAGE TREND (Based on ALL contracts) ---

			// 4B - Previous FY start and end dates
			$prev_fiscal_year_start = ($start_year - 1) . "-05-01";
			$prev_fiscal_year_end = ($end_year - 1) . "-04-30";

			// Query for previous fiscal year's TOTAL contracts (Removed NEWLAND condition)
			$stmt4B_prev = $pdo->prepare("SELECT COUNT(*) as count_prev_total FROM tbl_main WHERE start_date BETWEEN ? AND ?");
			$stmt4B_prev->execute([$prev_fiscal_year_start, $prev_fiscal_year_end]);
			$prev_total_fy = $stmt4B_prev->fetch(PDO::FETCH_ASSOC)['count_prev_total'] ?? 0;

			// Query for current fiscal year's TOTAL contracts (Removed NEWLAND condition)
			$stmt4B_curr = $pdo->prepare("SELECT COUNT(*) as count_curr_total FROM tbl_main WHERE start_date BETWEEN ? AND ?");
			$stmt4B_curr->execute([$fiscal_year_start, $fiscal_year_end]);
			$curr_total_fy = $stmt4B_curr->fetch(PDO::FETCH_ASSOC)['count_curr_total'] ?? 0;

			// Calculate difference based on TOTAL contracts
			$diff_newlands = $curr_total_fy - $prev_total_fy;

			// Set icon and color based on the difference
			$icon_newlands = ($diff_newlands > 0) ? '../users/images/arrow_up_golden_green.png' : (($diff_newlands < 0) ? '../users/images/arrow_down_golden_green.png' : '');
			$color_newlands = ($diff_newlands > 0) ? '#4CAF50' : (($diff_newlands < 0) ? '#F44336' : '#999');

			// Calculate percentage change
			if ($prev_total_fy == 0) {
				$newlands_percentage_change = 0;
			} else {
				$newlands_percentage_change = ($diff_newlands / $prev_total_fy) * 100;
			}
			$formatted_newlands_percentage_change = number_format(abs($newlands_percentage_change), 2);

	

	// Format the numbers (optional, for better display)
	$formatted_total_contracts = number_format($total_contracts);
	$formatted_total_lots_contracted = number_format($total_lots_contracted);
	$formatted_total_landowners = number_format($total_landowners);
	$formatted_total_hectares = number_format($total_hectares, 2);
	$formatted_total_newlands = number_format($total_newlands);
	
} catch (PDOException $e) {
	// Handle database errors
	$formatted_total_contracts = "Error";
	$formatted_total_lots_contracted = "Error";
	$formatted_total_landowners = "Error";
	$formatted_total_hectares = "Error";
	$formatted_total_newlands = "Error";

	error_log("Database Error: " . $e->getMessage());
}






?>


<?php

// QUERY FOR ALL CROP TYPE DISTRIBUTION START
try {
    // 1. Get Overall Total Arable (Deduplicated)
    $stmt_total_arable = $pdo->query("
        SELECT COALESCE(SUM(hectares), 0) as total 
        FROM (
            SELECT AVG(contracted_arable) as hectares 
            FROM tbl_main 
            WHERE lot_no IS NOT NULL AND lot_no != '' 
            GROUP BY lot_no, field_no
        ) as distinct_lots
    ");
    $overall_total_arable = (float) $stmt_total_arable->fetch(PDO::FETCH_ASSOC)['total'];

    // 2. Get Totals for ALL Crop Types
    $stmt_all_crops = $pdo->query("
        SELECT crop_type, COALESCE(SUM(hectares), 0) as total 
        FROM (
            SELECT crop_type, AVG(contracted_arable) as hectares 
            FROM tbl_main 
            WHERE lot_no IS NOT NULL AND lot_no != '' 
            GROUP BY lot_no, field_no, crop_type
        ) as distinct_lots
        GROUP BY crop_type
    ");
    
    $crops_data = $stmt_all_crops->fetchAll(PDO::FETCH_KEY_PAIR);

    // 3. Define all crop types
    $crop_types = ['C74', 'S16', 'OP', 'PAPAYA', 'AVOCADO', 'WHSE', 'BODEGA'];

    // 4. Initialize variables with 5 decimal precision
    foreach ($crop_types as $type) {
        $type_lower = strtolower($type);
        
        // Set Total
        ${$type_lower . '_total'} = isset($crops_data[$type]) ? (float) $crops_data[$type] : 0;
        
        // Set Percentage (Precision 5)
        ${$type_lower . '_percent'} = ($overall_total_arable > 0) 
            ? round(${$type_lower . '_total'} / $overall_total_arable * 100, 5) 
            : 0;
    }

} catch (PDOException $e) {
    $overall_total_arable = 0;
    $crop_types = ['C74', 'S16', 'OP', 'PAPAYA', 'AVOCADO', 'WHSE', 'BODEGA'];
    foreach ($crop_types as $type) {
        $type_lower = strtolower($type);
        ${$type_lower . '_total'} = 0;
        ${$type_lower . '_percent'} = 0;
    }
    error_log("Crop Distribution Error: " . $e->getMessage());
}
// QUERY FOR ALL CROP TYPE DISTRIBUTION END


// QUERY FOR S16 AND C74 DISTRIBUTION END
// try {
//     // 1. Get Overall Total Arable (Benchmark)
//     $stmt_total_arable = $pdo->query("SELECT COALESCE(SUM(contracted_arable), 0) as total FROM tbl_main");
//     $overall_total_arable = (float) $stmt_total_arable->fetch(PDO::FETCH_ASSOC)['total'];

//     // 2. Get Total Arable for S16
//     $stmt_s16 = $pdo->prepare("SELECT COALESCE(SUM(contracted_arable), 0) as total FROM tbl_main WHERE crop_type = 'S16'");
//     $stmt_s16->execute();
//     $s16_total = (float) $stmt_s16->fetch(PDO::FETCH_ASSOC)['total'];

//     // 3. Get Total Arable for C74
//     $stmt_c74 = $pdo->prepare("SELECT COALESCE(SUM(contracted_arable), 0) as total FROM tbl_main WHERE crop_type = 'C74'");
//     $stmt_c74->execute();
//     $c74_total = (float) $stmt_c74->fetch(PDO::FETCH_ASSOC)['total'];

//     // 4. Calculate Percentages
//     $s16_percent = ($overall_total_arable > 0) ? round(($s16_total / $overall_total_arable) * 100, 1) : 0;
//     $c74_percent = ($overall_total_arable > 0) ? round(($c74_total / $overall_total_arable) * 100, 1) : 0;

// } catch (PDOException $e) {
//     $s16_total = 0;
//     $c74_total = 0;
//     $overall_total_arable = 0;
//     $s16_percent = 0;
//     $c74_percent = 0;
//     error_log("S16/C74 Error: " . $e->getMessage());
// }
// QUERY FOR S16 AND C74 DISTRIBUTION END

// QUERY PARA SA PIE CHART (Dynamic Barangay List) - UPDATED LOGIC
try {
    // Inner query: Group distinct contracts first (Landowner + Dates)
    // We calculate the total arable per contract and collect barangays per contract here.
    $stmt_pie_inner = $pdo->query("
        SELECT 
            municipality,
            SUM(contracted_arable) as contract_arable,
            GROUP_CONCAT(DISTINCT CONCAT(COALESCE(barangay, 'Unknown'), ':', contracted_arable) SEPARATOR '||') as barangay_list
        FROM tbl_main
        WHERE municipality IS NOT NULL AND municipality != ''
        AND contracting_party IS NOT NULL
        GROUP BY contracting_party, start_date, expiry_date, paid_up_date
    ");

    // Outer query: Group by municipality for the dashboard
    $stmt_pie = $pdo->query("
        SELECT 
            municipality, 
            COUNT(*) as contracts_count, 
            SUM(contract_arable) as total_arable,
            GROUP_CONCAT(barangay_list SEPARATOR '||') as barangay_details
        FROM (
            " . $stmt_pie_inner->queryString . "
        ) as distinct_contracts
        GROUP BY municipality
        ORDER BY total_arable DESC
    ");
    
    // Re-run the logic manually or fetch since we can't execute a string directly like that in PDO without prepare.
    // BETTER APPROACH FOR PDO: Subquery in single statement
    
    $stmt_pie = $pdo->query("
        SELECT 
            municipality, 
            COUNT(*) as contracts_count, 
            SUM(contract_arable) as total_arable,
            GROUP_CONCAT(barangay_list SEPARATOR '||') as barangay_details
        FROM (
            SELECT 
                municipality,
                SUM(contracted_arable) as contract_arable,
                GROUP_CONCAT(DISTINCT CONCAT(COALESCE(barangay, 'Unknown'), ':', contracted_arable) SEPARATOR '||') as barangay_list
            FROM tbl_main
            WHERE municipality IS NOT NULL AND municipality != ''
            AND contracting_party IS NOT NULL
            GROUP BY contracting_party, start_date, expiry_date, paid_up_date
        ) as distinct_contracts
        GROUP BY municipality
        ORDER BY total_arable DESC
    ");

    $pie_raw = $stmt_pie->fetchAll(PDO::FETCH_ASSOC);

    $formatted_pie_data = [];
    foreach ($pie_raw as $row) {
        $brgy_array = [];
        $parts = explode('||', $row['barangay_details']);

        // Dito natin ginugrupo ang barangay para hindi paulit-ulit ang pangalan
        $temp_brgy = [];
        foreach ($parts as $p) {
            // Safety check for empty parts
            if(empty($p)) continue;
            list($name, $val) = explode(':', $p);
            if (!isset($temp_brgy[$name]))
                $temp_brgy[$name] = 0;
            $temp_brgy[$name] += (float) $val;
        }
        foreach ($temp_brgy as $name => $val) {
            $brgy_array[] = ['name' => $name, 'arable' => $val];
        }

        $formatted_pie_data[] = [
            'label' => strtoupper($row['municipality']),
            'contracts' => (int) $row['contracts_count'],
            'hectares' => (float) $row['total_arable'],
            'barangays' => $brgy_array
        ];
    }
} catch (PDOException $e) {
    $formatted_pie_data = [];
}
// QUERY PARA SA PIE CHART END

// QUERY FOR CONTRACTS PER FISCAL YEAR LINECHART START (Based on start_date) - UPDATED LOGIC
try {
    // 1. Get the raw min and max year from start_date
    $stmt_con_range = $pdo->query("
        SELECT 
            MIN(YEAR(start_date)) as min_year,
            MAX(YEAR(start_date)) as max_year
        FROM tbl_main 
        WHERE start_date IS NOT NULL
    ");
    $con_range = $stmt_con_range->fetch(PDO::FETCH_ASSOC);

    $raw_con_min_year = $con_range['min_year'] ?: date('Y');
    $raw_con_max_year = $con_range['max_year'] ?: date('Y');

    // 2. Determine the actual FISCAL year range
    $start_con_fiscal_year = $raw_con_min_year;
    $end_con_fiscal_year = $raw_con_max_year + 1;

    // 3. Query to get counts grouped by fiscal year
    // LOGIC CHANGE: Group inner query by contract definition, then count by Fiscal Year
    $stmt_con_chart = $pdo->prepare("
        SELECT 
            fiscal_year,
            COUNT(*) as total_contracts
        FROM (
            SELECT 
                CASE 
                    WHEN MONTH(start_date) >= 5 THEN YEAR(start_date)
                    ELSE YEAR(start_date) - 1
                END as fiscal_year
            FROM tbl_main 
            WHERE start_date IS NOT NULL 
            AND contracting_party IS NOT NULL
            GROUP BY contracting_party, start_date, expiry_date, paid_up_date
        ) as distinct_contracts
        WHERE fiscal_year BETWEEN ? AND ?
        GROUP BY fiscal_year
        ORDER BY fiscal_year
    ");

    $stmt_con_chart->execute([$start_con_fiscal_year, $end_con_fiscal_year]);
    $con_chart_data = $stmt_con_chart->fetchAll(PDO::FETCH_ASSOC);

    $con_years = [];
    $con_counts = [];

    // 4. Organize data into a lookup array
    $con_year_data = [];
    foreach ($con_chart_data as $row) {
        $con_year_data[$row['fiscal_year']] = (int) $row['total_contracts'];
    }

    // 5. Generate labels using the Fiscal Year range
    for ($year = $start_con_fiscal_year; $year <= $end_con_fiscal_year; $year++) {
        // Only add the year if we have data for it
        if (isset($con_year_data[$year]) && $con_year_data[$year] > 0) {
            $con_years[] = 'FY' . substr($year, 2); // e.g., FY24
            $con_counts[] = $con_year_data[$year];
        }
    }

} catch (PDOException $e) {
    // Fallback
    $con_years = [date('Y')];
    $con_counts = [0];
    error_log("Contracts Chart Error: " . $e->getMessage());
}
// QUERY FOR CONTRACTS PER FISCAL YEAR LINECHART END


// QUERY FOR TOTAL LOTS EXPIRING LINECHART START
try {
    // 1. Get the raw min and max year from the database
    // Used DISTINCT or simple MIN/MAX on dates is efficient here
    $stmt_range = $pdo->query("
        SELECT 
            MIN(YEAR(expiry_date)) as min_year,
            MAX(YEAR(expiry_date)) as max_year
        FROM tbl_main 
        WHERE expiry_date IS NOT NULL
    ");
    $year_range = $stmt_range->fetch(PDO::FETCH_ASSOC);

    $raw_min_year = $year_range['min_year'] ?: date('Y');
    $raw_max_year = $year_range['max_year'] ?: date('Y');

    // 2. Determine the actual FISCAL year range
    $start_fiscal_year = $raw_min_year;
    $end_fiscal_year = $raw_max_year + 1;

    // 3. Query to get counts grouped by fiscal year (DEDUPLICATED)
    // We use a subquery to get distinct lots and their arable size first,
    // then calculate the fiscal year and sum them up.
    $stmt_chart = $pdo->prepare("
        SELECT 
            fiscal_year,
            COUNT(*) as count,
            SUM(hectares) as total_arable
        FROM (
            SELECT 
                lot_no,
                field_no,
                AVG(contracted_arable) as hectares,
                CASE 
                    WHEN MONTH(expiry_date) >= 5 THEN YEAR(expiry_date)
                    ELSE YEAR(expiry_date) - 1
                END as fiscal_year
            FROM tbl_main 
            WHERE expiry_date IS NOT NULL
            AND lot_no IS NOT NULL AND lot_no != ''
            GROUP BY lot_no, field_no, fiscal_year
        ) as distinct_lots
        WHERE fiscal_year BETWEEN ? AND ?
        GROUP BY fiscal_year
        ORDER BY fiscal_year
    ");
    
    // Pass the calculated fiscal range to the query
    $stmt_chart->execute([$start_fiscal_year, $end_fiscal_year]);
    $chart_data = $stmt_chart->fetchAll(PDO::FETCH_ASSOC);

    $chart_years = [];
    $chart_counts = [];
    $chart_arable = [];

    // 4. Organize data into a lookup array
    $year_data = [];
    foreach ($chart_data as $row) {
        $year_data[$row['fiscal_year']] = [
            'count' => (int) $row['count'],
            'arable' => (float) ($row['total_arable'] ?: 0)
        ];
    }

    // 5. Generate labels using the Fiscal Year range
    for ($year = $start_fiscal_year; $year <= $end_fiscal_year; $year++) {
        // Only add the year if we have data for it
        if (isset($year_data[$year]) && $year_data[$year]['count'] > 0) {
            $chart_years[] = 'FY' . substr($year, 2); // e.g., FY27
            $chart_counts[] = $year_data[$year]['count'];
            $chart_arable[] = $year_data[$year]['arable'];
        }
    }


} catch (PDOException $e) {
    // Fallback to current year if error
    $current_year = date('Y');
    $chart_years = [$current_year];
    $chart_counts = [0];
    error_log("Database Error: " . $e->getMessage());
}
// QUERY FOR TOTAL LOTS EXPIRING LINECHART END


// QUERY FOR BARGRAPH (Contract Class) START
try {
	// Group by contract_class and count the IDs
	$stmt_bar = $pdo->query("
        SELECT 
            contract_class, 
            COUNT(*) as count 
        FROM tbl_main 
        WHERE contract_class IS NOT NULL AND contract_class != ''
        GROUP BY contract_class 
        ORDER BY count DESC
    ");
	$bar_data_raw = $stmt_bar->fetchAll(PDO::FETCH_ASSOC);

	// Prepare arrays for Chart.js
	$bar_labels = [];
	$bar_counts = [];

	foreach ($bar_data_raw as $row) {
		$bar_labels[] = $row['contract_class'];
		$bar_counts[] = (int) $row['count'];
	}

} catch (PDOException $e) {
	$bar_labels = [];
	$bar_counts = [];
	error_log("Bar Chart Error: " . $e->getMessage());
}
// QUERY FOR BARGRAPH END

// QUERY FOR BARGRAPH (Contract Status) START
try {
	// Group by contract_status and count the IDs
	$stmt_status = $pdo->query("
        SELECT 
            contract_status, 
            COUNT(*) as count 
        FROM tbl_main 
        WHERE contract_status IS NOT NULL AND contract_status != ''
        GROUP BY contract_status 
        ORDER BY count DESC
    ");
	$status_data_raw = $stmt_status->fetchAll(PDO::FETCH_ASSOC);

	// Prepare arrays for Chart.js
	$status_labels = [];
	$status_counts = [];

	foreach ($status_data_raw as $row) {
		$status_labels[] = $row['contract_status'];
		$status_counts[] = (int) $row['count'];
	}

} catch (PDOException $e) {
	$status_labels = [];
	$status_counts = [];
	error_log("Status Bar Chart Error: " . $e->getMessage());
}
// QUERY FOR BARGRAPH (Contract Status) END


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

<?php
// ---------------------------------------------------
// HANDLE TO-DO ACTIONS (Redirect Logic)
// ---------------------------------------------------

// 1. HANDLE ADD TO-DO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_todo'])) {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : NULL;
    $priority_level = $_POST['priority_level'] ?? 'Normal';
    
    // NEW: Get Progress
    $progress = isset($_POST['progress']) ? (int)$_POST['progress'] : 0;
    
    // LOGIC: If progress is 100%, status is completed. Else, queue.
    $status = ($progress >= 100) ? 'completed' : 'queue';
    
    $dateAdded = date('Y-m-d H:i:s');
    $dateModified = $dateAdded;

    $stmt = $pdo->prepare("INSERT INTO tbl_todos (user_id, title, description, due_date, priority_level, status, progress, dateAdded, dateModified) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $title, $description, $due_date, $priority_level, $status, $progress, $dateAdded, $dateModified]);
    header("Location: user_dashboard.php");
    exit();
}

// 2. HANDLE EDIT TASK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_todo'])) {
    $todo_id = $_POST['todo_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : NULL;
    $priority_level = $_POST['priority_level'];
    
    // NEW: Get Progress
    $progress = isset($_POST['progress']) ? (int)$_POST['progress'] : 0;

    // LOGIC: Auto-update status based on progress
    $status = ($progress >= 100) ? 'completed' : 'queue';

    $stmt = $pdo->prepare("UPDATE tbl_todos SET title = ?, description = ?, due_date = ?, priority_level = ?, progress = ?, status = ?, dateModified = NOW() WHERE todo_id = ? AND user_id = ?");
    $stmt->execute([$title, $description, $due_date, $priority_level, $progress, $status, $todo_id, $user_id]);
    header("Location: user_dashboard.php");
    exit();
}

// 3. HANDLE ACTIONS (DELETE / COMPLETE)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = $_GET['id'];

    if ($action === 'delete') {
        $stmt = $pdo->prepare("UPDATE tbl_todos SET status = 'deleted', dateModified = NOW() WHERE todo_id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
    }

    if ($action === 'complete') {
        // UPDATE: Set progress to 100 when clicking check icon
        $stmt = $pdo->prepare("UPDATE tbl_todos SET status = 'completed', progress = 100, dateModified = NOW() WHERE todo_id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
    }

    header("Location: user_dashboard.php");
    exit();
}
// ---------------------------------------------------
?>



<?php
// DASHBOARD TABLE
// --- HANDLE UPLOAD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $orientation = $_POST['orientation']; 
    $file = $_FILES['csv_file'];
    
    if ($file['error'] === 0) {
        $fileName = basename($file['name']);
        
        try {
            // 1. Save File Record
            $stmt = $pdo->prepare("INSERT INTO csv_uploads (file_name, orientation) VALUES (?, ?)");
            $stmt->execute([$fileName, $orientation]);
            $uploadId = $pdo->lastInsertId();

            // 2. Read CSV
            $csvData = [];
            if (($handle = fopen($file['tmp_name'], "r")) !== FALSE) {
                $firstLine = fgets($handle);
                rewind($handle);
                $delimiter = ',';
                if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
                    $delimiter = ';';
                }
                
                // We read all rows, but we will clean them later
                while (($row = fgetcsv($handle, 1000, $delimiter)) !== FALSE) {
                    $csvData[] = array_map('trim', $row); 
                }
                fclose($handle);
            }

            // 3. Insert into DB
            $insertRow = $pdo->prepare("INSERT INTO csv_rows (upload_id, row_data, row_order) VALUES (?, ?, ?)");

            if ($orientation === 'vertical') {
                foreach ($csvData as $index => $row) {
                    $dataJson = json_encode(['label' => $row[0] ?? '', 'value' => $row[1] ?? '']);
                    $insertRow->execute([$uploadId, $dataJson, $index]);
                }
            } else {
                foreach ($csvData as $index => $row) {
                    $dataJson = json_encode($row);
                    $insertRow->execute([$uploadId, $dataJson, $index]);
                }
            }
            
            $message = "File saved successfully!";
            
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}

// --- HANDLE DELETE ---
if (isset($_GET['delete_id'])) {
    $stmt = $pdo->prepare("DELETE FROM csv_uploads WHERE id = ?");
    $stmt->execute([$_GET['delete_id']]);
    header("Location: file_upload.php");
    exit;
}

// --- FETCH FOR DISPLAY ---
 $stmt = $pdo->query("SELECT * FROM csv_uploads ORDER BY created_at DESC");
 $savedFiles = $stmt->fetchAll();

 $activeId = isset($_GET['file_id']) ? $_GET['file_id'] : ($savedFiles[0]['id'] ?? 0);

 $displayData = [];
 $displayType = 'horizontal';
 $activeFileName = '';

if ($activeId) {
    $fileStmt = $pdo->prepare("SELECT * FROM csv_uploads WHERE id = ?");
    $fileStmt->execute([$activeId]);
    $activeFile = $fileStmt->fetch();
    
    if ($activeFile) {
        $activeFileName = $activeFile['file_name'];
        $displayType = $activeFile['orientation'];
        
        $rowStmt = $pdo->prepare("SELECT * FROM csv_rows WHERE upload_id = ? ORDER BY row_order ASC");
        $rowStmt->execute([$activeId]);
        $rows = $rowStmt->fetchAll();

        foreach ($rows as $row) {
            $displayData[] = json_decode($row['row_data'], true);
        }
    }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<link rel="icon" href="../images/web_icon.png" type="image/x-icon" />

	<!-- Boxicons -->
	<link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
	<link href='https://unpkg.com/boxicons@2.1.4/dist/boxicons.js' rel='stylesheet'>

	<!-- Chart -->
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
	<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
	<script src="js/user_dashChart.js" defer></script>

	<!-- My CSS -->
	<link rel="stylesheet" href="css/user_dashboard.css">

	<title>Dashboard | Land Asset Management Department</title>
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
					<h1>User Dashboard</h1>
					<!-- <ul class="breadcrumb">
						<li>
							<a href="#">Dashboard</a>
						</li>
						<li><i class='bx bx-chevron-right'></i></li>
						<li>
							<a class="active" href="#">Home</a>
						</li>
					</ul> -->
				</div>
			</div>




			<div class="dashboard-data" style="background-color: transparent;">
				<div class="dashboard-records" style="background-color: transparent;">


					<!-- LEFT COLUMN START -->

					<div class="records-left" style="background-color: transparent;">

            			<div class="section-title-cards"><img src="../users/images/arrow_up_golden_green.png" width="20"> Land Portfolio Analytics</div>
						<!-- CARDS ROW START -->
						<div class="count-cards-row">
							<!-- <li onclick="window.location.href='#'" class="contracts-card">
								<div class="dcards-content">
									<i class='bx bxs-file bx-lg'></i>
									<span class="text" style="display: grid;">
										<h3 class="count-up" data-target="<?php echo intval(str_replace(',', '', $formatted_total_contracts)); ?>">0</h3>
										<p>Total Contracts</p>
										<small class="plus-count">
											+<span class="plus-count-num" data-target="<?php echo intval(str_replace(',', '', $new_contracts_this_fy)); ?>">0</span> Contracts this
											<?php echo $fiscal_year_label; ?>
										</small>
										< !-- <span
                                            style="margin-top: -6px; font-size: 11px; font-weight: bold; color: <?php echo $color_contracts; ?>;">
                                            <img src="<?php echo $icon_contracts ?>" width="20"
                                                style="vertical-align: middle;">
                                            <?php echo ($lots_contracted_percentage_change > 0 ? '+' : ($lots_contracted_percentage_change < 0 ? '-' : '')); ?>
                                            <?php echo $formatted_lots_contracted_percentage_change; ?>%
                                            <em style="font-weight: 300; font-size: 11px;">from
                                                FY<?php echo substr($end_year - 1, 2); ?> </em>
                                        </span> -- >
									</span>
								</div>
							</li> -->
							<li onclick="window.location.href='#'" class="lots-contracted-card">
								<div class="dcards-content">
									<i class='bx bxs-file bx-lg'></i>
									<span class="text" style="display: grid;">
										<h3 class="count-up" data-target="<?php echo intval(str_replace(',', '', $formatted_total_lots_contracted)); ?>">0</h3>
										<p>Total Lots Contracted by Del Monte</p>
										<small class="plus-count">
											+<span class="plus-count-num" data-target="<?php echo intval(str_replace(',', '', $new_lots_contracted_this_fy)); ?>">0</span> Lots Contracted this
											<?php echo $fiscal_year_label; ?>
										</small>
										<!-- <span
                                            style="margin-top: -6px; font-size: 11px; font-weight: bold; color: <?php echo $color_contracts; ?>;">
                                            <img src="<?php echo $icon_contracts ?>" width="20"
                                                style="vertical-align: middle;">
                                            <?php echo ($lots_contracted_percentage_change > 0 ? '+' : ($lots_contracted_percentage_change < 0 ? '-' : '')); ?>
                                            <?php echo $formatted_lots_contracted_percentage_change; ?>%
                                            <em style="font-weight: 300; font-size: 11px;">from
                                                FY<?php echo substr($end_year - 1, 2); ?> </em>
                                        </span> -->
									</span>
								</div>
							</li>
							<li onclick="window.location.href='#'" class="landowners-card">
								<div class="dcards-content">
									<i class='bx bxs-group bx-lg'></i>
									<span class="text" style="display: grid;">
										<h3 class="count-up" data-target="<?php echo intval(str_replace(',', '', $formatted_total_landowners)); ?>">0</h3>
										<p>Total Land Owners</p>
										<small class="plus-count">
											+<span class="plus-count-num" data-target="<?php echo intval(str_replace(',', '', $new_landowners_this_fy)); ?>">0</span> Land Owners this
											<?php echo $fiscal_year_label; ?>
										</small>
										<!-- <span
                                            style="margin-top: -6px; font-size: 11px; font-weight: bold; color: <?php echo $color_contracts; ?>;">
                                            <img src="<?php echo $icon_landowners ?>" width="20"
                                                style="vertical-align: middle;">
                                            <?php echo ($landowners_percentage_change > 0 ? '+' : ($landowners_percentage_change < 0 ? '-' : '')); ?>
                                            <?php echo $formatted_landowners_percentage_change; ?>%
                                            <em style="font-weight: 300; font-size: 11px;">from
                                                FY<?php echo substr($end_year - 1, 2); ?> </em>
                                        </span> -->
									</span>
								</div>
							</li>
							<li onclick="window.location.href='#'" class="arable-card">
								<div class="dcards-content">
									<i class='bx bx-table bx-lg'></i>
									<span class="text" style="display: grid;">
										<h3 class="count-up" data-target="<?php echo floatval(str_replace(',', '', $formatted_total_hectares)); ?>" data-decimals="2">0</h3>
										<p>Total Arable Hectares</p>
										<small class="plus-count">
											+<span class="plus-count-num" data-target="<?php echo floatval(str_replace(',', '', $new_hectares_this_fy)); ?>" data-decimals="2">0</span> Hectares this
											<?php echo $fiscal_year_label; ?>
										</small>
										<!-- <span
                                            style="margin-top: -6px; font-size: 11px; font-weight: bold; color: <?php echo $color_contracts; ?>;">
                                            <img src="<?php echo $icon_hectares ?>" width="20"
                                                style="vertical-align: middle;">
                                            <?php echo ($hectares_percentage_change > 0 ? '+' : ($hectares_percentage_change < 0 ? '-' : '')); ?>
                                            <?php echo $formatted_hectares_percentage_change; ?>%
                                            <em style="font-weight: 300; font-size: 11px;">from
                                                FY<?php echo substr($end_year - 1, 2); ?> </em>
                                        </span> -->
									</span>
								</div>
							</li>
							<!-- <li onclick="window.location.href='#'" class="newland-card">
								<div class="dcards-content">
									<i class='bx bxs-landscape bx-lg'></i>
									<span class="text" style="display: grid;">
										<h3><?php echo htmlspecialchars($formatted_total_newlands); ?></h3>
										<p>Total Newland</p>
										<small class="plus-count">
											+<?php echo number_format($newlands_this_fy); ?> New Lands this
												<?php echo $fiscal_year_label; ?>
										</small>
										<span
                                            style="margin-top: -6px; font-size: 11px; font-weight: bold; color: #<?php echo $color_contracts; ?>;">
                                            <img src="<?php echo $icon_newlands ?>" width="20"
                                                style="vertical-align: middle;">
                                            <?php echo ($newlands_percentage_change > 0 ? '+' : ($newlands_percentage_change < 0 ? '-' : '')); ?>
                                            <?php echo $formatted_newlands_percentage_change; ?>%
                                            <em style="font-weight: 300; font-size: 11px;">from
                                                FY<?php echo substr($end_year - 1, 2); ?> </em>
										</span>
									</span>
								</div>
							</li> -->
						</div>
						<!-- CARDS ROW END -->


						<script>
							// =====================================================
							// FAST INCREMENT COUNTER EFFECT (0 to X in 2 seconds)
							// =====================================================
							function animateCounters() {
								const counters = document.querySelectorAll('.count-up');
								if (!counters.length) return;

								counters.forEach(counter => {
									const target = parseFloat(counter.getAttribute('data-target'));
									const decimals = parseInt(counter.getAttribute('data-decimals')) || 0;
									const duration = 2000; // 2 seconds
									const startTime = performance.now();

									function easeOutExpo(t) {
										// This math formula makes it count insanely fast at first, then gracefully slow down at the end
										return t === 1 ? 1 : 1 - Math.pow(2, -10 * t);
									}

									function updateCounter(currentTime) {
										const elapsed = currentTime - startTime;
										const progress = Math.min(elapsed / duration, 1);
										
										// Apply the "Fast Start, Slow End" math
										const easedProgress = easeOutExpo(progress);
										const currentValue = easedProgress * target;

										// Update the text (toLocaleString automatically adds the commas and handles decimals perfectly)
										counter.textContent = currentValue.toLocaleString('en-US', { 
											minimumFractionDigits: decimals,
											maximumFractionDigits: decimals 
										});

										// Keep looping until 2 seconds are up
										if (progress < 1) {
											requestAnimationFrame(updateCounter);
										}
									}

									// Start the animation
									requestAnimationFrame(updateCounter);
								});
							}

							// =====================================================
							// PLUS COUNT FAST INCREMENT EFFECT (Cascading Delay)
							// =====================================================
							function animatePlusCounts() {
								const plusCounters = document.querySelectorAll('.plus-count-num');
								if (!plusCounters.length) return;

								plusCounters.forEach(counter => {
									const target = parseFloat(counter.getAttribute('data-target'));
									const decimals = parseInt(counter.getAttribute('data-decimals')) || 0;
									const duration = 1500; // 1.5 seconds (slightly faster than the main number)
									const startTime = performance.now();

									function easeOutExpo(t) {
										return t === 1 ? 1 : 1 - Math.pow(2, -10 * t);
									}

									function updateCounter(currentTime) {
										const elapsed = currentTime - startTime;
										const progress = Math.min(elapsed / duration, 1);
										
										const easedProgress = easeOutExpo(progress);
										const currentValue = easedProgress * target;

										counter.textContent = currentValue.toLocaleString('en-US', { 
											minimumFractionDigits: decimals,
											maximumFractionDigits: decimals 
										});

										if (progress < 1) {
											requestAnimationFrame(updateCounter);
										}
									}

									// Start the animation
									requestAnimationFrame(updateCounter);
								});
							}

							// Trigger both animations with a staggered delay
							setTimeout(() => {
								animateCounters();     // Main numbers start immediately
								setTimeout(animatePlusCounts, 400); // Plus counts start 0.4s later (cascading effect)
							}, 300);
						</script>

						
            			<!-- <div class="section-title-table">Overall Contracted Landholdings</div> -->

						<!-- TABLE ROW START -->
						<div class="table-row" style="background-color: transparent;">
							<div class="csv-card-container">
    
								<!-- Floating Action Button -->
								<a href="file_upload.php" target="_blank" class="csv-float-update-btn">
									<i class='bx bx-sync'></i>
									<span class="btn-tooltip-text">Update Table</span>
								</a>


								<div class="table-container-scroll">
									<?php
									if (!empty($displayData)) {
										
										if ($displayType === 'vertical') {
											// --- VERTICAL VIEW ---
											echo "<table class='table table-excel'>";
											echo "<thead><tr><th>Label</th><th>Value</th></tr></thead>";
											echo "<tbody>";
											foreach ($displayData as $row) {
												echo "<tr>";
												echo "<td><strong>" . htmlspecialchars($row['label'] ?? '') . "</strong></td>";
												echo "<td>" . htmlspecialchars($row['value'] ?? '') . "</td>";
												echo "</tr>";
											}
											echo "</tbody></table>";
										} else {
											// --- HORIZONTAL VIEW WITH LOGIC ---
											
											// 1. Normalize Data (Pad rows)
											$maxCols = 0;
											foreach ($displayData as $row) {
												if (count($row) > $maxCols) $maxCols = count($row);
											}
											$normData = [];
											foreach ($displayData as $row) {
												$normData[] = array_pad($row, $maxCols, '');
											}

											// 2. FIND "USED" AREA (Remove ghost rows/cols)
											$lastRowIdx = -1;
											for ($r = count($normData) - 1; $r >= 0; $r--) {
												foreach ($normData[$r] as $cell) {
													if (trim($cell) !== '') {
														$lastRowIdx = $r;
														break 2;
													}
												}
											}
											
											if ($lastRowIdx === -1) {
												echo "<div class='csv-empty-state'><i class='bx bx-file-blank'></i><p>Empty file.</p></div>";
											} else {
												// Slice array to remove bottom empty rows
												$normData = array_slice($normData, 0, $lastRowIdx + 1);

												// Identify columns to remove (empty in ALL remaining rows)
												$colsToRemove = [];
												for ($c = 0; $c < $maxCols; $c++) {
													$isEmptyCol = true;
													foreach ($normData as $row) {
														if (isset($row[$c]) && trim($row[$c]) !== '') {
															$isEmptyCol = false;
															break;
														}
													}
													if ($isEmptyCol) $colsToRemove[] = $c;
												}

												// 3. Render Table
												echo "<table class='table table-excel'>";
												
												$isHeader = true;
												echo "<thead>";

												foreach ($normData as $row) {
													echo "<tr>";

													// Check if this specific row is empty (separator)
													$isRowEmpty = true;
													foreach ($row as $cell) {
														if (trim($cell) !== '') { $isRowEmpty = false; break; }
													}

													if ($isRowEmpty) {
														// Render separator row with invisible borders
														$visibleCols = $maxCols - count($colsToRemove);
														echo "<td colspan='$visibleCols' style='background-color: transparent; border-left:none; border-right:none; border-bottom:none;'>&nbsp;</td>";
													} else {
														for ($c = 0; $c < $maxCols; $c++) {
															if (in_array($c, $colsToRemove)) continue;

															$cellValue = $row[$c] ?? '';
															
															// Merge Logic (Colspan)
															$colspan = 1;
															for ($k = $c + 1; $k < $maxCols; $k++) {
																if (in_array($k, $colsToRemove)) continue;
																if (isset($row[$k]) && trim($row[$k]) === '') {
																	$colspan++;
																} else {
																	break;
																}
															}

															// Output
															$displayVal = htmlspecialchars($cellValue);
															if (trim($displayVal) === '') $displayVal = "&nbsp;";

															if ($isHeader) {
																echo "<th" . ($colspan > 1 ? " colspan='$colspan'" : "") . ">" . $displayVal . "</th>";
															} else {
																echo "<td" . ($colspan > 1 ? " colspan='$colspan'" : "") . ">" . $displayVal . "</td>";
															}

															if ($colspan > 1) {
																$c += ($colspan - 1);
															}
														}
													}
													echo "</tr>";
													
													if ($isHeader) {
														echo "</thead><tbody>";
														$isHeader = false;
													}
												}
												echo "</tbody></table>";
											}
										}

									} else {
										echo '<div class="csv-empty-state">
											<i class="bx bx-table"></i>
											<p>No CSV data loaded.</p>
											<small><a href="file_upload.php" class="csv-upload-link" target="_blank">Upload a file via the CSV Manager to display it here.</a></small>
										</div>';
									}
									?>
								</div>
							</div>
						</div>
						<!-- TABLE ROW END -->

						
            			<div class="section-title-s16-and-c74">
    <img src="../users/images/arrow_up_golden_green.png" width="20"> All Crop Type Distributions
</div>
                        
<!-- ROW 1: 3 CARDS (S16, C74, PAPAYA) -->
<!-- display: grid with 3 columns (repeat(3, 1fr)) -->
<div class="s16c74-row" style="background-color: transparent; display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">

    <!-- C74 Card -->
    <div class="crop-dist-card">
        <div class="crop-header">
            <div class="crop-icon-box c74">
                <i class='bx bxs-leaf'></i>
            </div>
            <div class="crop-info">
                <h3>C74</h3>
                <!-- <p>Crop Type Distribution</p> -->
            </div>
        </div>
        <div class="crop-stats">
            <span class="crop-percent"><?php echo number_format($c74_percent, 3); ?>%</span>
            <span class="crop-hectares">
                <?php echo number_format($c74_total, 2); ?> / <?php echo number_format($overall_total_arable, 2); ?> Has
            </span>
        </div>
        <div class="crop-progress-bg">
            <div class="crop-progress-fill c74" style="width: <?php echo $c74_percent; ?>%;"></div>
        </div>
    </div>
    
    <!-- S16 Card -->
    <div class="crop-dist-card">
        <div class="crop-header">
            <div class="crop-icon-box s16">
                <i class='bx bxs-leaf'></i>
            </div>
            <div class="crop-info">
                <h3>S16</h3>
                <!-- <p>Crop Type Distribution</p> -->
            </div>
        </div>
        <div class="crop-stats">
            <span class="crop-percent"><?php echo number_format($s16_percent, 3); ?>%</span>
            <span class="crop-hectares">
                <?php echo number_format($s16_total, 2); ?> / <?php echo number_format($overall_total_arable, 2); ?> Has
            </span>
        </div>
        <div class="crop-progress-bg">
            <div class="crop-progress-fill s16" style="width: <?php echo $s16_percent; ?>%;"></div>
        </div>
    </div>

    <!-- OP Card -->
    <div class="crop-dist-card">
        <div class="crop-header">
            <div class="crop-icon-box op">
                <i class='bx bxs-leaf'></i>
            </div>
            <div class="crop-info">
                <h3>OP</h3>
                <!-- <p>Crop Type Distribution</p> -->
            </div>
        </div>
        <div class="crop-stats">
            <span class="crop-percent"><?php echo number_format($op_percent, 3); ?>%</span>
            <span class="crop-hectares">
                <?php echo number_format($op_total, 2); ?> Has
            </span>
        </div>
        <div class="crop-progress-bg">
            <div class="crop-progress-fill op" style="width: <?php echo $op_percent; ?>%;"></div>
        </div>
    </div>

</div>

<!-- ROW 2: 4 CARDS (AVOCADO, OP, WHSE, BODEGA) -->
<!-- display: grid with 4 columns (repeat(4, 1fr)) -->
<div class="s16c74-row" style="background-color: transparent; margin-top: 20px; display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">

    <!-- PAPAYA Card -->
    <div class="crop-dist-card">
        <div class="crop-header">
            <div class="crop-icon-box papaya">
                <!-- <i class='bx bxs-lemon'></i> -->
                <i class='bx bxs-leaf'></i>
            </div>
            <div class="crop-info">
                <h3>PAPAYA</h3>
                <!-- <p>Crop Type Distribution</p> -->
            </div>
        </div>
        <div class="crop-stats">
            <span class="crop-percent"><?php echo number_format($papaya_percent, 3); ?>%</span>
            <span class="crop-hectares">
                <?php echo number_format($papaya_total, 2); ?> Has
            </span>
        </div>
        <div class="crop-progress-bg">
            <div class="crop-progress-fill papaya" style="width: <?php echo $papaya_percent; ?>%;"></div>
        </div>
    </div>

    <!-- AVOCADO Card -->
    <div class="crop-dist-card">
        <div class="crop-header">
            <div class="crop-icon-box avocado">
                <!-- <i class='bx bxs-tree'></i> -->
                <i class='bx bxs-leaf'></i>
            </div>
            <div class="crop-info">
                <h3>AVOCADO</h3>
                <!-- <p>Crop Type Distribution</p> -->
            </div>
        </div>
        <div class="crop-stats">
            <span class="crop-percent"><?php echo number_format($avocado_percent, 3); ?>%</span>
            <span class="crop-hectares">
                <?php echo number_format($avocado_total, 2); ?> Has
            </span>
        </div>
        <div class="crop-progress-bg">
            <div class="crop-progress-fill avocado" style="width: <?php echo $avocado_percent; ?>%;"></div>
        </div>
    </div>

    <!-- WHSE Card -->
    <div class="crop-dist-card">
        <div class="crop-header">
            <div class="crop-icon-box whse">
                <!-- <i class='bx bxs-warehouse'></i> -->
                <i class='bx bxs-leaf'></i>
            </div>
            <div class="crop-info">
                <h3>WHSE</h3>
                <!-- <p>Crop Type Distribution</p> -->
            </div>
        </div>
        <div class="crop-stats">
            <span class="crop-percent"><?php echo number_format($whse_percent, 3); ?>%</span>
            <span class="crop-hectares">
                <?php echo number_format($whse_total, 2); ?> Has
            </span>
        </div>
        <div class="crop-progress-bg">
            <div class="crop-progress-fill whse" style="width: <?php echo $whse_percent; ?>%;"></div>
        </div>
    </div>

    <!-- BODEGA Card -->
    <div class="crop-dist-card">
        <div class="crop-header">
            <div class="crop-icon-box bodega">
                <!-- <i class='bx bxs-home'></i> -->
                <i class='bx bxs-leaf'></i>
            </div>
            <div class="crop-info">
                <h3>BODEGA</h3>
                <!-- <p>Crop Type Distribution</p> -->
            </div>
        </div>
        <div class="crop-stats">
            <span class="crop-percent"><?php echo number_format($bodega_percent, 3); ?>%</span>
            <span class="crop-hectares">
                <?php echo number_format($bodega_total, 2); ?> Has
            </span>
        </div>
        <div class="crop-progress-bg">
            <div class="crop-progress-fill bodega" style="width: <?php echo $bodega_percent; ?>%;"></div>
        </div>
    </div>

</div>

            			<div class="section-title-charts-and-graphs"><img src="../users/images/arrow_up_golden_green.png" width="20"> Data Visualization & Analytics</div>


						<!-- CHARTS ROW START -->
						<div class="charts-row" style="background-color: transparent;">

							<div class="myChart">
								<!-- ADDED TITLE HERE -->
								<h3 class="piechart-card-title">Hectares and Contracts by Municipalities</h3>

								<div class="piechart-content-wrapper">
									<canvas id="myPieChart"></canvas>
									<div id="custom-legend" class="legend-container"></div>
								</div>
							</div>
							<div class="line-charts">
								<div class="linechart_for_contracts">
									<canvas id="contractsLineChart"></canvas>
								</div>
								<div class="linechart_for_expiry">
									<canvas id="expiryLineChart"></canvas>
								</div>
							</div>

						</div>

						<script>
							// Data para sa Line Chart
							const years = <?= json_encode($chart_years); ?>;
							const totalExpiring = <?= json_encode($chart_counts); ?>;
							const totalArable = <?= json_encode($chart_arable); ?>;

							// Data for the Contracts Chart
							const contractYears = <?php echo json_encode($con_years); ?>;
							const contractCounts = <?php echo json_encode($con_counts); ?>;

							// Data para sa Pie Chart
							const dynamicPieData = <?= json_encode($formatted_pie_data); ?>;
						</script>

						<!-- CHARTS ROW END -->


						<!-- BARS ROW START -->
						<div class="bargraphs-row" style="background-color: transparent;">
							<div class="bargraph">
								<!-- Existing Contract Class Graph -->
								<canvas id="cropBarGraph"></canvas>
							</div>
							<div class="bargraph">
								<!-- NEW Contract Status Graph -->
								<canvas id="statusBarGraph"></canvas>
							</div>
						</div>

						<script>
							// Data para sa Bar Graph
							const barLabels = <?= json_encode($bar_labels); ?>;
							const barCounts = <?= json_encode($bar_counts); ?>;
							// Data para sa Status Bar Graph
							const statusLabels = <?= json_encode($status_labels); ?>;
							const statusCounts = <?= json_encode($status_counts); ?>;
						</script>
						<!-- BARS ROW END -->


            			<div class="section-title-trends"><img src="../users/images/arrow_up_golden_green.png" width="20"> Landholdings Trends</div>

						<!-- LANDOWNERS TRENDS START -->
						<div class="landowners-trends-row">
						
							<?php include 'components/user_trends.php'; ?>
							
						</div>
						<!-- LANDOWNERS TRENDS END -->

					</div>
					<!-- LEFT COLUMN END -->

					<!-- RIGHT COLUMN START -->
					<div class="records-right">

						<div class="alert-section">
							<?php include 'components/user_action_required.php'; ?>
						</div>

						<div class="todos-section">
							<?php include 'components/user_todos.php'; ?>
						</div>

						<!-- <div class="awards-section">
							< ?php include 'components/user_awards.php'; ?>
						</div> -->

					</div>
					<!-- RIGHT COLUMN END -->

				</div>
			</div>





			
			
			<!-- HTML STRUCTURE -->

			<!-- 3. HIDDEN ADD MODAL -->
			<div id="addModal" class="edit-modal-container">
				<div class="edit-modal-content themed-modal">
					<div class="modal-header">
						<h3>Add New Task</h3>
						<button type="button" class="modal-close-btn" onclick="closeAddModal()">&times;</button>
					</div>
					<form method="POST" action="">
						<div class="form-group">
							<label>Task Title</label>
							<input type="text" name="title" placeholder="What needs to be done?" required>
						</div>

						<div class="form-group">
							<label>Description</label>
							<textarea name="description" placeholder="Add a note..." rows="3"></textarea>
						</div>

						<div class="form-row">
							<div class="form-group">
								<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
									<input type="checkbox" id="add_has_due_date" onchange="toggleDateField('add')" style="width: auto; margin: 0;">
									<span style="font-size: 11px; color: var(--dark-grey); font-weight: 600; text-transform: uppercase;">Set Due Date</span>
								</label>
								<div id="add_date_wrapper" class="date-wrapper" style="display: none; margin-top: 8px;">
									<input type="datetime-local" name="due_date" id="add_due_date" class="form-control" disabled>
								</div>
							</div>

							<div class="form-group">
								<label>Priority Level</label>
								<select name="priority_level" required>
									<option value="Low">Low</option>
									<option value="Normal" selected>Normal</option>
									<option value="Moderate">Moderate</option>
									<option value="Urgent">Urgent</option>
									<option value="Emergency">Emergency</option>
								</select>
							</div>
						</div>

						<!-- NEW: Progress Field -->
						<div class="form-group">
							<label>Progress</label>
							<select name="progress" class="form-control">
								<?php for($i=0; $i<=100; $i+=10): ?>
									<option value="<?= $i ?>" <?= $i == 0 ? 'selected' : '' ?>><?= $i ?>%</option>
								<?php endfor; ?>
							</select>
						</div>

						<div class="modal-actions">
							<button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
							<button type="submit" name="add_todo" class="btn-save">Save Task</button>
						</div>
					</form>
				</div>
			</div>

			<!-- 3B. HIDDEN EDIT MODAL -->
			<div id="editModal" class="edit-modal-container">
				<div class="edit-modal-content themed-modal">
					<div class="modal-header">
						<h3>Edit Task</h3>
						<button type="button" class="modal-close-btn" onclick="closeEditModal()">&times;</button>
					</div>
					<form method="POST" action="">
						<input type="hidden" name="todo_id" id="edit_todo_id">

						<div class="form-group">
							<label>Task Title</label>
							<input type="text" name="title" id="edit_title" placeholder="Task Title" required>
						</div>

						<div class="form-group">
							<label>Description</label>
							<textarea name="description" id="edit_description" placeholder="Description..." rows="3"></textarea>
						</div>

						<div class="form-row">
							<div class="form-group">
								<label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
									<input type="checkbox" id="edit_has_due_date" onchange="toggleDateField('edit')" style="width: auto; margin: 0;">
									<span style="font-size: 11px; color: var(--dark-grey); font-weight: 600; text-transform: uppercase;">Set Due Date</span>
								</label>
								<div id="edit_date_wrapper" class="date-wrapper" style="display: none; margin-top: 8px;">
									<input type="datetime-local" name="due_date" id="edit_due_date" class="form-control" disabled>
								</div>
							</div>

							<div class="form-group">
								<label>Priority Level</label>
								<select name="priority_level" id="edit_priority" required>
									<option value="Low">Low</option>
									<option value="Normal">Normal</option>
									<option value="Moderate">Moderate</option>
									<option value="Urgent">Urgent</option>
									<option value="Emergency">Emergency</option>
								</select>
							</div>
						</div>

						<!-- NEW: Progress Field -->
						<div class="form-group">
							<label>Progress</label>
							<select name="progress" id="edit_progress" class="form-control">
								<?php for($i=0; $i<=100; $i+=10): ?>
									<option value="<?= $i ?>"><?= $i ?>%</option>
								<?php endfor; ?>
							</select>
						</div>

						<div class="modal-actions">
							<button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
							<button type="submit" name="edit_todo" class="btn-save">Update Task</button>
						</div>
					</form>
				</div>
			</div>


		</main>
		<!-- MAIN -->
	</section>
	<!-- CONTENT -->

	<script src="js/user_dashboard.js"></script>
	
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

	<style>
/* ==========================================
   CSV DATA TABLE STYLES - CHAMPION EDITION
   ========================================== */

   .table-row { display: grid; grid-template-columns: 1fr 0fr; grid-gap: 0px; margin-bottom: 0px; background-color: transparent; margin-top: 30px;};

   .s17c74-row { display: grid; grid-template-columns: 1fr 0fr; grid-gap: 0px; margin-bottom: 0px; background-color: transparent; }
   
/* 1. CONTAINER & CARD STRUCTURE */
.csv-card-container {
    background: var(--card-bg);
    border-radius: 0px;
    /* Consolidated Shadow (Removed redundancy) */
    box-shadow: var(--card-shadow);
    border: 1px solid var(--card-border);
    height: auto;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    position: relative; /* Context for floating button */
    margin-bottom: 0px !important;
}

.csv-card-header {
    padding: 15px 25px;
    border-bottom: 1px solid var(--card-border);
    background-color: #ffffff;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.csv-card-header h3 {
    font-size: 16px;
    font-weight: 700;
    color: #334155;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    letter-spacing: -0.2px;
}

.csv-card-header h3 i {
    color: var(--dark);
    font-size: 20px;
}

/* 2. SCROLLABLE AREA & CUSTOM SCROLLBAR */
.table-container-scroll {
    width: 100%;
    flex-grow: 1;
    max-height: 564px;
    overflow: auto;
    background: #fff;
}

/* Scrollbar Styling */
.table-container-scroll::-webkit-scrollbar { width: 8px; height: 8px; }
.table-container-scroll::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
.table-container-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; border: 2px solid #f1f5f9; }
.table-container-scroll::-webkit-scrollbar-thumb:hover { background: var(--dark); }

/* 3. TABLE STYLING */
.table-excel {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    color: #334155;
    font-family: var(--lato);
}

.table-excel th, .table-excel td {
    border: 1px solid #e2e8f0;
    padding: 8px 12px;
    text-align: left;
    vertical-align: top;
    transition: background-color 0.15s ease;
    line-height: 1.5;
}

/* Header Row (Sticky Top) */
.table-excel thead th {
    background-color: var(--dark) !important;
    color: #ffffff !important;
    font-weight: 700;
    font-family: var(--poppins);
    position: sticky;
    top: 0;
    z-index: 4 !important; /* High z-index to overlay sticky column */
    border-color: rgba(255,255,255,0.1);
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

/* First Column (Sticky Left) */
.table-excel th:first-child,
.table-excel td:first-child {
    position: sticky;
    left: 0;
    background-color: #f8fafc;
    font-weight: 600;
    z-index: 2; 
    min-width: 150px;
    max-width: 250px;
    white-space: normal;
    word-wrap: break-word;
    border-right: 2px solid #e2e8f0;
    box-shadow: inset -1px 0 0 #fff;
}

/* Top-Left Cell (Intersection) - Needs highest z-index */
.table-excel thead th:first-child {
    z-index: 5 !important;
    background-color: var(--dark) !important;
}

/* Row Hover Effects */
.table-excel tbody tr:hover td { background-color: #f1f5f9; }
.table-excel tbody tr:hover td:first-child {
    background-color: #e2e8f0;
    box-shadow: inset -2px 0 0 var(--dark);
}

/* 4. EMPTY STATE */
.csv-empty-state {
    display: flex; flex-direction: column; justify-content: center; align-items: center;
    height: 100%; min-height: 400px; color: #94a3b8;
    padding: 40px; text-align: center;
}
.csv-empty-state i { font-size: 56px; margin-bottom: 20px; opacity: 0.3; color: var(--dark); }
.csv-empty-state p { font-weight: 600; font-size: 18px; margin-bottom: 5px; color: #475569; }
.csv-empty-state small { font-size: 13px; }

/* 5. FLOATING UPDATE BUTTON */
.csv-float-update-btn {
    position: absolute;
    top: 5px;
    right: 15px;
    width: 28px;
    height: 28px;
    background: rgba(255,255,255,0.05);
    /* border: 1px solid #e2e8f0; */
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #2ecc71;
    text-decoration: none;
    z-index: 10;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    overflow: hidden; /* Hides text when shrunk */
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.csv-float-update-btn i {
    font-size: 21px;color: #fff;
    transition: transform 0.3s ease;
}

.csv-float-update-btn .btn-tooltip-text {
    position: absolute;
    right: 100%; /* Sit outside initially */
    white-space: nowrap;
    font-size: 13px;
    font-weight: 600;
    color: var(--dark);
    padding-right: 10px;
    opacity: 0;
    transition: opacity 0.2s ease;
    font-family: var(--poppins);
}

.csv-float-update-btn:hover {
    width: 130px; /* Expand width */
    border-radius: 20px; /* Become a pill shape */
    color: #fff;
	background: #2ecc71;
	color: #fff;
	box-shadow: 0 4px 15px rgba(46, 204, 113, 0.4); 
    padding: 0 15px; /* Adjusted padding for expanded state */

}

.csv-float-update-btn:hover i { color: #fff; transform: rotate(180deg); }

.csv-float-update-btn:hover .btn-tooltip-text {
    opacity: 1;
    right: auto;
    position: relative;
    color: #fff;
    padding-right: 0;
    margin-left: 8px;
}
.csv-upload-link {
    color: #518be9; /* Slate Blue (Default) */
    text-decoration: underline;
    text-underline-offset: 3px; 
    transition: color 0.2s ease;
}

.csv-upload-link:hover {
    color: #3065bb; /* Default grey color */
}

/* Dark Mode version */
body.dark .csv-upload-link {
    color: var(--dark-grey);
}
body.dark .csv-upload-link:hover {
    color: #2ecc71;
}
.csv-float-update-btn:active { transform: scale(0.95); }

/* ==========================================
   6. DARK MODE OVERRIDES
   ========================================== */

/* Container & Header */
body.dark .csv-card-container { background: var(--card-bg); border-color: var(--card-border); box-shadow: 0 10px 30px rgba(0,0,0,0.4); }
body.dark .csv-card-header { background-color: rgba(0,0,0,0.2); border-bottom-color: rgba(46, 204, 113, 0.15); }
body.dark .csv-card-header h3 { color: var(--dark); }

/* Scrollbar */
body.dark .table-container-scroll { background: var(--card-bg); }
body.dark .table-container-scroll::-webkit-scrollbar-track { background: rgba(0,0,0,0.2); }
body.dark .table-container-scroll::-webkit-scrollbar-thumb { background: rgba(46, 204, 113, 0.3); border-color: rgba(0,0,0,0.2); }
body.dark .table-container-scroll::-webkit-scrollbar-thumb:hover { background: rgba(46, 204, 113, 0.6); }

/* Table Cells */
body.dark .table-excel th, body.dark .table-excel td { border-color: rgba(46, 204, 113, 0.15); color: var(--dark); }

body.dark .table-excel th:first-child, body.dark .table-excel td:first-child {
    background-color: rgba(0,0,0,0.3);
    border-right-color: rgba(46, 204, 113, 0.3);
}

/* Table Headers */
body.dark .table-excel thead th { background-color: #1b5e20 !important; z-index: 4 !important; }
body.dark .table-excel thead th:first-child { z-index: 5 !important; background-color: #1b5e20 !important; }

/* Hover States */
body.dark .table-excel tbody tr:hover td { background-color: rgba(255,255,255,0.03); }
body.dark .table-excel tbody tr:hover td:first-child { background-color: rgba(255,255,255,0.06); box-shadow: inset -2px 0 0 #2ecc71; }

/* Empty State */
body.dark .csv-empty-state { color: var(--dark-grey); background: transparent; }
body.dark .csv-empty-state p { color: var(--dark); }

/* Floating Button */
body.dark .csv-float-update-btn { background: rgba(255,255,255,0.05); border-color: rgba(46, 204, 113, 0.3); color: var(--dark); }
body.dark .csv-float-update-btn:hover { background: #2ecc71; border-color: #2ecc71; color: #fff; box-shadow: 0 4px 15px rgba(46, 204, 113, 0.4); }
body.dark .csv-float-update-btn:hover .btn-tooltip-text { color: #fff; }


/* ==========================================
   S16 & C74 DISTRIBUTION CARDS
   ========================================== */

/* Grid Layout for the Row */
/* ==========================================
   S16 & C74 DISTRIBUTION CARDS (UPDATED)
   ========================================== */

/* Grid Layout for the Row */
.s16c74-row {
    display: grid;
    /* Removed fixed columns. We define specific columns (3 or 4) in the HTML inline styles */
    grid-gap: 25px;
    margin-bottom: 18px;
    margin-top: 10px;
}

/* Card Container */
.crop-dist-card {
    background: var(--card-bg);
    border-radius: var(--radius-lg);
    padding: 20px;
    border: 1px solid var(--card-border);
    box-shadow: var(--card-shadow);
    transition: transform 0.2s ease;
    /* Ensure cards fill the grid space */
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

/* Header: Icon + Title */
.crop-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.crop-icon-box {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 24px;
    color: #fff;
    flex-shrink: 0; /* Prevents icon from squishing */
}

/* Icon Colors */
.crop-icon-box.s16 { background: linear-gradient(135deg, #10b981, #059669); box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3); }
.crop-icon-box.c74 { background: linear-gradient(135deg, #3b82f6, #2563eb); box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3); }

/* Colors for new crops */
.crop-icon-box.papaya { background: linear-gradient(135deg, #f59e0b, #d97706); box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3); }
.crop-icon-box.avocado { background: linear-gradient(135deg, #84cc16, #65a30d); box-shadow: 0 4px 10px rgba(132, 204, 22, 0.3); }
.crop-icon-box.op { background: linear-gradient(135deg, #64748b, #475569); box-shadow: 0 4px 10px rgba(100, 116, 139, 0.3); }
.crop-icon-box.whse { background: linear-gradient(135deg, #8b5cf6, #7c3aed); box-shadow: 0 4px 10px rgba(139, 92, 246, 0.3); }
.crop-icon-box.bodega { background: linear-gradient(135deg, #ef4444, #dc2626); box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3); }

.crop-info h3 {
    font-size: 24px;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
}

.crop-info p {
    font-size: 12px;
    color: #64748b;
    margin: 0;
}

/* Stats: Percentage and Hectares */
.crop-stats {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-bottom: 10px;
}

.crop-percent {
    font-size: 18px; /* Slightly reduced for better fit */
    font-weight: 800;
    color: #334155;
}

.crop-hectares {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-align: right;
}

/* Progress Bar */
.crop-progress-bg {
    width: 100%;
    height: 10px;
    background: #f1f5f9;
    border-radius: 5px;
    overflow: hidden;
}

.crop-progress-fill {
    height: 100%;
    border-radius: 5px;
    transition: width 1s ease-in-out;
}

/* Fill Colors */
.crop-progress-fill.s16 { background: linear-gradient(90deg, #10b981, #34d399); }
.crop-progress-fill.c74 { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
.crop-progress-fill.papaya { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
.crop-progress-fill.avocado { background: linear-gradient(90deg, #84cc16, #a3e635); }
.crop-progress-fill.op { background: linear-gradient(90deg, #64748b, #94a3b8); }
.crop-progress-fill.whse { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }
.crop-progress-fill.bodega { background: linear-gradient(90deg, #ef4444, #f87171); }

/* --- DARK MODE OVERRIDES --- */
body.dark .crop-dist-card {
    background: var(--card-bg);
    border-color: var(--card-border);
}

body.dark .crop-info h3 { color: var(--dark); }
body.dark .crop-info p { color: var(--dark-grey); }
body.dark .crop-percent { color: var(--dark); }
body.dark .crop-hectares { color: var(--dark-grey); }

body.dark .crop-progress-bg { background: rgba(255, 255, 255, 0.1); }

/* Responsive Adjustments */
@media screen and (max-width: 1200px) {
    /* Force 2 columns on medium screens if 3 or 4 is too tight */
    .s16c74-row {
        grid-template-columns: 1fr 1fr !important;
    }
}

@media screen and (max-width: 768px) {
    /* Force 1 column on mobile */
    .s16c74-row {
        grid-template-columns: 1fr !important;
    }
}
</style>
</body>

</html>