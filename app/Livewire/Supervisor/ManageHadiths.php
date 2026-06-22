<?php

namespace App\Livewire\Supervisor;

use App\Models\Hadith;
use App\Models\HadithChapter;
use App\Models\HadithLine;
use Flux\Flux;
use Livewire\Component;

class ManageHadiths extends Component
{
    public string $search = '';

    // Hadith Form Fields
    public ?int $editingHadithId = null;

    public ?int $hadithChapterId = null;

    public string $newChapterName = '';

    public string $name = '';

    public string $sanad = '';

    public string $ruling = '';

    // Bulk creation text for lines
    public string $linesText = '';

    // Lines Management Fields
    public ?int $selectedHadithId = null;

    // New Line Fields
    public ?int $newLineNumber = null;

    public string $newLineText = '';

    // Editing Line Fields
    public ?int $editingLineId = null;

    public ?int $editingLineNumber = null;

    public string $editingLineText = '';

    // Bulk Import Field for existing Hadith
    public string $bulkText = '';

    public bool $showBulkImport = false;

    // Filter by Chapter
    public ?int $filterChapterId = null;

    public function selectHadith(?int $id): void
    {
        $this->selectedHadithId = $id;
        $this->resetLineForm();
        if ($id) {
            $this->autoFillNextLineNumber();
        }
    }

    public function createHadith(): void
    {
        $this->reset(['editingHadithId', 'hadithChapterId', 'newChapterName', 'name', 'sanad', 'ruling', 'linesText']);
        Flux::modal('hadith-modal')->show();
    }

    public function editHadith(int $id): void
    {
        $hadith = Hadith::findOrFail($id);
        $this->editingHadithId = $hadith->id;
        $this->hadithChapterId = $hadith->hadith_chapter_id;
        $this->newChapterName = '';
        $this->name = $hadith->name;
        $this->sanad = $hadith->sanad ?? '';
        $this->ruling = $hadith->ruling ?? '';
        $this->linesText = '';
        Flux::modal('hadith-modal')->show();
    }

    public function saveHadith(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'hadithChapterId' => 'nullable|exists:hadith_chapters,id',
            'newChapterName' => 'nullable|string|max:255',
            'sanad' => 'nullable|string',
            'ruling' => 'nullable|string|max:255',
            'linesText' => 'nullable|string',
        ]);

        $chapterId = $this->hadithChapterId;

        // Create new chapter inline if specified
        if (! empty(trim($this->newChapterName))) {
            $chapter = HadithChapter::firstOrCreate([
                'name' => trim($this->newChapterName),
            ]);
            $chapterId = $chapter->id;
        }

        if ($this->editingHadithId) {
            $hadith = Hadith::findOrFail($this->editingHadithId);
            $hadith->update([
                'name' => $this->name,
                'hadith_chapter_id' => $chapterId,
                'sanad' => $this->sanad ?: null,
                'ruling' => $this->ruling ?: null,
            ]);
            Flux::toast('تم تعديل الحديث بنجاح', variant: 'success');
        } else {
            $hadith = Hadith::create([
                'name' => $this->name,
                'hadith_chapter_id' => $chapterId,
                'sanad' => $this->sanad ?: null,
                'ruling' => $this->ruling ?: null,
            ]);

            // Bulk import lines if provided during creation
            if (! empty($this->linesText)) {
                $lines = explode("\n", $this->linesText);
                $nextNum = 0;
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }

                    $nextNum++;
                    HadithLine::create([
                        'hadith_id' => $hadith->id,
                        'line_number' => $nextNum,
                        'text' => $line,
                    ]);
                }
            }

            Flux::toast('تم إنشاء الحديث بنجاح', variant: 'success');
            $this->selectedHadithId = $hadith->id; // Auto select for managing lines
            $this->autoFillNextLineNumber();
        }

        Flux::modal('hadith-modal')->close();
        $this->reset(['editingHadithId', 'hadithChapterId', 'newChapterName', 'name', 'sanad', 'ruling', 'linesText']);
    }

    public function deleteHadith(int $id): void
    {
        $hadith = Hadith::findOrFail($id);
        $hadith->delete();

        if ($this->selectedHadithId === $id) {
            $this->selectedHadithId = null;
            $this->resetLineForm();
        }

        Flux::toast('تم حذف الحديث بنجاح', variant: 'success');
    }

    // --- Line Management Functions ---

    public function autoFillNextLineNumber(): void
    {
        if (! $this->selectedHadithId) {
            return;
        }

        $max = HadithLine::where('hadith_id', $this->selectedHadithId)->max('line_number');
        $this->newLineNumber = $max ? $max + 1 : 1;
    }

    public function saveLine(): void
    {
        $this->validate([
            'newLineNumber' => 'required|integer|min:1',
            'newLineText' => 'required|string|max:2000',
        ]);

        HadithLine::create([
            'hadith_id' => $this->selectedHadithId,
            'line_number' => $this->newLineNumber,
            'text' => $this->newLineText,
        ]);

        Flux::toast('تم إضافة السطر بنجاح', variant: 'success');
        $this->reset(['newLineText']);
        $this->autoFillNextLineNumber();
    }

    public function startEditLine(int $id): void
    {
        $line = HadithLine::findOrFail($id);
        $this->editingLineId = $line->id;
        $this->editingLineNumber = $line->line_number;
        $this->editingLineText = $line->text;
    }

    public function saveEditingLine(): void
    {
        $this->validate([
            'editingLineNumber' => 'required|integer|min:1',
            'editingLineText' => 'required|string|max:2000',
        ]);

        $line = HadithLine::findOrFail($this->editingLineId);

        $line->update([
            'line_number' => $this->editingLineNumber,
            'text' => $this->editingLineText,
        ]);

        Flux::toast('تم تعديل السطر بنجاح', variant: 'success');
        $this->reset(['editingLineId', 'editingLineNumber', 'editingLineText']);
    }

    public function cancelEditLine(): void
    {
        $this->reset(['editingLineId', 'editingLineNumber', 'editingLineText']);
    }

    public function deleteLine(int $id): void
    {
        $line = HadithLine::findOrFail($id);
        $line->delete();
        Flux::toast('تم حذف السطر بنجاح', variant: 'success');
        $this->autoFillNextLineNumber();
    }

    public function resetLineForm(): void
    {
        $this->reset([
            'newLineNumber', 'newLineText',
            'editingLineId', 'editingLineNumber', 'editingLineText',
            'bulkText', 'showBulkImport',
        ]);
    }

    // --- Bulk Import of Lines ---

    public function openBulkImport(): void
    {
        $this->bulkText = '';
        $this->showBulkImport = true;
    }

    public function closeBulkImport(): void
    {
        $this->showBulkImport = false;
        $this->bulkText = '';
    }

    public function importBulkLines(): void
    {
        $this->validate([
            'bulkText' => 'required|string',
        ]);

        $lines = explode("\n", $this->bulkText);
        $importedCount = 0;
        $nextNum = HadithLine::where('hadith_id', $this->selectedHadithId)->max('line_number') ?: 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $nextNum++;
            HadithLine::create([
                'hadith_id' => $this->selectedHadithId,
                'line_number' => $nextNum,
                'text' => $line,
            ]);
            $importedCount++;
        }

        if ($importedCount > 0) {
            Flux::toast("تم استيراد {$importedCount} سطراً بنجاح", variant: 'success');
            $this->showBulkImport = false;
            $this->bulkText = '';
            $this->autoFillNextLineNumber();
        } else {
            $this->addError('bulkText', 'يرجى إدخال أسطر نصية غير فارغة للاستيراد.');
        }
    }

    public function render()
    {
        $query = Hadith::query()->with('chapter');
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('ruling', 'like', '%'.$this->search.'%')
                    ->orWhereHas('chapter', function ($sq) {
                        $sq->where('name', 'like', '%'.$this->search.'%');
                    });
            });
        }

        if ($this->filterChapterId) {
            $query->where('hadith_chapter_id', $this->filterChapterId);
        }

        $hadithsList = $query->latest()->get();
        $selectedHadith = $this->selectedHadithId ? Hadith::with(['lines', 'chapter'])->find($this->selectedHadithId) : null;
        $chapters = HadithChapter::orderBy('name')->get();

        return view('livewire.supervisor.manage-hadiths', [
            'hadithsList' => $hadithsList,
            'selectedHadith' => $selectedHadith,
            'chapters' => $chapters,
        ])->layout('layouts.role-shell');
    }
}
