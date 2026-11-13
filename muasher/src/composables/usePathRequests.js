import { onMounted, ref, shallowRef } from 'vue'
import { pathsService } from '@/services/pathsService'
import { PATH_REQUESTS_PER_PAGE } from '@/constants/entityPaths'

export function usePathRequests(path, { auto = true } = {}) {
  const rows = shallowRef([])
  const page = ref(1)
  const lastPage = ref(1)
  const total = ref(0)
  const status = ref('loading')

  async function load(target = page.value) {
    status.value = 'loading'
    try {
      const { data, meta } = await pathsService.listRequests(path, {
        page: target,
        perPage: PATH_REQUESTS_PER_PAGE,
      })
      rows.value = data
      page.value = meta?.pagination?.page ?? target
      lastPage.value = meta?.pagination?.lastPage ?? 1
      total.value = meta?.pagination?.total ?? data.length
      status.value = 'success'
    } catch {
      status.value = 'error'
    }
  }

  if (auto) onMounted(() => load(1))

  return { rows, page, lastPage, total, status, load }
}
