import { ref } from 'vue'
import { useCriteriaNodeActions } from '@/composables/useCriteriaNodeActions'
import { useCriteriaWorkspaceCreation } from '@/composables/useCriteriaWorkspaceCreation'
import { useCriteria } from '@/composables/useCriteria'
import { nodeLabel } from '@/utils/criteriaTree'
import { CRITERIA_NODE_TYPES as NODE } from '@/constants/criteria'

const TREE_NODES = [NODE.MAIN, NODE.SUB, NODE.POINT]

export function useCriteriaWorkspaceEditing(workspace) {
  const { localized } = useCriteria()
  const parentId = ref(null)
  const correction = ref({ open: false, target: null })
  const reload = () => workspace.reload()
  const actions = Object.fromEntries(
    TREE_NODES.map((type) => [type, useCriteriaNodeActions(type, reload, () => parentId.value)]),
  )
  const selectedActions = () => actions[workspace.selected.value.type]

  async function removeSelected() {
    const item = workspace.selected.value
    if (await selectedActions().remove(item.node, localized(nodeLabel(item)))) {
      workspace.select(item.parentKey)
    }
  }

  function reorder({ type, parentKey, ids }) {
    parentId.value = parentKey ? workspace.index.value.get(parentKey).node.id : null
    return actions[type].reorder(ids)
  }

  function openCorrection(target) {
    correction.value = { open: true, target }
  }

  return {
    ...useCriteriaWorkspaceCreation(workspace, actions, parentId),
    correction,
    saveSelected: ({ id, payload }) => selectedActions().save(id, payload),
    toggleSelected: () => selectedActions().toggle(workspace.selected.value.node),
    removeSelected,
    reorder,
    correctSelected: () => openCorrection({ ...workspace.selected.value }),
    correctVersionName: () => openCorrection(null),
    closeCorrection: () => (correction.value = { ...correction.value, open: false }),
    corrected: actions[NODE.MAIN].corrected,
  }
}
