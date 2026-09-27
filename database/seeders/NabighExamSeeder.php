<?php

namespace Database\Seeders;

use App\Models\Form;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The electronic half of «مقياس نابغة» for the third through sixth grades.
 *
 * The printed battery has seven parts. Three need a person in the room — a
 * teacher who has known the child three months, an interviewer, an observer
 * timing a group task — and stay on paper. These four travel on a screen with
 * a parent beside the child: reasoning, academic readiness, a self-regulation
 * questionnaire, and a situational-judgement test. See `App\Support\NabighExam`
 * for how those four map onto the printed guide's own weights.
 *
 * Two parallel forms, A and B, so a sibling or a friend tested the same day
 * cannot simply repeat what the child ahead of them said. Multiple-choice
 * options are shuffled at seed time with a fixed, per-item seed: the source
 * booklet placed the intended answer in the same letter far more often than
 * chance would (option ج in eight of Form A's twenty reasoning items, option
 * ب in every one of Form B's ten situational items) — a pattern a test-savvy
 * child or a coached parent can exploit without reading a single question.
 * Shuffling here only touches this seeded copy, not the printed booklet
 * already in anyone's hands.
 */
class NabighExamSeeder extends Seeder
{
    private const TEAL = '#1B9A8F';

    private const CORAL = '#EE6A4D';

    /** @var array<int, array<string, mixed>> */
    private array $fields = [];

    public function run(): void
    {
        $this->buildForm(
            letter: 'A',
            reasoning: $this->reasoningA(),
            readiness: $this->readinessA(),
            sjt: $this->sjtA(),
        );

        $this->buildForm(
            letter: 'B',
            reasoning: $this->reasoningB(),
            readiness: $this->readinessB(),
            sjt: $this->sjtB(),
        );
    }

    /**
     * @param  array<int, array{0: string, 1: array<int, string>, 2: int}>  $reasoning  [stem, choices، correct index]
     * @param  array{passage: string, reading: array<int, string>, math: array<int, string>, writing: string}  $readiness
     * @param  array<int, array{0: string, 1: array<int, string>, 2: int}>  $sjt
     */
    private function buildForm(string $letter, array $reasoning, array $readiness, array $sjt): void
    {
        $this->fields = [];
        $slug = 'nabigh-exam-'.strtolower($letter);

        $this->add('section', 'بيانات الطالب وولي الأمر');
        $this->add('text', 'اسم الطالب الكامل', true, isName: true);
        $this->add('select', 'الصف الدراسي الحالي', true, options: [
            'الثالث الابتدائي', 'الرابع الابتدائي', 'الخامس الابتدائي', 'السادس الابتدائي',
        ]);
        $this->add('text', 'رقم جوال ولي الأمر (للتواصل بخصوص النتيجة)', true);

        $this->add('section', 'الاستدلال والقدرة على التعلم — اختر إجابة واحدة، ولا تخمّن بسرعة إن لم تعرف؛ حاول فهم القاعدة أولًا');
        foreach ($reasoning as $i => [$stem, $choices, $correctIndex]) {
            $this->addMcq($stem, $choices, $correctIndex, 'reasoning', seed: 100 + $i);
        }

        $this->add('section', 'الجاهزية الأكاديمية — لا توجد إجابة واحدة صحيحة لكل سؤال؛ يقيّمها المصحح لاحقًا');
        foreach ($readiness['reading'] as $i => $question) {
            // Only the first reading question carries the passage — as a hint
            // beneath its label, not repeated above every question after it.
            $this->add('long_text', $question, true, extra: array_filter([
                'dimension' => 'readiness',
                'max_manual_score' => 1,
                'hint' => $i === 0 ? 'النص: «'.$readiness['passage'].'»' : null,
            ]));
        }

        foreach ($readiness['math'] as $question) {
            $this->add('text', $question, true, extra: ['dimension' => 'readiness', 'max_manual_score' => 1]);
        }
        $this->add('long_text', $readiness['writing'], true, extra: ['dimension' => 'readiness', 'max_manual_score' => 4]);

        $this->add('section', 'التنظيم الذاتي والدافعية — ضع علامة أقرب إجابة تشبهك، لا الإجابة التي تبدو أفضل');
        $this->addSelfRegulationItems();

        $this->add('section', 'الحكم الموقفي (SJT) — اختر التصرّف الذي تراه الأفضل في كل موقف');
        foreach ($sjt as $i => [$stem, $choices, $correctIndex]) {
            $this->addMcq($stem, $choices, $correctIndex, 'sjt', seed: 400 + $i, points: 1);
        }

        Form::updateOrCreate(
            ['slug' => $slug],
            [
                'title' => 'مقياس نابغة الإلكتروني — الصفوف الثالث إلى السادس — النموذج '.$letter,
                'description' => 'اختبار تجريبي (الإصدار 1.0) لتقدير ملاءمة الطالب لبرنامج نابغة، وليس مقياس ذكاء رسميًا.',
                'public_intro' => "اختبار إلكتروني قصير يُجريه الطالب على الجهاز، ومن الأفضل أن يكون أحد الوالدين قريبًا للمساعدة في التنقّل بين الأسئلة دون الإجابة عنه.\n"
                    .'يستغرق الاختبار من ٣٠ إلى ٤٥ دقيقة تقريبًا، ويفضَّل إنجازه في جلسة واحدة متصلة وبيئة هادئة.',
                'closing_note' => "هذا اختبار تجريبي للفرز الأولي، وليس حكمًا نهائيًا على قدرات الطالب أو قيمته.\n"
                    .'نتيجته جزء واحد من ملف القبول، وتُستكمل بتقييم المعلم والمقابلة ويوم المحاكاة.',
                'success_text' => 'تم استلام إجابات الطالب بنجاح. سنراجعها ضمن ملف القبول ونتواصل معكم على رقم الجوال الذي كتبتموه.',
                'policy_text' => 'إجابات هذا الاختبار تُستخدم لدراسة ملاءمة الطالب لبرنامج نابغة وحده، ولا يُطّلع عليها إلا لجنة القبول.',
                'color' => self::TEAL,
                'accent_color' => self::CORAL,
                'fields' => $this->fields,
                'status' => 'published',
                'published_at' => now(),
                'is_public' => true,
                'public_token' => Form::where('slug', $slug)->value('public_token') ?: Str::random(24),
                'audience' => [],
                // Nobody owns this form individually, so without this the
                // screen that reads its answers would refuse every supervisor.
                'is_supervisor_shared' => true,
            ],
        );

        $form = Form::where('slug', $slug)->first();

        // A message printed after the work is done must never undo the work —
        // route() can throw against a route table cached before this ran.
        $link = rescue(fn () => $form->publicUrl(), 'شغّل php artisan optimize:clear ليظهر الرابط', report: false);

        $this->command?->info("مقياس نابغة (نموذج {$letter}): ".count($this->fields).' حقلاً — '.$link);
    }

    /**
     * Append one field. The id is worked out from its position and label, not
     * drawn at random, so re-running this seeder to fix a typo does not leave
     * every answer already collected keyed to a question that no longer exists.
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

    /**
     * A scored multiple-choice item, its options shuffled with a seed fixed to
     * this item alone — so the correct answer's position varies from item to
     * item rather than settling into a letter a guesser could learn.
     *
     * @param  array<int, string>  $choices  in the source booklet's أ/ب/ج/د order
     */
    private function addMcq(string $stem, array $choices, int $correctIndex, string $dimension, int $seed, float $points = 1): void
    {
        $correctText = $choices[$correctIndex];

        mt_srand($seed);
        shuffle($choices);
        mt_srand(); // Leave the global generator unseeded for anything seeded after this.

        $this->add('mcq', $stem, true, options: $choices, extra: [
            'correct_option' => $correctText,
            'points' => $points,
            'dimension' => $dimension,
        ]);
    }

    /**
     * The 20-item self-report scale, verbatim from the booklet's §6, on its
     * own 1-4 wording rather than the app's default 1-5 agreement scale.
     * Items 4, 6, 8, 11, 15 and 18 are reverse-scored, exactly as the booklet's
     * own scoring note lists them.
     */
    private function addSelfRegulationItems(): void
    {
        $scaleLabels = [1 => 'لا يشبهني', 2 => 'يشبهني قليلًا', 3 => 'يشبهني غالبًا', 4 => 'يشبهني جدًا'];
        $reversed = [4, 6, 8, 11, 15, 18];

        $items = [
            'عندما لا أفهم شيئًا، أحاول بطريقة أخرى قبل أن أتركه.',
            'أستطيع إكمال المهمة حتى لو لم تكن ممتعة في كل أجزائها.',
            'أحب أن أعرف لماذا تحدث الأشياء.',
            'أحتاج غالبًا إلى شخص يذكرني بكل ما يجب علي فعله.',
            'عندما أخطئ أحاول معرفة سبب الخطأ.',
            'أبدأ أحيانًا أشياء كثيرة ولا أكملها.',
            'أستمتع بتعلم شيء لم أكن أعرفه.',
            'إذا كان العمل صعبًا أفضل تركه بسرعة.',
            'أستطيع تأجيل اللعب حتى أكمل مسؤولية مهمة.',
            'أحاول تحسين عملي عندما يعطيني المعلم ملاحظة.',
            'أعمل فقط عندما تكون هناك جائزة.',
            'أستطيع ترتيب ما أحتاج إلى عمله عندما تكون لدي عدة مهام.',
            'عندما أفشل من المرة الأولى أحاول مرة أخرى.',
            'أحب الأسئلة التي تحتاج إلى تفكير وليست إجابتها واضحة مباشرة.',
            'إذا لم يراقبني أحد يقل اهتمامي بالعمل كثيرًا.',
            'أستطيع التركيز على مهمة مهمة رغم وجود أشياء أخرى أحب فعلها.',
            'أسأل أو أبحث عندما يثير شيء فضولي.',
            'أجد صعوبة كبيرة في العودة للعمل بعد الاستراحة.',
            'عندما يكون عندي هدف طويل أحاول تقسيمه إلى خطوات.',
            'أتعلم بعض الأشياء لأنني أريد فهمها فعلًا، وليس فقط للحصول على درجة.',
        ];

        foreach ($items as $i => $label) {
            $number = $i + 1;

            $this->add('likert', $label, true, extra: [
                'scale_min' => 1,
                'scale_max' => 4,
                'scale_labels' => $scaleLabels,
                'reverse_scored' => in_array($number, $reversed, true),
                'dimension' => 'self_regulation',
            ]);
        }
    }

    /** @return array<int, array{0: string, 1: array<int, string>, 2: int}> */
    private function reasoningA(): array
    {
        return [
            ['2، 4، 8، 16، ؟', ['18', '24', '30', '32'], 3],
            ['3، 6، 9، 12، ؟', ['13', '14', '15', '16'], 2],
            ['1، 2، 4، 7، 11، ؟', ['14', '15', '16', '17'], 2],
            ['18، 15، 12، 9، ؟', ['8', '7', '6', '5'], 2],
            ['محمد أطول من سعد، وسعد أطول من علي. من الأقصر؟', ['محمد', 'سعد', 'علي', 'لا يمكن المعرفة'], 2],
            ['أحمد أمام خالد، وخالد أمام فهد في الطابور. من في الوسط؟', ['أحمد', 'خالد', 'فهد', 'لا أحد'], 1],
            ['عين : رؤية = أذن : ؟', ['سماع', 'حركة', 'كلام', 'شم'], 0],
            ['كتاب : قراءة = كرة : ؟', ['رياضة', 'نوم', 'رسم', 'حساب'], 0],
            ['كل الطيور لها أجنحة. العصفور طائر. ما النتيجة؟', ['كل ذي جناحين عصفور', 'العصفور له أجنحة', 'العصفور لا يطير', 'لا نتيجة'], 1],
            ['آلة تعمل بالقاعدة: 2←5، 3←7، 4←9. إذن 6←؟', ['10', '11', '12', '13'], 3],
            ['خمسة طلاب يحتاج كل واحد ورقتين. كم ورقة؟', ['5', '7', '10', '12'], 2],
            ['3 آلات تنتج 3 قطع في 3 دقائق، كل آلة تنتج قطعة واحدة في 3 دقائق. كم تنتج 6 آلات في 3 دقائق؟', ['3', '6', '9', '18'], 1],
            ['إذا كان اليوم الثلاثاء، فما اليوم بعد 10 أيام؟', ['الخميس', 'الجمعة', 'السبت', 'الأحد'], 1],
            ['4 كرات حمراء و1 زرقاء في الصندوق أ، و1 حمراء و4 زرقاء في ب. أين فرصة الأحمر أعلى؟', ['أ', 'ب', 'متساوية', 'لا يمكن المعرفة'], 0],
            ['إذا أصبحت كلمة «باب» إلى «بوب» باستبدال الحرف الأوسط بواو، فكيف تصبح «بيت»؟', ['بوت', 'بوي', 'ويت', 'بيت'], 0],
            ['أ، ب، أ، ب، أ، ب، ؟', ['أ', 'ب', 'ج', 'د'], 0],
            ['إذا كان كل أعضاء فريق النسر يرتدون قميصًا أزرق، وسالم عضو في فريق النسر، فما الصحيح؟', ['سالم يرتدي الأزرق', 'كل من يلبس الأزرق في الفريق', 'سالم قائد الفريق', 'لا نعرف شيئًا'], 0],
            ['أكمل العلاقة: مفتاح : باب = كلمة مرور : ؟', ['حساب', 'كتاب', 'طاولة', 'قلم'], 0],
            ['بدأت تجربة ولم تنجح بالطريقة الأولى. ما التفكير الأفضل؟', ['أكرر بلا تغيير', 'أتوقف', 'أبحث عن سبب الخطأ وأغير الطريقة', 'أختار جوابًا عشوائيًا'], 2],
            ['أي عدد لا ينتمي: 2، 4، 6، 9، 8؟', ['2', '6', '9', '8'], 2],
        ];
    }

    /** @return array<int, array{0: string, 1: array<int, string>, 2: int}> */
    private function reasoningB(): array
    {
        return [
            ['5، 10، 20، 40، ؟', ['45', '60', '70', '80'], 3],
            ['4، 8، 12، 16، ؟', ['18', '19', '20', '22'], 2],
            ['2، 5، 9، 14، 20، ؟', ['25', '26', '27', '28'], 2],
            ['30، 25، 20، 15، ؟', ['12', '10', '8', '5'], 1],
            ['نورة أسرع من هند، وهند أسرع من ريم. من الأبطأ؟', ['نورة', 'هند', 'ريم', 'لا يمكن المعرفة'], 2],
            ['سعيد خلف فهد، وفهد خلف مازن. من في الوسط؟', ['سعيد', 'فهد', 'مازن', 'لا أحد'], 1],
            ['أنف : شم = لسان : ؟', ['تذوق', 'سماع', 'رؤية', 'لمس'], 0],
            ['مقص : قص = فرشاة : ؟', ['رسم', 'قراءة', 'كتابة', 'قياس'], 0],
            ['كل المربعات أشكال. هذا الشكل مربع. إذن؟', ['هو شكل', 'كل الأشكال مربعات', 'ليس شكلًا', 'لا نتيجة'], 0],
            ['قاعدة: 1←4، 2←7، 3←10. إذن 5←؟', ['13', '14', '16', '18'], 2],
            ['7 أطفال، لكل واحد 3 بطاقات. المجموع؟', ['10', '18', '21', '24'], 2],
            ['4 صنابير تملأ 4 أوعية في 2 دقيقة، كل صنبور وعاء في دقيقتين. كم وعاء تملأ 8 صنابير في دقيقتين؟', ['4', '8', '12', '16'], 1],
            ['إذا كان اليوم الجمعة، فما اليوم بعد 9 أيام؟', ['السبت', 'الأحد', 'الاثنين', 'الثلاثاء'], 1],
            ['في كيس أ: 6 بيضاء و2 سوداء، وفي ب: 2 بيضاء و6 سوداء. أين فرصة البيضاء أعلى؟', ['أ', 'ب', 'متساوية', 'لا نعرف'], 0],
            ['القاعدة: غيّر أول حرف إلى م. «باب» تصبح؟', ['ماب', 'بام', 'مام', 'باب'], 0],
            ['ج، د، ج، د، ج، ؟', ['ج', 'د', 'هـ', 'و'], 3],
            ['كل طلاب المجموعة أ يقرؤون كتابًا أسبوعيًا. خالد من أ. ماذا نعلم؟', ['خالد يقرأ كتابًا أسبوعيًا', 'كل قارئ من أ', 'خالد يقرأ يوميًا', 'لا نعلم'], 0],
            ['بوصلة : اتجاه = ميزان : ؟', ['وزن', 'صوت', 'ضوء', 'لون'], 0],
            ['إذا أعطاك المعلم تلميحًا بعد فشل المحاولة الأولى، ما الأفضل؟', ['أطلب الحل', 'أستخدم التلميح وأحاول', 'أنسخ من زميل', 'أترك المهمة'], 1],
            ['أي عدد لا ينتمي: 3، 6، 9، 12، 14؟', ['3', '9', '12', '14'], 3],
        ];
    }

    /** @return array{passage: string, reading: array<int, string>, math: array<int, string>, writing: string} */
    private function readinessA(): array
    {
        return [
            'passage' => 'أراد فريق طلاب معرفة أثر مدة الضوء في نمو نبتة صغيرة. استخدموا ثلاث نبتات من النوع نفسه وفي أوعية متشابهة، وسقوها بكمية الماء نفسها. اختلف فقط عدد ساعات الضوء. بعد أسبوعين سجلوا طول كل نبتة.',
            'reading' => [
                'اقرأ ثم أجب: ما العامل الذي غيّره الطلاب في التجربة؟',
                'لماذا استخدموا النوع نفسه وكمية الماء نفسها؟',
                'إذا نمت نبتة أكثر من غيرها، هل يكفي ذلك للحكم على كل النباتات؟ ولماذا؟',
                'اقترح تحسينًا واحدًا للتجربة.',
                'اكتب استنتاجًا حذرًا يمكن قوله من هذه التجربة.',
            ],
            'math' => [
                'قسّم 24 قطعة بالتساوي على 6 طلاب. كم قطعة لكل طالب؟',
                'إذا قرأ طالب 12 صفحة يوميًا لمدة 5 أيام، فكم صفحة قرأ؟',
                'أيّهما أكبر: 3/4 أم 2/3؟ اشرح إجابتك باختصار.',
            ],
            'writing' => 'اكتب 3-4 أسطر تشرح فيها كيف تتعلم مهارة جديدة لم تجربها من قبل.',
        ];
    }

    /** @return array{passage: string, reading: array<int, string>, math: array<int, string>, writing: string} */
    private function readinessB(): array
    {
        return [
            'passage' => 'لاحظ طلاب أن مكعب سكر يذوب في الماء الدافئ أسرع من الماء البارد. كرروا التجربة مستخدمين الكمية نفسها من الماء والسكر، وغيّروا درجة حرارة الماء فقط.',
            'reading' => [
                'اقرأ ثم أجب: ما العامل الذي غيّره الطلاب في التجربة؟',
                'ما الأشياء التي ثبّتوها ولم يغيّروها؟',
                'لماذا يكرر العلماء التجربة أكثر من مرة؟',
                'اقترح قياسًا أدق من عبارة «ذاب بسرعة».',
                'ما الاستنتاج الذي يمكن قوله من هذه التجربة دون مبالغة؟',
            ],
            'math' => [
                '7 صناديق في كل صندوق 8 أقلام. ما مجموع الأقلام؟',
                'وزّع 36 بطاقة على 4 فرق بالتساوي. كم بطاقة لكل فريق؟',
                'رتّب الأعداد التالية من الأصغر إلى الأكبر: 0.5، 3/4، 0.25.',
            ],
            'writing' => 'اكتب 3-4 أسطر تقنع فيها فريقك بطريقة عادلة لاختيار فكرة مشروع من بين ثلاث أفكار.',
        ];
    }

    /** @return array<int, array{0: string, 1: array<int, string>, 2: int}> */
    private function sjtA(): array
    {
        return [
            ['بدأ نشاط صعب ولم تستطع الحل بعد 10 دقائق. ماذا تفعل؟', ['أترك النشاط', 'أنتظر من يعطيني الحل', 'أراجع المطلوب وأجرب طريقة أخرى ثم أطلب مساعدة محددة', 'أنقل إجابة زميلي'], 2],
            ['اختار فريقك فكرة غير فكرتك. ماذا تفعل؟', ['أرفض المشاركة', 'أظل أكرر أن فكرتي أفضل', 'أساعد الفريق وأعرض فكرتي لاحقًا بدليل إذا ظهرت حاجة', 'أتركهم يعملون'], 2],
            ['بقي 15 دقيقة للتسليم واكتشفت خطأ مهمًا. ماذا تفعل؟', ['أخفيه', 'أسلم كما هو', 'أحدد أهم تعديل ممكن وأخبر الفريق', 'أبدأ من الصفر'], 2],
            ['بدأت تتعب بعد عدة أنشطة. ماذا تفعل في الاستراحة؟', ['أقرر الانسحاب', 'أشرب وأهدأ وأرتب عودتي', 'أزعج الزملاء', 'أطلب إعفاء بلا محاولة'], 1],
            ['صحح المدرب إجابتك أمام المجموعة. ماذا تفعل؟', ['أجادله', 'أعتبره إهانة', 'أفهم سبب الخطأ وأعدل', 'أتوقف عن المشاركة'], 2],
            ['عضو في فريقك أبطأ من البقية. ماذا تفعل؟', ['أطلب إخراجه', 'أعمل مكانه بالكامل', 'أعرف أين يحتاج المساعدة وأعطيه دورًا مناسبًا', 'أتجاهله'], 2],
            ['أمامك مهمة سهلة وأخرى أصعب مناسبة لعمرك. أي سلوك أفضل للتعلم؟', ['السهل دائمًا', 'الأصعب لإثبات التفوق', 'أختار ما يعلمني وأقبل احتمال الخطأ', 'لا أختار'], 2],
            ['نسيت الخطوة الثانية من تعليمات من ثلاث خطوات. ماذا تفعل؟', ['أفعل أي شيء', 'أنتظر الجميع', 'أراجع التعليمات أو أسأل سؤالًا محددًا', 'أترك المهمة'], 2],
            ['اختلفت مع زميلك في مباراة. ماذا تفعل؟', ['أرفع صوتي', 'أترك غاضبًا', 'أرجع للقانون أو الحكم وأكمل', 'أنتقم لاحقًا'], 2],
            ['مشروعك بعد أسبوع. ماذا تفعل؟', ['أؤجله', 'أقسمه إلى خطوات وأبدأ مبكرًا', 'أنتظر تذكير أهلي', 'أطلب من غيري إنجازه'], 1],
        ];
    }

    /** @return array<int, array{0: string, 1: array<int, string>, 2: int}> */
    private function sjtB(): array
    {
        return [
            ['عندك سؤال لا تفهمه بعد محاولتين. ما الخطوة الأفضل؟', ['اختيار عشوائي', 'تحديد الجزء غير المفهوم وطلب توضيح محدد', 'ترك الورقة', 'انتظار الحل من زميل'], 1],
            ['في المشروع اقترح زميل فكرة أفضل من فكرتك. ماذا تفعل؟', ['أرفض', 'أقبلها وأساعد في تطويرها', 'أتوقف عن المشاركة', 'أحاول إفسادها'], 1],
            ['أخطأت أمام الفريق فتأخر العمل. ماذا تفعل؟', ['أخفي السبب', 'أوضح الخطأ وأقترح إصلاحًا', 'ألوم زميلًا', 'أترك المهمة'], 1],
            ['انتهت الرياضة وستبدأ حصة بعد 10 دقائق. ماذا تفعل؟', ['أستمر باللعب', 'أستخدم الوقت للتهدئة والماء والاستعداد', 'أتأخر عمدًا', 'أقول إنني متعب ولن أشارك'], 1],
            ['أعطاك المدرب ملاحظة لا توافقها. ماذا تفعل؟', ['أتجاهلها', 'أسأله عن المثال أو السبب ثم أقرر كيف أعدل', 'أغضب', 'أشتكي للطلاب'], 1],
            ['زميل لا يفهم جزءًا من المهمة. ماذا تفعل؟', ['أسخر', 'أشرح الجزء أو أقسم المهمة معه دون أن أفعلها كلها مكانه', 'أتجاهله', 'أطلب استبعاده'], 1],
            ['نجحت بطريقة سهلة لكن المدرب طلب تجربة طريقة أخرى. ماذا تفعل؟', ['أرفض', 'أجرب لمعرفة ما سأتعلمه', 'أترك المهمة', 'أكرر الطريقة نفسها فقط'], 1],
            ['عندك ثلاث مهام في الوقت نفسه. ماذا تفعل؟', ['أبدأ عشوائيًا', 'أرتب الأولوية والوقت ثم أبدأ', 'أؤجلها كلها', 'أطلب من أحد اختيار كل شيء لي'], 1],
            ['خسر فريقك بسبب قرار حكم تعتقد أنه خاطئ. ماذا تفعل؟', ['أصرخ', 'أحترم القرار وأكمل ثم أناقشه بهدوء إن لزم', 'أنسحب', 'أتعمد المخالفة'], 1],
            ['عندك هدف يحتاج شهرًا. ماذا تفعل؟', ['أنتظر آخر أسبوع', 'أقسمه لمراحل وأراجع التقدم', 'أفكر فيه دون خطة', 'أتركه إذا لم أحصل على جائزة'], 1],
        ];
    }
}
