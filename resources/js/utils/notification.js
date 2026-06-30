/**
 * Notification Module
 * Centralized notification and toast system for AJAX feedback
 */

/**
 * Show a notification message in the ajaxNotification container
 * @param {string} message - The message to display
 * @param {'success'|'error'|'warning'|'info'} type - Notification type
 * @param {number} duration - Duration in milliseconds (default: 4000)
 */
export function showNotification(message, type = 'success', duration = 4000) {
    if (!message) {
        return;
    }

    if (window.toast) {
        window.toast.show(message, type, duration);
    } else {
        console[type === 'error' ? 'error' : 'log'](message);
    }
}

/**
 * Show a success toast using the successToast element
 * @param {string} message - The message to display
 * @param {number} duration - Duration in milliseconds (default: 4000)
 */
export function showSuccessToast(message = 'Berhasil', duration = 4000) {
    showNotification(message, 'success', duration);
}

/**
 * Close the success toast with animation
 */
export function closeSuccessToast() {
    // No-op as we are using the unified window.toast system
}

/**
 * Show an inline alert message (for specific containers like livechat/report forms)
 * @param {HTMLElement|string} container - The container element or its ID
 * @param {string} message - The message to display
 * @param {'success'|'error'|'info'} type - Alert type
 */
export function showInlineAlert(container, message, type = 'info') {
    const el = typeof container === 'string' 
        ? document.getElementById(container) 
        : container;

    if (!(el instanceof HTMLElement)) {
        console.log(message);
        return;
    }

    el.textContent = message;
    el.className = 'rounded-xl border p-3 text-sm';

    const typeClasses = {
        success: 'bg-green-50 border-green-200 text-green-900',
        error: 'bg-red-50 border-red-200 text-red-900',
        info: 'bg-blue-50 border-blue-200 text-blue-900',
    };

    const classes = (typeClasses[type] || typeClasses.info).split(' ');
    el.classList.remove('hidden');
    el.classList.add(...classes);
}

/**
 * Hide an inline alert
 * @param {HTMLElement|string} container - The container element or its ID
 */
export function hideInlineAlert(container) {
    const el = typeof container === 'string' 
        ? document.getElementById(container) 
        : container;

    if (el instanceof HTMLElement) {
        el.classList.add('hidden');
    }
}

/**
 * Show validation errors on a form
 * @param {HTMLFormElement} form - The form
 * @param {Object} errors - The errors object from Laravel
 */
export function showFormValidationErrors(form, errors) {
    if (!(form instanceof HTMLFormElement) || !errors) return;

    // First clear existing errors
    clearFormValidationErrors(form);

    Object.entries(errors).forEach(([field, messages]) => {
        const input = form.querySelector(`[name="${field}"]`) || form.querySelector(`#${field}`) || form.querySelector(`[name="${field}[]"]`);
        if (!input) return;

        // Highlight input field
        input.classList.add('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');

        // Create error message element
        const errorEl = document.createElement('p');
        errorEl.className = 'mt-1 text-xs text-red-600 validation-error-msg';
        errorEl.textContent = messages[0]; // Display the first error message

        // Append after input element
        if (input.parentNode) {
            input.parentNode.appendChild(errorEl);
        }
    });
}

/**
 * Clear validation errors on a form
 * @param {HTMLFormElement} form - The form
 */
export function clearFormValidationErrors(form) {
    if (!(form instanceof HTMLFormElement)) return;

    // Remove input highlights
    form.querySelectorAll('.border-red-500').forEach(input => {
        input.classList.remove('border-red-500', 'focus:ring-red-500', 'focus:border-red-500');
    });

    // Remove error message elements
    form.querySelectorAll('.validation-error-msg').forEach(el => el.remove());
}