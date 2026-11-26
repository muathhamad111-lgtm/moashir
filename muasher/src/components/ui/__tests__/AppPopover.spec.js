import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import AppPopover from '@/components/ui/AppPopover.vue'
import { createTestGlobals } from '@/test-utils'
import { stubMatchMedia } from '@/test-utils/matchMedia'

function mountPopover() {
  return mount(AppPopover, {
    props: { label: 'الإشعارات', triggerLabel: 'الإشعارات (3 غير مقروء)' },
    slots: {
      trigger: '<span class="bell">3</span>',
      default: `<template #default="{ headingId, isSheet }">
        <h2 v-if="!isSheet" :id="headingId" tabindex="-1">الإشعارات</h2>
        <a href="/notifications" class="all">عرض الكل</a>
      </template>`,
    },
    attachTo: document.body,
    global: createTestGlobals(),
  })
}

function trigger(wrapper) {
  return wrapper.find('button[aria-haspopup="dialog"]')
}

afterEach(() => {
  vi.unstubAllGlobals()
  document.body.innerHTML = ''
})

describe('AppPopover', () => {
  it('opens a dialog named by its heading and moves the focus to the heading', async () => {
    const wrapper = mountPopover()
    expect(trigger(wrapper).attributes('aria-label')).toBe('الإشعارات (3 غير مقروء)')
    await trigger(wrapper).trigger('click')
    await flushPromises()
    const dialog = wrapper.find('[role="dialog"]')
    expect(trigger(wrapper).attributes('aria-controls')).toBe(dialog.attributes('id'))
    const heading = wrapper.find(`#${dialog.attributes('aria-labelledby')}`)
    expect(heading.text()).toBe('الإشعارات')
    expect(document.activeElement).toBe(heading.element)
    expect(wrapper.emitted('open')).toHaveLength(1)
  })

  it('closes on Escape, returns the focus and keeps a parent dialog open', async () => {
    const outer = vi.fn()
    document.body.addEventListener('keydown', outer)
    const wrapper = mountPopover()
    await trigger(wrapper).trigger('click')
    await flushPromises()
    await wrapper.find('[role="dialog"]').trigger('keydown', { key: 'Escape' })
    await flushPromises()
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    expect(document.activeElement).toBe(trigger(wrapper).element)
    expect(outer).not.toHaveBeenCalled()
    expect(wrapper.emitted('close')).toHaveLength(1)
    document.body.removeEventListener('keydown', outer)
  })

  it('closes when the focus leaves the panel and on a pointer press outside', async () => {
    const wrapper = mountPopover()
    const outside = document.createElement('button')
    document.body.appendChild(outside)
    await trigger(wrapper).trigger('click')
    await flushPromises()
    await wrapper.find('.all').trigger('focusout', { relatedTarget: outside })
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
    await trigger(wrapper).trigger('click')
    await flushPromises()
    document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }))
    await flushPromises()
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
  })

  it('becomes a modal sheet below 768 px and focuses the panel when the heading is hidden', async () => {
    stubMatchMedia({ width: 390 })
    const wrapper = mountPopover()
    await trigger(wrapper).trigger('click')
    await flushPromises()
    const sheet = wrapper.find('.dropdown-sheet')
    expect(sheet.attributes('role')).toBe('dialog')
    expect(sheet.attributes('aria-modal')).toBe('true')
    expect(wrapper.find(`#${sheet.attributes('aria-labelledby')}`).text()).toBe('الإشعارات')
    expect(wrapper.find('.dropdown-popover__panel').attributes('role')).toBeUndefined()
    expect(document.activeElement).toBe(wrapper.find('.dropdown-popover__panel').element)
  })
})
