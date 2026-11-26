import { ref, watch } from 'vue'
import { filteringService } from '@/services/filteringService'

// «تأهيل كل من بلغ حد التأهل» (operator decision D-4): the preview is read only by those who may decide; any
// refusal just keeps the action hidden. The server decides who may act and which requests are eligible.
export function useBulkQualification(cycleId) {
  const preview = ref(null)
  const busy = ref(false)

  async function load() {
    try {
      const { data } = await filteringService.eligible(cycleId.value)
      preview.value = data
    } catch {
      preview.value = null
    }
  }

  async function run(extra = {}) {
    busy.value = true
    try {
      const { data } = await filteringService.qualifyEligible(cycleId.value, extra)
      await load()
      return data
    } finally {
      busy.value = false
    }
  }

  watch(cycleId, load, { immediate: true })

  return { preview, busy, load, run }
}
