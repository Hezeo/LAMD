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

    // TOGGLE SIDEBAR
    const menuBar = document.querySelector('#content nav .bx.bx-menu');

    if (menuBar && sidebar) {
        menuBar.addEventListener('click', function () {
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
        });
    }

    // Helper to sync body class (used in toggle and resize)
    function updateBodyClass() {
        if (sidebar.classList.contains('hide')) {
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

    // ===== DARK MODE =====
    const switchMode = document.getElementById('switch-mode');

    if (switchMode) {
        switchMode.addEventListener('change', function () {
            document.body.classList.toggle('dark', this.checked);
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
        if (!e.target.closest('.notification') && notificationMenu) {
            notificationMenu.classList.remove('show');
        }
        if (!e.target.closest('.profile') && profileMenu) {
            profileMenu.classList.remove('show');
        }

        // CLOSE ROW ACTION MENU WHEN CLICKING OUTSIDE
        const actionMenu = document.querySelector('.row-action-menu');
        if (actionMenu && actionMenu.classList.contains('show')) {
            // If click is NOT on the menu AND NOT on the action cell
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
        width: 120px; /* Fixed width for consistent alignment */
        border: 1px solid #e0e0e0;
        overflow: hidden;
        padding: 6px 0; 
        animation: fadeInMenu 0.2s ease-out forwards;
        font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
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

    const searchForm = document.querySelector('form');
    const searchInput = document.getElementById('searchInput');
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
        currentCountSpan.textContent = visibleCount;
        totalCountSpan.textContent = totalRecords;
    }

    function toggleNoResultsMessage(show) {
        if (show) {
            noResultsMessage.style.display = 'block';
            document.querySelector('tbody').style.display = 'none';
        } else {
            noResultsMessage.style.display = 'none';
            document.querySelector('tbody').style.display = '';
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
                        let dateValue = rec[activeDateFilter.type + '_date']; //e.g. rec.expiry_date (incorrect property mapping)
                        // Correct mapping:
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

    // ===== MODAL LOGIC =====
    const modal = document.getElementById('landOwnerModal');
    const modalName = document.getElementById('modalName');
    const modalCode = document.getElementById('modalCode');
    // const modalAddress = document.getElementById('modalAddress');
    const overviewTab = document.getElementById('overview');
    const oldRecordsTab = document.getElementById('old_records');
    const oldBtn = document.getElementById('old_records_btn');

    document.querySelectorAll('.table-row').forEach(row => {
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

            // 2. Format for ARABLE: With commas, 4 decimal places (e.g., 1.2340)
            const formatArable = (num) => {
                // Remove existing commas to ensure valid parsing
                const cleanNum = String(num).replace(/,/g, '');
                const value = parseFloat(cleanNum);
                if (isNaN(value)) return '0.0000';

                return value.toLocaleString('en-US', {
                    minimumFractionDigits: 4,
                    maximumFractionDigits: 4
                });
            };

            const activeRecords = records.filter(rec => new Date(rec.expiry_date) >= today);
            const expiredRecords = records.filter(rec => new Date(rec.expiry_date) < today);

            // --- 2. OVERVIEW TABLE ---
            if (activeRecords.length === 0) {
                overviewTab.innerHTML = `<div style="text-align:center; padding:20px;">
                    <div style="font-size:40px;">📄</div>
                    <h3>No Active Contract</h3>
                    <p>There are no current lease agreements recorded matching your criteria.</p>
                </div>`;
            } else {
                const groupedByLot = {};
                activeRecords.forEach(rec => {
                    if (!groupedByLot[rec.lot_no]) {
                        groupedByLot[rec.lot_no] = {
                            cms_application_no: rec.cms_application_no, // ADDED
                            longlat: rec.longlat, // ADDED
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
                            total_arable: 0,
                            accounts: [],
                            vendor: rec.vendor
                        };
                    }
                    // Ensure we add a number, not a string
                    groupedByLot[rec.lot_no].total_arable += parseFloat(String(rec.contracted_arable).replace(/,/g, '')) || 0;
                    groupedByLot[rec.lot_no].accounts.push({
                        name: rec.group_acc,
                        arable: rec.contracted_arable
                    });
                });

                const rowCount = Object.keys(groupedByLot).length;
                const showFilters = rowCount >= 2;

                const createHeader = (text, colIndex) => {
                    if (showFilters) {
                        return `<div class="excel-header-container" data-col-index="${colIndex}">
                            ${text} <i class='bx bx-chevron-down excel-filter-icon'></i>
                        </div>`;
                    }
                    return text;
                };

                // UPDATED TABLE HEADERS with CMS Application No. and Long/Lat
                let overviewHTML = `
<table id="overview-table" style="width:100%; border-collapse: collapse; font-family: Arial, sans-serif; border: 1px solid #007A3D; border-radius:8px; overflow:hidden;">
<thead style="background-color:#78AB46; color:#fff; font-size: 12px;">
<tr>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('CMS Application No.', 0)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Lot No', 1)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Lease Status', 2)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Contract Status', 3)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Field No', 4)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Field Section', 5)}</th>
  <!-- <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Long/Lat', 6)}</th> -->
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Barangay', 7)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Municipality', 8)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Crop Type', 9)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Contract Class', 10)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">Rate</th>
  <th style="border:1px solid #007A3D; padding:8px;">Total Arable (HAS)</th>
  <th style="border:1px solid #007A3D; padding:8px;">Group Accounts</th>
  <th style="border:1px solid #007A3D; padding:8px;">Start Date</th>
  <th style="border:1px solid #007A3D; padding:8px;">Expiry Date</th>
  <th style="border:1px solid #007A3D; padding:8px;">Paid Up Date</th>
</tr>
</thead>
<tbody>`;

                for (const lot in groupedByLot) {
                    const data = groupedByLot[lot];
                    const accountsList = `<ul style="padding-left:15px; margin:0;">` +
                        // Use formatArable for the accounts list
                        data.accounts.map(acc => `<li>${acc.name}<strong>: ${formatArable(acc.arable)} HAS</strong></li>`).join('') +
                        `</ul>`;

                    overviewHTML += `
<tr style="background:#f9f9f9; font-size: 14px;" 
    data-lot="${lot}"
    data-name="${modalName.textContent}"
    data-vendor="${data.vendor || ''}" 
    data-col-0="${data.cms_application_no || ''}" 
    data-col-1="${lot}" 
    data-col-2="${data.lease_status}" 
    data-col-3="${data.contract_status}" 
    data-col-4="${data.field_no}" 
    data-col-5="${data.field_section}" 
    data-col-6="${data.longlat || ''}" 
    data-col-7="${data.barangay || ''}" 
    data-col-8="${data.municipality || ''}" 
    data-col-9="${data.crop_type}"
    data-col-10="${data.contract_class}">
  <td style="border:1px solid #007A3D; padding:5px;">${data.cms_application_no || 'N/A'}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${lot}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${data.lease_status}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${data.contract_status}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${data.field_no}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${data.field_section}</td>
  <!-- <td style="border:1px solid #007A3D; padding:5px;">${data.longlat || 'N/A'}</td> -->
  <td style="border:1px solid #007A3D; padding:5px;">${data.barangay || 'N/A'}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${data.municipality || 'N/A'}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${data.crop_type}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${data.contract_class}</td>
  <td style="border:1px solid #007A3D; padding:5px;">₱${formatRate(data.rate)}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${formatArable(data.total_arable)}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${accountsList}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${data.start_date || 'N/A'}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${data.expiry_date || 'N/A'}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${data.paid_up_date || 'N/A'}</td>
  <td class="action-cell">
     <i class='bx bx-dots-vertical-rounded row-action-icon' style="font-size: 18px; cursor: pointer;"></i>
  </td>
</tr>`;
                }
                overviewHTML += `</tbody></table>`;
                overviewTab.innerHTML = overviewHTML;

                setTimeout(() => {
                    // View URL: overview.php, Edit URL: handled inside function
                    makeTableRowsClickable('overview-table', 'landowners_tabs/overview.php?lot=', 'landowners_tabs/update_contract.php?lot=', modalName.textContent);

                    if (showFilters) {
                        initializeExcelFilters('overview-table');
                    }
                }, 0);
            }

            // --- 3. OLD RECORDS ---
            if (expiredRecords.length > 0) {
                oldBtn.style.display = 'block';
                oldBtn.textContent = `Old Records (${expiredRecords.length})`;

                const groupedOld = {};
                expiredRecords.forEach(rec => {
                    if (!groupedOld[rec.lot_no]) {
                        groupedOld[rec.lot_no] = {
                            cms_application_no: rec.cms_application_no,
                            longlat: rec.longlat,
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
                            total_arable: 0,
                            accounts: [],
                            vendor: rec.vendor // FIXED: Correctly map vendor here
                        };
                    }
                    groupedOld[rec.lot_no].total_arable += parseFloat(String(rec.contracted_arable).replace(/,/g, '')) || 0;
                    groupedOld[rec.lot_no].accounts.push({
                        name: rec.group_acc,
                        arable: rec.contracted_arable
                    });
                });

                const rowCount = Object.keys(groupedOld).length;
                const showFilters = rowCount >= 2;

                const createHeader = (text, colIndex) => {
                    if (showFilters) {
                        return `<div class="excel-header-container" data-col-index="${colIndex}">
                            ${text} <i class='bx bx-chevron-down excel-filter-icon'></i>
                        </div>`;
                    }
                    return text;
                };

                // UPDATED TABLE HEADERS with CMS Application No. and Long/Lat
                let oldTableHTML = `
<table id="old-records-table" style="width:100%; border-collapse: collapse; margin-top:20px; font-family: Arial, sans-serif; border: 1px solid #007A3D; border-radius:8px; overflow:hidden;">
<thead style="background:#78AB46; color:#fff; font-size: 12px;">
<tr>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('CMS Application No.', 0)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Lot No', 1)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Lease Status', 2)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Contract Status', 3)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Field No', 4)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Field Section', 5)}</th>
  <!-- <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Long/Lat', 6)}</th> -->
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Barangay', 7)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Municipality', 8)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Crop Type', 9)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Contract Class', 10)}</th>
  <th style="border:1px solid #007A3D; padding:8px;">Rate</th>
  <th style="border:1px solid #007A3D; padding:8px;">Total Arable (HAS)</th>
  <th style="border:1px solid #007A3D; padding:8px;">Group Accounts</th>
  <th style="border:1px solid #007A3D; padding:8px;">Start Date</th>
  <th style="border:1px solid #007A3D; padding:8px;">Expiry Date</th>
  <th style="border:1px solid #007A3D; padding:8px;">Paid Up Date</th>
</tr>
</thead>
<tbody>`;

                for (const lot in groupedOld) {
                    const data = groupedOld[lot];
                    const accountsList = `<ul style="padding-left:15px; margin:0;">` +
                        data.accounts.map(acc => `<li>${acc.name}<strong>: ${formatArable(acc.arable)} HAS</strong></li>`).join('') +
                        `</ul>`;

                    oldTableHTML += `
<tr style="background:#f9f9f9; font-size: 14px;" 
    data-lot="${lot}"
    data-name="${modalName.textContent}"
    data-vendor="${data.vendor || ''}" 
    data-col-0="${data.cms_application_no || ''}"
    data-col-1="${lot}"  
    data-col-2="${data.lease_status}" 
    data-col-3="${data.contract_status}" 
    data-col-4="${data.field_no}" 
    data-col-5="${data.field_section}" 
    data-col-6="${data.longlat || ''}" 
    data-col-7="${data.barangay || ''}" 
    data-col-8="${data.municipality || ''}" 
    data-col-9="${data.crop_type}"
    data-col-10="${data.contract_class}">
  <td style="border:1px solid #007A3D; padding:5px;">${data.cms_application_no || 'N/A'}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${lot}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${data.lease_status}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${data.contract_status}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${data.field_no}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${data.field_section}</td>
  <!-- <td style="border:1px solid #007A3D; padding:5px;">${data.longlat || 'N/A'}</td> -->
  <td style="border:1px solid #007A3D; padding:5px;">${data.barangay || 'N/A'}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${data.municipality || 'N/A'}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${data.crop_type}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${data.contract_class}</td>
  <td style="border:1px solid #007A3D; padding:5px;">₱${formatRate(data.rate)}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${formatArable(data.total_arable)}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${accountsList}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${data.start_date || 'N/A'}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${data.expiry_date || 'N/A'}</td>
  <td style="border:1px solid #007A3D; padding:5px;">${data.paid_up_date || 'N/A'}</td>
  <td class="action-cell">
     <i class='bx bx-dots-vertical-rounded row-action-icon' style="font-size: 18px; cursor: pointer;"></i>
  </td>
</tr>`;
                }
                oldTableHTML += `</tbody></table>`;
                oldRecordsTab.innerHTML = oldTableHTML;

                setTimeout(() => {
                    // View URL: old_records.php, Edit URL: handled inside function
                    makeTableRowsClickable('old-records-table', 'landowners_tabs/old_records.php?lot=', 'landowners_tabs/update_contract.php?lot=', modalName.textContent);

                    if (showFilters) {
                        initializeExcelFilters('old-records-table');
                    }
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
        actionMenu.innerHTML = `
            <div class="row-action-item" data-action="update">
                <i class='bx bx-edit-alt'></i> Update
            </div>
            <div class="row-action-item" data-action="delete">
                <i class='bx bx-trash'></i> Delete
            </div>
        `;
        document.body.appendChild(actionMenu);
    }

    // 2. Handle ROW CLICK (View Details)
    table.querySelectorAll('tbody tr').forEach(row => {
        row.style.cursor = 'pointer';

        row.addEventListener('click', (e) => {
            // FIX: If click is inside the action-cell OR the menu, ignore row click
            if (e.target.closest('.action-cell') || e.target.closest('.row-action-menu')) {
                return;
            }

            // Get Lot Number. 
            const lotCell = row.querySelector('td:nth-child(2)');
            const lotText = lotCell ? lotCell.textContent.trim() : '';

            // Construct View URL
            let url = `${viewUrlPrefix}${encodeURIComponent(lotText)}`;
            if (contractingPartyName) {
                url += `&name=${encodeURIComponent(contractingPartyName)}`;
            }
            window.open(url, '_blank');
        });
    });

    // 3. Handle ACTION CELL CLICK (Show Menu)
    // We target the .action-cell (td) instead of just the icon
    table.querySelectorAll('.action-cell').forEach(cell => {
        cell.addEventListener('click', (e) => {
            e.stopPropagation(); // Prevent the row click event above

            // Hide other open menus
            document.querySelectorAll('.row-action-menu.show').forEach(m => m.classList.remove('show'));

            // Use the icon inside the cell for positioning
            const icon = cell.querySelector('.row-action-icon');
            const rect = icon.getBoundingClientRect();

            // Position the menu
            actionMenu.style.top = `${rect.bottom + window.scrollY}px`;
            actionMenu.style.left = `${rect.right - 120}px`;

            // Pass data to the menu via dataset
            const row = icon.closest('tr'); // 'row' is defined here
            let lotNumber = row.dataset.lot;
            if (!lotNumber) {
                const lotCell = row.querySelector('td:nth-child(2)');
                lotNumber = lotCell ? lotCell.textContent.trim() : '';
            }

            actionMenu.dataset.lot = lotNumber;
            actionMenu.dataset.name = contractingPartyName;

            // FIX: Pass vendor to the menu dataset here
            actionMenu.dataset.vendor = row.dataset.vendor || '';

            actionMenu.classList.add('show');
        });
    });

    // 4. Handle MENU ITEM CLICK (Edit & Delete)
    if (!actionMenu.hasListener) {
        actionMenu.addEventListener('click', (e) => {
            const item = e.target.closest('.row-action-item');
            if (!item) return;

            const action = item.dataset.action;
            const lot = actionMenu.dataset.lot;
            const name = actionMenu.dataset.name;

            if (action === 'update' && lot && name) {
                const editUrl = `landowners_tabs/update_contract.php?lot=${encodeURIComponent(lot)}&name=${encodeURIComponent(name)}`;
                window.open(editUrl, '_blank');
            }

            // --- NEW DELETE LOGIC ---
            if (action === 'delete' && lot && name) {
                // 1. Populate the hidden modal fields
                document.getElementById('deleteLotNo').textContent = lot;
                document.getElementById('deleteName').textContent = name;

                // FIX: SET THE HIDDEN INPUT VALUE (This was missing)
                document.getElementById('deleteRecordId').value = lot;

                // FIX: Get Vendor from the actionMenu dataset
                const vendor = actionMenu.dataset.vendor || '';
                document.getElementById('deleteVendor').value = vendor;

                // 3. Open the modal
                const deleteModal = document.getElementById('deleteModal');
                if (deleteModal) deleteModal.classList.add('show-modal');
            }

            actionMenu.classList.remove('show');
        });
        actionMenu.hasListener = true;
    }
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
modalOverlay.addEventListener('click', (event) => {
    if (event.target === modalOverlay) {
        modalOverlay.style.display = 'none';
    }
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modalOverlay.style.display === 'flex') {
        modalOverlay.style.display = 'none';
    }
});

// Close modal button
document.querySelector('.modal-close').addEventListener('click', () => {
    const modal = document.getElementById('landOwnerModal');
    modal.style.display = 'none';
});

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

document.querySelector('.lo_dropdown_menu').addEventListener('scroll', () => {
    document.querySelectorAll('.submenu').forEach(sub => sub.style.display = 'none');
});


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

// ===== DELETE ACTION LOGIC =====

window.closeDeleteModal = function () {
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) deleteModal.classList.remove('show-modal');
}

window.confirmDelete = function () {
    const lot = document.getElementById('deleteRecordId').value;
    const name = document.getElementById('deleteName').textContent;
    const vendor = document.getElementById('deleteVendor').value; // ADDED: Get vendor

    // Optional: Show loading state on button
    const deleteBtn = document.querySelector('.btn-confirm-delete');
    const originalText = deleteBtn.textContent;
    deleteBtn.textContent = "Deleting...";
    deleteBtn.disabled = true;

    // Prepare FormData
    const formData = new FormData();
    formData.append('delete_contract', '1');
    formData.append('lot_no', lot);
    formData.append('contracting_party', name);
    formData.append('vendor', vendor); // ADDED: Send vendor

    fetch('actions/delete_contract_action.php', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            deleteBtn.textContent = originalText;
            deleteBtn.disabled = false;

            if (data.success) {
                closeDeleteModal();

                // Show Success Message
                const successModal = document.getElementById('successModal');
                const successMsg = successModal.querySelector('p');
                if (successMsg) successMsg.textContent = data.message || "Contract deleted successfully.";
                if (successModal) successModal.classList.add('show-modal');

                window.closeSuccessModal = function () {
                    window.location.reload();
                };

            } else {
                alert("Error: " + (data.message || "Could not delete record."));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('A network error occurred.');
            deleteBtn.textContent = originalText;
            deleteBtn.disabled = false;
        });
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

// ===== DYNAMIC GROUP ACCOUNT ROW LOGIC =====
window.addGroupRow = function () {
    const container = document.getElementById('groupAccountContainer');
    if (!container) return;

    const div = document.createElement('div');
    div.className = 'dynamic-row';
    div.innerHTML = `
        <div class="popup-form-group" style="flex: 2;">
            <input type="text" name="group_acc[]" placeholder="e.g. DELA CRUZ, JUAN A."
                   oninput="this.value = this.value.toUpperCase()">
        </div>
        <div class="popup-form-group" style="flex: 1;">
            <input type="number" step="0.0001" min="0" name="contracted_arable[]" placeholder="Arable (HAS)">
        </div>
        <button type="button" class="remove-row-btn" onclick="removeGroupRow(this)">&times;</button>
    `;
    container.appendChild(div);
}

window.removeGroupRow = function (btn) {
    const container = document.getElementById('groupAccountContainer');
    if (!container) return;

    if (container.querySelectorAll('.dynamic-row').length > 1) {
        btn.parentElement.remove();
    } else {
        alert("At least one group account entry is required.");
    }
}