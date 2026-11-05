import { paginate } from '../../contract/pagination'
import { normalizeSearchText } from '../searchIndex'
import { financeState, receiptOf } from './financeState'
import { fieldMessage, financeReject } from './financeRejection'
import { fromMinor, toMinor } from './financeMoney'

const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/
const PAYMENT_STATUS_VALUES = [
  'created',
  'pending',
  'under-review',
  'paid',
  'failed',
  'rejected',
  'cancelled',
  'refunded',
]

export function isCalendarDate(value) {
  if (!DATE_PATTERN.test(value)) return false
  const date = new Date(`${value}T00:00:00Z`)
  return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value
}
const RIYADH_OFFSET = '+03:00'
const DAY_MS = 86400000
const MAX_PER_PAGE = 100
const SEARCH_MIN = 2
const SEARCH_MAX = 100

export const LIST_RULES = {
  mine: {
    status: PAYMENT_STATUS_VALUES,
    purpose: ['accredited', 'bag'],
  },
  transactions: {
    status: PAYMENT_STATUS_VALUES,
    purpose: ['accredited', 'bag'],
    channel: ['electronic', 'bank_transfer'],
    method: ['creditcard', 'mada', 'applepay', 'stcpay'],
    mode: ['test', 'live'],
    from: 'date',
    to: 'date',
  },
  refunds: { purpose: ['accredited', 'bag'], from: 'date', to: 'date' },
  events: {
    source: ['checkout', 'verification', 'webhook', 'expiry', 'reconciliation', 'admin'],
    result: ['applied', 'pending', 'ignored', 'rejected', 'unmatched', 'late_payment'],
    mode: ['test', 'live'],
  },
  carts: { purpose: ['accredited', 'bag'], contacted: ['true', 'false'] },
}

export const TRANSACTION_SORTS = {
  '-created_at': ['createdAt', -1],
  created_at: ['createdAt', 1],
  '-total': ['total', -1],
  total: ['total', 1],
  '-paid_at': ['paidAt', -1],
  paid_at: ['paidAt', 1],
  '-updated_at': ['updatedAt', -1],
}

function filterEntries(query) {
  const entries = []
  query.forEach((value, key) => {
    const match = /^filter\[(\w+)\]$/.exec(key)
    if (match) entries.push([match[1], value])
  })
  return entries
}

function listedValues(value) {
  return String(value).split(',')
}

function matchesList(filter, value) {
  return !filter || listedValues(filter).includes(value)
}

function checkFilter(errors, rules, key, value) {
  const rule = rules[key]
  if (rule === undefined) return errors.push(['filter', fieldMessage('invalid')])
  if (value === '') return null
  if (rule === 'date' && !isCalendarDate(value))
    return errors.push([`filter.${key}`, fieldMessage('date')])
  if (Array.isArray(rule) && !listedValues(value).every((item) => rule.includes(item)))
    return errors.push([`filter.${key}`, fieldMessage('choice')])
  return null
}

function checkPaging(errors, query) {
  const perPage = query.get('perPage')
  const page = query.get('page')
  if (
    perPage !== null &&
    !(Number.isInteger(Number(perPage)) && Number(perPage) >= 1 && Number(perPage) <= MAX_PER_PAGE)
  ) {
    errors.push(['perPage', fieldMessage('invalid')])
  }
  if (page !== null && !(Number.isInteger(Number(page)) && Number(page) >= 1))
    errors.push(['page', fieldMessage('invalid')])
}

function checkSearch(errors, query, { searchable }) {
  const search = query.get('search')
  if (search === null || search === '') return
  if (!searchable) return errors.push(['search', fieldMessage('invalid')])
  const length = normalizeSearchText(search).replace(/\s+/g, '').length
  if (length < SEARCH_MIN || search.length > SEARCH_MAX)
    errors.push(['search', fieldMessage('invalid')])
  return null
}

function checkRange(errors, filters) {
  if (filters.from && filters.to && filters.to < filters.from)
    errors.push(['filter.to', fieldMessage('date_order')])
}

function rejectFieldErrors(errors) {
  if (errors.length)
    throw financeReject.validation(
      Object.fromEntries(errors.map(([field, message]) => [field, [message]])),
    )
}

export function validatedPaging(query) {
  const errors = []
  checkPaging(errors, query)
  rejectFieldErrors(errors)
}

export function listFilters(query, kind, { searchable = false, sortable = false } = {}) {
  const rules = LIST_RULES[kind]
  const errors = []
  const filters = Object.fromEntries(filterEntries(query))
  Object.entries(filters).forEach(([key, value]) => checkFilter(errors, rules, key, value))
  checkPaging(errors, query)
  checkSearch(errors, query, { searchable })
  checkRange(errors, filters)
  const sort = query.get('sort')
  if (sort !== null && (!sortable || !TRANSACTION_SORTS[sort]))
    errors.push(['sort', fieldMessage('choice')])
  rejectFieldErrors(errors)
  return {
    ...filters,
    search: normalizeSearchText(query.get('search') ?? ''),
    sort: sort ?? '-created_at',
  }
}

function dayStart(date) {
  return new Date(`${date}T00:00:00${RIYADH_OFFSET}`).getTime()
}

export function withinDates(iso, { from, to }) {
  const at = new Date(iso).getTime()
  if (from && at < dayStart(from)) return false
  if (to && at >= dayStart(to) + DAY_MS) return false
  return true
}

function paymentMatches(payment, term) {
  if (!term) return true
  const receiptNumber = receiptOf(payment.id)?.number ?? ''
  return [
    payment.reference,
    payment.providerPaymentId ?? '',
    payment.entityName.ar,
    payment.entityName.en ?? '',
    receiptNumber,
  ].some((value) => normalizeSearchText(value).includes(term))
}

function matchesPaymentFilters(payment, filters) {
  return (
    (!filters.status || payment.status === filters.status) &&
    (!filters.purpose || payment.purpose === filters.purpose) &&
    (!filters.method || payment.method === filters.method) &&
    (!filters.channel || (payment.channel ?? 'electronic') === filters.channel) &&
    withinDates(payment.createdAt, filters)
  )
}

function compareBy([field, direction]) {
  return (left, right) => {
    const a = field === 'total' ? toMinor(left.total) : (left[field] ?? '')
    const b = field === 'total' ? toMinor(right.total) : (right[field] ?? '')
    if (a === b) return 0
    return (a < b ? -1 : 1) * direction
  }
}

export function filteredTransactions(filters, mode = filters.mode ?? 'live') {
  return financeState()
    .payments.filter((payment) => payment.mode === mode)
    .filter(
      (payment) =>
        matchesPaymentFilters(payment, filters) && paymentMatches(payment, filters.search),
    )
}

export function sortedTransactions(filters) {
  return [...filteredTransactions(filters)].sort(compareBy(TRANSACTION_SORTS[filters.sort]))
}

function sumWhere(payments, status, field) {
  return fromMinor(
    payments
      .filter((payment) => payment.status === status)
      .reduce((sum, payment) => sum + toMinor(payment[field]), 0),
  )
}

export function statsOf(payments) {
  const count = (statuses) => payments.filter((payment) => statuses.includes(payment.status)).length
  return {
    transactionsCount: payments.length,
    completedCount: count(['paid']),
    incompleteCount: count([
      'created',
      'pending',
      'under-review',
      'failed',
      'rejected',
      'cancelled',
    ]),
    refundedCount: count(['refunded']),
    totalIncome: sumWhere(payments, 'paid', 'total'),
    totalVat: sumWhere(payments, 'paid', 'vatAmount'),
    totalRefunded: sumWhere(payments, 'refunded', 'total'),
    currency: 'SAR',
  }
}

export function ownStatsOf(payments) {
  const stats = statsOf(payments.filter((payment) => payment.mode === 'live'))
  return {
    count: stats.transactionsCount,
    paidCount: stats.completedCount,
    totalPaid: stats.totalIncome,
    totalVat: stats.totalVat,
    currency: stats.currency,
  }
}

export function ownPayments(user, filters) {
  return financeState()
    .payments.filter(
      (payment) => user.entityId !== null && payment.entityId === Number(user.entityId),
    )
    .filter(
      (payment) =>
        matchesList(filters.status, payment.status) &&
        (!filters.purpose || payment.purpose === filters.purpose),
    )
    .sort((left, right) => right.createdAt.localeCompare(left.createdAt))
}

function countKeyOf(status) {
  return status.replace(/-([a-z])/g, (match, letter) => letter.toUpperCase())
}

export function ownStatusCountsOf(payments) {
  return Object.fromEntries(
    PAYMENT_STATUS_VALUES.map((status) => [
      countKeyOf(status),
      payments.filter((payment) => payment.status === status).length,
    ]),
  )
}

export function page(items, query) {
  return paginate(items, query, 15)
}
