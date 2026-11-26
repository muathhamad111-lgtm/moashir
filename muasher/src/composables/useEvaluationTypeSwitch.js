import { useI18n } from 'vue-i18n'
import { useConfirm } from '@/composables/useConfirm'
import { toast } from '@/composables/useToast'
import { criteriaService } from '@/services/criteriaService'
import { EVALUATION_TYPES } from '@/constants/criteria'

// Switching keeps the indicators either way: evaluated directly they become descriptive and lose
// their weights; scored on indicators again they share the sub-criterion's weight equally.
export function useEvaluationTypeSwitch() {
  const { t } = useI18n()
  const { confirm } = useConfirm()

  function confirmSwitch(nextType) {
    return confirm({
      title: t('criteria.evaluationType.switchTitle'),
      message:
        nextType === EVALUATION_TYPES.GUIDING_POINTS
          ? t('criteria.evaluationType.toPointsMessage')
          : t('criteria.evaluationType.toDirectMessage'),
      confirmLabel: t('criteria.evaluationType.switchConfirm'),
      variant: 'confirm',
    })
  }

  async function switchType(sub, nextType) {
    if (!(await confirmSwitch(nextType))) return null
    return criteriaService.switchEvaluationType(sub.id, nextType)
  }

  function nextTypeOf(sub) {
    return sub.evaluationType === EVALUATION_TYPES.GUIDING_POINTS
      ? EVALUATION_TYPES.DIRECT
      : EVALUATION_TYPES.GUIDING_POINTS
  }

  async function toggleType(sub, reload) {
    try {
      const result = await switchType(sub, nextTypeOf(sub))
      if (!result) return null
      toast.success(t('criteria.toasts.typeSwitched'))
      await reload()
      return result
    } catch (error) {
      toast.error(error?.normalized?.message || t('criteria.toasts.saveFailed'))
      return null
    }
  }

  return { switchType, toggleType }
}
