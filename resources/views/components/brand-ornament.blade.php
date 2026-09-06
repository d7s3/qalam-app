{{--
    The tessellated eight-point star the brand's backgrounds are built from.

    It replaces the loose line drawings — a lantern, a rehl — that used to float
    at the corners of the welcome page: a repeating geometric ground reads as
    ornament at any size, where a single clip-art figure only reads as clip art.

    Two overlapping squares, one turned forty-five degrees, give the star; the
    stubs at the tile edges carry the lattice across the seam so the repeat is
    invisible. Colour and strength come from the caller — pass `text-gold` and an
    opacity — since the same ground sits on both the deep hero and the footer.
--}}
@props(['tile' => 64])

@php
    $patternId = 'brand-ornament-'.Str::random(6);
@endphp

<div aria-hidden="true" {{ $attributes->merge(['class' => 'pointer-events-none absolute inset-0']) }}>
    <svg width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <pattern id="{{ $patternId }}" width="{{ $tile }}" height="{{ $tile }}" patternUnits="userSpaceOnUse">
                <g fill="none" stroke="currentColor" stroke-width="1" stroke-linejoin="round">
                    <path d="M32 6 L58 32 L32 58 L6 32 Z" />
                    <path d="M13.4 13.4 H50.6 V50.6 H13.4 Z" />
                    <circle cx="32" cy="32" r="5.5" />
                </g>
                <g fill="none" stroke="currentColor" stroke-width="0.6" opacity="0.7">
                    <path d="M0 32 H6 M58 32 H64 M32 0 V6 M32 58 V64" />
                </g>
            </pattern>
        </defs>
        <rect width="100%" height="100%" fill="url(#{{ $patternId }})" />
    </svg>
</div>
