import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { ACCREDITED_STATUSES } from '@/constants/accredited'
import { ADVANCED_STATUSES } from '@/constants/advanced'

const AWAITING = [ACCREDITED_STATUSES.SUBMITTED, ADVANCED_STATUSES.AWAITING_ASSIGNMENT]
const IN_PROGRESS = [
  ACCREDITED_STATUSES.ASSIGNED,
  ACCREDITED_STATUSES.UNDER_REVIEW,
  ACCREDITED_STATUSES.NEEDS_COMPLETION,
  ADVANCED_STATUSES.UNDER_INITIAL_ASSESSMENT,
]

function sumOf(byStatus, keys) {
  return keys.reduce((sum, key) => sum + (byStatus[key] ?? 0), 0)
}

export function useQueueStatCards(stats) {
  const { t } = useI18n()

  return computed(() => {
    const value = stats.value
    if (!value) return []
    return [
      {
        id: 'total',
        icon: 'inbox',
        value: value.total,
        label: t('requests.stats.total'),
      },
      {
        id: 'awaiting',
        icon: 'user-clock',
        value: sumOf(value.byStatus, AWAITING),
        label: t('requests.stats.awaiting'),
      },
      {
        id: 'progress',
        icon: 'clipboard-check',
        value: sumOf(value.byStatus, IN_PROGRESS),
        label: t('requests.stats.inProgress'),
      },
      {
        id: 'overdue',
        icon: 'clock',
        value: value.overdue,
        label: t('requests.stats.overdue'),
      },
    ]
  })
}
