import { ref } from 'vue'
import { kpiCatalogService } from '@/services/kpiCatalogService'

export function useKpiCatalogAdmin(kind) {
  const service = kpiCatalogService[kind]
  const entries = ref([])
  const status = ref('loading')

  async function load() {
    status.value = 'loading'
    try {
      const { data } = await service.list()
      entries.value = data ?? []
      status.value = entries.value.length ? 'success' : 'empty'
    } catch {
      status.value = 'error'
    }
  }

  async function save(entry, payload) {
    const { data } = entry ? await service.update(entry.id, payload) : await service.create(payload)
    await load()
    return data
  }

  async function remove(entry) {
    await service.remove(entry.id)
    await load()
  }

  return { entries, status, load, save, remove }
}
