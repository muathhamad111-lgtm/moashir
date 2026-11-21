import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import process from 'node:process'
import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import BaseInput from '@/components/form/BaseInput.vue'
import PasswordRevealButton from '@/components/form/PasswordRevealButton.vue'
import { createTestGlobals } from '@/test-utils'

const PASSWORD = 'Rawdah@Riyadh2026'

function mountPassword(locale = 'ar', props = {}) {
  return mount(BaseInput, {
    props: {
      modelValue: PASSWORD,
      label: locale === 'ar' ? 'كلمة المرور' : 'Password',
      name: 'password',
      type: 'password',
      autocomplete: 'current-password',
      ...props,
    },
    attachTo: document.body,
    global: createTestGlobals({ locale }),
  })
}

function partial(name) {
  return readFileSync(resolve(process.cwd(), 'src/styles/components', name), 'utf8')
}

describe('password reveal in BaseInput', () => {
  it('renders a non-submitting button bound to the input with the show label', () => {
    const wrapper = mountPassword('en')
    const input = wrapper.find('input')
    const button = wrapper.find('button.password-reveal')
    expect(button.attributes('type')).toBe('button')
    expect(button.attributes('aria-controls')).toBe(input.attributes('id'))
    expect(button.attributes('aria-pressed')).toBe('false')
    expect(button.attributes('aria-label')).toBe('Show password')
    expect(button.find('[data-icon="eye"]').exists()).toBe(true)
    expect(input.attributes('type')).toBe('password')
    wrapper.unmount()
  })

  it('switches the type, the pressed state, the label and the icon on each press', async () => {
    const wrapper = mountPassword('ar')
    const button = wrapper.find('button.password-reveal')
    await button.trigger('click')
    expect(wrapper.find('input').attributes('type')).toBe('text')
    expect(button.attributes('aria-pressed')).toBe('true')
    expect(button.attributes('aria-label')).toBe('إخفاء كلمة المرور')
    expect(button.find('[data-icon="eye-slash"]').exists()).toBe(true)
    await button.trigger('click')
    expect(wrapper.find('input').attributes('type')).toBe('password')
    expect(button.attributes('aria-label')).toBe('إظهار كلمة المرور')
    wrapper.unmount()
  })

  it('keeps the value, the autocomplete hint and the focused input while toggling', async () => {
    const wrapper = mountPassword('en')
    const input = wrapper.find('input')
    input.element.focus()
    await wrapper.find('button.password-reveal').trigger('click')
    expect(input.element.value).toBe(PASSWORD)
    expect(input.attributes('autocomplete')).toBe('current-password')
    expect(document.activeElement).toBe(input.element)
    wrapper.unmount()
  })

  it('keeps the pointer press from moving focus away from the input', () => {
    const wrapper = mountPassword('en')
    const event = new MouseEvent('mousedown', { bubbles: true, cancelable: true })
    wrapper.find('button.password-reveal').element.dispatchEvent(event)
    expect(event.defaultPrevented).toBe(true)
    wrapper.unmount()
  })

  it('hides the value again when the form clears the field', async () => {
    const wrapper = mountPassword('en')
    await wrapper.find('button.password-reveal').trigger('click')
    expect(wrapper.find('input').attributes('type')).toBe('text')
    await wrapper.setProps({ modelValue: '' })
    expect(wrapper.find('input').attributes('type')).toBe('password')
    expect(wrapper.find('button.password-reveal').attributes('aria-pressed')).toBe('false')
    wrapper.unmount()
  })

  it('disables the toggle with the field', () => {
    const wrapper = mountPassword('en', { disabled: true })
    expect(wrapper.find('button.password-reveal').attributes('disabled')).toBeDefined()
    wrapper.unmount()
  })

  it('marks the field for inline-end room only when it is a password field', () => {
    const password = mountPassword('en')
    expect(password.find('.field').classes()).toContain('field--revealable')
    expect(password.find('.field__frame').classes()).toContain('field__frame--reveal')
    password.unmount()

    const text = mount(BaseInput, {
      props: { modelValue: '', label: 'Name', name: 'name' },
      global: createTestGlobals({ locale: 'en' }),
    })
    expect(text.find('button').exists()).toBe(false)
    expect(text.find('.field').classes()).not.toContain('field--revealable')
    expect(text.find('.field__frame').classes()).not.toContain('field__frame--reveal')
  })
})

describe('PasswordRevealButton', () => {
  it('names a secret value instead of a password when asked', () => {
    const wrapper = mount(PasswordRevealButton, {
      props: { revealed: false, controls: 'secret-1', subject: 'secret' },
      global: createTestGlobals({ locale: 'en' }),
    })
    expect(wrapper.attributes('aria-label')).toBe('Show the entered value')
  })

  it('places the toggle with logical properties only, so it mirrors in RTL', () => {
    const source = partial('_password-reveal.scss')
    expect(source).toContain('inset-inline-end')
    expect(source).toContain('padding-inline-end')
    expect(source).toContain('min-inline-size: var(--size-target-min)')
    expect(source).toContain('min-block-size: var(--size-target-min)')
    expect(source).toMatch(/&:focus-visible \{\s+@include focus-ring;/)
    expect(source).not.toMatch(/(^|[\s-])(left|right)\s*:/m)
    expect(source).not.toMatch(/(margin|padding|border)-(left|right)/)
  })
})
