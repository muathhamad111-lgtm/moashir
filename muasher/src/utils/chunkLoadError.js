const CHUNK_LOAD_FAILURES = [
  /failed to fetch dynamically imported module/i,
  /importing a module script failed/i,
  /error loading dynamically imported module/i,
  /unable to preload css/i,
]

function messageOf(error) {
  if (typeof error === 'string') return error
  return typeof error?.message === 'string' ? error.message : ''
}

export function isChunkLoadError(error) {
  const message = messageOf(error)
  return CHUNK_LOAD_FAILURES.some((pattern) => pattern.test(message))
}
