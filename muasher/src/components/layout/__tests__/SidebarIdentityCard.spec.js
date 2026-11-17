import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import SidebarIdentityCard from '@/components/layout/SidebarIdentityCard.vue'
import { createTestGlobals } from '@/test-utils'

const HOME_LABEL = 'مؤشر، الانتقال إلى لوحة المعلومات'

function factory({ collapsed = false, locale = 'ar' } = {}) {
  return mount(SidebarIdentityCard, {
    props: { homeLabel: HOME_LABEL, collapsed },
    global: createTestGlobals({ locale }),
  })
}

function previewOf(wrapper) {
  return wrapper.find('[data-testid="sidebar-preview-platform"]')
}

describe('SidebarIdentityCard', () => {
  it('links the logo to the dashboard with the given accessible name', () => {
    const wrapper = factory()
    expect(wrapper.find('.identity-card__brand').attributes('href')).toBe('/dashboard')
    expect(wrapper.find('.identity-card__logo').attributes('alt')).toBe(HOME_LABEL)
  })

  it('opens the public site in a new tab and says so to assistive technology', () => {
    const preview = previewOf(factory())
    expect(preview.attributes('href')).toBe('/')
    expect(preview.attributes('target')).toBe('_blank')
    expect(preview.attributes('rel')).toBe('noopener noreferrer')
    expect(preview.text()).toBe('معاينة المنصة (تفتح في تبويب جديد)')
    expect(preview.attributes('title')).toBeUndefined()
  })

  it('keeps the preview link named when collapsed and adds a hover title', () => {
    const wrapper = factory({ collapsed: true, locale: 'en' })
    expect(wrapper.classes()).toContain('identity-card--collapsed')
    expect(previewOf(wrapper).text()).toBe('Preview platform (opens in a new tab)')
    expect(previewOf(wrapper).attributes('title')).toBe('Preview platform')
  })
})
