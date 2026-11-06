import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { committeeService } from '@/services/committeeService'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { useAuthStore } from '@/stores/auth'
import { SEAT_STATUSES } from '@/constants/committee'

const REASON_DIALOGS = ['disable', 'remove', 'exclude']

function actionKeys(member) {
  if (member.status === SEAT_STATUSES.ACTIVE) return ['disable', 'remove']
  if (member.status === SEAT_STATUSES.DISABLED) return ['substitute', 'exclude']
  return []
}

export function useRosterActions({ cycleId, roster }) {
  const { t } = useI18n()
  const toast = useToast()
  const auth = useAuthStore()
  const { confirm } = useConfirm()
  const pending = ref(null)
  const substituting = ref(null)

  const reasonOpen = computed({
    get: () => pending.value !== null,
    set: (open) => {
      if (!open) pending.value = null
    },
  })
  const reasonDialog = computed(() => {
    if (!pending.value) return null
    const { key, member } = pending.value
    return {
      title: t(`award.roster.dialogs.${key}.title`),
      message: t(`award.roster.dialogs.${key}.message`, { name: member.user.name }),
      submitLabel: t(`award.roster.actions.${key}`),
      optional: key !== 'disable' && !auth.isSuperAdmin,
    }
  })

  function actionsFor(member) {
    return actionKeys(member).map((key) => ({
      key,
      label: t(`award.roster.actions.${key}`),
      icon: {
        disable: 'user-slash',
        remove: 'user-minus',
        substitute: 'users-two',
        exclude: 'user-x',
      }[key],
      ...(key === 'substitute' ? {} : { variant: 'danger' }),
    }))
  }

  const SENDERS = {
    disable: (member, payload) => committeeService.disable(cycleId.value, member.id, payload),
    remove: (member, payload) => committeeService.remove(cycleId.value, member.id, payload),
    exclude: (member, payload) => committeeService.exclude(cycleId.value, member.id, payload),
  }

  async function runWithReason(reason) {
    const { key, member } = pending.value
    const result = await SENDERS[key](member, reason ? { reason } : {})
    const recomputed = result?.meta?.recomputedRequestIds?.length ?? 0
    await roster.refresh(`award.roster.toasts.${key}`, {
      name: member.user.name,
      count: recomputed,
    })
    return result
  }

  async function start(key, member) {
    if (key === 'substitute') {
      substituting.value = member
      return
    }
    if (!REASON_DIALOGS.includes(key)) return
    if (key === 'remove' && !auth.isSuperAdmin) {
      const accepted = await confirm({
        title: t('award.roster.dialogs.remove.title'),
        message: t('award.roster.dialogs.remove.message', { name: member.user.name }),
        confirmLabel: t('award.roster.actions.remove'),
        variant: 'danger',
      })
      if (!accepted) return
      try {
        await SENDERS.remove(member, {})
        await roster.refresh('award.roster.toasts.remove', { name: member.user.name })
      } catch (error) {
        toast.error(error?.normalized?.message || t('errors.unexpected'))
      }
      return
    }
    pending.value = { key, member }
  }

  return { actionsFor, start, reasonDialog, reasonOpen, runWithReason, substituting }
}
