<x-app-layout>

    <!-- Header Section -->
    <div class="bg-gray-100 py-6 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-900">
                Kelola Tiket
            </h1>

            <!-- Breadcrumb -->
            <div class="text-sm text-gray-500 mt-2 flex items-center">
                <a href="{{ route('staff.dashboard') }}" class="text-red-500 hover:text-red-600 font-medium">Dashboard</a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700">Tiket Saya</span>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="grid grid-cols-12 gap-6">

            <!-- Sidebar Left -->
            <div class="col-span-12 md:col-span-3">
                <div class="border-r border-gray-200 pr-4">
                    <h3 class="text-sm uppercase text-gray-400 mb-4 font-medium tracking-wider">
                        Menu Staf
                    </h3>

                    <ul class="space-y-1 text-gray-700">
                        <li>
                            <a href="{{ route('staff.dashboard') }}" class="block rounded-l-md px-3 py-2 transition hover:text-red-500">
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('staff.tickets.index') }}" class="block rounded-l-md px-3 py-2 transition text-red-500 font-semibold border-l-4 border-red-500 bg-red-50">
                                Kelola Tiket
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('staff.articles.index') }}" class="block rounded-l-md px-3 py-2 transition hover:text-red-500">
                                Kelola Artikel
                            </a>
                        </li>
                    </ul>

                    

                </div>
            </div>

            @php
                $allTickets = $tickets->values();
                $allPages = max(1, ceil($allTickets->count() / 10));
                $completedPages = max(1, ceil($completedTickets->count() / 10));
                $waitingPages = max(1, ceil($waitingTickets->count() / 10));
            @endphp

            <!-- Main Content Right -->
            <div class="col-span-12 md:col-span-9" data-page="staff-tickets" data-all-pages="{{ $allPages }}" data-completed-pages="{{ $completedPages }}" data-waiting-pages="{{ $waitingPages }}">

                <!-- Success/Error Alert -->
                @if (session('success'))
                    <x-alert type="success" class="mb-6">
                        {{ session('success') }}
                    </x-alert>
                @endif

                <!-- Header Section with Statistics -->
                <div class="mb-6 bg-gradient-to-r from-gray-50 to-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="p-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Daftar Tiket</h2>
                                <p class="text-sm text-gray-500">Kelola tiket bantuan pelanggan.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <!-- Status Filter Dropdown -->
                                <div class="relative">
                                    <select id="statusSelect"
                                            onchange="window.location.href=this.value"
                                            class="h-10 pl-4 pr-8 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent appearance-none cursor-pointer">

                                        <option value="{{ route('staff.tickets.index', ['status' => null, 'priority' => request('priority')]) }}"
                                            {{ !request('status') ? 'selected' : '' }}>
                                            Semua Tiket
                                        </option>

                                        <option value="{{ route('staff.tickets.index', ['status' => 'progress', 'priority' => request('priority')]) }}"
                                            {{ request('status') == 'progress' ? 'selected' : '' }}>
                                            Diproses
                                        </option>

                                        <option value="{{ route('staff.tickets.index', ['status' => 'completed', 'priority' => request('priority')]) }}"
                                            {{ request('status') == 'completed' ? 'selected' : '' }}>
                                            Selesai
                                        </option>

                                    </select>
                                    <svg class="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </div>

                                <!-- Priority Filter Dropdown -->
                                <div class="relative">
                                    <select id="prioritySelect"
                                            onchange="window.location.href=this.value"
                                            class="h-10 pl-4 pr-8 rounded-lg border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent appearance-none cursor-pointer">

                                        <option value="{{ route('staff.tickets.index', ['priority' => null, 'status' => request('status')]) }}"
                                            {{ !request('priority') ? 'selected' : '' }}>
                                            Semua Priority
                                        </option>

                                        <option value="{{ route('staff.tickets.index', ['priority' => 'high', 'status' => request('status')]) }}"
                                            {{ request('priority') == 'high' ? 'selected' : '' }}>
                                            Tinggi
                                        </option>

                                        <option value="{{ route('staff.tickets.index', ['priority' => 'medium', 'status' => request('status')]) }}"
                                            {{ request('priority') == 'medium' ? 'selected' : '' }}>
                                            Sedang
                                        </option>

                                        <option value="{{ route('staff.tickets.index', ['priority' => 'low', 'status' => request('status')]) }}"
                                            {{ request('priority') == 'low' ? 'selected' : '' }}>
                                            Rendah
                                        </option>

                                    </select>
                                    <svg class="w-4 h-4 text-gray-400 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-6 pt-4 border-t border-gray-100">
                            <div class="flex items-center gap-2">
                                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-medium">Total Tiket</p>
                                    <p class="text-xl font-bold text-gray-900">{{ number_format($tickets->count()) }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-medium">Diproses</p>
                                    <p class="text-xl font-bold text-gray-900">{{ number_format($waitingTickets->count()) }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center">
                                    <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-500 font-medium">Selesai</p>
                                    <p class="text-xl font-bold text-gray-900">{{ number_format($completedTickets->count()) }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Active Ticket Section -->
                @if ($activeTicket)
                    <div class="mb-6 bg-white border border-gray-200 rounded-lg shadow-sm">
                        <div class="p-4">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <span class="text-sm font-semibold text-gray-900">#{{ $activeTicket->id }}</span>
                                        <span class="text-sm text-gray-600">{{ $activeTicket->subject }}</span>
                                        <span class="px-2 py-0.5 text-xs font-medium rounded-full bg-red-100 text-red-800">Aktif</span>
                                    </div>
                                    <div class="flex items-center gap-4 text-xs text-gray-500">
                                        <span>{{ $activeTicket->category->name }}</span>
                                        <span>{{ $activeTicket->name }}</span>
                                        <span>{{ $activeTicket->created_at->format('d M Y, H:i') }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if ($activeTicket->status === 'assigned')
                                        <button type="button" onclick="confirmAcceptTicket('{{ route('staff.tickets.start-progress', $activeTicket) }}')" class="h-9 px-4 rounded-lg bg-red-600 hover:bg-red-700 text-white text-xs font-medium transition">
                                            Terima
                                        </button>
                                        <button type="button" onclick="confirmRejectTicket('{{ route('staff.tickets.reject', $activeTicket) }}')" class="h-9 px-4 rounded-lg border border-red-600 text-red-600 hover:bg-red-50 text-xs font-medium transition">
                                            Tolak
                                        </button>
                                    @elseif ($activeTicket->status === 'progress')
                                        <button type="button" onclick="confirmCompleteTicket('{{ route('staff.tickets.complete', $activeTicket) }}', '{{ $activeTicket->priority }}')" class="h-9 px-4 rounded-lg bg-green-600 hover:bg-green-700 text-white text-xs font-medium transition">
                                            Selesai
                                        </button>
                                        <a href="{{ route('staff.tickets.show', $activeTicket) }}" class="inline-flex items-center h-9 px-4 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-xs font-medium transition">
                                            Detail
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <!-- No Active Ticket -->
                    <div class="mb-6 bg-white border border-gray-200 rounded-lg shadow-sm">
                        <div class="p-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span class="text-sm text-gray-700">Tidak Ada Tiket Aktif</span>
                                </div>
                                <span class="text-xs text-gray-500">Semua tiket telah ditangani</span>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Tickets Table -->
                <div class="bg-white border border-gray-100 rounded-lg shadow-sm">
                    <div class="p-4">
                        @php
                            $displayTickets = $tickets;
                            if (request('status') === 'completed') {
                                $displayTickets = $completedTickets;
                            } elseif (request('status') === 'progress') {
                                $displayTickets = $waitingTickets;
                            }
                            $totalTickets = $displayTickets->count();
                            $totalPages = max(1, ceil($totalTickets / 10));
                        @endphp
                        @if($displayTickets->count())
                            <table class="divide-y divide-gray-100" style="table-layout: fixed; width: 100%;">
                                <colgroup>
                                    <col style="width: 12%;">
                                    <col style="width: 30%;">
                                    <col style="width: 20%;">
                                    <col style="width: 20%;">
                                    <col style="width: 10%;">
                                    <col style="width: 8%;">
                                </colgroup>
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Subjek</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Kategori</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tanggal</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100" id="ticketsTableBody">
                                    @foreach($displayTickets as $ticket)
                                        <tr class="hover:bg-gray-50 transition" data-index="{{ $loop->index }}">
                                            <td class="px-4 py-3 overflow-hidden">
                                                <div class="truncate text-sm font-medium text-gray-900">#{{ $ticket->id }}</div>
                                            </td>
                                            <td class="px-4 py-3 overflow-hidden">
                                                <div class="truncate text-sm text-gray-900">{{ $ticket->subject }}</div>
                                            </td>
                                            <td class="px-4 py-3 overflow-hidden">
                                                <div class="truncate text-sm text-gray-600">{{ $ticket->category->name }}</div>
                                            </td>
                                            <td class="px-4 py-3">
                                                @if($ticket->status === 'closed')
                                                    <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Selesai</span>
                                                @elseif($ticket->status === 'progress')
                                                    <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Diproses</span>
                                                @elseif($ticket->status === 'assigned')
                                                    <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">Menunggu</span>
                                                @elseif($ticket->status === 'waiting')
                                                    <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">Menunggu</span>
                                                @elseif($ticket->status === 'rejected')
                                                    <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Ditolak</span>
                                                @else
                                                    <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">{{ $ticket->status }}</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 overflow-hidden">
                                                <div class="truncate text-sm text-gray-600">{{ $ticket->created_at->format('d M Y') }}</div>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium">
                                                <a href="{{ route('staff.tickets.show', $ticket) }}" class="inline-flex items-center px-3 py-1.5 rounded-md border border-gray-200 text-gray-700 hover:border-red-500 hover:text-red-600">Lihat</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            @if ($totalPages > 1)
                                <div class="p-4 border-t border-gray-100">
                                    <div class="hidden sm:flex sm:items-center sm:justify-between gap-4">
                                        <div>
                                            <p class="text-sm text-gray-600">
                                                Menampilkan
                                                <span class="font-medium text-gray-900" id="showingFrom">1</span>
                                                sampai
                                                <span class="font-medium text-gray-900" id="showingTo">10</span>
                                                dari
                                                <span class="font-medium text-gray-900">{{ $totalTickets }}</span>
                                                hasil
                                            </p>
                                        </div>
                                        <div>
                                            <span class="relative z-0 inline-flex flex-wrap gap-1" id="paginationContainer">
                                                <button type="button" id="prevPage" class="relative inline-flex items-center px-2 py-1.5 text-sm font-medium text-gray-700 bg-white border border-transparent hover:border-gray-200 rounded-md hover:text-red-600 transition-colors disabled:text-gray-400 disabled:cursor-default disabled:hover:border-transparent disabled:hover:text-gray-400">
                                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                                                    </svg>
                                                </button>
                                                <button type="button" id="nextPage" class="relative inline-flex items-center px-2 py-1.5 text-sm font-medium text-gray-700 bg-white border border-transparent hover:border-gray-200 rounded-md hover:text-red-600 transition-colors disabled:text-gray-400 disabled:cursor-default disabled:hover:border-transparent disabled:hover:text-gray-400">
                                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                                                    </svg>
                                                </button>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @else
                            <x-empty-state
                                icon="ticket"
                                title="Tidak ada tiket"
                                subtitle="Belum ada tiket yang ditampilkan."
                                size="sm"
                            />
                        @endif
                    </div>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const tableBody = document.getElementById('ticketsTableBody');
                        const rows = tableBody.querySelectorAll('tr');
                        const prevBtn = document.getElementById('prevPage');
                        const nextBtn = document.getElementById('nextPage');
                        const paginationContainer = document.getElementById('paginationContainer');
                        const showingFromSpan = document.getElementById('showingFrom');
                        const showingToSpan = document.getElementById('showingTo');
                        
                        const totalPages = {{ $totalPages }};
                        const totalItems = {{ $totalTickets }};
                        const itemsPerPage = 10;
                        let currentPage = 1;

                        function generatePageNumbers() {
                            // Remove existing page buttons (keep prev and next buttons)
                            const existingPageBtns = paginationContainer.querySelectorAll('.page-btn');
                            existingPageBtns.forEach(btn => btn.remove());
                            
                            // Insert page buttons between prev and next buttons
                            for (let i = 1; i <= totalPages; i++) {
                                const button = document.createElement('button');
                                button.type = 'button';
                                button.textContent = i;
                                button.classList.add('page-btn');
                                
                                if (i === currentPage) {
                                    button.className = 'page-btn relative inline-flex items-center px-3 py-1.5 text-sm font-semibold text-white bg-red-600 border border-red-600 rounded-md cursor-default shadow-sm';
                                    button.disabled = true;
                                } else {
                                    button.className = 'page-btn relative inline-flex items-center px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-transparent hover:border-gray-200 rounded-md hover:text-red-600 transition-colors';
                                    button.addEventListener('click', function() {
                                        currentPage = i;
                                        showPage(currentPage);
                                    });
                                }
                                
                                paginationContainer.insertBefore(button, nextBtn);
                            }
                        }

                        function showPage(page) {
                            const start = (page - 1) * itemsPerPage;
                            const end = Math.min(start + itemsPerPage, totalItems);
                            
                            rows.forEach((row, index) => {
                                if (index >= start && index < end) {
                                    row.style.display = '';
                                } else {
                                    row.style.display = 'none';
                                }
                            });
                            
                            showingFromSpan.textContent = start + 1;
                            showingToSpan.textContent = end;
                            
                            prevBtn.disabled = page === 1;
                            nextBtn.disabled = page === totalPages;
                            
                            generatePageNumbers();
                        }

                        if (prevBtn) {
                            prevBtn.addEventListener('click', function() {
                                if (currentPage > 1) {
                                    currentPage--;
                                    showPage(currentPage);
                                }
                            });
                        }

                        if (nextBtn) {
                            nextBtn.addEventListener('click', function() {
                                if (currentPage < totalPages) {
                                    currentPage++;
                                    showPage(currentPage);
                                }
                            });
                        }

                        showPage(1);
                    });
                </script>

                <!-- Confirmation Dialogs -->
                <x-confirm-dialog
                    id="confirm-accept-ticket"
                    title="Terima Tiket"
                    message="Apakah Anda yakin ingin menerima tiket ini?"
                    primaryText="Terima"
                    secondaryText="Batal"
                />

                <x-confirm-dialog
                    id="confirm-reject-ticket"
                    title="Tolak Tiket"
                    message="Apakah Anda yakin ingin menolak tiket ini?"
                    primaryText="Tolak"
                    secondaryText="Batal"
                />

                <x-confirm-dialog
                    id="confirm-complete-ticket"
                    title="Tandai Selesai"
                    message="Apakah Anda yakin ingin menandai tiket ini sebagai selesai?"
                    primaryText="Selesai"
                    secondaryText="Batal"
                />

                <script>
                function confirmAcceptTicket(actionUrl) {
                    window.confirmDialog.open('confirm-accept-ticket', {
                        onConfirm: function() {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = actionUrl;
                            form.innerHTML = '<input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="_method" value="PATCH">';
                            document.body.appendChild(form);
                            form.submit();
                        }
                    });
                }

                function confirmRejectTicket(actionUrl) {
                    window.confirmDialog.open('confirm-reject-ticket', {
                        onConfirm: function() {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = actionUrl;
                            form.innerHTML = '<input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="_method" value="PATCH">';
                            document.body.appendChild(form);
                            form.submit();
                        }
                    });
                }

                function confirmCompleteTicket(actionUrl, priority) {
                    window.confirmDialog.open('confirm-complete-ticket', {
                        onConfirm: function() {
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = actionUrl;
                            form.innerHTML = '<input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="_method" value="PATCH"><input type="hidden" name="priority" value="' + priority + '">';
                            document.body.appendChild(form);
                            form.submit();
                        }
                    });
                }
                </script>

            </div>
        </div>
    </div>

</x-app-layout>