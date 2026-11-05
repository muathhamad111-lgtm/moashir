import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { entitiesService } from '@/services/entitiesService'
import { staffProfileSchema } from '@/utils/entitySchemas'
import { profileFormValues, profilePayload, unifiedNumberPayload } from '@/utils/entityPayload'

export function useEntityEditOnBehalf({ entity, onSaved }) {
  const { t } = useI18n()
  const unifiedNumberRequired = computed(() => Boolean(entity.value?.unifiedNumber))

  const form = useValidatedForm({
    schema: computed(() =>
      staffProfileSchema(t, { unifiedNumberRequired: unifiedNumberRequired.value }),
    ),
    initialValues: { ...profileFormValues(entity.value), reason: '' },
    action: 'entities.updateOnBehalf.submit',
    submit: async (values) => {
      const { data } = await entitiesService.updateOnBehalf(entity.value.id, {
        ...profilePayload(values),
        ...unifiedNumberPayload(values.unifiedNumber),
        reason: values.reason.trim(),
      })
      onSaved(data)
    },
  })

  function start() {
    form.resetForm({ values: { ...profileFormValues(entity.value), reason: '' } })
  }

  return { form, start, unifiedNumberRequired }
}
