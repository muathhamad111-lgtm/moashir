import { describe, expect, it } from 'vitest'
import { REDACTED, scrubText, scrubUrl } from '@/utils/monitoring/scrubText'
import {
  SENTRY_SCRUBBING,
  scrubBreadcrumb,
  scrubData,
  scrubEvent,
  scrubObject,
} from '@/utils/monitoring/scrubEvent'

describe('scrubText', () => {
  it.each([
    ['info@alamal.example.sa wrote', `${REDACTED} wrote`],
    ['call +966512345678 now', `call ${REDACTED} now`],
    ['call 0551234567 now', `call ${REDACTED} now`],
    ['id 1012345678 found', `id ${REDACTED} found`],
    ['سجل الحضور.xlsx uploaded', `${REDACTED} uploaded`],
    ['Bearer abc.def-123 sent', `${REDACTED} sent`],
    ['key pk_test_abcdefABCDEF123', `key ${REDACTED}`],
    ['password=Hunter-2026', `password=${REDACTED}`],
    ['/auth/reset-password/AbCdEf123456', `/auth/reset-password/${REDACTED}`],
    ['/auth/invitations/invite-sarah-whitfield-2026', `/auth/invitations/${REDACTED}`],
    [`/files/${'a1'.repeat(20)}/download`, `/files/${REDACTED}/download`],
  ])('masks %s', (input, expected) => {
    expect(scrubText(input)).toBe(expected)
  })

  it('keeps safe text and non-strings untouched', () => {
    expect(scrubText('GET /api/v1/assessments/14')).toBe('GET /api/v1/assessments/14')
    expect(scrubText('ACC-2026-000014')).toBe('ACC-2026-000014')
    expect(scrubText(null)).toBeNull()
    expect(scrubText(42)).toBe(42)
  })
})

describe('scrubUrl', () => {
  it('drops the query string and the fragment', () => {
    expect(scrubUrl('https://moashir.example.sa/api/v1/entities?search=info@x.sa#top')).toBe(
      'https://moashir.example.sa/api/v1/entities',
    )
    expect(scrubUrl('/auth/reset-password/AbCdEf123456?email=a@b.sa')).toBe(
      `/auth/reset-password/${REDACTED}`,
    )
  })
})

describe('scrubEvent', () => {
  const event = {
    event_id: 'e1',
    message: 'Failed for info@alamal.example.sa',
    transaction: '/admin/contact-messages?search=Sara',
    user: { id: 12, email: 'info@alamal.example.sa', ip_address: '10.0.0.1' },
    request: {
      method: 'POST',
      url: 'https://moashir.example.sa/api/v1/contact-messages?ref=mail',
      query_string: 'ref=mail',
      cookies: { session: 'secret' },
      data: { message: 'Private text' },
      headers: { Accept: 'application/json', Cookie: 'a=b', 'Content-Type': 'application/json' },
    },
    exception: { values: [{ type: 'Error', value: 'Mobile 0551234567 refused' }] },
    extra: {
      action: 'contactMessages.submit',
      payloadShape: { email: 'string', message: 'string' },
      reason: 'The board asked to close the account',
    },
    tags: { route: 'contact' },
    breadcrumbs: [
      { category: 'xhr', data: { method: 'GET', url: '/api/v1/entities?search=Namaa' } },
      { category: 'console', message: 'value info@x.sa', data: { arguments: ['info@x.sa'] } },
    ],
    spans: [
      {
        description: 'GET /api/v1/audit-logs?filter[actor_id]=12',
        data: { 'http.query': 'filter=1', url: '/api/v1/audit-logs?x=1' },
      },
    ],
  }
  const scrubbed = scrubEvent(event)

  it('removes the user and keeps only safe request parts', () => {
    expect(scrubbed.user).toBeUndefined()
    expect(scrubbed.request).toEqual({
      method: 'POST',
      url: 'https://moashir.example.sa/api/v1/contact-messages',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
    })
  })

  it('masks the message, the transaction and the exception values', () => {
    expect(scrubbed.message).toBe(`Failed for ${REDACTED}`)
    expect(scrubbed.transaction).toBe('/admin/contact-messages')
    expect(scrubbed.exception.values[0].value).toBe(`Mobile ${REDACTED} refused`)
  })

  it('redacts form values in the extra context but keeps the action names', () => {
    expect(scrubbed.extra.action).toBe('contactMessages.submit')
    expect(scrubbed.extra.reason).toBe(REDACTED)
    expect(scrubbed.extra.payloadShape).toEqual({ email: REDACTED, message: REDACTED })
  })

  it('scrubs breadcrumbs and spans', () => {
    expect(scrubbed.breadcrumbs[0].data.url).toBe('/api/v1/entities')
    expect(scrubbed.breadcrumbs[1].message).toBe(`value ${REDACTED}`)
    expect(scrubbed.breadcrumbs[1].data.arguments).toBeUndefined()
    expect(scrubbed.spans[0].description).toBe('GET /api/v1/audit-logs')
    expect(scrubbed.spans[0].data).toEqual({ url: '/api/v1/audit-logs' })
  })

  it('is safe on empty input', () => {
    expect(scrubEvent(null)).toBeNull()
    expect(scrubBreadcrumb(undefined)).toBeUndefined()
    expect(scrubData(null)).toBeNull()
    expect(scrubObject(null)).toEqual({})
  })

  it('is the configuration given to the GlitchTip SDK', () => {
    expect(SENTRY_SCRUBBING).toEqual({
      sendDefaultPii: false,
      beforeSend: scrubEvent,
      beforeSendTransaction: scrubEvent,
      beforeBreadcrumb: scrubBreadcrumb,
    })
  })
})
