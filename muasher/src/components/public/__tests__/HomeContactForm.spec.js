import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import HomeContactForm from '@/components/public/HomeContactForm.vue'
import { contactMessagesService } from '@/services/contactMessagesService'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/contactMessagesService', () => ({
  contactMessagesService: { submit: vi.fn() },
}))

const MESSAGE = 'أرغب في معرفة متطلبات التقييم المعتمد والمدة المتوقعة.'

function apiRejection(status, fieldErrors = {}) {
  return Object.assign(new Error(`HTTP ${status}`), {
    normalized: { status, fieldErrors, message: 'rejected' },
  })
}

function mountForm({ user = null, locale = 'ar' } = {}) {
  const globals = createTestGlobals({ locale })
  if (user) useAuthStore(globals.pinia).setUser(user)
  return mount(HomeContactForm, { global: globals, attachTo: document.body })
}

async function fill(wrapper, { guest = true } = {}) {
  if (guest) {
    await wrapper.find('input[name="name"]').setValue('سارة العبدالله')
    await wrapper.find('input[name="email"]').setValue('sara@example.sa')
    await wrapper.find('input[name="organization"]').setValue('جمعية الأمل الخيرية')
  }
  await wrapper.find('input[name="mobile"]').setValue('0551234567')
  await wrapper.find('select[name="enquiryType"]').setValue('payments')
  await wrapper.find('textarea[name="message"]').setValue(MESSAGE)
  await wrapper.find('input[type="checkbox"]').setValue(true)
}

async function settle() {
  for (let round = 0; round < 5; round += 1) {
    await flushPromises()
    await new Promise((resolve) => setTimeout(resolve, 0))
  }
}

async function submit(wrapper) {
  await wrapper.find('form').trigger('submit')
  await settle()
}

beforeEach(() => vi.clearAllMocks())

describe('HomeContactForm', () => {
  it('lists the seven enquiry types with the approved payments and privacy labels', () => {
    const wrapper = mountForm()
    const options = wrapper.findAll('select[name="enquiryType"] option').slice(1)
    expect(options.map((option) => option.attributes('value'))).toEqual([
      'general',
      'self_assessment',
      'accredited_assessment',
      'payments',
      'technical_support',
      'privacy_request',
      'other',
    ])
    expect(options[3].text()).toBe('الدفع والإيصالات')
    expect(options[5].text()).toBe('طلب يتعلق بالبيانات الشخصية')
    expect(wrapper.text()).not.toMatch(/فاتور|فواتير/)
    wrapper.unmount()
  })

  it('sends a guest message with the honeypot and shows the reference number', async () => {
    contactMessagesService.submit.mockResolvedValue({
      data: { reference: 'CNT-2026-000014', createdAt: '2026-11-04T09:00:00+00:00' },
    })
    const wrapper = mountForm()
    await fill(wrapper)
    await submit(wrapper)

    expect(contactMessagesService.submit).toHaveBeenCalledWith({
      name: 'سارة العبدالله',
      email: 'sara@example.sa',
      organization: 'جمعية الأمل الخيرية',
      mobile: '0551234567',
      enquiryType: 'payments',
      message: MESSAGE,
      privacyConsent: true,
      website: '',
    })
    expect(wrapper.find('.contact-form__success').attributes('role')).toBe('status')
    expect(wrapper.find('.contact-form__reference-value').text()).toBe('CNT-2026-000014')
    expect(wrapper.find('.contact-form__reference-value').attributes('dir')).toBe('ltr')

    await wrapper.find('.contact-form__success .btn').trigger('click')
    expect(wrapper.find('form').exists()).toBe(true)
    expect(wrapper.find('textarea[name="message"]').element.value).toBe('')
    wrapper.unmount()
  })

  it('blocks submission with client errors that mirror the server rules', async () => {
    const wrapper = mountForm()
    await submit(wrapper)
    expect(contactMessagesService.submit).not.toHaveBeenCalled()
    expect(wrapper.findAll('.field__error').length).toBeGreaterThanOrEqual(5)
    wrapper.unmount()
  })

  it('maps the server field errors onto the fields', async () => {
    contactMessagesService.submit.mockRejectedValue(
      apiRejection(422, { email: ['البريد الإلكتروني مستخدم بصيغة غير صالحة.'] }),
    )
    const wrapper = mountForm()
    await fill(wrapper)
    await submit(wrapper)
    expect(wrapper.text()).toContain('البريد الإلكتروني مستخدم بصيغة غير صالحة.')
    expect(wrapper.find('.contact-form__success').exists()).toBe(false)
    wrapper.unmount()
  })

  it('explains the rate limit and a generic failure in an alert', async () => {
    contactMessagesService.submit.mockRejectedValueOnce(apiRejection(429))
    const wrapper = mountForm({ locale: 'en' })
    await fill(wrapper)
    await submit(wrapper)
    expect(wrapper.find('.contact-form__alert').attributes('role')).toBe('alert')
    expect(wrapper.find('.contact-form__alert').text()).toContain('several messages')

    contactMessagesService.submit.mockRejectedValueOnce(apiRejection(500))
    await submit(wrapper)
    expect(wrapper.find('.contact-form__alert').text()).toContain('couldn’t send')
    wrapper.unmount()
  })

  it('prefills the identity of a signed-in sender as read-only and sends only the message fields', async () => {
    contactMessagesService.submit.mockResolvedValue({ data: { reference: 'CNT-2026-000015' } })
    const wrapper = mountForm({
      user: {
        id: 7,
        name: 'نورة السالم',
        email: 'noura@alamal.example.sa',
        roles: ['entity'],
        permissions: [],
        entity: { name: { ar: 'جمعية الأمل الخيرية', en: 'Al-Amal Charity' } },
      },
    })
    await settle()
    const name = wrapper.find('input[name="name"]')
    expect(name.element.value).toBe('نورة السالم')
    expect(name.attributes('readonly')).toBeDefined()
    expect(wrapper.find('input[name="organization"]').element.value).toBe('جمعية الأمل الخيرية')
    expect(wrapper.find('.contact-form__signed-in').exists()).toBe(true)

    await fill(wrapper, { guest: false })
    await submit(wrapper)
    const payload = contactMessagesService.submit.mock.calls[0][0]
    expect(payload).not.toHaveProperty('name')
    expect(payload).not.toHaveProperty('email')
    expect(payload).not.toHaveProperty('organization')
    expect(payload).toMatchObject({ enquiryType: 'payments', privacyConsent: true })
    wrapper.unmount()
  })

  it('keeps the honeypot out of the accessibility tree and the tab order', () => {
    const wrapper = mountForm()
    const trap = wrapper.find('.contact-form__trap')
    expect(trap.attributes('aria-hidden')).toBe('true')
    expect(trap.find('input').attributes('tabindex')).toBe('-1')
    expect(trap.find('input').attributes('name')).toBe('website')
    wrapper.unmount()
  })
})
