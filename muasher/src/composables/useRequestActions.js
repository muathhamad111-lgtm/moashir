import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePermissions } from '@/composables/usePermissions'
import { useAuthStore } from '@/stores/auth'
import { PERMISSIONS } from '@/constants/permissionKeys'
import {
  ACCREDITED_STATUSES,
  ASSIGNMENT_MODES,
  ASSIGNMENT_STATUSES,
  FORCE_BLOCKED_STATUSES,
} from '@/constants/accredited'
import { ASSESSMENT_PATHS } from '@/constants/assessment'
import { reviewActionKey } from '@/utils/reviewAction'

export const REQUEST_MENU_ACTIONS = Object.freeze({
  RETURN: 'return',
  WITHDRAW: 'withdraw',
  OVERRIDE: 'override',
  REOPEN: 'reopen',
  FORCE: 'force',
})

// The «⋯» menu: return and the score override first, then the destructive actions (the menu draws a line above them).
function menuActionsOf(t, rights) {
  const M = REQUEST_MENU_ACTIONS
  return [
    rights.canReturn && { key: M.RETURN, label: t('requests.actions.return'), icon: 'reply' },
    rights.superAdmin &&
      rights.completed && { key: M.OVERRIDE, label: t('requests.overrides.scores'), icon: 'pen' },
    rights.canWithdraw && {
      key: M.WITHDRAW,
      label: t('requests.actions.withdraw'),
      icon: 'ban',
      variant: 'danger',
    },
    rights.superAdmin &&
      rights.completed && {
        key: M.REOPEN,
        label: t('requests.overrides.reopen'),
        icon: 'lock-open',
        variant: 'danger',
      },
    rights.superAdmin &&
      rights.forceable && {
        key: M.FORCE,
        label: t('requests.overrides.force'),
        icon: 'shuffle',
        variant: 'danger',
      },
  ].filter(Boolean)
}

// The actions the request page offers in its header (admin review 2026-11-20): one primary action for the
// request's state, the evidence bundle beside it, and the rest in a «⋯» menu with the Super Admin actions last.
export function useRequestActions(detail, { assignmentMode, canReturn, canWithdraw }) {
  const { t } = useI18n()
  const { can, canAny } = usePermissions()
  const auth = useAuthStore()

  const isAccredited = computed(() => detail.value.path === ASSESSMENT_PATHS.ACCREDITED)
  const isCompleted = computed(() => detail.value.status === ACCREDITED_STATUSES.COMPLETED)
  const mayReview = computed(
    () =>
      canAny([PERMISSIONS.REQUESTS_REVIEW_READ, PERMISSIONS.ASSESSMENT_SCORE_MANAGE]) &&
      detail.value.assignmentHistory.length > 0,
  )
  const isAssignee = computed(() =>
    detail.value.assignmentHistory.some(
      (assignment) =>
        assignment.status === ASSIGNMENT_STATUSES.ACTIVE &&
        assignment.assessor?.id === auth.user?.id,
    ),
  )
  const reviewLabel = computed(() =>
    isAssignee.value
      ? t(`requests.assigned.actions.${reviewActionKey(detail.value.status)}`)
      : t('requests.actions.openReview'),
  )
  const assignsFirst = computed(() => assignmentMode.value === ASSIGNMENT_MODES.ASSIGN)
  const mayBundle = computed(() => can(PERMISSIONS.EVIDENCE_VIEW))
  const superAdminOnAccredited = computed(() => auth.isSuperAdmin && isAccredited.value)

  const menuActions = computed(() =>
    menuActionsOf(t, {
      canReturn: canReturn.value,
      canWithdraw: canWithdraw.value,
      superAdmin: superAdminOnAccredited.value,
      completed: isCompleted.value,
      forceable: !FORCE_BLOCKED_STATUSES.includes(detail.value.status),
    }),
  )

  return { mayReview, reviewLabel, assignsFirst, mayBundle, menuActions }
}
