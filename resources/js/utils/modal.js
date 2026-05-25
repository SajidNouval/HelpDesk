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
 * Initialize global modal event handlers
 * Handles: open buttons, close buttons, backdrop clicks, escape key
 */
export function initModalHandlers() {
    // Click handler for modal interactions
    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        // Handle modal open buttons
        const openButton = target.closest('[data-open-modal]');
        if (openButton instanceof HTMLElement) {
            event.preventDefault();
            const modalSelector = openButton.dataset.openModal;
            const modal = modalSelector ? document.querySelector(modalSelector) : null;
            if (!(modal instanceof HTMLElement)) {
                return;
            }

            const actionPattern = openButton.dataset.modalFormAction;
            const articleId = openButton.dataset.articleId;
            if (actionPattern && articleId) {
                setModalFormAction(modal, actionPattern, articleId);
            }

            openModal(modal);
            return;
        }

        // Handle modal close buttons (only if inside a modal)
        const closeButton = target.closest('[data-close-modal]');
        if (closeButton instanceof HTMLElement) {
            event.preventDefault();
            const modal = closeButton.closest('[data-modal]');
            if (modal instanceof HTMLElement) {
                closeModal(modal);
            }
            return;
        }

        // Handle modal backdrop clicks (click on overlay to close modal)
        const modal = target.closest('[data-modal]');
        if (modal instanceof HTMLElement && target === modal && !modal.classList.contains('hidden')) {
            closeModal(modal);
            return;
        }
    });

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