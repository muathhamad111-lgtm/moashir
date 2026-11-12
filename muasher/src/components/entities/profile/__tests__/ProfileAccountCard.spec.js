import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import ProfileAccountCard from '@/components/entities/profile/ProfileAccountCard.vue'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'

const USER = {
  id: 501,
  name: { ar: 'أحمد بن سالم القحطاني', en: 'Ahmed bin Salem Al-Qahtani' },
  email: 'info@albirr-riyadh.example.sa',
  roles: ['entity'],
}

function mountCard(locale, user = USER) {
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  useAuthStore().setUser(user)
  return mount(ProfileAccountCard, { global: globals })
}

describe('ProfileAccountCard', () => {
  it.each([
    ['ar', 'بيانات الحساب', 'أحمد بن سالم القحطاني'],
    ['en', 'Account details', 'Ahmed bin Salem Al-Qahtani'],
  ])('shows the signed-in user name and e-mail in %s', (locale, title, name) => {
    const wrapper = mountCard(locale)
    expect(wrapper.find('h2').text()).toBe(title)
    const values = wrapper.findAll('dd').map((value) => value.text())
    expect(values).toEqual([name, 'info@albirr-riyadh.example.sa'])
    expect(wrapper.find('bdi').attributes('dir')).toBe('ltr')
  })

  it('hides the e-mail row when the session has none', () => {
    const wrapper = mountCard('ar', { ...USER, email: '' })
    expect(wrapper.findAll('dd')).toHaveLength(1)
  })
})
