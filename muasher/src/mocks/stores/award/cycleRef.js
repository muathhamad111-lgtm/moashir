import { findCycle } from './cycleStore'

export function cycleRefOf(cycleId) {
  const cycle = cycleId ? findCycle(cycleId) : null
  return cycle ? { id: cycle.id, name: { ...cycle.name } } : null
}
