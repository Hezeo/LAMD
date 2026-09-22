<?php

// QUERY FOR LANDOWNERS TRENDS START
try {
    // 1. Top 5 Highest Count of Contracts (UPDATED: Uses Inception Dates Logic)
    $stmt_trend1 = $pdo->query("
        SELECT 
            contracting_party, 
            COUNT(*) as total_count,
            MIN(start_date) as earliest_start
        FROM (
            SELECT 
                contracting_party,
                start_date,
                expiry_date,
                paid_up_date
            FROM tbl_main 
            WHERE contracting_party IS NOT NULL
              AND start_date IS NOT NULL 
              AND expiry_date IS NOT NULL 
              AND paid_up_date IS NOT NULL
            GROUP BY contracting_party, start_date, expiry_date, paid_up_date
        ) as unique_contracts
        GROUP BY contracting_party 
        ORDER BY total_count DESC 
        LIMIT 10
    ");
    $top_contractors = $stmt_trend1->fetchAll(PDO::FETCH_ASSOC);

    // 2. Top 5 Largest Arable Hectares (Owner) (UPDATED: Unique Lot Logic)
    $stmt_trend2 = $pdo->query("
        SELECT 
            contracting_party, 
            SUM(hectares) as total_hectares,
            MIN(start_date) as earliest_start
        FROM (
            SELECT 
                contracting_party,
                start_date,
                MAX(contracted_arable) as hectares
            FROM tbl_main 
            WHERE contracting_party IS NOT NULL
              AND lot_no IS NOT NULL AND lot_no != ''
            GROUP BY contracting_party, start_date, lot_no, field_no
        ) as distinct_owner_lots
        GROUP BY contracting_party 
        ORDER BY total_hectares DESC 
        LIMIT 10
    ");
    $top_hectares_owners = $stmt_trend2->fetchAll(PDO::FETCH_ASSOC);

        // 3. Top 5 Largest Arable Municipality (UPDATED: Added 'Since' Logic)
    $stmt_trend3 = $pdo->query("
        SELECT 
            municipality, 
            SUM(hectares) as total_hectares,
            MIN(earliest_date) as earliest_start -- Added to get the first contract date in that town
        FROM (
            SELECT 
                municipality,
                MAX(contracted_arable) as hectares,
                MIN(start_date) as earliest_date -- Get earliest date per lot/contract
            FROM tbl_main 
            WHERE municipality IS NOT NULL AND municipality != ''
              AND lot_no IS NOT NULL AND lot_no != ''
            GROUP BY municipality, lot_no, field_no
        ) as distinct_muni_lots
        GROUP BY municipality 
        ORDER BY total_hectares DESC 
        LIMIT 10
    ");
    $top_municipalities = $stmt_trend3->fetchAll(PDO::FETCH_ASSOC);

    // 4. Top 5 Highest Count of Renewals (Left Empty as requested)
    $top_renewal_owners = [];

} catch (PDOException $e) {
    error_log("Trends Error: " . $e->getMessage());
    // Fallback empty arrays
    $top_contractors = [];
    $top_hectares_owners = [];
    $top_municipalities = [];
    $top_renewal_owners = [];
}
// QUERY FOR LANDOWNERS TRENDS END

?>
                            <!-- <div class="trends-header" style="grid-column: 1 / -1;">



                                <i class='bx bx-trending-up bx-md'></i>
                                <h3>LANDHOLDINGS TRENDS</h3>
                            </div> -->

                            <div class="trends-grid-4-col">

                                <!-- COLUMN 1: Most Contracts -->
                                <div class="trend-column-card">
                                    <div class="trend-card-header">
                                        <div class="trend-icon-box blue">
                                            <i class='bx bxs-file'></i>
                                        </div>
                                        <span class="trend-category-title">Most Contracts</span>
                                        <div class="trophy-icon">
                                            <span>🏆</span>
                                        </div>
                                    </div>
                                    <div class="trend-list">
                                        <?php if (!empty($top_contractors)): ?>
                                            <?php foreach ($top_contractors as $i => $row): ?>
                                                <div class="trend-item">
                                                    <span class="trend-rank"><?php echo $i + 1; ?>.</span>
                                                    <div class="trend-info">
                                                        <span
                                                            class="trend-name"><?php echo htmlspecialchars($row['contracting_party']); ?></span>
                                                        <div class="trend-meta">
                                                            <span
                                                                class="trend-stat"><?php echo number_format($row['total_count']); ?>
                                                                Contracts</span>
                                                            <?php if (isset($row['earliest_start']) && $row['earliest_start']): ?>
                                                                <span class="trend-since">Since
                                                                    <?php echo date('Y', strtotime($row['earliest_start'])); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="trend-item"><span class="trend-name">No Data</span></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- COLUMN 2: Largest Arable (Owner) -->
                                <div class="trend-column-card">
                                    <div class="trend-card-header">
                                        <div class="trend-icon-box green">
                                            <i class='bx bxs-landscape'></i>
                                        </div>
                                        <span class="trend-category-title">Largest Arable (Owner)</span>
                                        <div class="trophy-icon">
                                            <span>🏆</span>
                                        </div>
                                    </div>
                                    <div class="trend-list">
                                        <?php if (!empty($top_hectares_owners)): ?>
                                            <?php foreach ($top_hectares_owners as $i => $row): ?>
                                                <div class="trend-item">
                                                    <span class="trend-rank"><?php echo $i + 1; ?>.</span>
                                                    <div class="trend-info">
                                                        <span
                                                            class="trend-name"><?php echo htmlspecialchars($row['contracting_party']); ?></span>
                                                        <div class="trend-meta">
                                                            <span
                                                                class="trend-stat"><?php echo number_format($row['total_hectares'], 2); ?>
                                                                Has</span>
                                                            <?php if (isset($row['earliest_start']) && $row['earliest_start']): ?>
                                                                <span class="trend-since">Since
                                                                    <?php echo date('Y', strtotime($row['earliest_start'])); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="trend-item"><span class="trend-name">No Data</span></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                                                <!-- COLUMN 3: Largest Municipality -->
                                <div class="trend-column-card">
                                    <div class="trend-card-header">
                                        <div class="trend-icon-box orange">
                                            <i class='bx bxs-map'></i>
                                        </div>
                                        <span class="trend-category-title">Largest Municipality</span>
                                        <div class="trophy-icon">
                                            <span>🏆</span>
                                        </div>
                                    </div>
                                    <div class="trend-list">
                                        <?php if (!empty($top_municipalities)): ?>
                                            <?php foreach ($top_municipalities as $i => $row): ?>
                                                <div class="trend-item">
                                                    <span class="trend-rank"><?php echo $i + 1; ?>.</span>
                                                    <div class="trend-info">
                                                        <span
                                                            class="trend-name"><?php echo htmlspecialchars($row['municipality']); ?></span>
                                                        <div class="trend-meta">
                                                            <span
                                                                class="trend-stat"><?php echo number_format($row['total_hectares'], 2); ?>
                                                                Has</span>
                                                            <!-- RESTORED: 'Since' Date -->
                                                            <?php if (isset($row['earliest_start']) && $row['earliest_start']): ?>
                                                                <span class="trend-since">Since
                                                                    <?php echo date('Y', strtotime($row['earliest_start'])); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="trend-item"><span class="trend-name">No Data</span></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- COLUMN 4: Most Renewals -->
                                <div class="trend-column-card">
                                    <div class="trend-card-header">
                                        <div class="trend-icon-box red">
                                            <i class='bx bxs-file-plus'></i>
                                        </div>
                                        <span class="trend-category-title">Most Renewals (Existing)</span>
                                        <div class="trophy-icon">
                                            <span>🏆</span>
                                        </div>
                                    </div>
                                    <div class="trend-list">
                                        <?php if (!empty($top_renewal_owners)): ?>
                                            <!-- <?php foreach ($top_renewal_owners as $i => $row): ?>
                                            <div class="trend-item">
                                                <span class="trend-rank"><?php echo $i + 1; ?>.</span>
                                                <div class="trend-info">
                                                    <span class="trend-name"><?php echo htmlspecialchars($row['contracting_party']); ?></span>
                                                    <div class="trend-meta">
                                                        <span class="trend-stat"><?php echo number_format($row['total_count']); ?> Renewals</span>
                                                        <?php if ($row['earliest_start']): ?>
                                                        <span class="trend-since">Since <?php echo date('Y', strtotime($row['earliest_start'])); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?> -->
                                            <div class="trend-item"><span class="trend-name">No Data</span></div>
                                        <?php else: ?>
                                            <div class="trend-item"><span class="trend-name">No Data</span></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                            </div>