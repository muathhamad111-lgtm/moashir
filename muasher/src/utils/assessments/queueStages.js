import { QUEUE_STAGES } from '@/constants/accredited'

const LABEL_KEYS = Object.freeze({
  [QUEUE_STAGES.ALL]: 'all',
  [QUEUE_STAGES.PREPARING]: 'preparing',
  [QUEUE_STAGES.AWAITING_ASSIGNMENT]: 'awaitingAssignment',
  [QUEUE_STAGES.IN_PROGRESS]: 'inProgress',
  [QUEUE_STAGES.OVERDUE]: 'overdue',
  [QUEUE_STAGES.CLOSED]: 'closed',
})

export function stageLabelKey(stage) {
  return `requests.stages.${LABEL_KEYS[stage] ?? LABEL_KEYS[QUEUE_STAGES.ALL]}`
}

// The count of every stage option: «all» is the list total; the others come from the stage counts.
export function stageCountsOf(stats) {
  if (!stats) return null
  return { [QUEUE_STAGES.ALL]: stats.total, ...stats.byStage }
}
