document.addEventListener('DOMContentLoaded', () => {
    // ==========================================
    // 1. PIE CHART
    // ==========================================
    const ctx = document.getElementById('myPieChart').getContext('2d');
    const data = dynamicPieData;

    if (data && data.length > 0) {
        const labels = data.map(d => d.label);
        const hectaresValues = data.map(d => d.hectares);
        const colors = Array.from({ length: labels.length }, (_, i) => `hsl(${i * 360 / labels.length}, 70%, 50%)`);

        const pieChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: hectaresValues,
                    backgroundColor: colors,
                    hoverOffset: 4,
                    borderColor: '#fff',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true, // Ensures the pie stays a perfect circle
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        enabled: true,
                        callbacks: {
                            title: () => '',
                            label: function (context) {
                                const item = data[context.dataIndex];
                                return [
                                    `${item.label}`,
                                    `Total Arable: ${item.hectares.toLocaleString()}`,
                                    `Contracts: ${item.contracts}`
                                ];
                            }
                        }
                    }
                }
            }
        });

        // Custom Legend Logic
        const legendContainer = document.getElementById('custom-legend');
        const hoverInfoBox = document.createElement('div');

        // --- START: Style the Hover Box ---
        hoverInfoBox.style.position = 'absolute';
        hoverInfoBox.style.display = 'none';
        hoverInfoBox.style.opacity = 0;
        hoverInfoBox.style.transition = 'opacity 0.2s ease';
        hoverInfoBox.style.backgroundColor = 'white';
        hoverInfoBox.style.border = '1px solid #ccc';
        hoverInfoBox.style.padding = '10px 15px'; // Slightly better padding
        hoverInfoBox.style.borderRadius = '4px';
        hoverInfoBox.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
        hoverInfoBox.style.pointerEvents = 'none';
        hoverInfoBox.style.zIndex = '1000';

        // FIX: Grab the font from an element that definitely has the correct font (Sidebar or Main Title)
        const refElement = document.querySelector('#sidebar .text') || document.querySelector('.head-title h1');
        if (refElement) {
            const computedFont = window.getComputedStyle(refElement).fontFamily;
            hoverInfoBox.style.fontFamily = computedFont;
        }
        // Add smoothing to match the page text
        hoverInfoBox.style.webkitFontSmoothing = 'antialiased';
        // --- END: Style the Hover Box ---

        document.body.appendChild(hoverInfoBox);

        function showHoverInfo(content, event) {
            hoverInfoBox.innerHTML = content;
            hoverInfoBox.style.display = 'block';
            const rect = hoverInfoBox.getBoundingClientRect();
            const boxWidth = rect.width;
            const boxHeight = rect.height;
            const padding = 10;
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            let left = event.clientX + padding;
            let top = event.clientY + padding;
            if (left + boxWidth > viewportWidth) left = event.clientX - boxWidth - padding;
            if (left < padding) left = padding;
            if (top + boxHeight > viewportHeight) top = event.clientY - boxHeight - padding;
            if (top < padding) top = padding;
            hoverInfoBox.style.left = left + 'px';
            hoverInfoBox.style.top = top + 'px';
            hoverInfoBox.style.opacity = 1;
        }

        function hideHoverInfo() {
            hoverInfoBox.style.opacity = 0;
            setTimeout(() => { hoverInfoBox.style.display = 'none'; }, 200);
        }

        data.forEach((item, index) => {
            const legendItem = document.createElement('div');
            legendItem.className = 'legend-item';
            const colorBox = document.createElement('span');
            colorBox.className = 'legend-color-box';
            colorBox.style.backgroundColor = colors[index];
            const labelText = document.createElement('span');
            labelText.innerText = item.label;

            const generateContent = (item) => {
                let brgyListHtml = '<div style="margin-top:8px; border-top:1px dotted #999; padding-top:5px; font-size:11px;">';
                if (item.barangays && item.barangays.length > 0) {
                    item.barangays.forEach(brgy => {
                        brgyListHtml += `
                            <div style="display:flex; justify-content:space-between; gap:20px; margin-bottom:2px;">
                                <span>${brgy.name}</span>
                                <span>${brgy.arable.toLocaleString()}</span>
                            </div>`;
                    });
                }
                brgyListHtml += '</div>';
                return `
                    <div style="min-width:180px;">
                        <strong style="font-size:14px; color:#333;">${item.label}</strong><br>
                        <div style="line-height: 0.7rem;">
                            <span style="color:#666; font-size:11px;">Total Arable: ${item.hectares.toLocaleString()}</span><br>
                            <span style="color:#666; font-size:11px;">Contracts: ${item.contracts}</span>
                        </div>
                        ${brgyListHtml}
                    </div>`;
            };

            legendItem.addEventListener('mouseenter', (e) => { showHoverInfo(generateContent(item), e); highlightSlice(index); });
            legendItem.addEventListener('mousemove', (e) => { showHoverInfo(generateContent(item), e); });
            legendItem.addEventListener('mouseleave', () => { hideHoverInfo(); resetHighlight(); });

            legendItem.appendChild(colorBox);
            legendItem.appendChild(labelText);
            legendContainer.appendChild(legendItem);
        });

        function highlightSlice(index) {
            if (!highlightSlice.originalColors) highlightSlice.originalColors = [...pieChart.data.datasets[0].backgroundColor];
            pieChart.data.datasets[0].backgroundColor = pieChart.data.datasets[0].backgroundColor.map((color, i) => i === index ? '#000' : color);
            pieChart.update();
        }
        function resetHighlight() {
            if (highlightSlice.originalColors) {
                pieChart.data.datasets[0].backgroundColor = [...highlightSlice.originalColors];
                pieChart.update();
            }
        }
    }

    // ==========================================
    // 2. LINE CHART
    // ==========================================
    const ctxLine = document.getElementById('expiryLineChart').getContext('2d');

    if (years.length === 0 || totalExpiring.length === 0) {
        console.error('No chart data available');
        document.getElementById('expiryLineChart').parentElement.innerHTML = '<p style="color: red; text-align: center;">No chart data available</p>';
    } else {
        const areaGradient = ctxLine.createLinearGradient(0, 0, 0, 400);
        areaGradient.addColorStop(0, 'rgba(0, 153, 255, 0.7)');
        areaGradient.addColorStop(1, 'rgba(225, 232, 236, 0)');

        new Chart(ctxLine, {
            type: 'line',
            data: {
                labels: years,
                datasets: [
                    {
                        label: 'Total Expiring',
                        data: totalExpiring,
                        fill: true,
                        backgroundColor: areaGradient,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 2,
                        tension: 0.4,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: 'rgba(54, 162, 235, 1)',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Total Arable',
                        data: totalArable,
                        borderColor: '#28a745',
                        backgroundColor: '#28a745',
                        pointRadius: 0,
                        borderWidth: 0,
                        fill: false,
                        yAxisID: 'yArable',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    title: {
                        display: true,
                        text: 'Lot Expiry per Fiscal Year',
                        font: { size: 16 },
                        color: '#2E7D32' // ADDED: Title Color (Green)
                    },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    x: {
                        title: { display: true, text: 'Fiscal Year', color: '#2E7D32' }, // ADDED
                        grid: { display: false },
                        ticks: { color: '#606060' } // ADDED: Axis numbers color
                    },
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Total Lots Expiring', color: '#2E7D32' }, // ADDED
                        ticks: { color: '#606060' } // ADDED: Axis numbers color
                    },
                    yArable: { display: false }
                }
            }
        });
    }


    // ==========================================
    // NEW: CONTRACTS LINE CHART (By Start Date)
    // ==========================================
    const ctxContractLine = document.getElementById('contractsLineChart'); // Reusing the same canvas ID, or change this ID if adding a new chart

    if (contractYears && contractYears.length > 0) {
        const contractGradient = ctxContractLine.getContext('2d').createLinearGradient(0, 0, 0, 400);
        contractGradient.addColorStop(0, 'rgba(255, 159, 64, 0.7)'); // Orange gradient
        contractGradient.addColorStop(1, 'rgba(255, 255, 255, 0)');

        new Chart(ctxContractLine, {
            type: 'line',
            data: {
                labels: contractYears,
                datasets: [
                    {
                        label: 'Total Contracts',
                        data: contractCounts,
                        fill: true,
                        backgroundColor: contractGradient,
                        borderColor: 'rgba(255, 159, 64, 1)', // Orange border
                        borderWidth: 2,
                        tension: 0.4,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: 'rgba(255, 159, 64, 1)',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        yAxisID: 'y',
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    title: {
                        display: true,
                        text: 'Contracts per Fiscal Year',
                        font: { size: 16 },
                        color: '#2E7D32'
                    },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    x: {
                        title: { display: true, text: 'Fiscal Year', color: '#2E7D32' },
                        grid: { display: false },
                        ticks: { color: '#606060' }
                    },
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Total Contracts', color: '#2E7D32' },
                        ticks: { color: '#606060' }
                    }
                }
            }
        });
    } else {
        console.error('No contract data available');
    }



    // ==========================================
    // 3. BAR GRAPH
    // ==========================================
    const ctxBar = document.getElementById('cropBarGraph');

    if (ctxBar && typeof barLabels !== 'undefined' && barLabels.length > 0) {

        const bgColors = [
            'rgba(54, 162, 235, 0.7)',
            'rgba(255, 99, 132, 0.7)',
            'rgba(255, 206, 86, 0.7)',
            'rgba(75, 192, 192, 0.7)',
            'rgba(153, 102, 255, 0.7)',
            'rgba(255, 159, 64, 0.7)'
        ];

        const labelColors = [
            'rgba(54, 162, 235, 1)',
            'rgba(255, 99, 132, 1)',
            'rgba(255, 206, 86, 1)',
            'rgba(75, 192, 192, 1)',
            'rgba(153, 102, 255, 1)',
            'rgba(255, 159, 64, 1)'
        ];

        new Chart(ctxBar.getContext('2d'), {
            type: 'bar',
            data: {
                labels: barLabels,
                datasets: [{
                    label: 'Total Contracts',
                    data: barCounts,
                    backgroundColor: bgColors,
                    borderColor: labelColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: { display: false },
                    title: {
                        display: true,
                        text: 'Contracts by Class',
                        font: { size: 16 },
                        color: '#2E7D32' // ADDED: Title Color (Green)
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Number of Contracts', color: '#2E7D32' }, // ADDED
                        ticks: { stepSize: 1, color: '#606060' } // ADDED
                    },
                    x: {
                        title: { display: true, text: 'Contract Class', color: '#2E7D32' }, // ADDED
                        ticks: {
                            font: { size: 11 },
                            maxRotation: 0,
                            minRotation: 0,
                            color: labelColors, // Keeps your multi-colored labels
                            callback: function (value, index, values) {
                                const label = this.getLabelForValue(value);
                                return label.split(' ');
                            }
                        }
                    }
                }
            }
        });
    } else {
        if (ctxBar) ctxBar.parentElement.innerHTML = '<p style="color:red; text-align:center;">No Contract Class data available</p>';
    }

    // ==========================================
    // 4. BAR GRAPH (Contract Status)
    // ==========================================
    const ctxStatus = document.getElementById('statusBarGraph');

    if (ctxStatus && typeof statusLabels !== 'undefined' && statusLabels.length > 0) {

        // Distinct colors for Status to differentiate from Class graph
        const statusBgColors = [
            'rgba(255, 159, 64, 0.7)',  // Orange
            'rgba(75, 192, 192, 0.7)',  // Teal
            'rgba(153, 102, 255, 0.7)', // Purple
            'rgba(255, 99, 132, 0.7)',  // Red
            'rgba(54, 162, 235, 0.7)',  // Blue
            'rgba(255, 206, 86, 0.7)',  // Yellow
            'rgba(50, 205, 50, 0.7)',   // Lime Green
            'rgba(231, 76, 60, 0.7)',   // Darker Red
            'rgba(52, 73, 94, 0.7)',    // Dark Blue Grey
            'rgba(241, 196, 15, 0.7)',  // Gold
            'rgba(46, 204, 113, 0.7)'   // Emerald
        ];

        const statusBorderColors = [
            'rgba(255, 159, 64, 1)',
            'rgba(75, 192, 192, 1)',
            'rgba(153, 102, 255, 1)',
            'rgba(255, 99, 132, 1)',
            'rgba(54, 162, 235, 1)',
            'rgba(255, 206, 86, 1)',
            'rgba(50, 205, 50, 1)',
            'rgba(231, 76, 60, 1)',
            'rgba(52, 73, 94, 1)',
            'rgba(241, 196, 15, 1)',
            'rgba(46, 204, 113, 1)'
        ];

        new Chart(ctxStatus.getContext('2d'), {
            type: 'bar',
            data: {
                labels: statusLabels,
                datasets: [{
                    label: 'Total Contracts',
                    data: statusCounts,
                    backgroundColor: statusBgColors,
                    borderColor: statusBorderColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                plugins: {
                    legend: { display: false },
                    title: {
                        display: true,
                        text: 'Contracts by Status',
                        font: { size: 16 },
                        color: '#2E7D32' // Green Title
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Number of Contracts', color: '#2E7D32' },
                        ticks: { stepSize: 1, color: '#606060' }
                    },
                    x: {
                        title: { display: true, text: 'Status', color: '#2E7D32' },
                        ticks: {
                            font: { size: 11 },
                            maxRotation: 45, // Rotated slightly for longer names like "NONRENEWING"
                            minRotation: 0,
                            color: '#606060'
                        }
                    }
                }
            }
        });
    } else {
        if (ctxStatus) ctxStatus.parentElement.innerHTML = '<p style="color:red; text-align:center;">No Status data available</p>';
    }



}); // End Main EventListener