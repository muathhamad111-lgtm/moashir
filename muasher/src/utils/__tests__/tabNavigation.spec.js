import { describe, expect, it } from 'vitest'
import { tabIndexAfterKey } from '@/utils/tabNavigation'

describe('tab keyboard navigation', () => {
  it.each([
    ['ArrowRight', 0, false, 1],
    ['ArrowRight', 2, false, 0],
    ['ArrowLeft', 0, false, 2],
    ['ArrowLeft', 0, true, 1],
    ['ArrowRight', 1, true, 0],
    ['Home', 2, true, 0],
    ['End', 0, false, 2],
  ])('%s from %i (rtl %s) goes to %i', (key, index, rightToLeft, expected) => {
    expect(tabIndexAfterKey(key, index, 3, rightToLeft)).toBe(expected)
  })

  it('ignores other keys and empty lists', () => {
    expect(tabIndexAfterKey('Enter', 0, 3, false)).toBeNull()
    expect(tabIndexAfterKey('ArrowRight', 0, 0, false)).toBeNull()
  })
})
