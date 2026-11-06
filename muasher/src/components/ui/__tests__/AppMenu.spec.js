import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import AppMenu from '@/components/ui/AppMenu.vue'
import { createTestGlobals } from '@/test-utils'
import { stubMatchMedia } from '@/test-utils/matchMedia'

const ACTIONS = [
  { key: 'edit', label: 'تعديل', icon: 'pen' },
  { key: 'history', label: 'عرض السجل', icon: 'eye' },
  { key: 'suspend', label: 'تعليق', icon: 'lock', disabled: true },
  { key: 'delete', label: 'حذف المدينة', icon: 'trash', variant: 'danger' },
]

function mountMenu(props = {}, slots = {}) {
  return mount(AppMenu, {
    props: { actions: ACTIONS, label: 'إجراءات: الرياض', ...props },
    slots,
    attachTo: document.body,
    global: createTestGlobals(),
  })
}

function trigger(wrapper) {
  return wrapper.find('button[aria-haspopup="menu"]')
}

function items(wrapper) {
  return wrapper.findAll('[role="menuitem"]')
}

async function open(wrapper, key) {
  trigger(wrapper).element.focus()
  if (key) await trigger(wrapper).trigger('keydown', { key })
  else await trigger(wrapper).trigger('click')
  await flushPromises()
}

async function pressInMenu(wrapper, key) {
  await wrapper.find('[role="menu"]').trigger('keydown', { key })
  await flushPromises()
}

afterEach(() => {
  vi.unstubAllGlobals()
  document.body.innerHTML = ''
})

describe('AppMenu', () => {
  it('is a labelled menu button that controls the menu only while open', async () => {
    const wrapper = mountMenu()
    const button = trigger(wrapper)
    expect(button.attributes('aria-label')).toBe('إجراءات: الرياض')
    expect(button.attributes('aria-expanded')).toBe('false')
    expect(button.attributes('aria-controls')).toBeUndefined()
    expect(button.find('svg').attributes('aria-hidden')).toBe('true')
    await open(wrapper)
    const menu = wrapper.find('[role="menu"]')
    expect(button.attributes('aria-expanded')).toBe('true')
    expect(button.attributes('aria-controls')).toBe(menu.attributes('id'))
    expect(menu.attributes('aria-label')).toBe('إجراءات: الرياض')
    expect(items(wrapper).map((item) => item.attributes('tabindex'))).toEqual([
      '-1',
      '-1',
      '-1',
      '-1',
    ])
  })

  it('falls back to the generic actions label', () => {
    expect(trigger(mountMenu({ label: '' })).attributes('aria-label')).toBe('الإجراءات')
  })

  it('opens on the first item with a click, Enter or ArrowDown and on the last with ArrowUp', async () => {
    const clicked = mountMenu()
    await open(clicked)
    expect(document.activeElement).toBe(items(clicked)[0].element)
    clicked.unmount()
    const down = mountMenu()
    await open(down, 'ArrowDown')
    expect(document.activeElement).toBe(items(down)[0].element)
    down.unmount()
    const up = mountMenu()
    await open(up, 'ArrowUp')
    expect(document.activeElement).toBe(items(up)[3].element)
  })

  it('moves with wrapping arrows, Home, End and first-character type-ahead', async () => {
    const wrapper = mountMenu()
    await open(wrapper)
    await pressInMenu(wrapper, 'ArrowUp')
    expect(document.activeElement).toBe(items(wrapper)[3].element)
    await pressInMenu(wrapper, 'ArrowDown')
    expect(document.activeElement).toBe(items(wrapper)[0].element)
    await pressInMenu(wrapper, 'End')
    expect(document.activeElement).toBe(items(wrapper)[3].element)
    await pressInMenu(wrapper, 'Home')
    expect(document.activeElement).toBe(items(wrapper)[0].element)
    await pressInMenu(wrapper, 'ع')
    expect(document.activeElement).toBe(items(wrapper)[1].element)
    expect(items(wrapper)[1].classes()).toContain('is-active')
  })

  it('emits the chosen key, closes and returns the focus to the button', async () => {
    const wrapper = mountMenu()
    await open(wrapper)
    await items(wrapper)[1].trigger('click')
    await flushPromises()
    expect(wrapper.emitted('select')).toEqual([['history']])
    expect(wrapper.find('[role="menu"]').exists()).toBe(false)
    expect(document.activeElement).toBe(trigger(wrapper).element)
  })

  it('announces a disabled item but does not run it', async () => {
    const wrapper = mountMenu()
    await open(wrapper)
    const disabled = items(wrapper)[2]
    expect(disabled.attributes('aria-disabled')).toBe('true')
    expect(disabled.attributes('disabled')).toBeUndefined()
    await disabled.trigger('click')
    expect(wrapper.emitted('select')).toBeUndefined()
  })

  it('separates the destructive items at the end and marks them as danger', async () => {
    const wrapper = mountMenu()
    await open(wrapper)
    const children = wrapper.find('[role="menu"]').element.children
    expect(children[3].getAttribute('role')).toBe('separator')
    expect(items(wrapper)[3].classes()).toContain('is-danger')
  })

  it('closes on Escape without closing a parent dialog and returns the focus', async () => {
    const outer = vi.fn()
    document.body.addEventListener('keydown', outer)
    const wrapper = mountMenu()
    await open(wrapper)
    await pressInMenu(wrapper, 'Escape')
    expect(wrapper.find('[role="menu"]').exists()).toBe(false)
    expect(document.activeElement).toBe(trigger(wrapper).element)
    expect(outer).not.toHaveBeenCalled()
    document.body.removeEventListener('keydown', outer)
  })

  it('closes on Tab and on a pointer press outside', async () => {
    const wrapper = mountMenu()
    await open(wrapper)
    await pressInMenu(wrapper, 'Tab')
    expect(wrapper.find('[role="menu"]').exists()).toBe(false)
    await open(wrapper)
    document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }))
    await flushPromises()
    expect(wrapper.find('[role="menu"]').exists()).toBe(false)
  })

  it('renders an avatar trigger with a header outside the menu items', async () => {
    const wrapper = mountMenu(
      { trigger: 'avatar' },
      { trigger: '<span>م</span>', header: '<span class="user-head">مدير النظام</span>' },
    )
    expect(trigger(wrapper).classes()).toContain('navbar__avatar')
    await open(wrapper)
    expect(wrapper.find('.dropdown-menu__header .user-head').exists()).toBe(true)
    expect(wrapper.find('[role="menu"] .user-head').exists()).toBe(false)
  })
})

describe('AppMenu below 768 px', () => {
  it('opens a sheet titled by the menu label with the same items', async () => {
    stubMatchMedia({ width: 390 })
    const wrapper = mountMenu()
    await open(wrapper)
    const sheet = wrapper.find('.dropdown-sheet')
    expect(sheet.attributes('role')).toBe('dialog')
    expect(wrapper.find(`#${sheet.attributes('aria-labelledby')}`).text()).toBe('إجراءات: الرياض')
    expect(items(wrapper)).toHaveLength(4)
    expect(document.activeElement).toBe(items(wrapper)[0].element)
    await wrapper.find('.dropdown-sheet__close').trigger('click')
    await flushPromises()
    expect(wrapper.find('.dropdown-sheet').exists()).toBe(false)
    expect(document.activeElement).toBe(trigger(wrapper).element)
  })
})
