import { bypass } from 'msw'
import { COMMITTEE_PERSONA, descriptorForPersona } from '../stores/accounts'
import { logger } from '@/utils/logger'

export const BACKEND_SESSION_PATH = '/api/v1/auth/session'
export const SESSION_CACHE_MS = 5000

const AUTH_TRANSITION_PATH =
  /^\/api\/v1\/auth\/(login|logout|otp\/verify|mfa\/challenge|mfa\/confirm|invitations\/[^/]+\/accept)$/

function roleKeysOf(roles) {
  return (roles ?? []).map((role) => (typeof role === 'string' ? role : role?.key)).filter(Boolean)
}

function personaKeyOf(roles, appointments) {
  return appointments.length ? COMMITTEE_PERSONA : (roles[0] ?? null)
}

export function demoDescriptorOf(user) {
  if (!user?.id) return null
  const roles = roleKeysOf(user.roles)
  const appointments = user.committee_appointments ?? []
  const personaKey = personaKeyOf(roles, appointments)
  const persona = personaKey ? descriptorForPersona(personaKey) : {}
  return {
    id: persona.id ?? user.id,
    name: user.name ?? null,
    roles: roles.length ? roles : (persona.roles ?? []),
    entityId: persona.entityId ?? null,
    locale: user.locale ?? null,
    superAdmin: user.is_super_admin === true,
  }
}

export function isAuthTransition(request) {
  return request.method !== 'GET' && AUTH_TRANSITION_PATH.test(new URL(request.url).pathname)
}

async function readSession(fetcher) {
  const request = new Request(new URL(BACKEND_SESSION_PATH, globalThis.location.origin), {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
  })
  const response = await fetcher(bypass(request))
  if (!response.ok) return null
  const body = await response.json()
  return demoDescriptorOf(body?.data)
}

export function createBackendSession({
  fetcher = (request) => fetch(request),
  now = () => Date.now(),
} = {}) {
  const state = { descriptor: null, loadedAt: null, pending: null }

  function isFresh() {
    return state.loadedAt !== null && now() - state.loadedAt < SESSION_CACHE_MS
  }

  async function load() {
    try {
      state.descriptor = await readSession(fetcher)
    } catch (error) {
      logger.error(error, { source: 'hybrid-session' })
      state.descriptor = null
    }
    state.loadedAt = now()
  }

  return {
    descriptor: () => state.descriptor,
    refresh() {
      if (isFresh()) return Promise.resolve()
      state.pending ??= load().finally(() => {
        state.pending = null
      })
      return state.pending
    },
    invalidate() {
      state.loadedAt = null
    },
  }
}
