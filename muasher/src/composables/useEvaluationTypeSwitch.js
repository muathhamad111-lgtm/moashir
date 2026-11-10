import { useI18n } from 'vue-i18n'
import { useConfirm } from '@/composables/useConfirm'
import { toast } from '@/composables/useToast'
import { criteriaService } from '@/services/criteriaService'
import { CRITERIA_ERROR_CODES, EVALUATION_TYPES } from '@/constants/criteria'

export function useEvaluationTypeSwitch() {
  const { t } = useI18n()
  const { confirm } = useConfirm()

  function warningFor(nextType, pointCount) {
    if (nextType === EVALUATION_TYPES.GUIDING_POINTS) {
      return {
        message: t('criteria.evaluationType.toPointsMessage'),
        confirmLabel: t('criteria.evaluationType.switchConfirm'),
        variant: 'confirm',
      }
    }
    if (!pointCount) {
      return {
        confirmLabel: t('criteria.evaluationType.switchConfirm'),
        variant: 'confirm',
      }
    }
    return {
      message: t('criteria.evaluationType.toDirectMessage', { count: pointCount }, pointCount),
      confirmLabel: t('criteria.evaluationType.switchConfirmDelete'),
      variant: 'danger',
    }
  }

  function confirmSwitch(nextType, pointCount) {
    return confirm({
      title: t('criteria.evaluationType.switchTitle'),
      ...warningFor(nextType, pointCount),
    })
  }

  async function sendSwitch(sub, nextType, confirmDeletePoints) {
    try {
      return await criteriaService.switchEvaluationType(sub.id, nextType, { confirmDeletePoints })
    } catch (error) {
      if (error?.normalized?.code !== CRITERIA_ERROR_CODES.POINTS_CONFIRM_REQUIRED) throw error
      const serverCount = error.normalized.meta?.impact?.pointCount ?? 0
      if (!(await confirmSwitch(nextType, serverCount))) return null
      return criteriaService.switchEvaluationType(sub.id, nextType, { confirmDeletePoints: true })
    }
  }

  async function switchType(sub, nextType) {
    const pointCount = sub.pointCount ?? 0
    if (!(await confirmSwitch(nextType, pointCount))) return null
    const deletesPoints = nextType === EVALUATION_TYPES.DIRECT && pointCount > 0
    return sendSwitch(sub, nextType, deletesPoints)
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
