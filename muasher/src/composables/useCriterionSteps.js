import { computed, ref } from 'vue'

export function useCriterionSteps(mains) {
  const index = ref(0)
  const total = computed(() => mains.value.length)
  const position = computed(() => Math.min(index.value, Math.max(total.value - 1, 0)))
  const current = computed(() => mains.value[position.value] ?? null)
  const isFirst = computed(() => position.value === 0)
  const isLast = computed(() => position.value >= total.value - 1)

  function goTo(target) {
    index.value = Math.max(0, Math.min(target, total.value - 1))
  }

  function next() {
    goTo(position.value + 1)
  }

  function previous() {
    goTo(position.value - 1)
  }

  function goToMain(mainId) {
    const target = mains.value.findIndex((main) => main.id === mainId)
    if (target >= 0) goTo(target)
  }

  return { position, total, current, isFirst, isLast, next, previous, goToMain }
}
