import { afterEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, nextTick, ref } from 'vue'
import { mount } from '@vue/test-utils'
import { useFocusTrap } from '@/composables/useFocusTrap'

const isActive = ref(false)
const onEscape = vi.fn()
let wrapper = null

const TrapHost = defineComponent({
  props: { autofocus: { type: Boolean, default: false } },
  setup(props) {
    const containerRef = ref(null)
    const { onKeydown } = useFocusTrap(containerRef, { isActive, onEscape })
    return () =>
      isActive.value
        ? h('div', { ref: containerRef, class: 'trap', tabindex: '-1', onKeydown }, [
            h('input', { class: 'trap__first' }),
            h('textarea', { class: 'trap__notes', 'data-autofocus': props.autofocus || undefined }),
            h('select', { class: 'trap__last' }),
          ])
        : h('p', 'closed')
  },
})

function mountHost(props = {}) {
  wrapper = mount(TrapHost, { props, attachTo: document.body })
  return wrapper
}

function opener() {
  const button = document.createElement('button')
  document.body.appendChild(button)
  button.focus()
  return button
}

async function open() {
  isActive.value = true
  await nextTick()
  await nextTick()
}

function press(key, options = {}) {
  const event = new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true, ...options })
  document.querySelector('.trap').dispatchEvent(event)
  return event
}

afterEach(() => {
  wrapper?.unmount()
  wrapper = null
  isActive.value = false
  onEscape.mockClear()
  document.body.innerHTML = ''
})

describe('useFocusTrap', () => {
  it('moves the focus to the first focusable element when activated', async () => {
    mountHost()
    await open()
    expect(document.activeElement.className).toBe('trap__first')
  })

  it('prefers the element marked with data-autofocus', async () => {
    mountHost({ autofocus: true })
    await open()
    expect(document.activeElement.className).toBe('trap__notes')
  })

  it('cycles Tab and Shift+Tab inside the container, including a select', async () => {
    mountHost()
    await open()
    document.querySelector('.trap__last').focus()
    expect(press('Tab').defaultPrevented).toBe(true)
    expect(document.activeElement.className).toBe('trap__first')
    expect(press('Tab', { shiftKey: true }).defaultPrevented).toBe(true)
    expect(document.activeElement.className).toBe('trap__last')
  })

  it('calls onEscape on Escape and stops the event from reaching outer handlers', async () => {
    mountHost()
    await open()
    const outer = vi.fn()
    document.body.addEventListener('keydown', outer)
    press('Escape')
    document.body.removeEventListener('keydown', outer)
    expect(onEscape).toHaveBeenCalledTimes(1)
    expect(outer).not.toHaveBeenCalled()
  })

  it('restores the focus to the opener when deactivated', async () => {
    const trigger = opener()
    mountHost()
    await open()
    expect(document.activeElement).not.toBe(trigger)
    isActive.value = false
    await nextTick()
    expect(document.activeElement).toBe(trigger)
  })

  it('restores the focus to the opener when unmounted while active', async () => {
    const trigger = opener()
    mountHost()
    await open()
    wrapper.unmount()
    wrapper = null
    expect(document.activeElement).toBe(trigger)
  })
})
