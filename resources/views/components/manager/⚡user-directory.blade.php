<?php

use Livewire\Component;

new class extends Component
{
    public string $activeTab = 'students';

    protected const TABS = ['students', 'teachers', 'supervisors', 'guardians'];

    public function mount(string $initialTab = 'students'): void
    {
        $this->activeTab = in_array($initialTab, self::TABS, true) ? $initialTab : 'students';
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, self::TABS, true)) {
            $this->activeTab = $tab;
        }
    }

    protected const TAB_LABELS = [
        'students' => 'الطلاب',
        'teachers' => 'المعلمون',
        'supervisors' => 'المشرفون',
        'guardians' => 'الأوصياء',
    ];

    public function with(): array
    {
        return [
            'tabLabels' => self::TAB_LABELS,
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
    @endif
</div>
