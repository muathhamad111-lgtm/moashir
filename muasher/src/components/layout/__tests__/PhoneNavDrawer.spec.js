import { afterEach, describe, expect, it } from 'vitest'
import { h } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import PhoneNavDrawer from '@/components/layout/PhoneNavDrawer.vue'
import { ROUTE_NAMES } from '@/constants/routeNames'
import { createTestGlobals } from '@/test-utils'

afterEach(() => {
  document.body.innerHTML = ''
})

async function factory() {
  const globals = createTestGlobals({ locale: 'ar' })
  await globals.router.push({ name: ROUTE_NAMES.APP_DASHBOARD })
  const wrapper = mount(PhoneNavDrawer, {
    attachTo: document.body,
    global: globals,
    slots: { default: () => h('nav', { class: 'sidebar' }, 'menu') },
  })
  return { wrapper, router: globals.router }
}

const drawer = () => document.querySelector('.modal--drawer-start')

describe('PhoneNavDrawer', () => {
  it('is closed until its button is pressed, then shows the menu from the reading start', async () => {
    const { wrapper } = await factory()
    expect(drawer()).toBeNull()
    const toggle = wrapper.find('[data-testid="nav-drawer-toggle"]')
    expect(toggle.attributes('aria-label')).toBe('فتح القائمة')
    await toggle.trigger('click')
    expect(drawer().querySelector('[data-testid="nav-drawer"] .sidebar')).not.toBeNull()
    expect(toggle.attributes('aria-expanded')).toBe('true')
  })

  it('titles the panel «القائمة»', async () => {
    const { wrapper } = await factory()
    await wrapper.find('[data-testid="nav-drawer-toggle"]').trigger('click')
    expect(drawer().querySelector('.modal__title').textContent.trim()).toBe('القائمة')
  })

  it('closes on Esc', async () => {
    const { wrapper } = await factory()
    await wrapper.find('[data-testid="nav-drawer-toggle"]').trigger('click')
    drawer().dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
    await flushPromises()
    expect(drawer()).toBeNull()
  })

  it('closes on the backdrop', async () => {
    const { wrapper } = await factory()
    await wrapper.find('[data-testid="nav-drawer-toggle"]').trigger('click')
    drawer().querySelector('.modal__backdrop').click()
    await flushPromises()
    expect(drawer()).toBeNull()
  })

  it('closes once a section is opened', async () => {
    const { wrapper, router } = await factory()
    await wrapper.find('[data-testid="nav-drawer-toggle"]').trigger('click')
    await router.push({ name: ROUTE_NAMES.NOTIFICATIONS_INDEX })
    await flushPromises()
    expect(drawer()).toBeNull()
  })
})
