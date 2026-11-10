import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { submissionService } from '@/services/submissionService'
import { useToast } from '@/composables/useToast'
import { success } from '@/composables/useSuccess'

export function useAccreditedSubmit({
  formState,
  cvDrafts,
  onRejected,
  onSubmitted,
  onIncomplete,
}) {
  const { t } = useI18n()
  const toast = useToast()
  const submitting = ref(false)
  const confirmOpen = ref(false)

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
      confirmOpen.value = false
      onRejected(error)
      if (missingCount()) onIncomplete()
    } finally {
      submitting.value = false
    }
  }

  return { submitting, confirmOpen, requestSubmit, confirmSubmit }
}
