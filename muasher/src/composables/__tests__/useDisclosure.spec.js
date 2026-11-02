import { describe, expect, it } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { mount } from '@vue/test-utils'
import { useDisclosure } from '@/composables/useDisclosure'

function harness() {
  let api
  const Host = defineComponent({
    setup() {
      api = useDisclosure()
      const other = ref(null)
      return () =>
        h('div', [
          h('div', { ref: api.rootRef, class: 'root' }, 'inside'),
          h('button', { ref: other, class: 'outside' }, 'outside'),
        ])
    },
  })
  const wrapper = mount(Host, { attachTo: document.body })
  return { api, wrapper }
}

describe('useDisclosure', () => {
  it('toggles open state', () => {
    const { api, wrapper } = harness()
    expect(api.isOpen.value).toBe(false)
    api.toggle()
    expect(api.isOpen.value).toBe(true)
    api.close()
    expect(api.isOpen.value).toBe(false)
    wrapper.unmount()
  })

  it('closes on outside click but not on inside click', async () => {
    const { api, wrapper } = harness()
    api.open()
    document.querySelector('.root').dispatchEvent(new MouseEvent('click', { bubbles: true }))
    expect(api.isOpen.value).toBe(true)
    document.querySelector('.outside').dispatchEvent(new MouseEvent('click', { bubbles: true }))
    expect(api.isOpen.value).toBe(false)
    wrapper.unmount()
  })

  it('closes on Escape', () => {
    const { api, wrapper } = harness()
    api.open()
    document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }))
    expect(api.isOpen.value).toBe(false)
    wrapper.unmount()
  })
})
