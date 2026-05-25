<x-app-layout>
    <div class="bg-gray-100 py-10 border-b">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-700">Buat Tiket & Verifikasi OTP</h1>
            <p class="mt-2 text-gray-500">Pilih Live Chat atau Laporan, lalu verifikasi email dengan OTP sebelum tiket diproses.</p>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-4 py-10">
        <div class="bg-white shadow rounded-3xl overflow-hidden">
            <div class="grid grid-cols-1 md:grid-cols-3 bg-gray-50 p-6 gap-4">
                <x-secondary-button type="button" id="liveChatTab" class="tab-button bg-red-500 text-white rounded-2xl px-4 py-3 font-semibold">Live Chat</x-secondary-button>
                <x-secondary-button type="button" id="reportTab" class="tab-button bg-white text-gray-700 rounded-2xl px-4 py-3 font-semibold border border-gray-200">Laporan</x-secondary-button>
                <div class="col-span-1 md:col-span-3 text-sm text-gray-600">Setelah mengirim formulir, Anda akan menerima kode OTP ke email. Masukkan kode tersebut untuk melanjutkan.</div>
            </div>
            @if (! $liveServiceEnabled)
                <div class="mb-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-900">
                    Live chat saat ini <strong>sedang offline</strong>. Anda dapat mengirimkan tiket laporan/report dan staf akan menanganinya.
                </div>
            @endif

            <div class="p-6">
                <div id="ticketPageAlert" class="hidden mb-4 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800"></div>

                <form id="guestTicketForm" class="space-y-5">
                    <input type="hidden" name="type" id="ticketType" value="livechat">

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Nama</label>
                            <input type="text" name="name" id="ticketName" required class="mt-1 w-full rounded-xl border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="Nama Anda">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" name="email" id="ticketEmail" required class="mt-1 w-full rounded-xl border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="email@example.com">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Kategori</label>
                            <select name="category_id" id="ticketCategory" required class="mt-1 w-full rounded-xl border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-red-500">
                                <option value="">Pilih Kategori</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Subjek</label>
                            <input type="text" name="subject" id="ticketSubject" required class="mt-1 w-full rounded-xl border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="Judul masalah">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Pesan</label>
                        <textarea name="message" id="ticketMessage" rows="4" required class="mt-1 w-full rounded-xl border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="Jelaskan masalah Anda..."></textarea>
                    </div>

                    <div id="otpStep" class="hidden space-y-4">
                        <div class="rounded-2xl border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-900">
                            Kode OTP sudah dikirim ke email Anda. Masukkan 6 digit kode di bawah ini.
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Kode OTP</label>
                            <input type="text" id="ticketOtp" maxlength="6" class="mt-1 w-full rounded-xl border-gray-300 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-red-500" placeholder="123456">
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row">
                        <x-primary-button id="ticketRequestOtpBtn" type="button" class="flex-1">Minta OTP</x-primary-button>
                        <x-primary-button id="ticketVerifyOtpBtn" type="button" class="hidden flex-1 bg-green-600 hover:bg-green-700">Verifikasi OTP</x-primary-button>
                    </div>
                </form>

                <div id="trackingLinkContainer" class="hidden mt-6 rounded-2xl border border-green-200 bg-green-50 p-4 text-sm text-green-900">
                    Tiket berhasil dibuat. Lihat status tiket di tautan berikut:
                    <div class="mt-2 break-all"><a id="trackingLink" class="font-semibold text-green-700 hover:underline" href="#"></a></div>
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
            const trackingLinkContainer = document.getElementById('trackingLinkContainer');
            const trackingLink = document.getElementById('trackingLink');
            let verificationToken = null;

            function showTab(selected) {
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
                trackingLinkContainer.classList.add('hidden');
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
                    ticketPageAlert.classList.add('bg-green-50', 'border', 'border-green-200', 'text-green-900');
                } else if (type === 'error') {
                    ticketPageAlert.classList.add('bg-red-50', 'border', 'border-red-200', 'text-red-900');
                } else {
                    ticketPageAlert.classList.add('bg-blue-50', 'border', 'border-blue-200', 'text-blue-900');
                }
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

                    const data = await window.safeJson(response) || {};
                    if (!response.ok) {
                        throw new Error(data.message || 'Gagal mengirim OTP.');
                    }

                    verificationToken = data.verification_token;
                    otpStep.classList.remove('hidden');
                    ticketVerifyOtpBtn.classList.remove('hidden');
                    ticketRequestOtpBtn.classList.add('hidden');
                    showAlert(data.message, 'success');
                } catch (error) {
                    showAlert(error.message, 'error');
                }
            });

            ticketVerifyOtpBtn.addEventListener('click', async function () {
                if (!verificationToken) {
                    showAlert('Sebelumnya minta kode OTP terlebih dahulu.', 'error');
                    return;
                }

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
                            otp_code: ticketOtp.value.trim(),
                        }),
                    });

                    const data = await window.safeJson(response) || {};
                    if (!response.ok) {
                        throw new Error(data.message || 'OTP salah.');
                    }

                    showAlert(data.message, 'success');
                    trackingLinkContainer.classList.remove('hidden');
                    trackingLink.href = data.tracking_url;
                    trackingLink.textContent = data.tracking_url;
                    ticketVerifyOtpBtn.classList.add('hidden');
                    otpStep.classList.add('hidden');
                } catch (error) {
                    showAlert(error.message, 'error');
                }
            });

            const liveServiceEnabled = {{ $liveServiceEnabled ? 'true' : 'false' }};

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
                trackingLinkContainer.classList.add('hidden');
                otpStep.classList.add('hidden');
                ticketVerifyOtpBtn.classList.add('hidden');
                ticketRequestOtpBtn.classList.remove('hidden');
                verificationToken = null;
            }

            liveChatTab.addEventListener('click', () => showTab('livechat'));
            reportTab.addEventListener('click', () => showTab('report'));

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
