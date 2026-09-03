<?php

namespace App\Support;

/**
 * Hadiths on seeking knowledge and its merit, for the portal's opening page.
 *
 * Kept in code rather than in the `hadiths` tables: those hold the texts the
 * students are set to memorise, which a supervisor edits, and a saying shown to
 * every visitor of the public page should not move when he does. Reading them
 * from there would also put a query on the one route in the application that
 * runs none.
 *
 * Every entry carries its source and its grading, and only what is graded sahih
 * or hasan is admitted — the academy's own subject is the sacred sciences, and
 * a weak attribution on its front door would be the first thing a visitor who
 * knows the field would notice.
 */
class KnowledgeHadiths
{
    /**
     * @return array<int, array{text: string, source: string, grade: string}>
     */
    public static function all(): array
    {
        return [
            [
                'text' => 'مَنْ سَلَكَ طَرِيقًا يَلْتَمِسُ فِيهِ عِلْمًا، سَهَّلَ اللهُ لَهُ بِهِ طَرِيقًا إِلَى الجَنَّةِ',
                'source' => 'رواه مسلم (٢٦٩٩)',
                'grade' => 'صحيح',
            ],
            [
                'text' => 'مَنْ يُرِدِ اللهُ بِهِ خَيْرًا يُفَقِّهْهُ فِي الدِّينِ',
                'source' => 'متفق عليه: البخاري (٧١) ومسلم (١٠٣٧)',
                'grade' => 'صحيح',
            ],
            [
                'text' => 'إِذَا مَاتَ الإِنْسَانُ انْقَطَعَ عَنْهُ عَمَلُهُ إِلَّا مِنْ ثَلَاثَةٍ: إِلَّا مِنْ صَدَقَةٍ جَارِيَةٍ، أَوْ عِلْمٍ يُنْتَفَعُ بِهِ، أَوْ وَلَدٍ صَالِحٍ يَدْعُو لَهُ',
                'source' => 'رواه مسلم (١٦٣١)',
                'grade' => 'صحيح',
            ],
            [
                'text' => 'خَيْرُكُمْ مَنْ تَعَلَّمَ القُرْآنَ وَعَلَّمَهُ',
                'source' => 'رواه البخاري (٥٠٢٧)',
                'grade' => 'صحيح',
            ],
            [
                'text' => 'لَا حَسَدَ إِلَّا فِي اثْنَتَيْنِ: رَجُلٌ آتَاهُ اللهُ مَالًا فَسَلَّطَهُ عَلَى هَلَكَتِهِ فِي الحَقِّ، وَرَجُلٌ آتَاهُ اللهُ حِكْمَةً فَهُوَ يَقْضِي بِهَا وَيُعَلِّمُهَا',
                'source' => 'متفق عليه: البخاري (٧٣) ومسلم (٨١٦)',
                'grade' => 'صحيح',
            ],
            [
                'text' => 'بَلِّغُوا عَنِّي وَلَوْ آيَةً',
                'source' => 'رواه البخاري (٣٤٦١)',
                'grade' => 'صحيح',
            ],
            [
                'text' => 'مَنْ دَعَا إِلَى هُدًى كَانَ لَهُ مِنَ الأَجْرِ مِثْلُ أُجُورِ مَنْ تَبِعَهُ، لَا يَنْقُصُ ذَلِكَ مِنْ أُجُورِهِمْ شَيْئًا',
                'source' => 'رواه مسلم (٢٦٧٤)',
                'grade' => 'صحيح',
            ],
            [
                'text' => 'العُلَمَاءُ وَرَثَةُ الأَنْبِيَاءِ، إِنَّ الأَنْبِيَاءَ لَمْ يُوَرِّثُوا دِينَارًا وَلَا دِرْهَمًا، إِنَّمَا وَرَّثُوا العِلْمَ، فَمَنْ أَخَذَهُ أَخَذَ بِحَظٍّ وَافِرٍ',
                'source' => 'رواه أبو داود (٣٦٤١) والترمذي (٢٦٨٢)',
                'grade' => 'صححه الألباني',
            ],
            [
                'text' => 'إِنَّ المَلَائِكَةَ لَتَضَعُ أَجْنِحَتَهَا لِطَالِبِ العِلْمِ رِضًا بِمَا يَصْنَعُ',
                'source' => 'رواه أبو داود (٣٦٤١) وابن ماجه (٢٢٣)',
                'grade' => 'صححه الألباني',
            ],
            [
                'text' => 'مَنْ خَرَجَ فِي طَلَبِ العِلْمِ فَهُوَ فِي سَبِيلِ اللهِ حَتَّى يَرْجِعَ',
                'source' => 'رواه الترمذي (٢٦٤٧)',
                'grade' => 'حسّنه الألباني',
            ],
        ];
    }

    /**
     * One of them, chosen afresh on every visit.
     *
     * @return array{text: string, source: string, grade: string}
     */
    public static function random(): array
    {
        $all = self::all();

        return $all[array_rand($all)];
    }
}
