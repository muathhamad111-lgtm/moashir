import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { effectScope, nextTick, ref } from 'vue'

let useDeferredVisibility
let SHOW_DELAY_MS
let MINIMUM_VISIBLE_MS

beforeEach(async () => {
  vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout', 'performance'] })
  vi.resetModules()
  ;({ useDeferredVisibility, SHOW_DELAY_MS, MINIMUM_VISIBLE_MS } =
    await import('../useDeferredVisibility'))
})

afterEach(() => {
  vi.useRealTimers()
})

function follow(loading) {
  const scope = effectScope()
  const visible = scope.run(() => useDeferredVisibility(loading))
  return { visible, stop: () => scope.stop() }
}

describe('useDeferredVisibility', () => {
  it('never shows for a load that ends within the delay', async () => {
    const loading = ref(true)
    const { visible } = follow(loading)
    vi.advanceTimersByTime(SHOW_DELAY_MS - 50)
    loading.value = false
    await nextTick()
    vi.advanceTimersByTime(MINIMUM_VISIBLE_MS)
    expect(visible.value).toBe(false)
  })

  it('shows after the delay and stays for the minimum time once shown', async () => {
    const loading = ref(true)
    const { visible } = follow(loading)
    vi.advanceTimersByTime(SHOW_DELAY_MS)
    expect(visible.value).toBe(true)

    vi.advanceTimersByTime(100)
    loading.value = false
    await nextTick()
    vi.advanceTimersByTime(MINIMUM_VISIBLE_MS - 150)
    expect(visible.value).toBe(true)
    vi.advanceTimersByTime(50)
    expect(visible.value).toBe(false)
  })

  it('hides at once when the load outlasted the minimum time', async () => {
    const loading = ref(true)
    const { visible } = follow(loading)
    vi.advanceTimersByTime(SHOW_DELAY_MS + MINIMUM_VISIBLE_MS)
    loading.value = false
    await nextTick()
    vi.advanceTimersByTime(0)
    expect(visible.value).toBe(false)
  })

  it('shows at once when it takes over from a waiting page that just closed', async () => {
    const first = follow(ref(true))
    vi.advanceTimersByTime(SHOW_DELAY_MS)
    first.stop()

    const next = follow(ref(true))
    expect(next.visible.value).toBe(true)
  })

  it('waits for the delay again once the last waiting page closed long ago', () => {
    const first = follow(ref(true))
    vi.advanceTimersByTime(SHOW_DELAY_MS)
    first.stop()
    vi.advanceTimersByTime(SHOW_DELAY_MS)

    const next = follow(ref(true))
    expect(next.visible.value).toBe(false)
  })
})
