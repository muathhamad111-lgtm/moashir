export const CONTENT_PLACEHOLDER = true

export const modelCriteriaFixture = [
  { order: 1, weight: 1000, type: 'enabler', name: { ar: 'القيادة الإدارية', en: 'Administrative Leadership' } },
  { order: 2, weight: 900, type: 'enabler', name: { ar: 'التخطيط الاستراتيجي', en: 'Strategic Planning' } },
  {
    order: 3,
    weight: 800,
    type: 'enabler',
    name: { ar: 'إدارة الموارد البشرية والمتطوعين', en: 'Human Resources & Volunteers Management' },
  },
  {
    order: 4,
    weight: 700,
    type: 'enabler',
    name: { ar: 'إدارة الموارد والشراكات', en: 'Resources & Partnerships Management' },
  },
  {
    order: 5,
    weight: 1200,
    type: 'enabler',
    name: { ar: 'إدارة العمليات والخدمات', en: 'Processes & Services Management' },
  },
  {
    order: 6,
    weight: 600,
    type: 'enabler',
    name: { ar: 'الابتكار وإدارة المعرفة', en: 'Innovation & Knowledge Management' },
  },
  { order: 7, weight: 700, type: 'result', name: { ar: 'نتائج الموارد البشرية', en: 'People Results' } },
  { order: 8, weight: 1200, type: 'result', name: { ar: 'نتائج المستفيدين', en: 'Beneficiary Results' } },
  { order: 9, weight: 1100, type: 'result', name: { ar: 'نتائج الأداء الرئيسية', en: 'Key Performance Results' } },
]

function emptyBilingual() {
  return { ar: '', en: '' }
}

function section(overrides) {
  return {
    id: overrides.id,
    key: overrides.key,
    type: overrides.type ?? overrides.key,
    enabled: overrides.enabled ?? true,
    order: overrides.order,
    title: overrides.title ?? emptyBilingual(),
    subtitle: overrides.subtitle ?? emptyBilingual(),
    description: overrides.description ?? emptyBilingual(),
    ctaLabel: overrides.ctaLabel ?? emptyBilingual(),
    ctaUrl: overrides.ctaUrl ?? '',
    image: overrides.image ?? '',
    icon: overrides.icon ?? '',
    background: overrides.background ?? '',
    items: overrides.items ?? [],
    meta: overrides.meta ?? {},
  }
}

function item(overrides) {
  return {
    id: overrides.id,
    order: overrides.order,
    icon: overrides.icon ?? '',
    image: overrides.image ?? '',
    title: overrides.title ?? emptyBilingual(),
    description: overrides.description ?? emptyBilingual(),
    ctaLabel: overrides.ctaLabel ?? emptyBilingual(),
    ctaUrl: overrides.ctaUrl ?? '',
    meta: overrides.meta ?? {},
  }
}

const heroSection = section({
  id: 'sec-hero',
  key: 'hero',
  order: 1,
  title: {
    ar: 'منصة مؤشر للتميز المؤسسي',
    en: 'Muasher Platform for Institutional Excellence',
  },
  subtitle: {
    ar: 'منصة رقمية متكاملة لقياس نضج المنظمات غير الربحية، وإدارة مسارات التقييم، وتحليل نتائج التميز المؤسسي بموثوقية واحترافية.',
    en: 'An integrated digital platform to measure the maturity of non-profit organizations, manage assessment paths, and analyze institutional excellence results with reliability and professionalism.',
  },
  description: {
    ar: 'تساعد منصة مؤشر الجمعيات والمنظمات غير الربحية على فهم مستوى جاهزيتها المؤسسية، ورفع جودة الأداء، والوصول إلى نتائج تقييم واضحة تدعم التحسين المستمر واتخاذ القرار.',
    en: 'Muasher helps associations and non-profit organizations understand their institutional readiness, raise performance quality, and reach clear assessment results that support continuous improvement and decision-making.',
  },
  ctaLabel: { ar: 'ابدأ التقييم', en: 'Start assessment' },
  ctaUrl: '/register',
  image: '/generated/hero.jpg',
  icon: 'fa-solid fa-chart-line',
  background: 'gradient-brand',
  items: [
    item({
      id: 'hero-cta-secondary',
      order: 1,
      title: { ar: 'استكشف مسارات التقييم', en: 'Explore assessment paths' },
      ctaLabel: { ar: 'استكشف مسارات التقييم', en: 'Explore assessment paths' },
      ctaUrl: '#evaluation-paths',
      meta: { role: 'secondary-cta' },
    }),
  ],
})

const evaluationPathsSection = section({
  id: 'sec-evaluation-paths',
  key: 'evaluation-paths',
  order: 2,
  title: { ar: 'مسارات التقييم', en: 'Assessment paths' },
  description: {
    ar: 'اختر المسار المناسب لتقييم جاهزية مؤسستك وفق مستوى النضج واحتياجك الحالي.',
    en: 'Choose the right path to assess your organization’s readiness based on your maturity level and current need.',
  },
  items: [
    item({
      id: 'path-self',
      order: 1,
      icon: 'fa-solid fa-list-check',
      title: { ar: 'التقييم الذاتي', en: 'Self assessment' },
      description: {
        ar: 'تقييم أولي سريع بدون شواهد، يتيح للجهة معرفة مستوى جاهزيتها بشكل تشخيصي غير معتمد.',
        en: 'A fast initial assessment without evidence, letting the organization gauge its readiness diagnostically and unofficially.',
      },
      ctaLabel: { ar: 'ابدأ التقييم الذاتي', en: 'Start self assessment' },
      ctaUrl: '/assessment/self',
      meta: {
        accent: 'calm',
        points: [
          { ar: 'تقييم سريع', en: 'Fast assessment' },
          { ar: 'بدون متطلبات شواهد', en: 'No evidence required' },
          { ar: 'تقرير تشخيصي فوري', en: 'Instant diagnostic report' },
        ],
      },
    }),
    item({
      id: 'path-accredited',
      order: 2,
      icon: 'fa-solid fa-clipboard-check',
      title: { ar: 'التقييم المعتمد', en: 'Accredited assessment' },
      description: {
        ar: 'تقييم رسمي يعتمد على رفع الشواهد ومراجعتها من قبل مقيّمين مختصين، وينتج عنه درجة وشهادة معتمدة.',
        en: 'An official assessment based on uploading evidence reviewed by specialist assessors, producing an accredited score and certificate.',
      },
      ctaLabel: { ar: 'ابدأ التقييم المعتمد', en: 'Start accredited assessment' },
      ctaUrl: '/assessment/accredited',
      meta: {
        accent: 'accredited',
        points: [
          { ar: 'شواهد موثقة', en: 'Documented evidence' },
          { ar: 'تقييم من خبراء', en: 'Expert assessment' },
          { ar: 'شهادة معتمدة', en: 'Accredited certificate' },
        ],
      },
    }),
    item({
      id: 'path-advanced',
      order: 3,
      icon: 'fa-solid fa-trophy',
      title: { ar: 'التقييم المتقدم', en: 'Advanced assessment' },
      description: {
        ar: 'تقييم تنافسي مرتبط بجائزة التميز في العمل الخيري، يعتمد على مراحل تقييم متعددة واعتماد نهائي للنتائج.',
        en: 'A competitive assessment linked to the Charitable Work Excellence Award, based on multiple assessment stages and a final accreditation of results.',
      },
      ctaLabel: { ar: 'ابدأ التقييم المتقدم', en: 'Start advanced assessment' },
      ctaUrl: '/assessment/advanced',
      meta: {
        accent: 'award',
        points: [
          { ar: 'تقييم تنافسي', en: 'Competitive assessment' },
          { ar: 'متعدد المقيمين', en: 'Multiple assessors' },
          { ar: 'ترشيح للجائزة', en: 'Award nomination' },
        ],
      },
    }),
  ],
})

const excellenceModelSection = section({
  id: 'sec-excellence-model',
  key: 'excellence-model',
  order: 3,
  title: { ar: 'نموذج التميز في العمل الأهلي', en: 'The Charitable Work Excellence Model' },
  description: {
    ar: 'يعتمد نموذج التميز على مجموعة من المعايير الرئيسية والفرعية التي تقيس قدرة المنظمة على التخطيط، والتنفيذ، والتعلم، وتحقيق النتائج المستدامة.',
    en: 'The excellence model relies on a set of main and sub criteria that measure an organization’s ability to plan, execute, learn, and achieve sustainable results.',
  },
  subtitle: {
    ar: 'يوفر النموذج إطارًا موحدًا لفهم مستوى النضج المؤسسي، وقياس الفجوات، وتوجيه خطط التحسين بناءً على نتائج دقيقة ومقارنة قابلة للتتبع.',
    en: 'The model provides a unified framework to understand institutional maturity, measure gaps, and guide improvement plans based on accurate, trackable, comparable results.',
  },
  image: '/generated/excellence-model.jpg',
  icon: 'fa-solid fa-diagram-project',
  items: [
    item({
      id: 'model-c1',
      order: 1,
      title: { ar: 'القيادة الإدارية', en: 'Administrative Leadership' },
      description: {
        ar: 'قدرة القيادة على توجيه المنظمة وترسيخ ثقافة التميز والحوكمة.',
        en: 'Leadership’s ability to steer the organization and embed a culture of excellence and governance.',
      },
      meta: { band: 'enabler', weight: 1000 },
    }),
    item({
      id: 'model-c2',
      order: 2,
      title: { ar: 'التخطيط الاستراتيجي', en: 'Strategic Planning' },
      description: {
        ar: 'وضوح التوجه الاستراتيجي وترجمته إلى خطط قابلة للقياس والتنفيذ.',
        en: 'Clarity of strategic direction and its translation into measurable, executable plans.',
      },
      meta: { band: 'enabler', weight: 900 },
    }),
    item({
      id: 'model-c3',
      order: 3,
      title: { ar: 'إدارة الموارد البشرية والمتطوعين', en: 'Human Resources & Volunteers Management' },
      description: {
        ar: 'استقطاب الكفاءات والمتطوعين وتنميتهم والحفاظ على تفاعلهم.',
        en: 'Attracting, developing, and retaining talent and volunteers and sustaining their engagement.',
      },
      meta: { band: 'enabler', weight: 800 },
    }),
    item({
      id: 'model-c4',
      order: 4,
      title: { ar: 'إدارة الموارد والشراكات', en: 'Resources & Partnerships Management' },
      description: {
        ar: 'إدارة الموارد المالية والمادية وبناء شراكات فاعلة ومستدامة.',
        en: 'Managing financial and physical resources and building effective, sustainable partnerships.',
      },
      meta: { band: 'enabler', weight: 700 },
    }),
    item({
      id: 'model-c5',
      order: 5,
      title: { ar: 'إدارة العمليات والخدمات', en: 'Processes & Services Management' },
      description: {
        ar: 'تصميم العمليات والبرامج والخدمات وتحسينها لتحقيق قيمة للمستفيدين.',
        en: 'Designing and improving processes, programs, and services to deliver value to beneficiaries.',
      },
      meta: { band: 'enabler', weight: 1200 },
    }),
    item({
      id: 'model-c6',
      order: 6,
      title: { ar: 'الابتكار وإدارة المعرفة', en: 'Innovation & Knowledge Management' },
      description: {
        ar: 'تبني الابتكار والتعلم المؤسسي وإدارة المعرفة لدعم التحسين المستمر.',
        en: 'Adopting innovation, organizational learning, and knowledge management to support continuous improvement.',
      },
      meta: { band: 'creativity', weight: 600 },
    }),
    item({
      id: 'model-c7',
      order: 7,
      title: { ar: 'نتائج الموارد البشرية', en: 'People Results' },
      description: {
        ar: 'مؤشرات رضا وتفاعل وتطور منسوبي المنظمة ومتطوعيها.',
        en: 'Indicators of the satisfaction, engagement, and growth of the organization’s people and volunteers.',
      },
      meta: { band: 'result', weight: 700 },
    }),
    item({
      id: 'model-c8',
      order: 8,
      title: { ar: 'نتائج المستفيدين', en: 'Beneficiary Results' },
      description: {
        ar: 'مؤشرات أثر الخدمات ورضا المستفيدين وجودة تجربتهم.',
        en: 'Indicators of service impact, beneficiary satisfaction, and experience quality.',
      },
      meta: { band: 'result', weight: 1200 },
    }),
    item({
      id: 'model-c9',
      order: 9,
      title: { ar: 'نتائج الأداء الرئيسية', en: 'Key Performance Results' },
      description: {
        ar: 'المؤشرات الرئيسية للأداء المؤسسي المرتبطة بتحقيق الرسالة والأهداف.',
        en: 'The key institutional-performance indicators tied to achieving the mission and objectives.',
      },
      meta: { band: 'result', weight: 1100 },
    }),
  ],
})

const howItWorksSection = section({
  id: 'sec-how-it-works',
  key: 'how-it-works',
  order: 4,
  title: { ar: 'كيف تعمل منصة مؤشر؟', en: 'How does Muasher work?' },
  description: {
    ar: 'رحلة واضحة من تسجيل الجهة حتى التحسين المستمر عبر خطوات متدرجة.',
    en: 'A clear journey from registering the organization to continuous improvement through progressive steps.',
  },
  icon: 'fa-solid fa-route',
  items: [
    item({
      id: 'step-1',
      order: 1,
      icon: 'fa-solid fa-building',
      title: { ar: 'تسجيل الجهة', en: 'Register the organization' },
      description: {
        ar: 'تقوم الجمعية أو المنظمة بتسجيل بياناتها الأساسية وتحديد مسار التقييم المناسب.',
        en: 'The association or organization registers its basic data and selects the suitable assessment path.',
      },
    }),
    item({
      id: 'step-2',
      order: 2,
      icon: 'fa-solid fa-file-pen',
      title: { ar: 'استكمال التقييم', en: 'Complete the assessment' },
      description: {
        ar: 'يتم تعبئة نموذج التقييم، ورفع الشواهد عند الحاجة حسب نوع المسار.',
        en: 'The assessment form is filled in and evidence is uploaded when needed based on the path type.',
      },
    }),
    item({
      id: 'step-3',
      order: 3,
      icon: 'fa-solid fa-user-check',
      title: { ar: 'المراجعة وتقييم اللجنة', en: 'Review and committee assessment' },
      description: {
        ar: 'تتم مراجعة الطلب من قبل المقيّمين والمقيمين حسب آلية كل مسار.',
        en: 'The request is reviewed by assessors and assessors according to each path’s mechanism.',
      },
    }),
    item({
      id: 'step-4',
      order: 4,
      icon: 'fa-solid fa-award',
      title: { ar: 'النتائج والتقارير', en: 'Results and reports' },
      description: {
        ar: 'تصدر الدرجة النهائية، والتقرير التعقيبي، والشهادة عند الاستحقاق.',
        en: 'The final score, the feedback report, and the certificate are issued when merited.',
      },
    }),
    item({
      id: 'step-5',
      order: 5,
      icon: 'fa-solid fa-arrow-trend-up',
      title: { ar: 'التحسين المستمر', en: 'Continuous improvement' },
      description: {
        ar: 'يمكن للجهة الاستفادة من حقيبة التميز السريع لمعالجة الفجوات ورفع مستوى النضج.',
        en: 'The organization can benefit from the Quick Excellence Kit to close gaps and raise its maturity level.',
      },
    }),
  ],
})

const fastExcellenceKitSection = section({
  id: 'sec-fast-excellence-kit',
  key: 'fast-excellence-kit',
  order: 5,
  title: { ar: 'حقيبة التميز السريع', en: 'Quick Excellence Kit' },
  subtitle: { ar: 'خدمة موجهة للتحسين المستمر', en: 'A service dedicated to continuous improvement' },
  description: {
    ar: 'رحلة تطوير مؤسسي مبنية على نتائج تقييمك، تفتح مراحل تحسين تدريجية تساعد الجهة على معالجة الفجوات ورفع مستوى النضج بشكل عملي ومستدام.',
    en: 'An institutional-development journey built on your assessment results, unlocking progressive improvement stages that help the organization close gaps and raise its maturity practically and sustainably.',
  },
  image: '/generated/fast-excellence-kit.jpg',
  icon: 'fa-solid fa-briefcase',
  meta: {
    howItWorks: {
      title: { ar: 'كيف تعمل الحقيبة؟', en: 'How does the kit work?' },
      body: {
        ar: 'بعد صدور نتائج التقييم المعتمد أو المتقدم، تقوم المنصة بتحليل الفجوات بين الدرجة الفعلية والدرجة المستهدفة. ثم يتم فتح مراحل تحسين تدريجية، يحدد فيها المقيّم الأولويات والملفات والنماذج الداعمة التي تساعد الجهة على التطوير.',
        en: 'After the accredited or advanced results are issued, the platform analyzes the gaps between the actual score and the target score. Then progressive improvement stages are unlocked, in which the assessor sets the priorities, files, and supporting templates that help the organization develop.',
      },
    },
  },
  items: [
    item({
      id: 'kit-card-1',
      order: 1,
      icon: 'fa-solid fa-magnifying-glass-chart',
      title: { ar: 'مبنية على الفجوات', en: 'Gap-based' },
      description: {
        ar: 'تحلل نتائج تقييمك وتحدد أين تحتاج الجهة إلى تدخل وتحسين.',
        en: 'It analyzes your assessment results and pinpoints where the organization needs intervention and improvement.',
      },
    }),
    item({
      id: 'kit-card-2',
      order: 2,
      icon: 'fa-solid fa-layer-group',
      title: { ar: 'فتح تدريجي', en: 'Progressive unlocking' },
      description: {
        ar: 'تفتح مراحل التحسين على دفعات، حتى لا يتم تحميل الجهة بجميع الفجوات مرة واحدة.',
        en: 'Improvement stages open in batches so the organization is not overwhelmed with all gaps at once.',
      },
    }),
    item({
      id: 'kit-card-3',
      order: 3,
      icon: 'fa-solid fa-folder-open',
      title: { ar: 'نماذج ومرفقات مساندة', en: 'Supporting templates and attachments' },
      description: {
        ar: 'يوفر المقيّم ملفات ونماذج وأدلة تساعد الجهة على التطبيق العملي.',
        en: 'The assessor provides files, templates, and guides that help the organization apply changes practically.',
      },
    }),
    item({
      id: 'kit-card-4',
      order: 4,
      icon: 'fa-solid fa-gauge-high',
      title: { ar: 'تحسين قابل للقياس', en: 'Measurable improvement' },
      description: {
        ar: 'تعود الجهة للتقييم لاحقًا لقياس التحسن ورفع مستوى النضج.',
        en: 'The organization returns to assessment later to measure progress and raise its maturity level.',
      },
    }),
  ],
})

const leadershipSection = section({
  id: 'sec-leadership',
  key: 'leadership',
  order: 6,
  title: { ar: 'مسار القيادات', en: 'Leadership path' },
  subtitle: { ar: 'قريبًا', en: 'Coming soon' },
  description: {
    ar: 'مسار تطويري مخصص لقياس وتمكين القيادات داخل المنظمات غير الربحية، بما يساعد على رفع كفاءة القيادة المؤسسية وتعزيز جاهزية الصف القيادي.',
    en: 'A development path dedicated to measuring and empowering leaders within non-profit organizations, helping raise institutional leadership efficiency and strengthen leadership-tier readiness.',
  },
  image: '/generated/leadership.jpg',
  icon: 'fa-solid fa-user-tie',
  meta: { comingSoon: true },
})

const analyticsLensesSection = section({
  id: 'sec-analytics-lenses',
  key: 'analytics-lenses',
  order: 7,
  title: { ar: 'العدسات التحليلية', en: 'Analytics lenses' },
  description: {
    ar: 'لوحات تحليلية تساعد أصحاب القرار على قراءة نتائج التقييم المعتمد والمتقدم من زوايا متعددة، بما يدعم التوجيه، والتمكين، وتحسين الأثر.',
    en: 'Analytical dashboards that help decision-makers read accredited and advanced assessment results from multiple angles, supporting guidance, empowerment, and impact improvement.',
  },
  subtitle: {
    ar: 'لا تعتمد العدسات على التقييم الذاتي، وتعتمد على آخر تقييم معتمد أو متقدم، وعند وجود تقييمين يتم اعتماد الأعلى حسب الإعدادات المعتمدة.',
    en: 'Lenses do not rely on self assessment; they rely on the latest accredited or advanced assessment, and when both exist the higher one is used per the approved settings.',
  },
  icon: 'fa-solid fa-layer-group',
  ctaLabel: { ar: 'استعرض العدسات التحليلية', en: 'Explore the analytics lenses' },
  ctaUrl: '/lenses',
  items: [
    item({
      id: 'lens-grantor',
      order: 1,
      icon: 'fa-solid fa-hand-holding-dollar',
      title: { ar: 'عدسة المانح', en: 'Grantor lens' },
      description: {
        ar: 'تقرأ الأداء من زاوية الجهات المانحة لدعم قرارات التمويل والدعم.',
        en: 'Reads performance from the grantors’ perspective to support funding and support decisions.',
      },
    }),
    item({
      id: 'lens-supervisory',
      order: 2,
      icon: 'fa-solid fa-building-shield',
      title: { ar: 'عدسة الجهات الإشرافية', en: 'Supervisory bodies lens' },
      description: {
        ar: 'تعرض الأداء من منظور الجهات الإشرافية والتنظيمية.',
        en: 'Presents performance from the perspective of supervisory and regulatory bodies.',
      },
    }),
    item({
      id: 'lens-community',
      order: 3,
      icon: 'fa-solid fa-people-group',
      title: { ar: 'عدسة المجتمع', en: 'Community lens' },
      description: {
        ar: 'تبرز أثر المنظمة وقيمتها المضافة على المجتمع.',
        en: 'Highlights the organization’s impact and added value to the community.',
      },
    }),
    item({
      id: 'lens-vision-2030',
      order: 4,
      icon: 'fa-solid fa-flag',
      title: { ar: 'عدسة برامج رؤية 2030', en: 'Vision 2030 programs lens' },
      description: {
        ar: 'تربط أداء الجهة بمستهدفات ومبادرات برامج رؤية المملكة 2030.',
        en: 'Links the organization’s performance to the targets and initiatives of Vision 2030 programs.',
      },
    }),
    item({
      id: 'lens-strategic',
      order: 5,
      icon: 'fa-solid fa-chess',
      title: { ar: 'العدسة الاستراتيجية', en: 'Strategic lens' },
      description: {
        ar: 'تحليل استراتيجي شامل يدعم التوجيه واتخاذ القرار على المدى البعيد.',
        en: 'A comprehensive strategic analysis that supports long-term guidance and decision-making.',
      },
    }),
  ],
})

const kpiPromoSection = section({
  id: 'sec-kpi-promo',
  key: 'kpi-promo',
  order: 8,
  title: { ar: 'مؤشرات الأداء الرئيسية', en: 'Key performance indicators' },
  description: {
    ar: 'صفحة مخصصة لعرض مؤشرات التميز المؤسسي للقطاع غير الربحي، بطريقة مرئية ومنظمة تشبه تجربة عرض مؤشرات رؤية 2030.',
    en: 'A dedicated page that presents institutional-excellence indicators for the non-profit sector in a visual, organized way inspired by the Vision 2030 indicators experience.',
  },
  icon: 'fa-solid fa-chart-column',
  ctaLabel: { ar: 'استعرض مؤشرات الأداء', en: 'Explore performance indicators' },
  ctaUrl: '/kpis',
})

const partnersSection = section({
  id: 'sec-partners',
  key: 'partners',
  order: 9,
  title: { ar: 'شركاء النجاح', en: 'Success partners' },
  description: {
    ar: 'نفخر بشراكاتنا التي تسهم في تعزيز التميز المؤسسي ورفع جودة الأداء في القطاع غير الربحي.',
    en: 'We take pride in our partnerships that help strengthen institutional excellence and raise performance quality in the non-profit sector.',
  },
  icon: 'fa-solid fa-handshake',
  items: [
    item({
      id: 'partner-1',
      order: 1,
      image: '',
      title: { ar: 'شريك استراتيجي', en: 'Strategic partner' },
      ctaUrl: '',
      meta: { placeholder: true },
    }),
    item({
      id: 'partner-2',
      order: 2,
      image: '',
      title: { ar: 'جهة داعمة', en: 'Supporting entity' },
      ctaUrl: '',
      meta: { placeholder: true },
    }),
    item({
      id: 'partner-3',
      order: 3,
      image: '',
      title: { ar: 'شريك ممكّن', en: 'Enabling partner' },
      ctaUrl: '',
      meta: { placeholder: true },
    }),
  ],
})

const smartAssistantSection = section({
  id: 'sec-smart-assistant',
  key: 'smart-assistant',
  order: 10,
  title: { ar: 'مساعد مؤشر الذكي', en: 'Muasher smart assistant' },
  description: {
    ar: 'اسأل عن مسارات التقييم، المعايير، آلية التسجيل، أو طريقة استخدام المنصة.',
    en: 'Ask about assessment paths, criteria, the registration mechanism, or how to use the platform.',
  },
  icon: 'fa-solid fa-robot',
  meta: { position: 'bottom-start' },
})

const contactSection = section({
  id: 'sec-contact',
  key: 'contact',
  order: 11,
  title: { ar: 'تواصل معنا', en: 'Contact us' },
  description: {
    ar: 'نسعد بتواصلكم للإجابة على استفساراتكم حول مسارات التقييم وخدمات المنصة.',
    en: 'We are glad to hear from you and answer your questions about the assessment paths and platform services.',
  },
  icon: 'fa-solid fa-envelope',
  ctaLabel: { ar: 'راسلنا', en: 'Email us' },
  ctaUrl: 'mailto:info@moashir.example.sa',
})

const footerSection = section({
  id: 'sec-footer',
  key: 'footer',
  order: 12,
  title: { ar: 'منصة مؤشر', en: 'Muasher platform' },
  description: {
    ar: 'منصة وطنية لقياس نضج وتميز المنظمات غير الربحية وتوجيهها نحو أثر أعمق ومستدام.',
    en: 'A national platform to measure the maturity and excellence of non-profit organizations and guide them toward deeper, sustainable impact.',
  },
  meta: {
    developedBy: {
      label: { ar: 'تم التطوير بواسطة شركة التحول التقني', en: 'Developed by Technical Transformation Company' },
      url: 'https://tts.sa',
    },
  },
})

const accreditedIntroSection = section({
  id: 'sec-accredited-intro',
  key: 'accredited-intro',
  order: 13,
  title: { ar: 'التقييم المعتمد', en: 'Accredited assessment' },
  subtitle: {
    ar: 'تقييم رسمي معتمد على الشواهد يمنح جهتك درجة موثوقة وشهادة معتمدة.',
    en: 'An official evidence-based assessment that gives your organization a trusted score and an accredited certificate.',
  },
  description: {
    ar: 'يعتمد التقييم المعتمد على رفع الشواهد ومراجعتها من قبل مقيّمين مختصين وفق نموذج التميز المؤسسي، وينتهي بإصدار درجة نهائية وتقرير تعقيبي وشهادة معتمدة تدعم التحسين المستمر واتخاذ القرار.',
    en: 'The accredited assessment relies on uploading evidence reviewed by specialist assessors against the institutional-excellence model, ending with a final score, a feedback report, and an accredited certificate that support continuous improvement and decision-making.',
  },
  icon: 'fa-solid fa-clipboard-check',
  meta: {
    whyRecommended: {
      title: { ar: 'لماذا نوصي بالتقييم المعتمد؟', en: 'Why we recommend the accredited assessment' },
      body: {
        ar: 'يمنح التقييم المعتمد جهتك قراءة دقيقة وموثوقة لمستوى نضجها المؤسسي، ويؤهلها للحصول على شهادة معتمدة، ويفتح لها لاحقًا حقيبة التميز السريع لمعالجة الفجوات.',
        en: 'The accredited assessment gives your organization an accurate, trusted reading of its institutional maturity, qualifies it for an accredited certificate, and later unlocks the Quick Excellence Kit to close gaps.',
      },
    },
    fees: {
      title: { ar: 'الرسوم والضريبة', en: 'Fees and tax' },
      body: {
        ar: 'تُحتسب رسوم التقييم المعتمد وفق ما هو معلن في المنصة، ويُضاف إليها ضريبة القيمة المضافة بنسبة 15%، ويظهر الإجمالي النهائي قبل الدفع مباشرة.',
        en: 'The accredited assessment fee is charged as published on the platform, with 15% VAT added, and the final total is shown right before payment.',
      },
      taxNote: {
        ar: 'ضريبة القيمة المضافة 15% مضمّنة في الإجمالي النهائي.',
        en: '15% VAT is included in the final total.',
      },
    },
    payment: {
      title: { ar: 'آلية الدفع', en: 'Payment mechanism' },
      body: {
        ar: 'يتم الدفع إلكترونيًا عبر بوابة دفع آمنة (مدى، بطاقات ائتمانية، Apple Pay). وبعد نجاح الدفع تنتقل الجهة إلى لوحة التحكم لبدء تعبئة نموذج التقييم المعتمد.',
        en: 'Payment is made electronically through a secure gateway (mada, credit cards, Apple Pay). After a successful payment the organization moves to its dashboard to start filling in the accredited assessment form.',
      },
    },
  },
  items: [
    item({
      id: 'accredited-benefit-1',
      order: 1,
      icon: 'fa-solid fa-file-shield',
      title: { ar: 'مراجعة الشواهد من الخبراء', en: 'Expert evidence review' },
      description: {
        ar: 'يراجع مقيّمون مختصون شواهد جهتك ويقيّمونها بموضوعية وفق النموذج المعتمد.',
        en: 'Specialist assessors review and objectively assess your evidence against the approved model.',
      },
    }),
    item({
      id: 'accredited-benefit-2',
      order: 2,
      icon: 'fa-solid fa-certificate',
      title: { ar: 'درجة وشهادة معتمدة', en: 'Accredited score and certificate' },
      description: {
        ar: 'تحصل الجهة على درجة نهائية موثوقة وشهادة معتمدة قابلة للتحميل.',
        en: 'The organization receives a trusted final score and a downloadable accredited certificate.',
      },
    }),
    item({
      id: 'accredited-benefit-3',
      order: 3,
      icon: 'fa-solid fa-comments',
      title: { ar: 'تقرير تعقيبي مفصّل', en: 'Detailed feedback report' },
      description: {
        ar: 'تقرير تعقيبي لكل معيار فرعي يوضح نقاط القوة وفرص التحسين.',
        en: 'A feedback report per sub-criterion that highlights strengths and improvement opportunities.',
      },
    }),
    item({
      id: 'accredited-benefit-4',
      order: 4,
      icon: 'fa-solid fa-briefcase',
      title: { ar: 'الوصول لحقيبة التميز السريع', en: 'Access to the Quick Excellence Kit' },
      description: {
        ar: 'بعد صدور النتيجة تُفتح حقيبة التميز السريع لمعالجة الفجوات ورفع مستوى النضج.',
        en: 'After the result is issued, the Quick Excellence Kit opens to close gaps and raise maturity.',
      },
    }),
  ],
})

const advancedIntroSection = section({
  id: 'sec-advanced-intro',
  key: 'advanced-intro',
  order: 14,
  title: { ar: 'التقييم المتقدم', en: 'Advanced assessment' },
  subtitle: {
    ar: 'مسار تنافسي مرتبط بجائزة التميز في العمل الخيري، يمر بمراحل تقييم متعددة واعتماد نهائي.',
    en: 'A competitive path linked to the Charitable Work Excellence Award, passing through multiple assessment stages and a final accreditation.',
  },
  description: {
    ar: 'يتيح التقييم المتقدم للجهات المؤهلة التنافس على جائزة التميز في العمل الخيري خلال دورة سنوية محددة المواعيد، عبر تقييم أولي ثم فرز ثم تقييم من لجنة مقيّمين مستقلين، وينتهي بإعلان النتائج وتكريم الفائزين.',
    en: 'The advanced assessment lets qualified organizations compete for the Charitable Work Excellence Award during a time-bound annual cycle: an initial assessment, then filtering, then assessment by an independent assessors committee, ending with the announcement of results and the honoring of winners.',
  },
  icon: 'fa-solid fa-trophy',
  meta: {
    nextCycle: {
      title: { ar: 'الدورة القادمة', en: 'Next cycle' },
      date: '2026-09-01',
      body: {
        ar: 'تُفتح مواعيد التقديم للدورة القادمة وفق ما تعتمده الأمانة، ويُعلن عن النتائج في حفل الجائزة السنوي.',
        en: 'Submission dates for the next cycle open as approved by the secretariat, and results are announced at the annual award ceremony.',
      },
    },
    award: {
      title: { ar: 'عن الجائزة', en: 'About the award' },
      body: {
        ar: 'جائزة التميز في العمل الخيري تكرّم المنظمات غير الربحية الأكثر تميزًا مؤسسيًا وفق نموذج معتمد ومراحل تقييم دقيقة، وتمنح الفائزين اعترافًا وطنيًا ومكانة تنافسية.',
        en: 'The Charitable Work Excellence Award honors the most institutionally excellent non-profit organizations against an approved model and rigorous assessment stages, granting winners national recognition and a competitive standing.',
      },
    },
  },
  items: [
    item({
      id: 'advanced-winner-2024-1',
      order: 1,
      icon: 'fa-solid fa-award',
      title: { ar: 'جمعية البر بالرياض', en: 'Al-Birr Charity — Riyadh' },
      description: { ar: 'الفائز بالمركز الأول — دورة 2024', en: 'First place — 2024 cycle' },
      meta: { group: 'winner', rank: 1 },
    }),
    item({
      id: 'advanced-winner-2024-2',
      order: 2,
      icon: 'fa-solid fa-medal',
      title: { ar: 'مؤسسة نماء للتنمية', en: 'Namaa Development Foundation' },
      description: { ar: 'الفائز بالمركز الثاني — دورة 2024', en: 'Second place — 2024 cycle' },
      meta: { group: 'winner', rank: 2 },
    }),
    item({
      id: 'advanced-winner-2024-3',
      order: 3,
      icon: 'fa-solid fa-medal',
      title: { ar: 'جمعية إحسان الخيرية', en: 'Ehsan Charitable Society' },
      description: { ar: 'الفائز بالمركز الثالث — دورة 2024', en: 'Third place — 2024 cycle' },
      meta: { group: 'winner', rank: 3 },
    }),
    item({
      id: 'advanced-partner-1',
      order: 4,
      icon: 'fa-solid fa-handshake-angle',
      title: { ar: 'المركز الوطني لتنمية القطاع غير الربحي', en: 'National Center for Non-Profit Sector Development' },
      description: { ar: 'شريك نجاح', en: 'Success partner' },
      meta: { group: 'partner' },
    }),
    item({
      id: 'advanced-sponsor-1',
      order: 5,
      icon: 'fa-solid fa-hand-holding-heart',
      title: { ar: 'مؤسسة العطاء المستدام', en: 'Sustainable Giving Foundation' },
      description: { ar: 'راعٍ ماسي', en: 'Diamond sponsor' },
      meta: { group: 'sponsor' },
    }),
    item({
      id: 'advanced-supporter-1',
      order: 6,
      icon: 'fa-solid fa-people-carry-box',
      title: { ar: 'غرفة العمل الخيري', en: 'Charitable Work Chamber' },
      description: { ar: 'جهة داعمة', en: 'Supporting entity' },
      meta: { group: 'supporter' },
    }),
  ],
})

export const homeSectionsFixture = [
  heroSection,
  evaluationPathsSection,
  excellenceModelSection,
  howItWorksSection,
  fastExcellenceKitSection,
  leadershipSection,
  analyticsLensesSection,
  kpiPromoSection,
  partnersSection,
  smartAssistantSection,
  contactSection,
  footerSection,
  accreditedIntroSection,
  advancedIntroSection,
]

export const siteSettingsFixture = {
  social: [
    { platform: 'x', url: 'https://x.com/muasher' },
    { platform: 'linkedin', url: 'https://www.linkedin.com/company/muasher' },
    { platform: 'youtube', url: 'https://www.youtube.com/@muasher' },
  ],
  contact: {
    email: 'info@moashir.example.sa',
    phone: '+966 11 000 0000',
    address: {
      ar: 'الرياض، المملكة العربية السعودية',
      en: 'Riyadh, Kingdom of Saudi Arabia',
    },
  },
  footerNote: {
    ar: 'جميع الحقوق محفوظة لمنصة مؤشر.',
    en: 'All rights reserved to Muasher platform.',
  },
  quickLinks: [
    { label: { ar: 'الرئيسية', en: 'Home' }, url: '/' },
    { label: { ar: 'مسارات التقييم', en: 'Assessment paths' }, url: '#evaluation-paths' },
    { label: { ar: 'حقيبة التميز السريع', en: 'Quick Excellence Kit' }, url: '#fast-excellence-kit' },
    { label: { ar: 'مؤشرات الأداء', en: 'Performance indicators' }, url: '/kpis' },
    { label: { ar: 'تواصل معنا', en: 'Contact us' }, url: '#contact' },
  ],
}

export const homeContentFixture = {
  published: true,
  sections: homeSectionsFixture,
  siteSettings: siteSettingsFixture,
}
