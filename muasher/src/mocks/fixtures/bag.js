import { FIXTURE_ENTITY_ID } from './assessments'
import { computePricing } from './bagProduct'

export { FIXTURE_ENTITY_ID }

export const BAG_SOURCE_ASSESSMENT_ID = 9102
export const BAG_SUBSCRIPTION_ID = 'bag-5001'

export function seedReference() {
  return {
    assessmentId: BAG_SOURCE_ASSESSMENT_ID,
    version: 1,
    kind: 'accredited',
    completedAt: '2026-06-22T11:00:00+03:00',
    frozenAt: '2026-06-23T09:00:00+03:00',
    scorePercent: 78,
  }
}

const SUB_MIRROR = {
  101: {
    weight: 400,
    mainCriterionId: 1,
    name: { ar: 'التوجه الاستراتيجي والرؤية', en: 'Strategic Direction & Vision' },
  },
  102: {
    weight: 350,
    mainCriterionId: 1,
    name: { ar: 'الحوكمة والامتثال', en: 'Governance & Compliance' },
  },
  103: {
    weight: 250,
    mainCriterionId: 1,
    name: { ar: 'ثقافة التميز والقيادة بالقدوة', en: 'Excellence Culture & Leading by Example' },
  },
  201: {
    weight: 500,
    mainCriterionId: 2,
    name: {
      ar: 'تحليل البيئة واحتياجات المستفيدين',
      en: 'Environment & Beneficiary Needs Analysis',
    },
  },
  202: {
    weight: 400,
    mainCriterionId: 2,
    name: { ar: 'صياغة الخطة الاستراتيجية ونشرها', en: 'Strategy Formulation & Deployment' },
  },
  301: {
    weight: 400,
    mainCriterionId: 3,
    name: { ar: 'استقطاب الكفاءات وتنميتها', en: 'Talent Attraction & Development' },
  },
  302: {
    weight: 400,
    mainCriterionId: 3,
    name: { ar: 'التمكين والرعاية وبيئة العمل', en: 'Empowerment, Wellbeing & Workplace' },
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
      en: 'Financial Sustainability & Resource Management',
    },
  },
  501: {
    weight: 500,
    mainCriterionId: 5,
    name: { ar: 'تصميم البرامج والخدمات', en: 'Program & Service Design' },
  },
  502: {
    weight: 350,
    mainCriterionId: 5,
    name: { ar: 'إدارة العمليات وتحسينها', en: 'Process Management & Improvement' },
  },
  503: {
    weight: 250,
    mainCriterionId: 5,
    name: {
      ar: 'التحول الرقمي وإدارة المعرفة',
      en: 'Digital Transformation & Knowledge Management',
    },
  },
  601: {
    weight: 600,
    mainCriterionId: 6,
    name: { ar: 'رضا المستفيدين وولاؤهم', en: 'Beneficiary Satisfaction & Loyalty' },
  },
  602: {
    weight: 500,
    mainCriterionId: 6,
    name: { ar: 'أثر البرامج على المستفيدين', en: 'Program Impact on Beneficiaries' },
  },
  701: {
    weight: 300,
    mainCriterionId: 7,
    name: { ar: 'رضا الموظفين والمتطوعين', en: 'Employee & Volunteer Satisfaction' },
  },
  702: {
    weight: 300,
    mainCriterionId: 7,
    name: { ar: 'نتائج التطوير المهني', en: 'Professional Development Results' },
  },
  801: {
    weight: 600,
    mainCriterionId: 8,
    name: {
      ar: 'الأثر المجتمعي والإسهام في رؤية 2030',
      en: 'Community Impact & Vision 2030 Contribution',
    },
  },
}

export const MAIN_MIRROR = {
  1: { ar: 'القيادة الإدارية', en: 'Administrative Leadership' },
  2: { ar: 'الاستراتيجية والتخطيط', en: 'Strategy & Planning' },
  3: { ar: 'الموارد البشرية والمتطوعون', en: 'Human Resources & Volunteers' },
  4: { ar: 'الشراكات والموارد', en: 'Partnerships & Resources' },
  5: { ar: 'العمليات والبرامج والخدمات', en: 'Processes, Programs & Services' },
  6: { ar: 'نتائج المستفيدين', en: 'Beneficiary Results' },
  7: { ar: 'نتائج الموارد البشرية', en: 'People Results' },
  8: { ar: 'نتائج المجتمع', en: 'Community Results' },
}

const SUB_RATIO_MIRROR = {
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

const UNMET_POINTS = {
  503: [
    {
      id: 'gp-503-1',
      ar: 'خطة تحول رقمي معتمدة مرتبطة بأهداف الجهة التشغيلية.',
      en: 'An approved digital transformation plan linked to operational goals.',
    },
    {
      id: 'gp-503-2',
      ar: 'رقمنة الخدمات الأساسية المقدمة للمستفيدين.',
      en: 'Digitizing the core services provided to beneficiaries.',
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
  'gap-503': {
    priority: 'high',
    status: 'approved',
    consultantNote: {
      ar: 'التحول الرقمي أكبر فجوة مؤثرة على بقية المعايير التشغيلية، فابدأ به أولًا.',
      en: 'Digital transformation is the highest-leverage gap affecting the other operational criteria — start here.',
    },
    recommendedAction: {
      ar: 'اعتماد خطة تحول رقمي مرتبطة بأهداف الجهة ورقمنة خدمتين أساسيتين خلال المرحلة.',
      en: 'Approve a digital-transformation plan tied to the entity goals and digitize two core services this stage.',
    },
  },
  'gap-502': {
    priority: 'medium',
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
  'gap-402': {
    priority: 'high',
    status: 'approved',
    consultantNote: {
      ar: 'الاستدامة المالية تعتمد على تنويع مصادر الدخل خارج التمويل التقليدي.',
      en: 'Financial sustainability depends on diversifying income beyond traditional funding.',
    },
    recommendedAction: {
      ar: 'تفعيل خطة معتمدة لتنويع مصادر الدخل بثلاث قنوات جديدة على الأقل.',
      en: 'Activate an approved income-diversification plan with at least three new channels.',
    },
  },
}

function buildGaps() {
  return Object.entries(SUB_MIRROR).map(([subId, meta]) => {
    const id = Number(subId)
    const target = meta.weight
    const ratio = SUB_RATIO_MIRROR[id] ?? 0
    const current = Math.round(ratio * target)
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
      ar: 'معالجة الفجوات الأكثر تأثيرًا في العمليات والتحول الرقمي والاستدامة المالية.',
      en: 'Address the highest-impact gaps in operations, digital transformation, and financial sustainability.',
    },
    startedAt: '2026-06-25T09:00:00+03:00',
    endsAt: '2026-10-25T09:00:00+03:00',
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
  'gap-503': [
    {
      id: 'hf-503-1',
      gapId: 'gap-503',
      subCriterionId: 503,
      guidingPointId: 'gp-503-1',
      kind: 'template',
      name: { ar: 'نموذج خطة التحول الرقمي', en: 'Digital transformation plan template' },
      fileName: 'digital-transformation-plan-template.docx',
      sizeKb: 320,
      url: '/assets/bag/helpers/digital-transformation-plan-template.docx',
      attachedAt: '2026-06-26T10:00:00+03:00',
    },
    {
      id: 'hf-503-2',
      gapId: 'gap-503',
      subCriterionId: 503,
      guidingPointId: 'gp-503-2',
      kind: 'guide',
      name: { ar: 'دليل رقمنة خدمات المستفيدين', en: 'Guide to digitizing beneficiary services' },
      fileName: 'digitizing-services-guide.pdf',
      sizeKb: 880,
      url: '/assets/bag/helpers/digitizing-services-guide.pdf',
      attachedAt: '2026-06-26T10:05:00+03:00',
    },
  ],
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
      attachedAt: '2026-06-26T10:10:00+03:00',
    },
  ],
  'gap-402': [
    {
      id: 'hf-402-1',
      gapId: 'gap-402',
      subCriterionId: 402,
      guidingPointId: 'gp-402-1',
      kind: 'recommendation',
      name: { ar: 'توصيات تنويع مصادر الدخل', en: 'Income diversification recommendations' },
      fileName: 'income-diversification-recommendations.pdf',
      sizeKb: 410,
      url: '/assets/bag/helpers/income-diversification-recommendations.pdf',
      attachedAt: '2026-06-26T10:15:00+03:00',
    },
  ],
}

export const STAGE_1_OPEN_GAP_IDS = ['gap-503', 'gap-502', 'gap-402']

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
    activatedAt: '2026-06-24T09:00:00+03:00',
    createdAt: '2026-06-23T09:00:00+03:00',
  }
}

export function seedEligibility() {
  const pricing = computePricing()
  return {
    eligible: true,
    source: 'accredited',
    sourceAssessmentId: BAG_SOURCE_ASSESSMENT_ID,
    sourceCompletedAt: '2026-06-22T11:00:00+03:00',
    sourceScorePercent: 78,
    referenceVersion: 1,
    pricing,
    fee: pricing.total,
    currency: pricing.currency,
  }
}

export function seedBagHistory() {
  return [
    {
      id: 'bag-6600',
      title: { ar: 'حقيبة التميز السريع — دورة 2025', en: 'Quick Excellence Kit — 2025 cycle' },
      status: 'reassessed',
      referenceAssessmentId: 9105,
      referenceScorePercent: 71,
      reassessedScorePercent: 79,
      improvementPoints: 8,
      paidAt: '2026-02-16T09:00:00+03:00',
      completedAt: '2026-05-20T09:00:00+03:00',
    },
  ]
}

export function seedAssessmentContext() {
  return {
    reference: {
      kind: 'accredited',
      assessmentId: BAG_SOURCE_ASSESSMENT_ID,
      version: 1,
      scorePercent: 78,
      completedAt: '2026-06-22T11:00:00+03:00',
    },
    context: [
      { kind: 'self', scorePercent: 82, completedAt: '2026-05-10T11:00:00+03:00' },
      { kind: 'advanced', scorePercent: 74, completedAt: '2026-04-01T11:00:00+03:00' },
    ],
  }
}

const SUB_RATIO_AFTER = {
  ...SUB_RATIO_MIRROR,
  503: 1.0,
  502: 0.86,
  402: 1.0,
  302: 0.66,
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
    const before = Math.round((SUB_RATIO_MIRROR[id] ?? 0) * weight)
    const after = Math.round((SUB_RATIO_AFTER[id] ?? SUB_RATIO_MIRROR[id] ?? 0) * weight)
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
  const beforePercent = Math.round((totalBefore / totalWeight) * 100)
  const afterPercent = Math.round((totalAfter / totalWeight) * 100)
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
      diffPercent: afterPercent - beforePercent,
      closed: criteria.filter((c) => c.outcome === 'closed').length,
      improved: criteria.filter((c) => c.outcome === 'partially-improved').length,
      stable: criteria.filter((c) => c.outcome === 'stable').length,
      regressed: criteria.filter((c) => c.outcome === 'regressed').length,
    },
    criteria,
  }
}
