import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import Pagination from '@/components/data-list/Pagination.vue'
import { createTestGlobals } from '@/test-utils'

function factory(props = {}) {
  return mount(Pagination, {
    props: { page: 2, lastPage: 5, ...props },
    global: createTestGlobals(),
  })
}

describe('Pagination', () => {
  it('renders a nav with the current page marked aria-current', () => {
    const wrapper = factory()
    expect(wrapper.find('nav.pagination').exists()).toBe(true)
    const active = wrapper.find('[aria-current="page"]')
    expect(active.text()).toBe('2')
    expect(active.classes()).toContain('is-active')
  })

  it('emits update:page for numbers, previous, and next', async () => {
    const wrapper = factory()
    await wrapper.find('[aria-label="الانتقال إلى الصفحة 4"]').trigger('click')
    expect(wrapper.emitted('update:page')[0]).toEqual([4])
    await wrapper.find('[aria-label="الصفحة السابقة"]').trigger('click')
    expect(wrapper.emitted('update:page')[1]).toEqual([1])
    await wrapper.find('[aria-label="الصفحة التالية"]').trigger('click')
    expect(wrapper.emitted('update:page')[2]).toEqual([3])
  })

  it('disables previous on the first page and next on the last', () => {
    const first = factory({ page: 1 })
    expect(first.find('[aria-label="الصفحة السابقة"]').attributes('disabled')).toBeDefined()
    const last = factory({ page: 5 })
    expect(last.find('[aria-label="الصفحة التالية"]').attributes('disabled')).toBeDefined()
  })
})
