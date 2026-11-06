import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import Maintenance from '@/views/public/Maintenance.vue'
import { contentService } from '@/services/contentService'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/contentService', () => ({
  contentService: { getStatus: vi.fn() },
}))

async function mountView(locale = 'ar') {
  const wrapper = mount(Maintenance, { global: createTestGlobals({ locale }) })
  await flushPromises()
  return wrapper
}

beforeEach(() => vi.clearAllMocks())

describe('Maintenance page (Q-96)', () => {
  it('explains the maintenance and keeps a sign-in link for staff and entities', async () => {
    contentService.getStatus.mockResolvedValue({ data: { maintenance: true, publishedAt: null } })
    const wrapper = await mountView()
    expect(wrapper.find('h1').text()).toBe('الموقع قيد التحديث')
    expect(wrapper.find('a[href="/auth/login"]').text()).toContain('تسجيل الدخول')
  })

  it('re-checks the status on demand and offers the homepage once published', async () => {
    contentService.getStatus.mockResolvedValueOnce({
      data: { maintenance: true, publishedAt: null },
    })
    contentService.getStatus.mockResolvedValueOnce({
      data: { maintenance: false, publishedAt: '2026-11-01T08:00:00Z' },
    })
    const wrapper = await mountView('en')
    await wrapper.find('.maintenance__actions .btn--ghost').trigger('click')
    await flushPromises()
    expect(wrapper.find('h1').text()).toBe('The site is available now.')
    expect(wrapper.find('a[href="/"]').exists()).toBe(true)
  })
})
