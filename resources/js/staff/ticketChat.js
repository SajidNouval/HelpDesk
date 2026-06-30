/**
 * Ticket Chat Module
 * Handles real-time messaging and log management for ticket show page
 */

import { getCsrfToken, safeJson, showNotification } from '../shared/dom';
import { safeFetch } from '../utils/http';
import { initializeRealtime } from '../shared/reverb';

class TicketChatManager {
    constructor(ticketId) {
        this.ticketId = ticketId;
        this.messagesList = document.getElementById('messages-list');
        this.lastMessageIds = new Set();
        this.websocketConnected = false;
        this.pollingInterval = null;
        this._channelSubscribed = false;
    }

    /**
     * Initialize the ticket chat manager
     */
    init() {
        if (!this.ticketId || !this.messagesList) {
            return;
        }

        this.scrollMessagesToBottom();
        this.loadMessages();
        this.loadLogs();
        this.setupMessageForm();
        this.setupLogForm();
        this.initializeWebSocket();
    }

    /**
     * Scroll messages container to bottom
     */
    scrollMessagesToBottom() {
        const container = document.getElementById('messages-container');
        if (container) {
            container.scrollTop = container.scrollHeight;
        }
    }

    /**
     * Load messages from API
     */
    async loadMessages() {
        // Show loading skeleton
        if (this.messagesList) {
            this.messagesList.innerHTML = `
                <div class="animate-pulse space-y-4">
                    <div class="flex justify-start">
                        <div class="w-2/3 space-y-2">
                            <div class="h-3 bg-gray-200 rounded w-1/4"></div>
                            <div class="h-10 bg-gray-200 rounded-2xl"></div>
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <div class="w-2/3 space-y-2">
                            <div class="h-3 bg-gray-200 rounded w-1/4 ml-auto"></div>
                            <div class="h-10 bg-gray-200 rounded-2xl"></div>
                        </div>
                    </div>
                    <div class="flex justify-start">
                        <div class="w-1/2 space-y-2">
                            <div class="h-3 bg-gray-200 rounded w-1/3"></div>
                            <div class="h-8 bg-gray-200 rounded-2xl"></div>
                        </div>
                    </div>
                </div>
            `;
        }

        try {
            const response = await fetch(`/api/tickets/${this.ticketId}/messages`, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                console.error('Failed to load messages:', response.status, response.statusText);
                return;
            }

            const messages = await safeJson(response) || [];
            this.messagesList.innerHTML = '';
            this.lastMessageIds.clear();

            messages.forEach(msg => {
                this.appendMessage(msg);
                if (msg.id) this.lastMessageIds.add(msg.id);
            });
            this.scrollMessagesToBottom();
        } catch (err) {
            console.error('Error loading messages:', err);
        }
    }

    /**
     * Load logs from API
     */
    async loadLogs() {
        const logsContainer = document.getElementById('logs-container');
        if (logsContainer) {
            // Show loading skeleton
            logsContainer.innerHTML = `
                <div class="animate-pulse space-y-3">
                    <div class="bg-gray-100 p-4 rounded-lg space-y-2">
                        <div class="flex justify-between">
                            <div class="h-4 bg-gray-200 rounded w-1/3"></div>
                            <div class="h-3 bg-gray-200 rounded w-1/4"></div>
                        </div>
                        <div class="h-3 bg-gray-200 rounded w-full"></div>
                    </div>
                    <div class="bg-gray-100 p-4 rounded-lg space-y-2">
                        <div class="flex justify-between">
                            <div class="h-4 bg-gray-200 rounded w-1/4"></div>
                            <div class="h-3 bg-gray-200 rounded w-1/4"></div>
                        </div>
                        <div class="h-3 bg-gray-200 rounded w-5/6"></div>
                    </div>
                </div>
            `;
        }

        try {
            const response = await fetch(`/api/tickets/${this.ticketId}/logs`, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json'
                }
            });

            if (!response.ok) {
                console.error('Failed to load logs:', response.status, response.statusText);
                return;
            }

            const logs = await safeJson(response) || [];
            if (!logsContainer) return;

            logsContainer.innerHTML = '';

            if (logs.length === 0) {
                logsContainer.innerHTML = '<div class="p-4 bg-slate-50 rounded-lg text-sm text-slate-600">Belum ada log tambahan.</div>';
                return;
            }

            logs.reverse().forEach(log => {
                const logDiv = document.createElement('div');
                logDiv.className = 'bg-slate-50 p-4 rounded-lg border border-slate-200';
                logDiv.innerHTML = `
                    <div class="flex items-center justify-between gap-4 mb-2">
                        <p class="text-sm font-semibold text-slate-900">${this.ucfirst(log.action.replace(/_/g, ' '))}</p>
                        <p class="text-xs text-slate-500">${new Date(log.created_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })} ${new Date(log.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}</p>
                    </div>
                    <p class="text-sm text-slate-700 whitespace-pre-wrap">${log.description}</p>
                `;
                logsContainer.appendChild(logDiv);
            });
        } catch (err) {
            console.error('Error loading logs:', err);
        }
    }

    /**
     * Setup WebSocket listener for real-time messages
     */
    setupWebSocketListener() {
        if (!this.ticketId) return;
        if (this._channelSubscribed) return; // Prevent double subscription

        if (typeof window.Echo === 'undefined' || window.Echo === null) {
            console.warn('⚠ Echo is not initialized. Real-time chat unavailable.');
            this.showWebSocketError();
            return;
        }

        try {
            const channelName = `ticket.${this.ticketId}`;
            const connector = window.Echo.connector;
            const pusherConnection = connector?.pusher?.connection;
            const socketReady = connector?.socket?.readyState === WebSocket.OPEN;
            const connectedState = pusherConnection?.state === 'connected';

            const subscribeToChannel = () => {
                if (!this.ticketId || this._channelSubscribed) return;
                this._channelSubscribed = true;

                // Remove any existing error notification once we're connected
                this.clearWebSocketError();

                window.Echo.channel(channelName)
                    .listen('.MessageSent', async (e) => {
                        console.log('📨 New message received via WebSocket', e);
                        // Append inbound message directly without full reload
                        if (e.sender_type !== 'staff') {
                            // Guest message received — show it live
                            if (e.id && !this.lastMessageIds.has(e.id)) {
                                this.appendMessage(e);
                            }
                        } else {
                            // Our own staff message — already optimistically shown;
                            // reload to replace temp ID with real ID
                            await this.loadMessages();
                        }
                    });

                this.websocketConnected = true;
                console.log('✓ WebSocket listener initialized for ticket:', this.ticketId);
            };

            if (socketReady || connectedState) {
                subscribeToChannel();
                return;
            }

            if (pusherConnection) {
                pusherConnection.bind('connected', () => {
                    console.log('🔗 WebSocket connection established, setting up listener');
                    subscribeToChannel();
                });

                pusherConnection.bind('error', (error) => {
                    console.error('✗ WebSocket connection error:', error);
                    this.websocketConnected = false;
                    this._channelSubscribed = false;
                    this.showWebSocketError();
                });

                pusherConnection.bind('disconnected', () => {
                    console.warn('⚠ WebSocket connection closed');
                    this.websocketConnected = false;
                    this._channelSubscribed = false;
                    this.showWebSocketError();
                });

                return;
            }

            subscribeToChannel();
        } catch (error) {
            console.error('Failed to setup WebSocket listener:', error);
            this.showWebSocketError();
        }
    }

    /**
     * Show WebSocket error message (only once, deduplicated)
     */
    showWebSocketError() {
        if (document.getElementById('websocket-error-notification')) return; // already shown
        const errorDiv = document.createElement('div');
        errorDiv.id = 'websocket-error-notification';
        errorDiv.className = 'p-3 bg-amber-50 border border-amber-300 text-amber-800 rounded-xl mb-3 text-sm flex items-center gap-2';
        errorDiv.innerHTML = '<svg class="w-4 h-4 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg><span>Real-time chat tidak tersedia. Pastikan server Reverb berjalan.</span>';
        const container = document.getElementById('chat-container');
        if (container) {
            container.prepend(errorDiv);
        }
    }

    /**
     * Remove WebSocket error notification if present
     */
    clearWebSocketError() {
        const el = document.getElementById('websocket-error-notification');
        if (el) el.remove();
    }

    /**
     * Initialize WebSocket connection
     */
    async initializeWebSocket() {
        if (typeof window.Echo === 'undefined' || window.Echo === null) {
            if (typeof initializeRealtime === 'function') {
                await initializeRealtime();
            }
        }

        if (typeof window.Echo !== 'undefined' && window.Echo !== null) {
            this.setupWebSocketListener();
        } else {
            console.log('Waiting for Echo to initialize...');
            setTimeout(() => this.initializeWebSocket(), 1000);
        }
    }

    /**
     * Setup message form handler
     */
    setupMessageForm() {
        const messageForm = document.getElementById('message-form');
        const messageInput = document.getElementById('message-input');

        if (!messageForm || !messageInput) {
            return;
        }

        const sendButton = messageForm.querySelector('button[type="submit"]');

        messageForm.addEventListener('submit', (e) => {
            e.preventDefault();

            const message = messageInput.value.trim();
            if (!message) return;

            // Optimistic UI — show message immediately on the left (staff side)
            const staffName = messageInput.dataset.senderName || 'Staff';
            const optimisticMsg = {
                id: 'temp-' + Date.now(),
                message: message,
                sender_type: 'staff',
                sender_name: staffName,
                created_at: new Date().toISOString()
            };
            this.appendMessage(optimisticMsg);
            this.lastMessageIds.add(optimisticMsg.id);

            messageInput.disabled = true;
            if (sendButton) sendButton.disabled = true;

            fetch('/api/messages', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify({ ticket_id: this.ticketId, message: message })
            })
                .then(async res => {
                    if (res.ok) {
                        messageInput.value = '';
                        // Reload to replace the temp-ID message with real server message
                        setTimeout(() => this.loadMessages(), 400);
                    } else {
                        // Remove optimistic message if failed
                        const optimisticEl = this.messagesList.querySelector(`[data-message-id="${optimisticMsg.id}"]`);
                        if (optimisticEl) optimisticEl.remove();
                        this.lastMessageIds.delete(optimisticMsg.id);
                        console.error('Failed to send message:', res.status);
                    }
                })
                .catch(err => {
                    const optimisticEl = this.messagesList.querySelector(`[data-message-id="${optimisticMsg.id}"]`);
                    if (optimisticEl) optimisticEl.remove();
                    this.lastMessageIds.delete(optimisticMsg.id);
                    console.error('Error sending message:', err);
                })
                .finally(() => {
                    messageInput.disabled = false;
                    if (sendButton) sendButton.disabled = false;
                    messageInput.focus();
                });
        });
    }

    /**
     * Setup log form handler
     */
    setupLogForm() {
        const logForm = document.getElementById('log-form');
        const logTextarea = document.getElementById('description');
        const logSubmitBtn = document.getElementById('log-submit-btn');

        if (!logForm || !logTextarea || !logSubmitBtn) {
            return;
        }

        logForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            const description = logTextarea.value.trim();
            if (!description) return;

            logTextarea.disabled = true;
            logSubmitBtn.disabled = true;
            logSubmitBtn.textContent = 'Menyimpan...';

            try {
                const response = await safeFetch(logForm.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken()
                    },
                    body: JSON.stringify({ description: description })
                });

                if (response.ok) {
                    logTextarea.value = '';
                    await this.loadLogs();
                    showNotification('Log berhasil ditambahkan!', 'success');
                } else {
                    console.error('Failed to save log:', response.status);
                    showNotification('Gagal menyimpan log. Silakan coba lagi.', 'error');
                }
            } catch (err) {
                console.error('Error saving log:', err);
                showNotification('Terjadi kesalahan. Silakan coba lagi.', 'error');
            } finally {
                logTextarea.disabled = false;
                logSubmitBtn.disabled = false;
                logSubmitBtn.textContent = 'Simpan Log';
                logTextarea.focus();
            }
        });
    }

    /**
     * Append a message to the messages list
     * Staff perspective:
     *   - Guest messages → right side, primary red bubble, white text
     *   - Staff messages → left side, light grey bubble, dark text
     *
     * @param {Object} msg - The message object
     */
    appendMessage(msg) {
        // Don't display duplicates based on ID
        if (msg.id && this.lastMessageIds.has(msg.id)) {
            return;
        }

        const isGuest = msg.sender_type !== 'staff';
        const senderLabel = isGuest ? (msg.sender_name || 'Guest') : (msg.sender_name || 'Staff');

        const msgTime = typeof msg.created_at === 'string'
            ? new Date(msg.created_at)
            : (msg.created_at instanceof Date ? msg.created_at : new Date());

        const timeStr = msgTime.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

        const wrapperDiv = document.createElement('div');
        wrapperDiv.className = `flex ${isGuest ? 'justify-end' : 'justify-start'} mb-3`;
        wrapperDiv.dataset.messageId = msg.id || 'temp';

        if (isGuest) {
            // Guest → right side bubble (red)
            wrapperDiv.innerHTML = `
                <div class="max-w-xs lg:max-w-sm">
                    <p class="text-xs text-gray-500 mb-1 text-right">${senderLabel}</p>
                    <div class="px-4 py-3 rounded-2xl rounded-tr-sm bg-red-600 text-white shadow-sm">
                        <p class="text-sm leading-relaxed whitespace-pre-wrap">${this.escapeHtml(msg.message)}</p>
                        <p class="text-xs opacity-70 mt-1.5 text-right">${timeStr}</p>
                    </div>
                </div>`;
        } else {
            // Staff → left side bubble (grey)
            wrapperDiv.innerHTML = `
                <div class="max-w-xs lg:max-w-sm">
                    <p class="text-xs text-gray-500 mb-1">${senderLabel}</p>
                    <div class="px-4 py-3 rounded-2xl rounded-tl-sm bg-gray-100 text-gray-900 shadow-sm">
                        <p class="text-sm leading-relaxed whitespace-pre-wrap">${this.escapeHtml(msg.message)}</p>
                        <p class="text-xs text-gray-400 mt-1.5">${timeStr}</p>
                    </div>
                </div>`;
        }

        this.messagesList.appendChild(wrapperDiv);

        if (msg.id) this.lastMessageIds.add(msg.id);
        this.scrollMessagesToBottom();
    }

    /**
     * Escape HTML special characters
     * @param {string} str
     * @returns {string}
     */
    escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /**
     * Capitalize first letter of string
     * @param {string} str - The string
     * @returns {string}
     */
    ucfirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    /**
     * Destroy the chat manager and cleanup
     */
    destroy() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
        }
    }
}

/**
 * Initialize ticket chat when DOM is ready
 */
document.addEventListener('DOMContentLoaded', () => {
    const ticketData = document.getElementById('ticket-data');
    if (!ticketData) {
        return;
    }

    const ticketId = ticketData.dataset.ticketId;
    if (!ticketId) {
        return;
    }

    const chatManager = new TicketChatManager(ticketId);
    chatManager.init();

    // Store reference for cleanup if needed
    window.ticketChatManager = chatManager;
});

export { TicketChatManager };