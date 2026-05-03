<!-- Articles Page Chat - Hybrid Chatbot & Staff Chat -->
<div id="articles-chat-container" class="hidden fixed bottom-4 right-4 z-50">
    <!-- Chat Bubble Button -->
    <div id="articles-chat-bubble" class="bg-red-500 text-white rounded-full p-4 shadow-lg cursor-pointer hover:bg-red-600 transition">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
        </svg>
    </div>

    <!-- Chat Modal -->
    <div id="articles-chat-modal" class="hidden absolute bottom-20 right-0 w-96 h-[500px] bg-white rounded-lg shadow-2xl border border-gray-200 flex flex-col">
        <!-- Header -->
        <div class="bg-gradient-to-r from-red-500 to-red-600 text-white p-4 rounded-t-lg">
            <div>
                <h3 id="chat-title" class="font-bold text-lg">Bantuan</h3>
            </div>
        </div>

        <!-- Messages Container -->
        <div id="articles-chat-messages" class="flex-1 overflow-y-auto p-4 bg-gray-50 space-y-4">
            <!-- Messages will be loaded here -->
        </div>

        <!-- Ticket Button Offer -->
        <div id="ticket-offer-container" class="hidden border-t border-gray-200 p-4 bg-gray-50">
            <button id="ticket-offer-btn" type="button" class="w-full bg-blue-600 text-white text-xs font-semibold py-2 rounded hover:bg-blue-700 transition">
                Buat Tiket untuk Bantuan Lebih Lanjut
            </button>
        </div>

        <!-- Ticket Creation Form (for chatbot) -->
        <div id="ticket-form-container" class="hidden border-t border-gray-200 p-4 bg-white max-h-48 overflow-y-auto">
            <p class="text-xs text-gray-600 font-semibold mb-3">Buat Tiket untuk Bantuan Lebih Lanjut</p>
            <form id="articles-ticket-form" class="space-y-3">
                <input type="hidden" name="message" id="form-message">
                
                <input type="text" name="title" placeholder="Judul masalah" class="w-full text-xs px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" required>
                
                <select name="category_id" class="w-full text-xs px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" required>
                    <option value="">Pilih Kategori</option>
                    @foreach($categories ?? [] as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                
                <input type="email" name="email" placeholder="Email Anda (opsional)" class="w-full text-xs px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                
                <textarea name="message_detail" placeholder="Jelaskan masalah Anda secara detail" rows="3" class="w-full text-xs px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500 resize-none" required></textarea>
                
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 bg-blue-600 text-white text-xs font-semibold py-2 rounded hover:bg-blue-700 transition">
                        Buat Tiket
                    </button>
                    <button type="button" id="articles-ticket-cancel" class="flex-1 bg-gray-200 text-gray-700 text-xs font-semibold py-2 rounded hover:bg-gray-300 transition">
                        Batal
                    </button>
                </div>
            </form>
        </div>

        <!-- Input Area -->
        <div class="border-t border-gray-200 p-4 bg-white rounded-b-lg">
            <form id="articles-chat-form" class="flex gap-2">
                <input 
                    type="text" 
                    id="articles-chat-input" 
                    placeholder="Ketik pesan..." 
                    class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-sm"
                    autocomplete="off"
                >
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition font-semibold">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5.951-1.488 5.951 1.488a1 1 0 001.169-1.409l-7-14z"></path>
                    </svg>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('articles-chat-container');
    const chatBubble = document.getElementById('articles-chat-bubble');
    const chatModal = document.getElementById('articles-chat-modal');
    const messagesContainer = document.getElementById('articles-chat-messages');
    const chatForm = document.getElementById('articles-chat-form');
    const chatInput = document.getElementById('articles-chat-input');
    const chatTitle = document.getElementById('chat-title');
    const ticketOfferContainer = document.getElementById('ticket-offer-container');
    const ticketOfferBtn = document.getElementById('ticket-offer-btn');
    const ticketFormContainer = document.getElementById('ticket-form-container');
    const ticketForm = document.getElementById('articles-ticket-form');
    const ticketFormCancel = document.getElementById('articles-ticket-cancel');

    let ticketId = null;
    let chatMode = 'chatbot'; // 'chatbot' or 'staff'
    let pollInterval = null;
    let waitingStartTime = null; // Track when waiting started
    let waitingTimer = null; // Timer for 20-minute auto-cancel
    let isWaitingMode = false; // Track if currently in waiting mode

    // Retrieve guest email from localStorage if available
    function getGuestEmail() {
        return localStorage.getItem('guest_email') || '';
    }

    function setGuestEmail(email) {
        if (email) {
            localStorage.setItem('guest_email', email);
        }
    }

    // Check if user has an active ticket
    async function checkActiveTicket() {
        try {
            const email = getGuestEmail();
            const storedTicketId = localStorage.getItem('guest_ticket_id');
            let url = '/api/articles/active-ticket';
            const params = [];
            
            if (email) params.push('email=' + encodeURIComponent(email));
            if (storedTicketId) params.push('ticket_id=' + encodeURIComponent(storedTicketId));
            
            if (params.length > 0) {
                url += '?' + params.join('&');
            }
            
            console.log('Checking active ticket with URL:', url);
            
            const response = await fetch(url, {
                headers: { 'Accept': 'application/json' }
            });

            if (response.ok) {
                const data = await response.json();
                console.log('Active ticket check result:', data);
                
                if (data.ticket_id) {
                    ticketId = data.ticket_id;
                    localStorage.setItem('guest_ticket_id', ticketId);
                    // Auto show the chat if ticket exists
                    container.classList.remove('hidden');

                    // Check ticket status to determine mode
                    await checkTicketStatus();

                    // If status check didn't switch modes, default to chat mode
                    if (chatMode === 'chatbot') {
                        switchToChatMode();
                        loadStaffMessages();
                        startPolling();
                    }
                    return true;
                }
            }
        } catch (error) {
            console.error('Error checking active ticket:', error);
        }
        
        // Show chat container for chatbot
        container.classList.remove('hidden');
        switchToChatbotMode();
        loadInitialMessage();
        return false;
    }

    // Switch to chatbot mode
    function switchToChatbotMode() {
        chatMode = 'chatbot';
        chatTitle.textContent = 'SiMinfo - Si Pemberi Informasi';
        ticketFormContainer.classList.add('hidden');
        ticketOfferContainer.classList.add('hidden');
        stopPolling();
        stopWaitingTimer();
        loadInitialMessage();
    }

    // Switch to staff chat mode
    function switchToChatMode() {
        console.log('Switching to staff chat mode for ticket:', ticketId);
        chatMode = 'staff';
        chatTitle.textContent = 'Chat dengan Staff';
        ticketFormContainer.classList.add('hidden');
        ticketOfferContainer.classList.add('hidden');
        loadStaffMessages();
        setupWebSocketListener();
        startPolling();
    }

    // Switch to waiting mode
    function switchToWaitingMode() {
        console.log('Switching to waiting mode for ticket:', ticketId);
        isWaitingMode = true;
        chatMode = 'waiting';
        chatTitle.textContent = 'Menunggu Staff';
        ticketFormContainer.classList.add('hidden');
        ticketOfferContainer.classList.add('hidden');
        showWaitingScreen();
        startWaitingTimer();
        startPolling();
    }

    // Show waiting screen
    function showWaitingScreen() {
        messagesContainer.innerHTML = '';

        const waitingDiv = document.createElement('div');
        waitingDiv.className = 'flex flex-col items-center justify-center h-full text-center p-6';
        waitingDiv.innerHTML = `
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-6 max-w-sm">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-yellow-600 mx-auto mb-4"></div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Menunggu Staff Tersedia</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Staff kami sedang melayani pelanggan lain. Mohon tunggu sebentar.
                </p>
                <div class="text-xs text-gray-500">
                    <p id="waiting-time">Waktu menunggu: 00:00</p>
                    <p class="mt-1">Jika tidak ada staff tersedia dalam 20 menit, tiket akan ditutup otomatis dan Anda diminta mengisi ulang formulir.</p>
                </div>
            </div>
        `;
        messagesContainer.appendChild(waitingDiv);

        // Update waiting time every second
        updateWaitingTime();
        setInterval(updateWaitingTime, 1000);
    }

    // Update waiting time display
    function updateWaitingTime() {
        if (!waitingStartTime || !isWaitingMode) return;

        const now = new Date();
        const elapsed = Math.floor((now - waitingStartTime) / 1000);
        const minutes = Math.floor(elapsed / 60);
        const seconds = elapsed % 60;

        const timeElement = document.getElementById('waiting-time');
        if (timeElement) {
            timeElement.textContent = `Waktu menunggu: ${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }
    }

    // Start 20-minute waiting timer
    function startWaitingTimer() {
        if (waitingTimer) clearTimeout(waitingTimer);

        waitingStartTime = new Date();
        waitingTimer = setTimeout(async () => {
            await autoCloseTicket();
        }, 20 * 60 * 1000); // 20 minutes
    }

    // Stop waiting timer
    function stopWaitingTimer() {
        if (waitingTimer) {
            clearTimeout(waitingTimer);
            waitingTimer = null;
        }
        waitingStartTime = null;
        isWaitingMode = false;
    }

    // Auto cancel ticket after 20 minutes
    async function autoCloseTicket() {
        if (!ticketId) return;

        try {
            console.log('Auto-closing ticket after 20 minutes:', ticketId);

            const response = await fetch(`/api/tickets/${ticketId}/close`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            });

            localStorage.removeItem('guest_ticket_id');

            showAutoClosedMessage();

        } catch (error) {
            console.error('Error auto-closing ticket:', error);
        }
    }

    // Show auto-closed message and prompt refill form
    function showAutoClosedMessage() {
        messagesContainer.innerHTML = '';

        const closeDiv = document.createElement('div');
        closeDiv.className = 'flex flex-col items-center justify-center h-full text-center p-6';
        closeDiv.innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-lg p-6 max-w-sm">
                <div class="text-red-600 text-4xl mb-4">⏰</div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Waktu Tunggu Habis</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Maaf, tidak ada staff yang tersedia dalam 20 menit. Tiket Anda telah ditutup otomatis.
                </p>
                <p class="text-sm text-gray-700 mb-4">
                    Silakan isi ulang formulir agar kami dapat membantu Anda kembali.
                </p>
                <button onclick="showTicketForm('')" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm font-semibold">
                    Isi Ulang Formulir
                </button>
            </div>
        `;
        messagesContainer.appendChild(closeDiv);

        ticketId = null;
        stopWaitingTimer();
        stopPolling();
    }

    // Show message when staff is unresponsive
    function showStaffUnresponsiveMessage() {
        messagesContainer.innerHTML = '';

        const unrespDiv = document.createElement('div');
        unrespDiv.className = 'flex flex-col items-center justify-center h-full text-center p-6';
        unrespDiv.innerHTML = `
            <div class="bg-orange-50 border border-orange-200 rounded-lg p-6 max-w-sm">
                <div class="text-orange-600 text-4xl mb-4">⚠️</div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">Staff Tidak Responsif</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Maaf, staff kami tidak merespons dalam 20 menit setelah menerima tiket Anda. Tiket telah ditutup otomatis.
                </p>
                <p class="text-sm text-gray-700 mb-4">
                    Silakan isi ulang formulir agar kami dapat membantu Anda kembali dengan staff yang lain.
                </p>
                <button onclick="showTicketForm('')" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition text-sm font-semibold">
                    Isi Ulang Formulir
                </button>
            </div>
        `;
        messagesContainer.appendChild(unrespDiv);

        ticketId = null;
        stopWaitingTimer();
        stopPolling();
    }

    // Load initial chatbot message
    function loadInitialMessage() {
        messagesContainer.innerHTML = '';
        const msgDiv = document.createElement('div');
        msgDiv.className = 'flex items-start';
        msgDiv.innerHTML = `
            <div class="bg-blue-100 text-blue-900 rounded-lg p-3 max-w-xs">
                <p class="text-sm font-semibold">Halo! 👋</p>
                <p class="text-sm mt-2">Kami siap membantu Anda. Silakan jelaskan masalah atau pertanyaan Anda, dan kami akan mencari artikel yang relevan.</p>
            </div>
        `;
        messagesContainer.appendChild(msgDiv);
    }

    // Load staff chat messages
    async function loadStaffMessages() {
        if (!ticketId) return;
        
        try {
            let url = `/api/tickets/${ticketId}/messages`;
            const email = getGuestEmail();
            if (email) {
                url += '?email=' + encodeURIComponent(email);
            }
            console.log('Loading messages from URL:', url);
            const response = await fetch(url, {
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) {
                console.warn('Failed to load messages:', response.status, response.statusText);
                if (response.status === 403) {
                    await checkTicketStatus();
                }
                return;
            }

            const messages = await response.json();
            console.log('Loaded messages:', messages);
            messagesContainer.innerHTML = '';

            messages.forEach(msg => {
                const msgDiv = document.createElement('div');
                const isGuest = msg.sender_type === 'guest';
                msgDiv.className = isGuest ? 'flex justify-end' : 'flex justify-start';
                
                const bubble = document.createElement('div');
                bubble.className = isGuest 
                    ? 'bg-blue-600 text-white rounded-lg p-3 max-w-xs'
                    : 'bg-gray-200 text-gray-900 rounded-lg p-3 max-w-xs';
                
                bubble.innerHTML = `
                    <p class="text-xs font-semibold mb-1">${msg.sender_name || 'Staff'}</p>
                    <p class="text-sm break-words">${msg.message}</p>
                `;
                
                msgDiv.appendChild(bubble);
                messagesContainer.appendChild(msgDiv);
            });

            messagesContainer.scrollTop = messagesContainer.scrollHeight;

            // Check ticket status
            await checkTicketStatus();
        } catch (error) {
            console.error('Error loading messages:', error);
        }
    }

    // Check ticket status
    async function checkTicketStatus() {
        if (!ticketId) return;

        try {
            const response = await fetch(`/api/tickets/${ticketId}/status`, {
                headers: { 'Accept': 'application/json' }
            });

            if (response.ok) {
                const data = await response.json();

                // If ticket is auto-closed due to staff inactivity
                if (data.auto_closed && data.status === 'closed') {
                    ticketId = null;
                    localStorage.removeItem('guest_ticket_id');
                    showStaffUnresponsiveMessage();
                    stopPolling();
                    stopWaitingTimer();
                }
                // If ticket is closed or completed, switch back to chatbot
                else if (['closed', 'completed'].includes(data.status)) {
                    ticketId = null;
                    localStorage.removeItem('guest_ticket_id');
                    switchToChatbotMode();
                    stopPolling();
                    stopWaitingTimer();
                }
                // If ticket is waiting and no staff is assigned, switch to waiting mode
                else if (data.status === 'waiting' && !data.assigned_staff) {
                    if (!isWaitingMode) {
                        switchToWaitingMode();
                    }
                }
                // If ticket is waiting and has staff, treat it as finished for guest
                else if (data.status === 'waiting' && data.assigned_staff) {
                    ticketId = null;
                    localStorage.removeItem('guest_ticket_id');
                    switchToChatbotMode();
                    stopPolling();
                    stopWaitingTimer();
                }
                // If ticket is assigned and has staff, stay in waiting mode until staff mulai mengerjakan
                else if (data.status === 'assigned' && data.assigned_staff) {
                    if (!isWaitingMode) {
                        switchToWaitingMode();
                    }
                }
                // If staff sudah mulai mengerjakan, switch to chat mode
                else if (data.status === 'progress' && data.assigned_staff) {
                    if (isWaitingMode || chatMode !== 'staff') {
                        stopWaitingTimer();
                        switchToChatMode();
                    }
                }
                // If ticket is open but no staff assigned yet, switch to waiting
                else if (data.status === 'open' && !data.assigned_staff) {
                    if (!isWaitingMode) {
                        switchToWaitingMode();
                    }
                }
            }
        } catch (error) {
            console.error('Error checking status:', error);
        }
    }

    // Send message
    chatForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const message = chatInput.value.trim();
        if (!message) return;

        // Hide any previous ticket offer for a new user query
        ticketOfferContainer.classList.add('hidden');

        if (chatMode === 'chatbot') {
            // Send to chatbot
            await sendChatbotMessage(message);
        } else if (chatMode === 'staff' && ticketId) {
            // Send to staff
            await sendStaffMessage(message);
        }

        chatInput.value = '';
    });

    // Send chatbot message
    async function sendChatbotMessage(message) {
        // Display user message
        const msgDiv = document.createElement('div');
        msgDiv.className = 'flex justify-end';
        msgDiv.innerHTML = `
            <div class="bg-blue-600 text-white rounded-lg p-3 max-w-xs">
                <p class="text-sm break-words">${message}</p>
            </div>
        `;
        messagesContainer.appendChild(msgDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;

        // Get chatbot response
        try {
            const response = await fetch('/chatbot/get-response', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ message: message })
            });

            if (response.ok) {
                const data = await response.json();
                
                // Display bot response
                const botDiv = document.createElement('div');
                botDiv.className = 'flex justify-start';
                botDiv.innerHTML = `
                    <div class="bg-blue-100 text-blue-900 rounded-lg p-3 max-w-xs">
                        <p class="text-sm break-words">${data.response || 'Maaf, saya tidak dapat memproses pertanyaan Anda.'}</p>
                    </div>
                `;
                messagesContainer.appendChild(botDiv);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;

                // Display related articles if any
                if (data.articles && data.articles.length > 0) {
                    const articlesDiv = document.createElement('div');
                    articlesDiv.className = 'flex justify-start';
                    articlesDiv.innerHTML = `
                        <div class="bg-gray-100 text-gray-900 rounded-lg p-3 max-w-xs">
                            <p class="text-xs font-semibold mb-2">📚 Artikel Terkait:</p>
                            <ul class="space-y-1">
                                ${data.articles.map(article => `
                                    <li>
                                        <a href="/articles/${article.slug}" target="_blank" class="text-blue-600 hover:text-blue-800 text-xs underline">
                                            ${article.title}
                                        </a>
                                    </li>
                                `).join('')}
                            </ul>
                        </div>
                    `;
                    messagesContainer.appendChild(articlesDiv);
                }

                // If chatbot suggests contacting support, offer a ticket button first
                if (data.suggest_ticket || data.show_contact_button) {
                    showTicketOffer(message, data.contact_button_text || 'Buat Laporan untuk Bantuan Lebih Lanjut');
                }

                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        } catch (error) {
            console.error('Error getting chatbot response:', error);
        }
    }

    function showTicketOffer(message, buttonText = 'Buat Laporan untuk Bantuan Lebih Lanjut') {
        ticketOfferContainer.classList.remove('hidden');
        ticketOfferBtn.textContent = buttonText;
        ticketOfferBtn.dataset.message = message;
        ticketFormContainer.classList.add('hidden');
    }

    function showTicketForm(message) {
        ticketOfferContainer.classList.add('hidden');
        ticketFormContainer.classList.remove('hidden');
        document.getElementById('form-message').value = message;
        // Auto-scroll to bottom
        setTimeout(() => {
            ticketFormContainer.scrollIntoView({ behavior: 'smooth', block: 'end' });
        }, 100);
    }

    function hideTicketForm() {
        ticketOfferContainer.classList.add('hidden');
        ticketFormContainer.classList.add('hidden');
        ticketForm.reset();
    }

    // Handle ticket form submission
    ticketForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const formData = {
            name: 'Guest User',
            title: this.querySelector('input[name="title"]').value,
            subject: this.querySelector('input[name="title"]').value,
            category_id: this.querySelector('select[name="category_id"]').value,
            email: this.querySelector('input[name="email"]').value,
            message: this.querySelector('textarea[name="message_detail"]').value,
        };

        // Store email for future reference
        if (formData.email) {
            setGuestEmail(formData.email);
        }

        try {
            const response = await fetch('/tickets', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(formData)
            });

            if (response.ok) {
                const data = await response.json();
                ticketId = data.ticket_id || data.id;
                
                if (ticketId) {
                    localStorage.setItem('guest_ticket_id', ticketId);
                    
                    // Show success message
                    const successDiv = document.createElement('div');
                    successDiv.className = 'flex justify-start';
                    successDiv.innerHTML = `
                        <div class="bg-green-100 text-green-900 rounded-lg p-3 max-w-xs">
                            <p class="text-sm font-semibold">✓ Tiket berhasil dibuat!</p>
                            <p class="text-xs mt-1">Staff sedang menghubungi Anda...</p>
                        </div>
                    `;
                    messagesContainer.appendChild(successDiv);
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;

                    // Switch to chat mode immediately since staff is assigned
                    setTimeout(() => {
                        ticketForm.reset();
                        ticketFormContainer.classList.add('hidden');
                        console.log('Switching to chat mode after ticket creation, ticketId:', ticketId);
                        switchToChatMode();
                    }, 2000); // Give user time to see success message
                }
            }
        } catch (error) {
            console.error('Error creating ticket:', error);
            const errorMsg = document.createElement('div');
            errorMsg.className = 'flex justify-start';
            errorMsg.innerHTML = `
                <div class="bg-red-100 text-red-900 rounded-lg p-3 max-w-xs">
                    <p class="text-sm font-semibold">❌ Gagal membuat tiket</p>
                    <p class="text-xs mt-1">${error.message}</p>
                </div>
            `;
            messagesContainer.appendChild(errorMsg);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    });

    ticketOfferBtn.addEventListener('click', function() {
        showTicketForm(ticketOfferBtn.dataset.message || '');
    });

    ticketFormCancel.addEventListener('click', hideTicketForm);

    // Send staff message
    async function sendStaffMessage(message) {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'flex justify-end';
        msgDiv.innerHTML = `
            <div class="bg-blue-600 text-white rounded-lg p-3 max-w-xs">
                <p class="text-sm break-words">${message}</p>
            </div>
        `;
        messagesContainer.appendChild(msgDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;

        try {
            const requestData = {
                ticket_id: ticketId,
                message: message,
                sender_type: 'guest'
            };
            
            const email = getGuestEmail();
            if (email) {
                requestData.email = email;
            }
            
            const response = await fetch('/api/messages', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(requestData)
            });

            if (response.ok) {
                // Reload messages to show the sent message
                await loadStaffMessages();
            }
        } catch (error) {
            console.error('Error sending message:', error);
        }
    }

    function startPolling() {
        if (pollInterval) return;
        pollInterval = setInterval(async () => {
            if (ticketId) {
                await loadStaffMessages();
            }
        }, 5000);
    }

    function stopPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }

    // Setup WebSocket listener for real-time updates
    function setupWebSocketListener() {
        if (!ticketId || typeof window.Echo === 'undefined') {
            console.log('WebSocket not available or no ticket ID');
            return;
        }

        try {
            const channelName = `ticket.${ticketId}`;
            console.log('Setting up WebSocket listener for:', channelName);

            window.Echo.channel(channelName)
                .listen('.MessageSent', (e) => {
                    console.log('Received WebSocket message:', e);
                    // Reload messages to show new message
                    loadStaffMessages();
                });

            console.log('WebSocket listener setup complete');
        } catch (error) {
            console.error('Failed to setup WebSocket listener:', error);
            // Fallback to polling if WebSocket fails
            startPolling();
        }
    }

    // Toggle chat modal
    chatBubble.addEventListener('click', function() {
        chatModal.classList.toggle('hidden');
        if (!chatModal.classList.contains('hidden')) {
            if (chatMode === 'staff') {
                loadStaffMessages();
            }
        }
    });

    // Initialize on page load
    checkActiveTicket();

    // Cleanup on page unload
    window.addEventListener('beforeunload', stopPolling);
});
</script>
