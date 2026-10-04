<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'WorkZone') }}</title>

        @if (!empty($page['props']['robotsNoIndex']))
            <meta name="robots" content="noindex, nofollow">
        @endif

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=ibm-plex-sans-arabic:400,500,600,700|plus-jakarta-sans:400,500,600,700&display=swap" rel="stylesheet" />

        <script>
            (function () {
                try {
                    var theme = localStorage.getItem('wz_theme');
                    if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                        document.documentElement.classList.add('dark');
                    }
                    var savedLocale = localStorage.getItem('wz_locale');
                    var locale = (savedLocale === 'ar' || savedLocale === 'en')
                        ? savedLocale
                        : ((navigator.language || '').toLowerCase().startsWith('ar') ? 'ar' : 'en');
                    document.documentElement.lang = locale;
                    document.documentElement.dir = locale === 'ar' ? 'rtl' : 'ltr';
                } catch (e) {}
            })();
        </script>

        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased bg-wz-bg text-wz-fg">
        @inertia
    </body>
</html>
