import { REJECTION_NOTE, REJECTION_REASONS, BULK_DECISION_MAX } from '@/constants/bankTransfer'
import { usersFixture } from '../../fixtures/users'
import { normalizeSearchText } from '../searchIndex'
import { findPayment, persistFinanceState, transferOf } from './financeState'
import { fieldMessage, financeReject } from './financeRejection'
import { issueReceipt, nowIso, transition } from './paymentLifecycle'
import { announcePaymentPaid } from './paymentHooks'
import { isFrozenEntity, transferPayments } from './bankTransferRecords'

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i
const REASON = { MIN: 10, MAX: 1000 }
const STATUSES = ['under-review', 'paid', 'rejected', 'cancelled', 'refunded', 'all']
const PURPOSES = ['accredited', 'bag', 'self', 'award']
const MODES = ['test', 'live']
const SORTS = ['submitted_at', '-submitted_at']
const FILTER_KEYS = ['status', 'purpose', 'bankAccount', 'mode', 'frozen']
const SEARCH_MIN = 2
const OTHER = 'other'

function filterValue(query, key) {
  return query.get(`filter[${key}]`) ?? ''
}

function checkChoice(errors, key, value, choices) {
  if (value && !choices.includes(value)) errors[`filter.${key}`] = [fieldMessage('choice')]
}

export function queueFilters(query) {
  const filters = Object.fromEntries(FILTER_KEYS.map((key) => [key, filterValue(query, key)]))
  const errors = {}
  checkChoice(errors, 'status', filters.status, STATUSES)
  checkChoice(errors, 'purpose', filters.purpose, PURPOSES)
  checkChoice(errors, 'mode', filters.mode, MODES)
  checkChoice(errors, 'frozen', filters.frozen, ['0', '1'])
  if (filters.bankAccount && !UUID.test(filters.bankAccount))
    errors['filter.bankAccount'] = [fieldMessage('invalid')]
  const sort = query.get('sort') ?? 'submitted_at'
  if (!SORTS.includes(sort)) errors.sort = [fieldMessage('choice')]
  const search = normalizeSearchText(query.get('search') ?? '')
  if (search && search.replace(/\s+/g, '').length < SEARCH_MIN)
    errors.search = [fieldMessage('invalid')]
  if (Object.keys(errors).length) throw financeReject.validation(errors)
  return {
    ...filters,
    status: filters.status || 'under-review',
    frozen: filters.frozen || '0',
    sort,
    search,
  }
}

function searchMatches(payment, term) {
  if (!term) return true
  return [payment.reference, payment.entityName?.ar ?? '', payment.entityName?.en ?? ''].some(
    (value) => normalizeSearchText(value).includes(term),
  )
}

function matches(payment, filters) {
  const transfer = transferOf(payment.id)
  return (
    (filters.status === 'all' || payment.status === filters.status) &&
    (!filters.purpose || payment.purpose === filters.purpose) &&
    (!filters.mode || payment.mode === filters.mode) &&
    (!filters.bankAccount || transfer?.bankAccountId === filters.bankAccount) &&
    isFrozenEntity(payment.entityId) === (filters.frozen === '1') &&
    searchMatches(payment, filters.search)
  )
}

function submittedAtOf(payment) {
  return transferOf(payment.id)?.submittedAt ?? payment.createdAt
}

export function queueOf(filters) {
  const direction = filters.sort.startsWith('-') ? -1 : 1
  return transferPayments()
    .filter((payment) => matches(payment, filters))
    .sort((left, right) => submittedAtOf(left).localeCompare(submittedAtOf(right)) * direction)
}

export function reviewablePayment(id) {
  const payment = UUID.test(id) ? findPayment(id) : null
  if (!payment || payment.channel !== 'bank_transfer') throw financeReject.notFound()
  return payment
}

function reviewerOf(user) {
  if (user.superAdmin) return { id: user.id, name: null }
  const staff = usersFixture.find((item) => item.id === user.id)
  return { id: user.id, name: staff?.name ?? null }
}

function text(value) {
  return typeof value === 'string' ? value.trim() : ''
}

export function validatedReason(user, body) {
  if (!user.superAdmin) return null
  const reason = text(body.reason)
  if (!reason) throw financeReject.reasonRequired()
  if (reason.length < REASON.MIN || reason.length > REASON.MAX)
    throw financeReject.validation({ reason: [fieldMessage('min', { min: REASON.MIN })] })
  return reason
}

export function validatedRejection(body) {
  const rejectionReason = text(body.rejectionReason)
  const note = text(body.note)
  const errors = {}
  if (!REJECTION_REASONS.includes(rejectionReason))
    errors.rejectionReason = [fieldMessage('choice')]
  if (rejectionReason === OTHER && !note) errors.note = [fieldMessage('rejection_note_required')]
  else if (note && note.length < REJECTION_NOTE.MIN)
    errors.note = [fieldMessage('min', { min: REJECTION_NOTE.MIN })]
  else if (note.length > REJECTION_NOTE.MAX)
    errors.note = [fieldMessage('max', { max: REJECTION_NOTE.MAX })]
  if (Object.keys(errors).length) throw financeReject.validation(errors)
  return { rejectionReason, note: note || null }
}

export function validatedIds(body) {
  const ids = body.paymentIds
  const valid =
    Array.isArray(ids) &&
    ids.length >= 1 &&
    ids.length <= BULK_DECISION_MAX &&
    new Set(ids).size === ids.length &&
    ids.every((id) => UUID.test(id))
  if (!valid) throw financeReject.validation({ paymentIds: [fieldMessage('invalid')] })
  return ids
}

function assertDecidable(payment) {
  if (isFrozenEntity(payment.entityId)) throw financeReject.entityDeleted()
  if (payment.status !== 'under-review')
    throw financeReject.illegalTransition(payment.status, 'review')
}

function recordReview(payment, user, fields = {}) {
  Object.assign(transferOf(payment.id), {
    reviewedAt: nowIso(),
    reviewedBy: reviewerOf(user),
    ...fields,
  })
}

export function approveTransfer(user, payment) {
  assertDecidable(payment)
  transition(payment, 'paid', 'approve')
  recordReview(payment, user)
  issueReceipt(payment)
  persistFinanceState()
  announcePaymentPaid(payment)
  return payment
}

export function rejectTransfer(user, payment, rejection) {
  assertDecidable(payment)
  transition(payment, 'rejected', 'reject')
  recordReview(payment, user, {
    rejectionReason: rejection.rejectionReason,
    reviewNote: rejection.note,
  })
  persistFinanceState()
  return payment
}

function skipCodeOf(error) {
  return error?.code === 'not_found' ? 'not_found' : error?.code
}

export function decideEach(ids, decide) {
  const outcome = { decided: [], skipped: [] }
  ids.forEach((id) => {
    try {
      decide(reviewablePayment(id))
      outcome.decided.push(id)
    } catch (error) {
      outcome.skipped.push({ id, code: skipCodeOf(error) })
    }
  })
  return outcome
}
