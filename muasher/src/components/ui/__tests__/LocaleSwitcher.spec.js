import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import LocaleSwitcher from '@/components/ui/LocaleSwitcher.vue'
import { createTestGlobals } from '@/test-utils'

const toggleLocale = vi.fn()
vi.mock('@/composables/useLocale', () => ({
  useLocale: () => ({ toggleLocale }),
}))

describe('LocaleSwitcher', () => {
  it('labels the button with the OTHER language and toggles on click', async () => {
    const wrapper = mount(LocaleSwitcher, { global: createTestGlobals() })
    expect(wrapper.text()).toContain('English')
    await wrapper.find('button').trigger('click')
    expect(toggleLocale).toHaveBeenCalledOnce()
  })
})
