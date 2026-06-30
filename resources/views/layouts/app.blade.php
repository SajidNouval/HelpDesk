<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <script src="{{ asset('js/confirm-dialog.js') }}"></script>
    </head>
    <body class="font-sans antialiased bg-gray-100">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @if (isset($header))
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main>
                @yield('content')
                {{ $slot ?? '' }}
            </main>

            <!-- Toast Container -->
            <div id="toast-container" class="fixed bottom-4 right-4 z-50 flex flex-col gap-2 pointer-events-none" aria-live="polite" aria-atomic="true"></div>
        </div>

        <!-- Flash Message Toast Handler -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                @if(session('success'))
                    window.toast?.success('{{ session('success') }}', 4000);
                @endif

                @if(session('error'))
                    window.toast?.error('{{ session('error') }}', 4000);
                @endif

                @if(session('info'))
                    window.toast?.info('{{ session('info') }}', 4000);
                @endif

                @if(session('warning'))
                    window.toast?.warning('{{ session('warning') }}', 4000);
                @endif
            });
        </script>

        <!-- Navigation State Preservation (Scroll & Tab Position) -->
        <script>
            (function() {
                // --- SCROLL POSITION PRESERVATION ---
                const scrollKey = 'scroll_pos_' + window.location.href;
                
                // Save scroll position before unloading
                window.addEventListener('beforeunload', () => {
                    sessionStorage.setItem(scrollKey, window.scrollY);
                });

                // Debounced scroll listener to ensure position is saved during active usage
                let scrollTimeout;
                window.addEventListener('scroll', () => {
                    clearTimeout(scrollTimeout);
                    scrollTimeout = setTimeout(() => {
                        sessionStorage.setItem(scrollKey, window.scrollY);
                    }, 200);
                });

                // Restore scroll position
                function restoreScroll() {
                    const savedScroll = sessionStorage.getItem(scrollKey);
                    if (savedScroll !== null) {
                        window.scrollTo(0, parseInt(savedScroll, 10));
                    }
                }

                if (document.readyState === 'complete') {
                    restoreScroll();
                } else {
                    window.addEventListener('load', restoreScroll);
                    document.addEventListener('DOMContentLoaded', restoreScroll);
                }

                // --- TAB STATE PRESERVATION ---
                // Automatically append current active tab to all forms if present in the URL
                document.addEventListener('DOMContentLoaded', () => {
                    const urlParams = new URLSearchParams(window.location.search);
                    const tabParam = urlParams.get('tab');
                    if (tabParam) {
                        document.querySelectorAll('form').forEach(form => {
                            if (!form.querySelector('input[name="tab"]') && form.method.toLowerCase() === 'post') {
                                const tabInput = document.createElement('input');
                                tabInput.type = 'hidden';
                                tabInput.name = 'tab';
                                tabInput.value = tabParam;
                                form.appendChild(tabInput);
                            }
                        });
                    }
                });
            })();
        </script>
    </body>
</html>
