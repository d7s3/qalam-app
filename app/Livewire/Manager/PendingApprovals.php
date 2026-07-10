<?php

namespace App\Livewire\Manager;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Services\NotificationService;
use Flux\Flux;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('طلبات التسجيل')]
#[Layout('layouts.app')]
class PendingApprovals extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = 'all';

    /** Selected target role per row (keyed "type-id"), used only for pending student requests. */
    public array $reassignType = [];

    /** @var array<string, class-string> */
    protected const MODELS = [
        'student' => Student::class,
        'teacher' => Teacher::class,
        'supervisor' => Supervisor::class,
        'guardian' => Guardian::class,
    ];

    public const ROLE_LABELS = [
        'student' => 'طالب',
        'teacher' => 'معلم',
        'supervisor' => 'مشرف',
        'guardian' => 'ولي أمر',
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    /**
     * A single UNION query across all four approval tables, so the request queue
     * can be searched/filtered/paginated as one list instead of per-role tabs.
     */
    protected function baseQuery(): Builder
    {
        $queries = [];

        foreach (self::MODELS as $type => $modelClass) {
            $table = (new $modelClass)->getTable();

            $query = DB::table($table)
                ->select([
                    'id',
                    'name',
                    'email',
                    DB::raw("COALESCE(phone, '') as phone"),
                    'is_approved',
                    'is_rejected',
                    'created_at',
                    DB::raw("'{$type}' as account_type"),
                ]);

            if ($this->search !== '') {
                $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%"));
            }

            match ($this->statusFilter) {
                'pending' => $query->where('is_approved', false)->where('is_rejected', false),
                'approved' => $query->where('is_approved', true),
                'rejected' => $query->where('is_rejected', true),
                default => null,
            };

            $queries[] = $query;
        }

        $first = array_shift($queries);
        foreach ($queries as $query) {
            $first->unionAll($query);
        }

        return $first;
    }

    #[Computed]
    public function pendingRows()
    {
        return $this->baseQuery()->orderByDesc('created_at')->paginate(15);
    }

    #[Computed]
    public function counts(): array
    {
        $totals = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total' => 0];

        foreach (self::MODELS as $modelClass) {
            $totals['pending'] += $modelClass::where('is_approved', false)->where('is_rejected', false)->count();
            $totals['approved'] += $modelClass::where('is_approved', true)->count();
            $totals['rejected'] += $modelClass::where('is_rejected', true)->count();
            $totals['total'] += $modelClass::count();
        }

        return $totals;
    }

    /**
     * Approve a request. When $newType differs from the request's current type
     * (only meaningful for self-registered "student" requests), the record is
     * moved into the target role's table rather than just flipping a flag —
     * each role lives in its own table/guard in this app, so "changing the type"
     * means creating the row in the right place and dropping the temporary one.
     */
    public function approve(int $id, string $type): void
    {
        $newType = $this->reassignType["{$type}-{$id}"] ?? $type;

        if (! isset(self::MODELS[$type]) || ! isset(self::MODELS[$newType])) {
            return;
        }

        $sourceModel = self::MODELS[$type];
        $record = $sourceModel::findOrFail($id);

        if ($newType === $type) {
            $record->update([
                'is_approved' => true,
                'is_rejected' => false,
                'approved_by' => Auth::id(),
            ]);

            if ($type === 'student' && $record->circle_id) {
                NotificationService::notifyCircleTeachers(
                    $record->circle_id,
                    'new_student',
                    'طالب جديد',
                    "انضم الطالب {$record->name} إلى حلقتك",
                    route('teacher.students'),
                );
            }

            Flux::toast(__('تمت الموافقة على الطلب بنجاح'), variant: 'success');

            return;
        }

        // Cross-table role reassignment — only supported from a self-registered
        // "student" request, since that's the only public entry point.
        if ($type !== 'student') {
            Flux::toast(__('لا يمكن تغيير نوع حساب غير ناتج عن تسجيل ذاتي'), variant: 'danger');

            return;
        }

        $targetModel = self::MODELS[$newType];

        DB::transaction(function () use ($record, $targetModel, $newType) {
            $targetModel::create([
                'name' => $record->name,
                'email' => $record->email,
                'phone' => $record->phone,
                'password' => $record->getRawOriginal('password'),
                'is_approved' => true,
                'approved_by' => Auth::id(),
            ]);

            $record->delete();

            Flux::toast(
                __('تم تحويل الحساب إلى :role بنجاح. يقدر المستخدم يسجّل دخول من جديد بنفس بريده وكلمة مروره.', ['role' => self::ROLE_LABELS[$newType]]),
                variant: 'success'
            );
        });

        unset($this->pendingRows, $this->counts);
    }

    public function reject(int $id, string $type): void
    {
        if (! isset(self::MODELS[$type])) {
            return;
        }

        $modelClass = self::MODELS[$type];
        $modelClass::findOrFail($id)->update([
            'is_rejected' => true,
            'rejected_at' => now(),
            'rejected_by' => Auth::id(),
            'is_approved' => false,
        ]);

        Flux::toast(__('تم رفض الطلب'), variant: 'success');
    }

    public function render(): View
    {
        return view('livewire.manager.pending-approvals');
    }
}
