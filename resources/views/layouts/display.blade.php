<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl" class="dark">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="color-scheme" content="dark">

    <title>{{ $title ?? 'شاشة العرض' }}</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">

    <link rel="preconnect" href="https://fonts.bunny.net">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance

    <script>
        // Projector page is dark-only regardless of any stored preference.
        document.documentElement.classList.add('dark');
    </script>
</head>
<body class="min-h-screen bg-zinc-950 text-white antialiased overflow-hidden">
    {{ $slot }}

    @fluxScripts
</body>
</html>
