import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { usersService } from '@/services/usersService'

// The users list leaves out the personal fields (phone, gender, national id); the details dialog
// and the edit form read the full record of the user instead of the list row.
export function useUserRecord() {
  const { t } = useI18n()
  const toast = useToast()

  async function recordOf(row) {
    try {
      const { data } = await usersService.get(row.id)
      return data
    } catch (error) {
      toast.error(error?.normalized?.message || t('errors.unexpected'))
      return null
    }
  }

  return { recordOf }
}
