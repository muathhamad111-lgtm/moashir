import { afterAll, afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import { setupServer } from 'msw/node'
import Checkout from '@/views/payments/Checkout.vue'
import { paymentsHandlers } from '@/mocks/handlers/payments'
import { moyasarSandboxHandlers } from '@/mocks/handlers/moyasarSandbox'
import { sanctumHandlers } from '@/mocks/handlers/sanctum'
import { installFakeMoyasar } from '@/mocks/moyasar/fakeMoyasarForm'
import { SANDBOX_OUTCOME_ORDER, moyasarSandboxTexts } from '@/mocks/fixtures/moyasarSandbox'
import { resetFinanceState } from '@/mocks/stores/finance/financeState'
import { resetIntegrationsState } from '@/mocks/stores/integrations/integrationsState'
import { clearMockSession, seedMockSession } from '@/test-utils/mockApi'
import { createTestGlobals } from '@/test-utils'

const server = setupServer(...sanctumHandlers, ...paymentsHandlers, ...moyasarSandboxHandlers)
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

function mountCheckout(purpose) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  return mount(Checkout, {
    props: { purpose },
    global: { ...globals, stubs: { teleport: true } },
    attachTo: document.body,
  })
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
    const wrapper = mountCheckout('accredited')
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
    const wrapper = mountCheckout('accredited')
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
    const wrapper = mountCheckout('bag')
    await wrapper.find('.checkout__start .btn--primary').trigger('click')
    await settle()
    expect(wrapper.find('.payment-notice--warning').exists()).toBe(true)
    expect(wrapper.find('[data-fake-moyasar]').exists()).toBe(false)
    wrapper.unmount()
  })
})
