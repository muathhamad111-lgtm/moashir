import { BUILD_POSTURE } from '@/constants/buildPosture'
import { shipsMockLayer } from '@/utils/build/buildPosture'

export async function enableMocking(posture = BUILD_POSTURE) {
  if (!shipsMockLayer(posture)) return
  const { startMockWorker } = await import('./browser')
  await startMockWorker()
}
