import { acceptsApplications, covers, runningCycle } from './cycleStore'

const RIYADH_OFFSET_MS = 3 * 60 * 60 * 1000

function riyadhYear(now) {
  return new Date(now + RIYADH_OFFSET_MS).getUTCFullYear()
}

export function entityAge(entity, now = Date.now()) {
  return riyadhYear(now) - Number(entity.foundingYear)
}

function checksOf(entity, cycle, now) {
  const age = entityAge(entity, now)
  return [
    {
      rule: 'min_entity_age',
      passed: age >= cycle.minEntityAgeYears,
      required: cycle.minEntityAgeYears,
      actual: age,
    },
    { rule: 'region', passed: covers(cycle, entity.regionId), required: null, actual: null },
  ]
}

export function eligibilityOf(entity, cycle = runningCycle(), now = Date.now()) {
  if (!cycle) return { cycle: null, windowOpen: false, checks: [] }
  return {
    cycle,
    windowOpen: acceptsApplications(cycle, now),
    checks: checksOf(entity, cycle, now),
  }
}

export function failuresOf(eligibility) {
  return eligibility.checks.filter((check) => !check.passed)
}

export function isEligible(eligibility) {
  return (
    Boolean(eligibility.cycle) && eligibility.windowOpen && failuresOf(eligibility).length === 0
  )
}

export function eligibilityResource(eligibility) {
  return {
    eligible: isEligible(eligibility),
    windowOpen: eligibility.windowOpen,
    conditions: eligibility.checks.map((check) => ({ ...check })),
  }
}
