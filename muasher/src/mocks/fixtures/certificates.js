import { FIXTURE_ENTITY_ID } from './assessments'
import { ACCREDITED_FIXTURE_IDS } from './accreditedRequests'

const COMPLETED_ACCREDITED_ID = ACCREDITED_FIXTURE_IDS.completedOwn

const assessorNotes = {
  103: {
    ar: 'تُظهر الجهة ثقافة تميز راسخة تنعكس في السلوك اليومي للقيادة، مع أدلة واضحة على القيادة بالقدوة. يُوصى بتوثيق مبادرات التميز الداخلية بشكل أكثر منهجية وربطها بمؤشرات قياس دورية.',
    en: 'The organization shows a well-rooted excellence culture reflected in leadership’s daily behavior, with clear evidence of leading by example. It is recommended to document internal excellence initiatives more systematically and link them to periodic measurement indicators.',
  },
  101: {
    ar: 'التوجه الاستراتيجي واضح ومعلن، وترتبط الأهداف بالرؤية بمؤشرات مناسبة. الفجوة الأبرز في دورية مراجعة التوجه وتحديثه وفق المتغيرات؛ يُنصح باعتماد آلية مراجعة نصف سنوية موثقة.',
    en: 'The strategic direction is clear and published, and goals are linked to the vision through suitable indicators. The main gap is the periodicity of reviewing and updating the direction; a documented semi-annual review mechanism is advised.',
  },
  102: {
    ar: 'اعتمدت الجهة لائحة حوكمة جيدة تحدد الأدوار وتعارض المصالح، مع إفصاح دوري مقبول. يُوصى بتعزيز شفافية التقارير المالية ونشرها للمستفيدين وأصحاب المصلحة.',
    en: 'The entity has adopted a solid governance charter defining roles and conflicts of interest, with acceptable periodic disclosure. Strengthening the transparency of financial reports and publishing them to beneficiaries and stakeholders is recommended.',
  },
  201: {
    ar: 'تعتمد الاستراتيجية على دراسة موثقة لاحتياجات المستفيدين خلال العامين الماضيين، وتحليل بيئي جيد. يُقترح تحديث الدراسة سنويًا وربط نتائجها مباشرة بالمبادرات.',
    en: 'The strategy rests on a documented beneficiary needs study over the past two years and a good environmental analysis. Updating the study annually and linking its findings directly to initiatives is suggested.',
  },
  202: {
    ar: 'الخطة الاستراتيجية معتمدة وواضحة، وخطة النشر متوفرة لكنها بحاجة إلى مؤشرات تنفيذ أدق على مستوى الإدارات. مستوى الأداء جيد بشكل عام.',
    en: 'The strategic plan is approved and clear, and a deployment plan exists but needs sharper execution indicators at the department level. Overall performance is good.',
  },
  301: {
    ar: 'خطة التدريب السنوية مبنية على تحليل فجوات المهارات، وبرنامج المتطوعين منظم. يُوصى بقياس أثر التدريب على الأداء بشكل كمّي.',
    en: 'The annual training plan is based on a skills gap analysis, and the volunteer program is well organized. Quantitatively measuring the impact of training on performance is recommended.',
  },
  302: {
    ar: 'بيئة العمل محفزة وسياسات الرعاية موجودة، غير أن مؤشرات رضا الموظفين تحتاج إلى قياس أكثر انتظامًا وربطها بخطط التحسين.',
    en: 'The workplace is motivating and wellbeing policies exist, yet employee satisfaction indicators need more regular measurement and linkage to improvement plans.',
  },
  401: {
    ar: 'سجل الشراكات موثق واتفاقياتها معتمدة، مع قيمة مضافة واضحة من بعض الشراكات. يُقترح تقييم أثر كل شراكة سنويًا.',
    en: 'The partnership register is documented and agreements are approved, with clear added value from several partnerships. Assessing the impact of each partnership annually is suggested.',
  },
  402: {
    ar: 'القوائم المالية مدققة والاستدامة المالية جيدة، لكن تنويع مصادر الدخل ما زال محدودًا؛ يُوصى بتفعيل خطة تنويع الدخل المعتمدة.',
    en: 'Financial statements are audited and financial sustainability is good, but income-source diversification remains limited; activating the approved income diversification plan is recommended.',
  },
  501: {
    ar: 'منهجية تصميم البرامج واضحة ومبنية على احتياجات المستفيدين، مع دليل معتمد للبرامج والخدمات. مستوى جيد جدًا.',
    en: 'The program design methodology is clear and grounded in beneficiary needs, with an approved programs and services manual. A very good level.',
  },
  502: {
    ar: 'خرائط العمليات متوفرة ويتم تحسينها، غير أن التحسين المستمر بحاجة إلى دورية موثقة ومؤشرات كفاءة أوضح.',
    en: 'Process maps exist and are being improved, yet continuous improvement needs a documented cadence and clearer efficiency indicators.',
  },
  503: {
    ar: 'خطة التحول الرقمي معتمدة ومرتبطة بالأهداف التشغيلية، مع تقدم ملموس. يُوصى بتسريع رقمنة خدمات المستفيدين.',
    en: 'The digital transformation plan is approved and linked to operational goals, with tangible progress. Accelerating the digitization of beneficiary services is recommended.',
  },
  601: {
    ar: 'مؤشرات رضا المستفيدين مرتفعة وموثقة عبر استبيانات دورية، مع ولاء واضح. أداء متميز في هذا المعيار.',
    en: 'Beneficiary satisfaction indicators are high and documented through periodic surveys, with clear loyalty. Distinguished performance on this criterion.',
  },
  602: {
    ar: 'تقارير قياس الأثر تُظهر نتائج إيجابية على المستفيدين، مع منهجية قياس سليمة يمكن تعزيزها بمقارنات مرجعية.',
    en: 'Impact measurement reports show positive results for beneficiaries, with a sound methodology that could be reinforced with benchmarking.',
  },
  701: {
    ar: 'نتائج رضا الموظفين والمتطوعين جيدة ومعدلات الاستبقاء مستقرة. يُوصى بتحليل أسباب المغادرة لتقليلها.',
    en: 'Employee and volunteer satisfaction results are good and retention rates are stable. Analyzing departure reasons to reduce them is recommended.',
  },
  702: {
    ar: 'برامج التطوير المهني قائمة وأثرها إيجابي، لكن توثيق مسارات التطور الوظيفي بحاجة إلى تحسين.',
    en: 'Professional development programs are in place with a positive impact, but documenting career development paths needs improvement.',
  },
  801: {
    ar: 'الأثر المجتمعي موثق ومرتبط بمستهدفات رؤية 2030، مع مبادرات نوعية. يُقترح توسيع نطاق قياس الأثر ليشمل الاستدامة طويلة المدى.',
    en: 'Community impact is documented and linked to Vision 2030 targets, with quality initiatives. Broadening impact measurement to include long-term sustainability is suggested.',
  },
}

const completedSubRatios = {
  101: 0.82,
  102: 0.75,
  103: 0.9,
  201: 0.78,
  202: 0.8,
  301: 0.76,
  302: 0.7,
  401: 0.72,
  402: 0.68,
  501: 0.85,
  502: 0.65,
  503: 0.6,
  601: 0.88,
  602: 0.82,
  701: 0.74,
  702: 0.7,
  801: 0.8,
}

const ACCREDITED_LABEL = { ar: 'التقييم المعتمد', en: 'Accredited assessment' }
const LEVEL_LABELS = {
  bronze: { ar: 'المستوى البرونزي', en: 'Bronze level' },
  silver: { ar: 'المستوى الفضي', en: 'Silver level' },
  gold: { ar: 'المستوى الذهبي', en: 'Gold level' },
  diamond: { ar: 'المستوى الماسي', en: 'Diamond level' },
}

function request(id, number) {
  return { id, number, path: 'accredited', pathLabel: ACCREDITED_LABEL }
}

function levelCertificate({ id, entity, number, level, percent, requestRef, code, issuedAt }) {
  return {
    id,
    entityId: entity.id,
    number,
    kind: 'accredited',
    level,
    levelLabel: level ? LEVEL_LABELS[level] : null,
    status: 'issued',
    isTest: false,
    percent,
    request: request(requestRef.id, requestRef.number),
    entity,
    verificationCode: code,
    issuedAt,
    revokedAt: null,
    revokedReason: null,
    supersededAt: null,
    suspendedAt: null,
    replaces: null,
    hasPdf: true,
  }
}

const LEVEL_ENTITIES = {
  ehsan: {
    id: 6002,
    reference: 'ENT-006002',
    name: { ar: 'جمعية إحسان الخيرية', en: 'Ehsan Charitable Society' },
  },
  ataa: {
    id: 6004,
    reference: 'ENT-006004',
    name: { ar: 'جمعية عطاء للأيتام', en: 'Ataa Orphans Society' },
  },
  ruwad: {
    id: 6005,
    reference: 'ENT-006005',
    name: { ar: 'مؤسسة رواد التعليم', en: 'Ruwad Education Foundation' },
  },
  tamkeen: {
    id: 6006,
    reference: 'ENT-006006',
    name: {
      ar: 'جمعية تمكين لذوي الإعاقة',
      en: 'Tamkeen Society for People with Disabilities',
    },
  },
  athar: {
    id: 7005,
    reference: 'ENT-007005',
    name: { ar: 'جمعية أثر للتنمية المجتمعية', en: 'Athar Community Development Society' },
  },
}

const LEVEL_CERTIFICATES = [
  levelCertificate({
    id: '2c9e7a41-0b5d-4f63-8e21-7d4a00009110',
    entity: LEVEL_ENTITIES.ehsan,
    number: 'CRT-2026-000010',
    level: 'gold',
    percent: '84.20',
    requestRef: { id: 9310, number: 'ACC-2026-000010' },
    code: 'H2KD-7QWM-PX4T-9ZRA-C6NV-3BYE-5FGL',
    issuedAt: '2026-08-14T08:00:00+00:00',
  }),
  levelCertificate({
    id: '2c9e7a41-0b5d-4f63-8e21-7d4a00009111',
    entity: LEVEL_ENTITIES.ruwad,
    number: 'CRT-2026-000011',
    level: 'diamond',
    percent: '93.10',
    requestRef: { id: 9311, number: 'ACC-2026-000011' },
    code: 'T8MV-3KQZ-W5HA-2NRC-Y7PD-4EXB-6LGU',
    issuedAt: '2026-03-18T08:00:00+00:00',
  }),
  levelCertificate({
    id: '2c9e7a41-0b5d-4f63-8e21-7d4a00009112',
    entity: LEVEL_ENTITIES.athar,
    number: 'CRT-2026-000012',
    level: 'bronze',
    percent: '64.40',
    requestRef: { id: 9312, number: 'ACC-2026-000012' },
    code: 'R5PW-8DXK-M2QT-6VZH-A9CN-1EYB-4JGF',
    issuedAt: '2026-07-19T08:00:00+00:00',
  }),
  levelCertificate({
    id: '2c9e7a41-0b5d-4f63-8e21-7d4a00009113',
    entity: LEVEL_ENTITIES.ataa,
    number: 'CRT-2026-000013',
    level: null,
    percent: '54.00',
    requestRef: { id: 9313, number: 'ACC-2026-000013' },
    code: 'Q4ZN-6WTC-K8MD-3HXA-P2RV-7EYB-9LFG',
    issuedAt: '2026-06-02T08:00:00+00:00',
  }),
  {
    ...levelCertificate({
      id: '2c9e7a41-0b5d-4f63-8e21-7d4a00009109',
      entity: LEVEL_ENTITIES.tamkeen,
      number: 'CRT-2026-000009',
      level: 'gold',
      percent: '81.00',
      requestRef: { id: 9309, number: 'ACC-2026-000009' },
      code: 'D3QF-9MXZ-V6KT-1WHA-N8PC-5RYE-2BGL',
      issuedAt: '2026-04-06T08:00:00+00:00',
    }),
    status: 'revoked',
    revokedAt: '2026-05-10T08:00:00+00:00',
    revokedReason: 'إلغاء بعد مراجعة الأدلة المقدمة.',
  },
]

function verificationUrl(code) {
  return `${window.location.origin}/verify/${code}`
}

export const certificatesFixture = [
  {
    id: '2c9e7a41-0b5d-4f63-8e21-7d4a00009102',
    entityId: FIXTURE_ENTITY_ID,
    number: 'CRT-2026-000001',
    kind: 'accredited',
    level: 'silver',
    levelLabel: LEVEL_LABELS.silver,
    status: 'issued',
    isTest: false,
    percent: '78.00',
    request: request(COMPLETED_ACCREDITED_ID, 'ACC-2026-000001'),
    entity: {
      id: FIXTURE_ENTITY_ID,
      reference: 'ENT-005001',
      name: { ar: 'جمعية البر بالرياض', en: 'Al-Birr Charity — Riyadh' },
    },
    verificationCode: 'K7Q2-M9XA-4TPE-8RWC-2HZN-6YVB-3DFG',
    issuedAt: '2026-06-22T09:00:00+00:00',
    revokedAt: null,
    revokedReason: null,
    supersededAt: null,
    suspendedAt: null,
    replaces: null,
    hasPdf: true,
  },
  {
    id: '2c9e7a41-0b5d-4f63-8e21-7d4a00009104',
    entityId: 6001,
    number: 'CRT-2026-000003',
    kind: 'accredited',
    level: 'silver',
    levelLabel: LEVEL_LABELS.silver,
    status: 'issued',
    isTest: false,
    percent: '74.50',
    request: request(ACCREDITED_FIXTURE_IDS.completedEarlier, 'ACC-2026-000003'),
    entity: {
      id: 6001,
      reference: 'ENT-006001',
      name: { ar: 'مؤسسة نماء للتنمية', en: 'Namaa Development Foundation' },
    },
    verificationCode: '9F3K-MQ2T-7XHD-M4PA-Z81C-QW5N-RT6B',
    issuedAt: '2026-09-05T08:00:00+00:00',
    revokedAt: null,
    revokedReason: null,
    supersededAt: null,
    suspendedAt: null,
    replaces: { id: '2c9e7a41-0b5d-4f63-8e21-7d4a00009103', number: 'CRT-2026-000002' },
    hasPdf: true,
  },
  {
    id: '2c9e7a41-0b5d-4f63-8e21-7d4a00009103',
    entityId: 6001,
    number: 'CRT-2026-000002',
    kind: 'accredited',
    level: 'silver',
    levelLabel: LEVEL_LABELS.silver,
    status: 'superseded',
    isTest: false,
    percent: '74.50',
    request: request(ACCREDITED_FIXTURE_IDS.completedEarlier, 'ACC-2026-000003'),
    entity: {
      id: 6001,
      reference: 'ENT-006001',
      name: { ar: 'مؤسسة نماء للتنمية', en: 'Namaa Development Foundation' },
    },
    verificationCode: 'B4NE-2WQ8-HC7T-KX3M-5RZA-9PDV-1GFY',
    issuedAt: '2026-09-03T08:00:00+00:00',
    revokedAt: null,
    revokedReason: null,
    supersededAt: '2026-09-05T08:00:00+00:00',
    suspendedAt: null,
    replaces: null,
    hasPdf: true,
  },
  ...LEVEL_CERTIFICATES,
].map((certificate) => ({
  ...certificate,
  verificationUrl: verificationUrl(certificate.verificationCode),
}))

export const feedbackRatios = completedSubRatios
export const feedbackNotes = assessorNotes
