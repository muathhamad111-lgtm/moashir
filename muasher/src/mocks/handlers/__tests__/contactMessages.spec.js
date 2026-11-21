import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest'
import { setupServer } from 'msw/node'
import { contactMessagesHandlers } from '@/mocks/handlers/contactMessages'
import { resetContactMessages, storedContactMessages } from '@/mocks/stores/contactMessages'
import { MOCK_API_BASE, clearMockSession, mockFetch, seedMockSession } from '@/test-utils/mockApi'

const server = setupServer(...contactMessagesHandlers)

const GUEST = {
  name: 'سارة العبدالله',
  email: 'sara@example.sa',
  enquiry_type: 'general',
  message: 'أرغب في معرفة متطلبات التقييم المعتمد.',
  privacy_consent: true,
  website: '',
}

async function post(body, locale = 'ar') {
  const res = await mockFetch(`${MOCK_API_BASE}/contact-messages`, {
    method: 'POST',
    headers: { 'content-type': 'application/json', 'accept-language': locale },
    body: JSON.stringify(body),
  })
  return { status: res.status, body: await res.json() }
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => {
  resetContactMessages()
  clearMockSession()
})
afterEach(() => server.resetHandlers())
afterAll(() => server.close())

describe('contact messages mock (Task-42 contract)', () => {
  it('accepts a guest message with a CNT reference', async () => {
    const { status, body } = await post(GUEST)
    expect(status).toBe(201)
    expect(body.data.reference).toMatch(/^CNT-\d{4}-\d{6}$/)
    expect(body.data.createdAt).toBeTruthy()
    expect(storedContactMessages()).toHaveLength(1)
  })

  it('answers 422 with field errors in the request language', async () => {
    const { status, body } = await post(
      { ...GUEST, email: 'x', enquiry_type: 'invoices', privacy_consent: false, mobile: '12' },
      'en',
    )
    expect(status).toBe(422)
    expect(body.code).toBe('validation_failed')
    expect(Object.keys(body.errors).sort()).toEqual(
      ['email', 'enquiryType', 'mobile', 'privacyConsent'].sort(),
    )
    expect(body.errors.email[0]).toBe('Enter a valid e-mail address.')
  })

  it('does not require the identity of a signed-in sender', async () => {
    seedMockSession({ id: 7000, roles: ['entity'] })
    const { status } = await post({ ...GUEST, name: '', email: '' })
    expect(status).toBe(201)
  })

  it('answers the honeypot with a decoy reference and stores nothing', async () => {
    const { status, body } = await post({ ...GUEST, website: 'https://spam.example' })
    expect(status).toBe(201)
    expect(body.data.reference).toMatch(/^CNT-/)
    expect(storedContactMessages()).toHaveLength(0)
  })

  it('rate limits after five messages in ten minutes', async () => {
    for (let index = 0; index < 5; index += 1) expect((await post(GUEST)).status).toBe(201)
    const { status, body } = await post(GUEST)
    expect(status).toBe(429)
    expect(body.code).toBe('rate_limited')
  })
})
