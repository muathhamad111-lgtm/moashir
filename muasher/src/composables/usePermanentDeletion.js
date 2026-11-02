import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { entityLifecycleService } from '@/services/entityLifecycleService'
import { permanentDeletionSchema } from '@/utils/entitySchemas'

const EMPTY_VALUES = { disclaimerAcknowledged: false, confirmation: '', reason: '' }

export function usePermanentDeletion({ entity, onErased }) {
  const { t } = useI18n()

  const form = useValidatedForm({
    schema: computed(() => permanentDeletionSchema(t, entity.value.reference)),
    initialValues: { ...EMPTY_VALUES },
    action: 'entities.permanentlyDelete.submit',
    submit: async (values) => {
      const { data } = await entityLifecycleService.permanentlyDelete(entity.value.id, {
        reason: values.reason.trim(),
        confirmation: values.confirmation.trim(),
        disclaimerAcknowledged: values.disclaimerAcknowledged === true,
      })
      onErased(data)
    },
  })

  function start() {
    form.resetForm({ values: { ...EMPTY_VALUES } })
  }

  return { form, start }
}
