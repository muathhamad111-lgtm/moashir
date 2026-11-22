export const NOTIFICATION_CATEGORIES = Object.freeze([
  'action_required',
  'informational',
  'alert',
  'error',
])

export const NOTIFICATION_CATEGORY_TONES = Object.freeze({
  action_required: 'warning',
  informational: 'info',
  alert: 'danger',
  error: 'danger',
})

export const NOTIFICATION_MODULES = Object.freeze([
  'USR',
  'REG',
  'SEC',
  'STD',
  'SELF',
  'ACC',
  'PER',
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

export const NOTIFICATION_AUDIENCES = Object.freeze([
  'account_holder',
  'entity',
  'system_admin',
  'super_admin',
  'request_follower',
  'assessor',
  'committee_assessor',
  'filtering_officer',
  'award_secretary',
  'criteria_officer',
  'content_officer',
  'finance_officer',
  'contact_handler',
])

export const NOTIFICATION_CHANNELS = Object.freeze(['in_app', 'email', 'sms'])

// Who a notification's wording speaks to; set by the system (beneficiary = entity users and guests).
export const NOTIFICATION_AUDIENCE_KINDS = Object.freeze(['beneficiary', 'management'])

// The sign-in / verification code: its text message is the SMS settings' OTP template, not a notice text.
export const OTP_NOTIFICATION_CODE = 'REG-02'
