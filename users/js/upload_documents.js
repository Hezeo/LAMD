document.addEventListener('DOMContentLoaded', function () {

    const fileInput = document.getElementById('contract_documents');
    const previewContainer = document.getElementById('documentPreviewArea');

    // Array to store the selected file objects (to handle append/remove logic)
    let documentFiles = [];

    // Syncs the actual HTML input with our custom array so the label updates (e.g., "2 files")
    function syncFileInput() {
        const dataTransfer = new DataTransfer();
        documentFiles.forEach(file => {
            dataTransfer.items.add(file);
        });
        fileInput.files = dataTransfer.files;
    }

    if (fileInput && previewContainer) {

        // 1. Listen for new file selections
        fileInput.addEventListener('change', function (e) {
            const newFiles = Array.from(this.files);

            if (newFiles.length === 0) return;

            // Loop through new files
            newFiles.forEach(file => {
                // CHECK FOR DUPLICATES: Check if file name already exists in our list
                const isDuplicate = documentFiles.some(existingFile => existingFile.name === file.name);

                // If not duplicate, add to the master list
                if (!isDuplicate) {
                    documentFiles.push(file);
                }
            });
            syncFileInput();
            // Clear the input value so the user can select the same file again if they deleted it (optional UX fix)
            // fileInput.value = ''; 

            // Re-render the preview based on the updated list
            renderDocumentPreview();
        });

        // 2. Function to Render the Preview Grid
        function renderDocumentPreview() {
            // Clear current container
            previewContainer.innerHTML = '';

            // Loop through the stored file list
            documentFiles.forEach((file, index) => {

                // --- CREATE MAIN CARD ---
                const card = document.createElement('div');
                card.className = 'doc-preview-item';

                // --- CREATE DELETE BUTTON (X) ---
                const removeBtn = document.createElement('div');
                removeBtn.className = 'remove-doc-btn';
                removeBtn.innerHTML = '<i class=\'bx bx-x\'></i>'; // Boxicons X
                removeBtn.title = 'Remove';

                // Delete Logic: Remove from array and re-render
                removeBtn.onclick = function (e) {
                    e.stopPropagation(); // Prevent triggering card clicks if any
                    documentFiles.splice(index, 1); // Remove file from array

                    syncFileInput(); // Updates "Choose Files - X files" text immediately

                    renderDocumentPreview(); // Update UI
                };

                // Append Delete Button to Card
                card.appendChild(removeBtn);


                // --- IMAGE / ICON BOX ---
                const imgBox = document.createElement('div');
                imgBox.className = 'img-preview-box';

                if (file.type.startsWith('image/')) {
                    // Image Logic
                    const reader = new FileReader();

                    reader.onload = function (e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        imgBox.appendChild(img);
                    }
                    reader.readAsDataURL(file);

                } else {
                    // Icon Logic (PDF, DOC, etc)
                    const icon = document.createElement('i');
                    icon.className = 'bx';

                    if (file.type.includes('pdf')) {
                        icon.classList.add('bxs-file-pdf');
                        icon.style.color = '#d32f2f';
                    } else if (file.type.includes('word') || file.name.endsWith('.docx')) {
                        icon.classList.add('bxs-file-doc');
                        icon.style.color = '#1976d2';
                    } else {
                        icon.classList.add('bxs-file');
                        icon.style.color = '#666';
                    }

                    imgBox.appendChild(icon);
                }

                card.appendChild(imgBox);

                // --- FILENAME INFO ---
                const nameWrapper = document.createElement('div');
                nameWrapper.className = 'filename-info';

                const span = document.createElement('span');
                span.className = 'filename-text';
                span.textContent = file.name;

                const tooltip = document.createElement('div');
                tooltip.className = 'filename-tooltip';
                tooltip.textContent = file.name;

                nameWrapper.appendChild(span);
                nameWrapper.appendChild(tooltip);

                card.appendChild(nameWrapper);

                // --- APPEND TO GRID ---
                previewContainer.appendChild(card);
            });
        }
    }
});