@props([
    'title',
    'subtitle' => null,
    'icon' => null,
    'href' => null,
    'linkText' => null,
])

<div class="flex items-center justify-between mb-4" dir="rtl">
    <div class="flex items-center gap-3">
        @if($icon)
            <div class="p-2 rounded-lg bg-maroon/10 text-maroon">
                <flux:icon :icon="$icon" variant="solid" class="size-5" />
            </div>
        @endif
        <div>
            <flux:heading size="lg">{{ $title }}</flux:heading>
            @if($subtitle)
                <flux:subheading>{{ $subtitle }}</flux:subheading>
            @endif
        </div>
    </div>

    @if($href)
        <a href="{{ $href }}" class="text-sm font-semibold text-maroon hover:underline shrink-0">
            {{ $linkText ?? __('عرض الكل') }}
        </a>
    @endif
</div>
