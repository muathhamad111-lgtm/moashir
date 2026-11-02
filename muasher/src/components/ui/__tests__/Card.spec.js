import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import Card from '@/components/ui/Card.vue'

describe('Card', () => {
  it('renders body slot inside the card contract classes', () => {
    const wrapper = mount(Card, { slots: { default: 'محتوى البطاقة' } })
    expect(wrapper.classes()).toContain('card')
    expect(wrapper.find('.card__body').text()).toBe('محتوى البطاقة')
    expect(wrapper.find('.card__header').exists()).toBe(false)
    expect(wrapper.find('.card__footer').exists()).toBe(false)
  })

  it('renders header from title prop and footer from slot', () => {
    const wrapper = mount(Card, {
      props: { title: 'معيار القيادة' },
      slots: { default: 'body', footer: 'footer' },
    })
    expect(wrapper.find('.card__header').text()).toBe('معيار القيادة')
    expect(wrapper.find('.card__footer').text()).toBe('footer')
  })
})
