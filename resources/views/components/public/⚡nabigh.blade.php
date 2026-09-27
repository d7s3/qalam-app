<?php

use App\Support\NabighExam;
use Livewire\Component;

/**
 * The one page a whole intake lands on.
 *
 * A family should not have to be told which token to open, which of two
 * parallel forms their child sits, or which battery their grade belongs to.
 * They know one thing for certain — the grade — so that is all this asks, and
 * the grade decides the rest: grades 3-6 are handed one of the two device
 * exams at random, and grades 1-2, which a small child cannot sit alone, are
 * told plainly that theirs is a live session and how it is arranged.
 */
new class extends Component
{
    /** The grade whose family has just been told it is assessed in person. */
    public ?string $inPersonGrade = null;

    /** Set when the grade's exam exists but is closed right now. */
    public bool $closed = false;

    /** @return array<int, string> */
    public function grades(): array
    {
        return array_keys(NabighExam::GRADE_ROUTES);
    }

    public function choose(string $grade): void
    {
        if (! array_key_exists($grade, NabighExam::GRADE_ROUTES)) {
            return;
        }

        $this->closed = false;
        $this->inPersonGrade = null;

        if (NabighExam::isInPersonGrade($grade)) {
            $this->inPersonGrade = $grade;

            return;
        }

        // One of the two parallel forms, chosen at random so no two students in
        // a row are handed the same one — the whole reason both were seeded.
        $exam = NabighExam::openExamForGrade($grade);

        if (! $exam) {
            $this->closed = true;

            return;
        }

        // A relative path, so the browser resolves it against whatever origin it
        // is actually on. An absolute URL would be built from the host Laravel
        // sees, which behind a tunnel or proxy is the internal one — and would
        // send a family from a public link back to a localhost that is not
        // theirs to reach.
        $this->redirect(route('forms.apply', ['token' => $exam->public_token], absolute: false));
    }

    public function chooseAgain(): void
    {
        $this->inPersonGrade = null;
        $this->closed = false;
    }
}; ?>

@php
    $brand = '#1B9A8F';
    $accent = '#EE6A4D';
    $phone = config('brand.contact.phone');
@endphp

<div dir="rtl" style="--brand: {{ $brand }}; --accent: {{ $accent }}; background: #FDF7EF;">
    <header class="relative overflow-hidden px-6 pb-14 pt-16 text-center">
        <div aria-hidden="true" class="pointer-events-none absolute -right-20 -top-24 size-56 rounded-full"
            style="background: color-mix(in oklab, var(--brand) 16%, transparent);"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -left-24 top-10 size-40 rounded-full"
            style="background: color-mix(in oklab, var(--accent) 14%, transparent);"></div>

        <div class="relative">
            <span class="inline-block rounded-full px-5 py-2 text-sm font-bold text-white" style="background: var(--brand);">
                {{ __('هنا تبدأ رحلة التعلّم، وتنطلق طاقات التميّز') }}
            </span>

            <h1 class="mt-7 text-4xl font-bold md:text-5xl" style="color: var(--accent);">
                {{ __('مقياس نابغة') }}
            </h1>

            <p class="mx-auto mt-6 max-w-xl leading-loose text-zinc-600">
                {{ __('اختبار إلكتروني قصير يقيس ملاءمة الطالب لبرنامج نابغة. اختر صف الطالب ليبدأ الاختبار المناسب له.') }}
            </p>
        </div>
    </header>

    <main class="mx-auto max-w-2xl px-5 pb-24">
        @if ($inPersonGrade)
            {{-- A grade a child cannot sit alone: not a dead end, a next step. --}}
            <div class="rounded-2xl border border-zinc-200 bg-white p-7 text-center">
                <div class="mx-auto flex size-14 items-center justify-center rounded-full"
                    style="background: color-mix(in oklab, var(--brand) 14%, transparent); color: var(--brand);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                        stroke-linejoin="round" class="size-7">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" />
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                </div>

                <h2 class="mt-5 text-xl font-bold text-zinc-900">{{ __('تقييم :grade يكون بجلسة حضورية', ['grade' => $inPersonGrade]) }}</h2>

                <p class="mx-auto mt-3 max-w-md leading-loose text-zinc-600">
                    {{ __('في هذه المرحلة يُطبَّق المقياس في جلسة فردية موجّهة مع مقيِّم، ولا يؤدّيه الطفل على الجهاز بمفرده. سيتواصل معكم فريق نابغة لتحديد موعد الجلسة.') }}
                </p>

                @if ($phone)
                    <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}"
                        class="mt-6 inline-flex items-center gap-2 rounded-2xl px-6 py-3 text-sm font-bold text-white"
                        style="background: var(--accent);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" class="size-4">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
                        </svg>
                        {{ __('للتواصل: :phone', ['phone' => $phone]) }}
                    </a>
                @endif

                <div>
                    <button type="button" wire:click="chooseAgain" class="mt-6 text-sm font-bold" style="color: var(--brand);">
                        {{ __('اختيار صف آخر') }}
                    </button>
                </div>
            </div>
        @elseif ($closed)
            <div class="rounded-2xl border border-zinc-200 bg-white p-7 text-center">
                <h2 class="text-xl font-bold text-zinc-900">{{ __('التسجيل مغلق حاليًا') }}</h2>
                <p class="mx-auto mt-3 max-w-md leading-loose text-zinc-600">
                    {{ __('اختبار هذه المرحلة غير متاح الآن. تابعونا ليُعاد فتحه، أو اختاروا صفًا آخر.') }}
                </p>
                <button type="button" wire:click="chooseAgain" class="mt-6 text-sm font-bold" style="color: var(--brand);">
                    {{ __('اختيار صف آخر') }}
                </button>
            </div>
        @else
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 md:p-8">
                <h2 class="text-center text-lg font-bold text-zinc-800">{{ __('في أي صف الطالب؟') }}</h2>

                <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach ($this->grades() as $grade)
                        <button type="button" wire:click="choose('{{ $grade }}')" wire:loading.attr="disabled"
                            class="rounded-2xl border-2 px-4 py-5 text-center font-bold text-zinc-700 transition
                                   hover:border-[color:var(--brand)] hover:bg-[color:var(--brand)] hover:text-white"
                            style="border-color: color-mix(in srgb, var(--brand) 25%, #e4e4e7);">
                            {{ $grade }}
                        </button>
                    @endforeach
                </div>

                <p class="mt-6 text-center text-xs leading-loose text-zinc-400">
                    {{ __('اختبار الصفوف الأول والثاني يكون بجلسة حضورية مع مقيِّم، أما الصفوف الثالث إلى السادس فيؤدّيه الطالب على الجهاز، ويُفضّل أن يكون أحد الوالدين قريبًا.') }}
                </p>
            </div>
        @endif
    </main>
</div>
