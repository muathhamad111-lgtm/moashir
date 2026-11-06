import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import SecretInput from '@/components/settings/SecretInput.vue'
import { createTestGlobals } from '@/test-utils'
import { SECRET_ACTIONS } from '@/utils/integrations/secretValue'

const STORED = {
  set: true,
  last4: 'wxyz',
  updatedAt: '2026-10-28T08:40:00Z',
  updatedBy: { id: 2, name: 'منيرة بنت خالد العتيبي' },
}

function mountSecret(props = {}) {
  return mount(SecretInput, {
    props: { name: 'apiKey', label: 'API key', ...props },
    global: createTestGlobals({ locale: 'en' }),
  })
}

describe('SecretInput', () => {
  it('shows the stored state, the last four and who updated it, never an input', () => {
    const wrapper = mountSecret({ meta: STORED })
    expect(wrapper.find('.secret-input__status').text()).toContain('•••• stored')
    expect(wrapper.find('.secret-input__last4').text()).toBe('ends with wxyz')
    expect(wrapper.find('.secret-input__updated').text()).toContain('منيرة بنت خالد العتيبي')
    expect(wrapper.find('input').exists()).toBe(false)
  })

  it('opens an empty password input on Replace and never pre-fills it', async () => {
    const wrapper = mountSecret({ meta: STORED })
    const replace = wrapper.findAll('button').find((button) => button.text() === 'Replace')
    await replace.trigger('click')
    expect(wrapper.emitted('update:modelValue').at(-1)[0]).toEqual({
      action: SECRET_ACTIONS.REPLACE,
      value: '',
    })
    await wrapper.setProps({ modelValue: { action: SECRET_ACTIONS.REPLACE, value: '' } })
    const input = wrapper.find('input')
    expect(input.attributes('type')).toBe('password')
    expect(input.attributes('autocomplete')).toBe('off')
    expect(input.element.value).toBe('')
  })

  it('emits the typed value as a replacement and offers no reveal toggle', async () => {
    const wrapper = mountSecret()
    await wrapper.find('input').setValue('sk_test_fake_example_value_0001')
    expect(wrapper.emitted('update:modelValue').at(-1)[0]).toEqual({
      action: SECRET_ACTIONS.REPLACE,
      value: 'sk_test_fake_example_value_0001',
    })
    expect(
      wrapper
        .findAll('button')
        .some((button) => /show|reveal/i.test(button.attributes('aria-label') ?? '')),
    ).toBe(false)
  })

  it('marks a stored secret for clearing and can undo it', async () => {
    const wrapper = mountSecret({
      meta: STORED,
      modelValue: { action: SECRET_ACTIONS.CLEAR, value: '' },
    })
    expect(wrapper.find('.secret-input__state--clearing').text()).toContain('will be removed')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Undo')
      .trigger('click')
    expect(wrapper.emitted('update:modelValue').at(-1)[0]).toEqual({
      action: SECRET_ACTIONS.KEEP,
      value: '',
    })
  })

  it('shows the field error and links it to the input', () => {
    const wrapper = mountSecret({ error: 'Must start with sk_test_' })
    const input = wrapper.find('input')
    const error = wrapper.find('.field__error')
    expect(error.text()).toBe('Must start with sk_test_')
    expect(input.attributes('aria-describedby')).toContain(error.attributes('id'))
  })
})
