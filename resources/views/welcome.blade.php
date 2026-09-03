<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>بوابة {{ config('brand.name') }} الرقمية</title>

    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="{{ asset(config('brand.favicon')) }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset(config('brand.apple_icon')) }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- After @vite, so the organisation's palette overrides the compiled tokens. --}}
    @include('partials.brand-theme')

    {{--
        This page builds its own head rather than including partials.head, and so
        used to miss the appearance script: a visitor who had chosen dark got it
        only when arriving through wire:navigate, and a light page on a direct
        visit to the very same URL.
    --}}
    @fluxAppearance

    <style>
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(24px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-fade-in-up {
            opacity: 0;
            animation: fadeInUp 0.7s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        .delay-100 { animation-delay: 100ms; }
        .delay-200 { animation-delay: 200ms; }
        .delay-300 { animation-delay: 300ms; }
        .delay-400 { animation-delay: 400ms; }

        @media (prefers-reduced-motion: reduce) {
            .animate-fade-in-up {
                opacity: 1;
                animation: none;
            }
        }
    </style>
</head>

<body class="bg-[#fdfaf5] dark:bg-accent-dark text-[#1b1b18] dark:text-[#EDEDEC] font-sans antialiased overflow-x-hidden selection:bg-gold selection:text-accent-dark">

    <x-site-header />

    {{-- ============================== البوابة ============================== --}}
    <section class="relative overflow-hidden bg-gradient-to-bl from-accent-dark via-burgundy to-maroon px-5 py-16 md:py-24">
        <x-brand-ornament class="text-gold opacity-[0.07]" />

        {{-- توهّج ذهبي ناعم يمنح العمق --}}
        <div class="pointer-events-none absolute -top-24 right-1/4 size-[26rem] rounded-full bg-gold/20 blur-[100px]"></div>
        <div class="pointer-events-none absolute -bottom-32 left-0 size-[22rem] rounded-full bg-gold/10 blur-[90px]"></div>

        <div class="relative max-w-6xl mx-auto grid lg:grid-cols-[1.05fr_.95fr] gap-12 lg:gap-16 items-center">

            {{-- النص والدخول --}}
            <div class="animate-fade-in-up text-center lg:text-start">
                <span class="inline-flex items-center gap-2 rounded-full border border-gold/40 bg-gold/10 px-4 py-1.5 text-[11px] md:text-xs font-bold text-gold">
                    <flux:icon icon="sparkles" class="size-3.5" />
                    {{ config('brand.tagline') }}
                </span>

                <h1 class="mt-6 text-3xl md:text-4xl lg:text-5xl font-extrabold text-white leading-[1.35] text-balance">
                    بوابة <span class="text-gold">{{ config('brand.name') }}</span> الرقمية
                </h1>

                <p class="mt-5 text-sm md:text-base text-white/70 leading-loose max-w-xl mx-auto lg:mx-0">
                    سجّل الدخول ببريدك الإلكتروني وكلمة المرور، ليتعرّف النظام على نوع حسابك
                    ويوجّهك إلى صفحتك الخاصة مباشرة.
                </p>

                <div class="mt-9 flex flex-col sm:flex-row items-stretch sm:items-center justify-center lg:justify-start gap-3">
                    <a href="{{ route('login') }}" wire:navigate
                       class="group inline-flex items-center justify-center gap-2 rounded-2xl bg-gold px-8 py-3.5 text-sm md:text-base font-extrabold text-accent-dark shadow-lg shadow-black/25 transition-all hover:brightness-110 hover:-translate-y-0.5">
                        تسجيل الدخول
                        <flux:icon icon="arrow-left" class="size-4 transition-transform group-hover:-translate-x-1" />
                    </a>
                    <a href="{{ route('register') }}" wire:navigate
                       class="inline-flex items-center justify-center gap-2 rounded-2xl border border-white/25 px-8 py-3.5 text-sm md:text-base font-bold text-white/90 transition-colors hover:bg-white/10 hover:text-white">
                        إنشاء حساب جديد
                    </a>
                </div>

                {{-- روابط المساعدة --}}
                <div class="mt-10 pt-8 border-t border-white/10">
                    <p class="text-xs font-bold text-white/50">هل تواجه مشكلة في تسجيل الدخول؟</p>
                    <div class="mt-4 flex flex-wrap items-center justify-center lg:justify-start gap-x-8 gap-y-4">
                        @foreach ([
                            ['icon' => 'lock-closed', 'label' => 'استعادة كلمة المرور', 'href' => route('password.request'), 'navigate' => true],
                            ['icon' => 'phone', 'label' => 'الاتصال بالدعم', 'href' => 'tel:'.config('brand.contact.phone'), 'navigate' => false],
                            ['icon' => 'envelope', 'label' => 'تواصل معنا', 'href' => '#contact', 'navigate' => false],
                        ] as $link)
                            <a href="{{ $link['href'] }}" @if ($link['navigate']) wire:navigate @endif
                               class="group inline-flex items-center gap-2 text-xs font-bold text-white/70 transition-colors hover:text-gold">
                                <flux:icon :icon="$link['icon']" class="size-4 text-gold/70 transition-colors group-hover:text-gold" />
                                {{ $link['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- ميدالية الشعار --}}
            <div class="animate-fade-in-up delay-200 flex justify-center lg:justify-end">
                <div class="relative w-full max-w-sm rounded-[2rem] border border-gold/25 bg-gradient-to-b from-[#fdfaf3] to-[#f3e8d6] p-8 md:p-10 text-center shadow-2xl shadow-black/30">
                    <div class="absolute inset-3 rounded-[1.6rem] border border-gold/25 pointer-events-none"></div>

                    <img src="{{ asset(config('brand.logo')) }}" alt="{{ config('brand.name') }}"
                         class="relative mx-auto h-28 md:h-32 w-auto object-contain" />

                    <div class="relative mt-6 flex items-center gap-3">
                        <span class="h-px flex-1 bg-gradient-to-l from-transparent to-gold/50"></span>
                        <flux:icon icon="sparkles" class="size-3.5 text-gold" />
                        <span class="h-px flex-1 bg-gradient-to-r from-transparent to-gold/50"></span>
                    </div>

                    <p class="relative mt-5 font-zain text-lg md:text-xl leading-[1.9] text-maroon text-balance">
                        «{{ $hadith['text'] }}»
                    </p>

                    <div class="relative mt-4 flex flex-wrap items-center justify-center gap-x-2 gap-y-1">
                        <span class="text-[11px] font-medium text-maroon/60">{{ $hadith['source'] }}</span>
                        <span class="inline-flex items-center rounded-full bg-maroon/8 px-2 py-0.5 text-[10px] font-bold text-maroon/70">
                            {{ $hadith['grade'] }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================== الأسئلة الشائعة ============================== --}}
    <section id="faq" class="bg-[#fdfaf5] dark:bg-accent-dark px-5 py-16 md:py-24">
        <div class="max-w-2xl mx-auto">
            <div class="animate-fade-in-up text-center">
                <span class="text-[11px] md:text-xs font-bold tracking-[0.2em] text-gold">الأسئلة الشائعة</span>
                <h2 class="mt-3 text-2xl md:text-3xl font-extrabold text-maroon dark:text-white">إجابات لما قد يشكل عليك</h2>
            </div>

            <div class="animate-fade-in-up delay-100 mt-10 space-y-3">
                @foreach ([
                    [
                        'q' => 'كيف أُنشئ حساباً جديداً؟',
                        'a' => 'من زر «إنشاء حساب جديد» في صفحة الدخول: تُدخل بياناتك، فيُحال طلبك مباشرة إلى إدارة '.config('brand.entity').' للمراجعة والموافقة.',
                    ],
                    [
                        'q' => 'كم يستغرق قبول طلب التسجيل؟',
                        'a' => 'تُراجع إدارة '.config('brand.entity').' الطلبات أولاً بأول، وتصلك رسالة فور قبول طلبك أو رفضه.',
                    ],
                    [
                        'q' => 'نسيت كلمة المرور، فماذا أفعل؟',
                        'a' => 'اضغط «نسيت كلمة المرور؟» في صفحة تسجيل الدخول، لتصلك رسالة تستعيد بها الوصول إلى حسابك.',
                    ],
                    [
                        'q' => 'أحتاج مساعدة في أمر آخر.',
                        'a' => 'يسعدنا تواصلك معنا عبر بيانات التواصل الموضّحة أدناه.',
                    ],
                ] as $item)
                    <details class="group rounded-2xl border border-maroon/10 dark:border-white/10 bg-white dark:bg-white/[0.04] px-5 py-4 transition-colors open:border-gold/40 hover:border-gold/30">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-sm md:text-base font-bold text-zinc-800 dark:text-white">
                            {{ $item['q'] }}
                            <span class="grid size-7 shrink-0 place-items-center rounded-full bg-gold/10 text-gold transition-transform group-open:rotate-180">
                                <flux:icon icon="chevron-down" class="size-4" />
                            </span>
                        </summary>
                        <p class="mt-3 text-xs md:text-sm leading-loose text-neutral-grey dark:text-white/60">{{ $item['a'] }}</p>
                    </details>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================== التواصل ============================== --}}
    <section id="contact" class="bg-white dark:bg-black/20 px-5 py-16 md:py-24 border-y border-maroon/10 dark:border-white/10">
        <div class="max-w-4xl mx-auto">
            <div class="animate-fade-in-up text-center">
                <span class="text-[11px] md:text-xs font-bold tracking-[0.2em] text-gold">تواصل معنا</span>
                <h2 class="mt-3 text-2xl md:text-3xl font-extrabold text-maroon dark:text-white">نحن في خدمتك</h2>
            </div>

            <div class="animate-fade-in-up delay-100 mt-12 grid grid-cols-1 sm:grid-cols-3 gap-5">
                @foreach ([
                    ['icon' => 'share', 'title' => 'حسابات التواصل', 'value' => config('brand.contact.social'), 'latin' => false],
                    ['icon' => 'phone', 'title' => 'الاتصال المباشر', 'value' => config('brand.contact.phone'), 'latin' => true],
                    ['icon' => 'map-pin', 'title' => 'الموقع', 'value' => config('brand.contact.location'), 'latin' => false],
                ] as $card)
                    @if (filled($card['value']))
                        <div class="flex flex-col items-center gap-3 rounded-2xl border border-maroon/10 dark:border-white/10 bg-[#fdfaf5] dark:bg-white/[0.04] p-6 text-center transition-all hover:border-gold/40 hover:-translate-y-1">
                            <div class="grid size-12 place-items-center rounded-2xl bg-gold/10 text-gold ring-1 ring-gold/20">
                                <flux:icon :icon="$card['icon']" class="size-5" />
                            </div>
                            <h3 class="text-sm font-extrabold text-maroon dark:text-white">{{ $card['title'] }}</h3>
                            <p @class([
                                'text-xs font-medium leading-relaxed text-neutral-grey dark:text-white/60',
                                'font-sans' => $card['latin'],
                            ])>{{ $card['value'] }}</p>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================== التذييل ============================== --}}
    <footer class="relative overflow-hidden bg-accent-dark px-5 py-12">
        <x-brand-ornament class="text-gold opacity-[0.05]" />

        <div class="relative max-w-4xl mx-auto flex flex-col items-center gap-5 text-center">
            <span class="grid size-20 place-items-center rounded-3xl bg-[#fdfaf3] ring-1 ring-gold/30">
                <img src="{{ asset(config('brand.logo')) }}" alt="{{ config('brand.name') }}"
                     class="h-14 w-auto object-contain" />
            </span>

            <div>
                <p class="text-sm font-extrabold text-white">{{ config('brand.name') }}</p>
                <p class="mt-1 text-[11px] text-white/50">{{ config('brand.tagline') }}</p>
            </div>

            @if (filled(config('brand.license')))
                <p class="text-[11px] font-medium text-gold/80">
                    رقم الترخيص: <span class="font-sans">{{ config('brand.license') }}</span>
                </p>
            @endif

            <span class="h-px w-24 bg-white/10"></span>

            <p class="text-[11px] text-white/45">
                &copy; <span class="font-sans">{{ date('Y') }}</span> {{ config('brand.name') }}. جميع الحقوق محفوظة.
            </p>
        </div>
    </footer>
</body>

</html>
