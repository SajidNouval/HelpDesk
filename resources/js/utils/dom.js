/**
 * DOM Utilities Module
 * Centralized DOM manipulation helpers
 */

/**
 * Set text content of an element
 * @param {HTMLElement} el - The element
 * @param {string|number|null|undefined} value - The text value
 */
export function setText(el, value) {
    if (el) el.textContent = value ?? '';
}

/**
 * Toggle hidden class on an element
 * @param {HTMLElement} el - The element
 * @param {boolean} hidden - Whether to hide the element
 */
export function toggleHidden(el, hidden) {
    if (el) el.classList.toggle('hidden', hidden);
}

/**
 * Set value of an input/select element
 * @param {HTMLInputElement|HTMLSelectElement} el - The element
 * @param {string|number|null|undefined} value - The value
 */
export function setValue(el, value) {
    if (el) el.value = value ?? '';
}

/**
 * Get value from an input element by ID
 * @param {string} id - The element ID
 * @returns {string}
 */
export function getInputValue(id) {
    const input = document.getElementById(id);
    return input?.value?.trim() || '';
}

/**
 * Add event listener to an element
 * @param {HTMLElement} el - The element
 * @param {string} event - The event type
 * @param {Function} handler - The event handler
 */
export function addEvent(el, event, handler) {
    if (el) el.addEventListener(event, handler);
}

/**
 * Remove event listener from an element
 * @param {HTMLElement} el - The element
 * @param {string} event - The event type
 * @param {Function} handler - The event handler
 */
export function removeEvent(el, event, handler) {
    if (el) el.removeEventListener(event, handler);
}

/**
 * Query selector with type checking
 * @param {string} selector - CSS selector
 * @param {Document|HTMLElement} [context] - Context element
 * @returns {HTMLElement|null}
 */
export function $(selector, context = document) {
    return context.querySelector(selector);
}

/**
 * Query selector all with type checking
 * @param {string} selector - CSS selector
 * @param {Document|HTMLElement} [context] - Context element
 * @returns {NodeList}
 */
export function $$(selector, context = document) {
    return context.querySelectorAll(selector);
}

/**
 * Create an element from HTML string
 * @param {string} html - HTML string
 * @returns {HTMLElement}
 */
export function createElement(html) {
    const template = document.createElement('template');
    template.innerHTML = html.trim();
    return template.content.firstChild;
}

/**
 * Check if an element is visible
 * @param {HTMLElement} el - The element
 * @returns {boolean}
 */
export function isVisible(el) {
    if (!(el instanceof HTMLElement)) {
        return false;
    }
    return !el.classList.contains('hidden') && el.offsetParent !== null;
}

/**
 * Set multiple data attributes on an element
 * @param {HTMLElement} el - The element
 * @param {Object<string, string>} attrs - Data attributes
 */
export function setDataAttrs(el, attrs) {
    if (!(el instanceof HTMLElement)) {
        return;
    }
    Object.entries(attrs).forEach(([key, value]) => {
        el.dataset[key] = value;
    });
}

/**
 * Get data attribute from an element
 * @param {HTMLElement} el - The element
 * @param {string} key - The data attribute key
 * @returns {string|undefined}
 */
export function getDataAttr(el, key) {
    if (!(el instanceof HTMLElement)) {
        return undefined;
    }
    return el.dataset[key];
}

/**
 * Delegate event handling to a parent element
 * @param {HTMLElement} parent - The parent element
 * @param {string} eventType - The event type
 * @param {string} selector - CSS selector for target elements
 * @param {Function} handler - The event handler
 */
export function delegate(parent, eventType, selector, handler) {
    if (!(parent instanceof HTMLElement)) {
        return;
    }

    parent.addEventListener(eventType, (event) => {
        const target = event.target.closest(selector);
        if (target && parent.contains(target)) {
            handler.call(target, event, target);
        }
    });
}

/**
 * Scroll element into view with smooth animation
 * @param {HTMLElement} el - The element
 * @param {ScrollIntoViewOptions} [options] - Scroll options
 */
export function scrollIntoView(el, options = { behavior: 'smooth', block: 'nearest' }) {
    if (el instanceof HTMLElement) {
        el.scrollIntoView(options);
    }
}

/**
 * Focus an element safely
 * @param {HTMLElement} el - The element
 * @param {boolean} [select=false] - Whether to select the text
 */
export function focusElement(el, select = false) {
    if (!(el instanceof HTMLElement)) {
        return;
    }

    el.focus();
    if (select && el instanceof HTMLInputElement) {
        el.select();
    }
}

/**
 * Wait for DOM to be ready
 * @param {Function} callback - The callback function
 */
export function onDOMReady(callback) {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback);
    } else {
        callback();
    }
}

/**
 * Debounce function calls
 * @param {Function} func - The function to debounce
 * @param {number} wait - Wait time in milliseconds
 * @returns {Function}
 */
export function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Throttle function calls
 * @param {Function} func - The function to throttle
 * @param {number} limit - Time limit in milliseconds
 * @returns {Function}
 */
export function throttle(func, limit) {
    let inThrottle;
    return function executedFunction(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => (inThrottle = false), limit);
        }
    };
}