<?php

namespace App\Support;

/**
 * Sayings on learning and its worth, for the portal's opening page.
 *
 * These replaced a set of hadiths the page used to carry: the platform is
 * handed to schools and academies of every kind, and a doorway that opens with
 * a religious text speaks for one of them rather than for all. What is said
 * about learning belongs to everyone who teaches.
 *
 * Kept in code rather than in a table, for the same two reasons the hadiths
 * were: a line shown to every visitor should not move when a supervisor edits
 * something, and the one route in the application that runs no query should
 * keep running none.
 *
 * Every saying is attributed, and every author has been dead long enough that
 * the words are nobody's property to withhold.
 */
class LearningSayings
{
    /**
     * @return array<int, array{text: string, source: string}>
     */
    public static function all(): array
    {
        return [
            [
                'text' => 'قُمْ للمعلِّمِ وفِّهِ التبجيلا · كادَ المعلِّمُ أن يكونَ رسولا',
                'source' => 'أحمد شوقي',
            ],
            [
                'text' => 'العِلمُ يُؤتى ولا يَأتي',
                'source' => 'مَثَلٌ عربيّ',
            ],
            [
                'text' => 'إنّ التعليمَ صناعةٌ، وللصنائعِ كلِّها تعليمٌ وتدريب',
                'source' => 'ابن خلدون · المقدّمة',
            ],
            [
                'text' => 'مَن لم يَذُقْ مُرَّ التعلُّمِ ساعةً، تجرَّعَ ذُلَّ الجهلِ طولَ حياتِه',
                'source' => 'أبو الفتح البُستيّ',
            ],
            [
                'text' => 'العِلمُ في الصِّغَرِ كالنقشِ في الحَجَر',
                'source' => 'مَثَلٌ عربيّ',
            ],
            [
                'text' => 'أوّلُ العِلمِ الصَّمْتُ، والثاني حُسنُ الاستماع، والثالثُ حِفظُه، والرابعُ العملُ به، والخامسُ نشرُه',
                'source' => 'سفيان بن عُيينة',
            ],
            [
                'text' => 'التعليمُ في الصِّبا أثبتُ، وهو أصلٌ لما بعده',
                'source' => 'ابن خلدون · المقدّمة',
            ],
            [
                'text' => 'لا يَزالُ الرجلُ عالماً ما طلبَ العِلم، فإذا ظنَّ أنّه قد عَلِمَ فقد جَهِل',
                'source' => 'عبدالله بن المبارك',
            ],
            [
                'text' => 'خيرُ ما وَرَّثَ الآباءُ الأبناءَ أدبٌ حسنٌ وعِلمٌ نافع',
                'source' => 'مَثَلٌ عربيّ',
            ],
            [
                'text' => 'اطلبوا العِلمَ ولو كان بعيدَ الدار، فإنّ بُعدَ الدارِ لا يُنسي قُربَ المنفعة',
                'source' => 'الجاحظ · البيان والتبيين',
            ],
        ];
    }

    /** @return array{text: string, source: string} */
    public static function random(): array
    {
        $sayings = self::all();

        return $sayings[array_rand($sayings)];
    }
}
