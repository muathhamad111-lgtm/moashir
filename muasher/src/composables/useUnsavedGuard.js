import { onBeforeUnmount, onMounted } from 'vue'
import { onBeforeRouteLeave, onBeforeRouteUpdate } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useConfirm } from '@/composables/useConfirm'

// `leavesOnUpdate(to)` also guards in-route navigation (a query change) that would drop the edits.
export function useUnsavedGuard(
  isDirty,
  {
    onDiscard = () => {},
    message = null,
    title = null,
    confirmLabel = null,
    leavesOnUpdate = null,
  } = {},
) {
  const { t } = useI18n()
  const { confirm } = useConfirm()

  async function allowLeaving() {
    if (!isDirty.value) return true
    const discard = await confirm({
      title: title ? title() : t('portal.unsaved.title'),
      message: message ? message() : t('portal.unsaved.message'),
      confirmLabel: confirmLabel ? confirmLabel() : t('portal.unsaved.discard'),
      variant: 'warning',
    })
    if (discard) onDiscard()
    return discard
  }

  function onBeforeUnload(event) {
    if (!isDirty.value) return
    event.preventDefault()
    event.returnValue = ''
  }

  onBeforeRouteLeave(allowLeaving)
  if (leavesOnUpdate) onBeforeRouteUpdate((to) => (leavesOnUpdate(to) ? allowLeaving() : true))
  onMounted(() => window.addEventListener('beforeunload', onBeforeUnload))
  onBeforeUnmount(() => window.removeEventListener('beforeunload', onBeforeUnload))

  return { allowLeaving }
}
