<div id="reportModal" data-modal class="hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm p-4 flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-2xl w-full max-w-xl overflow-hidden relative">
        <!-- Subtle decorative glow -->
        <div class="absolute -top-10 -right-10 w-24 h-24 bg-red-50 rounded-full blur-2xl pointer-events-none"></div>

        <!-- Header -->
        <div class="flex justify-between items-start px-6 py-5 border-b border-gray-100 bg-white">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Buat Laporan Tiket</h2>
                <p class="text-sm text-gray-500 mt-1">Laporan Anda akan ditinjau dan ditangani secara mendalam oleh staf kami.</p>
            </div>
        </div>

        <form action="#" method="POST" class="p-6 space-y-5 max-h-[75vh] overflow-y-auto" id="reportForm" data-request-otp-url="{{ route('tickets.request-otp') }}" data-verify-otp-url="{{ route('tickets.verify-otp') }}">
            @csrf

            <input type="hidden" name="type" value="report" id="reportType">
            <input type="hidden" id="reportVerificationToken" name="verification_token">

            <div id="reportAlert" class="hidden rounded-xl border p-3.5 text-sm font-medium"></div>

            <!-- Category -->
            <div>
                <label for="report_category_id" class="block text-sm font-semibold text-gray-800 mb-1">
                    Kategori Laporan <span class="text-red-500">*</span>
                </label>
                <p class="text-xs text-gray-400 mb-2">Pilih kategori masalah agar kami dapat menugaskan staf yang tepat.</p>
                <select id="report_category_id" name="category_id" required 
                    class="w-full h-11 px-4 border border-gray-300 rounded-xl text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                    <option value="">-- Pilih Kategori --</option>
                    @forelse($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @empty
                        <option disabled>Tidak ada kategori</option>
                    @endforelse
                </select>
                @error('category_id')
                    <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
                @enderror
            </div>

            <!-- Name & Email (Side-by-side) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Name -->
                <div>
                    <label for="report_name" class="block text-sm font-semibold text-gray-800 mb-1">
                        Nama Lengkap <span class="text-red-500">*</span>
                    </label>
                    <p class="text-xs text-gray-400 mb-2">Masukkan nama lengkap Anda.</p>
                    <input type="text" id="report_name" name="name" required placeholder="Contoh: Budi Santoso" 
                        class="w-full h-11 px-4 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition"
                        value="{{ old('name') }}">
                    @error('name')
                        <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Email -->
                <div>
                    <label for="report_email" class="block text-sm font-semibold text-gray-800 mb-1">
                        Alamat Email <span class="text-red-500">*</span>
                    </label>
                    <p class="text-xs text-gray-400 mb-2">Untuk mengirimkan status pelacakan.</p>
                    <input type="email" id="report_email" name="email" required placeholder="budi@example.com"
                        class="w-full h-11 px-4 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition"
                        value="{{ old('email') }}">
                    @error('email')
                        <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Subject -->
            <div>
                <label for="report_subject" class="block text-sm font-semibold text-gray-800 mb-1">
                    Subjek Masalah <span class="text-red-500">*</span>
                </label>
                <p class="text-xs text-gray-400 mb-2">Tuliskan ringkasan singkat mengenai kendala yang dihadapi.</p>
                <input type="text" id="report_subject" name="subject" required placeholder="Contoh: Akun tidak bisa login"
                    class="w-full h-11 px-4 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition"
                    value="{{ old('subject') }}">
                @error('subject')
                    <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
                @enderror
            </div>

            <!-- Message -->
            <div>
                <label for="report_message" class="block text-sm font-semibold text-gray-800 mb-1">
                    Pesan Detail <span class="text-red-500">*</span>
                </label>
                <p class="text-xs text-gray-400 mb-2">Jelaskan secara terperinci kronologi atau pesan error yang terjadi.</p>
                <textarea id="report_message" name="message" required rows="6" placeholder="Tulis deskripsi masalah Anda secara detail di sini..."
                    class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition resize-y min-h-[140px]">{{ old('message') }}</textarea>
                @error('message')
                    <p class="text-xs text-red-600 mt-1.5 font-medium">{{ $message }}</p>
                @enderror
            </div>

            <!-- OTP Step Container -->
            <div id="reportOtpStep" class="hidden space-y-4 pt-4 border-t border-gray-100">
                <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-900 flex items-start gap-2.5">
                    <svg class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <p>Kode OTP telah dikirim ke email Anda. Masukkan kode 6 digit di bawah ini untuk memverifikasi laporan.</p>
                </div>
                <div>
                    <label for="report_otp_code" class="block text-sm font-semibold text-gray-800 mb-2">Kode Verifikasi OTP</label>
                    <input id="report_otp_code" type="text" maxlength="6" 
                        class="w-full h-11 px-4 border border-gray-300 rounded-xl text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition font-mono tracking-widest text-center text-lg" 
                        placeholder="000000">
                </div>
            </div>

            <!-- Buttons -->
            <div class="flex gap-3 pt-4 border-t border-gray-100">
                <button type="button" data-close-modal class="h-11 px-5 rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm font-semibold transition flex-1">
                    Batal
                </button>
                <button type="submit" class="h-11 px-5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-semibold transition flex-1 flex items-center justify-center gap-2 submit-btn" id="submitReportBtn">
                    <span class="submit-text">Minta OTP</span>
                    <span class="submit-loading hidden">
                        <svg class="inline w-4 h-4 animate-spin text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Sukses Buat Laporan -->
<div id="reportSuccessModal" data-modal class="hidden fixed inset-0 bg-black bg-opacity-50 backdrop-blur-sm p-4 flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-2xl w-full max-w-md p-8 text-center relative overflow-hidden">
        <!-- Decorative subtle gradient glow -->
        <div class="absolute -top-10 -right-10 w-32 h-32 bg-green-50 rounded-full blur-2xl pointer-events-none"></div>
        <div class="absolute -bottom-10 -left-10 w-32 h-32 bg-red-50 rounded-full blur-2xl pointer-events-none"></div>

        <!-- Success Icon -->
        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 text-green-600 mb-5 relative">
            <svg class="h-9 w-9" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>

        <h3 class="text-2xl font-bold text-gray-900 mb-2">✓ Tiket Berhasil Dibuat</h3>
        
        <!-- Ticket ID Container -->
        <div id="successTicketNoContainer" class="inline-block bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 mb-4 text-sm text-gray-700">
            Nomor Tiket: <span class="font-bold font-mono text-red-600 ml-1">#<span id="successTicketNo"></span></span>
        </div>

        <p class="text-sm text-gray-600 mb-8 leading-relaxed">
            Laporan Anda telah berhasil dikirim dan akan segera ditinjau oleh staf kami.
        </p>

        <!-- CTA Buttons -->
        <div class="flex flex-col gap-3 relative z-10">
            <a id="successTrackLink" href="#" class="w-full h-11 flex items-center justify-center rounded-xl bg-red-600 hover:bg-red-700 text-white font-semibold text-sm transition shadow-sm hover:shadow-md">
                Lihat Status Tiket
            </a>
            <button type="button" data-close-modal class="w-full h-11 flex items-center justify-center rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 font-semibold text-sm transition">
                Kembali ke Artikel
            </button>
        </div>
    </div>
</div>
