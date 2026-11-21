import { afterEach, describe, expect, it, vi } from 'vitest'
import { effectScope } from 'vue'
import { useActiveIndex } from '@/composables/useActiveIndex'
import { isTypeaheadKey, useTypeahead } from '@/composables/useTypeahead'
import { TYPEAHEAD_RESET_MS } from '@/constants/dropdown'

afterEach(() => vi.useRealTimers())

describe('useActiveIndex', () => {
  it('steps without wrapping and clamps at both ends', () => {
    const active = useActiveIndex(() => 3)
    active.step(1)
    expect(active.activeIndex.value).toBe(0)
    active.step(10)
    expect(active.activeIndex.value).toBe(2)
    active.step(-10)
    expect(active.activeIndex.value).toBe(0)
  })

  it('starts from the end when stepping up with nothing active', () => {
    const active = useActiveIndex(() => 4)
    active.step(-1)
    expect(active.activeIndex.value).toBe(3)
  })

  it('wraps for menus and jumps to the first and last items', () => {
    const active = useActiveIndex(() => 3)
    active.last()
    active.wrap(1)
    expect(active.activeIndex.value).toBe(0)
    active.wrap(-1)
    expect(active.activeIndex.value).toBe(2)
    active.first()
    expect(active.activeIndex.value).toBe(0)
    active.clear()
    expect(active.activeIndex.value).toBe(-1)
  })

  it('stays empty on an empty list', () => {
    const active = useActiveIndex(() => 0)
    active.step(1)
    active.wrap(1)
    active.last()
    expect(active.activeIndex.value).toBe(-1)
  })
})

describe('useTypeahead', () => {
  const labels = ['المنطقة الشرقية', 'منطقة الرياض', 'منطقة مكة', 'مكة المكرمة', 'Riyadh']

  function typeahead() {
    const scope = effectScope()
    const api = scope.run(() => useTypeahead(() => labels))
    return { api, scope }
  }

  it('accepts printable keys only', () => {
    expect(isTypeaheadKey({ key: 'م' })).toBe(true)
    expect(isTypeaheadKey({ key: 'ArrowDown' })).toBe(false)
    expect(isTypeaheadKey({ key: 'a', ctrlKey: true })).toBe(false)
  })

  it('jumps to the next item starting with the typed character and cycles on repeats', () => {
    const { api } = typeahead()
    expect(api.type('م', -1)).toBe(1)
    expect(api.type('م', 1)).toBe(2)
    expect(api.type('م', 2)).toBe(3)
  })

  it('matches a typed prefix with Arabic normalisation and forgets it after the timeout', () => {
    vi.useFakeTimers()
    const { api } = typeahead()
    expect(api.type('ا', -1)).toBe(0)
    expect(api.type('ل', 0)).toBe(0)
    expect(api.hasBuffer()).toBe(true)
    vi.advanceTimersByTime(TYPEAHEAD_RESET_MS)
    expect(api.hasBuffer()).toBe(false)
    expect(api.type('r', 0)).toBe(4)
  })

  it('returns -1 when nothing matches and clears its timer on dispose', () => {
    vi.useFakeTimers()
    const { api, scope } = typeahead()
    expect(api.type('z', 0)).toBe(-1)
    scope.stop()
    expect(api.hasBuffer()).toBe(false)
  })
})
