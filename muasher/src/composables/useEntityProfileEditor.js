import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { useValidatedForm } from '@/composables/useValidatedForm'
import { useToast } from '@/composables/useToast'
import { useEntityStore } from '@/stores/entity'
import { ownProfileSchema } from '@/utils/entitySchemas'
import { PROFILE_FIELDS, profileChanges, profileFormValues, profilePayload } from '@/utils/entityPayload'

const REVIEWED_FIELDS = [...PROFILE_FIELDS, 'marketingConsent']

export const EDITOR_STEPS = { EDIT: 'edit', REVIEW: 'review' }

export function useEntityProfileEditor({ profile, onSaved }) {
  const { t } = useI18n()
  const toast = useToast()
  const entityStore = useEntityStore()
  const step = ref(EDITOR_STEPS.EDIT)
  const changes = ref([])
  const saveConfirmed = ref(false)
  const initialValues = { ...profileFormValues(profile), marketingConsent: profile.marketingConsent === true }

  function review(values) {
    const found = profileChanges(initialValues, values, REVIEWED_FIELDS)
    if (!found.length) {
      toast.info(t('entities.profile.noChanges'))
      return
    }
    changes.value = found
    step.value = EDITOR_STEPS.REVIEW
  }

  async function save(values) {
    await entityStore.updateProfile({ ...profilePayload(values), marketingConsent: values.marketingConsent === true })
    toast.success(t('entities.profile.saved'))
    onSaved()
  }

  const form = useValidatedForm({
    schema: computed(() => ownProfileSchema(t)),
    initialValues,
    action: 'entities.updateMine.submit',
    submit: (values) => (saveConfirmed.value ? save(values) : review(values)),
  })

  function confirmSave() {
    saveConfirmed.value = true
    return form.onSubmit().finally(() => {
      saveConfirmed.value = false
    })
  }

  function backToEdit() {
    step.value = EDITOR_STEPS.EDIT
  }

  function regionIdOf(side) {
    return side === 'before' ? initialValues.regionId : form.values.regionId
  }

  return { form, step, changes, confirmSave, backToEdit, regionIdOf }
}
