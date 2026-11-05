import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { setupServer } from 'msw/node'
import Checkout from '@/views/payments/Checkout.vue'
import { paymentsHandlers } from '@/mocks/handlers/payments'
import { moyasarSandboxHandlers } from '@/mocks/handlers/moyasarSandbox'
import { sanctumHandlers } from '@/mocks/handlers/sanctum'
import { servicePricesHandlers } from '@/mocks/handlers/servicePrices'
import { dashboardHandlers } from '@/mocks/handlers/dashboard'
import { entityBoardHandlers } from '@/mocks/handlers/entityBoard'
import { financeState } from '@/mocks/stores/finance/financeState'
import { paymentsService } from '@/services/paymentsService'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { installFakeMoyasar } from '@/mocks/moyasar/fakeMoyasarForm'
import { SANDBOX_OUTCOME_ORDER, moyasarSandboxTexts } from '@/mocks/fixtures/moyasarSandbox'
import { resetFinanceState } from '@/mocks/stores/finance/financeState'
import { resetIntegrationsState } from '@/mocks/stores/integrations/integrationsState'
import { clearMockSession, seedMockSession } from '@/test-utils/mockApi'
import { createTestGlobals } from '@/test-utils'

const server = setupServer(
  ...sanctumHandlers,
  ...paymentsHandlers,
  ...moyasarSandboxHandlers,
  ...servicePricesHandlers,
  ...entityBoardHandlers,
  ...dashboardHandlers,
)
const PDF = new File(['%PDF-1.4\n1 0 obj\n%%EOF\n'], 'bank-transfer-receipt.pdf', {
  type: 'application/pdf',
})
const assign = vi.fn()

function installSandbox() {
  const target = { document, fetch: (...args) => fetch(...args), location: { assign } }
  installFakeMoyasar(target, moyasarSandboxTexts, SANDBOX_OUTCOME_ORDER)
  window.Moyasar = target.Moyasar
}

async function settle(rounds = 30) {
  for (let round = 0; round < rounds; round += 1) {
    await flushPromises()
    await new Promise((resolve) => setTimeout(resolve, 20))
  }
}

let globals = null

async function mountCheckout(purpose) {
  globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  const wrapper = mount(Checkout, {
    props: { purpose },
    global: { ...globals, stubs: { teleport: true } },
    attachTo: document.body,
  })
  await settle()
  return wrapper
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
beforeEach(() => {
  resetFinanceState()
  resetIntegrationsState()
  installSandbox()
  seedMockSession({ id: 5001, roles: ['entity'], entityId: 5001 })
})
afterEach(() => {
  delete window.Moyasar
  clearMockSession()
  assign.mockClear()
  document.body.innerHTML = ''
})
afterAll(() => server.close())

describe('checkout view', () => {
  it('opens the order on demand, shows the server pricing and the TEST notice, and mounts the Moyasar form', async () => {
    const wrapper = await mountCheckout('accredited')
    expect(wrapper.find('[data-fake-moyasar]').exists()).toBe(false)
    expect(wrapper.find('.checkout-summary__pending').exists()).toBe(true)
    await wrapper.find('.checkout__start .btn--primary').trigger('click')
    expect(wrapper.find('.checkout__start .btn--primary').attributes('disabled')).toBeDefined()
    await settle()
    expect(wrapper.find('.test-mode-notice').exists()).toBe(true)
    expect(wrapper.find('.pricing-breakdown__row--total').text()).toContain('1,150.00')
    expect(wrapper.find('.order-countdown__time').text()).toMatch(/^(29|30):\d{2}$/)
    expect(wrapper.find('[data-fake-moyasar="form"]').exists()).toBe(true)
    wrapper.unmount()
  })

  it('verifies the gateway payment with the platform before the redirect', async () => {
    const wrapper = await mountCheckout('accredited')
    await wrapper.find('.checkout__start .btn--primary').trigger('click')
    await settle()
    wrapper
      .find('[data-fake-moyasar="form"]')
      .element.dispatchEvent(new Event('submit', { cancelable: true }))
    await settle()
    expect(assign).toHaveBeenCalledWith(
      expect.stringMatching(/\/payments\/[0-9a-f-]{36}\/return\?id=mock_pay_/),
    )
    wrapper.unmount()
  })

  it('reports the open payment of the same service instead of a second checkout', async () => {
    const wrapper = await mountCheckout('bag')
    await wrapper.find('.checkout__start .btn--primary').trigger('click')
    await settle()
    expect(wrapper.find('.payment-notice--warning').exists()).toBe(true)
    expect(wrapper.find('[data-fake-moyasar]').exists()).toBe(false)
    wrapper.unmount()
  })

  it('offers both channels with the electronic option chosen first', async () => {
    const wrapper = await mountCheckout('accredited')
    const options = wrapper.findAll('.method-option')
    expect(options.map((option) => option.attributes('data-value'))).toEqual([
      'electronic',
      'bank_transfer',
    ])
    expect(options[0].classes()).toContain('is-selected')
    expect(wrapper.find('.checkout-summary .pricing-breakdown__row--total').text()).toContain(
      '1,150.00',
    )
    wrapper.unmount()
  })

  it('skips the choice when only one channel is available', async () => {
    financeState().channels = ['bank_transfer']
    const transferOnly = await mountCheckout('accredited')
    expect(transferOnly.find('.method-option').exists()).toBe(false)
    expect(transferOnly.find('.transfer-panel').exists()).toBe(true)
    expect(transferOnly.find('.transfer-panel__change').exists()).toBe(false)
    transferOnly.unmount()
    financeState().channels = ['electronic']
    const cardOnly = await mountCheckout('accredited')
    expect(cardOnly.find('.method-option').exists()).toBe(false)
    expect(cardOnly.find('.checkout__intro').exists()).toBe(true)
    cardOnly.unmount()
  })

  it('submits a bank transfer with its receipt and opens the review state', async () => {
    const submitted = { id: '3f0c9a52-6d1e-4b7a-9c21-5a1e00000099', status: 'under-review' }
    const submit = vi
      .spyOn(paymentsService, 'submitTransfer')
      .mockResolvedValue({ data: submitted, meta: {} })
    const wrapper = await mountCheckout('accredited')
    await wrapper.find('.method-option[data-value="bank_transfer"] input').setValue(true)
    await wrapper.find('.checkout__start .btn--primary').trigger('click')
    await settle(5)
    expect(wrapper.find('.copy-row__value--latin').text()).toContain(
      'SA03 8000 0000 6080 1016 7519',
    )
    const send = wrapper.find('.transfer-panel__submit .btn--primary')
    expect(send.attributes('disabled')).toBeDefined()
    const input = wrapper.find('input[type="file"][name="proof"]')
    Object.defineProperty(input.element, 'files', { value: [PDF], configurable: true })
    await input.trigger('change')
    expect(wrapper.find('.proof-chip').text()).toContain('bank-transfer-receipt.pdf')
    await wrapper.find('.transfer-panel__submit .btn--primary').trigger('click')
    await settle()
    const [order, key] = submit.mock.calls[0]
    expect(order).toEqual({
      purpose: 'accredited',
      bankAccountId: '7c2d9e41-5b3a-4f6e-9d18-2a6b00000001',
      proof: PDF,
    })
    expect(key).toMatch(/^[0-9a-f-]{36}$/)
    expect(globals.router.currentRoute.value.name).toBe(ROUTE_NAMES.PAYMENTS_COMPLETE)
    expect(globals.router.currentRoute.value.params.id).toBe(submitted.id)
    submit.mockRestore()
    wrapper.unmount()
  })

  it('shows the transfer under review instead of a new checkout', async () => {
    seedMockSession({ id: 6002, roles: ['entity'], entityId: 6002 })
    const wrapper = await mountCheckout('bag')
    expect(wrapper.find('.payment-notice--info').text()).toContain('under review')
    expect(wrapper.find('.method-option').exists()).toBe(false)
    wrapper.unmount()
  })
})
