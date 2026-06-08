<x-app-layout>

    <!-- Header Section -->
    <div class="bg-gray-100 py-6 border-b">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-900">
                Buat Artikel Baru
            </h1>

            <!-- Breadcrumb -->
            <div class="text-sm text-gray-500 mt-2 flex items-center">
                <a href="{{ route('staff.dashboard') }}" class="text-red-500 hover:text-red-600 font-medium">Dashboard</a>
                <span class="mx-2 text-gray-400">/</span>
                <a href="{{ route('staff.articles.index') }}" class="text-red-500 hover:text-red-600 font-medium">Artikel</a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700">Buat Baru</span>
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
                            <a href="{{ route('staff.tickets.index') }}" class="block rounded-l-md px-3 py-2 transition hover:text-red-500">
                                Kelola Tiket
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('staff.articles.index') }}" class="block rounded-l-md px-3 py-2 transition text-red-500 font-semibold border-l-4 border-red-500 bg-red-50">
                                Kelola Artikel
                            </a>
                        </li>
                    </ul>

                </div>
            </div>

            <!-- Main Content Right -->
            <div class="col-span-12 md:col-span-9">
                @if($errors->any())
                    <div class="rounded-2xl bg-red-50 p-4 border border-red-200 mb-6">
                        <div class="flex gap-3">
                            <svg class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <h3 class="text-sm font-semibold text-red-800">Ada kesalahan dalam input:</h3>
                                <ul class="mt-2 text-sm text-red-700 list-disc list-inside space-y-1">
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
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Form Buat Artikel</h2>
                                <p class="text-sm text-gray-500">Tambah artikel baru.</p>
                            </div>
                        </div>

                        <form id="article-form" method="POST" action="{{ route('staff.articles.store') }}" class="space-y-5">
                            @csrf

                            <div class="space-y-4">
                                <div>
                                    <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">Kategori <span class="text-red-500">*</span></label>
                                    <select name="category_id" id="category_id" class="w-full h-11 px-4 border border-gray-300 rounded-xl text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition" required>
                                        <option value="">Pilih Kategori</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                                        @endforeach
                                    </select>
                                    @error('category_id')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="title" class="block text-sm font-medium text-gray-700 mb-2">Judul Artikel <span class="text-red-500">*</span></label>
                                    <input type="text" name="title" id="title" value="{{ old('title') }}" class="w-full h-11 px-4 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition" placeholder="Masukkan judul artikel" required>
                                    @error('title')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="excerpt" class="block text-sm font-medium text-gray-700 mb-2">Ringkasan</label>
                                    <textarea name="excerpt" id="excerpt" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition resize-none" placeholder="Ringkasan singkat artikel (opsional)">{{ old('excerpt') }}</textarea>
                                    @error('excerpt')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="content" class="block text-sm font-medium text-gray-700 mb-2">Konten Artikel <span class="text-red-500">*</span></label>
                                    <textarea name="content" id="content" rows="12" class="w-full px-4 py-3 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition" placeholder="Tulis konten artikel di sini...">{{ old('content') }}</textarea>
                                    @error('content')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="keywords" class="block text-sm font-medium text-gray-700 mb-2">Kata Kunci</label>
                                    <input type="text" name="keywords" id="keywords" value="{{ old('keywords') }}" class="w-full h-11 px-4 border border-gray-300 rounded-xl text-sm text-gray-700 placeholder:text-gray-400 bg-white focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-red-500 transition" placeholder="Contoh: reset password, lupa password, login">
                                    @error('keywords')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="pt-4 border-t border-gray-200 flex justify-end gap-3">
                                <a href="{{ route('staff.articles.index') }}">
                                    <button type="button" class="h-10 px-4 rounded-xl border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium transition">
                                        Batal
                                    </button>
                                </a>
                                <button type="button" id="btn-save-article" class="h-10 px-5 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-medium transition">
                                    Simpan Artikel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Confirmation Dialog -->
                <x-confirm-dialog
                    id="confirm-add-article"
                    title="Tambah Artikel"
                    message="Apakah Anda yakin ingin menambahkan artikel ini?"
                    primaryText="Tambah"
                    secondaryText="Batal"
                />

                <script>
                    document.getElementById('btn-save-article').addEventListener('click', function(e) {
                        e.preventDefault();
                        window.confirmDialog.open('confirm-add-article', {
                            onConfirm: function() {
                                document.getElementById('article-form').submit();
                            }
                        });
                    });
                </script>

            </div>
        </div>
    </div>

    <!-- CKEditor 5 Script -->
    <script src="https://cdn.ckeditor.com/ckeditor5/40.0.0/classic/ckeditor.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            ClassicEditor
                .create(document.querySelector('#content'), {
                    toolbar: [
                        'heading', '|',
                        'bold', 'italic', '|',
                        'bulletedList', 'numberedList', '|',
                        'blockQuote', 'link', '|',
                        'undo', 'redo'
                    ],
                    heading: {
                        options: [
                            { model: 'heading1', view: 'h1', title: 'Heading 1', class: 'ck-heading_heading1' },
                            { model: 'heading2', view: 'h2', title: 'Heading 2', class: 'ck-heading_heading2' },
                            { model: 'heading3', view: 'h3', title: 'Heading 3', class: 'ck-heading_heading3' }
                        ]
                    },
                    contentStyles: `
                        .ck-editor__editable h1 {
                            font-size: 2.25rem;
                            font-weight: 700;
                            line-height: 2.5rem;
                            margin: 1.5rem 0 1rem 0;
                            color: #111827;
                        }
                        .ck-editor__editable h2 {
                            font-size: 1.875rem;
                            font-weight: 600;
                            line-height: 2.25rem;
                            margin: 1.25rem 0 0.75rem 0;
                            color: #111827;
                        }
                        .ck-editor__editable h3 {
                            font-size: 1.5rem;
                            font-weight: 600;
                            line-height: 2rem;
                            margin: 1rem 0 0.5rem 0;
                            color: #111827;
                        }
                        .ck-editor__editable ul {
                            list-style-type: disc;
                            padding-left: 2.5rem;
                            margin: 1rem 0;
                        }
                        .ck-editor__editable ol {
                            list-style-type: decimal;
                            padding-left: 2.5rem;
                            margin: 1rem 0;
                        }
                        .ck-editor__editable li {
                            display: list-item;
                            margin: 0.5rem 0;
                        }
                        .ck-editor__editable ul ul {
                            list-style-type: circle;
                            padding-left: 2rem;
                        }
                        .ck-editor__editable ul ul ul {
                            list-style-type: square;
                            padding-left: 2rem;
                        }
                        .ck-editor__editable ol ol {
                            padding-left: 2rem;
                        }
                    `
                })
                .then(editor => {
                    // Add validation on form submit
                    const form = document.getElementById('article-form');
                    form.addEventListener('submit', function(e) {
                        const content = editor.getData();
                        if (!content || content.trim() === '') {
                            e.preventDefault();
                            alert('Konten Artikel wajib diisi');
                        }
                    });
                })
                .catch(error => {
                    console.error(error);
                });
        });
    </script>

</x-app-layout>