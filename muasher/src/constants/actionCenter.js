import { ROUTE_NAMES } from './routeNames'

export const ACTION_ITEM_KEYS = Object.freeze({
  COMPLETE_PROFILE: 'complete_profile',
  SUBMIT_SELF_DRAFT: 'submit_self_draft',
  SUBMIT_ACCREDITED_DRAFT: 'submit_accredited_draft',
  COMPLETE_ACCREDITED_REQUEST: 'complete_accredited_request',
  COMPLETE_ADVANCED_REQUEST: 'complete_advanced_request',
  SUBMIT_ADVANCED_DRAFT: 'submit_advanced_draft',
  COMPLETE_PAYMENT: 'complete_payment',
  BANK_TRANSFER_REJECTED: 'bank_transfer_rejected',
  BANK_TRANSFER_UNDER_REVIEW: 'bank_transfer_under_review',
  READ_CONTACT_REPLIES: 'read_contact_replies',
})

export const ATTENTION_TONES = Object.freeze({
  WARNING: 'warning',
  INFO: 'info',
  ACCENT: 'accent',
})

const K = ACTION_ITEM_KEYS
const T = ATTENTION_TONES

export const ATTENTION_ITEM_PRESENTATION = Object.freeze({
  [K.COMPLETE_PROFILE]: { icon: 'check-circle', tone: T.ACCENT },
  [K.SUBMIT_SELF_DRAFT]: { icon: 'doc', tone: T.ACCENT },
  [K.SUBMIT_ACCREDITED_DRAFT]: { icon: 'warning-triangle', tone: T.WARNING },
  [K.COMPLETE_ACCREDITED_REQUEST]: { icon: 'warning-triangle', tone: T.WARNING },
  [K.COMPLETE_ADVANCED_REQUEST]: { icon: 'warning-triangle', tone: T.WARNING },
  [K.SUBMIT_ADVANCED_DRAFT]: { icon: 'doc', tone: T.ACCENT },
  [K.COMPLETE_PAYMENT]: { icon: 'credit-card', tone: T.WARNING },
  [K.BANK_TRANSFER_REJECTED]: { icon: 'warning-triangle', tone: T.WARNING },
  [K.BANK_TRANSFER_UNDER_REVIEW]: { icon: 'refresh-dots', tone: T.INFO },
  [K.READ_CONTACT_REPLIES]: { icon: 'envelope', tone: T.INFO },
})

export const FALLBACK_ATTENTION_PRESENTATION = Object.freeze({
  icon: 'warning-triangle',
  tone: T.INFO,
})

export const ATTENTION_HIDDEN_ON_ROUTES = Object.freeze({
  [ACTION_ITEM_KEYS.COMPLETE_PROFILE]: Object.freeze([ROUTE_NAMES.ENTITY_PROFILE]),
})
