import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { kpisAdminService } from '@/services/kpisAdminService'
import { useConfirm } from './useConfirm'
import { useToast } from './useToast'

export function useKpiRowActions({ reload, localized }) {
  const { t } = useI18n()
  const { confirm } = useConfirm()
  const toast = useToast()
  const pendingId = ref(null)

  async function run(kpi, request, successKey) {
    pendingId.value = kpi.id
    try {
      await request()
      toast.success(t(successKey))
      await reload()
    } catch (error) {
      toast.error(error?.normalized?.message || t('kpis.admin.toasts.failed'))
    } finally {
      pendingId.value = null
    }
  }

  async function togglePublished(kpi) {
    const publishing = !kpi.published
    const accepted = await confirm({
      title: t(
        publishing ? 'kpis.admin.confirm.publishTitle' : 'kpis.admin.confirm.unpublishTitle',
      ),
      message: t(
        publishing ? 'kpis.admin.confirm.publishMessage' : 'kpis.admin.confirm.unpublishMessage',
        { name: localized(kpi.name) },
      ),
      confirmLabel: t(publishing ? 'kpis.admin.actions.publish' : 'kpis.admin.actions.unpublish'),
      variant: publishing ? 'primary' : 'danger',
    })
    if (!accepted) return
    await run(
      kpi,
      () => kpisAdminService.setPublished(kpi.id, publishing),
      publishing ? 'kpis.admin.toasts.published' : 'kpis.admin.toasts.unpublished',
    )
  }

  function toggleFeatured(kpi) {
    const featuring = !kpi.showOnHomepage
    return run(
      kpi,
      () => kpisAdminService.update(kpi.id, { showOnHomepage: featuring }),
      featuring ? 'kpis.admin.toasts.featured' : 'kpis.admin.toasts.unfeatured',
    )
  }

  async function archive(kpi) {
    const accepted = await confirm({
      title: t('kpis.admin.confirm.archiveTitle'),
      message: t('kpis.admin.confirm.archiveMessage', { name: localized(kpi.name) }),
      confirmLabel: t('kpis.admin.actions.archive'),
      variant: 'danger',
    })
    if (accepted)
      await run(kpi, () => kpisAdminService.archive(kpi.id), 'kpis.admin.toasts.archived')
  }

  function move(kpi, direction) {
    return run(kpi, () => kpisAdminService.move(kpi.id, direction), 'kpis.admin.toasts.reordered')
  }

  return { pendingId, togglePublished, toggleFeatured, archive, move }
}
