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

        /**
         * A list a father may have something outside of ends with «غير ذلك»,
         * and the form ends with a space to write it in.
         *
         * Not every list: the school years are all the years there are, and
         * offering «غير ذلك» beside them invites an answer nobody can place.
         * A list that is genuinely the whole world says so by not opening.
         */
        $closedLists = [
            // All the years there are.
            'الصف الدراسي في السنة الحالية',
            // A scale is closed by its nature: «غير ذلك» beside «ممتاز» and
            // «ضعيف» is not another grade, it is a way out of answering.
            'إتقانه لما حفظ',
            'قدرته على الحفظ',
            'قدرته على الفهم',
            'مستواه الدراسي العام في آخر فصل',
            // Two states, and the second already opens a box for the number.
            'رقم الجوال (للاتصال)',
        ];

        $add = function (string $type, string $label, bool $required = false, array $options = [], bool $isName = false, array $extra = []) use (&$fields, $closedLists) {
            if ($options !== [] && ! in_array($label, $closedLists, true) && ! in_array('غير ذلك', $options, true)) {
                $options[] = 'غير ذلك';
            }

            $fields[] = $extra + [
                // Worked out from the question, not drawn at random: an answer
                // is stored under the id of the field it answered, so a second
                // run of this seeder with random ids would leave every
                // application already received keyed to questions that no
                // longer exist — readable by nobody.
                'id' => 'f_'.substr(sha1(count($fields).'|'.$label), 0, 10),
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
        $add('text', 'الاسم الرباعي للطالب', true, [], true);
        $add('text', 'تاريخ الميلاد (هجري)', true);
        $add('select', 'الصف الدراسي في السنة الحالية', true, [
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
        // The shape is shown and then enforced. A number typed in Arabic-Indic
        // digits, or with spaces and dashes through it, is a number nobody can
        // paste into WhatsApp — and it is the only way back to the family.
        $add('text', 'رقم الواتساب', true, [], false, [
            'hint' => 'بالأرقام الإنجليزية، هكذا: 05xxxxxxxx أو 9665xxxxxxxx',
            'pattern' => '/^(?:05\\d{8}|\\+?9665\\d{8})$/',
            'pattern_says' => 'اكتب الرقم بالأرقام الإنجليزية على إحدى الصيغتين: 05xxxxxxxx أو 9665xxxxxxxx',
        ]);
        $add('select', 'رقم الجوال (للاتصال)', true, ['نفس رقم الواتساب', 'رقم آخر'], false, [
            'write_in' => ['رقم آخر'],
        ]);
        $add('text', 'البريد الإلكتروني');

        // ── المسار القرآني ──
        $add('section', '١ · المسار القرآني');
        $add('select', 'كم يحفظ من القرآن؟', true, [
            'أقلّ من جزء', 'جزء', 'جزءان إلى ثلاثة',
            'من أربعة إلى خمسة', 'من ستة إلى عشرة', 'أكثر من عشرة أجزاء',
        ]);
        // Words rather than a number out of five: a father marking his son six
        // and a father marking him seven mean nothing beside each other, and
        // «جيد جداً» means the same thing in every house.
        $add('select', 'إتقانه لما حفظ', true, ['ممتاز', 'جيد جداً', 'جيد', 'متوسط', 'ضعيف']);
        $add('yesno', 'هل يقرأ بأحكام التجويد؟', true);
        $add('yesno', 'هل الطالب ملتحق حالياً بحلقة لتحفيظ القرآن؟', true);
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
        // Memorising and understanding are the two things this programme leans
        // on hardest, and a father knows them about his son before any teacher
        // does. Asked apart, because a boy strong in one and not the other is
        // exactly the boy a programme needs to know about in advance.
        $add('select', 'قدرته على الحفظ', true, [
            'متميّز جداً', 'ممتاز', 'متوسط', 'أقلّ من المتوسط', 'ضعيف',
        ]);
        $add('select', 'قدرته على الفهم', true, [
            'متميّز جداً', 'ممتاز', 'متوسط', 'أقلّ من المتوسط', 'ضعيف',
        ]);
        $add('yesno', 'هل شارك في مسابقة أو أولمبياد أو معرض علمي؟');
        $add('long_text', 'إن شارك، فما هي ومتى؟');

        /**
         * ── المسار القيمي ──
         *
         * Two questions, both open. Scales were tried here and taken out: a
         * father asked to rate his own son's prayer out of five answers five,
         * and a column of fives tells a reading committee nothing it can act
         * on. What a father hopes for and where he sees strength are the two
         * things only he can say, and everything else about a boy's character
         * is better learnt from meeting him than from a form.
         */
        $add('section', '٣ · المسار القيمي');
        $add('long_text', 'ماذا تتمنّى أن ترى في ابنك من أخلاق؟', true);
        $add('long_text', 'أين ترى نقاط قوّة ابنك؟', true);

        // ── المسار المهاري ──
        $add('section', '٤ · المسار المهاري');
        $add('multiselect', 'ما الذي يجيده أو يميل إليه؟', false, [
            'الحفظ', 'الفهم السريع', 'الإلقاء والخطابة', 'الكتابة والقصّ',
            'الرسم والتصميم', 'البرمجة والتقنية', 'العمل اليدوي والتركيب',
            'التجارب العلمية', 'الحساب الذهني', 'قيادة المجموعة وتنظيمها',
        ]);
        // A ticked box says which skill; it never says what the boy actually
        // does with it. This is where a father tells the committee the thing no
        // list could have held.
        $add('long_text', 'حدّثنا عن مهاراته: ماذا يصنع بها، وأين ظهرت؟');
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
            'لديه مواصلات متوفّرة',
            'نرغب في توفير وسيلة مواصلات للذهاب والرجوع',
            'نرغب في توفير وسيلة مواصلات للذهاب فقط',
            'نرغب في توفير وسيلة مواصلات للرجوع فقط',
        ]);

        // ── ما يُرجى ──
        $add('section', 'أخيراً');
        $add('long_text', 'ما الذي ترجوه لابنك من هذا البرنامج؟', true);
        $add('select', 'كيف عرفتم عن البرنامج؟', false, [
            'من المدرسة', 'من صديق أو قريب', 'من وسائل التواصل', 'من حلقة التحفيظ',
        ]);

        // The last word is the father's. Every list above narrows him to what
        // somebody thought of in advance; this asks for what none of them held.
        $add('long_text', 'هل تريد أن تخبرنا بشيء إضافي عن الابن؟');
        // Not about the boy at all. A father who has been answering forty
        // questions about his son has usually been thinking about the programme
        // too, and nowhere above asks him.
        $add('long_text', 'مساحة حرّة — نسعد بسماع أفكارك ومقترحاتك');

        Form::updateOrCreate(
            ['slug' => 'nawabigh'],
            [
                'title' => 'نوابغ المستقبل — استمارة الالتحاق',
                'description' => 'استمارة الالتحاق بالدفعة الأولى من برنامج نوابغ المستقبل.',
                'public_intro' => 'برنامجٌ نوعيّ يبني شخصية الطالب بناءً متكاملاً، يستهدف نخبة الطلاب المتميّزين '
                    .'من الصف الأول الابتدائي حتى الثاني المتوسط، عبر خمسة مسارات: القرآني والعلمي '
                    ."والقيمي والمهاري والترفيهي.\n"
                    .'الإجابة الدقيقة تساعدنا على مزيد من الإفادة وحسن التوجيه لأبنائنا الطلاب.',
                'success_text' => 'وصلتنا استمارتك. سنراجعها ونتواصل معك على رقم الجوال الذي كتبته.',
                'policy_text' => 'ما تكتبه هنا يُستعمل لدراسة طلب الالتحاق وحده، ولا يُطّلع عليه إلا لجنة القبول.',
                'color' => self::TEAL,
                'accent_color' => self::CORAL,
                'fields' => $fields,
                'status' => 'published',
                'published_at' => now(),
                'is_public' => true,
                'public_token' => Form::where('slug', 'nawabigh')->value('public_token') ?: Str::random(24),
                'audience' => [],
                // Nobody owns this form, so without this the screen that reads
                // its answers refuses everybody — the applications would arrive
                // and sit where no admissions committee could open them.
                'is_supervisor_shared' => true,
            ],
        );

        $form = Form::where('slug', 'nawabigh')->first();

        // A message printed after the work is done must never undo the work.
        // On the first deployment this line killed the seeder — route() throws
        // against a route table cached before /apply existed — with the form
        // already written and the rest of the deployment, the asset build and
        // the cache rebuild, never run.
        $link = rescue(fn () => $form->publicUrl(), 'شغّل php artisan optimize:clear ليظهر الرابط', report: false);

        $this->command?->info('نوابغ: '.count($fields).' حقلاً — '.$link);
    }
}
