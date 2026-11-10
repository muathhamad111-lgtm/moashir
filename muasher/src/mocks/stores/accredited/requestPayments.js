import { findPayment, receiptOf } from '../finance/financeState'
import { sessionCan, sessionIsSuperAdmin } from '../sessionPermissions'
import { accreditedProgressOf } from './accreditedProgress'
import { recordOfPayment } from './accreditedRecords'
import { maskedStatus } from '../award/statusMask'
import { pathLabelOf } from '../../fixtures/pathLabels'

const FINANCE_VIEW = 'finance.operations.view'
const QUEUE_VIEW = 'requests.queue.view'

function linksFinance() {
  return sessionIsSuperAdmin() || sessionCan(FINANCE_VIEW)
}

function opensRequests() {
  return sessionIsSuperAdmin() || sessionCan(QUEUE_VIEW)
}

function receiptSummary(receipt, withId) {
  if (!receipt) return null
  const summary = { number: receipt.number, status: receipt.status, isTest: receipt.isTest }
  return withId ? { ...summary, id: receipt.id } : summary
}

export function requestPaymentOf(record) {
  const payment = record.paymentId ? findPayment(record.paymentId) : null
  if (!payment) return null
  const withId = linksFinance()
  const summary = {
    reference: payment.reference,
    channel: payment.channel,
    status: payment.status,
    isTest: payment.mode === 'test',
    paidAt: payment.paidAt ?? null,
    receipt: receiptSummary(receiptOf(payment.id), withId),
  }
  return withId ? { ...summary, id: payment.id } : summary
}

export function progressSummaryOf(record) {
  const progress = accreditedProgressOf(record)
  return progress ? { subsComplete: progress.subsComplete, subsTotal: progress.subsTotal } : null
}

export function linkedRequestOf(paymentId) {
  const record = recordOfPayment(paymentId)
  if (!record) return null
  const summary = {
    number: record.number,
    path: record.path,
    pathLabel: pathLabelOf(record.path),
    status: maskedStatus(record),
    progress: progressSummaryOf(record),
  }
  return opensRequests() ? { id: record.id, ...summary } : summary
}

export function lastActivityOf(record) {
  const stamps = [
    record.createdAt,
    record.updatedAt,
    ...record.evidence.map((row) => row.createdAt),
  ]
  return stamps.filter(Boolean).sort().at(-1) ?? null
}
