import { SEEDED_IDS } from './finance'

const FOLLOWER_ID = 8
const ARCHIVED_PAYMENT_SEQUENCE = 91
const DAY_MS = 24 * 60 * 60 * 1000
const OPEN_COMPLETION_DAYS_LEFT = 14

// The open completion request must stay open whatever the real date, so its deadline is set relative to now
// (end of the Riyadh day, 20:59 UTC). A fixed date expired on 2026-11-12 and broke the e2e flows built on it.
function openCompletionDeadline() {
  const deadline = new Date(Date.now() + OPEN_COMPLETION_DAYS_LEFT * DAY_MS)
  deadline.setUTCHours(20, 59, 0, 0)
  return deadline.toISOString()
}

function paymentOf(sequence) {
  return `3f0c9a52-6d1e-4b7a-9c21-5a1e${String(sequence).padStart(8, '0')}`
}

export const ACCREDITED_FIXTURE_IDS = {
  draft: 9101,
  completedOwn: 9102,
  refunded: 9103,
  completedEarlier: 9104,
  submitted: 9105,
  needsCompletion: 9106,
  assigned: 9107,
  underReview: 9108,
  completedPrevious: 9109,
}

export const ACCREDITED_FIXTURE_FOLLOWER_ID = FOLLOWER_ID

const ACCREDITED_EVIDENCE_IDS = {
  draftInfected: '6b1d3c2a-4e5f-4a7b-8c9d-0e1f00009101',
  draftPending: '6b1d3c2a-4e5f-4a7b-8c9d-0e1f00009102',
  reviewClean: '6b1d3c2a-4e5f-4a7b-8c9d-0e1f00009801',
}

export const cvTextsFixture = {
  'M1.1':
    'اعتمد مجلس الإدارة الرؤية والرسالة في عام 2024 بعد ورش عمل شارك فيها العاملون والمستفيدون، وتنشر الجهة الوثيقة على موقعها الإلكتروني وفي مقرها.',
  'M1.2':
    'تطبق الجهة لائحة حوكمة معتمدة تحدد صلاحيات المجلس والإدارة التنفيذية، وتصدر تقرير إفصاح سنويًا يُعرض على الجمعية العمومية.',
  'M1.3':
    'أطلقت الجهة مبادرة التميز الداخلي التي تكرّم الفرق المتميزة كل ربع سنة، ونُفذت من خلالها اثنتا عشرة مبادرة تحسين.',
  'M2.1':
    'أجرت الجهة دراسة احتياجات شاملة شملت 1,200 مستفيد عبر الاستبانات والمقابلات، وبُنيت البرامج على نتائجها.',
  'M2.2':
    'تعمل الجهة وفق خطة استراتيجية للفترة 2025 إلى 2028 بأربعة محاور ومؤشرات أداء ربع سنوية تتابعها لجنة مختصة.',
  'M3.1':
    'تنفذ الجهة خطة تدريب سنوية بمتوسط 30 ساعة تدريبية لكل موظف، وتقيس أثر التدريب على الأداء الوظيفي.',
  'M3.2':
    'اعتمدت الجهة سياسة لبيئة العمل تشمل المرونة في ساعات الدوام وبرنامجًا للرعاية الصحية للعاملين.',
  'M4.1':
    'ترتبط الجهة بثماني شراكات فاعلة مع جهات حكومية وخاصة، ولكل شراكة اتفاقية ومؤشرات متابعة.',
  'M4.2':
    'تصدر الجهة قوائم مالية مدققة سنويًا، وتعمل على تنويع مصادر الدخل من خلال الأوقاف والشراكات المجتمعية.',
  'M5.1':
    'يتوفر دليل معتمد للبرامج والخدمات يوضح معايير الاستحقاق ومسارات تقديم الخدمة لكل برنامج.',
  'M5.2':
    'وُثّقت العمليات الرئيسية في خرائط معتمدة، وتراجعها الجهة سنويًا لتقليل زمن تقديم الخدمة.',
  'M6.1':
    'تسير الجهة وفق خارطة طريق للتحول الرقمي أتمتت من خلالها استقبال الطلبات ومتابعتها إلكترونيًا.',
  'M6.2': 'يضم سجل الابتكار سبع مبادرات منفذة، أبرزها منصة التطوع الإلكترونية.',
  'M7.1': 'تتابع الجهة رضا العاملين سنويًا، وبلغت نسبة الرضا في آخر قياس 86 في المئة.',
  'M8.1': 'تقيس الجهة رضا المستفيدين بعد كل خدمة، وبلغ متوسط الرضا خلال العام الماضي 91 في المئة.',
  'M8.2':
    'تعد الجهة تقارير قياس الأثر الاجتماعي لبرامجها الرئيسية وفق منهجية العائد الاجتماعي على الاستثمار.',
}

export const feedbackFixture = {
  'M1.1': {
    strengths: 'وضوح الرؤية والرسالة وارتباطهما بأهداف الجهة.',
    improvementPoints: 'ربط الأهداف الاستراتيجية بمؤشرات قياس ربع سنوية.',
    notes: null,
  },
  'M2.2': {
    strengths: 'خطة استراتيجية معتمدة ومحاور واضحة.',
    improvementPoints: null,
    notes: 'يُنصح بنشر ملخص الخطة للمستفيدين.',
  },
  'M8.1': {
    strengths: 'قياس منتظم لرضا المستفيدين بعد كل خدمة.',
    improvementPoints: 'تحليل أسباب عدم الرضا ووضع خطط تحسين مرتبطة بها.',
    notes: null,
  },
}

// Namaa's assessor feedback on ACC-2026-000003, one entry per sub-criterion of its tree.
const NAMAA_FEEDBACK = {
  'M1.1': {
    strengths: 'التوجه الاستراتيجي واضح ومعلن، وترتبط الأهداف بالرؤية بمؤشرات مناسبة.',
    improvementPoints:
      'الفجوة الأبرز في دورية مراجعة التوجه وتحديثه وفق المتغيرات؛ يُنصح باعتماد آلية مراجعة نصف سنوية موثقة.',
    notes: null,
  },
  'M1.2': {
    strengths: 'اعتمدت الجهة لائحة حوكمة جيدة تحدد الأدوار وتعارض المصالح، مع إفصاح دوري مقبول.',
    improvementPoints: 'يُوصى بتعزيز شفافية التقارير المالية ونشرها للمستفيدين وأصحاب المصلحة.',
    notes: null,
  },
  'M1.3': {
    strengths:
      'تُظهر الجهة ثقافة تميز راسخة تنعكس في السلوك اليومي للقيادة، مع أدلة واضحة على القيادة بالقدوة.',
    improvementPoints:
      'يُوصى بتوثيق مبادرات التميز الداخلية بشكل أكثر منهجية وربطها بمؤشرات قياس دورية.',
    notes: null,
  },
  'M2.1': {
    strengths:
      'تعتمد الاستراتيجية على دراسة موثقة لاحتياجات المستفيدين خلال العامين الماضيين، وتحليل بيئي جيد.',
    improvementPoints: 'يُقترح تحديث الدراسة سنويًا وربط نتائجها مباشرة بالمبادرات.',
    notes: null,
  },
  'M2.2': {
    strengths:
      'الخطة الاستراتيجية معتمدة وواضحة، وخطة النشر متوفرة لكنها بحاجة إلى مؤشرات تنفيذ أدق على مستوى الإدارات.',
    improvementPoints: 'يُوصى بربط كل مبادرة بمؤشر تنفيذ ومسؤول عنه.',
    notes: null,
  },
  'M3.1': {
    strengths: 'خطة التدريب السنوية مبنية على تحليل فجوات المهارات، وبرنامج المتطوعين منظم.',
    improvementPoints: 'يُوصى بقياس أثر التدريب على الأداء بشكل كمّي.',
    notes: null,
  },
  'M3.2': {
    strengths:
      'بيئة العمل محفزة وسياسات الرعاية موجودة، غير أن مؤشرات رضا الموظفين تحتاج إلى قياس أكثر انتظامًا وربطها بخطط التحسين.',
    improvementPoints: null,
    notes: null,
  },
  'M4.1': {
    strengths: 'سجل الشراكات موثق واتفاقياتها معتمدة، مع قيمة مضافة واضحة من بعض الشراكات.',
    improvementPoints: 'يُقترح تقييم أثر كل شراكة سنويًا.',
    notes: null,
  },
  'M4.2': {
    strengths:
      'القوائم المالية مدققة والاستدامة المالية جيدة، لكن تنويع مصادر الدخل ما زال محدودًا؛ يُوصى بتفعيل خطة تنويع الدخل المعتمدة.',
    improvementPoints: null,
    notes: null,
  },
  'M5.1': {
    strengths:
      'منهجية تصميم البرامج واضحة ومبنية على احتياجات المستفيدين، مع دليل معتمد للبرامج والخدمات.',
    improvementPoints: 'يُوصى بإشراك المستفيدين في مراجعة البرامج قبل إطلاقها.',
    notes: null,
  },
  'M5.2': {
    strengths:
      'خرائط العمليات متوفرة ويتم تحسينها، غير أن التحسين المستمر بحاجة إلى دورية موثقة ومؤشرات كفاءة أوضح.',
    improvementPoints: null,
    notes: null,
  },
  'M6.1': {
    strengths:
      'خطة التحول الرقمي معتمدة ومرتبطة بالأهداف التشغيلية، مع تقدم ملموس في إدارة المعرفة.',
    improvementPoints: 'يُوصى بتسريع رقمنة خدمات المستفيدين.',
    notes: null,
  },
  'M6.2': {
    strengths: 'تُوثّق الجهة الدروس المستفادة من برامجها وتشاركها بين فرقها.',
    improvementPoints:
      'يُوصى بآلية معتمدة لجمع الأفكار المبتكرة وتقييمها وقياس أثر ما يُنفّذ منها.',
    notes: null,
  },
  'M7.1': {
    strengths: 'نتائج رضا الموظفين والمتطوعين جيدة ومعدلات الاستبقاء مستقرة.',
    improvementPoints: 'يُوصى بتحليل أسباب المغادرة لتقليلها.',
    notes: null,
  },
  'M7.2': {
    strengths:
      'برامج التطوير المهني قائمة وأثرها إيجابي، لكن توثيق مسارات التطور الوظيفي بحاجة إلى تحسين.',
    improvementPoints: null,
    notes: null,
  },
  'M8.1': {
    strengths: 'مؤشرات رضا المستفيدين جيدة وموثقة عبر استبيانات دورية، مع ولاء واضح.',
    improvementPoints: 'يُوصى بتحليل الشكاوى وربط نتائجها بخطط تحسين الخدمات.',
    notes: null,
  },
  'M8.2': {
    strengths: 'تقارير قياس الأثر تُظهر نتائج إيجابية على المستفيدين بمنهجية قياس سليمة.',
    improvementPoints: 'يُوصى بتعزيزها بمقارنات مرجعية وقياس الأثر على المدى الطويل.',
    notes: null,
  },
  'M9.1': {
    strengths: 'النتائج المالية والتشغيلية مستقرة وتحققت معظم مستهدفات الموازنة.',
    improvementPoints: 'يُوصى بعرض اتجاه النتائج لثلاث سنوات على الأقل مع مقارنتها بجهات مماثلة.',
    notes: null,
  },
  'M9.2': {
    strengths: 'مؤشرات الأداء المؤسسي معرّفة ومرتبطة بالخطة الاستراتيجية.',
    improvementPoints: 'يُوصى بتحديد مستهدفات سنوية لكل مؤشر وتحليل أسباب الانحراف عنها.',
    notes: null,
  },
}

export const ALL_CV = Object.keys(cvTextsFixture)

export const FULL_EVIDENCE = [
  ['M1.1', 'sub_101_1', 'vision-mission-2024.pdf'],
  ['M1.1', 'point_1001_1', 'vision-publication-evidence.pdf'],
  ['M1.2', 'sub_102_1', 'governance-charter.pdf'],
  ['M1.2', 'sub_102_2', 'compliance-report-2025.pdf'],
  ['M1.2', 'point_1101_1', 'governance-charter-approval.pdf'],
  ['M2.1', 'sub_201_1', 'beneficiary-needs-study.pdf'],
  ['M2.1', 'point_1201_1', 'needs-survey-results.xlsx'],
  ['M2.2', 'sub_202_1', 'strategic-plan-2025-2028.pdf'],
  ['M3.1', 'sub_301_1', 'training-plan-2026.pdf'],
  ['M3.1', 'point_1301_1', 'training-records.xlsx'],
  ['M4.1', 'sub_401_1', 'partnership-register.xlsx'],
  ['M4.2', 'sub_402_1', 'audited-statements-2025.pdf'],
  ['M5.1', 'sub_501_1', 'programmes-manual.pdf'],
  ['M5.1', 'point_1501_1', 'service-standards.pdf'],
  ['M8.1', 'sub_801_1', 'satisfaction-survey-2025.pdf'],
  ['M8.2', 'sub_802_1', 'impact-report-2025.pdf'],
]

export function evidence(entries, { scan = 'clean', exceptions = {} } = {}) {
  return entries.map(([subCode, attachmentKey, name], index) => ({
    subCode,
    attachmentKey,
    name,
    scanStatus: exceptions[attachmentKey]?.scanStatus ?? scan,
    id: exceptions[attachmentKey]?.id ?? null,
    order: index,
  }))
}

const DRAFT_EVIDENCE = [
  ...evidence(
    FULL_EVIDENCE.filter(([, key]) => key !== 'sub_802_1'),
    {
      exceptions: {
        sub_402_1: { scanStatus: 'infected', id: ACCREDITED_EVIDENCE_IDS.draftInfected },
        sub_401_1: { scanStatus: 'pending', id: ACCREDITED_EVIDENCE_IDS.draftPending },
      },
    },
  ),
  {
    subCode: 'M1.3',
    attachmentKey: null,
    name: 'excellence-initiatives-photos.jpg',
    scanStatus: 'clean',
    id: null,
    order: 99,
  },
]

export const accreditedRequestsFixture = [
  {
    id: ACCREDITED_FIXTURE_IDS.completedOwn,
    number: 'ACC-2026-000001',
    entityId: 5001,
    paymentId: SEEDED_IDS.paidAccredited,
    status: 'completed',
    scoring: { pattern: 'steady', count: null },
    cvCodes: ALL_CV,
    evidence: evidence(FULL_EVIDENCE),
    feedbackCodes: ['M1.1', 'M2.2', 'M8.1'],
    assignments: [
      {
        assessorId: 10,
        status: 'completed',
        assignedAt: '2026-08-21T07:00:00Z',
        startedAt: '2026-08-22T06:30:00Z',
        endedAt: '2026-09-10T12:00:00Z',
      },
    ],
    completionRequests: [],
    createdAt: '2026-08-12T08:15:00Z',
    submittedAt: '2026-08-20T09:00:00Z',
    completedAt: '2026-09-10T12:00:00Z',
    refundedAt: null,
  },
  {
    id: ACCREDITED_FIXTURE_IDS.refunded,
    number: 'ACC-2026-000002',
    entityId: 6002,
    paymentId: SEEDED_IDS.refunded,
    status: 'refunded',
    scoring: { pattern: 'developing', count: 4 },
    cvCodes: ALL_CV,
    evidence: evidence(FULL_EVIDENCE),
    feedbackCodes: [],
    assignments: [
      {
        assessorId: 11,
        status: 'ended',
        assignedAt: '2026-08-28T08:00:00Z',
        startedAt: '2026-08-30T07:00:00Z',
        endedAt: '2026-09-02T09:20:00Z',
        endReason: 'refunded',
      },
    ],
    completionRequests: [],
    createdAt: '2026-08-25T07:05:00Z',
    submittedAt: '2026-08-27T10:00:00Z',
    completedAt: null,
    refundedAt: '2026-09-02T09:20:00Z',
  },
  {
    id: ACCREDITED_FIXTURE_IDS.completedEarlier,
    number: 'ACC-2026-000003',
    entityId: 6001,
    paymentId: SEEDED_IDS.paidApplePay,
    status: 'completed',
    scoring: { pattern: 'silver', count: null },
    cvCodes: ALL_CV,
    evidence: evidence(FULL_EVIDENCE),
    feedbackCodes: [],
    feedback: NAMAA_FEEDBACK,
    assignments: [
      {
        assessorId: 15,
        status: 'completed',
        assignedAt: '2026-08-29T08:00:00Z',
        startedAt: '2026-08-30T07:00:00Z',
        endedAt: '2026-09-02T13:00:00Z',
      },
    ],
    completionRequests: [],
    createdAt: '2026-08-28T13:36:00Z',
    submittedAt: '2026-08-29T06:30:00Z',
    completedAt: '2026-09-02T13:00:00Z',
    refundedAt: null,
  },
  {
    id: ACCREDITED_FIXTURE_IDS.draft,
    number: 'ACC-2026-000004',
    entityId: 6001,
    paymentId: paymentOf(12),
    status: 'draft',
    scoring: { pattern: 'steady', count: 0 },
    cvCodes: ALL_CV.filter((code) => code !== 'M8.2'),
    evidence: DRAFT_EVIDENCE,
    feedbackCodes: [],
    assignments: [],
    completionRequests: [],
    createdAt: '2026-09-03T07:26:00Z',
    submittedAt: null,
    completedAt: null,
    refundedAt: null,
  },
  {
    id: ACCREDITED_FIXTURE_IDS.submitted,
    number: 'ACC-2026-000005',
    entityId: 6003,
    paymentId: paymentOf(14),
    status: 'submitted',
    scoring: { pattern: 'steady', count: 0 },
    cvCodes: ALL_CV,
    evidence: evidence(FULL_EVIDENCE),
    feedbackCodes: [],
    assignments: [],
    completionRequests: [],
    createdAt: '2026-09-14T11:46:00Z',
    submittedAt: '2026-09-16T08:00:00Z',
    completedAt: null,
    refundedAt: null,
  },
  {
    id: ACCREDITED_FIXTURE_IDS.needsCompletion,
    number: 'ACC-2026-000006',
    entityId: 6004,
    paymentId: paymentOf(15),
    status: 'needs-completion',
    scoring: { pattern: 'developing', count: 6 },
    cvCodes: ALL_CV,
    evidence: evidence(FULL_EVIDENCE.filter(([, key]) => key !== 'sub_402_1')),
    feedbackCodes: [],
    assignments: [
      {
        assessorId: 11,
        status: 'active',
        assignedAt: '2026-09-22T07:00:00Z',
        startedAt: '2026-09-23T06:45:00Z',
        endedAt: null,
      },
    ],
    completionRequests: [
      {
        status: 'open',
        notes: 'أكمل وصف تنفيذ الخطة الاستراتيجية، وأرفق القوائم المالية المدققة للعام الأخير.',
        items: [
          { subCode: 'M2.2', kind: 'cv', note: 'أضف أمثلة على مؤشرات الأداء ونتائج المتابعة.' },
          { subCode: 'M4.2', kind: 'evidence', note: 'القوائم المالية المدققة لعام 2025.' },
        ],
        requestedAt: '2026-10-28T09:00:00Z',
        deadlineAt: openCompletionDeadline(),
        expiryOutcome: 'continue_as_submitted',
        resolvedAt: null,
      },
    ],
    createdAt: '2026-09-19T07:00:00Z',
    submittedAt: '2026-09-21T09:30:00Z',
    completedAt: null,
    refundedAt: null,
  },
  {
    id: ACCREDITED_FIXTURE_IDS.assigned,
    number: 'ACC-2026-000007',
    entityId: 6006,
    paymentId: paymentOf(17),
    status: 'assigned',
    scoring: { pattern: 'steady', count: 0 },
    cvCodes: ALL_CV,
    evidence: evidence(FULL_EVIDENCE),
    feedbackCodes: [],
    assignments: [
      {
        assessorId: 10,
        status: 'active',
        assignedAt: '2026-10-01T07:00:00Z',
        startedAt: null,
        endedAt: null,
      },
    ],
    completionRequests: [],
    createdAt: '2026-09-29T08:35:00Z',
    submittedAt: '2026-09-30T10:00:00Z',
    completedAt: null,
    refundedAt: null,
  },
  {
    id: ACCREDITED_FIXTURE_IDS.underReview,
    number: 'ACC-2026-000008',
    entityId: 6002,
    paymentId: paymentOf(19),
    status: 'under-review',
    scoring: { pattern: 'steady', count: 10 },
    cvCodes: ALL_CV,
    evidence: [
      ...evidence(FULL_EVIDENCE, {
        exceptions: { sub_101_1: { scanStatus: 'clean', id: ACCREDITED_EVIDENCE_IDS.reviewClean } },
      }),
      {
        subCode: 'M1.3',
        attachmentKey: null,
        name: 'initiative-draft.pdf',
        scanStatus: 'pending',
        id: null,
        order: 98,
      },
    ],
    feedbackCodes: ['M1.1'],
    assignments: [
      {
        assessorId: 16,
        status: 'reassigned',
        assignedAt: '2026-10-26T07:00:00Z',
        startedAt: null,
        endedAt: '2026-10-26T11:00:00Z',
        reason: null,
      },
      {
        assessorId: 10,
        status: 'active',
        assignedAt: '2026-10-26T11:00:00Z',
        startedAt: '2026-10-27T06:00:00Z',
        endedAt: null,
        reason: 'إعادة توزيع الأحمال بين المقيّمين بعد إجازة المقيّم.',
      },
    ],
    completionRequests: [],
    createdAt: '2026-10-09T07:50:00Z',
    submittedAt: '2026-10-25T08:00:00Z',
    completedAt: null,
    refundedAt: null,
  },
  {
    id: ACCREDITED_FIXTURE_IDS.completedPrevious,
    number: 'ACC-2025-000012',
    entityId: 5001,
    paymentId: paymentOf(ARCHIVED_PAYMENT_SEQUENCE),
    status: 'completed',
    scoring: { pattern: 'developing', count: null },
    cvCodes: ALL_CV,
    evidence: evidence(FULL_EVIDENCE),
    feedbackCodes: ['M1.1'],
    assignments: [
      {
        assessorId: 11,
        status: 'completed',
        assignedAt: '2025-10-05T07:00:00Z',
        startedAt: '2025-10-06T06:30:00Z',
        endedAt: '2025-10-30T12:00:00Z',
      },
    ],
    completionRequests: [],
    createdAt: '2025-09-28T08:15:00Z',
    submittedAt: '2025-10-04T09:00:00Z',
    completedAt: '2025-10-30T12:00:00Z',
    refundedAt: null,
  },
]
