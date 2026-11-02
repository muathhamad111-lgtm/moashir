import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import BaseButton from '@/components/ui/BaseButton.vue'

describe('BaseButton', () => {
  it('renders the default slot inside the label with base classes', () => {
    const wrapper = mount(BaseButton, { slots: { default: 'حفظ' } })
    expect(wrapper.text()).toContain('حفظ')
    expect(wrapper.classes()).toContain('btn')
    expect(wrapper.classes()).toContain('btn--primary')
    expect(wrapper.attributes('type')).toBe('button')
  })

  it('applies variant and size modifier classes', () => {
    const wrapper = mount(BaseButton, { props: { variant: 'danger', size: 'lg' } })
    expect(wrapper.classes()).toContain('btn--danger')
    expect(wrapper.classes()).toContain('btn--lg')
  })

  it('emits click when enabled', async () => {
    const wrapper = mount(BaseButton)
    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toHaveLength(1)
  })

  it('does not emit click when disabled', async () => {
    const wrapper = mount(BaseButton, { props: { disabled: true } })
    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toBeUndefined()
    expect(wrapper.attributes('disabled')).toBeDefined()
  })

  it('shows the spinner and blocks clicks while loading', async () => {
    const wrapper = mount(BaseButton, { props: { loading: true } })
    expect(wrapper.find('.btn__spinner').exists()).toBe(true)
    expect(wrapper.classes()).toContain('btn--loading')
    expect(wrapper.attributes('aria-busy')).toBe('true')
    await wrapper.trigger('click')
    expect(wrapper.emitted('click')).toBeUndefined()
  })

  it('renders icon-only mode without a label', () => {
    const wrapper = mount(BaseButton, { props: { iconOnly: true, icon: 'fa-solid fa-plus' } })
    expect(wrapper.classes()).toContain('btn--icon')
    expect(wrapper.find('.btn__label').exists()).toBe(false)
    expect(wrapper.find('.btn__icon').exists()).toBe(true)
  })
})
