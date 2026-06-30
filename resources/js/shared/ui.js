/**
 * Shared UI Module
 * Global UI handlers for AJAX forms, modals, and article management
 */

import {
    getCsrfToken,
    showNotification,
    openModal,
    closeModal,
    setModalFormAction,
    closeModalById,
    showSuccessToast,
    closeSuccessToast,
    showFormValidationErrors,
    clearFormValidationErrors,
    setButtonLoading,
    setFormLoading
} from './dom';
import { safeFetch } from '../utils/http';
import { initModalHandlers } from '../utils/modal';

/**
 * Update status badge on article row
 * @param {HTMLElement} row - The table row
 * @param {string} status - The publish status
 */
function updateStatusBadge(row, status) {
    if (!(row instanceof HTMLElement)) {
        return;
    }

    const statusEl = row.querySelector('.article-status');
    if (!(statusEl instanceof HTMLElement)) {
        return;
    }

    statusEl.className = 'article-status px-3 py-1 rounded-full text-xs font-bold border';

    if (status === 'pending') {
        statusEl.classList.add('bg-yellow-100', 'text-yellow-800', 'border-yellow-300');
        statusEl.textContent = 'Menunggu';
    } else if (status === 'approved') {
        statusEl.classList.add('bg-green-100', 'text-green-800', 'border-green-300');
        statusEl.textContent = 'Disetujui';
    } else if (status === 'rejected') {
        statusEl.classList.add('bg-red-100', 'text-red-800', 'border-red-300');
        statusEl.textContent = 'Ditolak';
    }
}

/**
 * Update visibility badge on article row
 * @param {HTMLElement} row - The table row
 * @param {boolean} isHidden - Whether the article is hidden
 */
function updateVisibilityBadge(row, isHidden) {
    if (!(row instanceof HTMLElement)) {
        return;
    }

    const visibilityEl = row.querySelector('.article-visibility');
    if (!(visibilityEl instanceof HTMLElement)) {
        return;
    }

    visibilityEl.className = 'article-visibility px-2 py-1 rounded text-xs font-bold border';
    if (isHidden) {
        visibilityEl.classList.add('bg-gray-200', 'text-gray-800', 'border-gray-300');
        visibilityEl.textContent = 'Disembunyikan';
    } else {
        visibilityEl.classList.add('bg-red-100', 'text-red-800', 'border-red-300');
        visibilityEl.textContent = 'Publik';
    }
}

/**
 * Update action visibility on article row based on publish status
 * @param {HTMLElement} row - The table row
 * @param {string} publishStatus - The publish status
 */
function updateActionVisibility(row, publishStatus) {
    if (!(row instanceof HTMLElement)) {
        return;
    }

    const pendingBlock = row.querySelector('.article-action-pending');
    const rejectedBlock = row.querySelector('.article-action-rejected');
    const approvedBlock = row.querySelector('.article-action-approved');

    if (pendingBlock instanceof HTMLElement) {
        pendingBlock.classList.toggle('hidden', publishStatus !== 'pending');
    }
    if (rejectedBlock instanceof HTMLElement) {
        rejectedBlock.classList.toggle('hidden', publishStatus !== 'rejected');
    }
    if (approvedBlock instanceof HTMLElement) {
        approvedBlock.classList.toggle('hidden', publishStatus !== 'approved');
    }
}

/**
 * Update toggle button text and style on article row
 * @param {HTMLElement} row - The table row
 * @param {boolean} isHidden - Whether the article is hidden
 */
function updateToggleButton(row, isHidden) {
    if (!(row instanceof HTMLElement)) {
        return;
    }

    const toggleButton = row.querySelector('.toggle-visibility-button');
    if (!(toggleButton instanceof HTMLElement)) {
        return;
    }

    toggleButton.textContent = isHidden ? 'Tampilkan' : 'Sembunyikan';
    toggleButton.className = 'toggle-visibility-button inline-flex items-center text-xs px-2 py-1 rounded shadow hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-yellow-400 transition';

    if (isHidden) {
        toggleButton.classList.add('bg-red-600', 'text-white', 'hover:bg-red-700');
    } else {
        toggleButton.classList.add('bg-yellow-600', 'text-white', 'hover:bg-yellow-700');
    }
}

/**
 * Update article row with new data from API response
 * @param {string} articleId - The article ID
 * @param {Object} payload - The article data
 */
function updateArticleRow(articleId, payload) {
    if (!articleId || typeof payload !== 'object' || payload === null) {
        return;
    }

    const row = document.querySelector(`tr[data-article-id="${articleId}"]`);
    if (!(row instanceof HTMLElement)) {
        return;
    }

    if (payload.views !== undefined) {
        const viewsEl = row.querySelector('.article-views');
        if (viewsEl instanceof HTMLElement) {
            viewsEl.textContent = payload.views;
        }
    }

    if (payload.helpful_count !== undefined) {
        const helpfulEl = row.querySelector('.article-helpful');
        if (helpfulEl instanceof HTMLElement) {
            helpfulEl.textContent = payload.helpful_count;
        }
    }

    if (payload.not_helpful_count !== undefined) {
        const notHelpfulEl = row.querySelector('.article-not-helpful');
        if (notHelpfulEl instanceof HTMLElement) {
            notHelpfulEl.textContent = payload.not_helpful_count;
        }
    }

    if (payload.publish_status !== undefined) {
        updateStatusBadge(row, payload.publish_status);
        updateActionVisibility(row, payload.publish_status);
    }

    if (payload.is_hidden !== undefined) {
        updateVisibilityBadge(row, payload.is_hidden);
        updateToggleButton(row, payload.is_hidden);
    }
}

/**
 * Send AJAX form data and return response
 * @param {HTMLFormElement} form - The form element
 * @returns {Promise<Object|null>}
 */
async function sendAjaxForm(form) {
    if (!(form instanceof HTMLFormElement)) {
        return null;
    }

    const url = form.action;
    if (!url) {
        showNotification('Form action tidak ditemukan.', 'error');
        return null;
    }

    const method = (form.method || 'POST').toUpperCase();
    const formData = new FormData(form);

    try {
        const response = await safeFetch(url, {
            method,
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
            },
            body: formData,
        });

        if (!response.ok) {
            if (response.status === 422 && response.data?.errors) {
                showFormValidationErrors(form, response.data.errors);
            }
            throw new Error(response.data?.message || 'Terjadi kesalahan jaringan.');
        }

        return response.data;
    } catch (error) {
        showNotification(error?.message || 'Gagal mengirim permintaan.', 'error');
        return null;
    }
}

/**
 * Submit AJAX form with notification and row update
 * @param {HTMLFormElement} form - The form element
 * @returns {Promise<Object|null>}
 */
async function submitAjaxForm(form) {
    if (!(form instanceof HTMLFormElement)) {
        return null;
    }

    setFormLoading(form, true);

    const result = await sendAjaxForm(form);
    
    setFormLoading(form, false);
    form.dataset.submitting = 'false';
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        setButtonLoading(submitBtn, false);
    }

    if (!result || !result.success) {
        return null;
    }

    showNotification(result.message, 'success');

    const articleId = form.dataset.articleId || form.closest('[data-article-id]')?.dataset.articleId;
    if (articleId && result.article) {
        updateArticleRow(articleId, result.article);
    }

    if (form.hasAttribute('data-close-on-success')) {
        const modal = form.closest('[data-modal]');
        if (modal instanceof HTMLElement) {
            closeModal(modal);
        }
        form.reset();
    }

    return result;
}

/**
 * Initialize global event handlers
 * Handles: toast close, modal open/close/backdrop, form submission with confirmation
 */
function initGlobalHandlers() {
    // Single unified click handler for all click interactions.
    // Keeping this as one listener prevents race conditions between
    // multiple document-level handlers processing the same click event.
    document.addEventListener('click', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        // Handle toast close buttons
        const closeToastButton = target.closest('[data-close-toast]');
        if (closeToastButton instanceof HTMLElement) {
            event.preventDefault();
            closeSuccessToast();
            return;
        }

        // Handle global delete confirmation dialogs
        const deleteBtn = target.closest('[data-delete-form]');
        if (deleteBtn instanceof HTMLElement) {
            event.preventDefault();
            const formId = deleteBtn.dataset.deleteForm;
            const message = deleteBtn.dataset.confirmMessage || 'Apakah Anda yakin ingin menghapus data ini?';
            const title = deleteBtn.dataset.confirmTitle || 'Hapus Data';
            
            let dialogId = 'global-confirm-delete';
            let dialog = document.getElementById(dialogId);
            if (!dialog) {
                dialog = document.createElement('div');
                dialog.id = dialogId;
                dialog.className = 'hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm p-4 flex items-center justify-center z-50';
                dialog.innerHTML = `
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-xl w-full max-w-sm">
                        <div class="p-6">
                            <div class="flex gap-4">
                                <div class="flex-shrink-0">
                                    <div class="flex items-center justify-center h-12 w-12 rounded-2xl bg-red-100">
                                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold text-gray-900" id="global-delete-title">${title}</h3>
                                    <p class="mt-2 text-sm text-gray-600" id="global-delete-message">${message}</p>
                                </div>
                            </div>
                        </div>
                        <div class="px-6 py-4 bg-gray-50 rounded-b-2xl border-t border-gray-100 flex gap-3 justify-end">
                            <button type="button" data-confirm-cancel class="inline-flex items-center justify-center rounded-2xl border border-gray-300 bg-white text-gray-700 text-sm font-medium px-4 py-2 transition hover:bg-gray-50 focus:outline-none">
                                Batal
                            </button>
                            <button type="button" data-confirm-submit class="inline-flex items-center justify-center rounded-2xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 transition focus:outline-none">
                                Hapus
                            </button>
                        </div>
                    </div>
                `;
                document.body.appendChild(dialog);
            } else {
                const titleEl = document.getElementById('global-delete-title');
                const messageEl = document.getElementById('global-delete-message');
                if (titleEl) titleEl.textContent = title;
                if (messageEl) messageEl.textContent = message;
            }
            
            window.confirmDialog.open(dialogId, {
                onConfirm: () => {
                    const form = document.getElementById(formId);
                    if (form) form.submit();
                }
            });
            return;
        }

        // Handle customized delete confirmation dialogs (e.g. confirm-delete-staff)
        const customDeleteBtn = target.closest('[data-confirm-delete-dialog]');
        if (customDeleteBtn instanceof HTMLElement) {
            event.preventDefault();
            const dialogId = customDeleteBtn.dataset.confirmDeleteDialog;
            const formId = customDeleteBtn.dataset.confirmDeleteForm;
            
            if (dialogId === 'confirm-delete-staff') {
                const nameEl = document.getElementById('confirm-delete-staff-name');
                const emailEl = document.getElementById('confirm-delete-staff-email');
                const roleEl = document.getElementById('confirm-delete-staff-role');
                if (nameEl) nameEl.textContent = customDeleteBtn.dataset.userName || '';
                if (emailEl) emailEl.textContent = customDeleteBtn.dataset.userEmail || '';
                if (roleEl) roleEl.textContent = customDeleteBtn.dataset.userRole || '';
            }
            
            window.confirmDialog.open(dialogId, {
                onConfirm: () => {
                    const form = document.getElementById(formId);
                    if (form) form.submit();
                }
            });
            return;
        }

        // Handle modal open buttons.
        // preventDefault() is intentional here: these are <button> elements
        // (or elements with data-open-modal) that must not trigger link navigation.
        // Plain <a> sidebar links never carry [data-open-modal] and are unaffected.
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

        // Handle modal close buttons (only if the button is inside a [data-modal] element)
        const closeButton = target.closest('[data-close-modal]');
        if (closeButton instanceof HTMLElement) {
            event.preventDefault();
            const modal = closeButton.closest('[data-modal]');
            if (modal instanceof HTMLElement) {
                closeModal(modal);
            }
            return;
        }

        // Handle modal backdrop clicks: only fires when the click lands directly
        // on the backdrop element itself (not on any child content inside the modal)
        const modal = target.closest('[data-modal]');
        if (modal instanceof HTMLElement && target === modal && !modal.classList.contains('hidden')) {
            closeModal(modal);
            return;
        }
    });

    // Form submit handler with confirmation support
    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form');
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        // Clear previous validation errors
        clearFormValidationErrors(form);

        // Prevent double submits
        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }

        const confirmMessage = form.dataset.confirm;
        if (confirmMessage) {
            console.log('CLICK -> submit intercepted', { formId: form.id, confirmMessage });
            event.preventDefault();
            
            // Get or create a hidden dialog for this form
            let dialogId = `confirm-form-${form.id || Math.random().toString(36).substr(2, 9)}`;
            let dialog = document.getElementById(dialogId);

            if (!dialog) {
                // Create a hidden dialog with the message
                dialog = document.createElement('div');
                dialog.id = dialogId;
                dialog.className = 'hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-50';
                dialog.innerHTML = `
                    <div class="bg-white rounded-2xl border border-gray-200 shadow-xl w-full max-w-sm">
                        <div class="p-6">
                            <div class="flex gap-4">
                                <div class="flex-shrink-0">
                                    <div class="flex items-center justify-center h-12 w-12 rounded-2xl bg-red-100">
                                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4v.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold text-gray-900">Konfirmasi</h3>
                                    <p class="mt-2 text-sm text-gray-600">${confirmMessage}</p>
                                </div>
                            </div>
                        </div>
                        <div class="px-6 py-4 bg-gray-50 rounded-b-2xl border-t border-gray-100 flex gap-3 justify-end">
                            <button type="button" data-confirm-cancel class="inline-flex items-center justify-center rounded-2xl border border-gray-300 bg-white text-gray-700 text-sm font-medium px-4 py-2 transition hover:bg-gray-50 focus:outline-none">
                                Batal
                            </button>
                            <button type="button" data-confirm-submit class="inline-flex items-center justify-center rounded-2xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 transition focus:outline-none">
                                Lanjutkan
                            </button>
                        </div>
                    </div>
                `;
                document.body.appendChild(dialog);
            }

            if (typeof window.confirmDialog !== 'undefined') {
                console.log('CONFIRM OPEN', { dialogId, formId: form.id });
                window.confirmDialog.open(dialogId, {
                    onConfirm: () => {
                        console.log('CONFIRM YES', { dialogId, formId: form.id });
                        console.log('FORM SUBMIT');
                        form.dataset.submitting = 'true';
                        const submitBtn = form.querySelector('button[type="submit"]');
                        if (submitBtn) {
                            setButtonLoading(submitBtn, true);
                        }
                        form.submit();
                    }
                });
            } else {
                // Fallback to window.confirm if confirmDialog not available
                if (window.confirm(confirmMessage)) {
                    form.dataset.submitting = 'true';
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        setButtonLoading(submitBtn, true);
                    }
                    form.submit();
                }
            }
            return;
        }

        if (!form.dataset.ajax) {
            form.dataset.submitting = 'true';
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                setButtonLoading(submitBtn, true);
            }
            return;
        }

        event.preventDefault();
        submitAjaxForm(form);
    });
}

/**
 * Initialize all shared UI components
 */
function initSharedUI() {
    // Register the Escape key handler for modals.
    // Modal click handling (open/close/backdrop) lives inside initGlobalHandlers().
    initModalHandlers();

    // Register the unified document click + form submit handlers.
    initGlobalHandlers();

    // Initialize report modal dynamically if present
    if (document.getElementById('reportForm') || document.getElementById('reportModal')) {
        import('./report').then(({ initReportModal }) => {
            initReportModal();
        });
    }

    // Initialize live chat form dynamically if present
    if (document.getElementById('liveChatForm') || document.getElementById('liveChatModal')) {
        import('./livechat').then(({ initLiveChatForm }) => {
            initLiveChatForm();
        });
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', initSharedUI);

// Export functions for use in other modules
export {
    updateArticleRow,
    updateStatusBadge,
    updateVisibilityBadge,
    updateActionVisibility,
    updateToggleButton,
    sendAjaxForm,
    submitAjaxForm
};