import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { signedInActor } from '../stores/finance/financeAccess'
import { financeRoute, readJson } from '../stores/finance/financeRejection'
import {
  authorizePrices,
  cancelScheduledPrice,
  knownPriceKey,
  priceHistory,
  priceRowResource,
  pricesSettingsResource,
  writePrice,
} from '../stores/finance/pricesSettings'

const ROW = '/api/v1/settings/prices/:key'

function authorizedKey(params) {
  authorizePrices(signedInActor())
  return knownPriceKey(params.key)
}

export const pricesSettingsHandlers = [
  http.get(
    '/api/v1/settings/prices',
    financeRoute(() => {
      authorizePrices(signedInActor())
      return envelope(pricesSettingsResource())
    }),
  ),

  http.get(
    `${ROW}/history`,
    financeRoute(({ params, request }) => {
      const key = authorizedKey(params)
      const { items, pagination } = priceHistory(key, new URL(request.url).searchParams)
      return envelope(items, { pagination })
    }),
  ),

  http.put(
    ROW,
    financeRoute(async ({ params, request }) => {
      const key = authorizedKey(params)
      const meta = writePrice(signedInActor(), key, await readJson(request))
      return envelope(priceRowResource(key), meta)
    }),
  ),

  http.delete(
    `${ROW}/scheduled`,
    financeRoute(async ({ params, request }) => {
      const key = authorizedKey(params)
      cancelScheduledPrice(signedInActor(), key, await readJson(request))
      return envelope(priceRowResource(key))
    }),
  ),
]
