import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAccreditedForm } from '@/composables/useAccreditedForm'
import { useAccreditedRejection } from '@/composables/useAccreditedRejection'
import { useCvDrafts } from '@/composables/useCvDrafts'
import { useEvidenceUploads } from '@/composables/useEvidenceUploads'
import { useAccreditedSubmit } from '@/composables/useAccreditedSubmit'
import { useConfirm } from '@/composables/useConfirm'
import { acceptOf } from '@/utils/accredited/evidenceRules'

export function useAccreditedWorkspace({ assessmentId, onSubmitted, completeness }) {
  const { t } = useI18n()
  const { confirm } = useConfirm()
  const formState = useAccreditedForm(assessmentId)
  const { form } = formState
  const rejection = useAccreditedRejection(formState)
  const cvDrafts = useCvDrafts({ formState, onRejected: rejection.handle })
  const uploads = useEvidenceUploads({ formState, onRejected: rejection.handle })
  const submitter = useAccreditedSubmit({
    formState,
    cvDrafts,
    onRejected: rejection.handle,
    onSubmitted,
    onIncomplete: () => nextTick(() => completeness.value?.focus()),
  })
  const activeMainId = ref(null)
  const refreshing = ref(false)

  const accept = computed(() => acceptOf(form.value?.uploadLimits.allowedTypes ?? []))
  const workspace = { formState, cvDrafts, uploads, accept }
  const mains = computed(() => form.value?.criteria?.mains ?? [])
  const activeMain = computed(
    () => mains.value.find((main) => main.id === activeMainId.value) ?? mains.value[0] ?? null,
  )
  const activeIndex = computed(() => mains.value.indexOf(activeMain.value))

  watch(
    () => form.value?.cv,
    (cv) => {
      if (cv && !cvDrafts.dirty.value) cvDrafts.resetFrom(cv)
    },
  )

  function selectMain(mainId) {
    activeMainId.value = mainId
  }

  async function revealSub(subId) {
    const main = mains.value.find((item) => item.subs.some((sub) => sub.id === subId))
    if (main) activeMainId.value = main.id
    await nextTick()
    document.getElementById(`accredited-sub-${subId}`)?.focus()
  }

  async function onRemove(row) {
    const accepted = await confirm({
      title: t('accredited.evidence.removeTitle'),
      message: t('accredited.evidence.removeMessage', { name: row.fileName }),
      variant: 'danger',
      confirmLabel: t('accredited.evidence.removeConfirm'),
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
    refreshing,
    selectMain,
    revealSub,
    onRemove,
    refresh,
  }
}
