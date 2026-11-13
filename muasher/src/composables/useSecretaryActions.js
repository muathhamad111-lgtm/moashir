import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { secretaryService } from '@/services/secretaryService'
import { useToast } from '@/composables/useToast'
import { useSuperAdminReason } from '@/composables/useSuperAdminReason'

export function useSecretaryActions({ cycleId, assessmentId, request }) {
  const { t } = useI18n()
  const toast = useToast()
  const reasons = useSuperAdminReason()
  const dialog = ref(null)
  const recomputing = ref(false)

  const dialogOpen = computed({
    get: () => dialog.value !== null,
    set: (open) => {
      if (!open) dialog.value = null
    },
  })

  const SENDERS = {
    approve: (payload) => secretaryService.approve(cycleId.value, assessmentId.value, payload),
    returnToCommittee: (payload) =>
      secretaryService.returnToCommittee(cycleId.value, assessmentId.value, payload),
    openDiscussion: (payload) =>
      secretaryService.openDiscussion(cycleId.value, assessmentId.value, payload),
    approveSubstitution: (payload, target) =>
      secretaryService.approveSubstitution(cycleId.value, assessmentId.value, target.id, payload),
    closeDiscussion: (payload, target) =>
      secretaryService.closeDiscussion(cycleId.value, assessmentId.value, target.id, payload),
  }

  function open(kind, target = null) {
    dialog.value = { kind, target }
  }

  async function send(payload) {
    const { kind, target } = dialog.value
    const { data } = await SENDERS[kind](payload, target)
    if (['openDiscussion', 'closeDiscussion'].includes(kind)) {
      await request.load({ silent: true })
      toast.success(t(`award.secretary.toasts.${kind}`))
    } else request.apply(data, `award.secretary.toasts.${kind}`)
    return data
  }

  async function recompute() {
    if (recomputing.value) return
    recomputing.value = true
    try {
      const extra = await reasons.reasonExtra()
      if (extra === null) return
      const { data, meta } = await secretaryService.recomputeAverage(
        cycleId.value,
        assessmentId.value,
        extra,
      )
      request.apply(
        data,
        meta?.recomputed ? 'award.secretary.toasts.recomputed' : 'award.secretary.toasts.unchanged',
      )
    } catch (error) {
      toast.error(error?.normalized?.message || t('errors.unexpected'))
    } finally {
      recomputing.value = false
    }
  }

  return { dialog, dialogOpen, open, send, recompute, recomputing, reasons }
}
