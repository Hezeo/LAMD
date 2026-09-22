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
            if (sidebar.classList.contains('hide')) {
                localStorage.setItem('sidebarState', 'hidden');
            } else {
                localStorage.setItem('sidebarState', 'visible');
            }
        });
    }

    function adjustSidebar() {
        if (!sidebar) return;
        const isMobile = window.innerWidth <= 576;
        if (isMobile) {
            sidebar.classList.add('hide');
            sidebar.classList.remove('show');
        }
    }
    window.addEventListener('resize', adjustSidebar);

    // Profile menu toggle
    const profile = document.querySelector('.profile');
    const profileMenu = document.querySelector('.profile-menu');
    if (profile && profileMenu) {
        profile.addEventListener('click', () => profileMenu.classList.toggle('show'));
    }
    window.addEventListener('click', (e) => {
        if (!e.target.closest('.profile')) profileMenu?.classList.remove('show');
    });

    // ===== REPORT GENERATION LOGIC =====

    let mainChartInstance = null;
    let currentReportType = null;

    // Use the data passed from PHP
    const rawDataSet = SERVER_DATA || [];

    // --- NEW: DYNAMIC YEAR DROPDOWN LOGIC ---
    function updateYearOptions() {
        const reportType = document.getElementById('reportType').value;
        const filterYearSelect = document.getElementById('filterYear');

        // Clear existing options except "All Time"
        filterYearSelect.innerHTML = '<option value="all">All Time</option>';

        const years = new Set();

        // Determine which date field to use based on report type
        rawDataSet.forEach(item => {
            let dateStr = '';

            if (reportType === 'expiry') {
                dateStr = item.expiry_date; // Use Expiry Date for Expiry Report
            } else {
                dateStr = item.start_date;  // Use Start Date for others
            }

            if (dateStr && dateStr !== '0000-00-00') {
                const year = dateStr.split('-')[0];
                years.add(year);
            }
        });

        // Sort years and append to dropdown
        const sortedYears = Array.from(years).sort();
        sortedYears.forEach(y => {
            const option = document.createElement('option');
            option.value = y;
            option.textContent = y;
            filterYearSelect.appendChild(option);
        });
    }

    // Listen for changes on Report Type dropdown to update years
    const reportTypeSelect = document.getElementById('reportType');
    if (reportTypeSelect) {
        reportTypeSelect.addEventListener('change', updateYearOptions);
    }

    // 1. Generate Button Click
    document.getElementById('generateBtn').addEventListener('click', () => {
        const type = document.getElementById('reportType').value;
        const year = document.getElementById('filterYear').value;

        if (!type) {
            alert("Please select a report type.");
            return;
        }

        if (rawDataSet.length === 0) {
            alert("No data found in the database.");
            return;
        }

        currentReportType = type;
        document.getElementById('reportWrapper').style.display = 'block';
        document.getElementById('reportDate').innerText = new Date().toLocaleDateString();

        processDataAndRender(type, year);
    });

    // 2. Core Processing Logic
    function processDataAndRender(type, year) {
        let filteredData = rawDataSet;

        // --- GLOBAL CLEANUP FUNCTION ---
        // This ensures we start with a clean DOM before rendering ANY report
        resetChartLayout();

        if (type === 'expiry') {
            // For Expiry report, we pass the year to the specific function
            // No pre-filtering here.
        } else {
            // For Contract, Inventory, Crops: Filter by Start Date
            if (year !== 'all') {
                filteredData = rawDataSet.filter(item => {
                    const startMatch = item.start_date && String(item.start_date).includes(year);
                    return startMatch;
                });
            }
        }

        // Destroy previous main chart instance
        if (mainChartInstance) mainChartInstance.destroy();

        switch (type) {
            case 'contract':
                renderContractReport(filteredData);
                break;
            case 'inventory':
                renderInventoryReport(filteredData);
                break;
            case 'crops':
                renderCropsReport(filteredData);
                break;
            case 'expiry':
                renderExpiryReport(rawDataSet, year); // Pass full data & year
                break;
        }
    }

    // --- HELPER: RESET CHART LAYOUT ---
    function resetChartLayout() {
        const chartContainer = document.getElementById('chartContainer');
        const mainCanvas = document.getElementById('mainChartCanvas');
        const munWrapper = document.getElementById('munChartWrapper');
        const statusWrapper = document.getElementById('statusWrapper');

        // 1. Remove Municipality Chart Wrapper if it exists
        if (munWrapper) munWrapper.remove();

        // 2. Unwrap the Status Chart if it was wrapped
        // If statusWrapper exists, it means mainCanvas is inside it.
        if (statusWrapper && mainCanvas) {
            chartContainer.appendChild(mainCanvas); // Move canvas back to main container
            statusWrapper.remove(); // Remove the empty wrapper
        }

        // 3. Reset Container Styles to default (Single Chart View)
        chartContainer.style.display = 'block';
        chartContainer.style.height = '400px';
        chartContainer.style.flexDirection = 'unset';
        chartContainer.style.gap = 'unset';

        // 4. Destroy auxiliary chart instances
        if (window.municipalityChartInstance) {
            window.municipalityChartInstance.destroy();
            window.municipalityChartInstance = null;
        }
    }

    // --- REPORT 1: CONTRACT STATUS ---
    function renderContractReport(data) {
        document.getElementById('reportTitle').innerText = "Contract Status";

        // 1. Dynamic Status Counting
        const counts = {};
        data.forEach(item => {
            let status = item.contract_status ? item.contract_status.trim() : 'Unspecified';
            counts[status] = (counts[status] || 0) + 1;
        });

        // 2. Define UNIQUE & DISTINCT Colors for each Status
        const statusColorMap = {
            'for return': '#3498DB',      // Bright Blue
            'renewed': '#F1C40F',         // Strong Yellow
            'nonrenewing': '#E91E63',     // Pink
            'existing': '#00BCD4',        // Cyan
            'returned': '#9B59B6',        // Purple
            'extension': '#E67E22',       // Deep Orange
            'expired': '#ff0000',         // Red
            'retain': '#2ECC71',          // Green
            'cancelled': '#95A5A6',       // Grey
            'newland': '#1ABC9C'          // Teal
        };

        // Generate colors array based on the labels found in data
        const chartLabels = Object.keys(counts);
        const chartColors = chartLabels.map(label => {
            const key = label.toLowerCase();
            return statusColorMap[key] || getRandomColor();
        });

        // 3. Chart
        const ctx = document.getElementById('mainChartCanvas').getContext('2d');
        mainChartInstance = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: chartLabels,
                datasets: [{
                    data: Object.values(counts),
                    backgroundColor: chartColors
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        // --- CUSTOM LABEL GENERATOR (Adds Strikethrough & Grey Box when hidden) ---
                        labels: {
                            generateLabels: function (chart) {
                                const data = chart.data;
                                if (data.labels.length && data.datasets.length) {
                                    return data.labels.map((label, i) => {
                                        const meta = chart.getDatasetMeta(0);
                                        const style = meta.controller.getStyle(i);
                                        const isHidden = meta.data[i].hidden;

                                        return {
                                            text: label,
                                            fillStyle: isHidden ? '#ececec' : style.backgroundColor, // Grey box if hidden
                                            strokeStyle: isHidden ? '#CCCCCC' : style.backgroundColor,
                                            lineWidth: 1,
                                            hidden: isHidden,
                                            index: i,
                                            // Add strikethrough font style if hidden
                                            font: {
                                                strike: isHidden
                                            }
                                        };
                                    });
                                }
                                return [];
                            }
                        },
                        // --- CUSTOM LEGEND CLICK LOGIC ---
                        onClick: function (e, legendItem, legend) {
                            // 1. Perform default Chart.js behavior (Toggle slice visibility)
                            const index = legendItem.index;
                            const chart = legend.chart;
                            const meta = chart.getDatasetMeta(0);

                            // Toggle hidden state
                            meta.data[index].hidden = !meta.data[index].hidden;

                            // Update chart animation (This will also trigger generateLabels to update visual)
                            chart.update();

                            // 2. Synchronize with Table Rows
                            const clickedStatus = legendItem.text.toLowerCase().trim();
                            const isNowHidden = meta.data[index].hidden;

                            // Loop through all table rows
                            const tableRows = document.querySelectorAll('#reportTable tbody tr');
                            tableRows.forEach(row => {
                                // Status is in the 9th column (Index 8)
                                const statusCell = row.cells[8];
                                if (statusCell) {
                                    const rowStatus = statusCell.innerText.toLowerCase().trim();

                                    // If the row matches the clicked legend
                                    if (rowStatus === clickedStatus) {
                                        // If hidden in chart, hide in table
                                        if (isNowHidden) {
                                            row.style.display = 'none';
                                        } else {
                                            row.style.display = ''; // Restore display
                                        }
                                    }
                                }
                            });
                        }
                    }
                }
            }
        });

        // 4. Updated Table with Compact Columns
        renderTable(
            [
                'Contracting Party',
                'Vendor',
                'Lot No',
                'Field Section',
                'Barangay',
                'Municipality',
                'Province',
                'Arable (Ha)',
                'Status',
                'Start Date',
                'Expiry Date',
                'Paid Update'
            ],
            data.map(item => {
                const statusClass = item.contract_status
                    ? String(item.contract_status).toLowerCase().replace(/\s+/g, '-')
                    : 'unspecified';

                return [
                    item.contracting_party || 'N/A',
                    item.vendor || 'N/A',
                    item.lot_no || 'N/A',
                    item.field_section || 'N/A',
                    item.barangay || 'N/A',
                    item.municipality || 'N/A',
                    item.province || 'N/A',
                    item.contracted_arable || 'N/A',
                    `<span class="status-badge status-${statusClass}">${item.contract_status || 'N/A'}</span>`,
                    item.start_date || 'N/A',
                    item.expiry_date || 'N/A',
                    item.paid_up_date || 'N/A'
                ];
            })
        );
    }

    // Helper function for fallback random colors
    function getRandomColor() {
        const letters = '0123456789ABCDEF';
        let color = '#';
        for (let i = 0; i < 6; i++) {
            color += letters[Math.floor(Math.random() * 16)];
        }
        return color;
    }

    // --- REPORT 2: CROP DISTRIBUTION ANALYSIS (Grouped by Crop) ---
    function renderCropsReport(data) {
        document.getElementById('reportTitle').innerText = "Crop Distribution by Municipality";

        const cropGroups = {};
        let grandTotalArea = 0;

        // 1. Aggregate Data: Group by Crop -> then by Municipality
        data.forEach(item => {
            const crop = item.crop_type || 'Unspecified';
            const municipality = item.municipality || 'Unspecified';
            const area = parseFloat(item.contracted_arable) || 0;

            grandTotalArea += area;

            if (!cropGroups[crop]) {
                cropGroups[crop] = {
                    totalArea: 0,
                    municipalities: {}
                };
            }

            cropGroups[crop].totalArea += area;

            if (!cropGroups[crop].municipalities[municipality]) {
                cropGroups[crop].municipalities[municipality] = 0;
            }
            cropGroups[crop].municipalities[municipality] += area;
        });

        // 2. Process Data for the Table
        const tableRows = [];
        const tableRowAttrs = []; // NEW: Store attributes for rows (data-crop)
        const sortedCrops = Object.entries(cropGroups).sort((a, b) => b[1].totalArea - a[1].totalArea);

        sortedCrops.forEach(([cropName, cropData]) => {
            const cropPercent = ((cropData.totalArea / grandTotalArea) * 100).toFixed(3) + '%';

            // Parent Row
            tableRows.push([
                `<strong>${cropName}</strong>`,
                `<em>Total: ${cropData.totalArea.toFixed(2)} ha</em>`,
                cropPercent
            ]);
            // Add attribute for parent row
            tableRowAttrs.push(`data-crop="${cropName}"`);

            // Municipality Rows (Nested)
            const sortedMunicipalities = Object.entries(cropData.municipalities).sort((a, b) => b[1] - a[1]);

            sortedMunicipalities.forEach(([munName, munArea]) => {
                const munPercent = ((munArea / cropData.totalArea) * 100).toFixed(3) + '%';

                tableRows.push([
                    `&nbsp;&nbsp;&nbsp;↳ ${munName}`,
                    munArea.toFixed(4) + ' ha',
                    munPercent
                ]);
                // Add attribute for child row (same crop name)
                tableRowAttrs.push(`data-crop="${cropName}"`);
            });
        });

        // 3. Render Table (Passing attributes)
        renderTable(
            ['Crop Type / Municipality', 'Total Area', '% Share'],
            tableRows,
            tableRowAttrs
        );

        // --- CSS INJECTION START ---
        const table = document.getElementById('reportTable');
        if (table) {
            // Style Column 1 (Crop/Municipality) - Wider
            table.querySelectorAll('th:nth-child(1), td:nth-child(1)').forEach(el => {
                el.style.width = '50%';
                el.style.textAlign = 'left';
            });

            // Style Column 2 (Total Area) - Smaller
            table.querySelectorAll('th:nth-child(2), td:nth-child(2)').forEach(el => {
                el.style.width = '25%';
                el.style.textAlign = 'center';
            });

            // Style Column 3 (% Share) - Smaller
            table.querySelectorAll('th:nth-child(3), td:nth-child(3)').forEach(el => {
                el.style.width = '25%';
                el.style.textAlign = 'center';
            });
        }
        // --- CSS INJECTION END ---

        // 4. Render Chart
        const ctx = document.getElementById('mainChartCanvas').getContext('2d');

        if (mainChartInstance) mainChartInstance.destroy();

        // Prepare colors
        const chartLabels = sortedCrops.map(c => c[0]);
        const chartColors = ['#4BC0C0', '#36A2EB', '#FFCE56', '#FF6384', '#9966FF', '#FF9F40'];
        // Ensure we have enough colors
        while(chartColors.length < chartLabels.length) {
            chartColors.push(getRandomColor());
        }

        mainChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Hectares',
                    data: sortedCrops.map(c => c[1].totalArea.toFixed(2)),
                    backgroundColor: chartColors,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            generateLabels: function (chart) {
                                const data = chart.data;
                                if (data.labels.length && data.datasets.length) {
                                    return data.labels.map((label, i) => {
                                        const meta = chart.getDatasetMeta(0);
                                        const style = meta.controller.getStyle(i);
                                        const isHidden = meta.data[i] && meta.data[i].hidden;
                                        return {
                                            text: label,
                                            fillStyle: isHidden ? '#ececec' : style.backgroundColor,
                                            strokeStyle: isHidden ? '#CCCCCC' : style.backgroundColor,
                                            lineWidth: 1,
                                            hidden: isHidden,
                                            index: i,
                                            font: { strike: isHidden }
                                        };
                                    });
                                }
                                return [];
                            }
                        },
                        onClick: function (e, legendItem, legend) {
                            const index = legendItem.index;
                            const chart = legend.chart;
                            const meta = chart.getDatasetMeta(0);

                            if (meta.data[index]) {
                                meta.data[index].hidden = !meta.data[index].hidden;
                            }
                            chart.update();

                            // Sync Table Rows using the data-crop attribute
                            const clickedCrop = legendItem.text;
                            const isHidden = meta.data[index].hidden;

                            const rows = document.querySelectorAll(`#reportTable tbody tr[data-crop="${clickedCrop}"]`);
                            rows.forEach(row => {
                                row.style.display = isHidden ? 'none' : '';
                            });
                        }
                    }
                }
            }
        });
    }

    // --- REPORT 3: EXPIRY MONITORING (UPDATED) ---
    function renderExpiryReport(data, year) {
        document.getElementById('reportTitle').innerText = "Contract Expiry Monitoring";

        // Filter data by selected year if specific year is chosen
        let filteredData = data;
        if (year && year !== 'all') {
            filteredData = data.filter(item => {
                if (!item.expiry_date) return false;
                return item.expiry_date.startsWith(year);
            });
        }

        // VIEW: SUMMARY (All Time)
        if (year === 'all') {
            const yearlyGroups = {};
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const nearExpirationDate = new Date(today);
            nearExpirationDate.setFullYear(nearExpirationDate.getFullYear() + 2);

            filteredData.forEach(item => {
                if (!item.expiry_date || item.expiry_date === '0000-00-00') return;
                const yr = item.expiry_date.split('-')[0];
                const area = parseFloat(item.contracted_arable) || 0;

                if (!yearlyGroups[yr]) {
                    yearlyGroups[yr] = { count: 0, area: 0, expired: 0, nearExpiration: 0 };
                }

                yearlyGroups[yr].count++;
                yearlyGroups[yr].area += area;

                const expiryDate = new Date(item.expiry_date);

                if (expiryDate < today) {
                    yearlyGroups[yr].expired++;
                } else if (expiryDate <= nearExpirationDate) {
                    yearlyGroups[yr].nearExpiration++;
                }
            });

            const sortedYears = Object.keys(yearlyGroups).sort();

            const ctx = document.getElementById('mainChartCanvas').getContext('2d');
            mainChartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: sortedYears,
                    datasets: [{
                        label: 'Contracts Expiring',
                        data: sortedYears.map(y => yearlyGroups[y].count),
                        backgroundColor: '#FF6384'
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });

            renderTable(
                ['Expiry Year', 'Contract Count', 'Total Area (ha)', 'Total Expired', 'Near Expiration'],
                sortedYears.map(y => [
                    y,
                    yearlyGroups[y].count,
                    yearlyGroups[y].area.toFixed(2),
                    yearlyGroups[y].expired,
                    yearlyGroups[y].nearExpiration
                ])
            );

        }
        // VIEW: DETAILED (Specific Year) - DUAL CHARTS
        else {
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            const processedData = filteredData.map(item => {
                const daysLeft = getDaysLeft(item.expiry_date);
                let statusText = 'Active';
                let statusClass = 'status-active';

                if (daysLeft < 0) {
                    statusText = 'Expired';
                    statusClass = 'status-expired';
                } else if (daysLeft < 90) {
                    statusText = 'Expiring Soon';
                    statusClass = 'status-pending';
                }

                return {
                    ...item,
                    daysLeft: daysLeft,
                    statusText: statusText,
                    statusClass: statusClass
                };
            });

            processedData.sort((a, b) => {
                const priority = { 'Expiring Soon': 1, 'Active': 2, 'Expired': 3 };
                const prioA = priority[a.statusText] || 4;
                const prioB = priority[b.statusText] || 4;
                if (prioA !== prioB) return prioA - prioB;
                return a.daysLeft - b.daysLeft;
            });

            const statusColorMap = {
                'Expiring Soon': '#FFCE56',
                'Active': '#4BC0C0',
                'Expired': '#FF6384'
            };

            const municipalityColorMap = {};
            const defaultColors = ['#4BC0C0', '#36A2EB', '#FFCE56', '#FF6384', '#9966FF', '#FF9F40', '#e6194b', '#3cb44b', '#ffe119', '#4363d8', '#f58231', '#911eb4', '#46f0f0', '#f032e6', '#bcf60c', '#fabebe'];
            let colorIndex = 0;

            processedData.forEach(item => {
                const mun = item.municipality || 'Unspecified';
                if (!municipalityColorMap[mun]) {
                    municipalityColorMap[mun] = defaultColors[colorIndex % defaultColors.length];
                    colorIndex++;
                }
            });

            const chartContainer = document.getElementById('chartContainer');
            const mainCanvas = document.getElementById('mainChartCanvas');

            chartContainer.style.display = 'flex';
            chartContainer.style.flexDirection = 'row';
            chartContainer.style.gap = '20px';
            chartContainer.style.height = '400px';

            let statusWrapper = document.getElementById('statusWrapper');
            if (!statusWrapper) {
                statusWrapper = document.createElement('div');
                statusWrapper.id = 'statusWrapper';
                statusWrapper.style.flex = '1';
                statusWrapper.style.position = 'relative';
                statusWrapper.style.height = '100%';
                chartContainer.insertBefore(statusWrapper, mainCanvas);
                statusWrapper.appendChild(mainCanvas);
            }

            let munWrapper = document.getElementById('munChartWrapper');
            if (!munWrapper) {
                munWrapper = document.createElement('div');
                munWrapper.id = 'munChartWrapper';
                munWrapper.style.flex = '1';
                munWrapper.style.position = 'relative';
                munWrapper.style.height = '100%';
                const munCanvas = document.createElement('canvas');
                munCanvas.id = 'municipalityChartCanvas';
                munWrapper.appendChild(munCanvas);
                chartContainer.appendChild(munWrapper);
            }

            if (mainChartInstance) mainChartInstance.destroy();
            if (window.municipalityChartInstance) window.municipalityChartInstance.destroy();

            function updateDashboard() {
                const statusMeta = mainChartInstance.getDatasetMeta(0);
                const hiddenStatuses = new Set();
                mainChartInstance.data.labels.forEach((label, i) => {
                    if (statusMeta.data[i] && statusMeta.data[i].hidden) {
                        hiddenStatuses.add(label);
                    }
                });

                const munMeta = window.municipalityChartInstance.getDatasetMeta(0);
                const hiddenMunicipalities = new Set();
                window.municipalityChartInstance.data.labels.forEach((label, i) => {
                    if (munMeta.data[i] && munMeta.data[i].hidden) {
                        hiddenMunicipalities.add(label);
                    }
                });

                const tableRows = document.querySelectorAll('#reportTable tbody tr');
                tableRows.forEach((row, index) => {
                    const rowData = processedData[index];
                    const isStatusHidden = hiddenStatuses.has(rowData.statusText);
                    const munLabel = rowData.municipality || 'Unspecified';
                    const isMunHidden = hiddenMunicipalities.has(munLabel);

                    row.style.display = (isStatusHidden || isMunHidden) ? 'none' : '';
                });

                const munCounts = {};
                processedData.forEach(item => {
                    if (!hiddenStatuses.has(item.statusText)) {
                        const mun = item.municipality || 'Unspecified';
                        munCounts[mun] = (munCounts[mun] || 0) + 1;
                    }
                });

                const newMunLabels = Object.keys(munCounts).sort();
                const newMunData = newMunLabels.map(l => munCounts[l]);
                const newMunColors = newMunLabels.map(l => municipalityColorMap[l]);

                window.municipalityChartInstance.data.labels = newMunLabels;
                window.municipalityChartInstance.data.datasets[0].data = newMunData;
                window.municipalityChartInstance.data.datasets[0].backgroundColor = newMunColors;

                const newMunMeta = window.municipalityChartInstance.getDatasetMeta(0);
                newMunLabels.forEach((label, i) => {
                    if (newMunMeta.data[i]) {
                        newMunMeta.data[i].hidden = hiddenMunicipalities.has(label);
                    }
                });

                window.municipalityChartInstance.update('none');

                const statusCounts = { 'Expiring Soon': 0, 'Active': 0, 'Expired': 0 };
                processedData.forEach(item => {
                    const mun = item.municipality || 'Unspecified';
                    if (!hiddenMunicipalities.has(mun)) {
                        statusCounts[item.statusText]++;
                    }
                });

                mainChartInstance.data.datasets[0].data = [
                    statusCounts['Expiring Soon'],
                    statusCounts['Active'],
                    statusCounts['Expired']
                ];

                const newStatusMeta = mainChartInstance.getDatasetMeta(0);
                mainChartInstance.data.labels.forEach((label, i) => {
                    if (newStatusMeta.data[i]) {
                        newStatusMeta.data[i].hidden = hiddenStatuses.has(label);
                    }
                });

                mainChartInstance.update('none');
            }

            const statusCounts = { 'Expiring Soon': 0, 'Active': 0, 'Expired': 0 };
            processedData.forEach(item => statusCounts[item.statusText]++);

            mainChartInstance = new Chart(mainCanvas.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: Object.keys(statusCounts),
                    datasets: [{
                        data: Object.values(statusCounts),
                        backgroundColor: Object.keys(statusCounts).map(l => statusColorMap[l])
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                generateLabels: function (chart) {
                                    const data = chart.data;
                                    if (data.labels.length && data.datasets.length) {
                                        return data.labels.map((label, i) => {
                                            const meta = chart.getDatasetMeta(0);
                                            const style = meta.controller.getStyle(i);
                                            const isHidden = meta.data[i] && meta.data[i].hidden;
                                            return {
                                                text: label,
                                                fillStyle: isHidden ? '#ececec' : style.backgroundColor,
                                                strokeStyle: isHidden ? '#CCCCCC' : style.backgroundColor,
                                                lineWidth: 1,
                                                hidden: isHidden,
                                                index: i,
                                                font: { strike: isHidden }
                                            };
                                        });
                                    }
                                    return [];
                                }
                            },
                            onClick: function (e, legendItem, legend) {
                                const index = legendItem.index;
                                const chart = legend.chart;
                                const meta = chart.getDatasetMeta(0);
                                if (meta.data[index]) {
                                    meta.data[index].hidden = !meta.data[index].hidden;
                                }
                                chart.update();
                                updateDashboard();
                            }
                        }
                    }
                }
            });

            const munCounts = {};
            processedData.forEach(item => {
                const mun = item.municipality || 'Unspecified';
                munCounts[mun] = (munCounts[mun] || 0) + 1;
            });
            const munLabels = Object.keys(munCounts).sort();
            const munData = munLabels.map(l => munCounts[l]);
            const munColors = munLabels.map(l => municipalityColorMap[l]);

            const munCtx = document.getElementById('municipalityChartCanvas').getContext('2d');
            window.municipalityChartInstance = new Chart(munCtx, {
                type: 'doughnut',
                data: {
                    labels: munLabels,
                    datasets: [{
                        data: munData,
                        backgroundColor: munColors
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                generateLabels: function (chart) {
                                    const data = chart.data;
                                    if (data.labels.length && data.datasets.length) {
                                        return data.labels.map((label, i) => {
                                            const meta = chart.getDatasetMeta(0);
                                            const style = meta.controller.getStyle(i);
                                            const isHidden = meta.data[i] && meta.data[i].hidden;
                                            return {
                                                text: label,
                                                fillStyle: isHidden ? '#ececec' : style.backgroundColor,
                                                strokeStyle: isHidden ? '#CCCCCC' : style.backgroundColor,
                                                lineWidth: 1,
                                                hidden: isHidden,
                                                index: i,
                                                font: { strike: isHidden }
                                            };
                                        });
                                    }
                                    return [];
                                }
                            },
                            onClick: function (e, legendItem, legend) {
                                const index = legendItem.index;
                                const chart = legend.chart;
                                const meta = chart.getDatasetMeta(0);
                                if (meta.data[index]) {
                                    meta.data[index].hidden = !meta.data[index].hidden;
                                }
                                chart.update();
                                updateDashboard();
                            }
                        }
                    }
                }
            });

            renderTable(
                [
                    'Contracting Party',
                    'Vendor',
                    'Lot No',
                    'Field Section',
                    'Barangay',
                    'Municipality',
                    'Province',
                    'Arable (Ha)',
                    'Start Date',
                    'Expiry Date',
                    'Paid Up Date',
                    'Status'
                ],
                processedData.map(item => {
                    return [
                        item.contracting_party || 'N/A',
                        item.vendor || 'N/A',
                        item.lot_no || 'N/A',
                        item.field_section || 'N/A',
                        item.barangay || 'N/A',
                        item.municipality || 'N/A',
                        item.province || 'N/A',
                        item.contracted_arable || 'N/A',
                        item.start_date || 'N/A',
                        item.expiry_date || 'N/A',
                        item.paid_up_date || 'N/A',
                        `<span class="status-badge ${item.statusClass}">${item.statusText}</span>`
                    ];
                })
            );
        }
    }

    // Helper: Calculate days left
    function getDaysLeft(dateStr) {
        if (!dateStr) return 0;
        const expiry = new Date(dateStr);
        const today = new Date();
        const diff = expiry - today;
        return Math.ceil(diff / (1000 * 60 * 60 * 24));
    }

    // Helper: Render Table (Added rowAttrs param)
    function renderTable(headers, rows, rowAttrs = null) {
        const thead = document.getElementById('tableHead');
        const tbody = document.getElementById('tableBody');

        // --- INJECT TIGHT/FIXED TABLE STYLES ---
        if (!document.getElementById('compact-report-styles')) {
            const style = document.createElement('style');
            style.id = 'compact-report-styles';
            style.innerHTML = `
              #reportTable { 
                  width: 100%; 
                  border-collapse: collapse; 
                  font-size: 14px; 
                  table-layout: fixed; 
              }
              #reportTable th, #reportTable td { 
                  padding: 2px 4px; 
                  border: 1px solid #ddd; 
                  text-align: left; 
                  line-height: 1.2; 
                  vertical-align: top;
                  overflow: hidden; 
              }
              #reportTable th { 
                  background-color: #006400; 
                  color: white; 
                  font-weight: 600;
                  font-size: 10px; 
                  text-transform: uppercase;
                  text-align: center;
              }

              #reportTable th:nth-child(1),
              #reportTable td:nth-child(1) {
                  width: 200px;
                  max-width: 200px;
                  word-break: break-word;
                  white-space: normal;
              }

              #reportTable th:nth-child(4),
              #reportTable td:nth-child(4) {
                  width: 80px;
              }

              #reportTable th:nth-child(9),
              #reportTable td:nth-child(9) {
                  width: 100px;
                  text-align: center;
              }

              .status-badge { 
                  padding: 1px 3px; 
                  border-radius: 3px; 
                  font-size: 12px; 
                  display: inline-block;
              }
          `;
            document.head.appendChild(style);
        }

        thead.innerHTML = `<tr>${headers.map(h => `<th>${h}</th>`).join('')}</tr>`;
        
        // Use rowAttrs if provided
        tbody.innerHTML = rows.map((row, i) => {
            const attrs = rowAttrs && rowAttrs[i] ? rowAttrs[i] : '';
            return `<tr ${attrs}>${row.map(cell => `<td>${cell}</td>`).join('')}</tr>`;
        }).join('');
    }

    // ===== DOWNLOAD FUNCTION (PDF) =====
    window.downloadReport = async () => {
        if (!currentReportType) {
            alert("Generate a report first.");
            return;
        }

        const btn = document.getElementById('downloadBtn');
        btn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i>";

        try {
            const canvas = await html2canvas(document.getElementById('reportContainer'), {
                scale: 2,
                useCORS: true,
                logging: true
            });

            const imgData = canvas.toDataURL('image/png');
            const imgWidth = canvas.width;
            const imgHeight = canvas.height;

            const { jsPDF } = window.jspdf;
            const pdf = new jsPDF('p', 'mm', 'a4');

            const pdfWidth = pdf.internal.pageSize.getWidth();
            const pdfHeight = pdf.internal.pageSize.getHeight();

            const ratio = pdfWidth / imgWidth;
            const scaledWidth = pdfWidth;
            const scaledHeight = imgHeight * ratio;

            if (scaledHeight > pdfHeight) {
                let heightLeft = scaledHeight;
                let position = 0;

                pdf.addImage(imgData, 'PNG', 0, position, scaledWidth, scaledHeight);
                heightLeft -= pdfHeight;

                while (heightLeft >= 0) {
                    position = heightLeft - scaledHeight;
                    pdf.addPage();
                    pdf.addImage(imgData, 'PNG', 0, position, scaledWidth, scaledHeight);
                    heightLeft -= pdfHeight;
                }
            } else {
                pdf.addImage(imgData, 'PNG', 0, 0, scaledWidth, scaledHeight);
            }

            pdf.save(`${currentReportType}_report_${Date.now()}.pdf`);

        } catch (err) {
            console.error("Download failed", err);
            alert("Failed to generate PDF. Please check console for errors.");
        }

        btn.innerHTML = "<i class='bx bx-download'></i>";
    };

    // ===== PRINT FUNCTION =====
    window.printReport = () => {
        if (!currentReportType) {
            alert("Generate a report first.");
            return;
        }

        const tableElement = document.getElementById('tableContainer');
        const reportTitle = document.getElementById('reportTitle').innerText;
        const reportDate = document.getElementById('reportDate').innerText;

        if (!tableElement) {
            alert("Nothing to print. Please generate the report first.");
            return;
        }

        const printWin = window.open('', '', 'height=800,width=1000');
        printWin.document.write('<html><head><title>' + reportTitle + '</title>');

        printWin.document.write(`
        <style>
          body { font-family: Arial, sans-serif; padding: 20px; font-size: 12px; }
          * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
          .print-header {
              display: flex;
              justify-content: space-between;
              align-items: center;
              border-bottom: 2px solid #006400;
              padding-bottom: 10px;
              margin-bottom: 20px;
          }
          .header-left { width: 20%; }
          .header-center { width: 60%; text-align: center; }
          .header-right { width: 20%; text-align: right; }
          .dept-title { margin: 0; color: #006400; font-size: 18px; font-weight: bold; letter-spacing: 0.5px; }
          .dept-address { margin: 5px 0 0 0; font-size: 12px; color: #333; font-style: italic; }
          .report-info { text-align: center; margin-bottom: 15px; }
          .report-info h2 { margin: 0; font-size: 16px; color: #000; }
          .report-info p { margin: 5px 0 0 0; font-size: 11px; color: #555; }
          table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 11px; }
          th, td { padding: 4px 5px; border: 1px solid #ddd; text-align: left; line-height: 1.3; }
          th { background-color: #006400; color: white; font-size: 11px; text-align: center; }
          .status-badge { 
              padding: 2px 4px; 
              border-radius: 3px; 
              font-size: 9px; 
              font-weight: bold; 
              display: inline-block;
          }
          .status-expired { background: #ff0000; color: white; }
          .status-active { background: #004085; color: white; }
          .status-for-return { background: #3498DB; color: white; }
          .status-nonrenewing { background: #E91E63; color: white; }
          .status-extension { background: #E67E22; color: white; }
          .status-retain { background: #2ECC71; color: white; }
          .status-cancelled { background: #95A5A6; color: white; }
          .status-returned { background: #9B59B6; color: white; }
          .status-newland { background: #1ABC9C; color: white; }
          .status-existing { background: #00BCD4; color: white; }
          .status-renewed { background: #F1C40F; color: black; }
          .status-pending { background: #FFCE56; color: black; }
          .status-unspecified { background: #ccc; color: black; }
        </style>
      `);

        printWin.document.write('</head><body>');

        printWin.document.write(`
            <div class="print-header">
                <div class="header-left">
                    <img src="../images/web_icon.png" style="height: 70px; width: auto;">
                </div>
                <div class="header-center">
                    <h1 class="dept-title">LAND ASSET MANAGEMENT DEPARTMENT</h1>
                    <p class="dept-address">Camp Phillips, Agusan Canyon, Manolo Fortich, Bukidnon</p>
                </div>
                <div class="header-right">
                    <img src="../images/100years_icon.png" style="height: 70px; width: auto;">
                </div>
            </div>
        `);

        printWin.document.write(`
            <div class="report-info">
                <h2>${reportTitle}</h2>
                <p>Generated on: ${reportDate}</p>
            </div>
        `);

        printWin.document.write(tableElement.innerHTML);

        printWin.document.write('</body></html>');
        printWin.document.close();

        setTimeout(() => {
            printWin.print();
            printWin.close();
        }, 500);
    };

});