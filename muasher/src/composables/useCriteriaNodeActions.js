import { useI18n } from 'vue-i18n'
import { useConfirm } from '@/composables/useConfirm'
import { useCriteriaAction } from '@/composables/useCriteriaAction'
import { toast } from '@/composables/useToast'
import { criteriaService } from '@/services/criteriaService'
import { movedIds } from '@/utils/criteriaOrder'
import { CRITERIA_NODE_TYPES as NODE } from '@/constants/criteria'

const SERVICES = {
  [NODE.MAIN]: {
    create: (payload) => criteriaService.createMain(payload),
    update: (id, payload) => criteriaService.updateMain(id, payload),
    toggle: (id, active) => criteriaService.toggleMainActive(id, active),
    remove: (id) => criteriaService.removeMain(id),
    reorder: (ids) => criteriaService.reorderMains(ids),
  },
  [NODE.SUB]: {
    create: (payload) => criteriaService.createSub(payload),
    update: (id, payload) => criteriaService.updateSub(id, payload),
    toggle: (id, active) => criteriaService.toggleSubActive(id, active),
    remove: (id) => criteriaService.removeSub(id),
    reorder: (ids, parentId) => criteriaService.reorderSubs(parentId, ids),
  },
  [NODE.POINT]: {
    create: (payload, parentId) => criteriaService.createPoint(parentId, payload),
    update: (id, payload) => criteriaService.updatePoint(id, payload),
    toggle: (id, active) => criteriaService.togglePointActive(id, active),
    remove: (id) => criteriaService.removePoint(id),
    reorder: (ids, parentId) => criteriaService.reorderPoints(parentId, ids),
  },
}

export function useCriteriaNodeActions(kind, reload, parentId = () => null) {
  const { t } = useI18n()
  const { confirm } = useConfirm()
  const { run } = useCriteriaAction(reload)
  const api = SERVICES[kind]

  async function save(id, payload) {
    if (id) await api.update(id, payload)
    else await api.create(payload, parentId())
    toast.success(t(`criteria.toasts.${kind}${id ? 'Updated' : 'Created'}`))
    await reload()
  }

  function toggle(row) {
    return run(() => api.toggle(row.id, !row.active), {
      success: row.active ? 'criteria.toasts.deactivated' : 'criteria.toasts.activated',
      failure: 'criteria.toasts.toggleFailed',
    })
  }

  async function remove(row, name) {
    const accepted = await confirm({
      title: t(`criteria.confirm.${kind}DeleteTitle`),
      message: t(`criteria.confirm.${kind}DeleteMessage`, { name }),
      confirmLabel: t('actions.delete'),
      variant: 'danger',
    })
    if (!accepted) return null
    return run(() => api.remove(row.id), {
      success: `criteria.toasts.${kind}Deleted`,
      failure: 'criteria.toasts.deleteFailed',
    })
  }

  function move(items, index, delta) {
    const ids = movedIds(items, index, delta)
    if (!ids) return null
    return run(() => api.reorder(ids, parentId()), {
      success: 'criteria.toasts.reordered',
      failure: 'criteria.toasts.reorderFailed',
    })
  }

  function corrected(outcome) {
    const count = outcome?.meta?.changeCount ?? 0
    toast.success(
      count
        ? t('criteria.correction.success', { count }, count)
        : t('criteria.correction.noChanges'),
    )
    return reload()
  }

  return { save, toggle, remove, move, corrected }
}
