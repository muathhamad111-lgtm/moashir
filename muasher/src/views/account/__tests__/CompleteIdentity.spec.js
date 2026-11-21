import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import CompleteIdentity from '@/views/account/CompleteIdentity.vue'
import { identityService } from '@/services/identityService'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'
import { ROUTE_NAMES } from '@/constants/routeNames'

vi.mock('@/services/identityService', () => ({
  identityService: { provideNationalId: vi.fn() },
}))

async function mountStep(locale = 'ar', redirect = '/account') {
  const globals = createTestGlobals({ locale })
  useAuthStore(globals.pinia).setUser(
    sessionUserFor(['assessor'], { nationalIdRequired: true, hasNationalId: false }),
  )
  await globals.router.push({ name: ROUTE_NAMES.ACCOUNT_COMPLETE_IDENTITY, query: { redirect } })
  const wrapper = mount(CompleteIdentity, { global: globals, attachTo: document.body })
  return { wrapper, globals }
}

async function settle() {
  for (let round = 0; round < 3; round += 1) {
    await new Promise((resolve) => setTimeout(resolve, 10))
    await flushPromises()
  }
}

beforeEach(() => {
  vi.clearAllMocks()
  identityService.provideNationalId.mockResolvedValue({ data: { nationalIdMasked: '••••••0008' } })
})

describe('complete identity step', () => {
  it.each(['ar', 'en'])('explains the step and the purpose of the number (%s)', async (locale) => {
    const { wrapper, globals } = await mountStep(locale)
    const t = globals.i18n.global.t
    expect(wrapper.text()).toContain(t('nationalIdentity.complete.title'))
    expect(wrapper.text()).toContain(t('nationalIdentity.field.purpose'))
    wrapper.unmount()
  })

  it('refuses an invalid number without calling the API', async () => {
    const { wrapper } = await mountStep('en')
    await wrapper.find('input[name="nationalId"]').setValue('1000000009')
    await wrapper.find('form').trigger('submit')
    await settle()
    expect(identityService.provideNationalId).not.toHaveBeenCalled()
    await vi.waitFor(() =>
      expect(wrapper.text()).toContain('Enter a valid 10-digit national ID or Iqama number.'),
    )
    wrapper.unmount()
  })

  it('saves a valid number with Western digits, records it on the session and goes back', async () => {
    const { wrapper, globals } = await mountStep('ar')
    await wrapper.find('input[name="nationalId"]').setValue('١٠٠٠٠٠٠٠٠٨')
    await wrapper.find('form').trigger('submit')
    await settle()
    await vi.waitFor(() =>
      expect(identityService.provideNationalId).toHaveBeenCalledWith('1000000008'),
    )
    await settle()
    const auth = useAuthStore(globals.pinia)
    expect(auth.nationalIdMissing).toBe(false)
    expect(auth.user.nationalIdMasked).toBe('••••••0008')
    await vi.waitFor(() => expect(globals.router.currentRoute.value.fullPath).toBe('/account'), {
      timeout: 5000,
    })
    wrapper.unmount()
  })
})
