import { computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { entitiesService } from '@/services/entitiesService'
import { isAuthorizedPersonChanged, staffProfileSchema } from '@/utils/entitySchemas'
import { profileFormValues, profilePayload, unifiedNumberPayload } from '@/utils/entityPayload'

function initialValues(entity) {
  return { ...profileFormValues(entity), authorizationLetter: null, reason: '' }
}

function letterPayload(file) {
  return file ? { authorizationLetter: file } : {}
}

export function useEntityEditOnBehalf({ entity, onSaved }) {
  const { t } = useI18n()
  const unifiedNumberRequired = computed(() => Boolean(entity.value?.unifiedNumber))
  const currentPersonName = computed(() => entity.value?.authorizedPersonName ?? '')

  const form = useValidatedForm({
    schema: computed(() =>
      staffProfileSchema(t, {
        unifiedNumberRequired: unifiedNumberRequired.value,
        currentPersonName: currentPersonName.value,
      }),
    ),
    initialValues: initialValues(entity.value),
    action: 'entities.updateOnBehalf.submit',
    submit: async (values) => {
      const { data } = await entitiesService.updateOnBehalf(entity.value.id, {
        ...profilePayload(values),
        ...unifiedNumberPayload(values.unifiedNumber),
        ...letterPayload(values.authorizationLetter),
        reason: values.reason.trim(),
      })
      onSaved(data)
    },
  })

  // A new authorized person needs a new authorization letter in the same save (operator 2026-11-25).
  const personChanged = computed(() =>
    isAuthorizedPersonChanged(form.values.authorizedPersonName, currentPersonName.value),
  )

  function start() {
    form.resetForm({ values: initialValues(entity.value) })
  }

  return { form, start, unifiedNumberRequired, personChanged }
}
