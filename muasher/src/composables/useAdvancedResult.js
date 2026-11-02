import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { assessmentsService } from '@/services/assessmentsService'
import { localizedText } from '@/utils/localizedText'

export function useAdvancedResult(assessmentId) {
  const { locale } = useI18n()
  const status = ref('loading')
  const errorMessage = ref('')
  const result = ref(null)

  function localized(content) {
    return localizedText(content, locale.value)
  }

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const { data } = await assessmentsService.getAdvancedResult(assessmentId)
      result.value = data
      status.value = 'success'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  const available = computed(() => result.value?.available === true)
  const resultKind = computed(() => result.value?.resultKind ?? null)
  const feedbackItems = computed(() => result.value?.feedback?.items ?? [])

  return { status, errorMessage, result, available, resultKind, feedbackItems, localized, load }
}
