import { FIXTURE_ENTITY_ID } from './assessments'
import { ACCREDITED_FIXTURE_IDS } from './accreditedRequests'
import { computePricing } from './bagProduct'

export { FIXTURE_ENTITY_ID }

// The kit is built on the main entity's (Namaa's) latest accredited result.
export const BAG_SOURCE_ASSESSMENT_ID = ACCREDITED_FIXTURE_IDS.completedEarlier
export const BAG_SOURCE_REQUEST_NUMBER = 'ACC-2026-000003'
export const BAG_SUBSCRIPTION_ID = 'bag-5001'

export function seedReference() {
  return {
    assessmentId: BAG_SOURCE_ASSESSMENT_ID,
    number: BAG_SOURCE_REQUEST_NUMBER,
    version: 1,
    kind: 'accredited',
    completedAt: '2026-09-02T11:00:00+03:00',
    frozenAt: '2026-10-04T09:00:00+03:00',
    scorePercent: 74.54,
  }
}

// Mirrors the criteria version of Namaa's ACC-2026-000003 and the points it earned there,
// so the kit's gaps match the result the entity sees in its excellence tools.
const SUB_MIRROR = {
  101: {
    weight: 400,
    mainCriterionId: 1,
    name: { ar: 'التوجه الاستراتيجي والرؤية', en: 'Strategic Direction and Vision' },
  },
  102: {
    weight: 350,
    mainCriterionId: 1,
    name: { ar: 'الحوكمة والامتثال', en: 'Governance and Compliance' },
  },
  103: {
    weight: 250,
    mainCriterionId: 1,
    name: { ar: 'ثقافة التميز والقيادة بالقدوة', en: 'Excellence Culture and Leading by Example' },
  },
  201: {
    weight: 500,
    mainCriterionId: 2,
    name: {
      ar: 'تحليل البيئة واحتياجات المستفيدين',
      en: 'Environment and Beneficiary Needs Analysis',
    },
  },
  202: {
    weight: 400,
    mainCriterionId: 2,
    name: { ar: 'صياغة الخطة الاستراتيجية ونشرها', en: 'Strategy Formulation and Deployment' },
  },
  301: {
    weight: 400,
    mainCriterionId: 3,
    name: { ar: 'استقطاب الكفاءات وتنميتها', en: 'Talent Attraction and Development' },
  },
  302: {
    weight: 400,
    mainCriterionId: 3,
    name: { ar: 'التمكين والرعاية وبيئة العمل', en: 'Empowerment, Wellbeing and Workplace' },
  },
  401: {
    weight: 400,
    mainCriterionId: 4,
    name: { ar: 'إدارة الشراكات الاستراتيجية', en: 'Strategic Partnership Management' },
  },
  402: {
    weight: 300,
    mainCriterionId: 4,
    name: {
      ar: 'الاستدامة المالية وإدارة الموارد',
      en: 'Financial Sustainability and Resource Management',
    },
  },
  501: {
    weight: 500,
    mainCriterionId: 5,
    name: { ar: 'تصميم البرامج والخدمات', en: 'Programme and Service Design' },
  },
  502: {
    weight: 700,
    mainCriterionId: 5,
    name: { ar: 'إدارة العمليات وتحسينها', en: 'Process Management and Improvement' },
  },
  601: {
    weight: 300,
    mainCriterionId: 6,
    name: {
      ar: 'التحول الرقمي وإدارة المعرفة',
      en: 'Digital Transformation and Knowledge Management',
    },
  },
  602: {
    weight: 300,
    mainCriterionId: 6,
    name: { ar: 'الابتكار والتعلم المؤسسي', en: 'Innovation and Organisational Learning' },
  },
  701: {
    weight: 350,
    mainCriterionId: 7,
    name: { ar: 'رضا الموظفين والمتطوعين', en: 'Employee and Volunteer Satisfaction' },
  },
  702: {
    weight: 350,
    mainCriterionId: 7,
    name: { ar: 'نتائج التطوير المهني', en: 'Professional Development Results' },
  },
  801: {
    weight: 600,
    mainCriterionId: 8,
    name: { ar: 'رضا المستفيدين وولاؤهم', en: 'Beneficiary Satisfaction and Loyalty' },
  },
  802: {
    weight: 600,
    mainCriterionId: 8,
    name: { ar: 'أثر البرامج على المستفيدين', en: 'Programme Impact on Beneficiaries' },
  },
  901: {
    weight: 700,
    mainCriterionId: 9,
    name: { ar: 'النتائج المالية والتشغيلية', en: 'Financial and Operational Results' },
  },
  902: {
    weight: 400,
    mainCriterionId: 9,
    name: { ar: 'مؤشرات الأداء المؤسسي الرئيسية', en: 'Key Institutional Performance Indicators' },
  },
}

export const MAIN_MIRROR = {
  1: { ar: 'القيادة الإدارية', en: 'Administrative Leadership' },
  2: { ar: 'التخطيط الاستراتيجي', en: 'Strategic Planning' },
  3: { ar: 'إدارة الموارد البشرية والمتطوعين', en: 'Human Resources and Volunteers Management' },
  4: { ar: 'إدارة الموارد والشراكات', en: 'Resources and Partnerships Management' },
  5: { ar: 'إدارة العمليات والخدمات', en: 'Processes and Services Management' },
  6: { ar: 'الابتكار وإدارة المعرفة', en: 'Innovation and Knowledge Management' },
  7: { ar: 'نتائج الموارد البشرية', en: 'People Results' },
  8: { ar: 'نتائج المستفيدين', en: 'Beneficiary Results' },
  9: { ar: 'نتائج الأداء الرئيسية', en: 'Key Performance Results' },
}

const SUB_EARNED = {
  101: 300,
  102: 259.5,
  103: 182.5,
  201: 375,
  202: 300,
  301: 298,
  302: 292,
  401: 295,
  402: 225,
  501: 370,
  502: 511,
  601: 240,
  602: 210,
  701: 262.5,
  702: 252,
  801: 462,
  802: 438,
  901: 560,
  902: 280,
}

const UNMET_POINTS = {
  802: [
    {
      id: 'gp-802-1',
      ar: 'قياس أثر البرامج على المستفيدين بمؤشرات قبل التنفيذ وبعده.',
      en: 'Measuring programme impact on beneficiaries with before-and-after indicators.',
    },
    {
      id: 'gp-802-2',
      ar: 'ربط نتائج قياس الأثر بقرارات تطوير البرامج.',
      en: 'Linking impact-measurement results to programme-improvement decisions.',
    },
  ],
  801: [
    {
      id: 'gp-801-1',
      ar: 'قياس دوري لرضا المستفيدين بعينة ممثلة ومنهجية موثقة.',
      en: 'Regular beneficiary-satisfaction measurement with a representative sample and a documented method.',
    },
  ],
  502: [
    {
      id: 'gp-502-1',
      ar: 'دورية موثقة للتحسين المستمر للعمليات.',
      en: 'A documented cadence for continuous process improvement.',
    },
    {
      id: 'gp-502-2',
      ar: 'مؤشرات كفاءة أوضح على مستوى العمليات التشغيلية.',
      en: 'Clearer efficiency indicators at the operational-process level.',
    },
  ],
  402: [
    {
      id: 'gp-402-1',
      ar: 'تفعيل خطة معتمدة لتنويع مصادر الدخل.',
      en: 'Activating an approved income-source diversification plan.',
    },
  ],
  302: [
    {
      id: 'gp-302-1',
      ar: 'قياس منتظم لرضا الموظفين وربطه بخطط التحسين.',
      en: 'Regular employee-satisfaction measurement linked to improvement plans.',
    },
  ],
  702: [
    {
      id: 'gp-702-1',
      ar: 'توثيق مسارات التطور الوظيفي وربطها بالأداء.',
      en: 'Documenting career-development paths and linking them to performance.',
    },
  ],
  401: [
    {
      id: 'gp-401-1',
      ar: 'تقييم أثر كل شراكة سنويًا بمنهجية موثقة.',
      en: 'Assessing the impact of each partnership annually with a documented method.',
    },
  ],
  301: [
    {
      id: 'gp-301-1',
      ar: 'قياس أثر التدريب على الأداء بشكل كمّي.',
      en: 'Quantitatively measuring the impact of training on performance.',
    },
  ],
}

const GAP_GUIDANCE = {
  'gap-502': {
    priority: 'high',
    status: 'approved',
    consultantNote: {
      ar: 'العمليات مستقرة نسبيًا لكنها تحتاج دورية موثقة للتحسين المستمر.',
      en: 'Operations are relatively stable but need a documented continuous-improvement cadence.',
    },
    recommendedAction: {
      ar: 'توثيق دورة تحسين ربع سنوية مع مؤشرات كفاءة واضحة على مستوى العملية.',
      en: 'Document a quarterly improvement cycle with clear process-level efficiency indicators.',
    },
  },
  'gap-802': {
    priority: 'high',
    status: 'approved',
    consultantNote: {
      ar: 'أثر البرامج يُذكر وصفيًا ولا يُقاس بمؤشرات قبل التنفيذ وبعده.',
      en: 'Programme impact is described but not measured with before-and-after indicators.',
    },
    recommendedAction: {
      ar: 'اعتماد إطار لقياس الأثر وتطبيقه على برنامجين رئيسيين خلال المرحلة.',
      en: 'Adopt an impact-measurement framework and apply it to two core programmes this stage.',
    },
  },
  'gap-801': {
    priority: 'medium',
    status: 'approved',
    consultantNote: {
      ar: 'استبيانات الرضا موجودة لكنها غير منتظمة ولا تغطي جميع الخدمات.',
      en: 'Satisfaction surveys exist but are irregular and do not cover every service.',
    },
    recommendedAction: {
      ar: 'جدولة قياس ربع سنوي لرضا المستفيدين يغطي جميع الخدمات ونشر نتائجه داخليًا.',
      en: 'Schedule a quarterly beneficiary-satisfaction measurement covering every service and share the results internally.',
    },
  },
}

function buildGaps() {
  return Object.entries(SUB_MIRROR).map(([subId, meta]) => {
    const id = Number(subId)
    const target = meta.weight
    const current = SUB_EARNED[id] ?? 0
    const gap = target - current
    const guidance = GAP_GUIDANCE[`gap-${id}`] ?? {}
    return {
      id: `gap-${id}`,
      subCriterionId: id,
      mainCriterionId: meta.mainCriterionId,
      subName: { ...meta.name },
      mainName: { ...MAIN_MIRROR[meta.mainCriterionId] },
      target,
      current,
      gap,
      gapPercent: Math.round((gap / target) * 100),
      priority: guidance.priority ?? null,
      status: guidance.status ?? 'approved',
      consultantNote: guidance.consultantNote ? { ...guidance.consultantNote } : null,
      recommendedAction: guidance.recommendedAction ? { ...guidance.recommendedAction } : null,
      unmetPoints: (UNMET_POINTS[id] ?? []).map((p) => ({
        id: p.id,
        description: { ar: p.ar, en: p.en },
      })),
      stageId: null,
      open: false,
      helperFiles: [],
    }
  })
}

export const bagStagesFixture = [
  {
    id: 'stage-1',
    order: 1,
    status: 'active',
    title: { ar: 'المرحلة الأولى — الأساسيات التشغيلية', en: 'Stage 1 — Operational fundamentals' },
    description: {
      ar: 'معالجة الفجوات الأكثر تأثيرًا في العمليات وأثر البرامج ورضا المستفيدين.',
      en: 'Address the highest-impact gaps in operations, programme impact, and beneficiary satisfaction.',
    },
    startedAt: '2026-10-06T09:00:00+03:00',
    endsAt: '2027-02-06T09:00:00+03:00',
  },
  {
    id: 'stage-2',
    order: 2,
    status: 'open',
    title: { ar: 'المرحلة الثانية — التمكين والنتائج', en: 'Stage 2 — Enablement & results' },
    description: {
      ar: 'مرحلة لاحقة تركّز على تمكين الموارد البشرية ونتائج التطوير المهني والشراكات.',
      en: 'A later stage focused on people enablement, professional-development results, and partnerships.',
    },
    startedAt: null,
    endsAt: null,
  },
]

const stage1HelperFiles = {
  'gap-502': [
    {
      id: 'hf-502-1',
      gapId: 'gap-502',
      subCriterionId: 502,
      guidingPointId: 'gp-502-1',
      kind: 'procedure',
      name: {
        ar: 'إجراء التحسين المستمر للعمليات',
        en: 'Continuous process improvement procedure',
      },
      fileName: 'continuous-improvement-procedure.pdf',
      sizeKb: 540,
      url: '/assets/bag/helpers/continuous-improvement-procedure.pdf',
      attachedAt: '2026-10-06T10:10:00+03:00',
    },
  ],
  'gap-802': [
    {
      id: 'hf-802-1',
      gapId: 'gap-802',
      subCriterionId: 802,
      guidingPointId: 'gp-802-1',
      kind: 'template',
      name: { ar: 'نموذج إطار قياس الأثر', en: 'Impact-measurement framework template' },
      fileName: 'impact-measurement-framework.docx',
      sizeKb: 320,
      url: '/assets/bag/helpers/impact-measurement-framework.docx',
      attachedAt: '2026-10-06T10:00:00+03:00',
    },
  ],
  'gap-801': [
    {
      id: 'hf-801-1',
      gapId: 'gap-801',
      subCriterionId: 801,
      guidingPointId: 'gp-801-1',
      kind: 'guide',
      name: { ar: 'دليل قياس رضا المستفيدين', en: 'Beneficiary-satisfaction measurement guide' },
      fileName: 'beneficiary-satisfaction-guide.pdf',
      sizeKb: 610,
      url: '/assets/bag/helpers/beneficiary-satisfaction-guide.pdf',
      attachedAt: '2026-10-06T10:05:00+03:00',
    },
  ],
}

export const STAGE_1_OPEN_GAP_IDS = ['gap-502', 'gap-802', 'gap-801']

export function seedGaps() {
  return buildGaps().map((g) => {
    if (STAGE_1_OPEN_GAP_IDS.includes(g.id)) {
      return {
        ...g,
        open: true,
        stageId: 'stage-1',
        helperFiles: (stage1HelperFiles[g.id] ?? []).map((f) => ({ ...f })),
      }
    }
    return { ...g }
  })
}

export function seedSubscription() {
  const pricing = computePricing()
  return {
    id: BAG_SUBSCRIPTION_ID,
    entityId: FIXTURE_ENTITY_ID,
    status: 'stage-active',
    source: 'accredited',
    sourceAssessmentId: BAG_SOURCE_ASSESSMENT_ID,
    referenceVersion: 1,
    pricing,
    fee: pricing.total,
    currency: pricing.currency,
    paymentRef: 'bag-pay-5001',
    activeStageId: 'stage-1',
    activatedAt: '2026-10-05T09:00:00+03:00',
    createdAt: '2026-10-04T09:00:00+03:00',
  }
}

export function seedEligibility() {
  const pricing = computePricing()
  return {
    eligible: true,
    source: 'accredited',
    sourceAssessmentId: BAG_SOURCE_ASSESSMENT_ID,
    sourceCompletedAt: '2026-09-02T11:00:00+03:00',
    sourceScorePercent: 74.54,
    referenceVersion: 1,
    pricing,
    fee: pricing.total,
    currency: pricing.currency,
  }
}

// Namaa holds a single accredited result, so it has no earlier kit.
export function seedBagHistory() {
  return []
}

export function seedAssessmentContext() {
  return {
    reference: {
      kind: 'accredited',
      assessmentId: BAG_SOURCE_ASSESSMENT_ID,
      version: 1,
      scorePercent: 74.54,
      completedAt: '2026-09-02T11:00:00+03:00',
    },
    context: [
      { kind: 'self', scorePercent: 82, completedAt: '2026-05-10T11:00:00+03:00' },
      { kind: 'advanced', scorePercent: 74, completedAt: '2026-04-01T11:00:00+03:00' },
    ],
  }
}

const SUB_EARNED_AFTER = {
  ...SUB_EARNED,
  502: 602,
  802: 600,
  801: 600,
  302: 280,
}

function percentOf(points, weight) {
  return Number(((points / weight) * 100).toFixed(2))
}

function outcomeFor(diff, gapAfter) {
  if (gapAfter <= 0) return 'closed'
  if (diff > 0) return 'partially-improved'
  if (diff < 0) return 'regressed'
  return 'stable'
}

export function buildReassessment() {
  const criteria = Object.entries(SUB_MIRROR).map(([subId, meta]) => {
    const id = Number(subId)
    const weight = meta.weight
    const before = SUB_EARNED[id] ?? 0
    const after = SUB_EARNED_AFTER[id] ?? before
    const diff = after - before
    const gapAfter = weight - after
    return {
      subCriterionId: id,
      mainCriterionId: meta.mainCriterionId,
      subName: { ...meta.name },
      mainName: { ...MAIN_MIRROR[meta.mainCriterionId] },
      weight,
      before,
      after,
      diff,
      gapBefore: weight - before,
      gapAfter,
      outcome: outcomeFor(diff, gapAfter),
      inKitScope: STAGE_1_OPEN_GAP_IDS.includes(`gap-${id}`),
    }
  })
  const totalWeight = criteria.reduce((s, c) => s + c.weight, 0)
  const totalBefore = criteria.reduce((s, c) => s + c.before, 0)
  const totalAfter = criteria.reduce((s, c) => s + c.after, 0)
  const beforePercent = percentOf(totalBefore, totalWeight)
  const afterPercent = percentOf(totalAfter, totalWeight)
  return {
    available: true,
    reference: seedReference(),
    reassessment: {
      assessmentId: 9105,
      version: 2,
      kind: 'accredited',
      completedAt: '2026-12-20T11:00:00+03:00',
    },
    overall: {
      totalWeight,
      before: totalBefore,
      after: totalAfter,
      beforePercent,
      afterPercent,
      diff: totalAfter - totalBefore,
      diffPercent: Number((afterPercent - beforePercent).toFixed(2)),
      closed: criteria.filter((c) => c.outcome === 'closed').length,
      improved: criteria.filter((c) => c.outcome === 'partially-improved').length,
      stable: criteria.filter((c) => c.outcome === 'stable').length,
      regressed: criteria.filter((c) => c.outcome === 'regressed').length,
    },
    criteria,
  }
}
