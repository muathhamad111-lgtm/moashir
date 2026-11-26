import { computed, ref, shallowRef, watch } from 'vue'
import { committeeService } from '@/services/committeeService'
import { CYCLE_STAGE_ACCEPTS } from '@/constants/advanced'

function sameIds(left, right) {
  return JSON.stringify([...left].sort()) === JSON.stringify([...right].sort())
}

function messageOf(error) {
  const normalized = error?.normalized ?? {}
  return Object.values(normalized.fieldErrors ?? {}).flat()[0] ?? normalized.message ?? ''
}

function changesOf(selected, seats) {
  const seated = seats.map((seat) => seat.user.id)
  return {
    added: selected.filter((userId) => !seated.includes(userId)),
    removed: seats.filter((seat) => !selected.includes(seat.user.id)),
  }
}

async function syncCommittee(cycleId, { added, removed }, extra) {
  for (const userId of added) await committeeService.appoint(cycleId, { userId, ...extra })
  for (const seat of removed) await committeeService.remove(cycleId, seat.id, extra)
}

function useCommitteeCandidates(cycle, roster) {
  const candidates = shallowRef([])
  const searching = ref(false)
  const options = computed(() => {
    const known = new Map(candidates.value.map((person) => [person.id, person.name]))
    roster.activeSeats.value.forEach((seat) => known.set(seat.user.id, seat.user.name))
    return [...known.entries()].map(([value, label]) => ({ value, label }))
  })

  async function search(term = '') {
    searching.value = true
    try {
      const params = term ? { search: term } : {}
      candidates.value = (await committeeService.listCandidates(cycle.value.id, params)).data
    } catch {
      candidates.value = []
    } finally {
      searching.value = false
    }
  }

  return { options, searching, search }
}

function useCommitteeLocks(cycle, roster) {
  const stageLocked = computed(
    () => !CYCLE_STAGE_ACCEPTS.UNTIL_COMMITTEE.includes(cycle.value?.status),
  )
  const locked = computed(() => stageLocked.value || !roster.canManage.value)
  return { stageLocked, locked }
}

export function useCycleCommitteeSelection({ cycle, roster }) {
  const selection = ref([])
  const fieldError = ref('')
  const { stageLocked, locked } = useCommitteeLocks(cycle, roster)
  const seatedIds = computed(() => roster.activeSeats.value.map((seat) => seat.user.id))
  const dirty = computed(() => !locked.value && !sameIds(selection.value, seatedIds.value))

  function reset() {
    selection.value = seatedIds.value
    fieldError.value = ''
  }

  function select(ids) {
    selection.value = ids
    fieldError.value = ''
  }

  async function save(extra) {
    if (!dirty.value) return
    try {
      await syncCommittee(
        cycle.value.id,
        changesOf(selection.value, roster.activeSeats.value),
        extra,
      )
    } catch (error) {
      fieldError.value = messageOf(error)
      throw error
    } finally {
      await roster.load()
    }
  }

  watch(
    () => cycle.value?.id,
    async (id) => {
      if (!id) return
      await roster.load()
      reset()
    },
    { immediate: true },
  )

  const state = { selection, fieldError, stageLocked, locked, dirty }
  return { ...state, ...useCommitteeCandidates(cycle, roster), select, reset, save }
}
