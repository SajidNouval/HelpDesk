/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

try {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT,
        forceTLS: false,
        enabledTransports: ['ws'],
        reconnectAfter: 5,
        debug: true,
    });
    
    // Setup connection event handlers
    const socket = window.Echo.connector?.socket;
    if (socket) {
        socket.addEventListener('open', () => {
            console.log('✓ WebSocket connected successfully');
        });
        
        socket.addEventListener('error', (error) => {
            console.error('✗ WebSocket connection error:', error);
        });
        
        socket.addEventListener('close', () => {
            console.warn('⚠ WebSocket connection closed, will auto-reconnect');
        });
    }
    
    console.log('✓ Echo initialized with Reverb broadcaster');
} catch (error) {
    console.error('Failed to initialize Echo:', error);
    window.Echo = null;
}
