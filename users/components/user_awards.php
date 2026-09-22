<?php
require_once '../config.php';
require_once '../config_session.php';
checkLogin();

// Ensure we have the current user ID
 $user_id = getCurrentUserId();

// --- 1. FETCH USER STATS (Department Specific) ---
 $stats = [
    'contracts_initiated' => 0,
    'contracts_processed' => 0,
    'contracts_renewed' => 0,
    'contracts_inactive' => 0,
    'total_activity' => 0
];

try {
    // A. Count Contracts Initiated (in tbl_main)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tbl_main WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats['contracts_initiated'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // B. Count Contracts Processed (Updates in tbl_updated_contracts)
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tbl_updated_contracts WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats['contracts_processed'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // C. Count Contracts Renewed
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tbl_renewed_contracts WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats['contracts_renewed'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // D. Count Contracts Inactive/Archived
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tbl_archived_contracts WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats['contracts_inactive'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // E. Count Total Activity Logs
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tbl_activity_logs WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $stats['total_activity'] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];

} catch (PDOException $e) {
    error_log("Awards Stats Error: " . $e->getMessage());
}

// --- 2. CALCULATE WEIGHTED SCORE ---
 $weights = [
    'initiate' => 10,
    'process'  => 15,
    'renew'    => 25,
    'inactive' => 5,
    'activity' => 1
];

 $weighted_score = 
    ($stats['contracts_initiated'] * $weights['initiate']) +
    ($stats['contracts_processed'] * $weights['process']) +
    ($stats['contracts_renewed'] * $weights['renew']) +
    ($stats['contracts_inactive'] * $weights['inactive']) +
    ($stats['total_activity'] * $weights['activity']);

// Determine Rank/Tier
 $rank = 'Bronze';
 $rank_color = '#cd7f32';
if ($weighted_score > 500) { $rank = 'Silver'; $rank_color = '#C0C0C0'; }
if ($weighted_score > 1500) { $rank = 'Gold'; $rank_color = '#FFD700'; }
if ($weighted_score > 3000) { $rank = 'Platinum'; $rank_color = '#E5E4E2'; }
if ($weighted_score > 5000) { $rank = 'Diamond'; $rank_color = '#B9F2FF'; }

// --- 3. FETCH GLOBAL LEADERBOARDS (Names & Counts) ---
// Helper to get name by ID
function getUserName($pdo, $uid) {
    if (!$uid) return 'Unknown';
    try {
        $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM tbl_users WHERE user_id = ?");
        $stmt->execute([$uid]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res['name'] ?? 'Unknown';
    } catch (PDOException $e) {
        return 'Unknown';
    }
}

 $leaders = [];

try {
    // A. Top Initiator
    $stmt = $pdo->query("SELECT user_id, COUNT(*) as count FROM tbl_main GROUP BY user_id ORDER BY count DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $leaders['initiator'] = [
            'name' => getUserName($pdo, $row['user_id']),
            'id' => $row['user_id'],
            'count' => $row['count'],
            'is_me' => ($row['user_id'] == $user_id)
        ];
    }

    // B. Renewal Specialist
    $stmt = $pdo->query("SELECT user_id, COUNT(*) as count FROM tbl_renewed_contracts GROUP BY user_id ORDER BY count DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $leaders['renewer'] = [
            'name' => getUserName($pdo, $row['user_id']),
            'id' => $row['user_id'],
            'count' => $row['count'],
            'is_me' => ($row['user_id'] == $user_id)
        ];
    }

    // C. Top Processor
    $stmt = $pdo->query("SELECT user_id, COUNT(*) as count FROM tbl_updated_contracts GROUP BY user_id ORDER BY count DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $leaders['processor'] = [
            'name' => getUserName($pdo, $row['user_id']),
            'id' => $row['user_id'],
            'count' => $row['count'],
            'is_me' => ($row['user_id'] == $user_id)
        ];
    }

    // D. Most Active
    $stmt = $pdo->query("SELECT user_id, COUNT(*) as count FROM tbl_activity_logs GROUP BY user_id ORDER BY count DESC LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $leaders['active'] = [
            'name' => getUserName($pdo, $row['user_id']),
            'id' => $row['user_id'],
            'count' => $row['count'],
            'is_me' => ($row['user_id'] == $user_id)
        ];
    }

} catch (PDOException $e) {
    error_log("Leaderboard Error: " . $e->getMessage());
}
?>

<div class="awards-card">
    <div class="awards-header">
        <div class="awards-icon">
            <i class='bx bxs-award'></i>
        </div>
        <div class="awards-title">
            <h3>Staff Performance</h3>
            <p>Department Overview</p>
        </div>
    </div>

    <!-- Performance Score Section -->
    <div class="performance-meter">
        <div class="score-circle">
            <div class="score-value"><?= number_format($weighted_score) ?></div>
            <div class="score-label">INDEX</div>
        </div>
        <div class="score-rank" style="color: <?= $rank_color ?>; border-color: <?= $rank_color ?>;">
            <?= $rank ?> LEVEL
        </div>
    </div>

    <!-- Detailed Stats Grid -->
    <div class="stats-grid">
        <div class="stat-item">
            <div class="stat-icon-box initiate"><i class='bx bxs-file-plus'></i></div>
            <div class="stat-text">
                <span><?= number_format($stats['contracts_initiated']) ?></span>
                <small>Initiated</small>
            </div>
        </div>
        
        <div class="stat-item">
            <div class="stat-icon-box renew"><i class='bx bxs-calendar-check'></i></div>
            <div class="stat-text">
                <span><?= number_format($stats['contracts_renewed']) ?></span>
                <small>Renewed</small>
            </div>
        </div>

        <div class="stat-item">
            <div class="stat-icon-box process"><i class='bx bxs-edit-alt'></i></div>
            <div class="stat-text">
                <span><?= number_format($stats['contracts_processed']) ?></span>
                <small>Processed</small>
            </div>
        </div>

        <div class="stat-item">
            <div class="stat-icon-box inactive"><i class='bx bxs-archive-in'></i></div>
            <div class="stat-text">
                <span><?= number_format($stats['contracts_inactive']) ?></span>
                <small>Inactive</small>
            </div>
        </div>
    </div>

    <!-- Best In Awards Section -->
    <div class="awards-list">
        <h4><i class='bx bxs-star'></i> Current Leaders</h4>
        
        <?php if (isset($leaders['initiator'])): ?>
            <div class="award-badge <?= $leaders['initiator']['is_me'] ? 'mine' : '' ?>">
                <div class="award-icon <?= $leaders['initiator']['is_me'] ? 'mine' : '' ?>">
                    <i class='bx bxs-rocket'></i>
                </div>
                <div class="award-info">
                    <strong>Top Initiator</strong>
                    <small><?= $leaders['initiator']['is_me'] ? '<span class="me-tag">YOU</span>' : htmlspecialchars($leaders['initiator']['name']) ?></small>
                    <div class="award-count"><?= $leaders['initiator']['count'] ?> Contracts</div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($leaders['renewer'])): ?>
            <div class="award-badge <?= $leaders['renewer']['is_me'] ? 'mine' : '' ?>">
                <div class="award-icon renew-bg <?= $leaders['renewer']['is_me'] ? 'mine' : '' ?>">
                    <i class='bx bxs-badge-check'></i>
                </div>
                <div class="award-info">
                    <strong>Renewal Specialist</strong>
                    <small><?= $leaders['renewer']['is_me'] ? '<span class="me-tag">YOU</span>' : htmlspecialchars($leaders['renewer']['name']) ?></small>
                    <div class="award-count"><?= $leaders['renewer']['count'] ?> Renewals</div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($leaders['processor'])): ?>
            <div class="award-badge <?= $leaders['processor']['is_me'] ? 'mine' : '' ?>">
                <div class="award-icon process-bg <?= $leaders['processor']['is_me'] ? 'mine' : '' ?>">
                    <i class='bx bxs-edit-alt'></i>
                </div>
                <div class="award-info">
                    <strong>Top Processor</strong>
                    <small><?= $leaders['processor']['is_me'] ? '<span class="me-tag">YOU</span>' : htmlspecialchars($leaders['processor']['name']) ?></small>
                    <div class="award-count"><?= $leaders['processor']['count'] ?> Updates</div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($leaders['active'])): ?>
            <div class="award-badge <?= $leaders['active']['is_me'] ? 'mine' : '' ?>">
                <div class="award-icon active-bg <?= $leaders['active']['is_me'] ? 'mine' : '' ?>">
                    <i class='bx bxs-pulse'></i>
                </div>
                <div class="award-info">
                    <strong>Most Active Staff</strong>
                    <small><?= $leaders['active']['is_me'] ? '<span class="me-tag">YOU</span>' : htmlspecialchars($leaders['active']['name']) ?></small>
                    <div class="award-count"><?= $leaders['active']['count'] ?> Logs</div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    /* ================================================================
   AWARDS CARD STYLES (WITH NAMES)
   ================================================================ */

.awards-card {
    background: var(--card-bg);
    border-radius: var(--radius-lg);
    padding: 24px;
    border: 1px solid var(--card-border);
    box-shadow: var(--card-shadow);
    margin-bottom: 24px;
    position: relative;
    overflow: hidden;
}

.awards-card::before {
    content: '';
    position: absolute;
    top: -50px; right: -50px;
    width: 150px; height: 150px;
    background: radial-gradient(circle, rgba(255, 215, 0, 0.1) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
    z-index: 0;
}

.awards-header {
    display: flex; align-items: center; gap: 15px;
    margin-bottom: 25px; position: relative; z-index: 1;
}

.awards-icon {
    width: 45px; height: 45px;
    background: linear-gradient(135deg, #FFD700, #FDB931);
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 24px;
    box-shadow: 0 4px 10px rgba(255, 215, 0, 0.3);
}

.awards-title h3 { font-size: 16px; font-weight: 700; color: var(--dark); margin: 0; }
.awards-title p { font-size: 12px; color: var(--dark-grey); margin: 0; }

/* --- Performance Meter --- */
.performance-meter {
    display: flex; align-items: center; justify-content: space-between;
    background: rgba(0,0,0,0.02);
    border-radius: 12px; padding: 15px;
    margin-bottom: 20px; border: 1px solid var(--card-border);
}

.score-circle { display: flex; flex-direction: column; align-items: center; justify-content: center; }
.score-value { font-size: 24px; font-weight: 800; color: var(--dark); line-height: 1; }
.score-label { font-size: 10px; font-weight: 600; color: var(--dark-grey); text-transform: uppercase; letter-spacing: 1px; }
.score-rank { font-size: 14px; font-weight: 800; padding: 6px 12px; border-radius: 20px; border: 2px solid transparent; text-transform: uppercase; }

/* --- Stats Grid --- */
.stats-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 20px; }
.stat-item {
    display: flex; align-items: center; gap: 10px;
    background: rgba(0,0,0,0.02); padding: 10px;
    border-radius: 10px; border: 1px solid var(--card-border);
    transition: transform 0.2s;
}
.stat-item:hover { transform: translateY(-2px); background: rgba(0,0,0,0.04); }

.stat-icon-box {
    width: 36px; height: 36px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; color: white;
}
.stat-icon-box.initiate { background: #3b82f6; box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3); }
.stat-icon-box.renew { background: #10b981; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3); }
.stat-icon-box.process { background: #9b59b6; box-shadow: 0 4px 10px rgba(155, 89, 182, 0.3); }
.stat-icon-box.inactive { background: #ef4444; box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3); }

.stat-text span { display: block; font-size: 16px; font-weight: 700; color: var(--dark); line-height: 1.1; }
.stat-text small { font-size: 11px; color: var(--dark-grey); font-weight: 600; text-transform: uppercase; }

/* --- Awards List --- */
.awards-list h4 {
    font-size: 12px; text-transform: uppercase; color: var(--dark-grey);
    letter-spacing: 0.5px; margin: 0 0 15px 0;
    display: flex; align-items: center; gap: 5px;
}
.awards-list h4 i { color: #FFD700; }

.award-badge {
    display: flex; align-items: center; gap: 12px;
    background: var(--card-bg); border: 1px solid var(--card-border);
    padding: 10px; border-radius: 10px; margin-bottom: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.03);
    transition: 0.2s;
}

/* Highlighting the winner's name if it is the viewer */
.award-badge.mine {
    background: rgba(255, 215, 0, 0.05);
    border-color: rgba(255, 215, 0, 0.3);
    box-shadow: 0 4px 15px rgba(255, 215, 0, 0.1);
}

.award-icon {
    width: 32px; height: 32px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; background: #f1f5f9; color: #64748b;
}

/* Specific Icon Colors */
.award-badge:nth-child(2) .award-icon { color: #3b82f6; background: rgba(59, 130, 246, 0.1); }
.award-badge:nth-child(3) .award-icon { color: #2ecc71; background: rgba(46, 204, 113, 0.1); }
.award-badge:nth-child(4) .award-icon { color: #9b59b6; background: rgba(155, 89, 182, 0.1); }
.award-badge:nth-child(5) .award-icon { color: #e67e22; background: rgba(230, 126, 34, 0.1); }

/* If I am the winner, make icon gold */
.award-badge.mine .award-icon {
    background: #FFD700 !important;
    color: white !important;
    box-shadow: 0 0 10px rgba(255, 215, 0, 0.4);
}

.award-info { flex: 1; display: flex; flex-direction: column; }
.award-info strong {
    display: block; font-size: 13px; color: var(--dark); font-weight: 700;
}
.award-info small {
    font-size: 11px; color: var(--dark-grey);
    margin-bottom: 2px;
}

/* The "YOU" Tag */
.me-tag {
    background: #FFD700;
    color: #584200;
    padding: 1px 6px;
    border-radius: 4px;
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
}

.award-count {
    font-size: 10px;
    color: var(--dark-grey);
    background: rgba(0,0,0,0.03);
    padding: 2px 6px;
    border-radius: 4px;
    align-self: flex-start;
    font-weight: 600;
}

/* --- DARK MODE OVERRIDES --- */
body.dark .awards-card { background: var(--card-bg); border-color: var(--card-border); }
body.dark .awards-icon { filter: brightness(1.1); }
body.dark .awards-title h3 { color: var(--dark); }
body.dark .awards-title p { color: var(--dark-grey); }

body.dark .performance-meter { background: rgba(255,255,255,0.03); border-color: rgba(255,255,255,0.05); }
body.dark .score-value { color: var(--dark); }
body.dark .score-label { color: var(--dark-grey); }

body.dark .stat-item { background: rgba(255,255,255,0.03); border-color: rgba(255,255,255,0.05); }
body.dark .stat-item:hover { background: rgba(255,255,255,0.05); }
body.dark .stat-text span { color: var(--dark); }
body.dark .stat-text small { color: var(--dark-grey); }

body.dark .awards-list h4 { color: var(--dark-grey); }
body.dark .award-badge { background: rgba(255,255,255,0.03); border-color: rgba(255,255,255,0.05); }
body.dark .award-badge.mine { background: rgba(255, 215, 0, 0.1); border-color: rgba(255, 215, 0, 0.4); }
body.dark .award-info strong { color: var(--dark); }
body.dark .award-info small { color: var(--dark-grey); }
body.dark .award-count { background: rgba(255,255,255,0.1); color: var(--dark-grey); }
</style>