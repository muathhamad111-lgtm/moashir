export const COMMITTEE_SCENARIOS = {
  QUALIFIED: 'qualified',
  COMMITTEE: 'committee',
  RESULTS: 'results',
  ANNOUNCED: 'announced',
}

export const COMMITTEE_SEAT_USERS = [27, 29, 30]

export const COMMITTEE_SUBSTITUTE_USER = 31

const STRONG = ['8.50', '9.00', '8.00', '9.50', '8.50']
const SOUND = ['7.50', '8.00', '7.00', '8.50']
const FAIR = ['7.00', '6.50', '7.50', '6.00']

const MIXED = [
  { status: 'completed', values: SOUND },
  { status: 'completed', values: FAIR },
  { status: 'completed', values: STRONG.slice(1) },
]

const ALL_DONE = [
  { status: 'completed', values: STRONG },
  { status: 'completed', values: SOUND },
  { status: 'completed', values: FAIR },
]

const ACTIVE_OFFSETS = {
  submissionStart: -60,
  submissionEnd: -20,
  committeeStart: -10,
  committeeEnd: 20,
  announcementAt: 40,
}

const NOTE = 'وصف المنهجية واضح ومدعوم بالشواهد، ويحتاج قياس الأثر إلى مؤشرات كمية أدق.'
const GENERAL_NOTE = 'أداء مؤسسي متوازن مع فرص تحسين في التحول الرقمي وقياس رضا المستفيدين.'
const OPINION = 'أرى أن المتوسط يعكس مستوى الجهة بدقة، مع ملاحظة تميزها في الحوكمة والشفافية.'

export const SCENARIO_PLANS = {
  [COMMITTEE_SCENARIOS.QUALIFIED]: {
    cycle: {
      status: 'submission-closed',
      committeeStarted: false,
      offsets: { ...ACTIVE_OFFSETS, committeeStart: 5, committeeEnd: 35, announcementAt: 55 },
    },
    seated: false,
    records: {
      aboveThreshold: { status: 'qualified-for-committee' },
      belowThreshold: { status: 'qualified-for-committee' },
    },
  },
  [COMMITTEE_SCENARIOS.COMMITTEE]: {
    cycle: { status: 'committee-assessment', committeeStarted: true, offsets: ACTIVE_OFFSETS },
    seated: true,
    records: {
      aboveThreshold: {
        status: 'under-committee-assessment',
        rows: [
          { status: 'in-progress', values: STRONG, count: 4, note: NOTE },
          { status: 'not-started' },
          { status: 'completed', values: SOUND, generalNote: GENERAL_NOTE },
        ],
      },
      belowThreshold: { status: 'awaiting-secretary-approval', rows: ALL_DONE },
      underInitialAssessment: {
        status: 'committee-discussion',
        rows: MIXED,
        discussion: { mode: 'session', deadlineOffset: 5, opinion: OPINION },
      },
    },
  },
  [COMMITTEE_SCENARIOS.RESULTS]: {
    cycle: {
      status: 'awaiting-secretary',
      committeeStarted: true,
      offsets: { ...ACTIVE_OFFSETS, committeeEnd: -2 },
    },
    seated: true,
    records: {
      aboveThreshold: { status: 'approved', rows: ALL_DONE },
      belowThreshold: { status: 'qualified-for-national', rows: ALL_DONE, regionalRank: 1 },
      needsCompletion: { status: 'non-winner', rows: MIXED.slice().reverse() },
      underInitialAssessment: { status: 'qualified-for-national', rows: MIXED, regionalRank: 1 },
      awaitingAssignment: { outcome: 'not_qualified' },
    },
    approvedRegions: [2, 4],
  },
  [COMMITTEE_SCENARIOS.ANNOUNCED]: {
    cycle: {
      status: 'announced',
      committeeStarted: true,
      offsets: { ...ACTIVE_OFFSETS, committeeEnd: -6, announcementAt: -1 },
    },
    seated: true,
    records: {
      aboveThreshold: {
        status: 'national-winner',
        rows: ALL_DONE,
        regionalRank: 1,
        nationalRank: 1,
      },
      belowThreshold: {
        status: 'national-winner',
        rows: ALL_DONE,
        regionalRank: 1,
        nationalRank: 2,
      },
      needsCompletion: { status: 'non-winner', rows: MIXED.slice().reverse() },
      underInitialAssessment: { status: 'national-non-winner', rows: MIXED, regionalRank: 1 },
      awaitingAssignment: { outcome: 'not_qualified' },
    },
    approvedRegions: [1, 2, 3, 4],
    nationalApproved: true,
    announced: true,
  },
}
