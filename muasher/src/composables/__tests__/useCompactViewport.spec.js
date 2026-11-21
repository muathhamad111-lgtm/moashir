import { afterEach, describe, expect, it, vi } from 'vitest'
import { effectScope } from 'vue'
import { useCompactViewport } from '@/composables/useCompactViewport'
import { stubMatchMedia } from '@/test-utils/matchMedia'

afterEach(() => vi.unstubAllGlobals())

function compactAt(width) {
  const media = stubMatchMedia({ width })
  const scope = effectScope()
  const isCompact = scope.run(() => useCompactViewport())
  return { isCompact, media, scope }
}

describe('useCompactViewport', () => {
  it('is compact below the md breakpoint token only', () => {
    expect(compactAt(390).isCompact.value).toBe(true)
    expect(compactAt(767).isCompact.value).toBe(true)
    expect(compactAt(768).isCompact.value).toBe(false)
    expect(compactAt(1440).isCompact.value).toBe(false)
  })

  it('follows a resize across the breakpoint and releases its listener', () => {
    const { isCompact, media, scope } = compactAt(1440)
    media.resize(390)
    expect(isCompact.value).toBe(true)
    media.resize(1024)
    expect(isCompact.value).toBe(false)
    scope.stop()
    expect(media.listenerCount()).toBe(0)
  })

  it('treats a browser without matchMedia as a desktop', () => {
    vi.stubGlobal('matchMedia', undefined)
    const scope = effectScope()
    expect(scope.run(() => useCompactViewport()).value).toBe(false)
  })
})
