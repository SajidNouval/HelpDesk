/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import { safeFetch, safeJson } from './utils/http';

window.safeFetch = safeFetch;
window.safeJson = safeJson;

window.initializeRealtime = async () => {
    try {
        const module = await import('./shared/reverb');
        return module.initializeRealtime();
    } catch (error) {
        console.error('Failed to load Reverb realtime module:', error);
        return null;
    }
};
