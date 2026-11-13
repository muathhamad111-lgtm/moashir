import { item, section } from './content'

const accreditedIntroPage = {
  key: 'accredited_intro',
  title: { ar: 'التقييم المعتمد', en: 'Accredited assessment' },
  seo: { title: null, description: null },
  published: true,
  sections: [
    section({
      id: 301,
      key: 'overview',
      type: 'rich_text',
      order: 1,
      icon: 'fa-solid fa-clipboard-check',
      title: { ar: 'التقييم المعتمد', en: 'Accredited assessment' },
      subtitle: {
        ar: 'تقييم رسمي معتمد على الشواهد يمنح جهتك درجة موثوقة وشهادة معتمدة.',
        en: 'An official evidence-based assessment that gives your organization a trusted score and an accredited certificate.',
      },
      description: {
        ar: 'يعتمد التقييم المعتمد على رفع الشواهد ومراجعتها من قبل مقيّمين مختصين وفق نموذج التميز المؤسسي، وينتهي بإصدار درجة نهائية وتقرير تعقيبي وشهادة معتمدة تدعم التحسين المستمر واتخاذ القرار.',
        en: 'The accredited assessment relies on uploading evidence reviewed by specialist assessors against the institutional-excellence model, ending with a final score, a feedback report, and an accredited certificate that support continuous improvement and decision-making.',
      },
    }),
    section({
      id: 302,
      key: 'benefits',
      type: 'faq',
      order: 2,
      icon: 'fa-solid fa-star',
      title: {
        ar: 'لماذا نوصي بالتقييم المعتمد؟',
        en: 'Why we recommend the accredited assessment',
      },
      description: {
        ar: 'يمنح التقييم المعتمد جهتك قراءة دقيقة وموثوقة لمستوى نضجها المؤسسي، ويؤهلها للحصول على شهادة معتمدة، ويفتح لها لاحقًا حقيبة التميز السريع لمعالجة الفجوات.',
        en: 'The accredited assessment gives your organization an accurate, trusted reading of its institutional maturity, qualifies it for an accredited certificate, and later unlocks the Quick Excellence Kit to close gaps.',
      },
      items: [
        item({
          id: 3001,
          order: 1,
          icon: 'fa-solid fa-file-shield',
          title: { ar: 'مراجعة الشواهد من الخبراء', en: 'Expert evidence review' },
          description: {
            ar: 'يراجع مقيّمون مختصون شواهد جهتك ويقيّمونها بموضوعية وفق النموذج المعتمد.',
            en: 'Specialist assessors review and objectively assess your evidence against the approved model.',
          },
        }),
        item({
          id: 3002,
          order: 2,
          icon: 'fa-solid fa-certificate',
          title: { ar: 'درجة وشهادة معتمدة', en: 'Accredited score and certificate' },
          description: {
            ar: 'تحصل الجهة على درجة نهائية موثوقة وشهادة معتمدة قابلة للتحميل.',
            en: 'The organization receives a trusted final score and a downloadable accredited certificate.',
          },
        }),
        item({
          id: 3003,
          order: 3,
          icon: 'fa-solid fa-comments',
          title: { ar: 'تقرير تعقيبي مفصّل', en: 'Detailed feedback report' },
          description: {
            ar: 'تقرير تعقيبي لكل معيار فرعي يوضح نقاط القوة وفرص التحسين.',
            en: 'A feedback report per sub-criterion that highlights strengths and improvement opportunities.',
          },
        }),
        item({
          id: 3004,
          order: 4,
          icon: 'fa-solid fa-briefcase',
          title: { ar: 'الوصول لحقيبة التميز السريع', en: 'Access to the Quick Excellence Kit' },
          description: {
            ar: 'بعد صدور النتيجة تُفتح حقيبة التميز السريع لمعالجة الفجوات ورفع مستوى النضج.',
            en: 'After the result is issued, the Quick Excellence Kit opens to close gaps and raise maturity.',
          },
        }),
      ],
    }),
    section({
      id: 303,
      key: 'pricing',
      type: 'pricing_box',
      order: 3,
      icon: 'fa-solid fa-receipt',
      title: { ar: 'أكمل الدفع', en: 'Complete payment' },
      ctaLabel: { ar: 'ادفع وابدأ التقييم', en: 'Pay and start the assessment' },
      ctaUrl: '/assessment/accredited/pay',
    }),
    section({
      id: 304,
      key: 'payment',
      type: 'rich_text',
      order: 4,
      icon: 'fa-solid fa-credit-card',
      title: { ar: 'آلية الدفع', en: 'Payment mechanism' },
      description: {
        ar: 'يتم الدفع إلكترونيًا عبر بوابة دفع آمنة، ويصدر إيصال دفع بعد نجاح العملية، ثم تنتقل الجهة إلى لوحة التحكم لبدء تعبئة نموذج التقييم المعتمد.',
        en: 'Payment is made online through a secure gateway and a payment receipt is issued after a successful payment; the entity then moves to its dashboard to start the accredited assessment form.',
      },
    }),
  ],
}

const advancedIntroPage = {
  key: 'advanced_intro',
  title: { ar: 'التقييم المتقدم', en: 'Advanced assessment' },
  seo: { title: null, description: null },
  published: true,
  sections: [
    section({
      id: 311,
      key: 'overview',
      type: 'rich_text',
      order: 1,
      icon: 'fa-solid fa-trophy',
      title: { ar: 'عن الجائزة', en: 'About the award' },
      description: {
        ar: 'جائزة التميز في العمل الخيري تكرّم المنظمات غير الربحية الأكثر تميزًا مؤسسيًا وفق نموذج معتمد ومراحل تقييم دقيقة، وتمنح الفائزين اعترافًا وطنيًا ومكانة تنافسية.',
        en: 'The Charitable Work Excellence Award honors the most institutionally excellent non-profit organizations against an approved model and rigorous assessment stages, granting winners national recognition and a competitive standing.',
      },
    }),
    section({
      id: 312,
      key: 'cycle',
      type: 'cycle_box',
      order: 2,
      icon: 'fa-solid fa-calendar-days',
      title: { ar: 'الدورة القادمة', en: 'Next cycle' },
      description: {
        ar: 'تُفتح مواعيد التقديم للدورة القادمة وفق ما تعتمده الأمانة، ويُعلن عن النتائج في حفل الجائزة السنوي.',
        en: 'Submission dates for the next cycle open as approved by the secretariat, and results are announced at the annual award ceremony.',
      },
    }),
    section({
      id: 313,
      key: 'winners',
      type: 'winners',
      order: 3,
      icon: 'fa-solid fa-award',
      title: { ar: 'الفائزون في الدورة السابقة', en: 'Winners of the previous cycle' },
      items: [
        item({
          id: 3101,
          order: 1,
          icon: 'fa-solid fa-award',
          title: { ar: 'جمعية الأمل الخيرية', en: 'Al-Amal Charity' },
          description: { ar: 'المركز الأول', en: 'First place' },
          meta: { rank: 1 },
        }),
        item({
          id: 3102,
          order: 2,
          icon: 'fa-solid fa-medal',
          title: { ar: 'جمعية النور للتنمية', en: 'Al-Noor Development Society' },
          description: { ar: 'المركز الثاني', en: 'Second place' },
          meta: { rank: 2 },
        }),
        item({
          id: 3103,
          order: 3,
          icon: 'fa-solid fa-medal',
          title: { ar: 'مؤسسة الغد المستدام', en: 'Sustainable Tomorrow Foundation' },
          description: { ar: 'المركز الثالث', en: 'Third place' },
          meta: { rank: 3 },
        }),
      ],
    }),
    section({
      id: 314,
      key: 'partners',
      type: 'logos',
      order: 4,
      icon: 'fa-solid fa-handshake-angle',
      title: { ar: 'شركاء الجائزة', en: 'Award partners' },
      items: [
        item({
          id: 3111,
          order: 1,
          icon: 'fa-solid fa-handshake-angle',
          title: { ar: 'جمعية الأمل الخيرية', en: 'Al-Amal Charity' },
          description: { ar: 'شريك نجاح', en: 'Success partner' },
        }),
        item({
          id: 3112,
          order: 2,
          icon: 'fa-solid fa-hand-holding-heart',
          title: { ar: 'مؤسسة العطاء المستدام', en: 'Sustainable Giving Foundation' },
          description: { ar: 'راعٍ ماسي', en: 'Diamond sponsor' },
        }),
      ],
    }),
  ],
}

const bagDetailsPage = {
  key: 'bag_details',
  title: { ar: 'حقيبة التميز السريع', en: 'Quick Excellence Kit' },
  seo: { title: null, description: null },
  published: true,
  sections: [
    section({
      id: 321,
      key: 'overview',
      type: 'rich_text',
      order: 1,
      icon: 'fa-solid fa-briefcase',
      title: { ar: 'ما حقيبة التميز السريع؟', en: 'What is the Quick Excellence Kit?' },
      description: {
        ar: 'رحلة تحسين وليست تقييمًا جديدًا؛ تبنى على آخر نتيجة معتمدة أو متقدمة للجهة، ولا تطلب منها رفع شواهد جديدة.',
        en: 'An improvement journey, not a new assessment: it builds on the entity’s latest accredited or advanced result and does not ask it to upload new evidence.',
      },
    }),
    section({
      id: 322,
      key: 'faq',
      type: 'faq',
      order: 2,
      icon: 'fa-solid fa-circle-question',
      title: { ar: 'الأسئلة الشائعة', en: 'Frequently asked questions' },
      items: [
        item({
          id: 3201,
          order: 1,
          title: { ar: 'من يمكنه شراء الحقيبة؟', en: 'Who can purchase the kit?' },
          description: {
            ar: 'الجهات التي لديها نتيجة تقييم معتمد أو متقدم موثقة.',
            en: 'Entities that hold a verified accredited or advanced assessment result.',
          },
        }),
        item({
          id: 3202,
          order: 2,
          title: { ar: 'كم تستغرق الرحلة؟', en: 'How long does the journey take?' },
          description: {
            ar: 'تتكون الخطة من مراحل مدة كل منها أربعة أشهر افتراضيًا.',
            en: 'The plan is made of stages that last four months each by default.',
          },
        }),
      ],
    }),
  ],
}

const lensesPage = {
  key: 'lenses',
  title: { ar: 'العدسات التحليلية', en: 'Analytical lenses' },
  seo: { title: null, description: null },
  published: true,
  sections: [
    section({
      id: 331,
      key: 'overview',
      type: 'rich_text',
      order: 1,
      icon: 'fa-solid fa-glasses',
      title: { ar: 'العدسات التحليلية', en: 'Analytical lenses' },
      subtitle: {
        ar: 'لوحات تحليلية تساعد أصحاب القرار على قراءة نتائج التقييم المعتمد والمتقدم من زوايا متعددة.',
        en: 'Analytical views that help decision-makers read accredited and advanced results from several angles.',
      },
      description: {
        ar: 'تعتمد العدسات على أعلى نتيجة بين آخر تقييم معتمد وآخر تقييم متقدم للجهة، ولا تستخدم نتائج التقييم الذاتي.',
        en: 'The lenses use the higher of the entity’s latest accredited and latest advanced results and never the self-assessment results.',
      },
    }),
    section({
      id: 332,
      key: 'lens_cards',
      type: 'logos',
      order: 2,
      icon: 'fa-solid fa-layer-group',
      title: { ar: 'العدسات', en: 'The lenses' },
      items: [
        item({
          id: 3301,
          order: 1,
          icon: 'fa-solid fa-hand-holding-heart',
          title: { ar: 'عدسة المانحين', en: 'Donor lens' },
          description: {
            ar: 'تقرأ الأداء من زاوية الجهات المانحة لدعم قرارات التمويل والدعم.',
            en: 'Reads performance from the donors’ perspective to support funding decisions.',
          },
        }),
        item({
          id: 3302,
          order: 2,
          icon: 'fa-solid fa-building-shield',
          title: { ar: 'عدسة الجهات الإشرافية', en: 'Supervisory bodies lens' },
          description: {
            ar: 'تعرض الأداء من منظور الجهات الإشرافية والتنظيمية.',
            en: 'Presents performance from the perspective of supervisory and regulatory bodies.',
          },
        }),
        item({
          id: 3303,
          order: 3,
          icon: 'fa-solid fa-people-roof',
          title: { ar: 'عدسة المجتمع', en: 'Community lens' },
          description: {
            ar: 'تبرز أثر المنظمة وقيمتها المضافة على المجتمع.',
            en: 'Highlights the organization’s impact and added value to the community.',
          },
        }),
        item({
          id: 3304,
          order: 4,
          icon: 'fa-solid fa-flag',
          title: { ar: 'عدسة برامج رؤية 2030', en: 'Vision 2030 programs lens' },
          description: {
            ar: 'تربط أداء الجهة بمستهدفات ومبادرات برامج رؤية المملكة 2030.',
            en: 'Links the organization’s performance to the targets and initiatives of Vision 2030 programs.',
          },
        }),
        item({
          id: 3305,
          order: 5,
          icon: 'fa-solid fa-chess',
          title: { ar: 'العدسة الاستراتيجية', en: 'Strategic lens' },
          description: {
            ar: 'تحليل استراتيجي شامل يدعم التوجيه واتخاذ القرار على المدى البعيد.',
            en: 'A comprehensive strategic analysis that supports long-term guidance and decision-making.',
          },
        }),
      ],
    }),
  ],
}

const kpisPage = {
  key: 'kpis',
  title: { ar: 'مؤشرات الأداء', en: 'Performance indicators' },
  seo: { title: null, description: null },
  published: true,
  sections: [
    section({
      id: 341,
      key: 'hero',
      type: 'hero',
      order: 1,
      icon: 'fa-solid fa-chart-column',
      title: { ar: 'مؤشرات التميز المؤسسي', en: 'Institutional excellence indicators' },
      description: {
        ar: 'مؤشرات محدثة لقياس أداء القطاع غير الربحي ونضجه المؤسسي.',
        en: 'Up-to-date indicators that measure the performance and institutional maturity of the non-profit sector.',
      },
    }),
  ],
}

const accessibilityPage = {
  key: 'accessibility',
  title: { ar: 'بيان إمكانية الوصول — منصة مؤشر', en: 'Accessibility Statement — Moashir' },
  seo: { title: null, description: null },
  published: true,
  sections: [
    section({
      id: 391,
      key: 'intro',
      type: 'rich_text',
      order: 1,
      icon: null,
      title: null,
      description: {
        ar: 'مسودة للمراجعة القانونية — آخر تحديث: [[تاريخ الاعتماد]]',
        en: 'Draft for legal review — last updated: [[approval date]]',
      },
    }),
    section({
      id: 392,
      key: 'section-2',
      type: 'rich_text',
      order: 2,
      icon: null,
      title: { ar: '2. المعيار المعتمد', en: '2. Standard adopted' },
      description: {
        ar: '- نعتمد إرشادات إمكانية الوصول إلى محتوى الويب WCAG 2.1 بالمستوى AA.\n- نختبر الواجهة باللغتين العربية والإنجليزية وبالوضعين الفاتح والداكن.',
        en: '- We adopt the Web Content Accessibility Guidelines (WCAG) 2.1 at level AA.\n- We test the interface in Arabic and English and in light and dark modes.',
      },
    }),
  ],
}

export const otherPagesFixture = [
  accreditedIntroPage,
  advancedIntroPage,
  bagDetailsPage,
  lensesPage,
  kpisPage,
  accessibilityPage,
]
