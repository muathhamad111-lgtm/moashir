import { computed, ref } from 'vue'
import { entitiesService } from '@/services/entitiesService'
import { entityLifecycleService } from '@/services/entityLifecycleService'

export function useEntityRecord(entityId) {
  const entity = ref(null)
  const status = ref('loading')
  const errorMessage = ref('')
  const frozenRequests = ref([])
  const frozenStatus = ref('idle')

  async function loadFrozenRequests() {
    frozenStatus.value = 'loading'
    try {
      const { data } = await entityLifecycleService.deletionImpact(entity.value.id)
      frozenRequests.value = data.openRequests ?? []
      frozenStatus.value = 'success'
    } catch {
      frozenStatus.value = 'error'
    }
  }

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data } = await entitiesService.get(entityId.value)
      entity.value = data
      status.value = 'success'
      if (data.isDeleted) await loadFrozenRequests()
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = error?.normalized?.status === 404 ? 'missing' : 'error'
    }
  }

  async function refreshLogo() {
    const { data } = await entitiesService.get(entityId.value)
    if (entity.value) entity.value = { ...entity.value, logo: data.logo ?? null }
  }

  const isFullRecord = computed(() => Boolean(entity.value && 'entityType' in entity.value))

  return {
    entity,
    status,
    errorMessage,
    frozenRequests,
    frozenStatus,
    isFullRecord,
    load,
    loadFrozenRequests,
    refreshLogo,
  }
}
