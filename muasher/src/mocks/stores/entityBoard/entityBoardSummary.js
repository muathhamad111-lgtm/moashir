import { allAssessments, exposesResult } from '../assessments/assessmentsStore'
import { assessmentResource } from '../assessments/selfSerializers'
import { financeState } from '../finance/financeState'
import { inboxOf } from '../notificationsStore'
import { entityActionItems } from './entityActionItems'
import { eligibleResultsOf } from '../insights/insightsResults'
import { strengthsGapsOf } from '../insights/insightsTools'
import { levelWidgetOf, pathsWidgetOf } from '../periodic/pathsBoard'
import { nextStepOf } from '../periodic/nextStep'

const OPEN_ORDERS = ['created', 'pending', 'under-review']
const ISSUED = 'issued'

const PATH_LABELS = {
  self: { ar: 'التقييم الذاتي', en: 'Self assessment' },
  accredited: { ar: 'التقييم المعتمد', en: 'Accredited assessment' },
  advanced: { ar: 'التقييم المتقدم (الجائزة)', en: 'Advanced assessment (Award)' },
}

const UPLOADED = 'uploaded'
const CLEAN = 'clean'

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

function acceptedEvidenceCountOf(entityId) {
  return ownedRecords(entityId)
    .flatMap((record) => record.evidence ?? [])
    .filter((row) => row.status === UPLOADED && row.scanStatus === CLEAN).length
}

function accreditedMainsOf(entityId) {
  const [latest] = eligibleResultsOf(entityId)
  if (!latest) return []
  return strengthsGapsOf(latest).mains.map(({ code, name, percent }) => ({ code, name, percent }))
}

function presentPayment(payment) {
  return {
    id: payment.id,
    reference: payment.reference,
    purpose: payment.purpose,
    channel: payment.channel ?? 'electronic',
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

function pathWidgets(entityId) {
  if (!entityId) return []
  const paths = pathsWidgetOf(entityId)
  return [
    { key: 'level', data: levelWidgetOf(entityId) },
    { key: 'paths', data: paths },
    { key: 'next_step', data: nextStepOf(paths, entityId) },
  ]
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
      card('latest_result_percent', latest?.percent ?? null, 'percent', '/paths/accredited'),
      card('open_requests', open, 'number', '/paths/accredited'),
      card('unread_notifications', unread, 'number', '/notifications'),
      card('accepted_evidence_count', entityId ? acceptedEvidenceCountOf(entityId) : 0, 'count'),
    ],
    widgets: [
      { key: 'latest_result', data: latest },
      { key: 'criteria_progress', data: entityId ? accreditedMainsOf(entityId) : [] },
      { key: 'requests', data: [] },
      { key: 'payments_card', data: entityId ? paymentsCardOf(entityId) : null },
      ...pathWidgets(entityId),
    ],
    actionCenter: entityActionItems(user),
    generatedAt: now.toISOString(),
  }
}
