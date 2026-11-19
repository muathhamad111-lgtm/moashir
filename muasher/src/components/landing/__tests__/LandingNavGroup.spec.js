import { afterEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import LandingNavGroup from '../LandingNavGroup.vue'
import { createTestGlobals } from '@/test-utils'

const GROUP = {
  key: 'group-0',
  label: 'About',
  children: [
    { key: 'a', label: 'Model', href: null, anchor: 'model', to: { name: 'home' } },
    { key: 'b', label: 'Indicators', href: null, anchor: null, to: '/kpis' },
    { key: 'c', label: 'Guide', href: 'https://guide.example.sa', anchor: null, to: null },
  ],
}

let wrapper

function mountGroup(props = {}) {
  wrapper = mount(LandingNavGroup, {
    props: { group: GROUP, ...props },
    global: createTestGlobals({ locale: 'en' }),
    attachTo: document.body,
  })
  return wrapper
}

afterEach(() => {
  wrapper?.unmount()
  document.body.innerHTML = ''
})

describe('LandingNavGroup', () => {
  it('is a disclosure button that controls a plain list of links', async () => {
    mountGroup()
    const trigger = wrapper.find('button')
    const panel = wrapper.find('ul')
    expect(trigger.attributes('aria-expanded')).toBe('false')
    expect(trigger.attributes('aria-controls')).toBe(panel.attributes('id'))
    expect(panel.isVisible()).toBe(false)
    expect(wrapper.find('[role="menu"]').exists()).toBe(false)
    await trigger.trigger('click')
    expect(trigger.attributes('aria-expanded')).toBe('true')
    expect(panel.isVisible()).toBe(true)
    const links = panel.findAll('a')
    expect(links.map((link) => link.text())).toEqual(['Model', 'Indicators', 'Guide'])
    expect(links[0].attributes('href')).toBe('#model')
    expect(links[2].attributes('rel')).toBe('noopener noreferrer')
  })

  it('closes with Escape and returns focus to the button', async () => {
    mountGroup()
    const trigger = wrapper.find('button')
    await trigger.trigger('click')
    await wrapper.find('a').trigger('keydown', { key: 'Escape' })
    expect(trigger.attributes('aria-expanded')).toBe('false')
    expect(document.activeElement).toBe(trigger.element)
  })

  it('closes on a click outside and when a link is chosen', async () => {
    mountGroup()
    const trigger = wrapper.find('button')
    await trigger.trigger('click')
    document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }))
    await wrapper.vm.$nextTick()
    expect(trigger.attributes('aria-expanded')).toBe('false')
    await trigger.trigger('click')
    await wrapper.findAll('a')[2].trigger('click')
    expect(trigger.attributes('aria-expanded')).toBe('false')
    expect(wrapper.emitted('navigate')).toHaveLength(1)
  })

  it('marks the group active when the reader is in one of its sections', () => {
    mountGroup({ current: 'model' })
    expect(wrapper.find('button').classes()).toContain('is-active')
  })
})
