import { useI18n } from 'vue-i18n'
import { toast } from '@/composables/useToast'

export function useCriteriaAction(reload) {
  const { t } = useI18n()

  async function run(
    action,
    { success = '', successParams = {}, failure = 'criteria.toasts.saveFailed' } = {},
  ) {
    try {
      const result = await action()
      if (success) toast.success(t(success, successParams))
      await reload()
      return result ?? true
    } catch (error) {
      toast.error(error?.normalized?.message || t(failure))
      return null
    }
  }

  return { run }
}
