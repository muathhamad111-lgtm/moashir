import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { committeeService } from '@/services/committeeService'

function localizedFactory(locale) {
  return function localized(content) {
    if (content === null || content === undefined) return ''
    if (typeof content === 'string') return content
    return content[locale.value] || content.ar || content.en || ''
  }
}

export function useCommitteeQueue(cycleId) {
  const { locale } = useI18n()
  const localized = localizedFactory(locale)

  const status = ref('loading')
  const errorMessage = ref('')
  const committeeAssessors = ref([])
  const cycleName = ref(null)
  const disabling = ref(null)

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data, meta } = await committeeService.listCommitteeAssessors(cycleId)
      committeeAssessors.value = data ?? []
      cycleName.value = meta?.cycleName ?? null
      status.value = committeeAssessors.value.length ? 'success' : 'empty'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  async function disable(committeeAssessorId, options) {
    disabling.value = committeeAssessorId
    try {
      const { data } = await committeeService.disableCommitteeAssessor(cycleId, committeeAssessorId, options)
      await load()
      return data
    } finally {
      disabling.value = null
    }
  }

  const allCompleted = computed(() => {
    const active = committeeAssessors.value.filter((j) => j.status !== 'excluded' && j.status !== 'disabled')
    return active.length > 0 && active.every((j) => j.status === 'completed')
  })

  return { status, errorMessage, committeeAssessors, cycleName, disabling, allCompleted, localized, load, disable }
}

export function useCommitteeScore(cycleId, committeeAssessorId, assessmentId) {
  const { locale } = useI18n()
  const localized = localizedFactory(locale)

  const status = ref('loading')
  const errorMessage = ref('')
  const tree = ref([])
  const scaleKind = ref('of-10')
  const committeeAssessorStatus = ref('not-started')
  const entityName = ref(null)
  const scores = reactive({ subs: {}, points: {} })
  const notes = reactive({})
  const prior = ref(null)
  const saving = ref(false)
  const completing = ref(false)

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data } = await committeeService.getCommitteeAssessorScore(cycleId, committeeAssessorId, assessmentId)
      tree.value = data?.tree ?? []
      scaleKind.value = data?.scaleKind ?? 'of-10'
      committeeAssessorStatus.value = data?.committeeAssessorStatus ?? 'not-started'
      entityName.value = data?.entityName ?? null
      prior.value = data?.prior ?? null
      const record = data?.record ?? null
      Object.keys(scores.subs).forEach((k) => delete scores.subs[k])
      Object.keys(scores.points).forEach((k) => delete scores.points[k])
      Object.assign(scores.subs, record?.scores?.subs ?? {})
      Object.assign(scores.points, record?.scores?.points ?? {})
      Object.keys(notes).forEach((k) => delete notes[k])
      for (const note of record?.notes ?? []) notes[note.subCriterionId] = note.text
      status.value = tree.value.length ? 'success' : 'empty'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  const subs = computed(() => tree.value.flatMap((main) => main.subs))

  async function score(unit, ratio) {
    saving.value = true
    try {
      if (unit.pointId != null) {
        scores.points[unit.pointId] = ratio
        await committeeService.scorePoint(cycleId, committeeAssessorId, assessmentId, { pointId: unit.pointId, ratio })
      } else {
        scores.subs[unit.subCriterionId] = ratio
        await committeeService.scoreSub(cycleId, committeeAssessorId, assessmentId, {
          subCriterionId: unit.subCriterionId,
          ratio,
        })
      }
    } finally {
      saving.value = false
    }
  }

  async function saveNotes(subCriterionId, text) {
    saving.value = true
    try {
      notes[subCriterionId] = text
      await committeeService.saveNotes(cycleId, committeeAssessorId, assessmentId, { subCriterionId, text })
    } finally {
      saving.value = false
    }
  }

  async function complete() {
    if (completing.value) return null
    completing.value = true
    try {
      const { data } = await committeeService.complete(cycleId, committeeAssessorId, assessmentId)
      committeeAssessorStatus.value = data?.status ?? 'completed'
      return data
    } finally {
      completing.value = false
    }
  }

  return {
    status,
    errorMessage,
    tree,
    subs,
    scaleKind,
    committeeAssessorStatus,
    entityName,
    scores,
    notes,
    prior,
    saving,
    completing,
    localized,
    load,
    score,
    saveNotes,
    complete,
  }
}

export function useSecretary(cycleId) {
  const { locale } = useI18n()
  const localized = localizedFactory(locale)

  const status = ref('loading')
  const errorMessage = ref('')
  const pending = ref([])
  const cycleName = ref(null)
  const deciding = ref(null)

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data, meta } = await committeeService.listPending(cycleId)
      pending.value = data ?? []
      cycleName.value = meta?.cycleName ?? null
      status.value = pending.value.length ? 'success' : 'empty'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  function removePending(assessmentId) {
    const index = pending.value.findIndex((p) => p.assessmentId === assessmentId)
    if (index !== -1) pending.value.splice(index, 1)
  }

  async function approve(assessmentId) {
    deciding.value = assessmentId
    try {
      const { data } = await committeeService.secretaryApprove(cycleId, assessmentId)
      removePending(assessmentId)
      return data
    } finally {
      deciding.value = null
    }
  }

  async function returnToCommittee(assessmentId, generalNote) {
    deciding.value = assessmentId
    try {
      const { data } = await committeeService.secretaryReturn(cycleId, assessmentId, { generalNote })
      removePending(assessmentId)
      return data
    } finally {
      deciding.value = null
    }
  }

  return { status, errorMessage, pending, cycleName, deciding, localized, load, approve, returnToCommittee }
}

export function useCommitteeManagement(cycleId) {
  const { locale } = useI18n()
  const localized = localizedFactory(locale)

  const status = ref('loading')
  const errorMessage = ref('')
  const committeeAssessors = ref([])
  const available = ref([])
  const cycleName = ref(null)
  const qualifiedCount = ref(0)
  const busy = ref(false)
  const acting = ref(null)

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data, meta } = await committeeService.getRoster(cycleId)
      committeeAssessors.value = data?.committeeAssessors ?? []
      available.value = data?.available ?? []
      cycleName.value = meta?.cycleName ?? null
      qualifiedCount.value = meta?.qualifiedCount ?? 0
      status.value = committeeAssessors.value.length ? 'success' : 'empty'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  const activeCommitteeAssessors = computed(() =>
    committeeAssessors.value.filter((j) => j.status !== 'excluded' && j.status !== 'disabled'),
  )
  const canRedistribute = computed(() => activeCommitteeAssessors.value.length > 0)

  async function addCommitteeAssessor(committeeAssessorId) {
    busy.value = true
    acting.value = committeeAssessorId
    try {
      await committeeService.addCommitteeAssessor(cycleId, { committeeAssessorId })
      await load()
    } finally {
      busy.value = false
      acting.value = null
    }
  }

  async function removeCommitteeAssessor(committeeAssessorId) {
    busy.value = true
    acting.value = committeeAssessorId
    try {
      const { data } = await committeeService.removeCommitteeAssessor(cycleId, committeeAssessorId)
      await load()
      return data
    } finally {
      busy.value = false
      acting.value = null
    }
  }

  async function reassign(fromCommitteeAssessorId, toCommitteeAssessorId, assessmentId) {
    busy.value = true
    try {
      const { data } = await committeeService.reassignAssessment(cycleId, {
        fromCommitteeAssessorId,
        toCommitteeAssessorId,
        assessmentId,
      })
      await load()
      return data
    } finally {
      busy.value = false
    }
  }

  async function redistribute() {
    busy.value = true
    try {
      const { data } = await committeeService.redistribute(cycleId)
      await load()
      return data
    } finally {
      busy.value = false
    }
  }

  return {
    status,
    errorMessage,
    committeeAssessors,
    available,
    cycleName,
    qualifiedCount,
    activeCommitteeAssessors,
    canRedistribute,
    busy,
    acting,
    localized,
    load,
    addCommitteeAssessor,
    removeCommitteeAssessor,
    reassign,
    redistribute,
  }
}

export function useSecretaryReview(cycleId) {
  const { locale } = useI18n()
  const localized = localizedFactory(locale)

  const status = ref('loading')
  const errorMessage = ref('')
  const entities = ref([])
  const cycleName = ref(null)

  const selected = ref(null)
  const committeeAssessorsStatus = ref('idle')
  const committeeAssessorsError = ref('')
  const committeeAssessors = ref([])
  const entityMeta = ref(null)

  const detailStatus = ref('idle')
  const detailError = ref('')
  const detail = ref(null)
  const activeCommitteeAssessorId = ref(null)

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data, meta } = await committeeService.listReviewEntities(cycleId)
      entities.value = data ?? []
      cycleName.value = meta?.cycleName ?? null
      status.value = entities.value.length ? 'success' : 'empty'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  async function selectEntity(row) {
    selected.value = row
    detail.value = null
    detailStatus.value = 'idle'
    committeeAssessorsStatus.value = 'loading'
    committeeAssessorsError.value = ''
    try {
      const { data, meta } = await committeeService.listEntityCommitteeAssessors(cycleId, row.assessmentId)
      committeeAssessors.value = data ?? []
      entityMeta.value = meta ?? null
      committeeAssessorsStatus.value = committeeAssessors.value.length ? 'success' : 'empty'
    } catch (error) {
      committeeAssessorsError.value = error?.normalized?.message ?? ''
      committeeAssessorsStatus.value = 'error'
    }
  }

  function clearEntity() {
    selected.value = null
    committeeAssessors.value = []
    committeeAssessorsStatus.value = 'idle'
    detail.value = null
    detailStatus.value = 'idle'
  }

  async function openCommitteeAssessor(committeeAssessorId) {
    if (!selected.value || !committeeAssessorId) return
    activeCommitteeAssessorId.value = committeeAssessorId
    detailStatus.value = 'loading'
    detailError.value = ''
    try {
      const { data } = await committeeService.getEntityCommitteeAssessorDetail(
        cycleId,
        selected.value.assessmentId,
        committeeAssessorId,
      )
      detail.value = data ? { ...data, committeeAssessorId } : null
      detailStatus.value = 'success'
    } catch (error) {
      detailError.value = error?.normalized?.message ?? ''
      detailStatus.value = 'error'
    }
  }

  function retryDetail() {
    if (activeCommitteeAssessorId.value) openCommitteeAssessor(activeCommitteeAssessorId.value)
  }

  function closeCommitteeAssessor() {
    detail.value = null
    detailStatus.value = 'idle'
    activeCommitteeAssessorId.value = null
  }

  return {
    status,
    errorMessage,
    entities,
    cycleName,
    selected,
    committeeAssessorsStatus,
    committeeAssessorsError,
    committeeAssessors,
    entityMeta,
    detailStatus,
    detailError,
    detail,
    localized,
    load,
    selectEntity,
    clearEntity,
    openCommitteeAssessor,
    retryDetail,
    closeCommitteeAssessor,
  }
}
