import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { submissionService } from '@/services/submissionService'
import { accreditedService } from '@/services/accreditedService'
import { localizedText } from '@/utils/localizedText'
import { PERIOD_CONFLICT_CODE, SUBMISSION_REASONS } from '@/constants/entityPaths'
import { useToast } from '@/composables/useToast'
import { success } from '@/composables/useSuccess'

const ACCREDITED_PATH = 'accredited'

export function useAccreditedSubmit({
  formState,
  cvDrafts,
  onRejected,
  onSubmitted,
  onIncomplete,
}) {
  const { t, locale } = useI18n()
  const toast = useToast()
  const submitting = ref(false)
  const confirmOpen = ref(false)
  const periodLabel = ref('')
  const blockedUntil = ref('')

  function isAccredited() {
    return formState.assessment.value?.path === ACCREDITED_PATH
  }

  async function readPeriod() {
    periodLabel.value = ''
    blockedUntil.value = ''
    if (formState.isResubmission.value || !isAccredited()) return
    const current = await accreditedService
      .getCurrent()
      .then(({ data }) => data)
      .catch(() => null)
    if (current?.submission?.reason === SUBMISSION_REASONS.PERIOD_ALREADY_ASSESSED) {
      blockedUntil.value = current.submission.nextSubmissionFrom ?? ''
      return
    }
    const periodic = current?.periodic
    periodLabel.value = periodic?.isLive
      ? localizedText(periodic.currentPeriod?.label, locale.value)
      : ''
  }

  function blockOnConflict(error) {
    if (error?.normalized?.code !== PERIOD_CONFLICT_CODE) return false
    blockedUntil.value = error.normalized.meta?.nextSubmissionFrom ?? ''
    return true
  }

  function missingCount() {
    const missing = formState.form.value.missing
    return missing.missingCv.length + missing.missingAttachments.length
  }

  async function requestSubmit() {
    if (submitting.value) return
    if (!(await cvDrafts.save({ quiet: true }))) return
    const count = missingCount()
    if (count) {
      onIncomplete()
      toast.error(t('accredited.form.toasts.incomplete', { count }, count))
      return
    }
    await readPeriod()
    confirmOpen.value = true
  }

  async function confirmSubmit() {
    if (submitting.value) return
    submitting.value = true
    try {
      const { data } = await submissionService.submit(formState.assessment.value.id)
      confirmOpen.value = false
      success.show({
        title: t(
          formState.isResubmission.value
            ? 'accredited.form.toasts.resubmitted'
            : 'accredited.form.toasts.submitted',
        ),
        message: formState.assessment.value.number,
      })
      onSubmitted(data)
    } catch (error) {
      if (blockOnConflict(error)) return
      confirmOpen.value = false
      onRejected(error)
      if (missingCount()) onIncomplete()
    } finally {
      submitting.value = false
    }
  }

  return { submitting, confirmOpen, periodLabel, blockedUntil, requestSubmit, confirmSubmit }
}
