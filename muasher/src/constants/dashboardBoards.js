export const DASHBOARD_BOARDS = Object.freeze([
  'system_admin',
  'award_secretary',
  'filtering_officer',
  'request_follower',
  'assessor',
  'committee_assessor',
  'content_officer',
  'criteria_officer',
  'finance_officer',
  'entity',
  'executive',
])

export const BOARD_QUERY_KEY = 'board'

export const CARD_FORMATS = Object.freeze({
  COUNT: 'count',
  PERCENT: 'percent',
  MONEY: 'money',
  DAYS: 'days',
  DATE: 'date',
})

export const ACTION_SEVERITY_ORDER = Object.freeze(['danger', 'warning', 'info'])

export const ACTION_ICONS = Object.freeze({
  danger: 'warning-triangle',
  warning: 'clock',
  info: 'info-circle',
})
