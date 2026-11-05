import { useI18n } from 'vue-i18n'
import { usePermissions } from '@/composables/usePermissions'
import { useAuthStore } from '@/stores/auth'
import { PERMISSIONS as P } from '@/constants/permissionKeys'
import { ENTITY_STATUSES as S } from '@/constants/entityEnums'

export const ENTITY_ACTIONS = {
  VIEW: 'view',
  EDIT: 'edit',
  DEACTIVATE: 'deactivate',
  REACTIVATE: 'reactivate',
  RESEND_VERIFICATION: 'resendVerification',
  DELETE: 'delete',
  RESTORE: 'restore',
  PERMANENT_DELETE: 'permanentDelete',
  EXPORT_PERSONAL_DATA: 'exportPersonalData',
}

const ICONS = {
  view: 'eye',
  edit: 'pen',
  deactivate: 'ban',
  reactivate: 'check-circle',
  resendVerification: 'send',
  delete: 'trash',
  restore: 'undo',
  permanentDelete: 'trash',
  exportPersonalData: 'upload',
}

const DANGEROUS = new Set([
  ENTITY_ACTIONS.DEACTIVATE,
  ENTITY_ACTIONS.DELETE,
  ENTITY_ACTIONS.PERMANENT_DELETE,
])

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
    [ENTITY_ACTIONS.EXPORT_PERSONAL_DATA]: () => can(P.PRIVACY_REQUESTS_MANAGE),
  }

  function describe(key) {
    return {
      key,
      label: t(`entities.actions.${key}`),
      icon: ICONS[key],
      variant: DANGEROUS.has(key) ? 'danger' : undefined,
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
