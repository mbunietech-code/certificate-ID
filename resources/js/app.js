/**
 * Small progressive enhancements shared by every page (no framework needed):
 *  - data-confirm      : confirm() before submitting a form / following a link
 *  - data-check-all    : master checkbox toggling every [data-check-item] in the same form
 *  - data-autosubmit   : submit the parent form when a filter <select> changes
 *  - data-dismiss      : remove an alert
 *  - data-menu-toggle  : toggle a dropdown / mobile sidebar target
 *  - data-poll-url     : print job progress polling
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
