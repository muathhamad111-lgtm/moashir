import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import ChangePasswordCard from '@/components/account/ChangePasswordCard.vue'
import { authService } from '@/services/authService'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/authService', () => ({
  authService: { changePassword: vi.fn() },
}))

const CURRENT = 'Current-Pass-2026!'
const NEXT = 'Najd-Plateau-2026!'

function mountCard(locale = 'en') {
  return mount(ChangePasswordCard, {
    global: createTestGlobals({ locale }),
    attachTo: document.body,
  })
}

async function fill(wrapper, values) {
  for (const [name, value] of Object.entries(values)) {
    await wrapper.find(`input[name="${name}"]`).setValue(value)
  }
}

// vee-validate validates asynchronously, so the submit handler needs a few ticks to finish.
async function settle() {
  for (let round = 0; round < 5; round += 1) {
    await flushPromises()
    await new Promise((resolve) => setTimeout(resolve, 0))
  }
}

async function submit(wrapper) {
  await wrapper.find('[data-testid="change-password-form"]').trigger('submit')
  await settle()
}

const VALID = { currentPassword: CURRENT, password: NEXT, passwordConfirmation: NEXT }

beforeEach(() => vi.clearAllMocks())

describe('change password card', () => {
  it('saves in place: sends the three fields, confirms and clears the form', async () => {
    authService.changePassword.mockResolvedValue({})
    const wrapper = mountCard()
    await fill(wrapper, VALID)
    await submit(wrapper)
    expect(authService.changePassword).toHaveBeenCalledWith(VALID)
    expect(wrapper.find('[data-testid="change-password-saved"]').text()).toContain(
      'new password was saved',
    )
    const values = wrapper.findAll('input').map((input) => input.element.value)
    expect(values).toEqual(['', '', ''])
    wrapper.unmount()
  })

  it('checks the fields before calling the API', async () => {
    const wrapper = mountCard()
    await fill(wrapper, { password: NEXT, passwordConfirmation: 'Different-2026!' })
    await submit(wrapper)
    expect(authService.changePassword).not.toHaveBeenCalled()
    expect(wrapper.text()).toContain('The passwords do not match')
    wrapper.unmount()
  })

  it('shows the API rejection of the current password on its field', async () => {
    authService.changePassword.mockRejectedValue(
      Object.assign(new Error('invalid'), {
        normalized: {
          status: 422,
          message: 'The current password is incorrect.',
          fieldErrors: { currentPassword: ['The current password is incorrect.'] },
        },
      }),
    )
    const wrapper = mountCard()
    await fill(wrapper, VALID)
    await submit(wrapper)
    expect(wrapper.text()).toContain('The current password is incorrect.')
    expect(wrapper.find('[data-testid="change-password-saved"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('shows a rate limit in place on the form', async () => {
    authService.changePassword.mockRejectedValue(
      Object.assign(new Error('throttled'), {
        normalized: { status: 429, message: 'Too many attempts. Try again later.' },
      }),
    )
    const wrapper = mountCard('ar')
    await fill(wrapper, VALID)
    await submit(wrapper)
    expect(wrapper.find('[data-testid="change-password-error"]').text()).toContain(
      'Too many attempts',
    )
    wrapper.unmount()
  })
})
