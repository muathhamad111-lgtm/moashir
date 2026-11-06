import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick, ref } from 'vue'
import { withSetup } from '@/test-utils/withSetup'
import { useTourStepper } from '@/composables/useTourStepper'

function stubMotion(reduced) {
  vi.stubGlobal('matchMedia', () => ({ matches: reduced }))
}

beforeEach(() => vi.useFakeTimers())
afterEach(() => {
  vi.useRealTimers()
  vi.unstubAllGlobals()
})

describe('useTourStepper', () => {
  it('advances automatically and wraps around', () => {
    stubMotion(false)
    const { api: stepper, wrapper } = withSetup(() => useTourStepper(ref(3), { interval: 1000 }))
    expect(stepper.playing.value).toBe(true)
    vi.advanceTimersByTime(1000)
    expect(stepper.active.value).toBe(1)
    vi.advanceTimersByTime(2000)
    expect(stepper.active.value).toBe(0)
    wrapper.unmount()
  })

  it('stops advancing when the visitor picks a step', () => {
    stubMotion(false)
    const { api: stepper, wrapper } = withSetup(() => useTourStepper(ref(4), { interval: 1000 }))
    stepper.select(2)
    vi.advanceTimersByTime(5000)
    expect(stepper.active.value).toBe(2)
    expect(stepper.playing.value).toBe(false)
    wrapper.unmount()
  })

  it('does not play by itself when reduced motion is requested, and can be toggled', async () => {
    stubMotion(true)
    const { api: stepper, wrapper } = withSetup(() => useTourStepper(ref(3), { interval: 1000 }))
    expect(stepper.playing.value).toBe(false)
    vi.advanceTimersByTime(3000)
    expect(stepper.active.value).toBe(0)
    stepper.toggle()
    await nextTick()
    vi.advanceTimersByTime(1000)
    expect(stepper.active.value).toBe(1)
    wrapper.unmount()
  })

  it('pauses while the tour is off screen and resumes with the time left', async () => {
    stubMotion(false)
    const visible = ref(true)
    const { api: stepper, wrapper } = withSetup(() =>
      useTourStepper(ref(3), { interval: 1000, visible }),
    )
    vi.advanceTimersByTime(600)
    visible.value = false
    await nextTick()
    expect(stepper.running.value).toBe(false)
    vi.advanceTimersByTime(5000)
    expect(stepper.active.value).toBe(0)
    visible.value = true
    await nextTick()
    vi.advanceTimersByTime(399)
    expect(stepper.active.value).toBe(0)
    vi.advanceTimersByTime(1)
    expect(stepper.active.value).toBe(1)
    wrapper.unmount()
  })
})
