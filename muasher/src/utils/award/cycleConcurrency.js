import { CYCLE_CONCURRENCY } from '@/constants/advanced'

const LIMITS = {
  [CYCLE_CONCURRENCY.SINGLE]: (counts) => counts.running > 1,
  [CYCLE_CONCURRENCY.STAGGERED]: (counts) => counts.openForSubmission > 1,
}

export function exceedsConcurrency(mode, runningCycles) {
  if (!runningCycles) return false
  return LIMITS[mode]?.(runningCycles) ?? false
}
