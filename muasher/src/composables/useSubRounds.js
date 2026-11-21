import { computed } from 'vue'
import { savedScoreOf } from '@/utils/assessments/scoreSheet'
import { isAwaitingRescore, isNewInRound, roundRowsOf } from '@/utils/assessments/reviewRounds'

// One sub-criterion card's view of the completion rounds (design §B.3.3): each item's round rows,
// whether it waits for a new result, and the files the entity added in this round.
export function useSubRounds({ sub, units, files, workspace, disabled }) {
  const rounds = computed(() => workspace.rounds?.rounds.value ?? null)
  const newIds = computed(() =>
    files.value.filter((file) => isNewInRound(rounds.value, file)).map((file) => file.id),
  )
  const anyAwaiting = computed(() =>
    units.value.some((unit) => isAwaitingRescore(rounds.value, unit, sub.value.code)),
  )

  function roundOf(unit) {
    const awaiting = isAwaitingRescore(rounds.value, unit, sub.value.code)
    const saved = savedScoreOf(workspace.reviewState.review.value.scores, unit)
    return {
      rows: roundRowsOf(rounds.value, unit, sub.value.code),
      awaiting,
      newIds: newIds.value,
      canConfirm: awaiting && !disabled.value && Boolean(saved),
      confirming: workspace.actions.busy.value === 'confirm',
    }
  }

  return { roundOf, newIds, anyAwaiting }
}
