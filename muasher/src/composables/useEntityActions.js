import { useI18n } from 'vue-i18n'
import { usePermissions } from '@/composables/usePermissions'
import { useAuthStore } from '@/stores/auth'
import { PERMISSIONS as P } from '@/constants/permissionKeys'
import { ENTITY_STATUSES as S } from '@/constants/entityEnums'

export const ENTITY_ACTIONS = {
  VIEW: 'view',
  EDIT: 'edit',
  APPROVE: 'approve',
  REJECT: 'reject',
  DEACTIVATE: 'deactivate',
  REACTIVATE: 'reactivate',
  RESEND_VERIFICATION: 'resendVerification',
  DELETE: 'delete',
  RESTORE: 'restore',
  PERMANENT_DELETE: 'permanentDelete',
}

const ICONS = {
  view: 'eye',
  edit: 'pen',
  approve: 'check',
  reject: 'x',
  deactivate: 'lock',
  reactivate: 'check-circle',
  resendVerification: 'send',
  delete: 'trash',
  restore: 'undo',
  permanentDelete: 'trash',
}

const A = ENTITY_ACTIONS

// The row shows the first two (decisions first); the rest sit in the row's «⋮» menu.
const INLINE_BY_STATUS = Object.freeze({
  [S.PENDING_REVIEW]: [A.APPROVE, A.REJECT, A.VIEW],
  [S.PENDING_VERIFICATION]: [A.RESEND_VERIFICATION, A.REJECT, A.VIEW],
  [S.REJECTED]: [A.VIEW, A.APPROVE],
  [S.ACTIVE]: [A.VIEW, A.EDIT, A.DEACTIVATE],
  [S.DEACTIVATED]: [A.VIEW, A.REACTIVATE],
})

const INLINE_WHEN_DELETED = Object.freeze([A.VIEW, A.RESTORE])

export function entityInlineActionsFor(entity) {
  if (entity.isDeleted) return INLINE_WHEN_DELETED
  return INLINE_BY_STATUS[entity.status] ?? [A.VIEW, A.EDIT]
}

const POSITIVE = new Set([ENTITY_ACTIONS.APPROVE])

const DANGEROUS = new Set([
  ENTITY_ACTIONS.REJECT,
  ENTITY_ACTIONS.DEACTIVATE,
  ENTITY_ACTIONS.DELETE,
  ENTITY_ACTIONS.PERMANENT_DELETE,
])

function variantOf(key) {
  if (DANGEROUS.has(key)) return 'danger'
  return POSITIVE.has(key) ? 'success' : undefined
}

export function useEntityActions() {
  const { t } = useI18n()
  const { can } = usePermissions()
  const auth = useAuthStore()

  function isFullRecord(entity) {
    return 'entityType' in entity
  }

  const RULES = {
    [ENTITY_ACTIONS.EDIT]: (entity) =>
      can(P.ENTITIES_MANAGE) && isFullRecord(entity) && !entity.isDeleted,
    [ENTITY_ACTIONS.APPROVE]: (entity) =>
      can(P.ENTITIES_MANAGE) &&
      !entity.isDeleted &&
      [S.PENDING_REVIEW, S.REJECTED].includes(entity.status),
    [ENTITY_ACTIONS.REJECT]: (entity) =>
      can(P.ENTITIES_MANAGE) &&
      !entity.isDeleted &&
      [S.PENDING_VERIFICATION, S.PENDING_REVIEW].includes(entity.status),
    [ENTITY_ACTIONS.DEACTIVATE]: (entity) =>
      can(P.ENTITIES_DEACTIVATE) && !entity.isDeleted && entity.status === S.ACTIVE,
    [ENTITY_ACTIONS.REACTIVATE]: (entity) =>
      can(P.ENTITIES_REACTIVATE) && !entity.isDeleted && entity.status === S.DEACTIVATED,
    [ENTITY_ACTIONS.RESEND_VERIFICATION]: (entity) =>
      can(P.ENTITIES_VERIFICATION_RESEND) &&
      !entity.isDeleted &&
      entity.status === S.PENDING_VERIFICATION,
    [ENTITY_ACTIONS.DELETE]: (entity) => can(P.ENTITIES_DELETE) && !entity.isDeleted,
    [ENTITY_ACTIONS.RESTORE]: (entity) => can(P.ENTITIES_DELETE) && entity.isDeleted === true,
    [ENTITY_ACTIONS.PERMANENT_DELETE]: (entity) =>
      auth.user?.isSuperAdmin === true && entity.isDeleted === true,
  }

  function describe(key) {
    return {
      key,
      label: t(`entities.actions.${key}`),
      icon: ICONS[key],
      variant: variantOf(key),
    }
  }

  function actionsFor(entity) {
    return Object.keys(RULES)
      .filter((key) => RULES[key](entity))
      .map(describe)
  }

  function rowActionsFor(entity) {
    return [describe(ENTITY_ACTIONS.VIEW), ...actionsFor(entity)]
  }

  return { actionsFor, rowActionsFor }
}
