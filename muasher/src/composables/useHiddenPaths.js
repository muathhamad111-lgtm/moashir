import { computed, onMounted } from 'vue'
import { storeToRefs } from 'pinia'
import { useEntityPathsStore } from '@/stores/entityPaths'
import { PATH_ORDER } from '@/constants/entityPaths'
import { isHidden } from '@/utils/pathStates'

export function useHiddenPaths() {
  const store = useEntityPathsStore()
  const { paths } = storeToRefs(store)

  onMounted(() => store.ensureLoaded())

  return computed(
    () => new Set(paths.value ? PATH_ORDER.filter((key) => isHidden(paths.value[key])) : []),
  )
}
