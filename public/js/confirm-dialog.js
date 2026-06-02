/**
 * Confirmation Dialog Manager
 * Handles opening, closing, and submitting confirmation dialogs
 */
class ConfirmDialogManager {
    constructor() {
        this.currentDialog = null;
        this.currentForm = null;
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

        // Close on backdrop click
        dialog.addEventListener('click', (e) => {
            if (e.target === dialog) this.close();
        });

        // Close button
        const cancelBtn = dialog.querySelector('[data-confirm-cancel]');
        const submitBtn = dialog.querySelector('[data-confirm-submit]');

        if (cancelBtn) {
            cancelBtn.onclick = () => this.close();
        }

        if (submitBtn) {
            submitBtn.onclick = () => {
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
        this.ensureContainer();
    }

    ensureContainer() {
        let container = document.getElementById(this.containerId);
        if (!container) {
            container = document.createElement('div');
            container.id = this.containerId;
            container.className = 'fixed bottom-4 right-4 z-50 flex flex-col gap-2 pointer-events-none';
            container.setAttribute('aria-live', 'polite');
            container.setAttribute('aria-atomic', 'true');
            document.body.appendChild(container);
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
        toast.className = `pointer-events-auto w-full max-w-sm rounded-2xl border shadow-lg animate-in fade-in-50 slide-in-from-bottom-4 duration-300`;

        // Set type-specific classes
        const typeClasses = {
            success: 'bg-green-50 text-green-800 border-green-200',
            error: 'bg-red-50 text-red-800 border-red-200',
            info: 'bg-blue-50 text-blue-800 border-blue-200',
            warning: 'bg-yellow-50 text-yellow-800 border-yellow-200',
        };
        toast.className += ` ${typeClasses[type] || typeClasses.info}`;

        // Icon mapping
        const icons = {
            success: '<svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>',
            error: '<svg class="h-5 w-5 text-red-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>',
            info: '<svg class="h-5 w-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>',
            warning: '<svg class="h-5 w-5 text-yellow-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>',
        };

        toast.innerHTML = `
            <div class="flex gap-3 p-4">
                <div class="flex-shrink-0 pt-0.5">
                    ${icons[type] || icons.info}
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium">${message}</p>
                </div>
                <button type="button" class="ml-auto inline-flex text-current opacity-50 hover:opacity-75 transition" data-toast-close>
                    <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
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
            toast.style.animation = 'fadeOut 300ms ease-out forwards';
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
