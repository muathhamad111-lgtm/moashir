import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { bagService } from '@/services/bagService'

function localizedFactory(locale) {
  return function localized(content) {
    if (content === null || content === undefined) return ''
    if (typeof content === 'string') return content
    return content[locale.value] || content.ar || content.en || ''
  }
}

export function useBagEntity() {
  const { locale } = useI18n()
  const localized = localizedFactory(locale)
  const status = ref('loading')
  const errorMessage = ref('')

  const eligibility = ref(null)
  const subscription = ref(null)

  const product = ref(null)

  const history = ref([])

  const view = ref(null)

  const reference = computed(() => view.value?.reference ?? null)

  const subStatus = computed(() => subscription.value?.status ?? 'not-eligible')
  const isEligible = computed(() => eligibility.value?.eligible === true)

  const face = computed(() => {
    if (subStatus.value === 'period-ended') return 'period-ended'
    if (subStatus.value === 'activated' || subStatus.value === 'stage-active') return 'active'
    if (!isEligible.value && subStatus.value === 'not-eligible') return 'not-eligible'
    return 'eligible'
  })

  const fee = computed(() => subscription.value?.fee ?? eligibility.value?.fee ?? 0)
  const currency = computed(
    () => subscription.value?.currency ?? eligibility.value?.currency ?? 'SAR',
  )

  const pricing = computed(
    () =>
      subscription.value?.pricing ?? eligibility.value?.pricing ?? product.value?.pricing ?? null,
  )

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const [eligibilityData, subscriptionData, productData] = await Promise.all([
        bagService.getEligibility(),
        bagService.getSubscription(),
        bagService.getProduct(),
      ])
      eligibility.value = eligibilityData.data
      subscription.value = subscriptionData.data
      product.value = productData.data
      bagService
        .getHistory()
        .then(({ data }) => {
          history.value = data ?? []
        })
        .catch(() => {
          history.value = []
        })
      if (face.value === 'active') {
        const { data } = await bagService.getEntityView()
        view.value = data
      }
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  return {
    status,
    errorMessage,
    eligibility,
    subscription,
    view,
    reference,
    product,
    pricing,
    history,
    subStatus,
    isEligible,
    face,
    fee,
    currency,
    localized,
    load,
  }
}

export function useBagConsultant() {
  const { locale } = useI18n()
  const localized = localizedFactory(locale)

  const status = ref('loading')
  const errorMessage = ref('')

  const subscription = ref(null)
  const source = ref(null)
  const stages = ref([])
  const gaps = ref([])
  const tracks = ref(null)

  const selection = reactive(new Set())
  const opening = ref(false)
  const attaching = reactive({})
  const savingGuidance = reactive({})

  const activeStageId = computed(() => subscription.value?.activeStageId ?? null)
  const activeStage = computed(() => stages.value.find((s) => s.id === activeStageId.value) ?? null)

  const closedGaps = computed(() => gaps.value.filter((g) => !g.open))
  const openGaps = computed(() => gaps.value.filter((g) => g.open))
  const selectedCount = computed(() => selection.size)

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const [viewEnvelope, stageEnvelope] = await Promise.all([
        bagService.getConsultantView(),
        bagService.listStages(),
      ])
      const viewData = viewEnvelope.data
      subscription.value = viewData?.subscription ?? null
      source.value = viewData?.source ?? null
      tracks.value = viewData?.tracks ?? null
      gaps.value = viewData?.gaps ?? []
      stages.value = stageEnvelope.data ?? viewData?.stages ?? []
      selection.clear()
      status.value = gaps.value.length ? 'success' : 'empty'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  function toggle(gapId) {
    if (selection.has(gapId)) selection.delete(gapId)
    else selection.add(gapId)
  }

  function isSelected(gapId) {
    return selection.has(gapId)
  }

  async function openSelected() {
    if (!selection.size || opening.value) return null
    opening.value = true
    try {
      const gapIds = Array.from(selection)
      const { data: result } = await bagService.openStageGaps(gapIds)
      selection.clear()
      await load()
      return result
    } finally {
      opening.value = false
    }
  }

  async function saveGuidance(gap, guidance = {}) {
    savingGuidance[gap.id] = true
    try {
      const { data: updated } = await bagService.updateGapGuidance({
        gapId: gap.id,
        priority: guidance.priority,
        status: guidance.status,
        consultantNote: guidance.consultantNote,
        recommendedAction: guidance.recommendedAction,
      })
      const target = gaps.value.find((g) => g.id === gap.id)
      if (target) {
        target.priority = updated.priority
        target.status = updated.status
        target.consultantNote = updated.consultantNote
        target.recommendedAction = updated.recommendedAction
      }
      return updated
    } finally {
      savingGuidance[gap.id] = false
    }
  }

  async function attachFile(gap, file, meta = {}) {
    attaching[gap.id] = true
    try {
      const { data: created } = await bagService.attachHelperFile({
        gapId: gap.id,
        subCriterionId: gap.subCriterionId,
        guidingPointId: meta.guidingPointId ?? null,
        kind: meta.kind ?? 'guide',
        name: { ar: file.name, en: file.name },
        fileName: file.name,
        sizeKb: Math.round((file.size ?? 0) / 1024),
      })
      const target = gaps.value.find((g) => g.id === gap.id)
      if (target) target.helperFiles = [...(target.helperFiles ?? []), created]
      return created
    } finally {
      attaching[gap.id] = false
    }
  }

  return {
    status,
    errorMessage,
    subscription,
    source,
    tracks,
    stages,
    gaps,
    closedGaps,
    openGaps,
    activeStageId,
    activeStage,
    selection,
    selectedCount,
    opening,
    attaching,
    savingGuidance,
    localized,
    load,
    toggle,
    isSelected,
    openSelected,
    attachFile,
    saveGuidance,
  }
}

export function useBagReassessment() {
  const { locale } = useI18n()
  const localized = localizedFactory(locale)

  const status = ref('loading')
  const errorMessage = ref('')
  const data = ref(null)

  const overall = computed(() => data.value?.overall ?? null)
  const reference = computed(() => data.value?.reference ?? null)
  const reassessment = computed(() => data.value?.reassessment ?? null)
  const criteria = computed(() => data.value?.criteria ?? [])
  const kitCriteria = computed(() => criteria.value.filter((c) => c.inKitScope))
  const otherCriteria = computed(() => criteria.value.filter((c) => !c.inKitScope))

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const envelope = await bagService.getReassessment()
      data.value = envelope.data
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  return {
    status,
    errorMessage,
    data,
    overall,
    reference,
    reassessment,
    criteria,
    kitCriteria,
    otherCriteria,
    localized,
    load,
  }
}
