import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import IllustrationSlot from '@/components/public/IllustrationSlot.vue'

describe('IllustrationSlot', () => {
  it('renders a lazy image with the alt when a src is provided', () => {
    const wrapper = mount(IllustrationSlot, {
      props: { src: '/generated/hero.png', alt: 'وصف الصورة' },
    })
    const img = wrapper.find('img.illustration-slot__image')
    expect(img.exists()).toBe(true)
    expect(img.attributes('src')).toBe('/generated/hero.png')
    expect(img.attributes('alt')).toBe('وصف الصورة')
    expect(img.attributes('loading')).toBe('lazy')
    expect(wrapper.find('.illustration-slot__placeholder').exists()).toBe(false)
  })

  it('renders the branded placeholder with an accessible label when src is empty', () => {
    const wrapper = mount(IllustrationSlot, {
      props: { src: null, alt: 'صورة بديلة' },
    })
    const placeholder = wrapper.find('.illustration-slot__placeholder')
    expect(placeholder.exists()).toBe(true)
    expect(placeholder.attributes('role')).toBe('img')
    expect(placeholder.attributes('aria-label')).toBe('صورة بديلة')
    expect(wrapper.find('img').exists()).toBe(false)
  })

  it('treats a whitespace-only src as no image', () => {
    const wrapper = mount(IllustrationSlot, {
      props: { src: '   ', alt: 'صورة' },
    })
    expect(wrapper.find('.illustration-slot__placeholder').exists()).toBe(true)
    expect(wrapper.find('img').exists()).toBe(false)
  })

  it('applies the variant modifier class', () => {
    const wrapper = mount(IllustrationSlot, {
      props: { src: null, alt: 'صورة', variant: 'hero' },
    })
    expect(wrapper.find('.illustration-slot--hero').exists()).toBe(true)
  })
})
