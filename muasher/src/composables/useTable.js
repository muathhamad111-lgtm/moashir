import { onMounted, ref, watch } from 'vue'

export function useTable({ fetcher, filters = null, pagination = null, auto = true }) {
  const items = ref([])
  const status = ref('loading')
  const errorMessage = ref('')

  function buildParams() {
    return { ...filters?.toParams(), ...pagination?.toParams() }
  }

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const envelope = await fetcher(buildParams())
      items.value = envelope.data ?? []
      pagination?.applyMeta(envelope.meta)
      status.value = items.value.length ? 'success' : 'empty'
    } catch (error) {
      errorMessage.value = error?.normalized?.message ?? ''
      status.value = 'error'
    }
  }

  if (auto) {
    onMounted(load)
    watch(buildParams, () => load())
  }

  return { items, status, errorMessage, load }
}
