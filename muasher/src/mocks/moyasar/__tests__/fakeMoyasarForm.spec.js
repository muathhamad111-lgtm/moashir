import { afterAll, afterEach, beforeAll, describe, expect, it, vi } from 'vitest'
import { setupServer } from 'msw/node'
import { HttpResponse, http } from 'msw'
import { installFakeMoyasar } from '@/mocks/moyasar/fakeMoyasarForm'
import { SANDBOX_OUTCOME_ORDER, moyasarSandboxTexts } from '@/mocks/fixtures/moyasarSandbox'

const fulfilled = []
const server = setupServer(
  http.post(
    'https://api.moyasar.com/v1/payment_intents/:intent/fulfill',
    async ({ params, request }) => {
      const body = await request.json()
      fulfilled.push({ intent: params.intent, body })
      return HttpResponse.json(
        {
          id: 'mock_pay_42',
          status: 'paid',
          amount: 115000,
          currency: 'SAR',
          source: { type: 'creditcard', message: 'APPROVED' },
        },
        { status: 201 },
      )
    },
  ),
)

function sandbox() {
  const target = { document, fetch: (...args) => fetch(...args), location: { assign: vi.fn() } }
  installFakeMoyasar(target, moyasarSandboxTexts, SANDBOX_OUTCOME_ORDER)
  return target
}

function config(overrides = {}) {
  const element = document.createElement('div')
  document.body.append(element)
  return {
    element,
    amount: 115000,
    currency: 'SAR',
    publishable_api_key: 'pk_test_fake_example_moashir01',
    callback_url: 'https://moashir.example.sa/payments/p-1/return',
    language: 'en',
    methods: ['creditcard'],
    metadata: { moashir_payment_id: 'p-1' },
    use_payment_intent: true,
    payment_intent: { id: 'mock_pi_1', client_secret: 'mock_pi_1_secret_mock' },
    ...overrides,
  }
}

async function settle() {
  for (let round = 0; round < 10; round += 1) await new Promise((resolve) => setTimeout(resolve, 0))
}

beforeAll(() => server.listen({ onUnhandledRequest: 'error' }))
afterEach(() => {
  fulfilled.length = 0
  document.body.innerHTML = ''
})
afterAll(() => server.close())

describe('local fake of the Moyasar form', () => {
  it('fulfils the intent with the metadata, calls on_completed, then redirects to the callback with the id', async () => {
    const target = sandbox()
    const onCompleted = vi.fn().mockResolvedValue(undefined)
    const options = config({ on_completed: onCompleted })
    target.Moyasar.init(options)
    expect(options.element.querySelector('input')).toBeNull()
    options.element.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true }))
    await settle()
    expect(fulfilled[0]).toMatchObject({
      intent: 'mock_pi_1',
      body: { client_secret: 'mock_pi_1_secret_mock', metadata: { moashir_payment_id: 'p-1' } },
    })
    expect(onCompleted).toHaveBeenCalledWith(expect.objectContaining({ id: 'mock_pay_42' }))
    expect(target.location.assign).toHaveBeenCalledWith(
      'https://moashir.example.sa/payments/p-1/return?id=mock_pay_42&status=paid&message=APPROVED',
    )
  })

  it('stays on the form with an error when the platform verification fails', async () => {
    const target = sandbox()
    const options = config({ on_completed: vi.fn().mockRejectedValue(new Error('verify failed')) })
    target.Moyasar.init(options)
    options.element.querySelector('form').dispatchEvent(new Event('submit', { cancelable: true }))
    await settle()
    expect(target.location.assign).not.toHaveBeenCalled()
    expect(options.element.querySelector('.fake-moyasar__error').hidden).toBe(false)
    expect(options.element.querySelector('.fake-moyasar__error').textContent).toBe(
      moyasarSandboxTexts.en.interrupted,
    )
  })
})
