import { computed, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { submissionService } from '@/services/submissionService'
import { useToast } from '@/composables/useToast'

export function useCvDrafts({ formState, onRejected }) {
  const { t } = useI18n()
  const toast = useToast()
  const drafts = reactive({})
  const saving = ref(false)

  function savedOf(subId) {
    return formState.form.value?.cv?.[String(subId)] ?? ''
  }

  function resetFrom(cv) {
    Object.keys(drafts).forEach((key) => delete drafts[key])
    Object.entries(cv ?? {}).forEach(([subId, text]) => {
      drafts[subId] = text
    })
  }

  function setDraft(subId, text) {
    drafts[String(subId)] = text
  }

  function draftOf(subId) {
    return drafts[String(subId)] ?? ''
  }

  const changes = computed(() =>
    Object.fromEntries(
      Object.entries(drafts)
        .filter(([subId, text]) => (text ?? '') !== savedOf(subId))
        .map(([subId, text]) => [subId, text?.trim() ? text : null]),
    ),
  )
  const dirty = computed(() => Object.keys(changes.value).length > 0)

  async function save({ quiet = false } = {}) {
    if (saving.value || !dirty.value) return true
    saving.value = true
    try {
      const { data } = await submissionService.saveCv(formState.assessment.value.id, { cv: changes.value })
      formState.patch({ cv: data.cv, assessment: data.assessment })
      resetFrom(data.cv)
      await formState.load({ silent: true })
      if (!quiet) toast.success(t('accredited.form.toasts.cvSaved'))
      return true
    } catch (error) {
      onRejected(error)
      return false
    } finally {
      saving.value = false
    }
  }

  return { drafts, saving, dirty, changes, resetFrom, setDraft, draftOf, save }
}
