import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'

const CYCLE_ID = /^[1-9]\d*$/

function queryCycleId(route) {
  const raw = route.query.cycle
  return typeof raw === 'string' && CYCLE_ID.test(raw) ? Number(raw) : null
}

// Several award cycles may be open at once (operator, 2026-11-22): the cycle named in the link wins, the only open
// cycle is chosen by default, and otherwise the caller's fallback (the newest open cycle) is chosen.
export function useAwardCycleChoice(choices, fallbackId) {
  const route = useRoute()
  const chosenId = ref(queryCycleId(route))

  const showChooser = computed(() => choices.value.length > 1)
  const selectedId = computed(() => {
    const ids = choices.value.map((choice) => choice.id)
    if (ids.includes(chosenId.value)) return chosenId.value
    if (ids.length === 1) return ids[0]
    return fallbackId.value ?? ids[0] ?? null
  })
  const selected = computed(
    () => choices.value.find((choice) => choice.id === selectedId.value) ?? null,
  )

  function choose(cycleId) {
    chosenId.value = cycleId
  }

  return { showChooser, selectedId, selected, choose }
}
