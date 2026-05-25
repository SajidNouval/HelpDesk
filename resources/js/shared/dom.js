/**
 * Shared DOM Module
 * Re-exports from utils for backward compatibility
 * New code should import directly from utils/
 */

// Re-export DOM utilities
export {
    setText,
    toggleHidden,
    setValue,
    getInputValue,
    addEvent,
    removeEvent,
    $,
    $$,
    createElement,
    isVisible,
    setDataAttrs,
    getDataAttr,
    delegate,
    scrollIntoView,
    focusElement,
    onDOMReady,
    debounce,
    throttle
} from '../utils/dom';

// Re-export HTTP utilities (including getCsrfToken)
export { safeFetch, safeJson, getCsrfToken } from '../utils/http';

// Re-export modal utilities
export {
    disableBodyScroll,
    enableBodyScroll,
    openModal,
    closeModal,
    openModalById,
    closeModalById,
    setModalFormAction
} from '../utils/modal';

// Re-export notification utilities
export {
    showNotification,
    showSuccessToast,
    closeSuccessToast,
    showInlineAlert,
    hideInlineAlert
} from '../utils/notification';

// Re-export loading utilities
export {
    setButtonLoading,
    setDisabled,
    setFormLoading,
    showSpinner,
    hideSpinner,
    isFormLoading
} from '../utils/loading';

/**
 * Parse JSON response safely (legacy compatibility)
 * @param {Response} response - The fetch response
 * @returns {Promise<Object|null>}
 */
export async function parseJsonSafe(response) {
    const { safeJson } = await import('../utils/http');
    return safeJson(response);
}