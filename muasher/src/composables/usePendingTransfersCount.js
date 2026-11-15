import { onMounted, ref } from 'vue'
import { bankTransfersService } from '@/services/bankTransfersService'

// How many bank transfers wait for review, for the button that opens the review queue.
export function usePendingTransfersCount({ enabled }) {
  const count = ref(null)

  async function load() {
    if (!enabled) return
    try {
      const envelope = await bankTransfersService.list({ per_page: 1 })
      count.value = envelope.meta?.counts?.underReview ?? null
    } catch {
      count.value = null
    }
  }

  onMounted(load)
  return { count }
}
