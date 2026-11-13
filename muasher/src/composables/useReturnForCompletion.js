import { ref, shallowRef } from 'vue'
import { submissionService } from '@/services/submissionService'
import { awardCycleService } from '@/services/awardCycleService'
import { useToast } from '@/composables/useToast'
import { riyadhDateKey } from '@/utils/datetime'

const MINUTE_MS = 60 * 1000

function boundsOf(cycle, now = new Date()) {
  const lastDay = new Date(new Date(cycle.committeeStart).getTime() - MINUTE_MS)
  return { min: riyadhDateKey(now), max: riyadhDateKey(lastDay) }
}

export function useReturnForCompletion() {
  const toast = useToast()
  const preparing = ref(false)
  const criteria = shallowRef(null)
  const bounds = shallowRef(null)

  async function prepare(detail) {
    if (preparing.value) return false
    preparing.value = true
    try {
      const [form, cycle] = await Promise.all([
        submissionService.getForm(detail.id),
        awardCycleService.get(detail.awardCycle.id),
      ])
      criteria.value = form.data.criteria
      bounds.value = boundsOf(cycle.data)
      return true
    } catch (error) {
      toast.error(error?.normalized?.message ?? '')
      return false
    } finally {
      preparing.value = false
    }
  }

  return { preparing, criteria, bounds, prepare }
}
