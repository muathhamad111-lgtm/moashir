import { computed, ref, unref, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { assessorReviewService } from '@/services/assessorReviewService'
import { ASSESSOR_SUMMARY_MAX } from '@/constants/insights'

const ERROR_KEYS = {
  403: 'review.summary.errors.notAssignee',
  409: 'review.summary.errors.notUnderReview',
  422: 'review.summary.errors.invalid',
}
const NOT_APPLICABLE = 'assessment.summary_not_applicable'

export function useAssessorSummary({ assessmentId, summary, askReason, requiresReason, onSaved }) {
  const { t } = useI18n()
  const toast = useToast()
  const text = ref('')
  const error = ref('')
  const saving = ref(false)

  const current = computed(() => unref(summary))
  const length = computed(() => text.value.length)
  const tooLong = computed(() => text.value.trim().length > ASSESSOR_SUMMARY_MAX)
  const dirty = computed(() => text.value.trim() !== (current.value?.body ?? ''))

  watch(current, (value) => (text.value = value?.body ?? ''), { immediate: true })

  function messageOf(failure) {
    const normalized = failure?.normalized ?? {}
    if (normalized.code === NOT_APPLICABLE) return t('review.summary.errors.notApplicable')
    const fieldMessage = normalized.fieldErrors?.summary
    if (fieldMessage) return Array.isArray(fieldMessage) ? fieldMessage[0] : fieldMessage
    return t(ERROR_KEYS[normalized.status] ?? 'review.summary.errors.failed')
  }

  async function save() {
    if (tooLong.value || saving.value) return
    const reason = await askReason()
    if (unref(requiresReason) && !reason) return
    saving.value = true
    error.value = ''
    try {
      const body = text.value.trim() || null
      const payload = reason ? { summary: body, reason } : { summary: body }
      const { data } = await assessorReviewService.saveSummary(unref(assessmentId), payload)
      onSaved(data)
      toast.success(t(data ? 'review.summary.saved' : 'review.summary.cleared'))
    } catch (failure) {
      error.value = messageOf(failure)
    } finally {
      saving.value = false
    }
  }

  return { text, error, saving, length, tooLong, dirty, save }
}
