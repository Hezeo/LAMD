<?php
require '../config.php'; 
require_once '../config_session.php';
checkLogin();
requirePermission('Land Owners');

 $user_id = getCurrentUserId();
 $message = '';

// DB Connection Fallback
if (!isset($pdo)) {
    $dsn = "mysql:host=localhost;dbname=your_db_name;charset=utf8mb4";
    $pdo = new PDO($dsn, "root", ""); 
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}

// --- AUTO-FIX DATABASE STRUCTURE ---
// Ensure the column can hold large data
try {
    $pdo->exec("ALTER TABLE csv_rows MODIFY COLUMN row_data LONGTEXT");
} catch (Exception $e) {
    // Ignore if already correct or permissions issue
}

// --- HANDLE UPLOAD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $orientation = 'horizontal'; 
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
                
                // Read lines with no length limit (0)
                while (($row = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
                    // FIX: Only add if row is not null and not completely empty
                    if (is_array($row) && !empty(array_filter($row))) {
                        $csvData[] = array_map('trim', $row); 
                    }
                }
                fclose($handle);
            }

            // Check if we actually got data
            if (empty($csvData)) {
                throw new Exception("CSV file is empty or could not be read.");
            }

            // 3. Insert into DB (Chunked)
            $insertRow = $pdo->prepare("INSERT INTO csv_rows (upload_id, row_data, row_order) VALUES (?, ?, ?)");
            
            $pdo->beginTransaction();
            $count = 0;
            
            foreach ($csvData as $index => $row) {
                // FIX: Ensure row is always an array before JSON encode
                if (!is_array($row)) continue; 
                
                $dataJson = json_encode($row);
                if ($dataJson === false) continue; // Skip if JSON fails

                $insertRow->execute([$uploadId, $dataJson, $index]);
                $count++;
                
                // Commit every 500 rows to prevent packet size errors
                if ($count % 500 === 0) {
                    $pdo->commit();
                    $pdo->beginTransaction();
                }
            }
            
            $pdo->commit();
            $message = "Success! Imported " . number_format($count) . " rows.";
            
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
        }
    }
}

// --- HANDLE DELETE ---
if (isset($_GET['delete_id'])) {
    $stmt = $pdo->prepare("DELETE FROM csv_uploads WHERE id = ?");
    $stmt->execute([$_GET['delete_id']]);
    
    // Clean up rows
    $stmt = $pdo->prepare("DELETE FROM csv_rows WHERE upload_id = ?");
    $stmt->execute([$_GET['delete_id']]);
    
    header("Location: file_upload.php");
    exit;
}

// --- FETCH FOR DISPLAY ---
 $stmt = $pdo->query("SELECT * FROM csv_uploads ORDER BY created_at DESC");
 $savedFiles = $stmt->fetchAll();

 $activeId = isset($_GET['file_id']) ? $_GET['file_id'] : ($savedFiles[0]['id'] ?? 0);

 $displayData = [];
 $activeFileName = '';

if ($activeId) {
    $fileStmt = $pdo->prepare("SELECT * FROM csv_uploads WHERE id = ?");
    $fileStmt->execute([$activeId]);
    $activeFile = $fileStmt->fetch();
    
    if ($activeFile) {
        $activeFileName = $activeFile['file_name'];
        
        $rowStmt = $pdo->prepare("SELECT * FROM csv_rows WHERE upload_id = ? ORDER BY row_order ASC");
        $rowStmt->execute([$activeId]);
        $rows = $rowStmt->fetchAll();

        foreach ($rows as $row) {
            $decoded = json_decode($row['row_data'], true);
            // FIX: Ensure we only add valid arrays to displayData
            if (is_array($decoded)) {
                $displayData[] = $decoded;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSV Database System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; padding: 50px;}
        
        /* Table Styling */
        .table-excel th, .table-excel td {
            border: 1px solid #ccc;
            padding: 6px 12px;
            vertical-align: top; 
        }

        /* First Column Width Limit */
        .table-excel th:first-child,
        .table-excel td:first-child {
            width: 150px; 
            max-width: 150px;
            white-space: normal !important; 
            word-wrap: break-word;
            background-color: #f1f1f1; 
        }

        /* Other columns */
        .table-excel th:not(:first-child),
        .table-excel td:not(:first-child) {
            white-space: nowrap;
            min-width: 80px;
        }

        /* Dark Header Styling */
        .table-excel thead th {
            background-color: #343a40 !important; 
            color: #ffffff !important; 
            font-weight: bold;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        
        /* Empty Row Styling */
        .table-excel tbody tr.empty-row td {
            height: 30px;
            background-color: #fff;
        }

        .table-container-scroll {
            width: 100%;
            max-height: 64vh; /* 70% of screen height */
            overflow: auto;
            border: 1px solid #ddd;
        }
        
        .sidebar-item { cursor: pointer; font-size: 0.9rem; }
        .sidebar-item:hover { background-color: #e9ecef; }
        .sidebar-item.active { background-color: #0d6efd; color: white; }
        .sidebar-item.active a { color: white; }
    </style>
</head>
<body>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card shadow">
                <div class="card-header bg-dark text-white"><h6 class="mb-0">Saved Files</h6></div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php if ($savedFiles): ?>
                            <?php foreach ($savedFiles as $file): ?>
                                <li class="list-group-item sidebar-item d-flex justify-content-between align-items-center <?php echo ($file['id'] == $activeId) ? 'active' : ''; ?>">
                                    <a href="?file_id=<?php echo $file['id']; ?>" class="text-decoration-none <?php echo ($file['id'] == $activeId) ? 'text-white' : ''; ?>">
                                        <?php echo htmlspecialchars($file['file_name']); ?>
                                    </a>
                                    <a href="?delete_id=<?php echo $file['id']; ?>" class="text-danger" onclick="return confirm('Delete this file?')"><small>✕</small></a>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="list-group-item text-muted">No files saved.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <!-- Upload -->
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white"><h6 class="mb-0">Upload New CSV</h6></div>
                <div class="card-body">
                    <?php if ($message): ?>
                        <div class="alert alert-success py-2"><?php echo $message; ?></div>
                    <?php endif; ?>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="orientation" value="horizontal">
                        
                        <div class="row g-3 align-items-center">
                            <div class="col-md-7"><input type="file" name="csv_file" class="form-control" required accept=".csv"></div>
                            <div class="col-md-5"><button type="submit" class="btn btn-success w-100">Save</button></div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Preview -->
            <div class="card shadow">
                <div class="card-header bg-secondary text-white py-2 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Preview: <small><?php echo htmlspecialchars($activeFileName ?: 'Select a file'); ?></small></h6>
                    <small class="text-light"><?php echo number_format(count($displayData)); ?> rows</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-container-scroll">
                        <?php
                        if (!empty($displayData)) {
                            // --- RENDER LOGIC ---
                            
                            // 1. Calculate max columns safely
                            $maxCols = 0;
                            foreach ($displayData as $row) {
                                // FIX: count only if array, else 0
                                $c = is_array($row) ? count($row) : 0;
                                if ($c > $maxCols) $maxCols = $c;
                            }

                            // 2. Normalize Data
                            $normData = [];
                            foreach ($displayData as $row) {
                                // FIX: Ensure row is array before padding
                                if (is_array($row)) {
                                    $normData[] = array_pad($row, $maxCols, '');
                                }
                            }

                            // 3. Identify empty columns (Ghost Columns)
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

                            // 4. Render Table
                            echo "<table class='table table-excel table-sm mb-0'>";
                            
                            $isHeader = true;
                            echo "<thead>";

                            foreach ($normData as $row) {
                                echo "<tr>";

                                // Check if row is empty
                                $isRowEmpty = true;
                                foreach ($row as $cell) {
                                    if (trim($cell) !== '') { $isRowEmpty = false; break; }
                                }

                                if ($isRowEmpty) {
                                    $visibleCols = $maxCols - count($colsToRemove);
                                    if ($visibleCols > 0) {
                                        echo "<td colspan='$visibleCols' class='p-0 m-0' style='height:20px;'>&nbsp;</td>";
                                    }
                                } else {
                                    for ($c = 0; $c < $maxCols; $c++) {
                                        if (in_array($c, $colsToRemove)) continue;

                                        $cellValue = $row[$c] ?? '';
                                        
                                        $colspan = 1;
                                        for ($k = $c + 1; $k < $maxCols; $k++) {
                                            if (in_array($k, $colsToRemove)) continue;
                                            if (isset($row[$k]) && trim($row[$k]) === '') {
                                                $colspan++;
                                            } else {
                                                break;
                                            }
                                        }

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

                        } else {
                            echo "<p class='text-center p-4 text-muted'>No data found.</p>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>