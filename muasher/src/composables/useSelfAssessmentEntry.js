import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { assessmentsService } from '@/services/assessmentsService'
import { useToast } from '@/composables/useToast'
import { logger } from '@/utils/logger'
import { ASSESSMENT_PATHS, SELF_ASSESSMENT_CODES as CODES, SELF_STATUSES } from '@/constants/assessment'

export const ENTRY_STATES = {
  LOADING: 'loading',
  START: 'start',
  DRAFT: 'draft',
  NO_CRITERIA: 'no-criteria',
  BLOCKED: 'blocked',
  ERROR: 'error',
}

const BLOCKING_CODES = [CODES.ENTITY_NOT_ACTIVE, CODES.ENTITY_REQUIRED, CODES.ENTITY_DELETED]
const OPEN_DRAFT_QUERY = { 'filter[path]': ASSESSMENT_PATHS.SELF, 'filter[status]': SELF_STATUSES.DRAFT, perPage: 1 }

export function useSelfAssessmentEntry() {
  const { t } = useI18n()
  const toast = useToast()
  const state = ref(ENTRY_STATES.LOADING)
  const draftId = ref(null)
  const publishedVersion = ref(null)
  const blockedCode = ref('')
  const errorMessage = ref('')
  const starting = ref(false)

  function codeOf(error) {
    return error?.normalized?.code ?? null
  }

  async function loadPublishedVersion() {
    try {
      const { data } = await assessmentsService.getPublishedCriteria()
      publishedVersion.value = data
      state.value = ENTRY_STATES.START
    } catch (error) {
      if (codeOf(error) !== CODES.NO_PUBLISHED_CRITERIA) throw error
      state.value = ENTRY_STATES.NO_CRITERIA
    }
  }

  async function locate() {
    state.value = ENTRY_STATES.LOADING
    errorMessage.value = ''
    try {
      const { data } = await assessmentsService.list(OPEN_DRAFT_QUERY)
      if (data[0]) {
        draftId.value = data[0].id
        state.value = ENTRY_STATES.DRAFT
        return
      }
      await loadPublishedVersion()
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      state.value = ENTRY_STATES.ERROR
    }
  }

  function openDraft(id) {
    draftId.value = id
    state.value = ENTRY_STATES.DRAFT
  }

  function handleStartRejection(error) {
    const code = codeOf(error)
    if (code === CODES.DRAFT_EXISTS && error.normalized.meta?.assessmentId) {
      toast.info(t('assessment.self.toasts.draftResumed'))
      return openDraft(error.normalized.meta.assessmentId)
    }
    if (code === CODES.NO_PUBLISHED_CRITERIA) {
      state.value = ENTRY_STATES.NO_CRITERIA
      return
    }
    if (BLOCKING_CODES.includes(code)) {
      blockedCode.value = code
      state.value = ENTRY_STATES.BLOCKED
      return
    }
    toast.error(error?.normalized?.message || t('errors.unexpected'))
  }

  async function start() {
    if (starting.value) return
    starting.value = true
    logger.breadcrumb('form', 'assessments.startSelf.submit')
    try {
      const { data } = await assessmentsService.startSelf()
      toast.success(t('assessment.self.toasts.started', { number: data.number }))
      openDraft(data.id)
    } catch (error) {
      handleStartRejection(error)
    } finally {
      starting.value = false
    }
  }

  return { state, draftId, publishedVersion, blockedCode, errorMessage, starting, locate, start }
}
