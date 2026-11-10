import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { usersService } from '@/services/usersService'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
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
}

const ACTION_ICONS = {
  [A.EDIT]: 'pen',
  [A.SUSPEND]: 'user-slash',
  [A.ACTIVATE]: 'user-check',
  [A.UNLOCK]: 'lock-open',
  [A.RESEND_INVITATION]: 'send',
  [A.RESET_MFA]: 'lock',
  [A.QUALIFICATIONS]: 'graduation-cap',
  [A.REQUEST_SCOPE]: 'route',
}

const DANGER = [A.SUSPEND, A.RESET_MFA]

function availableKeys(user, selfId) {
  const keys = [A.EDIT]
  const isSelf = user.id === selfId
  if (user.status === S.ACTIVE && !isSelf) keys.push(A.SUSPEND)
  if (user.status === S.SUSPENDED) keys.push(A.ACTIVATE)
  if (user.status === S.LOCKED) keys.push(A.UNLOCK)
  if ([S.INVITED, S.EXPIRED].includes(user.status)) keys.push(A.RESEND_INVITATION)
  if (user.mfaEnabled && !isSelf) keys.push(A.RESET_MFA)
  if (isQualifiableUser(user) && !isSelf) keys.push(A.QUALIFICATIONS)
  if (isScopableUser(user) && !isSelf) keys.push(A.REQUEST_SCOPE)
  return keys
}

export function useUserActions({ onChanged }) {
  const { t } = useI18n()
  const toast = useToast()
  const auth = useAuthStore()
  const { confirm } = useConfirm()
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
    return availableKeys(user, auth.user?.id ?? null).map((key) => ({
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

  async function resendInvitation(user) {
    const accepted = await confirm({
      title: t('users.actionDialogs.resendInvitation.title'),
      message: user.name,
      confirmLabel: t('actions.send'),
      variant: 'confirm',
    })
    if (!accepted) return
    try {
      await usersService.resendInvitation(user.id)
      toast.success(t('users.toasts.resendInvitation', { name: user.name }))
      onChanged()
    } catch (error) {
      toast.error(error?.normalized?.message || t('errors.unexpected'))
    }
  }

  function start(key, user) {
    if (key === A.RESEND_INVITATION) return resendInvitation(user)
    pending.value = { key, user }
    return null
  }

  return { actionsFor, start, dialog, dialogOpen, runWithReason }
}
