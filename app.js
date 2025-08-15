class DropLockerApp {
  constructor() {
    // state
    this.currentFileId = null;
    this.currentFileExt = "";
    this.currentFileName = "";
    this.uploadInProgress = false;
    this.downloadInfo = null; // <-- new: store info for download-handling

    // init
    document.addEventListener("DOMContentLoaded", () => {
      this.cacheElements();
      this.setupListeners();
      this.checkDownloadLinkOnLoad();
    });
  }

  cacheElements() {
    // Upload + progress
    this.dropZone = document.getElementById("dropZone");
    this.fileInput = document.getElementById("fileInput");
    this.progressSection = document.getElementById("progressSection");

    this.fileNameLabel = document.getElementById("fileName");
    this.fileSizeLabel = document.getElementById("fileSize");

    this.compressProgress = document.getElementById("compressProgress");
    this.encryptProgress = document.getElementById("encryptProgress");
    this.uploadProgress = document.getElementById("uploadProgress");

    // Link / generation
    this.linkSection = document.getElementById("linkSection");
    this.generateLinkBtn = document.getElementById("generateLink");
    this.shareLinkInput = document.getElementById("shareLink");
    this.copyLinkBtn = document.getElementById("copyLink");
    this.finalLinkBlock = document.getElementById("finalLink");
    this.newUploadBtn = document.getElementById("newUpload");
    this.expirySelect = document.getElementById("expiryTime");
    this.passwordProtect = document.getElementById("passwordProtect");
    this.filePassword = document.getElementById("filePassword");

    // Download UI
    this.downloadSection = document.getElementById("downloadSection");
    this.downloadBtn = document.getElementById("downloadBtn");
    this.downloadFileName = document.getElementById("downloadFileName");
    this.downloadFileInfo = document.getElementById("downloadFileInfo");
    this.passwordSection = document.getElementById("passwordSection");
    this.downloadPassword = document.getElementById("downloadPassword");

    // stats elements
    this.originalSize = document.getElementById("originalSize");
    this.compressedSize = document.getElementById("compressedSize");
    this.compressionRatio = document.getElementById("compressionRatio");

    // toast container
    this.toastContainer = document.getElementById("toastContainer");
  }

  setupListeners() {
    // Drag & drop + click
    if (this.dropZone && this.fileInput) {
      this.dropZone.addEventListener("click", () => {
        if (!this.uploadInProgress) this.fileInput.click();
      });

      this.dropZone.addEventListener("dragover", (e) => {
        e.preventDefault();
        this.dropZone.classList.add("dragover");
      });

      this.dropZone.addEventListener("dragleave", () => {
        this.dropZone.classList.remove("dragover");
      });

      this.dropZone.addEventListener("drop", (e) => {
        e.preventDefault();
        this.dropZone.classList.remove("dragover");
        if (e.dataTransfer.files.length > 0 && !this.uploadInProgress) {
          this.handleFileUpload(e.dataTransfer.files[0]);
        }
      });

      this.fileInput.addEventListener("change", (e) => {
        if (e.target.files.length > 0) {
          this.handleFileUpload(e.target.files[0]);
        }
      });
    }

    // password toggle
    if (this.passwordProtect && this.filePassword) {
      this.passwordProtect.addEventListener("change", () => {
        this.filePassword.classList.toggle(
          "hidden",
          !this.passwordProtect.checked
        );
        if (!this.passwordProtect.checked) this.filePassword.value = "";
      });
    }

    // buttons
    if (this.generateLinkBtn)
      this.generateLinkBtn.addEventListener("click", () =>
        this.generateSecureLink()
      );
    if (this.copyLinkBtn)
      this.copyLinkBtn.addEventListener("click", () =>
        this.copyLinkToClipboard()
      );
    if (this.newUploadBtn)
      this.newUploadBtn.addEventListener("click", () => this.resetToUpload());
    // downloadBtn interaction is handled after info fetch (see checkDownloadLinkOnLoad)
  }

  // Upload with progress
  async handleFileUpload(file) {
    if (this.uploadInProgress) return;
    this.uploadInProgress = true;

    // UI
    this.showProgressSection(file);

    try {
      // Simulate compress step
      for (let i = 0; i <= 100; i += 20) {
        this.setProgressBar(this.compressProgress, i);
        await this.delay(60);
      }

      // Simulate encrypt step
      for (let i = 0; i <= 100; i += 25) {
        this.setProgressBar(this.encryptProgress, i);
        await this.delay(60);
      }

      // Real upload using XHR to get reliable progress
      const formData = new FormData();
      formData.append("file", file);

      await new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open("POST", "upload.php", true);

        xhr.upload.onprogress = (event) => {
          if (event.lengthComputable) {
            const percent = Math.round((event.loaded / event.total) * 100);
            this.setProgressBar(this.uploadProgress, percent);
          }
        };

        xhr.onerror = () => {
          reject(new Error("Network error during upload"));
        };

        xhr.onload = () => {
          if (xhr.status >= 200 && xhr.status < 300) {
            try {
              const res = JSON.parse(xhr.responseText);
              if (res.success) {
                // save metadata
                this.currentFileId = res.fileId;
                this.currentFileExt = res.extension || "";
                this.currentFileName = res.originalName || file.name;

                // show link generation area and stats
                this.showLinkSection(res);
                resolve();
              } else {
                reject(new Error(res.error || "Upload failed"));
              }
            } catch (e) {
              reject(new Error("Invalid server response"));
            }
          } else {
            reject(
              new Error("Upload failed: " + xhr.status + " " + xhr.statusText)
            );
          }
        };

        xhr.send(formData);
      });

      this.showToast("Upload completed", "success");
    } catch (err) {
      this.showToast("Upload failed: " + err.message, "error");
      this.resetToUpload();
    } finally {
      this.uploadInProgress = false;
    }
  }

  // show/hide UI helpers
  showProgressSection(file) {
    if (this.progressSection) this.progressSection.classList.remove("hidden");
    if (this.linkSection) this.linkSection.classList.add("hidden");
    if (this.fileNameLabel) this.fileNameLabel.textContent = file.name;
    if (this.fileSizeLabel)
      this.fileSizeLabel.textContent = this.formatFileSize(file.size);

    // reset bars
    this.setProgressBar(this.compressProgress, 0);
    this.setProgressBar(this.encryptProgress, 0);
    this.setProgressBar(this.uploadProgress, 0);

    // hide final link until generated
    if (this.finalLinkBlock) this.finalLinkBlock.classList.add("hidden");
  }

  showLinkSection(res) {
    if (this.progressSection) this.progressSection.classList.add("hidden");
    if (this.linkSection) this.linkSection.classList.remove("hidden");

    // update stats (upload.php returned sizes)
    if (this.originalSize)
      this.originalSize.textContent = this.formatFileSize(
        res.originalSize || 0
      );
    if (this.compressedSize)
      this.compressedSize.textContent = this.formatFileSize(
        res.compressedSize || 0
      );
    if (this.compressionRatio)
      this.compressionRatio.textContent =
        (res.compressionRatio || 0).toFixed(1) + "%";
  }

  setProgressBar(el, percent) {
    if (!el) return;
    el.style.width = Math.min(100, Math.max(0, percent)) + "%";
  }

  async generateSecureLink() {
    if (!this.currentFileId) {
      this.showToast("No uploaded file to create link for", "error");
      return;
    }

    // collect options
    const expiry = parseInt(this.expirySelect?.value || "0", 10);
    const passwordProtect = this.passwordProtect?.checked || false;
    const password = passwordProtect ? this.filePassword?.value || "" : null;
    if (passwordProtect && !password) {
      this.showToast("Please enter a password", "error");
      return;
    }

    const body = {
      fileId: this.currentFileId,
      extension: this.currentFileExt || "",
      expiry: expiry,
      password: password,
    };

    try {
      const resp = await fetch("generate_link.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(body),
      });
      const json = await resp.json();
      if (!json || !json.success)
        throw new Error(json?.error || "Failed to generate link");

      // server returns `link` (UI link) and `directLink` (download.php link)
      if (this.shareLinkInput) this.shareLinkInput.value = json.link;
      if (this.finalLinkBlock) this.finalLinkBlock.classList.remove("hidden");

      this.showToast("Secure link generated", "success");
    } catch (err) {
      console.error(err);
      this.showToast("Failed to generate link: " + err.message, "error");
    }
  }

  copyLinkToClipboard() {
    const el = this.shareLinkInput;
    if (!el) return;
    el.select();
    el.setSelectionRange(0, 99999);
    try {
      document.execCommand("copy");
      this.showToast("Link copied to clipboard", "success");
    } catch (e) {
      this.showToast("Copy failed", "error");
    }
  }

  resetToUpload() {
    if (this.progressSection) this.progressSection.classList.add("hidden");
    if (this.linkSection) this.linkSection.classList.add("hidden");
    if (this.finalLinkBlock) this.finalLinkBlock.classList.add("hidden");
    if (this.fileInput) this.fileInput.value = "";
    this.currentFileId = null;
    this.currentFileExt = "";
    this.currentFileName = "";
    // reset progress bars
    this.setProgressBar(this.compressProgress, 0);
    this.setProgressBar(this.encryptProgress, 0);
    this.setProgressBar(this.uploadProgress, 0);
  }

  // DOWNLOAD-PAGE:
  async checkDownloadLinkOnLoad() {
    const params = new URLSearchParams(window.location.search);
    const id = params.get("download");
    if (!id) return;

    // show download UI, fetch info
    try {
      // show download section, hide upload section
      const uploadSection = document.getElementById("uploadSection");
      if (uploadSection) uploadSection.classList.remove("active");
      if (this.downloadSection) {
        this.downloadSection.classList.remove("hidden");
        this.downloadSection.classList.add("active");
      }

      // query server for info
      const extParam = params.get("ext") || "";
      const infoResp = await fetch(
        `download.php?id=${encodeURIComponent(id)}&ext=${encodeURIComponent(
          extParam
        )}&info=1`
      );
      if (!infoResp.ok) {
        const text = await infoResp.text();
        throw new Error(text || "File not found");
      }
      const info = await infoResp.json();
      if (!info || !info.success)
        throw new Error(info?.error || "File not found");

      // store for later
      this.downloadInfo = info;

      // populate UI
      if (this.downloadFileName)
        this.downloadFileName.textContent = info.name || id;
      const sizeInfo = `${this.formatFileSize(
        info.originalSize
      )} (compressed: ${this.formatFileSize(info.compressedSize)})`;
      if (this.downloadFileInfo)
        this.downloadFileInfo.innerHTML = `${sizeInfo}<br><small>${
          info.algorithm || "None"
        } • Ratio: ${Number(info.compressionRatio || 0).toFixed(1)}%</small>`;

      // password section
      if (info.hasPassword) {
        if (this.passwordSection)
          this.passwordSection.classList.remove("hidden");
      } else {
        if (this.passwordSection) this.passwordSection.classList.add("hidden");
      }

      // set download link (direct to php/download.php) — but handle password via POST
      const direct = `download.php?id=${encodeURIComponent(id)}${
        info.ext ? "&ext=" + encodeURIComponent(info.ext) : ""
      }`;
      if (this.downloadBtn) {
        this.downloadBtn.setAttribute("href", direct);
        // set filename suggested for download attribute to include the original name
        if (info.name) this.downloadBtn.setAttribute("download", info.name);

        // if password-protected, intercept click and submit form with password (POST)
        this.downloadBtn.onclick = (e) => {
          if (this.downloadInfo && this.downloadInfo.hasPassword) {
            e.preventDefault();
            const pw = this.downloadPassword ? this.downloadPassword.value : "";
            if (!pw) {
              this.showToast("Please enter the password", "error");
              return;
            }
            // create form and submit to download.php using POST
            const form = document.createElement("form");
            form.method = "POST";
            form.action = "download.php";
            form.style.display = "none";

            const fid = document.createElement("input");
            fid.name = "id";
            fid.value = id;
            form.appendChild(fid);

            if (info.ext) {
              const fe = document.createElement("input");
              fe.name = "ext";
              fe.value = info.ext;
              form.appendChild(fe);
            }

            const fp = document.createElement("input");
            fp.name = "password";
            fp.value = pw;
            form.appendChild(fp);

            document.body.appendChild(form);
            form.submit();
          } else {
          }
        };
      }
    } catch (err) {
      console.error(err);
      this.showToast("File not found or expired", "error");
      // redirect back to main upload after a short delay
      setTimeout(() => {
        window.location.href = window.location.pathname;
      }, 2500);
    }
  }

  // small utilities
  showToast(message, type = "info") {
    if (!this.toastContainer) return;
    const t = document.createElement("div");
    t.className = `toast ${type}`;
    t.textContent = message;
    this.toastContainer.appendChild(t);
    setTimeout(() => {
      try {
        t.remove();
      } catch (e) {}
    }, 4500);
  }

  formatFileSize(bytes) {
    if (!bytes && bytes !== 0) return "0 Bytes";
    const unit = 1024;
    if (bytes < unit) return bytes + " B";
    if (bytes < unit * unit) return (bytes / unit).toFixed(2) + " KB";
    if (bytes < unit * unit * unit)
      return (bytes / (unit * unit)).toFixed(2) + " MB";
    return (bytes / (unit * unit * unit)).toFixed(2) + " GB";
  }

  delay(ms) {
    return new Promise((r) => setTimeout(r, ms));
  }
}

// initialize
window.DropLockerApp = new DropLockerApp();
