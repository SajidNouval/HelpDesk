<x-app-layout>
    <!-- Header Section -->
    <div class="bg-gray-100 py-6 border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-900">
                Kelola Staf
            </h1>
            <p class="mt-1 text-sm text-gray-500">
                Manajemen staf untuk sistem helpdesk.
            </p>

            <!-- Breadcrumb -->
            <div class="text-sm text-gray-500 mt-2 flex items-center">
                <a href="{{ route('admin.dashboard') }}" class="text-red-500 hover:text-red-600 font-medium">Dashboard Admin</a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700">Kelola Staf</span>
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
                        Menu Admin
                    </h3>

                    <ul class="space-y-1 text-gray-700">
                        <li>
                            <a href="{{ route('admin.dashboard') }}" class="block rounded-l-md px-3 py-2 transition hover:text-red-500">
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.users.index') }}" class="block rounded-l-md px-3 py-2 transition text-red-500 font-semibold border-l-4 border-red-500 bg-red-50">
                                Kelola Staf
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.categories.index') }}" class="block rounded-l-md px-3 py-2 transition hover:text-red-500">
                                Kelola Kategori
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('admin.articles.index') }}" class="block rounded-l-md px-3 py-2 transition hover:text-red-500">
                                Kelola Artikel
                            </a>
                        </li>
                    </ul>

                    <!-- Profile Card -->
                    <div class="mt-6 p-4 bg-gray-50 rounded">
                        <h4 class="font-semibold text-gray-700 mb-2">Profil Anda</h4>
                        <p class="text-sm text-gray-600 mb-2">{{ Auth::user()->name }}</p>
                        <p class="text-xs text-gray-500 mb-3">{{ Auth::user()->email }}</p>
                        <p class="text-xs font-semibold text-green-600">● Aktif</p>
                    </div>
                </div>
            </div>

            <!-- Main Content Right -->
            <div class="col-span-12 md:col-span-9">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-900">Daftar Staf</h2>
                        <p class="text-sm text-gray-500">Kelola akun petugas helpdesk.</p>
                    </div>

                    <div class="flex items-center">
                        <a href="{{ route('admin.users.create') }}" class="inline-flex items-center justify-center gap-2 h-10 px-5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            Tambah Staf
                        </a>
                    </div>
                </div>

                @if(session('success'))
                    <x-alert type="success" class="mb-6">
                        {{ session('success') }}
                    </x-alert>
                @endif

                @if($errors->has('delete'))
                    <x-alert type="error" class="mb-6">
                        {{ $errors->first('delete') }}
                    </x-alert>
                @endif

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-6">
                    <div class="bg-white border border-gray-100 rounded-xl p-4 shadow-sm">
                        <div class="text-sm text-gray-500">Total Staf</div>
                        <div class="text-xl font-semibold text-gray-900">{{ number_format($totalStaff) }}</div>
                    </div>
                    <div class="bg-white border border-gray-100 rounded-xl p-4 shadow-sm">
                        <div class="text-sm text-gray-500">Staf Aktif</div>
                        <div class="text-xl font-semibold text-gray-900">{{ number_format($activeStaff) }}</div>
                    </div>
                    <div class="bg-white border border-gray-100 rounded-xl p-4 shadow-sm">
                        <div class="text-sm text-gray-500">Total Admin</div>
                        <div class="text-xl font-semibold text-gray-900">{{ number_format($totalAdmin) }}</div>
                    </div>
                    <div class="bg-white border border-gray-100 rounded-xl p-4 shadow-sm">
                        <div class="text-sm text-gray-500">Staf Helpdesk</div>
                        <div class="text-xl font-semibold text-gray-900">{{ number_format($totalStaffHelpdesk) }}</div>
                    </div>
                </div>

                <div class="bg-white border border-gray-100 rounded-lg shadow-sm">
                    <div class="p-4 overflow-x-auto">
                        @if($users->count())
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nama Staff</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Artikel Dibuat</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bergabung</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-100">
                                        @foreach($users as $user)
                                            <tr class="hover:bg-gray-50 transition">
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <div class="flex items-center gap-3">
                                                        <div class="w-9 h-9 bg-red-100 rounded-full flex items-center justify-center text-red-600 font-semibold">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                                                        <div class="space-y-1">
                                                            <div class="flex items-center gap-2">
                                                                <x-truncate-text :value="$user->name" class="text-gray-900 font-medium block" />
                                                                
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <x-truncate-text :value="$user->email" class="text-gray-600 text-sm block" />
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">{{ ucfirst($user->role) }}</span>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $user->status === 'active' ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-700' }}">{{ $user->status === 'active' ? 'Aktif' : 'Nonaktif' }}</span>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <span class="text-gray-600 text-sm">{{ $user->articles_count }}</span>
                                                </td>
                                                <td class="px-4 py-3 whitespace-nowrap">
                                                    <span class="text-gray-600 text-sm">{{ $user->created_at->format('d M Y') }}</span>
                                                </td>
                                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                                    <div class="inline-flex items-center gap-2">
                                                        <a href="{{ route('admin.users.edit', $user) }}" class="inline-flex items-center px-3 py-1.5 rounded-md border border-gray-200 text-gray-700 hover:border-red-500 hover:text-red-600 mr-2" title="Edit">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                            </svg>
                                                        </a>

                                                        @if(auth()->id() !== $user->id)
                                                            <form id="toggle-form-user-{{ $user->id }}" action="{{ route('admin.users.update', $user) }}" method="POST" class="inline-block">
                                                                @csrf
                                                                @method('PATCH')
                                                                <input type="hidden" name="status" value="{{ $user->status === 'active' ? 'inactive' : 'active' }}" />
                                                                <button type="button"
                                                                        data-toggle-user="toggle-form-user-{{ $user->id }}"
                                                                        data-user-name="{{ $user->name }}"
                                                                        data-user-status="{{ $user->status }}"
                                                                        class="inline-flex items-center px-3 py-1.5 rounded-md border border-gray-200 text-gray-700 hover:bg-gray-50 transition text-sm font-medium"
                                                                        title="{{ $user->status === 'active' ? 'Nonaktifkan Staff' : 'Aktifkan Staff' }}">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3" />
                                                                    </svg>
                                                                </button>
                                                            </form>

                                                            <form id="delete-form-user-{{ $user->id }}" action="{{ route('admin.users.destroy', $user) }}" method="POST" class="inline-block">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="button"
                                                                        data-delete-user="delete-form-user-{{ $user->id }}"
                                                                        data-user-name="{{ $user->name }}"
                                                                        data-user-email="{{ $user->email }}"
                                                                        data-user-role="{{ ucfirst($user->role) }}"
                                                                        class="inline-flex items-center px-3 py-1.5 rounded-md border border-red-600 text-red-600 hover:bg-red-50 transition text-sm font-medium gap-1"
                                                                        title="Hapus">
                                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                                    </svg>
                                                                </button>
                                                            </form>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @if ($users->hasPages())
                                <div class="p-4 border-t border-gray-100">
                                    <x-pagination :paginator="$users" :elements="$users->links()->elements" />
                                </div>
                            @endif
                        @else
                            <x-empty-state 
                                icon="users"
                                title="Belum Ada Staf"
                                subtitle="Tambahkan staf pertama untuk mulai mengelola sistem helpdesk."
                                actionText="Tambah Staf"
                                actionUrl="{{ route('admin.users.create') }}"
                                actionIcon="plus"
                                size="md"
                            />
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Dialog -->
    <x-confirm-dialog 
        id="confirm-delete-staff"
        title="Hapus Staf"
        message="Akun staff yang dihapus tidak dapat dikembalikan."
        primaryText="Hapus Staff"
        secondaryText="Batal"
    >
        <div class="space-y-3 text-sm text-gray-600 mt-4">
            <div>
                <p class="font-semibold text-gray-900">Nama: <span id="confirm-delete-staff-name"></span></p>
            </div>
            <div>
                <p class="font-semibold text-gray-900">Email: <span id="confirm-delete-staff-email"></span></p>
            </div>
            <div>
                <p class="font-semibold text-gray-900">Role: <span id="confirm-delete-staff-role"></span></p>
            </div>
        </div>
    </x-confirm-dialog>

    <script>
        // Handle delete button clicks
        document.querySelectorAll('[data-delete-user]').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const formId = this.getAttribute('data-delete-user');
                const name = this.getAttribute('data-user-name');
                const email = this.getAttribute('data-user-email');
                const role = this.getAttribute('data-user-role');

                document.getElementById('confirm-delete-staff-name').textContent = name;
                document.getElementById('confirm-delete-staff-email').textContent = email;
                document.getElementById('confirm-delete-staff-role').textContent = role;

                window.confirmDialog.open('confirm-delete-staff', {
                    onConfirm: function() {
                        document.getElementById(formId).submit();
                    }
                });
            });
        });

        // Handle status toggle button clicks
        document.querySelectorAll('[data-toggle-user]').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const formId = this.getAttribute('data-toggle-user');
                const name = this.getAttribute('data-user-name');
                const currentStatus = this.getAttribute('data-user-status');
                const newStatus = currentStatus === 'active' ? 'inactive' : 'active';

                // populate dialog content
                const el = document.getElementById('confirm-toggle-staff-name');
                if(el) el.textContent = name;
                const statusEl = document.getElementById('confirm-toggle-staff-newstatus');
                if(statusEl) statusEl.textContent = newStatus === 'active' ? 'Aktif' : 'Nonaktif';

                window.confirmDialog.open('confirm-toggle-staff', {
                    onConfirm: function() {
                        // ensure hidden input value is set correctly then submit
                        const form = document.getElementById(formId);
                        if(form) {
                            const input = form.querySelector('input[name="status"]');
                            if(input) input.value = newStatus;
                            form.submit();
                        }
                    }
                });
            });
        });
    </script>

    <!-- Toggle Status Confirmation Dialog -->
    <x-confirm-dialog
        id="confirm-toggle-staff"
        title="Ubah Status Staff"
        message="Konfirmasi perubahan status staff."
        primaryText="Ubah Status"
        secondaryText="Batal"
    >
        <div class="space-y-3 text-sm text-gray-600 mt-4">
            <div>
                <p class="font-semibold text-gray-900">Nama: <span id="confirm-toggle-staff-name"></span></p>
            </div>
            <div>
                <p class="font-semibold text-gray-900">Status Baru: <span id="confirm-toggle-staff-newstatus"></span></p>
            </div>
        </div>
    </x-confirm-dialog>

</x-app-layout>
