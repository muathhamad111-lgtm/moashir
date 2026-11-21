import { afterEach, describe, expect, it, vi } from 'vitest'
import { reveal } from '@/directives/reveal'

let observers = []

class FakeObserver {
  constructor(callback, options) {
    this.callback = callback
    this.options = options
    this.disconnect = vi.fn()
    observers.push(this)
  }

  observe(element) {
    this.element = element
  }

  intersect(isIntersecting) {
    this.callback([{ isIntersecting, target: this.element }])
  }
}

function stubMotion(reduced) {
  vi.stubGlobal('matchMedia', () => ({ matches: reduced }))
}

afterEach(() => {
  observers = []
  vi.unstubAllGlobals()
})

describe('v-reveal', () => {
  it('keeps the element pending until it enters the viewport, then reveals it once', () => {
    stubMotion(false)
    vi.stubGlobal('IntersectionObserver', FakeObserver)
    const element = document.createElement('div')
    reveal.mounted(element, { value: undefined })
    expect(element.classList.contains('is-reveal-pending')).toBe(true)

    observers[0].intersect(false)
    expect(element.classList.contains('is-revealed')).toBe(false)

    observers[0].intersect(true)
    expect(element.classList.contains('is-reveal-pending')).toBe(false)
    expect(element.classList.contains('is-revealed')).toBe(true)
    expect(observers[0].disconnect).toHaveBeenCalled()
  })

  it('marks staggered containers so their children animate in sequence', () => {
    stubMotion(false)
    vi.stubGlobal('IntersectionObserver', FakeObserver)
    const element = document.createElement('ul')
    reveal.mounted(element, { value: { stagger: true } })
    expect(element.classList.contains('reveal-stagger')).toBe(true)
  })

  it('shows the element at once under reduced motion', () => {
    stubMotion(true)
    vi.stubGlobal('IntersectionObserver', FakeObserver)
    const element = document.createElement('div')
    reveal.mounted(element, { value: undefined })
    expect(element.classList.contains('is-revealed')).toBe(true)
    expect(element.classList.contains('is-reveal-pending')).toBe(false)
    expect(observers).toHaveLength(0)
  })

  it('disconnects the observer when the element is removed', () => {
    stubMotion(false)
    vi.stubGlobal('IntersectionObserver', FakeObserver)
    const element = document.createElement('div')
    reveal.mounted(element, { value: undefined })
    reveal.unmounted(element)
    expect(observers[0].disconnect).toHaveBeenCalled()
  })
})
