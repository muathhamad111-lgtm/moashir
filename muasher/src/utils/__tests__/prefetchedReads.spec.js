import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import {
  PREFETCH_FRESH_MS,
  clearPrefetchedReads,
  prefetchRead,
  takeRead,
} from '@/utils/prefetchedReads'

describe('prefetchedReads', () => {
  beforeEach(() => vi.useFakeTimers({ toFake: ['Date'] }))
  afterEach(() => {
    clearPrefetchedReads()
    vi.useRealTimers()
  })

  it('hands a fresh prefetched read to the page once, without fetching again', async () => {
    const fetcher = vi.fn().mockResolvedValue({ data: 'terms' })
    prefetchRead('terms', fetcher)
    prefetchRead('terms', fetcher)
    await expect(takeRead('terms', fetcher)).resolves.toEqual({ data: 'terms' })
    expect(fetcher).toHaveBeenCalledTimes(1)
    await takeRead('terms', fetcher)
    expect(fetcher).toHaveBeenCalledTimes(2)
  })

  it('fetches anew once a prefetched read is older than the freshness window', async () => {
    const fetcher = vi
      .fn()
      .mockResolvedValueOnce({ data: 'old' })
      .mockResolvedValue({ data: 'new' })
    prefetchRead('regions', fetcher)
    vi.setSystemTime(Date.now() + PREFETCH_FRESH_MS + 1)
    await expect(takeRead('regions', fetcher)).resolves.toEqual({ data: 'new' })
    expect(fetcher).toHaveBeenCalledTimes(2)
  })

  it('fetches anew when the prefetched read failed', async () => {
    const fetcher = vi
      .fn()
      .mockRejectedValueOnce(new Error('offline'))
      .mockResolvedValue({ data: 'ok' })
    prefetchRead('options', fetcher)
    await expect(takeRead('options', fetcher)).resolves.toEqual({ data: 'ok' })
    expect(fetcher).toHaveBeenCalledTimes(2)
  })

  it('fetches directly when nothing was prefetched', async () => {
    const fetcher = vi.fn().mockResolvedValue({ data: 'direct' })
    await expect(takeRead('missing', fetcher)).resolves.toEqual({ data: 'direct' })
    expect(fetcher).toHaveBeenCalledTimes(1)
  })
})
