import { CYCLE_FIELD_GROUPS, CYCLE_STATUSES } from '@/constants/advanced'
import { cycleSchema } from '@/utils/award/cycleForm'

export const SETUP_STEP_FIELDS = {
  identity: ['name', 'year', 'description'],
  dates: ['submissionStart', 'submissionEnd', 'committeeStart', 'committeeEnd', 'announcementAt'],
  conditions: [
    'regionIds',
    'minEntityAgeYears',
    'disqualificationScore',
    'participationCertificateEnabled',
  ],
  deadlines: ['initialReviewSlaDays'],
  fee: ['entryFeePaid', 'entryFee'],
}

export const SETUP_STEPS = [...Object.keys(SETUP_STEP_FIELDS), 'team', 'review']

export const CREATING_STEP = 'dates'

export const STEP_COUNTER = Symbol('cycleSetupStepCounter')

const LOCK_GROUP_REASONS = {
  DRAFT_ONLY: 'draftOnly',
  UNTIL_SUBMISSIONS_CLOSE: 'untilSubmissionsClose',
  UNTIL_COMMITTEE: 'untilCommittee',
}

export function isFormStep(step) {
  return Object.hasOwn(SETUP_STEP_FIELDS, step)
}

export function stepValues(values, step) {
  return Object.fromEntries(SETUP_STEP_FIELDS[step].map((field) => [field, values[field]]))
}

export function stepSchema(t, step) {
  return cycleSchema(t).pick(SETUP_STEP_FIELDS[step])
}

export function hasActiveInitialAssessor(cycle) {
  return (cycle?.initialAssessors ?? []).some((link) => link.isActive)
}

export function lacksInitialAssessor(cycle) {
  return (
    Boolean(cycle?.revealsStaff) &&
    cycle.status !== CYCLE_STATUSES.CLOSED &&
    !hasActiveInitialAssessor(cycle)
  )
}

export function attentionStep(cycle) {
  if (!cycle) return SETUP_STEPS[0]
  return lacksInitialAssessor(cycle) ? 'team' : 'review'
}

function groupReason(field) {
  const group = Object.keys(CYCLE_FIELD_GROUPS).find((key) =>
    CYCLE_FIELD_GROUPS[key].includes(field),
  )
  return group ? LOCK_GROUP_REASONS[group] : 'stage'
}

const OWN_HINT_FIELDS = ['entryFee', 'disqualificationScore']

function lockReason(field, cycle) {
  if (!cycle.canManage) return 'readOnly'
  if (cycle.status === CYCLE_STATUSES.CLOSED) return 'closed'
  return groupReason(field)
}

export function lockReasons(step, { cycle, locked }) {
  if (!cycle || !isFormStep(step)) return []
  const reasons = SETUP_STEP_FIELDS[step]
    .filter((field) => locked.has(field))
    .filter((field) => !cycle.canManage || !OWN_HINT_FIELDS.includes(field))
    .map((field) => lockReason(field, cycle))
  return [...new Set(reasons)]
}

export function descriptionParagraphs(text) {
  return (text ?? '')
    .split(/\n\s*\n/)
    .map((paragraph) => paragraph.trim())
    .filter(Boolean)
}
