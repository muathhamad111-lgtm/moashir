import { describe, expect, it, beforeEach } from 'vitest'
import { defineComponent, nextTick, ref } from 'vue'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { useSearchHandoffStore } from '@/stores/searchHandoff'
import { useSearchPrefill } from '@/composables/useSearchPrefill'

beforeEach(() => setActivePinia(createPinia()))

describe('search handoff', () => {
  it('hands a search text to one route and consumes it once', () => {
    const handoff = useSearchHandoffStore()
    handoff.hand('requests.index', 'جمعية البر')
    expect(handoff.consume('admin.users')).toBe('')
    expect(handoff.consume('requests.index')).toBe('جمعية البر')
    expect(handoff.consume('requests.index')).toBe('')
  })

  it('ignores empty texts', () => {
    const handoff = useSearchHandoffStore()
    handoff.hand('requests.index', '')
    expect(handoff.pending).toEqual({})
  })

  it('prefills the list search at setup and again when already on the page', async () => {
    const handoff = useSearchHandoffStore()
    handoff.hand('requests.index', 'نماء')
    const search = ref('')
    mount(
      defineComponent({
        setup() {
          useSearchPrefill(search, 'requests.index')
          return () => null
        },
      }),
    )
    expect(search.value).toBe('نماء')
    handoff.hand('requests.index', 'إحسان')
    await nextTick()
    expect(search.value).toBe('إحسان')
    expect(handoff.pending).toEqual({})
  })
})
