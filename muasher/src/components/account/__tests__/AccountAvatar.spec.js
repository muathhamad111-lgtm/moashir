import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import AccountAvatar from '@/components/account/AccountAvatar.vue'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'

function mountFor(roles, extra = {}, locale = 'ar') {
  const globals = createTestGlobals({ locale })
  const auth = useAuthStore(globals.pinia)
  auth.setUser(sessionUserFor(roles, { name: 'سارة القحطاني', ...extra }))
  return { wrapper: mount(AccountAvatar, { global: globals }), auth }
}

describe('AccountAvatar', () => {
  it('falls back to the person icon for a staff account without a photo', () => {
    const { wrapper } = mountFor(['assessor'])
    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.find('[data-icon="user"]').exists()).toBe(true)
  })

  it('falls back to the building icon for an entity account without a logo', () => {
    const { wrapper } = mountFor(['entity'])
    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.find('[data-icon="building"]').exists()).toBe(true)
  })

  it('renders the staff photo with the person name as alt text in both languages', () => {
    const avatar = { source: 'photo', url: '/api/v1/me/avatar?v=1' }
    const arabic = mountFor(['system_admin'], { avatar }).wrapper.find('img')
    expect(arabic.attributes('src')).toBe(avatar.url)
    expect(arabic.attributes('alt')).toBe('الصورة الشخصية لـ سارة القحطاني')
    const english = mountFor(['system_admin'], { avatar, name: 'Sara' }, 'en').wrapper.find('img')
    expect(english.attributes('alt')).toBe('Profile photo of Sara')
  })

  it('renders the entity logo on a light surface with the entity name as alt text', () => {
    const { wrapper } = mountFor(['entity'], {
      entity: { id: 5001, name: { ar: 'جمعية البر', en: 'Al-Birr Society' } },
      avatar: { source: 'entity_logo', url: '/api/v1/me/avatar?v=2' },
    })
    const image = wrapper.find('img')
    expect(image.classes()).toContain('account-avatar__image--logo')
    expect(image.attributes('alt')).toBe('شعار جمعية البر')
  })

  it('falls back to the icon when the image fails and retries a new image', async () => {
    const { wrapper, auth } = mountFor(['assessor'], {
      avatar: { source: 'photo', url: '/api/v1/me/avatar?v=old' },
    })
    await wrapper.find('img').trigger('error')
    expect(wrapper.find('img').exists()).toBe(false)
    expect(wrapper.find('[data-icon="user"]').exists()).toBe(true)
    auth.applyAvatar({ source: 'photo', url: '/api/v1/me/avatar?v=new' })
    await wrapper.vm.$nextTick()
    expect(wrapper.find('img').attributes('src')).toBe('/api/v1/me/avatar?v=new')
  })

  it('switches to the icon as soon as the photo is removed', async () => {
    const { wrapper, auth } = mountFor(['assessor'], {
      avatar: { source: 'photo', url: '/api/v1/me/avatar?v=1' },
    })
    auth.applyAvatar(null)
    await wrapper.vm.$nextTick()
    expect(wrapper.find('img').exists()).toBe(false)
  })
})
