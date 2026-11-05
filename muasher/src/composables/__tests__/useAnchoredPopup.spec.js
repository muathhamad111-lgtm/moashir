import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { effectScope, nextTick, ref } from 'vue'
import { isAnchorVisible, useAnchoredPopup } from '@/composables/useAnchoredPopup'

const VIEWPORT = { width: 1440, height: 900 }

function setViewport({ width, height }) {
  Object.defineProperty(document.documentElement, 'clientWidth', {
    value: width,
    configurable: true,
  })
  Object.defineProperty(document.documentElement, 'clientHeight', {
    value: height,
    configurable: true,
  })
}

function anchorAt({ left, top, width = 300, height = 48, direction = 'ltr' }) {
  const anchor = document.createElement('button')
  anchor.style.direction = direction
  anchor.getBoundingClientRect = () => ({
    left,
    top,
    width,
    height,
    right: left + width,
    bottom: top + height,
  })
  document.body.appendChild(anchor)
  return anchor
}

function popupOf({ inlineSize = 300, blockSize = 200 } = {}) {
  const popup = document.createElement('div')
  Object.defineProperty(popup, 'offsetWidth', { value: inlineSize })
  Object.defineProperty(popup, 'scrollHeight', { value: blockSize })
  popup.showPopover = vi.fn()
  popup.hidePopover = vi.fn()
  document.body.appendChild(popup)
  return popup
}

function setup({ anchor, popup, placement = 'field', isSheet = false }) {
  const isOpen = ref(false)
  const sheet = ref(isSheet)
  const onDismiss = vi.fn()
  const scope = effectScope()
  scope.run(() =>
    useAnchoredPopup({
      anchor: () => anchor,
      popup: () => popup,
      isOpen,
      isSheet: sheet,
      placement: () => placement,
      onDismiss,
    }),
  )
  return { isOpen, sheet, onDismiss, scope }
}

function variable(element, name) {
  return element.style.getPropertyValue(name)
}

beforeEach(() => {
  setViewport(VIEWPORT)
  vi.stubGlobal('requestAnimationFrame', (callback) => {
    callback()
    return 1
  })
  vi.stubGlobal('cancelAnimationFrame', () => {})
})

afterEach(() => {
  vi.unstubAllGlobals()
  document.body.innerHTML = ''
})

describe('useAnchoredPopup', () => {
  it('shows the list in the top layer under the field, as wide as the field, from the left in LTR', async () => {
    const anchor = anchorAt({ left: 200, top: 100 })
    const popup = popupOf()
    const { isOpen } = setup({ anchor, popup })
    isOpen.value = true
    await nextTick()
    expect(popup.showPopover).toHaveBeenCalled()
    expect(variable(popup, '--dropdown-anchor-inline-size')).toBe('300px')
    expect(variable(popup, '--dropdown-inline-start')).toBe('200px')
    expect(variable(popup, '--dropdown-block-start')).toBe('152px')
    expect(variable(popup, '--dropdown-block-end')).toBe('')
    expect(variable(popup, '--dropdown-max-block-size')).toBe('320px')
    expect(popup.dataset.side).toBe('below')
  })

  it('measures from the right edge in RTL', async () => {
    const anchor = anchorAt({ left: 200, top: 100, direction: 'rtl' })
    const popup = popupOf()
    const { isOpen } = setup({ anchor, popup })
    isOpen.value = true
    await nextTick()
    expect(variable(popup, '--dropdown-inline-start')).toBe('940px')
  })

  it('flips above near the viewport bottom and aligns a menu to the trigger end', async () => {
    const anchor = anchorAt({ left: 1300, top: 820, width: 36, height: 36 })
    const popup = popupOf({ inlineSize: 220, blockSize: 180 })
    const { isOpen } = setup({ anchor, popup, placement: 'menu' })
    isOpen.value = true
    await nextTick()
    expect(popup.dataset.side).toBe('above')
    expect(variable(popup, '--dropdown-block-end')).toBe('84px')
    expect(variable(popup, '--dropdown-block-start')).toBe('')
    expect(variable(popup, '--dropdown-inline-start')).toBe('1116px')
  })

  it('repositions on scroll and dismisses when the field leaves the view', async () => {
    let top = 100
    const anchor = anchorAt({ left: 200, top })
    anchor.getBoundingClientRect = () => ({
      left: 200,
      top,
      width: 300,
      height: 48,
      right: 500,
      bottom: top + 48,
    })
    const popup = popupOf()
    const { isOpen, onDismiss } = setup({ anchor, popup })
    isOpen.value = true
    await nextTick()
    top = 300
    window.dispatchEvent(new Event('scroll'))
    expect(variable(popup, '--dropdown-block-start')).toBe('352px')
    top = -100
    window.dispatchEvent(new Event('scroll'))
    expect(onDismiss).toHaveBeenCalledTimes(1)
  })

  it('dismisses on a pointer press outside the field and the list only', async () => {
    const anchor = anchorAt({ left: 0, top: 0 })
    const popup = popupOf()
    const { isOpen, onDismiss } = setup({ anchor, popup })
    isOpen.value = true
    await nextTick()
    popup.dispatchEvent(new Event('pointerdown', { bubbles: true }))
    anchor.dispatchEvent(new Event('pointerdown', { bubbles: true }))
    expect(onDismiss).not.toHaveBeenCalled()
    document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }))
    expect(onDismiss).toHaveBeenCalledTimes(1)
  })

  it('does not position a bottom sheet and stops listening once closed', async () => {
    const anchor = anchorAt({ left: 0, top: 0 })
    const popup = popupOf()
    const { isOpen, onDismiss, scope } = setup({ anchor, popup, isSheet: true })
    isOpen.value = true
    await nextTick()
    expect(popup.showPopover).toHaveBeenCalled()
    expect(variable(popup, '--dropdown-inline-start')).toBe('')
    document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }))
    expect(onDismiss).not.toHaveBeenCalled()
    isOpen.value = false
    await nextTick()
    expect(popup.hidePopover).toHaveBeenCalled()
    scope.stop()
  })

  it('treats a field hidden by a scrolling ancestor as out of view', () => {
    const container = document.createElement('div')
    container.style.overflowY = 'auto'
    container.getBoundingClientRect = () => ({
      left: 0,
      top: 0,
      right: 600,
      bottom: 300,
      width: 600,
      height: 300,
    })
    document.body.appendChild(container)
    const anchor = anchorAt({ left: 10, top: 400 })
    container.appendChild(anchor)
    expect(isAnchorVisible(anchor)).toBe(false)
  })
})
