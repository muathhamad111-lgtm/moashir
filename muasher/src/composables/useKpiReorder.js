import { computed, ref } from 'vue'
import { kpisAdminService } from '@/services/kpisAdminService'
import { KPI_DEFAULT_SORT, KPI_PAGE_SIZE } from '@/constants/kpis'
import { sameOrder } from '@/utils/reorder'

async function pageOfKpis(page) {
  const envelope = await kpisAdminService.list({ sort: KPI_DEFAULT_SORT, page, perPage: KPI_PAGE_SIZE.all })
  return { items: envelope.data ?? [], lastPage: envelope.meta?.pagination?.lastPage ?? 1 }
}

async function allLiveKpis() {
  const first = await pageOfKpis(1)
  const collected = [...first.items]
  for (let page = 2; page <= first.lastPage; page += 1) collected.push(...(await pageOfKpis(page)).items)
  return collected
}

export function useKpiReorder() {
  const entries = ref([])
  const original = ref([])
  const status = ref('loading')
  const saving = ref(false)

  const changed = computed(
    () =>
      !sameOrder(
        entries.value.map((entry) => entry.id),
        original.value,
      ),
  )

  async function load() {
    status.value = 'loading'
    try {
      entries.value = await allLiveKpis()
      original.value = entries.value.map((entry) => entry.id)
      status.value = entries.value.length ? 'success' : 'empty'
    } catch {
      status.value = 'error'
    }
  }

  function arrange(orderedIds) {
    const byId = new Map(entries.value.map((entry) => [entry.id, entry]))
    entries.value = orderedIds.map((id) => byId.get(id))
  }

  async function save() {
    saving.value = true
    try {
      const { data } = await kpisAdminService.reorder(entries.value.map((entry) => entry.id))
      original.value = (data ?? []).map((entry) => entry.id)
      return data
    } finally {
      saving.value = false
    }
  }

  return { entries, status, saving, changed, load, arrange, save }
}
