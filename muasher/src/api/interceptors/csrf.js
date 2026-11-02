import { ensureCsrfCookie, isStateChanging, refreshCsrfCookie } from '../csrf'

export const CSRF_MISMATCH_STATUS = 419

export async function ensureCsrfBeforeWrite(config) {
  if (isStateChanging(config.method)) await ensureCsrfCookie()
  return config
}

export function retryOnceAfterCsrfMismatch(client) {
  return async (error) => {
    const { config, response } = error
    if (response?.status !== CSRF_MISMATCH_STATUS || !config || config.csrfRetried) throw error
    await refreshCsrfCookie()
    return client.request({ ...config, csrfRetried: true })
  }
}
