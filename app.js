// DropLocker JavaScript Application
class DropLockerApp {
    constructor() {
        this.currentFileId = null;
        this.uploadInProgress = false;
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.checkDownloadLink();
    }

    setupEventListeners() {
        // File upload
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('fileInput');
        const passwordProtect = document.getElementById('passwordProtect');
        const filePassword = document.getElementById('filePassword');
        const generateLink = document.getElementById('generateLink');
        const copyLink = document.getElementById('copyLink');
        const newUpload = document.getElementById('newUpload');
        const downloadBtn = document.getElementById('downloadBtn');

        // Drop zone events
        if (dropZone && fileInput) {
            dropZone.addEventListener('click', () => {
                if (!this.uploadInProgress) {
                    fileInput.click();
                }
            });

            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });

            dropZone.addEventListener('dragleave', () => {
                dropZone.classList.remove('dragover');
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
                
                if (e.dataTransfer.files.length > 0 && !this.uploadInProgress) {
                    this.handleFileUpload(e.dataTransfer.files[0]);
                }
            });

            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    this.handleFileUpload(e.target.files[0]);
                }
            });
        }

        // Password protection toggle
        if (passwordProtect && filePassword) {
            passwordProtect.addEventListener('change', () => {
                filePassword.classList.toggle('hidden', !passwordProtect.checked);
                if (!passwordProtect.checked) {
                    filePassword.value = '';
                }
            });
        }

        // Generate link button
        if (generateLink) {
            generateLink.addEventListener('click', () => {
                this.generateSecureLink();
            });
        }

        // Copy link button
        if (copyLink) {
            copyLink.addEventListener('click', () => {
                this.copyLinkToClipboard();
            });
        }

        // New upload button
        if (newUpload) {
            newUpload.addEventListener('click', () => {
                this.resetToUpload();
            });
        }

        // Download button
        if (downloadBtn) {
            downloadBtn.addEventListener('click', () => {
                this.downloadFile();
            });
        }
    }

    async handleFileUpload(file) {
        if (this.uploadInProgress) return;
        
        this.uploadInProgress = true;
        
        try {
            // Update UI to show progress
            this.showProgressSection(file);
            
            // Create form data
            const formData = new FormData();
            formData.append('file', file);

            // Simulate compression progress
            this.updateProgress('compress', 0);
            await this.delay(500);
            
            for (let i = 0; i <= 100; i += 10) {
                this.updateProgress('compress', i);
                await this.delay(100);
            }

            // Start upload with compression
            this.updateProgress('encrypt', 0);
            this.updateProgress('upload', 0);

            const response = await fetch('php/upload.php', {
                method: 'POST',
                body: formData
            });

            // Simulate encryption progress
            for (let i = 0; i <= 100; i += 20) {
                this.updateProgress('encrypt', i);
                await this.delay(150);
            }

            // Simulate upload progress
            for (let i = 0; i <= 100; i += 25) {
                this.updateProgress('upload', i);
                await this.delay(200);
            }

            const result = await response.json();

            if (result.success) {
                this.currentFileId = result.fileId;
                this.showLinkSection(result);
            } else {
                throw new Error(result.error || 'Upload failed');
            }

        } catch (error) {
            this.showToast('Upload failed: ' + error.message, 'error');
            this.resetToUpload();
        } finally {
            this.uploadInProgress = false;
        }
    }

    showProgressSection(file) {
        // Hide upload section, show progress
        document.getElementById('uploadSection').querySelector('.drop-zone').style.display = 'none';
        
        const progressSection = document.getElementById('progressSection');
        progressSection.classList.remove('hidden');

        // Update file info
        document.getElementById('fileName').textContent = file.name;
        document.getElementById('fileSize').textContent = this.formatFileSize(file.size);
    }

    updateProgress(step, percentage) {
        const progressBar = document.getElementById(step + 'Progress');
        if (progressBar) {
            progressBar.style.width = percentage + '%';
        }

        // Update step icon
        const steps = document.querySelectorAll('.progress-step');
        steps.forEach((stepEl, index) => {
            const stepNames = ['compress', 'encrypt', 'upload'];
            const stepIcon = stepEl.querySelector('.step-icon');
            
            if (stepNames[index] === step && percentage > 0) {
                stepIcon.classList.add('active');
            }
        });
    }

    showLinkSection(result) {
        // Hide progress section, show link section
        document.getElementById('progressSection').classList.add('hidden');
        document.getElementById('linkSection').classList.remove('hidden');

        // Update compression stats
        document.getElementById('originalSize').textContent = this.formatFileSize(result.originalSize);
        document.getElementById('compressedSize').textContent = this.formatFileSize(result.compressedSize);
        document.getElementById('compressionRatio').textContent = result.compressionRatio.toFixed(1) + '%';

        // Show algorithm used
        if (result.algorithm) {
            const algorithmInfo = document.createElement('div');
            algorithmInfo.className = 'stat';
            algorithmInfo.innerHTML = `
                <span class="stat-label">Algorithm:</span>
                <span class="stat-value">${result.algorithm}</span>
            `;
            document.querySelector('.compression-stats').appendChild(algorithmInfo);
        }
    }

    async generateSecureLink() {
        if (!this.currentFileId) return;

        try {
            const expiryTime = document.getElementById('expiryTime').value;
            const passwordProtect = document.getElementById('passwordProtect').checked;
            const password = document.getElementById('filePassword').value;

            // Validate password if protection is enabled
            if (passwordProtect && !password.trim()) {
                this.showToast('Please enter a password', 'error');
                return;
            }

            // Generate the secure link
            const linkData = {
                fileId: this.currentFileId,
                expiryTime: parseInt(expiryTime),
                password: passwordProtect ? password : null
            };

            const response = await fetch('php/generate_link.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(linkData)
            });

            const result = await response.json();

            if (result.success) {
                this.showFinalLink(result.link);
            } else {
                throw new Error(result.error);
            }

        } catch (error) {
            this.showToast('Failed to generate link: ' + error.message, 'error');
        }
    }

    showFinalLink(link) {
        const finalLinkSection = document.getElementById('finalLink');
        const shareLinkInput = document.getElementById('shareLink');
        
        shareLinkInput.value = window.location.origin + window.location.pathname + '?download=' + this.currentFileId;
        finalLinkSection.classList.remove('hidden');
    }

    copyLinkToClipboard() {
        const linkInput = document.getElementById('shareLink');
        linkInput.select();
        linkInput.setSelectionRange(0, 99999); // For mobile devices
        
        try {
            document.execCommand('copy');
            this.showToast('Link copied to clipboard!', 'success');
        } catch (error) {
            this.showToast('Failed to copy link', 'error');
        }
    }

    resetToUpload() {
        // Reset all sections
        document.getElementById('progressSection').classList.add('hidden');
        document.getElementById('linkSection').classList.add('hidden');
        document.getElementById('finalLink').classList.add('hidden');
        
        // Show upload section
        document.getElementById('uploadSection').querySelector('.drop-zone').style.display = 'block';
        
        // Reset form
        document.getElementById('fileInput').value = '';
        document.getElementById('passwordProtect').checked = false;
        document.getElementById('filePassword').classList.add('hidden');
        document.getElementById('filePassword').value = '';
        
        // Reset progress bars
        const progressBars = document.querySelectorAll('.progress-fill');
        progressBars.forEach(bar => bar.style.width = '0%');
        
        // Reset step icons
        const stepIcons = document.querySelectorAll('.step-icon');
        stepIcons.forEach(icon => icon.classList.remove('active'));
        
        this.currentFileId = null;
        this.uploadInProgress = false;
    }

    checkDownloadLink() {
        const urlParams = new URLSearchParams(window.location.search);
        const downloadId = urlParams.get('download');
        
        if (downloadId) {
            this.switchToDownloadMode(downloadId);
        }
    }

    async switchToDownloadMode(fileId) {
        // Hide upload section, show download section
        document.getElementById('uploadSection').classList.remove('active');
        document.getElementById('downloadSection').classList.add('active');

        try {
            // Get file information
            const response = await fetch(`php/download.php?id=${fileId}&info=1`);
            const result = await response.json();

            if (result.success) {
                this.setupDownloadInterface(fileId, result);
            } else {
                throw new Error(result.error);
            }

        } catch (error) {
            this.showToast('File not found or expired', 'error');
            // Redirect to upload page after delay
            setTimeout(() => {
                window.location.href = window.location.pathname;
            }, 3000);
        }
    }

    setupDownloadInterface(fileId, fileInfo) {
        document.getElementById('downloadFileName').textContent = fileInfo.name;
        
        const sizeInfo = `${this.formatFileSize(fileInfo.originalSize)} (compressed from ${this.formatFileSize(fileInfo.compressedSize)})`;
        const algorithmInfo = `Compressed with ${fileInfo.algorithm} • Compression ratio: ${fileInfo.compressionRatio.toFixed(1)}%`;
        
        document.getElementById('downloadFileInfo').innerHTML = `
            ${sizeInfo}<br>
            <small>${algorithmInfo}</small>
        `;

        // Show password section if needed
        if (fileInfo.hasPassword) {
            document.getElementById('passwordSection').classList.remove('hidden');
        }

        // Store file ID for download
        this.currentDownloadId = fileId;
    }

    async downloadFile() {
        if (!this.currentDownloadId) return;

        try {
            const password = document.getElementById('downloadPassword')?.value || null;
            
            // Show download progress
            document.getElementById('downloadForm').classList.add('hidden');
            document.getElementById('downloadProgress').classList.remove('hidden');

            // Simulate download progress
            await this.simulateDownloadProgress();

            // Actually download the file
            const response = await fetch('php/download.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id: this.currentDownloadId,
                    password: password
                })
            });

            if (response.ok) {
                // The file will be downloaded automatically
                this.showDownloadComplete();
            } else {
                const error = await response.json();
                throw new Error(error.error);
            }

        } catch (error) {
            this.showToast('Download failed: ' + error.message, 'error');
            // Reset download form
            document.getElementById('downloadForm').classList.remove('hidden');
            document.getElementById('downloadProgress').classList.add('hidden');
        }
    }

    async simulateDownloadProgress() {
        const steps = ['downloadProgressBar', 'decryptProgressBar', 'decompressProgressBar'];
        
        for (let step of steps) {
            for (let i = 0; i <= 100; i += 10) {
                document.getElementById(step).style.width = i + '%';
                await this.delay(100);
            }
        }
    }

    showDownloadComplete() {
        document.getElementById('downloadProgress').classList.add('hidden');
        document.getElementById('downloadComplete').classList.remove('hidden');
    }

    // Utility functions
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    showToast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.textContent = message;
        
        container.appendChild(toast);
        
        // Remove toast after 5 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 5000);
    }

    delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

// Initialize the application
document.addEventListener('DOMContentLoaded', () => {
    new DropLockerApp();
});
