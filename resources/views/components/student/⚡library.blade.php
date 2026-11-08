<?php

use App\Models\BookQuote;
use App\Models\ReadingSession;
use App\Models\StudentBook;
use App\Services\ReadingService;
use App\Support\HijriDate;
use App\Support\SelfProgramUnit;
use Carbon\Carbon;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * The student's shelves, the focus timer, his streak and his cohort's week.
 * Pages he moves forward here are counted in المقروء as well.
 */
new class extends Component
{
    public string $shelf = StudentBook::READING;

    /** The book being added. */
    public string $title = '';

    public string $author = '';

    public string $totalPages = '';

    public string $newShelf = StudentBook::READING;

    /** The book opened for its quotes and review. */
    public ?int $openBookId = null;

    /** Page reached, per book, as typed. */
    public array $reached = [];

    public string $quote = '';

    public string $quotePage = '';

    /** The focus session running, if one is. */
    public ?int $sessionId = null;

    public string $sessionPage = '';

    public function mount(): void
    {
        $this->sessionId = ReadingSession::whereNull('ended_at')
            ->where('student_id', $this->student()->id)
            ->latest('id')
            ->value('id');
    }

    private function student()
    {
        return Auth::guard('student')->user();
    }

    private function book(int $id): StudentBook
    {
        return StudentBook::where('student_id', $this->student()->id)->find($id) ?? abort(404);
    }

    #[Computed]
    public function books()
    {
        return StudentBook::where('student_id', $this->student()->id)
            ->where('shelf', $this->shelf)
            ->latest('updated_at')
            ->get();
    }

    #[Computed]
    public function runningSession(): ?ReadingSession
    {
        return $this->sessionId
            ? ReadingSession::with('book')->where('student_id', $this->student()->id)->find($this->sessionId)
            : null;
    }

    public function addBook(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'totalPages' => ['nullable', 'string', 'max:10'],
            'newShelf' => ['required', 'in:'.implode(',', array_keys(StudentBook::SHELVES))],
        ], [], ['title' => 'عنوان الكتاب', 'totalPages' => 'عدد الصفحات']);

        $pages = SelfProgramUnit::parse($this->totalPages);

        StudentBook::create([
            'student_id' => $this->student()->id,
            'title' => trim($this->title),
            'author' => trim($this->author) ?: null,
            'total_pages' => $pages ? (int) min($pages, 65000) : null,
            'shelf' => $this->newShelf,
            'started_on' => $this->newShelf === StudentBook::READING ? today() : null,
        ]);

        $this->shelf = $this->newShelf;
        $this->reset('title', 'author', 'totalPages');
        unset($this->books);

        Flux::toast(__('أُضيف الكتاب إلى رفّك.'), variant: 'success');
    }

    public function logPage(int $bookId): void
    {
        $page = SelfProgramUnit::parse($this->reached[$bookId] ?? '');

        if ($page === null) {
            $this->addError("reached.{$bookId}", __('اكتب رقم الصفحة التي وصلت إليها.'));

            return;
        }

        $book = $this->book($bookId);
        $session = app(ReadingService::class)->advance($book, (int) $page);

        unset($this->reached[$bookId], $this->books);

        Flux::toast($session->pages > 0
            ? __('سُجّلت :pages صفحة، وأُضيفت إلى المقروء.', ['pages' => $session->pages])
            : __('حُفظ موضعك في الكتاب.'), variant: 'success');
    }

    public function startSession(int $bookId): void
    {
        $this->sessionId = app(ReadingService::class)->start($this->book($bookId))->id;
        $this->sessionPage = '';
        unset($this->runningSession);
    }

    public function finishSession(): void
    {
        $session = $this->runningSession;

        if (! $session) {
            $this->sessionId = null;

            return;
        }

        $page = SelfProgramUnit::parse($this->sessionPage);

        if ($page === null) {
            $this->addError('sessionPage', __('اكتب الصفحة التي وصلت إليها.'));

            return;
        }

        $done = app(ReadingService::class)->finish($session, (int) $page);

        $this->sessionId = null;
        unset($this->runningSession, $this->books);

        Flux::toast(__('أحسنت! قرأت :minutes دقيقة و:pages صفحة.', ['minutes' => $done->minutes, 'pages' => $done->pages]), variant: 'success');
    }

    public function cancelSession(): void
    {
        $this->runningSession?->delete();
        $this->sessionId = null;
        unset($this->runningSession);
    }

    public function moveTo(int $bookId, string $shelf): void
    {
        abort_unless(isset(StudentBook::SHELVES[$shelf]), 422);

        $book = $this->book($bookId);
        $book->update([
            'shelf' => $shelf,
            'started_on' => $book->started_on ?? ($shelf === StudentBook::READING ? today() : null),
            'finished_on' => $shelf === StudentBook::READ ? ($book->finished_on ?? today()) : $book->finished_on,
        ]);

        unset($this->books);
    }

    public function rate(int $bookId, int $stars): void
    {
        $this->book($bookId)->update(['rating' => max(1, min(5, $stars))]);
        unset($this->books);
    }

    public function saveReview(int $bookId, string $review): void
    {
        $this->book($bookId)->update(['review' => mb_substr(trim($review), 0, 2000) ?: null]);

        Flux::toast(__('حُفظ رأيك في الكتاب.'), variant: 'success');
    }

    public function addQuote(int $bookId): void
    {
        $this->validate(['quote' => ['required', 'string', 'max:2000']], [], ['quote' => 'الاقتباس']);

        $page = SelfProgramUnit::parse($this->quotePage);

        $this->book($bookId)->quotes()->create([
            'body' => trim($this->quote),
            'page' => $page ? (int) $page : null,
        ]);

        $this->reset('quote', 'quotePage');
    }

    public function deleteQuote(int $quoteId): void
    {
        BookQuote::whereKey($quoteId)
            ->whereHas('book', fn ($q) => $q->where('student_id', $this->student()->id))
            ->delete();
    }

    public function deleteBook(int $bookId): void
    {
        $this->book($bookId)->delete();
        $this->openBookId = null;
        unset($this->books);
    }

    public function with(): array
    {
        $student = $this->student();
        $reading = app(ReadingService::class);
        $today = Carbon::today();

        return [
            'streak' => $reading->streak($student),
            'plant' => $reading->plant($student),
            'week' => $reading->totals($student, $today->copy()->startOfWeek(Carbon::SUNDAY), $today),
            'month' => $reading->totals($student, $today->copy()->startOfMonth(), $today),
            'year' => $reading->totals($student, $today->copy()->startOfYear(), $today),
            'days' => $reading->lastSevenDays($student),
            'readDays' => $reading->readingDays($student)->flip(),
            'board' => $reading->cohortWeek($student),
            'classmates' => $reading->classmatesReading($student),
            'counts' => StudentBook::where('student_id', $student->id)->selectRaw('shelf, count(*) as n')->groupBy('shelf')->pluck('n', 'shelf'),
            'me' => $student->id,
        ];
    }
};
?>

<div class="space-y-6" dir="rtl">
    <div>
        <flux:heading size="xl" class="font-bold">{{ __('مكتبتي') }}</flux:heading>
        <flux:subheading class="mt-1">{{ __('كتبك وتقدّمك فيها. وكل صفحة تتقدّمها هنا تُحسب في «المقروء» من برنامجك الذاتي.') }}</flux:subheading>
    </div>

    {{-- جلسة القراءة --}}
    @if ($this->runningSession)
        @php $running = $this->runningSession; @endphp
        <div class="fixed inset-0 z-overlay flex items-center justify-center bg-zinc-950/90 p-4" data-focus-session
            x-data="{ started: {{ $running->started_at->getTimestamp() }}, now: Math.floor(Date.now() / 1000) }"
            x-init="setInterval(() => now = Math.floor(Date.now() / 1000), 1000)">
            <div class="w-full max-w-md space-y-6 text-center text-white">
                <div class="text-sm text-white/60">{{ __('جلسة قراءة') }}</div>
                <div class="text-xl font-bold">{{ $running->book?->title }}</div>
                <div class="text-6xl font-black tabular-nums" dir="ltr"
                    x-text="String(Math.floor((now - started) / 60)).padStart(2, '0') + ':' + String((now - started) % 60).padStart(2, '0')"></div>
                <p class="text-sm text-white/60">{{ __('اقرأ، ودع الجوال جانباً. وحين تنتهي اكتب الصفحة التي وصلت إليها.') }}</p>
                <div class="space-y-2 rounded-2xl bg-white p-4 text-start text-zinc-900">
                    <flux:input wire:model="sessionPage" inputmode="numeric" :label="__('وصلت إلى صفحة')"
                        :placeholder="__('كنت في صفحة :page', ['page' => $running->book?->current_page ?? 0])" />
                    <flux:error name="sessionPage" />
                    <div class="flex gap-2">
                        <flux:button variant="primary" class="flex-1" wire:click="finishSession">{{ __('أنهِ الجلسة') }}</flux:button>
                        <flux:button variant="ghost" wire:click="cancelSession">{{ __('ألغِها') }}</flux:button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- السلسلة والنبتة والأرقام --}}
    <div class="grid gap-4 sm:grid-cols-3">
        <flux:card class="space-y-1" data-streak>
            <div class="text-xs text-zinc-500">{{ __('أيام قراءة متتالية') }}</div>
            <div class="text-3xl font-black tabular-nums text-amber-600">{{ $streak['current'] }}</div>
            <div class="text-[11px] text-zinc-400">{{ __('أطول سلسلة') }}: {{ $streak['best'] }}</div>
        </flux:card>
        <flux:card class="space-y-1" data-plant>
            <div class="text-xs text-zinc-500">{{ __('نبتتك') }}</div>
            <div class="flex items-center gap-2">
                <flux:icon icon="sparkles" class="text-emerald-600" style="width: {{ 16 + array_search($plant['stage'], array_values(\App\Services\ReadingService::PLANT)) * 6 }}px" />
                <span class="text-2xl font-black text-emerald-700 dark:text-emerald-400">{{ $plant['stage'] }}</span>
            </div>
            <div class="text-[11px] text-zinc-400">
                {{ __(':count جلسة مركّزة', ['count' => $plant['sessions']]) }}
                @if ($plant['next'])
                    — {{ __('تكبر بعد :left', ['left' => $plant['next'] - $plant['sessions']]) }}
                @endif
            </div>
        </flux:card>
        <flux:card class="space-y-1">
            <div class="text-xs text-zinc-500">{{ __('هذا الأسبوع') }}</div>
            <div class="text-3xl font-black tabular-nums">{{ $week['pages'] }} <span class="text-sm font-normal text-zinc-500">{{ __('صفحة') }}</span></div>
            <div class="text-[11px] text-zinc-400">{{ SelfProgramUnit::say($week['minutes'], SelfProgramUnit::MINUTE) }} {{ __('تركيز') }}</div>
        </flux:card>
    </div>

    {{-- أضف كتاباً --}}
    <flux:card class="space-y-3">
        <flux:heading size="lg">{{ __('أضف كتاباً') }}</flux:heading>
        <div class="grid gap-3 sm:grid-cols-4">
            <flux:input wire:model="title" :placeholder="__('عنوان الكتاب')" class="sm:col-span-2" />
            <flux:input wire:model="author" :placeholder="__('المؤلف (اختياري)')" />
            <flux:input wire:model="totalPages" inputmode="numeric" :placeholder="__('عدد صفحاته')" />
        </div>
        <flux:error name="title" />
        <div class="flex flex-wrap items-center gap-2">
            <flux:select wire:model="newShelf" class="sm:w-48">
                @foreach (StudentBook::SHELVES as $key => $label)
                    <flux:select.option value="{{ $key }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:button variant="primary" icon="plus" wire:click="addBook">{{ __('أضف') }}</flux:button>
        </div>
    </flux:card>

    {{-- الرفوف --}}
    <div class="space-y-3">
        <div class="flex flex-wrap gap-2">
            @foreach (StudentBook::SHELVES as $key => $label)
                <button type="button" wire:click="$set('shelf', '{{ $key }}')" wire:key="shelf-{{ $key }}"
                    @class(['rounded-full border px-3 py-1.5 text-sm font-bold',
                        'border-maroon bg-maroon text-white' => $shelf === $key,
                        'border-zinc-200 text-zinc-600 dark:border-zinc-700 dark:text-zinc-300' => $shelf !== $key])>
                    {{ $label }} <span class="text-xs opacity-70">{{ $counts[$key] ?? 0 }}</span>
                </button>
            @endforeach
        </div>

        @forelse ($this->books as $book)
            <flux:card wire:key="book-{{ $book->id }}" class="space-y-3" data-book>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="font-bold text-zinc-900 dark:text-white">{{ $book->title }}</div>
                        @if ($book->author)
                            <div class="text-xs text-zinc-500">{{ $book->author }}</div>
                        @endif
                    </div>
                    <flux:select size="sm" class="w-36" wire:change="moveTo({{ $book->id }}, $event.target.value)">
                        @foreach (StudentBook::SHELVES as $key => $label)
                            <flux:select.option value="{{ $key }}" :selected="$book->shelf === $key">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                @if ($book->total_pages)
                    <div>
                        <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                            <div class="h-full rounded-full bg-sky-500" style="width: {{ $book->percent() }}%"></div>
                        </div>
                        <div class="mt-1 text-[11px] text-zinc-500 tabular-nums">{{ __('صفحة :at من :of', ['at' => $book->current_page, 'of' => $book->total_pages]) }} — {{ $book->percent() }}%</div>
                    </div>
                @endif

                @if ($book->shelf !== StudentBook::READ)
                    <div class="flex flex-wrap items-end gap-2">
                        <flux:input wire:model="reached.{{ $book->id }}" inputmode="numeric" class="w-40"
                            :placeholder="__('وصلت إلى صفحة')" />
                        <flux:button wire:click="logPage({{ $book->id }})">{{ __('سجّل') }}</flux:button>
                        <flux:button variant="primary" icon="clock" wire:click="startSession({{ $book->id }})">{{ __('جلسة قراءة') }}</flux:button>
                    </div>
                    <flux:error name="reached.{{ $book->id }}" />
                @endif

                <div class="flex items-center gap-3 text-xs">
                    <button type="button" class="text-maroon underline underline-offset-4 dark:text-red-secondary"
                        wire:click="$set('openBookId', {{ $openBookId === $book->id ? 'null' : $book->id }})">
                        {{ $openBookId === $book->id ? __('إخفاء') : __('الاقتباسات والتقييم') }}
                        @if ($book->quotes()->count())
                            ({{ $book->quotes()->count() }})
                        @endif
                    </button>
                </div>

                @if ($openBookId === $book->id)
                    <div class="space-y-4 border-t border-zinc-100 pt-3 dark:border-zinc-800">
                        <div class="flex items-center gap-1" data-rating>
                            <span class="text-xs text-zinc-500 me-2">{{ __('تقييمك') }}</span>
                            @foreach (range(1, 5) as $star)
                                <button type="button" wire:click="rate({{ $book->id }}, {{ $star }})" aria-label="{{ $star }}">
                                    <flux:icon icon="star" :variant="$star <= ($book->rating ?? 0) ? 'solid' : 'outline'" class="size-5 text-amber-500" />
                                </button>
                            @endforeach
                        </div>
                        <div x-data="{ review: @js($book->review ?? '') }" class="space-y-2">
                            <flux:textarea x-model="review" rows="2" :placeholder="__('رأيك في الكتاب باختصار')" />
                            <flux:button size="sm" x-on:click="$wire.saveReview({{ $book->id }}, review)">{{ __('احفظ رأيك') }}</flux:button>
                        </div>

                        <div class="space-y-2">
                            <div class="text-sm font-bold">{{ __('اقتباساتي') }}</div>
                            @foreach ($book->quotes as $saved)
                                <blockquote wire:key="q-{{ $saved->id }}" class="flex items-start justify-between gap-2 rounded-xl bg-zinc-50 p-3 text-sm leading-loose dark:bg-zinc-800/60">
                                    <span>«{{ $saved->body }}»@if ($saved->page) <span class="text-xs text-zinc-400">— {{ __('ص') }} {{ $saved->page }}</span>@endif</span>
                                    <button type="button" wire:click="deleteQuote({{ $saved->id }})" aria-label="{{ __('حذف') }}"><flux:icon icon="x-mark" class="size-4 text-zinc-400" /></button>
                                </blockquote>
                            @endforeach
                            <flux:textarea wire:model="quote" rows="2" :placeholder="__('اكتب اقتباساً أعجبك')" />
                            <flux:error name="quote" />
                            <div class="flex items-center gap-2">
                                <flux:input wire:model="quotePage" inputmode="numeric" class="w-28" :placeholder="__('الصفحة')" />
                                <flux:button size="sm" wire:click="addQuote({{ $book->id }})">{{ __('احفظ الاقتباس') }}</flux:button>
                            </div>
                        </div>

                        <flux:button size="xs" variant="ghost" class="text-red-600" wire:click="deleteBook({{ $book->id }})"
                            wire:confirm="{{ __('تحذف الكتاب من رفوفك؟ ما قرأته منه يبقى محسوباً.') }}">{{ __('احذف الكتاب') }}</flux:button>
                    </div>
                @endif
            </flux:card>
        @empty
            <flux:card class="py-10 text-center text-sm text-zinc-500">{{ __('لا كتب على هذا الرف بعد.') }}</flux:card>
        @endforelse
    </div>

    {{-- الإحصاءات --}}
    <flux:card class="space-y-4" data-stats>
        <flux:heading size="lg">{{ __('قراءتك') }}</flux:heading>
        @php $peak = max(1, max($days)); @endphp
        <div class="flex h-28 items-end gap-2">
            @foreach ($days as $date => $pages)
                <div class="flex flex-1 flex-col items-center gap-1">
                    <span class="text-[10px] tabular-nums text-zinc-500">{{ $pages ?: '' }}</span>
                    <div class="w-full rounded-t bg-sky-500/80" style="height: {{ max(2, round($pages / $peak * 80)) }}px"></div>
                    <span class="text-[10px] text-zinc-400">{{ HijriDate::weekday($date) }}</span>
                </div>
            @endforeach
        </div>
        <div class="grid grid-cols-3 gap-3 text-center text-sm">
            @foreach ([__('هذا الأسبوع') => $week, __('هذا الشهر') => $month, __('هذه السنة') => $year] as $label => $sum)
                <div class="rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/60">
                    <div class="text-xs text-zinc-500">{{ $label }}</div>
                    <div class="font-bold tabular-nums">{{ $sum['pages'] }} {{ __('صفحة') }}</div>
                    <div class="text-[11px] text-zinc-400">{{ __(':n كتب أتممتها', ['n' => $sum['books']]) }}</div>
                </div>
            @endforeach
        </div>
        @php $monthStart = Carbon::today()->startOfMonth(); @endphp
        <div>
            <div class="mb-2 text-xs text-zinc-500">{{ __('أيام قراءتك هذا الشهر') }}</div>
            <div class="flex flex-wrap gap-1.5">
                @foreach (range(0, Carbon::today()->day - 1) as $offset)
                    @php $date = $monthStart->copy()->addDays($offset)->toDateString(); @endphp
                    <span @class(['size-6 rounded-md text-[10px] leading-6 text-center tabular-nums',
                        'bg-emerald-500 text-white' => isset($readDays[$date]),
                        'bg-zinc-100 text-zinc-400 dark:bg-zinc-800' => ! isset($readDays[$date])])>{{ $offset + 1 }}</span>
                @endforeach
            </div>
        </div>
    </flux:card>

    {{-- الدفعة --}}
    @if ($board->isNotEmpty() || $classmates->isNotEmpty())
        <div class="grid gap-6 lg:grid-cols-2">
            <flux:card class="space-y-2" data-board>
                <flux:heading size="lg">{{ __('دفعتك هذا الأسبوع') }}</flux:heading>
                @foreach ($board->take(10) as $place => $row)
                    <div @class(['flex items-center justify-between rounded-lg px-2 py-1.5 text-sm', 'bg-amber-50 font-bold dark:bg-amber-900/20' => $row['student']->id === $me])>
                        <span><span class="inline-block w-6 tabular-nums text-zinc-400">{{ $place + 1 }}</span>{{ $row['student']->name }}</span>
                        <span class="tabular-nums">{{ $row['pages'] }} {{ __('صفحة') }}</span>
                    </div>
                @endforeach
            </flux:card>
            <flux:card class="space-y-2">
                <flux:heading size="lg">{{ __('يقرأ زملاؤك الآن') }}</flux:heading>
                @forelse ($classmates as $theirs)
                    <div class="text-sm">
                        <span class="font-bold">{{ $theirs->student?->name }}</span>:
                        {{ $theirs->title }}
                        @if ($theirs->percent() !== null)
                            <span class="text-xs text-zinc-400">— {{ $theirs->percent() }}%</span>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-zinc-500">{{ __('لم يضف زملاؤك كتباً بعد.') }}</p>
                @endforelse
            </flux:card>
        </div>
    @endif
</div>
