import { pathPricingOf, priceInForce, pricingOfPrice } from '../finance/priceVersions'
import { acceptsApplications, allCycles, runningCycle } from './cycleStore'

const NOT_YET_OPEN = ['draft']
const FREE_FEE = '0.00'
const CYCLE_FEE_STATES = ['open', 'upcoming']

export function windowStateOf(cycle, now = Date.now()) {
  if (!cycle) return 'none'
  if (acceptsApplications(cycle, now)) return 'open'
  return NOT_YET_OPEN.includes(cycle.status) ? 'upcoming' : 'closed'
}

export function entryFeeOf(cycle) {
  return cycle?.entryFee ?? FREE_FEE
}

export function nextCycleOf(cycle, now = Date.now()) {
  return (
    allCycles()
      .filter(
        (candidate) =>
          candidate.id !== cycle?.id &&
          NOT_YET_OPEN.includes(candidate.status) &&
          new Date(candidate.submissionStart).getTime() > now,
      )
      .sort((left, right) => (left.submissionStart < right.submissionStart ? -1 : 1))[0] ?? null
  )
}

export function awardAvailability() {
  return pathPricingOf('award').availability
}

function appliedFee(cycle, windowState, next) {
  if (CYCLE_FEE_STATES.includes(windowState)) return entryFeeOf(cycle)
  if (next) return entryFeeOf(next)
  return priceInForce('award') ?? FREE_FEE
}

export function awardPricingOf(cycle, now = Date.now()) {
  const windowState = windowStateOf(cycle, now)
  const next = windowState === 'closed' ? nextCycleOf(cycle, now) : null
  return pricingOfPrice(appliedFee(cycle, windowState, next), awardAvailability())
}

export function awardPricing(now = Date.now()) {
  return awardPricingOf(runningCycle(), now)
}

export function cycleFeePricing(cycle) {
  return pricingOfPrice(entryFeeOf(cycle), awardAvailability())
}
