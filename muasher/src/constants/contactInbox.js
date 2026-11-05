export const CONTACT_INBOX_STATUSES = Object.freeze(['new', 'in-progress', 'replied', 'closed'])

export const CONTACT_INBOX_OPEN_STATUSES = Object.freeze(['new', 'in-progress', 'replied'])

export const CONTACT_LINKED_FILTERS = Object.freeze(['true', 'false'])

export const CONTACT_ASSIGNEE_FILTERS = Object.freeze({ ME: 'me', NONE: 'none' })

export const CONTACT_ENTRY_KINDS = Object.freeze({
  MESSAGE: 'message',
  REPLY: 'reply',
  NOTE: 'note',
})

export const CONTACT_COMPOSER_MODES = Object.freeze({ REPLY: 'reply', NOTE: 'note' })

export const CONTACT_INBOX_CODES = Object.freeze({
  CLOSED: 'contact.message_closed',
  ASSIGNEE_NOT_ELIGIBLE: 'contact.assignee_not_eligible',
  TRANSITION_ILLEGAL: 'state.transition_illegal',
})

export const CONTACT_INBOX_LIMITS = Object.freeze({
  searchMin: 2,
  bodyMin: 2,
  bodyMax: 5000,
  perPage: 15,
  searchDelayMs: 300,
})

export const CONTACT_STATUS_QUERY = 'status'
