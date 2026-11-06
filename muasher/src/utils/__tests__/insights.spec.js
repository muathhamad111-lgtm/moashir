import { describe, expect, it } from 'vitest'
import {
  bandLevel,
  bandRanges,
  deltaValue,
  gapRanges,
  isAligned,
  isAscending,
  isRuleValue,
  itemKey,
  normalizedRule,
  previewOf,
  vatNumberInput,
  versionLabelOf,
} from '@/utils/insights'

describe('insights utilities', () => {
  it('reads the signed delta string with the shared one-decimal rounding', () => {
    expect(deltaValue('-2.50')).toBe(-2.5)
    expect(deltaValue('+8.04')).toBe(8)
    expect(deltaValue('0.00')).toBe(0)
    expect(deltaValue(null)).toBeNull()
    expect(deltaValue('')).toBeNull()
    expect(deltaValue('n/a')).toBeNull()
  })

  it('maps bands to a 1-4 glyph level', () => {
    expect(['emerging', 'developing', 'mature', 'leading'].map(bandLevel)).toEqual([1, 2, 3, 4])
    expect(bandLevel('unknown')).toBe(0)
  })

  it('builds exhaustive class and band ranges on the 0-100 scale', () => {
    expect(gapRanges({ criticalBelow: '50.00', strengthFrom: '80.00' })).toEqual([
      { gapClass: 'critical_gap', from: 0, to: 50 },
      { gapClass: 'opportunity', from: 50, to: 80 },
      { gapClass: 'strength', from: 80, to: 100 },
    ])
    const bands = bandRanges({ developingFrom: '50.00', matureFrom: '70.00', leadingFrom: '80.00' })
    expect(bands.map((range) => [range.band, range.from, range.to])).toEqual([
      ['emerging', 0, 50],
      ['developing', 50, 70],
      ['mature', 70, 80],
      ['leading', 80, 100],
    ])
  })

  it('detects the alignment of the two rule sets on two decimals', () => {
    const gaps = { criticalBelow: '50', strengthFrom: '80.00' }
    expect(isAligned(gaps, { developingFrom: '50.00', leadingFrom: '80' })).toBe(true)
    expect(isAligned(gaps, { developingFrom: '55.00', leadingFrom: '80' })).toBe(false)
  })

  it('validates and normalizes rule values with Arabic-Indic digits', () => {
    expect(isRuleValue('٥٠')).toBe(true)
    expect(isRuleValue('100')).toBe(true)
    expect(isRuleValue('0')).toBe(false)
    expect(isRuleValue('100.01')).toBe(false)
    expect(isRuleValue('49.999')).toBe(false)
    expect(normalizedRule('٥٥')).toBe('55.00')
    expect(normalizedRule('79.9')).toBe('79.90')
    expect(isAscending(['50', '70', '80'])).toBe(true)
    expect(isAscending(['50', '50', '80'])).toBe(false)
  })

  it('normalizes the VAT number input', () => {
    expect(vatNumberInput(' ٣٠٠٠١٢٣٤٥٦٠٠٠٠٣ ')).toBe('300012345600003')
    expect(vatNumberInput('300 0123 4560 0003')).toBe('300012345600003')
  })

  it('labels the criteria version and keys and previews items', () => {
    const version = { id: 4, versionNumber: 3, label: { ar: 'الإصدار 3', en: 'Version 3' } }
    expect(versionLabelOf(version, 'en')).toBe('Version 3')
    expect(versionLabelOf({ id: 4, versionNumber: 3, label: null }, 'ar')).toBe('3')
    expect(versionLabelOf(null, 'ar')).toBe('')
    expect(itemKey({ mainCode: 'M1', subCode: 'M1.2' })).toBe('M1:M1.2')
    expect(previewOf([1, 2, 3], false, 2)).toEqual([1, 2])
    expect(previewOf([1, 2, 3], true, 2)).toEqual([1, 2, 3])
  })
})
