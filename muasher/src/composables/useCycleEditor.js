import { computed, ref, shallowRef } from 'vue'
import { awardCycleService } from '@/services/awardCycleService'
import { assessmentsService } from '@/services/assessmentsService'
import { regionsService } from '@/services/regionsService'
import { toCamelKey } from '@/api/caseMapping'
import { cycleFormValues, lockedCycleFields } from '@/utils/award/cycleForm'

const NOT_FOUND = 404
const FORBIDDEN = 403

function failureState(error) {
  const status = error?.normalized?.status
  if (status === NOT_FOUND) return 'missing'
  if (status === FORBIDDEN) return 'blocked'
  return 'error'
}

async function optional(request) {
  try {
    const { data } = await request
    return data
  } catch {
    return null
  }
}

export function useCycleEditor(cycleId) {
  const status = ref('loading')
  const errorMessage = ref('')
  const cycle = shallowRef(null)
  const defaults = shallowRef(null)
  const published = shallowRef(null)
  const regions = ref([])
  const running = shallowRef(null)
  const serverLocked = ref([])

  const isNew = computed(() => !cycleId.value)
  const initialValues = computed(() => cycleFormValues(cycle.value, { defaults: defaults.value, published: published.value }))
  const locked = computed(() => new Set([...lockedCycleFields(cycle.value?.status), ...serverLocked.value]))
  const blockingCycle = computed(() => (running.value && running.value.id !== cycle.value?.id ? running.value : null))

  async function loadCycle() {
    if (isNew.value) {
      defaults.value = (await awardCycleService.getDefaultConditions()).data
      return
    }
    cycle.value = (await awardCycleService.get(cycleId.value)).data
  }

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    serverLocked.value = []
    try {
      const [, regionList, tree, current] = await Promise.all([
        loadCycle(),
        optional(regionsService.listRegions()),
        optional(assessmentsService.getPublishedCriteria()),
        optional(awardCycleService.getCurrent()),
      ])
      regions.value = regionList ?? []
      published.value = tree ? { versionId: tree.versionId, versionNumber: tree.versionNumber, scaleKind: tree.scaleKind } : null
      running.value = current
      status.value = 'ready'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = failureState(error)
    }
  }

  function apply(detail) {
    cycle.value = detail
  }

  function lockFields(fields) {
    serverLocked.value = [...new Set([...serverLocked.value, ...(fields ?? []).map(toCamelKey)])]
  }

  return { status, errorMessage, cycle, defaults, published, regions, running, blockingCycle, isNew, initialValues, locked, load, apply, lockFields }
}
