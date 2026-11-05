import { setupWorker } from 'msw/browser'
import { handlers, handlersOfModules } from './handlers'
import { installBackendSession } from './hybrid/installBackendSession'
import { setRandomFaults } from './contract/faults'
import { keepPageAnnounced } from './keepPageAnnounced'
import { MOCK_MODES } from '@/constants/mockModules'

const UNHANDLED = 'bypass'

export async function startMockWorker(plan) {
  if (plan.mode !== MOCK_MODES.HYBRID) {
    await setupWorker(...handlers).start({ onUnhandledRequest: UNHANDLED })
    keepPageAnnounced()
    return
  }
  const worker = setupWorker(...handlersOfModules(plan.modules))
  setRandomFaults(false)
  installBackendSession(worker)
  await worker.start({ onUnhandledRequest: UNHANDLED, quiet: true })
  keepPageAnnounced()
}
