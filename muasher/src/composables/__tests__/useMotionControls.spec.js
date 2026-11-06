import { afterEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { withSetup } from '@/test-utils/withSetup'
import { stubMatchMedia } from '@/test-utils/matchMedia'
import { TABLET_UP_QUERY } from '@/constants/breakpoints'
import { useMotionControls } from '@/composables/useMotionControls'

afterEach(() => vi.unstubAllGlobals())

function mountAt(width, reducedMotion = false) {
  const media = stubMatchMedia({ width, reducedMotion })
  const { api, wrapper } = withSetup(() => useMotionControls())
  return { media, controls: api, wrapper }
}

describe('useMotionControls', () => {
  it('hides the motion controls on phones, below the tablet breakpoint', () => {
    const { controls, wrapper } = mountAt(390)
    expect(controls.isVisible.value).toBe(false)
    expect(window.matchMedia).toHaveBeenCalledWith(TABLET_UP_QUERY)
    wrapper.unmount()
  })

  it.each([
    [767, false],
    [768, true],
    [1024, true],
    [1440, true],
  ])('at %i px wide the controls are visible: %s', (width, visible) => {
    const { controls, wrapper } = mountAt(width)
    expect(controls.isVisible.value).toBe(visible)
    wrapper.unmount()
  })

  it('does not depend on the reduced motion preference', () => {
    const desktop = mountAt(1440, true)
    expect(desktop.controls.isVisible.value).toBe(true)
    desktop.wrapper.unmount()
    const phone = mountAt(390, true)
    expect(phone.controls.isVisible.value).toBe(false)
    phone.wrapper.unmount()
  })

  it('updates when a tablet is rotated or a window is resized across the breakpoint', async () => {
    const { media, controls, wrapper } = mountAt(1024)
    media.resize(600)
    await nextTick()
    expect(controls.isVisible.value).toBe(false)
    media.resize(820)
    await nextTick()
    expect(controls.isVisible.value).toBe(true)
    wrapper.unmount()
    expect(media.listenerCount()).toBe(0)
  })
})
