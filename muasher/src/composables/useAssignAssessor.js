import { onBeforeUnmount, ref, watch } from 'vue'
import { requestsService } from '@/services/requestsService'
import { SEARCH_DEBOUNCE_MS } from '@/constants/globalSearch'

export const ASSIGNMENT_CODES = Object.freeze({ NOT_QUALIFIED: 'assessor.not_qualified' })

const NOT_FOUND = 404

// Today's rules until the API sends the general settings: the assign reason optional, the reassign one required.
const DEFAULT_REASON_RULES = Object.freeze({ assignRequired: false, reassignRequired: true })

function reasonRulesOf(meta) {
  const rules = meta?.reasonRules
  if (!rules) return DEFAULT_REASON_RULES
  return {
    assignRequired: rules.assignRequired === true,
    reassignRequired: rules.reassignRequired !== false,
  }
}

// For an award request the API says whether its cycle links any initial assessor, and gives the cycle to its managers.
function awardCycleOf(meta) {
  const cycle = meta?.awardCycle
  if (!cycle) return null
  return { hasInitialAssessors: cycle.hasInitialAssessors === true, cycleId: cycle.cycleId ?? null }
}

function emptyStatusOf(searched, awardCycle) {
  if (searched) return 'noMatch'
  return awardCycle && !awardCycle.hasInitialAssessors ? 'cycleUnlinked' : 'empty'
}

function isOutOfScope(error) {
  const normalized = error?.normalized ?? {}
  return normalized.status === NOT_FOUND || Boolean(normalized.fieldErrors?.assessmentId)
}

export function useAssignAssessor({ requestId }) {
  const assessors = ref([])
  const status = ref('idle')
  const search = ref('')
  const reasonRules = ref(DEFAULT_REASON_RULES)
  const awardCycle = ref(null)
  let timer = null

  async function loadAssessors() {
    status.value = 'loading'
    try {
      const params = {
        assessmentId: requestId.value,
        ...(search.value.trim() ? { search: search.value.trim() } : {}),
      }
      const { data, meta } = await requestsService.listAssessors(params)
      assessors.value = data
      reasonRules.value = reasonRulesOf(meta)
      awardCycle.value = awardCycleOf(meta)
      status.value = data.length ? 'ready' : emptyStatusOf(Boolean(params.search), awardCycle.value)
    } catch (error) {
      assessors.value = []
      status.value = isOutOfScope(error) ? 'outOfScope' : 'error'
    }
  }

  watch(search, () => {
    clearTimeout(timer)
    timer = setTimeout(loadAssessors, SEARCH_DEBOUNCE_MS)
  })

  function submit(mode, payload) {
    return mode === 'reassign'
      ? requestsService.reassign(requestId.value, payload)
      : requestsService.assign(requestId.value, payload)
  }

  onBeforeUnmount(() => clearTimeout(timer))

  return { assessors, status, search, reasonRules, awardCycle, loadAssessors, submit }
}

export function assignmentFailureOf(error) {
  const normalized = error?.normalized ?? {}
  return {
    notQualified: normalized.code === ASSIGNMENT_CODES.NOT_QUALIFIED,
    outOfScope: normalized.status === NOT_FOUND,
    requiredPath: normalized.meta?.path ?? null,
    fields: normalized.fieldErrors ?? {},
    message: normalized.message ?? '',
  }
}
