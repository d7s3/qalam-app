<?php

use App\Models\Form;
use App\Models\FormResponse;
use App\Support\SurveyFieldTypes;
use Livewire\Component;

/**
 * A form answered by somebody with no account.
 *
 * Every form before this one was aimed at people already inside the academy,
 * which is right for asking teachers how a term went and useless for the one
 * thing an academy does before anybody is inside it: taking applications.
 *
 * A stranger has no user row, so his answers carry his own name and number and
 * the academy reaches him by them. Nothing about him is created — an applicant
 * is not a student, and making him one before anybody has read his answers
 * would fill the academy with people nobody accepted.
 *
 * The form's own colour dresses the page, so one route serves every programme
 * that ever opens one and each keeps its own face.
 */
new class extends Component
{
    public Form $form;

    /** @var array<string, mixed> */
    public array $answers = [];

    public bool $done = false;

    public function mount(string $token): void
    {
        $form = Form::where('public_token', $token)->firstOrFail();

        abort_unless($form->isOpenToPublic(), 410, __('انتهى التسجيل في هذا النموذج.'));

        $this->form = $form;

        foreach ($this->questions() as $field) {
            $this->answers[$field['id']] = $field['type'] === 'multiselect' ? [] : null;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function questions(): array
    {
        return array_values(array_filter(
            $this->form->fields ?? [],
            fn (array $field) => ! SurveyFieldTypes::isLayout($field['type'] ?? 'text'),
        ));
    }

    /** Everything in the form, dividers included, in the order it is read. */
    public function parts(): array
    {
        return $this->form->fields ?? [];
    }

    public function submit(): void
    {
        abort_unless($this->form->isOpenToPublic(), 410);

        $rules = [];
        $names = [];

        foreach ($this->questions() as $field) {
            $key = "answers.{$field['id']}";
            $required = ($field['required'] ?? false) ? 'required' : 'nullable';

            $rules[$key] = match ($field['type']) {
                'multiselect' => [$required, 'array'],
                'date' => [$required, 'date'],
                'long_text' => [$required, 'string', 'max:2000'],
                default => [$required, 'string', 'max:500'],
            };

            $names[$key] = $field['label'];
        }

        $this->validate($rules, [], $names);

        // The applicant's own contact details, lifted out of his answers so the
        // academy can reach him without reading the whole form to find a number.
        $reach = $this->contactDetails();

        FormResponse::create([
            'form_id' => $this->form->id,
            'answers' => $this->answers,
            'respondent_name' => $reach['name'],
            'respondent_phone' => $reach['phone'],
            'respondent_email' => $reach['email'],
        ]);

        $this->done = true;
    }

    /** @return array{name: ?string, phone: ?string, email: ?string} */
    private function contactDetails(): array
    {
        $found = ['name' => null, 'phone' => null, 'email' => null];

        foreach ($this->questions() as $field) {
            $value = $this->answers[$field['id']] ?? null;

            if (! is_string($value) || $value === '') {
                continue;
            }

            $label = $field['label'] ?? '';

            $found['name'] ??= ($field['is_student_name'] ?? false) ? $value : null;
            $found['phone'] ??= str_contains($label, 'جوال') || str_contains($label, 'هاتف') ? $value : null;
            $found['email'] ??= filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
        }

        return $found;
    }
}; ?>

@php
    /**
     * The programme's own two colours, taken from its poster.
     *
     * One colour would have been simpler and would not have been نوابغ: the
     * poster alternates a teal and a coral all the way down — the badge teal,
     * the name coral, the five track numbers turning from one to the other —
     * on a warm cream ground rather than on white. A page in a single tone
     * beside that poster reads as a different programme.
     *
     * Both are columns on the form. The second was derived from the first by
     * matching a string at one point, which worked until the seeder ran again
     * with a different value and the two quietly became one — a colour somebody
     * chose belongs in a column, not in a condition. A form that names only one
     * uses it throughout, which is what a single-coloured programme wants.
     */
    $brand = $form->color ?: '#1B9A8F';
    $accent = $form->accent_color ?: $brand;

    // Written out rather than taken from the application's component library:
    // those inputs are built for the signed-in shell and inherit a translucent
    // ground that vanishes on white. A page a stranger opens should owe its
    // appearance to nothing but itself.
    $box = 'w-full rounded-xl border border-zinc-300 bg-white px-4 py-3 text-zinc-900 '
        .'placeholder:text-zinc-400 focus:outline-none focus:ring-2 focus:ring-[color:var(--brand)] '
        .'focus:border-[color:var(--brand)]';
@endphp

<div dir="rtl" style="--brand: {{ $brand }}; --accent: {{ $accent }}; background: #FDF7EF;">
    @if ($done)
        <div class="mx-auto max-w-xl px-6 py-24 text-center">
            <div class="mx-auto flex size-16 items-center justify-center rounded-full"
                style="background: color-mix(in oklab, var(--accent) 16%, transparent);">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
                    stroke-linecap="round" stroke-linejoin="round" class="size-8" style="color: var(--accent);">
                    <path d="M20 6 9 17l-5-5" />
                </svg>
            </div>

            <h1 class="mt-6 text-2xl font-bold text-zinc-900">{{ __('وصلنا طلبك') }}</h1>

            <p class="mt-3 leading-loose text-zinc-600">
                {{ $form->success_text ?: __('شكراً لك. سنراجع الطلب ونتواصل معك على الرقم الذي كتبته.') }}
            </p>
        </div>
    @else
        {{-- الترويسة --}}
        <header class="relative overflow-hidden px-6 pb-16 pt-14 text-center">
            {{-- The poster's two corner shapes, which are most of what makes it
                 recognisable from across a room. --}}
            <div aria-hidden="true" class="pointer-events-none absolute -right-20 -top-24 size-56 rounded-full"
                style="background: color-mix(in oklab, var(--brand) 16%, transparent);"></div>
            <div aria-hidden="true" class="pointer-events-none absolute -left-24 top-10 size-40 rounded-full"
                style="background: color-mix(in oklab, var(--accent) 14%, transparent);"></div>

            <div class="relative">
                <span class="inline-block rounded-full px-5 py-2 text-sm font-bold text-white"
                    style="background: var(--brand);">
                    {{ __('هنا تبدأ رحلة التعلّم، وتنطلق طاقات التميّز') }}
                </span>

                <h1 class="mt-7 text-4xl font-bold md:text-5xl" style="color: var(--accent);">
                    {{ $form->title }}
                </h1>

                {{-- قيمٌ ترسخ · عقولٌ تنبغ · وأثرٌ يمتد --}}
                <div class="mx-auto mt-6 flex max-w-md flex-wrap items-center justify-center gap-x-7 gap-y-2 text-sm font-bold">
                    @foreach ([['قيمٌ ترسخ', 'brand'], ['عقولٌ تنبغ', 'accent'], ['وأثرٌ يمتد', 'brand']] as [$word, $tone])
                        <span style="color: var(--{{ $tone }});">{{ $word }}</span>
                    @endforeach
                </div>

                @if ($form->public_intro)
                    <p class="mx-auto mt-7 max-w-2xl leading-loose text-zinc-600">{{ $form->public_intro }}</p>
                @endif

                @if ($form->closes_on)
                    <p class="mt-6 inline-block rounded-2xl bg-white px-5 py-3 text-sm font-bold shadow-sm"
                        style="color: var(--accent);">
                        {{ __('التسجيل متاح حتى') }} <x-hijri-date :date="$form->closes_on" />
                        <span class="text-zinc-400">·</span>
                        <span style="color: var(--brand);">{{ __('المقاعد محدودة') }}</span>
                    </p>
                @endif
            </div>
        </header>

        <form wire:submit="submit" class="mx-auto max-w-2xl space-y-5 px-5 pb-24">
            @foreach ($this->parts() as $field)
                @php $type = $field['type'] ?? 'text'; @endphp

                @if ($type === 'section')
                    @php
                        // Alternating, as the poster's five numbers do.
                        $tone = ($sectionIndex = ($sectionIndex ?? -1) + 1) % 2 === 0 ? 'brand' : 'accent';
                    @endphp
                    <div class="pt-10 first:pt-0">
                        <h2 class="text-xl font-bold" style="color: var(--{{ $tone }});">{{ $field['label'] }}</h2>
                        <div class="mt-2 h-1 w-14 rounded-full" style="background: var(--{{ $tone }});"></div>
                    </div>
                @else
                    <div class="rounded-2xl border border-zinc-200 bg-white p-5" wire:key="q-{{ $field['id'] }}">
                        <label class="block font-bold text-zinc-800">
                            {{ $field['label'] }}
                            @if ($field['required'] ?? false)
                                <span style="color: var(--brand);">*</span>
                            @endif
                        </label>

                        <div class="mt-3">
                            @switch($type)
                                @case('long_text')
                                    <textarea wire:model="answers.{{ $field['id'] }}" rows="3" class="{{ $box }}"></textarea>
                                    @break

                                @case('date')
                                    <input type="date" wire:model="answers.{{ $field['id'] }}" class="{{ $box }}" dir="ltr" />
                                    @break

                                @case('yesno')
                                    <div class="flex gap-2">
                                        @foreach (['نعم', 'لا'] as $choice)
                                            <label class="flex-1 cursor-pointer rounded-xl border px-4 py-2.5 text-center text-sm transition
                                                {{ ($answers[$field['id']] ?? null) === $choice ? 'font-bold text-white' : 'border-zinc-200 text-zinc-600' }}"
                                                style="{{ ($answers[$field['id']] ?? null) === $choice ? 'background: var(--brand); border-color: var(--brand);' : '' }}">
                                                <input type="radio" class="sr-only"
                                                    wire:model.live="answers.{{ $field['id'] }}" value="{{ $choice }}" />
                                                {{ $choice }}
                                            </label>
                                        @endforeach
                                    </div>
                                    @break

                                @case('select')
                                    <select wire:model="answers.{{ $field['id'] }}" class="{{ $box }}">
                                        <option value="">{{ __('اختر') }}</option>
                                        @foreach ($field['options'] ?? [] as $option)
                                            <option value="{{ $option }}">{{ $option }}</option>
                                        @endforeach
                                    </select>
                                    @break

                                @case('multiselect')
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        @foreach ($field['options'] ?? [] as $option)
                                            <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-zinc-200 px-3 py-2 text-sm text-zinc-700">
                                                <input type="checkbox" class="rounded"
                                                    style="accent-color: var(--brand);"
                                                    wire:model="answers.{{ $field['id'] }}" value="{{ $option }}" />
                                                {{ $option }}
                                            </label>
                                        @endforeach
                                    </div>
                                    @break

                                @case('rating')
                                @case('likert')
                                @case('nps')
                                    @php $top = $type === 'nps' ? 10 : ($field['scale'] ?? 5); @endphp
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach (range(1, $top) as $n)
                                            <label class="size-10 cursor-pointer rounded-xl border text-center text-sm leading-10 transition
                                                {{ (int) ($answers[$field['id']] ?? 0) === $n ? 'font-bold text-white' : 'border-zinc-200 text-zinc-500' }}"
                                                style="{{ (int) ($answers[$field['id']] ?? 0) === $n ? 'background: var(--brand); border-color: var(--brand);' : '' }}">
                                                <input type="radio" class="sr-only"
                                                    wire:model.live="answers.{{ $field['id'] }}" value="{{ $n }}" />
                                                {{ $n }}
                                            </label>
                                        @endforeach
                                    </div>
                                    @break

                                @default
                                    <input type="text" wire:model="answers.{{ $field['id'] }}" class="{{ $box }}" />
                            @endswitch

                            @error('answers.'.$field['id'])
                                <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                @endif
            @endforeach

            @if ($form->policy_text)
                <p class="px-1 text-xs leading-loose text-zinc-500">{{ $form->policy_text }}</p>
            @endif

            <button type="submit"
                class="w-full rounded-2xl py-4 text-lg font-bold text-white shadow-sm transition hover:opacity-90"
                style="background: var(--accent);">
                {{ __('أرسل الطلب') }}
            </button>

            <p class="text-center text-sm" style="color: var(--brand);">
                {{ __('امنح ابنك فرصةً تنمّي علمه وترسّخ قيمه وتطوّر مهاراته') }}
            </p>
        </form>
    @endif
</div>
