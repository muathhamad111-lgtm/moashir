import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { usersService } from '@/services/usersService'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { usePermissions } from '@/composables/usePermissions'
import { PERMISSIONS } from '@/constants/permissionKeys'
import { USER_ACTIONS as A, USER_STATUSES as S } from '@/constants/users'
import { isQualifiableUser, isScopableUser } from '@/utils/users/pathAccessRoles'

const REASON_ACTIONS = {
  [A.SUSPEND]: {
    run: (id, payload) => usersService.suspend(id, payload),
    required: true,
    destructive: true,
  },
  [A.ACTIVATE]: { run: (id, payload) => usersService.activate(id, payload), required: false },
  [A.UNLOCK]: { run: (id, payload) => usersService.unlock(id, payload), required: false },
  [A.RESET_MFA]: {
    run: (id, payload) => usersService.resetMfa(id, payload),
    required: true,
    destructive: true,
  },
  [A.REVOKE_SESSIONS]: {
    run: (id, payload) => usersService.revokeSessions(id, payload),
    required: false,
    destructive: true,
  },
}

const ACTION_ICONS = {
  [A.DETAILS]: 'eye',
  [A.EDIT]: 'pen',
  [A.SUSPEND]: 'user-slash',
  [A.ACTIVATE]: 'user-check',
  [A.UNLOCK]: 'lock-open',
  [A.RESEND_INVITATION]: 'send',
  [A.SEND_PASSWORD_RESET]: 'envelope',
  [A.REVOKE_SESSIONS]: 'logout',
  [A.CANCEL_INVITATION]: 'trash',
  [A.ACTIVITY]: 'clock',
  [A.RESET_MFA]: 'lock',
  [A.QUALIFICATIONS]: 'graduation-cap',
  [A.REQUEST_SCOPE]: 'route',
  [A.NAME_CORRECTION]: 'pen-line',
  [A.IDENTITY_HISTORY]: 'doc',
}

const DANGER = [A.SUSPEND, A.RESET_MFA, A.REVOKE_SESSIONS, A.CANCEL_INVITATION]

function identityKeys(user, isSelf, canReviewIdentity) {
  const keys = []
  if (![S.INVITED, S.EXPIRED].includes(user.status) && !isSelf) keys.push(A.NAME_CORRECTION)
  if (canReviewIdentity) keys.push(A.IDENTITY_HISTORY)
  return keys
}

function availableKeys(user, { selfId, canViewAudit, canReviewIdentity }) {
  const keys = [A.DETAILS, A.EDIT]
  const isSelf = user.id === selfId
  if (user.status === S.ACTIVE && !isSelf) keys.push(A.SUSPEND)
  if (user.status === S.SUSPENDED) keys.push(A.ACTIVATE)
  if (user.status === S.LOCKED) keys.push(A.UNLOCK)
  if ([S.INVITED, S.EXPIRED].includes(user.status)) keys.push(A.RESEND_INVITATION)
  if ([S.INVITED, S.EXPIRED].includes(user.status) && !isSelf) keys.push(A.CANCEL_INVITATION)
  if (user.status === S.ACTIVE && !isSelf) keys.push(A.SEND_PASSWORD_RESET)
  if (user.mfaEnabled && !isSelf) keys.push(A.RESET_MFA)
  if (isQualifiableUser(user) && !isSelf) keys.push(A.QUALIFICATIONS)
  if (isScopableUser(user) && !isSelf) keys.push(A.REQUEST_SCOPE)
  if (![S.INVITED, S.EXPIRED].includes(user.status) && !isSelf) keys.push(A.REVOKE_SESSIONS)
  keys.push(...identityKeys(user, isSelf, canReviewIdentity))
  if (canViewAudit) keys.push(A.ACTIVITY)
  return keys
}

export function useUserActions({ onChanged }) {
  const { t } = useI18n()
  const toast = useToast()
  const auth = useAuthStore()
  const { confirm } = useConfirm()
  const { can } = usePermissions()
  const pending = ref(null)

  const dialogOpen = computed({
    get: () => pending.value !== null,
    set: (open) => {
      if (!open) pending.value = null
    },
  })
  const dialog = computed(() => {
    if (!pending.value) return null
    const { key, user } = pending.value
    return {
      title: t(`users.actionDialogs.${key}.title`),
      subtitle: t(`users.actionDialogs.${key}.message`, { name: user.name }),
      submitLabel: t(`users.actions.${key}`),
      destructive: Boolean(REASON_ACTIONS[key].destructive),
      optional: !REASON_ACTIONS[key].required && !auth.isSuperAdmin,
    }
  })

  function actionsFor(user) {
    return availableKeys(user, {
      selfId: auth.user?.id ?? null,
      canViewAudit: can(PERMISSIONS.AUDIT_VIEW),
      canReviewIdentity: can(PERMISSIONS.USERS_IDENTITY_CHANGES_REVIEW),
    }).map((key) => ({
      key,
      label: t(`users.actions.${key}`),
      icon: ACTION_ICONS[key],
      ...(DANGER.includes(key) ? { variant: 'danger' } : {}),
    }))
  }

  async function runWithReason(reason) {
    const { key, user } = pending.value
    const payload = reason ? { reason } : {}
    const { data } = await REASON_ACTIONS[key].run(user.id, payload)
    toast.success(t(`users.toasts.${key}`, { name: data.name }))
    onChanged()
    return data
  }

  // Confirm-only actions: no reason, the server e-mails the user and the list reloads.
  const CONFIRM_ACTIONS = {
    [A.RESEND_INVITATION]: (id) => usersService.resendInvitation(id),
    [A.SEND_PASSWORD_RESET]: (id) => usersService.sendPasswordResetLink(id),
    [A.CANCEL_INVITATION]: (id) => usersService.cancelInvitation(id),
  }
  const CONFIRM_LABELS = { [A.CANCEL_INVITATION]: 'users.actions.cancelInvitation' }

  async function confirmAndSend(key, user) {
    const accepted = await confirm({
      title: t(`users.actionDialogs.${key}.title`),
      message: user.name,
      confirmLabel: t(CONFIRM_LABELS[key] ?? 'actions.send'),
      variant: CONFIRM_LABELS[key] ? 'danger' : 'confirm',
    })
    if (!accepted) return
    try {
      await CONFIRM_ACTIONS[key](user.id)
      toast.success(t(`users.toasts.${key}`, { name: user.name }))
      onChanged()
    } catch (error) {
      toast.error(error?.normalized?.message || t('errors.unexpected'))
    }
  }

  function start(key, user) {
    if (CONFIRM_ACTIONS[key]) return confirmAndSend(key, user)
    pending.value = { key, user }
    return null
  }

  return { actionsFor, start, dialog, dialogOpen, runWithReason }
}
