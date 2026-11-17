import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useEntityLevelStore } from '@/stores/entityLevel'
import { miniCardOf, showsReassessment, tierOf } from '@/utils/levelTier'

export function useEntityLevel({ auto = true } = {}) {
  const store = useEntityLevelStore()
  const { certificate, accredited, status } = storeToRefs(store)
  const sources = computed(() => ({
    certificate: certificate.value,
    accredited: accredited.value,
  }))
  const tier = computed(() => tierOf(sources.value))
  const reassessment = computed(() => showsReassessment(sources.value))
  const miniCard = computed(() => miniCardOf(tier.value))
  // Until the level is loaded the tier reads «none»; callers hide the badge instead of showing it.
  const ready = computed(() => status.value === 'success')

  if (auto) onMounted(() => store.ensureLoaded())

  return {
    certificate,
    accredited,
    status,
    ready,
    tier,
    reassessment,
    miniCard,
    load: () => store.load(),
  }
}
