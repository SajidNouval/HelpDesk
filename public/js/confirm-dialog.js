/**
 * Confirmation Dialog Manager
 * Handles opening, closing, and submitting confirmation dialogs
 */
class ConfirmDialogManager {
    constructor() {
        this.currentDialog = null;
        this.currentForm = null;
        // Track the backdrop listener so it can be removed before re-registering.
        // Without this, every open() call stacks an additional anonymous listener
        // on the dialog element that can never be garbage-collected.
        this._backdropHandler = null;
        this._currentDialogElement = null;
    }

    /**
     * Open confirmation dialog
     * @param {string} dialogId - The dialog element ID
     * @param {object} options - Configuration options
     * @param {Function} options.onConfirm - Callback when user confirms
     * @param {Function} options.onCancel - Callback when user cancels
     */
    open(dialogId, options = {}) {
        const dialog = document.getElementById(dialogId);
        if (!dialog) return;

        this.currentDialog = {
            id: dialogId,
            element: dialog,
            onConfirm: options.onConfirm || (() => {}),
            onCancel: options.onCancel || (() => {}),
        };

        dialog.classList.remove('hidden');
        document.body.style.overflow = 'hidden';

        // Remove any backdrop listener registered by a previous open() call
        // before adding a new one. This prevents listener accumulation when
        // the dialog is opened more than once without a full page reload.
        if (this._backdropHandler && this._currentDialogElement) {
            this._currentDialogElement.removeEventListener('click', this._backdropHandler);
        }
        this._backdropHandler = (e) => {
            if (e.target === dialog) this.close();
        };
        this._currentDialogElement = dialog;
        dialog.addEventListener('click', this._backdropHandler);

        // Cancel/submit buttons use .onclick so re-assignment is naturally
        // idempotent — no accumulation risk here.
        const cancelBtn = dialog.querySelector('[data-confirm-cancel]');
        const submitBtn = dialog.querySelector('[data-confirm-submit]');

        if (cancelBtn) {
            cancelBtn.onclick = () => this.close();
        }

        if (submitBtn) {
            submitBtn.onclick = () => {
                console.log('CONFIRM YES CLICKED', { dialogId: this.currentDialog?.id });
                this.currentDialog.onConfirm();
                this.close();
            };
        }
    }

    /**
     * Close confirmation dialog
     */
    close() {
        if (!this.currentDialog) return;

        this.currentDialog.element.classList.add('hidden');
        document.body.style.overflow = '';

        // Remove the stored backdrop listener so it does not fire on future
        // interactions and does not prevent the element from being GC'd.
        if (this._backdropHandler && this._currentDialogElement) {
            this._currentDialogElement.removeEventListener('click', this._backdropHandler);
            this._backdropHandler = null;
            this._currentDialogElement = null;
        }

        this.currentDialog = null;
    }
}

/**
 * Toast Manager
 * Handles displaying toast notifications
 */
class ToastManager {
    constructor(containerId = 'toast-container') {
        this.containerId = containerId;

        if (document.readyState !== 'loading') {
            this.ensureContainer();
        } else {
            document.addEventListener('DOMContentLoaded', () => this.ensureContainer(), { once: true });
        }
    }

    ensureContainer() {
        let container = document.getElementById(this.containerId);
        if (!container) {
            container = document.createElement('div');
            container.id = this.containerId;
            container.className = 'fixed top-6 right-6 z-50 flex flex-col gap-3 pointer-events-none w-full max-w-sm';
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-atomic', 'true');
            document.body.appendChild(container);
        } else {
            container.className = 'fixed top-6 right-6 z-50 flex flex-col gap-3 pointer-events-none w-full max-w-sm';
        }
        return container;
    }

    /**
     * Show toast notification
     * @param {string} message - Toast message
     * @param {string} type - Toast type: success, error, info, warning
     * @param {number} duration - Duration in milliseconds (0 = no auto-hide)
     */
    show(message, type = 'info', duration = 4000) {
        const container = this.ensureContainer();
        const toastId = `toast-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;

        // Create toast element
        const toast = document.createElement('div');
        toast.id = toastId;
        toast.className = `pointer-events-auto w-full max-w-sm bg-white border border-gray-100 shadow-xl rounded-xl toast-animate-in transition-all duration-300`;

        // Icon mapping
        const icons = {
            success: '<svg class="h-5 w-5 text-green-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>',
            error: '<svg class="h-5 w-5 text-red-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>',
            info: '<svg class="h-5 w-5 text-blue-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
            warning: '<svg class="h-5 w-5 text-yellow-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>',
        };

        toast.innerHTML = `
            <div class="flex items-center gap-3 p-4">
                <div class="flex-shrink-0 pt-0.5">
                    ${icons[type] || icons.info}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800">${message}</p>
                </div>
                <button type="button" class="ml-auto flex-shrink-0 inline-flex text-gray-400 hover:text-gray-600 transition p-1 hover:bg-gray-50 rounded-lg" data-toast-close>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        `;

        container.appendChild(toast);

        // Close button handler
        const closeBtn = toast.querySelector('[data-toast-close]');
        if (closeBtn) {
            closeBtn.onclick = () => this.remove(toastId);
        }

        // Auto-hide if duration > 0
        if (duration > 0) {
            setTimeout(() => this.remove(toastId), duration);
        }

        return toastId;
    }

    remove(toastId) {
        const toast = document.getElementById(toastId);
        if (toast) {
            toast.classList.remove('toast-animate-in');
            toast.classList.add('toast-animate-out');
            setTimeout(() => toast.remove(), 300);
        }
    }

    success(message, duration = 4000) {
        return this.show(message, 'success', duration);
    }

    error(message, duration = 4000) {
        return this.show(message, 'error', duration);
    }

    info(message, duration = 4000) {
        return this.show(message, 'info', duration);
    }

    warning(message, duration = 4000) {
        return this.show(message, 'warning', duration);
    }
}

// Initialize managers globally
window.confirmDialog = new ConfirmDialogManager();
window.toast = new ToastManager();

// Export for modular usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { ConfirmDialogManager, ToastManager };
}
