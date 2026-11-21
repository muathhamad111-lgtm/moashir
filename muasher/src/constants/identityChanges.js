export const IDENTITY_CHANGE_STATUSES = Object.freeze({
  PENDING: 'pending',
  APPROVED: 'approved',
  REJECTED: 'rejected',
  CANCELLED: 'cancelled',
})

export const IDENTITY_CHANGE_STATUS_VALUES = Object.freeze(Object.values(IDENTITY_CHANGE_STATUSES))

export const IDENTITY_FIELDS = Object.freeze({ NAME: 'name', NATIONAL_ID: 'national_id' })

export const IDENTITY_CHANGE_KINDS = Object.freeze({
  FIRST_ENTRY: 'first_entry',
  CORRECTION: 'correction',
})

export const IDENTITY_CHANGE_SOURCES = Object.freeze({
  APPROVED_REQUEST: 'approved_request',
  ADMIN_ENTRY: 'admin_entry',
  SUPER_ADMIN_ENTRY: 'super_admin_entry',
  ENTITY_REGISTRATION: 'entity_registration',
  INVITATION_ACCEPTANCE: 'invitation_acceptance',
})

// Session `national_id_status`: whether the account still has to give its number.
export const NATIONAL_ID_STATES = Object.freeze({
  NOT_REQUIRED: 'not_required',
  MISSING: 'missing',
  PENDING: 'pending',
  PROVIDED: 'provided',
})

export const GENDERS = Object.freeze({ MALE: 'male', FEMALE: 'female' })
export const GENDER_VALUES = Object.freeze(Object.values(GENDERS))
