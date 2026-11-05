import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { boolean as yupBoolean, object as yupObject } from 'yup'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { entityLifecycleService } from '@/services/entityLifecycleService'
import { reasonText } from '@/utils/entitySchemas'

export const STALE_OPEN_REQUESTS = 'entity.open_requests_confirmation_required'

const EMPTY_VALUES = { reason: '', confirmed: false }

export function useEntityDeletion({ entity, onDeleted }) {
  const { t } = useI18n()
  const impact = ref(null)
  const impactStatus = ref('idle')
  const stale = ref(false)
  const openRequests = computed(() => impact.value?.openRequests ?? [])

  async function loadImpact() {
    impactStatus.value = 'loading'
    try {
      const { data } = await entityLifecycleService.deletionImpact(entity.value.id)
      impact.value = data
      impactStatus.value = 'success'
    } catch {
      impactStatus.value = 'error'
    }
  }

  const schema = computed(() =>
    yupObject({
      reason: reasonText(t),
      confirmed: openRequests.value.length
        ? yupBoolean().oneOf([true], t('entities.delete.confirmRequired'))
        : yupBoolean(),
    }),
  )

  function showCurrentList(current) {
    impact.value = { ...impact.value, openRequests: current, openRequestsCount: current.length }
    stale.value = true
    form.setFieldValue('confirmed', false)
  }

  async function submitDeletion({ reason }) {
    const acknowledgedRequestNumbers = openRequests.value.map((request) => request.number)
    try {
      await entityLifecycleService.softDelete(entity.value.id, {
        reason: reason.trim(),
        acknowledgedRequestNumbers,
      })
      onDeleted()
    } catch (error) {
      if (error?.normalized?.code !== STALE_OPEN_REQUESTS) throw error
      showCurrentList(error.normalized.meta?.openRequests ?? [])
    }
  }

  const form = useValidatedForm({
    schema,
    initialValues: { ...EMPTY_VALUES },
    action: 'entities.softDelete.submit',
    submit: submitDeletion,
  })

  function start() {
    stale.value = false
    impact.value = null
    form.resetForm({ values: { ...EMPTY_VALUES } })
    return loadImpact()
  }

  return { form, impactStatus, openRequests, stale, start, loadImpact }
}
