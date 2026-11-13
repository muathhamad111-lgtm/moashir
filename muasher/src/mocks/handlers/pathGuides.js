import { delay } from 'msw'
import { http } from '../contract/http'
import { envelope, errorResponse } from '../contract/responses'
import { PATH_GUIDES_FIXTURE } from '../fixtures/pathGuides'
import { pathTextsOf } from '../stores/accredited/pathSettingsStore'
import { pathPricingOf } from '../stores/finance/priceVersions'
import { awardPricing } from '../stores/award/awardPricing'

const GUIDE_PRICING = { accredited: () => pathPricingOf('accredited'), advanced: awardPricing }

function priceFieldsOf(path) {
  const pricing = GUIDE_PRICING[path]?.()
  return pricing ? { isPaid: Boolean(pricing.price), price: pricing.price } : {}
}

const GUIDE_LATENCY_MS = 150

export const pathGuidesHandlers = [
  http.get('/api/v1/content/assessment-paths/:path', async ({ params }) => {
    await delay(GUIDE_LATENCY_MS)
    const guide = PATH_GUIDES_FIXTURE[params.path]
    if (!guide) return errorResponse(404, 'not_found', 'Not found.')
    return envelope({
      ...guide,
      ...priceFieldsOf(params.path),
      entityTexts: pathTextsOf(params.path),
    })
  }),
]
