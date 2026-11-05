import { computed, ref } from 'vue'
import { storeToRefs } from 'pinia'
import { useActionCenterStore } from '@/stores/actionCenter'
import { attentionViews } from '@/utils/attentionItems'
import { ATTENTION_STRIP_DESKTOP_LIMIT } from '@/constants/portal'

export function useAttentionStrip() {
  const store = useActionCenterStore()
  const { items, status } = storeToRefs(store)
  const expanded = ref(false)

  const views = computed(() => attentionViews(items.value))
  const visible = computed(() =>
    expanded.value ? views.value : views.value.slice(0, ATTENTION_STRIP_DESKTOP_LIMIT),
  )
  const hasMore = computed(() => views.value.length > ATTENTION_STRIP_DESKTOP_LIMIT)
  const first = computed(() => views.value[0] ?? null)
  const others = computed(() => views.value.slice(1))

  function toggle() {
    expanded.value = !expanded.value
  }

  return {
    views,
    visible,
    hasMore,
    first,
    others,
    expanded,
    status,
    toggle,
    load: () => store.ensureLoaded(),
    reload: () => store.load(),
  }
}
