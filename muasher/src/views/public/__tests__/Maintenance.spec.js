import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import Maintenance from '@/views/public/Maintenance.vue'
import { contentService } from '@/services/contentService'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/contentService', () => ({
  contentService: { getLanding: vi.fn() },
}))

async function mountView(locale = 'ar') {
  const wrapper = mount(Maintenance, { global: createTestGlobals({ locale }) })
  await flushPromises()
  return wrapper
}

beforeEach(() => vi.clearAllMocks())

describe('Maintenance page (Q-96)', () => {
  it('explains the maintenance and keeps a sign-in link for staff and entities', async () => {
    contentService.getLanding.mockResolvedValue({ data: { published: false, maintenance: true } })
    const wrapper = await mountView()
    expect(wrapper.find('h1').text()).toBe('الموقع قيد التحديث')
    expect(wrapper.find('a[href="/auth/login"]').text()).toContain('تسجيل الدخول')
  })

  it('re-checks the status on demand and offers the homepage once published', async () => {
    contentService.getLanding.mockResolvedValueOnce({ data: { published: false, maintenance: true } })
    contentService.getLanding.mockResolvedValueOnce({ data: { published: true } })
    const wrapper = await mountView('en')
    await wrapper.find('.maintenance__actions .btn--ghost').trigger('click')
    await flushPromises()
    expect(wrapper.find('h1').text()).toBe('The site is available now.')
    expect(wrapper.find('a[href="/"]').exists()).toBe(true)
  })
})
