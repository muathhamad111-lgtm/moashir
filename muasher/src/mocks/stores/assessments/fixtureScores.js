import { SELF_RAW_PATTERNS } from '../../fixtures/assessments'
import { parseRawHundredths, scorableUnits, unitKey } from './selfScoring'

export function fixtureScores(tree, { pattern, count }) {
  const values = SELF_RAW_PATTERNS[pattern]
  const units = scorableUnits(tree)
  const scored = count === null ? units : units.slice(0, count)
  return Object.fromEntries(
    scored.map((unit, index) => [unitKey(unit.level, unit.id), parseRawHundredths(values[index % values.length])]),
  )
}
