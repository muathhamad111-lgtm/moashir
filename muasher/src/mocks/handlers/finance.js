import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { failsRandomly } from '../contract/faults'
import {
  FINANCE_PERMISSIONS,
  authorizeExport,
  authorizeManualSettle,
  authorizeReceiptDownload,
  authorizeRefundRead,
  requirePermission,
  signedInActor,
} from '../stores/finance/financeAccess'
import {
  financeState,
  findExport,
  findFile,
  findPayment,
  findReceipt,
  findRefund,
} from '../stores/finance/financeState'
import {
  fieldMessage,
  financeReject,
  financeRoute,
  readJson,
} from '../stores/finance/financeRejection'
import {
  filteredTransactions,
  isCalendarDate,
  listFilters,
  page,
  sortedTransactions,
  statsOf,
  withinDates,
} from '../stores/finance/financeQueries'
import {
  abandonedCartResource,
  financeExportResource,
  paymentEventResource,
  receiptResource,
  refundResource,
  transactionDetailResource,
  transactionResource,
} from '../stores/finance/financeSerializers'
import { exportTransactions, progressed, readyExportFile } from '../stores/finance/financeExports'
import { recordRefund, validatedRefund } from '../stores/finance/refundRecording'
import {
  reissueReceipt,
  receiptFileOf,
  validatedReissueReason,
} from '../stores/finance/receiptOperations'
import { settleManually, validatedSettlement } from '../stores/finance/manualSettlement'
import { signedDownloadOf } from '../stores/finance/signedFiles'
import { normalizeSearchText } from '../stores/searchIndex'
import { boundPayment } from './payments'

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i
const PURPOSES = ['accredited', 'bag', 'self', 'award']
const CHANNELS = ['electronic', 'bank_transfer']
const failsToList = failsRandomly(0.04)

function found(record) {
  if (!record) throw financeReject.notFound()
  return record
}

function byUuid(value, finder) {
  return found(UUID.test(value) ? finder(value) : null)
}

function queryOf(request) {
  return new URL(request.url).searchParams
}

function rangeErrors(range) {
  const errors = Object.entries(range)
    .filter(([, value]) => value && !isCalendarDate(value))
    .map(([field]) => [field, [fieldMessage('date')]])
  if (!errors.length && range.from && range.to && range.to < range.from)
    errors.push(['to', [fieldMessage('date_order')]])
  return errors
}

function summaryRange(query) {
  const range = { from: query.get('from') ?? '', to: query.get('to') ?? '' }
  const errors = rangeErrors(range)
  if (errors.length) throw financeReject.validation(Object.fromEntries(errors))
  return range
}

function summaryOf(range) {
  const payments = financeState().payments.filter(
    (payment) => payment.mode === 'live' && withinDates(payment.createdAt, range),
  )
  return {
    ...statsOf(payments),
    byPurpose: PURPOSES.map((purpose) => ({
      purpose,
      ...statsOf(payments.filter((payment) => payment.purpose === purpose)),
    })),
    byChannel: CHANNELS.map((channel) => ({
      channel,
      ...statsOf(payments.filter((payment) => (payment.channel ?? 'electronic') === channel)),
    })),
  }
}

function unavailableSometimes() {
  if (failsToList()) throw financeReject.providerUnavailable()
}

function eventMatches(event, term) {
  if (!term) return true
  const reference = event.paymentId ? findPayment(event.paymentId)?.reference : ''
  return [reference ?? '', event.providerPaymentId ?? ''].some((value) =>
    normalizeSearchText(value).includes(term),
  )
}

function eventsOf(filters) {
  return financeState()
    .events.filter(
      (event) =>
        (!filters.source || event.source === filters.source) &&
        (!filters.result || event.result === filters.result),
    )
    .filter(
      (event) =>
        (!filters.mode || event.mode === filters.mode) && eventMatches(event, filters.search),
    )
    .sort((left, right) => right.createdAt.localeCompare(left.createdAt) || right.id - left.id)
}

function refundsOf(filters) {
  return financeState()
    .refunds.filter((refund) => withinDates(`${refund.refundedOn}T12:00:00+03:00`, filters))
    .filter(
      (refund) => !filters.purpose || findPayment(refund.paymentId).purpose === filters.purpose,
    )
    .sort((left, right) => right.createdAt.localeCompare(left.createdAt))
}

function cartMatches(cart, term) {
  if (!term) return true
  const { entity } = abandonedCartResource(cart)
  return [cart.email, entity.name.ar, entity.name.en ?? ''].some((value) =>
    normalizeSearchText(value).includes(term),
  )
}

function cartsOf(filters) {
  return financeState()
    .carts.filter((cart) => !filters.purpose || cart.purpose === filters.purpose)
    .filter(
      (cart) =>
        !filters.contacted || (filters.contacted === 'true') === (cart.contactedAt !== null),
    )
    .filter((cart) => cartMatches(cart, filters.search))
    .sort((left, right) => right.lastSeenAt.localeCompare(left.lastSeenAt))
}

function listResponse(items, query, serializer, meta = {}) {
  const { items: pageItems, pagination } = page(items, query)
  return envelope(pageItems.map(serializer), { pagination, ...meta })
}

export const financeHandlers = [
  http.get(
    '/api/v1/finance/summary',
    financeRoute(({ request }) => {
      requirePermission(signedInActor(), FINANCE_PERMISSIONS.OPERATIONS_VIEW)
      return envelope(summaryOf(summaryRange(queryOf(request))), { liveOnly: true })
    }),
  ),

  http.get(
    '/api/v1/finance/transactions',
    financeRoute(({ request }) => {
      requirePermission(signedInActor(), FINANCE_PERMISSIONS.OPERATIONS_VIEW)
      unavailableSometimes()
      const query = queryOf(request)
      const filters = listFilters(query, 'transactions', { searchable: true, sortable: true })
      return listResponse(sortedTransactions(filters), query, transactionResource, {
        stats: statsOf(filteredTransactions(filters, 'live')),
      })
    }),
  ),

  http.post(
    '/api/v1/finance/transactions/export',
    financeRoute(({ request }) => {
      const user = signedInActor()
      requirePermission(user, FINANCE_PERMISSIONS.OPERATIONS_EXPORT)
      const filters = listFilters(queryOf(request), 'transactions', {
        searchable: true,
        sortable: true,
      })
      const result = exportTransactions(user, filters, request)
      return result.stream ?? envelope(financeExportResource(result.queued), {}, { status: 202 })
    }),
  ),

  http.get(
    '/api/v1/finance/transactions/:payment',
    financeRoute(({ params }) => {
      const payment = boundPayment(params)
      requirePermission(signedInActor(), FINANCE_PERMISSIONS.OPERATIONS_VIEW)
      return envelope(transactionDetailResource(payment))
    }),
  ),

  http.get(
    '/api/v1/finance/payment-events',
    financeRoute(({ request }) => {
      requirePermission(signedInActor(), FINANCE_PERMISSIONS.EVENTS_VIEW)
      const query = queryOf(request)
      return listResponse(
        eventsOf(listFilters(query, 'events', { searchable: true })),
        query,
        paymentEventResource,
      )
    }),
  ),

  http.post(
    '/api/v1/finance/payments/:payment/refunds',
    financeRoute(async ({ params, request }) => {
      const payment = boundPayment(params)
      const user = signedInActor()
      requirePermission(user, FINANCE_PERMISSIONS.REFUNDS_MANAGE)
      const instruction = validatedRefund(await request.formData())
      return envelope(
        refundResource(await recordRefund(user, payment, instruction)),
        {},
        { status: 201 },
      )
    }),
  ),

  http.post(
    '/api/v1/finance/payments/:payment/manual-settle',
    financeRoute(async ({ params, request }) => {
      const payment = boundPayment(params)
      authorizeManualSettle(signedInActor())
      const settlement = validatedSettlement(await readJson(request))
      return envelope(transactionDetailResource(settleManually(payment, settlement)))
    }),
  ),

  http.get(
    '/api/v1/finance/refunds',
    financeRoute(({ request }) => {
      authorizeRefundRead(signedInActor())
      const query = queryOf(request)
      return listResponse(refundsOf(listFilters(query, 'refunds')), query, refundResource)
    }),
  ),

  http.get(
    '/api/v1/finance/refunds/:refund',
    financeRoute(({ params }) => {
      const refund = byUuid(params.refund, findRefund)
      authorizeRefundRead(signedInActor())
      return envelope(refundResource(refund))
    }),
  ),

  http.post(
    '/api/v1/finance/refunds/:refund/evidence/download',
    financeRoute(({ params }) => {
      const refund = byUuid(params.refund, findRefund)
      const user = signedInActor()
      authorizeRefundRead(user)
      return envelope(signedDownloadOf(user, found(findFile(refund.evidenceFileId))))
    }),
  ),

  http.post(
    '/api/v1/finance/receipts/:receipt/download',
    financeRoute(({ params }) => {
      const receipt = byUuid(params.receipt, findReceipt)
      const user = signedInActor()
      authorizeReceiptDownload(user, findPayment(receipt.paymentId))
      return envelope(signedDownloadOf(user, receiptFileOf(receipt)))
    }),
  ),

  http.post(
    '/api/v1/finance/receipts/:receipt/reissue',
    financeRoute(async ({ params, request }) => {
      const receipt = byUuid(params.receipt, findReceipt)
      requirePermission(signedInActor(), FINANCE_PERMISSIONS.RECEIPTS_MANAGE)
      validatedReissueReason(await readJson(request))
      return envelope(receiptResource(reissueReceipt(receipt)))
    }),
  ),

  http.get(
    '/api/v1/finance/exports/:export',
    financeRoute(({ params }) => {
      const record = byUuid(params.export, findExport)
      authorizeExport(signedInActor(), record)
      return envelope(financeExportResource(progressed(record)))
    }),
  ),

  http.post(
    '/api/v1/finance/exports/:export/download',
    financeRoute(({ params }) => {
      const record = byUuid(params.export, findExport)
      const user = signedInActor()
      authorizeExport(user, record)
      return envelope(signedDownloadOf(user, found(findFile(readyExportFile(record)))))
    }),
  ),

  http.get(
    '/api/v1/messaging/abandoned-carts',
    financeRoute(({ request }) => {
      requirePermission(signedInActor(), FINANCE_PERMISSIONS.CARTS_VIEW)
      const query = queryOf(request)
      return listResponse(
        cartsOf(listFilters(query, 'carts', { searchable: true })),
        query,
        abandonedCartResource,
      )
    }),
  ),
]
