import { ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { toast } from '@/composables/useToast'
import { weightChanges } from '@/utils/criteriaOrder'

export function useWeightDistribution({ saveParentMode, saveChildWeight, reload }) {
  const { t } = useI18n()
  const saving = ref(false)

  async function guarded(action, success) {
    if (saving.value) return false
    saving.value = true
    try {
      await action()
      toast.success(t(success))
      return true
    } catch (error) {
      toast.error(error?.normalized?.message || t('criteria.toasts.saveFailed'))
      return false
    } finally {
      saving.value = false
      await reload()
    }
  }

  function changeMode(mode) {
    return guarded(() => saveParentMode(mode === 'equal'), 'criteria.weights.modeSaved')
  }

  function saveWeights(edited, originals) {
    const changes = weightChanges(edited, originals)
    return guarded(async () => {
      for (const change of changes) await saveChildWeight(change.id, change.weight)
    }, 'criteria.toasts.weightsSaved')
  }

  return { saving, changeMode, saveWeights }
}
