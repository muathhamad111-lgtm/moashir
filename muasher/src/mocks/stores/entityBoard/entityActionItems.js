import { allAssessments } from '../assessments/assessmentsStore'
import { findEntity } from '../entitiesStore'
import { financeState } from '../finance/financeState'
import { expireDueOrders } from '../finance/paymentLifecycle'
import { openCompletionOf } from '../accredited/accreditedRecords'
import { accreditedProgressOf } from '../accredited/accreditedProgress'
import { senderThreadCards } from '../senderThreads'
import { profileCompletionOf } from './profileCompletion'

const SEVERITY_RANK = { danger: 0, warning: 1, info: 2 }
const OPEN_ORDERS = ['created', 'pending']
const COMPLETE_PERCENT = 100

const LINKS = {
  profile: '/entity/profile',
  self: '/assessment/self',
  accredited: '/assessment/accredited',
  payments: '/payments',
  messages: '/messages',
}

function emptyContext() {
  return {
    assessmentId: null,
    paymentId: null,
    messageId: null,
    reference: null,
    remainingSubs: null,
    percent: null,
  }
}

function item(key, count, severity, link, { dueAt = null, context = emptyContext() } = {}) {
  return { key, count, severity, link, dueAt, context }
}

function requestContext(records, extra = () => ({})) {
  if (records.length !== 1) return emptyContext()
  const [record] = records
  return {
    ...emptyContext(),
    assessmentId: record.id,
    reference: record.number,
    ...extra(record),
  }
}

function recordsIn(entityId, path, status) {
  return allAssessments().filter(
    (record) =>
      record.entityId === Number(entityId) && record.path === path && record.status === status,
  )
}

function profileItem(entity) {
  const { percent } = profileCompletionOf(entity)
  if (percent === COMPLETE_PERCENT) return null
  return item('complete_profile', 1, 'warning', LINKS.profile, {
    context: { ...emptyContext(), percent },
  })
}

function earliestDeadline(records) {
  const deadlines = records
    .map((record) => openCompletionOf(record)?.deadlineAt)
    .filter(Boolean)
    .sort()
  return deadlines[0] ?? null
}

function requestItems(entityId) {
  const selfDrafts = recordsIn(entityId, 'self', 'draft')
  const drafts = recordsIn(entityId, 'accredited', 'draft')
  const completions = recordsIn(entityId, 'accredited', 'needs-completion')
  return [
    item('submit_self_draft', selfDrafts.length, 'info', LINKS.self, {
      context: requestContext(selfDrafts),
    }),
    item('submit_accredited_draft', drafts.length, 'warning', LINKS.accredited, {
      context: requestContext(drafts, (record) => ({
        remainingSubs: accreditedProgressOf(record)?.remaining.length ?? null,
      })),
    }),
    item('complete_accredited_request', completions.length, 'danger', LINKS.accredited, {
      dueAt: earliestDeadline(completions),
      context: requestContext(completions),
    }),
  ]
}

function paymentItem(entityId) {
  expireDueOrders()
  const open = financeState()
    .payments.filter(
      (payment) => payment.entityId === Number(entityId) && OPEN_ORDERS.includes(payment.status),
    )
    .sort((left, right) => (left.expiresAt < right.expiresAt ? -1 : 1))
  const [earliest] = open
  const context =
    open.length === 1
      ? { ...emptyContext(), paymentId: earliest.id, reference: earliest.reference }
      : emptyContext()
  return item('complete_payment', open.length, 'warning', LINKS.payments, {
    dueAt: earliest?.expiresAt ?? null,
    context,
  })
}

function contactItem(user) {
  const unread = senderThreadCards(user).filter((thread) => thread.hasUnreadReply)
  const [single] = unread
  return item(
    'read_contact_replies',
    unread.length,
    'info',
    unread.length === 1 ? `${LINKS.messages}/${single.id}` : LINKS.messages,
    {
      context:
        unread.length === 1
          ? { ...emptyContext(), messageId: single.id, reference: single.reference }
          : emptyContext(),
    },
  )
}

function bySeverity(left, right) {
  return SEVERITY_RANK[left.severity] - SEVERITY_RANK[right.severity]
}

export function entityActionItems(user) {
  const entity = user?.entityId ? findEntity(user.entityId) : null
  if (!entity) return []
  return [
    profileItem(entity),
    ...requestItems(entity.id),
    paymentItem(entity.id),
    contactItem(user),
  ]
    .filter((candidate) => candidate && candidate.count > 0)
    .sort(bySeverity)
}
