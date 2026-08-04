<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        {{-- До первой отрисовки, иначе тёмная тема моргает белым. Ту же логику повторяет useTheme. --}}
        <script>
            (() => {
                const mode = localStorage.getItem('deployer.theme');
                const dark = mode === 'dark' || (mode !== 'light'
                    && window.matchMedia('(prefers-color-scheme: dark)').matches);

                document.documentElement.classList.toggle('dark', dark);
            })();
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    {{-- Цвет текста на body: элементы без своего text-* иначе остаются чёрными на тёмном фоне. --}}
    <body class="bg-gray-100 font-sans text-gray-900 antialiased dark:bg-gray-900 dark:text-gray-100">
        @inertia
    </body>
</html>
