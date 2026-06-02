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
    closeSuccessToast
} from './dom';
import { safeFetch } from '../utils/http';
import { initModalHandlers } from '../utils/modal';
import { initReportModal } from './report';
import { initLiveChatForm } from './livechat';

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

    const result = await sendAjaxForm(form);
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
 * Handles: toast close, form submission with confirmation
 */
function initGlobalHandlers() {
    // Single click handler for toast close buttons
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
    });

    // Form submit handler with confirmation support
    document.addEventListener('submit', (event) => {
        const form = event.target.closest('form');
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        const confirmMessage = form.dataset.confirm;
        if (confirmMessage) {
            event.preventDefault();
            
            // Get or create a hidden dialog for this form
            let dialogId = `confirm-form-${form.id || Math.random().toString(36).substr(2, 9)}`;
            let dialog = document.getElementById(dialogId);

            if (!dialog) {
                // Create a hidden dialog with the message
                dialog = document.createElement('div');
                dialog.id = dialogId;
                dialog.className = 'hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
                dialog.innerHTML = `
                    <div class="bg-white rounded-lg shadow-lg max-w-sm w-full mx-4">
                        <div class="flex items-center justify-center h-12 w-12 mx-auto mt-4 rounded-2xl bg-red-100">
                            <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4v2m0 0a9 9 0 11-18 0 9 9 0 0118 0zm0-4a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-center mt-2 text-lg font-medium text-gray-900">Konfirmasi</h3>
                        <p class="text-center mt-2 text-sm text-gray-600 px-4">${confirmMessage}</p>
                        <div class="mt-6 flex gap-3 justify-center px-4 pb-4">
                            <button type="button" data-confirm-cancel class="inline-flex items-center justify-center rounded-2xl border border-gray-300 bg-white text-gray-700 text-sm font-medium px-4 py-2 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2">
                                Batal
                            </button>
                            <button type="button" data-confirm-submit class="inline-flex items-center justify-center rounded-2xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium px-4 py-2 transition focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                                Lanjutkan
                            </button>
                        </div>
                    </div>
                `;
                document.body.appendChild(dialog);
            }

            if (typeof window.confirmDialog !== 'undefined') {
                window.confirmDialog.open(dialogId, {
                    onConfirm: () => {
                        form.submit();
                    }
                });
            } else {
                // Fallback to window.confirm if confirmDialog not available
                if (window.confirm(confirmMessage)) {
                    form.submit();
                }
            }
            return;
        }

        if (!form.dataset.ajax) {
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
    // Initialize modal handlers from utils
    initModalHandlers();

    // Initialize global handlers
    initGlobalHandlers();

    // Initialize report modal
    initReportModal();

    // Initialize live chat form
    initLiveChatForm();
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