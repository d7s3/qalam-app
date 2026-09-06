<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('brand.name') : config('brand.name') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="{{ asset(config('brand.favicon')) }}" type="image/svg+xml">
<link rel="apple-touch-icon" href="{{ asset(config('brand.apple_icon')) }}">

<link rel="preconnect" href="https://fonts.bunny.net">

@vite(['resources/css/app.css', 'resources/js/app.js'])

{{-- After @vite, so the organisation's palette overrides the compiled tokens. --}}
@include('partials.brand-theme')

@fluxAppearance
