import { computed, ref, shallowRef } from 'vue'
import { criteriaService } from '@/services/criteriaService'
import { toCycleBindings } from '@/services/mappers/award'
import { DRAFT_CYCLE_CHOICES } from '@/constants/committee'

export function useCycleBindings() {
  const bindings = shallowRef(null)
  const loading = ref(false)
  const choice = ref(DRAFT_CYCLE_CHOICES.KEEP)

  const hasDrafts = computed(() => (bindings.value?.drafts.length ?? 0) > 0)
  const isRelevant = computed(() => Boolean(bindings.value?.running) || hasDrafts.value)

  async function load(versionId) {
    loading.value = true
    choice.value = DRAFT_CYCLE_CHOICES.KEEP
    try {
      const envelope = await criteriaService.checkVersion(versionId)
      bindings.value = toCycleBindings(envelope?.meta?.awardCycles)
    } catch {
      bindings.value = null
    } finally {
      loading.value = false
    }
  }

  function instruction() {
    return hasDrafts.value ? { draftCycles: choice.value } : {}
  }

  return { bindings, loading, choice, hasDrafts, isRelevant, load, instruction }
}
