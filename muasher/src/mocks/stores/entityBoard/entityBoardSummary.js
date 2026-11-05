import { allAssessments, exposesResult } from '../assessments/assessmentsStore'
import { assessmentResource } from '../assessments/selfSerializers'
import { financeState } from '../finance/financeState'
import { inboxOf } from '../notificationsStore'
import { entityActionItems } from './entityActionItems'

const OPEN_ORDERS = ['created', 'pending']
const ISSUED = 'issued'

const PATH_LABELS = {
  self: { ar: 'التقييم الذاتي', en: 'Self assessment' },
  accredited: { ar: 'التقييم المعتمد', en: 'Accredited assessment' },
  advanced: { ar: 'التقييم المتقدم (الجائزة)', en: 'Advanced assessment (Award)' },
}

const RESULT_STATES = { self: 'result-available', accredited: 'completed' }

function ownedRecords(entityId) {
  return allAssessments().filter((record) => record.entityId === Number(entityId))
}

function latestResultOf(entityId) {
  const [latest] = ownedRecords(entityId)
    .filter((record) => RESULT_STATES[record.path] === record.status && exposesResult(record))
    .sort((left, right) => (left.completedAt < right.completedAt ? 1 : -1))
  if (!latest) return null
  const resource = assessmentResource(latest)
  return {
    assessmentId: latest.id,
    number: latest.number,
    path: latest.path,
    pathLabel: PATH_LABELS[latest.path],
    percent: resource.result?.percent ?? null,
    completedAt: latest.completedAt,
  }
}

function presentPayment(payment) {
  return {
    id: payment.id,
    reference: payment.reference,
    purpose: payment.purpose,
    status: payment.status,
    total: payment.total,
    currency: payment.currency,
    createdAt: payment.createdAt,
    paidAt: payment.paidAt,
  }
}

function paymentsCardOf(entityId) {
  const state = financeState()
  const payments = state.payments.filter((payment) => payment.entityId === Number(entityId))
  const [last] = [...payments].sort((left, right) => (left.createdAt < right.createdAt ? 1 : -1))
  const [pending] = payments
    .filter((payment) => OPEN_ORDERS.includes(payment.status))
    .sort((left, right) => (left.expiresAt < right.expiresAt ? -1 : 1))
  const ids = new Set(payments.map((payment) => payment.id))
  return {
    lastPayment: last ? presentPayment(last) : null,
    pendingPayment: pending ? { ...presentPayment(pending), expiresAt: pending.expiresAt } : null,
    receiptsCount: state.receipts.filter(
      (receipt) => ids.has(receipt.paymentId) && receipt.status === ISSUED,
    ).length,
    link: '/payments',
  }
}

function card(key, value, format = 'number', link = null) {
  return { key, value, format, link }
}

export function entityBoardSummary(user, now = new Date()) {
  const entityId = user.entityId
  const latest = entityId ? latestResultOf(entityId) : null
  const open = entityId
    ? ownedRecords(entityId).filter((record) => assessmentResource(record).isOpen).length
    : 0
  const unread = inboxOf(user).filter((item) => !item.readAt && !item.archivedAt).length
  return {
    board: 'entity',
    landingBoard: 'entity',
    availableBoards: ['entity'],
    cards: [
      card('latest_result_percent', latest?.percent ?? null, 'percent', '/assessment/history'),
      card('open_requests', open, 'number', '/assessment/history'),
      card('unread_notifications', unread, 'number', '/notifications'),
    ],
    widgets: [
      { key: 'latest_result', data: latest },
      { key: 'criteria_progress', data: [] },
      { key: 'requests', data: [] },
      { key: 'payments_card', data: entityId ? paymentsCardOf(entityId) : null },
    ],
    actionCenter: entityActionItems(user),
    generatedAt: now.toISOString(),
  }
}
