const landing = {
  sections: [
    {
      id: 101,
      key: 'hero',
      type: 'hero',
      order: 1,
      title: {
        ar: 'قِس تميّزك المؤسسي',
        en: 'Measure your institutional excellence',
      },
      description: {
        ar: 'منصة مؤشر تُمكّن الجمعيات والمؤسسات الأهلية والأوقاف من قياس نضجها المؤسسي وفق نموذج التميّز في العمل الخيري، وكشف فجوات الأداء، والحصول على تقييم معتمد يدعم التحسين المستمر.',
        en: 'Moashir enables civil associations, civil foundations and endowments to measure their institutional maturity against the Charitable Work Excellence Model, uncover performance gaps and obtain an accredited assessment that supports continuous improvement.',
      },
      ctaLabel: {
        ar: 'ابدأ التقييم الذاتي مجاناً',
        en: 'Start your free self assessment',
      },
      ctaUrl: '/register',
      meta: {
        eyebrow: {
          ar: 'منصّة نموذج التميّز في العمل الخيري',
          en: 'The Charitable Work Excellence Model Platform',
        },
        highlight: {
          ar: 'وانطلق نحو الريادة',
          en: 'and lead the way',
        },
        scoreCaption: {
          ar: 'نتيجة التقييم المعتمد تحدد مستوى شهادتك.',
          en: 'Your accredited assessment result sets your certificate level.',
        },
      },
      items: [
        {
          id: 1001,
          order: 1,
          ctaLabel: {
            ar: 'استكشف مسارات التقييم',
            en: 'Explore the assessment paths',
          },
          ctaUrl: '#paths',
          meta: {
            role: 'secondary-cta',
          },
        },
      ],
    },
    {
      id: 102,
      key: 'stats',
      type: 'kpi_promo',
      order: 2,
      title: {
        ar: 'مؤشرات الأداء',
        en: 'Performance indicators',
      },
      ctaLabel: {
        ar: 'عرض كل المؤشرات',
        en: 'View all indicators',
      },
      ctaUrl: '/kpis',
      items: [],
    },
    {
      id: 103,
      key: 'about',
      type: 'about',
      order: 3,
      title: {
        ar: 'عن منصة مؤشر',
        en: 'About Moashir',
      },
      description: {
        ar: 'منصة وطنية لقياس نضج وتميز المنظمات غير الربحية وفق نموذج التميّز في العمل الخيري، وتوجيهها نحو أثر أعمق ومستدام.',
        en: 'A national platform that measures the maturity and excellence of non-profit organisations against the Charitable Work Excellence Model and guides them towards a deeper, sustainable impact.',
      },
      meta: {
        eyebrow: {
          ar: 'عن المنصة',
          en: 'About the platform',
        },
        highlight: {
          ar: 'ولماذا وُجدت',
          en: 'and why it exists',
        },
        keyWords: {
          ar: 'نضج، وتميز، أثر، أعمق، ومستدام',
          en: 'maturity, excellence, impact, deeper, sustainable',
        },
        audienceLabel: {
          ar: 'تخدم المنصة',
          en: 'The platform serves',
        },
        goalsTitle: {
          ar: 'أهداف المنصة',
          en: 'Platform goals',
        },
      },
      items: [
        {
          id: 1002,
          order: 1,
          title: {
            ar: 'الجمعيات الأهلية',
            en: 'Civil associations',
          },
          meta: {
            role: 'audience',
            glyph: 'people',
          },
        },
        {
          id: 1003,
          order: 2,
          title: {
            ar: 'المؤسسات الأهلية',
            en: 'Civil foundations',
          },
          meta: {
            role: 'audience',
            glyph: 'institution',
          },
        },
        {
          id: 1004,
          order: 3,
          title: {
            ar: 'الأوقاف',
            en: 'Endowments',
          },
          meta: {
            role: 'audience',
            glyph: 'endowment',
          },
        },
        {
          id: 1005,
          order: 4,
          description: {
            ar: 'قياس النضج المؤسسي للجهات غير الربحية بمنهجية موحّدة ومعتمدة.',
            en: 'Measure the institutional maturity of non-profit entities with a unified, approved methodology.',
          },
          meta: {
            role: 'goal',
            glyph: 'gauge',
          },
        },
        {
          id: 1006,
          order: 5,
          description: {
            ar: 'كشف فجوات الأداء وتحويلها إلى أولويات تحسين واضحة.',
            en: 'Uncover performance gaps and turn them into clear improvement priorities.',
          },
          meta: {
            role: 'goal',
            glyph: 'gap_bars',
          },
        },
        {
          id: 1007,
          order: 6,
          description: {
            ar: 'منح شهادات تميّز معتمدة يمكن التحقق منها.',
            en: 'Award accredited, verifiable excellence certificates.',
          },
          meta: {
            role: 'goal',
            glyph: 'medal',
          },
        },
        {
          id: 1008,
          order: 7,
          description: {
            ar: 'تمكين أصحاب القرار من قراءة النتائج عبر العدسات التحليلية.',
            en: 'Enable decision makers to read the results through the analytical lenses.',
          },
          meta: {
            role: 'goal',
            glyph: 'lens',
          },
        },
        {
          id: 1009,
          order: 8,
          description: {
            ar: 'تحفيز التنافس الإيجابي عبر جائزة التميّز في العمل الخيري.',
            en: 'Encourage positive competition through the Charitable Work Excellence Award.',
          },
          meta: {
            role: 'goal',
            glyph: 'trophy',
          },
        },
        {
          id: 1010,
          order: 9,
          description: {
            ar: 'الإسهام في رفع جودة أداء القطاع غير الربحي ومواءمته مع مستهدفات رؤية المملكة 2030.',
            en: 'Contribute to raising the performance quality of the non-profit sector and aligning it with the targets of Saudi Vision 2030.',
          },
          meta: {
            role: 'goal',
            glyph: 'growth',
          },
        },
      ],
    },
    {
      id: 104,
      key: 'model',
      type: 'excellence_model',
      order: 4,
      title: {
        ar: 'كيف يقيس مؤشر',
        en: 'How Moashir measures',
      },
      description: {
        ar: 'إطار مرجعي يقيس قدرة الجهة على التخطيط والتنفيذ والتعلّم وتحقيق النتائج المستدامة.',
        en: "A reference framework that measures the entity's ability to plan, deliver, learn and achieve sustainable results.",
      },
      meta: {
        eyebrow: {
          ar: 'الفكرة العامة',
          en: 'The general idea',
        },
        highlight: {
          ar: 'نضج جهتك؟',
          en: "your entity's maturity",
        },
      },
      items: [
        {
          id: 1011,
          order: 1,
          title: {
            ar: 'نموذج مرجعي',
            en: 'Reference model',
          },
          description: {
            ar: 'تسعة معايير في ثلاث مجموعات: الممكّنات، والإبداع والتعلّم، والنتائج.',
            en: 'Nine criteria in three groups: enablers, innovation and learning, and results.',
          },
          meta: {
            glyph: 'grid',
          },
        },
        {
          id: 1012,
          order: 2,
          title: {
            ar: 'تقييم',
            en: 'Assessment',
          },
          description: {
            ar: 'إجابات الجهة، وفي المسار المعتمد شواهد يراجعها مقيّم مؤهل.',
            en: "The entity's answers and, in the accredited path, evidence reviewed by a qualified assessor.",
          },
          meta: {
            glyph: 'clipboard',
          },
        },
        {
          id: 1013,
          order: 3,
          title: {
            ar: 'نتيجة',
            en: 'Result',
          },
          description: {
            ar: 'نسبة لكل معيار ونتيجة كلية تحدد مستوى التميّز.',
            en: 'A percentage for every criterion and an overall result that sets the excellence level.',
          },
          meta: {
            glyph: 'gauge',
          },
        },
        {
          id: 1014,
          order: 4,
          title: {
            ar: 'تحسين',
            en: 'Improvement',
          },
          description: {
            ar: 'تقرير تغذية راجعة، وخطة لمعالجة الفجوات عبر حقيبة التميّز السريع.',
            en: 'A feedback report and a plan to close the gaps through the Quick Excellence Kit.',
          },
          meta: {
            glyph: 'growth',
          },
        },
      ],
    },
    {
      id: 105,
      key: 'paths',
      type: 'paths',
      order: 5,
      title: {
        ar: 'اختر المسار',
        en: 'Choose the path',
      },
      description: {
        ar: 'ثلاثة مسارات مستقلة، لكل منها فكرته ونتيجته.',
        en: 'Three independent paths, each with its own idea and outcome.',
      },
      meta: {
        eyebrow: {
          ar: 'مسارات التقييم',
          en: 'Assessment paths',
        },
        highlight: {
          ar: 'المناسب لمرحلة جهتك',
          en: "that suits your entity's stage",
        },
        ideaLabel: {
          ar: 'الفكرة',
          en: 'The idea',
        },
        costLabel: {
          ar: 'التكلفة',
          en: 'Cost',
        },
        freeLabel: {
          ar: 'مجاني',
          en: 'Free',
        },
      },
      items: [
        {
          id: 1015,
          order: 1,
          title: {
            ar: 'التقييم الذاتي',
            en: 'Self assessment',
          },
          subtitle: {
            ar: 'تشخيص أولي سريع لمستوى نضج جهتك وفق معايير النموذج.',
            en: "A quick initial diagnosis of your entity's maturity against the model criteria.",
          },
          description: {
            ar: 'تقيّم جهتك نفسها بدون شواهد، وتظهر نتيجة استرشادية فوراً، ويمكن إعادته في أي وقت.',
            en: 'Your entity assesses itself without evidence, gets an indicative result instantly and can repeat it at any time.',
          },
          ctaLabel: {
            ar: 'ابدأ التقييم الذاتي',
            en: 'Start the self assessment',
          },
          ctaUrl: '/assessment/self',
          meta: {
            path: 'self',
            costNote: {
              ar: 'مجاني',
              en: 'Free',
            },
          },
        },
        {
          id: 1016,
          order: 2,
          title: {
            ar: 'التقييم المعتمد',
            en: 'Accredited assessment',
          },
          subtitle: {
            ar: 'تقييم رسمي يراجعه مقيّمون مؤهلون بناءً على شواهد جهتك.',
            en: "An official assessment reviewed by qualified assessors based on your entity's evidence.",
          },
          description: {
            ar: 'يراجع مقيّم مؤهل شواهدك، فتحصل على تقرير تغذية راجعة وشهادة بمستوى التميّز. يتكرر كل فترة.',
            en: 'A qualified assessor reviews your evidence, and you receive a feedback report and a certificate showing your excellence level. It is repeated periodically.',
          },
          ctaLabel: {
            ar: 'اطلب التقييم المعتمد',
            en: 'Request the accredited assessment',
          },
          ctaUrl: '/assessment/accredited',
          meta: {
            path: 'accredited',
            costNote: {
              ar: 'رسوم التقييم + ضريبة القيمة المضافة',
              en: 'Assessment fee + VAT',
            },
          },
        },
        {
          id: 1017,
          order: 3,
          title: {
            ar: 'الجائزة (التقييم المتقدم)',
            en: 'The Award (advanced assessment)',
          },
          subtitle: {
            ar: 'مسار تنافسي ضمن جائزة التميّز في العمل الخيري.',
            en: 'A competitive path within the Charitable Work Excellence Award.',
          },
          description: {
            ar: 'تتنافس الجهات المؤهلة ضمن دورة سنوية، وتقيّمها لجنة مقيّمين، وتُعلن النتائج على مستوى المنطقة والمملكة.',
            en: 'Eligible entities compete in an annual cycle, are assessed by an assessors committee, and results are announced at the regional and national levels.',
          },
          ctaLabel: {
            ar: 'قدّم على الجائزة',
            en: 'Apply for the Award',
          },
          ctaUrl: '/assessment/advanced',
          meta: {
            path: 'advanced',
            costNote: {
              ar: 'رسوم المشاركة حسب الدورة',
              en: 'Participation fee per cycle',
            },
          },
        },
      ],
    },
    {
      id: 106,
      key: 'journey',
      type: 'how_it_works',
      order: 6,
      title: {
        ar: 'من التسجيل',
        en: 'From registration',
      },
      description: {
        ar: 'مرّر لترى كل خطوة. الوسم تحت كل خطوة يوضح المسارات التي تنطبق عليها.',
        en: 'Scroll to see every step. The tag under each step shows the paths it applies to.',
      },
      meta: {
        variant: 'journey',
        eyebrow: {
          ar: 'رحلتك خطوة بخطوة',
          en: 'Your journey step by step',
        },
        highlight: {
          ar: 'إلى التحسين المستمر',
          en: 'to continuous improvement',
        },
        stepLabel: {
          ar: 'الخطوة',
          en: 'Step',
        },
        tagAll: {
          ar: 'كل المسارات',
          en: 'All paths',
        },
        tagAccredited: {
          ar: 'المعتمد',
          en: 'Accredited',
        },
        tagAward: {
          ar: 'الجائزة',
          en: 'Award',
        },
      },
      items: [
        {
          id: 1018,
          order: 1,
          title: {
            ar: 'أنشئ حساب جهتك',
            en: 'Create your entity account',
          },
          description: {
            ar: 'سجّل الجهة وأكمل ملفها التعريفي.',
            en: 'Register the entity and complete its profile.',
          },
          meta: {
            tags: ['all'],
            screen: 'register',
          },
        },
        {
          id: 1019,
          order: 2,
          title: {
            ar: 'اختر المسار المناسب',
            en: 'Choose the right path',
          },
          description: {
            ar: 'ثلاثة مسارات مستقلة؛ اختر ما يناسب مرحلة جهتك.',
            en: "Three independent paths; choose the one that suits your entity's stage.",
          },
          meta: {
            tags: ['all'],
            screen: 'choose_path',
          },
        },
        {
          id: 1020,
          order: 3,
          title: {
            ar: 'نفّذ التقييم',
            en: 'Complete the assessment',
          },
          description: {
            ar: 'أجب عن معايير النموذج؛ وفي المعتمد والجائزة ارفع الشواهد لكل معيار فرعي مع تحقق آلي من سلامة الملفات.',
            en: 'Answer the model criteria; in the accredited path and the Award, upload the evidence for every sub-criterion with an automatic file safety check.',
          },
          meta: {
            tags: ['all'],
            screen: 'evidence',
          },
        },
        {
          id: 1021,
          order: 4,
          title: {
            ar: 'المراجعة',
            en: 'Review',
          },
          description: {
            ar: 'يراجع مقيّم مؤهل طلبك المعتمد، أو لجنة المقيّمين في الجائزة، وقد يُطلب منك استكمال.',
            en: 'A qualified assessor reviews your accredited request, or the assessors committee in the Award, and you may be asked to complete missing items.',
          },
          meta: {
            tags: ['accredited', 'award'],
            screen: 'review',
          },
        },
        {
          id: 1022,
          order: 5,
          title: {
            ar: 'النتيجة والشهادة',
            en: 'Result and certificate',
          },
          description: {
            ar: 'نتيجتك وتقرير التغذية الراجعة، وشهادة بمستوى التميّز في المسار المعتمد. نتيجة التقييم الذاتي فورية واسترشادية.',
            en: 'Your result and feedback report, and a certificate showing your excellence level in the accredited path. The self assessment result is instant and indicative.',
          },
          meta: {
            tags: ['all'],
            screen: 'result',
          },
        },
        {
          id: 1023,
          order: 6,
          title: {
            ar: 'التحسين المستمر',
            en: 'Continuous improvement',
          },
          description: {
            ar: 'بعد نتيجة التقييم المعتمد اطلب حقيبة التميّز السريع لتحويل الفجوات إلى خطة مرحلية.',
            en: 'After the accredited assessment result, request the Quick Excellence Kit to turn the gaps into a phased plan.',
          },
          meta: {
            tags: ['accredited'],
            screen: 'improvement',
          },
        },
      ],
    },
    {
      id: 107,
      key: 'mid_cta',
      type: 'rich_text',
      order: 7,
      title: {
        ar: 'جاهز تقيس نضج جهتك؟',
        en: "Ready to measure your entity's maturity?",
      },
      description: {
        ar: 'اختر المسار المناسب وابدأ اليوم.',
        en: 'Choose the right path and start today.',
      },
      ctaLabel: {
        ar: 'ابدأ التقييم الذاتي',
        en: 'Start the self assessment',
      },
      ctaUrl: '/assessment/self',
      meta: {
        variant: 'band',
      },
      items: [
        {
          id: 1024,
          order: 1,
          ctaLabel: {
            ar: 'اطلب التقييم المعتمد',
            en: 'Request the accredited assessment',
          },
          ctaUrl: '/assessment/accredited',
          meta: {
            role: 'secondary-cta',
          },
        },
      ],
    },
    {
      id: 108,
      key: 'levels',
      type: 'levels',
      order: 8,
      title: {
        ar: 'شهادة معتمدة',
        en: 'An accredited certificate',
      },
      description: {
        ar: 'نتيجة التقييم المعتمد تحدد مستوى شهادتك. حرّك المؤشر لترى أين تقع كل نتيجة.',
        en: 'Your accredited assessment result sets your certificate level. Move the slider to see where each result falls.',
      },
      meta: {
        eyebrow: {
          ar: 'مستويات التميّز',
          en: 'Excellence levels',
        },
        highlight: {
          ar: 'تعكس مستوى تميّزك',
          en: 'that reflects your excellence level',
        },
        resultLabel: {
          ar: 'نتيجة التقييم المعتمد',
          en: 'Accredited assessment result',
        },
        hint: {
          ar: 'اسحب المؤشر أو استخدم مفاتيح الأسهم.',
          en: 'Drag the slider or use the arrow keys.',
        },
        verifyNote: {
          ar: 'كل شهادة تحمل رمز تحقق ورمز QR للتأكد من صحتها.',
          en: 'Every certificate carries a verification code and a QR code to confirm its authenticity.',
        },
        settingsNote: {
          ar: 'الحدود المعروضة هي الإعداد الحالي، وتضبطها إدارة المنصة من إعدادات الشهادات.',
          en: 'The thresholds shown are the current settings, managed by the platform administration in the certificate settings.',
        },
        sampleScore: 84,
      },
      items: [],
    },
    {
      id: 109,
      key: 'kit',
      type: 'bag_promo',
      order: 9,
      title: {
        ar: 'أدوات تصنع الفرق',
        en: 'Tools that make a difference',
      },
      description: {
        ar: 'تُتاح حقيبة التميّز السريع بعد صدور نتيجة التقييم المعتمد، لتحويل الفجوات إلى خطة تحسين مرحلية.',
        en: 'The Quick Excellence Kit becomes available once the accredited assessment result is issued, to turn the gaps into a phased improvement plan.',
      },
      ctaLabel: {
        ar: 'تعرّف على الحقيبة',
        en: 'Learn about the Kit',
      },
      ctaUrl: '#contact',
      meta: {
        eyebrow: {
          ar: 'حقيبة التميّز السريع',
          en: 'Quick Excellence Kit',
        },
        highlight: {
          ar: 'في رحلتك',
          en: 'in your journey',
        },
        noticeTitle: {
          ar: 'تتطلب الحقيبة تقييماً معتمداً مكتملاً',
          en: 'The Kit requires a completed accredited assessment',
        },
        noticeText: {
          ar: 'لا تُطلب الحقيبة إلا بعد تقييم معتمد مكتمل، لأنها تقدّم خريطة معالجة الفجوات الناتجة عنه.',
          en: 'The Kit can only be requested after a completed accredited assessment, because it provides the gap treatment map that results from it.',
        },
        howLabel: {
          ar: 'كيف تعمل؟',
          en: 'How does it work?',
        },
        howText: {
          ar: 'تحسب المنصة الفجوة بين الدرجة المستهدفة والفعلية لكل معيار فرعي، ويرتّب المقيّم أولوياتها في مراحل تُفتح تدريجياً مع النماذج والأدلة الداعمة.',
          en: 'The platform calculates the gap between the target and the actual score for every sub-criterion, and the assessor arranges the priorities into stages that open gradually with supporting templates and guides.',
        },
      },
      items: [
        {
          id: 1025,
          order: 1,
          title: {
            ar: 'نتيجة معتمدة',
            en: 'Accredited result',
          },
          description: {
            ar: 'نتيجة التقييم المعتمد المكتمل لجهتك.',
            en: "The result of your entity's completed accredited assessment.",
          },
          meta: {
            role: 'step',
            glyph: 'shield',
          },
        },
        {
          id: 1026,
          order: 2,
          title: {
            ar: 'الفجوات',
            en: 'Gaps',
          },
          description: {
            ar: 'الفرق بين الدرجة المستهدفة والفعلية لكل معيار فرعي.',
            en: 'The difference between the target and the actual score for every sub-criterion.',
          },
          meta: {
            role: 'step',
            glyph: 'gap_bars',
          },
        },
        {
          id: 1027,
          order: 3,
          title: {
            ar: 'مراحل التحسين',
            en: 'Improvement stages',
          },
          description: {
            ar: 'يرتّب المقيّم الأولويات في مراحل تُفتح تدريجياً.',
            en: 'The assessor arranges the priorities into stages that open gradually.',
          },
          meta: {
            role: 'step',
            glyph: 'stairs',
          },
        },
        {
          id: 1028,
          order: 4,
          title: {
            ar: 'متابعة التقدّم',
            en: 'Progress tracking',
          },
          description: {
            ar: 'تتابع جهتك إنجاز كل مرحلة حتى اكتمال الخطة.',
            en: 'Your entity tracks the completion of every stage until the plan is complete.',
          },
          meta: {
            role: 'step',
            glyph: 'cycle',
          },
        },
        {
          id: 1029,
          order: 5,
          title: {
            ar: 'تقييم قابل للقياس',
            en: 'Measurable assessment',
          },
          description: {
            ar: 'أولويات تحسين واضحة مأخوذة من نتيجة التقييم.',
            en: 'Clear improvement priorities drawn from the assessment result.',
          },
          meta: {
            role: 'tool',
            glyph: 'target',
          },
        },
        {
          id: 1030,
          order: 6,
          title: {
            ar: 'نماذج وأدلة مساعدة',
            en: 'Supporting templates and guides',
          },
          description: {
            ar: 'أدلة ونماذج عملية تساعد على التنفيذ.',
            en: 'Practical guides and templates that help with implementation.',
          },
          meta: {
            role: 'tool',
            glyph: 'document',
          },
        },
        {
          id: 1031,
          order: 7,
          title: {
            ar: 'تحسين مبني على الفجوات',
            en: 'Gap-based improvement',
          },
          description: {
            ar: 'خطة مرحلية لمعالجة الفجوات ومتابعة التقدم.',
            en: 'A phased plan to close the gaps and track progress.',
          },
          meta: {
            role: 'tool',
            glyph: 'stairs',
          },
        },
        {
          id: 1032,
          order: 8,
          title: {
            ar: 'خريطة معالجة الفجوات',
            en: 'Gap treatment map',
          },
          description: {
            ar: 'تُبنى من نتيجة تقييمك المعتمد المكتمل، وترتّب الفجوات في مراحل واضحة.',
            en: 'Built from your completed accredited assessment result, it arranges the gaps into clear stages.',
          },
          meta: {
            role: 'tool',
            glyph: 'map',
          },
        },
      ],
    },
    {
      id: 110,
      key: 'lenses',
      type: 'lenses_brief',
      order: 10,
      title: {
        ar: 'العدسات التحليلية',
        en: 'Analytical lenses',
      },
      description: {
        ar: 'لوحات تحليلية تساعد أصحاب القرار على قراءة نتائج التقييم المعتمد والمتقدم من زوايا متعددة.',
        en: 'Analytical dashboards that help decision makers read accredited and advanced assessment results from multiple angles.',
      },
      ctaLabel: {
        ar: 'استعرض العدسات التحليلية',
        en: 'Explore the analytical lenses',
      },
      ctaUrl: '/lenses',
      meta: {
        eyebrow: {
          ar: 'لأصحاب القرار',
          en: 'For decision makers',
        },
        note: {
          ar: 'لا تعتمد العدسات على التقييم الذاتي، وتعتمد على آخر تقييم معتمد أو متقدم.',
          en: 'The lenses do not use the self assessment; they rely on the latest accredited or advanced assessment.',
        },
      },
      items: [
        {
          id: 1033,
          order: 1,
          title: {
            ar: 'عدسة المانح',
            en: 'Grantors and donors lens',
          },
          description: {
            ar: 'تقرأ الأداء من زاوية الجهات المانحة لدعم قرارات التمويل والدعم.',
            en: 'Reads performance from the perspective of grantors to support funding and support decisions.',
          },
        },
        {
          id: 1034,
          order: 2,
          title: {
            ar: 'عدسة الجهات الإشرافية',
            en: 'Supervisory bodies lens',
          },
          description: {
            ar: 'تعرض الأداء من منظور الجهات الإشرافية والتنظيمية.',
            en: 'Presents performance from the perspective of supervisory and regulatory bodies.',
          },
        },
        {
          id: 1035,
          order: 3,
          title: {
            ar: 'عدسة المجتمع',
            en: 'Community lens',
          },
          description: {
            ar: 'تبرز أثر المنظمة وقيمتها المضافة على المجتمع.',
            en: "Highlights the organisation's impact and added value to the community.",
          },
        },
        {
          id: 1036,
          order: 4,
          title: {
            ar: 'عدسة برامج رؤية 2030',
            en: 'Vision 2030 programs lens',
          },
          description: {
            ar: 'تربط أداء الجهة بمستهدفات ومبادرات برامج رؤية المملكة 2030.',
            en: "Links the entity's performance to the targets and initiatives of the Saudi Vision 2030 programs.",
          },
        },
        {
          id: 1037,
          order: 5,
          title: {
            ar: 'العدسة الاستراتيجية',
            en: 'Strategic lens',
          },
          description: {
            ar: 'تحليل استراتيجي شامل يدعم التوجيه واتخاذ القرار على المدى البعيد.',
            en: 'A comprehensive strategic analysis that supports long-term direction and decision making.',
          },
        },
      ],
    },
    {
      id: 111,
      key: 'leaders',
      type: 'leadership_promo',
      order: 11,
      title: {
        ar: 'مسار القيادات',
        en: 'Leadership path',
      },
      description: {
        ar: 'مسار تطويري مخصص لقياس وتمكين القيادات داخل المنظمات غير الربحية، بما يساعد على رفع كفاءة القيادة المؤسسية وتعزيز جاهزية الصف القيادي.',
        en: 'A development path dedicated to measuring and empowering leaders within non-profit organisations, helping to raise institutional leadership effectiveness and strengthen the readiness of the leadership pipeline.',
      },
      meta: {
        eyebrow: {
          ar: 'مسار جديد',
          en: 'New path',
        },
        availability: 'coming_soon',
        badgeLabel: {
          ar: 'قريباً',
          en: 'Coming soon',
        },
      },
      items: [],
    },
    {
      id: 112,
      key: 'partners',
      type: 'partners',
      order: 12,
      enabled: false,
      title: {
        ar: 'شركاء النجاح',
        en: 'Success partners',
      },
      description: {
        ar: 'نفخر بشراكاتنا التي تسهم في تعزيز التميز المؤسسي ورفع جودة الأداء في القطاع غير الربحي.',
        en: 'We take pride in our partnerships that help strengthen institutional excellence and raise the quality of performance in the non-profit sector.',
      },
      meta: {
        eyebrow: {
          ar: 'شركاء النجاح',
          en: 'Success partners',
        },
      },
      items: [
        {
          id: 1038,
          order: 1,
          title: {
            ar: 'المركز الوطني لتنمية القطاع غير الربحي',
            en: 'National Center for Non-Profit Sector Development',
          },
        },
        {
          id: 1039,
          order: 2,
          title: {
            ar: 'وزارة الاتصالات وتقنية المعلومات',
            en: 'Ministry of Communications and Information Technology',
          },
        },
        {
          id: 1040,
          order: 3,
          title: {
            ar: 'وزارة الصحة',
            en: 'Ministry of Health',
          },
        },
        {
          id: 1041,
          order: 4,
          title: {
            ar: 'وزارة التعليم',
            en: 'Ministry of Education',
          },
        },
      ],
    },
    {
      id: 113,
      key: 'start',
      type: 'rich_text',
      order: 13,
      title: {
        ar: 'جاهز لتبدأ رحلة التميّز؟',
        en: 'Ready to start your excellence journey?',
      },
      description: {
        ar: 'سجّل جهتك واختر المسار المناسب لها: قياس ذاتي سريع، أو تقييم معتمد بشهادة، أو المنافسة على الجائزة.',
        en: 'Register your entity and choose the path that suits it: a quick self measurement, an accredited assessment with a certificate, or competing for the Award.',
      },
      ctaLabel: {
        ar: 'أنشئ حساب جهتك',
        en: 'Create your entity account',
      },
      ctaUrl: '/register',
      meta: {
        variant: 'cta',
        eyebrow: {
          ar: 'ابدأ الآن',
          en: 'Get started',
        },
      },
      items: [],
    },
    {
      id: 114,
      key: 'contact',
      type: 'contact',
      order: 14,
      title: {
        ar: 'نحن هنا لخدمتك',
        en: 'We are here to help',
      },
      description: {
        ar: 'أرسل استفسارك عن مسارات التقييم أو خدمات المنصة، وسيصلك رقم مرجعي ورد من فريقنا على بريدك الإلكتروني.',
        en: 'Send your enquiry about the assessment paths or the platform services, and you will receive a reference number and a reply from our team by email.',
      },
      meta: {
        eyebrow: {
          ar: 'تواصل معنا',
          en: 'Contact us',
        },
        emailLabel: {
          ar: 'البريد الإلكتروني',
          en: 'Email',
        },
        phoneLabel: {
          ar: 'الهاتف / واتساب',
          en: 'Phone / WhatsApp',
        },
        addressLabel: {
          ar: 'العنوان',
          en: 'Address',
        },
        socialLabel: {
          ar: 'تابعنا',
          en: 'Follow us',
        },
      },
      items: [],
    },
    {
      id: 115,
      key: 'assistant',
      type: 'assistant',
      order: 15,
      title: {
        ar: 'مساعد مؤشر',
        en: 'Moashir assistant',
      },
      description: {
        ar: 'اسأل عن مسارات التقييم، المعايير، آلية التسجيل، أو طريقة استخدام المنصة.',
        en: 'Ask about the assessment paths, the criteria, how to register or how to use the platform.',
      },
      ctaLabel: {
        ar: 'تواصل معنا',
        en: 'Contact us',
      },
      ctaUrl: '#contact',
      meta: {
        inputLabel: {
          ar: 'اكتب سؤالك',
          en: 'Type your question',
        },
        fallbackReply: {
          ar: 'يجيب مساعد مؤشر عن الأسئلة الشائعة فقط، ولم يجد إجابة عن سؤالك. تواصل مع فريق المنصة وسيصلك الرد على بريدك الإلكتروني.',
          en: 'The Moashir assistant answers common questions only and could not find an answer to yours. Please contact the platform team and you will receive a reply by email.',
        },
      },
      items: [
        {
          id: 1042,
          order: 1,
          title: {
            ar: 'ما الفرق بين المسارات؟',
            en: 'What is the difference between the paths?',
          },
          description: {
            ar: 'التقييم الذاتي مجاني: تقيّم جهتك نفسها بدون شواهد، وتظهر نتيجة استرشادية فوراً. في التقييم المعتمد يراجع مقيّم مؤهل شواهدك، فتحصل على تقرير تغذية راجعة وشهادة بمستوى التميّز. أما الجائزة (التقييم المتقدم) فمسار تنافسي تتنافس فيه الجهات المؤهلة ضمن دورة سنوية، وتقيّمها لجنة مقيّمين.',
            en: 'The self assessment is free: your entity assesses itself without evidence and gets an indicative result instantly. In the accredited assessment, a qualified assessor reviews your evidence, and you receive a feedback report and a certificate showing your excellence level. The Award (advanced assessment) is a competitive path in which eligible entities compete in an annual cycle and are assessed by an assessors committee.',
          },
        },
        {
          id: 1043,
          order: 2,
          title: {
            ar: 'كيف أسجّل جهتي؟',
            en: 'How do I register my entity?',
          },
          description: {
            ar: 'أنشئ حساب جهتك وأكمل ملفها التعريفي، ثم اختر المسار المناسب لمرحلة جهتك وابدأ التقييم.',
            en: "Create your entity account and complete its profile, then choose the path that suits your entity's stage and start the assessment.",
          },
        },
        {
          id: 1044,
          order: 3,
          title: {
            ar: 'ما حقيبة التميّز السريع؟',
            en: 'What is the Quick Excellence Kit?',
          },
          description: {
            ar: 'أدوات تُتاح بعد صدور نتيجة التقييم المعتمد المكتمل، لتحويل الفجوات إلى خطة تحسين مرحلية: تحسب المنصة الفجوة بين الدرجة المستهدفة والفعلية لكل معيار فرعي، ويرتّب المقيّم أولوياتها في مراحل تُفتح تدريجياً مع النماذج والأدلة الداعمة.',
            en: 'A set of tools available once a completed accredited assessment result is issued, to turn the gaps into a phased improvement plan: the platform calculates the gap between the target and the actual score for every sub-criterion, and the assessor arranges the priorities into stages that open gradually with supporting templates and guides.',
          },
        },
      ],
    },
  ],
  headerLinks: [
    {
      label: {
        ar: 'نموذج التميّز',
        en: 'Excellence model',
      },
      url: '/#model',
    },
    {
      label: {
        ar: 'مسارات التقييم',
        en: 'Assessment paths',
      },
      url: '/#paths',
    },
    {
      label: {
        ar: 'مستويات التميّز',
        en: 'Excellence levels',
      },
      url: '/#levels',
    },
    {
      label: {
        ar: 'حقيبة التميز السريع',
        en: 'Quick Excellence Kit',
      },
      url: '/#kit',
    },
    {
      label: {
        ar: 'العدسات التحليلية',
        en: 'Analytical lenses',
      },
      url: '/#lenses',
    },
    {
      label: {
        ar: 'تواصل معنا',
        en: 'Contact us',
      },
      url: '/#contact',
    },
  ],
  quickLinks: [
    {
      label: {
        ar: 'نموذج التميّز',
        en: 'Excellence model',
      },
      url: '#model',
    },
    {
      label: {
        ar: 'مسارات التقييم',
        en: 'Assessment paths',
      },
      url: '#paths',
    },
    {
      label: {
        ar: 'مستويات التميّز',
        en: 'Excellence levels',
      },
      url: '#levels',
    },
    {
      label: {
        ar: 'حقيبة التميز السريع',
        en: 'Quick Excellence Kit',
      },
      url: '#kit',
    },
    {
      label: {
        ar: 'العدسات التحليلية',
        en: 'Analytical lenses',
      },
      url: '#lenses',
    },
    {
      label: {
        ar: 'تواصل معنا',
        en: 'Contact us',
      },
      url: '#contact',
    },
  ],
  title: {
    ar: 'منصة مؤشر — نموذج التميّز في العمل الخيري',
    en: 'Moashir — Charitable Work Excellence Model',
  },
  seo: {
    title: {
      ar: 'منصة مؤشر — نموذج التميّز في العمل الخيري',
      en: 'Moashir — Charitable Work Excellence Model',
    },
    description: {
      ar: 'منصة مؤشر تُمكّن الجمعيات والمؤسسات الأهلية والأوقاف من قياس نضجها المؤسسي وفق نموذج التميّز في العمل الخيري.',
      en: 'Moashir enables civil associations, civil foundations and endowments to measure their institutional maturity against the Charitable Work Excellence Model.',
    },
  },
}

export const landingHomeTitle = landing.title
export const landingHomeSeo = landing.seo
export const landingHeaderLinks = landing.headerLinks
export const landingQuickLinks = landing.quickLinks
export const landingSectionDefinitions = landing.sections
