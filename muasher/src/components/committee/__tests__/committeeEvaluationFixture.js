import { toEvaluation } from '@/services/mappers/committee'

export const POINTS_SUB = {
  id: 101,
  code: 'M1.1',
  name: { ar: 'التوجه الاستراتيجي', en: 'Strategic direction' },
  weight: '400.00',
  evaluationType: 'guiding-points',
  scoringType: 'score',
  hasCv: false,
  assessorNoteLabel: null,
  attachments: [],
  points: [
    {
      id: 1001,
      code: 'M1.1.1',
      description: { ar: 'رؤية منشورة', en: 'Published vision' },
      weight: '400.00',
      scoringType: 'score',
      attachments: [],
    },
  ],
}

export const SCORE_SUB = {
  id: 102,
  code: 'M1.2',
  name: { ar: 'الحوكمة', en: 'Governance' },
  weight: '350.00',
  evaluationType: 'direct',
  scoringType: 'score',
  hasCv: false,
  assessorNoteLabel: null,
  attachments: [],
  points: [],
}

export const ACHIEVEMENT_SUB = {
  ...SCORE_SUB,
  id: 103,
  code: 'M1.3',
  name: { ar: 'ثقافة التميز', en: 'Excellence culture' },
  weight: '250.00',
  scoringType: 'achievement',
}

export const RELATIVE_SUB = {
  ...SCORE_SUB,
  id: 104,
  code: 'M1.4',
  name: { ar: 'الأثر النسبي', en: 'Relative impact' },
  scoringType: 'relative',
}

const CRITERIA = {
  mains: [
    {
      id: 1,
      code: 'M1',
      name: { ar: 'القيادة', en: 'Leadership' },
      weight: '1000.00',
      attachments: [],
      subs: [POINTS_SUB, SCORE_SUB, ACHIEVEMENT_SUB],
    },
  ],
}

export const PRIOR_SCORES = {
  subs: {
    102: { rawValue: '7.50', percent: '75.00' },
    103: { rawValue: '1.00', percent: '100.00' },
  },
  points: { 1001: { rawValue: '8.00', percent: '80.00' } },
}

function priorOf(scores) {
  if (!scores) return null
  return {
    scores,
    progress: { scored: 3, required: 3 },
    percent: '84.38',
    status: 'voided',
    notes: { subs: {}, general: null },
    viewOnly: true,
  }
}

export function committeeEvaluationOf({ scale = null, prior = null, subs = null } = {}) {
  return toEvaluation({
    assignmentId: 7,
    status: 'in-progress',
    scale,
    scores: { subs: {}, points: {} },
    progress: { scored: 0, required: 3 },
    percent: null,
    assignment: {
      id: 7,
      assessmentId: 9204,
      cycle: {
        id: 31,
        name: { ar: 'دورة الجائزة', en: 'Award cycle' },
        status: 'committee-assessment',
      },
      entity: { visibility: 'code_only', code: 'C-1A2B3C4D', region: null },
      status: 'in-progress',
      membershipStatus: 'active',
      isSubstitute: prior !== null,
    },
    mode: 'edit',
    criteria: subs ? { mains: [{ ...CRITERIA.mains[0], subs }] } : CRITERIA,
    cv: {},
    evidence: [],
    notes: { subs: {}, general: null },
    returnNote: null,
    prior: priorOf(prior),
  })
}
