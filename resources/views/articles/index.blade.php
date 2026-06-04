@extends('layouts.app')

@section('title', 'Artikel Bantuan')

@section('content')

<!-- Header Section -->
<div class="bg-gray-100 py-6 border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4">
        <h1 class="text-3xl font-semibold text-gray-900">
            Artikel Bantuan
        </h1>
        <p class="mt-1 text-sm text-gray-500">
            Temukan jawaban atas pertanyaan Anda melalui artikel panduan kami.
        </p>

        <!-- Breadcrumb -->
        <div class="text-sm text-gray-500 mt-2 flex items-center">
            <a href="{{ url('/') }}" class="text-red-500 hover:text-red-600 font-medium">Beranda</a>
            <span class="mx-2 text-gray-400">/</span>
            <span class="text-gray-700">Artikel Bantuan</span>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="grid grid-cols-12 gap-8">

        <!-- Sidebar -->
        <div class="col-span-12 md:col-span-3 min-w-0">
            <div class="border-r border-gray-200 pr-4">
                <h3 class="text-xs uppercase text-gray-400 mb-4 font-semibold tracking-wider">
                    Kategori Artikel
                </h3>

                <ul class="space-y-1 text-gray-700">
                    <li>
                        <a href="{{ route('articles.index') }}"
                           title="Semua Artikel"
                           class="flex items-center rounded-l-md px-3 py-2 transition overflow-hidden {{ empty($selectedCategoryId) ? 'text-red-500 font-semibold border-l-4 border-red-500 bg-red-50' : 'hover:text-red-500' }}">
                            <span class="truncate block whitespace-nowrap overflow-hidden text-sm">Semua Artikel</span>
                        </a>
                    </li>
                    @foreach($categories as $category)
                        <li>
                            <a href="{{ route('articles.index', ['category' => $category->id]) }}"
                               title="{{ $category->name }}"
                               class="flex items-center rounded-l-md px-3 py-2 transition overflow-hidden {{ $selectedCategoryId == $category->id ? 'text-red-500 font-semibold border-l-4 border-red-500 bg-red-50' : 'hover:text-red-500' }}">
                                <span class="truncate block whitespace-nowrap overflow-hidden text-sm">{{ $category->name }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>

                <!-- Contact Info -->
                <div class="mt-8 p-4 bg-gray-50 rounded-xl border border-gray-200">
                    <h4 class="font-semibold text-gray-700 mb-1 text-sm">Butuh Bantuan?</h4>
                    <p class="text-xs text-gray-500 leading-relaxed mb-3">
                        Tidak menemukan artikel yang dicari?
                    </p>

                    <!-- Live Service Status -->
                    <div class="mb-3 p-2.5 rounded-lg {{ $liveServiceEnabled ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' }}">
                        <div class="flex items-center gap-2">
                            <div class="w-2 h-2 rounded-full flex-shrink-0 {{ $liveServiceEnabled ? 'bg-green-500' : 'bg-red-500' }}"></div>
                            <span class="text-xs font-semibold {{ $liveServiceEnabled ? 'text-green-700' : 'text-red-700' }}">
                                Live Chat {{ $liveServiceEnabled ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>
                        <p class="text-xs {{ $liveServiceEnabled ? 'text-green-600' : 'text-red-600' }} mt-1 pl-4">
                            {{ $liveServiceEnabled ? 'Chat langsung tersedia' : 'Hanya laporan yang bisa dibuat' }}
                        </p>
                    </div>

                    <x-primary-button type="button" data-open-modal="#ticketChoiceModal" class="w-full h-10 rounded-xl text-sm font-medium flex items-center justify-center gap-2">
                        <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        Buat Tiket
                    </x-primary-button>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="col-span-12 md:col-span-9">

            @php
                $selectedCategory = $categories->firstWhere('id', $selectedCategoryId);
            @endphp

            <div class="mb-5 flex items-center justify-between">
                <p class="text-sm text-gray-500">
                    Menampilkan <span class="font-medium text-gray-700">{{ $articles->count() }}</span> dari <span class="font-medium text-gray-700">{{ $articles->total() }}</span> artikel
                    @if($selectedCategory)
                        untuk kategori <span class="font-medium text-gray-700">{{ $selectedCategory->name }}</span>
                    @endif
                </p>
            </div>

            @if($articles->count() > 0)
                <div class="space-y-4">
                    @foreach($articles as $article)
                        <x-article-card
                            :article="$article"
                            :selectedCategoryId="$selectedCategoryId"
                        />
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="mt-8">
                    <x-pagination :paginator="$articles" :elements="$articles->links()->elements" />
                </div>
            @else
                <x-empty-state
                    icon="document"
                    title="Belum ada artikel"
                    subtitle="Artikel bantuan akan segera ditambahkan"
                    size="lg"
                />
            @endif

        </div>

    </div>
</div>

@endsection

<!-- Chatbot Widget -->
<x-chatbot-widget />

<!-- Modal Pilihan Tiket -->
<div id="ticketChoiceModal" data-modal class="hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm p-4 flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-2xl w-full max-w-2xl overflow-hidden relative">
        <!-- Subtle decorative glow -->
        <div class="absolute -top-10 -right-10 w-24 h-24 bg-red-50 rounded-full blur-2xl pointer-events-none"></div>

        <!-- Header -->
        <div class="flex justify-between items-start px-6 py-5 border-b border-gray-100">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Hubungi Layanan Bantuan</h2>
                <p class="text-sm text-gray-500 mt-1">Pilih metode bantuan yang paling sesuai dengan kebutuhan Anda saat ini.</p>
            </div>
            <button type="button" data-close-modal class="text-gray-400 hover:text-gray-600 p-1.5 hover:bg-gray-100 rounded-xl transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Content Options -->
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                
                <!-- Live Chat Option -->
                @if($liveServiceEnabled)
                    <button type="button" id="liveChatOption" data-open-modal="#liveChatModal" 
                        class="group flex flex-col justify-between text-left p-6 bg-white border-2 border-gray-200 hover:border-red-500 hover:bg-red-50/20 rounded-2xl transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-red-500">
                        <div>
                            <!-- Header option: Icon + Status -->
                            <div class="flex items-center justify-between mb-4">
                                <div class="w-12 h-12 rounded-xl bg-green-50 text-green-600 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                    </svg>
                                </div>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-green-50 text-green-700 border border-green-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                                    Staf Online
                                </span>
                            </div>
                            
                            <!-- Text info -->
                            <h3 class="text-lg font-bold text-gray-900 mb-2 group-hover:text-red-600 transition-colors">Live Chat</h3>
                            <p class="text-sm text-gray-600 leading-relaxed mb-4">
                                Hubungi staf kami secara langsung untuk percakapan real-time. Pilihan terbaik untuk respon instan dan solusi cepat bagi kendala mendesak Anda.
                            </p>
                        </div>
                        
                        <div class="w-full flex items-center justify-between text-xs font-semibold text-gray-500 group-hover:text-red-600 transition-colors pt-3 border-t border-gray-100 mt-4">
                            <span>Respon Instan</span>
                            <svg class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                            </svg>
                        </div>
                    </button>
                @else
                    <div class="flex flex-col justify-between p-6 bg-gray-50/50 border-2 border-gray-200 rounded-2xl opacity-70 cursor-not-allowed">
                        <div>
                            <!-- Header option: Icon + Status -->
                            <div class="flex items-center justify-between mb-4">
                                <div class="w-12 h-12 rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                    </svg>
                                </div>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-50 text-red-700 border border-red-200">
                                    Offline
                                </span>
                            </div>
                            
                            <!-- Text info -->
                            <h3 class="text-lg font-bold text-gray-800 mb-2">Live Chat</h3>
                            <p class="text-sm text-gray-500 leading-relaxed mb-4">
                                Layanan percakapan real-time saat ini sedang offline atau di luar jam kerja staf. Silakan gunakan metode Buat Laporan di sebelah.
                            </p>
                        </div>
                        
                        <div class="w-full text-xs font-semibold text-red-600 pt-3 border-t border-gray-100 mt-4">
                            Sedang Tidak Aktif
                        </div>
                    </div>
                @endif

                <!-- Report Option -->
                <button type="button" id="reportOption" data-open-modal="#reportModal" 
                    class="group flex flex-col justify-between text-left p-6 bg-white border-2 border-gray-200 hover:border-red-500 hover:bg-red-50/20 rounded-2xl transition-all duration-300 hover:shadow-lg hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-red-500">
                    <div>
                        <!-- Header option: Icon + Status -->
                        <div class="flex items-center justify-between mb-4">
                            <div class="w-12 h-12 rounded-xl bg-red-50 text-red-600 flex items-center justify-center group-hover:scale-110 transition-transform duration-300">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-red-50 text-red-700 border border-red-200">
                                Selalu Tersedia
                            </span>
                        </div>
                        
                        <!-- Text info -->
                        <h3 class="text-lg font-bold text-gray-900 mb-2 group-hover:text-red-600 transition-colors">Buat Laporan</h3>
                        <p class="text-sm text-gray-600 leading-relaxed mb-4">
                            Kirimkan detail permasalahan Anda dalam bentuk formulir laporan. Staf kami akan menganalisis secara menyeluruh dan memberikan solusi melalui email pelacakan.
                        </p>
                    </div>
                    
                    <div class="w-full flex items-center justify-between text-xs font-semibold text-gray-500 group-hover:text-red-600 transition-colors pt-3 border-t border-gray-100 mt-4">
                        <span>Pelacakan Tiket 24/7</span>
                        <svg class="w-4 h-4 transform group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </div>
                </button>
            </div>
        </div>

        <!-- Footer Note -->
        <div class="px-6 pb-5 pt-4 border-t border-gray-50 bg-gray-50/50">
            <p class="text-xs text-gray-500 text-center">
                Demi keamanan data Anda, setiap pelaporan membutuhkan verifikasi OTP yang akan dikirimkan ke email terdaftar.
            </p>
        </div>
    </div>
</div>

<!-- Modal Live Chat -->
<div id="liveChatModal" data-modal class="hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm p-4 flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center px-6 py-5 border-b border-gray-100 sticky top-0 bg-white">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">Live Chat</h2>
                <p class="text-sm text-gray-600">Chat langsung dengan tim support kami</p>
            </div>
            <x-secondary-button type="button" data-close-modal class="text-gray-400 hover:text-gray-600 p-0 w-8 h-8 flex items-center justify-center rounded-xl">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </x-secondary-button>
        </div>

        <form action="#" method="POST" class="p-6 space-y-4" id="liveChatForm"
              data-otp-url="{{ route('tickets.request-otp') }}"
              data-verify-url="{{ route('tickets.verify-otp') }}">
            @csrf

            <input type="hidden" name="type" value="livechat" id="liveChatType">
            <input type="hidden" id="liveChatVerificationToken" name="verification_token">

            <div id="liveChatAlert" class="hidden rounded-xl border p-3 text-sm"></div>

            <!-- Category -->
            <div>
                <label for="livechat_category_id" class="block text-sm font-medium text-gray-700 mb-2">
                    Kategori <span class="text-red-500">*</span>
                </label>
                <select id="livechat_category_id" name="category_id" required class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
                    <option value="">-- Pilih Kategori --</option>
                    @forelse($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @empty
                        <option disabled>Tidak ada kategori</option>
                    @endforelse
                </select>
            </div>

            <!-- Name -->
            <div>
                <label for="livechat_name" class="block text-sm font-medium text-gray-700 mb-2">
                    Nama <span class="text-red-500">*</span>
                </label>
                <input type="text" id="livechat_name" name="name" required placeholder="Nama Anda"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
            </div>

            <!-- Email -->
            <div>
                <label for="livechat_email" class="block text-sm font-medium text-gray-700 mb-2">
                    Email <span class="text-red-500">*</span>
                </label>
                <input type="email" id="livechat_email" name="email" required placeholder="email@example.com"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
            </div>

            <!-- Subject -->
            <div>
                <label for="livechat_subject" class="block text-sm font-medium text-gray-700 mb-2">
                    Subjek <span class="text-red-500">*</span>
                </label>
                <input type="text" id="livechat_subject" name="subject" required placeholder="Topik pertanyaan"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500">
            </div>

            <!-- Message -->
            <div>
                <label for="livechat_message" class="block text-sm font-medium text-gray-700 mb-2">
                    Pesan <span class="text-red-500">*</span>
                </label>
                <textarea id="livechat_message" name="message" required rows="4" placeholder="Jelaskan pertanyaan Anda..."
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 resize-none"></textarea>
            </div>

            <div id="liveChatOtpStep" class="hidden space-y-4">
                <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-900">
                    Kode OTP telah dikirim ke email Anda. Masukkan kode 6 digit untuk melanjutkan live chat.
                </div>
                <div>
                    <label for="livechat_otp_code" class="block text-sm font-medium text-gray-700 mb-2">Kode OTP</label>
                    <input id="livechat_otp_code" type="text" maxlength="6" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500" placeholder="123456">
                </div>
            </div>

            <!-- Buttons -->
            <div class="flex gap-3 pt-4">
                <x-secondary-button type="button" data-close-modal class="flex-1 px-4 py-2 text-sm rounded-xl">Batal</x-secondary-button>
                <x-primary-button type="submit" class="flex-1 px-4 py-2 text-sm rounded-xl submit-btn" id="submitLiveChatBtn">
                    <span class="submit-text">Minta OTP</span>
                    <span class="submit-loading hidden ml-2">
                        <svg class="inline w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </span>
                </x-primary-button>
            </div>
        </form>
    </div>
</div>

<!-- Include Report Modal -->
@include('reports.create')
