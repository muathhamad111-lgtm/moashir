import { afterEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import BaseModal from '@/components/modal/BaseModal.vue'
import { createTestGlobals } from '@/test-utils'

let wrapper

function factory(props = {}) {
  const globals = createTestGlobals()
  wrapper = mount(BaseModal, {
    props: { modelValue: true, title: 'نافذة تجريبية', ...props },
    slots: {
      default: '<p>المحتوى</p>',
      footer: '<button type="button" class="footer-btn">إغلاق</button>',
    },
    attachTo: document.body,
    global: { ...globals, stubs: { teleport: true } },
  })
  return wrapper
}

afterEach(() => {
  wrapper?.unmount()
  document.body.innerHTML = ''
})

describe('BaseModal', () => {
  it('renders a labelled dialog into the body with backdrop', () => {
    factory()
    const dialog = document.querySelector('[role="dialog"]')
    expect(dialog).not.toBeNull()
    expect(dialog.getAttribute('aria-modal')).toBe('true')
    const titleId = dialog.getAttribute('aria-labelledby')
    expect(document.getElementById(titleId).textContent).toBe('نافذة تجريبية')
    expect(document.querySelector('.modal__backdrop')).not.toBeNull()
  })

  it('moves focus into the panel when opened', async () => {
    factory()
    await new Promise((resolve) => setTimeout(resolve))
    expect(document.querySelector('.modal__panel').contains(document.activeElement)).toBe(true)
  })

  it('traps Tab focus inside the panel', async () => {
    factory()
    await new Promise((resolve) => setTimeout(resolve))
    const focusable = document.querySelectorAll(
      '.modal__panel button, .modal__panel a[href], .modal__panel input',
    )
    const last = focusable[focusable.length - 1]
    last.focus()
    await wrapper.find('.modal').trigger('keydown', { key: 'Tab' })
    expect(document.activeElement).toBe(focusable[0])
    focusable[0].focus()
    await wrapper.find('.modal').trigger('keydown', { key: 'Tab', shiftKey: true })
    expect(document.activeElement).toBe(last)
  })

  it('closes on Escape and emits update + close', async () => {
    factory()
    await wrapper.find('.modal').trigger('keydown', { key: 'Escape' })
    expect(wrapper.emitted('update:modelValue')[0]).toEqual([false])
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('closes on backdrop click only when allowed', async () => {
    factory({ closeOnBackdrop: false })
    await wrapper.find('.modal__backdrop').trigger('click')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    await wrapper.setProps({ closeOnBackdrop: true })
    await wrapper.find('.modal__backdrop').trigger('click')
    expect(wrapper.emitted('update:modelValue')[0]).toEqual([false])
  })

  it('restores focus to the previously focused trigger on close', async () => {
    const trigger = document.createElement('button')
    document.body.appendChild(trigger)
    trigger.focus()
    factory({ modelValue: false })
    await wrapper.setProps({ modelValue: true })
    await new Promise((resolve) => setTimeout(resolve))
    expect(document.activeElement).not.toBe(trigger)
    await wrapper.setProps({ modelValue: false })
    expect(document.activeElement).toBe(trigger)
  })
})
