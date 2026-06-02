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
            const logsContainer = document.getElementById('logs-container');
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
                if (!this.ticketId) return;
                window.Echo.channel(channelName)
                    .listen('MessageSent', async (e) => {
                        console.log('📨 New message received via WebSocket', e);
                        await this.loadMessages();
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
                    this.showWebSocketError();
                });

                pusherConnection.bind('disconnected', () => {
                    console.warn('⚠ WebSocket connection closed');
                    this.websocketConnected = false;
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
     * Show WebSocket error message
     */
    showWebSocketError() {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'p-4 bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-200 rounded mb-4';
        errorDiv.innerHTML = '<p class="font-semibold">⚠️ Real-time chat tidak tersedia. Pastikan server Reverb berjalan.</p>';
        const container = document.getElementById('chat-container');
        if (container) {
            container.prepend(errorDiv);
        }
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

        // Fallback polling every 5 seconds if WebSocket not available
        this.pollingInterval = setInterval(() => {
            if (!this.websocketConnected) {
                this.loadMessages();
            }
        }, 5000);
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

            // Optimistic UI - show message immediately
            const optimisticMsg = {
                id: 'temp-' + Date.now(),
                message: message,
                sender_type: 'staff',
                sender_name: messageInput.dataset.senderName || 'Staff',
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
                        // Reload messages to update with actual ID from server
                        setTimeout(() => this.loadMessages(), 500);
                    } else {
                        // Remove optimistic message if failed
                        const optimisticEl = Array.from(this.messagesList.children).pop();
                        if (optimisticEl) optimisticEl.remove();
                        this.lastMessageIds.delete(optimisticMsg.id);
                        console.error('Failed to send message:', res.status);
                    }
                })
                .catch(err => {
                    // Remove optimistic message if error
                    const optimisticEl = Array.from(this.messagesList.children).pop();
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
     * @param {Object} msg - The message object
     */
    appendMessage(msg) {
        // Don't display duplicates based on ID
        if (msg.id && this.lastMessageIds.has(msg.id)) {
            return;
        }

        const div = document.createElement('div');
        div.className = `mb-3 ${msg.sender_type === 'staff' ? 'text-right' : 'text-left'}`;
        div.dataset.messageId = msg.id || 'temp';

        // Add sender name
        const senderDiv = document.createElement('div');
        senderDiv.className = 'text-xs text-gray-500 mb-1';
        senderDiv.textContent = msg.sender_name || 'Guest';
        div.appendChild(senderDiv);

        const messageBubble = document.createElement('div');
        messageBubble.className = `inline-block max-w-xs px-4 py-3 rounded-2xl ${msg.sender_type === 'staff' ? 'bg-red-600 text-white' : 'bg-gray-200 dark:bg-gray-600 text-gray-900 dark:text-gray-100'}`;

        const messageText = document.createElement('p');
        messageText.className = 'text-sm leading-relaxed';
        messageText.textContent = msg.message;

        const timestamp = document.createElement('p');
        timestamp.className = 'text-xs opacity-75 mt-2';
        const msgTime = typeof msg.created_at === 'string'
            ? new Date(msg.created_at)
            : msg.created_at;
        timestamp.textContent = msgTime.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });

        messageBubble.appendChild(messageText);
        messageBubble.appendChild(timestamp);
        div.appendChild(messageBubble);
        this.messagesList.appendChild(div);

        if (msg.id) this.lastMessageIds.add(msg.id);
        this.scrollMessagesToBottom();
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