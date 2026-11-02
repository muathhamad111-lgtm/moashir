import { ref } from 'vue'
import { criteriaService } from '@/services/criteriaService'
import { useCriteriaVersion } from '@/composables/useCriteriaVersions'

export function useSubCriterionDetail(mainId, subId) {
  const status = ref('loading')
  const errorMessage = ref('')
  const sub = ref(null)
  const points = ref([])
  const { version, loadVersion } = useCriteriaVersion()

  async function refresh() {
    const { data } = await criteriaService.getSub(subId.value)
    sub.value = data.sub
    points.value = data.points
  }

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const [{ data: mainData }] = await Promise.all([
        criteriaService.getMain(mainId.value),
        refresh(),
      ])
      await loadVersion(mainData.main.versionId)
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  return { status, errorMessage, sub, points, version, load, refresh }
}
