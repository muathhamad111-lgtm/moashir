import { describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { mount } from '@vue/test-utils'
import ThemeToggle from '@/components/ui/ThemeToggle.vue'
import { createTestGlobals } from '@/test-utils'

const theme = ref('dark')
const toggleTheme = vi.fn(() => {
  theme.value = theme.value === 'dark' ? 'light' : 'dark'
})
vi.mock('@/composables/useTheme', () => ({
  useTheme: () => ({ theme, toggleTheme }),
}))

describe('ThemeToggle', () => {
  it('is icon-only with an accessible name describing the switch target', () => {
    const wrapper = mount(ThemeToggle, { global: createTestGlobals() })
    expect(wrapper.find('button').attributes('aria-label')).toBe('التبديل إلى الوضع النهاري')
    expect(wrapper.find('.btn--icon').exists()).toBe(true)
  })

  it('toggles the theme and updates icon + label', async () => {
    const wrapper = mount(ThemeToggle, { global: createTestGlobals() })
    await wrapper.find('button').trigger('click')
    expect(toggleTheme).toHaveBeenCalled()
    expect(wrapper.find('button').attributes('aria-label')).toBe('التبديل إلى الوضع الليلي')
  })
})
