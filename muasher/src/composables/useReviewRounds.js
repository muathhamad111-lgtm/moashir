import { computed, shallowRef, watch } from 'vue'
import { assessorReviewService } from '@/services/assessorReviewService'
import { logger } from '@/utils/logger'
import { isReviewFinished } from '@/utils/assessments/reviewStages'

// The completion rounds of the open review (design §B.3.3). They are read again after every save,
// so an item scored in the current round stops waiting at once. Without them the workspace still
// works; only the round rows are missing.
export function useReviewRounds(reviewState) {
  const loaded = shallowRef(null)
  // Once the review is over nothing waits for a new result (production report 2026-11-25).
  const rounds = computed(() =>
    loaded.value && isReviewFinished(reviewState.assessment.value)
      ? { ...loaded.value, finished: true }
      : loaded.value,
  )

  async function load() {
    const assessmentId = reviewState.assessment.value?.id
    if (!assessmentId) return
    try {
      const { data } = await assessorReviewService.getRounds(assessmentId)
      loaded.value = data
    } catch (error) {
      logger.error(error, { action: 'review.rounds.load' })
      loaded.value = null
    }
  }

  watch(
    () => reviewState.review.value?.scores,
    (scores) => {
      if (scores) load()
    },
  )

  return { rounds, load }
}
