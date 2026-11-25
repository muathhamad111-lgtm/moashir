import { afterEach, describe, expect, it, vi } from 'vitest'
import { h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import PhoneFilters from '@/components/data-list/PhoneFilters.vue'
import { createTestGlobals } from '@/test-utils'
import { stubMatchMedia } from '@/test-utils/matchMedia'

afterEach(() => {
  vi.unstubAllGlobals()
  document.body.innerHTML = ''
})

const form = () =>
  h('form', { class: 'audit-log__filters', onSubmit: (event) => event.preventDefault() }, [
    h('input', { name: 'from' }),
    h('button', { type: 'submit' }, 'Apply'),
  ])

function factory({ width, activeCount = 0 }) {
  stubMatchMedia({ width })
  return mount(PhoneFilters, {
    attachTo: document.body,
    props: { activeCount },
    global: createTestGlobals({ locale: 'en' }),
    slots: { default: form },
  })
}

describe('PhoneFilters', () => {
  it('leaves the filter form on the page from md up', () => {
    const wrapper = factory({ width: 1280 })
    expect(wrapper.find('form.audit-log__filters').exists()).toBe(true)
    expect(wrapper.find('[data-testid="phone-filters-toggle"]').exists()).toBe(false)
  })

  it('puts the form behind one «فلترة» button with the active count on a phone', () => {
    const wrapper = factory({ width: 390, activeCount: 2 })
    expect(wrapper.find('form').exists()).toBe(false)
    const toggle = wrapper.find('[data-testid="phone-filters-toggle"]')
    expect(toggle.find('.filter-dialog__count').text()).toBe('2')
    expect(toggle.attributes('aria-label')).toContain('2')
  })

  it('opens the same form in a bottom sheet and closes it when the form is applied', async () => {
    const wrapper = factory({ width: 390 })
    await wrapper.find('[data-testid="phone-filters-toggle"]').trigger('click')
    const sheetForm = document.querySelector('.modal--sheet form.audit-log__filters')
    expect(sheetForm).not.toBeNull()
    sheetForm.querySelector('button[type="submit"]').click()
    await flushPromises()
    expect(document.querySelector('.modal--sheet')).toBeNull()
  })
})
