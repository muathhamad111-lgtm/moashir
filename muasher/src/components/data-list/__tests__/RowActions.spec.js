import { describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import RowActions from '@/components/data-list/RowActions.vue'
import { USER_ACTIONS } from '@/constants/users'
import { createTestGlobals } from '@/test-utils'

// The row buttons a list names first; RowActions keeps at most two of them.
const INLINE_KEYS = [USER_ACTIONS.EDIT, USER_ACTIONS.SUSPEND, USER_ACTIONS.ACTIVATE]

const ACTIONS = [
  { key: USER_ACTIONS.EDIT, label: 'تعديل المستخدم', icon: 'pen' },
  { key: USER_ACTIONS.SUSPEND, label: 'إيقاف', icon: 'user-slash', variant: 'danger' },
  {
    key: USER_ACTIONS.RESET_MFA,
    label: 'إعادة ضبط التحقق الثنائي',
    icon: 'lock',
    variant: 'danger',
  },
  { key: USER_ACTIONS.REQUEST_SCOPE, label: 'نطاق المتابعة', icon: 'route' },
]

function mountActions(actions = ACTIONS) {
  return mount(RowActions, {
    props: { actions, name: 'Sara', inlineKeys: INLINE_KEYS },
    global: createTestGlobals(),
    attachTo: document.body,
  })
}

describe('RowActions', () => {
  it('puts edit and suspend in the row as named icon buttons, suspend marked as danger', () => {
    const wrapper = mountActions()
    const inline = wrapper.findAll('.row-actions > .tooltip button')
    expect(inline.map((button) => button.attributes('data-action'))).toEqual(['edit', 'suspend'])
    expect(inline[0].attributes('aria-label')).toBe('تعديل المستخدم — Sara')
    expect(inline[1].classes()).toContain('row-actions__button--danger')
    expect(inline[0].classes()).not.toContain('row-actions__button--danger')
    wrapper.unmount()
  })

  it('keeps at most two icon buttons in the row, the rest in the menu', () => {
    const wrapper = mount(RowActions, {
      props: {
        actions: ACTIONS,
        name: 'Sara',
        inlineKeys: ACTIONS.map((action) => action.key),
      },
      global: createTestGlobals(),
      attachTo: document.body,
    })
    const inline = wrapper.findAll('.row-actions > .tooltip button')
    expect(inline.map((button) => button.attributes('data-action'))).toEqual(['edit', 'suspend'])
    expect(wrapper.find('.row-actions__more').exists()).toBe(true)
    wrapper.unmount()
  })

  it('keeps the remaining actions behind the three-vertical-dots menu', async () => {
    const wrapper = mountActions()
    const trigger = wrapper.find('.row-actions__more > button')
    expect(trigger.find('[data-icon="dots-vertical"]').exists()).toBe(true)
    await trigger.trigger('click')
    await flushPromises()
    const items = [...document.querySelectorAll('[role="menuitem"]')].map((item) =>
      item.textContent.trim(),
    )
    expect(items).toEqual(['إعادة ضبط التحقق الثنائي', 'نطاق المتابعة'])
    wrapper.unmount()
  })

  it('emits the chosen key from a row button and from the menu', async () => {
    const wrapper = mountActions()
    await wrapper.find('[data-action="suspend"]').trigger('click')
    await wrapper.find('.row-actions__more > button').trigger('click')
    await flushPromises()
    document.querySelectorAll('[role="menuitem"]')[1].click()
    expect(wrapper.emitted('select')).toEqual([['suspend'], ['requestScope']])
    wrapper.unmount()
  })

  it('shows reactivation in the row for a suspended user and drops an empty menu', () => {
    const wrapper = mountActions([
      { key: USER_ACTIONS.EDIT, label: 'تعديل المستخدم', icon: 'pen' },
      { key: USER_ACTIONS.ACTIVATE, label: 'إعادة التفعيل', icon: 'user-check' },
    ])
    const keys = wrapper.findAll('[data-action]').map((button) => button.attributes('data-action'))
    expect(keys).toEqual(['edit', 'activate'])
    expect(wrapper.find('.row-actions__more').exists()).toBe(false)
    wrapper.unmount()
  })

  it('shows only edit in the row by default, with no menu when nothing else is left', () => {
    const wrapper = mount(RowActions, {
      props: { actions: [{ key: 'edit', label: 'Edit', icon: 'pen' }], name: 'dashboard.view' },
      global: createTestGlobals(),
    })
    expect(wrapper.findAll('[data-action]').map((b) => b.attributes('data-action'))).toEqual([
      'edit',
    ])
    expect(wrapper.find('.row-actions__more').exists()).toBe(false)
  })
})
