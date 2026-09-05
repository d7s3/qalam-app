<?php

namespace App\Livewire\Manager;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Models\UserRole;
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

    #[Url]
    public string $roleFilter = 'all';

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

    public function updatingRoleFilter(): void
    {
        $this->resetPage();
    }

    /**
     * One row per (user, role) via `user_roles`, joined back to `users` — now
     * that all four directory roles share one table, this replaces what used
     * to be a UNION across four separate per-role tables.
     */
    protected function baseQuery(): Builder
    {
        $roles = $this->roleFilter !== 'all' ? [$this->roleFilter] : array_keys(self::MODELS);

        $query = DB::table('user_roles')
            ->join('users', 'users.id', '=', 'user_roles.user_id')
            ->whereIn('user_roles.role', $roles)
            ->select([
                'users.id',
                'users.name',
                'users.email',
                DB::raw("COALESCE(users.phone, '') as phone"),
                'user_roles.is_approved',
                'user_roles.is_rejected',
                'user_roles.created_at',
                'user_roles.role as account_type',
            ]);

        if ($this->search !== '') {
            $query->where(fn ($q) => $q
                ->where('users.name', 'like', "%{$this->search}%")
                ->orWhere('users.email', 'like', "%{$this->search}%"));
        }

        match ($this->statusFilter) {
            'pending' => $query->where('user_roles.is_approved', false)->where('user_roles.is_rejected', false),
            'approved' => $query->where('user_roles.is_approved', true),
            'rejected' => $query->where('user_roles.is_rejected', true),
            default => null,
        };

        return $query;
    }

    #[Computed]
    public function pendingRows()
    {
        return $this->baseQuery()->orderByDesc('user_roles.created_at')->paginate(15);
    }

    #[Computed]
    public function counts(): array
    {
        $rows = DB::table('user_roles')
            ->whereIn('role', array_keys(self::MODELS))
            ->selectRaw('is_approved, is_rejected, count(*) as cnt')
            ->groupBy('is_approved', 'is_rejected')
            ->get();

        $totals = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'total' => 0];

        foreach ($rows as $row) {
            $totals['total'] += $row->cnt;

            if ($row->is_approved) {
                $totals['approved'] += $row->cnt;
            } elseif ($row->is_rejected) {
                $totals['rejected'] += $row->cnt;
            } else {
                $totals['pending'] += $row->cnt;
            }
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
                    "انضم الطالب {$record->name} إلى دفعتك",
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

        // Same underlying `users` row throughout — "changing the type" is just
        // swapping which `user_roles` entry it holds, not creating a new account.
        DB::transaction(function () use ($record, $newType) {
            UserRole::updateOrCreate(
                ['user_id' => $record->id, 'role' => $newType],
                ['is_approved' => true, 'approved_by' => Auth::id()]
            );

            UserRole::where('user_id', $record->id)->where('role', 'student')->delete();

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
