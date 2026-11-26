import { setupWorker } from 'msw/browser'
import { handlers } from './handlers'
import { keepPageAnnounced } from './keepPageAnnounced'

const UNHANDLED = 'bypass'

export async function startMockWorker() {
  await setupWorker(...handlers).start({ onUnhandledRequest: UNHANDLED })
  keepPageAnnounced()
}
