@extends('layouts.app')

@section('title', 'Masuk ke Helpdesk TA')

@section('content')
<div class="bg-gray-100 py-10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-8">
            <h1 class="text-3xl font-semibold text-gray-800">Sistem Helpdesk TA</h1>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[430px_minmax(0,1fr)] gap-8">
            <div class="bg-white border border-gray-200 rounded-3xl shadow-sm p-8">
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-gray-900">Login</h2>
                    <p class="mt-2 text-sm text-gray-600">Gunakan email dan password akun Anda.</p>
                </div>

                <x-auth-session-status class="mb-4" :status="session('status')" />

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="space-y-4">
                        <div>
                            <x-input-label for="email" :value="__('Email')" />
                            <x-text-input id="email" class="block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="password" :value="__('Password')" />
                            <x-text-input id="password" class="block w-full" type="password" name="password" required autocomplete="current-password" />
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-between gap-3">
                            <label for="remember_me" class="inline-flex items-center">
                                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
                            </label>
                            @if (Route::has('password.request'))
                                <a class="text-sm text-gray-600 hover:text-gray-900" href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a>
                            @endif
                        </div>

                        <div>
                            <x-primary-button class="w-full">{{ __('Log in') }}</x-primary-button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="bg-white border border-gray-200 rounded-3xl shadow-sm p-8">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Cara Menggunakan</h2>
                    <p class="mt-2 text-sm text-gray-600">Panduan singkat</p>

                    <div class="mt-6 space-y-4 text-gray-700 text-sm">
                        <div class="rounded-2xl bg-gray-50 p-4">
                            <span class="font-medium text-gray-900">1.</span> .
                        </div>
                        <div class="rounded-2xl bg-gray-50 p-4">
                            <span class="font-medium text-gray-900">2.</span> .
                        </div>
                        <div class="rounded-2xl bg-gray-50 p-4">
                            <span class="font-medium text-gray-900">3.</span> .
                        </div>
                        <div class="rounded-2xl bg-gray-50 p-4">
                            <span class="font-medium text-gray-900">4.</span> .
                        </div>
                    </div>
                </div>

                <div class="mt-8">
                    <a href="{{ asset('pdf/PanduanSistem.pdf') }}" class="inline-flex w-full items-center justify-center rounded-xl bg-red-600 px-4 py-3 text-sm font-semibold text-white hover:bg-red-700 transition">Download PDF</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
