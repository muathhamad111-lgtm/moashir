export const ADVANCED_STATUSES = {
  REJECTED_CONDITIONS: 'rejected-conditions',
  DRAFT: 'draft',
  EXPIRED: 'expired',
  AWAITING_ASSIGNMENT: 'awaiting-review-assignment',
  NEEDS_COMPLETION: 'needs-completion',
  DECLINED: 'declined',
  WITHDRAWN: 'withdrawn',
  ASSIGNED: 'assigned',
  UNDER_INITIAL_ASSESSMENT: 'under-initial-assessment',
  AWAITING_FILTERING: 'awaiting-filtering',
  NOT_QUALIFIED: 'not-qualified',
  QUALIFIED: 'qualified-for-committee',
  UNDER_REVIEW: 'under-review',
}

export const ADVANCED_QUEUE_STATUSES = [
  ADVANCED_STATUSES.AWAITING_ASSIGNMENT,
  ADVANCED_STATUSES.NEEDS_COMPLETION,
  ADVANCED_STATUSES.ASSIGNED,
  ADVANCED_STATUSES.UNDER_INITIAL_ASSESSMENT,
  ADVANCED_STATUSES.AWAITING_FILTERING,
  ADVANCED_STATUSES.QUALIFIED,
  ADVANCED_STATUSES.NOT_QUALIFIED,
  ADVANCED_STATUSES.WITHDRAWN,
  ADVANCED_STATUSES.DECLINED,
]

export const ADVANCED_WITHDRAWABLE_STATUSES = [
  ADVANCED_STATUSES.AWAITING_ASSIGNMENT,
  ADVANCED_STATUSES.NEEDS_COMPLETION,
  ADVANCED_STATUSES.ASSIGNED,
  ADVANCED_STATUSES.UNDER_INITIAL_ASSESSMENT,
  ADVANCED_STATUSES.AWAITING_FILTERING,
  ADVANCED_STATUSES.QUALIFIED,
]

export const ADVANCED_CLOSED_STATUSES = [
  ADVANCED_STATUSES.REJECTED_CONDITIONS,
  ADVANCED_STATUSES.EXPIRED,
  ADVANCED_STATUSES.DECLINED,
  ADVANCED_STATUSES.WITHDRAWN,
  ADVANCED_STATUSES.NOT_QUALIFIED,
]

export const FILTERING_STATUSES = [ADVANCED_STATUSES.AWAITING_FILTERING, ADVANCED_STATUSES.QUALIFIED, ADVANCED_STATUSES.NOT_QUALIFIED]

export const CYCLE_STATUSES = {
  DRAFT: 'draft',
  SUBMISSION_OPEN: 'submission-open',
  SUBMISSION_CLOSED: 'submission-closed',
  COMMITTEE_ASSESSMENT: 'committee-assessment',
  AWAITING_SECRETARY: 'awaiting-secretary',
  APPROVED: 'approved',
  ANNOUNCED: 'announced',
  CLOSED: 'closed',
}

export const CYCLE_STATUS_LIST = Object.values(CYCLE_STATUSES)

export const CYCLE_CLOSABLE_STATUSES = [
  CYCLE_STATUSES.SUBMISSION_CLOSED,
  CYCLE_STATUSES.COMMITTEE_ASSESSMENT,
  CYCLE_STATUSES.AWAITING_SECRETARY,
  CYCLE_STATUSES.APPROVED,
  CYCLE_STATUSES.ANNOUNCED,
]

export const CYCLE_FILTERING_STATUSES = [CYCLE_STATUSES.SUBMISSION_CLOSED, CYCLE_STATUSES.COMMITTEE_ASSESSMENT]

export const CYCLE_FIELD_GROUPS = {
  DRAFT_ONLY: ['submissionStart', 'criteriaVersionId', 'scaleKind'],
  UNTIL_SUBMISSIONS_CLOSE: ['submissionEnd', 'regionIds', 'minEntityAgeYears', 'disqualificationScore'],
  UNTIL_COMMITTEE: ['committeeStart', 'committeeEnd', 'initialReviewSlaDays'],
}

export const CYCLE_STAGE_ACCEPTS = {
  DRAFT_ONLY: [CYCLE_STATUSES.DRAFT],
  UNTIL_SUBMISSIONS_CLOSE: [CYCLE_STATUSES.DRAFT, CYCLE_STATUSES.SUBMISSION_OPEN],
  UNTIL_COMMITTEE: [CYCLE_STATUSES.DRAFT, CYCLE_STATUSES.SUBMISSION_OPEN, CYCLE_STATUSES.SUBMISSION_CLOSED],
}

export const MEMBER_ROLES = { FILTERING_OFFICER: 'filtering_officer', AWARD_SECRETARY: 'award_secretary' }

export const CANDIDATE_ROLES = { INITIAL_ASSESSOR: 'initial_assessor', ...MEMBER_ROLES }

export const FILTERING_OUTCOMES = { QUALIFIED: 'qualified', NOT_QUALIFIED: 'not_qualified' }

export const ELIGIBILITY_RULES = { MIN_ENTITY_AGE: 'min_entity_age', REGION: 'region' }

export const CYCLE_SORTS = ['-submission_start', 'submission_start', '-announcement_at', 'announcement_at', '-created_at', 'created_at']

export const DEFAULT_CYCLE_SORT = '-submission_start'

export const FILTERING_SORTS = ['-initial_percent', 'initial_percent', '-submitted_at', 'submitted_at']

export const DEFAULT_FILTERING_SORT = '-initial_percent'

export const AWARD_LIMITS = {
  NAME_MAX: 200,
  YEAR_MIN: 2000,
  YEAR_MAX: 2200,
  REGIONS_MIN: 1,
  REGIONS_MAX: 13,
  AGE_MIN: 0,
  AGE_MAX: 100,
  SCORE_MIN: 0,
  SCORE_MAX: 100,
  SLA_MIN: 1,
  SLA_MAX: 120,
  REASON_MIN: 10,
  REOPEN_REASON_MIN: 20,
  OVERRIDE_REASON_MIN: 20,
  REASON_MAX: 1000,
  NOTE_MAX: 1000,
  CANDIDATES_MAX: 100,
}

export const AWARD_CODES = {
  ANOTHER_OPEN: 'cycle.another_open',
  DATES_UNORDERED: 'cycle.dates_unordered',
  FIELD_LOCKED: 'cycle.field_locked',
  REGION_HAS_REQUESTS: 'cycle.region_has_requests',
  VERSION_NOT_PUBLISHED: 'cycle.criteria_version_not_published',
  PARTICIPATION_RESERVED: 'cycle.participation_setting_reserved',
  WINDOW_PASSED: 'cycle.submission_window_passed',
  REQUESTS_PENDING: 'cycle.requests_pending',
  SUBMISSION_CLOSED: 'cycle.submission_closed',
  CONDITIONS_FAILED: 'cycle.conditions_failed',
  MEMBER_INVALID: 'cycle.member_invalid',
  INITIAL_ASSESSOR_INVALID: 'cycle.initial_assessor_invalid',
  FILTERING_NOT_OPEN: 'cycle.filtering_not_open',
  REQUEST_EXISTS: 'advanced.request_exists',
  OVERRIDE_REASON_REQUIRED: 'filtering.override_reason_required',
  DEADLINE_AFTER_COMMITTEE: 'completion.deadline_after_committee_start',
  NOT_IN_CYCLE: 'assessor.not_in_cycle',
  SOD_CONFLICT: 'sod_conflict',
}

export const ADVANCED_REQUEST_PREFIX = 'ADV'

export const DEFAULT_DISQUALIFICATION_SCORE = '60.00'
