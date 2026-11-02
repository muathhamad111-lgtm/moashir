import { createBackendSession, isAuthTransition } from './backendSession'
import { setSessionSource } from '../stores/sessionStore'
import { onBeforeResolve } from '../contract/requestHooks'

export function installBackendSession(worker, session = createBackendSession()) {
  setSessionSource(session.descriptor)
  onBeforeResolve(() => session.refresh())
  worker.events.on('request:start', ({ request }) => {
    if (isAuthTransition(request)) session.invalidate()
  })
  return session
}
