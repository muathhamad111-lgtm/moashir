export const COMPLETED_ADVANCED_ID = 9302

const PAST_CYCLE_NAME = {
  ar: 'جائزة التميز المؤسسي — الدورة 2024',
  en: 'Institutional Excellence Award — 2024 Cycle',
}

const initialFeedback = {
  101: {
    scorePercent: 84,
    comment: {
      ar: 'التوجه الاستراتيجي واضح ومعلن وترتبط الأهداف بالرؤية بمؤشرات مناسبة. يُوصى باعتماد آلية مراجعة نصف سنوية موثقة لتحديث التوجه وفق المتغيرات.',
      en: 'The strategic direction is clear and published, and goals are linked to the vision through suitable indicators. Adopting a documented semi-annual review mechanism to update the direction is recommended.',
    },
  },
  102: {
    scorePercent: 78,
    comment: {
      ar: 'لائحة الحوكمة معتمدة وتحدد الأدوار وتعارض المصالح مع إفصاح دوري مقبول. يُوصى بتعزيز شفافية التقارير المالية ونشرها لأصحاب المصلحة.',
      en: 'The governance charter is approved and defines roles and conflicts of interest with acceptable periodic disclosure. Strengthening the transparency of financial reports and publishing them to stakeholders is recommended.',
    },
  },
  103: {
    scorePercent: 90,
    comment: {
      ar: 'تُظهر الجهة ثقافة تميز راسخة تنعكس في السلوك اليومي للقيادة مع أدلة واضحة على القيادة بالقدوة. يُوصى بتوثيق مبادرات التميز الداخلية بشكل أكثر منهجية.',
      en: 'The organization shows a well-rooted excellence culture reflected in leadership behavior, with clear evidence of leading by example. Documenting internal excellence initiatives more systematically is recommended.',
    },
  },
  201: {
    scorePercent: 80,
    comment: {
      ar: 'تعتمد الاستراتيجية على دراسة موثقة لاحتياجات المستفيدين وتحليل بيئي جيد. يُقترح تحديث الدراسة سنويًا وربط نتائجها مباشرة بالمبادرات.',
      en: 'The strategy rests on a documented beneficiary needs study and a good environmental analysis. Updating the study annually and linking its findings directly to initiatives is suggested.',
    },
  },
  601: {
    scorePercent: 92,
    comment: {
      ar: 'مؤشرات رضا المستفيدين مرتفعة وموثقة عبر استبيانات دورية مع ولاء واضح. أداء متميز في هذا المعيار يُنصح بالحفاظ عليه ومقارنته مرجعيًا.',
      en: 'Beneficiary satisfaction indicators are high and documented through periodic surveys, with clear loyalty. Distinguished performance on this criterion; maintaining it and benchmarking it is advised.',
    },
  },
}

export const advancedResultsFixture = [
  {
    id: COMPLETED_ADVANCED_ID,
    entityId: 5001,
    status: 'announced',
    cycleId: 'cycle-2024',
    cycleName: PAST_CYCLE_NAME,
    initialPercent: 85,
    certificateId: 'cert-adv-9302',
    certificateKind: 'advanced-winner',
    announcedAt: '2024-12-15T10:00:00+03:00',
  },
]

export const advancedFeedbackReportsFixture = [
  {
    assessmentId: COMPLETED_ADVANCED_ID,
    source: 'first-consultant',
    status: 'available',
    generatedAt: '2024-12-15T10:30:00+03:00',
    items: Object.entries(initialFeedback).map(([subCriterionId, entry]) => ({
      subCriterionId: Number(subCriterionId),
      scorePercent: entry.scorePercent,
      comment: entry.comment,
    })),
  },
]

export const advancedCertificatesFixture = [
  {
    id: 'cert-adv-9302',
    entityId: 5001,
    assessmentId: COMPLETED_ADVANCED_ID,
    cycleId: 'cycle-2024',
    kind: 'advanced',
    resultKind: 'advanced-winner',
    entityName: { ar: 'جمعية البر بالرياض', en: 'Al-Birr Charity — Riyadh' },
    cycleName: PAST_CYCLE_NAME,
    issuedAt: '2024-12-15T12:00:00+03:00',
    downloadUrl: '/assets/certificates/issued/cert-adv-9302.pdf',
  },
]
