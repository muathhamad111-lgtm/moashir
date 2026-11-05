import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import Payment from '@/views/settings/Payment.vue'
import { paymentIntegrationService } from '@/services/paymentIntegrationService'
import { useAuthStore } from '@/stores/auth'
import { createTestGlobals } from '@/test-utils'

vi.mock('@/services/paymentIntegrationService', () => ({
  paymentIntegrationService: {
    get: vi.fn(),
    update: vi.fn(),
    testConnection: vi.fn(),
    registerWebhook: vi.fn(),
    uploadAssociationFile: vi.fn(),
    removeAssociationFile: vi.fn(),
    checkAssociationFile: vi.fn(),
  },
}))

const SECRET = 'sk_test_fake_example_replacement_9876'
const REASON = 'Checkout rehearsal before the award cycle opens.'
const ACTOR = { id: 2, name: 'منيرة بنت خالد العتيبي' }
const STORED = { set: true, last4: 'ex01', updatedAt: '2026-10-28T08:40:00Z', updatedBy: ACTOR }
const EMPTY = { set: false, last4: null, updatedAt: null, updatedBy: null }
const UNCONFIRMED = { confirmed: false, confirmedAt: null, confirmedBy: null }

function pair(overrides = {}) {
  return {
    configured: false,
    active: false,
    publishableKey: null,
    secretKey: EMPTY,
    webhookSecret: EMPTY,
    webhook: null,
    lastTest: null,
    ...overrides,
  }
}

function resource(overrides = {}) {
  return {
    provider: 'moyasar',
    mode: 'test',
    active: false,
    source: 'settings',
    isTestModeInProduction: false,
    checkoutPattern: 'intent',
    test: pair({
      configured: true,
      publishableKey: 'pk_test_fake_example_moashir01',
      secretKey: STORED,
      webhook: {
        id: 'wh_test_fake_example_0001',
        url: 'https://moashir.ttshub.live/api/v1/webhooks/moyasar',
        registeredAt: '2026-10-28T08:55:00Z',
      },
    }),
    live: pair(),
    methods: ['creditcard'],
    supportedNetworks: ['mada', 'visa'],
    applePayLabel: { ar: 'منصة مؤشر', en: 'Moashir Platform' },
    confirmations: {
      applePayDomain: UNCONFIRMED,
      stcpayActivation: {
        confirmed: true,
        confirmedAt: '2026-10-29T11:05:00Z',
        confirmedBy: ACTOR,
      },
    },
    linkedPaths: ['accredited'],
    applePayAssociationFile: {
      set: false,
      sizeBytes: null,
      sha256: null,
      uploadedAt: null,
      uploadedBy: null,
    },
    valuesToCopy: {
      webhookUrl: 'https://moashir.ttshub.live/api/v1/webhooks/moyasar',
      webhookMethod: 'POST',
      webhookEvents: 'all',
      applePayHost: 'moashir.ttshub.live',
      applePayAssociationUrl:
        'https://moashir.ttshub.live/.well-known/apple-developer-merchantid-domain-association',
      egressIps: ['203.0.113.24'],
    },
    checklist: [
      { key: 'live_account_activation', confirmable: false, confirmed: null },
      { key: 'apple_pay_domain', confirmable: true, confirmed: false },
      { key: 'stcpay_activation', confirmable: true, confirmed: true },
      { key: 'ip_whitelist', confirmable: false, confirmed: null },
      { key: 'manual_webhook', confirmable: false, confirmed: null },
    ],
    ...overrides,
  }
}

function reasonRequired() {
  return {
    response: { status: 422 },
    normalized: {
      status: 422,
      code: 'reason_required',
      message: 'A reason is required for this action.',
      fieldErrors: { reason: ['A reason is required for this action.'] },
      meta: {},
    },
  }
}

async function mountView({ superAdmin = false, locale = 'en' } = {}) {
  const globals = createTestGlobals({ locale })
  const wrapper = mount(Payment, { global: globals, attachTo: document.body })
  useAuthStore().setUser({
    id: 2,
    roles: ['system_admin'],
    permissions: ['settings.payment.manage'],
    isSuperAdmin: superAdmin,
  })
  await flushPromises()
  return wrapper
}

function replaceButton(wrapper) {
  return wrapper
    .findAll('.key-pair')[0]
    .findAll('button')
    .find((button) => button.text() === 'Replace')
}

async function replaceTestSecret(wrapper) {
  await replaceButton(wrapper).trigger('click')
  await wrapper.find('input[name="test.secretKey"]').setValue(SECRET)
  await wrapper.find('form').trigger('submit')
}

beforeEach(() => {
  vi.clearAllMocks()
  document.body.innerHTML = ''
  paymentIntegrationService.get.mockResolvedValue({ data: resource(), meta: {} })
  paymentIntegrationService.update.mockResolvedValue({ data: resource(), meta: {} })
})

describe('Payment integration screen', () => {
  it('shows stored secrets as metadata, the webhook, the checklist and the values to copy', async () => {
    const wrapper = await mountView()
    expect(wrapper.text()).toContain('•••• stored')
    expect(wrapper.text()).toContain('ends with ex01')
    expect(wrapper.findAll('.key-pair')[0].text()).toContain('Webhook registered')
    expect(wrapper.findAll('.key-pair')[1].text()).toContain('Webhook not registered')
    expect(wrapper.text()).toContain('https://moashir.ttshub.live/api/v1/webhooks/moyasar')
    expect(wrapper.text()).toContain('All events')
    expect(wrapper.text()).toContain('203.0.113.24')
    expect(wrapper.text()).toContain('Activate the Moyasar account for live payments')
    expect(wrapper.text()).toContain('Confirmed at')
    wrapper.unmount()
  })

  it('sends only the replaced secret once and never renders it after saving', async () => {
    const wrapper = await mountView()
    await replaceTestSecret(wrapper)
    await vi.waitFor(() => expect(paymentIntegrationService.update).toHaveBeenCalledOnce())
    await flushPromises()
    expect(paymentIntegrationService.update.mock.calls[0][0]).toEqual({
      test: { secretKey: SECRET },
    })
    expect(wrapper.html()).not.toContain(SECRET)
    expect(wrapper.find('input[name="test.secretKey"]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('asks the super admin for a reason before a secret change is saved', async () => {
    const wrapper = await mountView({ superAdmin: true })
    await replaceTestSecret(wrapper)
    await vi.waitFor(() => expect(document.body.textContent).toContain('Reason for this change'))
    expect(paymentIntegrationService.update).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('opens the reason dialog when the server answers reason_required and resubmits with the reason', async () => {
    paymentIntegrationService.update.mockRejectedValueOnce(reasonRequired())
    const wrapper = await mountView()
    await wrapper.find('input[name="methods-creditcard"]').setValue(false)
    await wrapper.find('form').trigger('submit')
    await vi.waitFor(() => expect(document.body.textContent).toContain('Reason for this change'))
    const dialog = document.body.querySelector('.modal__panel')
    const textarea = dialog.querySelector('[name="reason"]')
    textarea.value = REASON
    textarea.dispatchEvent(new Event('input'))
    dialog.querySelector('button[type="submit"]').click()
    await vi.waitFor(() => expect(paymentIntegrationService.update).toHaveBeenCalledTimes(2))
    expect(paymentIntegrationService.update.mock.calls[1][0]).toEqual({
      methods: [],
      reason: REASON,
    })
    wrapper.unmount()
  })

  it('keeps live disabled until the live pair passes a test and shows the production test-mode banner', async () => {
    paymentIntegrationService.get.mockResolvedValue({
      data: resource({ isTestModeInProduction: true }),
      meta: {},
    })
    const wrapper = await mountView()
    const live = wrapper
      .findAll('input[type="radio"]')
      .find((input) => input.element.value === 'live')
    expect(live.attributes('disabled')).toBeDefined()
    expect(wrapper.find('.test-mode-banner').text()).toContain('Test mode — no real charge.')
    wrapper.unmount()
  })

  it('shows the forbidden state on 403', async () => {
    paymentIntegrationService.get.mockRejectedValue({
      response: { status: 403 },
      normalized: { message: 'You are not allowed to perform this action.' },
    })
    const wrapper = await mountView({ locale: 'ar' })
    expect(wrapper.text()).toContain('لا تملك صلاحية الوصول إلى هذه الشاشة')
    wrapper.unmount()
  })
})
