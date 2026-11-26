import { ref } from 'vue'
import { useForm } from 'vee-validate'
import { i18n } from '@/i18n'
import { logger } from '@/utils/logger'
import { toast } from '@/composables/useToast'
import { payloadShape } from '@/utils/payloadShape'

const CONFLICT = 409

// A rejection about the state of the record (409) or one that names no field is shown for the whole form.
function formLevelMessage(normalized, fieldErrors) {
  const isFormLevel = normalized?.status === CONFLICT || !Object.keys(fieldErrors).length
  return isFormLevel ? normalized?.message || i18n.global.t('errors.unexpected') : ''
}

export function useValidatedForm({
  schema,
  initialValues = {},
  action,
  submit,
  keepValuesOnUnmount = false,
}) {
  const form = useForm({ validationSchema: schema, initialValues, keepValuesOnUnmount })
  const submitting = ref(false)
  const serverErrors = ref({})
  const formError = ref('')

  function showRejection(normalized) {
    const fieldErrors = normalized?.fieldErrors ?? {}
    serverErrors.value = Object.fromEntries(
      Object.entries(fieldErrors).map(([field, messages]) => [
        field,
        Array.isArray(messages) ? messages[0] : messages,
      ]),
    )
    if (Object.keys(fieldErrors).length) form.setErrors(serverErrors.value)
    formError.value = formLevelMessage(normalized, fieldErrors)
    toast.error(normalized?.message || i18n.global.t('errors.unexpected'))
  }

  const handleValidSubmit = form.handleSubmit(async (values) => {
    if (submitting.value) return
    submitting.value = true
    serverErrors.value = {}
    formError.value = ''
    logger.breadcrumb('form', action, { fields: payloadShape(values) })
    try {
      await submit(values)
    } catch (error) {
      logger.error(error, { action, fields: payloadShape(values) })
      showRejection(error?.normalized)
    } finally {
      submitting.value = false
    }
  })

  // vee-validate marks the form as submitting before its asynchronous validation. A second submit during that
  // validation would otherwise run too, after the first one has already moved the screen on.
  function onSubmit(event) {
    if (!form.isSubmitting.value) return handleValidSubmit(event)
    if (event instanceof Event) event.preventDefault()
    return Promise.resolve()
  }

  function resetForm(...args) {
    serverErrors.value = {}
    formError.value = ''
    return form.resetForm(...args)
  }

  return { ...form, resetForm, submitting, serverErrors, formError, onSubmit }
}
