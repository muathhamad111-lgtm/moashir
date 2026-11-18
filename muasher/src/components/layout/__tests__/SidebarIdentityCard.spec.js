import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import SidebarIdentityCard from '@/components/layout/SidebarIdentityCard.vue'
import { createTestGlobals } from '@/test-utils'
import { useToast } from '@/composables/useToast'

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

  it('copies the platform link from the split button and says so', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined)
    vi.stubGlobal('navigator', { ...navigator, clipboard: { writeText } })
    const wrapper = factory()
    const copy = wrapper.find('[data-testid="sidebar-copy-platform-link"]')
    expect(copy.attributes('aria-label')).toBe('نسخ رابط المنصة')
    await copy.trigger('click')
    await flushPromises()
    expect(writeText).toHaveBeenCalledWith(`${window.location.origin}/`)
    expect(useToast().toasts.at(-1).message).toBe('تم نسخ رابط المنصة')
  })

  it('tells the user when the link cannot be copied', async () => {
    vi.stubGlobal('navigator', {
      ...navigator,
      clipboard: { writeText: vi.fn().mockRejectedValue(new Error('denied')) },
    })
    const wrapper = factory()
    await wrapper.find('[data-testid="sidebar-copy-platform-link"]').trigger('click')
    await flushPromises()
    expect(useToast().toasts.at(-1).message).toContain('تعذّر نسخ الرابط')
  })
})

afterEach(() => {
  vi.unstubAllGlobals()
  useToast().toasts.forEach((item) => useToast().dismiss(item.id))
})
