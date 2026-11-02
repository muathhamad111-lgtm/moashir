import axios from 'axios'
import { API_ORIGIN, CSRF_COOKIE_PATH } from './config'

export const XSRF_COOKIE_NAME = 'XSRF-TOKEN'
export const XSRF_HEADER_NAME = 'X-XSRF-TOKEN'

const STATE_CHANGING_METHODS = new Set(['post', 'put', 'patch', 'delete'])

const csrfClient = axios.create({
  baseURL: API_ORIGIN,
  withCredentials: true,
  headers: { Accept: 'application/json' },
})

let pendingRefresh = null

export function readXsrfToken() {
  const prefix = `${XSRF_COOKIE_NAME}=`
  const entry = document.cookie
    .split(';')
    .map((part) => part.trim())
    .find((part) => part.startsWith(prefix))
  return entry ? decodeURIComponent(entry.slice(prefix.length)) : null
}

export function isStateChanging(method) {
  return STATE_CHANGING_METHODS.has(String(method ?? 'get').toLowerCase())
}

export function refreshCsrfCookie() {
  if (!pendingRefresh) {
    pendingRefresh = csrfClient.get(CSRF_COOKIE_PATH).finally(() => {
      pendingRefresh = null
    })
  }
  return pendingRefresh
}

export async function ensureCsrfCookie() {
  if (!readXsrfToken()) await refreshCsrfCookie()
}
