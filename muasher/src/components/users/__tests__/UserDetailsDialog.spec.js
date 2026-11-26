import { afterEach, describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import UserDetailsDialog from '@/components/users/UserDetailsDialog.vue'
import { createTestGlobals } from '@/test-utils'

const USER = {
  id: 43,
  name: 'Laura Bennett',
  email: 'laura@moashir.example.sa',
  phone: '+966551234567',
  gender: 'female',
  nationalIdMasked: '••••••0505',
  nationalIdChangedAtAcceptanceAt: null,
}

let wrapper

async function mountDialog(user, locale = 'en') {
  const globals = createTestGlobals({ locale })
  wrapper = mount(UserDetailsDialog, {
    props: { modelValue: true, user },
    global: { ...globals, stubs: { teleport: true } },
    attachTo: document.body,
  })
  await flushPromises()
  return globals
}

afterEach(() => {
  wrapper?.unmount()
  document.body.innerHTML = ''
})

describe('user details dialog', () => {
  it.each(['ar', 'en'])(
    'shows the name, email, mobile, masked national ID and gender (%s)',
    async (locale) => {
      const globals = await mountDialog(USER, locale)
      const details = wrapper.find('[data-testid="user-details"]').text()
      expect(details).toContain('Laura Bennett')
      expect(details).toContain('laura@moashir.example.sa')
      expect(details).toContain('+966551234567')
      expect(wrapper.find('[data-testid="user-national-id"]').text()).toBe('••••••0505')
      expect(wrapper.find('[data-testid="user-gender"]').text()).toBe(
        globals.i18n.global.t('nationalIdentity.gender.female'),
      )
      expect(wrapper.find('[data-testid="user-national-id-changed"]').exists()).toBe(false)
    },
  )

  it('says what is not added yet and flags a number changed at invitation acceptance', async () => {
    await mountDialog({
      ...USER,
      phone: null,
      gender: null,
      nationalIdChangedAtAcceptanceAt: '2026-11-21T09:00:00Z',
    })
    expect(wrapper.find('[data-testid="user-gender"]').text()).toBe('Not added yet')
    expect(wrapper.find('[data-testid="user-national-id-changed"]').text()).toContain(
      'The user changed the national ID entered by the administration',
    )
  })
})
