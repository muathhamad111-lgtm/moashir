export const USER_STATUSES = {
  INVITED: 'invited',
  ACTIVE: 'active',
  LOCKED: 'locked',
  SUSPENDED: 'suspended',
  EXPIRED: 'expired',
}

export const USER_STATUS_LIST = Object.values(USER_STATUSES)

export const USER_ACTIONS = {
  EDIT: 'edit',
  SUSPEND: 'suspend',
  ACTIVATE: 'activate',
  UNLOCK: 'unlock',
  RESEND_INVITATION: 'resendInvitation',
  RESET_MFA: 'resetMfa',
  QUALIFICATIONS: 'qualifications',
  REQUEST_SCOPE: 'requestScope',
}

export const PATH_ACCESS_ACTIONS = [USER_ACTIONS.QUALIFICATIONS, USER_ACTIONS.REQUEST_SCOPE]
