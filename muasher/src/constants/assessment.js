export const ASSESSMENT_PATHS = {
  SELF: 'self',
  ACCREDITED: 'accredited',
  ADVANCED: 'advanced',
}

export const SELF_STATUSES = {
  DRAFT: 'draft',
  SUBMITTED: 'submitted',
  RESULT_AVAILABLE: 'result-available',
}

export const SCALE_KINDS = {
  OF_10: 'of-10',
  RELATIVE: 'relative',
}

export const SCALE_MAX = {
  [SCALE_KINDS.OF_10]: 10,
  [SCALE_KINDS.RELATIVE]: 100,
}

export const SCALE_STEP = {
  [SCALE_KINDS.OF_10]: 1,
  [SCALE_KINDS.RELATIVE]: 5,
}

export const SCORE_LEVELS = {
  SUB: 'sub',
  POINT: 'point',
}

export const SELF_ASSESSMENT_CODES = {
  DRAFT_EXISTS: 'self.draft_exists',
  NO_PUBLISHED_CRITERIA: 'criteria.no_published_version',
  ENTITY_REQUIRED: 'assessment.entity_required',
  ENTITY_NOT_ACTIVE: 'assessment.entity_not_active',
  ENTITY_DELETED: 'entity.deleted',
  TRANSITION_ILLEGAL: 'state.transition_illegal',
  CRITERIA_UPDATE_IN_PROGRESS: 'assessment.criteria_update_in_progress',
}

export const SELF_REQUEST_PREFIX = 'SLF'

export const ASSESSMENT_NOTICE_ICONS = {
  info: 'info-circle',
  warning: 'warning-triangle',
  danger: 'warning-triangle',
  frozen: 'snowflake',
}

export const ASSESSMENT_TABS = Object.freeze({
  CURRENT: 'current',
  HISTORY: 'history',
  RESULTS: 'results',
})
