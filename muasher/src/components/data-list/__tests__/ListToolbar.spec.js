import { describe, expect, it } from 'vitest'
import { defineComponent, h, nextTick } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import { useFilters } from '@/composables/useFilters'
import { createTestGlobals } from '@/test-utils'

function mountToolbar() {
  const state = {}
  const Host = defineComponent({
    setup() {
      state.filters = useFilters({ status: '', regionId: '' })
      return () =>
        h(
          ListToolbar,
          {
            search: state.filters.search.value,
            'onUpdate:search': (value) => (state.filters.search.value = value),
            filterState: state.filters,
          },
          {
            filters: ({ draft }) => {
              state.draft = draft
              return h('input', {
                class: 'draft-status',
                value: draft.filters.status,
                onInput: (event) => draft.setFilter('status', event.target.value),
              })
            },
          },
        )
    },
  })
  const wrapper = mount(Host, {
    global: createTestGlobals({ locale: 'en' }),
    attachTo: document.body,
  })
  return { wrapper, state }
}

describe('ListToolbar', () => {
  it('shows a search button that expands into a field and collapses when emptied', async () => {
    const { wrapper, state } = mountToolbar()
    expect(wrapper.find('.expandable-search__input').exists()).toBe(false)
    await wrapper.find('.expandable-search__toggle').trigger('click')
    await nextTick()
    const input = wrapper.find('.expandable-search__input')
    expect(document.activeElement).toBe(input.element)
    await input.setValue('Ehsan')
    expect(state.filters.search.value).toBe('Ehsan')
    await wrapper.find('.expandable-search__close').trigger('click')
    await nextTick()
    expect(state.filters.search.value).toBe('')
    expect(wrapper.find('.expandable-search__toggle').exists()).toBe(true)
    wrapper.unmount()
  })

  it('changes nothing until Apply, then counts the filters and offers to clear them', async () => {
    const { wrapper, state } = mountToolbar()
    await wrapper.find('.filter-dialog__toggle').trigger('click')
    await flushPromises()
    const dialog = document.querySelector('[role="dialog"]')
    expect(dialog).not.toBeNull()
    const field = dialog.querySelector('.draft-status')
    field.value = 'active'
    field.dispatchEvent(new Event('input'))
    expect(state.filters.filters.status).toBe('')

    const apply = [...dialog.querySelectorAll('button')].find(
      (b) => b.textContent.trim() === 'Apply',
    )
    apply.click()
    await flushPromises()
    expect(state.filters.filters.status).toBe('active')
    expect(wrapper.find('.filter-dialog__count').text()).toBe('1')
    expect(wrapper.find('.filter-dialog__toggle').attributes('aria-label')).toBe(
      'Filter, 1 applied',
    )

    const clear = wrapper.findAll('button').find((b) => b.text() === 'Clear filters')
    await clear.trigger('click')
    expect(state.filters.filters.status).toBe('')
    expect(wrapper.find('.filter-dialog__count').exists()).toBe(false)
    wrapper.unmount()
  })

  it('clears the draft with Clear all without touching the applied filters', async () => {
    const { wrapper, state } = mountToolbar()
    state.filters.setFilter('status', 'active')
    await wrapper.find('.filter-dialog__toggle').trigger('click')
    await flushPromises()
    expect(state.draft.filters.status).toBe('active')
    const dialog = document.querySelector('[role="dialog"]')
    const reset = [...dialog.querySelectorAll('button')].find(
      (b) => b.textContent.trim() === 'Clear all',
    )
    reset.click()
    expect(state.draft.filters.status).toBe('')
    expect(state.filters.filters.status).toBe('active')
    wrapper.unmount()
  })
})
