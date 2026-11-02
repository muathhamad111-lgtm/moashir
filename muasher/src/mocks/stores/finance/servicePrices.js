import { financeState } from './financeState'
import { quoteOf } from './financeMoney'

const PRICE_KEYS = { accredited: 'accreditedPrice', bag: 'bagPrice' }

function priceOf(purpose) {
  const settings = financeState().settings
  const price = settings[PRICE_KEYS[purpose]]
  return price && Number(price) > 0 ? quoteOf(price, settings.vatRate) : null
}

export function pricesResource() {
  return { accredited: priceOf('accredited'), bag: priceOf('bag') }
}
