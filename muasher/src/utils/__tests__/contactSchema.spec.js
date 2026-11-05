import { describe, expect, it } from 'vitest'
import { contactSchema } from '@/utils/contactSchema'

const t = (key, params) => (params ? `${key}:${JSON.stringify(params)}` : key)

const VALID = {
  name: 'سارة العبدالله',
  email: 'sara@example.sa',
  mobile: '',
  organization: '',
  enquiryType: 'general',
  message: 'أرغب في معرفة متطلبات التقييم المعتمد.',
  privacyConsent: true,
}

async function errorsOf(values, isGuest = true) {
  try {
    await contactSchema(t, { isGuest }).validate(values, { abortEarly: false })
    return {}
  } catch (error) {
    return Object.fromEntries(error.inner.map((issue) => [issue.path, issue.message]))
  }
}

describe('contactSchema', () => {
  it('accepts a complete guest message', async () => {
    expect(await errorsOf(VALID)).toEqual({})
  })

  it('mirrors the server rules for guests', async () => {
    const errors = await errorsOf({
      ...VALID,
      name: 'س',
      email: 'not-an-email',
      mobile: '0612345678',
      enquiryType: 'invoices',
      message: 'قصيرة',
      privacyConsent: false,
    })
    expect(Object.keys(errors).sort()).toEqual(
      ['email', 'enquiryType', 'message', 'mobile', 'name', 'privacyConsent'].sort(),
    )
    expect(errors.privacyConsent).toBe('validation.mustAgree')
    expect(errors.mobile).toBe('public.home.contact.form.mobileFormat')
  })

  it('accepts both mobile formats and enforces the length limits', async () => {
    expect(await errorsOf({ ...VALID, mobile: '+966551234567' })).toEqual({})
    expect(await errorsOf({ ...VALID, mobile: '0551234567' })).toEqual({})
    const errors = await errorsOf({
      ...VALID,
      organization: 'ج'.repeat(201),
      message: 'م'.repeat(5001),
    })
    expect(errors.organization).toBe('validation.maxChars:{"max":200}')
    expect(errors.message).toBe('validation.maxChars:{"max":5000}')
  })

  it('does not require the identity fields for signed-in senders', async () => {
    expect(await errorsOf({ ...VALID, name: '', email: '', organization: '' }, false)).toEqual({})
  })
})
