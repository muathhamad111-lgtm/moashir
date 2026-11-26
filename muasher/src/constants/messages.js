export const MESSAGE_TABS = Object.freeze({ OPEN: 'open', CLOSED: 'closed' })

export const MESSAGE_TAB_FILTERS = Object.freeze({
  [MESSAGE_TABS.OPEN]: 'new,in_progress,replied',
  [MESSAGE_TABS.CLOSED]: 'closed',
})

export const MESSAGE_STATUS_TONES = Object.freeze({
  new: 'info',
  'in-progress': 'warning',
  replied: 'success',
  closed: 'muted',
})

export const MESSAGE_AUTHORS = Object.freeze({ SENDER: 'sender', TEAM: 'team' })

export const MESSAGE_REPLY_LIMITS = Object.freeze({ min: 2, max: 5000 })
