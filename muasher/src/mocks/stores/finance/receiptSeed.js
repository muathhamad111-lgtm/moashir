import { FINANCE_YEAR } from '../../fixtures/finance'
import { receiptNumberOf, receiptSnapshotOf } from './receiptSnapshot'

const RECEIPT_ID_PREFIX = '7d2e4f10-8b3a-4c6d-b5e9-0a1f'

function receiptIdOf(index) {
  return `${RECEIPT_ID_PREFIX}${String(index + 1).padStart(8, '0')}`
}

function issuedPayments(payments) {
  return payments
    .filter((payment) => payment.paidAt && ['paid', 'refunded'].includes(payment.status))
    .sort((left, right) => left.paidAt.localeCompare(right.paidAt))
}

export function issueSeedReceipts(payments, refunds) {
  let issued = 0
  const receipts = issuedPayments(payments).map((payment, index) => {
    const isTest = payment.mode === 'test'
    if (!isTest) issued += 1
    const refund = refunds.find((item) => item.paymentId === payment.id)
    return {
      id: receiptIdOf(index),
      paymentId: payment.id,
      number: isTest ? null : receiptNumberOf(FINANCE_YEAR, issued),
      status: refund ? 'voided' : 'issued',
      isTest,
      issuedAt: payment.paidAt,
      voidedAt: refund ? refund.createdAt : null,
      reissueCount: 0,
      lastReissuedAt: null,
      snapshot: receiptSnapshotOf(payment),
    }
  })
  return { receipts, issued }
}
