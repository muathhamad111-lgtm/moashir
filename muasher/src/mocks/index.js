import { MOCK_MODES } from '@/constants/mockModules'
import { mockPlan } from '@/utils/mockPlan'
import { logger } from '@/utils/logger'

export async function enableMocking(plan = mockPlan) {
  if (plan.unknown.length) logger.info('mocks.unknown_demo_modules', { unknown: plan.unknown })
  if (plan.mode === MOCK_MODES.OFF) return
  const { startMockWorker } = await import('./browser')
  await startMockWorker(plan)
}
