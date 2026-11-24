import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { setActivePinia } from 'pinia'
import NdaGateHost from '@/components/identity/nda/NdaGateHost.vue'
import { ndaService } from '@/services/ndaService'
import { useAuthStore } from '@/stores/auth'
import { useNdaGateStore } from '@/stores/ndaGate'
import { requestNdaAcceptance, resetNdaGate } from '@/api/ndaEvents'
import { createTestGlobals } from '@/test-utils'
import { sessionUserFor } from '@/test-utils/session'
import { ROUTE_NAMES } from '@/constants/routeNames'

vi.mock('@/services/ndaService', () => ({
  ndaService: { mine: vi.fn(), accept: vi.fn() },
}))

const AGREEMENT = {
  id: 501,
  version: '1.1',
  title: { ar: 'اتفاقية عدم الإفصاح', en: 'Non-disclosure agreement' },
  body: {
    ar: [{ type: 'paragraph', text: 'نص الاتفاقية' }],
    en: [
      { type: 'heading', level: 2, text: '1. Purpose' },
      { type: 'paragraph', text: 'Keep information confidential.' },
    ],
  },
  changeSummary: { ar: [], en: [{ type: 'paragraph', text: 'A screenshot clause was added.' }] },
}

async function mountHost({ required = true } = {}) {
  const globals = createTestGlobals({ locale: 'en' })
  setActivePinia(globals.pinia)
  useAuthStore(globals.pinia).setUser(
    sessionUserFor(['assessor'], { ndaAcceptanceRequired: required }),
  )
  await globals.router.push({ name: ROUTE_NAMES.APP_DASHBOARD })
  const wrapper = mount(NdaGateHost, {
    global: { ...globals, stubs: { teleport: true } },
    attachTo: document.body,
  })
  await flushPromises()
  return wrapper
}

function scrollToEnd(wrapper) {
  const text = wrapper.get('[data-testid="nda-text"]')
  Object.defineProperty(text.element, 'scrollHeight', { value: 900, configurable: true })
  Object.defineProperty(text.element, 'clientHeight', { value: 300, configurable: true })
  text.element.scrollTop = 600
  return text.trigger('scroll')
}

beforeEach(() => {
  vi.clearAllMocks()
  resetNdaGate()
  ndaService.mine.mockResolvedValue({ data: { acceptanceRequired: true, version: AGREEMENT } })
  ndaService.accept.mockResolvedValue({ data: { accepted: true } })
})

afterEach(() => {
  document.body.innerHTML = ''
})

describe('mandatory agreement modal', () => {
  it('opens after sign-in when the session says an acceptance is due, with no way to close it', async () => {
    const wrapper = await mountHost()

    const dialog = wrapper.get('[role="dialog"]')
    expect(dialog.attributes('aria-modal')).toBe('true')
    expect(dialog.attributes('aria-labelledby')).toBeTruthy()
    expect(wrapper.text()).toContain('Non-disclosure agreement')
    expect(wrapper.text()).toContain('A screenshot clause was added.')
    expect(wrapper.find('.modal__close').exists()).toBe(false)

    await wrapper.get('.modal').trigger('keydown', { key: 'Escape' })
    await wrapper.get('.modal__backdrop').trigger('click')
    expect(useNdaGateStore().open).toBe(true)
    expect(wrapper.find('[role="dialog"]').exists()).toBe(true)
  })

  it('keeps the approve button in one place first in the footer, with the hint beside it until the end', async () => {
    const wrapper = await mountHost()
    const footer = () => wrapper.get('.modal__footer').element
    const accept = () => wrapper.get('[data-testid="nda-accept"]')

    expect(footer().firstElementChild).toBe(accept().element)
    expect(accept().attributes('aria-describedby')).toBe('nda-accept-hint')
    expect(wrapper.get('#nda-accept-hint').text()).toBe(
      'Scroll to the end of the agreement to accept.',
    )

    await scrollToEnd(wrapper)
    expect(footer().firstElementChild).toBe(accept().element)
    expect(accept().attributes('disabled')).toBeUndefined()
    expect(accept().attributes('aria-describedby')).toBeUndefined()
    expect(wrapper.find('#nda-accept-hint').exists()).toBe(false)
  })

  it('enables the single approve button only once the text was scrolled to the end', async () => {
    const wrapper = await mountHost()
    const accept = wrapper.get('[data-testid="nda-accept"]')
    expect(accept.attributes('disabled')).toBeDefined()

    await scrollToEnd(wrapper)
    expect(wrapper.get('[data-testid="nda-accept"]').attributes('disabled')).toBeUndefined()
    await wrapper.get('[data-testid="nda-accept"]').trigger('click')
    await flushPromises()

    expect(ndaService.accept).toHaveBeenCalledWith(501)
    expect(useNdaGateStore().open).toBe(false)
    expect(useAuthStore().user.ndaAcceptanceRequired).toBe(false)
  })

  it('opens when the server refuses a request with nda.acceptance_required', async () => {
    const wrapper = await mountHost({ required: false })
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)

    requestNdaAcceptance()
    await flushPromises()

    expect(wrapper.find('[role="dialog"]').exists()).toBe(true)
    expect(ndaService.mine).toHaveBeenCalled()
  })

  it('reloads the text when the version changed in the meantime', async () => {
    const wrapper = await mountHost()
    await scrollToEnd(wrapper)
    ndaService.accept.mockRejectedValueOnce({
      normalized: { code: 'nda.version_not_current', message: 'Not current' },
    })

    await wrapper.get('[data-testid="nda-accept"]').trigger('click')
    await flushPromises()

    expect(useNdaGateStore().open).toBe(true)
    expect(ndaService.mine).toHaveBeenCalledTimes(2)
  })
})
