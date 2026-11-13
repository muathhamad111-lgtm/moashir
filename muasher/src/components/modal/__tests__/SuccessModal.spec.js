import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import SuccessModal from '@/components/modal/SuccessModal.vue'
import { success, useSuccess } from '@/composables/useSuccess'
import { SUCCESS_AUTO_CLOSE_MS } from '@/constants/modal'
import { createTestGlobals } from '@/test-utils'

let wrapper

function factory(locale = 'ar') {
  const globals = createTestGlobals({ locale })
  wrapper = mount(SuccessModal, {
    attachTo: document.body,
    global: { ...globals, stubs: { teleport: true } },
  })
  return wrapper
}

function panel() {
  return document.querySelector('.modal__panel')
}

afterEach(() => {
  success.close()
  vi.useRealTimers()
  wrapper?.unmount()
  document.body.innerHTML = ''
})

describe('SuccessModal + useSuccess', () => {
  it('shows a centred success icon, the default title and one OK button', async () => {
    factory()
    success.show()
    await flushPromises()
    expect(document.querySelector('.modal__icon--success').getAttribute('aria-hidden')).toBe(
      'true',
    )
    expect(document.querySelector('.modal--centered')).not.toBeNull()
    expect(document.querySelector('.modal__title').textContent).toBe('تمت العملية بنجاح')
    const buttons = [...document.querySelectorAll('.modal__footer .btn')]
    expect(buttons.map((button) => button.textContent.trim())).toEqual(['حسناً'])
    expect(document.activeElement).toBe(buttons[0])
  })

  it('uses an action title and one line that describes the dialog', async () => {
    factory('en')
    success.show({ title: 'Your request was sent for review', message: 'ACC-2026-000004' })
    await flushPromises()
    expect(document.querySelector('.modal__title').textContent).toBe(
      'Your request was sent for review',
    )
    const descriptionId = panel().getAttribute('aria-describedby')
    expect(document.getElementById(descriptionId).textContent).toBe('ACC-2026-000004')
  })

  it('closes on OK and resolves the promise', async () => {
    factory()
    const closed = useSuccess().showSuccess({ title: 'اعتُمد التحويل' })
    await flushPromises()
    document.querySelector('.modal__footer .btn').click()
    await expect(closed).resolves.toBeUndefined()
    await flushPromises()
    expect(panel()).toBeNull()
  })

  it('closes on Escape', async () => {
    factory()
    success.show({ title: 'اعتُمد التحويل' })
    await flushPromises()
    panel().dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await flushPromises()
    expect(useSuccess().successState.isOpen).toBe(false)
  })

  it('closes by itself after a few seconds', async () => {
    vi.useFakeTimers()
    factory()
    success.show({ title: 'أُسند الطلب' })
    await flushPromises()
    vi.advanceTimersByTime(SUCCESS_AUTO_CLOSE_MS)
    await flushPromises()
    expect(useSuccess().successState.isOpen).toBe(false)
  })

  it('stays open once the user moves the pointer onto it or uses the keyboard', async () => {
    vi.useFakeTimers()
    factory()
    success.show({ title: 'أُسند الطلب' })
    await flushPromises()
    panel().dispatchEvent(new Event('pointerenter'))
    vi.advanceTimersByTime(SUCCESS_AUTO_CLOSE_MS * 2)
    await flushPromises()
    expect(useSuccess().successState.isOpen).toBe(true)

    success.show({ title: 'أُلغيت الشهادة' })
    await flushPromises()
    panel().dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true }))
    vi.advanceTimersByTime(SUCCESS_AUTO_CLOSE_MS * 2)
    await flushPromises()
    expect(useSuccess().successState.isOpen).toBe(true)
  })

  it('restarts the timer when a new success replaces the open one', async () => {
    vi.useFakeTimers()
    factory()
    const first = success.show({ title: 'نُشرت الصفحة' })
    await flushPromises()
    vi.advanceTimersByTime(SUCCESS_AUTO_CLOSE_MS - 100)
    success.show({ title: 'نُشر الإصدار' })
    await flushPromises()
    await expect(first).resolves.toBeUndefined()
    vi.advanceTimersByTime(200)
    await flushPromises()
    expect(useSuccess().successState.isOpen).toBe(true)
    expect(document.querySelector('.modal__title').textContent).toBe('نُشر الإصدار')
  })
})
