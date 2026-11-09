<?php

namespace App\Models;

use Database\Factories\StudentEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing a student did or ran into: signing in, failing to, opening a page,
 * meeting an error, or reaching a page with nothing on it for him.
 *
 * Kept so trouble shows before he gives up — a student who cannot sign in, who
 * signs in and does nothing, or who keeps meeting the same empty page. Nothing
 * he types is kept, only where he went and what went wrong, and only for
 * ninety days.
 */
class StudentEvent extends Model
{
    /** @use HasFactory<StudentEventFactory> */
    use HasFactory, MassPrunable;

    public const LOGIN = 'login';

    public const LOGIN_FAILED = 'login_failed';

    public const PAGE = 'page';

    public const PROBLEM = 'problem';

    public const SIGNAL = 'signal';

    /** Something he did on a page: recorded an achievement, added a book… */
    public const ACTION = 'action';

    /** The actions, as the manager reads them. */
    public const ACTIONS = [
        'self_program_saved' => 'سجّل إنجازاً في البرنامج الذاتي',
        'self_program_harvest' => 'استعمل أزرار الحصاد',
        'self_program_made_up' => 'عوّض متأخراً',
        'library_book_added' => 'أضاف كتاباً',
        'library_page_logged' => 'سجّل تقدّمه في كتاب',
        'library_session_finished' => 'أنهى جلسة قراءة',
        'library_quote_added' => 'حفظ اقتباساً',
    ];

    public const UPDATED_AT = null;

    protected $fillable = ['student_id', 'kind', 'route_name', 'detail', 'status', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /**
     * Write one down. It never stands in the student's way: a failure to
     * record is reported and forgotten.
     */
    public static function record(int $studentId, string $kind, ?string $routeName = null, ?string $detail = null, ?int $status = null): void
    {
        try {
            static::create([
                'student_id' => $studentId,
                'kind' => $kind,
                'route_name' => $routeName ? mb_substr($routeName, 0, 120) : null,
                'detail' => $detail ? mb_substr($detail, 0, 255) : null,
                'status' => $status,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Note something the signed-in student did, by one of the ACTIONS keys. */
    public static function action(string $what): void
    {
        $student = auth('student')->user();

        if ($student) {
            static::record($student->id, self::ACTION, request()->route()?->getName(), $what);
        }
    }

    /** The kind of device a browser says it is, in words. */
    public static function device(?string $userAgent): string
    {
        return match (true) {
            (bool) preg_match('/iPad|Tablet/i', (string) $userAgent) => 'جهاز لوحي',
            (bool) preg_match('/Mobi|Android|iPhone/i', (string) $userAgent) => 'جوال',
            default => 'حاسب',
        };
    }

    /**
     * A signal once a day is enough to read: a page drawn again on every click
     * would otherwise say the same thing twenty times.
     */
    public static function signalOnceToday(int $studentId, string $detail, ?string $routeName = null): void
    {
        $already = static::where('student_id', $studentId)
            ->where('kind', self::SIGNAL)
            ->where('detail', $detail)
            ->where('created_at', '>=', now()->startOfDay())
            ->exists();

        if (! $already) {
            static::record($studentId, self::SIGNAL, $routeName, $detail);
        }
    }

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(90));
    }
}
