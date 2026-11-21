import { computed, ref } from 'vue'
import { useForm } from 'vee-validate'
import { useI18n } from 'vue-i18n'
import { useToast } from '@/composables/useToast'
import { useEntityStore } from '@/stores/entity'
import { logger } from '@/utils/logger'
import { payloadShape } from '@/utils/payloadShape'
import { riyadhDateKey } from '@/utils/datetime'
import { readPath } from '@/utils/entityProfile/paths'
import {
  editableFieldsOf,
  sectionFormValues,
  sectionPayload,
} from '@/utils/entityProfile/formValues'
import { profileSectionSchema } from '@/utils/entityProfile/schema'
import { PROFILE_SAVE_STATES as S, PROFILE_STALE_CODE } from '@/constants/entityProfile'

const VALIDATION_STATUS = 422
const CONFLICT_STATUS = 409
const FORBIDDEN_STATUS = 403

function sameValue(left, right) {
  return JSON.stringify(left ?? '') === JSON.stringify(right ?? '')
}

function copyOf(values) {
  return JSON.parse(JSON.stringify(values))
}

function firstMessages(fieldErrors) {
  return Object.fromEntries(
    Object.entries(fieldErrors).map(([field, messages]) => [
      field,
      Array.isArray(messages) ? messages[0] : messages,
    ]),
  )
}

export function useProfileSectionForm(section, { onSaved }) {
  const { t } = useI18n()
  const toast = useToast()
  const store = useEntityStore()
  const names = section.value.fields.map((field) => field.name)
  const editable = editableFieldsOf(names)
  const required = section.value.fields.filter((field) => field.required).map((field) => field.name)
  const action = `entities.profile.${section.value.key}.save`
  const saved = ref(sectionFormValues(store.profile, names))
  const status = ref(S.IDLE)

  const form = useForm({
    validationSchema: computed(() =>
      profileSectionSchema(t, { fields: names, required, today: riyadhDateKey(new Date()) }),
    ),
    initialValues: copyOf(saved.value),
  })

  const isDirty = computed(() =>
    editable.some((name) => !sameValue(readPath(form.values, name), readPath(saved.value, name))),
  )
  const errorFields = computed(() => editable.filter((name) => form.errors.value[name]))

  async function reloadFresh() {
    await store.loadProfile({ force: true })
    saved.value = sectionFormValues(store.profile, names)
    form.resetForm({ values: copyOf(saved.value) })
  }

  async function onFailure(error) {
    const httpStatus = error?.response?.status
    if (httpStatus === CONFLICT_STATUS && error?.normalized?.code === PROFILE_STALE_CODE) {
      status.value = S.STALE
      await reloadFresh().catch((failure) => {
        logger.error(failure, { action })
        toast.error(t('errors.unexpected'))
      })
      return
    }
    if (httpStatus === FORBIDDEN_STATUS) {
      status.value = S.IDLE
      toast.error(error?.normalized?.message || t('errors.unexpected'))
      await store.loadProfile({ force: true }).catch((failure) => logger.error(failure, { action }))
      return
    }
    if (httpStatus === VALIDATION_STATUS) {
      form.setErrors(firstMessages(error.normalized?.fieldErrors ?? {}))
      status.value = S.ERROR
      return
    }
    logger.error(error, { action })
    status.value = S.IDLE
    toast.error(error?.normalized?.message || t('errors.unexpected'))
  }

  const submit = form.handleSubmit(
    async (values) => {
      if (status.value === S.SAVING) return
      const payload = sectionPayload(values, names, section.value.revision)
      status.value = S.SAVING
      logger.breadcrumb('form', action, { fields: payloadShape(payload) })
      try {
        await store.saveSection(section.value.key, payload)
        status.value = S.SAVED
        onSaved()
      } catch (error) {
        await onFailure(error)
      }
    },
    () => {
      status.value = S.ERROR
    },
  )

  return { form, status, isDirty, errorFields, submit }
}
