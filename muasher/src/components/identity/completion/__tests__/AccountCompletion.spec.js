import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import AccountCompletionDialog from '@/components/identity/completion/AccountCompletionDialog.vue'
import AccountCompletionBanner from '@/components/identity/completion/AccountCompletionBanner.vue'
import AccountCompletionHost from '@/components/identity/completion/AccountCompletionHost.vue'
import { identityService } from '@/services/identityService'
import { useAuthStore } from '@/stores/auth'
import { useAccountCompletionStore } from '@/stores/accountCompletion'
import { toast } from '@/composables/useToast'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'
import { ROUTE_NAMES } from '@/constants/routeNames'

vi.mock('@/services/identityService', () => ({
  identityService: {
    provideNationalId: vi.fn(),
    recordGender: vi.fn(),
    myChanges: vi.fn(),
  },
}))

const SIGN_IN_ONE = '2026-11-21T20:00:00Z'
const SIGN_IN_TWO = '2026-11-22T08:00:00Z'

function setup({ locale = 'en', user = {}, roles = ['assessor'] } = {}) {
  const globals = createTestGlobals({ locale })
  setActivePinia(globals.pinia)
  useAuthStore(globals.pinia).setUser(
    sessionUserFor(roles, {
      nationalIdRequired: true,
      nationalIdStatus: 'missing',
      genderMissing: true,
      sessionExpiresAt: SIGN_IN_ONE,
      ...user,
    }),
  )
  return globals
}

function mountWith(component, globals) {
  return mount(component, {
    global: { ...globals, stubs: { teleport: true } },
    attachTo: document.body,
  })
}

beforeEach(() => {
  localStorage.clear()
  vi.clearAllMocks()
  vi.spyOn(toast, 'success').mockImplementation(() => {})
  identityService.recordGender.mockResolvedValue({})
  identityService.provideNationalId.mockResolvedValue({
    data: { nationalIdStatus: 'pending', nationalIdMasked: null },
  })
})

afterEach(() => {
  document.body.innerHTML = ''
})

describe('account completion store', () => {
  it('asks for what is missing until the holder defers it for this sign-in only', () => {
    setup()
    const completion = useAccountCompletionStore()
    expect(completion.shouldPrompt).toBe(true)
    completion.open()
    completion.defer()
    expect(completion.shouldPrompt).toBe(false)
    expect(completion.showsBanner).toBe(true)

    useAuthStore().patchUser({ sessionExpiresAt: SIGN_IN_TWO })
    expect(completion.shouldPrompt).toBe(true)
    expect(completion.showsBanner).toBe(false)
  })

  it('never nags while the number awaits approval or once everything is complete', () => {
    setup({ user: { nationalIdStatus: 'pending', genderMissing: false } })
    const completion = useAccountCompletionStore()
    expect(completion.isNeeded).toBe(false)
    expect(completion.shouldPrompt).toBe(false)
    expect(completion.showsBanner).toBe(false)
  })

  it('treats a not required number as nothing to ask for', () => {
    setup({
      user: { nationalIdRequired: false, nationalIdStatus: 'not_required', genderMissing: false },
    })
    expect(useAccountCompletionStore().isNeeded).toBe(false)
  })
})

describe('account completion dialog', () => {
  it.each(['ar', 'en'])(
    'asks for the number and the gender with the rules shown (%s)',
    async (locale) => {
      const globals = setup({ locale })
      useAccountCompletionStore().open()
      const wrapper = mountWith(AccountCompletionDialog, globals)
      await flushPromises()
      const t = globals.i18n.global.t
      const input = document.querySelector('input[name="nationalId"]')
      expect(input.getAttribute('maxlength')).toBe('10')
      expect(input.getAttribute('inputmode')).toBe('numeric')
      expect(document.body.textContent).toContain(t('nationalIdentity.field.hint'))
      expect(document.body.textContent).toContain(t('nationalIdentity.complete.approval'))
      expect(document.querySelectorAll('input[name="gender"]')).toHaveLength(2)
      wrapper.unmount()
    },
  )

  it('says which rule the number breaks and sends nothing', async () => {
    const globals = setup()
    useAccountCompletionStore().open()
    const wrapper = mountWith(AccountCompletionDialog, globals)
    await flushPromises()
    const input = wrapper.find('input[name="nationalId"]')
    await input.setValue('3000000008')
    await wrapper.find('input[name="gender"][value="female"]').setValue(true)
    await wrapper.find('form').trigger('submit')
    await vi.waitFor(() =>
      expect(document.body.textContent).toContain(
        'The national ID or Iqama number starts with 1 or 2.',
      ),
    )
    expect(identityService.provideNationalId).not.toHaveBeenCalled()

    await input.setValue('100000')
    await wrapper.find('form').trigger('submit')
    await vi.waitFor(() =>
      expect(document.body.textContent).toContain(
        'The national ID or Iqama number has exactly 10 digits.',
      ),
    )
    wrapper.unmount()
  })

  it('saves the gender and sends the number for approval, then shows it pending', async () => {
    const globals = setup()
    const completion = useAccountCompletionStore()
    completion.open()
    const wrapper = mountWith(AccountCompletionDialog, globals)
    await flushPromises()
    await wrapper.find('input[name="nationalId"]').setValue('١٠٠٠٠٠٠٠٠٩')
    await wrapper.find('input[name="gender"][value="male"]').setValue(true)
    await wrapper.find('form').trigger('submit')
    await vi.waitFor(() => expect(completion.dialogOpen).toBe(false))

    expect(identityService.recordGender).toHaveBeenCalledWith('male')
    expect(identityService.provideNationalId).toHaveBeenCalledWith('1000000009')
    const auth = useAuthStore()
    expect(auth.nationalIdStatus).toBe('pending')
    expect(auth.user.genderMissing).toBe(false)
    expect(completion.dialogOpen).toBe(false)
    expect(toast.success).toHaveBeenCalledWith(
      'Your number was sent to the administration for approval.',
    )
    wrapper.unmount()
  })

  it('tells the Super Admin the number is saved directly', async () => {
    const globals = setup({ user: { isSuperAdmin: true, genderMissing: false } })
    useAccountCompletionStore().open()
    const wrapper = mountWith(AccountCompletionDialog, globals)
    await flushPromises()
    expect(document.body.textContent).toContain(
      globals.i18n.global.t('nationalIdentity.complete.savedDirectly'),
    )
    expect(document.querySelector('input[name="gender"]')).toBeNull()
    wrapper.unmount()
  })

  it('defers with «Later» and leaves the banner, which opens the dialog again', async () => {
    const globals = setup()
    const completion = useAccountCompletionStore()
    completion.open()
    const dialog = mountWith(AccountCompletionDialog, globals)
    const banner = mountWith(AccountCompletionBanner, globals)
    await flushPromises()
    const later = [...document.querySelectorAll('button')].find(
      (button) => button.textContent.trim() === 'Later',
    )
    later.click()
    await flushPromises()
    expect(completion.dialogOpen).toBe(false)
    expect(banner.find('[data-testid="account-completion-banner"]').text()).toContain(
      'Your account needs a national ID or Iqama number and a gender.',
    )

    await banner.find('[data-testid="account-completion-action"]').trigger('click')
    expect(completion.dialogOpen).toBe(true)
    dialog.unmount()
    banner.unmount()
  })
})

describe('account completion host', () => {
  it('opens the dialog on a signed-in page, never on the sign-in pages', async () => {
    const globals = setup()
    await globals.router.push({ name: ROUTE_NAMES.AUTH_LOGIN })
    const wrapper = mountWith(AccountCompletionHost, globals)
    await flushPromises()
    expect(useAccountCompletionStore().dialogOpen).toBe(false)

    await globals.router.push({ name: ROUTE_NAMES.ACCOUNT_PROFILE })
    await flushPromises()
    expect(useAccountCompletionStore().dialogOpen).toBe(true)
    wrapper.unmount()
  })
})

function fileRefusal(state) {
  return {
    response: { status: 403 },
    normalized: {
      status: 403,
      code: 'national_id.required_for_files',
      message: 'refused',
      meta: { nationalIdStatus: state },
    },
  }
}

describe('file access refusal', () => {
  it('opens the refusal dialog for a missing number and offers to enter it', async () => {
    const globals = setup({ user: { genderMissing: false } })
    const completion = useAccountCompletionStore()
    expect(completion.refuseFileAccess(new Error('offline'))).toBe(false)
    expect(completion.refuseFileAccess(fileRefusal('missing'))).toBe(true)
    const wrapper = mountWith(AccountCompletionHost, globals)
    await flushPromises()
    expect(document.querySelector('[data-testid="file-access-refusal"]').textContent).toContain(
      'You can open or download assessment evidence once you enter your national ID',
    )
    document.querySelector('[data-testid="file-access-enter-id"]').click()
    await flushPromises()
    expect(completion.refusedFileAccess).toBeNull()
    expect(completion.dialogOpen).toBe(true)
    expect(completion.asksNationalId).toBe(true)
    wrapper.unmount()
  })

  it('says the number awaits approval, without asking for it again', async () => {
    const globals = setup({ locale: 'ar', user: { genderMissing: false } })
    const completion = useAccountCompletionStore()
    completion.refuseFileAccess(fileRefusal('pending'))
    const wrapper = mountWith(AccountCompletionHost, globals)
    await flushPromises()
    expect(useAuthStore().nationalIdStatus).toBe('pending')
    expect(document.querySelector('[data-testid="file-access-refusal"]').textContent).toContain(
      globals.i18n.global.t('nationalIdentity.files.pending'),
    )
    expect(document.querySelector('[data-testid="file-access-enter-id"]')).toBeNull()
    wrapper.unmount()
  })
})
