/**
 * Screenshot Capture for Template LayoutImage
 * 
 * Uses html2canvas to capture content-only preview and uploads as LayoutImage.
 * Requires html2canvas library (loaded from CDN if not available).
 */
(function () {
    'use strict';

    // Load html2canvas from CDN if not available
    function loadHtml2Canvas() {
        return new Promise((resolve, reject) => {
            if (window.html2canvas) {
                resolve(window.html2canvas);
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
            script.onload = () => resolve(window.html2canvas);
            script.onerror = () => reject(new Error('Failed to load html2canvas'));
            document.head.appendChild(script);
        });
    }

    // Create and show modal with preview iframe
    function showCaptureModal(previewUrl, templateId, uploadUrl, securityId) {
        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'screenshot-capture-overlay';
        overlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 10000;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        `;

        // Create modal container
        const modal = document.createElement('div');
        modal.className = 'screenshot-capture-modal';
        modal.style.cssText = `
            background: white;
            border-radius: 8px;
            max-width: 90vw;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        `;

        // Create header
        const header = document.createElement('div');
        header.style.cssText = `
            padding: 16px 20px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        `;
        header.innerHTML = `
            <h3 style="margin: 0; font-size: 18px; color: #333;">Capture Preview Image</h3>
            <button type="button" class="close-btn" style="border: none; background: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
        `;

        // Create iframe container - scrollable to show desktop preview
        const iframeContainer = document.createElement('div');
        iframeContainer.style.cssText = `
            flex: 1;
            overflow: auto;
            padding: 20px;
            min-height: 400px;
            max-height: 600px;
        `;

        // Use lg breakpoint width (1400px) to ensure desktop 3-column layout
        const iframe = document.createElement('iframe');
        iframe.src = previewUrl;
        iframe.style.cssText = `
            width: 1400px;
            min-width: 1400px;
            height: 900px;
            border: 1px solid #ddd;
            border-radius: 4px;
            transform-origin: top left;
        `;
        iframeContainer.appendChild(iframe);

        // Create footer with action buttons
        const footer = document.createElement('div');
        footer.style.cssText = `
            padding: 16px 20px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        `;

        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.textContent = 'Cancel';
        cancelBtn.style.cssText = `
            padding: 8px 16px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 4px;
            cursor: pointer;
        `;

        const captureBtn = document.createElement('button');
        captureBtn.type = 'button';
        captureBtn.textContent = 'Capture Screenshot';
        captureBtn.className = 'btn-primary';
        captureBtn.style.cssText = `
            padding: 8px 16px;
            border: none;
            background: #0071c4;
            color: white;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        `;
        captureBtn.disabled = true;

        const statusText = document.createElement('span');
        statusText.style.cssText = `
            flex: 1;
            color: #666;
            font-size: 14px;
        `;
        statusText.textContent = 'Loading preview...';

        footer.appendChild(statusText);
        footer.appendChild(cancelBtn);
        footer.appendChild(captureBtn);

        // Assemble modal
        modal.appendChild(header);
        modal.appendChild(iframeContainer);
        modal.appendChild(footer);
        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        // Enable capture button when iframe loads
        iframe.onload = function () {
            captureBtn.disabled = false;
            statusText.textContent = 'Ready to capture';
        };

        // Close handlers
        function closeModal() {
            overlay.remove();
        }

        header.querySelector('.close-btn').onclick = closeModal;
        cancelBtn.onclick = closeModal;
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeModal();
        });

        // Capture handler
        captureBtn.onclick = async function () {
            captureBtn.disabled = true;
            statusText.textContent = 'Capturing screenshot...';

            try {
                await loadHtml2Canvas();

                // Access iframe content
                const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
                const content = iframeDoc.querySelector('.template-preview-container') || iframeDoc.body;

                // Capture with html2canvas at desktop width
                const canvas = await html2canvas(content, {
                    useCORS: true,
                    allowTaint: true,
                    scale: 1,
                    backgroundColor: '#ffffff',
                    logging: false,
                    windowWidth: 1400, // Force lg breakpoint width for desktop layout
                    width: 1400, // Fixed width to match preview and prevent horizontal overflow
                });

                statusText.textContent = 'Uploading...';

                // Convert to base64
                const imageData = canvas.toDataURL('image/png');

                // Upload to server
                const formData = new FormData();
                formData.append('templateID', templateId);
                formData.append('screenshot', imageData);
                formData.append('SecurityID', securityId);

                const response = await fetch(uploadUrl, {
                    method: 'POST',
                    body: formData,
                });

                const result = await response.json();

                if (result.success) {
                    statusText.textContent = 'Screenshot saved successfully!';
                    statusText.style.color = '#28a745';

                    setTimeout(() => {
                        closeModal();
                        // Reload the form to show updated image
                        window.location.reload();
                    }, 1500);
                } else {
                    throw new Error(result.error || 'Upload failed');
                }
            } catch (error) {
                console.error('Screenshot capture failed:', error);
                statusText.textContent = 'Error: ' + error.message;
                statusText.style.color = '#dc3545';
                captureBtn.disabled = false;
            }
        };
    }

    // Bind to CMS actions using Entwine
    if (typeof jQuery !== 'undefined' && jQuery.entwine) {
        jQuery.entwine('ss', function ($) {
            // LeKoala CMS Actions uses a different name pattern
            $('button[name="action_doCustomAction[CaptureScreenshot]"]').entwine({
                onclick: function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const btn = this[0];
                    const previewUrl = btn.dataset.previewUrl;
                    const templateId = btn.dataset.templateId;
                    const uploadUrl = btn.dataset.uploadUrl;
                    const securityId = btn.dataset.securityId;

                    if (!previewUrl || !templateId) {
                        console.error('Missing required data attributes for screenshot capture');
                        return false;
                    }

                    showCaptureModal(previewUrl, templateId, uploadUrl, securityId);
                    return false;
                }
            });
        });
    }
})();
