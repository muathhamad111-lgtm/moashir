import { EVALUATION_TYPES } from '@/constants/criteria'

export const criteriaSeedLabel = {
  ar: 'نموذج التميز المؤسسي - الإصدار الأول',
  en: 'Institutional Excellence Model - Version 1',
}

export const CRITERIA_SEED_PUBLISHED_AT = '2026-10-31T09:00:00Z'

export const criteriaSeedFixture = [
  {
    name: {
      ar: 'القيادة الإدارية',
      en: 'Administrative Leadership',
    },
    description: {
      ar: 'قدرة القيادة على توجيه الجهة وترسيخ ثقافة التميز والحوكمة الرشيدة.',
      en: "Leadership's ability to steer the organisation and embed a culture of excellence and sound governance.",
    },
    weight: '1000.00',
    distributeEqually: false,
    group: 'enablers',
    subs: [
      {
        name: {
          ar: 'التوجه الاستراتيجي والرؤية',
          en: 'Strategic Direction and Vision',
        },
        description: {
          ar: 'مدى وضوح التوجه الاستراتيجي وارتباطه بقيم الجهة وأهدافها.',
          en: 'Clarity of the strategic direction and its link to the values and goals of the organisation.',
        },
        weight: '400.00',
        distributeEqually: true,
        evaluationType: EVALUATION_TYPES.GUIDING_POINTS,
        hasCv: true,
        attachments: [
          {
            name: {
              ar: 'وثيقة الرؤية والرسالة المعتمدة',
              en: 'Approved vision and mission document',
            },
            required: true,
          },
          {
            name: {
              ar: 'محاضر مجلس الإدارة',
              en: 'Board meeting minutes',
            },
            required: false,
          },
        ],
        points: [
          {
            description: {
              ar: 'وجود وثيقة رؤية ورسالة معتمدة ومعلنة للعاملين والمستفيدين.',
              en: 'An approved vision and mission document, published to staff and beneficiaries.',
            },
            weight: '133.34',
            attachments: [
              {
                name: {
                  ar: 'وثيقة الرؤية والرسالة',
                  en: 'Vision and mission document',
                },
                required: true,
              },
            ],
          },
          {
            description: {
              ar: 'ارتباط الأهداف الاستراتيجية بالرؤية بمؤشرات قياس واضحة.',
              en: 'Strategic goals linked to the vision with clear measurement indicators.',
            },
            weight: '133.33',
            attachments: [],
          },
          {
            description: {
              ar: 'مراجعة دورية للتوجه الاستراتيجي وتحديثه وفق المتغيرات.',
              en: 'Periodic review and update of the strategic direction as conditions change.',
            },
            weight: '133.33',
            attachments: [],
          },
        ],
      },
      {
        name: {
          ar: 'الحوكمة والامتثال',
          en: 'Governance and Compliance',
        },
        description: {
          ar: 'التزام الجهة بمعايير الحوكمة والشفافية والإفصاح.',
          en: 'Adherence to governance, transparency and disclosure standards.',
        },
        weight: '350.00',
        distributeEqually: false,
        evaluationType: EVALUATION_TYPES.GUIDING_POINTS,
        hasCv: true,
        attachments: [
          {
            name: {
              ar: 'لائحة الحوكمة المعتمدة',
              en: 'Approved governance charter',
            },
            required: true,
          },
          {
            name: {
              ar: 'تقرير الالتزام النظامي',
              en: 'Regulatory compliance report',
            },
            required: true,
          },
        ],
        points: [
          {
            description: {
              ar: 'اعتماد لائحة حوكمة تحدد الأدوار والمسؤوليات وتعارض المصالح.',
              en: 'An approved governance charter defining roles, responsibilities and conflicts of interest.',
            },
            weight: '200.00',
            attachments: [
              {
                name: {
                  ar: 'لائحة الحوكمة',
                  en: 'Governance charter',
                },
                required: true,
              },
            ],
          },
          {
            description: {
              ar: 'إفصاح دوري وشفاف عن التقارير المالية والإدارية.',
              en: 'Periodic, transparent disclosure of financial and administrative reports.',
            },
            weight: '150.00',
            attachments: [],
          },
        ],
      },
      {
        name: {
          ar: 'ثقافة التميز والقيادة بالقدوة',
          en: 'Excellence Culture and Leading by Example',
        },
        description: {
          ar: 'ترسيخ القيادة لثقافة التميز عبر السلوك والممارسات اليومية.',
          en: 'How leadership embeds an excellence culture through daily behaviour and practice.',
        },
        weight: '250.00',
        distributeEqually: false,
        evaluationType: EVALUATION_TYPES.DIRECT,
        hasCv: true,
        attachments: [
          {
            name: {
              ar: 'نماذج مبادرات التميز الداخلية',
              en: 'Samples of internal excellence initiatives',
            },
            required: false,
          },
        ],
        points: [],
      },
    ],
  },
  {
    name: {
      ar: 'التخطيط الاستراتيجي',
      en: 'Strategic Planning',
    },
    description: {
      ar: 'وضوح التوجه الاستراتيجي وترجمته إلى خطط قابلة للقياس والتنفيذ.',
      en: 'Clarity of strategic direction and its translation into measurable, executable plans.',
    },
    weight: '900.00',
    distributeEqually: false,
    group: 'enablers',
    subs: [
      {
        name: {
          ar: 'تحليل البيئة واحتياجات المستفيدين',
          en: 'Environment and Beneficiary Needs Analysis',
        },
        description: {
          ar: 'مدى اعتماد الاستراتيجية على بيانات ودراسات دقيقة لاحتياجات المستفيدين.',
          en: 'How far the strategy relies on accurate data and studies of beneficiary needs.',
        },
        weight: '500.00',
        distributeEqually: true,
        evaluationType: EVALUATION_TYPES.GUIDING_POINTS,
        hasCv: true,
        attachments: [
          {
            name: {
              ar: 'دراسة احتياجات المستفيدين',
              en: 'Beneficiary needs study',
            },
            required: true,
          },
        ],
        points: [
          {
            description: {
              ar: 'إجراء دراسة موثقة لاحتياجات المستفيدين خلال آخر عامين.',
              en: 'A documented beneficiary needs study conducted within the last two years.',
            },
            weight: '250.00',
            attachments: [
              {
                name: {
                  ar: 'دراسة الاحتياجات',
                  en: 'Needs study',
                },
                required: true,
              },
            ],
          },
          {
            description: {
              ar: 'تحليل البيئة الداخلية والخارجية ضمن عملية التخطيط.',
              en: 'Internal and external environment analysis within the planning process.',
            },
            weight: '250.00',
            attachments: [],
          },
        ],
      },
      {
        name: {
          ar: 'صياغة الخطة الاستراتيجية ونشرها',
          en: 'Strategy Formulation and Deployment',
        },
        description: {
          ar: 'وضوح الخطة الاستراتيجية وارتباطها بالمبادرات والمؤشرات التشغيلية.',
          en: 'Clarity of the strategic plan and its link to initiatives and operational indicators.',
        },
        weight: '400.00',
        distributeEqually: false,
        evaluationType: EVALUATION_TYPES.DIRECT,
        hasCv: true,
        attachments: [
          {
            name: {
              ar: 'الخطة الاستراتيجية المعتمدة',
              en: 'Approved strategic plan',
            },
            required: true,
          },
          {
            name: {
              ar: 'خطة النشر والتنفيذ',
              en: 'Deployment and execution plan',
            },
            required: false,
          },
        ],
        points: [],
      },
    ],
  },
  {
    name: {
      ar: 'إدارة الموارد البشرية والمتطوعين',
      en: 'Human Resources and Volunteers Management',
    },
    description: {
      ar: 'استقطاب الكفاءات والمتطوعين وتنميتهم والحفاظ على تفاعلهم.',
      en: 'Attracting, developing and retaining talent and volunteers and sustaining their engagement.',
    },
    weight: '800.00',
    distributeEqually: true,
    group: 'enablers',
    subs: [
      {
        name: {
          ar: 'استقطاب الكفاءات وتنميتها',
          en: 'Talent Attraction and Development',
        },
        description: {
          ar: 'آليات استقطاب الكفاءات وتنمية قدرات الموظفين والمتطوعين.',
          en: 'Mechanisms to attract talent and develop employee and volunteer capabilities.',
        },
        weight: '400.00',
        distributeEqually: true,
        evaluationType: EVALUATION_TYPES.GUIDING_POINTS,
        hasCv: true,
        attachments: [
          {
            name: {
              ar: 'خطة التدريب والتطوير السنوية',
              en: 'Annual training and development plan',
            },
            required: true,
          },
        ],
        points: [
          {
            description: {
              ar: 'خطة تدريب سنوية مبنية على تحليل الفجوات في المهارات.',
              en: 'An annual training plan based on a skills gap analysis.',
            },
            weight: '200.00',
            attachments: [
              {
                name: {
                  ar: 'خطة التدريب',
                  en: 'Training plan',
                },
                required: true,
              },
            ],
          },
          {
            description: {
              ar: 'برنامج معتمد لإدارة المتطوعين واستقطابهم وتأهيلهم.',
              en: 'An approved programme for volunteer management, recruitment and onboarding.',
            },
            weight: '200.00',
            attachments: [],
          },
        ],
      },
      {
        name: {
          ar: 'التمكين والرعاية وبيئة العمل',
          en: 'Empowerment, Wellbeing and Workplace',
        },
        description: {
          ar: 'تمكين الموظفين والمتطوعين وتوفير بيئة عمل محفزة وآمنة.',
          en: 'Empowering employees and volunteers and providing a motivating, safe workplace.',
        },
        weight: '400.00',
        distributeEqually: false,
        evaluationType: EVALUATION_TYPES.DIRECT,
        hasCv: true,
        attachments: [
          {
            name: {
              ar: 'سياسة بيئة العمل ورعاية الموظفين',
              en: 'Workplace and employee wellbeing policy',
            },
            required: false,
          },
        ],
        points: [],
      },
    ],
  },
  {
    name: {
      ar: 'إدارة الموارد والشراكات',
      en: 'Resources and Partnerships Management',
    },
    description: {
      ar: 'إدارة الموارد المالية والمادية وبناء شراكات فاعلة ومستدامة.',
      en: 'Managing financial and physical resources and building effective, sustainable partnerships.',
    },
    weight: '700.00',
    distributeEqually: false,
    group: 'enablers',
    subs: [
      {
        name: {
          ar: 'إدارة الشراكات الاستراتيجية',
          en: 'Strategic Partnership Management',
        },
        description: {
          ar: 'بناء الشراكات الاستراتيجية وإدارتها بما يحقق قيمة مضافة مستدامة.',
          en: 'Building and managing strategic partnerships to deliver sustainable added value.',
        },
        weight: '400.00',
        distributeEqually: false,
        evaluationType: EVALUATION_TYPES.GUIDING_POINTS,
        hasCv: true,
        attachments: [
          {
            name: {
              ar: 'سجل الشراكات واتفاقياتها',
              en: 'Partnership register and agreements',
            },
            required: true,
          },
        ],
        points: [
          {
            description: {
              ar: 'سجل موثق للشراكات الاستراتيجية واتفاقياتها المعتمدة.',
              en: 'A documented register of strategic partnerships and their approved agreements.',
            },
            weight: '150.00',
            attachments: [
              {
                name: {
                  ar: 'اتفاقيات الشراكة',
                  en: 'Partnership agreements',
                },
                required: false,
              },
            ],
          },
          {
            description: {
              ar: 'تقييم دوري لأثر الشراكات ومدى تحقيقها للأهداف المشتركة.',
              en: 'Periodic evaluation of partnership impact and the achievement of shared goals.',
            },
            weight: '250.00',
            attachments: [],
          },
        ],
      },
      {
        name: {
          ar: 'الاستدامة المالية وإدارة الموارد',
          en: 'Financial Sustainability and Resource Management',
        },
        description: {
          ar: 'إدارة الموارد المالية بكفاءة وتنويع مصادر الدخل لضمان الاستدامة.',
          en: 'Managing finances efficiently and diversifying income sources for sustainability.',
        },
        weight: '300.00',
        distributeEqually: false,
        evaluationType: EVALUATION_TYPES.DIRECT,
        hasCv: true,
        attachments: [
          {
            name: {
              ar: 'القوائم المالية المدققة',
              en: 'Audited financial statements',
            },
            required: true,
          },
          {
            name: {
              ar: 'خطة تنويع مصادر الدخل',
              en: 'Income diversification plan',
            },
            required: false,
          },
        ],
        points: [],
      },
    ],
  },
  {
    name: {
      ar: 'إدارة العمليات والخدمات',
      en: 'Processes and Services Management',
    },
    description: {
      ar: 'تصميم العمليات والبرامج والخدمات وتحسينها لتحقيق قيمة للمستفيدين.',
      en: 'Designing and improving processes, programmes and services to deliver value to beneficiaries.',
    },
    weight: '1200.00',
    distributeEqually: false,
    group: 'enablers',
    subs: [
      {
        name: {
          ar: 'تصميم البرامج والخدمات',
          en: 'Programme and Service Design',
        },
        description: {
          ar: 'منهجية تصميم البرامج والخدمات بناءً على احتياجات المستفيدين.',
          en: 'Methodology for designing programmes and services based on beneficiary needs.',
        },
        weight: '500.00',
        distributeEqually: false,
        evaluationType: EVALUATION_TYPES.GUIDING_POINTS,
        hasCv: true,
        attachments: [
          {
            name: {
              ar: 'دليل البرامج والخدمات',
              en: 'Programmes and services manual',
            },
            required: true,
          },
        ],
        points: [
          {
            description: {
              ar: 'منهجية موثقة لتصميم البرامج بناءً على احتياجات المستفيدين.',
              en: 'A documented methodology for designing programmes based on beneficiary needs.',
            },
            weight: '300.00',
            attachments: [
              {
                name: {
                  ar: 'دليل تصميم البرامج',
                  en: 'Programme design guide',
                },
                required: true,
              },
            ],
          },
          {
            description: {
              ar: 'مراجعة دورية لجودة البرامج والخدمات وتحسينها.',
              en: 'Periodic review of programme and service quality and their improvement.',
            },
            weight: '200.00',
            attachments: [],
          },
        ],
      },
      {
        name: {
          ar: 'إدارة العمليات وتحسينها',
          en: 'Process Management and Improvement',
        },
        description: {
          ar: 'إدارة العمليات التشغيلية وتحسينها المستمر لرفع الكفاءة.',
          en: 'Managing operational processes and continuously improving efficiency.',
        },
        weight: '700.00',
        distributeEqually: false,
        evaluationType: EVALUATION_TYPES.DIRECT,
        hasCv: true,
        attachments: [
          {
            name: {
              ar: 'خرائط العمليات المعتمدة',
              en: 'Approved process maps',
            },
            required: false,
          },
        ],
        points: [],
      },
    ],
  },
  {
    name: {
      ar: 'الابتكار وإدارة المعرفة',
      en: 'Innovation and Knowledge Management',
    },
    description: {
      ar: 'تبني الابتكار والتعلم المؤسسي وإدارة المعرفة لدعم التحسين المستمر.',
      en: 'Adopting innovation, organisational learning and knowledge management to support continuous improvement.',
    },
    weight: '600.00',
    distributeEqually: false,
    group: 'creativity_learning',
    subs: [
      {
        name: {
          ar: 'التحول الرقمي وإدارة المعرفة',
          en: 'Digital Transformation and Knowledge Management',
        },
        description: {
          ar: 'توظيف الحلول الرقمية وإدارة المعرفة لتحسين الأداء المؤسسي.',
          en: 'Using digital solutions and knowledge management to improve institutional performance.',
        },
        weight: '300.00',
        distributeEqually: false,
        evaluationType: EVALUATION_TYPES.GUIDING_POINTS,
        hasCv: true,
        attachments: [
          {
            name: {
              ar: 'خطة التحول الرقمي',
              en: 'Digital transformation roadmap',
            },
            required: false,
          },
        ],
        points: [
          {
            description: {
              ar: 'خطة تحول رقمي معتمدة مرتبطة بأهداف الجهة التشغيلية.',
              en: 'An approved digital transformation plan linked to operational goals.',
            },
            weight: '300.00',
            attachments: [
              {
                name: {
                  ar: 'خطة التحول الرقمي',
                  en: 'Digital transformation plan',
                },
                required: false,
              },
            ],
          },
        ],
      },
      {
        name: {
          ar: 'الابتكار والتعلم المؤسسي',
          en: 'Innovation and Organisational Learning',
        },
        description: {
          ar: 'تشجيع المبادرات الابتكارية وتوثيق الدروس المستفادة وتعميمها داخل الجهة.',
          en: 'Encouraging innovative initiatives and documenting and sharing lessons learned across the organisation.',
        },
        weight: '300.00',
        distributeEqually: false,
        evaluationType: EVALUATION_TYPES.DIRECT,
        hasCv: true,
        attachments: [
          {
            name: {
              ar: 'سجل المبادرات الابتكارية',
              en: 'Register of innovation initiatives',
            },
            required: false,
          },
        ],
        points: [],
      },
    ],
  },
  {
    name: {
      ar: 'نتائج الموارد البشرية',
      en: 'People Results',
    },
    description: {
      ar: 'مؤشرات رضا وتفاعل وتطور منسوبي الجهة ومتطوعيها.',
      en: "Indicators of the satisfaction, engagement and growth of the organisation's people and volunteers.",
    },
    weight: '700.00',
    distributeEqually: true,
    group: 'results',
    subs: [
      {
        name: {
          ar: 'رضا الموظفين والمتطوعين',
          en: 'Employee and Volunteer Satisfaction',
        },
        description: {
          ar: 'نتائج قياس رضا الموظفين والمتطوعين ومعدلات الاستبقاء.',
          en: 'Employee and volunteer satisfaction results and retention rates.',
        },
        weight: '350.00',
        distributeEqually: false,
        evaluationType: EVALUATION_TYPES.DIRECT,
        hasCv: true,
        attachments: [],
        points: [],
      },
      {
        name: {
          ar: 'نتائج التطوير المهني',
          en: 'Professional Development Results',
        },
        description: {
          ar: 'أثر برامج التدريب والتطوير على أداء الموظفين والمتطوعين.',
          en: 'Impact of training and development programmes on staff and volunteer performance.',
        },
        weight: '350.00',
        distributeEqually: false,
        evaluationType: EVALUATION_TYPES.DIRECT,
        hasCv: false,
        attachments: [],
        points: [],
      },
    ],
  },
  {
    name: {
      ar: 'نتائج المستفيدين',
      en: 'Beneficiary Results',
    },
    description: {
      ar: 'مؤشرات أثر الخدمات ورضا المستفيدين وجودة تجربتهم.',
      en: 'Indicators of service impact, beneficiary satisfaction and experience quality.',
    },
    weight: '1200.00',
    distributeEqually: false,
    group: 'results',
    subs: [
      {
        name: {
          ar: 'رضا المستفيدين وولاؤهم',
          en: 'Beneficiary Satisfaction and Loyalty',
        },
        description: {
          ar: 'مستوى رضا المستفيدين واستمرارهم في الاستفادة من خدمات الجهة.',
          en: 'Beneficiary satisfaction levels and their continued use of the services.',
        },
        weight: '600.00',
        distributeEqually: false,
        evaluationType: EVALUATION_TYPES.DIRECT,
        hasCv: true,
        attachments: [
          {
            name: {
              ar: 'تقارير قياس رضا المستفيدين',
              en: 'Beneficiary satisfaction survey reports',
            },
            required: true,
          },
        ],
        points: [],
      },
      {
        name: {
          ar: 'أثر البرامج على المستفيدين',
          en: 'Programme Impact on Beneficiaries',
        },
        description: {
          ar: 'الأثر الفعلي للبرامج والخدمات على حياة المستفيدين.',
          en: 'The actual impact of programmes and services on the lives of beneficiaries.',
        },
        weight: '600.00',
        distributeEqually: false,
        evaluationType: EVALUATION_TYPES.DIRECT,
        hasCv: true,
        attachments: [
          {
            name: {
              ar: 'تقارير قياس الأثر',
              en: 'Impact measurement reports',
            },
            required: true,
          },
        ],
        points: [],
      },
    ],
  },
  {
    name: {
      ar: 'نتائج الأداء الرئيسية',
      en: 'Key Performance Results',
    },
    description: {
      ar: 'المؤشرات الرئيسية للأداء المؤسسي المرتبطة بتحقيق الرسالة والأهداف.',
      en: 'The key institutional-performance indicators tied to achieving the mission and objectives.',
    },
    weight: '1100.00',
    distributeEqually: false,
    group: 'results',
    subs: [
      {
        name: {
          ar: 'النتائج المالية والتشغيلية',
          en: 'Financial and Operational Results',
        },
        description: {
          ar: 'النتائج المالية والتشغيلية الرئيسية ومعدلات نموها.',
          en: 'Key financial and operational results and their growth rates.',
        },
        weight: '700.00',
        distributeEqually: false,
        evaluationType: EVALUATION_TYPES.DIRECT,
        hasCv: false,
        attachments: [],
        points: [],
      },
      {
        name: {
          ar: 'مؤشرات الأداء المؤسسي الرئيسية',
          en: 'Key Institutional Performance Indicators',
        },
        description: {
          ar: 'المؤشرات الرئيسية التي تعكس نضج أداء الجهة واستدامته.',
          en: 'Key indicators reflecting the maturity and sustainability of performance.',
        },
        weight: '400.00',
        distributeEqually: false,
        evaluationType: EVALUATION_TYPES.DIRECT,
        hasCv: false,
        attachments: [],
        points: [],
      },
    ],
  },
]
