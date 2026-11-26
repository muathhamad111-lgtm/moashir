export const AUDIT_FILTER_KEYS = Object.freeze([
  'from',
  'to',
  'event',
  'subjectType',
  'subjectId',
  'actorId',
  'ip',
])

export const AUDIT_ACTOR_KINDS = Object.freeze({
  USER: 'user',
  SUPER_ADMIN: 'super_admin',
  SYSTEM: 'system',
})

export const AUDIT_LIMITS = Object.freeze({ perPage: 25 })

export const AUDIT_CODES = Object.freeze({ TOO_LARGE: 'export.too_large' })
