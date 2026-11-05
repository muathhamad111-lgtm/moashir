import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import PhoneVerificationCard from '@/components/entities/profile/portal/PhoneVerificationCard.vue'
import { phoneVerificationService } from '@/services/phoneVerificationService'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/phoneVerificationService', () => ({
  phoneVerificationService: { start: vi.fn(), confirm: vi.fn() },
}))

const PHONE = '+966500000123'
const SMS_UNAVAILABLE = 'Text messages cannot be sent right now. Please try again later.'

function challenge() {
  return {
    challengeId: 'p-1',
    channel: 'sms',
    length: 6,
    expiresAt: new Date(Date.now() + 300 * 1000).toISOString(),
    resendAvailableAt: new Date(Date.now() + 60 * 1000).toISOString(),
  }
}

function mountCard(profile, props = {}) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  return mount(PhoneVerificationCard, {
    props: { profile, canEdit: true, ...props },
    global: globals,
  })
}

async function typeCode(wrapper, code) {
  const boxes = wrapper.findAll('.otp-input__box')
  for (const [index, digit] of [...code].entries()) await boxes[index].setValue(digit)
  await flushPromises()
}

beforeEach(() => {
  vi.clearAllMocks()
  phoneVerificationService.start.mockResolvedValue({ data: challenge() })
})

describe('PhoneVerificationCard', () => {
  it('asks for a saved number first', () => {
    const wrapper = mountCard({ phone: null, phoneVerified: false })
    expect(wrapper.text()).toContain('Add a mobile number in this section and save it')
    expect(wrapper.find('button').exists()).toBe(false)
  })

  it('shows a verified number with its badge and no action', () => {
    const wrapper = mountCard({ phone: PHONE, phoneVerified: true })
    expect(wrapper.find('.phone-verification__number').attributes('dir')).toBe('ltr')
    expect(wrapper.find('.badge--success').text()).toBe('Verified')
    expect(wrapper.text()).toContain('You can sign in with this number')
    expect(wrapper.find('button').exists()).toBe(false)
  })

  it('explains why the number must be verified and waits for an unsaved number', async () => {
    const wrapper = mountCard({ phone: PHONE, phoneVerified: false }, { phoneDirty: true })
    expect(wrapper.find('.badge--warning').text()).toBe('Not verified')
    expect(wrapper.text()).toContain('Signing in with the mobile number needs a verified number')
    expect(wrapper.text()).toContain('Save the new number first')
    expect(wrapper.find('button').attributes('disabled')).toBeDefined()
  })

  it('sends a code by SMS, confirms it and reports the verified number', async () => {
    phoneVerificationService.confirm.mockResolvedValue({
      data: { phoneVerified: true, phoneVerifiedAt: '2026-11-05T09:00:00Z' },
    })
    const wrapper = mountCard({ phone: PHONE, phoneVerified: false })
    await wrapper.find('button').trigger('click')
    await flushPromises()
    expect(phoneVerificationService.start).toHaveBeenCalled()
    expect(wrapper.text()).toContain('We sent a code by text message. It is valid for 5 minutes.')
    await typeCode(wrapper, '482913')
    expect(phoneVerificationService.confirm).toHaveBeenCalledWith({
      challengeId: 'p-1',
      code: '482913',
    })
    expect(wrapper.emitted('verified')).toHaveLength(1)
    expect(wrapper.find('.phone-verification__form').exists()).toBe(false)
  })

  it('shows the server answer when the SMS provider is not configured', async () => {
    phoneVerificationService.start.mockRejectedValue({
      response: { status: 503 },
      normalized: { status: 503, code: 'phone.sms_unavailable', message: SMS_UNAVAILABLE },
    })
    const wrapper = mountCard({ phone: PHONE, phoneVerified: false })
    await wrapper.find('button').trigger('click')
    await flushPromises()
    const error = wrapper.find('.phone-verification__error')
    expect(error.attributes('role')).toBe('alert')
    expect(error.text()).toBe(SMS_UNAVAILABLE)
    expect(wrapper.find('.phone-verification__form').exists()).toBe(false)
  })

  it('keeps the code form open after a wrong code and lets the user cancel', async () => {
    phoneVerificationService.confirm.mockRejectedValue({
      response: { status: 422 },
      normalized: { status: 422, code: 'otp.invalid', message: 'otp.invalid' },
    })
    const wrapper = mountCard({ phone: PHONE, phoneVerified: false })
    await wrapper.find('button').trigger('click')
    await flushPromises()
    await typeCode(wrapper, '000000')
    expect(wrapper.find('.phone-verification__error').text()).toBe(
      'The verification code is incorrect.',
    )
    const cancel = wrapper.findAll('button').find((button) => button.text() === 'Cancel')
    await cancel.trigger('click')
    expect(wrapper.find('.phone-verification__form').exists()).toBe(false)
    expect(wrapper.find('.phone-verification__error').exists()).toBe(false)
  })
})
