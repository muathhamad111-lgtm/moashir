import { onMounted, shallowRef } from 'vue'
import { awardCycleService } from '@/services/awardCycleService'

export function useAwardCycle(cycleId) {
  const cycle = shallowRef(null)

  async function loadCycle() {
    try {
      cycle.value = (await awardCycleService.get(cycleId.value)).data
    } catch {
      cycle.value = null
    }
  }

  onMounted(loadCycle)

  return { cycle, loadCycle }
}
