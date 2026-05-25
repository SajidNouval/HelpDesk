document.addEventListener('DOMContentLoaded', () => {
    const articlePageRoot = document.querySelector('[data-admin-page="articles"]') || document.querySelector('[data-open-modal][data-modal-form-action]');
    if (!articlePageRoot) {
        return;
    }

    // Admin article pages use shared UI helpers in ui.js for AJAX and modal controls.
});