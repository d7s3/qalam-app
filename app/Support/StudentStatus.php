<?php

namespace App\Support;

/**
 * Where a student stands with the academy, held in `users.status`.
 *
 * The words grew a screen at a time — four in the forms, a fifth written by
 * placement that no form, badge or report knew — so the same student read
 * «غادر الدفعات» in one list and a bare English word in the next. They are
 * decided here, once: every label, colour, option and rule reads from this.
 *
 * Which of them a student held on a given day lives in the status history;
 * the column is the latest of it.
 */
enum StudentStatus: string
{
    /** In a cohort and attending: the only one counted on a register. */
    case Active = 'active';

    /** Known to the academy and not yet placed in a cohort. */
    case Registering = 'registering';

    /** Kept from attending for a time, often with a date to come back. */
    case Suspended = 'suspended';

    /**
     * Taken out of his cohort but still the academy's: his name, record and
     * history stay, and he may be placed again.
     */
    case Inactive = 'inactive';

    /** Gone from the academy for good. */
    case Left = 'left';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'مشارك',
            self::Registering => 'تحت التسجيل',
            self::Suspended => 'موقوف',
            self::Inactive => 'غير فعّال',
            self::Left => 'غادر الأكاديمية',
        };
    }

    /** The Flux badge colour, the same on every screen. */
    public function color(): string
    {
        return match ($this) {
            self::Active => 'green',
            self::Registering => 'blue',
            self::Suspended => 'amber',
            self::Inactive => 'zinc',
            self::Left => 'red',
        };
    }

    /** The band this status draws on a student's timeline. */
    public function timelineClass(): string
    {
        return match ($this) {
            self::Active => 'bg-green-400 dark:bg-green-600',
            self::Registering => 'bg-blue-400 dark:bg-blue-600',
            self::Suspended => 'bg-amber-400 dark:bg-amber-600',
            self::Inactive => 'bg-zinc-400 dark:bg-zinc-500',
            self::Left => 'bg-red-400 dark:bg-red-600',
        };
    }

    /**
     * The status a stored value stands for. The column defaults to active, so
     * an empty one is active; a word outside the five is nothing.
     */
    public static function of(?string $stored): ?self
    {
        return $stored === null || $stored === '' ? self::Active : self::tryFrom($stored);
    }

    /** A stored value's label, or the value itself when it is none of the five. */
    public static function labelOf(?string $stored): string
    {
        return self::of($stored)?->label() ?? (string) $stored;
    }

    /** A stored value's badge colour, grey when it is none of the five. */
    public static function colorOf(?string $stored): string
    {
        return self::of($stored)?->color() ?? 'zinc';
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
