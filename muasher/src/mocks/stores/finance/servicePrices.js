import { awardPricing } from '../award/awardPricing'
import { pathPricingOf, priceInForce, quoteOfPrice } from './priceVersions'

function quoteWhenPriced(key) {
  const price = priceInForce(key)
  return price && Number(price) > 0 ? quoteOfPrice(price) : null
}

export function pricesResource() {
  return {
    accredited: quoteWhenPriced('accredited'),
    bag: quoteWhenPriced('bag'),
    paths: {
      self: pathPricingOf('self'),
      accredited: pathPricingOf('accredited'),
      award: awardPricing(),
      bag: pathPricingOf('bag'),
    },
  }
}
