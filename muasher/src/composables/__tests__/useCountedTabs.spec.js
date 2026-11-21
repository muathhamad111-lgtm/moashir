import { describe, expect, it } from 'vitest'
import { nextTick, ref } from 'vue'
import { useCountedTabs } from '@/composables/useCountedTabs'

function setup(initial = 'a') {
  const meta = ref({})
  const selected = ref(initial)
  const { tabs, tab } = useCountedTabs({
    values: ['a', 'b', 'c'],
    labelOf: (value) => value.toUpperCase(),
    countOf: (value) => meta.value.counts?.[value],
    counts: ref(undefined),
    current: () => selected.value,
    select: (value) => (selected.value = value),
  })
  return { meta, selected, tabs, tab }
}

describe('useCountedTabs', () => {
  it('labels each tab with its count once the counts are known', () => {
    const { meta, tabs } = setup()
    expect(tabs.value.map((tab) => tab.label)).toEqual(['A', 'B', 'C'])
    meta.value = { counts: { a: 0, b: 2, c: 5 } }
    expect(tabs.value.map((tab) => tab.label)).toEqual(['A (0)', 'B (2)', 'C (5)'])
  })

  it('opens the first tab with rows when the selected one arrives empty, only once', async () => {
    const meta = ref({})
    const counts = ref(undefined)
    const selected = ref('a')
    const { tab } = useCountedTabs({
      values: ['a', 'b', 'c'],
      labelOf: (value) => value,
      countOf: (value) => meta.value.counts?.[value],
      counts,
      current: () => selected.value,
      select: (value) => (selected.value = value),
    })
    meta.value = { counts: { a: 0, b: 0, c: 3 } }
    counts.value = meta.value.counts
    await nextTick()
    expect(tab.value).toBe('c')
    tab.value = 'a'
    counts.value = { a: 0, b: 1, c: 3 }
    await nextTick()
    expect(selected.value).toBe('a')
  })

  it('keeps the selected tab when it has rows', async () => {
    const counts = ref(undefined)
    const selected = ref('b')
    useCountedTabs({
      values: ['a', 'b'],
      labelOf: (value) => value,
      countOf: (value) => counts.value?.[value],
      counts,
      current: () => selected.value,
      select: (value) => (selected.value = value),
    })
    counts.value = { a: 4, b: 1 }
    await nextTick()
    expect(selected.value).toBe('b')
  })
})
