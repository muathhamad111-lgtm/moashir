export const USER_STATUSES = {
  INVITED: 'invited',
  ACTIVE: 'active',
  LOCKED: 'locked',
  SUSPENDED: 'suspended',
  EXPIRED: 'expired',
}

export const USER_STATUS_LIST = Object.values(USER_STATUSES)

export const USER_ACTIONS = {
  DETAILS: 'details',
  EDIT: 'edit',
  SUSPEND: 'suspend',
  ACTIVATE: 'activate',
  UNLOCK: 'unlock',
  RESEND_INVITATION: 'resendInvitation',
  SEND_PASSWORD_RESET: 'sendPasswordReset',
  REVOKE_SESSIONS: 'revokeSessions',
  CANCEL_INVITATION: 'cancelInvitation',
  ACTIVITY: 'activity',
  RESET_MFA: 'resetMfa',
  QUALIFICATIONS: 'qualifications',
  REQUEST_SCOPE: 'requestScope',
  NAME_CORRECTION: 'nameCorrection',
  IDENTITY_HISTORY: 'identityHistory',
}

// The users table shows «عرض» on every row; every other action lives in the «⋮» menu.
export const USER_INLINE_ACTIONS = [USER_ACTIONS.DETAILS]

export const PATH_ACCESS_ACTIONS = [USER_ACTIONS.QUALIFICATIONS, USER_ACTIONS.REQUEST_SCOPE]

export const IDENTITY_ACTIONS = [USER_ACTIONS.NAME_CORRECTION, USER_ACTIONS.IDENTITY_HISTORY]
