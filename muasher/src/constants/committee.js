export const COMMITTEE_REQUEST_STATUSES = {
  UNDER_COMMITTEE: 'under-committee-assessment',
  DISCUSSION: 'committee-discussion',
  AWAITING_SECRETARY: 'awaiting-secretary-approval',
  RETURNED: 'returned-to-committee',
}

export const RESULT_STATUSES = {
  APPROVED: 'approved',
  REGIONAL_WINNER: 'regional-winner',
  NON_WINNER: 'non-winner',
  QUALIFIED_FOR_NATIONAL: 'qualified-for-national',
  NATIONAL_WINNER: 'national-winner',
  NATIONAL_NON_WINNER: 'national-non-winner',
}

export const SECRETARY_STATUSES = [
  ...Object.values(COMMITTEE_REQUEST_STATUSES),
  ...Object.values(RESULT_STATUSES),
]

export const SEAT_STATUSES = {
  ACTIVE: 'active',
  DISABLED: 'disabled',
  EXCLUDED: 'excluded',
  SUBSTITUTED: 'substituted',
  REMOVED: 'removed',
}

export const ASSIGNMENT_STATUSES = {
  NOT_STARTED: 'not-started',
  IN_PROGRESS: 'in-progress',
  COMPLETED: 'completed',
  VOIDED: 'voided',
}

export const ASSIGNMENT_FILTERS = [
  ASSIGNMENT_STATUSES.NOT_STARTED,
  ASSIGNMENT_STATUSES.IN_PROGRESS,
  ASSIGNMENT_STATUSES.COMPLETED,
]

export const ENTITY_VISIBILITY = { NAME_ONLY: 'name_only', CODE_ONLY: 'code_only', FULL: 'full' }

export const ENTITY_VISIBILITY_LIST = Object.values(ENTITY_VISIBILITY)

export const DISCUSSION_MODES = { SESSION: 'session', AGREED_OUTCOME: 'agreed_outcome' }

export const DISCUSSION_STATUSES = { OPEN: 'open', CLOSED: 'closed', CANCELLED: 'cancelled' }

export const ENTRY_KINDS = { OPINION: 'opinion', APPROVE: 'approve', REQUEST_EDIT: 'request_edit' }

export const DECISION_KINDS = {
  APPROVE_AVERAGE: 'approve_average',
  RETURN: 'return_to_committee',
  APPROVE_SUBSTITUTE: 'approve_substitute',
  APPROVE_REGION: 'approve_region',
  UNAPPROVE_REGION: 'unapprove_region',
  APPROVE_NATIONAL: 'approve_national',
  ANNOUNCE: 'announce',
}

export const AWARD_RANKS = [1, 2, 3]

export const RANK_KEYS = { 1: 'first', 2: 'second', 3: 'third' }

export const RANK_NONE = 'none'

export const SECRETARY_SORTS = ['-submitted_at', 'submitted_at', '-average', 'average']

export const DEFAULT_SECRETARY_SORT = '-submitted_at'

export const DRAFT_CYCLE_CHOICES = { KEEP: 'keep', MOVE: 'move' }

export const COMMITTEE_LIMITS = {
  REASON_MIN: 10,
  REASON_MAX: 1000,
  UNAPPROVE_REASON_MIN: 20,
  RETURN_NOTE_MIN: 10,
  RETURN_NOTE_MAX: 2000,
  DISCUSSION_TEXT_MIN: 10,
  DISCUSSION_TEXT_MAX: 5000,
  SUMMARY_MAX: 5000,
  NOTE_MAX: 5000,
  SECRETARY_NOTE_MAX: 1000,
  PER_PAGE: 10,
}

export const COMMITTEE_CODES = {
  CANDIDATE_INVALID: 'committee.candidate_invalid',
  MEMBER_EXISTS: 'committee.member_exists',
  NONE_ACTIVE: 'committee.none_active',
  HAS_SCORES: 'committee_assessor.has_scores',
  EVALUATION_LOCKED: 'committee.evaluation_locked',
  MEMBERSHIP_INACTIVE: 'committee.membership_inactive',
  NOT_STARTED: 'cycle.committee_not_started',
  INCOMPLETE: 'committee.incomplete',
  SUBSTITUTION_PENDING: 'committee.substitution_pending',
  SUBSTITUTE_NOT_COMPLETED: 'committee.substitute_not_completed',
  SCORES_INCOMPLETE: 'scores.incomplete',
  DISCUSSION_CLOSED: 'discussion.closed',
  DEADLINE_PASSED: 'discussion.deadline_passed',
  RANK_TAKEN: 'rank.taken',
  REGION_LOCKED: 'award.region_locked',
  REGION_APPROVED: 'award.region_approved',
  REGION_NOT_APPROVED: 'award.region_not_approved',
  NATIONAL_APPROVED: 'award.national_approved',
  REGION_PENDING: 'region.requests_pending',
  REGIONS_PENDING: 'regions.pending',
  WINNERS_NONE: 'winners.none',
  SOD_CONFLICT: 'sod_conflict',
  ENTITY_DELETED: 'entity.deleted',
  TRANSITION_ILLEGAL: 'state.transition_illegal',
  REASON_REQUIRED: 'reason_required',
}

export const COMMITTEE_CYCLE_STATUSES = ['committee-assessment', 'awaiting-secretary']

export const ROSTER_OPEN_CYCLE_STATUSES = [
  'draft',
  'submission-open',
  'submission-closed',
  'committee-assessment',
  'awaiting-secretary',
]
