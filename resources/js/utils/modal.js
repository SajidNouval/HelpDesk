/**
 * Modal Module
 * Centralized modal open/close/overlay handling
 */

/**
 * Disable body scroll when modal is open
 */
export function disableBodyScroll() {
    if (!document.body) {
        return;
    }
    document.body.style.overflow = 'hidden';
}

/**
 * Enable body scroll when modal is closed
 */
export function enableBodyScroll() {
    if (!document.body) {
        return;
    }
    document.body.style.overflow = '';
}

/**
 * Open a modal element
 * @param {HTMLElement} modal - The modal element to open
 */
export function openModal(modal) {
    if (!(modal instanceof HTMLElement)) {
        return;
    }

    modal.classList.remove('hidden');
    disableBodyScroll();
}

/**
 * Close a modal element
 * @param {HTMLElement} modal - The modal element to close
 */
export function closeModal(modal) {
    if (!(modal instanceof HTMLElement)) {
        return;
    }

    modal.classList.add('hidden');
    enableBodyScroll();
}

/**
 * Open a modal by its ID
 * @param {string} id - The ID of the modal element
 */
export function openModalById(id) {
    if (!id) {
        return;
    }

    const modal = document.getElementById(id);
    if (!(modal instanceof HTMLElement)) {
        return;
    }

    openModal(modal);
}

/**
 * Close a modal by its ID
 * @param {string} id - The ID of the modal element
 */
export function closeModalById(id) {
    if (!id) {
        return;
    }

    const modal = document.getElementById(id);
    if (!(modal instanceof HTMLElement)) {
        return;
    }

    closeModal(modal);
}

/**
 * Set form action URL and article ID on a modal form
 * @param {HTMLElement} modal - The modal element
 * @param {string} pattern - URL pattern with {id} placeholder
 * @param {string} articleId - The article ID to insert
 */
export function setModalFormAction(modal, pattern, articleId) {
    if (!(modal instanceof HTMLElement) || !pattern || !articleId) {
        return;
    }

    const form = modal.querySelector('form');
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    form.action = pattern.replace('{id}', articleId);
    form.dataset.articleId = articleId;
}

/**
 * Initialize global modal keyboard handler
 * Handles: escape key to close open modals
 * Note: click handling (open/close/backdrop) is consolidated in shared/ui.js
 */
export function initModalHandlers() {
    // Escape key handler for modals
    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') {
            return;
        }

        const openModals = document.querySelectorAll('[data-modal]:not(.hidden)');
        openModals.forEach((modal) => {
            if (modal instanceof HTMLElement) {
                closeModal(modal);
            }
        });
    });
}