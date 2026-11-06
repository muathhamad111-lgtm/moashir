import { describe, expect, it } from 'vitest'
import {
  fieldNameOfServerPath,
  isRawScore,
  sameRawValue,
  savedScoreOf,
  scorableUnits,
  scoreFieldName,
  sheetFieldErrors,
  sheetPayload,
  sheetValues,
  unscoredUnits,
  withinScale,
} from '@/utils/assessments/scoreSheet'
import { scoreSheetSchema } from '@/utils/assessments/scoreSheetSchema'
import { criteriaTree } from './fixtures'

const units = scorableUnits(criteriaTree)
const t = (key, params) => (params ? `${key}:${JSON.stringify(params)}` : key)

describe('scorable units of the bound tree', () => {
  it('scores guiding points instead of their sub and direct subs themselves, in tree order', () => {
    expect(units.map((unit) => [unit.level, unit.id, unit.code])).toEqual([
      ['point', 1001, 'M1.1.1'],
      ['point', 1002, 'M1.1.2'],
      ['sub', 102, 'M1.2'],
    ])
    expect(units[0]).toMatchObject({
      mainId: 1,
      subId: 101,
      label: { en: 'An approved vision document' },
    })
    expect(scoreFieldName(units[2])).toBe('sub-102')
  })

  it('returns no unit without a tree', () => {
    expect(scorableUnits(null)).toEqual([])
  })
})

describe('sheet values and the complete PUT sheet', () => {
  const scores = {
    subs: { 102: { rawValue: '7.50', percent: '75.00' } },
    points: { 1001: { rawValue: '8.00', percent: '80.00' } },
  }

  it('reads the saved raw values exactly as returned', () => {
    expect(sheetValues(scores, units)).toEqual({
      'point-1001': '8.00',
      'point-1002': '',
      'sub-102': '7.50',
    })
    expect(savedScoreOf(scores, units[0])).toEqual({ rawValue: '8.00', percent: '80.00' })
    expect(savedScoreOf(scores, units[1])).toBeNull()
  })

  it('sends every unit, null for empty ones, and converts Arabic digits and separators', () => {
    const arabicEightPointFive = String.fromCodePoint(0x0668, 0x066b, 0x0665)
    const payload = sheetPayload(
      { 'point-1001': arabicEightPointFive, 'point-1002': '  ', 'sub-102': '6' },
      units,
    )
    expect(payload).toEqual({ subs: { 102: '6' }, points: { 1001: '8.5', 1002: null } })
  })

  it('lists the units without a value', () => {
    expect(
      unscoredUnits({ 'point-1001': '8', 'point-1002': '', 'sub-102': null }, units).map(
        (unit) => unit.code,
      ),
    ).toEqual(['M1.1.2', 'M1.2'])
  })

  it('maps server field paths to sheet fields and ignores other paths', () => {
    expect(fieldNameOfServerPath('scores.points.1002')).toBe('point-1002')
    expect(fieldNameOfServerPath('reason')).toBeNull()
    expect(sheetFieldErrors({ 'scores.subs.102': ['Out of range'], reason: ['Required'] })).toEqual(
      { 'sub-102': 'Out of range' },
    )
  })
})

describe('raw value rules', () => {
  it('accepts at most two decimals within the scale maximum', () => {
    expect(isRawScore('7.25')).toBe(true)
    expect(isRawScore('7.255')).toBe(false)
    expect(isRawScore('-1')).toBe(false)
    expect(withinScale('10.00', '10.00')).toBe(true)
    expect(withinScale('10.01', '10.00')).toBe(false)
    expect(withinScale('99.99', '100.00')).toBe(true)
  })

  it('compares raw values by their value, not their text', () => {
    expect(sameRawValue('7.5', '7.50')).toBe(true)
    expect(sameRawValue('7.5', '7.40')).toBe(false)
    expect(sameRawValue('', null)).toBe(true)
  })

  it('validates format and range with translated messages', async () => {
    const schema = scoreSheetSchema(t, units, '10.00')
    await expect(
      schema.validate({ 'point-1001': '', 'point-1002': '9.5', 'sub-102': null }),
    ).resolves.toBeTruthy()
    await expect(schema.validateAt('point-1001', { 'point-1001': '7.555' })).rejects.toThrow(
      'assessment.self.validation.format',
    )
    await expect(schema.validateAt('point-1001', { 'point-1001': '11' })).rejects.toThrow(
      'assessment.self.validation.range',
    )
  })
})
