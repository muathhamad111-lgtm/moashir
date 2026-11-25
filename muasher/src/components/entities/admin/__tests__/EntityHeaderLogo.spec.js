import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import EntityHeaderLogo from '@/components/entities/admin/EntityHeaderLogo.vue'
import { createTestGlobals } from '@/test-utils'

function render(logo) {
  return mount(EntityHeaderLogo, {
    props: { logo, entityName: 'جمعية البر', refresh: vi.fn() },
    global: createTestGlobals({ locale: 'ar' }),
  })
}

describe('EntityHeaderLogo — read-only on the staff record', () => {
  it('shows the uploaded logo with the entity name as its alternative text', () => {
    const wrapper = render({ url: 'https://files.example/logo.png' })
    const image = wrapper.find('.entity-head-logo__image')
    expect(image.attributes('src')).toBe('https://files.example/logo.png')
    expect(image.attributes('alt')).toContain('جمعية البر')
  })

  it('shows the neutral building mark when the entity has no logo', () => {
    const wrapper = render(null)
    expect(wrapper.find('.entity-head-logo__image').exists()).toBe(false)
    expect(wrapper.find('.entity-head-logo__empty').exists()).toBe(true)
  })

  it('offers no upload, change or remove control', () => {
    const wrapper = render({ url: 'https://files.example/logo.png' })
    expect(wrapper.findAll('button, input')).toHaveLength(0)
  })
})
