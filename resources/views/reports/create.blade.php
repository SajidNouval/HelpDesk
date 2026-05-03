<!-- Modal untuk membuat laporan dari artikel -->
<div id="reportModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 p-4 flex items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl w-full max-w-md">
        <div class="flex justify-between items-center p-6 border-b border-gray-200 dark:border-gray-700">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Buat Laporan</h2>
                <p class="text-sm text-gray-600 dark:text-gray-300">Laporan akan ditangani staf tanpa membuka sesi live chat.</p>
            </div>
            <button onclick="closeReportModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <form action="{{ route('reports.store') }}" method="POST" class="p-6 space-y-4" id="reportForm">
            @csrf

            <!-- Category -->
            <div>
                <label for="report_category_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Kategori <span class="text-red-500">*</span>
                </label>
                <select id="report_category_id" name="category_id" required class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
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
                <label for="report_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Nama <span class="text-red-500">*</span>
                </label>
                <input type="text" id="report_name" name="name" required placeholder="Nama Anda" 
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                    value="{{ old('name') }}">
                @error('name')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Email -->
            <div>
                <label for="report_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Email <span class="text-red-500">*</span>
                </label>
                <input type="email" id="report_email" name="email" required placeholder="email@example.com"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                    value="{{ old('email') }}">
                @error('email')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Subject -->
            <div>
                <label for="report_subject" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Subjek <span class="text-red-500">*</span>
                </label>
                <input type="text" id="report_subject" name="subject" required placeholder="Subjek laporan"
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                    value="{{ old('subject') }}">
                @error('subject')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Message -->
            <div>
                <label for="report_message" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    Pesan <span class="text-red-500">*</span>
                </label>
                <textarea id="report_message" name="message" required rows="4" placeholder="Jelaskan masalah Anda..."
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg dark:bg-gray-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">{{ old('message') }}</textarea>
                @error('message')
                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Buttons -->
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeReportModal()" class="flex-1 px-4 py-2 text-gray-700 dark:text-gray-300 bg-gray-200 dark:bg-gray-700 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition">
                    Batal
                </button>
                <button type="submit" class="flex-1 px-4 py-2 text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition submit-btn" id="submitReportBtn">
                    <span class="submit-text">Buat Laporan</span>
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
<div id="successToast" class="hidden fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg flex items-center gap-3 z-50 animate-fade-in">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
    </svg>
    <span id="toastMessage">Laporan berhasil dibuat!</span>
    <button onclick="closeSuccessToast()" class="ml-auto text-white hover:text-gray-200">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
        </svg>
    </button>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const reportForm = document.getElementById('reportForm');
    
    if (reportForm) {
        reportForm.addEventListener('submit', function() {
            const submitBtn = document.getElementById('submitReportBtn');
            const submitText = document.querySelector('.submit-text');
            const submitLoading = document.querySelector('.submit-loading');
            
            // Show loading state
            submitBtn.disabled = true;
            submitText.classList.add('hidden');
            submitLoading.classList.remove('hidden');
        });
    }
});
</script>

<script>
function openReportModal() {
    document.getElementById('reportModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeReportModal() {
    document.getElementById('reportModal').classList.add('hidden');
    document.body.style.overflow = '';
}

// Close modal ketika klik di luar
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('reportModal');
    if (modal) {
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeReportModal();
            }
        });

        // Show modal if form has errors
        @if ($errors->any() && old('name'))
            openReportModal();
        @endif
    }
});
</script>
