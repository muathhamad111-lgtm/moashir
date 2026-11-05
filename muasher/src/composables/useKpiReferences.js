import { onMounted, ref } from 'vue'
import { kpisService } from '@/services/kpisService'

export function useKpiReferences({ auto = true } = {}) {
  const categories = ref([])
  const types = ref([])

  async function load() {
    const [categoryList, typeList] = await Promise.allSettled([
      kpisService.listCategories(),
      kpisService.listTypes(),
    ])
    categories.value = categoryList.status === 'fulfilled' ? (categoryList.value.data ?? []) : []
    types.value = typeList.status === 'fulfilled' ? (typeList.value.data ?? []) : []
  }

  if (auto) onMounted(load)

  return { categories, types, load }
}
