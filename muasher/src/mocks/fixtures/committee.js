export const COMMITTEE_CYCLE_ID = 'cycle-committee-2026'

export const COMMITTEE_DISQUALIFICATION_SCORE = 60

export const QUALIFIED_ASSESSMENT_A = 9401
export const QUALIFIED_ASSESSMENT_B = 9402
export const BELOW_THRESHOLD_ASSESSMENT = 9403

export const COMMITTEE_ASSESSOR_COMPLETED_A = 'jur-9401'
export const COMMITTEE_ASSESSOR_COMPLETED_B = 'jur-9402'
export const COMMITTEE_ASSESSOR_IN_PROGRESS = 'jur-9403'
export const COMMITTEE_ASSESSOR_NOT_STARTED = 'jur-9404'

export const committeeCycleFixture = {
  id: COMMITTEE_CYCLE_ID,
  name: {
    ar: 'جائزة التميز المؤسسي — دورة تقييم اللجنة 2026',
    en: 'Institutional Excellence Award — 2026 Committee Assessment Cycle',
  },
  status: 'under-committee-assessment',
  submissionStart: '2025-06-01T00:00:00+03:00',
  submissionEnd: '2025-08-31T23:59:59+03:00',
  committeeStart: '2025-09-15T00:00:00+03:00',
  committeeEnd: '2026-11-30T23:59:59+03:00',
  announcementDate: '2026-12-15T00:00:00+03:00',
  disqualificationScore: COMMITTEE_DISQUALIFICATION_SCORE,
  conditions: { minEntityAge: 3, qualificationScore: COMMITTEE_DISQUALIFICATION_SCORE },
}

const entityNames = {
  [QUALIFIED_ASSESSMENT_A]: { ar: 'جمعية البر بالرياض', en: 'Al-Birr Charity — Riyadh' },
  [QUALIFIED_ASSESSMENT_B]: { ar: 'مؤسسة نماء التنموية', en: 'Namaa Development Foundation' },
  [BELOW_THRESHOLD_ASSESSMENT]: { ar: 'جمعية عون الخيرية', en: 'Aoun Charitable Society' },
}

export const filteringResultsFixture = [
  {
    assessmentId: QUALIFIED_ASSESSMENT_A,
    entityId: 5001,
    entityName: { ...entityNames[QUALIFIED_ASSESSMENT_A] },
    consultantScore: 88,
    filteringStatus: 'qualified',
    overridden: false,
    initialAssessedAt: '2026-09-20T10:00:00+03:00',
    decidedAt: '2026-09-22T09:00:00+03:00',
  },
  {
    assessmentId: QUALIFIED_ASSESSMENT_B,
    entityId: 5003,
    entityName: { ...entityNames[QUALIFIED_ASSESSMENT_B] },
    consultantScore: 74,
    filteringStatus: 'qualified',
    overridden: false,
    initialAssessedAt: '2026-09-20T11:30:00+03:00',
    decidedAt: '2026-09-22T09:10:00+03:00',
  },
  {
    assessmentId: BELOW_THRESHOLD_ASSESSMENT,
    entityId: 5004,
    entityName: { ...entityNames[BELOW_THRESHOLD_ASSESSMENT] },
    consultantScore: 52,
    filteringStatus: 'pending',
    overridden: false,
    initialAssessedAt: '2026-09-20T13:00:00+03:00',
    decidedAt: null,
  },
]

export const committeeAssessorsFixture = [
  { id: COMMITTEE_ASSESSOR_COMPLETED_A, name: { ar: 'د. سارة الحربي', en: 'Dr. Sara Al-Harbi' }, status: 'completed' },
  { id: COMMITTEE_ASSESSOR_COMPLETED_B, name: { ar: 'أ. خالد العتيبي', en: 'Mr. Khalid Al-Otaibi' }, status: 'completed' },
  { id: COMMITTEE_ASSESSOR_IN_PROGRESS, name: { ar: 'د. منى الشمري', en: 'Dr. Muna Al-Shammari' }, status: 'in-progress' },
  { id: COMMITTEE_ASSESSOR_NOT_STARTED, name: { ar: 'أ. فهد القحطاني', en: 'Mr. Fahd Al-Qahtani' }, status: 'not-started' },
]

export const committeeScoresFixture = [
  {
    cycleId: COMMITTEE_CYCLE_ID,
    committeeAssessorId: COMMITTEE_ASSESSOR_COMPLETED_A,
    assessmentId: QUALIFIED_ASSESSMENT_A,
    status: 'completed',
    total: 90,
    scores: { subs: { 101: 0.9, 102: 0.85 }, points: {} },
    notes: [
      { subCriterionId: 101, text: 'توجه استراتيجي واضح ومترابط مع مؤشرات أداء دقيقة.' },
      { subCriterionId: 102, text: 'حوكمة قوية مع إفصاح دوري مقبول.' },
    ],
    completedAt: '2026-10-05T10:00:00+03:00',
  },
  {
    cycleId: COMMITTEE_CYCLE_ID,
    committeeAssessorId: COMMITTEE_ASSESSOR_COMPLETED_B,
    assessmentId: QUALIFIED_ASSESSMENT_A,
    status: 'completed',
    total: 86,
    scores: { subs: { 101: 0.86, 102: 0.82 }, points: {} },
    notes: [{ subCriterionId: 101, text: 'أداء متميز مع فرص لتوثيق أعمق للمبادرات.' }],
    completedAt: '2026-10-05T12:00:00+03:00',
  },
  {
    cycleId: COMMITTEE_CYCLE_ID,
    committeeAssessorId: COMMITTEE_ASSESSOR_IN_PROGRESS,
    assessmentId: QUALIFIED_ASSESSMENT_A,
    status: 'completed',
    total: 88,
    scores: { subs: { 101: 0.88, 102: 0.84 }, points: {} },
    notes: [],
    completedAt: '2026-10-06T09:00:00+03:00',
  },
  {
    cycleId: COMMITTEE_CYCLE_ID,
    committeeAssessorId: COMMITTEE_ASSESSOR_NOT_STARTED,
    assessmentId: QUALIFIED_ASSESSMENT_A,
    status: 'completed',
    total: 84,
    scores: { subs: { 101: 0.84, 102: 0.8 }, points: {} },
    notes: [],
    completedAt: '2026-10-06T11:00:00+03:00',
  },
  {
    cycleId: COMMITTEE_CYCLE_ID,
    committeeAssessorId: COMMITTEE_ASSESSOR_COMPLETED_A,
    assessmentId: QUALIFIED_ASSESSMENT_B,
    status: 'completed',
    total: 79,
    scores: { subs: { 101: 0.79, 102: 0.75 }, points: {} },
    notes: [{ subCriterionId: 101, text: 'استراتيجية جيدة تحتاج لتحديث الدراسة سنويًا.' }],
    completedAt: '2026-10-07T10:00:00+03:00',
  },
  {
    cycleId: COMMITTEE_CYCLE_ID,
    committeeAssessorId: COMMITTEE_ASSESSOR_COMPLETED_B,
    assessmentId: QUALIFIED_ASSESSMENT_B,
    status: 'completed',
    total: 81,
    scores: { subs: { 101: 0.81, 102: 0.78 }, points: {} },
    notes: [],
    completedAt: '2026-10-07T12:30:00+03:00',
  },
  {
    cycleId: COMMITTEE_CYCLE_ID,
    committeeAssessorId: COMMITTEE_ASSESSOR_IN_PROGRESS,
    assessmentId: QUALIFIED_ASSESSMENT_B,
    status: 'in-progress',
    total: null,
    scores: { subs: { 101: 0.7 }, points: {} },
    notes: [{ subCriterionId: 101, text: 'ما زلت أراجع الأدلة قبل تقدير المعيار الثاني.' }],
    completedAt: null,
  },
  {
    cycleId: COMMITTEE_CYCLE_ID,
    committeeAssessorId: COMMITTEE_ASSESSOR_NOT_STARTED,
    assessmentId: QUALIFIED_ASSESSMENT_B,
    status: 'not-started',
    total: null,
    scores: { subs: {}, points: {} },
    notes: [],
    completedAt: null,
  },
]

export const assignableCommitteeAssessorsFixture = [
  { id: 'jur-9501', name: { ar: 'د. ريم الدوسري', en: 'Dr. Reem Al-Dosari' } },
  { id: 'jur-9502', name: { ar: 'أ. ماجد الغامدي', en: 'Mr. Majed Al-Ghamdi' } },
  { id: 'jur-9503', name: { ar: 'د. هند العنزي', en: 'Dr. Hind Al-Anazi' } },
  { id: 'jur-9504', name: { ar: 'أ. سلطان المطيري', en: 'Mr. Sultan Al-Mutairi' } },
]

export const committeeAveragesFixture = [
  {
    cycleId: COMMITTEE_CYCLE_ID,
    assessmentId: QUALIFIED_ASSESSMENT_A,
    entityId: 5001,
    entityName: { ...entityNames[QUALIFIED_ASSESSMENT_A] },
    average: 87,
    committeeAssessorCount: 4,
    completedCount: 4,
    secretaryStatus: 'pending',
    computedAt: '2026-10-06T12:00:00+03:00',
    approvedAt: null,
    returnedAt: null,
    generalNote: null,
  },
]
