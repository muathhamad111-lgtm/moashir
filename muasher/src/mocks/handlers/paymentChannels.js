import { http } from '../contract/http'
import { envelope, noContent } from '../contract/responses'
import { requirePermission, signedInActor } from '../stores/finance/financeAccess'
import { financeReject, financeRoute, readJson } from '../stores/finance/financeRejection'
import {
  activateAccount,
  bankAccountResource,
  createBankAccount,
  deactivateAccount,
  deleteAccount,
  listBankAccounts,
  listedAccount,
  reorderAccounts,
  updateAccountNote,
  validatedNewAccount,
} from '../stores/finance/bankAccountsStore'
import {
  paymentChannelsResource,
  updateChannels,
  validatedChannels,
} from '../stores/finance/paymentChannelsStore'
import { validatedReason } from '../stores/finance/bankTransferReview'
import { page } from '../stores/finance/financeQueries'

const MANAGE = 'settings.payment.manage'
const BASE = '/api/v1/settings/payment'
const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i

function manager() {
  const user = signedInActor()
  requirePermission(user, MANAGE)
  return user
}

function accountOf(params) {
  const account = UUID.test(params.account) ? listedAccount(params.account) : null
  if (!account) throw financeReject.notFound()
  return account
}

async function managedWrite(request) {
  const user = manager()
  const body = await readJson(request)
  validatedReason(user, body)
  return { user, body }
}

function accountsQuery(request) {
  const query = new URL(request.url).searchParams
  return { query, active: query.get('filter[active]') ?? '' }
}

export const paymentChannelsHandlers = [
  http.get(
    `${BASE}/channels`,
    financeRoute(() => {
      manager()
      return envelope(paymentChannelsResource())
    }),
  ),

  http.put(
    `${BASE}/channels`,
    financeRoute(async ({ request }) => {
      const { body } = await managedWrite(request)
      updateChannels(validatedChannels(body))
      return envelope(paymentChannelsResource())
    }),
  ),

  http.get(
    `${BASE}/bank-accounts`,
    financeRoute(({ request }) => {
      manager()
      const { query, active } = accountsQuery(request)
      const { items, pagination } = page(listBankAccounts({ active }), query)
      return envelope(items.map(bankAccountResource), { pagination })
    }),
  ),

  http.post(
    `${BASE}/bank-accounts`,
    financeRoute(async ({ request }) => {
      const { user, body } = await managedWrite(request)
      const account = createBankAccount(user, validatedNewAccount(body))
      return envelope(bankAccountResource(account), {}, { status: 201 })
    }),
  ),

  http.put(
    `${BASE}/bank-accounts/order`,
    financeRoute(async ({ request }) => {
      const { body } = await managedWrite(request)
      return envelope(reorderAccounts(body).map(bankAccountResource))
    }),
  ),

  http.patch(
    `${BASE}/bank-accounts/:account`,
    financeRoute(async ({ params, request }) => {
      const account = accountOf(params)
      const { body } = await managedWrite(request)
      return envelope(bankAccountResource(updateAccountNote(account, body)))
    }),
  ),

  http.post(
    `${BASE}/bank-accounts/:account/deactivate`,
    financeRoute(async ({ params, request }) => {
      const account = accountOf(params)
      await managedWrite(request)
      return envelope(bankAccountResource(deactivateAccount(account)))
    }),
  ),

  http.post(
    `${BASE}/bank-accounts/:account/activate`,
    financeRoute(async ({ params, request }) => {
      const account = accountOf(params)
      await managedWrite(request)
      return envelope(bankAccountResource(activateAccount(account)))
    }),
  ),

  http.delete(
    `${BASE}/bank-accounts/:account`,
    financeRoute(async ({ params, request }) => {
      const account = accountOf(params)
      await managedWrite(request)
      deleteAccount(account)
      return noContent()
    }),
  ),
]
