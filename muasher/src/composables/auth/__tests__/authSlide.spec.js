import { afterEach, describe, expect, it, vi } from 'vitest'
import {
  AUTH_MOTION,
  PANEL_LEAVING,
  holdVerified,
  slideDirection,
  slideIn,
  slideOut,
  swipeIn,
  swipeOut,
} from '@/composables/auth/authSlide'

function stubReducedMotion(reduce) {
  vi.stubGlobal('matchMedia', (query) => ({
    matches: reduce && query.includes('prefers-reduced-motion'),
    media: query,
    addEventListener: () => {},
    removeEventListener: () => {},
  }))
}

function animatedElement() {
  const element = document.createElement('div')
  element.animate = vi.fn(() => ({ finished: Promise.resolve() }))
  return element
}

function shiftsOf(element) {
  const [keyframes] = element.animate.mock.calls[0]
  return keyframes.map((frame) => parseFloat(frame.translate))
}

afterEach(() => {
  document.documentElement.dir = ''
  vi.unstubAllGlobals()
})

describe('slideDirection', () => {
  it.each([
    [0, 1, 1],
    [1, 1, 1],
    [1, 0, -1],
    [-1, 1, 0],
    [0, -1, 0],
  ])('goes from position %i to %i with direction %i', (from, to, direction) => {
    stubReducedMotion(false)
    expect(slideDirection(from, to)).toBe(direction)
  })

  it('stays still for reduced motion', () => {
    stubReducedMotion(true)
    expect(slideDirection(0, 1)).toBe(0)
  })
})

describe('slideOut and slideIn', () => {
  it.each([
    ['ltr', 1],
    ['rtl', -1],
  ])('moves ahead along the %s reading direction', async (dir, ahead) => {
    document.documentElement.dir = dir
    const leaving = animatedElement()
    const entering = animatedElement()
    const done = vi.fn()
    slideOut(leaving, 1, done)
    slideIn(entering, 1, done)
    await new Promise((resolve) => setTimeout(resolve))
    expect(Math.sign(shiftsOf(leaving)[1])).toBe(-ahead)
    expect(leaving.inert).toBe(true)
    expect(entering.inert).toBeFalsy()
    expect(Math.sign(shiftsOf(entering)[0])).toBe(ahead)
    expect(done).toHaveBeenCalledTimes(2)
  })

  it('swaps at once without animating when there is no direction', async () => {
    const element = animatedElement()
    const done = vi.fn()
    slideIn(element, 0, done)
    expect(done).toHaveBeenCalledOnce()
    slideOut(element, 0, done)
    expect(done).toHaveBeenCalledOnce()
    await new Promise((resolve) => setTimeout(resolve))
    expect(done).toHaveBeenCalledTimes(2)
    expect(element.animate).not.toHaveBeenCalled()
    expect(element.inert).toBeFalsy()
  })
})

describe('swipeOut and swipeIn', () => {
  it.each([
    ['ltr', 1],
    ['rtl', -1],
  ])('moves both panels a full width plus the gap along %s', async (dir, ahead) => {
    document.documentElement.dir = dir
    const leaving = animatedElement()
    const entering = animatedElement()
    const done = vi.fn()
    swipeOut(leaving, 1, done)
    swipeIn(entering, 1, done)
    await new Promise((resolve) => setTimeout(resolve))
    expect(shiftsOf(leaving)[1]).toBe(-AUTH_MOTION.panelGapPx * ahead)
    expect(shiftsOf(entering)[0]).toBe(AUTH_MOTION.panelGapPx * ahead)
    expect(leaving.classList.contains(PANEL_LEAVING)).toBe(true)
    expect(leaving.inert).toBe(true)
    expect(entering.classList.contains(PANEL_LEAVING)).toBe(false)
    expect(done).toHaveBeenCalledTimes(2)
  })

  it('swaps at once without a direction', async () => {
    const element = animatedElement()
    const done = vi.fn()
    swipeIn(element, 0, done)
    swipeOut(element, 0, done)
    await new Promise((resolve) => setTimeout(resolve))
    expect(done).toHaveBeenCalledTimes(2)
    expect(element.animate).not.toHaveBeenCalled()
    expect(element.classList.contains(PANEL_LEAVING)).toBe(false)
  })
})

describe('holdVerified', () => {
  it('waits for the success state before moving on', async () => {
    stubReducedMotion(false)
    vi.useFakeTimers()
    const held = vi.fn()
    holdVerified().then(held)
    await vi.advanceTimersByTimeAsync(AUTH_MOTION.verifiedHoldMs - 1)
    expect(held).not.toHaveBeenCalled()
    await vi.advanceTimersByTimeAsync(1)
    expect(held).toHaveBeenCalled()
    vi.useRealTimers()
  })

  it('moves on at once for reduced motion', async () => {
    stubReducedMotion(true)
    const held = vi.fn()
    await holdVerified().then(held)
    expect(held).toHaveBeenCalled()
  })
})
