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
const NDA = {
  id: 501,
  version: '1.0',
  title: { ar: 'اتفاقية عدم الإفصاح', en: 'Non-disclosure agreement' },
  body: {
    ar: [{ type: 'paragraph', text: 'نص' }],
    en: [{ type: 'paragraph', text: 'Keep it confidential.' }],
  },
  changeSummary: null,
}

let wrapper

async function settle() {
  for (let round = 0; round < 3; round += 1) {
    await new Promise((resolve) => setTimeout(resolve, 10))
    await flushPromises()
  }
}

async function mountInvitation(nda) {
  authService.checkInvitation.mockResolvedValue({
    data: { usable: true, nationalIdMasked: null, nda },
  })
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  await globals.router.push('/auth/login')
  wrapper = mount(AcceptInvitation, {
    props: { token: 'invite-token' },
    global: globals,
    attachTo: document.body,
  })
  await settle()
  await wrapper.find('[name="password"]').setValue(STRONG)
  await wrapper.find('[name="passwordConfirmation"]').setValue(STRONG)
}

function scrollToEnd() {
  const text = wrapper.get('[data-testid="nda-text"]')
  Object.defineProperty(text.element, 'scrollHeight', { value: 800, configurable: true })
  Object.defineProperty(text.element, 'clientHeight', { value: 200, configurable: true })
  text.element.scrollTop = 600
  return text.trigger('scroll')
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

describe('agreement step at invitation acceptance', () => {
  it('moves from the password to the agreement, then accepts with the version once the text was read', async () => {
    await mountInvitation(NDA)
    expect(wrapper.text()).toContain('1. Password')

    await wrapper.find('form').trigger('submit')
    await settle()
    expect(authService.acceptInvitation).not.toHaveBeenCalled()
    expect(wrapper.find('[data-testid="invitation-nda"]').exists()).toBe(true)
    expect(
      wrapper.get('[data-testid="invitation-nda-accept"]').attributes('disabled'),
    ).toBeDefined()

    await scrollToEnd()
    await wrapper.get('[data-testid="invitation-nda-accept"]').trigger('click')
    await settle()

    expect(authService.acceptInvitation).toHaveBeenCalledWith(
      'invite-token',
      expect.objectContaining({ ndaVersionId: 501, password: STRONG }),
    )
  })

  it('goes back to the password step', async () => {
    await mountInvitation(NDA)
    await wrapper.find('form').trigger('submit')
    await settle()

    await wrapper.findAll('[data-testid="invitation-nda"] button').at(1).trigger('click')
    await settle()

    expect(wrapper.find('[name="password"]').exists()).toBe(true)
  })

  it('submits directly when the roles are out of scope', async () => {
    await mountInvitation(null)
    await wrapper.find('form').trigger('submit')
    await settle()

    expect(authService.acceptInvitation).toHaveBeenCalledWith(
      'invite-token',
      expect.not.objectContaining({ ndaVersionId: expect.anything() }),
    )
  })
})
