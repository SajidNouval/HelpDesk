/**
 * Loading Module
 * Centralized loading state management for buttons and forms
 */

/**
 * Set loading state on a button element
 * @param {HTMLButtonElement} button - The button element
 * @param {boolean} isLoading - Whether to show loading state
 * @param {string} [loadingText] - Optional loading text to display
 */
export function setButtonLoading(button, isLoading, loadingText = 'Loading...') {
    if (!(button instanceof HTMLElement)) {
        return;
    }

    button.disabled = isLoading;

    // Handle loading text/spinner if they exist
    const submitText = button.querySelector('.submit-text');
    const submitLoading = button.querySelector('.submit-loading');

    if (submitText instanceof HTMLElement) {
        submitText.classList.toggle('hidden', isLoading);
        if (!isLoading) {
            // Restore original text if loadingText was provided
            if (button.dataset.originalText && !submitText.dataset.originalText) {
                submitText.dataset.originalText = button.dataset.originalText;
            }
        }
    }

    if (submitLoading instanceof HTMLElement) {
        submitLoading.classList.toggle('hidden', !isLoading);
    }

    // Store original text on first loading
    if (isLoading && submitText && !button.dataset.originalText) {
        button.dataset.originalText = submitText.textContent;
    }
}

/**
 * Set disabled state on a form element
 * @param {HTMLFormElement|HTMLElement} element - The element to disable
 * @param {boolean} isDisabled - Whether to disable the element
 */
export function setDisabled(element, isDisabled) {
    if (!(element instanceof HTMLElement)) {
        return;
    }

    element.disabled = isDisabled;

    // If it's a form, disable all form controls
    if (element instanceof HTMLFormElement) {
        const controls = element.querySelectorAll('input, textarea, select, button');
        controls.forEach(control => {
            control.disabled = isDisabled;
        });
    }
}

/**
 * Set loading state on a form with visual feedback
 * @param {HTMLFormElement} form - The form element
 * @param {boolean} isLoading - Whether to show loading state
 * @param {HTMLButtonElement} [submitButton] - Optional specific submit button
 */
export function setFormLoading(form, isLoading, submitButton = null) {
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const btn = submitButton || form.querySelector('button[type="submit"]');
    if (btn) {
        setButtonLoading(btn, isLoading);
    }

    // Disable form inputs during loading (except the submit button)
    if (isLoading) {
        form.dataset.loading = 'true';
        const inputs = form.querySelectorAll('input:not([type="submit"]), textarea, select');
        inputs.forEach(input => {
            input.disabled = true;
        });
    } else {
        form.dataset.loading = 'false';
        const inputs = form.querySelectorAll('input:not([type="submit"]), textarea, select');
        inputs.forEach(input => {
            input.disabled = false;
        });
    }
}

/**
 * Show a loading spinner in a container
 * @param {HTMLElement} container - The container to add spinner to
 * @param {string} [message] - Optional loading message
 */
export function showSpinner(container, message = '') {
    if (!(container instanceof HTMLElement)) {
        return;
    }

    // Remove existing spinner if any
    hideSpinner(container);

    const spinner = document.createElement('div');
    spinner.className = 'loading-spinner flex items-center justify-center gap-2 p-4';
    spinner.dataset.spinner = 'true';
    spinner.innerHTML = `
        <svg class="animate-spin h-5 w-5 text-gray-500" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        ${message ? `<span class="text-sm text-gray-600">${message}</span>` : ''}
    `;

    container.appendChild(spinner);
}

/**
 * Hide loading spinner from a container
 * @param {HTMLElement} container - The container to remove spinner from
 */
export function hideSpinner(container) {
    if (!(container instanceof HTMLElement)) {
        return;
    }

    const spinner = container.querySelector('[data-spinner="true"]');
    if (spinner instanceof HTMLElement) {
        spinner.remove();
    }
}

/**
 * Check if a form is currently loading
 * @param {HTMLFormElement} form - The form element
 * @returns {boolean}
 */
export function isFormLoading(form) {
    if (!(form instanceof HTMLFormElement)) {
        return false;
    }
    return form.dataset.loading === 'true';
}