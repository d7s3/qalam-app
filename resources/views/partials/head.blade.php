<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('brand.name') : config('brand.name') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
{{-- Typed from the file rather than assumed: an organisation that has a PNG
     mark and no SVG one was being served it as image/svg+xml. --}}
<link rel="icon" href="{{ asset(config('brand.favicon')) }}"
    type="{{ str_ends_with(config('brand.favicon'), '.svg') ? 'image/svg+xml' : 'image/png' }}">
<link rel="apple-touch-icon" href="{{ asset(config('brand.apple_icon')) }}">

<link rel="preconnect" href="https://fonts.bunny.net">

@vite(['resources/css/app.css', 'resources/js/app.js'])

{{-- After @vite, so the organisation's palette overrides the compiled tokens. --}}
@include('partials.brand-theme')

@fluxAppearance
