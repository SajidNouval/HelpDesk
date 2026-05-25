/**
 * Utils Module Index
 * Central export point for all utility modules
 */

// HTTP utilities (including getCsrfToken)
export { safeFetch, safeJson, getCsrfToken } from './http';

// DOM utilities
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
} from './dom';

// Modal utilities
export {
    disableBodyScroll,
    enableBodyScroll,
    openModal,
    closeModal,
    openModalById,
    closeModalById,
    setModalFormAction,
    initModalHandlers
} from './modal';

// Notification utilities
export {
    showNotification,
    showSuccessToast,
    closeSuccessToast,
    showInlineAlert,
    hideInlineAlert
} from './notification';

// Loading utilities
export {
    setButtonLoading,
    setDisabled,
    setFormLoading,
    showSpinner,
    hideSpinner,
    isFormLoading
} from './loading';