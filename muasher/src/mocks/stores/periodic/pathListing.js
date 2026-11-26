const HIDDEN = 'hidden'

export function isListed(pricing, hasRequests, openOrder) {
  return pricing.availability !== HIDDEN || hasRequests || Boolean(openOrder)
}
