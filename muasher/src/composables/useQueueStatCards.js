import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { QUEUE_STAGES } from '@/constants/accredited'

// The stat cards of the request list; each card's id is the stage it filters.
const CARDS = [
  { id: QUEUE_STAGES.ALL, icon: 'inbox', label: 'requests.stats.total' },
  { id: QUEUE_STAGES.AWAITING_ASSIGNMENT, icon: 'user-clock', label: 'requests.stats.awaiting' },
  { id: QUEUE_STAGES.IN_PROGRESS, icon: 'clipboard-check', label: 'requests.stats.inProgress' },
  { id: QUEUE_STAGES.OVERDUE, icon: 'clock', label: 'requests.stats.overdue' },
]

export function useQueueStatCards(stats) {
  const { t } = useI18n()

  return computed(() => {
    const value = stats.value
    if (!value) return []
    return CARDS.map((card) => ({
      id: card.id,
      icon: card.icon,
      label: t(card.label),
      value: card.id === QUEUE_STAGES.ALL ? value.total : (value.byStage[card.id] ?? 0),
    }))
  })
}
