export const NOTIFICATION_TABS = Object.freeze({ ALL: 'all', UNREAD: 'unread' })

export const NOTIFICATION_CATEGORIES = Object.freeze({
  ACTION_REQUIRED: 'action_required',
  INFORMATIONAL: 'informational',
  ALERT: 'alert',
  ERROR: 'error',
})

export const NOTIFICATION_CATEGORY_ICONS = Object.freeze({
  action_required: 'lightning',
  informational: 'info-circle',
  alert: 'warning-triangle',
  error: 'warning-triangle',
})

export const NOTIFICATION_MODULE_ICONS = Object.freeze({
  REG: 'building',
  SELF: 'doc',
  ACC: 'clipboard-check',
  ADV: 'trophy',
  EVD: 'folder',
  PAY: 'credit-card',
  CERT: 'award',
  CNT: 'envelope',
})

export const NOTIFICATION_MODULES = Object.freeze([
  'USR',
  'REG',
  'SEC',
  'STD',
  'SELF',
  'ACC',
  'ADV',
  'CYC',
  'EVD',
  'INT',
  'AI',
  'PAY',
  'FIN',
  'CMS',
  'CERT',
  'CNT',
])

export const EDITABLE_NOTIFICATION_CHANNELS = Object.freeze(['email', 'sms'])

export function notificationIconOf(notification) {
  return (
    NOTIFICATION_MODULE_ICONS[notification.module] ??
    NOTIFICATION_CATEGORY_ICONS[notification.category] ??
    NOTIFICATION_CATEGORY_ICONS.informational
  )
}
