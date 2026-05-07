<x-app-layout>

    <!-- Header Section -->
    <div class="bg-gray-100 py-10 border-b">
        <div class="max-w-7xl mx-auto px-4">
            <h1 class="text-3xl font-semibold text-gray-700 tracking-wide">
                Pengaturan Profil
            </h1>

            <!-- Breadcrumb -->
            <div class="text-sm text-gray-500 mt-2 flex items-center">
                <a href="{{ route('dashboard') }}" class="text-red-500 hover:text-red-600 font-medium">Dashboard</a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700">Profil Saya</span>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-10">
        <div class="space-y-6">
            @if (session('status') === 'profile-updated' || session('status') === 'password-updated' || session('status') === 'verification-link-sent')
                <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    @if (session('status') === 'profile-updated')
                        ✓ {{ __('Profil berhasil diperbarui.') }}
                    @elseif (session('status') === 'password-updated')
                        ✓ {{ __('Kata sandi berhasil diperbarui.') }}
                    @else
                        ✓ {{ __('Tautan verifikasi telah dikirim ke email Anda.') }}
                    @endif
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    ✗ {{ session('error') }}
                </div>
            @endif

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="bg-white shadow sm:rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="px-6 py-6 sm:px-8 sm:py-8">
                        @include('profile.partials.update-profile-information-form')
                    </div>
                </div>

                <div class="bg-white shadow sm:rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="px-6 py-6 sm:px-8 sm:py-8">
                        @include('profile.partials.update-password-form')
                    </div>
                </div>
            </div>
        </div>
    </div>

</x-app-layout>
