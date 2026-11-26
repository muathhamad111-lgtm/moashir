import { computed, ref, watch } from 'vue'

export function useListboxNavigation(options) {
  const activeIndex = ref(-1)
  const activeOption = computed(() => options.value[activeIndex.value] ?? null)

  function move(step) {
    const count = options.value.length
    if (!count) {
      activeIndex.value = -1
      return
    }
    activeIndex.value =
      activeIndex.value < 0 && step < 0 ? count - 1 : (activeIndex.value + step + count) % count
  }

  function activate(index) {
    activeIndex.value = index >= 0 && index < options.value.length ? index : -1
  }

  function reset() {
    activeIndex.value = -1
  }

  watch(options, reset)

  return { activeIndex, activeOption, move, activate, reset }
}
