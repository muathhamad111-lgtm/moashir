export const PREFETCH_FRESH_MS = 30_000

const held = new Map()

function isFresh(entry) {
  return Boolean(entry) && Date.now() - entry.at < PREFETCH_FRESH_MS
}

export function prefetchRead(key, fetcher) {
  if (isFresh(held.get(key))) return
  const promise = fetcher()
  held.set(key, { promise, at: Date.now() })
  promise.catch(() => {
    if (held.get(key)?.promise === promise) held.delete(key)
  })
}

export function takeRead(key, fetcher) {
  const entry = held.get(key)
  held.delete(key)
  return isFresh(entry) ? entry.promise.catch(() => fetcher()) : fetcher()
}

export function clearPrefetchedReads() {
  held.clear()
}
