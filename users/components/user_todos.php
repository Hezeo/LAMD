<!-- PHP LOGIC: Fetch Data Only -->
<?php
// Fetch Queue (Active) Tasks
$stmt_queue = $pdo->prepare("
    SELECT *, (due_date < NOW()) as is_overdue 
    FROM tbl_todos 
    WHERE user_id = ? AND status = 'queue' 
    ORDER BY 
        is_overdue DESC, 
        due_date IS NULL ASC, 
        due_date ASC, 
        FIELD(priority_level, 'Emergency', 'Urgent', 'Moderate', 'Normal', 'Low') ASC
");
$stmt_queue->execute([$user_id]);
$todos_queue = $stmt_queue->fetchAll();

// Fetch Completed Tasks
$stmt_completed = $pdo->prepare("SELECT * FROM tbl_todos WHERE user_id = ? AND status = 'completed' ORDER BY due_date ASC");
$stmt_completed->execute([$user_id]);
$todos_completed = $stmt_completed->fetchAll();
?>

<!-- TO DOS HEADER -->
<div class="todos-header">
    <div class="todos-icon">
        <span>📝</span>
    </div>
    <div class="todos-text">
        <h4>My To-Do's</h4>
        <p>Keep yourself productive and on track</p>
    </div>
</div>

<!-- HTML & CSS STRUCTURE -->

<div class="records-right-todos">

    <!-- 2. BUTTON ROW -->
    <div class="addnewtaskbtn-div">
        <div class="todo-tabs-wrapper">
            <button class="todo-tab-btn active-tab" onclick="switchTab('queue')">
                <span>Queue</span>
            </button>
            <button class="todo-tab-btn" onclick="switchTab('completed')">
                <span>Completed</span>
            </button>
        </div>
        <button class="todo-add-btn-main" onclick="openAddModal()">
            <span>+</span>
        </button>
    </div>

    <!-- 4. TODO LIST CONTAINER -->
    <div class="todo-list-wrapper" id="queue-container">
        <div class="todo-list-container">
            <?php if (count($todos_queue) > 0): ?>
                <?php foreach ($todos_queue as $todo): ?>
                    <?php
                    $priority_colors = [
                        'Low' => '#ef9a9a',
                        'Normal' => '#e57373',
                        'Moderate' => '#e57373',
                        'Urgent' => '#f44336',
                        'Emergency' => '#b71c1c'
                    ];
                    $p_color = $priority_colors[$todo['priority_level']] ?? '#777';

                    $has_date = !empty($todo['due_date']);
                    $is_overdue = $has_date && (strtotime($todo['due_date']) < time());
                    $date_style = $is_overdue ? 'color: #c94a4a;' : '';

                    // Progress Logic
                    $progress = isset($todo['progress']) ? (int)$todo['progress'] : 0;
                    $prog_color = '#e0e0e0';
                    if ($progress >= 100) $prog_color = '#4CAF50';
                    else if ($progress > 50) $prog_color = '#8bc34a';
                    else if ($progress > 20) $prog_color = '#ffc107';
                    ?>
                    <div class="todo-item">
                        <div class="todo-main">
                            <div style="display: flex; gap: 5px; margin-bottom: 5px;">
                                <div class="todo-priority-badge" style="background-color: <?php echo $p_color; ?>; color: white; border: none;">
                                    <?php echo htmlspecialchars($todo['priority_level']); ?>
                                </div>
                                <?php if ($is_overdue): ?>
                                    <div class="todo-priority-badge" style="background: transparent; color: #c94a4a; border: 1px solid #c94a4a;">
                                        OVERDUE
                                    </div>
                                <?php endif; ?>
                            </div>

                            <h5 class="todo-title"><?= htmlspecialchars($todo['title']) ?></h5>
                            <p class="todo-desc"><?= htmlspecialchars($todo['description']) ?></p>
                        </div>

                        <!-- Progress Bar with % at Top Right -->
                        <div class="todo-progress-wrapper">
                            <span class="progress-percent-label"><?= $progress ?>%</span>
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill" style="width: <?= $progress ?>%; background-color: <?= $prog_color ?>;"></div>
                            </div>
                        </div>

                        <div class="todo-footer">
                            <div class="todo-date" style="<?php echo $date_style; ?>">
                                <i class='bx bx-calendar'></i>
                                <span>
                                    <?php
                                    if ($has_date) echo date('M d, Y h:i A', strtotime($todo['due_date']));
                                    else echo 'No Due Date';
                                    ?>
                                </span>
                            </div>

                            <div class="todo-actions">
                                <a href="?action=complete&id=<?= $todo['todo_id'] ?>" class="action-check" title="Mark as Done"
                                    onclick="return confirm('Are you sure you want to mark this task as completed?');">
                                    <i class='bx bx-check'></i>
                                </a>

                                <!-- FIXED: Used Data Attributes instead of inline function arguments -->
                                <a href="#" class="action-edit" title="Edit Task"
                                    data-id="<?= $todo['todo_id'] ?>"
                                    data-title="<?= htmlspecialchars($todo['title']) ?>"
                                    data-desc="<?= htmlspecialchars($todo['description']) ?>"
                                    data-date="<?= $has_date ? date('Y-m-d\TH:i', strtotime($todo['due_date'])) : '' ?>"
                                    data-priority="<?= $todo['priority_level'] ?>"
                                    data-progress="<?= $progress ?>"
                                    onclick="openEditModalFromButton(this); return false;">
                                    <i class='bx bx-edit'></i>
                                </a>

                                <a href="?action=delete&id=<?= $todo['todo_id'] ?>" class="action-delete" title="Delete"
                                    onclick="return confirm('Are you sure you want to remove this task?');">
                                    <i class='bx bx-trash'></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-tasks">
                    <p>No active tasks in queue.<br>Click the + button to add one!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 5. COMPLETED LIST CONTAINER -->
    <div class="todo-list-wrapper" id="completed-container" style="display: none;">
        <div class="todo-list-container">
            <?php if (count($todos_completed) > 0): ?>
                <?php foreach ($todos_completed as $todo): ?>
                    <div class="todo-item" style="opacity: 0.7;">
                        <div class="todo-main">
                            <h5 class="todo-title" style="text-decoration: line-through; color: #555;"><?= htmlspecialchars($todo['title']) ?></h5>
                            <p class="todo-desc"><?= htmlspecialchars($todo['description']) ?></p>
                        </div>

                        <!-- Show 100% bar for completed -->
                        <div class="todo-progress-wrapper">
                            <span class="progress-percent-label">100%</span>
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill" style="width: 100%; background-color: #4CAF50;"></div>
                            </div>
                        </div>

                        <div class="todo-footer">
                            <div class="todo-date">
                                <span>Completed</span>
                            </div>
                            <div class="todo-actions">
                                <a href="?action=delete&id=<?= $todo['todo_id'] ?>" class="action-delete" title="Delete"
                                    onclick="return confirm('Are you sure you want to remove this task?');">
                                    <i class='bx bx-trash'></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-tasks">
                    <p>No completed tasks yet.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- JAVASCRIPT -->
<script>
    function toggleDateField(mode) {
        var checkbox, wrapper, input;
        if (mode === 'add') {
            checkbox = document.getElementById('add_has_due_date');
            wrapper = document.getElementById('add_date_wrapper');
            input = document.getElementById('add_due_date');
        } else {
            checkbox = document.getElementById('edit_has_due_date');
            wrapper = document.getElementById('edit_date_wrapper');
            input = document.getElementById('edit_due_date');
        }

        if (checkbox.checked) {
            wrapper.style.display = 'block';
            input.disabled = false;
            if (!input.value) {
                const now = new Date();
                now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
                input.value = now.toISOString().slice(0, 16);
            }
        } else {
            wrapper.style.display = 'none';
            input.disabled = true;
            input.value = '';
        }
    }

    function openAddModal() {
        const modal = document.getElementById('addModal');
        document.getElementById('add_has_due_date').checked = false;
        document.getElementById('add_date_wrapper').style.display = 'none';
        document.getElementById('add_due_date').disabled = true;
        document.getElementById('add_due_date').value = '';
        modal.style.display = 'block';
        modal.querySelector('.edit-modal-content').style.animation = 'slideInFromBottomRight 0.4s forwards';
    }

    function closeAddModal() {
        const modal = document.getElementById('addModal');
        const content = modal.querySelector('.edit-modal-content');
        content.style.animation = 'slideOutToBottomRight 0.4s forwards';
        setTimeout(() => {
            modal.style.display = 'none';
        }, 400);
    }

    // NEW: Helper function to read data attributes safely
    function openEditModalFromButton(button) {
        openEditModal(
            button.getAttribute('data-id'),
            button.getAttribute('data-title'),
            button.getAttribute('data-desc'),
            button.getAttribute('data-date'),
            button.getAttribute('data-priority'),
            button.getAttribute('data-progress')
        );
    }

    function openEditModal(id, title, desc, date, priority, progress) {
        const modal = document.getElementById('editModal');

        document.getElementById('edit_todo_id').value = id;
        document.getElementById('edit_title').value = title;
        document.getElementById('edit_description').value = desc;
        document.getElementById('edit_priority').value = priority;

        document.getElementById('edit_progress').value = progress;

        const checkbox = document.getElementById('edit_has_due_date');
        const input = document.getElementById('edit_due_date');

        if (date && date !== '') {
            checkbox.checked = true;
            input.value = date;
            input.disabled = false;
            document.getElementById('edit_date_wrapper').style.display = 'block';
        } else {
            checkbox.checked = false;
            input.value = '';
            input.disabled = true;
            document.getElementById('edit_date_wrapper').style.display = 'none';
        }

        modal.style.display = 'block';
        modal.querySelector('.edit-modal-content').style.animation = 'slideInFromBottomRight 0.4s forwards';
    }

    function closeEditModal() {
        const modal = document.getElementById('editModal');
        const content = modal.querySelector('.edit-modal-content');
        content.style.animation = 'slideOutToBottomRight 0.4s forwards';
        setTimeout(() => {
            modal.style.display = 'none';
        }, 400);
    }

    function switchTab(tabName) {
        document.getElementById('queue-container').style.display = 'none';
        document.getElementById('completed-container').style.display = 'none';
        var tabs = document.querySelectorAll('.todo-tab-btn');
        tabs.forEach(function(tab) {
            tab.classList.remove('active-tab');
        });

        if (tabName === 'queue') {
            document.getElementById('queue-container').style.display = 'block';
            tabs[0].classList.add('active-tab');
        } else {
            document.getElementById('completed-container').style.display = 'block';
            tabs[1].classList.add('active-tab');
        }
    }
</script>

<style>
    /* --- CONTAINER STYLING --- */
    .todo-list-container {
        display: flex;
        flex-direction: column;
        gap: 8px;
        /* max-height: 1000px; */
        max-height: auto;
        padding-right: 0px;
    }

    .addnewtaskbtn-div {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        margin-top: -6px;
        margin-bottom: 8px;
    }

    .todo-tabs-wrapper {
        display: flex;
        gap: 5px;
    }

    /* Tab Styling */
    .todo-tab-btn {
        background: transparent;
        border: 1px solid var(--card-border);
        color: var(--dark-grey);
        padding: 5px 15px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.2s;
        font-family: var(--poppins);
    }

    .todo-tab-btn:hover {
        background: var(--light-blue);
    }

    .todo-tab-btn.active-tab {
        background: var(--light);
        color: var(--dark);
        box-shadow: 0 2px 5px rgba(46, 125, 50, 0.2);
    }

    /* Add Button with Calm Hover */
    .todo-add-btn-main {
        background: var(--light);
        color: white;
        padding: 5px 15px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: var(--shadow-sm);
    }

    .todo-add-btn-main span {
        color: var(--dark);
        font-size: 18px;
        font-weight: 700;
    }

    /* Calm Hover Effect */
    .todo-add-btn-main:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        opacity: 0.9;
    }

    /* --- TODO ITEM CARD --- */
    .todo-item {
        background: #bfdbfe;
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        border-radius: 12px;
        padding: 12px;
        box-shadow: var(--shadow-sm);
        border: 1px solid var(--card-border);
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .todo-title {
        margin: 0 0 2px 0;
        font-size: 14px;
        color: var(--dark);
        font-weight: 600;
    }

    .todo-desc {
        margin: 0 0 8px 0;
        font-size: 12px;
        color: var(--dark-grey);
    }

    .todo-priority-badge {
        display: inline-block;
        font-size: 9px;
        font-weight: 700;
        text-transform: uppercase;
        padding: 4px 8px;
        border-radius: 4px;
        color: white;
        border: none;
    }

    .todo-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 8px;
    }

    .todo-date {
        font-size: 11px;
        color: var(--dark-grey);
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .todo-actions {
        display: flex;
        gap: 8px;
    }

    .action-check,
    .action-edit,
    .action-delete {
        text-decoration: none;
        font-size: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 26px;
        height: 26px;
        border-radius: 50%;
        transition: background 0.2s;
    }

    .action-check {
        color: var(--dark);
        background: rgba(46, 125, 50, 0.1);
    }

    .action-check:hover {
        background: var(--dark);
        color: white;
    }

    .action-edit {
        color: #0288d1;
        background: rgba(2, 136, 209, 0.1);
    }

    .action-edit:hover {
        background: #0288d1;
        color: white;
    }

    .action-delete {
        color: #c94a4a;
        background: rgba(201, 74, 74, 0.1);
    }

    .action-delete:hover {
        background: #c94a4a;
        color: white;
    }

    .no-tasks {
        text-align: center;
        padding: 20px;
        color: var(--dark-grey);
        font-size: 13px;
    }

    /* --- PROGRESS BAR (Text Upper Right) --- */
    .todo-progress-wrapper {
        margin-bottom: 8px;
        position: relative;
        padding-top: 12px;
    }

    /* The Percentage Text */
    .progress-percent-label {
        position: absolute;
        margin-top: -13px;
        right: 0;
        font-size: 10px;
        margin-bottom: 10px !important;
        color: var(--dark-grey);
    }

    .progress-bar-container {
        width: 100%;
        background-color: var(--grey);
        border-radius: 10px;
        height: 8px;
        position: relative;
        overflow: hidden;
        box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    .progress-bar-fill {
        height: 100%;
        border-radius: 10px;
        transition: width 0.4s ease;
        background-color: #4CAF50;
    }


    /* --- MODAL STYLING --- */
    .edit-modal-container {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 1000;
        background: rgba(0, 0, 0, 0.3);
    }

    .themed-modal {
        background: var(--card-bg) !important;
        border-radius: 12px;
        border: 1px solid var(--card-border);
        padding: 20px;
    }

    body:not(.dark) .themed-modal {
        background: linear-gradient(135deg, #ffffe9, #e9e9c1) !important;
    }

    /* UPDATED: Modal Content with Glow */
    .edit-modal-content {
        position: absolute;
        width: 380px;
        bottom: 20px;
        right: 20px;
        z-index: 1001;
        box-shadow:
            0 10px 40px rgba(0, 0, 0, 0.08),
            0 15px 50px rgba(0, 0, 0, 0.12),
            0 0 0 1px rgba(255, 255, 255, 0.1);
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        border-bottom: 1px solid var(--card-border);
        padding-bottom: 10px;
    }

    .modal-header h3 {
        margin: 0;
        color: var(--dark);
        font-size: 16px;
        font-weight: 700;
    }

    .modal-close-btn {
        background: transparent;
        border: none;
        font-size: 20px;
        color: var(--dark-grey);
        cursor: pointer;
    }

    .modal-close-btn:hover {
        color: var(--dark);
    }

    .form-group {
        margin-bottom: 12px;
    }

    .form-group label {
        display: block;
        font-size: 11px;
        color: var(--dark-grey);
        font-weight: 600;
        margin-bottom: 4px;
        text-transform: uppercase;
    }

    .form-group input,
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 10px;
        border: 1px solid var(--card-border);
        border-radius: 8px;
        font-size: 13px;
        background: var(--grey);
        color: var(--dark);
        box-sizing: border-box;
    }

    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus {
        outline: none;
        border-color: var(--dark);
        box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.1);
    }

    .form-row {
        display: flex;
        gap: 10px;
    }

    .form-row .form-group {
        flex: 1;
    }

    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 15px;
    }

    .btn-cancel {
        background: var(--grey);
        color: #c94a4a;
        border: 1px solid var(--card-border);
        padding: 8px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
    }

    .btn-cancel:hover {
        background: #fee2e2;
        border-color: #c94a4a;
    }

    .btn-save {
        background: var(--dark);
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
    }

    .btn-save:hover {
        background: #1B5E20;
    }

    @keyframes slideInFromBottomRight {
        0% {
            opacity: 0;
            transform: translate(100px, 100px);
        }

        100% {
            opacity: 1;
            transform: translate(0, 0);
        }
    }

    @keyframes slideOutToBottomRight {
        0% {
            opacity: 1;
            transform: translate(0, 0);
        }

        100% {
            opacity: 0;
            transform: translate(100px, 100px);
        }
    }

    /* --- DARK MODE OVERRIDES --- */

    /* Tab Buttons Dark Mode - "Best Fit" Styling */
    body.dark .todo-tab-btn {
        background: rgba(255, 255, 255, 0.03);
        border-color: rgba(255, 255, 255, 0.1);
        color: var(--dark-grey);
    }

    body.dark .todo-tab-btn:hover {
        background: rgba(255, 255, 255, 0.08);
        border-color: rgba(255, 255, 255, 0.2);
        color: #fff;
    }

    body.dark .todo-tab-btn.active-tab {
        background: rgba(255, 255, 255, 0.1);
        color: #fff;
        border-color: #2E7D32;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
    }

    /* Add Button Dark Mode Hover */
    body.dark .todo-add-btn-main {
        background: rgba(255, 255, 255, 0.05);
    }

    body.dark .todo-add-btn-main span {
        color: var(--dark);
    }

    body.dark .todo-add-btn-main:hover {
        background: rgba(255, 255, 255, 0.1);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    /* UPDATED: Light Green Glow for Dark Mode */
    body.dark .edit-modal-content {
        box-shadow:
            0 15px 50px rgba(46, 125, 50, 0.5),
            /* Soft green ambient glow */
            0 0 60px rgba(76, 175, 80, 0.25),
            /* Wider diffused green haze */
            0 0 0 1px rgba(76, 175, 80, 0.2);
        /* Subtle green rim light */
    }

    body.dark .todo-item {
        background: linear-gradient(135deg, #1b5e1f55 0%, #00000000 100%);
    }

    body.dark .progress-bar-container {
        background-color: rgba(255, 255, 255, 0.1);
    }

    body.dark .progress-percent-label {
        color: var(--dark-grey);
    }

    body.dark .action-check {
        background: rgba(46, 204, 113, 0.1);
        color: #2ecc71;
    }

    body.dark .action-check:hover {
        background: #2ecc71;
        color: #fff;
    }

    body.dark .action-edit {
        background: rgba(52, 152, 219, 0.1);
        color: #3498db;
    }

    body.dark .action-edit:hover {
        background: #3498db;
        color: #fff;
    }

    body.dark .action-delete {
        background: rgba(231, 76, 60, 0.1);
        color: #e74c3c;
    }

    body.dark .action-delete:hover {
        background: #e74c3c;
        color: #fff;
    }

    body.dark .btn-cancel {
        background: rgba(0, 0, 0, 0.2);
        color: #e74c3c;
        border-color: rgba(255, 255, 255, 0.1);
    }

    body.dark .btn-cancel:hover {
        background: rgba(231, 76, 60, 0.2);
    }

    body.dark .btn-save {
        background: #2ecc71;
    }

    body.dark .btn-save:hover {
        background: #27ae60;
    }
</style>