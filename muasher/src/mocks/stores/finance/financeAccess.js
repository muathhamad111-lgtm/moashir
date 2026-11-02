import { currentSessionUser } from '../sessionStore'
import { sessionCan } from '../sessionPermissions'
import { financeReject } from './financeRejection'

export const FINANCE_PERMISSIONS = {
  OWN_VIEW: 'payments.own.view',
  OWN_CREATE: 'payments.own.create',
  OPERATIONS_VIEW: 'finance.operations.view',
  OPERATIONS_EXPORT: 'finance.operations.export',
  REFUNDS_MANAGE: 'finance.refunds.manage',
  RECEIPTS_MANAGE: 'finance.receipts.manage',
  EVENTS_VIEW: 'settings.payment.manage',
  CARTS_VIEW: 'messaging.campaigns.send',
  ASSESSMENT_SETTINGS: 'settings.assessment.manage',
  BAG_ADMIN: 'bag.admin.manage',
}

export const PURPOSE_PERMISSIONS = {
  accredited: 'assessment.accredited.manage',
  bag: 'bag.own.purchase',
}

export function signedInActor() {
  const user = currentSessionUser()
  if (!user) throw financeReject.unauthenticated()
  return user
}

export function actorCan(user, permission) {
  return user.superAdmin || sessionCan(permission)
}

export function requirePermission(user, permission) {
  if (!actorCan(user, permission)) throw financeReject.forbidden()
}

export function requireAnyPermission(user, permissions) {
  if (!permissions.some((permission) => actorCan(user, permission))) throw financeReject.forbidden()
}

export function ownsPayment(user, payment) {
  return user.entityId !== null && payment.entityId === Number(user.entityId)
}

export function authorizeOwnList(user) {
  if (user.superAdmin) return
  if (user.entityId === null || !sessionCan(FINANCE_PERMISSIONS.OWN_VIEW)) throw financeReject.forbidden()
}

export function authorizeCreate(user) {
  if (user.superAdmin) return
  if (user.entityId === null || !sessionCan(FINANCE_PERMISSIONS.OWN_CREATE)) throw financeReject.forbidden()
}

export function authorizePurpose(user, purpose) {
  const permission = PURPOSE_PERMISSIONS[purpose]
  if (!permission || user.superAdmin) return
  if (!sessionCan(permission)) throw financeReject.forbidden()
}

function authorizeOwnRecord(user, payment, permission) {
  if (user.superAdmin) return
  if (!sessionCan(permission)) throw financeReject.forbidden()
  if (!ownsPayment(user, payment)) throw financeReject.notFound()
}

export function authorizeViewOwn(user, payment) {
  authorizeOwnRecord(user, payment, FINANCE_PERMISSIONS.OWN_VIEW)
}

export function authorizeVerify(user, payment) {
  authorizeOwnRecord(user, payment, FINANCE_PERMISSIONS.OWN_CREATE)
}

export function authorizeReceiptDownload(user, payment) {
  if (actorCan(user, FINANCE_PERMISSIONS.OPERATIONS_VIEW)) return
  authorizeViewOwn(user, payment)
}

export function authorizeRefundRead(user) {
  requireAnyPermission(user, [FINANCE_PERMISSIONS.REFUNDS_MANAGE, FINANCE_PERMISSIONS.OPERATIONS_VIEW])
}

export function authorizeExport(user, record) {
  requirePermission(user, FINANCE_PERMISSIONS.OPERATIONS_EXPORT)
  if (record.requestedBy !== user.id) throw financeReject.notFound()
}

export function authorizeManualSettle(user) {
  if (!user.superAdmin) throw financeReject.forbidden()
}
