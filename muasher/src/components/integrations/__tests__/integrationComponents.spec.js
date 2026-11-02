import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import ConnectionTestPanel from '@/components/integrations/ConnectionTestPanel.vue'
import ValuesToCopyPanel from '@/components/integrations/ValuesToCopyPanel.vue'
import IntegrationBadges from '@/components/integrations/IntegrationBadges.vue'
import LastTestLine from '@/components/integrations/LastTestLine.vue'
import SmsSegmentCounter from '@/components/integrations/sms/SmsSegmentCounter.vue'
import { createTestGlobals } from '@/test-utils'

function mountWith(component, props, locale = 'en') {
  return mount(component, { props, global: createTestGlobals({ locale }) })
}

function providerFailure(kind) {
  return Promise.reject({
    response: { status: 502, headers: {} },
    normalized: { status: 502, code: 'provider_error', message: 'raw provider text', fieldErrors: { providerErrorKind: [kind] } },
  })
}

afterEach(() => vi.restoreAllMocks())

describe('ConnectionTestPanel', () => {
  it('shows the checks and safe facts of a passing test', async () => {
    const run = () =>
      Promise.resolve({
        data: {
          ok: true,
          latencyMs: 268,
          checks: [{ key: 'service_status', ok: true, detailCode: null }, { key: 'account_usable', ok: true, detailCode: null }],
          info: { balance: '1250.00', currency: 'SAR', accountStatus: 'active', senderVerifiedBy: 'test_sms' },
        },
      })
    const wrapper = mountWith(ConnectionTestPanel, { run })
    await wrapper.find('button').trigger('click')
    await flushPromises()
    expect(wrapper.find('.test-result__summary').text()).toContain('Connection test passed')
    expect(wrapper.text()).toContain('Account status and balance')
    expect(wrapper.text()).toContain('1250.00 SAR')
    expect(wrapper.text()).toContain('Confirmed by sending a test SMS')
    expect(wrapper.emitted('settled')).toHaveLength(1)
  })

  it('shows the failed checks of a 502 answer with the translated error kind, never the raw provider message', async () => {
    const result = { ok: false, latencyMs: 90, checks: [{ key: 'key_prefixes', ok: false, detailCode: 'prefix_mismatch' }], info: {}, errorKind: 'misconfigured' }
    const run = () =>
      Promise.reject({
        response: { status: 502, headers: {} },
        normalized: { status: 502, code: 'provider_error', message: 'raw provider text', fieldErrors: { providerErrorKind: ['misconfigured'] }, meta: { result } },
      })
    const wrapper = mountWith(ConnectionTestPanel, { run })
    await wrapper.find('button').trigger('click')
    await flushPromises()
    expect(wrapper.find('.test-result--failed .test-result__summary').text()).toContain('Connection test failed')
    expect(wrapper.text()).toContain('Key prefixes of the environment')
    expect(wrapper.text()).toContain('must both start with the prefix of this environment')
    expect(wrapper.find('.provider-feedback').text()).toContain('needs a setting on its side')
    expect(wrapper.text()).not.toContain('raw provider text')
    expect(wrapper.emitted('settled')[0][0]).toEqual(result)
  })

  it('translates the not-configured answer by its code', async () => {
    const run = () => Promise.reject({ response: { status: 409, headers: {} }, normalized: { status: 409, code: 'integration.not_configured', message: 'server text', fieldErrors: {}, meta: { kind: 'sms' } } })
    const wrapper = mountWith(ConnectionTestPanel, { run }, 'ar')
    await wrapper.find('button').trigger('click')
    await flushPromises()
    expect(wrapper.find('.provider-feedback--rejected').text()).toContain('لم يُضبط هذا الربط بعد')
  })

  it('shows the translated error kind and never the raw provider message', async () => {
    const wrapper = mountWith(ConnectionTestPanel, { run: () => providerFailure('auth_failed') })
    await wrapper.find('button').trigger('click')
    await flushPromises()
    expect(wrapper.find('.provider-feedback').text()).toContain('rejected the credentials')
    expect(wrapper.text()).not.toContain('raw provider text')
  })

  it('shows the wait time on 429 in Arabic', async () => {
    const run = () => Promise.reject({ response: { status: 429, headers: { 'retry-after': '41' } }, normalized: { status: 429, code: 'rate_limited', fieldErrors: {} } })
    const wrapper = mountWith(ConnectionTestPanel, { run }, 'ar')
    await wrapper.find('button').trigger('click')
    await flushPromises()
    expect(wrapper.find('.provider-feedback--rate_limited').text()).toContain('41')
  })

  it('stays disabled with a hint until keys are saved', () => {
    const wrapper = mountWith(ConnectionTestPanel, { run: vi.fn(), disabled: true, disabledHint: 'Save the keys first' })
    expect(wrapper.find('button').attributes('disabled')).toBeDefined()
    expect(wrapper.find('.connection-test__hint').text()).toBe('Save the keys first')
  })
})

describe('ValuesToCopyPanel', () => {
  it('copies a value with an accessible button and announces it', async () => {
    const writeText = vi.fn(() => Promise.resolve())
    vi.stubGlobal('navigator', { clipboard: { writeText } })
    const wrapper = mountWith(ValuesToCopyPanel, { items: [{ key: 'webhookUrl', label: 'Webhook URL', value: 'https://moashir.ttshub.live/api/v1/webhooks/moyasar' }] })
    const button = wrapper.find('button')
    expect(button.attributes('aria-label')).toBe('Copy Webhook URL')
    await button.trigger('click')
    await flushPromises()
    expect(writeText).toHaveBeenCalledWith('https://moashir.ttshub.live/api/v1/webhooks/moyasar')
    expect(wrapper.find('[aria-live="polite"]').text()).toBe('Webhook URL copied')
    vi.unstubAllGlobals()
  })
})

describe('status badges', () => {
  it('shows status, source and mode as text, not colour alone', () => {
    const wrapper = mountWith(IntegrationBadges, { source: 'environment', configured: true, active: false, mode: 'live', lastTest: null })
    expect(wrapper.text()).toContain('Configured — not tested')
    expect(wrapper.text()).toContain('Source: environment (bootstrap)')
    expect(wrapper.text()).toContain('Live')
  })

  it('reports not configured from configured, whatever the source', () => {
    const wrapper = mountWith(IntegrationBadges, { source: 'settings', configured: false, active: false, mode: 'test', lastTest: { ok: true } })
    expect(wrapper.text()).toContain('Not configured')
    expect(wrapper.text()).toContain('Source: settings')
  })

  it('names who ran the last test and the translated failure', () => {
    const wrapper = mountWith(LastTestLine, { lastTest: { at: '2026-10-31T07:30:00Z', ok: false, errorKind: 'unavailable', by: { id: 2, name: 'منيرة بنت خالد العتيبي' } } })
    expect(wrapper.text()).toContain('by منيرة بنت خالد العتيبي')
    expect(wrapper.text()).toContain('could not be reached')
  })
})

describe('SmsSegmentCounter', () => {
  it('warns only when a single-segment message needs more than one SMS', () => {
    const arabic = mountWith(SmsSegmentCounter, { text: 'ر'.repeat(71), singleSegment: true })
    expect(arabic.find('.sms-counter__summary').text()).toBe('71 characters · 2 SMS (estimated)')
    expect(arabic.find('.sms-counter__warning').exists()).toBe(true)
    const english = mountWith(SmsSegmentCounter, { text: 'a'.repeat(71), singleSegment: true })
    expect(english.find('.sms-counter__warning').exists()).toBe(false)
  })

  it('marks texts against the approved Msegat templates with the server placeholder rules', () => {
    const unapproved = mountWith(SmsSegmentCounter, { text: 'Code {code}', approvedBodies: ['Other text'] })
    expect(unapproved.find('.sms-counter__match.is-unapproved').text()).toContain('No matching approved template')
    const approved = mountWith(SmsSegmentCounter, { text: 'Code {code}', approvedBodies: ['Code {{1}}'] })
    expect(approved.find('.sms-counter__match.is-approved').exists()).toBe(true)
  })
})
