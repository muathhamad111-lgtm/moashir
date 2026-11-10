import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import PublicLayout from '@/components/layout/PublicLayout.vue'
import { createTestGlobals } from '@/test-utils'

const posture = vi.hoisted(() => ({ showsModulesAwaitingBackend: true }))

vi.mock('@/constants/buildPosture', async (importOriginal) => ({
  ...(await importOriginal()),
  get SHOWS_MODULES_AWAITING_BACKEND() {
    return posture.showsModulesAwaitingBackend
  },
}))

async function mountAt(path) {
  const globals = createTestGlobals()
  await globals.router.push(path)
  await flushPromises()
  return mount(PublicLayout, {
    slots: { default: '<p class="page">الصفحة الرئيسية</p>' },
    global: globals,
  })
}

describe('PublicLayout', () => {
  it('wraps the slot in a main region between navbar and footer', async () => {
    const wrapper = await mountAt('/kpis')
    expect(wrapper.find('.public-navbar').exists()).toBe(true)
    expect(wrapper.find('main.public-layout__content .page').text()).toBe('الصفحة الرئيسية')
    expect(wrapper.find('.footer').exists()).toBe(true)
  })

  it('hides the AI assistant on the home page in phase 1 and keeps it elsewhere', async () => {
    posture.showsModulesAwaitingBackend = true
    expect((await mountAt('/')).find('.chat-widget').exists()).toBe(false)
    expect((await mountAt('/kpis')).find('.chat-widget').exists()).toBe(true)
  })

  it('never shows the AI assistant against the API while it awaits its backend', async () => {
    posture.showsModulesAwaitingBackend = false
    expect((await mountAt('/kpis')).find('.chat-widget').exists()).toBe(false)
  })
})
