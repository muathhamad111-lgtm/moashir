import { ref } from 'vue'
import { useForm } from 'vee-validate'
import { i18n } from '@/i18n'
import { logger } from '@/utils/logger'
import { toast } from '@/composables/useToast'
import { payloadShape } from '@/utils/payloadShape'

export function useValidatedForm({
  schema,
  initialValues = {},
  action,
  submit,
  keepValuesOnUnmount = false,
}) {
  const form = useForm({ validationSchema: schema, initialValues, keepValuesOnUnmount })
  const submitting = ref(false)

  const onSubmit = form.handleSubmit(async (values) => {
    if (submitting.value) return
    submitting.value = true
    logger.breadcrumb('form', action, { fields: payloadShape(values) })
    try {
      await submit(values)
    } catch (error) {
      logger.error(error, { action, fields: payloadShape(values) })
      const fieldErrors = error?.normalized?.fieldErrors ?? {}
      if (Object.keys(fieldErrors).length) {
        form.setErrors(
          Object.fromEntries(
            Object.entries(fieldErrors).map(([field, messages]) => [
              field,
              Array.isArray(messages) ? messages[0] : messages,
            ]),
          ),
        )
      }
      toast.error(error?.normalized?.message || i18n.global.t('errors.unexpected'))
    } finally {
      submitting.value = false
    }
  })

  return { ...form, submitting, onSubmit }
}
