import { computed, reactive, ref } from 'vue'
import { assessorReviewService } from '@/services/assessorReviewService'
import { noteKey, notesByUnit } from '@/utils/assessments/reviewNotes'

// The viewer's internal notes on a review (operator 2026-11-22): one per unit and author, never
// shown to the entity. Drafts are keyed by unit; the saved notes come with the review.
export function useReviewNotes({ reviewState }) {
  const drafts = reactive({})
  const saving = ref(false)

  const notes = computed(() => reviewState.review.value?.reviewNotes ?? null)
  const canRead = computed(() => notes.value !== null)
  const canWrite = computed(() => Boolean(reviewState.review.value?.canWriteReviewNotes))
  const byUnit = computed(() => notesByUnit(notes.value))

  const savedBodyOf = (unit) => byUnit.value.get(noteKey(unit))?.mine?.body ?? ''
  // A viewer who may not write (an assessor whose assignment ended) reads their own note with the rest.
  function threadOf(unit) {
    const entry = byUnit.value.get(noteKey(unit))
    if (!entry) return []
    return canWrite.value || !entry.mine ? entry.others : [entry.mine, ...entry.others]
  }

  function bodyOf(unit) {
    const draft = drafts[noteKey(unit)]
    return draft ? draft.body : savedBodyOf(unit)
  }

  function setBody(unit, body) {
    drafts[noteKey(unit)] = { unit, body }
  }

  function resetFrom() {
    Object.keys(drafts).forEach((key) => delete drafts[key])
  }

  const changes = computed(() =>
    Object.values(drafts)
      .filter(({ unit, body }) => body.trim() !== savedBodyOf(unit).trim())
      .map(({ unit, body }) => ({ ...unit, body: body.trim() ? body.trim() : null })),
  )
  const dirty = computed(() => changes.value.length > 0)

  async function save(extra = {}) {
    if (!dirty.value) return null
    saving.value = true
    try {
      const { data } = await assessorReviewService.saveReviewNotes(
        reviewState.assessment.value.id,
        { notes: changes.value, ...extra },
      )
      reviewState.patch(data)
      resetFrom()
      return data
    } finally {
      saving.value = false
    }
  }

  return { canRead, canWrite, saving, dirty, changes, bodyOf, threadOf, setBody, resetFrom, save }
}
