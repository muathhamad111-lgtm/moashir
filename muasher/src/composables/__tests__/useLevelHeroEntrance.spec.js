import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, ref } from 'vue'
import { flushPromises, mount } from '@vue/test-utils'
import { useLevelHeroEntrance } from '@/composables/useLevelHeroEntrance'
import { stubMatchMedia } from '@/test-utils/matchMedia'
import { STORAGE_KEYS } from '@/constants/storageKeys'

const KEY = '7601.c-silver'
const STORED = `${STORAGE_KEYS.levelHeroEntrance}.${KEY}`
let observers = []

class FakeIntersectionObserver {
  constructor(callback) {
    this.callback = callback
    this.disconnected = false
    observers.push(this)
  }

  observe() {}

  disconnect() {
    this.disconnected = true
  }

  reveal() {
    this.callback([{ isIntersecting: true }])
  }
}

function mountEntrance({ ready = true, score = '74.50' } = {}) {
  const state = { ready: ref(ready), score: ref(score), api: null }
  const wrapper = mount(
    defineComponent({
      setup() {
        const root = ref(null)
        state.api = useLevelHeroEntrance(root, {
          ready: state.ready,
          playKey: ref(KEY),
          score: state.score,
        })
        return () => h('section', { ref: root })
      },
    }),
  )
  return { ...state, wrapper }
}

beforeEach(() => {
  observers = []
  sessionStorage.clear()
  vi.useFakeTimers({
    toFake: [
      'setTimeout',
      'clearTimeout',
      'requestAnimationFrame',
      'cancelAnimationFrame',
      'performance',
    ],
  })
  vi.stubGlobal('IntersectionObserver', FakeIntersectionObserver)
  stubMatchMedia({ width: 1440, reducedMotion: false })
})

afterEach(() => {
  vi.useRealTimers()
  vi.unstubAllGlobals()
})

describe('useLevelHeroEntrance', () => {
  it('waits hidden until the card is in view, then plays once and settles on the final score', async () => {
    const { api } = mountEntrance()
    await flushPromises()
    expect(api.phase.value).toBe('pending')
    observers[0].reveal()
    expect(api.phase.value).toBe('playing')
    expect(sessionStorage.getItem(STORED)).toBe('1')
    expect(api.displayScore.value).toBe(0)
    vi.advanceTimersByTime(700)
    expect(api.displayScore.value).toBeGreaterThan(60)
    expect(api.displayScore.value).toBeLessThan(74.5)
    vi.advanceTimersByTime(600)
    expect(api.phase.value).toBe('static')
    expect(api.displayScore.value).toBe('74.50')
  })

  it('shows the final state at once when it already played in this session', async () => {
    sessionStorage.setItem(STORED, '1')
    const { api } = mountEntrance()
    await flushPromises()
    expect(api.phase.value).toBe('static')
    expect(observers).toHaveLength(0)
    expect(api.displayScore.value).toBe('74.50')
  })

  it('never animates under reduced motion', async () => {
    stubMatchMedia({ width: 1440, reducedMotion: true })
    const { api } = mountEntrance()
    await flushPromises()
    expect(api.phase.value).toBe('static')
    expect(observers).toHaveLength(0)
  })

  it('waits for the data before arming', async () => {
    const { api, ready } = mountEntrance({ ready: false })
    await flushPromises()
    expect(api.phase.value).toBe('static')
    ready.value = true
    await flushPromises()
    expect(api.phase.value).toBe('pending')
  })

  it('jumps to the final state when the tab is hidden', async () => {
    vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('hidden')
    const { api } = mountEntrance()
    await flushPromises()
    observers[0].reveal()
    expect(api.phase.value).toBe('static')
    expect(sessionStorage.getItem(STORED)).toBe('1')
  })

  it('stops observing and counting when the card unmounts', async () => {
    const { wrapper } = mountEntrance()
    await flushPromises()
    wrapper.unmount()
    expect(observers[0].disconnected).toBe(true)
  })
})
