import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { privacyRequestsService } from '@/services/privacyRequestsService'
import { localizedText } from '@/utils/localizedText'

const PICKER_SIZE = 10
const MIN_TERM = 2

export function useEntityPicker() {
  const { t, locale } = useI18n()
  const options = ref([])
  const loading = ref(false)

  function toOption(entity) {
    return {
      value: String(entity.id),
      label: `${localizedText(entity.name, locale.value)} (${entity.reference})`,
      badge: entity.isDeleted === true ? t('entities.picker.deleted') : null,
    }
  }

  async function search(term = '') {
    if (term && term.trim().length < MIN_TERM) return
    loading.value = true
    try {
      const { data } = await privacyRequestsService.lookupEntities({ search: term.trim() || undefined, limit: PICKER_SIZE })
      options.value = (data ?? []).map(toOption)
    } catch {
      options.value = []
    } finally {
      loading.value = false
    }
  }

  return { options, loading, search }
}
