<?php

use App\Support\Access;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Everyone in the academy, by the office they hold.
 *
 * The managers were missing from here, and they were the one office nobody
 * could create: supervisors, teachers, guardians and students all had their tab
 * and their «new» button, so an administrator looking for where to make a
 * manager looked here, found four tabs, and reasonably concluded it could not
 * be done. It was put in the sidebar on its own instead, which is not where
 * anybody was looking.
 */
new class extends Component
{
    public string $activeTab = 'students';

    protected const TABS = ['students', 'teachers', 'supervisors', 'guardians', 'managers'];

    public function mount(string $initialTab = 'students'): void
    {
        $this->activeTab = $this->allows($initialTab) ? $initialTab : 'students';
    }

    public function setTab(string $tab): void
    {
        if ($this->allows($tab)) {
            $this->activeTab = $tab;
        }
    }

    /**
     * Whether this reader may stand on a tab.
     *
     * The managers' tab is the centre's own — a manager over one programme does
     * not make managers — so it is asked about rather than assumed, and the
     * same answer draws the tab and guards the standing on it.
     */
    public function allows(string $tab): bool
    {
        if (! in_array($tab, self::TABS, true)) {
            return false;
        }

        return $tab !== 'managers'
            || Access::canSee(Auth::guard('manager')->user(), 'manager', 'manager.managers');
    }

    protected const TAB_LABELS = [
        'students' => 'الطلاب',
        'teachers' => 'المعلمون',
        'supervisors' => 'المشرفون',
        'guardians' => 'الأوصياء',
        'managers' => 'المديرون',
    ];

    public function with(): array
    {
        return [
            'tabLabels' => array_filter(
                self::TAB_LABELS,
                fn (string $tab) => $this->allows($tab),
                ARRAY_FILTER_USE_KEY,
            ),
        ];
    }
};
?>

<div class="space-y-6" dir="rtl">
    <div class="flex items-center gap-2 border-b border-zinc-100 dark:border-zinc-800 overflow-x-auto">
        @foreach($tabLabels as $tab => $label)
            <button
                wire:click="setTab('{{ $tab }}')"
                wire:key="user-directory-tab-{{ $tab }}"
                class="px-4 py-2.5 text-sm font-bold whitespace-nowrap border-b-2 transition-colors
                    {{ $activeTab === $tab ? 'border-maroon text-maroon dark:text-red-secondary' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if($activeTab === 'students')
        <livewire:manager.students :key="'user-directory-students'" />
    @elseif($activeTab === 'teachers')
        <livewire:manager.teachers :key="'user-directory-teachers'" />
    @elseif($activeTab === 'supervisors')
        <livewire:manager.supervisors :key="'user-directory-supervisors'" />
    @elseif($activeTab === 'guardians')
        <livewire:manager.guardians :key="'user-directory-guardians'" />
    @elseif($activeTab === 'managers')
        <livewire:manager.managers :key="'user-directory-managers'" />
    @endif
</div>
