import { afterEach, describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import ConfirmModal from '@/components/modal/ConfirmModal.vue'
import { useConfirm } from '@/composables/useConfirm'
import { createTestGlobals } from '@/test-utils'

let wrapper

function factory(locale = 'ar') {
  const globals = createTestGlobals({ locale })
  wrapper = mount(ConfirmModal, {
    attachTo: document.body,
    global: { ...globals, stubs: { teleport: true } },
  })
  return wrapper
}

function footerButtons() {
  return [...document.querySelectorAll('.modal__footer .btn')]
}

function dialog() {
  return document.querySelector('.modal__panel')
}

afterEach(() => {
  useConfirm().dismiss()
  wrapper?.unmount()
  document.body.innerHTML = ''
})

describe('ConfirmModal + useConfirm', () => {
  it('shows one question, one line and the verb first, and resolves true on accept', async () => {
    factory()
    const promise = useConfirm().confirm({
      title: 'إرسال الطلب للمراجعة؟',
      message: 'ACC-2026-000004',
      confirmLabel: 'إرسال',
    })
    await flushPromises()
    expect(dialog().getAttribute('role')).toBe('alertdialog')
    expect(document.querySelector('.modal__title').textContent).toBe('إرسال الطلب للمراجعة؟')
    expect(document.querySelector('.modal__message').textContent).toBe('ACC-2026-000004')
    expect(footerButtons().map((button) => button.textContent.trim())).toEqual(['إرسال', 'إلغاء'])
    footerButtons()[0].click()
    await expect(promise).resolves.toBe(true)
  })

  it('labels the dialog by its title and describes it by its line, with a decorative icon', async () => {
    factory()
    useConfirm().confirm({ title: 'حذف الحساب البنكي؟', message: 'بنك المثال', variant: 'danger' })
    await flushPromises()
    const labelId = dialog().getAttribute('aria-labelledby')
    const descriptionId = dialog().getAttribute('aria-describedby')
    expect(document.getElementById(labelId).textContent).toBe('حذف الحساب البنكي؟')
    expect(document.getElementById(descriptionId).textContent).toBe('بنك المثال')
    const icon = document.querySelector('.modal__icon')
    expect(icon.getAttribute('aria-hidden')).toBe('true')
    expect(icon.classList.contains('modal__icon--danger')).toBe(true)
  })

  it.each([
    ['confirm', 'btn--primary', 'modal__icon--confirm'],
    ['warning', 'btn--primary', 'modal__icon--warning'],
    ['danger', 'btn--danger', 'modal__icon--danger'],
  ])('renders the %s variant with its button and icon tone', async (variant, button, tone) => {
    factory()
    useConfirm().confirm({ title: 'إجراء؟', variant })
    await flushPromises()
    expect(footerButtons()[0].classList.contains(button)).toBe(true)
    expect(document.querySelector(`.${tone}`)).not.toBeNull()
  })

  it('falls back to the confirm variant and the default labels', async () => {
    factory('en')
    useConfirm().confirm({ variant: 'unknown' })
    await flushPromises()
    expect(document.querySelector('.modal__title').textContent).toBe('Confirmation')
    expect(document.querySelector('.modal__icon--confirm')).not.toBeNull()
    expect(footerButtons().map((button) => button.textContent.trim())).toEqual([
      'Confirm',
      'Cancel',
    ])
    expect(document.querySelector('.modal__message')).toBeNull()
  })

  it('puts the initial focus on the action for a confirmation', async () => {
    factory()
    useConfirm().confirm({ title: 'نشر الصفحة؟', variant: 'confirm' })
    await flushPromises()
    expect(document.activeElement).toBe(footerButtons()[0])
  })

  it.each(['warning', 'danger'])(
    'puts the initial focus on cancel for the %s variant',
    async (variant) => {
      factory()
      useConfirm().confirm({ title: 'حذف المسودة؟', variant })
      await flushPromises()
      expect(document.activeElement).toBe(footerButtons()[1])
    },
  )

  it('resolves false on cancel, on the close button and on Escape', async () => {
    factory()
    const { confirm } = useConfirm()
    const cancelled = confirm({ title: 'حذف؟', variant: 'danger' })
    await flushPromises()
    footerButtons()[1].click()
    await expect(cancelled).resolves.toBe(false)

    const closed = confirm({ title: 'حذف؟' })
    await flushPromises()
    document.querySelector('.modal__close').click()
    await expect(closed).resolves.toBe(false)

    const escaped = confirm({ title: 'حذف؟' })
    await flushPromises()
    dialog().dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await expect(escaped).resolves.toBe(false)
  })

  it('keeps the keyboard focus inside the dialog', async () => {
    factory()
    useConfirm().confirm({ title: 'حذف؟', variant: 'danger' })
    await flushPromises()
    const close = document.querySelector('.modal__close')
    footerButtons()[1].focus()
    footerButtons()[1].dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true }))
    expect(document.activeElement).toBe(close)
    close.dispatchEvent(
      new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true }),
    )
    expect(document.activeElement).toBe(footerButtons()[1])
  })
})
