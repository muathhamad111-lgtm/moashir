import { describe, expect, it } from 'vitest'
import { defineComponent, h } from 'vue'
import { mount } from '@vue/test-utils'
import { useCriteria } from '@/composables/useCriteria'
import { createTestGlobals } from '@/test-utils'

function harness(locale) {
  let api
  const Host = defineComponent({
    setup() {
      api = useCriteria()
      return () => h('div')
    },
  })
  mount(Host, { global: createTestGlobals({ locale }) })
  return api
}

describe('useCriteria', () => {
  it('resolves bilingual content to the active locale with fallback', () => {
    const api = harness('en')
    expect(api.localized({ ar: 'القيادة', en: 'Leadership' })).toBe('Leadership')
    expect(api.localized({ ar: 'القيادة', en: '' })).toBe('القيادة')
    expect(api.localized(null)).toBe('')
  })

  it('formats two-decimal weights with Western digits in Arabic', () => {
    const api = harness('ar')
    expect(api.weight('1000.00')).not.toMatch(/[\u0660-\u0669]/)
    expect(api.weight('133.34')).toMatch(/133.34/)
  })
})
