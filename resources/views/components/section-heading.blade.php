{{--
    The head of a section, in the brand's own hand.

    Every section used to open the same way: an icon in a rounded tile, tinted a
    colour picked for that section alone, then the title, then a grey line under
    it. Repeated down a page it stops being a heading and becomes wallpaper —
    and the tile's colour, chosen per section, was the loudest thing on screen
    saying nothing.

    What is left is what the sign-in page already does: a short gold rule, the
    title set in the display face, and the detail beneath it quietly. The icon
    is optional and small, in the ink of the text, because a heading is read
    before it is looked at.
--}}
@props(['icon' => null, 'level' => 'xl'])

<div {{ $attributes->merge(['class' => 'flex items-start gap-3']) }}>
    <span aria-hidden="true" class="mt-2.5 h-px w-6 shrink-0 bg-gradient-to-l from-transparent to-gold"></span>

    <div class="min-w-0">
        <div class="flex items-center gap-2">
            @if ($icon)
                <flux:icon :icon="$icon" class="size-5 shrink-0 text-maroon/70 dark:text-gold/80" />
            @endif
            <flux:heading :size="$level" class="font-bold">{{ $slot }}</flux:heading>
        </div>

        @isset($detail)
            <flux:subheading class="mt-0.5 text-zinc-500 dark:text-zinc-400">{{ $detail }}</flux:subheading>
        @endisset
    </div>
</div>
