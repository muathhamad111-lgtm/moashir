import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { requestLocale } from '../contract/locale'
import {
  authorizeCreate,
  authorizeOwnList,
  authorizeVerify,
  authorizeViewOwn,
  signedInActor,
} from '../stores/finance/financeAccess'
import { findPayment, receiptOf } from '../stores/finance/financeState'
import { financeReject, financeRoute, readJson } from '../stores/finance/financeRejection'
import {
  listFilters,
  ownPayments,
  ownStatsOf,
  ownStatusCountsOf,
  page,
} from '../stores/finance/financeQueries'
import { ownPaymentResource, receiptResource } from '../stores/finance/financeSerializers'
import { checkoutOf } from '../stores/finance/checkoutObject'
import { expireDueOrders } from '../stores/finance/paymentLifecycle'
import {
  assertLinkedPath,
  authorizeKnownPurpose,
  gatewayMode,
  idempotencyKeyOf,
  openOrder,
  openOrderOf,
  payingEntity,
  quoteFor,
  replayOf,
  validatedPurpose,
} from '../stores/finance/paymentOrders'
import {
  VERIFICATION,
  validatedProviderId,
  verifyPayment,
} from '../stores/finance/paymentVerification'
import { receiptFileOf } from '../stores/finance/receiptOperations'
import { fileDownloadResponse, signedDownloadOf } from '../stores/finance/signedFiles'
import { assertMayPurchase } from '../stores/finance/paymentHooks'
import { recordOfPayment } from '../stores/accredited/accreditedRecords'

const ACCREDITED_LABEL = { ar: 'التقييم المعتمد', en: 'Accredited assessment' }

function openedRequestOf(payment) {
  const record = payment.status === 'paid' ? recordOfPayment(payment.id) : null
  return record
    ? { id: record.id, number: record.number, path: record.path, pathLabel: ACCREDITED_LABEL }
    : null
}

function ownPaymentWithRequest(payment) {
  return { ...ownPaymentResource(payment), request: openedRequestOf(payment) }
}

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i

export function boundPayment(params) {
  const payment = UUID.test(params.payment) ? findPayment(params.payment) : null
  if (!payment) throw financeReject.notFound()
  return payment
}

function createdResponse(payment, request, { replay = false, existing = false } = {}) {
  return envelope(
    {
      ...ownPaymentResource(payment),
      request: null,
      checkout: checkoutOf(payment, requestLocale(request)),
    },
    { idempotentReplay: replay, existingOrder: existing },
    { status: existing ? 200 : 201 },
  )
}

async function createPayment({ request }) {
  const user = signedInActor()
  authorizeCreate(user)
  const key = idempotencyKeyOf(request)
  const body = await readJson(request)
  authorizeKnownPurpose(user, body)
  const purpose = validatedPurpose(body)
  expireDueOrders()
  const replay = replayOf(user, purpose, key)
  if (replay) return createdResponse(replay, request, { replay: true })
  const entity = payingEntity(user)
  const mode = gatewayMode()
  assertLinkedPath(purpose)
  const quote = quoteFor(purpose)
  const existing = openOrderOf(entity.id, purpose)
  if (existing) return createdResponse(existing, request, { existing: true })
  assertMayPurchase(entity, purpose)
  return createdResponse(openOrder({ user, entity, purpose, quote, mode, key }), request)
}

function issuedReceipt(payment) {
  const receipt = receiptOf(payment.id)
  if (!receipt) throw financeReject.receiptNotIssued()
  return receipt
}

export const paymentsHandlers = [
  http.get(
    '/api/v1/payments/mine',
    financeRoute(({ request }) => {
      const user = signedInActor()
      authorizeOwnList(user)
      const query = new URL(request.url).searchParams
      expireDueOrders()
      const filters = listFilters(query, 'mine')
      const payments = ownPayments(user, filters)
      const counted = ownPayments(user, { ...filters, status: '' })
      const { items, pagination } = page(payments, query)
      return envelope(items.map(ownPaymentWithRequest), {
        pagination,
        stats: ownStatsOf(payments),
        statusCounts: ownStatusCountsOf(counted),
      })
    }),
  ),

  http.post('/api/v1/payments', financeRoute(createPayment)),

  http.get(
    '/api/v1/payments/:payment',
    financeRoute(({ params }) => {
      const user = signedInActor()
      const payment = boundPayment(params)
      authorizeViewOwn(user, payment)
      expireDueOrders()
      return envelope(ownPaymentWithRequest(payment))
    }),
  ),

  http.post(
    '/api/v1/payments/:payment/verify',
    financeRoute(async ({ params, request }) => {
      const user = signedInActor()
      const payment = boundPayment(params)
      authorizeVerify(user, payment)
      const providerPaymentId = validatedProviderId(await readJson(request))
      expireDueOrders()
      const verification = verifyPayment(payment, providerPaymentId)
      const status = verification === VERIFICATION.PENDING ? 202 : 200
      return envelope(ownPaymentResource(payment), { verification }, { status })
    }),
  ),

  http.get(
    '/api/v1/payments/:payment/receipt',
    financeRoute(({ params }) => {
      const user = signedInActor()
      const payment = boundPayment(params)
      authorizeViewOwn(user, payment)
      return envelope(receiptResource(issuedReceipt(payment)))
    }),
  ),

  http.post(
    '/api/v1/payments/:payment/receipt/download',
    financeRoute(({ params }) => {
      const user = signedInActor()
      const payment = boundPayment(params)
      authorizeViewOwn(user, payment)
      return envelope(signedDownloadOf(user, receiptFileOf(issuedReceipt(payment))))
    }),
  ),

  http.get(
    '/api/v1/files/:file/download',
    financeRoute(({ request }) => fileDownloadResponse(request, signedInActor())),
  ),
]
