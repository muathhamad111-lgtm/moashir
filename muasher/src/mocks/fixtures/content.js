import { fixtureImage } from './cmsImages'

function localizedOrNull(value) {
  return value && (value.ar || value.en) ? value : null
}

function contentFields(overrides) {
  return {
    title: localizedOrNull(overrides.title),
    subtitle: localizedOrNull(overrides.subtitle),
    description: localizedOrNull(overrides.description),
    ctaLabel: localizedOrNull(overrides.ctaLabel),
    ctaUrl: overrides.ctaUrl || null,
    icon: overrides.icon || null,
    image: overrides.image ? fixtureImage(overrides.image) : null,
    meta: overrides.meta && Object.keys(overrides.meta).length ? overrides.meta : null,
  }
}

export function section(overrides) {
  return {
    id: overrides.id,
    key: overrides.key,
    type: overrides.type,
    enabled: overrides.enabled ?? true,
    order: overrides.order,
    ...contentFields(overrides),
    background: overrides.background || null,
    items: overrides.items ?? [],
  }
}

export function item(overrides) {
  return {
    id: overrides.id,
    enabled: overrides.enabled ?? true,
    order: overrides.order,
    ...contentFields(overrides),
  }
}

const heroSection = section({
  id: 101,
  key: 'hero',
  type: 'hero',
  order: 1,
  title: {
    ar: 'قِس تميّزك المؤسسي،',
    en: 'Measure your organizational excellence,',
  },
  subtitle: {
    ar: 'منصة رقمية متكاملة لقياس نضج الجهات غير الربحية وإدارة مسارات التقييم وتحليل نتائج التميز المؤسسي.',
    en: 'An integrated digital platform that measures the maturity of non-profit entities, manages the assessment paths and analyses institutional excellence results.',
  },
  description: {
    ar: 'منصة مؤشر تُمكّن الجمعيات والمؤسسات الأهلية والأوقاف من قياس نضجها المؤسسي وفق نموذج التميّز في العمل الخيري، وكشف فجوات الأداء، والحصول على تقييم معتمد يدعم التحسين المستمر.',
    en: 'Moashir enables civil associations, civil foundations and endowments to measure their institutional maturity against the Charitable Work Excellence Model, uncover performance gaps and obtain an accredited assessment that supports continuous improvement.',
  },
  ctaLabel: {
    ar: 'ابدأ التقييم الذاتي مجاناً',
    en: 'Start the free self assessment',
  },
  ctaUrl: '/register',
  icon: 'fa-solid fa-chart-line',
  meta: {
    eyebrow: {
      ar: 'منصّة نموذج التميّز في العمل الخيري',
      en: 'Charitable Work Excellence Model Platform',
    },
    highlight: {
      ar: 'وانطلق نحو الريادة.',
      en: 'and lead with confidence.',
    },
    preview: {
      title: {
        ar: 'مستوى النضج المؤسسي',
        en: 'Institutional maturity',
      },
      caption: {
        ar: 'النتيجة حسب المعايير الرئيسية',
        en: 'Result by main criterion',
      },
      score: 78,
      scoreLabel: {
        ar: 'الجاهزية',
        en: 'Readiness',
      },
      bars: [
        {
          label: {
            ar: 'القيادة الإدارية',
            en: 'Administrative leadership',
          },
          value: 92,
        },
        {
          label: {
            ar: 'إدارة الموارد البشرية والمتطوعين',
            en: 'People and volunteers management',
          },
          value: 74,
        },
        {
          label: {
            ar: 'إدارة العمليات والخدمات',
            en: 'Operations and services management',
          },
          value: 81,
        },
      ],
      badges: [
        {
          icon: 'fa-solid fa-award',
          title: {
            ar: 'شهادة معتمدة',
            en: 'Accredited certificate',
          },
          caption: {
            ar: 'بمستوى التميّز',
            en: 'With the excellence level',
          },
        },
        {
          icon: 'fa-solid fa-file-lines',
          title: {
            ar: 'تقرير تغذية راجعة',
            en: 'Feedback report',
          },
          caption: {
            ar: 'لكل معيار فرعي',
            en: 'For every sub-criterion',
          },
        },
      ],
    },
  },
  background: 'gradient-brand',
  image: '/generated/hero.jpg',
  items: [
    item({
      id: 1001,
      order: 1,
      title: {
        ar: 'استكشف مسارات التقييم',
        en: 'Explore the assessment paths',
      },
      ctaLabel: {
        ar: 'استكشف مسارات التقييم',
        en: 'Explore the assessment paths',
      },
      ctaUrl: '#paths',
      meta: {
        role: 'secondary-cta',
      },
    }),
    item({
      id: 1002,
      order: 2,
      title: {
        ar: 'نموذج تميّز معتمد',
        en: 'An approved excellence model',
      },
      icon: 'fa-solid fa-circle-check',
      meta: {
        role: 'point',
      },
    }),
    item({
      id: 1003,
      order: 3,
      title: {
        ar: 'مقيّمون مؤهلون',
        en: 'Qualified assessors',
      },
      icon: 'fa-solid fa-circle-check',
      meta: {
        role: 'point',
      },
    }),
    item({
      id: 1004,
      order: 4,
      title: {
        ar: 'نتائج وتقارير واضحة',
        en: 'Clear results and reports',
      },
      icon: 'fa-solid fa-circle-check',
      meta: {
        role: 'point',
      },
    }),
  ],
})

const evaluationPathsSection = section({
  id: 102,
  key: 'evaluation-paths',
  type: 'paths',
  order: 3,
  title: {
    ar: 'اختر المسار المناسب لمرحلة جهتك',
    en: "Choose the path that fits your entity's stage",
  },
  description: {
    ar: 'ثلاثة مسارات متدرجة، من التشخيص الذاتي إلى الاعتماد الرسمي والتنافس على الجائزة.',
    en: 'Three progressive paths, from self diagnosis to official accreditation and competing for the award.',
  },
  icon: 'fa-solid fa-route',
  meta: {
    eyebrow: {
      ar: 'مسارات التقييم',
      en: 'Assessment paths',
    },
  },
  items: [
    item({
      id: 1005,
      order: 1,
      title: {
        ar: 'التقييم الذاتي',
        en: 'Self assessment',
      },
      description: {
        ar: 'تشخيص أولي سريع لمستوى نضج جهتك وفق معايير النموذج.',
        en: "A quick first diagnosis of your entity's maturity against the model criteria.",
      },
      ctaLabel: {
        ar: 'ابدأ التقييم الذاتي',
        en: 'Start the self assessment',
      },
      ctaUrl: '/assessment/self',
      icon: 'fa-solid fa-list-check',
      meta: {
        accent: 'calm',
        availability: 'live',
        points: [
          {
            ar: 'بدون شواهد',
            en: 'No evidence required',
          },
          {
            ar: 'نتيجة فورية',
            en: 'Instant result',
          },
          {
            ar: 'يمكن إعادته',
            en: 'Can be repeated',
          },
        ],
      },
    }),
    item({
      id: 1006,
      order: 2,
      title: {
        ar: 'التقييم المعتمد',
        en: 'Accredited assessment',
      },
      description: {
        ar: 'تقييم رسمي يراجعه مقيّمون مؤهلون بناءً على شواهد جهتك.',
        en: "An official assessment reviewed by qualified assessors on the basis of your entity's evidence.",
      },
      ctaLabel: {
        ar: 'ابدأ التقييم المعتمد',
        en: 'Start the accredited assessment',
      },
      ctaUrl: '/accredited',
      icon: 'fa-solid fa-clipboard-check',
      meta: {
        accent: 'accredited',
        availability: 'live',
        points: [
          {
            ar: 'مراجعة مقيّم مؤهل',
            en: 'Review by a qualified assessor',
          },
          {
            ar: 'تقرير تغذية راجعة',
            en: 'Feedback report',
          },
          {
            ar: 'شهادة بمستوى التميّز',
            en: 'Certificate with the excellence level',
          },
        ],
      },
    }),
    item({
      id: 1007,
      order: 3,
      title: {
        ar: 'التقييم المتقدم (الجائزة)',
        en: 'Advanced assessment (the Award)',
      },
      description: {
        ar: 'مسار تنافسي ضمن جائزة التميّز في العمل الخيري، بتقييم أولي ولجنة مقيّمين ونتائج على مستوى المنطقة والمملكة.',
        en: 'A competitive path within the Charitable Work Excellence Award, with an initial assessment, an assessors committee and results at regional and national level.',
      },
      icon: 'fa-solid fa-trophy',
      meta: {
        accent: 'award',
        availability: 'coming_soon',
        points: [
          {
            ar: 'دورات سنوية',
            en: 'Annual cycles',
          },
          {
            ar: 'لجنة مقيّمين',
            en: 'Assessors committee',
          },
          {
            ar: 'تكريم الفائزين',
            en: 'Winners honoured',
          },
        ],
      },
    }),
  ],
})

const excellenceModelSection = section({
  id: 103,
  key: 'excellence-model',
  type: 'excellence_model',
  order: 4,
  title: {
    ar: 'نموذج التميّز في العمل الخيري',
    en: 'Charitable Work Excellence Model',
  },
  description: {
    ar: 'إطار مرجعي يقيس قدرة الجهة على التخطيط والتنفيذ والتعلّم وتحقيق النتائج المستدامة.',
    en: "A reference framework that measures an entity's ability to plan, deliver, learn and achieve sustainable results.",
  },
  meta: {
    eyebrow: {
      ar: 'النموذج المرجعي',
      en: 'Reference model',
    },
  },
  icon: 'fa-solid fa-diagram-project',
  items: [
    item({
      id: 1008,
      order: 1,
      title: { ar: 'القيادة الإدارية', en: 'Administrative Leadership' },
      description: {
        ar: 'قدرة القيادة على توجيه المنظمة وترسيخ ثقافة التميز والحوكمة.',
        en: 'Leadership’s ability to steer the organization and embed a culture of excellence and governance.',
      },
      meta: { band: 'enabler', weight: 1000 },
    }),
    item({
      id: 1009,
      order: 2,
      title: { ar: 'التخطيط الاستراتيجي', en: 'Strategic Planning' },
      description: {
        ar: 'وضوح التوجه الاستراتيجي وترجمته إلى خطط قابلة للقياس والتنفيذ.',
        en: 'Clarity of strategic direction and its translation into measurable, executable plans.',
      },
      meta: { band: 'enabler', weight: 900 },
    }),
    item({
      id: 1010,
      order: 3,
      title: {
        ar: 'إدارة الموارد البشرية والمتطوعين',
        en: 'Human Resources & Volunteers Management',
      },
      description: {
        ar: 'استقطاب الكفاءات والمتطوعين وتنميتهم والحفاظ على تفاعلهم.',
        en: 'Attracting, developing, and retaining talent and volunteers and sustaining their engagement.',
      },
      meta: { band: 'enabler', weight: 800 },
    }),
    item({
      id: 1011,
      order: 4,
      title: { ar: 'إدارة الموارد والشراكات', en: 'Resources & Partnerships Management' },
      description: {
        ar: 'إدارة الموارد المالية والمادية وبناء شراكات فاعلة ومستدامة.',
        en: 'Managing financial and physical resources and building effective, sustainable partnerships.',
      },
      meta: { band: 'enabler', weight: 700 },
    }),
    item({
      id: 1012,
      order: 5,
      title: { ar: 'إدارة العمليات والخدمات', en: 'Processes & Services Management' },
      description: {
        ar: 'تصميم العمليات والبرامج والخدمات وتحسينها لتحقيق قيمة للمستفيدين.',
        en: 'Designing and improving processes, programs, and services to deliver value to beneficiaries.',
      },
      meta: { band: 'enabler', weight: 1200 },
    }),
    item({
      id: 1013,
      order: 6,
      title: { ar: 'الابتكار وإدارة المعرفة', en: 'Innovation & Knowledge Management' },
      description: {
        ar: 'تبني الابتكار والتعلم المؤسسي وإدارة المعرفة لدعم التحسين المستمر.',
        en: 'Adopting innovation, organizational learning, and knowledge management to support continuous improvement.',
      },
      meta: { band: 'creativity', weight: 600 },
    }),
    item({
      id: 1014,
      order: 7,
      title: { ar: 'نتائج الموارد البشرية', en: 'People Results' },
      description: {
        ar: 'مؤشرات رضا وتفاعل وتطور منسوبي المنظمة ومتطوعيها.',
        en: 'Indicators of the satisfaction, engagement, and growth of the organization’s people and volunteers.',
      },
      meta: { band: 'result', weight: 700 },
    }),
    item({
      id: 1015,
      order: 8,
      title: { ar: 'نتائج المستفيدين', en: 'Beneficiary Results' },
      description: {
        ar: 'مؤشرات أثر الخدمات ورضا المستفيدين وجودة تجربتهم.',
        en: 'Indicators of service impact, beneficiary satisfaction, and experience quality.',
      },
      meta: { band: 'result', weight: 1200 },
    }),
    item({
      id: 1016,
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
  id: 104,
  key: 'how-it-works',
  type: 'how_it_works',
  order: 2,
  title: {
    ar: 'شاهد رحلتك خطوة بخطوة',
    en: 'See your journey step by step',
  },
  description: {
    ar: 'من تسجيل جهتك حتى استلام شهادتك — تجربة رقمية متكاملة وواضحة.',
    en: 'From registering your entity to receiving your certificate — one clear, integrated digital experience.',
  },
  icon: 'fa-solid fa-shoe-prints',
  meta: {
    eyebrow: {
      ar: 'جولة في المنصة',
      en: 'Platform tour',
    },
  },
  items: [
    item({
      id: 1017,
      order: 1,
      title: {
        ar: 'تسجيل الجهة',
        en: 'Register the entity',
      },
      description: {
        ar: 'أنشئ حساب جهتك وأكمل ملفها التعريفي.',
        en: 'Create your entity account and complete its profile.',
      },
      icon: 'fa-solid fa-user-plus',
    }),
    item({
      id: 1018,
      order: 2,
      title: {
        ar: 'اختيار المسار',
        en: 'Choose the path',
      },
      description: {
        ar: 'ابدأ بالتقييم الذاتي، ثم انتقل إلى التقييم المعتمد.',
        en: 'Start with the self assessment, then move on to the accredited assessment.',
      },
      icon: 'fa-solid fa-route',
    }),
    item({
      id: 1019,
      order: 3,
      title: {
        ar: 'رفع الشواهد',
        en: 'Upload the evidence',
      },
      description: {
        ar: 'أرفق الشواهد لكل معيار مع التحقق الآلي من سلامة الملفات.',
        en: 'Attach the evidence for every criterion, with automatic file safety checks.',
      },
      icon: 'fa-solid fa-file-arrow-up',
    }),
    item({
      id: 1020,
      order: 4,
      title: {
        ar: 'المراجعة والتقييم',
        en: 'Review and assessment',
      },
      description: {
        ar: 'يراجع المقيّم المؤهل ملفك ويقيّمه وفق النموذج، وقد يطلب منك استكمالاً.',
        en: 'A qualified assessor reviews and assesses your file against the model and may ask you to complete it.',
      },
      icon: 'fa-solid fa-clipboard-check',
    }),
    item({
      id: 1021,
      order: 5,
      title: {
        ar: 'النتيجة والشهادة',
        en: 'Result and certificate',
      },
      description: {
        ar: 'اطّلع على نتيجتك وتقرير التغذية الراجعة، واحصل على شهادة بمستوى تميّزك.',
        en: 'See your result and feedback report, and receive a certificate with your excellence level.',
      },
      icon: 'fa-solid fa-award',
    }),
  ],
})

const fastExcellenceKitSection = section({
  id: 105,
  key: 'fast-excellence-kit',
  type: 'bag_promo',
  order: 6,
  title: {
    ar: 'أدوات تصنع الفرق في رحلتك',
    en: 'Tools that make the difference on your journey',
  },
  description: {
    ar: 'تُتاح حقيبة التميّز السريع بعد صدور نتيجة التقييم المعتمد، لتحويل الفجوات إلى خطة تحسين مرحلية.',
    en: 'The Quick Excellence Kit becomes available after the accredited assessment result, to turn the gaps into a staged improvement plan.',
  },
  icon: 'fa-solid fa-briefcase',
  meta: {
    availability: 'coming_soon',
    eyebrow: {
      ar: 'حقيبة التميّز السريع',
      en: 'Quick Excellence Kit',
    },
  },
  items: [
    item({
      id: 1022,
      order: 1,
      title: {
        ar: 'تقييم قابل للقياس',
        en: 'Measurable assessment',
      },
      description: {
        ar: 'أولويات تحسين واضحة مأخوذة من نتيجة التقييم.',
        en: 'Clear improvement priorities taken from the assessment result.',
      },
      icon: 'fa-solid fa-ruler-combined',
    }),
    item({
      id: 1023,
      order: 2,
      title: {
        ar: 'نماذج وأدلة مساعدة',
        en: 'Templates and guides',
      },
      description: {
        ar: 'أدلة ونماذج عملية تساعد على التنفيذ.',
        en: 'Practical guides and templates that support delivery.',
      },
      icon: 'fa-solid fa-book-open',
    }),
    item({
      id: 1024,
      order: 3,
      title: {
        ar: 'تحسين مبني على الفجوات',
        en: 'Gap-based improvement',
      },
      description: {
        ar: 'خطة مرحلية لمعالجة الفجوات ومتابعة التقدم.',
        en: 'A staged plan to close the gaps and follow progress.',
      },
      icon: 'fa-solid fa-chart-simple',
    }),
  ],
})

const leadershipSection = section({
  id: 106,
  key: 'leadership',
  type: 'leadership_promo',
  order: 8,
  title: {
    ar: 'مسار القيادات',
    en: 'Leaders path',
  },
  description: {
    ar: 'مسار تطويري لقياس وتمكين القيادات في الجهات غير الربحية — قريباً.',
    en: 'A development path to measure and empower the leaders of non-profit entities — coming soon.',
  },
  icon: 'fa-solid fa-compass',
  meta: {
    badge: 'coming_soon',
    availability: 'coming_soon',
    eyebrow: {
      ar: 'قريباً',
      en: 'Coming soon',
    },
  },
})

const analyticsLensesSection = section({
  id: 107,
  key: 'analytics-lenses',
  type: 'lenses_brief',
  order: 10,
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
      id: 1026,
      order: 1,
      icon: 'fa-solid fa-hand-holding-dollar',
      title: { ar: 'عدسة المانح', en: 'Grantor lens' },
      description: {
        ar: 'تقرأ الأداء من زاوية الجهات المانحة لدعم قرارات التمويل والدعم.',
        en: 'Reads performance from the grantors’ perspective to support funding and support decisions.',
      },
    }),
    item({
      id: 1027,
      order: 2,
      icon: 'fa-solid fa-building-shield',
      title: { ar: 'عدسة الجهات الإشرافية', en: 'Supervisory bodies lens' },
      description: {
        ar: 'تعرض الأداء من منظور الجهات الإشرافية والتنظيمية.',
        en: 'Presents performance from the perspective of supervisory and regulatory bodies.',
      },
    }),
    item({
      id: 1028,
      order: 3,
      icon: 'fa-solid fa-people-group',
      title: { ar: 'عدسة المجتمع', en: 'Community lens' },
      description: {
        ar: 'تبرز أثر المنظمة وقيمتها المضافة على المجتمع.',
        en: 'Highlights the organization’s impact and added value to the community.',
      },
    }),
    item({
      id: 1029,
      order: 4,
      icon: 'fa-solid fa-flag',
      title: { ar: 'عدسة برامج رؤية 2030', en: 'Vision 2030 programs lens' },
      description: {
        ar: 'تربط أداء الجهة بمستهدفات ومبادرات برامج رؤية المملكة 2030.',
        en: 'Links the organization’s performance to the targets and initiatives of Vision 2030 programs.',
      },
    }),
    item({
      id: 1030,
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
  id: 108,
  key: 'kpi-promo',
  type: 'kpi_promo',
  order: 9,
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
  id: 109,
  key: 'partners',
  type: 'partners',
  order: 11,
  title: { ar: 'شركاء النجاح', en: 'Success partners' },
  description: {
    ar: 'نفخر بشراكاتنا التي تسهم في تعزيز التميز المؤسسي ورفع جودة الأداء في القطاع غير الربحي.',
    en: 'We take pride in our partnerships that help strengthen institutional excellence and raise performance quality in the non-profit sector.',
  },
  icon: 'fa-solid fa-handshake',
  items: [
    item({
      id: 1031,
      order: 1,
      title: { ar: 'شريك استراتيجي', en: 'Strategic partner' },
      ctaUrl: '',
      meta: { placeholder: true },
    }),
    item({
      id: 1032,
      order: 2,
      title: { ar: 'جهة داعمة', en: 'Supporting entity' },
      ctaUrl: '',
      meta: { placeholder: true },
    }),
    item({
      id: 1033,
      order: 3,
      title: { ar: 'شريك ممكّن', en: 'Enabling partner' },
      ctaUrl: '',
      meta: { placeholder: true },
    }),
  ],
})

const smartAssistantSection = section({
  id: 110,
  key: 'smart-assistant',
  type: 'assistant',
  order: 13,
  title: { ar: 'مساعد مؤشر الذكي', en: 'Moashir smart assistant' },
  description: {
    ar: 'اسأل عن مسارات التقييم، المعايير، آلية التسجيل، أو طريقة استخدام المنصة.',
    en: 'Ask about assessment paths, criteria, the registration mechanism, or how to use the platform.',
  },
  icon: 'fa-solid fa-robot',
  meta: { position: 'bottom-start' },
})

const contactSection = section({
  id: 111,
  key: 'contact',
  type: 'contact',
  order: 12,
  title: {
    ar: 'نحن هنا لخدمتك',
    en: 'We are here to help',
  },
  description: {
    ar: 'أرسل استفسارك عن مسارات التقييم أو خدمات المنصة، وسيصلك رقم مرجعي ورد من فريقنا على بريدك الإلكتروني.',
    en: 'Send your question about the assessment paths or the platform services. You receive a reference number and a reply from our team by e-mail.',
  },
  icon: 'fa-solid fa-envelope',
  meta: {
    eyebrow: {
      ar: 'تواصل معنا',
      en: 'Contact us',
    },
  },
})

const footerSection = section({
  id: 112,
  key: 'footer',
  type: 'footer',
  order: 14,
  title: { ar: 'منصة مؤشر', en: 'Moashir platform' },
  description: {
    ar: 'منصة وطنية لقياس نضج وتميز المنظمات غير الربحية وتوجيهها نحو أثر أعمق ومستدام.',
    en: 'A national platform to measure the maturity and excellence of non-profit organizations and guide them toward deeper, sustainable impact.',
  },
  meta: {
    developedBy: {
      label: {
        ar: 'تم التطوير بواسطة شركة التحول التقني',
        en: 'Developed by Technical Transformation Company',
      },
      url: 'https://tts.sa',
    },
  },
})

const journeySection = section({
  id: 113,
  key: 'entity-journey',
  type: 'how_it_works',
  order: 5,
  title: {
    ar: 'رحلتك نحو التميّز المؤسسي',
    en: 'Your journey to institutional excellence',
  },
  description: {
    ar: 'مسار واضح يرافق جهتك من أول قياس حتى الريادة والتميّز المستدام.',
    en: 'A clear path that accompanies your entity from its first measurement to leadership and sustainable excellence.',
  },
  icon: 'fa-solid fa-flag-checkered',
  meta: {
    variant: 'journey',
    eyebrow: {
      ar: 'رحلة الجهة',
      en: 'The entity journey',
    },
  },
  items: [
    item({
      id: 1034,
      order: 1,
      title: {
        ar: 'الانطلاق',
        en: 'Start',
      },
      description: {
        ar: 'تسجيل الجهة وتفعيل حسابها.',
        en: 'Register the entity and activate its account.',
      },
      icon: 'fa-solid fa-flag',
    }),
    item({
      id: 1035,
      order: 2,
      title: {
        ar: 'القياس',
        en: 'Measure',
      },
      description: {
        ar: 'التقييم وفق معايير النموذج.',
        en: 'Assess against the model criteria.',
      },
      icon: 'fa-solid fa-gauge-high',
    }),
    item({
      id: 1036,
      order: 3,
      title: {
        ar: 'التحليل',
        en: 'Analyse',
      },
      description: {
        ar: 'كشف نقاط القوة والفجوات.',
        en: 'Reveal the strengths and the gaps.',
      },
      icon: 'fa-solid fa-magnifying-glass-chart',
    }),
    item({
      id: 1037,
      order: 4,
      title: {
        ar: 'التطوير',
        en: 'Improve',
      },
      description: {
        ar: 'تنفيذ خطة تحسين مبنية على الفجوات.',
        en: 'Carry out an improvement plan built on the gaps.',
      },
      icon: 'fa-solid fa-screwdriver-wrench',
    }),
    item({
      id: 1038,
      order: 5,
      title: {
        ar: 'الاعتماد',
        en: 'Accreditation',
      },
      description: {
        ar: 'الحصول على شهادة تميّز معتمدة.',
        en: 'Obtain an accredited excellence certificate.',
      },
      icon: 'fa-solid fa-award',
    }),
    item({
      id: 1039,
      order: 6,
      title: {
        ar: 'الريادة',
        en: 'Leadership',
      },
      description: {
        ar: 'تميّز مؤسسي مستدام.',
        en: 'Sustainable institutional excellence.',
      },
      icon: 'fa-solid fa-star',
    }),
  ],
})

const ctaBannerSection = section({
  id: 114,
  key: 'cta-banner',
  type: 'rich_text',
  order: 7,
  title: {
    ar: 'جاهز لتبدأ رحلة التميّز؟',
    en: 'Ready to start your excellence journey?',
  },
  description: {
    ar: 'سجّل جهتك وابدأ التقييم الذاتي مجاناً، ثم انتقل إلى التقييم المعتمد متى كنت جاهزاً.',
    en: 'Register your entity and start the free self assessment, then move on to the accredited assessment whenever you are ready.',
  },
  ctaLabel: {
    ar: 'أنشئ حساب جهتك',
    en: 'Create your entity account',
  },
  ctaUrl: '/register',
  icon: 'fa-solid fa-star',
  meta: {
    variant: 'cta',
    eyebrow: {
      ar: 'ابدأ الآن',
      en: 'Get started',
    },
  },
  items: [
    item({
      id: 1041,
      order: 1,
      title: {
        ar: 'تسجيل الدخول',
        en: 'Sign in',
      },
      ctaLabel: {
        ar: 'تسجيل الدخول',
        en: 'Sign in',
      },
      ctaUrl: '/login',
      meta: {
        role: 'secondary-cta',
      },
    }),
  ],
})

export const homePageFixture = {
  key: 'home',
  title: { ar: 'الصفحة الرئيسية', en: 'Home page' },
  seo: {
    title: { ar: 'منصة مؤشر للتميز المؤسسي', en: 'Moashir institutional excellence platform' },
    description: {
      ar: 'منصة لقياس نضج الجهات غير الربحية وتميزها المؤسسي وفق نموذج التميز في العمل الخيري.',
      en: 'A platform that measures the maturity and institutional excellence of non-profit entities against the Charitable Work Excellence Model.',
    },
  },
  published: true,
  sections: [
    heroSection,
    howItWorksSection,
    evaluationPathsSection,
    excellenceModelSection,
    journeySection,
    fastExcellenceKitSection,
    ctaBannerSection,
    leadershipSection,
    kpiPromoSection,
    analyticsLensesSection,
    partnersSection,
    contactSection,
    smartAssistantSection,
    footerSection,
  ],
}

export const siteSettingsFixture = {
  social: [
    { platform: 'x', url: 'https://x.com/moashir.example' },
    { platform: 'linkedin', url: 'https://www.linkedin.com/company/moashir-example' },
    { platform: 'youtube', url: 'https://www.youtube.com/@moashir.example' },
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
    en: 'All rights reserved to the Moashir platform.',
  },
  quickLinks: [
    { label: { ar: 'الرئيسية', en: 'Home' }, url: '/' },
    { label: { ar: 'مسارات التقييم', en: 'Assessment paths' }, url: '#paths' },
    { label: { ar: 'حقيبة التميز السريع', en: 'Quick Excellence Kit' }, url: '#kit' },
    { label: { ar: 'مؤشرات الأداء', en: 'Performance indicators' }, url: '/kpis' },
    { label: { ar: 'تواصل معنا', en: 'Contact us' }, url: '#contact' },
  ],
  seo: {
    title: { ar: 'منصة مؤشر', en: 'Moashir' },
    description: {
      ar: 'بوابة التقييم المؤسسي وفق نموذج التميز في العمل الخيري.',
      en: 'The institutional assessment portal of the Charitable Work Excellence Model.',
    },
  },
  featuredKpiLimit: 6,
  showFeaturedKpis: true,
}
