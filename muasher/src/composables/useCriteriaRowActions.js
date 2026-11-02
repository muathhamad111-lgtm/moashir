import { useI18n } from 'vue-i18n'

export function useCriteriaRowActions(access) {
  const { t } = useI18n()

  function editActions(row) {
    return [
      { key: 'edit', label: t('criteria.actions.edit'), icon: 'fa-solid fa-pen' },
      {
        key: 'toggle',
        label: row.active ? t('criteria.actions.deactivate') : t('criteria.actions.activate'),
        icon: row.active ? 'fa-solid fa-toggle-off' : 'fa-solid fa-toggle-on',
      },
      {
        key: 'delete',
        label: t('criteria.actions.delete'),
        icon: 'fa-solid fa-trash-can',
        variant: 'danger',
      },
    ]
  }

  function actionsFor(row, open = null) {
    const actions = open ? [{ key: 'open', label: t(open.label), icon: open.icon }] : []
    if (access.canEditTree.value) actions.push(...editActions(row))
    if (access.canCorrectText.value) {
      actions.push({
        key: 'correct',
        label: t('criteria.actions.correctText'),
        icon: 'fa-solid fa-spell-check',
      })
    }
    return actions
  }

  return { actionsFor }
}
