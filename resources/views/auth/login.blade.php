@extends('layouts.app')

@section('title', 'Masuk ke Helpdesk TA')

@section('content')
<div class="bg-gradient-to-b from-gray-50 to-white py-12 sm:py-16 lg:py-20 min-h-[calc(100vh-64px)]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Page Header -->
        <div class="mb-12">
            <h1 class="text-4xl font-bold text-gray-900 tracking-tight">HelpMinfo</h1>
            <p class="mt-2 text-base text-gray-600">Kemudahan Layanan dalam Satu Sentuhan</p>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-10">
            
            <!-- Login Card -->
            <div class="flex flex-col">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 sm:p-10 h-full">
                    <!-- Card Header -->
                    <div class="mb-8">
                        <h2 class="text-2xl font-bold text-gray-900">Silahkan Masuk</h2>
                    </div>

                    <x-auth-session-status class="mb-6 rounded-lg bg-green-50 border border-green-200 p-4 text-sm text-green-800" :status="session('status')" />

                    <!-- Login Form -->
                    <form method="POST" action="{{ route('login') }}" class="space-y-5">
                        @csrf

                        <!-- Email Input -->
                        <div>
                            <x-input-label for="email" value="Alamat Email" class="text-gray-700 font-semibold text-sm mb-2" />
                            <x-text-input 
                                id="email" 
                                class="block w-full px-4 py-3 border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-colors duration-200" 
                                type="email" 
                                name="email" 
                                :value="old('email')" 
                                placeholder="nama@email.com"
                                required 
                                autofocus 
                                autocomplete="username" 
                            />
                            <x-input-error :messages="$errors->get('email')" class="mt-2 text-sm text-red-600" />
                        </div>

                        <!-- Password Input -->
                        <div>
                            <x-input-label for="password" value="Kata Sandi" class="text-gray-700 font-semibold text-sm mb-2" />
                            <x-text-input 
                                id="password" 
                                class="block w-full px-4 py-3 border border-gray-300 rounded-lg text-gray-900 placeholder-gray-400 focus:border-red-500 focus:ring-1 focus:ring-red-500 transition-colors duration-200" 
                                type="password" 
                                name="password" 
                                placeholder="••••••••"
                                required 
                                autocomplete="current-password" 
                            />
                            <x-input-error :messages="$errors->get('password')" class="mt-2 text-sm text-red-600" />
                        </div>

                        <!-- Remember Me & Forgot Password -->
                        <div class="flex items-center justify-between pt-2">
                            <label for="remember_me" class="flex items-center gap-2 cursor-pointer">
                                    <input 
                                    id="remember_me" 
                                    type="checkbox" 
                                    class="w-4 h-4 border-gray-300 rounded text-red-600 focus:ring-red-500 cursor-pointer" 
                                    name="remember"
                                >
                                <span class="text-sm text-gray-600 font-medium">Ingat saya</span>
                            </label>
                            @if (Route::has('password.request'))
                                <a class="text-sm text-red-600 hover:text-red-700 font-medium transition-colors duration-200" href="{{ route('password.request') }}">
                                    Lupa kata sandi?
                                </a>
                            @endif
                        </div>

                        <!-- Login Button -->
                        <div class="pt-4">
                            <x-primary-button class="w-full py-3 text-base font-semibold rounded-lg">
                                Masuk ke Sistem
                            </x-primary-button>
                        </div>
                    </form>

                    <!-- Footer Note -->
                    <div class="mt-8 pt-6 border-t border-gray-200">
                        <p class="text-xs text-gray-500 text-center">
                            
                        </p>
                    </div>
                </div>
            </div>

            <!-- Guide Card -->
            <div class="flex flex-col">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-8 sm:p-10 h-full flex flex-col">
                    <!-- Card Header -->
                    <div class="mb-8">
                        <h2 class="text-2xl font-bold text-gray-900">Panduan Singkat</h2>
                        <p class="mt-2 text-sm text-gray-600">Langkah-langkah untuk memulai</p>
                    </div>

                    <!-- Guide Steps -->
                    <div class="flex-1 space-y-4">
                        <!-- Step 1 -->
                        <div class="flex gap-4">
                            <div class="flex-shrink-0">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-100 text-red-700 font-bold text-sm">
                                    1
                                </div>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold text-gray-900 text-sm">Login dengan Akun Anda</h3>
                                <p class="mt-1 text-sm text-gray-600">Gunakan email dan password yang telah diberikan</p>
                            </div>
                        </div>

                        <!-- Step 2 -->
                        <div class="flex gap-4">
                            <div class="flex-shrink-0">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-100 text-red-700 font-bold text-sm">
                                    2
                                </div>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold text-gray-900 text-sm">Akses Dashboard</h3>
                                <p class="mt-1 text-sm text-gray-600">Kelola tiket dan artikel dari dashboard utama</p>
                            </div>
                        </div>

                        <!-- Step 3 -->
                        <div class="flex gap-4">
                            <div class="flex-shrink-0">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-100 text-red-700 font-bold text-sm">
                                    3
                                </div>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold text-gray-900 text-sm">Kelola Konten</h3>
                                <p class="mt-1 text-sm text-gray-600">Buat, edit, atau publikasikan artikel bantuan</p>
                            </div>
                        </div>

                        <!-- Step 4 -->
                        <div class="flex gap-4">
                            <div class="flex-shrink-0">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-100 text-red-700 font-bold text-sm">
                                    4
                                </div>
                            </div>
                            <div class="flex-1">
                                <h3 class="font-semibold text-gray-900 text-sm">Layani Pengguna</h3>
                                <p class="mt-1 text-sm text-gray-600">Respons tiket dan berikan solusi kepada pengguna</p>
                            </div>
                        </div>
                    </div>

                    <!-- Download PDF Button -->
                    <div class="mt-8 pt-6 border-t border-gray-200">
                        <a 
                            href="{{ asset('pdf/PanduanSistem.pdf') }}" 
                            class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-red-600 px-6 py-3 text-sm font-semibold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors duration-200"
                            download
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2m0 0v-8m0 8l-6-4m6 4l6-4" />
                            </svg>
                            Download Panduan PDF
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
