import { describe, expect, it } from 'vitest'
import { defineComponent, h, nextTick } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import ListToolbar from '@/components/data-list/ListToolbar.vue'
import { useFilters } from '@/composables/useFilters'
import { createTestGlobals } from '@/test-utils'
import ExpandableSearch from '@/components/data-list/ExpandableSearch.vue'
import { useAuthStore } from '@/stores/auth'
import { sessionUserFor } from '@/test-utils/session'

function mountToolbar(slots = {}) {
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
            ...slots,
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

  it('puts search and filter last, in the table top end corner after the page actions', () => {
    const { wrapper } = mountToolbar({
      actions: () => h('button', { class: 'page-action' }, 'Add'),
    })
    const children = wrapper.find('.filter-toolbar').element.children
    expect([...children].map((child) => child.className)).toEqual([
      'toolbar__actions',
      'filter-toolbar__controls',
    ])
    expect(wrapper.find('.filter-toolbar__controls .expandable-search__toggle').exists()).toBe(true)
    expect(wrapper.find('.filter-toolbar__controls .filter-dialog__toggle').exists()).toBe(true)
    wrapper.unmount()
  })

  it('always puts the export button last, after search and filter', () => {
    const { wrapper } = mountToolbar({
      actions: () => h('button', { class: 'page-action' }, 'Add'),
      export: () => h('button', { class: 'page-export' }, 'Export'),
    })
    const buttons = wrapper.findAll('.filter-toolbar button').map((b) => b.classes()[0])
    expect(buttons.at(-1)).toBe('page-export')
    expect(buttons[0]).toBe('page-action')
    wrapper.unmount()
  })

  it('shows the search as an open field in the back office, as in the unified design', async () => {
    const globals = createTestGlobals({ locale: 'en' })
    useAuthStore(globals.pinia).setUser(sessionUserFor(['system_admin']))
    await globals.router.push('/dashboard')
    const wrapper = mount(ExpandableSearch, {
      props: { modelValue: '', placeholder: 'Search entities' },
      global: globals,
    })
    expect(wrapper.find('.expandable-search__toggle').exists()).toBe(false)
    expect(wrapper.find('input[type="search"]').attributes('placeholder')).toBe('Search entities')
    expect(wrapper.find('.expandable-search__close').exists()).toBe(false)
    await wrapper.setProps({ modelValue: 'Amal' })
    expect(wrapper.find('.expandable-search__close').exists()).toBe(true)
  })
})
