@props(['show' => true])

<div id="chatbot-widget" class="fixed bottom-4 right-4 w-96 h-[600px] bg-white rounded-lg shadow-2xl flex flex-col z-50 hidden">
    <!-- Header -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-4 rounded-t-lg flex justify-between items-center">
        <div>
            <h3 class="font-bold text-lg">Bantuan Otomatis</h3>
            
        </div>
        <button id="chatbot-close" class="text-white hover:bg-blue-800 p-2 rounded transition">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>

    <!-- Chat Container -->
    <div id="chatbot-messages" class="flex-1 overflow-y-auto p-4 bg-gray-50 space-y-4">
        <!-- Initial Message -->
        <div class="flex items-start">
            <div class="bg-blue-100 text-blue-900 rounded-lg p-3 max-w-xs">
                <p class="text-sm font-semibold">Halo! 👋</p>
                <p class="text-sm mt-2">Kami siap membantu Anda. Silakan jelaskan masalah atau pertanyaan Anda, dan kami akan mencari artikel yang relevan atau membantu Anda membuat tiket.</p>
            </div>
        </div>
    </div>

    <!-- Response Area -->
    <div id="chatbot-response" class="hidden px-4 py-3 bg-white border-t border-gray-200 max-h-40 overflow-y-auto">
        <!-- Bot response akan ditampilkan di sini -->
        <div id="contact-button-container" class="hidden mt-3">
            <button id="contact-staff-btn" class="bg-green-600 text-white text-xs font-semibold py-2 px-4 rounded hover:bg-green-700 transition">
                Buat Tiket untuk Bantuan Lebih Lanjut
            </button>
        </div>
    </div>

    <!-- Articles Section -->
    <div id="chatbot-articles" class="hidden px-4 py-3 bg-white border-t border-gray-200 max-h-40 overflow-y-auto">
        <p class="text-xs text-gray-600 font-semibold mb-2">📚 Artikel Terkait:</p>
        <div id="articles-list" class="space-y-2">
            <!-- Articles akan ditampilkan di sini -->
        </div>
    </div>

    <!-- Ticket Creation Form -->
    <div id="chatbot-form" class="hidden px-4 py-4 bg-white border-t border-gray-200 max-h-48 overflow-y-auto">
        <p class="text-xs text-gray-600 font-semibold mb-3">Buat Tiket untuk Bantuan Lebih Lanjut</p>
        <form id="ticket-form" class="space-y-3">
            <input type="hidden" name="message" id="form-message">
            
            <input type="text" name="title" placeholder="Judul masalah" class="w-full text-xs px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" required>
            
            <select name="category_id" class="w-full text-xs px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" required>
                <option value="">Pilih Kategori</option>
                @foreach($categories ?? [] as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
            
            <input type="email" name="email" placeholder="Email Anda (opsional)" class="w-full text-xs px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
            
            <textarea name="message" placeholder="Jelaskan masalah Anda secara detail" rows="3" class="w-full text-xs px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500 resize-none" required></textarea>
            
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-blue-600 text-white text-xs font-semibold py-2 rounded hover:bg-blue-700 transition">
                    Buat Tiket
                </button>
                <button type="button" id="chatbot-cancel-btn" class="flex-1 bg-gray-200 text-gray-700 text-xs font-semibold py-2 rounded hover:bg-gray-300 transition">
                    Batal
                </button>
            </div>
        </form>
    </div>

    <!-- Input Area -->
    <div class="border-t border-gray-200 p-4 bg-white rounded-b-lg">
        <form id="chatbot-form-input" class="flex gap-2">
            <input 
                type="text" 
                id="chatbot-input" 
                placeholder="Ketik pertanyaan Anda..." 
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

<!-- Floating Button -->
<button id="chatbot-toggle" class="fixed bottom-4 right-4 bg-blue-600 hover:bg-blue-700 text-white rounded-full p-4 shadow-lg transition transform hover:scale-110 z-50">
    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
        <path d="M2 5a2 2 0 012-2h12a2 2 0 012 2v10a2 2 0 01-2 2H4a2 2 0 01-2-2V5z"></path>
        <path d="M15 7H5"></path>
    </svg>
</button>

<style>
    #chatbot-widget.show {
        display: flex;
    }
    
    #chatbot-toggle.hide {
        display: none;
    }

    .bot-message {
        @apply text-left;
    }

    .user-message {
        @apply text-right;
    }

    .message-bubble {
        @apply rounded-lg p-3 max-w-xs text-sm break-words;
    }

    .bot-bubble {
        @apply bg-blue-100 text-blue-900;
    }

    .user-bubble {
        @apply bg-blue-600 text-white;
    }

    .article-link {
        @apply text-blue-600 hover:text-blue-800 underline text-xs;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const widget = document.getElementById('chatbot-widget');
    const toggle = document.getElementById('chatbot-toggle');
    const closeBtn = document.getElementById('chatbot-close');
    const input = document.getElementById('chatbot-input');
    const form = document.getElementById('chatbot-form-input');
    const messagesContainer = document.getElementById('chatbot-messages');
    const responseContainer = document.getElementById('chatbot-response');
    const articlesContainer = document.getElementById('chatbot-articles');
    const formContainer = document.getElementById('chatbot-form');
    const ticketForm = document.getElementById('ticket-form');
    const cancelTicketBtn = document.getElementById('chatbot-cancel-btn');
    const contactButtonContainer = document.getElementById('contact-button-container');
    const contactStaffBtn = document.getElementById('contact-staff-btn');

    // Toggle widget
    toggle.addEventListener('click', () => {
        widget.classList.toggle('show');
        toggle.classList.toggle('hide');
    });

    closeBtn.addEventListener('click', () => {
        widget.classList.remove('show');
        toggle.classList.remove('hide');
    });

    // Send message
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const message = input.value.trim();
        
        if (!message) return;

        // Add user message
        addMessage(message, 'user');
        input.value = '';

        try {
            // Hide any stale ticket offer while processing a new question
            contactButtonContainer.classList.add('hidden');
            responseContainer.classList.add('hidden');
            formContainer.classList.add('hidden');

            // Get chatbot response
            const response = await fetch('{{ route("chatbot.get-response") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ message })
            });

            const data = await response.json();

            if (data.success) {
                // Add bot response
                addMessage(data.response, 'bot');

                // Show articles if available
                if (data.articles && data.articles.length > 0) {
                    showArticles(data.articles);
                }
            } else {
                // Add bot response
                addMessage(data.response, 'bot');

                // Show contact button if suggested
                if (data.show_contact_button) {
                    responseContainer.classList.remove('hidden');
                    contactButtonContainer.classList.remove('hidden');
                    if (data.contact_button_text) {
                        contactStaffBtn.textContent = data.contact_button_text;
                    }
                }
            }
        } catch (error) {
            console.error('Error:', error);
            addMessage('Maaf, terjadi kesalahan. Silakan coba lagi.', 'bot');
        }
    });

    // Handle ticket form submission
    ticketForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(ticketForm);
        const data = Object.fromEntries(formData);

        try {
            const response = await fetch('{{ route("chatbot.create-ticket") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                addMessage('✅ ' + result.message, 'bot');
                formContainer.classList.add('hidden');
                ticketForm.reset();
                
                setTimeout(() => {
                    widget.classList.remove('show');
                    toggle.classList.remove('hide');
                }, 2000);
            }
        } catch (error) {
            console.error('Error:', error);
            addMessage('Maaf, terjadi kesalahan saat membuat tiket.', 'bot');
        }
    });

    // Handle contact staff button
    contactStaffBtn.addEventListener('click', () => {
        const lastUserMessage = messagesContainer.querySelector('.user-message:last-child .message-bubble');
        const message = lastUserMessage ? lastUserMessage.textContent : '';
        document.getElementById('form-message').value = message;
        showTicketForm(message);
        contactButtonContainer.classList.add('hidden');
    });

    cancelTicketBtn.addEventListener('click', hideTicketForm);

    function addMessage(text, sender) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `flex ${sender === 'user' ? 'justify-end' : 'justify-start'} mb-3`;
        
        const bubble = document.createElement('div');
        bubble.className = `message-bubble ${sender === 'bot' ? 'bot-bubble' : 'user-bubble'}`;
        bubble.textContent = text;
        
        messageDiv.appendChild(bubble);
        messagesContainer.appendChild(messageDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function showArticles(articles) {
        articlesContainer.classList.remove('hidden');
        const articlesList = document.getElementById('articles-list');
        articlesList.innerHTML = '';

        articles.forEach(article => {
            const link = document.createElement('a');
            link.href = `{{ url('/articles') }}/${article.slug}`;
            link.target = '_blank';
            link.className = 'article-link block hover:bg-blue-50 p-2 rounded';
            link.textContent = `📄 ${article.title}`;
            link.addEventListener('click', (e) => {
                // Optionally track article view
                window.open(link.href, '_blank');
            });
            articlesList.appendChild(link);
        });
    }

    function showTicketForm(message) {
        responseContainer.classList.add('hidden');
        formContainer.classList.remove('hidden');
        document.getElementById('form-message').value = message;
        contactButtonContainer.classList.add('hidden');
    }

    function hideTicketForm() {
        formContainer.classList.add('hidden');
        ticketForm.reset();
    }
});
</script>
