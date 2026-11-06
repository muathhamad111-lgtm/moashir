import { describe, expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import PublicLayout from '@/components/layout/PublicLayout.vue'
import { createTestGlobals } from '@/test-utils'

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
    expect((await mountAt('/')).find('.chat-widget').exists()).toBe(false)
    expect((await mountAt('/kpis')).find('.chat-widget').exists()).toBe(true)
  })
})
