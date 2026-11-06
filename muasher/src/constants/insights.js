export const GAP_CLASSES = Object.freeze({
  STRENGTH: 'strength',
  OPPORTUNITY: 'opportunity',
  CRITICAL_GAP: 'critical_gap',
})

export const GAP_COLUMNS = Object.freeze([
  {
    gapClass: GAP_CLASSES.STRENGTH,
    itemsKey: 'strengths',
    countKey: 'strength',
    icon: 'check-circle',
  },
  {
    gapClass: GAP_CLASSES.CRITICAL_GAP,
    itemsKey: 'criticalGaps',
    countKey: 'criticalGap',
    icon: 'warning-triangle',
  },
  {
    gapClass: GAP_CLASSES.OPPORTUNITY,
    itemsKey: 'opportunities',
    countKey: 'opportunity',
    icon: 'chart-up',
  },
])

export const GAP_TONES = Object.freeze({
  strength: 'strength',
  opportunity: 'opportunity',
  critical_gap: 'critical',
})

export const GAP_ICONS = Object.freeze({
  strength: 'check-circle',
  opportunity: 'chart-up',
  critical_gap: 'warning-triangle',
})

export const MATURITY_BANDS = Object.freeze(['emerging', 'developing', 'mature', 'leading'])

export const BAND_BOUNDS = Object.freeze({
  developing: 'developingFrom',
  mature: 'matureFrom',
  leading: 'leadingFrom',
})

export const EMPTY_STATE_REASONS = Object.freeze({
  NO_VERIFIED_RESULT: 'no_verified_result',
  ONLY_SELF: 'only_self',
  IN_PROGRESS: 'in_progress',
  UNDER_REVIEW: 'under_review',
})

export const EMPTY_STATE_ICONS = Object.freeze({
  no_verified_result: 'flag',
  only_self: 'flag',
  in_progress: 'clock',
  under_review: 'refresh-dots',
})

export const EXCLUSION_REASONS = Object.freeze({
  CERTIFICATE_REVOKED: 'certificate_revoked',
  UNDER_REVIEW: 'under_review',
})

export const CTA_KINDS = Object.freeze({
  START_ACCREDITED: 'start_accredited',
  VIEW_REQUEST: 'view_request',
})

export const DELTA_COMPARISONS = Object.freeze({
  MATCHED: 'matched',
  NEW_IN_VERSION: 'new_in_version',
  NO_PREVIOUS: 'no_previous',
})

export const CERTIFICATE_STANDINGS = Object.freeze({
  LEVEL: 'level',
  PARTICIPATION: 'participation',
  NOT_ISSUED: 'not_issued',
})

export const LIBRARY_ITEM_KINDS = Object.freeze({
  FEEDBACK_REPORT: 'feedback_report',
  BOARD_REPORT: 'board_report',
  CERTIFICATE: 'certificate',
})

export const LIBRARY_ITEM_ICONS = Object.freeze({
  feedback_report: 'docs',
  board_report: 'layers',
  certificate: 'award',
})

export const INSIGHTS_TOOLS = Object.freeze({
  STRENGTHS: 'strengths',
  MATURITY: 'maturity',
  BOARD_REPORTS: 'boardReports',
})

export const INSIGHTS_ERROR_CODES = Object.freeze({
  NOT_ELIGIBLE: 'insights.result_not_eligible',
})

export const RESULT_QUERY_KEY = 'result'
export const STRENGTHS_PREVIEW_COUNT = 6
export const ASSESSOR_SUMMARY_MAX = 2000
export const MATURITY_TEXT_MAX = 600
export const RULE_DECIMAL_PATTERN = /^\d{1,3}(\.\d{1,2})?$/
export const RULE_MAX = 100
export const VAT_NUMBER_PATTERN = /^3\d{13}3$/
