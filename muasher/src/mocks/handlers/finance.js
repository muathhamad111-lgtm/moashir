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
import { financeReject, financeRoute, readJson } from '../stores/finance/financeRejection'
import {
  filteredTransactions,
  listFilters,
  page,
  sortedTransactions,
  statsOf,
} from '../stores/finance/financeQueries'
import {
  abandonedCartResource,
  financeExportResource,
  paymentEventResource,
  receiptResource,
  refundResource,
  transactionDetailResource,
  transactionEventResource,
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
const TRANSACTION_EVENTS_LIMIT = 50
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
    '/api/v1/finance/transactions',
    financeRoute(({ request }) => {
      requirePermission(signedInActor(), FINANCE_PERMISSIONS.OPERATIONS_VIEW)
      unavailableSometimes()
      const query = queryOf(request)
      const filters = listFilters(query, 'transactions', { searchable: true, sortable: true })
      return listResponse(sortedTransactions(filters), query, transactionResource, {
        stats: statsOf(filteredTransactions(filters)),
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
    '/api/v1/finance/transactions/:payment/events',
    financeRoute(({ params }) => {
      const payment = boundPayment(params)
      requirePermission(signedInActor(), FINANCE_PERMISSIONS.OPERATIONS_VIEW)
      const events = eventsOf({}).filter((event) => event.paymentId === payment.id)
      return envelope(events.slice(0, TRANSACTION_EVENTS_LIMIT).map(transactionEventResource), {
        limit: TRANSACTION_EVENTS_LIMIT,
        hasMore: events.length > TRANSACTION_EVENTS_LIMIT,
      })
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
