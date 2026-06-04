<x-app-layout>
    <div class="bg-gray-100 py-10 border-b">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-700">Buat Tiket & Verifikasi OTP</h1>
            <p class="mt-2 text-gray-500">Pilih Live Chat atau Laporan, lalu verifikasi email dengan OTP sebelum tiket diproses.</p>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-4 py-10">
        <div class="bg-white shadow rounded-3xl overflow-hidden">
            <div id="ticketTabsHeader" class="grid grid-cols-1 md:grid-cols-3 bg-gray-50 p-6 gap-4">
                <x-secondary-button type="button" id="liveChatTab" class="tab-button bg-red-500 text-white rounded-2xl px-4 py-3 font-semibold">Live Chat</x-secondary-button>
                <x-secondary-button type="button" id="reportTab" class="tab-button bg-white text-gray-700 rounded-2xl px-4 py-3 font-semibold border border-gray-200">Laporan</x-secondary-button>
                <div class="col-span-1 md:col-span-3 text-sm text-gray-600">Setelah mengirim formulir, Anda akan menerima kode OTP ke email. Masukkan kode tersebut untuk melanjutkan.</div>
            </div>
            @if (! $liveServiceEnabled)
                <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-900 mx-6 mt-6">
                    Live chat saat ini <strong>sedang offline</strong>. Anda dapat mengirimkan tiket laporan/report dan staf akan menanganinya.
                </div>
            @endif

            <div class="p-6">
                <div id="ticketPageAlert" class="hidden mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"></div>

                <div id="formContainer">
                    <form id="guestTicketForm" class="space-y-5">
                        <input type="hidden" name="type" id="ticketType" value="livechat">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Nama Lengkap <span class="text-red-500">*</span></label>
                                <input type="text" name="name" id="ticketName" required class="w-full h-11 px-4 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition bg-white" placeholder="Nama Anda">
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Alamat Email <span class="text-red-500">*</span></label>
                                <input type="email" name="email" id="ticketEmail" required class="w-full h-11 px-4 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition bg-white" placeholder="email@example.com">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Kategori Masalah <span class="text-red-500">*</span></label>
                                <select name="category_id" id="ticketCategory" required class="w-full h-11 px-4 border border-gray-300 rounded-xl text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                                    <option value="">Pilih Kategori</option>
                                    @foreach($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-1.5">Subjek Laporan <span class="text-red-500">*</span></label>
                                <input type="text" name="subject" id="ticketSubject" required class="w-full h-11 px-4 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition bg-white" placeholder="Judul masalah">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Deskripsi Pesan <span class="text-red-500">*</span></label>
                            <textarea name="message" id="ticketMessage" rows="6" required class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition resize-y min-h-[140px] bg-white" placeholder="Jelaskan masalah Anda secara detail..."></textarea>
                        </div>

                        <div id="otpStep" class="hidden space-y-4 pt-4 border-t border-gray-100">
                            <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-900 flex items-start gap-2.5">
                                <svg class="w-5 h-5 text-yellow-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                <p>Kode OTP telah dikirim ke email Anda. Masukkan kode 6 digit di bawah ini untuk memverifikasi laporan.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-800 mb-2">Kode Verifikasi OTP</label>
                                <input type="text" id="ticketOtp" maxlength="6" class="w-full h-11 px-4 border border-gray-300 rounded-xl text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition font-mono tracking-widest text-center text-lg" placeholder="000000">
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row pt-4 border-t border-gray-100">
                            <x-primary-button id="ticketRequestOtpBtn" type="button" class="flex-1 h-11 !py-0 !px-4 !text-sm !font-semibold rounded-xl">Minta OTP</x-primary-button>
                            <x-primary-button id="ticketVerifyOtpBtn" type="button" class="hidden flex-1 bg-green-600 hover:bg-green-700 h-11 !py-0 !px-4 !text-sm !font-semibold rounded-xl">Verifikasi OTP</x-primary-button>
                        </div>
                    </form>
                </div>

                <!-- SUCCESS VIEW (Hidden by default) -->
                <div id="helpSuccessView" class="hidden py-8 text-center max-w-md mx-auto">
                    <!-- Report Success Section -->
                    <div id="reportSuccessSection" class="hidden">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 text-green-600 mb-5 relative">
                            <svg class="h-9 w-9" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>

                        <h3 class="text-2xl font-bold text-gray-900 mb-2">✓ Laporan Berhasil Dibuat</h3>
                        
                        <div class="inline-block bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 mb-4 text-sm text-gray-700">
                            Nomor Tiket: <span class="font-bold font-mono text-red-600 ml-1">#<span class="successTicketNo"></span></span>
                        </div>

                        <p class="text-sm text-gray-600 mb-8 leading-relaxed">
                            Laporan Anda telah berhasil dikirim dan akan segera ditinjau oleh staf kami. Tautan pelacakan telah dikirim ke email Anda.
                        </p>

                        <div class="flex flex-col gap-3">
                            <a href="#" class="successTrackLink w-full h-11 flex items-center justify-center rounded-xl bg-red-600 hover:bg-red-700 text-white font-semibold text-sm transition shadow-sm hover:shadow-md">
                                Lihat Status Tiket
                            </a>
                            <a href="{{ route('articles.index') }}" class="w-full h-11 flex items-center justify-center rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 font-semibold text-sm transition">
                                Kembali ke Artikel
                            </a>
                        </div>
                    </div>

                    <!-- Live Chat Assigned Section -->
                    <div id="livechatAssignedSection" class="hidden">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 text-green-600 mb-5 relative animate-pulse">
                            <svg class="h-9 w-9" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                        </div>

                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Staff Telah Bergabung!</h3>
                        
                        <div class="inline-block bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 mb-4 text-sm text-gray-700">
                            Nomor Tiket: <span class="font-bold font-mono text-red-600 ml-1">#<span class="successTicketNo"></span></span>
                        </div>

                        <p class="text-sm text-gray-600 mb-8 leading-relaxed">
                            Staf kami telah bergabung ke percakapan. Halaman ini akan dialihkan secara otomatis ke ruang chat dalam waktu <span id="countdownText" class="font-semibold text-red-600">3</span> detik...
                        </p>

                        <div class="flex flex-col gap-3">
                            <a href="#" class="successTrackLink w-full h-11 flex items-center justify-center rounded-xl bg-green-600 hover:bg-green-700 text-white font-semibold text-sm transition shadow-sm hover:shadow-md">
                                Mulai Chat Sekarang
                            </a>
                        </div>
                    </div>

                    <!-- Live Chat Waiting Section -->
                    <div id="livechatWaitingSection" class="hidden">
                        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 text-blue-600 mb-5 relative">
                            <svg class="animate-spin h-9 w-9 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>

                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Mencari Staf Tersedia...</h3>
                        
                        <div class="inline-block bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 mb-4 text-sm text-gray-700">
                            Nomor Tiket: <span class="font-bold font-mono text-red-600 ml-1">#<span class="successTicketNo"></span></span>
                        </div>

                        <p class="text-sm text-gray-600 mb-6 leading-relaxed">
                            Mohon tidak menutup halaman ini. Kami sedang mencari staf yang tersedia untuk melayani Anda.
                        </p>

                        <!-- An alert block for auto-close error, initially hidden -->
                        <div id="waitingErrorAlert" class="hidden mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-900 text-left">
                        </div>

                        <div class="flex flex-col gap-3">
                            <a href="#" class="successTrackLink w-full h-11 flex items-center justify-center rounded-xl bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold text-sm transition">
                                Lihat Detail & Antrean
                            </a>
                            <button type="button" id="cancelWaitingBtn" class="w-full h-11 flex items-center justify-center rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 font-semibold text-sm transition">
                                Batalkan & Kembali
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const liveChatTab = document.getElementById('liveChatTab');
            const reportTab = document.getElementById('reportTab');
            const ticketType = document.getElementById('ticketType');
            const ticketRequestOtpBtn = document.getElementById('ticketRequestOtpBtn');
            const ticketVerifyOtpBtn = document.getElementById('ticketVerifyOtpBtn');
            const otpStep = document.getElementById('otpStep');
            const ticketOtp = document.getElementById('ticketOtp');
            const ticketPageAlert = document.getElementById('ticketPageAlert');
            const liveServiceEnabled = {{ $liveServiceEnabled ? 'true' : 'false' }};
            let verificationToken = null;

            function showTab(selected) {
                if (selected === 'livechat' && !liveServiceEnabled) {
                    showAlert('Live chat sedang offline. Silakan gunakan laporan/report.', 'error');
                    return;
                }

                if (selected === 'livechat') {
                    liveChatTab.classList.add('bg-red-500', 'text-white');
                    liveChatTab.classList.remove('bg-white', 'text-gray-700', 'border');
                    reportTab.classList.add('bg-white', 'text-gray-700', 'border');
                    reportTab.classList.remove('bg-red-500', 'text-white');
                    ticketType.value = 'livechat';
                } else {
                    reportTab.classList.add('bg-red-500', 'text-white');
                    reportTab.classList.remove('bg-white', 'text-gray-700', 'border');
                    liveChatTab.classList.add('bg-white', 'text-gray-700', 'border');
                    liveChatTab.classList.remove('bg-red-500', 'text-white');
                    ticketType.value = 'report';
                }
                ticketPageAlert.classList.add('hidden');
                
                // Show form and hide success view
                document.getElementById('formContainer').classList.remove('hidden');
                document.getElementById('helpSuccessView').classList.add('hidden');
                document.getElementById('ticketTabsHeader').classList.remove('hidden');

                otpStep.classList.add('hidden');
                ticketVerifyOtpBtn.classList.add('hidden');
                ticketRequestOtpBtn.classList.remove('hidden');
                verificationToken = null;
            }

            function showAlert(message, type = 'info') {
                ticketPageAlert.textContent = message;
                ticketPageAlert.className = ticketPageAlert.className.replace(/bg-(red|green|blue)-\d{3} text-(red|green|blue)-\d{3}/g, '');
                ticketPageAlert.classList.add('block');
                if (type === 'success') {
                    ticketPageAlert.className = 'mb-4 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900';
                } else if (type === 'error') {
                    ticketPageAlert.className = 'mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900';
                } else {
                    ticketPageAlert.className = 'mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900';
                }
            }

            let pollingInterval = null;

            function showLivechatAssigned(trackingUrl) {
                document.getElementById('livechatAssignedSection').classList.remove('hidden');
                let seconds = 3;
                const countdownText = document.getElementById('countdownText');
                if (countdownText) countdownText.textContent = seconds;

                const countdownInterval = setInterval(() => {
                    seconds--;
                    if (countdownText) countdownText.textContent = seconds;
                    if (seconds <= 0) {
                        clearInterval(countdownInterval);
                        window.location.href = trackingUrl;
                    }
                }, 1000);
            }

            function showLivechatWaiting(ticketId, trackingUrl) {
                document.getElementById('livechatWaitingSection').classList.remove('hidden');
                
                const waitingErrorAlert = document.getElementById('waitingErrorAlert');
                if (waitingErrorAlert) {
                    waitingErrorAlert.classList.add('hidden');
                    waitingErrorAlert.textContent = '';
                }

                if (pollingInterval) clearInterval(pollingInterval);

                pollingInterval = setInterval(async () => {
                    try {
                        const response = await fetch(`/api/tickets/${ticketId}/status`, {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json',
                            }
                        });

                        if (!response.ok) {
                            throw new Error('Gagal memeriksa status tiket.');
                        }

                        const statusData = await response.json();

                        if (statusData.status === 'assigned') {
                            clearInterval(pollingInterval);
                            document.getElementById('livechatWaitingSection').classList.add('hidden');
                            showLivechatAssigned(trackingUrl);
                        } else if (statusData.status === 'closed' || statusData.auto_closed) {
                            clearInterval(pollingInterval);
                            showWaitingError(statusData.reason || 'Sesi live chat ditutup otomatis karena tidak aktif.');
                        }
                    } catch (error) {
                        console.error('Polling error:', error);
                    }
                }, 5000);
            }

            function showWaitingError(message) {
                const waitingErrorAlert = document.getElementById('waitingErrorAlert');
                if (waitingErrorAlert) {
                    waitingErrorAlert.textContent = message;
                    waitingErrorAlert.classList.remove('hidden');
                }
                
                const waitingTrackLink = document.querySelector('#livechatWaitingSection .successTrackLink');
                if (waitingTrackLink) waitingTrackLink.classList.add('hidden');
            }

            const cancelWaitingBtn = document.getElementById('cancelWaitingBtn');
            if (cancelWaitingBtn) {
                cancelWaitingBtn.addEventListener('click', () => {
                    if (pollingInterval) clearInterval(pollingInterval);
                    window.location.reload();
                });
            }

            liveChatTab.addEventListener('click', () => showTab('livechat'));
            reportTab.addEventListener('click', () => showTab('report'));

            ticketRequestOtpBtn.addEventListener('click', async function () {
                const payload = {
                    name: document.getElementById('ticketName').value.trim(),
                    email: document.getElementById('ticketEmail').value.trim(),
                    subject: document.getElementById('ticketSubject').value.trim(),
                    message: document.getElementById('ticketMessage').value.trim(),
                    category_id: document.getElementById('ticketCategory').value,
                    type: ticketType.value,
                };

                ticketRequestOtpBtn.disabled = true;
                ticketRequestOtpBtn.textContent = 'Mengirim...';

                try {
                    const response = await fetch('{{ route('tickets.request-otp') }}', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        },
                        body: JSON.stringify(payload),
                    });

                    let data = {};
                    try { data = await response.json(); } catch (e) {}

                    if (!response.ok) {
                        throw new Error(data.message || 'Gagal mengirim OTP.');
                    }

                    verificationToken = data.verification_token;
                    otpStep.classList.remove('hidden');
                    ticketVerifyOtpBtn.classList.remove('hidden');
                    ticketRequestOtpBtn.classList.add('hidden');
                    showAlert(data.message || 'OTP dikirim ke email Anda.', 'success');
                } catch (error) {
                    showAlert(error.message || 'Terjadi kesalahan.', 'error');
                } finally {
                    ticketRequestOtpBtn.disabled = false;
                    ticketRequestOtpBtn.textContent = 'Minta OTP';
                }
            });

            ticketVerifyOtpBtn.addEventListener('click', async function () {
                if (!verificationToken) {
                    showAlert('Sebelumnya minta kode OTP terlebih dahulu.', 'error');
                    return;
                }

                const otpValue = ticketOtp.value.trim();
                if (!otpValue) {
                    showAlert('Masukkan kode OTP terlebih dahulu.', 'error');
                    return;
                }

                // Show loading state
                ticketVerifyOtpBtn.disabled = true;
                const originalVerifyText = ticketVerifyOtpBtn.textContent;
                ticketVerifyOtpBtn.textContent = 'Memverifikasi...';

                try {
                    const response = await fetch('{{ route('tickets.verify-otp') }}', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        },
                        body: JSON.stringify({
                            verification_token: verificationToken,
                            otp_code: otpValue,
                        }),
                    });

                    let data = {};
                    try { data = await response.json(); } catch (e) {}

                    if (!response.ok) {
                        throw new Error(data.message || 'OTP tidak valid.');
                    }

                    // Hide form and tabs header
                    document.getElementById('formContainer').classList.add('hidden');
                    document.getElementById('ticketTabsHeader').classList.add('hidden');
                    ticketPageAlert.classList.add('hidden');

                    // Populate success view values
                    document.querySelectorAll('.successTicketNo').forEach(el => {
                        el.textContent = data.ticket_id;
                    });
                    document.querySelectorAll('.successTrackLink').forEach(el => {
                        el.href = data.tracking_url;
                    });

                    // Hide all success sub-sections first
                    document.getElementById('reportSuccessSection').classList.add('hidden');
                    document.getElementById('livechatAssignedSection').classList.add('hidden');
                    document.getElementById('livechatWaitingSection').classList.add('hidden');

                    // Show success state container
                    const helpSuccessView = document.getElementById('helpSuccessView');
                    if (helpSuccessView) helpSuccessView.classList.remove('hidden');

                    const type = data.ticket_type;
                    const status = data.ticket_status;

                    if (type === 'report') {
                        document.getElementById('reportSuccessSection').classList.remove('hidden');
                    } else if (type === 'livechat') {
                        if (status === 'assigned') {
                            showLivechatAssigned(data.tracking_url);
                        } else {
                            showLivechatWaiting(data.ticket_id, data.tracking_url);
                        }
                    }

                } catch (error) {
                    showAlert(error.message || 'Terjadi kesalahan saat verifikasi.', 'error');
                    ticketVerifyOtpBtn.disabled = false;
                    ticketVerifyOtpBtn.textContent = originalVerifyText;
                }
            });

            // Check URL parameter for initial tab selection
            const urlParams = new URLSearchParams(window.location.search);
            const typeParam = urlParams.get('type');

            if (!liveServiceEnabled) {
                liveChatTab.disabled = true;
                liveChatTab.classList.add('opacity-50', 'cursor-not-allowed');
                showTab('report');
            } else if (typeParam === 'report') {
                showTab('report');
            } else {
                showTab('livechat');
            }
        });
    </script>
</x-app-layout>
