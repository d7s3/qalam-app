{{--
    The single «نابغة» landing, owing nothing to the organisation hosting it.

    Like a public form's own page, this carries the programme's face rather than
    the academy's: its name in the tab, its colour as the browser's, its words in
    whatever preview a messaging app draws of the link. One link on a poster, and
    each family is sent from here to the exam their child's grade calls for.
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl" class="antialiased">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <title>{{ __('مقياس نابغة') }}</title>
    <meta name="description" content="{{ __('اختر صف الطالب ليبدأ الاختبار المناسب له.') }}" />

    <meta property="og:type" content="website" />
    <meta property="og:title" content="{{ __('مقياس نابغة') }}" />
    <meta property="og:description" content="{{ __('اختر صف الطالب ليبدأ الاختبار المناسب له.') }}" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta name="twitter:card" content="summary" />

    {{-- The tab's mark: the programme's initial in its own colour, drawn inline
         so the page asks the association's server for no icon of its. --}}
    <link rel="icon" href="data:image/svg+xml,{{ rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
        .'<rect width="64" height="64" rx="14" fill="#1B9A8F"/>'
        .'<text x="32" y="45" font-size="38" font-family="system-ui,sans-serif" font-weight="bold"'
        .' text-anchor="middle" fill="#fff">ن</text></svg>'
    ) }}" type="image/svg+xml">

    <meta name="theme-color" content="#1B9A8F" />

    <link rel="preconnect" href="https://fonts.bunny.net">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @fluxAppearance
</head>
<body class="min-h-screen text-zinc-900" style="background:#FDF7EF">
    <livewire:public.nabigh />
</body>
</html>
