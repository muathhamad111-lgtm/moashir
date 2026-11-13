import { describe, expect, it } from 'vitest'
import { toPixels } from '@/utils/dropdown/cssLength'
import { optionIndexOf, sameValue, valueKey } from '@/utils/dropdown/optionValue'
import { DROPDOWN_METRICS } from '@/constants/dropdown'

describe('toPixels', () => {
  it('reads px and rem lengths from the tokens', () => {
    expect(toPixels('4px')).toBe(4)
    expect(toPixels('20rem')).toBe(320)
    expect(toPixels('1.5rem', 20)).toBe(30)
    expect(toPixels('auto')).toBe(0)
  })

  it('exports the placement metrics from the SCSS tokens', () => {
    expect(DROPDOWN_METRICS).toEqual({
      gap: '4px',
      margin: '8px',
      maxBlock: '20rem',
      flipMin: '15rem',
    })
  })
})

describe('option values', () => {
  it('compares values across number and string forms and treats null as empty', () => {
    expect(sameValue(2, '2')).toBe(true)
    expect(sameValue(null, '')).toBe(true)
    expect(sameValue('a', 'b')).toBe(false)
    expect(valueKey(undefined)).toBe('')
  })

  it('finds the index of an option by value', () => {
    const options = [{ value: '' }, { value: 12 }]
    expect(optionIndexOf(options, '12')).toBe(1)
    expect(optionIndexOf(options, 7)).toBe(-1)
  })
})
