import { computed, onMounted, reactive, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { assessmentsService } from '@/services/assessmentsService'
import { accreditedService } from '@/services/accreditedService'
import { certificatesService } from '@/services/certificatesService'
import { dashboardService } from '@/services/dashboardService'
import { useEntityStore } from '@/stores/entity'
import { useActionCenterStore } from '@/stores/actionCenter'
import { useEntityJourney } from '@/composables/useEntityJourney'

const SELF_QUERY = { 'filter[path]': 'self', perPage: 20 }
const CERTIFICATE_QUERY = { 'filter[status]': 'issued,suspended', perPage: 5 }

function loadCertificates() {
  return certificatesService.listCards(CERTIFICATE_QUERY).then(({ data }) => data)
}

const SOURCE_LOADERS = {
  entity: (entityStore) => entityStore.loadProfile({ force: true }),
  selfAssessments: () => assessmentsService.list(SELF_QUERY).then(({ data }) => data),
  accredited: () => accreditedService.getCurrent().then(({ data }) => data),
  certificates: loadCertificates,
  board: () => dashboardService.getEntityBoard().then(({ data }) => data),
}

const REQUIRED_SOURCES = ['entity', 'selfAssessments', 'accredited']

export function useJourneyHome({ auto = true } = {}) {
  const entityStore = useEntityStore()
  const actionCenter = useActionCenterStore()
  const { items: actionItems } = storeToRefs(actionCenter)
  const sources = reactive({
    entity: null,
    selfAssessments: [],
    accredited: null,
    certificates: [],
    board: null,
  })
  const failed = reactive({})
  const status = ref('loading')

  async function loadSource(key) {
    try {
      sources[key] = await SOURCE_LOADERS[key](entityStore)
      failed[key] = false
    } catch {
      failed[key] = true
    }
  }

  async function load() {
    status.value = 'loading'
    await Promise.all([...Object.keys(SOURCE_LOADERS).map(loadSource), actionCenter.ensureLoaded()])
    status.value = REQUIRED_SOURCES.some((key) => failed[key]) ? 'error' : 'success'
  }

  const journey = computed(() => useEntityJourney({ ...sources, actionItems: actionItems.value }))

  if (auto) onMounted(load)

  return { sources, failed, status, journey, load }
}
