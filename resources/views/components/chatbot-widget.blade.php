@props(['show' => true])

<div id="chatbot-widget" class="fixed bottom-4 right-4 w-96 bg-white rounded-2xl shadow-2xl flex flex-col z-50 hidden overflow-hidden" style="height:600px;">

    <!-- Ticket Closed Overlay (shown when staff ends session) -->
    <div id="chatbot-closed-overlay" class="absolute inset-0 bg-white/95 backdrop-blur-sm z-50 flex flex-col items-center justify-center p-6 text-center hidden rounded-2xl">
        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>
        <h4 class="text-lg font-bold text-gray-900 mb-2">Percakapan Telah Selesai</h4>
        <p class="text-sm text-gray-600 mb-4 leading-relaxed">Sesi live chat ini telah ditutup oleh staff.</p>
        <p class="text-xs text-gray-400">Terima kasih telah menggunakan layanan Helpdesk TA.</p>
        <div class="mt-5 flex items-center gap-2 text-xs text-gray-400">
            <div class="w-4 h-4 border-2 border-gray-300 border-t-green-500 rounded-full animate-spin"></div>
            Menutup otomatis dalam beberapa detik...
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- HEADER                                                         -->
    <!-- ============================================================ -->
    <div id="chatbot-header" class="bg-gradient-to-r from-red-600 to-red-700 text-white px-4 py-3 rounded-t-2xl flex justify-between items-center flex-shrink-0">
        <div class="flex items-center gap-3">
            <!-- Avatar / Icon wrapper -->
            <div id="chatbot-header-avatar" class="w-9 h-9 rounded-full bg-white/20 flex items-center justify-center flex-shrink-0">
                <!-- AI Icon (default) -->
                <svg id="chatbot-avatar-ai" class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 5a2 2 0 012-2h12a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5z"></path>
                </svg>
                <!-- Staff online dot -->
                <span id="chatbot-avatar-live" class="hidden text-sm font-bold leading-none"></span>
            </div>
            <div>
                <h3 id="chatbot-header-title" class="font-bold text-sm leading-tight">SiMinfo Assistant</h3>
                <div class="flex items-center gap-1.5 mt-0.5">
                    <span id="chatbot-header-dot" class="w-1.5 h-1.5 rounded-full bg-red-300"></span>
                    <p id="chatbot-status" class="text-xs text-red-200">Bantuan Otomatis</p>
                </div>
            </div>
        </div>
        <button id="chatbot-close" class="text-white/80 hover:text-white hover:bg-white/20 p-1.5 rounded-lg transition flex-shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    <!-- ============================================================ -->
    <!-- CHAT MESSAGES                                                  -->
    <!-- ============================================================ -->
    <div id="chatbot-messages" class="flex-1 overflow-y-auto px-3 py-3 bg-gray-50 space-y-1">
        <!-- Initial Greeting -->
        <div id="chatbot-greeting" class="space-y-2" data-chatbot-greeting>
            <!-- Bot greeting bubble -->
            <div class="flex items-end gap-2 justify-start">
                <div class="w-6 h-6 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0 mb-0.5">
                    <svg class="w-3.5 h-3.5 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M2 5a2 2 0 012-2h12a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5z"></path>
                    </svg>
                </div>
                <div class="max-w-[75%]">
                    <div class="bg-white border border-gray-200 text-gray-800 rounded-2xl rounded-bl-sm px-3.5 py-2.5 shadow-sm" data-chatbot-greeting-message>
                        <p class="text-sm font-semibold">Halo!</p>
                        <p class="text-sm mt-0.5 text-gray-600">Ada yang bisa saya bantu?</p>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1 ml-1">Bot</p>
                </div>
            </div>
            <!-- Category chips -->
            <div id="category-chips" class="flex flex-wrap gap-1.5 pl-8" data-chatbot-categories>
                <!-- Categories will be loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- CLARIFICATION PANEL (below messages, above input)             -->
    <!-- ============================================================ -->
    <div id="chatbot-clarification" class="hidden px-3 py-2.5 bg-white border-t border-gray-100 flex-shrink-0">
        <p id="clarification-question" class="text-xs text-gray-600 font-semibold mb-2"></p>
        <div id="clarification-suggestions" class="flex flex-wrap gap-1.5">
            <!-- Clarification suggestions will be loaded dynamically -->
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- SUBTOPIC PANEL                                                 -->
    <!-- ============================================================ -->
    <div id="chatbot-subtopics" class="hidden px-3 py-2.5 bg-white border-t border-gray-100 max-h-28 overflow-y-auto flex-shrink-0">
        <p id="subtopic-question" class="text-xs text-gray-600 font-semibold mb-2"></p>
        <div id="subtopics-list" class="flex flex-wrap gap-1.5">
            <!-- Subtopics will be loaded dynamically -->
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- SEARCH SUGGESTIONS DROPDOWN                                   -->
    <!-- ============================================================ -->
    <div id="search-suggestions" class="hidden absolute bottom-[72px] left-3 right-3 bg-white rounded-xl shadow-lg border border-gray-200 max-h-48 overflow-y-auto z-10">
        <div id="search-suggestions-list" class="py-1">
            <!-- Search suggestions will be loaded dynamically -->
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- CONTACT STAFF BUTTON (kept for API compatibility)             -->
    <!-- ============================================================ -->
    <div id="chatbot-response" class="hidden flex-shrink-0">
        <div id="contact-button-container" class="hidden px-3 pb-2">
            <button id="contact-staff-btn" class="w-full bg-red-600 text-white text-xs font-semibold py-2 px-4 rounded-xl hover:bg-red-700 transition">
                Buat Tiket untuk Bantuan Lebih Lanjut
            </button>
        </div>
    </div>

    <!-- ============================================================ -->
    <!-- TICKET CREATION FORM (compact, in-widget)                    -->
    <!-- ============================================================ -->
    <div id="chatbot-form" class="hidden px-3 py-3 bg-white border-t border-gray-100 max-h-52 overflow-y-auto flex-shrink-0">
        <p class="text-xs text-gray-500 font-semibold mb-2 flex items-center gap-1">
            <svg class="w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path></svg>
            Buat Tiket
        </p>
        <form id="ticket-form" class="space-y-2">
            <input type="hidden" name="message" id="form-message">
            <input type="text" name="title" placeholder="Judul masalah" class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-red-400 transition" required>
            <select name="category_id" class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-red-400 transition" required>
                <option value="">Pilih Kategori</option>
                @foreach($categories ?? [] as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
            <input type="email" name="email" placeholder="Email Anda (opsional)" class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-red-400 transition">
            <textarea name="message" placeholder="Jelaskan masalah Anda" rows="2" class="w-full px-3 py-2 border border-gray-200 rounded-xl text-xs text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-red-400 transition resize-none" required></textarea>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-red-600 text-white text-xs font-semibold py-2 rounded-xl hover:bg-red-700 transition">Buat Tiket</button>
                <button type="button" id="chatbot-cancel-btn" class="flex-1 bg-gray-100 text-gray-600 text-xs font-semibold py-2 rounded-xl hover:bg-gray-200 transition">Batal</button>
            </div>
        </form>
    </div>

    <!-- ============================================================ -->
    <!-- INPUT AREA                                                    -->
    <!-- ============================================================ -->
    <div class="border-t border-gray-200 px-3 py-3 bg-white rounded-b-2xl flex-shrink-0">
        <form id="chatbot-form-input" class="flex items-center gap-2">
            <div class="flex-1 relative">
                <input
                    type="text"
                    id="chatbot-input"
                    placeholder="Tanyakan sesuatu..."
                    class="w-full px-3.5 py-2.5 pr-8 border border-gray-200 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-red-400 transition"
                    autocomplete="off"
                >
                <button type="button" id="clear-input" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 hidden">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
            <button type="submit" id="chatbot-send-btn" class="w-9 h-9 bg-red-600 hover:bg-red-700 text-white rounded-xl flex items-center justify-center flex-shrink-0 transition shadow-sm hover:shadow-md">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5.951-1.488 5.951 1.488a1 1 0 001.169-1.409l-7-14z"></path>
                </svg>
            </button>
        </form>
    </div>
</div>

<!-- ================================================================ -->
<!-- FLOATING TOGGLE BUTTON                                            -->
<!-- ================================================================ -->
<button id="chatbot-toggle" class="fixed bottom-4 right-4 bg-red-600 hover:bg-red-700 text-white rounded-full p-4 shadow-lg transition transform hover:scale-105 z-50" title="Bantuan & Support">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M3 18v-6a9 9 0 0118 0v6"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M21 19a2 2 0 01-2 2h-1a2 2 0 01-2-2v-3a2 2 0 012-2h3zM3 19a2 2 0 002 2h1a2 2 0 002-2v-3a2 2 0 00-2-2H3z"/>
    </svg>
</button>

<!-- ================================================================ -->
<!-- STYLES                                                            -->
<!-- ================================================================ -->
<style>
    /* Widget show/hide */
    #chatbot-widget.show { display: flex; }
    #chatbot-toggle.hide  { display: none; }

    /* ---- CHAT BUBBLES ---- */
    /* Bot bubble: white card with border, left-aligned */
    .cb-bot-bubble {
        background: #ffffff;
        border: 1px solid #e5e7eb;
        color: #1f2937;
        border-radius: 1rem 1rem 1rem 0.2rem;
        padding: 0.55rem 0.85rem;
        max-width: 76%;
        font-size: 0.8125rem;
        line-height: 1.5;
        box-shadow: 0 1px 2px rgba(0,0,0,.05);
        word-break: break-words;
    }

    /* Guest bubble: red, right-aligned */
    .cb-guest-bubble {
        background: #dc2626;
        color: #ffffff;
        border-radius: 1rem 1rem 0.2rem 1rem;
        padding: 0.55rem 0.85rem;
        min-width: fit-content;
        max-width: 75%;
        width: auto;
        font-size: 0.8125rem;
        line-height: 1.5;
        box-shadow: 0 1px 3px rgba(220,38,38,.3);
        white-space: normal;
        word-break: normal;
        overflow-wrap: break-word;
    }

    /* Staff bubble: warm gray, left-aligned */
    .cb-staff-bubble {
        background: #f3f4f6;
        color: #1f2937;
        border-radius: 1rem 1rem 1rem 0.2rem;
        padding: 0.55rem 0.85rem;
        max-width: 76%;
        font-size: 0.8125rem;
        line-height: 1.5;
        box-shadow: 0 1px 2px rgba(0,0,0,.04);
        word-break: break-words;
    }

    /* Timestamps */
    .cb-timestamp {
        font-size: 0.625rem;
        color: #9ca3af;
        margin-top: 0.2rem;
    }

    /* ---- SYSTEM MESSAGE ---- */
    .cb-system-msg {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        justify-content: center;
        padding: 0.375rem 0.75rem;
        margin: 0.5rem auto;
    }

    .cb-system-msg span {
        font-size: 0.6875rem;
        color: #6b7280;
        font-weight: 500;
        white-space: nowrap;
    }

    .cb-system-msg::before,
    .cb-system-msg::after {
        content: '';
        flex: 1;
        height: 1px;
        background: #e5e7eb;
    }

    /* Waiting / searching state bubble */
    .cb-waiting-msg {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        background: #fef9c3;
        border: 1px solid #fde68a;
        border-radius: 1rem;
        padding: 0.9rem 1rem;
        margin: 0.5rem auto;
        max-width: 88%;
        text-align: center;
    }

    .cb-waiting-msg p {
        font-size: 0.75rem;
        color: #92400e;
        font-weight: 500;
    }

    /* Inline article attachment */
    .cb-article-attachment {
        display: flex;
        align-items: center;
        gap: 0.625rem;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-left: 3px solid #dc2626;
        border-radius: 0.625rem;
        padding: 0.5rem 0.625rem;
        cursor: pointer;
        transition: box-shadow 0.15s, border-color 0.15s;
        text-decoration: none;
    }

    .cb-article-attachment:hover {
        box-shadow: 0 2px 8px rgba(220,38,38,0.12);
        border-color: #dc2626;
    }

    .cb-article-attachment .art-icon {
        flex-shrink: 0;
        width: 1.75rem;
        height: 1.75rem;
        background: #fef2f2;
        border-radius: 0.375rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .cb-article-attachment .art-title {
        font-size: 0.75rem;
        font-weight: 600;
        color: #1f2937;
        line-height: 1.3;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .cb-article-attachment .art-badge {
        font-size: 0.6rem;
        font-weight: 600;
        padding: 0.1rem 0.4rem;
        border-radius: 99px;
    }

    .badge-high   { background:#dcfce7; color:#166534; }
    .badge-medium { background:#fef3c7; color:#92400e; }
    .badge-low    { background:#f3f4f6; color:#6b7280; }

    /* ---- CATEGORY / SUGGESTION CHIPS ---- */
    .category-chip {
        padding: 0.3rem 0.75rem;
        background: #fff;
        color: #dc2626;
        font-size: 0.7rem;
        font-weight: 600;
        border-radius: 99px;
        border: 1px solid #fca5a5;
        cursor: pointer;
        transition: all 0.15s;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 200px;
    }
    .category-chip:hover {
        background: #fef2f2;
        border-color: #dc2626;
        transform: translateY(-1px);
    }

    .suggestion-chip {
        padding: 0.25rem 0.6rem;
        background: #fff;
        color: #374151;
        font-size: 0.7rem;
        border-radius: 99px;
        border: 1px solid #d1d5db;
        cursor: pointer;
        transition: all 0.15s;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 200px;
    }
    .suggestion-chip:hover {
        background: #fef2f2;
        border-color: #fca5a5;
        color: #dc2626;
    }

    /* ---- TYPING INDICATOR ---- */
    .typing-dot {
        width: 5px;
        height: 5px;
        background: #9ca3af;
        border-radius: 50%;
        animation: typingBounce 1.4s infinite ease-in-out both;
    }
    .typing-dot:nth-child(1) { animation-delay: -0.32s; }
    .typing-dot:nth-child(2) { animation-delay: -0.16s; }

    @keyframes typingBounce {
        0%, 80%, 100% { transform: scale(0.75); opacity: 0.5; }
        40%           { transform: scale(1);    opacity: 1; }
    }

    /* ---- SCROLLBAR ---- */
    #chatbot-messages::-webkit-scrollbar { width: 4px; }
    #chatbot-messages::-webkit-scrollbar-track { background: transparent; }
    #chatbot-messages::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
    #chatbot-messages::-webkit-scrollbar-thumb:hover { background: #9ca3af; }

    /* Header mode states */
    #chatbot-header.mode-waiting {
        background: linear-gradient(to right, #b45309, #d97706);
    }
    #chatbot-header.mode-live {
        background: linear-gradient(to right, #065f46, #059669);
    }
</style>

<!-- ================================================================ -->
<!-- JAVASCRIPT  (backend/websocket logic preserved 100%)             -->
<!-- ================================================================ -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const widget               = document.getElementById('chatbot-widget');
    const toggle               = document.getElementById('chatbot-toggle');
    const closeBtn             = document.getElementById('chatbot-close');
    const input                = document.getElementById('chatbot-input');
    const clearInputBtn        = document.getElementById('clear-input');
    const form                 = document.getElementById('chatbot-form-input');
    const messagesContainer    = document.getElementById('chatbot-messages');
    const responseContainer    = document.getElementById('chatbot-response');
    const formContainer        = document.getElementById('chatbot-form');
    const ticketForm           = document.getElementById('ticket-form');
    const cancelTicketBtn      = document.getElementById('chatbot-cancel-btn');
    const contactButtonContainer = document.getElementById('contact-button-container');
    const contactStaffBtn      = document.getElementById('contact-staff-btn');
    const clarificationContainer = document.getElementById('chatbot-clarification');
    const clarificationQuestion  = document.getElementById('clarification-question');
    const clarificationSuggestions = document.getElementById('clarification-suggestions');
    const subtopicsContainer   = document.getElementById('chatbot-subtopics');
    const subtopicQuestion     = document.getElementById('subtopic-question');
    const subtopicsList        = document.getElementById('subtopics-list');
    const searchSuggestionsContainer = document.getElementById('search-suggestions');
    const searchSuggestionsList      = document.getElementById('search-suggestions-list');

    let searchDebounceTimer  = null;
    let currentContext       = null;
    let clarificationActive  = false;
    let activeLiveChatTicketId = null;
    let activeLiveChatEmail    = null;
    let chatMode = 'chatbot'; // 'chatbot' | 'waiting_staff' | 'staff_connected'
    let statusPollingInterval = null;

    /* ------------------------------------------------------------------ */
    /* HELPER – current timestamp string                                   */
    /* ------------------------------------------------------------------ */
    function nowTime() {
        return new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    }

    /* ------------------------------------------------------------------ */
    /* UI STATE SYNC  (header + input area)                                */
    /* ------------------------------------------------------------------ */
    function syncChatUiState(mode, staffName) {
        const header     = document.getElementById('chatbot-header');
        const titleEl    = document.getElementById('chatbot-header-title');
        const statusEl   = document.getElementById('chatbot-status');
        const dotEl      = document.getElementById('chatbot-header-dot');
        const avatarAi   = document.getElementById('chatbot-avatar-ai');
        const avatarLive = document.getElementById('chatbot-avatar-live');
        const sendBtn    = document.getElementById('chatbot-send-btn');

        // Remove all mode classes first
        header.classList.remove('mode-waiting', 'mode-live');

        if (mode === 'chatbot') {
            header.classList.remove('mode-waiting', 'mode-live');
            titleEl.textContent  = 'SiMinfo Assistant';
            statusEl.textContent = 'Bantuan Otomatis';
            statusEl.className   = 'text-xs text-red-200';
            dotEl.className      = 'w-1.5 h-1.5 rounded-full bg-red-300';
            avatarAi.classList.remove('hidden');
            avatarLive.classList.add('hidden');
            input.disabled       = false;
            input.placeholder    = 'Tanyakan sesuatu...';
            sendBtn.disabled     = false;
            sendBtn.classList.remove('opacity-50', 'cursor-not-allowed');

        } else if (mode === 'waiting_staff') {
            header.classList.add('mode-waiting');
            titleEl.textContent  = 'SiMinfo Assistant';
            statusEl.textContent = 'Mencari Staff...';
            statusEl.className   = 'text-xs text-yellow-200 animate-pulse';
            dotEl.className      = 'w-1.5 h-1.5 rounded-full bg-yellow-300 animate-pulse';
            avatarAi.classList.remove('hidden');
            avatarLive.classList.add('hidden');
            input.disabled       = true;
            input.placeholder    = 'Mohon tunggu, mencari staff...';
            sendBtn.disabled     = true;
            sendBtn.classList.add('opacity-50', 'cursor-not-allowed');

        } else if (mode === 'staff_connected') {
            header.classList.add('mode-live');
            const name = staffName || localStorage.getItem('guest_livechat_staff_name') || 'Staff';
            titleEl.textContent  = name;
            statusEl.textContent = 'Online';
            statusEl.className   = 'text-xs text-green-200 font-semibold';
            dotEl.className      = 'w-1.5 h-1.5 rounded-full bg-green-300';
            // Show initials in avatar
            avatarAi.classList.add('hidden');
            avatarLive.classList.remove('hidden');
            avatarLive.textContent = name.charAt(0).toUpperCase();
            input.disabled       = false;
            input.placeholder    = 'Ketik pesan untuk staff...';
            sendBtn.disabled     = false;
            sendBtn.classList.remove('opacity-50', 'cursor-not-allowed');
        }
    }

    /* ------------------------------------------------------------------ */
    /* ADD MESSAGE  – bot (left) or guest/user (right)                     */
    /* ------------------------------------------------------------------ */
    function addMessage(text, sender) {
        const isGuest = (sender === 'user');
        const row = document.createElement('div');
        row.className = `flex items-end gap-1.5 mb-1 ${isGuest ? 'justify-end' : 'justify-start'}`;

        if (!isGuest) {
            // Bot avatar
            const av = document.createElement('div');
            av.className = 'w-5 h-5 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0 mb-3';
            av.innerHTML = `<svg class="w-3 h-3 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path d="M2 5a2 2 0 012-2h12a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5z"></path></svg>`;
            row.appendChild(av);
        }

        const wrapper = document.createElement('div');
        wrapper.className = isGuest ? 'flex flex-col items-end' : 'flex flex-col items-start';

        const bubble = document.createElement('div');
        bubble.className = isGuest ? 'cb-guest-bubble' : 'cb-bot-bubble';
        bubble.innerHTML = text;

        const ts = document.createElement('p');
        ts.className = `cb-timestamp ${isGuest ? 'text-right' : 'text-left'}`;
        ts.textContent = nowTime();

        wrapper.appendChild(bubble);
        wrapper.appendChild(ts);
        row.appendChild(wrapper);

        messagesContainer.appendChild(row);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    /* ------------------------------------------------------------------ */
    /* ADD LIVE CHAT MESSAGE  – guest(right) or staff(left + label)        */
    /* ------------------------------------------------------------------ */
    function addLiveChatMessage(senderName, text, senderType, timestamp) {
        const isGuest = senderType === 'guest' || senderType === 'customer';

        let timeStr = '';
        if (timestamp) {
            const d = new Date(timestamp);
            if (!isNaN(d.getTime())) {
                timeStr = d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            }
        }
        if (!timeStr) timeStr = nowTime();

        const row = document.createElement('div');
        row.className = `flex items-end gap-1.5 mb-1 ${isGuest ? 'justify-end' : 'justify-start'}`;

        if (!isGuest) {
            // Staff avatar initials
            const staffDisplayName = senderName || 'Staff';
            const av = document.createElement('div');
            av.className = 'w-6 h-6 rounded-full bg-emerald-100 text-emerald-700 text-[10px] font-bold flex items-center justify-center flex-shrink-0 mb-4';
            av.textContent = staffDisplayName.charAt(0).toUpperCase();
            row.appendChild(av);
        }

        const wrapper = document.createElement('div');
        wrapper.className = isGuest ? 'flex flex-col items-end max-w-[76%]' : 'flex flex-col items-start max-w-[76%]';

        if (!isGuest) {
            const label = document.createElement('p');
            label.className = 'text-[10px] text-gray-500 font-semibold mb-0.5 ml-0.5';
            label.textContent = senderName || 'Staff';
            wrapper.appendChild(label);
        }

        const bubble = document.createElement('div');
        bubble.className = isGuest ? 'cb-guest-bubble' : 'cb-staff-bubble';

        const textPara = document.createElement('p');
        textPara.className = 'leading-relaxed whitespace-pre-wrap';
        textPara.textContent = text;
        bubble.appendChild(textPara);

        const ts = document.createElement('p');
        ts.className = `cb-timestamp ${isGuest ? 'text-right' : 'text-left'}`;
        ts.textContent = timeStr;

        wrapper.appendChild(bubble);
        wrapper.appendChild(ts);
        row.appendChild(wrapper);

        messagesContainer.appendChild(row);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        return row;
    }

    /* ------------------------------------------------------------------ */
    /* SYSTEM MESSAGES                                                      */
    /* ------------------------------------------------------------------ */
    function addSystemMessage(text) {
        const row = document.createElement('div');
        row.className = 'cb-system-msg my-2';
        row.innerHTML = `<span>${text}</span>`;
        messagesContainer.appendChild(row);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function addWaitingMessage() {
        const row = document.createElement('div');
        row.id = 'cb-waiting-indicator';
        row.innerHTML = `
            <div class="cb-waiting-msg">
                <div class="flex items-center gap-1.5">
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                    <span class="typing-dot"></span>
                </div>
                <p id="cb-queue-text">Sedang mencari staff yang tersedia...</p>
                <p id="cb-queue-est" class="text-[10px] text-amber-700/70 mt-0.5">Mohon jangan menutup halaman ini</p>
            </div>`;
        messagesContainer.appendChild(row);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function updateQueuePositionUI(position, estimatedMinutes) {
        const textEl = document.getElementById('cb-queue-text');
        const estEl = document.getElementById('cb-queue-est');
        if (textEl) {
            textEl.innerHTML = `Anda berada di antrean nomor <span class="font-bold text-red-600">#${position}</span>`;
        }
        if (estEl) {
            estEl.textContent = `Estimasi waktu tunggu: ${estimatedMinutes} menit`;
        }
    }

    function removeWaitingMessage() {
        const el = document.getElementById('cb-waiting-indicator');
        if (el) el.remove();
    }

    /* ------------------------------------------------------------------ */
    /* SHOW ARTICLES  – inline compact attachments in chat flow            */
    /* ------------------------------------------------------------------ */
    function showArticles(articles) {
        if (!articles || articles.length === 0) return;

        // Keep the legacy container hidden (no longer used for display)
        const legacyContainer = document.getElementById('chatbot-articles');
        if (legacyContainer) legacyContainer.classList.add('hidden');

        // Render as a bot-side bubble containing compact article links
        const row = document.createElement('div');
        row.className = 'flex items-end gap-1.5 mb-1 justify-start';

        // Bot avatar
        const av = document.createElement('div');
        av.className = 'w-5 h-5 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0 mb-3';
        av.innerHTML = `<svg class="w-3 h-3 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>`;
        row.appendChild(av);

        const wrapper = document.createElement('div');
        wrapper.className = 'flex flex-col items-start' + (articles.length > 1 ? ' gap-1.5' : '');
        wrapper.style.maxWidth = '82%';

        // Label
        const label = document.createElement('p');
        label.className = 'text-[10px] text-gray-500 font-semibold mb-1 ml-0.5';
        label.textContent = 'Artikel Terkait';
        wrapper.appendChild(label);

        articles.forEach(article => {
            const badgeClass = article.confidence === 'high'
                ? 'badge-high'
                : article.confidence === 'medium'
                    ? 'badge-medium'
                    : 'badge-low';
            const badgeText = article.confidence === 'high'
                ? 'Relevan'
                : article.confidence === 'medium'
                    ? 'Cukup Relevan'
                    : 'Mungkin Relevan';

            const link = document.createElement('a');
            link.href = `{{ url('/articles') }}/${article.slug}`;
            link.target = '_blank';
            link.className = 'cb-article-attachment';
            link.style.width = '100%';

            link.innerHTML = `
                <div class="art-icon">
                    <svg class="w-3.5 h-3.5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="art-title">${article.title}</p>
                    <div class="flex items-center gap-1.5 mt-0.5">
                        <span class="art-badge ${badgeClass}">${badgeText}</span>
                        ${article.category_name ? `<span class="text-[10px] text-gray-400 truncate block max-w-[120px] overflow-hidden text-ellipsis whitespace-nowrap">${article.category_name}</span>` : ''}
                    </div>
                </div>
                <svg class="w-3 h-3 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                </svg>`;

            wrapper.appendChild(link);
        });

        row.appendChild(wrapper);
        messagesContainer.appendChild(row);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    /* ------------------------------------------------------------------ */
    /* TYPING INDICATOR                                                    */
    /* ------------------------------------------------------------------ */
    function showTypingIndicator() {
        const row = document.createElement('div');
        row.id = 'typing-indicator-message';
        row.className = 'flex items-end gap-1.5 mb-1 justify-start';
        row.innerHTML = `
            <div class="w-5 h-5 rounded-full bg-red-100 flex items-center justify-center flex-shrink-0 mb-3">
                <svg class="w-3 h-3 text-red-600" fill="currentColor" viewBox="0 0 20 20"><path d="M2 5a2 2 0 012-2h12a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5z"></path></svg>
            </div>
            <div class="cb-bot-bubble flex items-center gap-1 py-3 px-3.5">
                <span class="typing-dot"></span>
                <span class="typing-dot"></span>
                <span class="typing-dot"></span>
            </div>`;
        messagesContainer.appendChild(row);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function hideTypingIndicator() {
        const el = document.getElementById('typing-indicator-message');
        if (el) el.remove();
    }

    /* ------------------------------------------------------------------ */
    /* TOGGLE WIDGET                                                       */
    /* ------------------------------------------------------------------ */
    toggle.addEventListener('click', () => {
        console.log('[CHATBOT] Toggle clicked');
        widget.classList.toggle('show');
        toggle.classList.toggle('hide');
        if (widget.classList.contains('show')) {
            console.log('[CHATBOT] Widget opened');
            if (chatMode === 'chatbot') loadGreeting();
        }
    });

    closeBtn.addEventListener('click', () => {
        widget.classList.remove('show');
        toggle.classList.remove('hide');
        if (chatMode === 'chatbot') {
            syncChatUiState('chatbot');
            hideTypingIndicator();
        }
    });

    /* ------------------------------------------------------------------ */
    /* CLEAR INPUT                                                         */
    /* ------------------------------------------------------------------ */
    clearInputBtn.addEventListener('click', () => {
        input.value = '';
        clearInputBtn.classList.add('hidden');
        searchSuggestionsContainer.classList.add('hidden');
        input.focus();
    });

    input.addEventListener('input', () => {
        clearInputBtn.classList.toggle('hidden', input.value.length === 0);
        clearTimeout(searchDebounceTimer);
        if (input.value.length >= 2) {
            searchDebounceTimer = setTimeout(() => loadSearchSuggestions(input.value), 300);
        } else {
            searchSuggestionsContainer.classList.add('hidden');
        }
    });

    /* ------------------------------------------------------------------ */
    /* KEYDOWN HANDLER - Prevent auto-submit on Enter                     */
    /* ------------------------------------------------------------------ */
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault(); // Prevent default form submission
            form.dispatchEvent(new Event('submit')); // Manually trigger submit
        }
    });

    /* ------------------------------------------------------------------ */
    /* GREETING UTILS                                                      */
    /* ------------------------------------------------------------------ */
    function isGreetingQuery(message) {
        const greetings = ['halo', 'hai', 'hello', 'hi', 'pagi', 'siang', 'sore', 'malam', 'assalamualaikum', 'permisi'];
        const lower = message.toLowerCase().trim();
        return greetings.some(g => lower === g || lower.startsWith(g + ' ') || lower.endsWith(' ' + g));
    }

    function getGreetingResponse() {
        const h = new Date().getHours();
        if (h < 11) return 'Selamat pagi! Ada yang bisa saya bantu?';
        if (h < 15) return 'Selamat siang! Silakan tanyakan sesuatu.';
        if (h < 18) return 'Selamat sore! Ada yang bisa saya bantu?';
        return 'Selamat malam! Silakan tanyakan sesuatu.';
    }

    function resetToGreeting() {
        clearPreviousResults();
        hideAllContainers();
        const greeting = document.getElementById('chatbot-greeting');
        if (greeting) greeting.classList.remove('hidden');
        clarificationActive = false;
        loadGreeting();
    }

    /* ------------------------------------------------------------------ */
    /* SUBMIT HANDLER                                                      */
    /* ------------------------------------------------------------------ */
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const message = input.value.trim();
        if (!message) return;

        if (chatMode === 'staff_connected') {
            await sendStaffMessage(message);
            return;
        }

        clarificationActive = false;

        if (isGreetingQuery(message)) {
            addMessage(message, 'user');
            input.value = '';
            clearInputBtn.classList.add('hidden');
            searchSuggestionsContainer.classList.add('hidden');
            showTypingIndicator();
            setTimeout(() => {
                hideTypingIndicator();
                addMessage(getGreetingResponse(), 'bot');
            }, 500);
            return;
        }

        addMessage(message, 'user');
        input.value = '';
        clearInputBtn.classList.add('hidden');
        searchSuggestionsContainer.classList.add('hidden');
        showTypingIndicator();

        try {
            clearPreviousResults();
            hideAllContainers();

            const ambiguityResponse = await fetch('{{ route("chatbot.check-ambiguity") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ message })
            });

            const ambiguityData = await window.safeJson(ambiguityResponse) || {};

            if (ambiguityData.success && ambiguityData.is_ambiguous && ambiguityData.clarification) {
                hideTypingIndicator();
                showClarificationUI(ambiguityData.clarification, message);
                return;
            }

            const response = await fetch('{{ route("chatbot.get-response") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ message })
            });

            const data = await window.safeJson(response) || {};
            hideTypingIndicator();

            if (data.response) {
                addMessage(data.response, 'bot');
            }
            
            if (data.articles && data.articles.length > 0) {
                showArticles(data.articles);
            }
            
            if (data.show_contact_button) {
                responseContainer.classList.remove('hidden');
                contactButtonContainer.classList.remove('hidden');
                if (data.contact_button_text) {
                    contactStaffBtn.textContent = data.contact_button_text;
                }
            }
        } catch (error) {
            console.error('Error:', error);
            hideTypingIndicator();
            addMessage('Maaf, terjadi kesalahan. Silakan coba lagi.', 'bot');
        }
    });

    /* ------------------------------------------------------------------ */
    /* TICKET FORM                                                         */
    /* ------------------------------------------------------------------ */
    ticketForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(ticketForm);
        const data = Object.fromEntries(formData);

        try {
            const response = await fetch('{{ route("chatbot.create-ticket") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify(data)
            });
            const result = await window.safeJson(response) || {};
            if (result.success) {
                addMessage(result.message, 'bot');
                formContainer.classList.add('hidden');
                ticketForm.reset();
                setTimeout(() => { widget.classList.remove('show'); toggle.classList.remove('hide'); }, 2000);
            }
        } catch (error) {
            console.error('Error:', error);
            addMessage('Maaf, terjadi kesalahan saat membuat tiket.', 'bot');
        }
    });

    contactStaffBtn.addEventListener('click', () => {
        const lastUserBubble = messagesContainer.querySelector('.cb-guest-bubble:last-child');
        const message = lastUserBubble ? lastUserBubble.textContent.trim() : '';
        
        const choiceModal = document.getElementById('ticketChoiceModal');
        if (choiceModal) {
            // Isi subjek & deskripsi di modal tiket/livechat agar user tidak perlu mengetik ulang
            const livechatSubject = document.getElementById('livechat_subject');
            if (livechatSubject) livechatSubject.value = message;
            
            const livechatMessage = document.getElementById('livechat_message');
            if (livechatMessage) livechatMessage.value = message;
            
            const reportSubject = document.getElementById('report_subject');
            if (reportSubject) reportSubject.value = message;
            
            const reportMessage = document.getElementById('report_message');
            if (reportMessage) reportMessage.value = message;

            // Buka modal pilihan tiket
            choiceModal.classList.remove('hidden');
        } else {
            // Fallback ke form inline bawaan widget jika modal tidak ada di halaman saat ini
            document.getElementById('form-message').value = message;
            showTicketForm(message);
        }
        contactButtonContainer.classList.add('hidden');
    });

    cancelTicketBtn.addEventListener('click', hideTicketForm);

    /* ------------------------------------------------------------------ */
    /* GREETING / CATEGORIES                                               */
    /* ------------------------------------------------------------------ */
    async function loadGreeting() {
        console.log('[CHATBOT] loadGreeting called');
        try {
            const response = await fetch('{{ route("chatbot.greeting") }}');
            const data = await window.safeJson(response) || {};
            console.log('[CHATBOT] Greeting response:', data);
            if (data.success) {
                updateGreeting(data.greeting);
                renderCategories(data.categories);
            }
        } catch (error) {
            console.error('[CHATBOT] Error loading greeting:', error);
        }
    }

    function updateGreeting(greeting) {
        const greetingEl = document.querySelector('[data-chatbot-greeting-message]');
        console.log('[CHATBOT] updateGreeting called, element found:', !!greetingEl);
        if (greetingEl) {
            greetingEl.innerHTML = `<p class="text-sm font-semibold">${greeting.replace(/\n/g, '<br>')}</p>`;
        }
    }

    function renderCategories(categories) {
        const container = document.querySelector('[data-chatbot-categories]');
        console.log('[CHATBOT] renderCategories called, container found:', !!container, 'categories:', categories ? categories.length : 0);
        if (!container) { console.error('[CHATBOT] Category container not found'); return; }
        container.innerHTML = '';
        categories.forEach(category => {
            const chip = document.createElement('button');
            chip.className = 'category-chip';
            chip.textContent = category.label;
            chip.addEventListener('click', () => handleCategoryClick(category));
            container.appendChild(chip);
        });
    }

    async function handleCategoryClick(category) {
        addMessage(category.label, 'user');
        document.getElementById('chatbot-greeting').classList.add('hidden');
        showTypingIndicator();
        try {
            const response = await fetch('{{ route("chatbot.category-subtopics") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ category_id: category.id })
            });
            const data = await window.safeJson(response) || {};
            hideTypingIndicator();
            if (data.success) {
                addMessage(data.question, 'bot');
                showSubtopics(data.subtopics);
            }
        } catch (error) {
            console.error('Error:', error);
            hideTypingIndicator();
            addMessage('Maaf, terjadi kesalahan. Silakan coba lagi.', 'bot');
        }
    }

    function showSubtopics(subtopics) {
        subtopicsList.innerHTML = '';
        subtopicQuestion.textContent = 'Pilih yang paling sesuai:';
        subtopics.forEach(subtopic => {
            const chip = document.createElement('button');
            chip.className = 'suggestion-chip';
            chip.textContent = subtopic.label;
            chip.addEventListener('click', () => handleSubtopicClick(subtopic));
            subtopicsList.appendChild(chip);
        });
        subtopicsContainer.classList.remove('hidden');
    }

    async function handleSubtopicClick(subtopic) {
        addMessage(subtopic.label, 'user');
        subtopicsContainer.classList.add('hidden');
        clarificationActive = false;
        showTypingIndicator();
        try {
            clearPreviousResults();
            const response = await fetch('{{ route("chatbot.get-response") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ message: subtopic.full_title })
            });
            const data = await window.safeJson(response) || {};
            hideTypingIndicator();
            if (data.success) {
                addMessage(data.response, 'bot');
                if (data.articles && data.articles.length > 0) showArticles(data.articles);
            }
        } catch (error) {
            console.error('Error:', error);
            hideTypingIndicator();
            addMessage('Maaf, terjadi kesalahan. Silakan coba lagi.', 'bot');
        }
    }

    function showClarificationUI(clarification, originalMessage) {
        if (clarificationActive) {
            input.value = originalMessage;
            form.dispatchEvent(new Event('submit'));
            return;
        }
        clarificationActive = true;
        clarificationQuestion.textContent = clarification.question || 'Bisa lebih spesifik?';
        clarificationSuggestions.innerHTML = '';
        const suggestions = clarification.suggestions || [];
        suggestions.forEach(suggestion => {
            const chip = document.createElement('button');
            chip.className = 'suggestion-chip';
            chip.textContent = suggestion.label;
            chip.addEventListener('click', () => {
                addMessage(suggestion.label, 'user');
                clarificationContainer.classList.add('hidden');
                clarificationActive = false;
                clearPreviousResults();
                const specificMessage = `${suggestion.label} ${originalMessage}`;
                input.value = specificMessage;
                form.dispatchEvent(new Event('submit'));
            });
            clarificationSuggestions.appendChild(chip);
        });
        clarificationContainer.classList.remove('hidden');
    }

    function showClarification(suggestions) {
        clarificationQuestion.textContent = 'Bisa lebih spesifik?';
        clarificationSuggestions.innerHTML = '';
        suggestions.forEach(suggestion => {
            const chip = document.createElement('button');
            chip.className = 'suggestion-chip';
            chip.textContent = suggestion.label;
            chip.addEventListener('click', () => {
                addMessage(suggestion.label, 'user');
                clarificationContainer.classList.add('hidden');
                input.value = suggestion.label;
                form.dispatchEvent(new Event('submit'));
            });
            clarificationSuggestions.appendChild(chip);
        });
        clarificationContainer.classList.remove('hidden');
    }

    async function loadSearchSuggestions(query) {
        // Show loading skeleton
        searchSuggestionsList.innerHTML = `
            <div class="animate-pulse p-3 space-y-2.5">
                <div class="flex items-center gap-2">
                    <div class="w-3.5 h-3.5 bg-gray-200 rounded-full"></div>
                    <div class="h-3 bg-gray-200 rounded w-2/3"></div>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3.5 h-3.5 bg-gray-200 rounded-full"></div>
                    <div class="h-3 bg-gray-200 rounded w-1/2"></div>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-3.5 h-3.5 bg-gray-200 rounded-full"></div>
                    <div class="h-3 bg-gray-200 rounded w-3/4"></div>
                </div>
            </div>
        `;
        searchSuggestionsContainer.classList.remove('hidden');

        try {
            const response = await fetch('{{ route("chatbot.search-suggestions") }}?q=' + encodeURIComponent(query), {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' },
            });
            const data = await window.safeJson(response) || {};
            if (data.success && data.suggestions.length > 0) {
                renderSearchSuggestions(data.suggestions);
            } else {
                searchSuggestionsContainer.classList.add('hidden');
            }
        } catch (error) {
            console.error('Error loading search suggestions:', error);
        }
    }

    function renderSearchSuggestions(suggestions) {
        searchSuggestionsList.innerHTML = '';
        suggestions.forEach(suggestion => {
            const item = document.createElement('button');
            item.className = 'w-full text-left px-3 py-2 hover:bg-gray-50 transition-colors flex items-center gap-2';
            item.innerHTML = `
                <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <span class="text-xs text-gray-700">${suggestion.label}</span>`;
            item.addEventListener('click', () => {
                input.value = suggestion.label;
                searchSuggestionsContainer.classList.add('hidden');
                clearInputBtn.classList.remove('hidden');
                input.focus();
            });
            searchSuggestionsList.appendChild(item);
        });
        searchSuggestionsContainer.classList.remove('hidden');
    }

    /* ------------------------------------------------------------------ */
    /* HELPER FUNCTIONS                                                    */
    /* ------------------------------------------------------------------ */
    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function hideAllContainers() {
        clarificationContainer.classList.add('hidden');
        subtopicsContainer.classList.add('hidden');
        responseContainer.classList.add('hidden');
        formContainer.classList.add('hidden');
        contactButtonContainer.classList.add('hidden');
    }

    function showTicketForm(message) {
        hideAllContainers();
        formContainer.classList.remove('hidden');
        document.getElementById('form-message').value = message;
    }

    function hideTicketForm() {
        formContainer.classList.add('hidden');
        ticketForm.reset();
    }

    function clearPreviousResults() {
        const articlesList = document.getElementById('articles-list');
        if (articlesList) articlesList.innerHTML = '';
        const articlesContainer = document.getElementById('chatbot-articles');
        if (articlesContainer) articlesContainer.classList.add('hidden');
        const subList = document.getElementById('subtopics-list');
        if (subList) subList.innerHTML = '';
        const subCont = document.getElementById('chatbot-subtopics');
        if (subCont) subCont.classList.add('hidden');
        const clarifSugg = document.getElementById('clarification-suggestions');
        if (clarifSugg) clarifSugg.innerHTML = '';
        const clarifCont = document.getElementById('chatbot-clarification');
        if (clarifCont) clarifCont.classList.add('hidden');
        const respCont = document.getElementById('chatbot-response');
        if (respCont) respCont.classList.add('hidden');
        const contactBtnCont = document.getElementById('contact-button-container');
        if (contactBtnCont) contactBtnCont.classList.add('hidden');
    }

    document.addEventListener('click', (e) => {
        if (!input.contains(e.target) && !searchSuggestionsContainer.contains(e.target)) {
            searchSuggestionsContainer.classList.add('hidden');
        }
    });

    /* ------------------------------------------------------------------ */
    /* LIVE CHAT – INPUT DISABLED STATE (kept for API compatibility)      */
    /* ------------------------------------------------------------------ */
    function setInputDisabledState(isDisabled) {
        // Now delegates to syncChatUiState
        if (isDisabled) {
            syncChatUiState('waiting_staff');
        } else {
            if (chatMode === 'staff_connected') {
                syncChatUiState('staff_connected');
            } else {
                syncChatUiState('chatbot');
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /* SEND STAFF MESSAGE                                                  */
    /* ------------------------------------------------------------------ */
    async function sendStaffMessage(message) {
        const msgEl = addLiveChatMessage(null, message, 'guest', null);
        input.value = '';
        if (clearInputBtn) clearInputBtn.classList.add('hidden');
        if (searchSuggestionsContainer) searchSuggestionsContainer.classList.add('hidden');

        try {
            const requestData = {
                ticket_id: activeLiveChatTicketId,
                message: message,
                sender_type: 'guest'
            };
            if (activeLiveChatEmail) requestData.email = activeLiveChatEmail;

            const response = await fetch('/api/messages', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(requestData)
            });
            if (response.ok) {
                await loadStaffMessages();
            } else {
                markMessageFailed(msgEl, message);
            }
        } catch (error) {
            console.error('Error sending message:', error);
            markMessageFailed(msgEl, message);
        }
    }

    function markMessageFailed(msgEl, message) {
        if (!msgEl) return;
        const bubble = msgEl.querySelector('.cb-guest-bubble');
        if (bubble) {
            bubble.classList.add('border', 'border-red-300', 'bg-red-50', '!text-red-800');
        }
        const wrapper = msgEl.querySelector('.flex-col');
        if (wrapper && !msgEl.querySelector('.cb-retry-btn')) {
            const statusContainer = document.createElement('div');
            statusContainer.className = 'flex items-center gap-1.5 mt-1 cb-retry-container justify-end';
            statusContainer.innerHTML = `
                <span class="text-[10px] text-red-500 font-semibold flex items-center gap-1">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    Gagal
                </span>
                <button type="button" class="text-[10px] text-red-600 hover:text-red-700 underline font-semibold cb-retry-btn">
                    Coba lagi
                </button>
            `;
            statusContainer.querySelector('.cb-retry-btn').addEventListener('click', () => {
                msgEl.remove();
                sendStaffMessage(message);
            });
            const ts = wrapper.querySelector('.cb-timestamp');
            if (ts) ts.remove();
            wrapper.appendChild(statusContainer);
        }
        if (window.toast) {
            window.toast.error('Gagal mengirim pesan. Silakan coba lagi.');
        }
    }

    /* ------------------------------------------------------------------ */
    /* LOAD STAFF MESSAGES                                                 */
    /* ------------------------------------------------------------------ */
    async function loadStaffMessages() {
        if (!activeLiveChatTicketId) return;
        try {
            let url = `/api/tickets/${activeLiveChatTicketId}/messages`;
            if (activeLiveChatEmail) url += '?email=' + encodeURIComponent(activeLiveChatEmail);

            const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (response.ok) {
                const messages = await response.json();
                messagesContainer.innerHTML = '';

                const staffName = localStorage.getItem('guest_livechat_staff_name') || 'Staf';
                // System separator: staff connected
                addSystemMessage(`${staffName} telah bergabung`);

                messages.forEach(msg => {
                    addLiveChatMessage(msg.sender_name, msg.message, msg.sender_type, msg.created_at);
                });
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
            await checkStaffConnectedTicketStatus();
        } catch (error) {
            console.error('Error loading staff messages:', error);
        }
    }

    /* ------------------------------------------------------------------ */
    /* POLLING STATUS & INACTIVITY TIMEOUTS                               */
    /* ------------------------------------------------------------------ */
    function startStatusPolling() {
        if (statusPollingInterval) clearInterval(statusPollingInterval);
        
        window.guestChatWarningDisplayed = false;

        statusPollingInterval = setInterval(async () => {
            if (!activeLiveChatTicketId) {
                clearInterval(statusPollingInterval);
                return;
            }
            try {
                const response = await fetch(`/api/tickets/${activeLiveChatTicketId}/status`, {
                    headers: { 'Accept': 'application/json' }
                });
                if (response.ok) {
                    const data = await response.json();
                    
                    if (chatMode === 'waiting_staff') {
                        if (data.status === 'progress') {
                            clearInterval(statusPollingInterval);
                            connectToStaff(data.staff_name);
                        } else if (['closed', 'completed'].includes(data.status)) {
                            clearInterval(statusPollingInterval);
                            showWaitingError(data.reason || 'Sesi live chat ditutup otomatis karena tidak aktif.');
                        } else if (data.status === 'waiting' || data.status === 'assigned') {
                            updateQueuePositionUI(data.queue_position, data.estimated_waiting_minutes);
                            
                            // Check for warning at 17 mins in queue
                            if (data.warning) {
                                if (!window.guestChatWarningDisplayed) {
                                    addSystemMessage(`${data.warning_message}`);
                                    window.guestChatWarningDisplayed = true;
                                }
                            } else {
                                window.guestChatWarningDisplayed = false;
                            }
                        }
                    } else if (chatMode === 'staff_connected') {
                        // Handle warning message if guest is inactive in progress chat
                        if (data.warning) {
                            if (!window.guestChatWarningDisplayed) {
                                addSystemMessage(`${data.warning_message}`);
                                window.guestChatWarningDisplayed = true;
                            }
                        } else {
                            window.guestChatWarningDisplayed = false;
                        }

                        if (['closed', 'completed'].includes(data.status)) {
                            clearInterval(statusPollingInterval);
                            showTicketClosedOverlay();
                        } else if (data.status === 'waiting') {
                            clearInterval(statusPollingInterval);
                            cleanupLiveChatSession();
                            addSystemMessage('Sesi live chat ditangguhkan karena Anda tidak aktif.');
                        }
                    }
                }
            } catch (error) {
                console.error('Error polling status in widget:', error);
            }
        }, 5000);
    }

    async function checkStaffConnectedTicketStatus() {
        // Obsolete: logic is now integrated into startStatusPolling
    }

    /* ------------------------------------------------------------------ */
    /* CONNECT TO STAFF                                                    */
    /* ------------------------------------------------------------------ */
    function connectToStaff(staffName) {
        chatMode = 'staff_connected';
        localStorage.setItem('guest_livechat_mode', 'staff_connected');
        if (staffName) {
            localStorage.setItem('guest_livechat_staff_name', staffName);
        } else {
            staffName = localStorage.getItem('guest_livechat_staff_name') || 'Staf';
        }

        // Update header UI
        syncChatUiState('staff_connected', staffName);

        // Clear waiting message and show system separator
        removeWaitingMessage();
        messagesContainer.innerHTML = '';
        addSystemMessage(`${staffName} telah bergabung`);

        initRealtimeAndListen(activeLiveChatTicketId);
        loadStaffMessages();
        
        startStatusPolling();
    }

    function showWaitingError(message) {
        removeWaitingMessage();
        messagesContainer.innerHTML = '';
        addSystemMessage('Gagal Terhubung');
        addMessage(message, 'bot');
        cleanupLiveChatSession();
    }

    function showTicketClosedOverlay() {
        const overlay = document.getElementById('chatbot-closed-overlay');
        if (overlay) overlay.classList.remove('hidden');
        setTimeout(() => {
            cleanupLiveChatSession();
            window.location.reload();
        }, 5000);
    }

    /* ------------------------------------------------------------------ */
    /* CLEANUP                                                             */
    /* ------------------------------------------------------------------ */
    function cleanupLiveChatSession() {
        const storedTicketId = activeLiveChatTicketId;

        activeLiveChatTicketId = null;
        activeLiveChatEmail    = null;
        chatMode = 'chatbot';

        localStorage.removeItem('guest_livechat_ticket_id');
        localStorage.removeItem('guest_livechat_email');
        localStorage.removeItem('guest_livechat_mode');
        localStorage.removeItem('guest_livechat_staff_name');

        if (statusPollingInterval) clearInterval(statusPollingInterval);

        if (window.Echo && storedTicketId) window.Echo.leave(`ticket.${storedTicketId}`);

        syncChatUiState('chatbot');
    }

    /* ------------------------------------------------------------------ */
    /* WEBSOCKET SETUP  (logic fully preserved)                           */
    /* ------------------------------------------------------------------ */
    function setupWebSocketListener(ticketId) {
        if (!ticketId || typeof window.Echo === 'undefined') {
            console.log('WebSocket not available or no ticket ID');
            return;
        }
        try {
            const channelName = `ticket.${ticketId}`;
            console.log('Setting up WebSocket listener for chatbot widget:', channelName);
            window.Echo.channel(channelName)
                .listen('.MessageSent', (e) => {
                    console.log('Chatbot widget received WebSocket message:', e);
                    loadStaffMessages();
                })
                .listen('.QueuePositionUpdated', (e) => {
                    console.log('Chatbot widget received QueuePositionUpdated event:', e);
                    if (chatMode === 'waiting_staff') updateQueuePositionUI(e.position, e.estimated_waiting_minutes);
                })
                .listen('.StaffConnected', (e) => {
                    console.log('Chatbot widget received StaffConnected event:', e);
                    if (chatMode === 'waiting_staff') connectToStaff(e.staff_name);
                })
                .listen('.TicketClosed', (e) => {
                    console.log('Chatbot widget received TicketClosed event:', e);
                    if (chatMode === 'staff_connected' || chatMode === 'waiting_staff') showTicketClosedOverlay();
                });
        } catch (error) {
            console.error('Failed to setup WebSocket listener:', error);
        }
    }

    async function initRealtimeAndListen(ticketId) {
        if (typeof window.initializeRealtime === 'function') await window.initializeRealtime();
        setupWebSocketListener(ticketId);
    }

    /* ------------------------------------------------------------------ */
    /* GLOBAL: startLiveChatMode  (called from other JS contexts)         */
    /* ------------------------------------------------------------------ */
    window.startLiveChatMode = function(ticketId, ticketStatus, email, queuePosition, estimatedWaitingMinutes) {
        console.log('[CHATBOT] window.startLiveChatMode called', { ticketId, ticketStatus, email, queuePosition, estimatedWaitingMinutes });

        activeLiveChatTicketId = ticketId;
        activeLiveChatEmail    = email;

        localStorage.setItem('guest_livechat_ticket_id', ticketId);
        localStorage.setItem('guest_livechat_email', email);

        const choiceModal = document.getElementById('ticketChoiceModal');
        if (choiceModal) choiceModal.classList.add('hidden');
        const liveChatModal = document.getElementById('liveChatModal');
        if (liveChatModal) liveChatModal.classList.add('hidden');
        document.body.style.overflow = '';

        widget.classList.add('show');
        toggle.classList.add('hide');

        clearPreviousResults();
        hideAllContainers();
        const greeting = document.getElementById('chatbot-greeting');
        if (greeting) greeting.classList.add('hidden');

        messagesContainer.innerHTML = '';

        if (ticketStatus === 'progress') {
            chatMode = 'staff_connected';
            localStorage.setItem('guest_livechat_mode', 'staff_connected');
            syncChatUiState('staff_connected');
        } else {
            chatMode = 'waiting_staff';
            localStorage.setItem('guest_livechat_mode', 'waiting_staff');
            syncChatUiState('waiting_staff');
            // Show modern waiting indicator in chat flow
            addWaitingMessage();
            if (queuePosition) {
                updateQueuePositionUI(queuePosition, estimatedWaitingMinutes);
            }
        }

        initRealtimeAndListen(ticketId);
        if (ticketStatus === 'progress') {
            connectToStaff();
        } else {
            startStatusPolling();
        }
    };

    /* ------------------------------------------------------------------ */
    /* RESTORE SESSION on page load                                        */
    /* ------------------------------------------------------------------ */
    async function checkActiveLiveChat() {
        const storedTicketId = localStorage.getItem('guest_livechat_ticket_id');
        const storedEmail    = localStorage.getItem('guest_livechat_email');
        const storedMode     = localStorage.getItem('guest_livechat_mode');

        // Tidak ada sesi tersimpan - tidak perlu buka widget
        if (!storedTicketId || !storedMode) return;

        console.log('[CHATBOT] Checking active live chat session:', { storedTicketId, storedMode });

        activeLiveChatTicketId = storedTicketId;
        activeLiveChatEmail    = storedEmail;

        try {
            const response = await fetch(`/api/tickets/${storedTicketId}/status`, {
                headers: { 'Accept': 'application/json' }
            });

            // Jika ticket tidak ditemukan (404) atau error server - cleanup dan jangan buka widget
            if (!response.ok) {
                console.log('[CHATBOT] Stored ticket not found or error, cleaning up session');
                cleanupLiveChatSession();
                return;
            }

            const data = await response.json();

            // Ticket sudah closed/completed - cleanup dan jangan buka widget
            if (['closed', 'completed'].includes(data.status)) {
                console.log('[CHATBOT] Stored ticket is closed/completed, cleaning up');
                cleanupLiveChatSession();
                return;
            }

            // Ticket waiting tapi sudah ada staff - cleanup (edge case)
            if (data.status === 'waiting' && data.assigned_staff) {
                cleanupLiveChatSession();
                return;
            }

            // Status valid: baru sekarang buka widget
            widget.classList.add('show');
            toggle.classList.add('hide');

            const greeting = document.getElementById('chatbot-greeting');
            if (greeting) greeting.classList.add('hidden');

            if (data.status === 'progress' && data.assigned_staff) {
                connectToStaff(data.staff_name);
            } else {
                // Masih menunggu staff
                chatMode = 'waiting_staff';
                localStorage.setItem('guest_livechat_mode', 'waiting_staff');
                syncChatUiState('waiting_staff');
                messagesContainer.innerHTML = '';
                addWaitingMessage();
                if (data.queue_position) {
                    updateQueuePositionUI(data.queue_position, data.estimated_waiting_minutes);
                }
                initRealtimeAndListen(activeLiveChatTicketId);
                startStatusPolling();
            }
        } catch (error) {
            // Fetch gagal (network error, server down) - cleanup dan JANGAN buka widget
            console.error('[CHATBOT] Failed to restore live chat session, cleaning up:', error);
            cleanupLiveChatSession();
        }
    }

    checkActiveLiveChat();
});
</script>