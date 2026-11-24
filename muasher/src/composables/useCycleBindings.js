import { computed, ref, shallowRef } from 'vue'
import { criteriaService } from '@/services/criteriaService'
import { toCycleBindings } from '@/services/mappers/award'

export function useCycleBindings() {
  const bindings = shallowRef(null)
  const loading = ref(false)

  const hasDrafts = computed(() => (bindings.value?.drafts.length ?? 0) > 0)
  const isRelevant = computed(() => Boolean(bindings.value?.running) || hasDrafts.value)

  async function load(versionId) {
    loading.value = true
    try {
      const envelope = await criteriaService.checkVersion(versionId)
      bindings.value = toCycleBindings(envelope?.meta?.awardCycles)
    } catch {
      bindings.value = null
    } finally {
      loading.value = false
    }
  }

  return { bindings, loading, hasDrafts, isRelevant, load }
}
