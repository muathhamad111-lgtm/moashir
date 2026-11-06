import { describe, expect, it, vi, beforeEach } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import More from '@/views/portal/More.vue'
import { useAuthStore } from '@/stores/auth'
import { usePortalCountsStore } from '@/stores/portalCounts'
import { entitiesService } from '@/services/entitiesService'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

vi.mock('@/services/entitiesService', () => ({ entitiesService: { getMine: vi.fn() } }))

async function factory(locale = 'ar') {
  const globals = createTestGlobals({ locale })
  useAuthStore(globals.pinia).setUser(
    sessionUserFor(['entity'], {
      name: 'سارة عبدالله',
      entity: { id: 5001, name: { ar: 'جمعية الأمل النموذجية', en: 'Al-Amal Model Association' } },
    }),
  )
  const counts = usePortalCountsStore(globals.pinia)
  counts.unreadReplies = 1
  counts.unreadNotifications = 2
  const wrapper = mount(More, { global: globals })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  vi.clearAllMocks()
  entitiesService.getMine.mockResolvedValue({ data: { profileCompletion: { percent: 80 } } })
})

describe('More (M4)', () => {
  it('lists my services with the counts and the profile completion', async () => {
    const wrapper = await factory()
    expect(wrapper.find('.portal-more__entity').text()).toBe('جمعية الأمل النموذجية')
    const services = wrapper.findAll('.portal-more__group')[0].findAll('.portal-menu-entry')
    expect(services.map((entry) => entry.find('.portal-menu-entry__label').text())).toEqual([
      'رسائلي',
      'مدفوعاتي',
      'الإشعارات',
      'ملف الجهة',
    ])
    expect(services[0].find('.portal-menu-entry__value').text()).toBe('1 جديد')
    expect(services[2].find('.portal-menu-entry__value').text()).toBe('2')
    expect(services[3].find('.portal-menu-entry__value').text()).toBe('80%')
    expect(services[1].find('.portal-menu-entry__value').exists()).toBe(false)
  })

  it('shows the account and preference entries in English', async () => {
    const wrapper = await factory('en')
    const account = wrapper.findAll('.portal-more__group')[2]
    expect(account.findAll('.portal-menu-entry__label').map((label) => label.text())).toEqual([
      'My profile',
      'Language',
      'Appearance',
      'Help and guides',
    ])
    expect(account.findAll('.portal-menu-entry__value')[0].text()).toBe('العربية')
  })
})
