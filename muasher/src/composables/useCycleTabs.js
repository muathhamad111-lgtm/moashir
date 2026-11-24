import { computed, toValue } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePermissions } from '@/composables/usePermissions'
import { CYCLE_STATUSES } from '@/constants/advanced'
import { COMMITTEE_CYCLE_STATUSES } from '@/constants/committee'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { ROUTE_NAMES } from '@/constants/routeNames'

const AFTER_SUBMISSIONS = [
  CYCLE_STATUSES.SUBMISSION_CLOSED,
  ...COMMITTEE_CYCLE_STATUSES,
  CYCLE_STATUSES.APPROVED,
  CYCLE_STATUSES.ANNOUNCED,
  CYCLE_STATUSES.CLOSED,
]

// The results tab also covers the national board, which opens from it.
const TAB_PAGES = {
  results: [ROUTE_NAMES.AWARD_RESULTS_BOARD, ROUTE_NAMES.AWARD_NATIONAL],
}

// The cycle's stages a user may open, in the order the cycle runs through them.
export function useCycleTabs(cycle) {
  const { t } = useI18n()
  const { can } = usePermissions()

  return computed(() => {
    const status = toValue(cycle)?.status
    const afterSubmissions = AFTER_SUBMISSIONS.includes(status)
    return [
      { key: 'details', route: ROUTE_NAMES.AWARD_CYCLE, icon: 'doc', shown: true },
      {
        key: 'filtering',
        route: ROUTE_NAMES.AWARD_FILTERING_QUEUE,
        icon: 'filter',
        shown: can(PERMISSIONS.AWARD_FILTERING_VIEW) && status !== CYCLE_STATUSES.DRAFT,
      },
      {
        key: 'committee',
        route: ROUTE_NAMES.AWARD_COMMITTEE_ROSTER,
        icon: 'users-two',
        shown: can(PERMISSIONS.AWARD_COMMITTEE_MANAGE) || can(PERMISSIONS.AWARD_SECRETARY_REVIEW),
      },
      {
        key: 'secretary',
        route: ROUTE_NAMES.AWARD_SECRETARY_DESK,
        icon: 'stamp',
        shown: can(PERMISSIONS.AWARD_SECRETARY_REVIEW) && afterSubmissions,
      },
      {
        key: 'results',
        route: ROUTE_NAMES.AWARD_RESULTS_BOARD,
        icon: 'trophy',
        shown: can(PERMISSIONS.AWARD_RESULTS_VIEW) && afterSubmissions,
      },
    ]
      .filter((tab) => tab.shown)
      .map((tab) => ({
        ...tab,
        label: t(`cycles.workspace.tabs.${tab.key}`),
        names: TAB_PAGES[tab.key] ?? [tab.route],
      }))
  })
}
