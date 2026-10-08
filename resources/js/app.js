/**
 * Small progressive enhancements shared by every page (no framework needed):
 *  - data-confirm      : confirm() before submitting a form / following a link
 *  - data-check-all    : master checkbox toggling every [data-check-item] in the same form
 *  - data-autosubmit   : submit the parent form when a filter <select> changes
 *  - data-dismiss      : remove an alert
 *  - data-menu-toggle  : toggle a dropdown / mobile sidebar target
 *  - data-poll-url     : print job progress polling
 *  - data-camera-field : capture an image into a file input
 *  - data-export-selected : export link that sends only the checked [data-check-item] rows (all rows when none)
 */

document.addEventListener('submit', (e) => {
    const form = e.target;
    const message = form.dataset.confirm || e.submitter?.dataset.confirm;
    if (message && !window.confirm(message)) {
        e.preventDefault();
    }
});

document.addEventListener('click', (e) => {
    const link = e.target.closest('a[data-confirm]');
    if (link && !window.confirm(link.dataset.confirm)) {
        e.preventDefault();
    }

    const dismiss = e.target.closest('[data-dismiss]');
    if (dismiss) {
        dismiss.closest('.alert')?.remove();
    }

    const exportLink = e.target.closest('a[data-export-selected]');
    if (exportLink) {
        const checked = [...document.querySelectorAll('[data-check-item]:checked')];
        if (checked.length) {
            e.preventDefault();
            const url = new URL(exportLink.href);
            checked.forEach((cb) => url.searchParams.append('ids[]', cb.value));
            window.location.href = url.toString();
        }
    }

    const toggle = e.target.closest('[data-menu-toggle]');
    if (toggle) {
        document.getElementById(toggle.dataset.menuToggle)?.toggleAttribute('hidden');
    } else {
        document.querySelectorAll('[data-menu]').forEach((menu) => {
            if (!menu.contains(e.target)) {
                menu.setAttribute('hidden', '');
            }
        });
    }
});

document.addEventListener('change', (e) => {
    const master = e.target.closest('[data-check-all]');
    if (master) {
        const scope = master.closest('form') || document;
        scope.querySelectorAll('[data-check-item]').forEach((cb) => {
            cb.checked = master.checked;
        });
    }

    if (e.target.matches('[data-check-item], [data-check-all]')) {
        const scope = e.target.closest('form') || document;
        const count = scope.querySelectorAll('[data-check-item]:checked').length;
        scope.querySelectorAll('[data-selected-count]').forEach((el) => {
            el.textContent = count;
        });
        document.querySelectorAll('[data-export-scope]').forEach((el) => {
            el.textContent = count ? `${count} selected only` : el.dataset.exportScope;
        });
    }

    if (e.target.matches('[data-autosubmit]')) {
        e.target.form?.requestSubmit();
    }

    if (e.target.matches('[data-camera-input]')) {
        previewSelectedImage(e.target);
    }
});

const previewSelectedImage = (input) => {
    const file = input.files?.[0];
    if (!file) return;

    const field = input.closest('[data-camera-field]');
    const preview = field?.querySelector('[data-camera-preview]');
    const placeholder = field?.querySelector('[data-camera-placeholder]');
    if (!preview) return;

    preview.src = URL.createObjectURL(file);
    preview.classList.remove('hidden');
    placeholder?.classList.add('hidden');

    const remove = field.querySelector('input[name^="remove_"]');
    if (remove) remove.checked = false;
};

document.querySelectorAll('[data-camera-field]').forEach((field) => {
    const input = field.querySelector('[data-camera-input]');
    const panel = field.querySelector('[data-camera-panel]');
    const video = field.querySelector('[data-camera-video]');
    const canvas = field.querySelector('[data-camera-canvas]');
    const start = field.querySelector('[data-camera-start]');
    const capture = field.querySelector('[data-camera-capture]');
    const switchCamera = field.querySelector('[data-camera-switch]');
    const stop = field.querySelector('[data-camera-stop]');
    const error = field.querySelector('[data-camera-error]');
    const nativeCamera = field.querySelector('[data-camera-native]');
    let stream = null;
    let facingMode = 'environment';

    // ID photos are 455 × 488 px (the server stores exactly that size); capture at 2× for quality.
    const drawPassportCrop = (source, width, height) => {
        const targetWidth = 910;
        const targetHeight = 976;
        const sourceRatio = width / height;
        const guideRatio = 455 / 488;
        let sourceW = width;
        let sourceH = height;

        if (sourceRatio > guideRatio) {
            sourceW = Math.round(height * guideRatio);
        } else {
            sourceH = Math.round(width / guideRatio);
        }

        const sourceX = Math.round((width - sourceW) / 2);
        const sourceY = Math.round((height - sourceH) / 2);
        canvas.width = targetWidth;
        canvas.height = targetHeight;

        const context = canvas.getContext('2d');
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, targetWidth, targetHeight);
        context.drawImage(source, sourceX, sourceY, sourceW, sourceH, 0, 0, targetWidth, targetHeight);
    };

    // Puts the cropped canvas into the form's file input as a small JPEG.
    const useCanvasPhoto = (done) => {
        canvas.toBlob((blob) => {
            if (!blob) {
                showError('Could not capture the photo.');
                return;
            }

            const file = new File([blob], `student-photo-${Date.now()}.jpg`, { type: 'image/jpeg' });
            const transfer = new DataTransfer();
            transfer.items.add(file);
            input.files = transfer.files;
            previewSelectedImage(input);
            done?.();
        }, 'image/jpeg', 0.9);
    };

    const showError = (message) => {
        if (!error) return;
        error.textContent = message;
        error.hidden = false;
    };

    const stopCamera = () => {
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        if (video) video.srcObject = null;
        if (panel) panel.hidden = true;
        if (capture) capture.hidden = true;
        if (switchCamera) switchCamera.hidden = true;
        if (stop) stop.hidden = true;
        if (start) start.hidden = false;
    };

    const startCamera = async () => {
        error.hidden = true;
        // Live camera needs HTTPS (or localhost). On a phone opening the site over the
        // network (http://192.168…), fall back to the phone's own camera app instead.
        if (!navigator.mediaDevices?.getUserMedia) {
            if (nativeCamera) {
                nativeCamera.click();
            } else {
                showError('Camera is not available in this browser.');
            }
            return;
        }

        try {
            stream?.getTracks().forEach((track) => track.stop());
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: facingMode } }, audio: false });
            video.srcObject = stream;
            panel.hidden = false;
            start.hidden = true;
            capture.hidden = false;
            switchCamera.hidden = false;
            stop.hidden = false;
        } catch (err) {
            if (facingMode === 'environment') {
                facingMode = 'user';
                try {
                    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: facingMode } }, audio: false });
                    video.srcObject = stream;
                    panel.hidden = false;
                    start.hidden = true;
                    capture.hidden = false;
                    switchCamera.hidden = false;
                    stop.hidden = false;
                    return;
                } catch (fallbackErr) {
                    // Show the generic camera error below.
                }
            }
            showError('Camera permission was denied or no camera was found.');
        }
    };

    start?.addEventListener('click', async () => {
        await startCamera();
    });

    switchCamera?.addEventListener('click', async () => {
        facingMode = facingMode === 'environment' ? 'user' : 'environment';
        await startCamera();
    });

    capture?.addEventListener('click', () => {
        if (!stream || !video.videoWidth || !video.videoHeight) {
            showError('Camera is not ready yet.');
            return;
        }

        drawPassportCrop(video, video.videoWidth, video.videoHeight);
        useCanvasPhoto(stopCamera);
    });

    // Photo taken with the phone's camera app: crop it to the ID shape and shrink it before upload.
    nativeCamera?.addEventListener('change', () => {
        const file = nativeCamera.files?.[0];
        if (!file) return;

        const image = new Image();
        const url = URL.createObjectURL(file);
        image.onload = () => {
            drawPassportCrop(image, image.naturalWidth, image.naturalHeight);
            URL.revokeObjectURL(url);
            nativeCamera.value = '';
            useCanvasPhoto();
        };
        image.onerror = () => {
            URL.revokeObjectURL(url);
            showError('Could not read the photo from the camera.');
        };
        image.src = url;
    });

    stop?.addEventListener('click', stopCamera);
});

/** Print job progress: reloads the page when the job finishes. */
const poll = document.querySelector('[data-poll-url]');
if (poll) {
    const bar = poll.querySelector('[data-progress-bar]');
    const label = poll.querySelector('[data-progress-label]');
    const waiting = poll.querySelector('[data-waiting]');
    const tick = async () => {
        try {
            const res = await fetch(poll.dataset.pollUrl, { headers: { Accept: 'application/json' } });
            const data = await res.json();
            if (bar) bar.style.width = `${data.percent}%`;
            if (label) label.textContent = `${data.completed + data.failed} / ${data.total} (${data.percent}%)`;
            if (waiting) waiting.hidden = !data.waiting;
            if (data.finished) {
                window.location.reload();
                return;
            }
        } catch (err) {
            // Network hiccup: keep polling.
        }
        setTimeout(tick, 2000);
    };
    setTimeout(tick, 1500);
}
