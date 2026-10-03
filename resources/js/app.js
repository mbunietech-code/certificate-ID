/**
 * Small progressive enhancements shared by every page (no framework needed):
 *  - data-confirm      : confirm() before submitting a form / following a link
 *  - data-check-all    : master checkbox toggling every [data-check-item] in the same form
 *  - data-autosubmit   : submit the parent form when a filter <select> changes
 *  - data-dismiss      : remove an alert
 *  - data-menu-toggle  : toggle a dropdown / mobile sidebar target
 *  - data-poll-url     : print job progress polling
 *  - data-camera-field : capture an image into a file input
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
    const stop = field.querySelector('[data-camera-stop]');
    const error = field.querySelector('[data-camera-error]');
    let stream = null;

    const drawPassportCrop = () => {
        const targetSize = 900;
        const videoRatio = video.videoWidth / video.videoHeight;
        const guideRatio = 1;
        let sourceW = video.videoWidth;
        let sourceH = video.videoHeight;

        if (videoRatio > guideRatio) {
            sourceW = Math.round(video.videoHeight * guideRatio);
        } else {
            sourceH = Math.round(video.videoWidth / guideRatio);
        }

        const sourceX = Math.round((video.videoWidth - sourceW) / 2);
        const sourceY = Math.round((video.videoHeight - sourceH) / 2);
        canvas.width = targetSize;
        canvas.height = targetSize;

        const context = canvas.getContext('2d');
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, targetSize, targetSize);
        context.drawImage(video, sourceX, sourceY, sourceW, sourceH, 0, 0, targetSize, targetSize);
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
        if (stop) stop.hidden = true;
        if (start) start.hidden = false;
    };

    start?.addEventListener('click', async () => {
        error.hidden = true;
        if (!navigator.mediaDevices?.getUserMedia) {
            showError('Camera is not available in this browser.');
            return;
        }

        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
            video.srcObject = stream;
            panel.hidden = false;
            start.hidden = true;
            capture.hidden = false;
            stop.hidden = false;
        } catch (err) {
            showError('Camera permission was denied or no camera was found.');
        }
    });

    capture?.addEventListener('click', () => {
        if (!stream || !video.videoWidth || !video.videoHeight) {
            showError('Camera is not ready yet.');
            return;
        }

        drawPassportCrop();

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
            stopCamera();
        }, 'image/jpeg', 0.9);
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
