document.addEventListener('DOMContentLoaded', () => {

    // ===== SIDEBAR STATE INITIALIZATION =====
    const sidebar = document.getElementById('sidebar');
    const savedState = localStorage.getItem('sidebarState');

    // 1. Clean up the preload class from <html> so transitions work normally again
    document.documentElement.classList.remove('sidebar-preload-hide');

    // 2. Apply the correct class to the sidebar element now that it's loaded
    // This ensures the toggle button logic works correctly
    if (sidebar) {
        if (savedState === 'hidden') {
            sidebar.classList.add('hide');
            sidebar.classList.remove('show');
        } else {
            sidebar.classList.remove('hide');
            sidebar.classList.add('show');
        }
    }

    // ===== SIDEBAR ACTIVE =====
    const allSideMenu = document.querySelectorAll('#sidebar .side-menu.top li a');

    allSideMenu.forEach(item => {
        const li = item.parentElement;
        item.addEventListener('click', () => {
            allSideMenu.forEach(i => i.parentElement.classList.remove('active'));
            li.classList.add('active');
        });
    });

        // ===== TOGGLE SIDEBAR (UPDATED FOR MOBILE BEHAVIOR) =====
    const menuBar = document.querySelector('#content nav .bx.bx-menu');

    if (menuBar && sidebar) {
        menuBar.addEventListener('click', function (e) {
            if (window.innerWidth <= 576) {
                // --- MOBILE MODE: Slide In/Out + Toggle Overlay ---
                sidebar.classList.toggle('show');
                document.body.classList.toggle('nav-open');
            } else {
                // --- DESKTOP MODE: Collapse/Expand (Your Original Logic) ---
                sidebar.classList.toggle('hide');

                // SAVE STATE TO LOCAL STORAGE
                if (sidebar.classList.contains('hide')) {
                    localStorage.setItem('sidebarState', 'hidden');
                } else {
                    localStorage.setItem('sidebarState', 'visible');
                }

                // === FIX: Update Body Class and Modal Width ===
                updateBodyClass(); // Sync body class for CSS
                adjustModalWidth(); // Recalculate modal width immediately
            }
        });
    }

    // Helper to sync body class (used in toggle and resize)
    function updateBodyClass() {
        if (sidebar && sidebar.classList.contains('hide')) {
            document.body.classList.add('sidebar-closed');
        } else {
            document.body.classList.remove('sidebar-closed');
        }
    }

    function adjustSidebar() {
        if (!sidebar) return;

        const isMobile = window.innerWidth <= 576;
        const savedState = localStorage.getItem('sidebarState');

        if (isMobile) {
            sidebar.classList.add('hide');
            sidebar.classList.remove('show');
        } else {
            // On Desktop, respect the saved state
            if (savedState === 'hidden') {
                sidebar.classList.add('hide');
                sidebar.classList.remove('show');
            } else {
                sidebar.classList.remove('hide');
                sidebar.classList.add('show');
            }
        }

        // === FIX: Ensure body class is updated on resize/load ===
        updateBodyClass();
    }

    // Use a small timeout on resize to prevent jitter, or just call it directly
    window.addEventListener('resize', adjustSidebar);

    // Initial call
    adjustSidebar();

    // ===== DARK MODE =====
    const switchMode = document.getElementById('switch-mode');

    if (switchMode) {
        switchMode.addEventListener('change', function () {
            document.body.classList.toggle('dark', this.checked);
            localStorage.setItem('theme', this.checked ? 'dark' : 'light');
        });
    }

    // ===== NOTIFICATION =====
    const notification = document.querySelector('.notification');
    const notificationMenu = document.querySelector('.notification-menu');

    if (notification && notificationMenu) {
        notification.addEventListener('click', () => {
            notificationMenu.classList.toggle('show');
        });
    }

    // ===== PROFILE =====
    const profile = document.querySelector('.profile');
    const profileMenu = document.querySelector('.profile-menu');

    if (profile && profileMenu) {
        profile.addEventListener('click', () => {
            profileMenu.classList.toggle('show');
        });
    }

    window.addEventListener('click', (e) => {
        // --- Existing Notification Logic (PRESERVED) ---
        if (!e.target.closest('.notification') && notificationMenu) {
            notificationMenu.classList.remove('show');
        }
        // --- Existing Profile Logic (PRESERVED) ---
        if (!e.target.closest('.profile') && profileMenu) {
            profileMenu.classList.remove('show');
        }

        // --- NEW: CLOSE MOBILE MENU IF CLICKING OVERLAY OR OUTSIDE ---
        if (window.innerWidth <= 576 && sidebar.classList.contains('show')) {
            // If click is NOT on sidebar AND NOT on burger button
            if (!e.target.closest('#sidebar') && !e.target.closest('.bx-menu')) {
                sidebar.classList.remove('show');
                document.body.classList.remove('nav-open');
            }
        }

        // --- Existing Row Action Menu Logic (PRESERVED) ---
        const actionMenu = document.querySelector('.row-action-menu');
        if (actionMenu && actionMenu.classList.contains('show')) {
            if (!e.target.closest('.row-action-menu') && !e.target.closest('.action-cell')) {
                actionMenu.classList.remove('show');
            }
        }
    });

    // ===== MENU TOGGLE =====
    window.toggleMenu = function (menuId) {
        const menu = document.getElementById(menuId);
        if (!menu) return;

        document.querySelectorAll('.menu').forEach(m => {
            if (m !== menu) m.style.display = 'none';
        });

        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
    };

    document.querySelectorAll('.menu').forEach(menu => {
        menu.style.display = 'none';
    });

    // ===== INJECT EXCEL FILTER STYLES =====
    const filterStyles = `
        .excel-header-container { display: inline-flex; align-items: center; cursor: pointer; position: relative; }
        .excel-filter-icon { font-size: 14px; margin-left: 5px; color: #fff; transition: color 0.2s; }
        .excel-header-container:hover .excel-filter-icon { color: #ffeb3b; }
        
        .excel-header-container.is-filtered .excel-filter-icon { color: #ffeb3b !important; font-weight: bold; }

        .excel-dropdown { 
            position: fixed; 
            z-index: 9999; 
            background: #fff; 
            border: 1px solid #ccc; 
            box-shadow: 0 8px 20px rgba(0,0,0,0.2); 
            border-radius: 4px; 
            padding: 10px; 
            width: 240px; 
            display: none; 
        }
        .excel-dropdown.active { display: block; }
        
        .excel-search-input { width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 8px; font-size: 12px; box-sizing: border-box; }
        .excel-list { max-height: 200px; overflow-y: auto; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        .excel-item { display: flex; align-items: center; padding: 6px 2px; font-size: 12px; cursor: pointer; user-select: none; }
        .excel-item:hover { background: #f5f5f5; }
        
        .excel-item input { margin-right: 8px; cursor: pointer; }
        
        .excel-actions { margin-top: 10px; display: flex; justify-content: space-between; gap: 10px; }
        .excel-btn { font-size: 12px; padding: 6px 12px; border: 1px solid #ccc; background: #f9f9f9; cursor: pointer; border-radius: 3px; width: 100%; font-weight: 600; }
        .excel-btn:hover { background: #eee; }
        .excel-btn.clear { color: #555; border-color: #ccc; }
        .excel-btn.done { background: #4CAF50; color: white; border-color: #4CAF50; }
        .excel-btn.done:hover { background: #45a049; }

        #overview-table thead, #old-records-table thead { 
            position: sticky; 
            top: 0; 
            z-index: 10; 
        }
        #overview-table th, #old-records-table th {
            background-color: #78AB46; 
        }

        /* --- MAIN FILTER BUTTON ACTIVE STATE --- */
        .f_lo.is-filtering {
            color: #78AB46 !important;
            background: rgba(255, 193, 7, 0.1);
            animation: pulse-filter 2s infinite;
        }
        
        @keyframes pulse-filter {
            0% { box-shadow: 0 0 0 0 #FFC107; }
            70% { box-shadow: 0 0 0 6px rgba(255, 193, 7, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
        }

        /* --- DROPDOWN ITEM ACTIVE STATE --- */
        .lo_dropdown_menu li.active-fy-filter {
            background-color: #e8f5e9 !important;
            color: #2e7d32 !important;
            font-weight: bold;
            position: relative;
        }
        
        .lo_dropdown_menu li.active-fy-filter::after {
            content: '\\2714'; /* Checkmark */
            position: absolute;
            right: 10px;
            color: #2e7d32;
        }

                /* --- ROW ACTION MENU STYLES --- */
    .row-action-menu {
        position: absolute;
        background: #ffffff;
        border-radius: 8px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.15), 0 5px 10px rgba(0,0,0,0.05);
        z-index: 9999;
        display: none;
        width: 160px; /* Fixed width for consistent alignment */
        border: 1px solid #e0e0e0;
        overflow: hidden;
        padding: 6px 0; 
        animation: fadeInMenu 0.2s ease-out forwards;
        font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;

        margin-left: 120px !important;
        margin-top: -25px !important;
    }

    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-5px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .row-action-menu.show {
        display: block;
    }
    
    .row-action-item {
        padding: 12px 15px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
        color: #333;
        /* transition: all 0.2s ease; */
        border-bottom: 1px solid #f0f0f0;
    }

    .row-action-item:last-child {
        border-bottom: none;
    }

    .row-action-item:hover {
        background: #f1f8e9; /* Light Green Background */
        color: #2E7D32; /* Dark Green Text */
    }
    
    .row-action-item i {
        font-size: 18px;
        color: #555;
        transition: color 0.2s;
    }
    
    .row-action-item:hover i {
        color: #2E7D32;
    }
    
    /* Specific Colors for Actions */
    .row-action-item[data-action="update"] i {
        color: #1976d2; /* Blue for Edit/Update */
    }
    
    .row-action-item[data-action="delete"] i {
        color: #d32f2f; /* Red for Delete (future use) */
    }

    /* Container for the icon to handle positioning */
    .action-cell {
        position: relative;
        text-align: center;
        border: none !important; 
        background: transparent !important;
    }
        .action-cell:hover {
        background: rgb(235, 235, 235) !important;
        }

    
        /* --- UPDATED PERFECT GROUP HOVER STYLES --- */
    
    .group-hover-active {
        background-color: #C5E1A5 !important; 
        transition: background-color 0.15s ease;
    }

    tr.group-hover-active td.action-cell {
        background-color: #C5E1A5 !important;
    }

    tr.group-hover-active td.action-cell:hover {
        background-color: #C5E1A5 !important;
        cursor: pointer;
    }
    `;
    const styleSheet = document.createElement("style");
    styleSheet.innerText = filterStyles;
    document.head.appendChild(styleSheet);

    // === 1. CONSOLIDATE DUPLICATE NAMES (FRONTEND GROUPING) ===
    const tableRows = document.querySelectorAll('.table-row');
    const seenNames = {};

    tableRows.forEach(row => {
        const name = row.dataset.name.trim().toLowerCase();

        if (seenNames[name]) {
            const existingRecords = JSON.parse(seenNames[name].dataset.allRecords);
            const currentRecords = JSON.parse(row.dataset.allRecords);
            const mergedRecords = existingRecords.concat(currentRecords);

            seenNames[name].dataset.allRecords = JSON.stringify(mergedRecords);
            row.style.display = 'none';
            row.parentNode.removeChild(row);
        } else {
            seenNames[name] = row;
        }
    });

    // === 2. COUNT CONTRACTS LOGIC (UPDATED: Relies on contract_id) ===
    // Re-select active rows because some were removed in the previous step
    const allActiveRows = document.querySelectorAll('.table-row');
    allActiveRows.forEach(row => {
        const records = JSON.parse(row.dataset.allRecords || '[]');

        const uniqueContracts = new Set();
        let earliestDate = null;

        records.forEach(rec => {
            // --- COUNT LOGIC (UPDATED: Use contract_id) ---
            const cid = rec.contract_id;
            // Only count if contract_id exists (non-null/non-empty)
            if (cid) {
                uniqueContracts.add(cid);
            }

            // --- EARLIEST DATE LOGIC (Preserved) ---
            const currentDate = rec.start_date;
            if (currentDate && currentDate !== '0000-00-00') {
                if (!earliestDate || currentDate < earliestDate) {
                    earliestDate = currentDate;
                }
            }
        });

        // 1. Apply the final counted number to the cell
        const countCell = row.querySelector('.contract-count-cell');
        if (countCell) {
            countCell.textContent = uniqueContracts.size;
        }

        // 2. Calculate and apply Total Hectares (No changes needed here)
        const totalHasCell = row.querySelector('.contract-total-has-cell');
        if (totalHasCell) {
            let totalHas = 0;
            records.forEach(rec => {
                const arable = parseFloat(String(rec.contracted_arable || '0').replace(/,/g, ''));
                if (!isNaN(arable)) {
                    totalHas += arable;
                }
            });
            totalHasCell.textContent = totalHas.toLocaleString('en-US', {
                minimumFractionDigits: 4,
                maximumFractionDigits: 4
            });
        }
    });

    // === FIX: RE-SELECT ACTIVE ROWS AFTER MERGING ===
    // We must query again because some rows were removed from DOM
    const activeRows = document.querySelectorAll('.table-row');

    const noResultsMessage = document.getElementById('noResultsMessage');
    const currentCountSpan = document.getElementById('currentCount');
    const totalCountSpan = document.getElementById('totalCount');

    const totalRecords = activeRows.length;

    // --- STATE FOR DATE FILTER ---
    let activeDateFilter = { type: null, fy: null };

    // --- FISCAL YEAR HELPER FUNCTION (MODIFIED FOR REUSABILITY) ---

    // 1. Pure calculation logic (extracted to be reusable for both filtering and list generation)
    function getFYLabel(dateStr) {
        if (!dateStr || dateStr === '0000-00-00') return null;

        // 1. Cutoff Date: Fiscal Year system starts May 1, 2014
        if (dateStr < '2014-05-01') {
            return dateStr.split('-')[0]; // Just return Year string
        }

        // 2. FISCAL YEAR CALCULATION
        const parts = dateStr.split('-');
        if (parts.length < 2) return null;
        const year = parseInt(parts[0]);
        const month = parseInt(parts[1]);

        if (month <= 4) {
            return 'FY-' + String(year).slice(-2);
        } else {
            return 'FY-' + String(year + 1).slice(-2);
        }
    }

    // 2. Main filter function (Checks toggle state and calls the helper)
    function getFiscalYear(dateStr) {
        const isFyMode = document.body.classList.contains('fy-mode-active');

        if (!isFyMode) {
            // Calendar Year Mode
            if (!dateStr || dateStr === '0000-00-00') return null;
            return dateStr.split('-')[0];
        }

        // Fiscal Year Mode
        return getFYLabel(dateStr);
    }

    // === POPULATE DROPDOWNS DYNAMICALLY (Runs immediately on load) ===
    // This uses the 'activeRows' variable you already defined and the 'getFYLabel' helper above
    const yearCollections = {
        start: { cy: new Set(), fy: new Set() },
        paid: { cy: new Set(), fy: new Set() },
        expiry: { cy: new Set(), fy: new Set() }
    };

    activeRows.forEach(row => {
        try {
            const records = JSON.parse(row.dataset.allRecords || '[]');
            records.forEach(rec => {
                // Start Date
                if (rec.start_date) {
                    yearCollections.start.cy.add(rec.start_date.split('-')[0]);
                    const fyVal = getFYLabel(rec.start_date);
                    if (fyVal) yearCollections.start.fy.add(fyVal);
                }
                // Paid Date (Note: mapped to 'paid_up_date')
                if (rec.paid_up_date) {
                    yearCollections.paid.cy.add(rec.paid_up_date.split('-')[0]);
                    const fyVal = getFYLabel(rec.paid_up_date);
                    if (fyVal) yearCollections.paid.fy.add(fyVal);
                }
                // Expiry Date
                if (rec.expiry_date) {
                    yearCollections.expiry.cy.add(rec.expiry_date.split('-')[0]);
                    const fyVal = getFYLabel(rec.expiry_date);
                    if (fyVal) yearCollections.expiry.fy.add(fyVal);
                }
            });
        } catch (e) { console.error("Error parsing row data", e); }
    });

    // Update the DOM elements
    document.querySelectorAll('.has-submenu').forEach(menuItem => {
        const label = menuItem.textContent.trim();
        let type = null;
        if (label.includes('Start Date')) type = 'start';
        else if (label.includes('Paid Date')) type = 'paid';
        else if (label.includes('Expiry Date')) type = 'expiry';

        if (!type) return;

        const cyList = menuItem.querySelector('.cy-list');
        const fyList = menuItem.querySelector('.fy-list');

        // Populate Calendar Year List (DESCENDING: Highest Year First)
        if (cyList) {
            const sorted = Array.from(yearCollections[type].cy).sort((a, b) => b - a);
            cyList.innerHTML = sorted.map(yr =>
                `<li onclick="filterBy('${type}', '${yr}')">${yr}</li>`
            ).join('');
        }

        // Populate Fiscal Year List (DESCENDING: Highest FY First)
        if (fyList) {
            // We sort standard first, then reverse to get FY-39 -> FY-38
            const sorted = Array.from(yearCollections[type].fy).sort().reverse();
            fyList.innerHTML = sorted.map(yr =>
                `<li onclick="filterBy('${type}', '${yr}')">${yr}</li>`
            ).join('');
        }
    });

    // --- UPDATED FILTER BY FUNCTION ---
    window.filterBy = function (type, fy) {
        activeDateFilter = { type, fy };

        // 1. Visual Indicator on Main Button
        const filterBtn = document.querySelector('.f_lo');
        if (!filterBtn.classList.contains('is-filtering')) {
            filterBtn.classList.add('is-filtering');
        }

        // 2. Visual Indicator on Dropdown Item
        document.querySelectorAll('.lo_dropdown_menu li.active-fy-filter').forEach(el => el.classList.remove('active-fy-filter'));

        const allMenuItems = document.querySelectorAll('.lo_dropdown_menu li');
        allMenuItems.forEach(li => {
            if (li.textContent.trim() === fy) {
                li.classList.add('active-fy-filter');
            }
        });

        // 3. UPDATE DISPLAY: Label normal, Year BOLD
        const displayElement = document.getElementById('activeFilterDisplay');
        if (displayElement) {
            const labels = {
                'start': 'Start Date',
                'paid': 'Paid Date',
                'expiry': 'Expiry Date'
            };

            const labelText = labels[type] || type;
            // Use innerHTML and wrap fy in <b> tag
            displayElement.innerHTML = `${labelText}: <b>${fy}</b>`;
        }

        // 4. Perform Filter Logic
        let visibleCount = 0;

        activeRows.forEach(row => {
            const records = JSON.parse(row.dataset.allRecords || '[]');

            const matches = records.some(record => {
                let dateValue = '';
                if (type === 'start') dateValue = record.start_date;
                else if (type === 'paid') dateValue = record.paid_up_date;
                else if (type === 'expiry') dateValue = record.expiry_date;

                const recordFy = getFiscalYear(dateValue);
                return recordFy === fy;
            });

            if (matches) {
                row.style.display = "";
                visibleCount++;
            } else {
                row.style.display = "none";
            }
        });

        updateResultsCounter(visibleCount);
        toggleNoResultsMessage(visibleCount === 0);

        // Close menu if open
        if (filterBtn.classList.contains('on')) {
            changeClass(filterBtn);
        }
    };

    // === TOGGLE YEAR MODE FUNCTION ===
    window.toggleYearMode = function (isFiscalYear) {
        // 1. Toggle Visibility of Lists in Submenus
        document.querySelectorAll('.submenu').forEach(submenu => {
            const fyList = submenu.querySelector('.fy-list');
            const cyList = submenu.querySelector('.cy-list');

            if (fyList && cyList) {
                if (isFiscalYear) {
                    fyList.style.display = 'grid';
                    cyList.style.display = 'none';
                } else {
                    fyList.style.display = 'none';
                    cyList.style.display = 'grid';
                }
            }
        });

        // 2. Update Logic State (for helper function)
        if (isFiscalYear) {
            document.body.classList.add('fy-mode-active');
        } else {
            document.body.classList.remove('fy-mode-active');
        }

        // 3. (Optional) Re-apply current filter if one is active? 
        // Usually better to let user select new year.
    }

    // --- UPDATED SHOW ALL ROWS FUNCTION ---
    window.showAllRows = function () {
        activeDateFilter = { type: null, fy: null };
        resetTable();

        // 1. Remove Visual Indicators
        const filterBtn = document.querySelector('.f_lo');
        filterBtn.classList.remove('is-filtering');

        document.querySelectorAll('.lo_dropdown_menu li.active-fy-filter').forEach(el => el.classList.remove('active-fy-filter'));

        // CLEAR THE DISPLAY TEXT
        const displayElement = document.getElementById('activeFilterDisplay');
        if (displayElement) {
            displayElement.textContent = '';
        }

        // Close menu if open
        if (filterBtn.classList.contains('on')) {
            changeClass(filterBtn);
        }
    };

    function updateResultsCounter(visibleCount) {
        if (currentCountSpan) currentCountSpan.textContent = visibleCount;
        if (totalCountSpan) totalCountSpan.textContent = totalRecords;
    }

    function toggleNoResultsMessage(show) {
        if (show) {
            noResultsMessage.style.display = 'block';
            const tbody = document.querySelector('tbody');
            if (tbody) tbody.style.display = 'none';
        } else {
            noResultsMessage.style.display = 'none';
            const tbody = document.querySelector('tbody');
            if (tbody) tbody.style.display = '';
        }
    }

    function resetTable() {
        let visibleCount = 0;

        activeRows.forEach(row => {
            row.style.display = "";
            visibleCount++;

            const cells = row.querySelectorAll('td');
            cells.forEach(cell => {
                const target = cell.querySelector('p') || cell.querySelector('.status') || cell;
                if (target.hasAttribute('data-original-text')) {
                    const originalText = target.getAttribute('data-original-text');
                    target.innerHTML = originalText;
                }
            });
        });

        updateResultsCounter(visibleCount);
        toggleNoResultsMessage(false);
    }

    updateResultsCounter(totalRecords);

    // === SEARCH LOGIC (FIX: Use specific ID selector) ===
    const searchForm = document.getElementById('landownersSearchForm');
    const searchInput = document.getElementById('searchInput');

    if (searchInput && searchForm) {
        searchInput.addEventListener('search', function () {
            if (this.value === '') {
                resetTable();
            }
        });

        searchForm.addEventListener('submit', function (e) {
            e.preventDefault();

            // === CLOSE MODAL ON SEARCH ===
            const modal = document.getElementById('landOwnerModal');
            if (modal) {
                modal.style.display = 'none';
            }

            const searchTerms = searchInput.value.trim().toLowerCase().split(/\s+/).filter(t => t !== "");
            let visibleCount = 0;

            activeRows.forEach(row => {
                const rowData = {
                    party: row.dataset.name.toLowerCase(),
                    landlist: row.dataset.landlist.toLowerCase(),
                    textContent: row.textContent.toLowerCase(),
                    allRecords: JSON.parse(row.dataset.allRecords || '[]')
                };

                const allBarangays = rowData.allRecords.map(r => (r.barangay || '').toLowerCase()).join(' ');
                const allMunicipalities = rowData.allRecords.map(r => (r.municipality || '').toLowerCase()).join(' ');
                const allLotNos = rowData.allRecords.map(r => String(r.lot_no || '')).join(' ').toLowerCase();
                const allFieldNos = rowData.allRecords.map(r => String(r.field_no || '')).join(' ').toLowerCase();
                const allFieldSections = rowData.allRecords.map(r => String(r.field_section || '')).join(' ').toLowerCase();
                const allCropTypes = rowData.allRecords.map(r => (r.crop_type || '').toLowerCase()).join(' ');
                const allContractClasses = rowData.allRecords.map(r => (r.contract_class || '').toLowerCase()).join(' ');

                const isMatch = searchTerms.every(term => {
                    const searchableFields = [
                        rowData.party,
                        allBarangays,
                        allMunicipalities,
                        rowData.landlist,
                        allLotNos,
                        allFieldNos,
                        allFieldSections,
                        allCropTypes,
                        allContractClasses
                    ];

                    return searchableFields.some(fieldText => {
                        const exactMatchRegex = new RegExp(`\\b${term}\\b`, 'i');
                        return exactMatchRegex.test(fieldText);
                    });
                });

                const cells = row.querySelectorAll('td');
                cells.forEach(cell => {
                    const target = cell.querySelector('p') || cell.querySelector('.status') || cell;

                    if (!target.hasAttribute('data-original-text')) {
                        target.setAttribute('data-original-text', target.textContent.trim());
                    }

                    const originalText = target.getAttribute('data-original-text');

                    if (isMatch && searchTerms.length > 0) {
                        const combinedRegex = new RegExp(`\\b(${searchTerms.join('|')})\\b`, 'gi');
                        target.innerHTML = originalText.replace(combinedRegex, '<mark class="highlight">$1</mark>');
                    } else {
                        target.innerHTML = originalText;
                    }
                });

                // Handle Search Logic with Date Filter
                if (searchTerms.length === 0) {
                    // If search is empty, rely on date filter state
                    if (activeDateFilter.type) {
                        const records = JSON.parse(row.dataset.allRecords || '[]');
                        const matchesDate = records.some(rec => {
                            let dateValue = '';
                            if (activeDateFilter.type === 'start') dateValue = rec.start_date;
                            else if (activeDateFilter.type === 'paid') dateValue = rec.paid_up_date;
                            else if (activeDateFilter.type === 'expiry') dateValue = rec.expiry_date;

                            return getFiscalYear(dateValue) === activeDateFilter.fy;
                        });
                        if (matchesDate) { row.style.display = ""; visibleCount++; } else { row.style.display = "none"; }
                    } else {
                        row.style.display = ""; visibleCount++;
                    }
                } else {
                    // If search has terms
                    if (isMatch) {
                        // Check date filter too if active
                        if (activeDateFilter.type) {
                            const records = JSON.parse(row.dataset.allRecords || '[]');
                            const matchesDate = records.some(rec => {
                                let dateValue = '';
                                if (activeDateFilter.type === 'start') dateValue = rec.start_date;
                                else if (activeDateFilter.type === 'paid') dateValue = rec.paid_up_date;
                                else if (activeDateFilter.type === 'expiry') dateValue = rec.expiry_date;
                                return getFiscalYear(dateValue) === activeDateFilter.fy;
                            });
                            if (matchesDate) { row.style.display = ""; visibleCount++; } else { row.style.display = "none"; }
                        } else {
                            row.style.display = ""; visibleCount++;
                        }
                    } else {
                        row.style.display = "none";
                    }
                }
            });

            updateResultsCounter(visibleCount);

            if (searchTerms.length > 0 && visibleCount === 0) {
                toggleNoResultsMessage(true);
            } else {
                toggleNoResultsMessage(false);
            }
        });
    } // End search check

    // ===== MODAL LOGIC =====
    const modal = document.getElementById('landOwnerModal');
    const modalName = document.getElementById('modalName');
    const modalCode = document.getElementById('modalCode');
    const overviewTab = document.getElementById('overview');
    const oldRecordsTab = document.getElementById('old_records');
    const oldBtn = document.getElementById('old_records_btn');

    // === FIX: ATTACH CLICK LISTENERS TO ACTIVE ROWS ===
    activeRows.forEach(row => {
        row.addEventListener('click', () => {
            let records = JSON.parse(row.dataset.allRecords);

            // --- STEP 1: FILTER BY DATE (FROM MAIN FILTER - FY LOGIC) ---
            if (activeDateFilter.type && activeDateFilter.fy) {
                records = records.filter(rec => {
                    let dateValue = '';
                    if (activeDateFilter.type === 'start') dateValue = rec.start_date;
                    else if (activeDateFilter.type === 'paid') dateValue = rec.paid_up_date;
                    else if (activeDateFilter.type === 'expiry') dateValue = rec.expiry_date;

                    const recordFy = getFiscalYear(dateValue);
                    return recordFy === activeDateFilter.fy;
                });
            }

            // --- STEP 2: FILTER BY SEARCH INPUT ---
            if (searchInput) {
                const currentSearchVal = searchInput.value.trim().toLowerCase();
                const searchTerms = currentSearchVal.split(/\s+/).filter(t => t !== "");

                if (searchTerms.length > 0) {
                    records = records.filter(rec => {
                        return searchTerms.some(term => {
                            const fieldsToCheck = [
                                String(rec.contracting_party || ''),
                                String(rec.lot_no || ''),
                                String(rec.field_no || ''),
                                String(rec.field_section || ''),
                                String(rec.barangay || ''),
                                String(rec.municipality || ''),
                                String(rec.crop_type || ''),
                                String(rec.contract_class || '')
                            ];

                            const regex = new RegExp(`\\b${term}\\b`, 'i');
                            return fieldsToCheck.some(field => regex.test(field));
                        });
                    });
                }
            }
            // --- END FILTERS ---

            const today = new Date();
            today.setHours(0, 0, 0, 0);

            modal.style.display = 'flex';
            adjustModalWidth();

            // === RESET TO OVERVIEW TAB ===
            document.querySelectorAll('.modal-tabs button').forEach(btn => btn.classList.remove('active'));
            document.getElementById('overview_btn').classList.add('active');

            document.querySelectorAll('.modal-tab-content, .oldrec-tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById('overview').classList.add('active');

            modalName.textContent = row.dataset.name;
            // modalCode.textContent = "CMS Application No.: " + row.dataset.landlist;
            // modalAddress.textContent = row.dataset.barangay + ", " + row.dataset.municipality + ", " + row.dataset.province;

            overviewTab.innerHTML = '';
            oldRecordsTab.innerHTML = '';
            oldBtn.style.display = 'none';

            // === FORMATTER FUNCTIONS ===

            // 1. Format for RATE: With commas, 2 decimal places (e.g., 25,000.00)
            const formatRate = (num) => {
                // Remove existing commas to ensure valid parsing
                const cleanNum = String(num).replace(/,/g, '');
                const value = parseFloat(cleanNum);
                if (isNaN(value)) return '0.00';

                return value.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            };

            // 2. Format for ARABLE: DYNAMIC decimals (No limit, preserves database precision)
            // 2. Format for ARABLE: DYNAMIC decimals (Strips trailing zeros)
            const formatArable = (num) => {
                // 1. Clean the number (remove commas)
                const cleanNum = String(num).replace(/,/g, '');

                // 2. Parse to float (This strips trailing zeros: "2.4200" becomes 2.42)
                const value = parseFloat(cleanNum);
                if (isNaN(value)) return '0';

                // 3. Detect decimals based on the FLOAT value, not the original string
                // This ensures 2.42 stays 2.42, and 2.12345 stays 2.12345
                let decimals = 0;

                // Convert float to string to count what is actually left
                const valStr = value.toString();

                if (valStr.includes('.')) {
                    decimals = valStr.split('.')[1].length;
                }

                return value.toLocaleString('en-US', {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals
                });
            };

            const activeRecords = records.filter(rec => new Date(rec.expiry_date) >= today);
            const expiredRecords = records.filter(rec => new Date(rec.expiry_date) < today);

            // === HELPER: COUNT UNIQUE CONTRACTS (Uses contract_id) ===
            function countUniqueContracts(recordList) {
                const uniqueSet = new Set();
                recordList.forEach(rec => {
                    // Logic changed to rely on contract_id
                    const cid = rec.contract_id;
                    if (cid) {
                        uniqueSet.add(cid);
                    }
                });
                return uniqueSet.size;
            }

            // === UPDATE BUTTON TEXT WITH CORRECT COUNTS ===
            const activeContractCount = countUniqueContracts(activeRecords);
            const expiredContractCount = countUniqueContracts(expiredRecords);

            // 1. Update Overview Button
            const overviewBtn = document.getElementById('overview_btn');
            if (overviewBtn) overviewBtn.textContent = `Overview (${activeContractCount})`;

            // 2. Update Old Records Button
            if (expiredContractCount > 0) {
                oldBtn.style.display = 'block';
                oldBtn.textContent = `Old Records (${expiredContractCount})`;
            } else {
                oldBtn.style.display = 'none';
            }

                        // --- 2. OVERVIEW TABLE ---
            if (activeRecords.length === 0) {
                overviewTab.innerHTML = `<div style="text-align:center; padding:20px;">
                    <div style="font-size:40px;">📄</div>
                    <h3>No Active Contract</h3>
                    <p>There are no current lease agreements recorded matching your criteria.</p>
                </div>`;
            } else {

                // --- NEW LOGIC: GROUP BY CONTRACT ID ---
                const displayRowsMap = {};

                activeRecords.forEach(rec => {
                    // === FIX: Use contract_id as the unique key ===
                    const uniqueKey = (rec.contract_id || 'fallback_' + rec.id) + '_' + (rec.lot_no || '');

                    if (!displayRowsMap[uniqueKey]) {
                        displayRowsMap[uniqueKey] = {
                            id: rec.id,
                            cms_application_no: rec.cms_application_no,
                            lot_no: rec.lot_no,
                            lease_status: rec.lease_status,
                            contract_status: rec.contract_status,
                            field_no: rec.field_no,
                            field_section: rec.field_section,
                            barangay: rec.barangay,
                            municipality: rec.municipality,
                            crop_type: rec.crop_type,
                            contract_class: rec.contract_class,
                            rate: rec.rate,
                            start_date: rec.start_date,
                            expiry_date: rec.expiry_date,
                            paid_up_date: rec.paid_up_date,
                            longlat: rec.longlat,
                            // Keep signature for sorting if needed, but not for grouping
                            dateSignature: `${rec.start_date}_${rec.expiry_date}_${rec.paid_up_date}`,
                            contract_id: rec.contract_id,
                            accounts: []
                        };
                    }

                    displayRowsMap[uniqueKey].accounts.push({
                        name: rec.group_acc,
                        arable: rec.contracted_arable,
                        id: rec.id
                    });
                });

                let displayRows = Object.values(displayRowsMap);

                // SORT (Keep existing sort logic)
                displayRows.sort((a, b) => {
                    if (a.start_date < b.start_date) return -1;
                    if (a.start_date > b.start_date) return 1;
                    if (a.expiry_date < b.expiry_date) return -1;
                    if (a.expiry_date > b.expiry_date) return 1;
                    if (a.paid_up_date < b.paid_up_date) return -1;
                    if (a.paid_up_date > b.paid_up_date) return 1;
                    return a.lot_no.localeCompare(b.lot_no);
                });

                const rowCount = displayRows.length;
                const showFilters = rowCount >= 2;

                const createHeader = (text, colIndex) => {
                    if (showFilters) {
                        return `<div class="excel-header-container" data-col-index="${colIndex}">
                            ${text} <i class='bx bx-chevron-down excel-filter-icon'></i>
                        </div>`;
                    }
                    return text;
                };

                // === UPDATED HEADER ORDER ===
                let overviewHTML = `
<table id="overview-table" style="width:100%; border-collapse: collapse; font-family: Arial, sans-serif; border: 1px solid #007A3D; border-radius:8px; overflow:hidden;">
<thead style="background-color:#78AB46; color:#fff; font-size: 12px;">
<tr>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('CMS Application No.', 0)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Lot No', 1)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Field No', 2)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Field Section', 3)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Barangay', 4)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Municipality', 5)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Crop Type', 6)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Contract Class', 7)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">Rate</th>
  <th style="border:1px solid #007A3D; padding:8px;">Arable (HAS)</th>
  <th style="border:1px solid #007A3D; padding:8px;">Group Accounts</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Lease Status', 8)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Contract Status', 9)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">Start Date</th>
  <th style="border:1px solid #007A3D; padding:8px;">Expiry Date</th>
  <th style="border:1px solid #007A3D; padding:8px;">Paid Up Date</th>
</tr>
</thead>
<tbody>`;

                // === FIX: CALCULATE ROWSPAN MAP (Based on Contract ID) ===
                // Note: Since we are grouping by contract_id, each key in displayRowsMap is effectively 1 block.
                // However, if a single contract has multiple "rows" (due to multiple lots/fields mixed in one contract ID),
                // we need to count how many rows exist per contract ID block.
                // Based on your previous logic, it seems 1 Contract ID = 1 Group Block, but let's map it safely:
                const contractIdCountMap = {};
                displayRows.forEach(r => {
                    // Use the ID that is used for grouping
                    const key = r.contract_id || r.id; 
                    contractIdCountMap[key] = (contractIdCountMap[key] || 0) + 1;
                });

                let currentContractId = null;
                let colorClass = '';
                let contractCounter = 0;
                let rowsHTML = '';

                displayRows.forEach((row) => {
                    let totalRowArable = 0;
                    row.accounts.forEach(acc => {
                        const val = parseFloat(String(acc.arable || '0').replace(/,/g, ''));
                        if (!isNaN(val)) totalRowArable += val;
                    });

                    let groupAccountsHTML = '<ul style="padding-left:15px; margin:0;">';
                    row.accounts.forEach(acc => {
                        groupAccountsHTML += `<li>${acc.name}<strong>: ${formatArable(acc.arable)} HAS</strong></li>`;
                    });
                    groupAccountsHTML += '</ul>';

                    // === FIX: CHECK CONTRACT ID FOR COLOR CHANGE ===
                    let isHeaderRow = false;
                    if (row.contract_id !== currentContractId) {
                        currentContractId = row.contract_id;
                        contractCounter++;
                        if (contractCounter % 2 !== 0) {
                            colorClass = 'contract-yellow';
                        } else {
                            colorClass = 'contract-orange';
                        }
                        isHeaderRow = true;
                    }

                    // === FIX: GENERATE ACTION COLUMN WITH ROWSPAN ===
                    let actionCellHTML = '';
                    if (isHeaderRow) {
                        const key = row.contract_id || row.id;
                        const rowspan = contractIdCountMap[key]; 
                        actionCellHTML = `
                        <td class="action-cell" rowspan="${rowspan}" style="vertical-align: middle; text-align: center;">
                            ${window.CAN_EDIT_LANDOWNERS ? `<i class='bx bx-dots-vertical-rounded row-action-icon' style="font-size: 18px; cursor: pointer;"></i>` : ''}
                        </td>`;
                    }

                    // === UPDATED ROW ORDER & DATA ATTRIBUTES ===
                    // Using contract_id in data-contract-sig ensures hover works correctly for this specific contract
                    rowsHTML += `
<tr class="${colorClass}" 
    data-contract-sig="${row.contract_id || row.id}"
    data-contract-id="${row.contract_id || ''}"
    data-start-date="${row.start_date || ''}"
    data-expiry-date="${row.expiry_date || ''}"
    data-paid-up-date="${row.paid_up_date || ''}"
    style="background:#f9f9f9; font-size: 14px;" 
    data-id="${row.id || ''}" 
    data-lot="${row.lot_no}"
    data-name="${modalName.textContent}"
    data-vendor="${row.vendor || ''}" 
    data-col-0="${row.cms_application_no || ''}" 
    data-col-1="${row.lot_no}" 
    data-col-2="${row.field_no}" 
    data-col-3="${row.field_section || ''}"  
    data-col-4="${row.barangay || ''}" 
    data-col-5="${row.municipality || ''}" 
    data-col-6="${row.crop_type}"
    data-col-7="${row.contract_class}"
    data-col-8="${row.lease_status}" 
    data-col-9="${row.contract_status}">
  <td style="border:1px solid #007A3D; padding:5px;">${row.cms_application_no || 'N/A'}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${row.lot_no}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${row.field_no}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${row.field_section || 'N/A'}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${row.barangay || 'N/A'}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${row.municipality || 'N/A'}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${row.crop_type}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${row.contract_class}</td>
  <td style="border:1px solid #007A3D; padding:5px;">₱${formatRate(row.rate)}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${formatArable(totalRowArable)}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${groupAccountsHTML}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${row.lease_status}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${row.contract_status}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${row.start_date || 'N/A'}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${row.expiry_date || 'N/A'}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${row.paid_up_date || 'N/A'}</td>
  ${actionCellHTML}
</tr>`;
                });

                overviewHTML += rowsHTML;
                overviewHTML += `</tbody></table>`;
                overviewTab.innerHTML = overviewHTML;

                setTimeout(() => {
                    makeTableRowsClickable('overview-table', 'landowners_tabs/overview.php?lot=', 'landowners_tabs/update_contract.php?lot=', modalName.textContent);
                    if (showFilters) initializeExcelFilters('overview-table');
                    setupActionCellGroupHover('overview-table');
                }, 0);
            }

                        // --- 3. OLD RECORDS (UPDATED WITH SORTING & COLOR) ---
            if (expiredContractCount > 0) {
                // --- NEW LOGIC: GROUP BY CONTRACT ID ---
                const displayRowsMap = {};

                expiredRecords.forEach(rec => {
                    // === FIX: Use contract_id as the unique key ===
                    const uniqueKey = (rec.contract_id || 'fallback_' + rec.id) + '_' + (rec.lot_no || '');

                    if (!displayRowsMap[uniqueKey]) {
                        displayRowsMap[uniqueKey] = {
                            id: rec.id,
                            cms_application_no: rec.cms_application_no,
                            lot_no: rec.lot_no,
                            lease_status: rec.lease_status,
                            contract_status: rec.contract_status,
                            field_no: rec.field_no,
                            field_section: rec.field_section,
                            barangay: rec.barangay,
                            municipality: rec.municipality,
                            crop_type: rec.crop_type,
                            contract_class: rec.contract_class,
                            rate: rec.rate,
                            start_date: rec.start_date,
                            expiry_date: rec.expiry_date,
                            paid_up_date: rec.paid_up_date,
                            longlat: rec.longlat,
                            dateSignature: `${rec.start_date}_${rec.expiry_date}_${rec.paid_up_date}`,
                            contract_id: rec.contract_id,
                            accounts: []
                        };
                    }

                    displayRowsMap[uniqueKey].accounts.push({
                        name: rec.group_acc,
                        arable: rec.contracted_arable,
                        id: rec.id
                    });
                });

                let displayRows = Object.values(displayRowsMap);

                // SORT
                displayRows.sort((a, b) => {
                    if (a.start_date < b.start_date) return -1;
                    if (a.start_date > b.start_date) return 1;
                    if (a.expiry_date < b.expiry_date) return -1;
                    if (a.expiry_date > b.expiry_date) return 1;
                    if (a.paid_up_date < b.paid_up_date) return -1;
                    if (a.paid_up_date > b.paid_up_date) return 1;
                    return a.lot_no.localeCompare(b.lot_no);
                });

                const rowCount = displayRows.length;
                const showFilters = rowCount >= 2;

                const createHeader = (text, colIndex) => {
                    if (showFilters) {
                        return `<div class="excel-header-container" data-col-index="${colIndex}">
                            ${text} <i class='bx bx-chevron-down excel-filter-icon'></i>
                        </div>`;
                    }
                    return text;
                };

                // === UPDATED HEADER ORDER ===
                let oldTableHTML = `
<table id="old-records-table" style="width:100%; border-collapse: collapse; margin-top:20px; font-family: Arial, sans-serif; border: 1px solid #007A3D; border-radius:8px; overflow:hidden;">
<thead style="background:#78AB46; color:#fff; font-size: 12px;">
<tr>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('CMS Application No.', 0)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Lot No', 1)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Field No', 2)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Field Section', 3)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Barangay', 4)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Municipality', 5)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Crop Type', 6)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Contract Class', 7)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">Rate</th>
  <th style="border:1px solid #007A3D; padding:8px;">Arable (HAS)</th>
  <th style="border:1px solid #007A3D; padding:8px;">Group Accounts</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Lease Status', 8)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Contract Status', 9)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">Start Date</th>
  <th style="border:1px solid #007A3D; padding:8px;">Expiry Date</th>
  <th style="border:1px solid #007A3D; padding:8px;">Paid Up Date</th>
</tr>
</thead>
<tbody>`;

                // === FIX: CALCULATE ROWSPAN MAP ===
                const contractIdCountMap = {};
                displayRows.forEach(r => {
                    const key = r.contract_id || r.id;
                    contractIdCountMap[key] = (contractIdCountMap[key] || 0) + 1;
                });

                let currentContractId = null;
                let colorClass = '';
                let contractCounter = 0;
                let oldRowsHTML = '';

                displayRows.forEach((row) => {
                    let totalRowArable = 0;
                    row.accounts.forEach(acc => {
                        const val = parseFloat(String(acc.arable || '0').replace(/,/g, ''));
                        if (!isNaN(val)) totalRowArable += val;
                    });

                    let groupAccountsHTML = '<ul style="padding-left:15px; margin:0;">';
                    row.accounts.forEach(acc => {
                        groupAccountsHTML += `<li>${acc.name}<strong>: ${formatArable(acc.arable)} HAS</strong></li>`;
                    });
                    groupAccountsHTML += '</ul>';

                    // === FIX: CHECK CONTRACT ID FOR COLOR CHANGE ===
                    let isHeaderRow = false;
                    if (row.contract_id !== currentContractId) {
                        currentContractId = row.contract_id;
                        contractCounter++;
                        if (contractCounter % 2 !== 0) {
                            colorClass = 'contract-yellow';
                        } else {
                            colorClass = 'contract-orange';
                        }
                        isHeaderRow = true;
                    }

                    // === FIX: GENERATE ACTION COLUMN WITH ROWSPAN ===
                    let actionCellHTML = '';
                    if (isHeaderRow) {
                        const key = row.contract_id || row.id;
                        const rowspan = contractIdCountMap[key];
                        actionCellHTML = `
                        <td class="action-cell" rowspan="${rowspan}" style="vertical-align: middle; text-align: center;">
                            ${window.CAN_EDIT_LANDOWNERS ? `<i class='bx bx-dots-vertical-rounded row-action-icon' style="font-size: 18px; cursor: pointer;"></i>` : ''}
                        </td>`;
                    }

                    // === UPDATED ROW ORDER & DATA ATTRIBUTES ===
                    oldRowsHTML += `
<tr class="${colorClass}"
    data-contract-sig="${row.contract_id || row.id}"
    data-contract-id="${row.contract_id || ''}"
    data-start-date="${row.start_date || ''}"
    data-expiry-date="${row.expiry_date || ''}"
    data-paid-up-date="${row.paid_up_date || ''}"
    style="background:#f9f9f9; font-size: 14px;" 
    data-id="${row.id || ''}"
    data-lot="${row.lot_no}"
    data-name="${modalName.textContent}"
    data-vendor="${row.vendor || ''}" 
    data-col-0="${row.cms_application_no || ''}"
    data-col-1="${row.lot_no}"  
    data-col-2="${row.field_no}" 
    data-col-3="${row.field_section || ''}"  
    data-col-4="${row.barangay || ''}" 
    data-col-5="${row.municipality || ''}" 
    data-col-6="${row.crop_type}"
    data-col-7="${row.contract_class}"
    data-col-8="${row.lease_status}" 
    data-col-9="${row.contract_status}">
  <td style="border:1px solid #007A3D; padding:5px;">${row.cms_application_no || 'N/A'}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${row.lot_no}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${row.field_no}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${row.field_section || 'N/A'}</td> 
  <td style="border:1px solid #007A3D; padding:5px;">${row.barangay || 'N/A'}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${row.municipality || 'N/A'}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${row.crop_type}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${row.contract_class}</td>
  <td style="border:1px solid #007A3D; padding:5px;">₱${formatRate(row.rate)}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${formatArable(totalRowArable)}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${groupAccountsHTML}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${row.lease_status}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${row.contract_status}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${row.start_date || 'N/A'}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${row.expiry_date || 'N/A'}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${row.paid_up_date || 'N/A'}</td>
  ${actionCellHTML}
</tr>`;
                });

                oldTableHTML += oldRowsHTML;
                oldTableHTML += `</tbody></table>`;
                oldRecordsTab.innerHTML = oldTableHTML;

                setTimeout(() => {
                    makeTableRowsClickable('old-records-table', 'landowners_tabs/old_records.php?lot=', 'landowners_tabs/update_contract.php?lot=', modalName.textContent);
                    if (showFilters) initializeExcelFilters('old-records-table');
                    setupActionCellGroupHover('old-records-table');
                }, 0);
            }
        });
    });


}); //EventListener END


// Helper function to make table rows clickable and handle action menus
function makeTableRowsClickable(tableId, viewUrlPrefix, editUrlPrefix, contractingPartyName) {
    const table = document.getElementById(tableId);
    if (!table) return;

    // 1. Create the action menu (singleton pattern to avoid duplicates)
    let actionMenu = document.querySelector('.row-action-menu');
    if (!actionMenu) {
        actionMenu = document.createElement('div');
        actionMenu.className = 'row-action-menu';

        // UPDATED MENU: Added View Contract, Removed Delete
        // ADDED CLASS 'menu-item-update' TO THE UPDATE BUTTON
        actionMenu.innerHTML = `
            <div class="row-action-item" data-action="view">
                <i class='bx bx-show'></i> View Contract
            </div>
            <div class="row-action-item menu-item-update" data-action="update">
                <i class='bx bx-edit-alt'></i> Update Contract
            </div>
        `;
        document.body.appendChild(actionMenu);
    }

    // 2. Handle ROW CLICK (View Details)
    table.querySelectorAll('tbody tr').forEach(row => {
        row.style.cursor = 'pointer';

        row.addEventListener('click', (e) => {
            // If click is inside the action-cell OR the menu, ignore row click
            if (e.target.closest('.action-cell') || e.target.closest('.row-action-menu')) {
                return;
            }

            const lotCell = row.querySelector('td:nth-child(2)');
            const lotText = lotCell ? lotCell.textContent.trim() : '';
            const recordId = row.dataset.id || '';

            let url = `${viewUrlPrefix}${encodeURIComponent(lotText)}`;
            if (recordId) {
                url += `&id=${encodeURIComponent(recordId)}`;
            }
            if (contractingPartyName) {
                url += `&name=${encodeURIComponent(contractingPartyName)}`;
            }
            window.open(url, '_blank');
        });
    });

    // 3. Handle ACTION CELL CLICK (Show Menu) - UPDATED TO CAPTURE DATES
    table.querySelectorAll('.action-cell').forEach(cell => {

        cell.addEventListener('click', (e) => {
            e.stopPropagation();

            // PERMISSION CHECK
            if (!window.CAN_EDIT_LANDOWNERS) {
                return;
            }

            document.querySelectorAll('.row-action-menu.show').forEach(m => m.classList.remove('show'));

            // === FIX: Get position from the ICON inside the cell ===
            const icon = cell.querySelector('.row-action-icon');
            let rect;

            if (icon) {
                rect = icon.getBoundingClientRect();
            } else {
                rect = cell.getBoundingClientRect();
            }

            // Set menu position relative to the ICON
            actionMenu.style.top = `${rect.bottom + window.scrollY + 4}px`;
            actionMenu.style.left = `${rect.right - 120}px`;

            // ==========================================
            // NEW LOGIC: Toggle Update Button Visibility
            // ==========================================
            const updateBtn = actionMenu.querySelector('.menu-item-update');
            if (tableId === 'old-records-table') {
                // If it is the Old Records table, HIDE the Update button
                if (updateBtn) updateBtn.style.display = 'none';
            } else {
                // If it is the Overview table, SHOW the Update button (ensure it is visible)
                if (updateBtn) updateBtn.style.display = 'flex';
            }

            // --- GET DATA FROM THE ROW THE CELL BELONGS TO ---
            const row = cell.closest('tr');
            const cells = row.querySelectorAll('td');

            // 1. Get Lot & ID (Existing Logic)
            let lotNumber = row.dataset.lot;
            if (!lotNumber) {
                const lotCell = row.querySelector('td:nth-child(2)');
                lotNumber = lotCell ? lotCell.textContent.trim() : '';
            }
            const recordId = row.dataset.id || '';

            // 2. GET CONTRACT ID FROM ROW (NEW LOGIC)
            const contractIdVal = row.dataset.contractId ? row.dataset.contractId.trim() : '';

            // 3. Store data on the menu element for the action handlers
            actionMenu.dataset.lot = lotNumber;
            actionMenu.dataset.name = contractingPartyName;
            actionMenu.dataset.vendor = row.dataset.vendor || '';
            actionMenu.dataset.id = recordId;

            // STORE CONTRACT ID FOR VIEW/UPDATE LINK
            actionMenu.dataset.contractId = contractIdVal;

            actionMenu.classList.add('show');
        });
    });

    // 4. Handle MENU ITEM CLICK (View & Update)
    if (!actionMenu.hasListener) {
        actionMenu.addEventListener('click', (e) => {
            const item = e.target.closest('.row-action-item');
            if (!item) return;

            const action = item.dataset.action;
            const contractId = actionMenu.dataset.contractId;

            if (action === 'view') {
                const viewUrl = `landowners_tabs/view_contract.php?contract_id=${contractId}`;
                window.open(viewUrl, '_blank');
            }

            if (action === 'update') {
                const editUrl = `landowners_tabs/update_contract.php?contract_id=${contractId}`;
                window.open(editUrl, '_blank');
            }

            actionMenu.classList.remove('show');
        });
        actionMenu.hasListener = true;
    }
}

// --- UPDATED FUNCTION: UNIQUE GROUP HOVER EFFECT ---
function setupGroupHovering(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;

    table.querySelectorAll('tbody tr').forEach(row => {
        row.addEventListener('mouseenter', function () {
            // 1. Get the unique signature of the hovered row's contract
            const sig = this.getAttribute('data-contract-sig');

            if (sig) {
                // 2. Select ONLY rows with this exact signature
                const groupRows = table.querySelectorAll(`tbody tr[data-contract-sig="${sig}"]`);

                // 3. Apply the highlight class
                groupRows.forEach(r => r.classList.add('group-hover-active'));
            }
        });

        row.addEventListener('mouseleave', function () {
            // 4. Remove the highlight
            table.querySelectorAll('tbody tr.group-hover-active').forEach(r => {
                r.classList.remove('group-hover-active');
            });
        });
    });
}

// === SETUP ACTION CELL GROUP HOVER ===
// When hovering the ellipsis, highlight the ENTIRE contract group
// with the "stronger" hover color
function setupActionCellGroupHover(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;

    table.querySelectorAll('.action-cell').forEach(cell => {
        cell.addEventListener('mouseenter', function () {
            const row = cell.closest('tr');
            if (!row) return;

            const sig = row.getAttribute('data-contract-sig');

            if (sig) {
                // Add class to ALL rows with the same contract signature
                const groupRows = table.querySelectorAll(`tbody tr[data-contract-sig="${sig}"]`);
                groupRows.forEach(r => r.classList.add('group-hover-active'));
            }
        });

        cell.addEventListener('mouseleave', function () {
            // Remove the class from all rows
            table.querySelectorAll('tbody tr.group-hover-active').forEach(r => {
                r.classList.remove('group-hover-active');
            });
        });
    });
}

// ===== ADJUST MODAL WIDTH =====
function adjustModalWidth() {
    const sidebar = document.getElementById('sidebar');
    const modalContent = document.querySelector('.modal');

    if (!sidebar || !modalContent) return;

    // Check if the screen matches the Laptop View (1366px width)
    const isLaptopView = window.matchMedia("(min-width: 1025px) and (max-width: 1366px)").matches;

    if (isLaptopView) {
        // --- SETTINGS FOR LAPTOP VIEW (1366) ---
        if (sidebar.classList.contains('hide')) {
            modalContent.style.width = '94%';     // Adjusted specifically for laptop
            modalContent.style.maxWidth = '94%';
        } else {
            modalContent.style.width = '82%';     // Adjusted specifically for laptop
            modalContent.style.maxWidth = '92%';
            modalContent.style.height = '87%';
        }
    } else {
        // --- DEFAULT SETTINGS FOR OTHER SCREENS ---
        if (sidebar.classList.contains('hide')) {
            modalContent.style.width = '95%';
            modalContent.style.maxWidth = '95%';
        } else {
            modalContent.style.width = '86%';
            modalContent.style.maxWidth = '95%';
        }
    }
}

// IMPORTANT: Add this listener so the function runs automatically when you resize the window
window.addEventListener('resize', adjustModalWidth);

// ===== EXCEL FILTER LOGIC (Advanced) =====
let excelFilterState = {};

function initializeExcelFilters(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;

    excelFilterState = {};

    const headers = table.querySelectorAll('.excel-header-container');

    let dropdown = document.getElementById('global-excel-dropdown');
    if (!dropdown) {
        dropdown = document.createElement('div');
        dropdown.id = 'global-excel-dropdown';
        dropdown.className = 'excel-dropdown';

        dropdown.innerHTML = `
            <div class="excel-top-actions" style="display: flex; justify-content: space-between; margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 8px;">
                <button class="excel-btn clear" style="width: 48%; color: #d32f2f; border-color: #ffcdd2;">Clear</button>
                <button class="excel-btn select-all" style="width: 48%;">Select All</button>
            </div>
            <input type="text" class="excel-search-input" placeholder="Search...">
            <div class="excel-list"></div>
            <div class="excel-actions" style="margin-top: 10px; display: flex; justify-content: space-between; gap: 10px;">
                <button class="excel-btn cancel" style="width: 48%;">Cancel</button>
                <button class="excel-btn done" style="width: 48%;">Done</button>
            </div>
        `;
        document.body.appendChild(dropdown);
    }

    headers.forEach(header => {
        header.addEventListener('click', (e) => {
            e.stopPropagation();
            e.preventDefault();

            const colIndex = header.dataset.colIndex;

            const rect = header.getBoundingClientRect();
            dropdown.style.top = `${rect.bottom + window.scrollY}px`;
            dropdown.style.left = `${rect.left + window.scrollX}px`;

            dropdown.classList.add('active');
            dropdown.dataset.currentCol = colIndex;
            dropdown.dataset.targetTable = tableId;

            const listContainer = dropdown.querySelector('.excel-list');
            const searchInput = dropdown.querySelector('.excel-search-input');
            searchInput.value = '';

            const uniqueValues = new Set();
            table.querySelectorAll('tbody tr').forEach(row => {
                const val = row.getAttribute(`data-col-${colIndex}`);
                if (val) uniqueValues.add(val);
            });

            let hasBlanks = false;
            table.querySelectorAll('tbody tr').forEach(row => {
                const val = row.getAttribute(`data-col-${colIndex}`);
                if (!val) hasBlanks = true;
            });
            if (hasBlanks) uniqueValues.add('(Blanks)');

            const activeFilters = excelFilterState[colIndex];

            listContainer.innerHTML = '';

            const sortedValues = [...uniqueValues].sort((a, b) => {
                if (a === '(Blanks)') return -1;
                if (b === '(Blanks)') return 1;
                return a.localeCompare(b);
            });

            sortedValues.forEach(val => {
                const isChecked = !excelFilterState[colIndex] || excelFilterState[colIndex].includes(val);

                const item = document.createElement('div');
                item.className = 'excel-item';
                item.innerHTML = `<input type="checkbox" value="${val}" ${isChecked ? 'checked' : ''}><span>${val}</span>`;
                listContainer.appendChild(item);
            });

            searchInput.focus();
        });
    });

    dropdown.querySelector('.excel-search-input').addEventListener('input', (e) => {
        const searchTerm = e.target.value.toLowerCase();
        dropdown.querySelectorAll('.excel-item').forEach(item => {
            const text = item.querySelector('span').textContent.toLowerCase();
            item.style.display = text.includes(searchTerm) ? '' : 'none';
        });
    });

    dropdown.querySelector('.excel-list').addEventListener('click', (e) => {
        const item = e.target.closest('.excel-item');
        if (item) {
            const checkbox = item.querySelector('input');
            checkbox.checked = !checkbox.checked;
        }
    });

    dropdown.querySelector('.excel-btn.clear').addEventListener('click', (e) => {
        e.stopPropagation();
        dropdown.querySelectorAll('.excel-item input').forEach(cb => cb.checked = false);
    });

    dropdown.querySelector('.excel-btn.select-all').addEventListener('click', (e) => {
        e.stopPropagation();
        dropdown.querySelectorAll('.excel-item input').forEach(cb => cb.checked = true);
    });

    dropdown.querySelector('.excel-btn.cancel').addEventListener('click', (e) => {
        e.stopPropagation();
        dropdown.classList.remove('active');
    });

    dropdown.querySelector('.excel-btn.done').addEventListener('click', (e) => {
        e.stopPropagation();

        const colIndex = dropdown.dataset.currentCol;
        const targetTableId = dropdown.dataset.targetTable || tableId;

        const checkedValues = [];

        dropdown.querySelectorAll('.excel-item input:checked').forEach(cb => {
            checkedValues.push(cb.value);
        });

        const totalItems = dropdown.querySelectorAll('.excel-item').length;

        if (checkedValues.length === totalItems) {
            delete excelFilterState[colIndex];
        } else {
            excelFilterState[colIndex] = checkedValues;
        }

        applyFilters(targetTableId);
        updateFilterHighlight(targetTableId);

        dropdown.classList.remove('active');
    });

    document.addEventListener('click', (e) => {
        if (!dropdown.contains(e.target) && !e.target.closest('.excel-header-container')) {
            dropdown.classList.remove('active');
        }
    });
}

function updateFilterHighlight(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;

    const headers = table.querySelectorAll('.excel-header-container');
    headers.forEach(header => {
        const colIndex = header.dataset.colIndex;
        if (excelFilterState[colIndex]) {
            header.classList.add('is-filtered');
        } else {
            header.classList.remove('is-filtered');
        }
    });
}

function applyFilters(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    const rows = table.querySelectorAll('tbody tr');

    rows.forEach(row => {
        let show = true;

        for (const colIndex in excelFilterState) {
            const allowedValues = excelFilterState[colIndex];
            const rowValue = row.getAttribute(`data-col-${colIndex}`);

            const isBlankAllowed = allowedValues.includes('(Blanks)');
            const isValueAllowed = allowedValues.includes(rowValue);

            if (!rowValue) {
                if (!isBlankAllowed) show = false;
            } else {
                if (!isValueAllowed) show = false;
            }

            if (!show) break;
        }

        row.style.display = show ? '' : 'none';
    });
}


// CLOSES THE MODAL WHEN BACKGROUND OR ESC KEY IS CLICKED
const modalOverlay = document.getElementById('landOwnerModal');
if (modalOverlay) {
    modalOverlay.addEventListener('click', (event) => {
        if (event.target === modalOverlay) {
            modalOverlay.style.display = 'none';
        }
    });
}
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modalOverlay && modalOverlay.style.display === 'flex') {
        modalOverlay.style.display = 'none';
    }
});

// Close modal button
const modalCloseBtn = document.querySelector('.modal-close');
if (modalCloseBtn) {
    modalCloseBtn.addEventListener('click', () => {
        const modal = document.getElementById('landOwnerModal');
        if (modal) modal.style.display = 'none';
    });
}

// Unified Tab Logic
document.querySelectorAll('.modal-tabs button').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.modal-tabs button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        document.querySelectorAll('.modal-tab-content, .oldrec-tab-content').forEach(tab => {
            tab.classList.remove('active');
        });

        const targetTab = document.getElementById(btn.dataset.tab);
        if (targetTab) {
            targetTab.classList.add('active');
        }
    });
});

// FILTER Main Page
function changeClass(e) {
    e.classList.toggle('off');
    e.classList.toggle('on');
}

document.querySelectorAll('.f_lo').forEach(span => span.addEventListener('click', function () {
    changeClass(this);
}));

// === CLOSE DROPDOWN WHEN CLICKING OUTSIDE ===
document.addEventListener('click', function (e) {
    const filterBtn = document.querySelector('.f_lo');
    const dropdownMenu = document.querySelector('.lo_dropdown_menu');

    // Check if the click target is NOT the filter button AND NOT inside the dropdown menu
    if (!e.target.closest('.f_lo') && !e.target.closest('.lo_dropdown_menu')) {

        // If the button has the 'on' class, the menu is currently open
        if (filterBtn && filterBtn.classList.contains('on')) {
            filterBtn.classList.remove('on'); // Close the menu
            filterBtn.classList.add('off');   // Ensure proper state
        }

        // Also ensure any submenus are hidden
        if (dropdownMenu) {
            const allSubmenus = dropdownMenu.querySelectorAll('.submenu');
            allSubmenus.forEach(sub => {
                sub.style.display = 'none';
            });
        }
    }
});

document.querySelectorAll('.has-submenu').forEach(item => {
    item.addEventListener('mouseenter', function () {
        const submenu = this.querySelector('.submenu');
        if (!submenu) return;
        const parentRect = this.getBoundingClientRect();
        submenu.style.display = 'grid';
        submenu.style.left = (parentRect.left - submenu.offsetWidth) + 'px';
        const submenuHeight = submenu.offsetHeight;
        if (parentRect.top + submenuHeight > window.innerHeight) {
            submenu.style.top = (parentRect.bottom - submenuHeight) + 'px';
        } else {
            submenu.style.top = parentRect.top + 'px';
        }
    });

    item.addEventListener('mouseleave', function () {
        const submenu = this.querySelector('.submenu');
        if (submenu) {
            submenu.style.display = 'none';
        }
    });
});

const loDropdownMenu = document.querySelector('.lo_dropdown_menu');
if (loDropdownMenu) {
    loDropdownMenu.addEventListener('scroll', () => {
        document.querySelectorAll('.submenu').forEach(sub => sub.style.display = 'none');
    });
}


// ===== MODAL ELEMENTS =====
const addBtn = document.getElementById("openAddContractModal");
const addModal = document.getElementById("addContractModal");

if (addBtn) {
    addBtn.addEventListener('click', (e) => {
        e.preventDefault();
        if (addModal) addModal.classList.add("show-modal"); // Use class to trigger CSS
    });
}

if (addModal) {
    // Close if clicking outside content
    addModal.addEventListener('click', (e) => {
        if (e.target === addModal) {
            addModal.classList.remove("show-modal");
        }
    });
}

// Update close span
const addCloseSpan = addModal ? addModal.querySelector(".popup-close-modal") : null;
if (addCloseSpan) {
    addCloseSpan.addEventListener('click', () => {
        if (addModal) addModal.classList.remove("show-modal");
    });
}

// ===== VALIDATION & CONFIRMATION LOGIC =====

// 1. Vendor Input Restriction
const vendorInput = document.getElementById('vendor_input');
if (vendorInput) {
    vendorInput.addEventListener('input', function () {
        this.value = this.value.replace(/[^0-9]/g, '');
    });
}

// Contracting Party Input Restriction (Letters and Spaces Only)
const contractingPartyInput = document.querySelector('input[name="contracting_party"]');
if (contractingPartyInput) {
    contractingPartyInput.addEventListener('input', function () {
        // Allows Uppercase and Spaces only. Strips numbers and symbols.
        this.value = this.value.toUpperCase().replace(/[^A-Z\s]/g, '');
    });
}

// 2. Rate Input Restriction
const rateInput = document.getElementById('rate_input');
if (rateInput) {
    rateInput.addEventListener('input', function () {
        this.value = this.value.replace(/,/g, '');
    });
}

// 3. Validate and Show Confirmation
window.validateAndConfirm = function () {
    const form = document.getElementById('addContractForm');

    // Check HTML5 validity
    if (!form.checkValidity()) {
        form.reportValidity(); // Show browser errors
        return;
    }

    // Custom check for Vendor (just in case)
    if (vendorInput && vendorInput.value === '') {
        alert("Vendor must contain numbers only.");
        return;
    }

    // Show Confirmation Modal
    const confirmModal = document.getElementById('confirmModal');
    if (confirmModal) confirmModal.classList.add('show-modal');
}

window.closeConfirmModal = function () {
    const confirmModal = document.getElementById('confirmModal');
    if (confirmModal) confirmModal.classList.remove('show-modal');
}

// 4. Submit Form via AJAX
window.submitFinalForm = function () {
    closeConfirmModal(); // Hide confirmation

    const form = document.getElementById('addContractForm');
    const formData = new FormData(form);

    // Manually append 'add_contract' so the PHP isset works
    formData.append('add_contract', '1');

    console.log("Submitting data...");

    fetch('actions/add_new_contract_action.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json()) // Changed to .json() because PHP now returns JSON
        .then(data => {
            console.log("Response:", data);

            if (data.success) {
                // Show Success Modal
                const successModal = document.getElementById('successModal');
                if (successModal) successModal.classList.add('show-modal');

                // Close the Add Contract Modal
                const addModal = document.getElementById('addContractModal');
                if (addModal) addModal.classList.remove('show-modal');

                // Reset the form fields
                form.reset();
            } else {
                // Show Error Message
                alert("Error: " + (data.message || "Unknown error occurred."));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('A network error occurred. Check console for details.');
        });
}

window.closeSuccessModal = function () {
    const successModal = document.getElementById('successModal');
    if (successModal) successModal.classList.remove('show-modal');
    // Reload page to see changes
    window.location.reload();
}



// ===== PSGC API LOGIC (Preserved) =====
const provinceSelect = document.getElementById('provinceSelect');
const municipalitySelect = document.getElementById('municipalitySelect');
const barangaySelect = document.getElementById('barangaySelect');

async function loadProvinces() {
    try {
        const response = await fetch('https://psgc.gitlab.io/api/provinces/');
        const data = await response.json();

        if (!provinceSelect) return;

        provinceSelect.innerHTML = '<option value="" disabled selected>Select Province</option>';

        data.sort((a, b) => {
            const nameA = a.name.toUpperCase();
            const nameB = b.name.toUpperCase();
            if (nameA === 'BUKIDNON') return -1;
            if (nameB === 'BUKIDNON') return 1;
            return nameA.localeCompare(nameB);
        });

        data.forEach(province => {
            const option = document.createElement('option');
            const upperName = province.name.toUpperCase();
            option.value = upperName;
            option.textContent = upperName;
            option.dataset.code = province.code;
            provinceSelect.appendChild(option);
        });
    } catch (error) {
        console.error('Error loading provinces:', error);
    }
}

if (provinceSelect) {
    loadProvinces();

    provinceSelect.addEventListener('change', async function () {
        const selectedOption = this.options[this.selectedIndex];
        const provinceCode = selectedOption.dataset.code;

        municipalitySelect.innerHTML = '<option value="" disabled selected>Loading...</option>';
        municipalitySelect.disabled = true;
        barangaySelect.innerHTML = '<option value="" disabled selected>Select Municipality First</option>';
        barangaySelect.disabled = true;

        if (provinceCode) {
            try {
                const response = await fetch(`https://psgc.gitlab.io/api/provinces/${provinceCode}/cities-municipalities/`);
                const data = await response.json();

                municipalitySelect.innerHTML = '<option value="" disabled selected>Select Municipality</option>';

                data.forEach(muni => {
                    const option = document.createElement('option');
                    const upperName = muni.name.toUpperCase();
                    option.value = upperName;
                    option.textContent = upperName;
                    option.dataset.code = muni.code;
                    municipalitySelect.appendChild(option);
                });
                municipalitySelect.disabled = false;
            } catch (error) {
                console.error('Error loading municipalities:', error);
            }
        }
    });

    municipalitySelect.addEventListener('change', async function () {
        const selectedOption = this.options[this.selectedIndex];
        const muniCode = selectedOption.dataset.code;

        barangaySelect.innerHTML = '<option value="" disabled selected>Loading...</option>';
        barangaySelect.disabled = true;

        if (muniCode) {
            try {
                const response = await fetch(`https://psgc.gitlab.io/api/cities-municipalities/${muniCode}/barangays/`);
                const data = await response.json();

                barangaySelect.innerHTML = '<option value="" disabled selected>Select Barangay</option>';

                data.forEach(brgy => {
                    const option = document.createElement('option');
                    const upperName = brgy.name.toUpperCase();
                    option.value = upperName;
                    option.textContent = upperName;
                    barangaySelect.appendChild(option);
                });
                barangaySelect.disabled = false;
            } catch (error) {
                console.error('Error loading barangays:', error);
            }
        }
    });
}

// ===== GLOBAL COUNTER FOR LOTS =====
let lotCount = 0;

// ===== 1. INITIALIZE ONE LOT ON PAGE LOAD =====
document.addEventListener('DOMContentLoaded', () => {
    // Ensure we start with at least one lot block
    addLotBlock();
});

// ===== 2. FUNCTION TO ADD A NEW LOT BLOCK (UPDATED) =====
window.addLotBlock = function () {
    const container = document.getElementById('lotsContainer');
    if (!container) return;

    // FIX 1: Calculate index based on CURRENT children length
    const currentCount = container.querySelectorAll('.lot-block').length;
    const displayNumber = currentCount + 1;

    const div = document.createElement('div');
    div.className = 'lot-block';
    div.style.border = '1px solid #ccc';
    div.style.padding = '15px';
    div.style.marginBottom = '20px';
    div.style.borderRadius = '8px';
    div.style.backgroundColor = '#f4f6f8';
    div.style.position = 'relative';

    div.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom: 2px solid #ddd; padding-bottom: 5px;">
            <!-- UPDATE: Added ID and Icon here -->
            <h4 id="lot_title_${currentCount}" style="margin:0; color:#2E7D32;">
                <i class='bx bx-map'></i> Lot ?
            </h4>
            ${currentCount > 0 ? `<button type="button" onclick="removeLotBlock(this)" style="background:#ffebee; color:#c62828; border:1px solid #c62828; padding: 5px 10px; border-radius:4px; cursor:pointer; font-weight:600; font-size:12px;">Remove Lot</button>` : ''}
        </div>

        <!-- 1. LOT IDENTITY -->
        <div class="popup-form-row">
            <div class="popup-form-group">
                <label>Lot No</label>
                <!-- UPDATE: Added oninput trigger here -->
                <input type="text" name="lots[${currentCount}][lot_no]" oninput="this.value = this.value.toUpperCase(); updateLotTitle(this, ${currentCount})" required style="font-weight:bold;">
            </div>
            <div class="popup-form-group">
                <label>Field No</label>
                <input type="text" name="lots[${currentCount}][field_no]" oninput="this.value = this.value.toUpperCase()">
            </div>
            <div class="popup-form-group">
                <label>Field Section</label>
                <input type="text" name="lots[${currentCount}][field_section]" oninput="this.value = this.value.toUpperCase()">
            </div>
        </div>

        <!-- 2. LOCATION (FIX 2: Dropdowns Returned) -->
        <div class="popup-form-row">
            <div class="popup-form-group">
                <label>Province</label>
                <select name="lots[${currentCount}][province]" id="province_${currentCount}" onchange="loadMunicipalitiesForLot(${currentCount})" required style="background:#fff;">
                    <option value="" disabled selected>Loading...</option>
                </select>
            </div>
            <div class="popup-form-group">
                <label>Municipality</label>
                <select name="lots[${currentCount}][municipality]" id="municipality_${currentCount}" onchange="loadBarangaysForLot(${currentCount})" disabled style="background:#fff;">
                    <option value="" disabled selected>Select Province First</option>
                </select>
            </div>
        </div>
        <div class="popup-form-row">
            <div class="popup-form-group">
                <label>Barangay</label>
                <select name="lots[${currentCount}][barangay]" id="barangay_${currentCount}" disabled style="background:#fff;">
                    <option value="" disabled selected>Select Municipality First</option>
                </select>
            </div>
            <div class="popup-form-group">
                <label>Long/Lat</label>
                <input type="text" name="lots[${currentCount}][longlat]" placeholder="e.g. 8.123, 124.456">
            </div>
        </div>

        <!-- 3. FINANCIALS & CROP (FIX 3: Input Restrictions) -->
        <div class="popup-form-row">
            <div class="popup-form-group">
                <label>Rate</label>
                <!-- Only allow numbers, commas, and dots -->
                <input type="text" name="lots[${currentCount}][rate]" placeholder="e.g. 15000.00" oninput="this.value = this.value.replace(/[^0-9.,]/g, '')">
            </div>
            <div class="popup-form-group">
                <label>Crop Type</label>
                <select name="lots[${currentCount}][crop_type]">
                    <option value="S16">S16</option>
                    <option value="C74">C74</option>
                    <option value="PAPAYA">PAPAYA</option>
                    <option value="AVOCADO">AVOCADO</option>
                    <option value="OP">OP</option>
                    <option value="WHSE">WHSE</option>
                    <option value="BODEGA">BODEGA</option>
                </select>
            </div>
        </div>

        <!-- 4. STATUSES -->
        <div class="popup-form-row">
            <div class="popup-form-group">
                <label>Lease Status</label>
                <select name="lots[${currentCount}][lease_status]">
                    <option value="Active">Active</option>
                    <option value="Expired">Expired</option>
                    <option value="Near Expiration">Near Expiration</option>
                </select>
            </div>
            <div class="popup-form-group">
                <label>Contract Status</label>
                <select name="lots[${currentCount}][contract_status]">
                    <option value="EXISTING">EXISTING</option>
                    <option value="NONRENEWING">NONRENEWING</option>
                    <option value="FOR RETURN">FOR RETURN</option>
                    <option value="RETURNED">RETURNED</option>
                    <option value="RENEWED">RENEWED</option>
                    <option value="EXTENSION">EXTENSION</option>
                    <option value="NEWLAND">NEWLAND</option>
                    <option value="EXPIRED">EXPIRED</option>
                    <option value="UNUTILIZED">UNUTILIZED</option>
                    <option value="RETAIN">RETAIN</option>
                    <option value="CANCELLED">CANCELLED</option>
                </select>
            </div>
        </div>
        <div class="popup-form-row">
            <div class="popup-form-group">
                <label>Contract Class</label>
                <select name="lots[${currentCount}][contract_class]">
                    <option value="CP&GA">CP&GA</option>
                    <option value="DEVELOPMENT AGREEMENT">DEVELOPMENT AGREEMENT</option>
                    <option value="MOA">MOA</option>
                    <option value="CONTRACT OF LEASE">CONTRACT OF LEASE</option>
                    <option value="GROWERSHIP AGREEMENT">GROWERSHIP AGREEMENT</option>
                    <option value="GROWERSHIP">GROWERSHIP</option>
                </select>
            </div>
            <div class="popup-form-group">
                <!-- Empty spacer -->
            </div>
        </div>

        <!-- 5. GROUP ACCOUNTS (Nested) -->
        <div style="margin-top: 15px; border-top: 1px dashed #bbb; padding-top: 10px;">
            <label style="font-size:0.8rem; font-weight:600; color:#555;">Group Accounts for this Lot</label>
            <div class="group-account-container" id="groupContainer_${currentCount}">
                <div class="dynamic-row">
                    <div class="popup-form-group" style="flex: 2;">
                        <input type="text" name="lots[${currentCount}][groups][0][name]" value="NONE" placeholder="Group Account Name" oninput="this.value = this.value.toUpperCase().replace(/[^A-Z\s]/g, '')" disabled>
                    </div>
                    <div class="popup-form-group" style="flex: 1;">
                        <!-- Only numbers and dots for Arable -->
                        <input type="number" step="0.0001" min="0" name="lots[${currentCount}][groups][0][arable]" placeholder="Arable (HAS)" required>
                    </div>
                </div>
            </div>
            <button type="button" class="add-row-btn" onclick="addGroupRowToLot(${currentCount})" style="margin-top: 5px; font-size: 0.8rem; padding: 4px 8px;">
                <i class='bx bx-plus'></i> Add Group Account
            </button>
        </div>
    `;

    container.appendChild(div);

    // INITIALIZATION: Load Provinces immediately for this new block
    loadProvincesForLot(currentCount);
}


// ==========================================
// --- FEATURE: Live Update Lot Title ---
// ==========================================
window.updateLotTitle = function (input, index) {
    // 1. Find the title element by the unique ID
    const titleEl = document.getElementById(`lot_title_${index}`);

    // 2. Get the current text inside the input field
    const val = input.value.trim();

    // 3. Update the HTML with Icon and Lot Number
    if (val) {
        titleEl.innerHTML = `<i class='bx bx-map'></i> Lot ${val}`;
    } else {
        // If empty, revert to the placeholder icon and "?"
        titleEl.innerHTML = `<i class='bx bx-map'></i> Lot ${'?'}`;
    }
}

// ===== 3. FUNCTION TO ADD GROUP ACCOUNT TO A SPECIFIC LOT =====
window.addGroupRowToLot = function (lotIndex) {
    // Find the specific container for this lot
    const container = document.getElementById(`groupContainer_${lotIndex}`);
    if (!container) return;

    // Count existing rows to determine next index (simplification)
    // Or just let PHP handle the array indices by using empty brackets [] if preferred.
    // To keep it clean with explicit indices, we calculate next index:
    const existingGroups = container.querySelectorAll('.dynamic-row').length;

    const div = document.createElement('div');
    div.className = 'dynamic-row';
    div.innerHTML = `
        <div class="popup-form-group" style="flex: 2;">
            <input type="text" name="lots[${lotIndex}][groups][${existingGroups}][name]" placeholder="e.g. DELA CRUZ, JUAN A." oninput="this.value = this.value.toUpperCase().replace(/[^A-Z\s]/g, '')">
        </div>
        <div class="popup-form-group" style="flex: 1;">
            <input type="number" step="0.0001" min="0" name="lots[${lotIndex}][groups][${existingGroups}][arable]" placeholder="Arable (HAS)" required>
        </div>
        <button type="button" class="remove-row-btn" onclick="this.parentElement.remove()" style="margin-left: 5px;">&times;</button>
    `;
    container.appendChild(div);
}

// Remove a specific Lot Block
window.removeLotBlock = function (btn) {
    if (confirm("Are you sure you want to remove this Lot?")) {
        btn.closest('.lot-block').remove();
        // Optional: Re-index existing lots visually if you want the numbers to shift down?
        // For now, the "add" logic handles the next number correctly based on what remains.
    }
}

window.removeGroupRow = function (btn) {
    const row = btn.parentElement;
    // Find the container specific to this lot (closest parent with class starting with groupContainer)
    const container = row.closest('.group-account-container') || row.parentElement;

    // Check if it's the main group container or a sub-row
    // If it's inside a lot block, check siblings
    if (container && container.querySelectorAll('.dynamic-row').length > 1) {
        row.remove();
    } else {
        alert("At least one group account entry is required.");
    }
}

// ===== HELPER FUNCTIONS FOR DYNAMIC DROPDOWNS =====

// 1. Load Provinces for a specific Lot Index
window.loadProvincesForLot = function (index) {
    const pSelect = document.getElementById(`province_${index}`);
    if (!pSelect) return;

    pSelect.innerHTML = '<option value="" disabled selected>Loading...</option>';

    fetch('https://psgc.gitlab.io/api/provinces/')
        .then(response => response.json())
        .then(data => {
            pSelect.innerHTML = '<option value="" disabled selected>Select Province</option>';

            // Sort logic (Bukidnon first)
            data.sort((a, b) => {
                const nameA = a.name.toUpperCase();
                const nameB = b.name.toUpperCase();
                if (nameA === 'BUKIDNON') return -1;
                if (nameB === 'BUKIDNON') return 1;
                return nameA.localeCompare(nameB);
            });

            data.forEach(province => {
                const option = document.createElement('option');
                const upperName = province.name.toUpperCase();
                option.value = upperName;
                option.textContent = upperName;
                option.dataset.code = province.code;
                pSelect.appendChild(option);
            });
        })
        .catch(error => console.error('Error loading provinces:', error));
};

// 2. Load Municipalities for a specific Lot Index
window.loadMunicipalitiesForLot = function (index) {
    const pSelect = document.getElementById(`province_${index}`);
    const mSelect = document.getElementById(`municipality_${index}`);
    const bSelect = document.getElementById(`barangay_${index}`);

    if (!pSelect || !mSelect || !bSelect) return;

    const selectedOption = pSelect.options[pSelect.selectedIndex];
    const provinceCode = selectedOption.dataset.code;

    mSelect.innerHTML = '<option value="" disabled selected>Loading...</option>';
    mSelect.disabled = true;
    bSelect.innerHTML = '<option value="" disabled selected>Select Municipality First</option>';
    bSelect.disabled = true;

    if (provinceCode) {
        fetch(`https://psgc.gitlab.io/api/provinces/${provinceCode}/cities-municipalities/`)
            .then(response => response.json())
            .then(data => {
                mSelect.innerHTML = '<option value="" disabled selected>Select Municipality</option>';
                data.forEach(muni => {
                    const option = document.createElement('option');
                    const upperName = muni.name.toUpperCase();
                    option.value = upperName;
                    option.textContent = upperName;
                    option.dataset.code = muni.code;
                    mSelect.appendChild(option);
                });
                mSelect.disabled = false;
            })
            .catch(error => console.error('Error loading municipalities:', error));
    }
};

// 3. Load Barangays for a specific Lot Index
window.loadBarangaysForLot = function (index) {
    const mSelect = document.getElementById(`municipality_${index}`);
    const bSelect = document.getElementById(`barangay_${index}`);

    if (!mSelect || !bSelect) return;

    const selectedOption = mSelect.options[mSelect.selectedIndex];
    const muniCode = selectedOption.dataset.code;

    bSelect.innerHTML = '<option value="" disabled selected>Loading...</option>';
    bSelect.disabled = true;

    if (muniCode) {
        fetch(`https://psgc.gitlab.io/api/cities-municipalities/${muniCode}/barangays/`)
            .then(response => response.json())
            .then(data => {
                bSelect.innerHTML = '<option value="" disabled selected>Select Barangay</option>';
                data.forEach(brgy => {
                    const option = document.createElement('option');
                    const upperName = brgy.name.toUpperCase();
                    option.value = upperName;
                    option.textContent = upperName;
                    bSelect.appendChild(option);
                });
                bSelect.disabled = false;
            })
            .catch(error => console.error('Error loading barangays:', error));
    }
};