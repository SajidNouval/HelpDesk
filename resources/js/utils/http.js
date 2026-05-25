/**
 * Get CSRF token from meta tag
 * @returns {string}
 */
export function getCsrfToken() {
    const tokenElement = document.querySelector('meta[name="csrf-token"]');
    return tokenElement?.content || '';
}

export async function safeJson(response) {
    const contentType = response.headers.get('content-type');

    if (!contentType?.includes('application/json')) {
        return null;
    }

    try {
        return await response.json();
    } catch {
        return null;
    }
}

export async function safeFetch(url, options = {}) {
    try {
        const response = await fetch(url, options);
        const data = await safeJson(response);

        return {
            ok: response.ok,
            status: response.status,
            data,
        };
    } catch (error) {
        console.error(error);

        return {
            ok: false,
            status: 500,
            data: null,
        };
    }
}
