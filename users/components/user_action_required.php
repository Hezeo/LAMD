<?php
// ==========================================
// ACTION REQUIRED COMPONENT DATA QUERIES
// ==========================================
// Note: This relies on $pdo, $fiscal_year_start, $fiscal_year_end, 
// and $fiscal_year_label being defined in the parent file

try {
    // =========================================================================
    // EXPIRY CARDS (UPDATED: Uses Inception Dates Logic for Contracts)
    // =========================================================================

    // 5 - Query for CONTRACTS expiring within this fiscal year
    // FIXED: Added contracting_party to SELECT and GROUP BY
    $stmt5 = $pdo->prepare("
        SELECT COUNT(*) as total_contracts_expiring 
        FROM (
            SELECT 
                contracting_party, -- ADDED
                start_date, 
                expiry_date, 
                paid_up_date 
            FROM tbl_main 
            WHERE start_date IS NOT NULL 
              AND expiry_date IS NOT NULL 
              AND paid_up_date IS NOT NULL
              AND contracting_party IS NOT NULL -- ADDED
              AND expiry_date BETWEEN ? AND ?
            GROUP BY 
                contracting_party, -- ADDED
                start_date, 
                expiry_date, 
                paid_up_date
        ) as expiring_contracts
    ");
    $stmt5->execute([$fiscal_year_start, $fiscal_year_end]);
    $total_contracts_expiring = $stmt5->fetch(PDO::FETCH_ASSOC)['total_contracts_expiring'];

    // 5B - NEW: Query for Lots expiring within this fiscal year (Untouched - Correct)
    $stmt5_lots = $pdo->prepare("SELECT COUNT(DISTINCT lot_no, field_no) as total_lots_expiring FROM tbl_main WHERE expiry_date BETWEEN ? AND ?");
    $stmt5_lots->execute([$fiscal_year_start, $fiscal_year_end]);
    $total_lots_expiring = $stmt5_lots->fetch(PDO::FETCH_ASSOC)['total_lots_expiring'];

    // 6 - NEW: Query for Hectares expiring within this fiscal year (Untouched - Correct)
    $stmt6_hectares = $pdo->prepare("SELECT COALESCE(SUM(contracted_arable), 0) as total_hectares_expiring FROM tbl_main WHERE expiry_date BETWEEN ? AND ?");
    $stmt6_hectares->execute([$fiscal_year_start, $fiscal_year_end]);
    $total_hectares_expiring_card = $stmt6_hectares->fetch(PDO::FETCH_ASSOC)['total_hectares_expiring'];

    // =========================================================================
    // DOUGHNUT CHARTS
    // =========================================================================

    // 7 - Query for total renewed_contracts
    // FIXED: Added contracting_party to SELECT and GROUP BY
    $stmt7 = $pdo->query("
        SELECT COUNT(*) as total_renewed_contracts 
        FROM (
            SELECT 
                contracting_party, -- ADDED
                start_date, 
                expiry_date, 
                paid_up_date 
            FROM tbl_renewed_contracts 
            WHERE start_date IS NOT NULL 
              AND expiry_date IS NOT NULL 
              AND paid_up_date IS NOT NULL
              AND contracting_party IS NOT NULL -- ADDED
            GROUP BY 
                contracting_party, -- ADDED
                start_date, 
                expiry_date, 
                paid_up_date
        ) as renewed_contracts
    ");
    $total_renewed_contracts = $stmt7->fetch(PDO::FETCH_ASSOC)['total_renewed_contracts'] ?? 0;

    // Calculate Renewal Progress
    $contracts_renewal_progress = 0;
    if ($total_contracts_expiring > 0) {
        $contracts_renewal_progress = round(($total_renewed_contracts / $total_contracts_expiring) * 100, 2);
    }

    // 8 - Query for total renewed_hectares hectares (Untouched - Correct)
    $stmt8 = $pdo->query("
        SELECT COALESCE(SUM(hectares), 0) as total_renewed_hectares 
        FROM (
            SELECT MAX(contracted_arable) as hectares -- Changed AVG to MAX for safety
            FROM tbl_renewed_contracts 
            WHERE lot_no IS NOT NULL AND lot_no != '' 
            GROUP BY lot_no, field_no
        ) as distinct_renewed_lots
    ");
    $total_renewed_hectares = $stmt8->fetch(PDO::FETCH_ASSOC)['total_renewed_hectares'] ?? 0;

    // Format the numbers
    $formatted_contracts_expiring = number_format($total_contracts_expiring);
    $formatted_lots_expiring = number_format($total_lots_expiring);
    $formatted_hectares_expiring_card = number_format($total_hectares_expiring_card, 3);

    // Calculate the "Not Renewed Contracts" count
    $not_renewed_contracts = max(0, $total_contracts_expiring - $total_renewed_contracts);
    $donut_renewed_contracts = [
        'renewed_contracts' => $total_renewed_contracts,
        'not_renewed_contracts' => $not_renewed_contracts
    ];

    // ==========================================
    // NEW: TODAY'S EXPIRY COUNTS
    // ==========================================
    $today_date = date('Y-m-d');

    // FIXED: Today's Contracts Count
    $stmt_today_con = $pdo->prepare("
        SELECT COUNT(*) as today_contracts 
        FROM (
            SELECT 
                contracting_party, -- ADDED
                start_date, 
                expiry_date, 
                paid_up_date 
            FROM tbl_main 
            WHERE expiry_date = ? 
              AND contracting_party IS NOT NULL
            GROUP BY 
                contracting_party, -- ADDED
                start_date, 
                expiry_date, 
                paid_up_date
        ) as today_contracts_sub
    ");
    $stmt_today_con->execute([$today_date]);
    $today_contracts = $stmt_today_con->fetch(PDO::FETCH_ASSOC)['today_contracts'] ?? 0;

    $stmt_today_ha = $pdo->prepare("SELECT COALESCE(SUM(contracted_arable), 0) as today_hectares FROM tbl_main WHERE expiry_date = ?");
    $stmt_today_ha->execute([$today_date]);
    $today_hectares = $stmt_today_ha->fetch(PDO::FETCH_ASSOC)['today_hectares'] ?? 0;

    $formatted_today_contracts = number_format($today_contracts);
    $formatted_today_hectares = (float) $today_hectares;
} catch (PDOException $e) {
    $formatted_contracts_expiring = "Error";
    $formatted_lots_expiring = "Error";
    $formatted_hectares_expiring_card = "Error";
    $contracts_renewal_progress = 0;
    $formatted_today_contracts = "0";
    $formatted_today_hectares = 0;
    error_log("Action Required DB Error: " . $e->getMessage());
}
?>

<style>
    /* ==========================================
   ACTION REQUIRED COMPONENT STYLES
   ========================================== */

    /* Animation for alert icon */
    @keyframes lookAround {
        0%, 100% { transform: rotate(0deg); }
        25% { transform: rotate(5deg); }
        75% { transform: rotate(-5deg); }
    }

    @keyframes fadeSlideIn {
        from { opacity: 0; transform: translateY(-2px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* ==========================================
   1. ALERT HEADER
   ========================================== */
    .alert-header {
        background: linear-gradient(135deg, #fee2e2, #fecaca);
        border-radius: 10px;
        padding: 10px;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        color: #991b1b;
        margin-bottom: 10px;
        border: 1px solid transparent; 
    }

    .alert-icon {
        font-size: 28px;
        background: rgba(255, 255, 255, 0.6);
        width: 45px;
        height: 45px;
        border-radius: 50%;
        display: flex;
        justify-content: center;
        align-items: center;
        animation: lookAround 3s infinite;
    }

    .alert-text h4 { margin: 0; font-size: 16px; font-weight: 700; font-family: 'Poppins', sans-serif; }
    .alert-text p { margin: 0; font-size: 12px; opacity: 0.8; font-family: 'Lato', sans-serif; }

    /* ==========================================
   2. CONTAINER
   ========================================== */
    .records-right-cards { background-color: transparent; padding: 0; margin: 0; list-style: none; }

    /* ==========================================
   3. CARD LAYOUT (Single Column)
   ========================================== */
    .right-cards {
        background: #f8fafc;
        border-radius: 10px;
        padding: 12px;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        display: flex;
        flex-direction: column;
        gap: 15px; /* Space between sections */
        margin-bottom: 10px;
        border: 1px solid #f1f5f9;
    }

    /* ==========================================
   4. TOP ROW (Content + Chart)
   ========================================== */
    .card-top-section {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 15px;
    }

    .content-left {
        flex: 1;
    }

    .chart-right {
        flex-shrink: 0;
    }

    /* ==========================================
   5. LEFT CONTENT (Contracts)
   ========================================== */
    .main-count-section {
        margin-bottom: 8px;
    }

    .expiring-count {
        font-size: 28px;
        font-weight: 800;
        color: #dc2626;
        margin: 0 0 4px 0;
        line-height: 1;
        letter-spacing: -0.5px;
    }

    .expiring-label {
        color: #64748b;
        font-size: 13px;
        font-weight: 500;
        margin: 0;
        line-height: 1.3;
    }

    /* ==========================================
   5B. TODAY COUNT
   ========================================== */
    .today-count {
        font-size: 12px;
        color: #94a3b8;
        animation: fadeSlideIn 0.4s ease-out 0.5s forwards;
    }

    /* ==========================================
   6. SUB STATS PILLS (Auto Width)
   ========================================== */
    .sub-stats-container {
        display: flex;
        flex-direction: row;
        gap: 8px;
        width: 100%;
    }

    .stat-pill {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 6px 10px;
        border-radius: 8px;
        font-size: 11px;
        width: auto; /* Auto width */
        white-space: nowrap;
        text-align: center;
    }

    .stat-pill span {
        font-size: 11px;
        font-weight: 600;
        opacity: 0.8;
        display: block;
    }

    .pill-lots { background-color: #e0f2fe; color: #0369a1; }
    .pill-hectares { background-color: #f0fdf4; color: #15803d; }

    /* ==========================================
   7. FOOTER BUTTON (Full Width)
   ========================================== */
    .exp-view-more-btn {
        display: block;
        width: 100%;
        text-align: center;
        padding: 10px 0;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #dc2626;
        background-color: #fff;
        border: 1px solid #fecaca;
        border-radius: 6px;
        text-decoration: none;
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .exp-view-more-btn:hover {
        background-color: #fecaca;
        box-shadow: 0 4px 6px rgba(220, 38, 38, 0.15);
    }

    /* ==========================================
   8. CHART
   ========================================== */
    .donut-chart-div {
        position: relative;
        width: 110px;
        height: 110px;
        transform: translateZ(0);
    }

    .donut-chart-div canvas {
        width: 100% !important;
        height: 100% !important;
    }

    .donut-center-text {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
        pointer-events: none;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: 85%;
    }

    .donut-percentage {
        font-size: 20px;
        font-weight: 800;
        color: #2E7D32;
        line-height: 1;
    }

    .donut-count-fraction-label {
        font-size: 8px;
        color: #64748b;
        text-transform: uppercase;
        line-height: .6rem;
        margin-top: 2px;
        letter-spacing: 0.5px;
    }

    /* ==========================================
   9. DARK MODE
   ========================================== */
    body.dark .alert-section {
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
        border-color: rgba(46, 204, 113, 0.15);
        padding: 15px;
        border-radius: 18px;
        background: #0a1f12;
    }

    body.dark .alert-header {
        background: linear-gradient(135deg, rgba(254, 226, 226, 0.1), rgba(254, 202, 202, 0.05));
        color: #ff6b6b;
        border-color: rgba(255, 107, 107, 0.2);
    }

    body.dark .alert-icon { background: rgba(0, 0, 0, 0.3); }
    body.dark .alert-text h4 { color: #ff6b6b; }
    body.dark .alert-text p { color: rgba(255, 107, 107, 0.7); }

    body.dark .right-cards {
        background: rgba(0, 0, 0, 0.2);
        border-color: rgba(46, 204, 113, 0.15);
    }

    body.dark .expiring-count { color: #ff6b6b !important; }
    body.dark .expiring-label { color: #a9c7ac; }
    body.dark .today-count { color: rgba(169, 199, 172, 0.6); }

    body.dark .pill-lots {
        background-color: rgba(14, 165, 233, 0.15);
        color: #7dd3fc;
    }

    body.dark .pill-hectares {
        background-color: rgba(34, 197, 94, 0.15);
        color: #86efac;
    }

    body.dark .exp-view-more-btn {
        background-color: rgba(0, 0, 0, 0.2);
        border-color: rgba(255, 107, 107, 0.3);
        color: #ff6b6b;
    }

    body.dark .exp-view-more-btn:hover {
        background-color: rgba(255, 107, 107, 0.2);
        color: #ff6b6b;
    }

    body.dark .donut-percentage { color: #e8f5e9 !important; }
    body.dark .donut-count-fraction-label { color: #a9c7ac !important; }

    /* ==========================================
   10. RESPONSIVE
   ========================================== */
    @media screen and (max-width: 768px) {
        .card-top-section { flex-direction: column; align-items: center; gap: 15px; }
        .content-left { text-align: center; width: 100%; }
        .chart-right { width: 110px; height: 110px; }
        .sub-stats-container { justify-content: center; }
    }
</style>


<!-- NEW ALERT HEADER -->
<div class="alert-header">
    <div class="alert-icon">
        <i class='bx bxs-bell-ring bx-tada'></i>
    </div>
    <div class="alert-text">
        <h4>Action Required</h4>
        <p>Important contract expiration updates</p>
    </div>
</div>


<div class="records-right-cards">

    <!-- CARD 1: CONTRACTS -->
    <div class="right-cards">
        
        <!-- TOP SECTION: Content (Left) + Chart (Right) -->
        <div class="card-top-section">
            
            <!-- LEFT: Contracts -->
            <div class="content-left">
                <div class="main-count-section">
                    <h3 class="expiring-count">
                        <?php echo htmlspecialchars($formatted_contracts_expiring); ?>
                    </h3>
                    <p class="expiring-label">Total Contracts Expiring in
                        <?php echo htmlspecialchars($fiscal_year_label); ?> <small class="today-count">• <?php echo $formatted_today_contracts; ?> today</small>
                    </p>
                    
                </div>

                <!-- SUB STATS PILLS -->
                <div class="sub-stats-container">
                    <div class="stat-pill pill-lots">
                        <span><?php echo $formatted_lots_expiring; ?> Lots</span> 
                    </div>
                    <div class="stat-pill pill-hectares">
                        <span><?php echo $formatted_hectares_expiring_card; ?> Has</span> 
                    </div>
                </div>

            </div>

            <!-- RIGHT: Chart -->
            <div class="chart-right donut-chart-div">
                <canvas id="renewalDonutChart"></canvas>
                <div class="donut-center-text">
                    <span class="donut-percentage"><?php echo $contracts_renewal_progress; ?>%</span>
                    <span class="donut-count-fraction-label"><?php echo $total_renewed_contracts; ?>/<?php echo $total_contracts_expiring; ?><br>Renewed</span>
                </div>
            </div>

        </div>

        <!-- FOOTER: View More (Full Width) -->
        <a href="user_exp.php" class="exp-view-more-btn">View More</a>

    </div>

</div>


<script>
    // ==========================================
    // FIX: INJECT MISSING DATA VARIABLE
    // ==========================================
    const donutChartData = {
        renewed_contracts: <?= json_encode($donut_renewed_contracts['renewed_contracts']); ?>,
        not_renewed_contracts: <?= json_encode($donut_renewed_contracts['not_renewed_contracts']); ?>
    };

    // THE "CHAMELEON" TRACK COLOR 
    const trackColor = 'rgba(148, 163, 184, 0.2)';

    // ==========================================
    // RENEWAL DONUT CHART (Contracts Only)
    // ==========================================

    const ctxContracts = document.getElementById('renewalDonutChart');

    if (ctxContracts) {
        const renewedContractsCount = donutChartData.renewed_contracts;
        const notRenewedContractsCount = donutChartData.not_renewed_contracts;

        const renewedContractsGradient = ctxContracts.getContext('2d').createLinearGradient(0, 0, 0, 110);
        renewedContractsGradient.addColorStop(0, '#059669'); 
        renewedContractsGradient.addColorStop(1, '#34d399'); 

        new Chart(ctxContracts.getContext('2d'), {
            type: 'doughnut', // FIXED TYPO
            data: {
                labels: ['Renewed Contracts', 'Not Renewed Contracts'],
                datasets: [{
                    data: [renewedContractsCount, notRenewedContractsCount],
                    backgroundColor: [renewedContractsGradient, trackColor],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '80%', 
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        titleFont: {
                            family: 'Poppins',
                            size: 12,
                            weight: '600'
                        },
                        bodyFont: {
                            family: 'Lato',
                            size: 11
                        },
                        padding: 10,
                        cornerRadius: 8,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return ` ${context.label}: ${context.raw}`;
                            }
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeInOutQuart'
                }
            }
        });
    }
</script>