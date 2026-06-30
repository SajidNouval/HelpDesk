<x-app-layout>
    <!-- Header Section -->
    <div class="bg-gray-100 py-6 border-b">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-900">
                Edit Staf
            </h1>

            <!-- Breadcrumb -->
            <div class="text-sm text-gray-500 mt-2 flex items-center">
                <a href="{{ route('admin.dashboard') }}" class="text-red-500 hover:text-red-600 font-medium">Dashboard Admin</a>
                <span class="mx-2 text-gray-400">/</span>
                <a href="{{ route('admin.users.index') }}" class="text-red-500 hover:text-red-600 font-medium">Kelola Staf</a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700">Edit Staf</span>
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

                    
                </div>
            </div>

            <!-- Main Content Right -->
            <div class="col-span-12 md:col-span-9">
                @if($errors->any())
                    <div class="rounded-md bg-red-50 p-4 border border-red-200 mb-6">
                        <div class="flex">
                            <svg class="w-5 h-5 text-red-400 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <h3 class="text-sm font-medium text-red-800">Ada kesalahan dalam input:</h3>
                                <ul class="mt-2 text-sm text-red-700 list-disc list-inside">
                                    @foreach($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Form Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm max-w-4xl">
                    <div class="p-6">
                        <!-- Header -->
                        <div class="flex items-center gap-3 mb-6 pb-6 border-b border-gray-100">
                            <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Form Edit Staf</h2>
                                <p class="text-sm text-gray-500">Perbarui informasi staf {{ $user->name }}.</p>
                            </div>
                        </div>

                            <form id="staff-form" action="{{ route('admin.users.update', $user) }}" method="POST" class="space-y-6">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="return_url" value="{{ request('return_url', old('return_url', url()->previous())) }}">

                                <div class="space-y-4">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Nama Lengkap <span class="text-red-500">*</span></label>
                                            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required
                                                   class="w-full h-11 px-4 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition"
                                                   placeholder="Masukkan nama lengkap">
                                        </div>

                                        <div>
                                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email <span class="text-red-500">*</span></label>
                                            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required
                                                   class="w-full h-11 px-4 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition"
                                                   placeholder="Masukkan alamat email">
                                        </div>
                                    </div>

                                    <input type="hidden" name="role" value="{{ old('role', $user->role) }}">

                                    @if($user->role !== 'admin' && $user->email !== 'admin@gmail.com')
                                        <div>
                                            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status Akun <span class="text-red-500">*</span></label>
                                            <select id="status" name="status" required
                                                    class="w-full h-11 px-4 border border-gray-300 rounded-xl text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition">
                                                <option value="active" {{ old('status', $user->status) === 'active' ? 'selected' : '' }}>Aktif</option>
                                                <option value="inactive" {{ old('status', $user->status) === 'inactive' ? 'selected' : '' }}>Nonaktif</option>
                                            </select>
                                        </div>
                                    @else
                                        <input type="hidden" name="status" value="active">
                                    @endif

                                    <div class="pt-4 border-t border-gray-100">
                                        <p class="text-sm font-medium text-gray-700 mb-3">Ganti Password <span class="text-xs text-gray-400 font-normal">(opsional)</span></p>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Password Baru</label>
                                                <input id="password" name="password" type="password"
                                                       class="w-full h-11 px-4 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition"
                                                       placeholder="Masukkan password baru (kosongkan jika tidak ingin mengubah)">
                                            </div>
                                            <div>
                                                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-2">Konfirmasi Password Baru</label>
                                                <input id="password_confirmation" name="password_confirmation" type="password"
                                                       class="w-full h-11 px-4 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition"
                                                       placeholder="Ulangi password baru">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Kategori Assignment (hanya untuk role staff) -->
                                <div id="category-section" class="{{ old('role', $user->role) !== 'staff' ? 'hidden' : '' }}">
                                    <div class="pt-4 border-t border-gray-100">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Kategori yang Ditangani
                                            <span class="ml-1 text-xs text-gray-400 font-normal">(opsional, bisa lebih dari satu)</span>
                                        </label>
                                        @if($categories->isEmpty())
                                            <p class="text-sm text-gray-500 italic">Belum ada kategori. <a href="{{ route('admin.categories.index') }}" class="text-red-500 hover:underline">Buat kategori terlebih dahulu.</a></p>
                                        @else
                                            <!-- Checkbox Dropdown Container using Alpine.js -->
                                            <div x-data="{
                                                open: false,
                                                selected: [],
                                                init() {
                                                    this.updateSelected();
                                                    // Close dropdown on click outside
                                                    document.addEventListener('click', (e) => {
                                                        if (!this.$el.contains(e.target)) {
                                                            this.open = false;
                                                        }
                                                    });
                                                },
                                                updateSelected() {
                                                    let checked = [];
                                                    this.$el.querySelectorAll('input[type=checkbox]:checked').forEach(el => {
                                                        checked.push(el.nextElementSibling.textContent.trim());
                                                    });
                                                    this.selected = checked;
                                                },
                                                get buttonText() {
                                                    if (this.selected.length === 0) {
                                                        return 'Pilih Kategori...';
                                                    }
                                                    if (this.selected.length <= 2) {
                                                        return this.selected.join(', ');
                                                    }
                                                    return this.selected.length + ' Kategori Terpilih';
                                                }
                                            }" class="relative w-full">
                                                <!-- Dropdown Trigger Button -->
                                                <button type="button" @click="open = !open" 
                                                        class="w-full h-11 px-4 border border-gray-300 rounded-xl text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition flex items-center justify-between cursor-pointer">
                                                    <span x-text="buttonText" class="truncate text-gray-600 block min-w-0 text-left flex-1 mr-2">Pilih Kategori...</span>
                                                    <svg class="w-4 h-4 text-gray-400 transition-transform duration-200 flex-shrink-0" :class="{'rotate-180': open}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                </button>

                                                <!-- Dropdown Menu (Scrollable + Scrollbar Custom Styling) -->
                                                <div x-show="open" x-transition 
                                                     class="absolute z-50 left-0 right-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg max-h-48 overflow-y-auto p-2 space-y-1 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-thumb]:bg-gray-300 [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-track]:bg-transparent"
                                                     style="display: none;">
                                                    @foreach($categories as $category)
                                                        <label for="category_{{ $category->id }}" 
                                                               class="flex items-center gap-3 p-2.5 rounded-lg cursor-pointer transition hover:bg-red-50 has-[:checked]:bg-red-50/50">
                                                            <input
                                                                type="checkbox"
                                                                id="category_{{ $category->id }}"
                                                                name="categories[]"
                                                                value="{{ $category->id }}"
                                                                {{ in_array($category->id, old('categories', $assignedCategoryIds)) ? 'checked' : '' }}
                                                                @change="updateSelected()"
                                                                class="h-4 w-4 text-red-600 border-gray-300 rounded focus:ring-red-500"
                                                            >
                                                            <span class="text-sm text-gray-700 font-medium select-none truncate" title="{{ $category->name }}">{{ $category->name }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <div class="pt-4 border-t border-gray-200 flex justify-end gap-3">
                                    <a href="{{ request('return_url', route('admin.users.index')) }}">
                                        <button type="button" class="h-10 px-4 rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium transition">
                                            Batal
                                        </button>
                                    </a>
                                    <button type="button" id="btn-save-staff" class="h-10 px-5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition">
                                        Simpan Perubahan
                                    </button>
                                </div>
                            </form>
                    </div>
                </div>

                <!-- Confirmation Dialog -->
                <x-confirm-dialog
                    id="confirm-edit-staff"
                    title="Simpan Perubahan"
                    message="Apakah Anda yakin ingin menyimpan perubahan staf ini?"
                    primaryText="Simpan"
                    secondaryText="Batal"
                />

                <script>
                    document.getElementById('btn-save-staff').addEventListener('click', function(e) {
                        e.preventDefault();
                        window.confirmDialog.open('confirm-edit-staff', {
                            onConfirm: function() {
                                document.getElementById('staff-form').submit();
                            }
                        });
                    });
                </script>
            </div>
        </div>
    </div>

</x-app-layout>
