import { onMounted, reactive, ref } from 'vue'
import { assessmentsService } from '@/services/assessmentsService'
import { certificatesService } from '@/services/certificatesService'
import { useEntityStore } from '@/stores/entity'
import { useActionCenterStore } from '@/stores/actionCenter'
import { useEntityPathsStore } from '@/stores/entityPaths'

const SELF_QUERY = { 'filter[path]': 'self', perPage: 20 }
const CERTIFICATE_QUERY = { 'filter[status]': 'issued,suspended', perPage: 5 }

function loadCertificates() {
  return certificatesService.listCards(CERTIFICATE_QUERY).then(({ data }) => data)
}

const SOURCE_LOADERS = {
  entity: ({ entityStore }) => entityStore.loadProfile({ force: true }),
  selfAssessments: () => assessmentsService.list(SELF_QUERY).then(({ data }) => data),
  certificates: loadCertificates,
  board: ({ pathsStore }) => pathsStore.load().then(() => pathsStore.board),
}

const REQUIRED_SOURCES = ['entity', 'board']

export function useJourneyHome({ auto = true } = {}) {
  const stores = { entityStore: useEntityStore(), pathsStore: useEntityPathsStore() }
  const actionCenter = useActionCenterStore()
  const sources = reactive({
    entity: null,
    selfAssessments: [],
    certificates: [],
    board: null,
  })
  const failed = reactive({})
  const status = ref('loading')

  async function loadSource(key) {
    try {
      sources[key] = await SOURCE_LOADERS[key](stores)
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

  if (auto) onMounted(load)

  return { sources, failed, status, load }
}
