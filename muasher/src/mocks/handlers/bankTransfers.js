import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import {
  actorCan,
  requireAnyPermission,
  requirePermission,
  signedInActor,
} from '../stores/finance/financeAccess'
import { findFile, transferOf } from '../stores/finance/financeState'
import { financeReject, financeRoute, readJson } from '../stores/finance/financeRejection'
import { page } from '../stores/finance/financeQueries'
import { signedDownloadOf } from '../stores/finance/signedFiles'
import { awaitingReviewCount, staffTransferResource } from '../stores/finance/bankTransferRecords'
import {
  approveTransfer,
  decideEach,
  queueFilters,
  queueOf,
  rejectTransfer,
  reviewablePayment,
  validatedIds,
  validatedReason,
  validatedRejection,
} from '../stores/finance/bankTransferReview'

const BASE = '/api/v1/finance/bank-transfers'
const REVIEW = 'finance.transfers.review'
const OPERATIONS = 'finance.operations.view'

function viewer() {
  const user = signedInActor()
  requireAnyPermission(user, [REVIEW, OPERATIONS])
  return user
}

function reviewer() {
  const user = signedInActor()
  requirePermission(user, REVIEW)
  return user
}

function cleanProofOf(payment) {
  const file = findFile(transferOf(payment.id).proofFileId)
  if (!file || file.scanStatus !== 'clean') throw financeReject.fileNotClean()
  return file
}

async function decisionInput(request) {
  const user = reviewer()
  const body = await readJson(request)
  return { user, body, reason: validatedReason(user, body) }
}

export function reviewerActionItem(user) {
  if (!actorCan(user, REVIEW)) return null
  const count = awaitingReviewCount()
  if (!count) return null
  return {
    key: 'review_bank_transfers',
    count,
    severity: 'warning',
    link: '/finance/transfers',
    dueAt: null,
    context: null,
  }
}

export const bankTransfersHandlers = [
  http.get(
    BASE,
    financeRoute(({ request }) => {
      viewer()
      const query = new URL(request.url).searchParams
      const { items, pagination } = page(queueOf(queueFilters(query)), query)
      return envelope(
        items.map((payment) => staffTransferResource(payment)),
        { pagination, counts: { underReview: awaitingReviewCount() } },
      )
    }),
  ),

  http.post(
    `${BASE}/bulk-approve`,
    financeRoute(async ({ request }) => {
      const { user, body } = await decisionInput(request)
      const ids = validatedIds(body)
      return envelope(decideEach(ids, (payment) => approveTransfer(user, payment)))
    }),
  ),

  http.post(
    `${BASE}/bulk-reject`,
    financeRoute(async ({ request }) => {
      const { user, body } = await decisionInput(request)
      const ids = validatedIds(body)
      const rejection = validatedRejection(body)
      return envelope(decideEach(ids, (payment) => rejectTransfer(user, payment, rejection)))
    }),
  ),

  http.get(
    `${BASE}/:payment`,
    financeRoute(({ params }) => {
      viewer()
      return envelope(staffTransferResource(reviewablePayment(params.payment), { detail: true }))
    }),
  ),

  http.post(
    `${BASE}/:payment/proof/download`,
    financeRoute(({ params }) => {
      const user = viewer()
      const payment = reviewablePayment(params.payment)
      return envelope(signedDownloadOf(user, cleanProofOf(payment)))
    }),
  ),

  http.post(
    `${BASE}/:payment/approve`,
    financeRoute(async ({ params, request }) => {
      const payment = reviewablePayment(params.payment)
      const { user } = await decisionInput(request)
      return envelope(staffTransferResource(approveTransfer(user, payment), { detail: true }))
    }),
  ),

  http.post(
    `${BASE}/:payment/reject`,
    financeRoute(async ({ params, request }) => {
      const payment = reviewablePayment(params.payment)
      const { user, body } = await decisionInput(request)
      const rejection = validatedRejection(body)
      return envelope(
        staffTransferResource(rejectTransfer(user, payment, rejection), { detail: true }),
      )
    }),
  ),
]
