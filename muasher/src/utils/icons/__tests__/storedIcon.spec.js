import { describe, expect, it } from 'vitest'
import { STORED_ICON_PATTERN } from '@/constants/icons'
import { PICKABLE_ICON_NAMES } from '@/utils/icons/iconRegistry'
import { iconNameFromStored, storedIconFor } from '@/utils/icons/storedIcon'
import { svgPathData } from '@/utils/icons/svgPathData'

describe('stored icon values', () => {
  it('reads legacy class values as platform icon names', () => {
    expect(iconNameFromStored('fa-solid fa-circle-info')).toBe('info-circle')
    expect(iconNameFromStored('fa-regular fa-clock')).toBe('clock')
    expect(iconNameFromStored(' fa-solid fa-check-circle ')).toBe('check-circle')
  })

  it('keeps plain icon names and ignores empty values', () => {
    expect(iconNameFromStored('award')).toBe('award')
    expect(iconNameFromStored('')).toBe('')
    expect(iconNameFromStored(null)).toBe('')
    expect(iconNameFromStored(42)).toBe('')
  })

  it('writes a platform icon name in the format the API validates', () => {
    expect(storedIconFor('chart-line')).toBe('fa-solid fa-chart-line')
    expect(iconNameFromStored(storedIconFor('check-circle'))).toBe('check-circle')
    expect(storedIconFor('  ')).toBeNull()
    expect(storedIconFor(null)).toBeNull()
  })

  it('stores every pickable icon in a value the API icon rule accepts and reads it back', () => {
    const rejected = PICKABLE_ICON_NAMES.filter((name) => {
      const stored = storedIconFor(name)
      return !STORED_ICON_PATTERN.test(stored) || iconNameFromStored(stored) !== name
    })
    expect(rejected).toEqual([])
  })
})

describe('svgPathData', () => {
  it('extracts the path data of every path element', () => {
    const source =
      '<svg viewBox="0 0 24 24"><path d="M1 1H2Z"/><path fill="currentColor" d="M3 3H4Z"></path></svg>'
    expect(svgPathData(source)).toEqual(['M1 1H2Z', 'M3 3H4Z'])
    expect(svgPathData('<svg></svg>')).toEqual([])
  })
})
