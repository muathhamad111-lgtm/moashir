import { computed, ref, watch } from 'vue'
import { useCriteriaVersions } from '@/composables/useCriteriaVersions'
import { useCriteriaTreeState } from '@/composables/useCriteriaTreeState'
import { useCriteriaSelection } from '@/composables/useCriteriaSelection'
import { indexTree } from '@/utils/criteriaTree'

export function useCriteriaWorkspace() {
  const versions = useCriteriaVersions()
  const index = ref(new Map())
  const selection = useCriteriaSelection(index)
  const version = computed(
    () =>
      versions.findVersion(selection.requestedVersionId.value) ??
      versions.draft.value ??
      versions.published.value,
  )
  const treeState = useCriteriaTreeState(version)
  const status = computed(() =>
    versions.status.value === 'success' ? treeState.status.value : versions.status.value,
  )

  watch(treeState.tree, (tree) => (index.value = indexTree(tree)))
  watch(() => version.value?.id, treeState.load)

  async function reload() {
    const shownId = version.value?.id
    await versions.load()
    if (version.value?.id === shownId) await treeState.load()
  }

  return {
    versions,
    version,
    index,
    status,
    tree: treeState.tree,
    errorMessage: treeState.errorMessage,
    problems: treeState.problems,
    selected: selection.selected,
    selectedPath: selection.selectedPath,
    board: selection.board,
    load: versions.load,
    reload,
    select: selection.select,
    showMain: selection.showMain,
    showVersion: selection.showVersion,
  }
}
