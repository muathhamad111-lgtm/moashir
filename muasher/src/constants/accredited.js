export const ACCREDITED_STATUSES = {
  AWAITING_PAYMENT: 'awaiting-payment',
  DRAFT: 'draft',
  SUBMITTED: 'submitted',
  ASSIGNED: 'assigned',
  UNDER_REVIEW: 'under-review',
  NEEDS_COMPLETION: 'needs-completion',
  COMPLETED: 'completed',
  REFUNDED: 'refunded',
  DECLINED: 'declined',
}

export const QUEUE_STATUSES = [
  ACCREDITED_STATUSES.SUBMITTED,
  ACCREDITED_STATUSES.ASSIGNED,
  ACCREDITED_STATUSES.UNDER_REVIEW,
  ACCREDITED_STATUSES.NEEDS_COMPLETION,
  ACCREDITED_STATUSES.COMPLETED,
  ACCREDITED_STATUSES.REFUNDED,
  ACCREDITED_STATUSES.DECLINED,
]

export const ENTITY_EDITABLE_STATUSES = [
  ACCREDITED_STATUSES.DRAFT,
  ACCREDITED_STATUSES.NEEDS_COMPLETION,
]

export const ASSIGNABLE_STATUSES = [ACCREDITED_STATUSES.SUBMITTED]

export const REASSIGNABLE_STATUSES = [
  ACCREDITED_STATUSES.ASSIGNED,
  ACCREDITED_STATUSES.UNDER_REVIEW,
  ACCREDITED_STATUSES.NEEDS_COMPLETION,
]

export const FORCE_TARGETS = [
  ACCREDITED_STATUSES.DRAFT,
  ACCREDITED_STATUSES.SUBMITTED,
  ACCREDITED_STATUSES.ASSIGNED,
  ACCREDITED_STATUSES.UNDER_REVIEW,
  ACCREDITED_STATUSES.COMPLETED,
]

export const FORCE_BLOCKED_STATUSES = [
  ACCREDITED_STATUSES.AWAITING_PAYMENT,
  ACCREDITED_STATUSES.REFUNDED,
  ACCREDITED_STATUSES.DECLINED,
]

export const COMPLETION_STATUSES = {
  OPEN: 'open',
  RESUBMITTED: 'resubmitted',
  CANCELLED: 'cancelled',
  EXPIRED_CONTINUED: 'expired-continued',
  EXPIRED_DECLINED: 'expired-declined',
}

export const COMPLETION_ITEM_KINDS = { CV: 'cv', EVIDENCE: 'evidence' }

export const EXPIRY_OUTCOMES = { CONTINUE: 'continue_as_submitted', DECLINE: 'decline' }

export const SCAN_STATUSES = {
  PENDING: 'pending',
  CLEAN: 'clean',
  INFECTED: 'infected',
  FAILED: 'failed',
}

export const BUNDLE_STATUSES = {
  PREPARING: 'preparing',
  READY: 'ready',
  FAILED: 'failed',
  EXPIRED: 'expired',
}

export const REVIEW_MODES = { EDIT: 'edit', READ_ONLY: 'read_only' }

export const ASSIGNED_TABS = { NEW: 'new', IN_PROGRESS: 'in_progress', COMPLETED: 'completed' }

export const ASSIGNMENT_STATUSES = {
  ACTIVE: 'active',
  REASSIGNED: 'reassigned',
  COMPLETED: 'completed',
  ENDED: 'ended',
}

export const FEEDBACK_FIELDS = ['strengths', 'improvementPoints', 'notes']

export const QUEUE_SORTS = [
  '-submitted_at',
  'submitted_at',
  'due_at',
  '-due_at',
  'status',
  '-status',
  '-created_at',
  'created_at',
]

export const DEFAULT_QUEUE_SORT = '-submitted_at'

export const ACCREDITED_LIMITS = {
  CV_MAX: 10000,
  FEEDBACK_MAX: 5000,
  COMPLETION_NOTES_MIN: 10,
  COMPLETION_NOTES_MAX: 2000,
  COMPLETION_ITEM_NOTE_MAX: 500,
  COMPLETION_DEADLINE_MAX_DAYS: 60,
  REASSIGN_REASON_MIN: 10,
  OVERRIDE_REASON_MIN: 20,
  REASON_MAX: 1000,
  ASSESSORS_SEARCH_MAX: 100,
}

export const EVIDENCE_POLL_MS = 3000

export const BUNDLE_POLL_MS = 2000

export const EVIDENCE_TYPE_EXTENSIONS = {
  pdf: ['.pdf'],
  docx: ['.docx'],
  xlsx: ['.xlsx'],
  pptx: ['.pptx'],
  jpg: ['.jpg', '.jpeg'],
  png: ['.png'],
}

export const ACCREDITED_CODES = {
  REQUEST_OPEN: 'accredited.request_open',
  PAYMENT_BEING_PROCESSED: 'accredited.payment_being_processed',
  SUBMISSION_INCOMPLETE: 'submission.incomplete',
  ITEM_NOT_REQUESTED: 'completion.item_not_requested',
  DEADLINE_PASSED: 'completion.deadline_passed',
  SUB_FULL: 'attachment.sub_full',
  CONTENT_MISMATCH: 'file.content_mismatch',
  NOT_CLEAN: 'file.not_clean',
  SCORES_INCOMPLETE: 'scores.incomplete',
  BUNDLE_EMPTY: 'evidence.bundle_empty',
  BUNDLE_NOT_READY: 'evidence.bundle_not_ready',
  ENTITY_DELETED: 'entity.deleted',
  ENTITY_NOT_ACTIVE: 'assessment.entity_not_active',
  TRANSITION_ILLEGAL: 'state.transition_illegal',
  CRITERIA_UPDATE_IN_PROGRESS: 'assessment.criteria_update_in_progress',
  REASON_REQUIRED: 'reason_required',
  NOT_ASSIGNEE: 'assessment.not_assignee',
}

export const ACCREDITED_REQUEST_PREFIX = 'ACC'
