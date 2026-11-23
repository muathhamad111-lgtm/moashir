import { computed, ref, shallowRef } from 'vue'
import { awardCycleService } from '@/services/awardCycleService'
import { useAuthStore } from '@/stores/auth'
import { useAwardCycleChoice } from '@/composables/useAwardCycleChoice'

// The public award intro: the running cycle for visitors and staff. A signed-in entity is sent to its award path
// page before it gets here (router/awardEntry.js), where it applies and follows its requests (AJ-04).
export function useAdvancedIntro() {
  const auth = useAuthStore()
  const cycleStatus = ref('loading')
  const cycle = shallowRef(null)
  const openCycles = shallowRef([])
  const isAuthenticated = computed(() => auth.isAuthenticated)
  const choice = useAwardCycleChoice(
    openCycles,
    computed(() => cycle.value?.id ?? null),
  )
  const displayedCycle = computed(() => choice.selected.value ?? cycle.value)

  async function loadOpen() {
    try {
      openCycles.value = (await awardCycleService.listOpen()).data ?? []
    } catch {
      openCycles.value = []
    }
  }

  async function load() {
    cycleStatus.value = 'loading'
    try {
      const [current] = await Promise.all([awardCycleService.getCurrent(), loadOpen()])
      cycle.value = current.data
      cycleStatus.value = cycle.value ? 'ready' : 'empty'
    } catch {
      cycleStatus.value = 'error'
    }
  }

  return {
    cycleStatus,
    cycle,
    openCycles,
    displayedCycle,
    showChooser: choice.showChooser,
    selectedId: choice.selectedId,
    selectCycle: choice.choose,
    isAuthenticated,
    load,
  }
}
