document.addEventListener('DOMContentLoaded', () => {

    // ===== SIDEBAR STATE INITIALIZATION =====
    const sidebar = document.getElementById('sidebar');
    const savedState = localStorage.getItem('sidebarState');

    document.documentElement.classList.remove('sidebar-preload-hide');

    if (sidebar) {
        if (savedState === 'hidden') {
            sidebar.classList.add('hide');
            sidebar.classList.remove('show');
        } else {
            sidebar.classList.remove('hide');
            sidebar.classList.add('show');
        }
    }

    const allSideMenu = document.querySelectorAll('#sidebar .side-menu.top li a');

    allSideMenu.forEach(item => {
        const li = item.parentElement;
        item.addEventListener('click', () => {
            allSideMenu.forEach(i => i.parentElement.classList.remove('active'));
            li.classList.add('active');
        });
    });

    const menuBar = document.querySelector('#content nav .bx.bx-menu');

    // Helper to sync body class
    function updateBodyClass() {
        if (sidebar && sidebar.classList.contains('hide')) {
            document.body.classList.add('sidebar-closed');
        } else {
            document.body.classList.remove('sidebar-closed');
        }
    }

    if (menuBar && sidebar) {
        menuBar.addEventListener('click', function () {
            sidebar.classList.toggle('hide');
            if (sidebar.classList.contains('hide')) {
                localStorage.setItem('sidebarState', 'hidden');
            } else {
                localStorage.setItem('sidebarState', 'visible');
            }
            updateBodyClass();
            adjustModalWidth();
        });
    }

    function adjustSidebar() {
        if (!sidebar) return;
        const isMobile = window.innerWidth <= 576;
        const savedState = localStorage.getItem('sidebarState');

        if (isMobile) {
            sidebar.classList.add('hide');
            sidebar.classList.remove('show');
        } else {
            if (savedState === 'hidden') {
                sidebar.classList.add('hide');
                sidebar.classList.remove('show');
            } else {
                sidebar.classList.remove('hide');
                sidebar.classList.add('show');
            }
        }
        updateBodyClass();
    }

    window.addEventListener('resize', adjustSidebar);
    adjustSidebar(); // Initial call

    const switchMode = document.getElementById('switch-mode');

    if (switchMode) {
        switchMode.addEventListener('change', function () {
            document.body.classList.toggle('dark', this.checked);
        });
    }

    const notification = document.querySelector('.notification');
    const notificationMenu = document.querySelector('.notification-menu');

    if (notification && notificationMenu) {
        notification.addEventListener('click', () => {
            notificationMenu.classList.toggle('show');
        });
    }

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
    });

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

        .lo_dropdown_menu li.active-fy-filter {
            background-color: #e8f5e9 !important;
            color: #2e7d32 !important;
            font-weight: bold;
            position: relative;
        }
        
        .lo_dropdown_menu li.active-fy-filter::after {
            content: '\\2714';
            position: absolute;
            right: 10px;
            color: #2e7d32;
        }

        /* --- ADDED: ROW ACTION MENU STYLES --- */
        .row-action-menu {
            position: absolute;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.15), 0 5px 10px rgba(0,0,0,0.05);
            z-index: 9999;
            display: none;
            width: 150px; /* Wider for "Renew Contract" text */
            border: 1px solid #e0e0e0;
            overflow: hidden;
            padding: 6px 0; 
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
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
        }

        .row-action-item:hover {
            background: #f1f8e9; 
            color: #2E7D32; 
        }
        
        .row-action-item i {
            font-size: 18px;
            color: #555;
        }
        
        .row-action-item:hover i {
            color: #2E7D32;
        }

        /* Container for the icon */
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

    // === FIX: RE-SELECT ACTIVE ROWS AFTER MERGING ===
    const activeRows = document.querySelectorAll('.table-row');

    const searchForm = document.querySelector('form');
    const searchInput = document.getElementById('searchInput');
    const noResultsMessage = document.getElementById('noResultsMessage');
    const currentCountSpan = document.getElementById('currentCount');
    const totalCountSpan = document.getElementById('totalCount');

    const totalRecords = activeRows.length;

    // --- STATE FOR DATE FILTER ---
    let activeDateFilter = { type: null, fy: null };

    // --- FISCAL YEAR HELPER FUNCTION (UPDATED FOR TOGGLE) ---
    function getFiscalYear(dateStr) {
        if (!dateStr || dateStr === '0000-00-00') return null;
        const isFyMode = document.body.classList.contains('fy-mode-active');
        const parts = dateStr.split('-');
        if (parts.length < 2) return null;
        const year = parseInt(parts[0]);
        const month = parseInt(parts[1]);

        if (!isFyMode) return String(year);

        if (dateStr < '2014-05-01') return String(year);

        // --- UPDATED: START YEAR LOGIC ---
        let fyStartYear;
        if (month <= 4) fyStartYear = year - 1; // Jan-April started last year
        else fyStartYear = year;                // May-Dec started this year

        return 'FY-' + String(fyStartYear).slice(-2);
    }

    // --- HELPER: FORMAT DATE TO "M d, Y" ---
    function formatDisplayDate(dateStr) {
        if (!dateStr || dateStr === '0000-00-00') return 'N/A';
        const date = new Date(dateStr + 'T00:00:00');
        if (isNaN(date)) return 'N/A';

        const options = { month: 'short', day: '2-digit', year: 'numeric' };
        return date.toLocaleDateString('en-US', options);
    }

    // --- HELPER: GET PRIORITY RECORD ---
    function getPriorityRecord(records) {
        const statusPriority = {
            'Near Expiration': 1,
            'Expired': 2,
            'Active': 3
        };

        const sorted = records.sort((a, b) => {
            const prioA = statusPriority[a.lease_status] || 99;
            const prioB = statusPriority[b.lease_status] || 99;

            if (prioA !== prioB) return prioA - prioB;

            const dateA = new Date(a.expiry_date || '9999-12-31');
            const dateB = new Date(b.expiry_date || '9999-12-31');
            return dateA - dateB;
        });

        return sorted[0];
    }

    // --- HELPER: CALCULATE DAYS LEFT ---
    function getDaysLeft(dateStr) {
        if (!dateStr || dateStr === '0000-00-00') return NaN;

        const expiry = new Date(dateStr + 'T00:00:00');
        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const diffTime = expiry - today;
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        return diffDays;
    }

    // --- HELPER: FORMAT DAYS TEXT (UPDATED) ---
    function formatDaysText(days) {
        if (isNaN(days)) return 'N/A';

        const isPast = days < 0;
        const absDays = Math.abs(days);
        let text = "";

        if (absDays >= 365) {
            const years = Math.floor(absDays / 365);
            const dayRemainder = absDays % 365;

            text += years + " year" + (years > 1 ? "s" : "");
            if (dayRemainder > 0) {
                text += " " + dayRemainder + " day" + (dayRemainder > 1 ? "s" : "");
            }
        } else {
            text = absDays + " day" + (absDays !== 1 ? "s" : "");
        }

        if (isPast) {
            text += " ago";
        }

        return text;
    }

    // --- HELPER: UPDATE ROW DISPLAY (STATUS + DATE + DAYS LEFT) ---
    function updateRowDisplay(row, records) {
        if (!records || records.length === 0) return;
        const bestRecord = getPriorityRecord(records);
        if (!bestRecord) return;

        // 1. Update Status
        const statusElement = row.querySelector('td:nth-child(2) p');
        if (statusElement) {
            const newStatus = bestRecord.lease_status;
            statusElement.textContent = newStatus;
            const newClass = newStatus.toLowerCase().replace(/[\s-]/g, '');
            statusElement.classList.remove('nearexpiration', 'expired', 'active');
            statusElement.classList.add(newClass);
        }

        // 2. Update Expiration Date
        const expiryElement = row.querySelector('td:nth-child(3) p');
        if (expiryElement) {
            expiryElement.textContent = formatDisplayDate(bestRecord.expiry_date);
        }

        // 3. UPDATE DAYS LEFT COLUMN
        const daysLeftElement = row.querySelector('td:nth-child(4) .days-left-badge');
        if (daysLeftElement) {
            const days = getDaysLeft(bestRecord.expiry_date);
            const text = formatDaysText(days);

            let className = 'days-left-badge';
            if (!isNaN(days)) {
                if (days < 0) className += ' days-expired';
                else if (days < 90) className += ' days-critical';
                else if (days < 180) className += ' days-warning';
                else className += ' days-ok';
            }

            daysLeftElement.textContent = text;
            daysLeftElement.className = className;
        }
    }

    // --- HELPER: SORT TABLE ROWS (UPDATED LOGIC) ---
    function sortTable() {
        const tbody = document.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('.table-row'));

        rows.sort((a, b) => {
            // 1. Determine the primary record for each row (respecting active filters)
            const recordsA = JSON.parse(a.dataset.allRecords || '[]');
            const recordsB = JSON.parse(b.dataset.allRecords || '[]');

            // If a date filter is active, we must sort based on the records that match the filter
            let relevantRecordsA = recordsA;
            let relevantRecordsB = recordsB;

            if (activeDateFilter.type) {
                relevantRecordsA = recordsA.filter(rec => {
                    let dateValue = '';
                    if (activeDateFilter.type === 'start') dateValue = rec.start_date;
                    else if (activeDateFilter.type === 'paid') dateValue = rec.paid_up_date;
                    else if (activeDateFilter.type === 'expiry') dateValue = rec.expiry_date;
                    return getFiscalYear(dateValue) === activeDateFilter.fy;
                });
                relevantRecordsB = recordsB.filter(rec => {
                    let dateValue = '';
                    if (activeDateFilter.type === 'start') dateValue = rec.start_date;
                    else if (activeDateFilter.type === 'paid') dateValue = rec.paid_up_date;
                    else if (activeDateFilter.type === 'expiry') dateValue = rec.expiry_date;
                    return getFiscalYear(dateValue) === activeDateFilter.fy;
                });
            }

            // Get the single best record to represent the row
            const bestA = getPriorityRecord(relevantRecordsA);
            const bestB = getPriorityRecord(relevantRecordsB);

            // Fallback if no records (push to bottom)
            if (!bestA && !bestB) return 0;
            if (!bestA) return 1;
            if (!bestB) return -1;

            // 2. Calculate Days Left
            const daysA = getDaysLeft(bestA.expiry_date);
            const daysB = getDaysLeft(bestB.expiry_date);

            // Handle Invalid Dates (push to bottom)
            if (isNaN(daysA) && isNaN(daysB)) return 0;
            if (isNaN(daysA)) return 1;
            if (isNaN(daysB)) return -1;

            // 3. Determine if Expired
            const isExpiredA = daysA < 0;
            const isExpiredB = daysB < 0;

            // Priority 1: Active/Near Expiration comes BEFORE Expired
            if (!isExpiredA && isExpiredB) return -1;
            if (isExpiredA && !isExpiredB) return 1;

            // Priority 2: Sort by Days Left
            if (!isExpiredA && !isExpiredB) {
                // Both Active: Sort ASCENDING (1 day left, 5 days left, 100 days left)
                return daysA - daysB;
            } else {
                // Both Expired: Sort DESCENDING (Expired yesterday (-1) > Expired last week (-10))
                // Logic: -1 is greater than -10. We want -1 first.
                return daysB - daysA;
            }
        });

        rows.forEach(row => tbody.appendChild(row));
    }

    // Helper to update the H3 header
    function updateExpiryHeader(count, label) {
        const header = document.getElementById('expiryHeader');
        if (header) {
            // Use innerHTML to include a span for styling the count
            header.innerHTML = label + ': <span style="background-color: #FFCDD2; border-radius: 7px; color: #2E7D32; padding: 0px 5px;">' + count + '</span>';
        }
    }

    // --- UPDATED FILTER BY FUNCTION ---
    window.filterBy = function (type, fy) {
        activeDateFilter = { type, fy };
        const filterBtn = document.querySelector('.f_lo');
        if (!filterBtn.classList.contains('is-filtering')) filterBtn.classList.add('is-filtering');

        document.querySelectorAll('.lo_dropdown_menu li.active-fy-filter').forEach(el => el.classList.remove('active-fy-filter'));
        const allMenuItems = document.querySelectorAll('.lo_dropdown_menu li');
        allMenuItems.forEach(li => {
            if (li.textContent.trim() === fy) li.classList.add('active-fy-filter');
        });

        const displayElement = document.getElementById('activeFilterDisplay');
        if (displayElement) displayElement.textContent = fy;

        let visibleCount = 0;
        let contractCount = 0; // COUNT INDIVIDUAL CONTRACTS

        activeRows.forEach(row => {
            const records = JSON.parse(row.dataset.allRecords || '[]');
            const matchingRecords = records.filter(record => {
                let dateValue = '';
                if (type === 'start') dateValue = record.start_date;
                else if (type === 'paid') dateValue = record.paid_up_date;
                else if (type === 'expiry') dateValue = record.expiry_date;
                const recordFy = getFiscalYear(dateValue);
                return recordFy === fy;
            });

            if (matchingRecords.length > 0) {
                row.style.display = "";
                visibleCount++;

                // --- FIX: Count unique parcels to match PHP "COUNT(DISTINCT lot_no, field_no)" logic ---
                const uniqueParcels = new Set();
                matchingRecords.forEach(rec => {
                    // Create a unique key for Lot + Field to ensure distinct counting
                    uniqueParcels.add((rec.lot_no || '') + '|' + (rec.field_no || ''));
                });
                contractCount += uniqueParcels.size;
                // -------------------------------------------------------------------------

                updateRowDisplay(row, matchingRecords);
            } else {
                row.style.display = "none";
            }
        });

        sortTable();
        updateResultsCounter(visibleCount);
        toggleNoResultsMessage(visibleCount === 0);

        if (filterBtn.classList.contains('on')) changeClass(filterBtn);

        // --- UPDATE H3 HEADER ---
        const today = new Date();
        const year = today.getFullYear();
        const month = today.getMonth() + 1;
        // --- UPDATED: START YEAR LOGIC ---
        let currentFY;
        if (month >= 5) currentFY = 'FY-' + String(year).slice(-2);      // May-Dec started this year
        else currentFY = 'FY-' + String(year - 1).slice(-2);            // Jan-April started last year

        let labelText = "";
        if (fy === currentFY) {
            labelText = "Expiring lots this " + fy;
        } else if (fy > currentFY) {
            labelText = "Expiring lots in " + fy;
        } else {
            labelText = "Expired lots on " + fy;
        }

        // PREVENT OVERWRITING THE PHP COUNT ON INITIAL LOAD
        if (typeof window.initialLoadDone === 'undefined') {
            window.initialLoadDone = true; // Mark initial load as complete
        } else {
            updateExpiryHeader(contractCount, labelText);
        }
    };

    // === TOGGLE YEAR MODE FUNCTION ===
    window.toggleYearMode = function (isFiscalYear) {
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

        if (isFiscalYear) document.body.classList.add('fy-mode-active');
        else document.body.classList.remove('fy-mode-active');
    }

    // --- UPDATED SHOW ALL ROWS FUNCTION ---
    window.showAllRows = function () {
        activeDateFilter = { type: null, fy: null };
        resetTable();
        const filterBtn = document.querySelector('.f_lo');
        filterBtn.classList.remove('is-filtering');
        document.querySelectorAll('.lo_dropdown_menu li.active-fy-filter').forEach(el => el.classList.remove('active-fy-filter'));
        const displayElement = document.getElementById('activeFilterDisplay');
        if (displayElement) displayElement.textContent = '';
        if (filterBtn.classList.contains('on')) changeClass(filterBtn);

        // Update header to show total count
        let totalContracts = 0;
        activeRows.forEach(r => {
            totalContracts += JSON.parse(r.dataset.allRecords || '[]').length;
        });
        updateExpiryHeader(totalContracts, 'Total Contracts');
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
            const statusEl = row.querySelector('td:nth-child(2) p');
            const expiryEl = row.querySelector('td:nth-child(3) p');

            if (statusEl && statusEl.hasAttribute('data-original-text')) {
                statusEl.textContent = statusEl.getAttribute('data-original-text');
            }
            if (statusEl && statusEl.hasAttribute('data-original-class')) {
                statusEl.className = 'status ' + statusEl.getAttribute('data-original-class');
            }
            if (expiryEl && expiryEl.hasAttribute('data-original-text')) {
                expiryEl.textContent = expiryEl.getAttribute('data-original-text');
            }

            const allRecs = JSON.parse(row.dataset.allRecords || '[]');
            updateRowDisplay(row, allRecs);
        });

        sortTable();
        updateResultsCounter(visibleCount);
        toggleNoResultsMessage(false);

        // Reset H3 Header
        let totalLots = 0;
        activeRows.forEach(r => {
            const allRecs = JSON.parse(r.dataset.allRecords || '[]');
            const uniqueLotsInRow = new Set();
            allRecs.forEach(rec => {
                // Count unique lots similar to the filter logic
                uniqueLotsInRow.add((rec.lot_no || '') + '|' + (rec.field_no || ''));
            });
            totalLots += uniqueLotsInRow.size;
        });
        updateExpiryHeader(totalLots, 'Total Lots');
    }

    updateResultsCounter(totalRecords);

    // === SEARCH LOGIC (FIX: Safe check for searchInput) ===
    if (searchInput) {
        searchInput.addEventListener('search', function () {
            if (this.value === '') resetTable();
        });

        if (searchForm) {
            searchForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const modal = document.getElementById('landOwnerModal');
                if (modal) modal.style.display = 'none';

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
                            rowData.party, allBarangays, allMunicipalities, rowData.landlist,
                            allLotNos, allFieldNos, allFieldSections, allCropTypes, allContractClasses
                        ];
                        return searchableFields.some(fieldText => {
                            // const exactMatchRegex = new RegExp(`\\b${term}\\b`, 'i'); COMMENTED THIS LINE BECAUSE SEARCH FIELD DON'T DISPLAY UPPERCASE RESULTS WHEN TYPING LOWERCASE
                            return fieldText.includes(term);
                        });
                    });

                    if (searchTerms.length === 0) {
                        if (activeDateFilter.type) {
                            const records = JSON.parse(row.dataset.allRecords || '[]');
                            const matchingRecords = records.filter(rec => {
                                let dateValue = '';
                                if (activeDateFilter.type === 'start') dateValue = rec.start_date;
                                else if (activeDateFilter.type === 'paid') dateValue = rec.paid_up_date;
                                else if (activeDateFilter.type === 'expiry') dateValue = rec.expiry_date;
                                return getFiscalYear(dateValue) === activeDateFilter.fy;
                            });
                            if (matchingRecords.length > 0) {
                                row.style.display = ""; visibleCount++;
                                updateRowDisplay(row, matchingRecords);
                            } else row.style.display = "none";
                        } else {
                            row.style.display = ""; visibleCount++;
                        }
                    } else {
                        if (isMatch) {
                            if (activeDateFilter.type) {
                                const records = JSON.parse(row.dataset.allRecords || '[]');
                                const matchingRecords = records.filter(rec => {
                                    let dateValue = '';
                                    if (activeDateFilter.type === 'start') dateValue = rec.start_date;
                                    else if (activeDateFilter.type === 'paid') dateValue = rec.paid_up_date;
                                    else if (activeDateFilter.type === 'expiry') dateValue = rec.expiry_date;
                                    return getFiscalYear(dateValue) === activeDateFilter.fy;
                                });
                                if (matchingRecords.length > 0) {
                                    row.style.display = ""; visibleCount++;
                                    updateRowDisplay(row, matchingRecords);
                                } else row.style.display = "none";
                            } else {
                                row.style.display = ""; visibleCount++;
                            }
                        } else row.style.display = "none";
                    }
                });

                sortTable();
                updateResultsCounter(visibleCount);
                if (searchTerms.length > 0 && visibleCount === 0) toggleNoResultsMessage(true);
                else toggleNoResultsMessage(false);
            });
        }
    } // End search check

    // ===== MODAL LOGIC =====
    const modal = document.getElementById('landOwnerModal');
    const modalName = document.getElementById('modalName');
    const modalCode = document.getElementById('modalCode');
    const overviewTab = document.getElementById('overview');
    const oldRecordsTab = document.getElementById('old_records');
    const oldBtn = document.getElementById('old_records_btn');

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

    // === FIX: ATTACH CLICK LISTENERS TO ACTIVE ROWS ===
    activeRows.forEach(row => {
        row.addEventListener('click', () => {
            let records = JSON.parse(row.dataset.allRecords);

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

            // Filter by search if active
            if (searchInput) {
                const currentSearchVal = searchInput.value.trim().toLowerCase();
                const searchTerms = currentSearchVal.split(/\s+/).filter(t => t !== "");

                if (searchTerms.length > 0) {
                    records = records.filter(rec => {
                        return searchTerms.some(term => {
                            const fieldsToCheck = [
                                String(rec.contracting_party || ''), String(rec.lot_no || ''),
                                String(rec.field_no || ''), String(rec.field_section || ''),
                                String(rec.barangay || ''), String(rec.municipality || ''),
                                String(rec.crop_type || ''), String(rec.contract_class || ''),
                                String(rec.contract_status || '')
                            ];
                            const regex = new RegExp(`\\b${term}\\b`, 'i');
                            return fieldsToCheck.some(field => regex.test(field));
                        });
                    });
                }
            }

            const today = new Date();
            today.setHours(0, 0, 0, 0);

            modal.style.display = 'flex';
            adjustModalWidth();

            document.querySelectorAll('.modal-tabs button').forEach(btn => btn.classList.remove('active'));
            document.getElementById('overview_btn').classList.add('active');
            document.querySelectorAll('.modal-tab-content, .oldrec-tab-content').forEach(content => content.classList.remove('active'));
            document.getElementById('overview').classList.add('active');

            modalName.textContent = row.dataset.name;

            overviewTab.innerHTML = '';
            oldRecordsTab.innerHTML = '';
            oldBtn.style.display = 'none';

            const formatRate = (num) => {
                const cleanNum = String(num).replace(/,/g, '');
                const value = parseFloat(cleanNum);
                if (isNaN(value)) return '0.00';
                return value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            };

            const formatArable = (num) => {
                const cleanNum = String(num).replace(/,/g, '');
                const value = parseFloat(cleanNum);
                if (isNaN(value)) return '0.0000';
                return value.toLocaleString('en-US', { minimumFractionDigits: 4, maximumFractionDigits: 4 });
            };

            const activeRecords = records.filter(rec => new Date(rec.expiry_date) >= today);
            const expiredRecords = records.filter(rec => new Date(rec.expiry_date) < today);

            const overviewBtn = document.getElementById('overview_btn');
            if (overviewBtn) {
                overviewBtn.textContent = `Overview (${activeRecords.length})`;
            }

            if (activeRecords.length === 0) {
                overviewTab.innerHTML = `<div style="text-align:center; padding:20px;"><div style="font-size:40px;">📄</div><h3>No Active Contract</h3><p>There are no current lease agreements recorded matching your criteria.</p></div>`;
            } else {

                // --- NEW LOGIC: GROUP BY CONTRACT ID ---
                const displayRowsMap = {};

                activeRecords.forEach(rec => {
                    // === FIXED: MATCH GROUPING LOGIC TO LAND_OWNERS (Use Contract ID) ===
                    const uniqueKey = (rec.contract_id || 'fallback_' + rec.id) + '_' + (rec.lot_no || '');

                    if (!displayRowsMap[uniqueKey]) {
                        displayRowsMap[uniqueKey] = {
                            id: rec.id,
                            cms_application_nos: [], // Keep array for user_exp specific logic
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
                            lot_no: rec.lot_no, // Essential for grouping display
                            contract_id: rec.contract_id,
                            total_arable: 0,
                            accounts: [],
                            sig: uniqueKey // STORE THE SIGNATURE FOR HTML
                        };
                    }

                    // Collect CMS Nos (user_exp logic)
                    if (rec.cms_application_no && !displayRowsMap[uniqueKey].cms_application_nos.includes(rec.cms_application_no)) {
                        displayRowsMap[uniqueKey].cms_application_nos.push(rec.cms_application_no);
                    }

                    displayRowsMap[uniqueKey].total_arable += parseFloat(String(rec.contracted_arable).replace(/,/g, '')) || 0;
                    displayRowsMap[uniqueKey].accounts.push({ name: rec.group_acc, arable: rec.contracted_arable });
                });

                let displayRows = Object.values(displayRowsMap);

                // === FIX: CALCULATE ROWSPAN MAP (Based on Contract ID) ===
                const contractIdCountMap = {};
                displayRows.forEach(r => {
                    const key = r.contract_id || r.id;
                    contractIdCountMap[key] = (contractIdCountMap[key] || 0) + 1;
                });

                // SORT (Keep existing sort logic or standard sort)
                displayRows.sort((a, b) => {
                    if (a.start_date < b.start_date) return -1;
                    if (a.start_date > b.start_date) return 1;
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
                    <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Barangay', 6)}</th>
                    <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Municipality', 7)}</th>
                    <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Crop Type', 8)}</th>
                    <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Contract Class', 9)}</th>
                    <th style="border:1px solid #007A3D; padding:8px;">Rate</th>
                    <th style="border:1px solid #007A3D; padding:8px;">Arable (HAS)</th>
                    <th style="border:1px solid #007A3D; padding:8px;">Group Accounts</th>
                    <th style="border:1px solid #007A3D; padding:8px;">Start Date</th>
                    <th style="border:1px solid #007A3D; padding:8px;">Expiry Date</th>
                    <th style="border:1px solid #007A3D; padding:8px;">Paid Up Date</th>
                    </tr>
                    </thead>
                    <tbody>`;


                let currentContractId = null;
                let colorClass = '';
                let contractCounter = 0;
                let rowsHTML = '';

                displayRows.forEach((row) => {
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
                    ${window.CAN_EDIT_EXPIRY ? `<i class='bx bx-dots-vertical-rounded row-action-icon' style="font-size: 18px; cursor: pointer;"></i>` : ''}
                </td>`;
                    }

                    rowsHTML += `
                    <tr class="${colorClass}" 
                        data-contract-sig="${row.sig}"
                        data-contract-id="${row.contract_id || ''}"
                        data-start-date="${row.start_date || ''}"
                        data-expiry-date="${row.expiry_date || ''}"
                        data-paid-up-date="${row.paid_up_date || ''}"
                        style="background:#f9f9f9; font-size: 14px;" 
                        data-id="${row.id || ''}"
                        data-lot="${row.lot_no}"
                        data-name="${modalName.textContent}"
                        data-vendor="${row.vendor || ''}" 
                        data-col-0="${row.cms_application_nos.join(', ')}" 
                        data-col-1="${row.lot_no}" 
                        data-col-2="${row.lease_status}" 
                        data-col-3="${row.contract_status}" 
                        data-col-4="${row.field_no}" 
                        data-col-5="${row.field_section || ''}"  
                        data-col-6="${row.barangay || ''}" 
                        data-col-7="${row.municipality || ''}" 
                        data-col-8="${row.crop_type}"
                        data-col-9="${row.contract_class}">
                    <td style="border:1px solid #007A3D; padding:5px;">${row.cms_application_nos.join(', ')}</td>
                    <td style="border:1px solid #007A3D; padding:5px;">${row.lot_no}</td>
                    <td style="border:1px solid #007A3D; padding:5px;">${row.lease_status}</td>
                    <td style="border:1px solid #007A3D; padding:5px;">${row.contract_status}</td>
                    <td style="border:1px solid #007A3D; padding:5px;">${row.field_no}</td>
                    <td style="border:1px solid #007A3D; padding:5px;">${row.field_section || 'N/A'}</td>
                    <td style="border:1px solid #007A3D; padding:5px;">${row.barangay || 'N/A'}</td>
                    <td style="border:1px solid #007A3D; padding:5px;">${row.municipality || 'N/A'}</td>
                    <td style="border:1px solid #007A3D; padding:5px;">${row.crop_type}</td>
                    <td style="border:1px solid #007A3D; padding:5px;">${row.contract_class}</td>
                    <td style="border:1px solid #007A3D; padding:5px;">₱${formatRate(row.rate)}</td>
                    <td style="border:1px solid #007A3D; padding:5px;">${formatArable(row.total_arable)}</td>
                    <td style="border:1px solid #007A3D; padding:5px;">${groupAccountsHTML}</td>
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
                    makeTableRowsClickable('overview-table', 'landowners_tabs/overview.php?lot=', modalName.textContent);
                    if (showFilters) initializeExcelFilters('overview-table');
                    setupActionCellGroupHover('overview-table'); // ADD THIS
                }, 0);
            }

            if (expiredRecords.length > 0) {
                oldBtn.style.display = 'block';
                oldBtn.textContent = `Old Records (${expiredRecords.length})`;

                // --- NEW LOGIC: GROUP BY CONTRACT ID (SAME AS ABOVE) ---
                const displayRowsMap = {};

                expiredRecords.forEach(rec => {
                    // === FIXED: MATCH GROUPING LOGIC TO LAND_OWNERS (Use Contract ID) ===
                    const uniqueKey = (rec.contract_id || 'fallback_' + rec.id) + '_' + (rec.lot_no || '');

                    if (!displayRowsMap[uniqueKey]) {
                        displayRowsMap[uniqueKey] = {
                            id: rec.id,
                            cms_application_nos: [],
                            lease_status: rec.lease_status, contract_status: rec.contract_status, field_no: rec.field_no, field_section: rec.field_section,
                            barangay: rec.barangay, municipality: rec.municipality, crop_type: rec.crop_type, contract_class: rec.contract_class,
                            rate: rec.rate, start_date: rec.start_date, expiry_date: rec.expiry_date, paid_up_date: rec.paid_up_date, total_arable: 0, accounts: [],
                            lot_no: rec.lot_no,
                            contract_id: rec.contract_id,
                            sig: uniqueKey // STORE THE SIGNATURE FOR HTML
                        };
                    }

                    if (rec.cms_application_no && !displayRowsMap[uniqueKey].cms_application_nos.includes(rec.cms_application_no)) {
                        displayRowsMap[uniqueKey].cms_application_nos.push(rec.cms_application_no);
                    }

                    displayRowsMap[uniqueKey].total_arable += parseFloat(String(rec.contracted_arable).replace(/,/g, '')) || 0;
                    displayRowsMap[uniqueKey].accounts.push({ name: rec.group_acc, arable: rec.contracted_arable });
                });

                let displayRows = Object.values(displayRowsMap);
                displayRows.sort((a, b) => {
                    if (a.start_date < b.start_date) return -1;
                    if (a.start_date > b.start_date) return 1;
                    return a.lot_no.localeCompare(b.lot_no);
                });

                const rowCount = displayRows.length;
                const showFilters = rowCount >= 2;
                const createHeader = (text, colIndex) => (showFilters ? `<div class="excel-header-container" data-col-index="${colIndex}">${text} <i class='bx bx-chevron-down excel-filter-icon'></i></div>` : text);

                let oldTableHTML = `<table id="old-records-table" style="width:100%; border-collapse: collapse; margin-top:20px; font-family: Arial, sans-serif; border: 1px solid #007A3D; border-radius:8px; overflow:hidden;">
                    <thead style="background:#78AB46; color:#fff; font-size: 12px;"><tr>
                    <th style="border:1px solid #007A3D; padding:8px;">${createHeader('CMS Application No.', 0)}</th>
                    <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Lot No', 1)}</th>
                    <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Lease Status', 2)}</th>
                    <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Contract Status', 3)}</th>
                    <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Field No', 4)}</th>
                    <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Field Section', 5)}</th>
                    <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Barangay', 6)}</th>
                    <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Municipality', 7)}</th>
                    <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Crop Type', 8)}</th>
                    <th style="border:1px solid #007A3D; padding:8px;">${createHeader('Contract Class', 9)}</th>
                    <th style="border:1px solid #007A3D; padding:8px;">Rate</th>
                    <th style="border:1px solid #007A3D; padding:8px;">Arable (HAS)</th>
                    <th style="border:1px solid #007A3D; padding:8px;">Group Accounts</th>
                    <th style="border:1px solid #007A3D; padding:8px;">Start Date</th>
                    <th style="border:1px solid #007A3D; padding:8px;">Expiry Date</th>
                    <th style="border:1px solid #007A3D; padding:8px;">Paid Up Date</th></tr></thead><tbody>`;

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
                    let groupAccountsHTML = '<ul style="padding-left:15px; margin:0;">' + row.accounts.map(acc => `<li>${acc.name}<strong>: ${formatArable(acc.arable)} HAS</strong></li>`).join('') + `</ul>`;

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

                    let actionCellHTML = '';
                    if (isHeaderRow) {
                        const key = row.contract_id || row.id;
                        const rowspan = contractIdCountMap[key];
                        actionCellHTML = `
                        <td class="action-cell" rowspan="${rowspan}" style="vertical-align: middle; text-align: center;">
                            ${window.CAN_EDIT_EXPIRY ? `<i class='bx bx-dots-vertical-rounded row-action-icon' style="font-size: 18px; cursor: pointer;"></i>` : ''}
                        </td>`;
                    }

                    oldRowsHTML += `
                    <tr class="${colorClass}"
                        data-contract-sig="${row.sig}"
                        data-contract-id="${row.contract_id || ''}"
                        data-start-date="${row.start_date || ''}"
                        data-expiry-date="${row.expiry_date || ''}"
                        data-paid-up-date="${row.paid_up_date || ''}"
                        style="background:#f9f9f9; font-size: 14px;" 
                        data-id="${row.id || ''}"
                        data-lot="${row.lot_no}"
                        data-name="${modalName.textContent}"
                        data-vendor="${row.vendor || ''}" 
                        data-col-0="${row.cms_application_nos.join(', ')}" 
                        data-col-1="${row.lot_no}" 
                        data-col-2="${row.lease_status}" 
                        data-col-3="${row.contract_status}" 
                        data-col-4="${row.field_no}" 
                        data-col-5="${row.field_section || ''}"  
                        data-col-6="${row.barangay || ''}" 
                        data-col-7="${row.municipality || ''}" 
                        data-col-8="${row.crop_type}"
                        data-col-9="${row.contract_class}">
                    <td style="border:1px solid #007A3D; padding:5px;">${row.cms_application_nos.join(', ')}</td>
                    <td style="border:1px solid #007A3D; padding:5px;">${row.lot_no}</td>
                    <td style="border:1px solid #007A3D; padding:5px;">${row.lease_status}</td>
                    <td style="border:1px solid #007A3D; padding:5px;">${row.contract_status}</td>
                    <td style="border:1px solid #007A3D; padding:5px;">${row.field_no}</td>
                    <td style="border:1px solid #007A3D; padding:5px;">${row.field_section || 'N/A'}</td>
                    <td style="border:1px solid #007A3D; padding:5px;">${row.barangay || 'N/A'}</td>
                    <td style="border:1px solid #007A3D; padding:5px;">${row.municipality || 'N/A'}</td>
                    <td style="border:1px solid #007A3D; padding:5px;">${row.crop_type}</td>
                    <td style="border:1px solid #007A3D; padding:5px;">${row.contract_class}</td>
                    <td style="border:1px solid #007A3D; padding:5px;">₱${formatRate(row.rate)}</td>
                    <td style="border:1px solid #007A3D; padding:5px;">${formatArable(row.total_arable)}</td>
                    <td style="border:1px solid #007A3D; padding:5px;">${groupAccountsHTML}</td>
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
                    makeTableRowsClickable('old-records-table', 'landowners_tabs/old_records.php?lot=', modalName.textContent);
                    if (showFilters) initializeExcelFilters('old-records-table');
                    setupActionCellGroupHover('old-records-table'); // ADD THIS
                }, 0);
            }
        });
    });

    // =============================================================
    // === DEFAULT FILTER LOGIC: CURRENT FISCAL YEAR ON LOAD =====
    // =============================================================
    const fyToggle = document.getElementById('fyModeToggle');
    if (fyToggle) fyToggle.checked = true;
    document.body.classList.add('fy-mode-active');

    document.querySelectorAll('.submenu').forEach(submenu => {
        const fyList = submenu.querySelector('.fy-list');
        const cyList = submenu.querySelector('.cy-list');
        if (fyList && cyList) { fyList.style.display = 'grid'; cyList.style.display = 'none'; }
    });

    const todayDate = new Date();
    const currentYear = todayDate.getFullYear();
    const currentMonth = todayDate.getMonth() + 1;
    let currentFY;

    // --- UPDATED: START YEAR LOGIC ---
    if (currentMonth >= 5) {
        // May to Dec: Fiscal Year started THIS year
        currentFY = 'FY-' + String(currentYear).slice(-2);
    } else {
        // Jan to April: Fiscal Year started PREVIOUS year
        currentFY = 'FY-' + String(currentYear - 1).slice(-2);
    }

    setTimeout(() => {
        // 1. Force the header to display the exact PHP query count
        if (typeof window.phpExpiringCount !== 'undefined') {
            updateExpiryHeader(window.phpExpiringCount, "Expiring lots this " + currentFY);
        }

        // 2. Still run the filter so the table sorts/updates visually
        window.filterBy('expiry', currentFY);
    }, 100);


}); //EventListener END


// Helper function to make table rows clickable and handle action menus
function makeTableRowsClickable(tableId, urlPrefix, contractingPartyName) {
    const table = document.getElementById(tableId);
    if (!table) return;

    // 1. Create the action menu (singleton pattern to avoid duplicates)
    let actionMenu = document.querySelector('.row-action-menu');
    if (!actionMenu) {
        actionMenu = document.createElement('div');
        actionMenu.className = 'row-action-menu';
        // SPECIFIC ACTION FOR EXPIRY TRACKER: "Renew Contract"
        actionMenu.innerHTML = `
            <div class="row-action-item" data-action="renew">
                <i class='bx bx-refresh'></i> Renew Contract
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

            // In user_exp.js, Lot No is in data-col-1
            const lotNumber = row.getAttribute('data-col-1');
            const finalLotNumber = lotNumber || (row.querySelector('td:nth-child(2)')?.textContent.trim() || '');

            let url = `${urlPrefix}${encodeURIComponent(finalLotNumber)}`;
            if (contractingPartyName) {
                url += `&name=${encodeURIComponent(contractingPartyName.trim())}`;
            }
            window.open(url, '_blank');
        });
    });

    // 3. Handle ACTION CELL CLICK (Show Menu)
    table.querySelectorAll('.action-cell').forEach(cell => {

        // PERMISSION CHECK: If no edit permission, hide icon and disable click
        if (!window.CAN_EDIT_EXPIRY) {
            const icon = cell.querySelector('.row-action-icon');
            if (icon) icon.style.display = 'none';
            return;
        }

        cell.addEventListener('click', (e) => {
            e.stopPropagation();

            document.querySelectorAll('.row-action-menu.show').forEach(m => m.classList.remove('show'));

            const icon = cell.querySelector('.row-action-icon');
            const rect = icon.getBoundingClientRect();

            actionMenu.style.top = `${rect.bottom + window.scrollY}px`;
            actionMenu.style.left = `${rect.right - 120}px`;

            const row = icon.closest('tr');
            const lotNumber = row.getAttribute('data-col-1');

            actionMenu.dataset.lot = lotNumber;
            actionMenu.dataset.name = contractingPartyName;

            const contractIdVal = row.dataset.contractId ? row.dataset.contractId.trim() : '';
            actionMenu.dataset.contractId = contractIdVal;

            actionMenu.classList.add('show');
        });
    });

    // 4. Handle MENU ITEM CLICK (Renew Action)
    if (!actionMenu.hasListener) {
        actionMenu.addEventListener('click', (e) => {
            const item = e.target.closest('.row-action-item');
            if (!item) return;

            const action = item.dataset.action;
            const lot = actionMenu.dataset.lot;
            const name = actionMenu.dataset.name;

            const contractId = actionMenu.dataset.contractId;

            // RENEW ACTION
            if (action === 'renew') {
                // Changed from name/lot to contract_id
                const renewUrl = `landowners_tabs/renew_contract.php?contract_id=${contractId}`;
                window.open(renewUrl, '_blank');
            }

            actionMenu.classList.remove('show');
        });

        // === ADDED: Close menu when clicking anywhere else ===
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.row-action-menu') && !e.target.closest('.action-cell')) {
                actionMenu.classList.remove('show');
            }
        });

        // === ADDED: Close menu when pressing Escape ===
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && actionMenu.classList.contains('show')) {
                actionMenu.classList.remove('show');
            }
        });

        actionMenu.hasListener = true;
    }
}

function adjustModalWidth() {
    const sidebar = document.getElementById('sidebar');
    const modalContent = document.querySelector('.modal');
    if (!sidebar || !modalContent) return;

    const isLaptopView = window.matchMedia("(min-width: 1025px) and (max-width: 1366px)").matches;

    if (isLaptopView) {
        if (sidebar.classList.contains('hide')) {
            modalContent.style.width = '94%';
            modalContent.style.maxWidth = '94%';
        } else {
            modalContent.style.width = '82%';
            modalContent.style.maxWidth = '92%';
            modalContent.style.height = '87%';
        }
    } else {
        if (sidebar.classList.contains('hide')) {
            modalContent.style.width = '95%';
            modalContent.style.maxWidth = '95%';
        } else {
            modalContent.style.width = '86%';
            modalContent.style.maxWidth = '95%';
        }
    }
}

// ===== EXCEL FILTER LOGIC =====
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
        dropdown.innerHTML = `<div class="excel-top-actions" style="display: flex; justify-content: space-between; margin-bottom: 8px; border-bottom: 1px solid #eee; padding-bottom: 8px;">
            <button class="excel-btn clear" style="width: 48%; color: #d32f2f; border-color: #ffcdd2;">Clear</button>
            <button class="excel-btn select-all" style="width: 48%;">Select All</button></div>
            <input type="text" class="excel-search-input" placeholder="Search..."><div class="excel-list"></div>
            <div class="excel-actions" style="margin-top: 10px; display: flex; justify-content: space-between; gap: 10px;">
            <button class="excel-btn cancel" style="width: 48%;">Cancel</button><button class="excel-btn done" style="width: 48%;">Done</button></div>`;
        document.body.appendChild(dropdown);
    }

    headers.forEach(header => {
        header.addEventListener('click', (e) => {
            e.stopPropagation(); e.preventDefault();
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
            table.querySelectorAll('tbody tr').forEach(row => { const val = row.getAttribute(`data-col-${colIndex}`); if (val) uniqueValues.add(val); });
            let hasBlanks = false;
            table.querySelectorAll('tbody tr').forEach(row => { const val = row.getAttribute(`data-col-${colIndex}`); if (!val) hasBlanks = true; });
            if (hasBlanks) uniqueValues.add('(Blanks)');
            const activeFilters = excelFilterState[colIndex];
            listContainer.innerHTML = '';
            const sortedValues = [...uniqueValues].sort((a, b) => { if (a === '(Blanks)') return -1; if (b === '(Blanks)') return 1; return a.localeCompare(b); });
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
        if (item) { const checkbox = item.querySelector('input'); checkbox.checked = !checkbox.checked; }
    });
    dropdown.querySelector('.excel-btn.clear').addEventListener('click', (e) => { e.stopPropagation(); dropdown.querySelectorAll('.excel-item input').forEach(cb => cb.checked = false); });
    dropdown.querySelector('.excel-btn.select-all').addEventListener('click', (e) => { e.stopPropagation(); dropdown.querySelectorAll('.excel-item input').forEach(cb => cb.checked = true); });
    dropdown.querySelector('.excel-btn.cancel').addEventListener('click', (e) => { e.stopPropagation(); dropdown.classList.remove('active'); });
    dropdown.querySelector('.excel-btn.done').addEventListener('click', (e) => {
        e.stopPropagation();
        const colIndex = dropdown.dataset.currentCol;
        const targetTableId = dropdown.dataset.targetTable || tableId;
        const checkedValues = [];
        dropdown.querySelectorAll('.excel-item input:checked').forEach(cb => { checkedValues.push(cb.value); });
        const totalItems = dropdown.querySelectorAll('.excel-item').length;
        if (checkedValues.length === totalItems) delete excelFilterState[colIndex];
        else excelFilterState[colIndex] = checkedValues;
        applyFilters(targetTableId);
        updateFilterHighlight(targetTableId);
        dropdown.classList.remove('active');
    });
    document.addEventListener('click', (e) => { if (!dropdown.contains(e.target) && !e.target.closest('.excel-header-container')) dropdown.classList.remove('active'); });
}

function updateFilterHighlight(tableId) {
    const table = document.getElementById(tableId);
    if (!table) return;
    const headers = table.querySelectorAll('.excel-header-container');
    headers.forEach(header => {
        const colIndex = header.dataset.colIndex;
        if (excelFilterState[colIndex]) header.classList.add('is-filtered');
        else header.classList.remove('is-filtered');
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
            if (!rowValue) { if (!isBlankAllowed) show = false; }
            else { if (!isValueAllowed) show = false; }
            if (!show) break;
        }
        row.style.display = show ? '' : 'none';
    });
}

// CLOSES THE MODAL WHEN BACKGROUND OR ESC KEY IS CLICKED
const modalOverlay = document.getElementById('landOwnerModal');
if (modalOverlay) modalOverlay.addEventListener('click', (event) => { if (event.target === modalOverlay) modalOverlay.style.display = 'none'; });
document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && modalOverlay && modalOverlay.style.display === 'flex') modalOverlay.style.display = 'none'; });

const modalCloseBtn = document.querySelector('.modal-close');
if (modalCloseBtn) modalCloseBtn.addEventListener('click', () => { const modal = document.getElementById('landOwnerModal'); if (modal) modal.style.display = 'none'; });

// Unified Tab Logic
document.querySelectorAll('.modal-tabs button').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.modal-tabs button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        document.querySelectorAll('.modal-tab-content, .oldrec-tab-content').forEach(tab => { tab.classList.remove('active'); });
        const targetTab = document.getElementById(btn.dataset.tab);
        if (targetTab) targetTab.classList.add('active');
    });
});

// FILTER Main Page
function changeClass(e) { e.classList.toggle('off'); e.classList.toggle('on'); }
document.querySelectorAll('.f_lo').forEach(span => span.addEventListener('click', function () { changeClass(this); }));

// === CLOSE DROPDOWN WHEN CLICKING OUTSIDE ===
document.addEventListener('click', function (e) {
    const filterBtn = document.querySelector('.f_lo');
    const dropdownMenu = document.querySelector('.lo_dropdown_menu');
    if (!e.target.closest('.f_lo') && !e.target.closest('.lo_dropdown_menu')) {
        if (filterBtn && filterBtn.classList.contains('on')) { filterBtn.classList.remove('on'); filterBtn.classList.add('off'); }
        if (dropdownMenu) dropdownMenu.querySelectorAll('.submenu').forEach(sub => sub.style.display = 'none');
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
        if (parentRect.top + submenuHeight > window.innerHeight) submenu.style.top = (parentRect.bottom - submenuHeight) + 'px';
        else submenu.style.top = parentRect.top + 'px';
    });
    item.addEventListener('mouseleave', function () { const submenu = this.querySelector('.submenu'); if (submenu) submenu.style.display = 'none'; });
});

const loDropdownMenu = document.querySelector('.lo_dropdown_menu');
if (loDropdownMenu) loDropdownMenu.addEventListener('scroll', () => { document.querySelectorAll('.submenu').forEach(sub => sub.style.display = 'none'); });


// === SETUP ACTION CELL GROUP HOVER ===
// When hovering the ellipsis, highlight the ENTIRE contract group
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