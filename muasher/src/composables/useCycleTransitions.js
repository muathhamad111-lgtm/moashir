import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { awardCycleService } from '@/services/awardCycleService'
import { useToast } from '@/composables/useToast'
import { useConfirm } from '@/composables/useConfirm'
import { AWARD_CODES } from '@/constants/advanced'

const TRANSITIONS = {
  open: { call: 'open', variant: 'primary' },
  closeSubmissions: { call: 'closeSubmissions', variant: 'danger' },
  close: { call: 'close', variant: 'danger' },
  startCommittee: { call: 'startCommittee', variant: 'primary' },
}

export function useCycleTransitions({ editor, reasonExtra }) {
  const { t } = useI18n()
  const toast = useToast()
  const { confirm } = useConfirm()
  const busy = ref('')
  const blocked = ref(null)

  function handle(error) {
    const normalized = error?.normalized ?? {}
    if (normalized.code === AWARD_CODES.REQUESTS_PENDING)
      blocked.value = { code: normalized.code, requests: normalized.meta?.openRequests ?? [] }
    else if (normalized.code === AWARD_CODES.ANOTHER_OPEN)
      blocked.value = { code: normalized.code, cycleId: normalized.meta?.awardCycleId ?? null }
    else blocked.value = null
    toast.error(normalized.message || t('errors.unexpected'))
  }

  async function run(action, extraPayload = {}) {
    if (busy.value) return false
    busy.value = action
    try {
      const extra = await reasonExtra()
      if (extra === null) return false
      const { data } = await awardCycleService[TRANSITIONS[action]?.call ?? action](
        editor.cycle.value.id,
        { ...extraPayload, ...extra },
      )
      editor.apply(data)
      blocked.value = null
      toast.success(t(`cycles.actions.${action}.done`))
      return true
    } catch (error) {
      handle(error)
      return false
    } finally {
      busy.value = ''
    }
  }

  async function confirmAndRun(action) {
    const accepted = await confirm({
      title: t(`cycles.actions.${action}.title`),
      message: t(`cycles.actions.${action}.message`),
      variant: TRANSITIONS[action].variant,
      confirmLabel: t(`cycles.actions.${action}.confirm`),
    })
    return accepted ? run(action) : false
  }

  async function reopen(reason) {
    busy.value = 'reopen'
    try {
      const { data } = await awardCycleService.reopen(editor.cycle.value.id, { reason })
      editor.apply(data)
      blocked.value = null
      toast.success(t('cycles.actions.reopen.done'))
      return data
    } catch (error) {
      if (error?.normalized?.code === AWARD_CODES.ANOTHER_OPEN)
        blocked.value = {
          code: AWARD_CODES.ANOTHER_OPEN,
          cycleId: error.normalized.meta?.awardCycleId ?? null,
        }
      throw error
    } finally {
      busy.value = ''
    }
  }

  return { busy, blocked, confirmAndRun, reopen }
}
