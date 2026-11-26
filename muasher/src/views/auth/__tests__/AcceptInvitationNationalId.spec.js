import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import AcceptInvitation from '@/views/auth/AcceptInvitation.vue'
import { authService } from '@/services/authService'
import { termsService } from '@/services/termsService'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/authService', () => ({
  UNAUTHENTICATED_STATUS: 401,
  authService: { checkInvitation: vi.fn(), acceptInvitation: vi.fn(), session: vi.fn() },
}))
vi.mock('@/services/termsService', () => ({ termsService: { current: vi.fn() } }))

const STRONG = 'Namaa-Strong-2026!'
const TOKEN = 'invite-sarah-whitfield-2026'

let wrapper

async function settle() {
  for (let round = 0; round < 3; round += 1) {
    await new Promise((resolve) => setTimeout(resolve, 10))
    await flushPromises()
  }
}

async function mountInvitation(nationalIdMasked, locale = 'en') {
  authService.checkInvitation.mockResolvedValue({ data: { usable: true, nationalIdMasked } })
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  await globals.router.push('/auth/login')
  wrapper = mount(AcceptInvitation, {
    props: { token: TOKEN },
    global: globals,
    attachTo: document.body,
  })
  await settle()
  await wrapper.find('[name="password"]').setValue(STRONG)
  await wrapper.find('[name="passwordConfirmation"]').setValue(STRONG)
  return globals
}

async function submit() {
  await wrapper.find('form').trigger('submit')
  await settle()
}

beforeEach(() => {
  vi.clearAllMocks()
  termsService.current.mockResolvedValue({ data: { terms: null, privacy: null } })
  authService.acceptInvitation.mockResolvedValue({ data: { step: 'mfa_enrollment', user: null } })
})

afterEach(() => {
  wrapper?.unmount()
  document.body.innerHTML = ''
})

describe('national ID at invitation acceptance', () => {
  it.each(['ar', 'en'])(
    'shows the number the administrator entered and keeps it when left empty (%s)',
    async (locale) => {
      const globals = await mountInvitation('••••••0505', locale)
      expect(wrapper.find('[data-testid="invitation-national-id-preset"]').text()).toBe(
        '••••••0505',
      )
      expect(wrapper.find('[data-testid="invitation-national-id"]').text()).toContain(
        globals.i18n.global.t('nationalIdentity.invitation.different'),
      )
      await submit()
      expect(authService.acceptInvitation).toHaveBeenCalledWith(TOKEN, {
        password: STRONG,
        passwordConfirmation: STRONG,
        termsVersionId: null,
      })
    },
  )

  it('sends a different number, with Western digits', async () => {
    await mountInvitation('••••••0505')
    await wrapper.find('[name="nationalId"]').setValue('٢٠٠٠٠٠٠٠٠١')
    await submit()
    expect(authService.acceptInvitation).toHaveBeenCalledWith(TOKEN, {
      password: STRONG,
      passwordConfirmation: STRONG,
      termsVersionId: null,
      nationalId: '2000000001',
    })
  })

  it('offers an optional field when no number was entered and says which rule a number breaks', async () => {
    await mountInvitation(null)
    expect(wrapper.find('[data-testid="invitation-national-id-preset"]').exists()).toBe(false)
    expect(wrapper.text()).toContain('Optional: enter your number now')
    await wrapper.find('[name="nationalId"]').setValue('3000000008')
    await submit()
    expect(authService.acceptInvitation).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('The national ID or Iqama number starts with 1 or 2.')
  })
})
