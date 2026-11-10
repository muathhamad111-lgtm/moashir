import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { QUEUE_STAGES } from '@/constants/accredited'

export function useQueueStage() {
  const route = useRoute()
  const router = useRouter()

  const stage = computed({
    get: () =>
      route.query.stage === QUEUE_STAGES.PREPARING ? QUEUE_STAGES.PREPARING : QUEUE_STAGES.QUEUE,
    set: (next) => {
      const query = { ...route.query }
      if (next === QUEUE_STAGES.PREPARING) query.stage = next
      else delete query.stage
      router.replace({ query })
    },
  })
  const stageQuery = computed(() =>
    stage.value === QUEUE_STAGES.PREPARING ? { stage: QUEUE_STAGES.PREPARING } : {},
  )

  return { stage, stageQuery }
}
