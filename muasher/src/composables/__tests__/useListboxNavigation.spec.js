import { describe, expect, it } from 'vitest'
import { nextTick, ref } from 'vue'
import { useListboxNavigation } from '@/composables/useListboxNavigation'

describe('useListboxNavigation', () => {
  it('moves forward and backward with wrap-around', () => {
    const options = ref(['a', 'b', 'c'])
    const nav = useListboxNavigation(options)
    nav.move(1)
    expect(nav.activeOption.value).toBe('a')
    nav.move(-1)
    expect(nav.activeOption.value).toBe('c')
    nav.move(1)
    expect(nav.activeIndex.value).toBe(0)
  })

  it('starts from the end when moving up first and resets when options change', async () => {
    const options = ref(['a', 'b'])
    const nav = useListboxNavigation(options)
    nav.move(-1)
    expect(nav.activeOption.value).toBe('b')
    options.value = ['x']
    await nextTick()
    expect(nav.activeIndex.value).toBe(-1)
  })

  it('does nothing on an empty list and ignores out-of-range activation', () => {
    const nav = useListboxNavigation(ref([]))
    nav.move(1)
    expect(nav.activeIndex.value).toBe(-1)
    nav.activate(4)
    expect(nav.activeOption.value).toBeNull()
  })
})
