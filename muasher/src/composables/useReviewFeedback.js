import { computed, reactive, ref } from 'vue'
import { assessorReviewService } from '@/services/assessorReviewService'
import { FEEDBACK_FIELDS } from '@/constants/accredited'

const EMPTY_FIELDS = Object.freeze(Object.fromEntries(FEEDBACK_FIELDS.map((field) => [field, ''])))

export function useReviewFeedback({ reviewState }) {
  const drafts = reactive({})
  const saving = ref(false)

  function savedOf(subId, field) {
    return reviewState.review.value?.feedback?.[String(subId)]?.[field] ?? ''
  }

  function resetFrom(feedback) {
    Object.keys(drafts).forEach((key) => delete drafts[key])
    Object.entries(feedback ?? {}).forEach(([subId, fields]) => {
      drafts[subId] = Object.fromEntries(FEEDBACK_FIELDS.map((field) => [field, fields[field] ?? '']))
    })
  }

  function fieldsOf(subId) {
    return drafts[String(subId)] ?? EMPTY_FIELDS
  }

  function setField(subId, field, value) {
    const key = String(subId)
    if (!drafts[key]) drafts[key] = { ...EMPTY_FIELDS }
    drafts[key][field] = value
  }

  const changes = computed(() =>
    Object.fromEntries(
      Object.entries(drafts).flatMap(([subId, fields]) => {
        const changed = FEEDBACK_FIELDS.filter((field) => (fields[field] ?? '') !== savedOf(subId, field))
        if (!changed.length) return []
        return [[subId, Object.fromEntries(changed.map((field) => [field, fields[field]?.trim() ? fields[field] : null]))]]
      }),
    ),
  )
  const dirty = computed(() => Object.keys(changes.value).length > 0)

  async function save(extra = {}) {
    if (!dirty.value) return null
    saving.value = true
    try {
      const { data } = await assessorReviewService.saveFeedback(reviewState.assessment.value.id, { feedback: changes.value, ...extra })
      reviewState.patch({ feedback: data.feedback, assessment: data.assessment })
      resetFrom(data.feedback)
      return data
    } finally {
      saving.value = false
    }
  }

  return { drafts, saving, dirty, changes, resetFrom, fieldsOf, setField, save }
}
