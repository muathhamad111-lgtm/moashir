import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAccreditedForm } from '@/composables/useAccreditedForm'
import { useAccreditedRejection } from '@/composables/useAccreditedRejection'
import { useCvDrafts } from '@/composables/useCvDrafts'
import { useEvidenceUploads } from '@/composables/useEvidenceUploads'
import { useAccreditedSubmit } from '@/composables/useAccreditedSubmit'
import { usePassedStations } from '@/composables/usePassedStations'
import { useConfirm } from '@/composables/useConfirm'

export function useAccreditedWorkspace({ assessmentId, onSubmitted }) {
  const { t } = useI18n()
  const { confirm } = useConfirm()
  const formState = useAccreditedForm(assessmentId)
  const { form } = formState
  const rejection = useAccreditedRejection(formState)
  const cvDrafts = useCvDrafts({ formState, onRejected: rejection.handle })
  const uploads = useEvidenceUploads({ formState, onRejected: rejection.handle })
  const passedStations = usePassedStations({
    assessmentId,
    formState,
    onRejected: rejection.handle,
  })
  const submitter = useAccreditedSubmit({
    formState,
    cvDrafts,
    onRejected: rejection.handle,
    onSubmitted,
    onIncomplete: () => revealFirstMissing(),
  })
  const activeMainId = ref(null)
  const refreshing = ref(false)

  const workspace = { formState, cvDrafts, uploads }
  const mains = computed(() => form.value?.criteria?.mains ?? [])
  const activeMain = computed(
    () => mains.value.find((main) => main.id === activeMainId.value) ?? mains.value[0] ?? null,
  )
  const activeIndex = computed(() => mains.value.indexOf(activeMain.value))
  const previousMain = computed(() => mains.value[activeIndex.value - 1] ?? null)
  const nextMain = computed(() => mains.value[activeIndex.value + 1] ?? null)

  watch(
    () => form.value?.cv,
    (cv) => {
      if (cv && !cvDrafts.dirty.value) cvDrafts.resetFrom(cv)
    },
  )

  // Moving forward past a station records it as passed; going back or jumping to a gap does not.
  function selectMain(mainId) {
    const leaving = activeMain.value
    activeMainId.value = mainId
    const target = mains.value.findIndex((main) => main.id === mainId)
    if (leaving && target > mains.value.indexOf(leaving)) passedStations.pass(leaving.id)
  }

  async function revealSub(subId) {
    const main = mains.value.find((item) => item.subs.some((sub) => sub.id === subId))
    if (main) activeMainId.value = main.id
    await nextTick()
    document.getElementById(`accredited-sub-${subId}`)?.focus()
  }

  async function revealMain(mainId) {
    activeMainId.value = mainId
    await nextTick()
    document.getElementById(`accredited-main-evidence-${mainId}`)?.focus()
  }

  function firstMissingSubId(missing) {
    return (missing.missingCv[0] ?? missing.missingAttachments[0])?.subCriterionId ?? null
  }

  function revealFirstMissing() {
    const missing = form.value?.missing
    if (!missing) return Promise.resolve()
    const subId = firstMissingSubId(missing)
    if (subId) return revealSub(subId)
    const main = missing.missingMainAttachments[0]
    return main ? revealMain(main.mainCriterionId) : Promise.resolve()
  }

  async function onRemove(row) {
    const accepted = await confirm({
      title: t('accredited.evidence.removeTitle'),
      message: row.fileName,
      variant: 'danger',
      confirmLabel: t('actions.remove'),
    })
    if (accepted) uploads.remove(row)
  }

  async function refresh() {
    refreshing.value = true
    rejection.clear()
    await formState.load()
    uploads.watchScans()
    refreshing.value = false
  }

  onMounted(async () => {
    await formState.load()
    if (form.value) cvDrafts.resetFrom(form.value.cv)
    uploads.watchScans()
  })

  return {
    formState,
    rejection,
    cvDrafts,
    uploads,
    submitter,
    workspace,
    mains,
    activeMain,
    activeIndex,
    previousMain,
    nextMain,
    refreshing,
    selectMain,
    revealSub,
    revealMain,
    onRemove,
    refresh,
  }
}
