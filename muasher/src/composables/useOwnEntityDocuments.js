import { computed, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { toSnakeKey } from '@/api/caseMapping'
import { entitiesService } from '@/services/entitiesService'
import { useEntityStore } from '@/stores/entity'
import { useToast } from '@/composables/useToast'
import { fileUploadMessage } from '@/utils/fileUploadError'
import { entityDocumentIssue } from '@/utils/entityDocumentRules'
import { ENTITY_DOCUMENT_RULES, REQUIRED_ENTITY_DOCUMENTS } from '@/constants/entityDocuments'

/**
 * The entity's own license certificate and authorization letter: which are missing, which wait for approval, and
 * the upload that proposes a new one through the profile change request.
 */
export function useOwnEntityDocuments() {
  const { t } = useI18n()
  const toast = useToast()
  const store = useEntityStore()
  const busy = reactive({})
  const errors = reactive({})

  const documents = computed(() => store.profile?.documents ?? {})
  const pendingItems = computed(() => store.profile?.pendingProfileChange?.items ?? [])
  const missing = computed(() => REQUIRED_ENTITY_DOCUMENTS.filter((key) => !documents.value[key]))

  function isPending(key) {
    return pendingItems.value.includes(toSnakeKey(key))
  }

  function issueMessage(issue) {
    return t(`entities.documents.errors.${issue}`, { max: ENTITY_DOCUMENT_RULES.maxMb })
  }

  async function upload(key, file) {
    if (busy[key]) return false
    const issue = entityDocumentIssue(file)
    errors[key] = issue ? issueMessage(issue) : ''
    if (issue) return false
    busy[key] = true
    try {
      const { data } = await entitiesService.uploadOwnDocument(key, file)
      await store.applyProposal(data)
      toast.success(t('entities.documents.submitted'))
      return true
    } catch (failure) {
      errors[key] = fileUploadMessage(failure, t('errors.unexpected'))
      return false
    } finally {
      busy[key] = false
    }
  }

  function requestLink(key) {
    return entitiesService.requestOwnDocumentDownload(key)
  }

  return { documents, missing, busy, errors, isPending, upload, requestLink }
}
