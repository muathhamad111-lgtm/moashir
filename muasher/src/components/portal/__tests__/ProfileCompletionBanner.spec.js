import { describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import ProfileCompletionBanner from '@/components/portal/ProfileCompletionBanner.vue'
import { useActionCenterStore } from '@/stores/actionCenter'
import { createTestGlobals } from '@/test-utils'

function item(key, context = {}) {
  return { key, count: 1, severity: 'warning', link: '/entity/profile', dueAt: null, context }
}

const PROFILE = item('complete_profile', { percent: 60 })
const DRAFT = item('submit_accredited_draft', { reference: 'ACC-2026-000004' })

async function factory(items, { locale = 'ar', path = '/dashboard' } = {}) {
  const globals = createTestGlobals({ locale })
  const store = useActionCenterStore(globals.pinia)
  store.$patch({ items, status: 'success' })
  await globals.router.push(path)
  const wrapper = mount(ProfileCompletionBanner, { global: globals })
  await flushPromises()
  return { wrapper, store }
}

const BANNER = '[data-testid="profile-completion-banner"]'

describe('ProfileCompletionBanner', () => {
  it('shows the incomplete profile with its progress and a button to the profile page', async () => {
    const { wrapper } = await factory([DRAFT, PROFILE])
    const banner = wrapper.find(BANNER)
    expect(banner.text()).toContain('ملف جهتك غير مكتمل')
    expect(banner.text()).toContain('60')
    const action = banner.find('[data-testid="profile-completion-action"]')
    expect(action.text()).toContain('أكمل الملف')
    expect(action.attributes('href')).toBe('/entity/profile')
  })

  it('shows nothing for the other action items once the profile is complete', async () => {
    const { wrapper } = await factory([DRAFT])
    expect(wrapper.find(BANNER).exists()).toBe(false)
  })

  it('leaves as soon as the server stops sending the profile item', async () => {
    const { wrapper, store } = await factory([PROFILE])
    expect(wrapper.find(BANNER).exists()).toBe(true)
    store.$patch({ items: [] })
    await flushPromises()
    expect(wrapper.find(BANNER).exists()).toBe(false)
  })

  it('stays out of the way on the profile page itself', async () => {
    const { wrapper } = await factory([PROFILE], { path: '/entity/profile' })
    expect(wrapper.find(BANNER).exists()).toBe(false)
  })

  it('reads in English without a percentage when the server sends none', async () => {
    const { wrapper } = await factory([item('complete_profile', { percent: null })], {
      locale: 'en',
    })
    const banner = wrapper.find(BANNER)
    expect(banner.text()).toContain('Your entity profile is incomplete')
    expect(banner.text()).not.toContain('%')
    expect(banner.text()).toContain('Complete profile')
  })
})
