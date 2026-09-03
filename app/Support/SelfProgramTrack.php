<?php

namespace App\Support;

/**
 * The five fields the self programme is made of.
 *
 * They are fixed: the programme is defined as these five, and a week that
 * dropped one or grew a sixth would no longer be the same programme. So they
 * live in code rather than in a table an administrator could edit out from
 * under the progress calculation.
 */
enum SelfProgramTrack: string
{
    case QuranWird = 'quran_wird';
    case Maqrou = 'maqrou';
    case Masmou = 'masmou';
    case Tahdheer = 'tahdheer';
    case Mahfoudh = 'mahfoudh';

    public function label(): string
    {
        return match ($this) {
            self::QuranWird => 'الورد القرآني',
            self::Maqrou => 'المقروء',
            self::Masmou => 'المسموع',
            self::Tahdheer => 'التحضير',
            self::Mahfoudh => 'المحفوظ',
        };
    }

    /**
     * The Quran wird is measured in mushaf pages and nothing else: a recitation
     * recorded by the teacher is converted to pages before it is written here,
     * so a supervisor choosing another unit would break that arithmetic. The
     * rest are the supervisor's to name.
     */
    public function fixedUnit(): ?string
    {
        return $this === self::QuranWird ? 'صفحة' : null;
    }

    public function defaultUnit(): string
    {
        return $this->fixedUnit() ?? match ($this) {
            self::Maqrou => 'صفحة',
            self::Masmou => 'درس',
            self::Tahdheer => 'درس',
            self::Mahfoudh => 'حديث',
            default => 'وحدة',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::QuranWird => 'book-open',
            self::Maqrou => 'document-text',
            self::Masmou => 'speaker-wave',
            self::Tahdheer => 'pencil-square',
            self::Mahfoudh => 'bookmark',
        };
    }

    /** @return array<int, self> */
    public static function ordered(): array
    {
        return [self::QuranWird, self::Maqrou, self::Masmou, self::Tahdheer, self::Mahfoudh];
    }
}
