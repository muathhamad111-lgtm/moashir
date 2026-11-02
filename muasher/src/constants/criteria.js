export const EVALUATION_TYPES = {
  DIRECT: 'direct',
  GUIDING_POINTS: 'guiding-points',
}

export const CRITERIA_VERSION_STATUSES = {
  DRAFT: 'draft',
  PUBLISHED: 'published',
  ARCHIVED: 'archived',
}

export const CRITERIA_EDIT_SCOPES = {
  ALL: 'all',
  NEW_ONLY: 'new_only',
  RESET_OPEN: 'reset_open',
}

export const CRITERIA_EDIT_SCOPE_ORDER = [
  CRITERIA_EDIT_SCOPES.ALL,
  CRITERIA_EDIT_SCOPES.NEW_ONLY,
  CRITERIA_EDIT_SCOPES.RESET_OPEN,
]

export const CRITERION_GROUPS = {
  ENABLERS: 'enablers',
  CREATIVITY_LEARNING: 'creativity_learning',
  RESULTS: 'results',
}

export const CRITERION_GROUP_ORDER = [
  CRITERION_GROUPS.ENABLERS,
  CRITERION_GROUPS.CREATIVITY_LEARNING,
  CRITERION_GROUPS.RESULTS,
]

export const CRITERIA_NODE_TYPES = {
  MAIN: 'main',
  SUB: 'sub',
  POINT: 'point',
  ATTACHMENT: 'attachment',
}

export const CONSISTENCY_PROBLEMS = {
  NO_ACTIVE_MAIN: 'no_active_main',
  WEIGHTS_MISMATCH: 'weights_mismatch',
  NO_ACTIVE_POINT: 'no_active_point',
  POINTS_EXCEED_SUB: 'points_exceed_sub',
}

export const CRITERIA_ERROR_CODES = {
  WEIGHTS_INCONSISTENT: 'criteria.weights_inconsistent',
  SCOPE_REQUIRED: 'criteria.scope_required',
  REASON_REQUIRED: 'reason_required',
  POINTS_CONFIRM_REQUIRED: 'criteria.points_confirm_required',
  DRAFT_EXISTS: 'criteria.draft_exists',
  NO_DRAFT: 'criteria.no_draft',
  VERSION_NOT_DRAFT: 'criteria.version_not_draft',
  NO_PUBLISHED_VERSION: 'criteria.no_published_version',
}

export const CRITERIA_REASON_MIN_LENGTH = 10
export const CRITERIA_REASON_MAX_LENGTH = 1000
export const CRITERIA_NAME_MAX_LENGTH = 200
export const CRITERIA_DESCRIPTION_MAX_LENGTH = 2000
export const CRITERIA_TEXT_MIN_LENGTH = 2
