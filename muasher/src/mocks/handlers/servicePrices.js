import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope } from '../contract/responses'
import { pricesResource } from '../stores/finance/servicePrices'

const PRICES_LATENCY_MS = 120

export const servicePricesHandlers = [
  http.get('/api/v1/content/prices', async () => {
    await delay(PRICES_LATENCY_MS)
    return envelope(pricesResource())
  }),
]
