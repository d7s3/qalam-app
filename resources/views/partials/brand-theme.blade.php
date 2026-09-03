{{--
    The organisation's palette, applied over the tokens the compiled stylesheet
    declares.

    Tailwind resolves `bg-maroon` and its siblings to `var(--color-maroon)`, so
    redefining those variables here re-colours all 183 uses of them without
    touching a class name or rebuilding the CSS — which is what lets a new
    organisation change colour by editing .env alone.

    It sits after @vite in the head so it wins on order, and the dark block
    mirrors the stylesheet's own so an explicit choice and the system default
    both land on the same colour.
--}}
@php
    $brand = config('brand.colors');
@endphp

<style>
    :root {
        --color-maroon: {{ $brand['primary'] }};
        --color-burgundy: {{ $brand['dark'] }};
        --color-red-secondary: {{ $brand['on_dark'] }};
        --color-accent-dark: {{ $brand['deepest'] }};
        --color-gold: {{ $brand['gold'] }};
        --color-accent: {{ $brand['primary'] }};
    }

    .dark {
        --color-accent: {{ $brand['on_dark'] }};
    }
</style>
