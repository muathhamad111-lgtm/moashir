import { computed, onScopeDispose, ref, shallowRef, watch } from 'vue'
import { assessmentOverridesService } from '@/services/assessmentOverridesService'
import {
  isFilled,
  normalizedRawValue,
  sameRawValue,
  savedScoreOf,
  scoreFieldName,
  unitScorePayload,
} from '@/utils/assessments/scoreSheet'
import { allowsRawValue } from '@/utils/assessments/scoringTypes'
import { logger } from '@/utils/logger'

// The edit pauses this long before the server recomputes the total and level.
const PREVIEW_DELAY_MS = 400

function mergePayloads(parts) {
  return parts.reduce(
    (merged, part) => ({
      subs: { ...merged.subs, ...part.subs },
      points: { ...merged.points, ...part.points },
    }),
    { subs: {}, points: {} },
  )
}

// Asks the server for the total and level the edits would give, once the edits pause; a late answer
// to an older edit is dropped.
function useOverridePreview({ reviewState, scores, ready }) {
  const preview = shallowRef(null)
  const previewing = ref(false)
  let timer = null
  let sequence = 0

  async function refresh() {
    const current = ++sequence
    if (!ready.value) {
      preview.value = null
      return
    }
    previewing.value = true
    try {
      const assessmentId = reviewState.assessment.value.id
      const { data } = await assessmentOverridesService.previewScores(assessmentId, {
        scores: scores.value,
      })
      if (current === sequence) preview.value = data
    } catch (error) {
      logger.error(error, { action: 'overrides.previewScores' })
      if (current === sequence) preview.value = null
    } finally {
      if (current === sequence) previewing.value = false
    }
  }

  function schedule() {
    clearTimeout(timer)
    timer = setTimeout(refresh, PREVIEW_DELAY_MS)
  }

  function stop() {
    clearTimeout(timer)
    sequence += 1
    preview.value = null
    previewing.value = false
  }

  watch(() => JSON.stringify(scores.value), schedule)
  onScopeDispose(() => clearTimeout(timer))

  return { preview, previewing, stop }
}

// The score override as an edit mode of the review (design §B.3.5, AS-9): the published values
// stay in the score sheet's saved scores, the Super Admin edits the fields in place, and the server
// previews the new total and level as the edits settle. Nothing is saved before the confirmation.
export function useScoreOverride({ reviewState, scoreSheet }) {
  const active = ref(false)
  const units = computed(() => reviewState.units.value)
  const saved = (unit) => savedScoreOf(reviewState.review.value?.scores, unit)
  const valueOf = (unit) => scoreSheet.sheet.values[scoreFieldName(unit)]

  const changedUnits = computed(() =>
    active.value
      ? units.value.filter((unit) => !sameRawValue(valueOf(unit), saved(unit)?.rawValue ?? ''))
      : [],
  )
  const isValid = computed(() =>
    changedUnits.value.every(
      (unit) => isFilled(valueOf(unit)) && allowsRawValue(valueOf(unit), scoreSheet.typeOf(unit)),
    ),
  )
  const scores = computed(() =>
    mergePayloads(
      changedUnits.value.map((unit) => unitScorePayload(unit, normalizedRawValue(valueOf(unit)))),
    ),
  )
  const ready = computed(() => changedUnits.value.length > 0 && isValid.value)
  const { preview, previewing, stop } = useOverridePreview({ reviewState, scores, ready })

  function enter() {
    scoreSheet.resetFrom(reviewState.review.value.scores)
    stop()
    active.value = true
  }

  function leave() {
    stop()
    active.value = false
    scoreSheet.resetFrom(reviewState.review.value.scores)
  }

  async function submit(reason) {
    const assessmentId = reviewState.assessment.value.id
    await assessmentOverridesService.overrideScores(assessmentId, { scores: scores.value, reason })
    stop()
    active.value = false
    await reviewState.load({ silent: true })
    scoreSheet.resetFrom(reviewState.review.value.scores)
  }

  return { active, preview, previewing, changedUnits, isValid, saved, enter, leave, submit }
}
