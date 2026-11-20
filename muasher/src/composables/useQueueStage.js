import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { QUEUE_STAGE_LIST, QUEUE_STAGES } from '@/constants/accredited'

// The request list's stage filter, kept in the address so a link or a reload shows the same stage.
export function useQueueStage() {
  const route = useRoute()
  const router = useRouter()

  const stage = computed({
    get: () =>
      QUEUE_STAGE_LIST.includes(route.query.stage) ? route.query.stage : QUEUE_STAGES.ALL,
    set: (next) => {
      const query = { ...route.query }
      if (QUEUE_STAGE_LIST.includes(next)) query.stage = next
      else delete query.stage
      router.replace({ query })
    },
  })
  return { stage }
}
