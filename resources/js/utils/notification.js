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

    const container = document.getElementById('ajaxNotification');
    if (!container) {
        console[type === 'error' ? 'error' : 'log'](message);
        return;
    }

    const styles = {
        success: {
            bg: 'bg-green-50',
            border: 'border-green-200',
            text: 'text-green-800',
            icon: '<svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>'
        },
        error: {
            bg: 'bg-red-50',
            border: 'border-red-200',
            text: 'text-red-800',
            icon: '<svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>'
        },
        warning: {
            bg: 'bg-yellow-50',
            border: 'border-yellow-200',
            text: 'text-yellow-800',
            icon: '<svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>'
        },
        info: {
            bg: 'bg-red-50',
            border: 'border-red-200',
            text: 'text-red-800',
            icon: '<svg class="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>'
        }
    };

    const style = styles[type] || styles.info;

    const notification = document.createElement('div');
    notification.className = `rounded-xl border shadow-lg px-4 py-3 flex items-start gap-3 ${style.bg} ${style.border} ${style.text} transition-opacity duration-200`;
    notification.innerHTML = `
        <div class="flex-shrink-0">${style.icon}</div>
        <div class="flex-1 min-w-0">
            <p class="text-sm text-gray-600">${message}</p>
        </div>
    `;

    container.appendChild(notification);

    window.setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateY(-10px)';
        setTimeout(() => notification.remove(), 200);
    }, duration);
}

/**
 * Show a success toast using the successToast element
 * @param {string} message - The message to display
 * @param {number} duration - Duration in milliseconds (default: 4000)
 */
export function showSuccessToast(message = 'Berhasil', duration = 4000) {
    const toast = document.getElementById('successToast');
    const toastMessage = document.getElementById('toastMessage');

    if (!(toast instanceof HTMLElement) || !(toastMessage instanceof HTMLElement)) {
        showNotification(message, 'success', duration);
        return;
    }

    toastMessage.textContent = message;
    toast.classList.remove('hidden', 'animate-fade-out');
    toast.classList.add('animate-fade-in');

    window.setTimeout(() => {
        closeSuccessToast();
    }, duration);
}

/**
 * Close the success toast with animation
 */
export function closeSuccessToast() {
    const toast = document.getElementById('successToast');
    if (!(toast instanceof HTMLElement)) {
        return;
    }

    toast.classList.remove('animate-fade-in');
    toast.classList.add('animate-fade-out');
    window.setTimeout(() => {
        toast.classList.add('hidden');
    }, 300);
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