import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { usePagination } from '@/composables/usePagination'

const PER_PAGE = 10
const FORBIDDEN_STATUS = 403

export function useProfileChanges(loader) {
  const { t } = useI18n()
  const paginator = usePagination({ perPage: PER_PAGE })
  const changes = ref([])
  const status = ref('loading')
  const errorMessage = ref('')

  async function load() {
    status.value = 'loading'
    errorMessage.value = ''
    try {
      const envelope = await loader(paginator.toParams())
      changes.value = envelope.data ?? []
      paginator.applyMeta(envelope.meta)
      status.value = changes.value.length ? 'success' : 'empty'
    } catch (error) {
      errorMessage.value = error?.normalized?.message || t('common.error')
      status.value = error?.response?.status === FORBIDDEN_STATUS ? 'forbidden' : 'error'
    }
  }

  function goTo(page) {
    paginator.setPage(page)
    return load()
  }

  return { changes, status, errorMessage, pagination: paginator.pagination, load, goTo }
}
