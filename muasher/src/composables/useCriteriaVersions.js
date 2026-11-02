import { computed, ref } from 'vue'
import { criteriaService } from '@/services/criteriaService'
import { CRITERIA_VERSION_STATUSES } from '@/constants/criteria'

const VERSION_PAGE_SIZE = 100

export function useCriteriaVersions() {
  const versions = ref([])
  const status = ref('loading')

  const draft = computed(
    () =>
      versions.value.find((version) => version.status === CRITERIA_VERSION_STATUSES.DRAFT) ?? null,
  )
  const published = computed(
    () =>
      versions.value.find((version) => version.status === CRITERIA_VERSION_STATUSES.PUBLISHED) ??
      null,
  )

  function findVersion(id) {
    return versions.value.find((version) => version.id === Number(id)) ?? null
  }

  async function load() {
    status.value = 'loading'
    try {
      const envelope = await criteriaService.listVersions({ perPage: VERSION_PAGE_SIZE })
      versions.value = envelope.data ?? []
      status.value = versions.value.length ? 'success' : 'empty'
    } catch {
      status.value = 'error'
    }
  }

  return { versions, status, draft, published, findVersion, load }
}

export function useCriteriaVersion() {
  const version = ref(null)

  async function loadVersion(versionId) {
    const envelope = await criteriaService.getVersion(versionId)
    version.value = envelope.data
    return version.value
  }

  return { version, loadVersion }
}
