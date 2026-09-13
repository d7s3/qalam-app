<?php

namespace Database\Seeders;

use App\Models\Form;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The application form for «نوابغ المستقبل».
 *
 * The programme selects able children from the first primary year to the second
 * intermediate, and runs them along five tracks — Quran, knowledge, values,
 * skills and recreation. A form that only collected names would leave whoever
 * has to choose reading two hundred identical applications.
 *
 * So the questions are the tracks. Each one asks what that track would need to
 * know about a boy before it took him, and the answers line up beside each
 * other for the person deciding: what he memorises, where he stands at school,
 * how he behaves when something is hard, what he can already do with his hands,
 * and whether the family can actually bring him four evenings a week.
 *
 * The last is not an afterthought. A programme running Sunday to Wednesday from
 * five to eight loses more children to the school run than to ability, and it
 * is kinder to ask before the seat is given than after it is lost.
 */
class NawabighApplicationSeeder extends Seeder
{
    /**
     * نوابغ's own two colours, read off the programme's poster.
     *
     * The teal carries the badge and the odd-numbered tracks; the coral carries
     * the name and the even ones. Neither is decoration — together they are how
     * the programme is recognised from across a room.
     */
    private const TEAL = '#1B9A8F';

    private const CORAL = '#EE6A4D';

    public function run(): void
    {
        $fields = [];

        $add = function (string $type, string $label, bool $required = false, array $options = [], bool $isName = false) use (&$fields) {
            $fields[] = [
                'id' => 'f_'.Str::random(10),
                'type' => $type,
                'label' => $label,
                'required' => $type === 'section' ? false : $required,
                'options' => $options,
                'is_student_name' => $isName,
                'is_student_username' => false,
                'scale' => in_array($type, ['rating', 'likert'], true) ? 5 : null,
            ];
        };

        // ── الطالب ──
        $add('section', 'بيانات الطالب');
        $add('text', 'اسم الطالب الكامل', true, [], true);
        $add('date', 'تاريخ الميلاد', true);
        $add('select', 'الصف الدراسي', true, [
            'الأول الابتدائي', 'الثاني الابتدائي', 'الثالث الابتدائي',
            'الرابع الابتدائي', 'الخامس الابتدائي', 'السادس الابتدائي',
            'الأول المتوسط', 'الثاني المتوسط',
        ]);
        $add('text', 'المدرسة التي يدرس فيها', true);
        $add('text', 'الحيّ', true);

        // ── وليّ الأمر ──
        $add('section', 'وليّ الأمر');
        $add('text', 'اسم وليّ الأمر', true);
        $add('select', 'صلته بالطالب', true, ['الأب', 'الأم', 'الأخ', 'الجدّ', 'غير ذلك']);
        $add('text', 'رقم الجوال (واتساب)', true);
        $add('text', 'البريد الإلكتروني');

        // ── المسار القرآني ──
        $add('section', '١ · المسار القرآني');
        $add('select', 'كم يحفظ من القرآن؟', true, [
            'أقلّ من جزء', 'جزء', 'جزءان إلى ثلاثة',
            'من أربعة إلى خمسة', 'من ستة إلى عشرة', 'أكثر من عشرة أجزاء',
        ]);
        $add('likert', 'إتقانه لما حفظ', true);
        $add('yesno', 'هل يقرأ بأحكام التجويد؟', true);
        $add('yesno', 'هل هو ملتحق بحلقة تحفيظ الآن؟', true);
        $add('text', 'اسم الحلقة ومكانها، إن كان ملتحقاً');

        // ── المسار العلمي ──
        $add('section', '٢ · المسار العلمي');
        $add('select', 'مستواه الدراسي العام في آخر فصل', true, [
            'ممتاز (٩٥٪ فأعلى)', 'جيد جداً (٩٠ إلى ٩٥٪)',
            'جيد (٨٠ إلى ٩٠٪)', 'مقبول (أقلّ من ٨٠٪)',
        ]);
        $add('multiselect', 'المواد التي يتفوّق فيها', false, [
            'القرآن والعلوم الشرعية', 'اللغة العربية', 'الرياضيات',
            'العلوم', 'اللغة الإنجليزية', 'الحاسب', 'الاجتماعيات',
        ]);
        $add('multiselect', 'المواد التي يحتاج فيها دعماً', false, [
            'القرآن والعلوم الشرعية', 'اللغة العربية', 'الرياضيات',
            'العلوم', 'اللغة الإنجليزية', 'الحاسب', 'الاجتماعيات',
        ]);
        $add('yesno', 'هل شارك في مسابقة أو أولمبياد أو معرض علمي؟');
        $add('long_text', 'إن شارك، فما هي ومتى؟');

        /**
         * ── المسار القيمي ──
         *
         * Open questions only, and deliberately. A father asked to rate his
         * son's prayer out of five answers five, and a scale of ones and twos
         * tells a reading committee nothing it could act on. A father asked
         * what actually happens at prayer time writes a sentence somebody can
         * read a boy out of.
         *
         * So none of these judge. Each asks for a scene — what he did, what was
         * said, what happened next — and the judging is left to the people who
         * read them, which is where it belongs.
         */
        $add('section', '٣ · المسار القيمي');
        $add('long_text', 'صِف موقفاً رأيتَ فيه ابنك يتصرّف تصرّفاً أعجبك. ماذا فعل بالضبط؟', true);
        $add('long_text', 'ماذا يحدث في بيتكم عند وقت الصلاة؟ صِف ما يجري فعلاً، لا ما تتمنّاه.', true);
        $add('long_text', 'إذا أخطأ ابنك أو كُسر شيء بسببه، ماذا يفعل عادةً؟', true);
        $add('long_text', 'احكِ موقفاً احتاج فيه ابنك أن يصبر أو يتنازل لأخيه أو صاحبه. كيف تصرّف؟');
        $add('long_text', 'إذا واجه أمراً صعباً — واجباً أو حفظاً أو خصومة — فكيف يتعامل معه؟ اذكر مثالاً قريباً.');
        $add('long_text', 'ما الخلق الذي تودّ أن يعمل عليه البرنامج مع ابنك؟ ولماذا هو بالذات؟', true);

        // ── المسار المهاري ──
        $add('section', '٤ · المسار المهاري');
        $add('multiselect', 'ما الذي يجيده أو يميل إليه؟', false, [
            'الإلقاء والخطابة', 'الكتابة والقصّ', 'الرسم والتصميم',
            'البرمجة والتقنية', 'العمل اليدوي والتركيب', 'التجارب العلمية',
            'الحساب الذهني', 'قيادة المجموعة وتنظيمها',
        ]);
        $add('select', 'كيف يتعلّم الأشياء الجديدة أسرع؟', true, [
            'حين يسمع الشرح', 'حين يرى ويشاهد',
            'حين يجرّب بيده', 'حين يشرحه لغيره',
        ]);
        $add('select', 'في المجموعة يكون غالباً', true, [
            'قائداً يوزّع ويتابع', 'مشاركاً متعاوناً',
            'منفّذاً هادئاً', 'يفضّل العمل وحده',
        ]);

        // ── المسار الترفيهي ──
        $add('section', '٥ · المسار الترفيهي');
        $add('multiselect', 'ما الذي يستمتع به؟', false, [
            'الرياضة والحركة', 'الألعاب الذهنية والألغاز',
            'الرحلات والاستكشاف', 'المسرح والتمثيل',
            'الأشغال الفنية', 'الأنشطة الجماعية',
        ]);
        $add('long_text', 'هل لديه ما يحتاج مراعاته صحّياً أو غذائياً؟');

        // ── الالتزام ──
        $add('section', 'الالتزام بالمواعيد');
        // A pledge rather than a question about capability. Asking «can he
        // manage it?» invites a hopeful yes and leaves the seat at risk; asking
        // a father to undertake it binds the answer to the acceptance, and a
        // man who will not undertake it has told the academy something useful
        // before the seat was given rather than after it was lost.
        $add('yesno', 'أتعهّد بالتزام ابني بالحضور من الأحد إلى الأربعاء، من الخامسة إلى الثامنة مساءً، في حال قبوله', true);
        // Asked as the four states a family is actually in, rather than as a
        // means of transport. A father who has a ride one way and not the other
        // is a seat the programme can still keep — and telling him apart from a
        // father who has none is what lets somebody arrange it before the term
        // starts instead of losing the boy in the second week.
        $add('select', 'كيف سيصل الطالب ويعود؟', true, [
            'لدينا مواصلات ولا إشكال فيها',
            'لدينا مواصلات للذهاب ولا يوجد للعودة، ونبحث عن حلّ بمقابل مناسب',
            'لدينا مواصلات للعودة ولا يوجد للذهاب، ونبحث عن حلّ بمقابل مناسب',
            'لا يوجد مواصلات، ونبحث عن حلّ بمقابل مناسب',
        ]);

        // ── ما يُرجى ──
        $add('section', 'أخيراً');
        $add('long_text', 'ما الذي ترجوه لابنك من هذا البرنامج؟', true);
        $add('select', 'كيف عرفتم عن البرنامج؟', false, [
            'من المدرسة', 'من صديق أو قريب', 'من وسائل التواصل',
            'من حلقة التحفيظ', 'غير ذلك',
        ]);

        Form::updateOrCreate(
            ['slug' => 'nawabigh'],
            [
                'title' => 'نوابغ المستقبل — استمارة الالتحاق',
                'description' => 'استمارة الالتحاق بالدفعة الأولى من برنامج نوابغ المستقبل.',
                'public_intro' => 'برنامجٌ نوعيّ يبني شخصية الطالب بناءً متكاملاً، يستهدف نخبة الطلاب المتميّزين '
                    .'من الصف الأول الابتدائي حتى الثاني المتوسط، عبر خمسة مسارات: القرآني والعلمي '
                    .'والقيمي والمهاري والترفيهي. أجب عمّا يلي بدقّة، فالإجابات هي ما يُبنى عليه القبول.',
                'success_text' => 'وصلتنا استمارتك. سنراجعها ونتواصل معك على رقم الجوال الذي كتبته. '
                    .'المقاعد محدودة، والأولوية بحسب ما تُظهره الاستمارة.',
                'policy_text' => 'ما تكتبه هنا يُستعمل لدراسة طلب الالتحاق وحده، ولا يُطّلع عليه إلا لجنة القبول.',
                'color' => self::TEAL,
                'accent_color' => self::CORAL,
                'fields' => $fields,
                'status' => 'published',
                'published_at' => now(),
                'is_public' => true,
                'public_token' => Form::where('slug', 'nawabigh')->value('public_token') ?: Str::random(24),
                'audience' => [],
            ],
        );

        $form = Form::where('slug', 'nawabigh')->first();

        $this->command?->info('نوابغ: '.count($fields).' حقلاً — '.$form->publicUrl());
    }
}
