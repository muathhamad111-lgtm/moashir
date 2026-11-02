import { readCookie } from './cookies'
import { errorResponse } from './responses'
import { requestLocale } from './locale'

export const RANDOM_FAULTS_COOKIE = 'moashir_mock_faults'
export const FORCED_FAULT_COOKIE = 'moashir_mock_fault'

const FAULT_SWITCH = { ON: 'on', OFF: 'off' }

const FORCED_FAULT_MESSAGES = {
  ar: 'الخدمة غير متاحة مؤقتًا. يرجى المحاولة مرة أخرى.',
  en: 'The service is temporarily unavailable. Please try again.',
}

const state = { randomFaults: null, forcedFaults: [] }

function enabledByDefault() {
  return import.meta.env.MODE !== 'test'
}

function cookieSwitch() {
  const value = readCookie(RANDOM_FAULTS_COOKIE)
  if (value === FAULT_SWITCH.ON) return true
  if (value === FAULT_SWITCH.OFF) return false
  return null
}

export function randomFaultsEnabled() {
  if (state.randomFaults !== null) return state.randomFaults
  return cookieSwitch() ?? enabledByDefault()
}

export function failsRandomly(defaultRate) {
  return (rate = defaultRate) => randomFaultsEnabled() && Math.random() < rate
}

export function setRandomFaults(enabled) {
  state.randomFaults = enabled
}

export function forceMockFault(fault) {
  state.forcedFaults.push(fault)
}

export function clearMockFaults() {
  state.randomFaults = null
  state.forcedFaults = []
}

function cookieFaults() {
  const raw = readCookie(FORCED_FAULT_COOKIE)
  if (!raw) return []
  try {
    const parsed = JSON.parse(raw)
    return Array.isArray(parsed) ? parsed : [parsed]
  } catch {
    return []
  }
}

function matches(fault, method, pathname) {
  return fault.method?.toUpperCase() === method.toUpperCase() && fault.path === pathname
}

export function forcedFaultFor(method, url) {
  const pathname = new URL(url).pathname
  return [...state.forcedFaults, ...cookieFaults()].find((fault) => matches(fault, method, pathname)) ?? null
}

export function forcedFaultResponse(fault, request) {
  const message = FORCED_FAULT_MESSAGES[requestLocale(request)]
  return errorResponse(fault.status ?? 503, fault.code ?? 'service_unavailable', message)
}
