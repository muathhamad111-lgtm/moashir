import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

const STAT_ICONS = {
  total: 'users-two',
  active: 'user-check',
  invited: 'envelope',
  suspended: 'user-slash',
  locked: 'lock',
}

export function useUserStatCards(stats) {
  const { t } = useI18n()
  return computed(() =>
    Object.entries(STAT_ICONS).map(([key, icon]) => ({
      id: key,
      icon,
      value: stats.value?.[key] ?? 0,
      label: t(`users.stats.${key}`),
    })),
  )
}
