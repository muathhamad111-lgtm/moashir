import { ref } from 'vue'
import { nodeKey } from '@/utils/criteriaTree'
import { CRITERIA_NODE_TYPES as NODE } from '@/constants/criteria'

const CHILD_TYPE = { [NODE.MAIN]: NODE.SUB, [NODE.SUB]: NODE.POINT }

export function useCriteriaWorkspaceCreation(workspace, actions, parentId) {
  const creating = ref(null)

  function startCreate(parentKey) {
    const parent = parentKey ? workspace.index.value.get(parentKey) : null
    parentId.value = parent?.node.id ?? null
    creating.value = { type: parent ? CHILD_TYPE[parent.type] : NODE.MAIN, parent }
  }

  function cancelCreate() {
    creating.value = null
  }

  async function create({ payload }) {
    const { type } = creating.value
    const created = await actions[type].save(null, payload)
    const createdId = (created?.point ?? created)?.id
    creating.value = null
    if (createdId) workspace.select(nodeKey(type, createdId))
  }

  return { creating, startCreate, cancelCreate, create }
}
