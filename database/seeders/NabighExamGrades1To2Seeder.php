<?php

namespace Database\Seeders;

use App\Models\Form;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The digital half of «مقياس نابغة» for the first and second grades.
 *
 * A six- or seven-year-old cannot fill in fifty multiple-choice items alone —
 * there is no self-report questionnaire or situational-judgement test in this
 * age band's printed guide at all, only a live examiner. So unlike the
 * grades 3-6 booklet, nothing here is answered by the child on a screen: every
 * form in this seeder is filled BY an adult — an examiner during a 45-60
 * minute one-to-one session, a teacher, an observer watching a group task —
 * reading a paper rubric off a device instead of off paper. See
 * `App\Support\NabighExam::GRADES_1_TO_2` for how the five digitised domains
 * are weighed.
 *
 * «نابغة المصغر» (15% of the battery) has no rating card in the source guide —
 * a schedule of what to watch for during the mini programme day, not a rubric
 * — so it is not seeded as a form here and stays on paper, exactly like grades
 * 3-6's teacher-known-three-months rating, interview and simulation day.
 *
 * The source guide names a sixth reasoning task, «البناء المكاني» (spatial
 * construction), worth 4 of the domain's 25 points in its summary table, but
 * neither source document supplies the task's actual content — no materials,
 * no instructions, no rubric. Rather than invent one, this seeder omits it;
 * the reasoning domain here is A1, A2, A3, A4 and A5 only. Add it once the
 * task itself is written.
 */
class NabighExamGrades1To2Seeder extends Seeder
{
    private const TEAL = '#1B9A8F';

    private const CORAL = '#EE6A4D';

    /** @var array<int, array<string, mixed>> */
    private array $fields = [];

    public function run(): void
    {
        $this->buildSessionForm('A', $this->sessionA());
        $this->buildSessionForm('B', $this->sessionB());
        $this->buildExecutiveFunctionCard();
        $this->buildTeacherCard();
        $this->buildTeamworkCard();
    }

    /**
     * @param  array{
     *     patterns: array<int, array{0: string, 1: string}>,
     *     classification: array<int, array{0: string, 1: string}>,
     *     relations: array<int, array{0: string, 1: array<int, string>, 2: int}>,
     *     ruleLabel: string,
     *     problemLabel: string,
     *     passage: string,
     *     readingQuestions: array<int, string>,
     * }  $session
     */
    private function buildSessionForm(string $letter, array $session): void
    {
        $this->fields = [];
        $slug = 'nabigh-1-2-session-'.strtolower($letter);

        $this->add('section', 'بيانات الجلسة');
        $this->add('text', 'اسم الطالب الكامل', true, isName: true);
        $this->add('select', 'الصف الدراسي الحالي', true, options: ['الأول الابتدائي', 'الثاني الابتدائي']);
        $this->add('text', 'اسم المقيِّم', true);
        $this->add('text', 'رقم جوال ولي الأمر (للتواصل بخصوص النتيجة)', true);

        $this->add('section', 'A1 — الأنماط: اعرض التسلسل شفهيًا أو ببطاقات، ودوّن ما قاله الطفل');
        foreach ($session['patterns'] as $i => [$stem, $correct]) {
            $this->add('text', ($i + 1).'. '.$stem, true, extra: [
                'dimension' => 'reasoning', 'max_manual_score' => 1,
                'hint' => 'الإجابة المتوقعة: '.$correct,
            ]);
        }

        $this->add('section', 'A2 — التصنيف والتفسير: اطلب من الطفل تحديد العنصر المختلف وسبب ذلك');
        foreach ($session['classification'] as $i => [$stem, $expected]) {
            $this->add('long_text', ($i + 1).'. '.$stem, true, extra: [
                'dimension' => 'reasoning', 'max_manual_score' => 2,
                'hint' => 'العنصر المختلف المتوقع: '.$expected.' — الدرجة: 0 غير صحيح، 1 اختيار صحيح، 2 اختيار صحيح مع تفسير منطقي (تُقبل تفسيرات بديلة معقولة).',
            ]);
        }

        $this->add('section', 'A3 — العلاقات');
        foreach ($session['relations'] as $i => [$stem, $choices, $correctIndex]) {
            $this->addMcq($stem, $choices, $correctIndex, 'reasoning', seed: ($letter === 'A' ? 500 : 600) + $i);
        }

        $this->add('section', 'A4 — التعلم الديناميكي: '.$session['ruleLabel']);
        $this->addObservedRating('فهم القاعدة الأولى', ['لم يفهم', 'فهم بعد تكرار', 'فهم سريعًا']);
        $this->addObservedRating('تطبيق القاعدة الأولى', ['أخطاء كثيرة', 'أخطاء محدودة', 'مستقر']);
        $this->addObservedRating('ترك القاعدة القديمة', ['تمسك بها', 'احتاج تذكيرًا', 'انتقل سريعًا']);
        $this->addObservedRating('تعلم القاعدة الجديدة', ['صعب', 'متوسط', 'سريع']);
        $this->addObservedRating('تصحيح الذات', ['لا يصحح', 'بعد تنبيه', 'يصحح تلقائيًا']);

        $this->add('section', 'A5 — حل مشكلة جديدة: '.$session['problemLabel']);
        $this->addObservedRating('يبدأ بالمحاولة', ['لا', 'بعد دفع', 'مباشرة']);
        $this->addObservedRating('يفكر قبل التكرار', ['لا', 'أحيانًا', 'نعم']);
        $this->addObservedRating('يغيّر الاستراتيجية بعد الفشل', ['لا', 'بعد تلميح', 'تلقائيًا']);
        $this->addObservedRating('يطلب مساعدة مناسبة', ['غير مناسب', 'عام', 'محدد']);

        $this->add('section', 'A6 — الجاهزية الأساسية');
        foreach ($session['readingQuestions'] as $i => $question) {
            $this->add('long_text', $question, true, extra: array_filter([
                'dimension' => 'readiness',
                'max_manual_score' => 1,
                'hint' => $i === 0 ? 'النص: «'.$session['passage'].'»' : null,
            ]));
        }
        $this->add('text', 'مهمة عددية: أعطِ الطفل 8 قطع واطلب «أعطني 5». ماذا فعل؟', true, extra: [
            'dimension' => 'readiness', 'max_manual_score' => 1, 'hint' => 'الإجابة المتوقعة: يعطي 5 قطع بالضبط.',
        ]);
        $this->add('text', 'ثم اطلب «أضف قطعتين». ماذا فعل؟', true, extra: [
            'dimension' => 'readiness', 'max_manual_score' => 1, 'hint' => 'الإجابة المتوقعة: يصل إلى 7.',
        ]);
        $this->add('text', 'ثم اطلب قسمة الـ8 بالتساوي بين طفلين (وللصف الثاني: قسمة 12 بين 3 أطفال). ماذا فعل؟', true, extra: [
            'dimension' => 'readiness', 'max_manual_score' => 1, 'hint' => 'الإجابة المتوقعة: 4 لكل طفل (أو 4 لكل طفل من الثلاثة في نسخة الصف الثاني).',
        ]);
        $this->add('yesno', 'اتباع التعليمات: «خذ القلم، ضعه تحت الورقة، ثم أعطني المكعب» (ارفعها لأربع خطوات للصف الثاني إن نجح). هل نفّذها بالترتيب الصحيح؟', true, extra: [
            'dimension' => 'readiness', 'max_manual_score' => 1,
        ]);

        $this->save($slug, 'مقياس نابغة (أول وثاني) — الجلسة الفردية — يملؤها المقيِّم — نموذج '.$letter,
            'دليل تطبيق حيّ يملؤه المقيِّم أثناء جلسة فردية مع الطفل (45-60 دقيقة)، وليس استبانة يجيب عنها الطفل بنفسه.');
    }

    private function buildExecutiveFunctionCard(): void
    {
        $this->fields = [];
        $items = [
            'ينتظر اكتمال التعليمات قبل البدء',
            'يمنع الاستجابة التلقائية عندما تتغير القاعدة',
            'يحفظ تعليمات من خطوتين أو ثلاث',
            'يصحح نفسه بعد الخطأ',
            'يتحول إلى قاعدة جديدة دون تشبث طويل',
            'يعود للمهمة بعد تشتت بسيط',
            'يتحمل الخطأ دون انسحاب',
            'يستفيد من التلميح ولا يعتمد عليه',
            'ينظم الأدوات أثناء المهمة',
            'ينتقل بين الأنشطة بهدوء نسبي',
        ];

        $this->add('section', 'بطاقة التنظيم والوظائف التنفيذية — أول وثاني');
        $this->add('text', 'اسم الطالب الكامل', true, isName: true);
        $this->add('text', 'اسم المقيِّم/الملاحظ', true);
        $this->addFiveScaleLikert($items, 'executive_function', low: 'ضعيف جدًا / يحتاج دعمًا مستمرًا', mid: 'مناسب لعمره في أغلب الوقت', high: 'مرتفع وواضح');

        $this->save('nabigh-1-2-executive-function', 'مقياس نابغة (أول وثاني) — بطاقة التنظيم والوظائف التنفيذية',
            'يملؤها المقيِّم أو الملاحظ بعد مهام كف الاستجابة والذاكرة العاملة وتبديل القاعدة.');
    }

    private function buildTeacherCard(): void
    {
        $this->fields = [];
        $items = [
            'يفهم التعليمات الجديدة بسرعة',
            'يسأل أسئلة تدل على الفضول',
            'يتذكر ما تعلمه ويستخدمه في موقف آخر',
            'يجرب أكثر من طريقة للحل',
            'يكمل العمل الذي بدأه',
            'يستمر عند صعوبة مناسبة لعمره',
            'يعمل دون مراقبة مستمرة',
            'ينتقل بين الأنشطة دون تعطيل كبير',
            'يتقبل التصحيح',
            'ينتظر دوره',
            'يحافظ على أدواته',
            'يتعاون مع زملائه',
            'يعود للتركيز بعد الاستراحة',
            'يتابع تعليمات متعددة',
            'يبدي رغبة حقيقية في تعلم أشياء جديدة',
        ];

        $this->add('section', 'بطاقة المعلم — أول وثاني');
        $this->add('text', 'اسم الطالب الكامل', true, isName: true);
        $this->add('text', 'اسم المعلم', true);

        foreach ($items as $label) {
            $this->add('likert', $label, true, extra: [
                'scale_min' => 1, 'scale_max' => 4,
                'scale_labels' => [1 => 'نادرًا', 2 => 'أحيانًا', 3 => 'غالبًا', 4 => 'دائمًا'],
                'dimension' => 'teacher',
            ]);
        }

        $this->save('nabigh-1-2-teacher-card', 'مقياس نابغة (أول وثاني) — بطاقة المعلم',
            'يملؤها معلم يعرف الطالب، بمعزل عن أداء الجلسة الفردية.');
    }

    private function buildTeamworkCard(): void
    {
        $this->fields = [];
        $items = [
            'يشارك بفكرة',
            'يستمع لغيره',
            'ينتظر دوره',
            'يشارك الأدوات',
            'يقبل عدم اختيار فكرته',
            'يساعد زميلًا',
            'يتعامل مع الخلاف بهدوء',
            'يطلب المساعدة بطريقة مناسبة',
        ];

        $this->add('section', 'المهمة الجماعية — أول وثاني (أربعة أطفال، 12 دقيقة، بناء برج أو جسر بمواد بسيطة — لا تُقيَّم جودة المنتج)');
        $this->add('text', 'اسم الطالب الكامل', true, isName: true);
        $this->add('text', 'اسم الملاحظ', true);
        $this->addFiveScaleLikert($items, 'teamwork', low: 'نادرًا', mid: 'أحيانًا', high: 'باستمرار');

        $this->save('nabigh-1-2-teamwork-card', 'مقياس نابغة (أول وثاني) — بطاقة المهمة الجماعية',
            'يملؤها ملاحظ أثناء أو مباشرة بعد المهمة الجماعية ضمن نابغة المصغر.');
    }

    /** @param array<int, string> $items */
    private function addFiveScaleLikert(array $items, string $dimension, string $low, string $mid, string $high): void
    {
        foreach ($items as $label) {
            $this->add('likert', $label, true, extra: [
                'scale_min' => 1, 'scale_max' => 5,
                'scale_labels' => [1 => $low, 3 => $mid, 5 => $high],
                'dimension' => $dimension,
            ]);
        }
    }

    /** One 0-2 observed indicator, worded for this indicator alone rather than off a shared scale. */
    private function addObservedRating(string $label, array $labels3): void
    {
        $this->add('likert', $label, true, extra: [
            'scale_min' => 0, 'scale_max' => 2,
            'scale_labels' => [0 => $labels3[0], 1 => $labels3[1], 2 => $labels3[2]],
            'dimension' => 'reasoning',
        ]);
    }

    /**
     * Append one field. The id is worked out from its position and label, not
     * drawn at random, so re-running this seeder to fix a typo does not leave
     * an already-collected observation keyed to a question that no longer exists.
     *
     * @param  array<int, string>  $options
     * @param  array<string, mixed>  $extra
     */
    private function add(string $type, string $label, bool $required = false, array $options = [], bool $isName = false, array $extra = []): void
    {
        $this->fields[] = $extra + [
            'id' => 'f_'.substr(sha1(count($this->fields).'|'.$label), 0, 10),
            'type' => $type,
            'label' => $label,
            'required' => $type === 'section' ? false : $required,
            'options' => $options,
            'is_student_name' => $isName,
            'is_student_username' => false,
        ];
    }

    /** @param array<int, string> $choices in the source booklet's أ/ب/ج/د order */
    private function addMcq(string $stem, array $choices, int $correctIndex, string $dimension, int $seed): void
    {
        $correctText = $choices[$correctIndex];

        mt_srand($seed);
        shuffle($choices);
        mt_srand();

        $this->add('mcq', $stem, true, options: $choices, extra: [
            'correct_option' => $correctText, 'points' => 1, 'dimension' => $dimension,
        ]);
    }

    private function save(string $slug, string $title, string $description): void
    {
        Form::updateOrCreate(
            ['slug' => $slug],
            [
                'title' => $title,
                'description' => $description,
                'public_intro' => 'استمارة داخلية لفريق القياس في برنامج نابغة — تُملأ أثناء التطبيق أو مباشرة بعده، لا يجيب عنها ولي الأمر ولا الطالب.',
                'closing_note' => 'الإصدار التجريبي 1.0. لا تُستخدم كلمة «درجة ذكاء»؛ الصياغة الصحيحة: درجة الملاءمة لبرنامج نابغة.',
                'success_text' => 'حُفظت البطاقة بنجاح.',
                'color' => self::TEAL,
                'accent_color' => self::CORAL,
                'fields' => $this->fields,
                'status' => 'published',
                'published_at' => now(),
                'is_public' => true,
                'public_token' => Form::where('slug', $slug)->value('public_token') ?: Str::random(24),
                'audience' => [],
                'is_supervisor_shared' => true,
            ],
        );

        $form = Form::where('slug', $slug)->first();
        $link = rescue(fn () => $form->publicUrl(), 'شغّل php artisan optimize:clear ليظهر الرابط', report: false);

        $this->command?->info($title.': '.count($this->fields).' حقلاً — '.$link);
    }

    /** @return array{patterns: array<int, array{0: string, 1: string}>, classification: array<int, array{0: string, 1: string}>, relations: array<int, array{0: string, 1: array<int, string>, 2: int}>, ruleLabel: string, problemLabel: string, passage: string, readingQuestions: array<int, string>} */
    private function sessionA(): array
    {
        return [
            'patterns' => [
                ['أحمر - أزرق - أحمر - أزرق - ؟', 'أحمر'],
                ['دائرة - دائرة - مربع - دائرة - دائرة - مربع - ؟', 'دائرة'],
                ['1 - 2 - 1 - 2 - 1 - ؟', '2'],
                ['كبير - صغير - صغير - كبير - صغير - صغير - ؟', 'كبير'],
            ],
            'classification' => [
                ['تفاحة - موزة - برتقالة - سيارة. أي واحد مختلف؟ ولماذا؟', 'سيارة'],
                ['قلم - دفتر - كتاب - ملعقة. أي واحد مختلف؟ ولماذا؟', 'ملعقة'],
                ['سمكة - قطة - عصفور - كرسي. أي واحد مختلف؟ ولماذا؟', 'كرسي'],
                ['حذاء - قبعة - قميص - كرة. أي واحد مختلف؟ ولماذا؟', 'كرة'],
            ],
            'relations' => [
                ['سمكة : ماء = طائر : ؟', ['سماء', 'كتاب', 'حذاء', 'سيارة'], 0],
                ['يد : قفاز = قدم : ؟', ['حذاء', 'قلم', 'كوب', 'كتاب'], 0],
                ['ملعقة : أكل = قلم : ؟', ['كتابة', 'نوم', 'جري', 'ماء'], 0],
                ['عين : رؤية = أذن : ؟', ['سماع', 'مشي', 'أكل', 'كتابة'], 0],
            ],
            'ruleLabel' => 'القاعدة الأولى: البطاقات الدائرية يمينًا، والمربعة يسارًا. بعد 6 محاولات صحيحة غيّر القاعدة: تجاهل الشكل؛ الكبير يمينًا والصغير يسارًا.',
            'problemLabel' => 'ضع قطعة صغيرة في علبة عميقة، وأمام الطفل عصا قصيرة وخيط وورقة مطوية ومكعب. اطلب إخراج القطعة دون قلب العلبة.',
            'passage' => 'خرج سالم إلى الحديقة، فرأى نبتة أوراقها متدلية. لمس التربة فوجدها جافة، فسقاها قليلًا. في اليوم التالي وجد أوراقها أكثر انتصابًا.',
            'readingQuestions' => [
                'لماذا سقى سالم النبتة؟',
                'ما الدليل الذي جعله يظن أنها تحتاج ماء؟',
                'ماذا تتوقع لو بقيت التربة جافة عدة أيام؟',
                'هل نستطيع من هذه النبتة وحدها أن نقول إن الماء يحل كل مشكلات النباتات؟ ولماذا؟',
            ],
        ];
    }

    /** @return array{patterns: array<int, array{0: string, 1: string}>, classification: array<int, array{0: string, 1: string}>, relations: array<int, array{0: string, 1: array<int, string>, 2: int}>, ruleLabel: string, problemLabel: string, passage: string, readingQuestions: array<int, string>} */
    private function sessionB(): array
    {
        return [
            'patterns' => [
                ['أصفر - أخضر - أصفر - أخضر - ؟', 'أصفر'],
                ['مثلث - مربع - مربع - مثلث - مربع - مربع - ؟', 'مثلث'],
                ['2 - 3 - 2 - 3 - 2 - ؟', '3'],
                ['صغير - كبير - كبير - صغير - كبير - كبير - ؟', 'صغير'],
            ],
            'classification' => [
                ['سيارة - حافلة - دراجة - موزة. أي واحد مختلف؟ ولماذا؟', 'موزة'],
                ['سرير - كرسي - طاولة - سمكة. أي واحد مختلف؟ ولماذا؟', 'سمكة'],
                ['أسد - حصان - أرنب - كتاب. أي واحد مختلف؟ ولماذا؟', 'كتاب'],
                ['كوب - صحن - ملعقة - حذاء. أي واحد مختلف؟ ولماذا؟', 'حذاء'],
            ],
            'relations' => [
                ['نحلة : عسل = بقرة : ؟', ['حليب', 'كتاب', 'ماء', 'شجرة'], 0],
                ['رأس : قبعة = قدم : ؟', ['جورب', 'ملعقة', 'باب', 'كرة'], 0],
                ['فرشاة : رسم = مقص : ؟', ['قص', 'قراءة', 'نوم', 'شرب'], 0],
                ['أنف : شم = لسان : ؟', ['تذوق', 'سماع', 'مشي', 'رؤية'], 0],
            ],
            'ruleLabel' => 'القاعدة الأولى: بطاقات النقطة يمينًا، وبطاقات الخط يسارًا. بعد التدريب غيّر القاعدة: تجاهل العلامة؛ الطويل يمينًا والقصير يسارًا.',
            'problemLabel' => 'ضع قطعة على طاولة بعيدًا عن يد الطفل، وأمامه مسطرة وشريط ورقي وكوب ومكعب. اطلب الوصول إليها دون الصعود على الطاولة.',
            'passage' => 'وضع مازن قطعة ثلج في طبق على الطاولة، وترك قطعة أخرى في مكان أبرد. بعد مدة وجد أن القطعة على الطاولة ذابت أسرع.',
            'readingQuestions' => [
                'ماذا حدث لقطعتي الثلج؟',
                'ما العامل الذي اختلف بين مكان القطعتين؟',
                'ماذا يتوقع لو وضعنا قطعة ثالثة في مكان أكثر حرارة؟',
                'كيف يمكن أن نجعل هذه المقارنة أكثر عدلًا؟',
            ],
        ];
    }
}
