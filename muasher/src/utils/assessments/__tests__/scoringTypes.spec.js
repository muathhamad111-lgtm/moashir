import { describe, expect, it } from 'vitest'
import {
  allowsRawValue,
  isAchievement,
  maximumOf,
  unitScoringType,
} from '@/utils/assessments/scoringTypes'
import { scoreSheetSchema } from '@/utils/assessments/scoreSheetSchema'

const t = (key, params) => `${key}${params ? JSON.stringify(params) : ''}`

describe('scoringTypes', () => {
  it('uses the unit type for an unstamped request and the stamp for a legacy one', () => {
    const point = { scoringType: 'achievement' }
    expect(unitScoringType(point, null)).toBe('achievement')
    expect(unitScoringType(point, { kind: 'of-10' })).toBe('score')
    expect(unitScoringType(point, { kind: 'relative' })).toBe('relative')
    expect(unitScoringType({}, null)).toBe('score')
  })

  it('knows the maximum and the allowed values of each type', () => {
    expect(maximumOf('relative')).toBe('100.00')
    expect(maximumOf('score')).toBe('10.00')
    expect(isAchievement('achievement')).toBe(true)
    expect(allowsRawValue('87.45', 'relative')).toBe(true)
    expect(allowsRawValue('10.5', 'score')).toBe(false)
    expect(allowsRawValue('1', 'achievement')).toBe(true)
    expect(allowsRawValue('0.00', 'achievement')).toBe(true)
    expect(allowsRawValue('0.5', 'achievement')).toBe(false)
  })

  it('validates each unit of a sheet against its own type', async () => {
    const units = [
      { level: 'point', id: 1, scoringType: 'achievement' },
      { level: 'point', id: 2, scoringType: 'relative' },
    ]
    const schema = scoreSheetSchema(t, units, null)
    await expect(schema.validate({ 'point-1': '1', 'point-2': '87' })).resolves.toBeTruthy()
    await expect(schema.validate({ 'point-1': '0.5' })).rejects.toThrow(
      'assessment.self.validation.achievement',
    )
    await expect(schema.validate({ 'point-2': '120' })).rejects.toThrow('"max":"100"')
  })
})
