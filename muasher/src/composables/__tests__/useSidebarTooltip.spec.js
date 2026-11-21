import { afterEach, describe, expect, it } from 'vitest'
import { useSidebarTooltip } from '@/composables/useSidebarTooltip'

function itemAt({ left, right, top, height }, labelWidth = 0) {
  return {
    getBoundingClientRect: () => ({ left, right, top, height }),
    querySelector: () => ({ getBoundingClientRect: () => ({ width: labelWidth }) }),
  }
}

afterEach(() => {
  document.documentElement.dir = ''
})

describe('useSidebarTooltip', () => {
  it('places the label beside the hovered icon on the content side in RTL', () => {
    document.documentElement.dir = 'rtl'
    const tooltip = useSidebarTooltip()
    const right = window.innerWidth
    tooltip.show('لوحة المعلومات', itemAt({ left: right - 72, right, top: 100, height: 40 }))
    expect(tooltip.hint.value.label).toBe('لوحة المعلومات')
    expect(tooltip.style.value).toEqual({ top: '120px', insetInlineStart: '80px' })
  })

  it('places it after the icon in LTR', () => {
    document.documentElement.dir = 'ltr'
    const tooltip = useSidebarTooltip()
    tooltip.show('Dashboard', itemAt({ left: 0, right: 72, top: 10, height: 20 }))
    expect(tooltip.style.value).toEqual({ top: '20px', insetInlineStart: '80px' })
  })

  it('stays hidden while the label is visible and hides on leave', () => {
    const tooltip = useSidebarTooltip()
    tooltip.show('Dashboard', itemAt({ left: 0, right: 240, top: 10, height: 20 }, 120))
    expect(tooltip.hint.value).toBeNull()
    tooltip.show('Dashboard', itemAt({ left: 0, right: 72, top: 10, height: 20 }))
    expect(tooltip.hint.value).not.toBeNull()
    tooltip.hide()
    expect(tooltip.hint.value).toBeNull()
  })
})
