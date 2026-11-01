@php
    $route = request()->route()?->getName();
    $steps = config('app.page_tours') ? \App\Support\PageTours::for($route) : [];
@endphp

{{-- The first time a page is opened, walk through it step by step (App\Support\PageTours).
     Whether a page has been walked is kept in this browser only. --}}
@if (count($steps))
    @once
        <script>
            window.pageTour = (route, steps) => ({
                open: false,
                index: 0,
                shown: [],
                box: null,
                placeAbove: false,
                key: `qalam.tour.${route}`,

                init() {
                    try {
                        if (localStorage.getItem(this.key)) return;
                    } catch (e) {
                        return;
                    }
                    // Wait for the verse or word that greets a person on opening to be closed.
                    const start = () => document.querySelector('[data-opening]') ? setTimeout(start, 500) : this.start();
                    setTimeout(start, 700);
                },

                start() {
                    this.shown = steps.map((step) => ({ ...step, node: this.target(step) }))
                        .filter((step) => step.node !== undefined);
                    if (this.shown.length === 0) return;
                    this.open = true;
                    this.show(0);
                },

                /** The element a step points at (by `el`, narrowed by `find` when both are given); null for a step said mid-screen, undefined when it is not on the page. */
                target(step) {
                    const visible = (node) => {
                        const r = node.getBoundingClientRect();
                        return r.width > 0 && r.height > 0 && r.right > 0 && r.left < window.innerWidth;
                    };
                    if (step.el) {
                        return [...document.querySelectorAll(step.el)]
                            .find((node) => !node.closest('[data-page-tour]') && visible(node) && (!step.find || node.textContent.includes(step.find)));
                    }
                    if (step.find) {
                        const main = document.querySelector('[data-flux-main]') ?? document.body;
                        const walker = document.createTreeWalker(main, NodeFilter.SHOW_TEXT);
                        while (walker.nextNode()) {
                            const holder = walker.currentNode.parentElement;
                            if (walker.currentNode.nodeValue.includes(step.find) && visible(holder)) {
                                // A card taller than most of the screen could not be seen whole; point at its words instead.
                                const card = holder.closest('[data-flux-card], section, .rounded-3xl, .rounded-2xl, .rounded-xl');
                                return card && card.getBoundingClientRect().height < window.innerHeight * 0.6 ? card : holder;
                            }
                        }
                        return undefined;
                    }
                    return null;
                },

                show(index) {
                    this.index = index;
                    const node = this.shown[index].node;
                    node?.scrollIntoView({ block: 'center', behavior: 'smooth' });
                    this.box = null;
                    setTimeout(() => this.point(), node ? 350 : 0);
                },

                point() {
                    const node = this.shown[this.index]?.node;
                    if (!node) {
                        this.box = null;
                        this.placeAbove = false;
                        return;
                    }
                    const r = node.getBoundingClientRect();
                    const pad = 6;
                    this.box = { top: r.top - pad, left: r.left - pad, width: r.width + pad * 2, height: r.height + pad * 2 };
                    this.placeAbove = r.top + r.height / 2 > window.innerHeight / 2;
                },

                next() {
                    this.index + 1 < this.shown.length ? this.show(this.index + 1) : this.finish();
                },

                back() {
                    this.show(this.index - 1);
                },

                finish() {
                    this.open = false;
                    try {
                        localStorage.setItem(this.key, '1');
                    } catch (e) {}
                },
            });
        </script>
    @endonce

    <div x-data="pageTour(@js($route), @js($steps))" x-on:scroll.window.passive="open && point()" x-on:resize.window="open && point()" data-page-tour>
        <template x-if="open">
            <div class="fixed inset-0 z-overlay" role="dialog" aria-modal="true" aria-labelledby="page-tour-text">
                <div x-show="!box" class="absolute inset-0 bg-black/55"></div>
                <div x-show="box" class="absolute rounded-xl ring-4 ring-amber-400 transition-all duration-300 shadow-[0_0_0_9999px_rgba(0,0,0,0.55)]"
                    :style="box && `top:${box.top}px;left:${box.left}px;width:${box.width}px;height:${box.height}px`"></div>

                <div class="absolute inset-x-3 mx-auto max-w-md rounded-2xl bg-white p-4 shadow-xl text-zinc-900"
                    :class="placeAbove ? 'top-[max(0.75rem,env(safe-area-inset-top))]' : 'bottom-[max(0.75rem,env(safe-area-inset-bottom))]'">
                    <p class="text-xs text-zinc-500" x-text="`${index + 1} من ${shown.length}`"></p>
                    <p id="page-tour-text" class="mt-1 text-base leading-7" x-text="shown[index]?.text"></p>
                    <div class="mt-3 flex items-center gap-2">
                        <flux:button size="sm" variant="primary" x-on:click="next()" data-tour-next><span x-text="index + 1 < shown.length ? 'التالي' : 'تم'"></span></flux:button>
                        <flux:button size="sm" variant="ghost" x-show="index > 0" x-on:click="back()">{{ __('السابق') }}</flux:button>
                        <flux:spacer />
                        <flux:button size="sm" variant="ghost" x-show="index + 1 < shown.length" x-on:click="finish()">{{ __('تخطَّ الجولة') }}</flux:button>
                    </div>
                </div>
            </div>
        </template>
    </div>

@endif
