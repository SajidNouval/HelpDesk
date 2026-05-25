<!-- Modal untuk membuat laporan dari artikel -->
<div id="reportModal" data-modal class="hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm p-4 flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-xl w-full max-w-lg">
        <div class="flex justify-between items-center px-6 py-5 border-b border-gray-100">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Buat Laporan</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">Laporan akan ditangani staf tanpa membuka sesi live chat.</p>
            </div>
            <button type="button" data-close-modal class="text-gray-400 hover:text-gray-600 p-0 w-8 h-8 flex items-center justify-center rounded-xl">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form action="#" method="POST" class="p-6 space-y-4" id="reportForm" data-request-otp-url="{{ route('tickets.request-otp') }}" data-verify-otp-url="{{ route('tickets.verify-otp') }}">
            @csrf

            <input type="hidden" name="type" value="report" id="reportType">
            <input type="hidden" id="reportVerificationToken" name="verification_token">

            <div id="reportAlert" class="hidden rounded-xl border p-3 text-sm"></div>

            <!-- Category -->
            <div>
                <label for="report_category_id" class="block text-sm font-medium text-gray-700 mb-2">
                    Kategori <span class="text-red-500">*</span>
                </label>
                <select id="report_category_id" name="category_id" required class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">-- Pilih Kategori --</option>
                    @forelse($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @empty
                        <option disabled>Tidak ada kategori</option>
                    @endforelse
                </select>
                @error('category_id')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Name -->
            <div>
                <label for="report_name" class="block text-sm font-medium text-gray-700 mb-2">
                    Nama <span class="text-red-500">*</span>
                </label>
                <input type="text" id="report_name" name="name" required placeholder="Nama Anda" 
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    value="{{ old('name') }}">
                @error('name')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Email -->
            <div>
                <label for="report_email" class="block text-sm font-medium text-gray-700 mb-2">
                    Email <span class="text-red-500">*</span>
                </label>
                <input type="email" id="report_email" name="email" required placeholder="email@example.com"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    value="{{ old('email') }}">
                @error('email')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Subject -->
            <div>
                <label for="report_subject" class="block text-sm font-medium text-gray-700 mb-2">
                    Subjek <span class="text-red-500">*</span>
                </label>
                <input type="text" id="report_subject" name="subject" required placeholder="Subjek laporan"
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                    value="{{ old('subject') }}">
                @error('subject')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Message -->
            <div>
                <label for="report_message" class="block text-sm font-medium text-gray-700 mb-2">
                    Pesan <span class="text-red-500">*</span>
                </label>
                <textarea id="report_message" name="message" required rows="4" placeholder="Jelaskan masalah Anda..."
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none">{{ old('message') }}</textarea>
                @error('message')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div id="reportOtpStep" class="hidden space-y-4">
                <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-900">
                    Kode OTP telah dikirim ke email Anda. Masukkan kode 6 digit untuk menyelesaikan laporan.
                </div>
                <div>
                    <label for="report_otp_code" class="block text-sm font-medium text-gray-700 mb-2">Kode OTP</label>
                    <input id="report_otp_code" type="text" maxlength="6" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="123456">
                </div>
            </div>

            <!-- Buttons -->
            <div class="flex gap-3 pt-4">
                <button type="button" data-close-modal class="flex-1 px-4 py-2 text-sm text-gray-700 bg-gray-100 rounded-xl hover:bg-gray-200 transition font-medium">
                    Batal
                </button>
                <button type="submit" class="flex-1 px-4 py-2 text-sm text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 transition submit-btn font-medium" id="submitReportBtn">
                    <span class="submit-text">Minta OTP</span>
                    <span class="submit-loading hidden ml-2">
                        <svg class="inline w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Success Notification Toast -->
<div id="successToast" class="hidden fixed top-4 right-4 rounded-xl border shadow-lg px-4 py-3 flex items-start gap-3 bg-green-50 border-green-200 text-green-800 z-50 animate-fade-in">
    <div class="flex-shrink-0">
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
    </div>
    <div class="flex-1 min-w-0">
        <p class="text-sm text-gray-600" id="toastMessage">Laporan berhasil dibuat!</p>
    </div>
    <button type="button" data-close-toast class="flex-shrink-0 ml-auto -mx-1.5 -my-1.5 rounded-lg p-1.5 inline-flex items-center justify-center h-8 w-8 text-green-800 hover:bg-green-100 focus:outline-none">
        <span class="sr-only">Close</span>
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
    </button>
</div>


