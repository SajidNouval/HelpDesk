let echoInstance = null;

export async function initializeRealtime() {
    if (echoInstance) {
        return echoInstance;
    }

    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const [{ default: Echo }, { default: Pusher }] = await Promise.all([
            import('laravel-echo'),
            import('pusher-js'),
        ]);

        window.Pusher = Pusher;

        echoInstance = new Echo({
            broadcaster: 'reverb',
            key: import.meta.env.VITE_REVERB_APP_KEY,
            wsHost: import.meta.env.VITE_REVERB_HOST,
            wsPort: import.meta.env.VITE_REVERB_PORT,
            forceTLS: false,
            enabledTransports: ['ws'],
            reconnectAfter: 5,
            debug: true,
        });

        const socket = echoInstance.connector?.socket;
        if (socket) {
            socket.addEventListener('open', () => {
                if (import.meta.env.DEV) {
                    console.log('✓ WebSocket connected successfully');
                }
            });
            socket.addEventListener('error', (error) => {
                console.error('✗ WebSocket connection error:', error);
            });
            socket.addEventListener('close', () => {
                console.warn('⚠ WebSocket connection closed, will auto-reconnect');
            });
        }

        window.Echo = echoInstance;
        if (import.meta.env.DEV) {
            console.log('✓ Echo initialized with Reverb broadcaster');
        }
        return echoInstance;
    } catch (error) {
        console.error('Failed to initialize Echo/Reverb:', error);
        echoInstance = null;
        window.Echo = null;
        return null;
    }
}
