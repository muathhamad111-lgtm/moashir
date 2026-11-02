import { ref } from 'vue'
import { criteriaService } from '@/services/criteriaService'
import { useCriteriaVersion } from '@/composables/useCriteriaVersions'

export function useMainCriterionDetail(mainId) {
  const status = ref('loading')
  const errorMessage = ref('')
  const main = ref(null)
  const subs = ref([])
  const { version, loadVersion } = useCriteriaVersion()

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data } = await criteriaService.getMain(mainId.value)
      await loadVersion(data.main.versionId)
      main.value = data.main
      subs.value = data.subs
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  async function refresh() {
    const { data } = await criteriaService.getMain(mainId.value)
    main.value = data.main
    subs.value = data.subs
  }

  return { status, errorMessage, main, subs, version, load, refresh }
}
