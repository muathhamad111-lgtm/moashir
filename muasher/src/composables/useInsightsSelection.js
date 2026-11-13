import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { RESULT_QUERY_KEY } from '@/constants/insights'

const NUMERIC_ID = /^\d+$/

function selectedFrom(requested, overview) {
  return requested ?? overview.value?.selected?.assessmentId ?? null
}

export function useQuerySelection(overview) {
  const route = useRoute()
  const router = useRouter()
  const requested = computed(() => {
    const value = String(route.query[RESULT_QUERY_KEY] ?? '')
    return NUMERIC_ID.test(value) ? Number(value) : null
  })
  const selectedId = computed(() => selectedFrom(requested.value, overview))

  function select(assessmentId) {
    return router.replace({
      query: { ...route.query, [RESULT_QUERY_KEY]: String(assessmentId) },
    })
  }

  return { selectedId, select }
}

export function useLocalSelection(overview) {
  const requested = ref(null)
  const selectedId = computed(() => selectedFrom(requested.value, overview))

  function select(assessmentId) {
    requested.value = Number(assessmentId)
  }

  return { selectedId, select }
}
