export const IDENTITY_CHANGE_STATUSES = Object.freeze({
  PENDING: 'pending',
  APPROVED: 'approved',
  REJECTED: 'rejected',
  CANCELLED: 'cancelled',
})

export const IDENTITY_CHANGE_STATUS_VALUES = Object.freeze(Object.values(IDENTITY_CHANGE_STATUSES))

export const IDENTITY_FIELDS = Object.freeze({ NAME: 'name', NATIONAL_ID: 'national_id' })
