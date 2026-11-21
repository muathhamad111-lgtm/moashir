import { describe, expect, it } from 'vitest'
import { integrationStatusOf } from '@/utils/integrations/integrationStatus'
import {
  SECRET_ACTIONS,
  keptSecret,
  secretPatch,
  withSecrets,
} from '@/utils/integrations/secretValue'
import { smsSegmentEstimate } from '@/utils/integrations/smsSegments'
import { matchesApprovedTemplate } from '@/utils/integrations/smsTemplates'
import {
  failedResultOf,
  firstFieldMessage,
  integrationErrorKey,
  knownErrorKind,
  providerErrorKindOf,
  retryAfterSecondsOf,
  testFailureOf,
  TEST_FAILURES,
} from '@/utils/integrations/providerError'
import {
  changedValues,
  withoutEmptyObjects,
  writesEverything,
} from '@/utils/integrations/partialPayload'
import { checklistSteps } from '@/utils/integrations/checklist'

describe('integrationStatusOf', () => {
  it('maps configured, activation and the last test to the five statuses', () => {
    expect(integrationStatusOf({ configured: false, active: true, lastTest: { ok: true } })).toBe(
      'not_configured',
    )
    expect(integrationStatusOf({ configured: true, active: true, lastTest: null })).toBe('active')
    expect(integrationStatusOf({ configured: true, active: false, lastTest: null })).toBe(
      'configured_untested',
    )
    expect(integrationStatusOf({ configured: true, active: false, lastTest: { ok: true } })).toBe(
      'test_passed',
    )
    expect(integrationStatusOf({ configured: true, active: false, lastTest: { ok: false } })).toBe(
      'test_failed',
    )
  })
})

describe('partial payloads and checklists', () => {
  it('keeps only changed values unless the environment bootstrap needs a full write', () => {
    expect(
      changedValues(
        { mode: 'live', methods: ['creditcard'] },
        { mode: 'test', methods: ['creditcard'] },
      ),
    ).toEqual({ mode: 'live' })
    expect(changedValues({ mode: 'test' }, { mode: 'test' }, { full: true })).toEqual({
      mode: 'test',
    })
    expect(writesEverything({ source: 'environment' })).toBe(true)
    expect(writesEverything({ source: 'settings' })).toBe(false)
    expect(withoutEmptyObjects({ taqnyat: {}, msegat: { senderName: 'Moashir' } })).toEqual({
      msegat: { senderName: 'Moashir' },
    })
  })

  it('labels checklist items by key and marks only confirmable ones as done or pending', () => {
    const steps = checklistSteps(
      [
        { key: 'apple_pay_domain', confirmable: true, confirmed: true },
        { key: 'ip_whitelist', confirmable: false, confirmed: null },
      ],
      (key) => `label:${key}`,
    )
    expect(steps).toEqual([
      { key: 'apple_pay_domain', label: 'label:apple_pay_domain', done: true },
      { key: 'ip_whitelist', label: 'label:ip_whitelist' },
    ])
  })
})

describe('secret values', () => {
  it('omits kept secrets, sends replacements and an explicit clear', () => {
    expect(secretPatch(keptSecret())).toBeUndefined()
    expect(secretPatch({ action: SECRET_ACTIONS.REPLACE, value: '' })).toBeUndefined()
    expect(secretPatch({ action: SECRET_ACTIONS.REPLACE, value: 'sk_test_fake_example' })).toBe(
      'sk_test_fake_example',
    )
    expect(secretPatch({ action: SECRET_ACTIONS.CLEAR, value: 'ignored' })).toEqual({ clear: true })
  })

  it('merges only the changed secrets into a payload', () => {
    const payload = withSecrets(
      { senderName: 'Moashir' },
      { bearerToken: keptSecret(), apiKey: { action: SECRET_ACTIONS.CLEAR, value: '' } },
    )
    expect(payload).toEqual({ senderName: 'Moashir', apiKey: { clear: true } })
  })
})

describe('smsSegmentEstimate', () => {
  it('uses 160/153 for Latin text and 70/67 for Arabic text', () => {
    expect(smsSegmentEstimate('Hello')).toEqual({ characters: 5, segments: 1, encoding: 'gsm' })
    expect(smsSegmentEstimate('a'.repeat(161)).segments).toBe(2)
    expect(smsSegmentEstimate('ر'.repeat(70))).toEqual({
      characters: 70,
      segments: 1,
      encoding: 'unicode',
    })
    expect(smsSegmentEstimate('ر'.repeat(71)).segments).toBe(2)
    expect(smsSegmentEstimate('').segments).toBe(0)
  })

  it('counts extended GSM characters twice', () => {
    expect(smsSegmentEstimate('{x}').characters).toBe(5)
  })
})

describe('matchesApprovedTemplate', () => {
  it('compares texts like the server: whitespace normalized and placeholder forms unified', () => {
    expect(matchesApprovedTemplate('رمز  التحقق {code}', ['رمز التحقق {{1}}'])).toBe(true)
    expect(
      matchesApprovedTemplate('Code {code} for {minutes} minutes', ['Code [x] for %s minutes']),
    ).toBe(true)
    expect(matchesApprovedTemplate('Code xxxx now', ['Code {code} now'])).toBe(true)
    expect(matchesApprovedTemplate('نص آخر', ['رمز التحقق {{1}}'])).toBe(false)
    expect(matchesApprovedTemplate('رمز التحقق {code}', [])).toBe(false)
  })
})

describe('provider errors', () => {
  const providerError = {
    normalized: { code: 'provider_error', fieldErrors: { providerErrorKind: ['auth_failed'] } },
    response: { status: 502 },
  }

  it('reads the provider error kind and falls back to unknown', () => {
    expect(providerErrorKindOf(providerError)).toBe('auth_failed')
    expect(
      providerErrorKindOf({
        normalized: { fieldErrors: { providerErrorKind: ['raw provider text'] } },
      }),
    ).toBe('unknown')
    expect(knownErrorKind(undefined)).toBe('unknown')
  })

  it('classifies failures and reads Retry-After', () => {
    expect(testFailureOf(providerError)).toBe(TEST_FAILURES.PROVIDER)
    expect(testFailureOf({ response: { status: 429 } })).toBe(TEST_FAILURES.RATE_LIMITED)
    expect(testFailureOf({ response: { status: 403 } })).toBe(TEST_FAILURES.FORBIDDEN)
    expect(
      testFailureOf({
        response: { status: 409 },
        normalized: { code: 'integration.not_configured' },
      }),
    ).toBe(TEST_FAILURES.REJECTED)
    expect(
      testFailureOf({
        response: { status: 404 },
        normalized: { code: 'payment.apple_pay_file_missing' },
      }),
    ).toBe(TEST_FAILURES.REJECTED)
    expect(
      testFailureOf({
        response: { status: 422 },
        normalized: { code: 'sms.recipient_not_in_test_numbers' },
      }),
    ).toBe(TEST_FAILURES.REJECTED)
    expect(
      testFailureOf({ response: { status: 422 }, normalized: { code: 'validation_failed' } }),
    ).toBe(TEST_FAILURES.INVALID)
    expect(testFailureOf(new Error('network'))).toBe(TEST_FAILURES.UNEXPECTED)
    expect(retryAfterSecondsOf({ response: { headers: { 'retry-after': '42' } } })).toBe(42)
    expect(retryAfterSecondsOf({ response: { headers: {} } })).toBeNull()
  })

  it('reads the failed result, the first field message and the translation key of a known code', () => {
    const result = {
      ok: false,
      checks: [{ key: 'secret_key_valid', ok: false, detailCode: 'auth_failed' }],
    }
    expect(failedResultOf({ normalized: { meta: { result } } })).toBe(result)
    expect(
      firstFieldMessage({
        normalized: {
          fieldErrors: { providerErrorKind: ['auth_failed'], recipient: ['Not allowed'] },
        },
      }),
    ).toBe('Not allowed')
    expect(integrationErrorKey('integration.not_configured')).toBe(
      'integrations.errors.integration.not_configured',
    )
    expect(integrationErrorKey('something_else')).toBeNull()
  })
})
