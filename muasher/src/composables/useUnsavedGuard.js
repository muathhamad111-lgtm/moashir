import { onBeforeUnmount, onMounted } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import { useI18n } from 'vue-i18n'
import { useConfirm } from '@/composables/useConfirm'

export function useUnsavedGuard(isDirty, { onDiscard = () => {} } = {}) {
  const { t } = useI18n()
  const { confirm } = useConfirm()

  async function allowLeaving() {
    if (!isDirty.value) return true
    const discard = await confirm({
      title: t('portal.unsaved.title'),
      message: t('portal.unsaved.message'),
      confirmLabel: t('portal.unsaved.discard'),
      cancelLabel: t('portal.unsaved.stay'),
      variant: 'danger',
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
  onMounted(() => window.addEventListener('beforeunload', onBeforeUnload))
  onBeforeUnmount(() => window.removeEventListener('beforeunload', onBeforeUnload))

  return { allowLeaving }
}
