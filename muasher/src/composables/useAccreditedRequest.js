import { computed, ref, shallowRef, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { pathsService } from '@/services/pathsService'
import { useAccreditedResult } from '@/composables/useAccreditedResult'
import { REQUEST_TABS, REQUEST_TAB_ORDER } from '@/constants/entityPaths'

const NOT_FOUND = 404
const RESULT_TABS = [REQUEST_TABS.RESULT, REQUEST_TABS.FEEDBACK]

export function useAccreditedRequest() {
  const route = useRoute()
  const router = useRouter()
  const assessmentId = computed(() => Number(route.params.id))
  const record = shallowRef(null)
  const status = ref('loading')
  const resultState = useAccreditedResult(assessmentId)

  const tabs = computed(() => {
    const available = record.value?.availableTabs ?? [REQUEST_TABS.REQUEST]
    return REQUEST_TAB_ORDER.filter((tab) => available.includes(tab))
  })
  const activeTab = computed(() => {
    const requested = route.query.tab
    if (tabs.value.includes(requested)) return requested
    return record.value?.defaultTab ?? REQUEST_TABS.REQUEST
  })

  async function load() {
    status.value = 'loading'
    try {
      const { data } = await pathsService.getRequest(assessmentId.value)
      record.value = data
      status.value = 'success'
    } catch (error) {
      status.value = error?.normalized?.status === NOT_FOUND ? 'missing' : 'error'
    }
  }

  function selectTab(tab) {
    router.replace({ query: { ...route.query, tab } })
  }

  watch(
    () => [status.value, activeTab.value],
    ([state, tab]) => {
      const needsResult = state === 'success' && RESULT_TABS.includes(tab)
      if (needsResult && !resultState.result.value) resultState.load()
    },
  )

  watch(assessmentId, load, { immediate: true })

  return { assessmentId, record, status, tabs, activeTab, selectTab, load, resultState }
}
