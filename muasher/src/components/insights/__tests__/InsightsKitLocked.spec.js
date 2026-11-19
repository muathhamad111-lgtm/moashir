import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import InsightsToolError from '@/components/insights/InsightsToolError.vue'
import { isKitLocked } from '@/constants/insights'
import { createTestGlobals } from '@/test-utils'

describe('excellence tools locked until the Kit is activated', () => {
  it('reads the server code as a locked state', () => {
    expect(isKitLocked({ normalized: { status: 403, code: 'insights.kit_required' } })).toBe(true)
    expect(isKitLocked({ normalized: { status: 403, code: 'forbidden' } })).toBe(false)
  })

  it('shows the Kit card with a link to the Kit page instead of an error', () => {
    const wrapper = mount(InsightsToolError, {
      props: { status: 'locked' },
      global: createTestGlobals({ locale: 'ar' }),
    })
    const card = wrapper.find('[data-testid="insights-kit-locked"]')
    expect(card.text()).toContain('تُفتح أدوات التميّز بعد تفعيل حقيبة التميز السريع')
    expect(card.find('a').text()).toBe('حقيبة التميز السريع')
    expect(wrapper.find('[data-testid="insights-ineligible"]').exists()).toBe(false)
  })
})
