import { computed, onMounted, ref, toValue } from 'vue'
import { certificatesService } from '@/services/certificatesService'
import { currentLevelCertificate, tierOf } from '@/utils/levelTier'

const HISTORY_LIMIT = 20

export function useEntityLevelHistory(entityId, { auto = true } = {}) {
  const certificates = ref([])
  const status = ref('idle')

  async function load() {
    status.value = 'loading'
    try {
      const params = { 'filter[entityId]': toValue(entityId), perPage: HISTORY_LIMIT }
      const { data } = await certificatesService.listCards(params)
      certificates.value = data
      status.value = 'success'
    } catch {
      status.value = 'error'
    }
  }

  const current = computed(() => currentLevelCertificate(certificates.value))
  const tier = computed(() => tierOf({ certificate: current.value }))
  const history = computed(() =>
    certificates.value.map((certificate) => ({
      ...certificate,
      tier: tierOf({ certificate }),
      isCurrent: certificate.id === current.value?.id,
    })),
  )

  if (auto) onMounted(load)

  return { status, current, tier, history, load }
}
