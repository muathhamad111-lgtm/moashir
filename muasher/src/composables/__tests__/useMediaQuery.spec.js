import { afterEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { withSetup } from '@/test-utils/withSetup'
import { stubMatchMedia } from '@/test-utils/matchMedia'
import { useMediaQuery } from '@/composables/useMediaQuery'

const QUERY = '(min-width: 768px)'

afterEach(() => vi.unstubAllGlobals())

describe('useMediaQuery', () => {
  it('reads whether the query matches when it is created', () => {
    stubMatchMedia({ width: 1440 })
    const { api: matches, wrapper } = withSetup(() => useMediaQuery(QUERY))
    expect(matches.value).toBe(true)
    expect(window.matchMedia).toHaveBeenCalledWith(QUERY)
    wrapper.unmount()
  })

  it('follows the query when the viewport changes, without a reload', async () => {
    const media = stubMatchMedia({ width: 1440 })
    const { api: matches, wrapper } = withSetup(() => useMediaQuery(QUERY))
    media.resize(390)
    await nextTick()
    expect(matches.value).toBe(false)
    media.resize(768)
    await nextTick()
    expect(matches.value).toBe(true)
    wrapper.unmount()
  })

  it('removes its change listener when the owner unmounts', () => {
    const media = stubMatchMedia({ width: 390 })
    const { wrapper } = withSetup(() => useMediaQuery(QUERY))
    expect(media.listenerCount()).toBe(1)
    wrapper.unmount()
    expect(media.listenerCount()).toBe(0)
  })

  it('reports no match when the browser cannot evaluate media queries', () => {
    vi.stubGlobal('matchMedia', undefined)
    const { api: matches, wrapper } = withSetup(() => useMediaQuery(QUERY))
    expect(matches.value).toBe(false)
    wrapper.unmount()
  })
})
