import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useEntityStore } from '@/stores/entity'
import { useConfirm } from '@/composables/useConfirm'
import { useToast } from '@/composables/useToast'
import { pendingSectionsOf } from '@/utils/entityProfile/proposedValues'

/** The entity's pending profile change request: what it proposes and how to withdraw it. */
export function usePendingProfileChange() {
  const { t } = useI18n()
  const toast = useToast()
  const { confirm } = useConfirm()
  const store = useEntityStore()
  const busy = ref(false)

  const hasPending = computed(() => store.hasPendingChange)
  const details = computed(() => store.pendingChange)
  const pendingSections = computed(() => pendingSectionsOf(store.pendingChange))

  async function cancel() {
    if (busy.value) return
    const accepted = await confirm({
      title: t('portal.profile.pendingChange.cancelTitle'),
      message: t('portal.profile.pendingChange.cancelMessage'),
      variant: 'danger',
      confirmLabel: t('portal.profile.pendingChange.cancel'),
    })
    if (!accepted) return
    busy.value = true
    try {
      await store.cancelPendingChange()
      toast.success(t('portal.profile.pendingChange.cancelled'))
    } catch (error) {
      toast.error(error?.normalized?.message || t('errors.unexpected'))
      await store.loadProfile({ force: true }).catch(() => null)
    } finally {
      busy.value = false
    }
  }

  return { hasPending, details, pendingSections, busy, cancel }
}
