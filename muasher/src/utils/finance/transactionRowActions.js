import { OPEN_PAYMENT_STATUSES, PAYMENT_CHANNELS, PAYMENT_STATUSES } from '@/constants/payments'
import { PERMISSIONS } from '@/constants/permissionKeys'

export const TRANSACTION_ROW_ACTIONS = Object.freeze({
  VIEW: 'view',
  APPROVE: 'approve',
  REJECT: 'reject',
  REFUND: 'refund',
  SETTLE: 'settle',
})

const A = TRANSACTION_ROW_ACTIONS

// The decisions sit in the row (a pending transfer shows approve and reject, its details in the menu);
// recording a refund or settling opens the transaction's own page,
// which checks the same rules again before it opens the dialog.
export const TRANSACTION_INLINE_ACTIONS = Object.freeze([A.APPROVE, A.REJECT, A.VIEW])

export function isPendingTransfer(item) {
  return (
    item.channel === PAYMENT_CHANNELS.BANK_TRANSFER && item.status === PAYMENT_STATUSES.UNDER_REVIEW
  )
}

// The actions one operation offers, by its status and the viewer's permissions: the same rules the
// transfer queue and the transaction page apply (the server decides again on every action).
export function transactionRowActionKeys(item, { can, isSuperAdmin }) {
  const keys = [A.VIEW]
  if (isPendingTransfer(item) && can(PERMISSIONS.FINANCE_TRANSFERS_REVIEW)) {
    keys.push(A.APPROVE, A.REJECT)
  }
  if (
    item.status === PAYMENT_STATUSES.PAID &&
    !item.hasRefund &&
    can(PERMISSIONS.FINANCE_REFUNDS_MANAGE)
  ) {
    keys.push(A.REFUND)
  }
  if (
    isSuperAdmin &&
    item.channel !== PAYMENT_CHANNELS.BANK_TRANSFER &&
    OPEN_PAYMENT_STATUSES.includes(item.status)
  ) {
    keys.push(A.SETTLE)
  }
  return keys
}
